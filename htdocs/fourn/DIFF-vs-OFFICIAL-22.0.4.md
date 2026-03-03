# fourn 目录与官方 Dolibarr 22.0.4 的差异说明

对比基准：当前分支 (SLY22.0) vs 官方 tag `22.0.4`  
生成命令：`git diff 22.0.4 -- htdocs/fourn/`

---

## 涉及文件（共 12 个）

| 文件 | 变更类型 | 与 PO→INV 问题的关系 |
|------|----------|----------------------|
| `fourn/class/paiementfourn.class.php` | 多币种 | 无关 |
| `fourn/commande/card.php` | 兼容性 | 无关 |
| `fourn/commande/list.php` | Hook / 多币种 | 无关 |
| `fourn/commande/tpl/linkedobjectblock.tpl.php` | 模板 | 无关 |
| **`fourn/facture/card.php`** | **创建/折扣/多币种/修复** | **直接相关** |
| `fourn/facture/list.php` | Hook / 多币种合计 | 无关（不隐藏草稿） |
| `fourn/facture/paiement.php` | 付款页 | 无关 |
| `fourn/facture/tpl/linkedobjectblock.tpl.php` | 模板 | 无关 |
| `fourn/paiement/list.php` | 付款列表 | 无关 |
| `langs/en_US/errors.lang` | 新增 lang key | 配合修复 |
| `langs/zh_CN/errors.lang` | 新增 lang key | 配合修复 |
| `langs/fr_FR/errors.lang` | 新增 lang key | 配合修复 |

---

## 1. 官方 22.0.4 与当前版本在 PO→INV 上的差异

- **官方 22.0.4**：从采购订单创建供应商发票时，若 `$object->create($user)` 失败（返回 ≤0），**没有**对 `$id <= 0` 做专门处理，仍会执行 `commit()` 并 `header("Location: ...?id=".$id)`，可能跳转到 `?id=0` 或 `?id=-1`，导致**空白页**；且未创建草稿，返回列表也**看不到草稿**。
- **当前 SLY 版本**：在 `fourn/facture/card.php` 中已增加：
  - 从 order_supplier 创建时，若 `create()` 返回 `$id <= 0`：设置 `$error++` 并显示 `$object->error` / `$object->errors`。
  - 提交后仅在 `$id > 0` 时重定向；若 `$id` 为空或 ≤0，不重定向，保留在创建页并显示错误。

因此，**"跳转空白 + 返回看不见草稿"在官方 22.0.4 上也会出现**，属于同一逻辑缺陷；当前 SLY 版本通过上述修改已修复该表现。

---

## 2. fourn/facture/card.php 相对 22.0.4 的主要差异（按功能）

### 2.1 与 PO→INV 空白/草稿相关的修复（当前已有）

- **创建失败时不再错误跳转**
  - `$id = $object->create($user)` 之后：若 `$id <= 0`，则 `$error++` 并 `setEventMessages($object->error, $object->errors, 'errors')`。
  - 提交成功后：仅当 `$id > 0` 时才 `header("Location: ...?id=".$id)` 并 `exit`；否则保持 `$action = 'create'` 并显示错误（优先 `$object->error`，否则 `ErrorBadParameters`）。

### 2.2 新增修复（2026-03-03：针对仍然出现空白/无提示的情况）

经深入对比分析发现两处遗漏的 Bug，已修复：

#### Bug A：fetch 源订单（PO）失败时无错误消息
- **文件**：`fourn/facture/card.php`，约 L1443-1445
- **问题**：create 成功（`$id > 0`）后，`$srcobject->fetch(originid)` 读取源订单失败（返回 ≤0），旧代码仅 `$error++` 无任何 `setEventMessages`，导致 rollback 后页面看起来没有任何提示（感觉"空白"）。
- **修复**：在 `else { $error++; }` 块中补加 `setEventMessages($srcobject->error, $srcobject->errors, 'errors')`。

#### Bug B：rollback 后错误消息可能为空
- **文件**：`fourn/facture/card.php`，约 L1469-1475
- **问题**：`if ($error)` rollback 块只显示 `$object->error`，但在 Bug A 场景下 `$object` 是新建的 invoice 对象，其 `->error` 可能为空，导致 `setEventMessages(null, null, 'errors')` 无任何效果，页面感觉空白。
- **修复**：改为三级 fallback：
  1. 优先显示 `$object->error`（发票对象错误）
  2. 若空，显示 `$srcobject->error`（源订单对象错误，加 `isset()` 保护，因 `$srcobject` 只在 `$id > 0` 分支中定义）
  3. 若都为空，显示兜底消息 `ErrorCreateSupplierInvoiceFailed`

#### Bug C：ErrorCreateSupplierInvoiceFailed lang key 新增
- **文件**：`langs/en_US/errors.lang`、`langs/zh_CN/errors.lang`、`langs/fr_FR/errors.lang`
- **内容**：
  - EN：`Failed to create supplier invoice. Check the error log for details.`
  - ZH：`创建供应商发票失败，请检查错误日志获取详细信息。`
  - FR：`Échec de la création de la facture fournisseur. Veuillez consulter le journal des erreurs pour plus de détails.`

### 2.3 折扣（remise）相关（SLY 定制）

- **unlinkdiscount**
  - 官方：直接 `$discount->unlink_invoice()`。
  - 当前：仅在发票为草稿或已确认未付款时允许解绑，并校验 `$discount->fk_invoice_supplier == $object->id`，解绑后更新金额并可选重新生成 PDF。
- **setabsolutediscount**
  - 官方：有 id 时在页面直接应用折扣。
  - 当前：**创建表单（无 id）**下，若选择折扣则写入 session `pending_supplier_invoice_discount_remise_id`，并重定向回 `?action=create&socid=...&origin=...&originid=...`（**origin/originid 参数已保留**），在**发票真正创建成功后**再应用该折扣。

### 2.4 创建表单与展示（SLY 定制）

- 创建页"折扣"区块：按 `FACTURE_SUPPLIER_DEPOSITS_ARE_JUST_PAYMENTS` 等过滤可用折扣，并传入 `$discount_form_action`，使用 `object_discounts.tpl.php`。
- 已存在发票的折扣区块：根据关联的 `order_supplier` 预选同订单的 remise（`$preselected_remise_id_for_payment`），并设置 `$backtopage`。
- 多币种：金额列顺序、付款表多币种列、`$show_multicurrency_column`、已付时 `$multicurrency_resteapayer = 0` 等。

### 2.5 其他

- 多币种显示与计算、PDF 重新生成等与 PO→INV 空白/草稿无直接关系。

---

## 3. 其他 fourn 文件变更概要

- **paiementfourn.class.php**：多币种下按外币余额判断"已付清"并自动关闭（与客户付款逻辑一致）。
- **commande/card.php**：`$object->thirdparty->default_lang` 可能未定义时改用 `$defaultlang`，避免 PHP 8.2+ 告警。
- **commande/list.php**：在"Ref"后增加 Hook（`printFieldListOption` / `printFieldListTitle` / `printFieldListValue`），以及 `$totalarray['val_by_currency']`、传 `$object, $action` 给 hook。
- **facture/list.php**：`$arrayfields` 排序、Ref 后 Hook、`val_by_currency` 及多币种合计列；**未改状态筛选**，草稿显示逻辑与官方一致（列表会正常显示草稿发票）。
- **facture/paiement.php**、**paiement/list.php**、两个 **linkedobjectblock.tpl.php**：多为展示或列表增强，不改变 PO→INV 创建流程。

---

## 4. 结论与建议

- **空白页与"看不见草稿"的根本原因**：
  1. **原始缺陷**（官方 22.0.4 同样存在）：从 PO 创建供应商发票时，`create()` 失败后仍重定向到无效 id，导致空白；草稿未生成，列表中也无记录。
  2. **Bug A（已修复）**：create 成功但 fetch 源订单（PO）失败时，也没有任何错误提示，用户看到空白表单。
  3. **Bug B（已修复）**：rollback 时只显示 `$object->error`，但在 Bug A 场景下该字段为空，改为三级 fallback 确保必有消息显示。

- 若修复后**仍出现无法创建**，建议核查以下实际失败原因：
  1. 查看 PHP 错误日志（`/var/log/php_errors.log` 或 Dolibarr 的 `documents/dolibarr.log`）中的具体错误。
  2. 确认从 PO 进入创建页时 URL 带有 `origin=order_supplier&originid=PO的ID&socid=供应商ID`。
  3. 供应商发票列表状态筛选选"**草稿**"或"**全部**"，确认草稿是否真的未创建。
  4. 检查 `ref_supplier`（供应商账单参考号）字段 — 该字段为**必填**，若空则会报错返回创建页（此时应有错误提示，不是空白页）。
