# 仅保留 PDF + ShipsGo 所需的最小核心补丁清单（官方 Dolibarr 14.0）

在**官方 Dolibarr 14.0** 上使用 **slycustom 模块**时：

| 功能 | 是否需要核心补丁 | 说明 |
|------|------------------|------|
| SLY PDF 模板 | **否** | 模块自带，设置里选模板即可 |
| ShipsGo 数据库 | **是** | 执行一次 SQL，为 `llx_expedition_extrafields` 增加 ShipsGo 用列 |
| ShipsGo 确认时提交 | **是** | 修改 `htdocs/expedition/card.php` 两处 |
| ShipsGo 列表订单列 + 批量更新 | **是** | 修改 `htdocs/expedition/list.php` 多处 |
| ShipsGo 定时同步（cron） | **否** | 模块内 `ShipsGo_Update.class.php` 即可，仅需 DB + API 配置 |

---

- **PDF 模板**：无需任何核心补丁，模块提供的模板可直接选用。
- **ShipsGo 物流**：需要以下**最小**核心改动（数据库 + 2 个核心文件 + 1 个常量）。

---

## 一、PDF：无需补丁

- 所有 SLY PDF 模板（订单、形式发票、发货单、发票、采购订单）仅使用：
  - 标准 `$conf->global` 常量（如 `MAIN_PDF_MARGIN_*`、`MAIN_USE_FPDF` 等）
  - 标准 `ExtraFields` 与 `$object->array_options`
  - 核心已有的 `$object->tracking_number`（发货单）
- 只需在 **设置** 中为对应文档类型选择 SLY 模板即可，无需改核心代码。

---

## 二、ShipsGo：最小补丁列表

### 1. 数据库：发货单扩展表增加 ShipsGo 用列

官方 14.0 的 `llx_expedition_extrafields` 只有 `rowid, tms, fk_object, import_key`。ShipsGo 的 cron 与卡片页会读写以下列，需在**安装或升级后执行一次**以下 SQL（按需调整表前缀 `llx_`）：

```sql
-- 在 llx_expedition_extrafields 上增加 ShipsGo 所需列（表前缀请按实际 MAIN_DB_PREFIX 修改）
ALTER TABLE llx_expedition_extrafields
  ADD COLUMN sailingstatusid INT NULL DEFAULT NULL,
  ADD COLUMN pol VARCHAR(64) NULL DEFAULT NULL,
  ADD COLUMN atd DATE NULL DEFAULT NULL,
  ADD COLUMN pod VARCHAR(64) NULL DEFAULT NULL,
  ADD COLUMN ata DATE NULL DEFAULT NULL,
  ADD COLUMN livemapurl VARCHAR(255) NULL DEFAULT NULL,
  ADD COLUMN updatedtime DATETIME NULL DEFAULT NULL,
  ADD COLUMN requestid INT NULL DEFAULT NULL,
  ADD COLUMN blno VARCHAR(64) NULL DEFAULT NULL;
```

说明：若你通过 Dolibarr 的「扩展字段」为发货单创建了同名字段（如 `sailingstatusid`），则实际列名可能是 `options_sailingstatusid`；此时要么保留上述**直接列**并在模块的 ShipsGo_Update 中继续使用 `sailingstatusid`，要么改为使用扩展字段的 `options_*` 列名并相应修改 `slycustom/class/ShipsGo_Update.class.php` 中的 SQL。

---

### 2. 核心文件补丁

以下补丁仅在需要 **ShipsGo 功能**时应用；若只使用 PDF，可跳过。

#### 2.1 `htdocs/expedition/card.php`

**目的**：发货单确认（validate）时调用 ShipsGo API 并回写 `expedition_extrafields`。

- **位置 1**：在 `require_once` 区域增加（约第 40 行后）：
  ```php
  require_once DOL_DOCUMENT_ROOT.'/slycustom/class/ShipsGo_API.class.php';
  ```

- **位置 2**：在发货单「确认」分支内、调用 `$object->valid($user)` 之后，增加 ShipsGo 提交与更新逻辑（参考 SLY14.0 的 `card.php` 中 “ShipsGo Posting Container Info” 一段）：
  - 若 `tracking_number`、发货方式、参考非空且尚未提交过（如无 `requestid`），则：
    - 调用 `ShipsGo_API::PostContainerInfo` 或 `PostContainerInfoWithBl`
    - 用返回的 `RequestId` 及 `updatedtime` 更新 `llx_expedition_extrafields`（`requestid`, `updatedtime`）。

这样在官方 14.0 上确认发货单时即可向 ShipsGo 提交柜号并写入扩展表。

#### 2.2 `htdocs/expedition/list.php`

**目的**：列表显示「订单」列、以及批量「更新 ShipsGo 状态」操作。

- **位置 1**：在 `require_once` 区域增加：
  ```php
  require_once DOL_DOCUMENT_ROOT.'/slycustom/class/ShipsGo_API.class.php';
  require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
  require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
  ```

- **位置 2**：在列表的 `$arrayfields` 中增加订单列，例如：
  ```php
  'c.ref' => array('label' => $langs->trans("Order"), 'checked' => 1),
  ```

- **位置 3**：在 SQL 中增加与订单的关联（LEFT JOIN `element_element` + `commande`），并在 SELECT 中增加 `c.rowid as orderid`, `c.ref as reforder`；在 WHERE 中支持按订单号筛选（如 `search_reforder`）。

- **位置 4**：在批量操作数组 `$arrayofmassactions` 中增加：
  ```php
  'updateships' => img_picto('', 'calendar', 'class="pictofixedwidth"').$langs->trans("UpdateShips"),
  ```

- **位置 5**：在 `if (GETPOST('confirmmassaction'...)` 之前增加对 `massaction === 'updateships'` 的处理：对选中的发货单循环，用 `ShipsGo_API::GetContainerInfo` 取状态，并 UPDATE `llx_expedition_extrafields` 的 `sailingstatusid`, `pol`, `atd`, `pod`, `ata`, `livemapurl`, `updatedtime`（逻辑与 `slycustom/class/ShipsGo_Update.class.php` 中 `updateships` 一致，可抽成共用方法减少重复）。

- **位置 6**：表头与行输出中增加「订单」列，显示 `$order->getNomUrl(1,'commande')`（其中 `$order->ref` 来自上面 SELECT 的 `reforder`）。

以上为最小必要改动，不改变其它列表行为。

---

### 3. 配置常量

- 在 **首页 → 配置 → 其它**（或对应「发货」设置页）中增加常量：
  - **API_KEY_SHIPSGO**：ShipsGo 的 API 密钥。

若该常量在官方 14.0 中不存在，可在数据库表 `llx_const` 中插入一条记录，或在配置界面添加相应输入项。

---

## 三、补丁应用顺序建议

1. 部署 **slycustom** 模块并启用（PDF 即可用）。
2. 若需要 ShipsGo：
   - 执行上述 **数据库** SQL（一次）。
   - 按上述说明打 **expedition/card.php** 和 **expedition/list.php** 的补丁。
   - 配置 **API_KEY_SHIPSGO**。
3. 在 **计划任务** 中配置 ShipsGo 定时更新（调用 `slycustom/class/ShipsGo_Update.class.php` 中的 `ShipmentStatus::updateships`），可选。

---

## 四、与官方升级的兼容性

- **PDF**：无核心修改，官方升级不影响。
- **ShipsGo**：仅修改 `expedition/card.php`、`expedition/list.php` 和一张扩展表结构；升级 14.x 小版本时需重新检查这两处是否有冲突，若有则合并或重打上述逻辑。
- 建议将上述两文件的改动做成**独立补丁文件**（如 `.patch`），每次升级后重新应用并解决冲突，以便长期维护。

---

## 五、可选：不打核心补丁时的 ShipsGo 使用方式

若暂时不想改核心文件：

- **Cron 更新**：仍可单独使用 `slycustom/class/ShipsGo_Update.class.php` 的 `ShipmentStatus::updateships` 做定时同步（需先执行数据库 SQL 并配置 API_KEY_SHIPSGO）。
- **提交到 ShipsGo**：需在确认发货单时手动调用外部脚本或通过其它方式触发 API，核心不会自动在确认时提交。
- **列表「订单」列与批量更新**：需通过 hook 在 `shipmentlist` 上实现（若官方 14.0 的 list 已提供足够 hook），或放弃这两项功能，仅保留 cron 更新。

以上即为「仅保留 PDF + ShipsGo」在官方 14.0 上的**最小核心补丁清单**。
