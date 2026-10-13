# SLY Custom — ChangeLog

本文件仅记录 SLY Custom 模块自身的增删改查。**严禁**修改 Dolibarr 上游目录下的 `ChangeLog`。

## 2.3.6

### 新增

- **Wise 核对队列的「银行对账」**（`WiseIncomingPayment::findBankMatches` / `findPaiementMatches` / `markReconciled` + 列表 chip + 详情对账面板）。用于"用户已经在银行或发票付款 UI 入账，但 Wise 入队行还显示 NEW/ENRICHED"的场景——以前只能「忽略」或「重新分摊入账」把行消化掉，二者都会再产生会计数据。改为**只读对账**：
  1. **列表 chip**（操作列左侧）：把 200 行的 Wise 入队按（最早发生时间 − 3 天 .. 最晚 + 3 天）扩成一条批 SQL 一次拉完（`ABS(b.amount) ± 30 USD` 容纳手续费、`UPPER(currency_code)` 一致），循环内零查询。**单条 best 匹配时直接在行内给出 `Bank #ID / $X / 日期` 可见文字 + `[✓ Mark]` 内联按钮**（POST + CSRF，写完跳回列表）；多条候选时显示前两条 ID 摘要并链到详情页（详情页有完整候选表 + 表单可挑）；无候选显示「—」；`fk_paiement` 已设显示绿章 ✓ Linked。这是为了让用户能"看见具体哪行银行流水、确认、标记"——只显示「⚠ Bank only」一个笼统章实际价值很小。
  2. **详情对账面板**：分别列出 `llx_bank` 与 `llx_paiement` 候选（最多 5 条，含差额与日期），每条都链到原生 bank/付款卡片；表单可挑（`bank_id` 可空、`paiement_id` 可空）→ `mark_reconciled` POST + CSRF → 行置 `IGNORED` + `note_private` 追加审计备注「auto-reconciled by user #X on YYYY-MM-DD HH:MM:SS — paiement #N, bank #N」。**不**对 `llx_bank` / `llx_paiement` / `llx_facture` 做任何写入，符合"银行→付款→发票的链路单向"的 Dolibarr 不变式。`return=list|detail` 参数决定 PRG 跳回列表还是详情。
  3. **设计依据**：用户明确"不要直接写入bank，因为dolibarr不允许从bank倒过来补充payment链接"，且 6.11 USD 差额形态在用户截图里出现 3/4 次（银行手续费被叠进 credit 金额），±30 USD 容差覆盖。**SQL 坑**：`llx_bank.datev` 是 `DATE` 列、`llx_paiement.datep` 是 `DATETIME` 列；`BETWEEN <unix_int>` 会被 MySQL 当 `YYYYMMDD` 解析导致零命中，必须分别包 `DATE(FROM_UNIXTIME(...))` / `FROM_UNIXTIME(...)`。
  4. **权限**：`mark_reconciled` 与现有 `record` / `save_draft` / `auto_record` 一致，校验 `wise->write`；`note_private` 已有，列以 `show COLUMNS` 守卫后 `ALTER TABLE ADD COLUMN` 幂等补齐。
- Wise 入账队列新增 **payment 草稿**（状态 `PREPARED`）：核对页可先把勾选的发票分摊 **保存为草稿**，草稿存在 `llx_slycustom_wise_incoming.match_data.allocation`，**不产生任何会计数据**（不建 `Paiement`、不写银行流水、不关票），留在队列里等复核；「确认入账」才真正入账，表单未勾选时按已存草稿确认；「丢弃草稿」把行退回补明细后的状态（`IGNORED` 的行保持 `IGNORED`）。保存/丢弃/入账共用 `wise->write` 权限。
- `WiseIncomingPayment::validateAllocations()`：把 `recordPayment()` 里内联的发票批量校验抽成独立方法，`saveDraft()` 与 `recordPayment()` 共用——能存下的草稿一定能入账。发票仍是一次性批量加载，循环内零查询。
- 队列状态筛选、设置页队列计数新增「待确认草稿」。

### 修复

- **Remain-to-pay / 候选金额全线改为发票币种口径**。此前 5 处（自动候选 SO 链、金额窗兜底、分摊超额校验、手动选票列表、金额接近度排序）一律用 `f.total_ttc - SUM(pf.amount)`——对多币种发票这两个列存的是**本位币（SGD）折算值**，发票面额在 `multicurrency_total_ttc`、外币付款在 `pf.multicurrency_amount`，混用后每张外币（USD）发票的总额/剩余都按汇率错一遍（显示成"USD 63,051 / 剩余 8 万 SGD 数值"这类乱象，金额窗兜底匹配也因此永远命中不了）。统一改为 `CASE WHEN multicurrency 发票 THEN multicurrency_total_ttc / multicurrency_amount ELSE total_ttc / amount END`；自动入账的唯一匹配校验与 FIFO 预填随之受益。`recordPayment` 的外币 `Paiement`（`multicurrency_amounts`）原本即正确，未动。
- **核对详情页"草稿"视图可编辑，提交后跳到原生付款卡**。`PREPARED` 行之前只显示一行草稿汇总（总额/已分摊/未分摊），下方的分摊表单只在 `!empty($candidates)` 时才渲染——结果草稿里那张手动选的发票往往不在候选里，「保存草稿 / 确认入账」按钮根本不渲染，唯一出口是「丢弃草稿」（更糟：丢弃后状态被 `discardDraft()` 退回 `ENRICHED/NEW`，那张发票的入账意图也跟着丢）。修复：
  1. 详情页新增"当前草稿分摊"独立节（`WiseDraftSectionTitle`），先按草稿 `items[].facid` 批量加载发票（`total_cur`/`paid`/`paye`/`fk_statut`），整段渲染在候选表**之前**的同一个 POST 表单内——复选框默认勾上、金额预填、发票已收（`paye=1` 或 `fk_statut` 越界）时只读展示并附 `WiseDraftRowMissing` 警告、刷选票按钮可重选；`wiseParseAllocations()` 不动，校验路径不变。
  2. `showManual` 默认在 `PREPARED + 有草稿` 时为真（避免草稿那张票被收起的手动选票面板挡掉）。
  3. `record` 与 `auto_record` PRG 成功时改跳 `compta/paiement/card.php?id={fk_paiement}`——Doli 原生付款卡，含关联发票/银行/客户/分录/附言全套；失败仍回详情页（错误信息保留在 PRG flash）。
- `Wise_Incoming` / `Wise_Payment`：**4 处 `date_creation` 直接写入 `escape(dol_now())`（Unix 时间戳整数）**，在 MySQL/MariaDB 严格模式（`STRICT_TRANS_TABLES`，cPanel 默认）下报 `Incorrect datetime value`，INSERT 全部静默失败——webhook 收到投递、IP 白名单放行、dump 正常落盘，但 `slycustom_wise_event` 永远 0 行，`createFromWebhook` 返回错误令 webhook 回 500，Wise 按指数退避无限重试；且 `dol_syslog` 在无 syslog 主机上是 no-op，error_log 全程空白，与"从未收到推送"无法区分。全部改为 Dolibarr 标准的 `$db->idate(dol_now())`（event 入库、incoming 入队、statement 兜底、outgoing transfer 映射四处）。
- `Wise_Incoming`：**对账单补明细改用收款行自己的 profile**（新增 `llx_slycustom_wise_incoming.wise_profile_id`）。此前入队只存 `wise_balance_id`，补明细却拿实体的 `WISE_PROFILE_ID` 去调 `/v1/profiles/{id}/balance-statements/{balanceId}/...`；而 balance id 只在其所属 profile 下有效，一个 Wise 账号可以同时持有多套 profile（按币种/按地区），推送报文里的 `profile_id` 每笔都可能不同。两者不一致时该请求必然查不到流水，补明细一直停在 `NEW`，参考号/对方/手续费永远补不上——表现和"Wise 没推送"完全一样。webhook 入队写报文的 `resource.profile_id`，cron 兜底入队写它读取所用的 profile，`enrichRow()` 优先用行内 profile，为空才回退到配置值；`fetchRow()` 一并返回该列。列以`SHOW COLUMNS` 守卫后 `ALTER TABLE ADD COLUMN` 幂等补齐（`CREATE TABLE IF NOT EXISTS` 不会改动已存在的表）。
- `webhook/wise.php`：**被 IP 白名单拒绝的投递现在也会留下证据**。此前白名单在写 dump 之前就 `403 exit`，一次被拒的推送在本地不留任何痕迹，与"Wise 从未推送"完全无法区分——2026-10-10 一笔 16,166.59 USD 就是这样消失的。改为先写 dump 再响应，dump 落到 `DOL_DATA_ROOT/wise_webhook/rejected/`。dump 分 `accepted/` 与 `rejected/` 两个桶各自轮转50 个：白名单武装后，匿名扫描者只能冲掉自己那桶的噪音，不会把真实的 Wise 证据轮转掉。
- `webhook/wise.php`：白名单从 4 个 `/32` 单 IP 放宽到实际在用的 AWS `/16`（`18.184.0.0/16`、`18.185.0.0/16`、`18.188.0.0/16`、`18.197.0.0/16`）。依据是 dump 证据：2026-10-10 的投递来自 `18.184.162.75`，2026-09-16 来自 `18.184.251.153`——Wise 在整个 `/16` 内轮换出口，钉死单机会静默拒掉真实投递。文档化的 `45.129.54.0/24`、`45.129.55.0/24` 与 sandbox 地址保持不变。此为**过渡措施**：攻击者需要在这些 AWS 段内自建基础设施；验签公钥在业务 token 上取不到（订阅 API 返回 `403 unauthorized`），RSA 验签这条路不可用，白名单是唯一通道。
- `webhook/wise.php`：`$bootstrap` / `$keyFile` 的解析提前到 IP 白名单之前，使"被拒投递"这一分支能以未验签（截断）形式写 dump；原先 3b 里重复赋值了两遍。同时删掉步骤 4 里那句 `body over 64 KB, diagnostic dump skipped` 的错误日志——该分支在 bootstrap 下永远走不到，真实语义是"已在 3b 写过截断 dump"，日志文本与事实相反。

- `webhook/wise.php`：bootstrap 模式（`DOL_DATA_ROOT/wise_webhook/verification_key.pem` 不存在）在开启 `WISE_WEBHOOK_IPCHECK` 时不再丢弃投递。此前无论 IP 白名单是否开启都只回 `200 {"note":"bootstrap..."}` 就 `exit`，生产环境下表现为"从未有过任何 WISE 记录"——事件表与诊断 dump 同时为空，与"Wise 从未推送"无法区分。现在：`WISE_WEBHOOK_IPCHECK=1` 且来源 IP 在 Wise 出口网段白名单内时，投递照常入库（系统日志以 `UNVERIFIED delivery accepted` 告警）；两者都未启用时维持原样丢弃。
- `webhook/wise.php`：抽出 `wiseWriteDump()`，被丢弃/被拒的投递同样写诊断 JSON（未验签的只保留 2 KB 头部），目录按 50 个文件轮转。此前 bootstrap 丢弃发生在写 dump 之前，"零记录"完全没有现场证据。
- `wise/reconcile.php`：所有操作按钮由 `<input type="submit" value="翻译标签">` 改为 `<button name="step" value="固定 token">`。按钮 label 走 `GETPOST('action','aZ09')` 之类的过滤，中文标签会被过滤成空串导致按钮点了没反应；同时分摊表单改为 `action=allocate` + `step=record|save_draft`，一个表单两个按钮。
- `WiseIncomingPayment::recordPayment()` 与 `WisePayment::recordSupplierPayment()`：付款备注字段由 `note_public` 改为 `note_private`。核心 `Paiement::create()` 落库取的是 `note_private ?: note`、`PaiementFourn::create()` 只取 `note_private`，付款卡片显示的也是 `note_private`——原先写的 `note_public` 从不落库，导致"Wise 转账号 / 收款客户名"这段溯源信息在付款记录里完全看不到。
- `WiseIncomingPayment::enrichRow()`：重新补明细不再覆盖已存的草稿，也不再把 `PREPARED` 状态打回 `ENRICHED`。
- `langs/{en_US,zh_CN}/slycustom.lang`：`WISE_SIGNATURE_KEY_MISSING` 原文写着 "unsigned deliveries are currently accepted with a warning"，与代码行为相反，误导管理员以为链路已通；改为说明"不启用 IP 白名单则不入库"。`WISE_IPCHECK_ACTIVE` / `WISE_WEBHOOK_IPCHECKTooltip` 同步补上"未验签时只有开启此项才会入库"以及"站点前有反向代理/CDN 时全部推送会被拒（只认 `REMOTE_ADDR`）"。
- **SLY ALL-in-One 与导出详情页的"错链"修复**（`custom/slycustom/exports/export_all.php`）。5 个数据集（SO/SO Invoice/Shipment/PO/PO Invoice Details）的有误对象链接此前都跳到 `list.php?search_ref=...`：multicompany 下 ref 可跨实体重名，list 搜索结果不唯一时用户找不到具体对象。`$pushRef` 改为 `ref => rowid` 映射，`renderRow` 优先渲染 `card.php?id=<rowid>`，无 id 时回退到 list 搜索链接。同步清理：`slyAllInOneValidate()` 旧实现（-420 行）、`getSlyExportDatasets__legacy()`（-495 行）、`tools.service.php` 三个 pass-through 包装（-27 行）、`@deprecated slyExportsPrintDualTabNavigation()`（-8 行）共 -997/+73 = -924 行死代码。

### 新增（诊断）

- **核对队列「自动入账」一键操作**（`WiseIncomingPayment::autoRecord()` + 列表行按钮，`NEW`/`ENRICHED` 状态、`wise->write` 权限显示）。先从对账单 API 补参考号，然后**仅在匹配无歧义时**自动建 `Paiement`：参考号命中订单链（`mode=so`，金额+日期窗的兜底匹配视为低置信、永不自动）、恰好一张未收发票、币种一致、收款额 ≤ 发票余额。任何需要猜的条件（无参考号 -21、未命中订单 -22、多张候选 -23、币种不符 -24、超额 -25、已处理/有草稿 -20）一律拒绝并把行留在队列走人工分摊。按钮带确认弹窗与 CSRF，操作后 PRG。
- **`wise/dumps.php` — Webhook 投递存档的手动重放/清理页**（核对页标题栏入口）。列出 `accepted/`、`rejected/` 两桶 dump（当前实体、最新在前），显示投递时间、事件类型、金额币种、投递 ID、来源 IP、bootstrap 标记与入库状态；「已入库」（`payload_md5` 命中，整页一次批量 IN 查询，循环内零 SQL）、「未入库」可重放、「已截断」（bootstrap 2 KB 头部切片）仅可删。重放走 `WiseIncomingPayment::createFromWebhook` 原入口，md5 去重天然幂等——webhook 短暂故障（如入库 bug）期间丢失的投递由运维手动补救，不再依赖 cron 对账单兜底，也不必等 Wise 重试窗口。删除仅删存档文件、不动已入库事件。文件名经 `dumpPath()` 严格正则（`Ymd-His-eN-8hex.json`）防路径穿越；查看需 `wise->read`，重放/删除需 `wise->write`，POST + CSRF，操作后 PRG 重定向。
- **核对队列列表新增可见操作列**：按状态显示「处理 / 处理草稿 / 查看」按钮（此前唯一的入口是第一列裸数字链接，用户无从发现详情页）。
- **时间显示统一为用户时区**：核对列表与详情页此前直接打印 UTC 字符串（`2026-10-13 02:31 UTC`），与系统界面（新加坡 UTC+8）相差 8 小时，曾被误判为"时间不对"；改为 `sqlUtcToTimestamp()` + `dol_print_date(..., 'tzuserrel')`，dumps 页同样显式 `tzuserrel`。数据库存储仍为 UTC 不变。
- `admin/setup.php` 诊断面板显示**被白名单拒绝的投递数**（`WiseRejectedCount`），并按 `accepted/`、`rejected/` 两个子目录分别统计 dump（旧的平铺文件仍计入）。这是"开启白名单后是否把真实推送挡在门外"的唯一可见信号。
- `patches/sly24.0-wise-webhook-unverified-ingest.patch`：本次 webhook 修复的记录补丁（`webhook/wise.php`、`admin/setup.php`、`Wise_Incoming.class.php`、`modSlyCustom.class.php`、`sql/llx_slycustom_wise.sql`、两个语言文件）。

- `wise/reconcile.php`：收款详情页新增 **手动指定发票** 选择器。匹配引擎认不出一笔收款时，候选表为空、`!empty($candidates)` 的门导致「保存草稿 / 确认入账」按钮根本不渲染，唯一出口是「忽略」——而忽略只改状态、不产生任何会计数据，等于把这笔钱静默丢掉。后端 `validateAllocations()` 本就独立校验实体/`paye`/`fk_statut`/`type`/币种/剩余/超额，与候选集无关，所以这里只放宽界面：手动选的发票与自动候选走**完全相同**的解析、校验与草稿存储。
  - 筛选框接受 **SO 号或发票号**，且 SO 号带分隔符无关匹配（`SO2501-0012` / `SO25010012` / `SO2501_0012` 互通），与引擎 `extractSoRefs()` 的容错一致——客户在付款信息里给的是 SO 号，不是发票号。
  - 列表每行显示其**来源 SO 号**，方便肉眼核对。可按客户名二次筛选，默认按发票日期倒序，最多列 200 张并在超出时明确提示（多取 1 行判断是否被截断）。
  - 分摊金额默认**留空**：两张以上发票同时列出时自动预填等于把一笔款静默摊到没人看过的发票上。仅当筛选把列表收窄到**唯一一张**发票时，才预填 `min(该票剩余, 收款额)`。
  - 候选非空时选择器默认收起，页面上给一个「改为手动指定发票」入口，避免把正常路径的页面撑长。

- `admin/setup.php` Wise 页新增 **webhook 诊断面板**（仅管理员可见，`action=wise_diag` 带 CSRF）：直接显示本实体下的事件入库数与最近入库时间、明细补全计划任务的状态/已运行次数/上次运行时间、诊断 dump 目录的文件数与最新文件名、`dolibarr_wise_webhook.log` 是否可读（按 `mod_syslog_file::getFilename()` 的规则拼路径）。
- 同页新增 **「运行 webhook 自检」**：服务器向自身 `webhook/wise.php` POST 一个非 Wise 的假 body、无签名头，只会得到 403（白名单已武装）或 bootstrap 确认回应 + 一份诊断 dump，**不可能**往付款表写任何东西；按 200/403/404/5xx/超时分别给出对应的排查方向。用于在无 SSH 的 cPanel 上确认接收端文件确实在盘上、URL 外部可达、PHP 能正常执行。
- 四步浏览器排查指引（服务器自测 → 本机浏览器测外部可达 → 看 dump 目录 → 去 Wise 后台重存订阅触发测试通知）。

### 文档

- 新建 `ChangeLog.md`（此前 README 引用了它但文件并不存在）。
- `README.md`：Wise 章节补写验签状态与入库条件的关系、payment 草稿流程；版本条目对齐到 2.3.6，模块目标版本改为 24.0。

## 2.3.5

- shipment extrafield 的日期类字段默认值取当天。

## 2.1.0

- 新增 Wise 付款准备（flow A）：供应商发票卡片"通过 Wise 付款"、未注资转账（幂等 uuid）、参考号取供应商自己的订单号、`outgoing_payment_sent` 后自动记供应商付款。

## 2.0.0

- Wise 收款集成端到端（flow B）：webhook 收款入队、对账单 API 回补参考号、SO 编号匹配引擎、银行账户映射、人工核对页、对账单兜底 cron。