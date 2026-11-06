# ChangeLog — Odoo Connector (odooconnector)

本文件只记录自定义模块 `odooconnector` 自身的迭代，**不涉及 Dolibarr 上游核心的 ChangeLog**。

## 1.0.5 — 2026-11-06

### 新增

- **连接器运行锁（防止重复推送）**：新增基于 `flock()` 的排他锁，锁文件放在
  `DOL_DATA_DIR/odooconnector/sync_lock_e<entity>.lock`（**按实体分锁**，多公司互不影响）。
  取得锁的入口全部包了同一把锁：整单推送 `runSync()`、货运日期刷新 `runShipmentDateSync()`、
  审核后即时推送 `syncDocumentNow()` / `syncExpenseNow()`、单据反审核 `syncDocumentUnvalidate()`。
  - 为什么需要：重复防护（按 ref 在 Odoo 里找已有分录）**只在「该单据还没有映射行」时才生效**。
    若 cron、setup 页的手动按钮、审核触发器**同时**跑到同一个还没推送过的单据，
    两边都会看到「没有映射行」→ 各自建一张 Odoo 分录 → Odoo 里出现重复草稿。
  - 行为：cron 抢不到锁时**直接跳过**本次运行并打印
    「another run (…) is in progress, this run is skipped to avoid pushing the same document twice」；
    手动按钮与触发器最多等 15 / 10 秒再放弃（单据会被后续 cron 补推）。
  - 锁取不到或数据目录不可写时**不阻断同步**（宁可少一层保护，也不能让同步整体停摆）。
  - 锁在 `finally` 中释放，另有 `__destruct()` 兜底，异常中断也不会把锁留死。
- `runSync()` / `runShipmentDateSync()` 新增可选参数 `$waitSeconds`（等待锁的秒数，默认 0）。

### 验证

- `php -l` 通过（`class/OdooSync.class.php`、`admin/setup.php`、模块描述）。
- Dry-run 桩件 27 项断言全部通过，新增 4 项锁断言：锁可取得、并发运行被跳过且**完全没有碰 Odoo**、
  释放锁后运行恢复正常。

## 1.0.4 — 2026-11-06

### 新增

- **Odoo 到期日（Due Date）现在跟着货运走**：货运刷新任务以前只写记账日期
  （`account.move.date`），到期日（`account.move.invoice_date_due`）没动，所以 Odoo 上的
  Due Date 一直停在建单那天的付款条件算出来的值。新增到期日规则（setup 页可选）：
  - `ata_offset`（默认）：**到期日 = ATA − N 天**，N 由 setup 页填写（默认 10）。
    货运没有 ATA 时退回记账日期。
  - `accounting_date`：到期日 = 记账日期。
  - `ata_then_eta`：ATA，没有则 ETA（1.0.3 之前的旧行为）。
  - `none`：连接器完全不写到期日，Odoo 保留按付款条件算出的到期日。
  没有关联货运单的单据默认不写到期日（`accounting_date` 规则除外）。
- 整单推送与货运日期刷新任务**共用同一套到期日规则**（新增 `computeDueDate()`），
  两条路径不会就同一个字段互相覆盖。
- 刷新任务的 Odoo 预读字段增加 `invoice_date_due`（之前只读 date/invoice_date/state），
  因此才能判断「到期日是不是已经对了」而跳过重复写入。
- 输出里同时打印到期日的变化：`accounting date 2026-09-01 -> 2026-11-23, due date 2026-08-01 -> 2026-11-13`。

### 说明

- 记账日期与到期日分两次写入会踩坑：把已过账分录 `button_draft` 重置后，Odoo 会按付款条件
  重算到期日，所以两个字段必须在重置之后**同一次 write** 里一起写。现在就是这样做的。

### 验证

- `php -l` 通过（模块内全部 PHP 文件）。
- Dry-run 桩件 23 项断言全部通过，新增到期日规则的 8 项断言（ATA−N 天、无 ATA 退回记账日期、
  无货运单不写、跟随记账日期、ATA→ETA、不写入，以及刷新任务确实同时写出到期日）。

## 1.0.3 — 2026-11-06

### 修正（本次「Odoo 记账日期没有跟着 ATA/ETA 更新」的真正根因）

- **货运刷新任务遍历了错误的货运单 ID**（核心 bug）：`getRecentlyUpdatedExpeditionIds()` 返回的是
  「值 = 货运单 id 的列表」（`array_keys(id => true)`），但任务里写的是
  `foreach (array_keys($expIds) as $expId)`，取到的是**下标 0、1、2…** 而不是真实货运单 id。
  于是每个货运单都按 id 0/1/2 去查关联单据 → **一条都查不到** → 任务每次都是
  「0 个关联单据、0 条更新」，看起来「跑过了但日期就是不动」。
  已改为 `foreach ($expIds as $expId)`（主同步里的同名调用本来就是对的，只有这个任务错了）。
- 连带修正：同一批任务里 `$obj->fetch()` 被提到「已过账跳过」判断之前，
  「未推送」「已过账跳过」「日期本来就对」三个分支现在都会打印单据号，输出可对账。

### 新增

- **setup 页新增「立即刷新货运日期」按钮**：一键执行与 cron 完全相同的货运刷新任务，
  不用等定时任务，也不依赖任务是否被启用（模块安装时两个 cron 都是 `status = 0`，
  需要在 首页 → 设置 → 定时任务 里手动启用；`insert_cronjobs()` 只按 label+entity 判重，
  **不会更新已存在行的 status**，所以改描述文件也改不回来）。
- **任务输出改为逐单据可核对**：开头打印本次生效的配置（记账日期来源 / 取哪个货运日期字段 /
  是否允许改已过账分录），每个货运单打印读到的 ATA、ETA、extrafields 更新时间与关联到的
  发票 / 账单数量，每张单据打印最终动作（更新 / 本来就对 / 未推送 / 已过账跳过 / 写回失败）。
- **新建实例时两个 cron 任务默认启用**（描述文件 `status` 由 0 改为 1）。
  注意：**这只影响新装的模块** —— `insert_cronjobs()` 按 label+entity 判重，**不会更新库里
  已存在的行的 status**，所以生产库上仍需在 首页 → 设置 → 定时任务 里手动启用，
  或直接用 setup 页的「立即刷新货运日期」按钮。

### 验证

- `php -l` 通过（`class/OdooSync.class.php`、`admin/setup.php`、模块描述、触发器）。
- Dry-run 桩件 13 项断言全部通过，新增回归断言「任务查的必须是真实货运单 id（42），
  不能是下标」，专门盯住这次的根因。

## 1.0.2 — 2026-11-06

### 上线顺序（重要：必须先改库，再停用/启用模块）

模块改名后，库里旧的行仍挂着旧模块名 `odoo_connector`，直接「停用 → 启用」会报：

```
Error Menu entry (all,100,/custom/odooconnector/admin/setup.php) already exists
```

原因（Dolibarr 核心行为，非本模块 bug）：

- `llx_menu` 的唯一判重键是 `menu_handler + fk_menu + position + url + entity`，**与 module 无关**；
- `delete_menus()` 按 `module = rights_class` 删除，旧行的 module 还是 `odoo_connector` → 停用时删不掉；
- 启用时 `insert_menus()` 发现同 URL 菜单已存在 → 报错并回滚 → 模块启用失败。

因此顺序必须是：

```sql
-- 1) 先把旧行的归属改成新模块名（改完停用时才能被正确删除）
UPDATE llxsf_menu      SET module      = 'odooconnector' WHERE module      = 'odoo_connector';
UPDATE llxsf_rights_def SET module      = 'odooconnector' WHERE module      = 'odoo_connector';
UPDATE llxsf_cronjob    SET module_name = 'odooconnector' WHERE module_name = 'odoo_connector';
```

2) 首页 → 设置 → 应用（模块）→ 停用 `odooconnector`（此步会清掉那条旧菜单行与权限行；
   **不会**删表：描述文件 `remove()` 传入的是空 SQL，`llx_odoo_connector_sync` 映射表与
   `llx_odoo_connector_synclog` 失败日志表都保留；两个计划任务的行也保留，因为它们的
   `test` 是 `$conf->odooconnector->enabled`，核心只删 `test = '1'` 的行）；
3) 再重新启用 → `insert_menus` 不再撞车，`insert_permissions` 遇到同权限只记 INFO，
   `insert_cronjobs` 按 `(label, entity)` 去重，不会产生重复行；
4) 计划任务列表里确认两个 Odoo Connector 任务的 Module 列显示 `odooconnector`。

### 修正

- **常量名错误**：`runSync()` 读的是 `ODOO_CONNECTOR_SUPPLIER_BILLS`，而 setup 页保存的键是
  `ODOO_CONNECTOR_SYNC_SUPPLIER_BILLS`。供应商账单同步开关因此永远取默认值（=1），在 setup 页
  关掉也没用。已统一为 `ODOO_CONNECTOR_SYNC_SUPPLIER_BILLS`。
- **货运记账日期刷新形同虚设**：记账日期取自全局来源（默认 `invoice_date`），ATA/ETA 根本不参与
  计算，刷新任务每次算出来的都是发票自己的日期 → 写回同一个值 → 一直显示 "already correct"。
  现在：
  - 新增 `useShipmentDateAsAccountingDate()`：来源为 `delivery_reception`，**或**来源为默认的
    `invoice_date` 且开启了「根据货运更新刷新记账日期」时，记账日期取货运的 ATA/ETA；
  - 货运刷新任务直接取货运日期（ATA → ETA，或 setup 里指定的字段），不再受全局来源影响；
  - 主同步与货运刷新任务用同一套取值规则，两边不会互相覆盖同一个 Odoo 字段。
- **费用报销状态过滤**：`fk_statut >= 1` 会把已取消（4）和已拒绝（99）的报销单也推给 Odoo。
  改为 `fk_statut IN (2, 5, 6)`（已提交 / 已审批 / 已付关闭），草稿（0）不推。
- **费用报销日期列**：默认改用 `date_create`（报销单没有发票日期，`date_debut` 是费用期间起点，
  只在「到期日」筛选时使用）。

### 新增

- **费用报销即时推送**：触发器新增 `EXPENSE_REPORT_VALIDATE` / `EXPENSE_REPORT_APPROVE`
  → `OdooSync::syncExpenseNow()`。此前费用报销没有任何即时通道，只能等小时级 cron。
- **货运刷新可更新已过账分录**：新常量 `ODOO_CONNECTOR_SHIPMENT_DATE_UPDATE_POSTED`
  （setup 页新增开关，默认开）。开启后：货运 ATA/ETA 变化 → `button_draft` 把已过账的 Odoo 分录
  重置为草稿 → 写入新的记账日期 → 标记 To Review，由会计复核后重新过账。Odoo 拒绝重置
  （期间已锁定、已核销）时在讨论区留言并记入失败日志，主同步路径的策略与此一致。

### 说明（Odoo 19）

- Odoo 19 的 `account.move` **没有** `delivery_date` 字段（它在 stock.picking / sale.order 上），
  代码里 `odooFieldExists('account.move', 'delivery_date')` 的保护会跳过该写入，不会报错。
  Odoo 账单上能承载 ATA/ETA 的只有 `date`（记账日期，货运刷新任务写）和 `invoice_date_due`
  （到期日，只在整单推送时写）。

## 1.0.1 — 2026-11-06

### 修正

- `rights_class` 由 `odoo_connector` 改为 `odooconnector`（= 模块目录名 / `strtolower($this->name)`）。
  该值被核心用于三处，改名后与模块真实名称一致：
  - `llx_cronjob.module_name` —— 计划任务列表里 Module 列不再显示 `odoo_connector`；
  - `llx_rights_def.module` —— 权限定义归属正确的模块；
  - `llx_menu.module` —— 菜单管理器归属正确的模块。
- 触发器 `family` 由 `odoo_connector` 改为 `odooconnector`（核心惯例为模块名，如 `agenda` / `ticket`）。

### 需要在数据库侧同步（生产执行一次）

```sql
-- 1) 计划任务：删除历史遗留的重复行，并把 Module 名改对
DELETE FROM llxsf_cronjob
WHERE (classesname LIKE '%odoo%' OR module_name LIKE '%odoo%')
  AND label NOT IN ('Odoo Connector sync (invoices, bills, expenses)',
                    'Odoo Connector shipment date refresh (ATA/ETA updates)');

UPDATE llxsf_cronjob SET module_name = 'odooconnector'
WHERE module_name = 'odoo_connector' AND classesname = 'custom/odooconnector/class/OdooSync.class.php';

-- 2) 权限定义与菜单归属同步
UPDATE llxsf_rights_def SET module = 'odooconnector' WHERE module = 'odoo_connector';
UPDATE llxsf_menu SET module = 'odooconnector' WHERE module = 'odoo_connector';
```

> 表前缀按实际环境替换（生产 slyfood.com 为 `llxsf_`）。
> 两个合法任务在描述文件里默认 `status = 0`（停用），若依赖小时级兜底同步，需在
> 首页 → 设置 → 计划任务 中手动启用。
