# 在官方 22.0 上应用/移植 SLY 补丁

本目录下的 **sly14.0-*.patch** 是针对**官方 14.0** 生成的。在 **22.0** 上**不能直接** `git apply` 无冲突应用，需采用「按逻辑移植」或「apply 后逐冲突解决」。

**Core 修改最小化**：请阅读上一级目录的 **CORE-MINIMAL-REVIEW.md**，其中给出「最小 core 补丁集」与可省略项，便于 14.0 升级时只打必要补丁。  
**按功能模块对照与缺口**：请阅读本目录 **PATCHES-BY-MODULE.md**，按模块查看 14.0/22.0 补丁对应关系及 14.0→22.0 未覆盖项。

---

## 22.0 专用补丁完整列表与推荐应用顺序

在**干净官方 22.0** 源码根目录（与 htdocs 同级）按下列顺序应用 **sly22.0-*.patch**（均为针对 22.0 生成，可无冲突应用）：

```bash
# 1. 核心（货币符号）
git apply --ignore-whitespace htdocs/slycustom/patches/sly22.0-core-price-symbol.patch

# 1b. 左侧菜单三级结构：父节点只取第一个匹配（sly_export → sly_export_all → 5 个详情）
git apply --ignore-whitespace htdocs/slycustom/patches/sly22.0-menu-parent-match.patch

# 2. 客户付款列表 arrayfields
git apply --ignore-whitespace htdocs/slycustom/patches/sly22.0-paiement-arrayfields.patch

# 3. remx 折扣拆分 hook
git apply --ignore-whitespace htdocs/slycustom/patches/sly22.0-remx-hooks.patch

# 4. 发票/付款多币种与 linkedobject（compta）
git apply --ignore-whitespace htdocs/slycustom/patches/sly22.0-compta-multicurrency.patch

# 5. 客户订单草稿改客户 + linkedobject 多币种（commande）
git apply --ignore-whitespace htdocs/slycustom/patches/sly22.0-commande.patch

# 6. 供应商付款 list arrayfields
git apply --ignore-whitespace htdocs/slycustom/patches/sly22.0-fourn-paiement-arrayfields.patch

# 7. 采购/供应商 linkedobject 多币种（fourn）
git apply --ignore-whitespace htdocs/slycustom/patches/sly22.0-fourn-linkedobject.patch

# 8. 报价 linkedobject 多币种（comm）
git apply --ignore-whitespace htdocs/slycustom/patches/sly22.0-comm-propal-linkedobject.patch

# 9. 管理后台（admin）：debugbar 布局、dict 错误输出、mails_templates 图标、等
git apply --ignore-whitespace htdocs/slycustom/patches/sly22.0-admin.patch

# 10. API：生产模式缓存目录、禁用用户可调 API、文档 ECM/上传简化、CORS/endpoint 规则
git apply --ignore-whitespace htdocs/slycustom/patches/sly22.0-api.patch

# 11. Misc/Cron/其它（cron 预筛选与 processing=0、去掉 cron 删除确认、delivery generateDocument 默认参数、don setPaid 去掉 paid）
git apply --ignore-whitespace htdocs/slycustom/patches/sly22.0-misc-cron-other.patch

# 12. 其它模块（adherents、contact、contrat）：会员卡/API 删除/订阅表单、联系人 update_extras 与 delete 触发顺序、合同议程按钮
git apply --ignore-whitespace htdocs/slycustom/patches/sly22.0-other-modules.patch

# 12b. 银行计划明细（Upcoming entries）：Currency 列、当前/未来余额与 Balance 列对齐、金额不重复显示货币符号
git apply --ignore-whitespace htdocs/slycustom/patches/sly22.0-bank-treso.patch

# 12c. 客户发票列表：Source order 列紧跟在 Invoice No（Ref）后（需 slycustom 模块）
git apply --ignore-whitespace htdocs/slycustom/patches/sly22.0-invoice-list-source-order-position.patch

# 12d. 供应商发票列表：Source order 列紧跟在 Ref 后（需 slycustom 模块）
git apply --ignore-whitespace htdocs/slycustom/patches/sly22.0-supplier-invoice-list-source-order-position.patch
# 若「选择列」中仍无 Source order：Dolibarr 仅在模块激活时把 hook 上下文写入数据库。请 设置→模块→应用 中先停用 slycustom，再重新启用，以刷新 MAIN_MODULE_SLYCUSTOM_HOOKS（含 supplierinvoicelist）。

# 12e. 客户发票 PDF：旧模版 sponge_SLY_consignee 回退到系统默认（避免 Failed to load doc generator）
git apply --ignore-whitespace htdocs/slycustom/patches/sly22.0-facture-pdf-fallback.patch

# 13. 语言包（en_US、fr_FR、zh_CN）：SLY 翻译与权限键对齐；已排除 22.0 不存在的 zh_CN 文件（externalsite、ftp、link、zapier）
git apply --ignore-whitespace --3way htdocs/slycustom/patches/sly22.0-langs.patch
# 若有冲突，保留 SLY 翻译：在冲突块中保留 ======= 与 >>>>>>> 之间的内容后删除标记，或对 htdocs/langs 执行「接受传入」后 git add。
```

---

## 按模块查看（对应 PATCHES-BY-MODULE.md）

按功能排查问题时，可据此快速定位该模块涉及的 22.0 补丁：

| 模块 | 22.0 补丁文件 |
|------|----------------|
| **core** | sly22.0-core-price-symbol.patch, sly22.0-menu-parent-match.patch |
| **compta** | sly22.0-compta-multicurrency.patch, sly22.0-paiement-arrayfields.patch, sly22.0-bank-treso.patch, sly22.0-invoice-list-source-order-position.patch, sly22.0-facture-pdf-fallback.patch |
| **commande** | sly22.0-commande.patch |
| **fourn** | sly22.0-fourn-paiement-arrayfields.patch, sly22.0-fourn-linkedobject.patch, sly22.0-supplier-invoice-list-source-order-position.patch |
| **comm** | sly22.0-remx-hooks.patch, sly22.0-comm-propal-linkedobject.patch |
| **expedition** | 仅数据库：sly22.0-expedition-extrafields.sql（业务在 slycustom） |
| **admin** | sly22.0-admin.patch |
| **api** | sly22.0-api.patch |
| **misc** | sly22.0-misc-cron-other.patch |
| **other-modules** | sly22.0-other-modules.patch |
| **langs** | sly22.0-langs.patch |

---

## 最小 Core 应用（仅必选 + 多币种）

若希望 **core 修改最少**，可只应用以下补丁（详见上一级 **CORE-MINIMAL-REVIEW.md** §2.4）：

```bash
git apply --ignore-whitespace htdocs/slycustom/patches/sly22.0-core-price-symbol.patch
git apply --ignore-whitespace htdocs/slycustom/patches/sly22.0-paiement-arrayfields.patch
git apply --ignore-whitespace htdocs/slycustom/patches/sly22.0-fourn-paiement-arrayfields.patch
git apply --ignore-whitespace htdocs/slycustom/patches/sly22.0-compta-multicurrency.patch
git apply --ignore-whitespace htdocs/slycustom/patches/sly22.0-commande.patch
git apply --ignore-whitespace htdocs/slycustom/patches/sly22.0-fourn-linkedobject.patch
git apply --ignore-whitespace htdocs/slycustom/patches/sly22.0-comm-propal-linkedobject.patch
# 可选：sly22.0-bank-treso.patch、sly22.0-remx-hooks.patch、列表列位置、admin、api、misc、other-modules、langs
```

**数据库**：另需执行一次 **sly22.0-expedition-extrafields.sql**（ShipsGo 扩展表），见下方「数据库（22.0）」小节。

**说明**：**accountancy** 按约定不移植；admin/API 已从 14.0 补丁按逻辑移植到 22.0 并生成上述补丁。**其它模块**（adherents、contact、product、societe 等）因 22.0 与 14.0 差异大，**sly14.0-other-modules.patch** 无法整体 apply，需按 **CORE-CUSTOMIZATIONS-FULL-LIST.md** §2.11 逐文件参考移植。**语言包**：已生成 **sly22.0-langs.patch**（由 sly14.0-langs.patch 去掉 22.0 不存在的 4 个 zh_CN 文件后应用并解决冲突得到）。在 22.0 上应用步骤 13；若出现冲突，保留 SLY 翻译段后保存并 `git add htdocs/langs/`。

---

## 推荐：按逻辑移植（策略 B）

1. 打开 **CORE-CUSTOMIZATIONS-FULL-LIST.md**，找到要移植的模块（如 §2.7 compta）。
2. 对每个列出的文件，在 SLY14.0 仓库执行：  
   `git diff upstream/14.0..SLY14.0 -- htdocs/路径`
3. 在 22.0 的**对应文件**中手工实现同等逻辑（注意 22.0 的类名/API 可能已变）。
4. 测试通过后，可生成 22.0 专用补丁：  
   `git diff upstream/22.0 -- htdocs/模块/ > slycustom/patches/sly22.0-模块.patch`

---

## 可选：在 22.0 上尝试 apply 14.0 补丁

在**干净官方 22.0** 源码根目录（与 htdocs 同级）按**依赖顺序**尝试：

```bash
# 1. 核心（冲突可能很多）
git apply --ignore-whitespace --3way htdocs/slycustom/patches/sly14.0-core.patch

# 2. 业务模块
git apply --ignore-whitespace --3way htdocs/slycustom/patches/sly14.0-compta.patch
git apply --ignore-whitespace --3way htdocs/slycustom/patches/sly14.0-commande.patch
git apply --ignore-whitespace --3way htdocs/slycustom/patches/sly14.0-fourn.patch
git apply --ignore-whitespace --3way htdocs/slycustom/patches/sly14.0-comm.patch
git apply --ignore-whitespace --3way htdocs/slycustom/patches/sly14.0-accountancy-admin-api.patch
git apply --ignore-whitespace --3way htdocs/slycustom/patches/sly14.0-other-modules.patch
git apply --ignore-whitespace --3way htdocs/slycustom/patches/sly14.0-misc.patch

# 3. 语言包（可单独应用）
git apply --ignore-whitespace --3way htdocs/slycustom/patches/sly14.0-langs.patch
```

**注意**：  
- **不要**在 22.0 上应用 **sly14.0-expedition-shipsgo.patch**：ShipsGo 已迁入 slycustom 模块，零 core 补丁。  
- 若有冲突，根据报错逐个文件编辑解决后 `git add`；若冲突过多，建议改用上方「按逻辑移植」。

---

## 付款列表 arrayfields（22.0）

- **用途**：让付款列表页的 `doActions` hook 收到 `arrayfields` 引用，slycustom 等模块可在其中增加可选列（如多币种、来源等）。
- **补丁**：**sly22.0-paiement-arrayfields.patch**（仅改 `compta/paiement/list.php` 一行）。

```bash
git apply --ignore-whitespace htdocs/slycustom/patches/sly22.0-paiement-arrayfields.patch
```

---

## remx 折扣拆分（22.0）

- **功能**：22.0 官方 **comm/remx.php** 已包含「拆分折扣」(Split discount)：将一条客户/供应商全局折扣拆成两条、多币种按比例分配，无需从 14.0 移植业务逻辑。
- **可选补丁**：若需在拆分后由模块做扩展（审计、同步等），可应用 **sly22.0-remx-hooks.patch**，在 remx 中增加 `initHooks('remx')` 与 `executeHooks('afterSplitDiscount', ...)`。应用后 slycustom 的 `afterSplitDiscount` 会被调用。

```bash
git apply --ignore-whitespace htdocs/slycustom/patches/sly22.0-remx-hooks.patch
```

---

## 货币符号前置（22.0）

- **用途**：金额显示时货币符号在数字前（如 $123.00），适用于贷项等场景。在 Dolibarr **设置 → 其它** 中启用常量 **MAIN_CURRENCY_SYMBOL_BEFORE_VALUE** 后生效。
- **补丁**：**sly22.0-core-price-symbol.patch**（仅改 `core/lib/functions.lib.php` 的 `price()`）。

```bash
git apply --ignore-whitespace htdocs/slycustom/patches/sly22.0-core-price-symbol.patch
```

---

## 供应商付款列表 arrayfields（22.0）

- **用途**：让供应商付款列表页的 `doActions` hook 收到 `arrayfields` 引用，slycustom 等模块可增加可选列。
- **补丁**：**sly22.0-fourn-paiement-arrayfields.patch**（仅改 `fourn/paiement/list.php` 一行）。

```bash
git apply --ignore-whitespace htdocs/slycustom/patches/sly22.0-fourn-paiement-arrayfields.patch
```

---

## 数据库（22.0）

仅 ShipsGo 需额外表结构，执行**一次**：

- **sly22.0-expedition-extrafields.sql**（本目录下）

表前缀按 `MAIN_DB_PREFIX` 修改后执行，或通过 Dolibarr 安装/升级界面执行 SQL。
