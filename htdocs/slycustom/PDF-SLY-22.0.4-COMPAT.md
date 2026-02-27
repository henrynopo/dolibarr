# SLY 定制 PDF 模版与 Dolibarr 22.0.4 不兼容项说明

本文档列出 `slycustom` 下订单相关 PDF 模版（`pdf_sly_order`、`pdf_sly_proforma`）与官方 22.0.4 的差异与潜在不兼容点，便于后续对齐或迁移。

---

## 一、架构差异

| 项目 | 官方 22.0.4 | SLY 定制 |
|------|-------------|----------|
| 继承关系 | `pdf_proforma` → `pdf_eratosthene` → `ModelePDFCommandes` | `pdf_sly_proforma` → `pdf_sly_order` → `ModelePDFCommandes` |
| 说明 | SLY 未继承官方 `pdf_eratosthene`，而是完整复制并修改为 `pdf_sly_order`，因此不会自动获得官方后续对 eratosthene 的修改。 |

---

## 二、不兼容或需同步的内容

### 1. 配置项读取方式（22.x 已统一为 getDolGlobal*）

**官方 22.x** 使用：
- `getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10)`、`getDolGlobalString('PDF_USE_ALSO_LANGUAGE_CODE')` 等

**SLY** 仍使用旧写法：
- `$conf->global->MAIN_PDF_MARGIN_LEFT`、`$conf->global->PDF_USE_ALSO_LANGUAGE_CODE`
- `!empty($conf->multicurrency->enabled)` 而非 `isModEnabled('multicurrency')`

**影响**：在多公司/多配置环境下，22.x 的 `getDolGlobal*` 会按实体等正确解析；SLY 的 `$conf->global->...` 可能取不到或取错值，导致边距、多语言、多币种等行为与官方不一致。

**建议**：在 `pdf_sly_order.modules.php` 中全局将配置读取改为 `getDolGlobalInt` / `getDolGlobalString` / `getDolGlobalBool`，多币种判断改为 `isModEnabled('multicurrency')`。

---

### 2. `_pagehead()` 返回值（22.x 改为返回数组）

**官方 22.x**：
- `_pagehead()` 返回 `array('top_shift' => $top_shift, 'shipp_shift' => $shipp_shift)`
- 调用处：`$pagehead = $this->_pagehead(...); $top_shift = $pagehead['top_shift']; $shipp_shift = $pagehead['shipp_shift']; $tab_top = 90 + $top_shift + $shipp_shift;`

**SLY**：
- `_pagehead()` 仅返回 `$top_shift`（数值）
- 无 `shipp_shift`，`$tab_top = 90 + $top_shift`

**影响**：SLY 内部自洽，但若将来与官方共用同一调用链（例如从官方 `write_file` 调用 SLY 的 `_pagehead`），会因期望数组而拿到数值导致错误。当前 SLY 仅被自身 `write_file` 调用，故暂时能工作。

**建议**：为与 22.x 契约一致，可将 SLY 的 `_pagehead` 改为返回 `array('top_shift' => $top_shift, 'shipp_shift' => 0)`，并在 `write_file` 中按 `$pagehead['top_shift']`、`$pagehead['shipp_shift']` 使用，与官方一致。

---

### 3. `drawTotalTable()` 方法签名（22.x 增加第 6 个参数）

**官方 22.x**：
```php
protected function drawTotalTable(&$pdf, $object, $deja_regle, $posy, $outputlangs, $outputlangsbis = null)
```

**SLY**：
```php
protected function drawTotalTable(&$pdf, $object, $deja_regle, $posy, $outputlangs)
```

**影响**：SLY 在方法内自行创建 `$outputlangsbis`，未从参数传入。若将来由官方代码调用 SLY 的 `drawTotalTable` 并传入第 6 个参数，SLY 会忽略该参数，双语言显示可能不一致。当前仅 SLY 自身调用，功能正常。

**建议**：在 `pdf_sly_order` 和 `pdf_sly_proforma` 的 `drawTotalTable` 上增加第 6 个参数 `$outputlangsbis = null`，与官方签名一致，并在需要时使用传入的 `$outputlangsbis`。

---

### 4. 构造函数中缺少 22.x 新增属性

**官方 22.x** 在 `pdf_eratosthene` 中已有：
- `$this->corner_radius = getDolGlobalInt('MAIN_PDF_FRAME_CORNER_RADIUS', 0);`
- `$this->tva_array = array();`（与 `$this->tva` 配套）

**SLY**：
- 未设置 `corner_radius`
- 未设置 `tva_array`

**影响**：若 22.x 某处依赖 `corner_radius` 或 `tva_array`（例如圆角框、税额汇总），SLY 模版可能报未定义或行为不一致。

**建议**：在 `pdf_sly_order` 的构造函数中增加与官方相同的 `corner_radius`、`tva_array` 初始化，边距等继续建议改为 `getDolGlobalInt`。

---

### 5. 草稿水印与状态判断

**官方 22.x**：
- 在 `write_file` 中：`if ($object->statut == $object::STATUS_DRAFT && getDolGlobalString('COMMANDE_DRAFT_WATERMARK')) { $this->watermark = ... }`
- 使用类常量 `STATUS_DRAFT`，水印通过统一机制在后续绘制

**SLY**：
- 在 `_pagehead` 中：`if ($object->statut == 0 && (!empty($conf->global->COMMANDE_DRAFT_WATERMARK))) { pdf_watermark(...); }`
- 使用魔术数字 `0`，且水印在页头内直接调用 `pdf_watermark`

**影响**：若订单状态常量在 22.x 中调整，`statut == 0` 可能不再表示草稿；配置读取方式也与 22.x 不一致。

**建议**：改为 `$object->statut == $object::STATUS_DRAFT`，水印配置改为 `getDolGlobalString('COMMANDE_DRAFT_WATERMARK')`；若希望与官方行为完全一致，可考虑像官方一样在 `write_file` 里只设置 `$this->watermark`，由公共逻辑统一画水印。

---

### 6. 总表：行折扣与 TotalHTBeforeDiscount / TotalDiscount（22.x 有，SLY 无）

**官方 22.x** 的 `drawTotalTable` 中：
- 若存在行级折扣（`$total_discount_on_lines > 0`），会先输出「折前总 HT」和「折扣」两行（TotalHTBeforeDiscount、TotalDiscount），再输出 Total HT。
- 使用 `pdfGetLineTotalDiscountAmount()` 等计算行折扣。

**SLY**：
- 无 TotalHTBeforeDiscount / TotalDiscount 两行，直接输出 Total HT（并加上 `$object->remise`）。

**影响**：当订单存在按行折扣时，SLY 模版不会像 22.x 那样拆成「折前合计 + 折扣 = 净额」，仅显示含折扣后的总 HT，与官方版式不一致。

**建议**：若需与官方展示一致，可在 `pdf_sly_order::drawTotalTable()` 中参照官方 22.x 增加行折扣计算及 TotalHTBeforeDiscount、TotalDiscount 的绘制逻辑。

**已同步**：已在 `pdf_sly_order::drawTotalTable()` 中增加行折扣汇总（`pdfGetLineTotalDiscountAmount`）、`$total_discount_on_lines > 0` 时输出「折前总 HT」与「折扣」两行，Total HT 行位置按 `$index` 顺延，与 22.x 版式一致。

---

### 7. 语言包加载

**官方 22.x**：
- `write_file` 中：`$outputlangs->loadLangs(array("main", "dict", "companies", "bills", "products", "orders", "deliveries", "compta"));`
- `drawTotalTable` 内创建 `outputlangsbis` 时也加载了 `"compta"`

**SLY**：
- `write_file` 中未包含 `"compta"`：`array("main", "dict", "companies", "bills", "products", "orders", "deliveries")`
- `drawTotalTable` 内 `outputlangsbis` 未加载 `"compta"`

**影响**：若 PDF 中使用了来自 `compta` 的翻译键，SLY 模版可能显示键名或空白。

**建议**：在 SLY 的 `write_file`（以及若存在则包括 `drawTotalTable` 里的 loadLangs）中为 `outputlangs` / `outputlangsbis` 增加 `"compta"`，与官方一致。

---

### 8. write_file 中若干 22.x 行为未同步

- **dol_syslog**：官方在 `write_file` 开头有 `dol_syslog("write_file outputlangs->defaultlang=...");`，SLY 无，对排查问题略有影响。
- **FPDF 兼容**：官方用 `getDolGlobalInt('MAIN_USE_FPDF')`，SLY 用 `!empty($conf->global->MAIN_USE_FPDF)`，建议统一为 getDolGlobal。
- **nblines**：官方使用 `$nblines = (is_array($object->lines) ? count($object->lines) : 0);`，SLY 使用 `$nblines = count($object->lines);`，在 `$object->lines` 非数组时可能告警或异常。
- **高度/背景/条款**：官方有 `MAIN_PDF_FREETEXT_HEIGHT`、`MAIN_ADD_PDF_BACKGROUND`、`MAIN_INFO_ORDER_TERMSOFSALE`、`MAIN_PDF_ADD_TERMSOFSALE_ORDER` 等逻辑，SLY 可能缺失或写法不同，需逐项对照。
- **Incoterm**：官方在 write_file 中有 `isModEnabled('incoterm')` 及 `$object->getIncotermsForPDF()` 等处理，SLY 需确认是否已同步。
- **getMultidirOutput**：官方 logo 路径使用 `getMultidirOutput($mysoc, 'mycompany')`，SLY 使用 `$conf->mycompany->multidir_output[$object->entity]`，在多实体下可能与官方行为不一致。

建议按需逐项对照官方 `write_file`，将上述逻辑与 getDolGlobal / isModEnabled 一起纳入同步范围。

---

### 9. defineColumnField：列定义与配置

**官方 22.x**：
- 存在 `cols['position']`（含 `PDF_ERATOSTHENE_ADD_POSITION` / `PDF_ERATOSHTENE_ADD_POSITION` / `PDF_ADD_POSITION` 等配置）。
- 列显示与 getDolGlobal*、getDolGlobalBool 等挂钩（如 `PDF_ORDER_HIDE_PRICE_EXCL_TAX`、`PDF_ORDER_SHOW_PRICE_INCL_TAX`）。

**SLY**：
- 无 `cols['position']`。
- 部分列仍用 `$conf->global->...` 或固定值（如 `PDF_PROPAL_SHOW_PRICE_INCL_TAX`）。

**影响**：列是否显示、是否显示位置列等与官方不一致，且配置方式不统一。

**建议**：若需与 22.x 列布局一致，可补充 `position` 列及对应 getDolGlobal 配置，其余列配置也改为 getDolGlobal* / getDolGlobalBool。

---

### 10. pdf_sly_proforma：drawTotalTable 中 outputlangsbis

`pdf_sly_proforma::drawTotalTable()` 内使用了 `$outputlangsbis`（用于 Prepayment 行的双语言标签），但方法签名未接收该参数，当前依赖父类或本方法内某处对 `$outputlangsbis` 的赋值。父类 `pdf_sly_order::drawTotalTable()` 在内部自行创建了 `$outputlangsbis`，未通过参数传入，因此子类中若未再次定义，可能依赖父类执行顺序或全局变量。建议在子类 `drawTotalTable` 中显式增加 `$outputlangsbis = null` 参数，并在需要时从参数或父类逻辑获取，避免隐式依赖。

---

## 三、其他 SLY PDF 模版（未在本文逐行对比）

以下模版同样可能存在与 22.0.4 的配置读取、API 签名、多币种/多语言等差异，建议按需做类似对比：

- `slycustom/core/modules/supplier_order/doc/pdf_cornas_SLY.modules.php`
- `slycustom/core/modules/facture/doc/pdf_sly_invoice.modules.php`
- `slycustom/core/modules/expedition/doc/pdf_sly_packinglist.modules.php`

---

## 四、建议优先级（仅作参考）

| 优先级 | 项 | 说明 |
|--------|----|------|
| 高 | 配置读取统一为 getDolGlobal* / isModEnabled | 影响多环境、多实体下行为正确性 |
| 高 | 多币种判断 isModEnabled('multicurrency') | 与核心行为一致 |
| 中 | _pagehead 返回数组 + write_file 使用 top_shift/shipp_shift | 与 22.x 契约一致，便于后续合并或复用 |
| 中 | drawTotalTable 增加第 6 参数 $outputlangsbis | 签名与官方一致，避免将来调用错误 |
| 中 | 构造函数补充 corner_radius、tva_array | 避免未定义与后续功能扩展问题 |
| 中 | 草稿状态用 STATUS_DRAFT，水印配置 getDolGlobal | 与 22.x 语义一致 |
| 低 | 总表行折扣与 TotalHTBeforeDiscount/TotalDiscount | 仅当需要与官方版式完全一致时再做 |
| 低 | loadLangs 增加 "compta"、write_file 细节与列定义 | 按需逐步对齐 |

以上内容基于对当前仓库中 SLY 订单 PDF 与官方 22.0.4 的对比整理，实际合并时请以官方最新代码为准再核对一遍。
