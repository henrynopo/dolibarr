# 尚未实现功能中可在 slycustom 模块完成的部分

本文档对照 **CORE-CUSTOMIZATIONS-FULL-LIST** 与 **patches/PATCHES-BY-MODULE**，列出尚未实现的功能里**可在自定义模块中实现**的项（无需改 core），并标明实现方式（hook 名、可用方法）。尽量在 slycustom 中完成，以减少 core 补丁。

---

## 一、总览：可在模块中实现 vs 必须 core 补丁

| 功能类别 | 可在 slycustom 实现 | 必须 core 补丁 |
|----------|---------------------|----------------|
| **列表列与筛选** | ✅ 来源订单列、销售/采购代表列、订单号筛选、多币种列、排序（列显示） | 列表默认排序逻辑、部分 SQL 聚合 |
| **列表合计** | ✅ 通过 printFieldListFooter 注入合计行（需配合 Select/From 扩展 SQL） | 官方已有合计时无需重复 |
| **卡片按钮/表单** | ✅ 自定义按钮（addMoreActionsButtons）、确认框（formConfirm）、搜索项（formObjectOptions） | 卡片内核心业务逻辑（如草稿改客户） |
| **发票/付款展示** | ✅ 列表多币种列、付款列表多币种列（22.0 已支持时可仅扩展） | 贷项 $ 符号、按多币种判定已付、付款自动关闭逻辑 |
| **PDF/模板** | ✅ 已有 SLY 模板在模块内；可扩展 substitutions | 银行账号字体、State 翻译、合计表货币符号（若无 substitution 则需 core） |
| **API** | ⚠️ 若 22.0 提供 API 响应 hook 则可注入字段 | API 返回金额/联系人/付款日等逻辑 |
| **工作流/触发器** | ✅ 模块可注册触发器（如已用 SHIPPING_VALIDATE） | 无 |
| **remx 折扣拆分** | ✅ 22.0 已有完整拆分+多币种；可选补丁增加 afterSplitDiscount hook | 可选 sly22.0-remx-hooks.patch |
| **linkedobject 模板** | ❌ 通常为 .tpl.php 直接输出 | 多币种列、block 结构 |
| **常量/配置** | ❌ MAIN_CURRENCY_SYMBOL_BEFORE_VALUE 等 | core conf |
| **Cron 时区** | ❌ | core/install |

---

## 二、可在 slycustom 中实现的具体项与实现方式

### 2.1 列表：增加列、筛选、合计（均通过 list 页 hook）

22.0 以下列表页均支持 `printFieldListSelect`、`printFieldListFrom`、`printFieldListWhere`、`printFieldListTitle`、`printFieldListValue`、`printFieldListFooter` 等，**可在模块中**增加列或合计行，无需改 core。

| 列表页 | Hook 上下文名 | 可做功能 |
|--------|----------------|----------|
| 客户订单 list | `orderlist` | 来源采购订单列、销售代表列、订单号筛选、多币种列、合计行 |
| 客户发票 list | `invoicelist` | 来源订单列、多币种列（22.0 已有可扩展）、合计 |
| 客户付款 list | `paymentlist` | 多币种列、合计（22.0 已有可扩展） |
| 发货单 list | `shipmentlist` | 来源订单列、订单号筛选（当前批量 updateships 已在模块） |
| 采购订单 list | `supplierorderlist` | 来源销售订单列、采购代表列、多币种列、合计行 |
| 供应商发票 list | `supplierinvoicelist` | 多币种列、合计 |
| 供应商付款 list | `paymentsupplierlist` | 多币种列、合计 |
| 报价 list | `propallist` | 多币种列、合计、自定义列 |

**实现方式**：在 `actions_slycustom.class.php` 中实现：

- `printFieldListSelect`：追加 SQL SELECT（如订单 ref、多币种金额）。
- `printFieldListFrom`：追加 JOIN。
- `printFieldListWhere`：追加筛选（如订单号）。
- `printFieldListTitle`：输出表头。
- `printFieldListValue`：输出该行单元格。
- `printFieldListFooter`：输出合计行（若需自定义合计）。
- `printFieldListSearchParam`：输出搜索表单隐藏域或参数。

**模块需注册的 hook 上下文**（在 `modSlyCustom.class.php` 的 `hooks['data']` 中）：  
`orderlist`、`invoicelist`、`paymentlist`、`shipmentlist`、`supplierorderlist`、`supplierinvoicelist`、`paymentsupplierlist`、`propallist`。  
（已注册的保留，缺的补上。）

---

### 2.2 卡片：按钮、确认框、搜索项

| 页面 | Hook 上下文 | 可用方法 | 可做功能 |
|------|-------------|----------|----------|
| 发票 card | `invoicecard` | addMoreActionsButtons, formConfirm, formObjectOptions | 自定义按钮、确认框、搜索/筛选选项 |
| 订单 card | `ordercard` | addMoreActionsButtons, formConfirm | 自定义按钮、确认框（如草稿改客户的二次确认可在此加） |
| 发货单 card | `expeditioncard` | 已用 addMoreActionsButtons, formConfirm | 已实现 Update Ships、反确认 |
| 采购订单 card | `ordersuppliercard` | addMoreActionsButtons, formConfirm | 自定义按钮、确认框 |
| 供应商发票 card | `invoicesuppliercard` | addMoreActionsButtons, formConfirm | 自定义按钮、确认框 |

**说明**：「草稿订单改客户」的**业务逻辑**（改 fk_soc、校验）通常在 core；模块可在**同一操作**上挂 formConfirm 做二次确认或 addMoreActionsButtons 挂入口，具体改库逻辑若 core 无 hook 则仍需 core 补丁。

---

### 2.3 触发器（Triggers）

- 模块已使用：`SHIPPING_VALIDATE`（确认发货单时调 ShipsGo）。
- 可继续在 slycustom 的 triggers 目录下添加其它 **element** 的 trigger（如 `ORDER_CLOSE`、`INVOICE_VALIDATE` 等），在 22.0 支持的 trigger 列表中挂接，实现「发货关闭时关订单」等，**无需改 core**（只要 22.0 已有对应 trigger 事件）。

---

### 2.4 PDF / 模板

- **已做**：SLY 的 PDF 模板在 slycustom 的 `core/modules/.../doc/` 下，无需 core 补丁。
- **可做**：若 22.0 支持「按模块扩展 substitution」，可在模块中注册 substitution，实现银行账号字体、State 翻译、合计表货币符号等；若 22.0 无此类扩展点，则属 core 或需 core 补丁。

---

### 2.5 API

- 若 22.0 的 API 层提供「响应前 hook」或「字段注入」，可在模块中为订单/发票/付款等 API 增加多币种、联系人、付款日等字段。
- 需查 22.0 的 `api/` 与 `core/` 中是否暴露 `executeHooks`；若有，可在 slycustom 中挂接，**无需改 core**。

---

## 三、必须在 core 中实现或 22.0 已具备的项

- **贷项凭证 $ 符号**、**按多币种判定已付**、**付款自动关闭**：逻辑在 facture/paiement 类内部，无 hook 则需 core。→ **已做**：sly22.0-compta-multicurrency.patch + sly22.0-core-price-symbol.patch（启用 MAIN_CURRENCY_SYMBOL_BEFORE_VALUE）。
- **MAIN_CURRENCY_SYMBOL_BEFORE_VALUE**：常量在 conf，需 core。→ **已做**：sly22.0-core-price-symbol.patch（price() 中判断该常量）。
- **linkedobject 多币种**：多为 .tpl.php 直接输出，无 hook 则需 core。→ **已做**：发票/订单/报价/采购/供应商的 linkedobject 模板已打补丁（sly22.0-compta-multicurrency、sly22.0-commande、sly22.0-fourn-linkedobject、sly22.0-comm-propal-linkedobject.patch）。
- **remx 折扣拆分**：22.0 官方 comm/remx.php 已含拆分与多币种；可选应用 **sly22.0-remx-hooks.patch** 增加 `afterSplitDiscount` hook，便于 slycustom 扩展。
- **草稿订单改客户**：→ **已做**：sly22.0-commande.patch（客户选择去掉 status=1 限制）。
- **列表合计**：22.0 若已有多币种合计，模块仅需在需要时用 printFieldListFooter 做**补充**合计。
- **Cron 时区**、**install/upgrade**：core 或 install。→ 待做。

---

## 四、建议实现顺序（在 slycustom 内）

1. **补全 hook 上下文**：在 `modSlyCustom.class.php` 中增加 `supplierorderlist`、`supplierinvoicelist`、`paymentsupplierlist`、`propallist`，以及卡片 `ordersuppliercard`、`invoicesuppliercard`（若要在采购/供应商卡片上做按钮或确认框）。
2. **列表列**：在 `actions_slycustom` 中按需实现 `printFieldList*`，先做「来源订单列」「销售/采购代表列」等与 SLY14.0 行为一致的列。
3. **列表筛选**：用 `printFieldListWhere` + `printFieldListSearchParam` 做订单号等筛选。
4. **列表合计**：用 `printFieldListFooter`（及对应 Select/From）在需要时补充多币种或其它合计行。
5. **卡片**：按需为订单/发票/采购/供应商卡片加 addMoreActionsButtons 或 formConfirm。
6. **触发器**：按业务需要再挂接 22.0 已有的其它 trigger。

按上述顺序做，可**尽量在 slycustom 中完成**列表与卡片相关扩展，仅把「无 hook 的业务逻辑」留给 core 补丁。

---

## 五、已实现（零 core 补丁）

| 列表/页面 | 已实现内容 |
|-----------|------------|
| **invoicelist** | 「来源订单」列（sly_order_ref）：可选显示，链接到客户订单卡片。 |
| **orderlist** | 「来源采购订单」列（sly_source_supplier_order_ref）：通过 element_element（commande → order_supplier）+ commande_fournisseur JOIN，可选显示，链接到采购订单卡片。 |
| **supplierorderlist** | 「来源销售订单」列（sly_source_order_ref）：通过 element_element（order_supplier ← commande）+ commande JOIN，可选显示，链接到客户订单卡片。 |
| **supplierinvoicelist** | 「来源采购订单」列（sly_source_supplier_order_ref）：通过 element_element（invoice_supplier ← order_supplier）+ commande_fournisseur JOIN，可选显示，链接到采购订单卡片。 |
| **shipmentlist** | 批量 updateships、卡片 Update Ships/反确认（此前已实现）；22.0 核心已有订单列与 search_reforder 筛选。 |
| **remx 折扣拆分** | 22.0 官方 comm/remx.php 已含「拆分折扣」及多币种分配；可选应用 **sly22.0-remx-hooks.patch** 后，slycustom 可响应 `afterSplitDiscount` 做扩展。 |
| **卡片 hook** | invoicecard、ordercard、ordersuppliercard、invoicesuppliercard 已注册，可按需在 addMoreActionsButtons/formConfirm 中加按钮或确认框。 |

**说明**：**paymentlist**（客户付款列表）与 **paymentsupplierlist**（供应商付款列表）已通过 **sly22.0-paiement-arrayfields.patch**、**sly22.0-fourn-paiement-arrayfields.patch** 在 doActions 中传入 `&$arrayfields`，模块可注入可选列。

---

## 六、其余功能是否**必须** core 补丁？

**不必**全部用 core 补丁。按是否能在 22.0 的 hook/模块机制内实现，分为三类：

### 不必 core 补丁（可在 slycustom 或「小改 core」内完成）

| 类型 | 说明 |
|------|------|
| **列表列/筛选/合计** | 用现有 list hook（printFieldList*）在模块里加列、筛选、合计；仅 **paymentlist** 需 core 小改（doActions 传 `&$arrayfields`）才能加可选列。 |
| **卡片按钮/确认** | addMoreActionsButtons、formConfirm、formObjectOptions 在模块内实现；仅「草稿改客户」的**改库逻辑**若无 hook 才需 core。 |
| **触发器** | 模块可注册 22.0 已有 trigger（如 ORDER_CLOSE、INVOICE_VALIDATE），无需改 core。 |
| **PDF 模板** | SLY 模板已在模块内；若 22.0 有 substitution 扩展点，字体/State/货币符号也可在模块扩展。 |
| **API 扩展** | 若 22.0 的 API 层有响应 hook，可在模块内注入字段，无需改 core。 |
| **remx 折扣拆分** | 22.0 已有；扩展用可选 **sly22.0-remx-hooks.patch**。 |

### 必须 core 补丁（无 hook 或逻辑在 core 内部）— 多数已做

| 类型 | 说明 | 状态 |
|------|------|------|
| **发票/付款业务逻辑** | 贷项符号、按多币种判定已付、付款自动关闭 | ✅ 已做（sly22.0-compta-multicurrency.patch） |
| **全局常量/配置** | MAIN_CURRENCY_SYMBOL_BEFORE_VALUE | ✅ 已做（sly22.0-core-price-symbol.patch） |
| **linkedobject 多币种** | 发票/订单/报价/采购/供应商关联区块 | ✅ 已做（见 patches/APPLY-ON-22.md） |
| **草稿订单改客户** | commande/card 客户选择允许非 status=1 | ✅ 已做（sly22.0-commande.patch） |
| **Cron 时区、install/升级** | 在 core/install 中 | ⏳ 待做 |

### 可选 core 小改（一条线级别）— 已做

| 项目 | 改法 | 状态 |
|------|------|------|
| **paymentlist 加可选列** | compta/paiement/list.php 的 doActions 增加 `'arrayfields' => &$arrayfields` | ✅ sly22.0-paiement-arrayfields.patch |
| **paymentsupplierlist 加可选列** | fourn/paiement/list.php 同上 | ✅ sly22.0-fourn-paiement-arrayfields.patch |
| **remx 拆分后扩展** | 应用 **sly22.0-remx-hooks.patch** | ✅ 已提供 |

**结论**：其余功能里，**列表/卡片/触发器/PDF 模板/API（若有 hook）/remx** 可尽量在 slycustom 或少量 core 小改内完成；**必须** core 补丁的，主要是**发票与付款的内部业务逻辑**、**linkedobject 模板**、**全局配置与 install**。按 **§四** 的顺序先把「可在模块实现」的做完，可显著减少 core 补丁范围。

---

## 七、尚未迁入的功能清单

以下为 **CORE-CUSTOMIZATIONS-FULL-LIST** 中与 SLY 定制相关的项。**已通过 22.0 core 补丁迁入**的已标注 ✅，其余为待做或可选。

### 7.1 发票/付款（compta）

| 功能 | 说明 | 状态 |
|------|------|------|
| 贷项凭证 $ 符号 | 金额前符号（启用 MAIN_CURRENCY_SYMBOL_BEFORE_VALUE） | ✅ sly22.0-core-price-symbol.patch |
| 按多币种判定已付 | 已付状态按多币种金额判定 | ✅ sly22.0-compta-multicurrency.patch |
| 付款自动关闭 | 多币种付清后自动 setPaid | ✅ 同上 |
| 付款列表可选列 | doActions 传 arrayfields | ✅ sly22.0-paiement-arrayfields.patch |
| 付款卡片/实时合计多币种 | 卡片与合计展示 | 22.0 已部分支持，slycustom 可扩展 |
| 银行流水显示供应商付款 | 银行模块展示逻辑 | ⏳ 待做 |
| linkedobject 多币种 | 发票关联对象区块 | ✅ sly22.0-compta-multicurrency.patch |

### 7.2 客户订单（commande）

| 功能 | 说明 | 状态 |
|------|------|------|
| 草稿订单改客户 | 客户选择允许非 status=1 | ✅ sly22.0-commande.patch |
| linkedobject 多币种 | 订单关联对象区块 | ✅ 同上 |
| API 多币种与联系人 | 订单 API 返回字段 | ⏳ 待做（按需移植 api_orders） |

### 7.3 采购/供应商（fourn）

| 功能 | 说明 | 状态 |
|------|------|------|
| 采购代表列/筛选 | 列表列 | slycustom list hook 可扩展 |
| 列表合计（多币种等） | 合计行 | slycustom printFieldListFooter 可扩展 |
| 禁止生成 PDF | 采购订单无模板时不生成 | 22.0 已为 empty modele 时 return 0 |
| 供应商付款列表可选列 | doActions 传 arrayfields | ✅ sly22.0-fourn-paiement-arrayfields.patch |
| linkedobject 多币种 | 采购/供应商发票关联区块 | ✅ sly22.0-fourn-linkedobject.patch |

### 7.4 报价/商业（comm）

| 功能 | 说明 | 状态 |
|------|------|------|
| remx 拆分后 hook | afterSplitDiscount | ✅ sly22.0-remx-hooks.patch |
| 报价 linkedobject 多币种 | 报价关联区块 | ✅ sly22.0-comm-propal-linkedobject.patch |
| 报价列表/卡片/API 其它 | 除上述外 | ⏳ 按需移植 sly14.0-comm.patch |

### 7.5 核心与全局

| 功能 | 说明 | 状态 |
|------|------|------|
| MAIN_CURRENCY_SYMBOL_BEFORE_VALUE | 金额前显示货币符号 | ✅ sly22.0-core-price-symbol.patch |
| PDF 合计表货币符号 / 银行账号字体 / State 翻译 | 无 substitution 时需改 core 模板 | ⏳ 待做 |
| Cron 时区 | 计划任务时区 | ⏳ 待做 |
| CSRF 默认值等 | 若 SLY 有特殊默认 | ⏳ 待做 |

### 7.6 会计 / 后台 / API（accountancy、admin、api）— 待做

| 功能 | 说明 | 参考 |
|------|------|------|
| 会计模块定制 | 见 CORE 清单 §2.1 | sly14.0-accountancy-admin-api.patch |
| 后台/管理定制 | 见 §2.3 | 同上 |
| API 全局（联系人与付款日等） | 若 API 无 hook 则改 api/ | §2.4 |

### 7.7 其它模块 + 语言包 + 杂项 — 待做

| 功能 | 说明 | 参考 |
|------|------|------|
| adherents、contact、contrat、product、projet、reception、societe、ticket、user 等 | 按 CORE 清单 §2.11 按需移植 | sly14.0-other-modules.patch |
| 语言包 | 约 95 个 lang 文件 | sly14.0-langs.patch |
| install/构建/脚本、misc | 时区、main.inc 等 | sly14.0-misc.patch §4 |

### 7.8 可选 core 小改 — 已做

| 功能 | 改法 | 状态 |
|------|------|------|
| paymentlist 增加可选列 | compta/paiement/list.php doActions 传 arrayfields | ✅ sly22.0-paiement-arrayfields.patch |
| paymentsupplierlist 增加可选列 | fourn/paiement/list.php 同上 | ✅ sly22.0-fourn-paiement-arrayfields.patch |
| remx 拆分后 hook | 应用 **sly22.0-remx-hooks.patch** | ✅ 已提供 |

**汇总**：已迁入 **slycustom**（零 core）的为列表来源列、发货单 ShipsGo、仪表盘 9 个 widget。已迁入 **22.0 core 补丁**的为：发票/付款多币种与 linkedobject、草稿改客户、订单/报价/采购/供应商 linkedobject 多币种、货币符号前置、付款列表 arrayfields（客户+供应商）、remx hook。详见 **patches/APPLY-ON-22.md**「22.0 专用补丁完整列表与推荐应用顺序」。**待做**：accountancy/admin/API、其它模块、语言包、Cron 时区等，见 7.6、7.7 及 **patches/PATCHES-BY-MODULE.md**（迁移缺口一节）。
