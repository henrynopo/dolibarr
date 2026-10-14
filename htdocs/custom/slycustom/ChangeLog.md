# SLY Custom — ChangeLog

本文件仅记录 SLY Custom 模块自身的增删改查。**严禁**修改 Dolibarr 上游目录下的 `ChangeLog`。

## 2.5.1

### 收件箱列表加 Match chip（一键跳银行行卡片）

2.5.0 之后用户问："这两条消息居然没有自动核对 bank entry？" 答：按用户原话"手工对账"，**不**自动写 `llx_paiement` / `llx_bank`（保留 2.4.0 硬约束"不要直接写入 bank"）。但**显示**候选 bank 行可以帮用户快速决定"这条通知是否已经在银行入账"——判完就批量删除孤儿通知。本轮加：

**`Wise_Incoming::findBankMatchesBatch()`**（[class/Wise_Incoming.class.php](custom/slycustom/class/Wise_Incoming.class.php)）：批量版匹配。输入 listDumps 行的 map，按 `(entity, currency)` 分桶后**单次 SELECT 拉所有候选 bank 行**（`LIMIT 5000` 兜底），PHP 端按每行的 from/to + amount±tol 在内存过滤 + diff 排序，限 5 候选/行。**严格遵守"禁止循环内查询"硬约束**——200 行 reconcile 列表只跑 1~3 个 SQL（按 entity+currency 切）。

**`reconcile.php` 列表加列**（[wise/reconcile.php](custom/slycustom/wise/reconcile.php)）：在 Status 列后、Actions 列前新增 **Match** 列。渲染逻辑：
- 找到候选 → 蓝色 chip：`#行号 · 日期 · 差额`（链 `/compta/bank/line.php?rowid=N`），若候选 >1 附 `+N` 提示；
- 状态 RECONCILED 且已 `fk_paiement` → 绿 chip：`Paiement #N`（链 `/compta/paiement/card.php?id=N`）；
- 否则灰条 `-`。

表头 colspan 8→9；空态行同步。**不**写任何 DB / sidecar / `llx_paiement` / `llx_bank`——chip 是只读 affordance，落地决策由 detail 页的 mark_reconciled form 完成（与 2.5.0 一致）。

性能：200 行 × 1~3 桶 = 1~3 SQL/页，相比原方案 200 SQL/页降低 60~200×。`git apply --check` 通过（16820 行 patch）。

## 2.5.0

### 收件箱全文件化 + Email Collector

2.4.x 把 webhook 角色降级为"通知用户有新到账"，但每条投递仍要写 `llx_slycustom_wise_incoming` + `llx_slycustom_wise_event` 两张表——表里只放每条 .json 文件已经持有的元数据加一个 `status` 列。这是**冗余的持久化层**。本轮把两张表彻底删掉，reconcile 改为纯文件视图（per-dump sidecar 写状态）；同时新增 **email collector 模块**（专用邮箱 + IMAP cron + 模板解析），把到账邮件也落到同一桶，统一人工 reconcile 流程。

**`reconcile.php` 全重写**（[wise/reconcile.php](custom/slycustom/wise/reconcile.php)）：数据源从 `SELECT FROM incoming` 改为 `scandir(wise_webhook/accepted/*.json)` + 读 `<basename>.meta.json` sidecar。列表行：Source / Date / Amount / Ref / Counterparty / Status（IGNORED 灰条）/ Actions。状态过滤 4 选 1：NEW / ENRICHED / RECONCILED / IGNORED；来源过滤 2 选 1：webhook / email。详情页（`?file=<basename>`）渲染主 dump body（pretty JSON）+ sidecar + bank/paiement 候选表（沿用 `findBankMatches` / `findPaiementMatches`，匹配键改用 sidecar 的 amount/currency/date 而不是 DB row）。4 个 action 全改 sidecar：
- `ignore`：sidecar 写 `status: IGNORED`（无 DB 写）
- `enrich`：仅 webhook 投递，调 `Wise_API` 拉对账单更新 sidecar `enriched.*`（邮件已经模板抽字段，不需要 enrich）
- `mark_reconciled`：sidecar 写 `status: RECONCILED, fk_paiement: N, fk_bank: M`（**不**写 `llx_paiement` / `llx_bank`，与 2.4.0 硬约束一致）
- `delete_incoming` / `bulk_delete_incoming`：unlink 主 dump + sidecar（仅终态行可删）

**`Wise_Incoming.class.php` 大瘦身**（[class/Wise_Incoming.class.php](custom/slycustom/class/Wise_Incoming.class.php)）：从 1402 行到 570 行。删：`createFromWebhook` / `enrichPending` / `enrichRow` / `syncFromStatements` / `enrichPendingCron` / `fetchRow` / `deleteIncoming` / `setNote` / `quoteOrNull` / `incomingRowExists` 等 12 个方法。保留：4 个 `STATUS_*` 常量（`STATUS_RECONCILED` 是新名字，`STATUS_RECORDED` 保留为 alias = 'RECONCILED'）+ `ZERO_UUID` / `DEFAULT_SO_PATTERN` + 工具方法（`isIncomingEnabled` / `isOutgoingEnabled` / `getApiTokenForEntity` / `getProfileIdForEntity` / `isoToSqlUtc` / `sqlUtcToTimestamp` / `getSoPattern` / `deriveSoPatternFromNumbering` / `maskToPattern` / `extractSoRefs` / `dumpPath` / `listDumps` / `deleteDump` / `findBankMatches` / `findPaiementMatches`）+ `handleTransferStateChange`（outgoing flow A 仍由 transfers#state-change 驱动）+ 新增 `sidecarPath` / `readSidecar` / `writeSidecar` / `enrichDumpFromStatement` / `ignoreDump` / `markReconciledDump` 5 个 file-based 写方法。

**`webhook/wise.php` 改**（[webhook/wise.php](custom/slycustom/webhook/wise.php)）：去掉 `createFromWebhook` 调用（删表了）。流程：`wiseWriteDump(..., 'accepted')` 写主 dump → 立即写 sidecar `status: NEW, source: webhook`。如果 sidecar 已存在且 status != NEW，**保留原值不覆盖**（re-delivery 不会冲掉用户已 ignore/reconcile 的决策）。`transfers#state-change` 事件直调 `Wise_Incoming::handleTransferStateChange()`，不再绕 event 表。响应 `array('received' => true, 'event' => $eventTypeForLog, 'transfer_row' => $handledTransferRow)`。

**`dumps.php` 简化**（[wise/dumps.php](custom/slycustom/wise/dumps.php)）：accepted 桶整个迁到 reconcile 视图。dumps 页退化为 **rejected 桶诊断**：顶部"查看已入账通知"链 reconcile + rejected 桶行数；列表只显示 `rejected/*.json` + 单条/bulk delete。

**Email collector 模块**（新增）：
- [class/slyemailcollector/EmailCollector.class.php](custom/slycustom/class/slyemailcollector/EmailCollector.class.php) — 收件、解析、落盘
- [class/slyemailcollector/EmailTemplate.class.php](custom/slycustom/class/slyemailcollector/EmailTemplate.class.php) — 模板接口
- [class/slyemailcollector/TemplateWise.php](custom/slycustom/class/slyemailcollector/TemplateWise.php) — Wise 邮件模板（MVP，匹配 "You received X.XX USD from NAME" subject + body 三段字段提取）
- [cron/email_collector.php](custom/slycustom/cron/email_collector.php) — 5 分钟 IMAP cron
- [wise/email_collector.php](custom/slycustom/wise/email_collector.php) — 手动拉取页（运维用）

IMAP 配置走 `dolibarr_get_const('SLYEMAIL_IMAP_HOST')` / `_USER` / `_PASS` / `_PORT` / `_FOLDER` / `SLYEMAIL_COLLECTOR_ENABLED`。每封邮件拉下来后：原始 .eml 落 `wise_webhook/email/<msgid>.eml`；模板命中则主 dump 写 `accepted/` + sidecar `status: ENRICHED, source: email`；模板未命中则主 dump 写 `rejected/` + sidecar `note: 'no template matched'`。所有邮件都标 IMAP Seen 防重入。

**`modSlyCustom.class.php` 改**（[core/modules/modSlyCustom.class.php](custom/slycustom/core/modules/modSlyCustom.class.php)）：版本 `2.4.2` → `2.5.0`；删 2 个 `CREATE TABLE`（incoming/event）+ 删 `SHOW COLUMNS / ALTER TABLE` 升级 guard（`wise_profile_id` 列在 2.5.0 不再需要补）；cron entry 1 从 `enrich` 改为 `email_collector`（每 5 分钟）；新增 rights 4 (`email:read`) + 5 (`email:write`)，admin 默认获得。

**`sql/llx_slycustom_wise.sql` 清空**（[sql/llx_slycustom_wise.sql](custom/slycustom/sql/llx_slycustom_wise.sql)）：只留 `llx_slycustom_wise_transfer`（outgoing flow A 用）。`llx_slycustom_wise_incoming` / `llx_slycustom_wise_event` 的 `CREATE TABLE` 全部删除（部署前手动 DROP）。

**`admin/setup.php` 改**（[admin/setup.php](custom/slycustom/admin/setup.php)）：删 incoming queue `GROUP BY status` SQL（表都没了）+ 删 events-stored SQL；替换为 file-based inbox overview（`$wp->listDumps()` 扫 accepted/*.json，按 status 4 选 1 + source 2 选 1 分布）；enrich cron state widget 改为 email collector cron state（`label = 'Wise email collector pull'`）。

**lang 增改**：[langs/{en_US,zh_CN}/slycustom.lang](custom/slycustom/langs/) 新增 `WiseInboxTitle` / `WiseInboxCount` / `WiseSource*` / `WiseStatusReconciled` / `WiseMarkReconciled*` / `EmailCollector*` / `WISE_INBOX_*` / `WISE_EMAIL_CRON_*` 系列；`WiseStatusRecorded` 保留为 alias。

**部署注意**（生产 slyfood.com）：
- pre-deploy SQL：先 `CREATE TABLE llxsf_slycustom_wise_incoming_backup_2414 AS SELECT * FROM llxsf_slycustom_wise_incoming;` 备份，再 `DROP TABLE`（注意 `llxsf_` 前缀，不是 `llx_`）。
- 部署后老 `accepted/*.json` 文件无 sidecar → 视为 `status: NEW, source: webhook` → reconcile 列表正常显示；可手工 ignore 或 mark_reconciled 后写 sidecar。
- Email collector 需先在 admin/setup.php Email tab（或直接写 `llxsf_const`）配置 IMAP host/user/pass/port/folder，再启用 cron（默认 status=0）。

### UI 重命名 — Wise → 银行邮件通知 + 收件箱 UI 重整 + IMAP 表单

3 个用户反馈的修正一并交付：

1. **"这里完全混乱"** — `wise/reconcile.php` 顶部 7 个过滤 chip（4 状态 + 2 来源 + All）跨 2 行堆叠，重整为 1 行 `filterbar`：status chip + source chip 内联紧凑排列，配以显著 "配置邮件收集器" 按钮。状态徽章本地化（`BankEmailStatus*`，回退 `WiseStatus*`）。
2. **"在哪里设置email提取呢？"** — 之前 IMAP 配置只能走 `llxsf_const` 手动 SQL。2.5.0 在 `admin/setup.php?tab=wise` 增加 **Bank email collector** 配置表单：6 字段（启用 / 主机 / 端口 / 用户 / 密码 / 文件夹）+ 保存按钮 + 立即拉取一次。密码框永远不回显（POST 为空 = 保留旧值）。`update_bankemail` action 写 `llxsf_const`（entity=0，多公司共享），保存后 PRG 跳回 tab。
3. **"这个功能已经不能再叫做wise了"** — 用户可见文本全部从 "Wise" 改为 "银行邮件通知" / "Bank Email Notification"。范围**仅限 UI 字符串**（菜单项、页面标题、表单标签、状态徽章、cron 显示名），**不**动：类名（`WiseIncomingPayment` / `EmailCollector`）、文件路径（`wise/reconcile.php` / `wise/dumps.php`）、DB 常量（`WISE_API_TOKEN` / `SLYEMAIL_IMAP_HOST`）、cron 内部 classname 路径、log suffix。

**改动的文件**：
- [core/modules/modSlyCustom.class.php](custom/slycustom/core/modules/modSlyCustom.class.php) — cron label `'Wise email collector pull'` → `'Bank email collector pull'`；4 个 rights label 改 "Bank email notification inbox ..."；菜单 `titre` 改 `SLYBankEmailInbox`（lang key 指向新翻译）；leftmenu id 改 `sly_bankemail_inbox`。
- [admin/setup.php](custom/slycustom/admin/setup.php) — `tab=wise` 块加 `update_bankemail` action + IMAP 表单 + "立即拉取" 按钮；tab fallback 标签 "Wise" / "Wise 收款" → "Bank Email Notification" / "银行邮件通知"；cron 状态查询兼容新旧 label。
- [wise/reconcile.php](custom/slycustom/wise/reconcile.php) — UI 顶部重整（filterbar + "配置邮件收集器" 按钮 + 状态徽章本地化）；页面 id `page-wise_inbox` → `page-bankemail_inbox`。
- [wise/email_collector.php](custom/slycustom/wise/email_collector.php) — 页面标题 / 概览 / 状态字段全部改用 `BankEmailCollector*` lang key；页面 id 改 `page-bankemail_collector`。
- [wise/dumps.php](custom/slycustom/wise/dumps.php) — 页面 id 改 `page-bankemail_dumps`。
- [langs/en_US/slycustom.lang](custom/slycustom/langs/en_US/slycustom.lang) + [langs/zh_CN/slycustom.lang](custom/slycustom/langs/zh_CN/slycustom.lang) — 旧 `Wise*` 关键值改为 "Bank Email Notification" / "银行邮件通知"；新增 `BankEmail*` / `SLYBankEmail*` 系列 lang key（与旧 key 等价，新代码引用）；新增 18 个 `SLYBankEmailImap*` 表单字段 key（zh/en 各 18 个）。

**回退注意**：本子节纯 UI / lang 改动，不涉及 schema / SQL / 文件结构。`WiseIncomingPayment` / `EmailCollector` / `Wise_API` 等类名、cron classname 路径、DB 常量、log suffix、`accepted/` `rejected/` 目录结构均**不变**。已部署的 2.5.0 升级本子节不需要 SQL 操作。

---

## 2.4.2

### 核对页 + 投递存档页：多选批量删除

2.4.1 加了"逐行删除"对已 reconcile 通知的处理，但每行一次"勾选按钮 → confirm → POST"在 50-200 条终态行的回归期（用户已明确表示"没必要保留"）依然很重；同样地，`dumps.php` 的轮转噪音（每天几十条 accepted / rejected 投递）也只能一条条删。本轮把"逐行"升级为"多选 + 一次提交"，保留全选框 + 每行 checkbox 模式，跟 Dolibarr 原生 list 风格一致。

- **`reconcile.php` 加 `bulk_delete_incoming` 写动作**（[reconcile.php:155](custom/slycustom/wise/reconcile.php#L155) 权限闸 + [reconcile.php:158](custom/slycustom/wise/reconcile.php#L158) handler）。校验：CSRF token + 每行 entity 匹配 + 状态必须是 `STATUS_RECORDED` 或 `STATUS_IGNORED`（live 状态 `NEW` / `ENRICHED` 静默跳过、不报错——避免"全选 + 提交"被一条 live 行阻断）。handler 对每个 rowid 调 `Wise_Incoming::deleteIncoming()`，最后按结果聚合成三种提示：纯成功、含 dump 清理的纯成功、部分失败/跳过。
- **reconcile 列表行加 checkbox 列 + header 全选框**（[reconcile.php:466-473](custom/slycustom/wise/reconcile.php#L466)）。把整个 list `<table>` 包进一个 `<form method=POST action=...>`，header 的 `<input type=checkbox id=incoming-select-all>` 用 inline JS 绑 `change` 事件做全选；每行的 `name="rowids[]" value=<rowid>` 是批量 POST 的语义。
- **reconcile 列表提交行**（[reconcile.php:560-563](custom/slycustom/wise/reconcile.php#L560)）：表格下方一个 `<button class="butActionDelete">` 提交按钮，inline JS 在 submit 事件里读取实际勾选数，注入 `WiseIncomingBulkConfirm=%s` 的 confirm() 弹窗；零勾选时弹 `WiseIncomingBulkNoSelection` 提示。**替换**了 2.4.1 引入的逐行 Delete form（多余的 UX 路径），详情页的 Delete 按钮保留——单行决策的场景仍可用。
- **`dumps.php` 加 `bulk_delete` 写动作**（[dumps.php:53](custom/slycustom/wise/dumps.php#L53) 权限闸 + [dumps.php:78](custom/slycustom/wise/dumps.php#L78) handler）。每个 bucket（accepted / rejected）独立包成一个 `<form>`，隐藏 `bucket` 字段与 form 一一对应（避免跨桶误选；rejected 桶的 dump 没有 incoming row 关联、accepted 桶的 dump 是 incoming row 的来源证据，二者诊断独立性不该被一次提交混在一起）。`files[]` 数组每个值是 `"<bucket>/<file>"`，handler 校验前缀与 form 隐藏字段一致后再 `deleteDump()`，成功/失败聚合成 `WiseDumpBulkDeletedOk` 或 `WiseDumpBulkPartial`。
- **dumps 列表行加 checkbox 列 + per-bucket 全选框**（[dumps.php:157-165](custom/slycustom/wise/dumps.php#L157)）。同样的 inline JS 模式：select-all 改每行 checkbox，全选框写在 `<th>` 而不是 `<td>`（list_titre 行）。
- **dumps 列表提交行**（[dumps.php:185-187](custom/slycustom/wise/dumps.php#L185)）：表格下方一个 `<button class="butActionDelete">` 提交按钮，inline JS 动态 confirm。**替换**了 2.4.0 沿用的逐行 Delete form。
- **不动的部分**：
  - 详情页的"Delete notification"按钮（2.4.1 引入）保留——单行决策的路径仍在。
  - 权限不变，仍复用 `wise->write`，菜单/RBAC 不动。
  - `Wise_Incoming::deleteIncoming()` 与 `Wise_Incoming::deleteDump()` 不动，bulk handler 只是一次性循环调用现成方法。
  - `llx_paiement` / `llx_bank` / `llx_facture` 永远不写——`deleteIncoming` 的设计保证这一点。
- **新 lang key**：
  - dumps 批量：`WiseDumpSelectAll` / `WiseDumpBulkDelete` / `WiseDumpBulkConfirm` / `WiseDumpBulkDeletedOk` / `WiseDumpBulkPartial` / `WiseDumpBulkNoSelection`（zh_CN + en_US）。
  - reconcile 批量：`WiseIncomingSelectAll` / `WiseIncomingBulkDelete` / `WiseIncomingBulkConfirm` / `WiseIncomingBulkDeletedOk` / `WiseIncomingBulkDeletedWithDump` / `WiseIncomingBulkPartial` / `WiseIncomingBulkNoSelection`（zh_CN + en_US）。
- **菜单 lang key 兼容补丁**（[langs/{en_US,zh_CN}/slycustom.lang:238](custom/slycustom/langs/zh_CN/slycustom.lang#L238)）：老部署代码 `modSlyCustom.menu[$r]['titre']='SLYWiseReconcile'` 与新 lang 文件不匹配，左菜单会显示原始 key。补 `SLYWiseReconcile=Wise 收款通知`（zh）/ `=Wise incoming notifications`（en）作为 legacy alias——不删，待所有部署点的代码都升级到 2.4.0/2.4.1+ 再清。

### patch

- `patches/sly24.0-wise-incoming-row-delete.patch`：本轮累计 patch（含 2.4.1 逐行删除 + 2.4.2 批量多选），文件仍按同名累积。

## 2.4.1

### 核对页直接删除已 reconcile 通知

2.4.0 把 webhook 角色从"自动入账"降级为"通知用户有新到账"，但只提供了 ignore / enrich 两个动作——已 reconcile 的行（`STATUS_RECORDED` / `STATUS_IGNORED`）会无限堆积，只能整表 `DELETE` 才能清。决定把"删除"挪到 reconcile 页面，对已决策的行可以一键处理，对应的 `accepted/` 桶 dump 文件也一起清理。`dumps.php` 页面（手动重放已删）保持只读浏览/单条删除，不再是主要清理入口。

- **`Wise_Incoming::deleteIncoming($rowid, $entity)`**：新方法（[Wise_Incoming.class.php:1312](custom/slycustom/class/Wise_Incoming.class.php#L1312)）。流程：先 `SELECT rowid, entity, fk_event` 校验 entity，再 `SELECT payload_md5 FROM llx_slycustom_wise_event WHERE rowid = fk_event` 拿到 body hash，然后 `DELETE FROM llx_slycustom_wise_incoming`；最后 best-effort 扫 `DOL_DATA_ROOT/wise_webhook/accepted/*.json`，把 body md5 等于 `payload_md5` 的全部 unlink（同一事件可能有 1-2 次重投递，每个都清）。返回值 `array('ok'=>bool, 'message'=>string, 'dump_deleted'=>int)`。
- **`reconcile.php` 加 `delete_incoming` 写动作**（[reconcile.php:45](custom/slycustom/wise/reconcile.php#L45) 权限闸 + [reconcile.php:111](custom/slycustom/wise/reconcile.php#L111) handler）。校验：CSRF token + entity 匹配 + 状态必须是 `STATUS_RECORDED` 或 `STATUS_IGNORED`（live 状态 `NEW` / `ENRICHED` 直接拒绝，强制先 ignore 否则跳过人审）。handler 跑完 PRG 跳回列表（带 `status` 过滤），不跳详情——详情已经 404。
- **列表行按钮**（[reconcile.php:388](custom/slycustom/wise/reconcile.php#L388)）：终态行在原有 `WiseView` 链接旁加一个 `<form method=POST>` 的 `butActionDelete` 按钮，inline `confirm(WiseIncomingDeleteConfirm)` 防误触。
- **详情页按钮**（[reconcile.php:215](custom/slycustom/wise/reconcile.php#L215)）：终态行展示一个独立的 `<div class=tabsAction>` 块，单独 `butActionDelete` 按钮。STATUS_IGNORED 行因为上面 ignore/enrich 块也会渲染（line 188 的条件是 `!== RECORDED`），所以会同时显示 ignore+enrich+delete 三个按钮——ignore/enrich 对已 IGNORED 的行是 no-op，delete 才是有效操作；不互斥对用户更直观。
- **权限**：复用 `wise->write`（与 ignore / enrich 同权限），菜单/RBAC 不变。`accessforbidden()` 闸在 action 入口统一拦。
- **不动的部分**：
  - `dumps.php` 页面（只读浏览 + 单条删除）保留——`rejected/` 桶的 dump（IP 白名单拦截的投递）没有 incoming row 关联，2.4.1 删不到，只能在 dumps.php 处理。bootstrap 截断的 dump 也不会被 `deleteIncoming` 误删（body 是 2 KB 头切片，md5 必不匹配完整 payload）。
  - `Wise_Incoming::deleteDump()` 不动，仍是 dumps.php 单条删除路径。
  - `llx_paiement` / `llx_bank` / `llx_facture` 永远不写——`fk_paiement` 字段保留只作为 chip 跳转锚点，删除通知时**不**联动付款。
- **新 lang key（5 条 × 2 语）**：
  - `WiseIncomingDelete` / `WiseIncomingDeleteConfirm` / `WiseIncomingDeleted` / `WiseIncomingDeletedWithDump` / `WiseIncomingDeleteNotAllowed` / `WiseIncomingDeleteFailed`（zh_CN + en_US）。

### patch

- `patches/sly24.0-wise-incoming-row-delete.patch`：本轮新增 patch，记录 2.4.1 的 diff。

## 2.4.0

### 回退（与 2.3.6 的"激进瘦身"决策对齐）

slyfood.com 生产的 Wise webhook（`balances#credit` 事件，schema 2.0.0）经 dump 验证**不含付款 reference**——只有 `amount / currency / occurred_at / resource.id / profile_id`，reference 必须由对账单 API 异步回补；2026-10 上线后实测发现"自动入账"链路命中率远低于预期，误判成本高（错配会产生假付款/假银行流水，且 Dolibarr 不允许从 bank 倒回来补充 payment 链接）。决定把 webhook 角色从"自动入账"降级为**"通知用户有新到账 + 列出可能的 bank entry"**。

- **`Wise_Incoming` 删 8 个方法**（实代码 + 对应 UI 入口）：
  - `findInvoiceCandidates` / `validateAllocations` / `saveDraft` / `getDraft`（payment 草稿整条链路）
  - `markReconciled` / `discardDraft`（用户实际操作：mark_reconciled 把行置 IGNORED + note_private 追加审计备注；discardDraft 退回 ENRICHED/NEW）
  - `recordPayment`（核心入账）
  - `autoRecord`（一键自动入账按钮）
  - `replayDump`（dumps 页手动重放 webhook 投递）
  - 上述方法在 live class 里保留为**已废弃桩**——`return -2;` / `return array('mode'=>'none', ...);` / `return array('ok'=>false, 'message'=>'replay removed in 2.4.0; rely on enrichPendingCron');`——防止任何残留调用点（opcache、session 缓存、第三方钩子）触发 fatal。
- **`enrichRow` 瘦身**：移除其中 `findInvoiceCandidates` 调用，`match_data` 现在只写 `sos`（按 ref 批量查 command 表的 SO 候选）与 `soc`（任一 SO 关联的第三方）；`enrichPendingCron` 仍跑（对账单 API 兜底回补 reference/counterparty/fees 仍需），写库 SQL 一次性批量加载，循环内零查询。
- **`reconcile.php` 列表/详情删 6 个入口**：`statusList` 移除 `STATUS_PREPARED`；详情页的「分摊发票」表单、「Current draft rows」草稿表、「保存草稿 / 丢弃草稿 / 确认入账」按钮全删；列表的「自动入账」按钮、「改为手动指定发票」选择器、bank chip 单候选分支的「✓ Mark」内联按钮全删。chip 形态保留三态：已关联 → 绿章链 `compta/paiement/card.php?id=N`；1 候选 → 黄章链 `compta/bank/line.php?rowid=N`（**Doli 24 真实 URL，非 `line/card.php?id=`**）；N 候选 → 黄章列前 2 ID 各链 bank line + 尾部 `(+N)` 链到详情页。
- **`dumps.php` 改为只读列表 + 仅删除按钮**：删 `replay_dump` / `replay_all` action 与 `WiseIncomingPayment::replayDump()` 调用；保留「accepted」/「rejected」双桶列表与「Delete」按钮（轮转噪音用），删除经 `dumpPath()` 严格正则（`Ymd-His-eN-8hex.json`）防路径穿越。访问 `wise->read`，删除 `wise->write`。
- **权限**：`modSlyCustom.class.php` 的 `wise` 权限仅剩 `read`（队列查看、详情查看、dumps 列表查看）+ `write`（ignore / enrich / dumps 删除），原 2.3.6 的 `auto_record` 入口已删且权限字符串改为 "Wise queue mutations (ignore / enrich / dumps delete)"——"自动入账"语义从此无对应入口。
- **菜单**：「Tools → SLYWiseReconcile」标题 lang key 改 `SLYWiseReconcile` → `SLYWiseNotify`，中文「Wise 收款核对」→「Wise 收款通知」、英文「Wise reconcile」→「Wise incoming notifications」——语义对齐"通知而非入账"的新角色。
- **lang 清理**：`{en_US,zh_CN}/slycustom.lang` 删 30+ 个 action 相关 key（`WiseConfirmRecord / WiseSaveDraft / WiseDiscardDraft / WiseDraft* / WiseManualPick* / WiseAutoRecord* / WiseErrAuto* / WiseOpenDraft / WiseMarkReconciled* / WiseBankRow / WisePaiementRow / WiseDumpsHelp / WiseDumpDelivery / WiseDumpSourceIp / WiseDumpReplay / WiseDumpReplayedOk / WiseDumpReplayFailed / WiseDumpTruncated / WiseDumpTruncatedHelp / WiseErrAlloc / WiseErrOverAlloc / WiseErrOverCredit / WiseErrCreate / WiseErrAlready / WiseStatusPrepared / WiseAllocateFill / WiseNoCandidate / WiseMatchedSOs / WiseLowConfidence / WiseRecordedAs` 保留——`prepare.php` 仍用）。
- **`patches/sly24.0-wise-bank-reconciliation.patch` 整文件覆盖**为占位 stub。`1cf8ab87929` commit 已合主干，patch 工具保留这个文件名用于 hotfix flow 引用，无新 patch 时整文件回退到"已合并入主干，瘦身后再无独立 patch"。

### 保留

- `createFromWebhook` / `enrichPending` / `enrichRow`（瘦身版）/ `extractSoRefs` / `getSoPattern/deriveSoPatternFromNumbering/maskToPattern` / `findBankMatches` / `findPaiementMatches` / `syncFromStatements` / `incomingRowExists` / `enrichPendingCron` / `fetchRow` / `setNote/quoteOrNull` / `isIncomingEnabled` / `isOutgoingEnabled` / `getApiTokenForEntity/getProfileIdForEntity` / `isoToSqlUtc/sqlUtcToTimestamp` / `listDumps` / `deleteDump` / `dumpPath`。
- 三态 chip（Linked/1候选/N候选/无匹配）；只读银行/付款候选表；列表「Ignore / Reenrich」按钮；`webhook/wise.php` IP 白名单 + bootstrap 模式 + 拒投 dump 落盘；`prepare.php` 供应商付款 flow A 全程未动。

## 2.3.6 — 已被 2.4.0 回退

> 本节保留为历史记录，**所有新增的"自动入账 / 草稿 / 手动指定发票 / 银行对账 mark_reconciled / dumps 手动重放"入口已在 2.4.0 删除**——webhook 改为只通知，不产生会计数据。回退原因与具体删除清单见 2.4.0 节。

### 当时新增（已回退）

- **Wise 核对队列的「银行对账」**（`WiseIncomingPayment::findBankMatches` / `findPaiementMatches` / `markReconciled` + 列表 chip + 详情对账面板）。用于"用户已经在银行或发票付款 UI 入账，但 Wise 入队行还显示 NEW/ENRICHED"的场景——以前只能「忽略」或「重新分摊入账」把行消化掉，二者都会再产生会计数据。改为**只读对账**：
  1. **列表 chip**（操作列左侧）：把 200 行的 Wise 入队按（最早发生时间 − 3 天 .. 最晚 + 3 天）扩成一条批 SQL 一次拉完（`ABS(b.amount) ± 30 USD` 容纳手续费、`UPPER(currency_code)` 一致），循环内零查询。**单条 best 匹配时直接在行内给出 `Bank #ID / $X / 日期` 可见文字 + `[✓ Mark]` 内联按钮**（POST + CSRF，写完跳回列表）；多条候选时显示前两条 ID 摘要并链到详情页（详情页有完整候选表 + 表单可挑）；无候选显示「—」；`fk_paiement` 已设显示绿章 ✓ Linked。这是为了让用户能"看见具体哪行银行流水、确认、标记"——只显示「⚠ Bank only」一个笼统章实际价值很小。
  2. **详情对账面板**：分别列出 `llx_bank` 与 `llx_paiement` 候选（最多 5 条，含差额与日期），每条都链到原生 bank/付款卡片；表单可挑（`bank_id` 可空、`paiement_id` 可空）→ `mark_reconciled` POST + CSRF → 行置 `IGNORED` + `note_private` 追加审计备注「auto-reconciled by user #X on YYYY-MM-DD HH:MM:SS — paiement #N, bank #N」。**不**对 `llx_bank` / `llx_paiement` / `llx_facture` 做任何写入，符合"银行→付款→发票的链路单向"的 Dolibarr 不变式。`return=list|detail` 参数决定 PRG 跳回列表还是详情。
  3. **设计依据**：用户明确"不要直接写入bank，因为dolibarr不允许从bank倒回来补充payment链接"，且 6.11 USD 差额形态在用户截图里出现 3/4 次（银行手续费被叠进 credit 金额），±30 USD 容差覆盖。**SQL 坑**：`llx_bank.datev` 是 `DATE` 列、`llx_paiement.datep` 是 `DATETIME` 列；`BETWEEN <unix_int>` 会被 MySQL 当 `YYYYMMDD` 解析导致零命中，必须分别包 `DATE(FROM_UNIXTIME(...))` / `FROM_UNIXTIME(...)`。
  4. **权限**：`mark_reconciled` 与现有 `record` / `save_draft` / `auto_record` 一致，校验 `wise->write`；`note_private` 已有，列以 `show COLUMNS` 守卫后 `ALTER TABLE ADD COLUMN` 幂等补齐。
- Wise 入账队列新增 **payment 草稿**（状态 `PREPARED`）：核对页可先把勾选的发票分摊 **保存为草稿**，草稿存在 `llx_slycustom_wise_incoming.match_data.allocation`，**不产生任何会计数据**（不建 `Paiement`、不写银行流水、不关票），留在队列里等复核；「确认入账」才真正入账，表单未勾选时按已存草稿确认；「丢弃草稿」把行退回补明细后的状态（`IGNORED` 的行保持 `IGNORED`）。保存/丢弃/入账共用 `wise->write` 权限。
- `WiseIncomingPayment::validateAllocations()`：把 `recordPayment()` 里内联的发票批量校验抽成独立方法，`saveDraft()` 与 `recordPayment()` 共用——能存下的草稿一定能入账。发票仍是一次性批量加载，循环内零查询。
- 队列状态筛选、设置页队列计数新增「待确认草稿」。

### 当时修复（2.4.0 仍生效）

- **Remain-to-pay / 候选金额全线改为发票币种口径**。此前 5 处（自动候选 SO 链、金额窗兜底、分摊超额校验、手动选票列表、金额接近度排序）一律用 `f.total_ttc - SUM(pf.amount)`——对多币种发票这两个列存的是**本位币（SGD）折算值**，发票面额在 `multicurrency_total_ttc`、外币付款在 `pf.multicurrency_amount`，混用后每张外币（USD）发票的总额/剩余都按汇率错一遍。统一改为 `CASE WHEN multicurrency 发票 THEN multicurrency_total_ttc / multicurrency_amount ELSE total_ttc / amount END`；自动入账的唯一匹配校验与 FIFO 预填随之受益。`recordPayment` 的外币 `Paiement`（`multicurrency_amounts`）原本即正确，未动。（`recordPayment` 已在 2.4.0 删，本条目历史有效，仅供参考迁移到任何后续重写。）
- `Wise_Incoming` / `Wise_Payment`：**4 处 `date_creation` 直接写入 `escape(dol_now())`（Unix 时间戳整数）**，在 MySQL/MariaDB 严格模式（`STRICT_TRANS_TABLES`，cPanel 默认）下报 `Incorrect datetime value`，INSERT 全部静默失败。全部改为 Dolibarr 标准的 `$db->idate(dol_now())`。
- `Wise_Incoming`：**对账单补明细改用收款行自己的 profile**（新增 `llx_slycustom_wise_incoming.wise_profile_id`）。webhook 入队写报文的 `resource.profile_id`，cron 兜底入队写它读取所用的 profile，`enrichRow()` 优先用行内 profile，为空才回退到配置值。列以`SHOW COLUMNS` 守卫后 `ALTER TABLE ADD COLUMN` 幂等补齐。
- `webhook/wise.php`：**被 IP 白名单拒绝的投递现在也会留下证据**。改为先写 dump 再响应，dump 落到 `DOL_DATA_ROOT/wise_webhook/rejected/`。
- `webhook/wise.php`：白名单从 4 个 `/32` 单 IP 放宽到实际在用的 AWS `/16`（`18.184.0.0/16` 等）。
- `webhook/wise.php`：`$bootstrap` / `$keyFile` 的解析提前到 IP 白名单之前；bootstrap 模式（`DOL_DATA_ROOT/wise_webhook/verification_key.pem` 不存在）在开启 `WISE_WEBHOOK_IPCHECK` 时不再丢弃投递。`wiseWriteDump()` 抽出；未验签的只保留 2 KB 头部，目录按 50 个文件轮转。
- `wise/reconcile.php`：所有操作按钮由 `<input type="submit" value="翻译标签">` 改为 `<button name="step" value="固定 token">`。
- `WiseIncomingPayment::recordPayment()` 与 `WisePayment::recordSupplierPayment()`：付款备注字段由 `note_public` 改为 `note_private`。（`recordPayment` 已在 2.4.0 删；供应商端 `recordSupplierPayment` 仍存活在 `WisePayment` 类，保留修复语义。）
- `WiseIncomingPayment::enrichRow()`：重新补明细不再覆盖已存的草稿，也不再把 `PREPARED` 状态打回 `ENRICHED`。
- `langs/{en_US,zh_CN}/slycustom.lang`：`WISE_SIGNATURE_KEY_MISSING` 原文与代码行为相反，误导管理员，改为说明"不启用 IP 白名单则不入库"；`WISE_IPCHECK_ACTIVE` / `WISE_WEBHOOK_IPCHECKTooltip` 同步补上"未验签时只有开启此项才会入库"以及"站点前有反向代理/CDN 时全部推送会被拒"。
- **SLY ALL-in-One 与导出详情页的"错链"修复**（`custom/slycustom/exports/export_all.php`）。5 个数据集的有误对象链接此前都跳到 `list.php?search_ref=...`：`$pushRef` 改为 `ref => rowid` 映射，`renderRow` 优先渲染 `card.php?id=<rowid>`，无 id 时回退到 list 搜索链接。同步清理 `slyAllInOneValidate()` 旧实现、`getSlyExportDatasets__legacy()`、`tools.service.php` 三个 pass-through 包装、`@deprecated slyExportsPrintDualTabNavigation()` 共 -997/+73 = -924 行死代码。

### 当时诊断（2.4.0 仍生效）

- `admin/setup.php` 诊断面板显示**被白名单拒绝的投递数**（`WiseRejectedCount`），并按 `accepted/`、`rejected/` 两个子目录分别统计 dump。
- `patches/sly24.0-wise-webhook-unverified-ingest.patch`：本次 webhook 修复的记录补丁。
- `wise/reconcile.php`：收款详情页新增 **手动指定发票** 选择器。筛选框接受 SO 号或发票号（`extractSoRefs()` 容错一致），列表显示来源 SO 号，可按客户名二次筛选，默认按发票日期倒序，最多 200 张。分摊金额默认留空，单票筛选时预填 `min(该票剩余, 收款额)`。**已于 2.4.0 删除**——`findInvoiceCandidates` 整条链路已拆。
- `admin/setup.php` Wise 页 **webhook 诊断面板** + 「运行 webhook 自检」按钮 + 四步浏览器排查指引。

### 文档

- 新建 `ChangeLog.md`（此前 README 引用了它但文件并不存在）。
- `README.md`：Wise 章节补写验签状态与入库条件的关系、payment 草稿流程；版本条目对齐到 2.3.6，模块目标版本改为 24.0。

## 2.3.5

- shipment extrafield 的日期类字段默认值取当天。

## 2.1.0

- 新增 Wise 付款准备（flow A）：供应商发票卡片"通过 Wise 付款"、未注资转账（幂等 uuid）、参考号取供应商自己的订单号、`outgoing_payment_sent` 后自动记供应商付款。

## 2.0.0

- Wise 收款集成端到端（flow B）：webhook 收款入队、对账单 API 回补参考号、SO 编号匹配引擎、银行账户映射、人工核对页、对账单兜底 cron。
