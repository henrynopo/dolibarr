# 按功能模块索引：14.0→22.0 补丁与迁移缺口

本文档按**功能模块**整理 SLY 14.0 与 22.0 的补丁对应关系、数据库变更、slycustom 已实现部分及**迁移缺口**，便于排查「某功能在 22.0 对应哪几个 patch」以及「14.0 有而 22.0 未覆盖」的项。

**应用补丁**：22.0 上按顺序执行见 [APPLY-ON-22.md](APPLY-ON-22.md)。**最小 core** 建议见上一级 [CORE-MINIMAL-REVIEW.md](../CORE-MINIMAL-REVIEW.md) §2.4。

---

## 一、模块与补丁对应表（总览）

| 模块 | 14.0 补丁 | 22.0 补丁（多个则按应用顺序） |
|------|-----------|------------------------------|
| core | sly14.0-core | sly22.0-core-price-symbol, sly22.0-menu-parent-match |
| compta | sly14.0-compta | sly22.0-compta-multicurrency, sly22.0-paiement-arrayfields, sly22.0-bank-treso, sly22.0-invoice-list-source-order-position |
| commande | sly14.0-commande | sly22.0-commande |
| fourn | sly14.0-fourn | sly22.0-fourn-paiement-arrayfields, sly22.0-fourn-linkedobject, sly22.0-supplier-invoice-list-source-order-position |
| comm | sly14.0-comm | sly22.0-remx-hooks, sly22.0-comm-propal-linkedobject |
| expedition | sly14.0-expedition-shipsgo | （仅 SQL + slycustom）sly22.0-expedition-extrafields.sql |
| admin | sly14.0-accountancy-admin-api（部分） | sly22.0-admin |
| api | 同上 | sly22.0-api |
| misc | sly14.0-misc | sly22.0-misc-cron-other |
| other-modules | sly14.0-other-modules | sly22.0-other-modules |
| langs | sly14.0-langs | sly22.0-langs |

---

## 二、各模块详情

### 1. core

- **功能简述**：全局金额显示（货币符号前置）、lib、boxes、triggers、list_print_total、html.form 等。
- **14.0**：`sly14.0-core.patch`
- **22.0**：`sly22.0-core-price-symbol.patch`（仅移植了 `price()` 的 MAIN_CURRENCY_SYMBOL_BEFORE_VALUE，其余未移植）；`sly22.0-menu-parent-match.patch`（左侧菜单父节点只取第一个匹配，支持 Tools 下 SLY Export 三级菜单）。
- **数据库**：无。
- **slycustom**：无（core 为全局基础）。
- **迁移缺口**：14.0 的 boxes、lib、triggers、list_print_total、html.form 等仅在 14.0 有；22.0 只移植了 price 符号。若需 boxes 多币种等，需按逻辑从 sly14.0-core 摘取并生成新 22.0 补丁或迁入 slycustom。

---

### 2. compta

- **功能简述**：发票/付款多币种「已付」判定、linkedobject 多币种显示、付款自动关闭、付款列表 arrayfields、银行计划明细、客户发票列表「来源订单」列位置。
- **14.0**：`sly14.0-compta.patch`
- **22.0**（按应用顺序）：
  1. `sly22.0-paiement-arrayfields.patch`（与 commande 前先打，因 doActions 需 arrayfields）
  2. `sly22.0-compta-multicurrency.patch`
  3. `sly22.0-bank-treso.patch`
  4. `sly22.0-invoice-list-source-order-position.patch`（可选，不打则列在末尾）
- **数据库**：无。
- **slycustom**：发票列表「来源订单」列的数据与筛选由 slycustom hook 提供；仅列位置需 core 的 invoice-list-source-order-position。
- **迁移缺口**：compta/index.php、paiement/list 除 arrayfields 外的改动（若有）可能未在 patch 中。

---

### 3. commande

- **功能简述**：客户订单草稿改客户、订单 linkedobject 多币种显示。
- **14.0**：`sly14.0-commande.patch`
- **22.0**：`sly22.0-commande.patch`
- **数据库**：无。
- **slycustom**：订单列表「来源采购订单」列由 slycustom hook 提供。
- **迁移缺口**：commande/list.php、stats/index.php、class/commandestats.class.php 等若在 14.0 有改动，当前 22.0 仅有 card + linkedobject 的 patch，未成独立 patch。

---

### 4. fourn

- **功能简述**：采购/供应商订单与发票的 linkedobject 多币种、供应商付款列表 arrayfields、供应商发票列表「来源订单」列位置。
- **14.0**：`sly14.0-fourn.patch`
- **22.0**（按应用顺序）：
  1. `sly22.0-fourn-paiement-arrayfields.patch`
  2. `sly22.0-fourn-linkedobject.patch`
  3. `sly22.0-supplier-invoice-list-source-order-position.patch`（可选）
- **数据库**：无。
- **slycustom**：采购订单/供应商发票列表「来源订单」列由 slycustom hook 提供。
- **迁移缺口**：fourn/commande/list.php、fourn/facture/card.php、fourn/facture/list.php 等若 14.0 有改，22.0 仅有 linkedobject + arrayfields + 列表列位置，可能还有未覆盖的列表/卡片逻辑。

---

### 5. comm

- **功能简述**：报价 linkedobject 多币种、remx 折扣拆分后 hook（afterSplitDiscount）。
- **14.0**：`sly14.0-comm.patch`
- **22.0**（按应用顺序）：
  1. `sly22.0-remx-hooks.patch`
  2. `sly22.0-comm-propal-linkedobject.patch`
- **数据库**：无。
- **slycustom**：无（remx 业务逻辑在 core，hook 供模块扩展）。
- **迁移缺口**：若 14.0 有报价 card/list/API 其它改动，需对照 sly14.0-comm 逐项核对。

---

### 6. expedition

- **功能简述**：发货单、ShipsGo 集成（列表订单列、批量更新、取消验证等）。
- **14.0**：`sly14.0-expedition-shipsgo.patch`
- **22.0**：无 core 代码补丁；仅数据库 `sly22.0-expedition-extrafields.sql`（ShipsGo 扩展字段）。
- **数据库**：`sly22.0-expedition-extrafields.sql`（执行一次，表前缀按 MAIN_DB_PREFIX）。
- **slycustom**：ShipsGo 更新、取消验证、批量更新、列表订单列、左侧菜单移至商业等均在 slycustom 内实现（doActions、addMoreActionsButtons、formConfirm、menuLeftMenuItems、getShipmentListOrderJoin 等）。
- **迁移缺口**：expedition/card.php、list.php、class/*、stats/* 的 core 改动（若有）部分已迁至 slycustom，其余可能仍在工作区但未成 patch；若仅用 slycustom + SQL 即可满足，可不再补 core patch。

---

### 7. admin

- **功能简述**：后台 debugbar 布局、dict 错误输出、mails_templates 图标等。
- **14.0**：`sly14.0-accountancy-admin-api.patch`（admin 部分）
- **22.0**：`sly22.0-admin.patch`
- **数据库**：无。
- **slycustom**：无。
- **迁移缺口**：admin/company.php 在工作区有修改但无对应 sly22.0-* patch，需单独对比 14.0 决定是否移植或放弃。

---

### 8. api

- **功能简述**：生产模式缓存目录、API 访问控制、文档/上传简化、CORS/endpoint 规则。
- **14.0**：`sly14.0-accountancy-admin-api.patch`（api 部分）
- **22.0**：`sly22.0-api.patch`
- **数据库**：无。
- **slycustom**：无。
- **迁移缺口**：无（已按逻辑移植）。

---

### 9. misc

- **功能简述**：cron 预筛选与 processing=0、去掉 cron 删除确认、delivery generateDocument 默认参数、don setPaid 去掉 paid 等。
- **14.0**：`sly14.0-misc.patch`
- **22.0**：`sly22.0-misc-cron-other.patch`
- **数据库**：无。
- **slycustom**：无。
- **迁移缺口**：install、theme、main.inc 等若 14.0 有改且 22.0 仍需，需从 sly14.0-misc 摘取或单独维护。

---

### 10. other-modules

- **功能简述**：adherents（会员卡/API/订阅）、contact（联系人 update_extras 与 delete 顺序）、contrat（议程按钮）等；14.0 还含 product、projet、reception、societe、ticket、user、loan、salaries 等。
- **14.0**：`sly14.0-other-modules.patch`（或 sly14.0-other-modules-no-fournisseurs.patch）
- **22.0**：`sly22.0-other-modules.patch`（仅 adherents、contact、contrat）。
- **数据库**：无。
- **slycustom**：无。
- **迁移缺口**：loan、salaries、societe、supplier_proposal、theme、product、projet、reception、ticket、user 等在工作区有修改但无对应 sly22.0-* patch；需按业务优先级逐项对比 14.0，决定**必须移植**（生成新 patch）、**迁入 slycustom** 或**暂不移植**。

---

### 11. langs

- **功能简述**：en_US、fr_FR、zh_CN 等 SLY 翻译与权限键对齐。
- **14.0**：`sly14.0-langs.patch`
- **22.0**：`sly22.0-langs.patch`（已排除 22.0 不存在的 zh_CN 文件）；应用时若有冲突用 `--3way` 并保留 SLY 翻译。
- **数据库**：无。
- **slycustom**：slycustom 自身语言在 `slycustom/langs/`。
- **迁移缺口**：无（按 22.0 键合并即可）。

---

## 三、14.0→22.0 未覆盖项（迁移缺口汇总）

以下为 14.0 或当前工作区存在、但**未**形成 sly22.0-* 补丁的 core 修改；近期修修补补多与此类缺口有关。

| 模块/路径 | 文件/范围 | 建议 |
|-----------|-----------|------|
| **core** | boxes/*, class/*, lib/*（除 price 外）, tpl/* | 按需：若仍需多币种 boxes 等，从 sly14.0-core 按逻辑移植或生成 sly22.0-core-*.patch；否则可**暂不移植**或迁入 slycustom（若 hook 支持）。 |
| **commande** | list.php, stats/, commandestats.class.php | 若 14.0 有列表/统计逻辑，可**必须移植**（新 patch）或确认已由 slycustom hook 覆盖则**不移植**。 |
| **compta** | index.php, paiement/list 除 arrayfields 外 | 对比 14.0 差异，按需**必须移植**或**暂不移植**。 |
| **fourn** | commande/list, facture/card, facture/list 除已有 patch 外 | 同上。 |
| **expedition** | card/list, class/*, stats/* 的 core 改动 | 大部分已在 slycustom；若有残留 core 改动且必要，可**必须移植**或并入 slycustom。 |
| **admin** | company.php | 对比 14.0，按需**必须移植**或**暂不移植**。 |
| **install** | mysql/migration/*, tables/* | 仅移植 22.0 仍需要的表/迁移；与官方 22.0 冲突处以官方为准。 |
| **loan** | card.php, class/*, payment/*, schedule.php | 按业务**必须移植**（新 patch）或**暂不移植**。 |
| **salaries** | list.php, payments.php 等 | 同上。 |
| **societe** | card.php, class/societe.class.php | 同上。 |
| **supplier_proposal** | list.php | 同上。 |
| **theme** | eldy/global.inc.php, md/style.css.php | 按需**必须移植**或**暂不移植**。 |

处理策略缩写：**必须移植** = 生成新 sly22.0-* 或并入现有模块 patch；**迁入 slycustom** = 用 hook/override 在模块内实现；**暂不移植** = 文档注明，后续按需再处理。

---

## 四、22.0 推荐应用顺序（按模块）

在**干净官方 22.0** 源码根目录（与 htdocs 同级）按以下顺序应用。完整可复制命令见 [APPLY-ON-22.md](APPLY-ON-22.md)。

| 顺序 | 模块 | 22.0 补丁 / SQL |
|------|------|-----------------|
| 1 | core | sly22.0-core-price-symbol.patch |
| 2 | compta（arrayfields 先） | sly22.0-paiement-arrayfields.patch |
| 3 | comm | sly22.0-remx-hooks.patch |
| 4 | compta | sly22.0-compta-multicurrency.patch |
| 5 | commande | sly22.0-commande.patch |
| 6 | fourn | sly22.0-fourn-paiement-arrayfields.patch |
| 7 | fourn | sly22.0-fourn-linkedobject.patch |
| 8 | comm | sly22.0-comm-propal-linkedobject.patch |
| 9 | admin | sly22.0-admin.patch |
| 10 | api | sly22.0-api.patch |
| 11 | misc | sly22.0-misc-cron-other.patch |
| 12 | other-modules | sly22.0-other-modules.patch |
| 12b | compta | sly22.0-bank-treso.patch |
| 12c | compta | sly22.0-invoice-list-source-order-position.patch |
| 12d | fourn | sly22.0-supplier-invoice-list-source-order-position.patch |
| 13 | langs | sly22.0-langs.patch（建议 --3way） |
| — | expedition | **数据库**：sly22.0-expedition-extrafields.sql（执行一次） |

---

## 五、最小 Core 应用（仅必选 + 多币种）

若希望 **core 修改最少**，只应用以下模块对应补丁即可（详见 [CORE-MINIMAL-REVIEW.md](../CORE-MINIMAL-REVIEW.md) §2.4）：

- **core**：sly22.0-core-price-symbol.patch  
- **compta**：sly22.0-paiement-arrayfields.patch, sly22.0-fourn-paiement-arrayfields.patch, sly22.0-compta-multicurrency.patch  
- **commande**：sly22.0-commande.patch  
- **fourn**：sly22.0-fourn-linkedobject.patch  
- **comm**：sly22.0-comm-propal-linkedobject.patch  
- **数据库**：sly22.0-expedition-extrafields.sql  

其余（remx-hooks、bank-treso、列表列位置、admin、api、misc、other-modules、langs）按业务与运维需要再追加。
