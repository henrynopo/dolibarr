# 在官方 24.0.1 上应用 SLY 补丁（22.0.4 → 24.0.1 移植）

本目录下 **sly24.0-*.patch** 系列由「官方 24.0.1 + 全部 [archive/sly22.0-*.patch](archive/) + 未入库定制」3way 合并、逐冲突人工裁决后重新生成（见 `gen_sly24_patches.sh`）。
所有落库 PHP 文件均通过 `php -l`（PHP 8.1）语法检查；d-test（24.0.1 + 生产 custom）为功能验证环境。

生成基线：官方 tag `24.0.1`（commit `b7958385f00`）。**与 22 版 patch 的关键差异见下文「淘汰清单」与「Hook 化与官方吸收分析」。**

---

## 〇、升级必做：dol_eval 白名单（与补丁无关，但 24 升级必配）

Dolibarr 24 的 `dol_eval()` 对计算公式（extrafield computed、权限表达式等）默认只允许一个
函数白名单（`core/lib/functions.lib.php` 顶部默认值），**不含 `isset`/`empty`**。从 22 迁移
过来的数据库里，SLY 的 shipment 计算字段（ATA−ETA、ATD−ETD 等）大量使用
`isset($object->array_options) && ...` 写法，在 24 上会抛：

```
Bad string syntax to evaluate. A function or method "isset" was called and is not
into the parameter $dolibarr_main_restrict_eval_methods of white-listed functions...
```

未捕获处表现为页面 500（如 compta/index.php、发票卡片），捕获处显示为列表列的错误文本
（如 shipment 列表计算列）。**该问题与任何 sly24 补丁无关**——未打补丁的 expedition 页面
同样报错即为此证。

**修复（d-test / d-test2 / prod 升 24 时必做，二选一）**：编辑 `htdocs/conf/conf.php`，在其他
`$dolibarr_main_*` 配置附近加一行。

**方案 A（不推荐——括号语法限制，实测不适用）**：

```php
$dolibarr_main_restrict_eval_methods = '';
```

空字符串=切回「黑名单模式」（v22 的默认行为）：允许所有**直接命名**的函数（`isset`、`array_sum`、
`array_column`、`is_array`、`count`……全部旧公式原样可跑），但仍强制拦截动态调用、反引号、字符串拼接、
白名单外的 `$变量`，以及硬黑名单（exec/system/proc_open、文件读写 fopen/file_get_contents、
include/require、eval 族、可接收 callable 的函数、混淆函数 base64_decode/sprintf 等、posix/pcntl）。
这就是 v24 为存量公式设计的官方兼容开关。
（**d-test2 实测 2026-09-23：不适用**——'' 模式的括号语法扫描器要求每个 '(' 必须是字符串开头、紧跟 &&/||/! 或带空格的函数/方法名；嵌套分组（如 round(($a-$b)/86400)）与层叠调用（array_sum(array_column(...))）会剩下未消费的 '(' 而被拒："found call of a function or method without using the direct name"。官方示例恰好按此语法书写，SLY 公式不符合。请用方案 B。）**用此方案时数据库公式一条都不用改**（含 shipment 的
isset 日期差与 array_sum 聚合公式）。

**方案 B（推荐——白名单模式追加函数）**：

```php
$dolibarr_main_restrict_eval_methods = 'getDolGlobalString, getDolGlobalInt, getDolCurrency, getDolEntity, getDolDBType, fetchNoCompute, hasRight, isAdmin, isExternalUser, isModEnabled, isStringVarMatching, abs, min, max, round, dol_now, preg_match, isset, empty, is_array, array_column, array_sum';
```

（= 官方默认白名单 + SLY 公式所需函数；更严格，但今后公式用到新函数都要追加。）

另：v24 官方为「公式需要已加载的其他对象」提供了新机制——公式内 `new X($db)` + `->fetchNoCompute($id)`
（fetchNoCompute 即「完整 fetch 但关闭计算以防递归」，这就是它在默认白名单里的原因）。官方示例见
admin.lang ComputedFormulaDesc3。SLY 的行聚合公式无需此机制（求值时 lines 已加载）。

## 〇b、升级后必配的两个常量（Setup → Other Setup，非补丁）

v24 上游新增了两个行为开关，d-test22 上的默认观感在 d-test24 会「消失」，都是**纯配置**，
在 后台 → 设置 → 其他设置（Other Setup / 常量编辑器）里各加一条即可，不要为它们打补丁：

1. **`MAIN_STATISTICS_IN_MENU` = `1`** —— v22 里订单/发票左侧菜单的 Statistics 入口是无条件的，
   v24 的 eldy 菜单把它（连同供应商订单、合同、 Intervention、薪资等全部统计入口）包进了
   `if (getDolGlobalString('MAIN_STATISTICS_IN_MENU'))`，不设置即全部隐藏。
2. **`MAIN_CURRENCY_SYMBOL_BEFORE_VALUE` = `1`** —— v22 SLY 的观感「货币符号在金额前面」来自
   `sly24.0-core.patch` 对 `price()` 的定制（`$listofcurrenciesbefore` 前置判断加了这个开关）。
   打上 core 补丁后仍需设置此常量，符号才会前置；不设则符号在金额后。

另注：SG 日期格式（dd/mm/yyyy 斜杠）在 `langs/en_SG/main.lang`（2.2.18 起随
`sly24.0-langs.patch` 下发，仅 2 行）；用户语言需选 English (Singapore) 才生效。

---

## 一、淘汰清单（22 版有、24 版不需要）

| 22 版 patch / 定制 | 24.0.1 官方状态 | 处置 |
|---|---|---|
| `archive/sly22.0-paiement-arrayfields.patch` | 官方 `compta/paiement/list.php` doActions 已传 `arrayfields`（键顺序不同，语义相同） | **淘汰**，无需 24 版 |
| `archive/sly22.0-fourn-paiement-arrayfields.patch` | 官方 `fourn/paiement/list.php` 同上 | **淘汰**，无需 24 版 |
| admin patch 中 `admin/supplier_invoice.php`、`admin/supplier_order.php` 两处 | v24 已删除这两个文件；定制内容仅为 `getDolGlobalString()` 化，v24 官方已全面采用该写法 | **淘汰**（生成 24 版 admin patch 时已排除） |
| `compta/paiement.php` 的 SLY 多币种付款页改造 | v24 官方已原生实现完整多币种付款页（JS 分支、合计列、`multicurrency_result`） | 并入 `sly24.0-compta-multicurrency.patch` 时整体取官方版 |
| `compta/ajaxpayment.php` 的 SLY 多币种分摊 | v24 官方 camelCase 重写版已实现（含 is_array 防护） | 同上，整体取官方版 |
| 发票卡片金额区的多币种行（HT/VAT/TTC/RemainderToPay Multicurrency 独立行） | v24 官方已原生显示 | facture/card.php 相关冲突块取官方版 |

## 二、24 版补丁列表与推荐应用顺序

在**干净官方 24.0.1** 源码根目录（与 htdocs 同级）按序执行：

```bash
# 1. 核心（price 货币符号前置 + dol_eval $obj 防护 + boxes/tpl 多币种合计）
git apply --ignore-whitespace htdocs/custom/slycustom/patches/sly24.0-core.patch

# 1b. 左侧菜单父节点唯一匹配（Tools 下 SLY 三级菜单）
git apply --ignore-whitespace htdocs/custom/slycustom/patches/sly24.0-menu-parent-match.patch

# 2. 客户发票/付款多币种与已付判定（facture card、various_payment、index 等；paiement.php/ajaxpayment 取官方版后仅保留必要差异）
git apply --ignore-whitespace htdocs/custom/slycustom/patches/sly24.0-compta-multicurrency.patch

# 3. remx 折扣多路拆分（含外币拆分、afterSplitDiscount hook——slycustom 依赖）
git apply --ignore-whitespace htdocs/custom/slycustom/patches/sly24.0-remx.patch

# 4. 客户订单（草稿改客户、addline 只填外币价折算、linkedobject 多币种、datefield 统计）
git apply --ignore-whitespace htdocs/custom/slycustom/patches/sly24.0-commande.patch

# 5. 供应商发票/付款（linkedobject 多币种、付款页来源订单列）
git apply --ignore-whitespace htdocs/custom/slycustom/patches/sly24.0-fourn-linkedobject.patch

# 6. 报价 linkedobject 多币种
git apply --ignore-whitespace htdocs/custom/slycustom/patches/sly24.0-comm-propal-linkedobject.patch

# 7. 管理后台（debugbar/dict/mails_templates/company；已排除两个 v24 不存在的文件）
git apply --ignore-whitespace htdocs/custom/slycustom/patches/sly24.0-admin.patch

# 8. API（生产缓存目录、禁用用户可调 API、文档/上传简化、CORS）
git apply --ignore-whitespace htdocs/custom/slycustom/patches/sly24.0-api.patch

# 9. Misc/Cron（cron 预筛选 processing=0、delivery/don 小改动）
git apply --ignore-whitespace htdocs/custom/slycustom/patches/sly24.0-misc-cron-other.patch

# 10. 其它模块（adherents/contact/contrat/loan 多阶段还款/salaries/societe capital_currency/supplier_proposal/expedition/install/theme）
git apply --ignore-whitespace htdocs/custom/slycustom/patches/sly24.0-other-modules.patch

# 11. 银行计划明细多币种列
git apply --ignore-whitespace htdocs/custom/slycustom/patches/sly24.0-bank-treso.patch

# 12. 发票列表「来源订单」列位置（数据列由 slycustom hook 提供）
git apply --ignore-whitespace htdocs/custom/slycustom/patches/sly24.0-invoice-list-source-order-position.patch

# 12b. 供应商发票列表「来源订单」列位置（需 slycustom 模块重启用刷新 MAIN_MODULE_SLYCUSTOM_HOOKS）
git apply --ignore-whitespace htdocs/custom/slycustom/patches/sly24.0-supplier-invoice-list-source-order-position.patch

# 12c. 发票类 extrafields computed 初始化防护 + 旧 PDF 模板回退
git apply --ignore-whitespace htdocs/custom/slycustom/patches/sly24.0-facture-pdf-fallback.patch

# 13. 会计：BookKeeping 多币种列 + bookkeepingCreateBefore hook（SFRS Reports 依赖）
git apply --ignore-whitespace htdocs/custom/slycustom/patches/sly24.0-accountancy-sfrs.patch

# 14. 语言包（SLY 翻译）
git apply --ignore-whitespace htdocs/custom/slycustom/patches/sly24.0-langs.patch
```

**数据库（一次性）**：
- `archive/sly22.0-expedition-extrafields.sql`（ShipsGo 扩展字段）——22 升级时已执行过的库无需重跑；新库执行一次（24.0.1 启用 slycustom 模块时由 `sql/llx_slycustom_*.sql` 自动建表/补扩展字段，**不要**重复跑 archive 下的 SQL 以防与 24 字典冲突）
- `archive/sly22.0-facture-so-inv-extrafields.sql`（SO/PO Invoice Details 导出 extrafields）——同上
- `llx_societe.capital_currency`、`llx_loan.schedule_phases` 两列——生产 22 库已存在则无需动作

## 三、Hook 化与官方吸收分析（哪些 patch 可以不打、哪些永远无法 hook）

**结论：24 版 18 个 patch 中，仅 2 个纯 hook 服务型 patch 已被官方吸收淘汰；其余定制分三类——(A) 无法 hook 化（Dolibarr 无对应 hook 点，必须 patch）；(B) 理论可 hook 化但代价大于收益；(C) 已被 v24 官方原生实现。**

### A. 无法 hook 化（必须保留 core patch）

| patch | 为什么不能 hook |
|---|---|
| `sly24.0-core.patch`（price 货币符号） | `price()` 是全局函数，无 hook 点 |
| `sly24.0-menu-parent-match.patch` | `Menubase` 树构建的类内部逻辑 |
| `sly24.0-remx.patch` | 本身就是「给 remx 加 hook 点」的 patch；`afterSplitDiscount` 官方不存在 |
| `sly24.0-accountancy-sfrs.patch` | `BookKeeping::create()` 类内部 INSERT；同理是「加 hook 点」的 patch |
| `compta/commande/fourn/comm` 各 linkedobject 多币种 | `linkedobjectblock` 渲染（tpl + CommonObject）在 v24 **仍无 hook 点**（实测：v24 `commonobject.class.php` 已无 `showLinkToObjectBlock`，tpl 直接 include；页面 10 个 executeHooks 均为标准页面级 hook） |
| facture card 的 `$effective_resteapayer` 多币种已付判定 | 影响「Classify paid」按钮出现与否的状态机，在卡片主逻辑内 |
| `sly24.0-misc-cron-other.patch` | cron 卡片/列表行为，无 hook |
| `sly24.0-commande.patch` 的 addline 外币折算 | `card.php` addline 主流程变量推导，无 hook |
| `sly24.0-langs.patch` | core 语言键覆盖，无 hook（模块 langs 可覆盖同名键但加载时序不可靠） |
| admin / api / bank-treso / 列表列位置 | 自定义页面布局与 bootstrap 阶段行为，无 hook 点 |

### B. 理论可 hook 化、但不建议迁移（代价 > 收益）

| patch 定制 | 理论 hook | 不建议的原因 |
|---|---|---|
| contrat/agenda.php 议程按钮 | `addMoreActionsButtons` | 已随 other-modules patch 打上；单独重写进 slycustom 会分裂 remnant |
| adherents 订阅表单调整 | `formObjectOptions` 等 | 同上；且 22→24 表单结构变化大，hook 版需重做 UI 映射 |

### C. v24 官方已吸收（22→24 升级的最大红利）

见「一、淘汰清单」。付款列表 arrayfields、多币种付款页（paiement.php/ajaxpayment）、发票卡片多币种金额行、`getDolGlobalString` 化、供应商发票/订单 admin 页删除——这些在 v24 上**零 patch 直接可用**。

### 长期建议（供 25+ 升级规划）

1. **逐年核对淘汰清单**：每次大版本升级先跑 `git apply --check` 全量 patch，CLEAN 且 diff 落点与官方新实现重叠的，优先评估淘汰。
2. **新定制一律 hook 优先**：output 层（列/按钮/HTML）用 `printFieldList*`/`addMoreActionsButtons`；数据层用 `doActions`（v24 已普遍传 `arrayfields` 引用）。
3. **「加 hook 点」型 patch（remx、bookkeeping）保持最小 diff**：只保留 `initHooks + executeHooks` 行，业务逻辑全在 slycustom。

## 四、本移植的冲突裁决记录（审计线索）

32 个文件 123 个冲突块，裁决原则：官方 24 重构噪音取 ours；SLY 实质定制（`// SLY` 注释、multicurrency 字段、hook 调用、草稿改客户）取 theirs 并适配 ours 结构。关键裁决：

- `facture/card.php`（23 块）：6 块官方多币种行取 ours；17 块 SLY 付款表格原币列/折扣原币折算/`$effective_resteapayer` 取 theirs
- `comm/remx.php`（21 块）：全取 theirs——SLY 版为多币种 N 路拆分超集，官方 24 的 `DISCOUNT_SPLIT_MORE_THAN_TWO_PARTS` 仅本币
- `fourn/facture/card.php`（14 块）：全取 theirs（与表头 `AmountMulticurrency` 列自洽）
- `compta/paiement.php`（7 块）、`ajaxpayment.php`（4 块）：全取 ours（官方原生多币种实现）
- `commande/card.php`：补回 3way 静默丢失的 2 处——addline 只填外币价折算块、`getSelectConditionsPaiements` 第 3 参 `1`（仅活跃付款条件）
- `install/mysql/migration/21.0.0-22.0.0.sql`：取 ours（22→24 直升由 v24 安装器自带迁移，SLY 对旧迁移的修改不带入）

**复核建议（d-test 验证重点）**：
1. api_documents 上传去病毒扫描/`.noexe` 防护是 SLY 刻意移除的生产定制——确认仍符合预期
2. contrat/agenda 添加事件后不返回议程页（SLY 行为）
3. remx 外币拆分、发票付款表格原币列对齐（theirs 保留旧式 `align="right"` 属性，功能等价）

**3way 静默丢失补回记录**（自动合并跳过、已手工恢复——升级经验：CLEAN apply ≠ 功能保留）：
- `commande/card.php`：addline 只填外币价折算块（v22 提交 0cf6371 的 bug fix）、`getSelectConditionsPaiements` 第 3 参 `1`
- `commande/list.php`：销售代表列 SALESREPFOLL 优先 + `$saleRepNomUrlCache` 缓存
- `cron/list.php`：modulesetup/entity-0 显示全部任务的 entity 预筛选
- `core/lib/functions.lib.php`：`forgeSQLFromUniversalSearchCriteria` 坏过滤串返回安全 `'1 = 2'`（防字面错误串拼 SQL 崩页——即 d-test select_company 事故的防线）、`dol_eval_standard` 的 `$object`/`$obj` 空对象防护
- `core/tpl/object_discounts.tpl.php` + `html.form.class.php::form_remise_dispo()`：可用折扣按对象币种折算显示（display 参数追加为第 13/14 参，v24 官方占了 11/12 位）
- `fourn/commande/card.php`：草稿改供应商联动付款条款/方式/银行账户、PO 创建预填链（供应商报价单→供应商默认→全局常量）、`$newlang`/`$societe` 12 处 PHP8 空对象防护
- `fourn/facture/card.php`：付款条件 `filtertype=1`（两处）

**已核实为官方吸收、无需补回的假丢失**：commande/index.php 与 compta/index.php 的多币种金额列（官方原生 multicurrency 版）、fourn/commande/card.php 的 `$defaultlang` PDF 语言与 filtertype 下拉、`showdocuments` 传参、`form_conditions_reglement` filtertype（官方版已带）。

---

## 五、SLY24.0.1 分支实际应用记录（2026-09-30 升级）

本次升级基线：upstream tag `24.0.1`（commit `b7958385f00`），分支 `SLY24.0.1`。

工作方式：在 `../dolibarr-SLY24` 新建 worktree → 干净 24.0.1 工作树 → 按上文第二节顺序应用全部 17 个 patch → 迁移 20 个 SLY 自研模块。

### 5.1 应用批次与 commit hash

| 批 | commit | 内容 | 改动数 |
|---|---|---|---|
| A | `7ed0319693a` | sly24.0-core + sly24.0-menu-parent-match | 21 文件 / +1056 / -152 |
| B | `dc59edbcaaa` | sly24.0-compta-multicurrency + sly24.0-remx | 10 文件 / +1507 / -782 |
| C | `65cfe5237ed` | sly24.0-commande + sly24.0-fourn-linkedobject + sly24.0-comm-propal-linkedobject | 14 文件 / +858 / -297 |
| D | `e70c9bb0aa5` | sly24.0-admin + sly24.0-api + sly24.0-misc-cron-other + sly24.0-other-modules | 43 文件 / +940 / -397 |
| E | `6e915c0a0a9` | sly24.0-bank-treso + sly24.0-invoice-list-source-order-position + sly24.0-supplier-invoice-list-source-order-position + sly24.0-facture-pdf-fallback + sly24.0-accountancy-sfrs + sly24.0-langs | 15 文件 / +966 / -29 |

**17 个 patch 全部 `--check --ignore-whitespace` 干净通过**，**php -l 抽检全过**。
唯一警告：compta-multicurrency.patch 与 langs 补丁的少数行有 trailing whitespace / CRLF，对应用与语法检查无影响。

### 5.2 与「§四 冲突裁决记录」一致性的实际验证

从干净 24.0.1 出发，`git apply --check --ignore-whitespace` 全部 17 个 patch 均无冲突，
证明 §四 记录的「32 文件 123 冲突块」全部已在生成 24 版 patch 时人工裁决干净，**`--check` 阶段零冲突 = 上游 tag 24.0.1 与 sly24.0-*.patch 系列当前一致**。

§四的「3way 静默丢失补回」共 7 处（commande/card.php、commande/list.php、cron/list.php、
core/lib/functions.lib.php、core/tpl/object_discounts.tpl.php、
html.form.class.php::form_remise_dispo()、fourn/commande/card.php、
fourn/facture/card.php）——已在 24 版 patch 内全部保留（抽检 git diff 24.0.1..HEAD 对应位置
均可命中相关 `// SLY` 注释与多币种块）。

### 5.3 SLY 自研模块迁移

| 阶段 | commit | 模块 | 必修 |
|---|---|---|---|
| 低风险 | `47757afe1f9` | docsemployes, ecv, grh, langpicker, listincsv, previewdocuments, recrutement, rubis, tos, totp2fa | — |
| 中风险 | `670838ef2ff` | changetiers, customlink, mydoli, strongauth, sgpayroll, odooconnector | strongauth 需 `composer install` |
| 高风险 | `78d112ea204` | embeddedbookkeeping, supplierorderfromorder, sfrs_reports | supplierorderfromorder/cbn.php:860 + ordercustomer.php:1673 `'s.fournisseur = 1'` → `'(s.fournisseur:=:1)'` |
| slycustom | `b38f50fb28c` | slycustom 2.2.19 → 2.3.0（need_dolibarr_version 14 → 24） | — |

### 5.4 仓库基础设施调整

- `htdocs/custom/.gitignore`：从 SLY22.0.4 复制白名单版本（v24 上游 `/*` + `!README.md` + `!index.html` 会忽略全部 SLY 模块）
- 全部 20 个 custom 模块入口已被 git 追踪

### 5.5 tag 与推送

- tag: `SLY24.0.1`（annotated）
- 推送目标：fork `henrynopo/dolibarr`
- SLY22.0.4 分支保持不变，d-test22 / 生产 22 部署不受影响

### 5.6 d-test24 验证待办（仅代码层面升级完成，部署验证为下一阶段）

1. `conf/conf.php` 加 `$dolibarr_main_restrict_eval_methods`（含 `isset/empty/array_sum` 等）
2. Setup → Other Setup 加 `MAIN_STATISTICS_IN_MENU=1` 和 `MAIN_CURRENCY_SYMBOL_BEFORE_VALUE=1`
3. 启用 slycustom 模块（need_dolibarr_version=24.0 校验）
4. slycustom 设置页 → Sync PDF models / Sync menu icons / Sync boxes
5. supplierorderfromorder 必修后 CBN/PO-from-SO 页面不再白屏
6. sfrs_reports 多币种列显示非 NULL（依赖 sly24.0-accountancy-sfrs.patch hook）
7. shipment ETA/ATD 计算列在 en_SG 语言下显示数字而非错误文本

---

## 六、勘误（2026-10-02）：ajaxpayment.php 回退官方版

**症状**：`/compta/paiement.php` 付款页外币「剩余支付金额」（`#multicurrency_result`）恒为空白，自动计算失效；生产日志刷
`PHP Warning: Undefined variable $multicurrency_result / $multicurrency_totalRemaining in compta/ajaxpayment.php on line 228-230`。

**根因**：sly24.0-compta-multicurrency.patch 移植时带入了 v22「并行双轨」版本的 19 行残留。其中文件尾部的
`if (isModEnabled('multicurrency'))` 块引用 `$multicurrency_result`/`$multicurrency_totalRemaining`——这两个变量在 v24 官方
重构（LRR：`GETPOSTINT('multicurrency')` 互斥分支，用 `$result`/`$totalRemaining`）后已不存在。该坏块在**每次**请求中用
`price(null)` 把官方分支刚算好的 `multicurrency_result/resultnum/makeRed` 覆盖为空 → 前端显示空白。另 13 行为 GETPOST 后
从未使用的死代码。

**处置**：

- `htdocs/compta/ajaxpayment.php` 完整回退官方 24.0.1 版（`git diff 24.0.1 --` 该文件 = 0 行差异），`php -l` 通过
- `sly24.0-compta-multicurrency.patch` 重新生成：10 → 8 文件（ajaxpayment.php 一节移除），反向校验 `git apply --check -R -p2` 通过
- `gen_sly24_patches.sh` 同步移除该路径，防止再生成时回归
- §5.1 表中 commit `dc59edbcaaa` 的 stat（10 文件）为当时应用记录，按历史保留

**已部署环境**：需将本修复后的 `compta/ajaxpayment.php` 同步到生产（slyfood）与 d-test 后清 error log 验证。

### 6.1 供应商付款页（fourn/facture/paiement.php）外币 JS 协议升级（同日）

**症状**：供应商付款页外币「剩余支付金额」（`#multicurrency_result`）恒空白。注意官方 24.0.1 该页**本无外币自动计算 JS**
（仅本币单轨，外币单元格空挂）——外币功能是 SLY patch 从 v22 带入的有效增强，但用的还是 v22「单请求双轨」协议
（不发 `multicurrency=1`、期望响应含 `multicurrency_result`/`multicurrency_label`），与 §六 修复后的官方互斥分支后端不匹配。

**处置**：JS 升级为客户页（compta/paiement.php）的 v24 协议——`callForResult(imgId, multicurrency = 0)` + `keyresult` 模式；
外币输入框 change/keyup 发 `multicurrency=1` + `multicurrency_amounts/remains`。供应商页无外币「支付金额」总输入框，
故不发 `multicurrency_amountPayment`（后端此时返回外币合计，语义正确）。`sly24.0-fourn-linkedobject.patch` 已重新生成
（7 文件不变，仅 paiement.php 节更新），`php -l` 与反向校验通过。

### 6.2 供应商付款单卡片（fourn/paiement/card.php）双币分录列找回（同日）

**症状**：`/fourn/paiement/card.php` 发票分录列表只剩本币金额，v22 的「外币在上、本币在下」双币显示（ExpectedToPay /
PayedByThisPayment 两列）消失。

**根因**：gen 脚本 fourn patch 清单遗漏 `htdocs/fourn/paiement/` 目录——该文件升级后保持官方 24.0.1 原样（官方版无任何
multicurrency），SLY22 的 14 处定制整体静默丢失（又一例 3way 静默丢失，未入 §四 的 7 处清单——因为当时 diff 源就没包含它）。

**处置**：从 SLY22.0.4 原样移植（循环内 fetch 发票取 multicurrency 字段，外币 ≠ 本币时 ExpectedToPay 显示
`multicurrency_total_ttc`（无则 total_ttc×tx 折算）、PayedByThisPayment 显示 `amount×tx` 折算）；fourn patch 重生成为
8 文件（新增 fourn/paiement/card.php），gen 脚本清单同步加 `htdocs/fourn/paiement/`。php -l 与反向校验通过。

### 6.3 客户付款单卡片（compta/paiement/card.php）双币分录列找回（同日）

与 §6.2 同因同修：gen 脚本清单有 `compta/paiement.php`（文件）与 `compta/paiement/class/`（目录）但漏了
`compta/paiement/card.php`，SLY22 的双币定制静默丢失。已从 SLY22.0.4 原样移植：ExpectedToPay / PayedByThisPayment /
**RemainderToPay 三列**双币显示（外币在上本币在下；外币判定条件含 `multicurrency_tx != 1.0`；外币剩余用
`getRemainToPay(1)`）。compta-multicurrency patch 重生成为 9 文件（新增 card.php），gen 脚本同步。php -l 与反向校验通过。

### 6.4 两张付款卡片的 Amount 字段双币显示补全（同日）

§6.2/6.3 只移植了分录列表；卡片顶部的 **Amount 字段** v22 也是双币的（外币在上本币在下），本轮补上：付款关联银行行时，
外币 = 银行账户 `currency_code`、外币金额 = `abs(bankline->amount)`（银行账户币种的实际发生额）；本币 = 付款对象 amount。
银行账户/BankTransactionLine 段复用 Amount 区已 fetch 的 `$bankline`（`empty($bankline->id)` 才重新 fetch），不产生重复查询。
两页 patch 已重生成，php -l 与反向校验通过。

### 6.5 供应商发票卡片 default_lang null 守卫（2026-10-05）

**症状**：生产 error_log `Attempt to read property "default_lang" on null`（fourn/facture/card.php:614）。`setabsolutediscount`
后 PDF 自动重生成块读 `$object->thirdparty->default_lang`，该 action 分支未加载 thirdparty——PHP 7 静默返回 null（按默认语言
出 PDF），PHP 8 起 E_WARNING 进 error_log。官方原版同款写法全文件共 7 处（276/349/434/614/1830/1978/2078 附近）。

**处置**：7 处统一改 `$object->thirdparty->default_lang ?? ''`（null 合并 isset 语义，thirdparty 已加载时行为完全不变）。
`sly24.0-fourn-linkedobject.patch` 重新生成：8 文件不变，card.php 节 25 → 31 hunk（6 处守卫新增独立 hunk，
setabsolutediscount 重写块内 1 处随原有 hunk），diff 基线仍为官方 blob `837f888f088`，目标 hash `cb33d769b0d`。
`php -l` 与反向校验（`git apply --check -R -p2`）通过。**注意**：d-test2 若已应用旧版需先 `-R` 卸载再应用新版（或基线
重放），bundle（sha 7dbda9b5）需重新生成。

### 6.6 langs patch 移除 fr_FR 段（2026-10-05）

**症状**：生产 apply/dry-run 报 `langs/fr_FR/main.lang: No such file or directory`——生产与本地均已精简语言包
（仅留 en_US / en_SG / zh_CN），fr_FR 目录不存在，而 patch 仍保留精简**之前**生成的 fr_FR 段（`INCT=TVA+Taxes locales
incluses` → `INCT=TTC` 单行翻译改动，无功能影响）。gen 脚本清单本就无 fr_FR（精简时已清），仅 patch 文件残留。

**处置**：`sly24.0-langs.patch` 移除 fr_FR 段：11 → 10 段 / 10 → 9 文件；对基线正向 `--check` 与本地 repo 反向
`--check -R` 均通过。**注意**：d-test2 需同步新版 patch（旧版含 fr_FR 段，对无 fr_FR 的环境恒 conflict），bundle 重新生成。
配套基线包 `sly24_langs_baseline.tar.gz`（9 文件，无 fr_FR）与 `sly24_fourn_baseline.tar.gz`（8 文件）用于生产恢复
官方 24.0.1 基线后再 apply（生产 langs 为 v22 遗留内容，多文件 `patch failed at 行号` 的根因）。

### 6.7 供应商付款页 Ref. vendor 列整体错位修复（2026-10-06）

**症状**：`fourn/facture/paiement.php` 付款页发票表格**所有数据行**与表头错位一列（日期落在 Ref. vendor 表头下、金额依次
左移），tfoot Total (incl GST) 行与数据行错开一列。

**根因**：官方 22→24 删除了 RefSupplier 独立列——表头 `<th>` 与表体 `<td data-col="ref-supplier">` 均注释，ref_supplier
改为 Invoice 单元格内小字副标题。SLY22 移植版表体 td 跟随官方 24 注释掉，但表头 `<th>` 仍按 SLY22 保留、tfoot colspan=5
也计入该列：表头/tfoot 比每个数据行多一列（14 vs 13）。

**处置**：按官方 24 方向对齐——注释表头 `<th>`（paiement.php ~L666），tfoot colspan 5 → 4（`displayAllInvoices` 时仍
自动 +1，即 4/5），列数注释同步。ref_supplier 信息无丢失（仍显示于发票号下方副标题）。patch 重生成仅动 paiement.php
节（2 hunk，行数不变 1585）；顺带修正旧版手误的 card.php 目标 index hash（`cb33d769b0e` → 真值 `cb33d769b0d`，与 §6.5
记载一致）。`php -l` 与反向校验（`git apply --check -R -p2`，于 htdocs 目录）通过。

### 6.8 expedition setClosed() 订单行发运量缺键警告修复（2026-10-06）

**症状**：生产 error_log `Undefined array key 11560` ×2（expedition/class/expedition.class.php:2974/2976）。入口有二：①Shipment
卡片/列表「classify closed」；②**compta/facture 验证发票**——`interface_20_modWorkflow_WorkflowManager` 触发器在
`WORKFLOW_SHIPPING_CLASSIFY_CLOSED_INVOICE`（deprecated 变体，生产在用）开启时对每个关联 Shipment 调
`setClosed()`，警告因此在 compta 页面请求内落 log。**官方 24.0.1 原版 bug**：`setClosed()` 用
`$order->expeditions[$lineid]` 按订单行 rowid 取已关闭发运量，而 `loadExpeditions(STATUS_CLOSED)` 只为有发运量的行建键
——服务行、未发运/部分发运行无键 → PHP 8 每行两条警告。行为本就正确（null≠qty 判不匹配、不误关订单），纯噪音。

**处置**：2974/2976 两处 `?? 0` 守卫（语义等价：缺键原本按 null 参与 `!=`，与 0 的比较结论一致，不影响
`shipments_match_order` 判定与订单自动关闭逻辑）。该文件**首个** SLY 改动（此前与 24.0.1 零差异），
`sly24.0-other-modules.patch` 重生成：2052 → 2068 行（+1 节 / 1 hunk / 16 行，其余节零变动）。`php -l` 与反向校验
（`git apply --check -R -p2`，htdocs 目录）通过。**注意**：d-test2 已应用旧 other-modules patch 的需 `-R` 卸载再重放；
生产需同步 expedition/class/expedition.class.php。同日模块层配套（不进 patch）：slycustom doActions 的 ShipsGo 键预置改为对
页面全局对象**无条件**执行（原按 element 枚举，facture→commande→order_supplier 逐例报障逐例补——供应商订单卡
fourn/commande/card.php ~L2932 的关联对象块同样完整 fetch 关联 Expedition，element=`order_supplier` 不在清单内；凡渲染期
fetch Expedition 的页面都会以自身全局 `$object` 求值公式）。键仅在缺失时添加、不入库（core 只保存该 element 已声明的
extrafields）。ChangeLog 2.3.5。

### 6.9 core patch：computed formula 渲染期 atd/ata/etd/eta 缺键告警修复（2026-10-07）

**症状**：commande/error_log、compta/facture/error_log、fourn/commande/error_log 持续刷
`Undefined array key "options_atd"/"options_ata"`（09:10–25 段峰），`expedition/error_log` 不出现。slycustom 2.3.5
已把 `ensureShipmentDateOptionKeys()` 提为对页面全局 `$object` 无条件，但**仍触发**——根因：slycustom doActions
钩子的执行时机是页面进入控制流时；computed-field 公式在 `commonobject.class.php:8860` 的 `dol_eval()` 里**渲染期**
被求值，对象是 `printExtraFields()` 当前正在迭代的局部 `$object`，而不是 slycustom 钩子里的页面全局
`$object`。当 invoice/commande 卡片或 supplier order 卡片 fetch 关联 Expedition 后渲染其
`array_options`，公式（注册在 `expedition` 元素上）会被 evaluate 到这张当前对象的 `array_options`——而
commande/facture/order_supplier 自身不声明 atd/ata 键。

**处置**：在 `core/class/commonobject.class.php:8859` 的 `if ($computed)` 分支内，`$objectoffield = $this;`
之后立刻对 `$this->array_options` 做 atd/ata/etd/eta 预填（`is_array(...) ?? null` 守卫 + `array_key_exists`
判存在，`isset` 失败键补 0）。补丁段独立、可独立 forward/reverse dry-run 通过。`sly24.0-core.patch`
8857,8 → 8857,22（+14 行）。`php -l` 与反向校验通过。**注**：doActions 钩子期预填**不取消**（仍有部分列表渲染路径
依赖），两层叠加。

### 6.10 fourn/facture/card.php:4584 `$societe->default_lang` null 守卫（2026-10-07）

**症状**：`compta/facture/card.php` 某路径下日志里 "Attempt to read property default_lang on null"；与 6.5 的
`$object->thirdparty->default_lang` 是**不同位置**——本处是 `$formfile->showdocuments(..., $societe->default_lang)`
的尾参，`$societe`（供应商 societe 实例）未初始化即被解引用。生产报告行号 3396，本地仓库因其他改动使
该语句位于 4584；hunk header 用本地坐标，加载到生产后由 patch 工具自动 remap。

**处置**：单 hunk 改 `$societe->default_lang` → `is_object($societe) ? $societe->default_lang : ''`。
`sly24.0-fourn-linkedobject.patch` +1 节（7 行），正反 `git apply --check` 通过。ChangeLog 2.3.6。
