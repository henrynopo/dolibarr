# 采购订单默认付款条件：官方 14.0.5 与 22.0.4 对比

## 1. 直接新建采购订单（无 origin，从供应商卡片点「新建采购订单」）

### 14.0.5（可正确读取供应商默认付款条件）

```php
$societe = '';
if ($socid > 0) {
    $societe = new Societe($db);
    $societe->fetch($socid);
}

if (!empty($origin) && !empty($originid)) {
    // ... 从其他对象创建
} else {
    // 直接新建：直接使用 $societe 的供应商字段，不做 !empty() 判断
    $cond_reglement_id  = $societe->cond_reglement_supplier_id;
    $mode_reglement_id  = $societe->mode_reglement_supplier_id;
    // ...
}
```

- 不检查 `fetch()` 返回值；未检查 `$societe` 是否为对象。
- 直接赋值：`$societe->cond_reglement_supplier_id`（包括 0/null 也会被赋上）。
- 当从供应商卡片带 `socid` 进入时，`$societe` 一定在上一段被 `fetch($socid)` 过，因此能读到供应商默认付款条件。

### 22.0.4 官方（可能出现读不到的情况）

```php
$societe = '';
$objectsrc = null;

if ($socid > 0) {
    $societe = new Societe($db);
    $societe->fetch($socid);   // 未检查返回值
}

if (!empty($origin) && !empty($originid)) {
    // ...
} else {
    // 直接新建：用 !empty() 判断，且未对 $societe 做 is_object 保护
    $cond_reglement_id  = !empty($societe->cond_reglement_supplier_id) ? $societe->cond_reglement_supplier_id : 0;
    $mode_reglement_id  = !empty($societe->mode_reglement_supplier_id) ? $societe->mode_reglement_supplier_id : 0;
    // ...
}
```

- 若 `fetch($socid)` 失败，`$societe` 仍是 Societe 实例但可能未正确加载，或某些环境下 `$societe` 可能被置空，后续访问 `$societe->cond_reglement_supplier_id` 会报错或得到 0。
- 使用 `!empty($societe->...)` 时，若 `$societe` 不是有效对象，会先触发“读 null/非对象”的警告再得到 0，导致“无法成功读取供应商默认付款条件”。

**结论（直接新建）**  
14.0.5 在“有 socid 时”逻辑简单且始终用 `$societe` 赋值；22.0.4 在 fetch 失败或 `$societe` 非对象时缺少保护，容易读不到供应商默认付款条件。

---

## 2. 从其他对象创建（如供应商报价 supplier_proposal）

### 14.0.5

```php
$soc = $objectsrc->client;   // 第三方用属性名 "client"
$cond_reglement_id = (!empty($objectsrc->cond_reglement_id) ? $objectsrc->cond_reglement_id
    : (!empty($soc->cond_reglement_id) ? $soc->cond_reglement_id : 0));
$mode_reglement_id = (!empty($objectsrc->mode_reglement_id) ? $objectsrc->mode_reglement_id
    : (!empty($soc->mode_reglement_id) ? $soc->mode_reglement_id : 0));
```

- 回退使用的是 **客户** 付款条件：`$soc->cond_reglement_id` / `$soc->mode_reglement_id`（销售用）。
- 对采购订单而言，更合理的是供应商默认：`cond_reglement_supplier_id` / `mode_reglement_supplier_id`。

### 22.0.4 官方

```php
$soc = $objectsrc->thirdparty;   // 第三方改为 "thirdparty"
$cond_reglement_id = (!empty($objectsrc->cond_reglement_id) ? $objectsrc->cond_reglement_id
    : (!empty($soc->cond_reglement_id) ? $soc->cond_reglement_id : 0));
$mode_reglement_id = (!empty($objectsrc->mode_reglement_id) ? $objectsrc->mode_reglement_id
    : (!empty($soc->mode_reglement_id) ? $soc->mode_reglement_id : 0));
```

- 同样用 `$soc->cond_reglement_id` / `$soc->mode_reglement_id`（客户字段），未用供应商字段。

**结论（从其他对象创建）**  
14.0.5 与 22.0.4 在这段都是“用客户付款条件做回退”；22.0.4 上我们已改为用 `cond_reglement_supplier_id` / `mode_reglement_supplier_id`，与采购场景一致。

---

## 3. 22.0.4 新增的「从销售订单(commande)创建采购订单」分支

22.0.4 多了一段 `if ($origin == "commande")`：

- 这里用 `$societe = $object->thirdparty` 和 `cond_reglement_supplier_id`，逻辑正确。
- 14.0.5 没有这段，从销售订单创建时走的是上面“从其他对象”的通用分支（用客户付款条件）。

---

## 4. 表单隐藏字段 remise_percent

- **14.0.5**：`value="'.$soc->remise_supplier_percent.'"`  
  在“直接新建”（无 origin）时，当前分支未定义 `$soc`，会用到未定义变量。
- **22.0.4**：同样用 `$soc->remise_supplier_percent`，在无 origin 时 `$soc` 也未定义。  
  我们已改为：有 `$soc` 用 `$soc`，否则用 `$societe->remise_supplier_percent`，避免未定义变量并兼容直接新建。

---

## 5. 我们在 SLY 22.0.4 上已做的对齐与修正

| 项目 | 处理方式 |
|------|----------|
| fetch 失败时仍把 `$societe` 当有效对象用 | `fetch($socid) <= 0` 时置 `$societe = ''`，避免误用失败加载结果。 |
| 直接新建时访问 `$societe->cond_reglement_supplier_id` 未做保护 | 使用 `is_object($societe) && !empty($societe->cond_reglement_supplier_id)`，并在“有 socid 但付款条件仍为空”时用新 Societe 再 `fetch($socid)` 一次，确保从 DB 读到供应商默认。 |
| 从其他对象创建时回退用客户付款条件 | 改为回退用 `$soc->cond_reglement_supplier_id` / `mode_reglement_supplier_id`。 |
| 从供应商报价创建时优先用报价付款条件 | 提交时若为空且 origin=supplier_proposal，先从 SupplierProposal 取付款条件再回退供应商/全局。 |
| remise_percent 在无 origin 时用未定义 `$soc` | 改为按是否有 `$soc` 选择 `$soc` 或 `$societe` 的 remise_supplier_percent。 |

这样 22.0.4 在“从供应商卡片进入并直接新建采购订单”时的行为与 14.0.5 一致（能正确读取供应商默认付款条件），并修正了从其他对象创建时的字段与兜底逻辑。
