# Core 修改完整审查与最小化方案

本文档对当前分支（SLY22.0）中**所有对 core（非 slycustom）的修改**进行完整审查，并给出**最小 core 修改**建议，便于对 operational 14.0 进行升级时只保留必要补丁。

---

## 一、当前 Core 修改总览

### 1.1 已由 sly22.0-*.patch 覆盖的修改

以下 core 文件当前被修改，且对应补丁已在 `patches/` 中，应用顺序见 `patches/APPLY-ON-22.md`。

| 补丁 | 涉及 core 文件 | 用途 | 是否可省略/迁入模块 |
|------|----------------|------|---------------------|
| **sly22.0-core-price-symbol** | core/lib/functions.lib.php | 金额显示：MAIN_CURRENCY_SYMBOL_BEFORE_VALUE（如 $123） | 不可迁入模块，price() 为全局函数 |
| **sly22.0-paiement-arrayfields** | compta/paiement/list.php | 传 arrayfields 给 doActions，供模块加列 | **建议保留**：否则 slycustom 无法在付款列表加列 |
| **sly22.0-fourn-paiement-arrayfields** | fourn/paiement/list.php | 同上，供应商付款列表 | **建议保留** |
| **sly22.0-remx-hooks** | comm/remx.php | 折扣拆分后触发 afterSplitDiscount | 可选：若不用 remx 扩展可省略 |
| **sly22.0-compta-multicurrency** | compta/facture/card.php, facture/tpl/linkedobjectblock.tpl.php, paiement/class/paiement.class.php | 发票多币种“已付”判定、linkedobject 多币种显示、付款自动关闭 | 业务需要多币种时**建议保留** |
| **sly22.0-commande** | commande/card.php, tpl/linkedobjectblock.tpl.php | 草稿改客户、订单 linkedobject 多币种 | 业务需要时保留 |
| **sly22.0-fourn-linkedobject** | fourn/commande, fourn/facture 的 linkedobjectblock.tpl.php | 采购/供应商发票 linkedobject 多币种 | 同上 |
| **sly22.0-comm-propal-linkedobject** | comm/propal/tpl/linkedobjectblock.tpl.php | 报价 linkedobject 多币种 | 同上 |
| **sly22.0-admin** | admin/debugbar.php, dict.php, mails_templates.php | 后台布局、字典错误输出、邮件模板图标 | 可按需保留或省略 |
| **sly22.0-api** | api/class/api*.php, api/index.php | 生产缓存目录、API 访问控制、文档/上传、CORS | 按安全/运维需求决定 |
| **sly22.0-misc-cron-other** | cron/*, delivery/class/delivery.class.php, don/class/don.class.php, public/cron/* | cron 预筛选、delivery 默认参数、don setPaid 等 | 可按需保留 |
| **sly22.0-other-modules** | adherents/*, contact/*, contrat/agenda.php | 会员卡/API/订阅、联系人、合同议程 | 按业务需要 |
| **sly22.0-bank-treso** | compta/bank/treso.php | 计划明细 Currency、余额对齐、金额不重复符号 | 多币种/银行计划需要时保留 |
| **sly22.0-invoice-list-source-order-position** | compta/facture/list.php | 在 Ref 后插入 hook，使「来源订单」列紧跟 Ref | **可省略**：不应用则列在列表末尾，slycustom 仍可显示 |
| **sly22.0-supplier-invoice-list-source-order-position** | fourn/facture/list.php | 同上，供应商发票列表 | **可省略** |
| **sly22.0-langs** | htdocs/langs/* (en_US, fr_FR, zh_CN) | SLY 翻译与权限键 | 建议保留或单独维护 |

### 1.2 未在 sly22.0 补丁中的 Core 修改（当前工作区额外修改）

以下文件在 git status 中为 **M**，但**没有**对应的 `sly22.0-*.patch`，属于直接对 core 的修改或历史遗留，升级 14.0 时需单独决定是否移植。

| 模块/路径 | 文件 | 说明 |
|-----------|------|------|
| admin | company.php | 公司信息页等，需对比 14.0 差异 |
| commande | list.php, stats/index.php, class/commandestats.class.php, tpl/linkedobjectblock.tpl.php | 可能含列表/统计/链接块修改 |
| compta | index.php, paiement/list.php（若与 patch 重复则以 patch 为准） | 首页、付款列表 |
| core | boxes/*, class/*, lib/*, tpl/* | 仪表盘、通用类、模板等 |
| expedition | card.php, list.php, class/*, stats/* | 发货单、ShipsGo 相关（部分已迁入 slycustom，可能剩列表/统计） |
| fourn | commande/list.php, facture/card.php, list.php, paiement/list.php | 采购订单/供应商发票列表与卡片 |
| install | mysql/migration/*, tables/* | 安装/升级 SQL |
| loan | card.php, class/*, payment/*, schedule.php | 贷款模块 |
| salaries | list.php, payments.php | 工资单 |
| societe | card.php, class/societe.class.php | 第三方卡片与类 |
| supplier_proposal | list.php | 供应商报价列表 |
| theme | eldy/global.inc.php, md/style.css.php | 主题 |

**建议**：对 14.0 升级时，优先以 **sly22.0-* 补丁 + slycustom 模块** 为主；上表文件可逐项对比 14.0 与当前差异，只移植**业务强依赖**部分，其余尽量用配置或 slycustom hook 替代。

---

## 二、最小 Core 补丁集（推荐）

目标：**在保证 SLY 核心业务的前提下，core 改动最少**。

### 2.1 必须保留（无法用模块替代）

| 补丁 | 原因 |
|------|------|
| sly22.0-core-price-symbol | 全局 `price()` 行为，无 hook 可替代 |
| sly22.0-paiement-arrayfields | 付款列表 doActions 需 arrayfields，否则模块无法加列 |
| sly22.0-fourn-paiement-arrayfields | 同上，供应商付款列表 |

### 2.2 多币种/发票/订单业务需要时保留

| 补丁 | 原因 |
|------|------|
| sly22.0-compta-multicurrency | 发票“已付/未付”按多币种余款、linkedobject 多币种、自动关闭 |
| sly22.0-commande | 草稿改客户、订单 linkedobject 多币种 |
| sly22.0-fourn-linkedobject | 采购/供应商 linkedobject 多币种 |
| sly22.0-comm-propal-linkedobject | 报价 linkedobject 多币种 |
| sly22.0-bank-treso | 银行计划多币种与显示 |

### 2.3 可选（按需应用）

| 补丁 | 说明 |
|------|------|
| sly22.0-remx-hooks | 仅当需要在折扣拆分后做扩展（审计/同步）时保留 |
| sly22.0-invoice-list-source-order-position | 不应用则「来源订单」列在列表末尾，功能仍在 |
| sly22.0-supplier-invoice-list-source-order-position | 同上 |
| sly22.0-admin | 后台 UI/行为，可按习惯决定 |
| sly22.0-api | 安全与运维策略 |
| sly22.0-misc-cron-other | cron/delivery/don 等行为 |
| sly22.0-other-modules | adherents/contact/contrat 等 |
| sly22.0-langs | 可单独维护为语言包补丁或不动 core 仅维护 slycustom 语言 |

### 2.4 最小应用顺序（仅必选 + 多币种）

若只做**最小 core**（必选 + 多币种相关）：

```bash
# 1. 货币符号
git apply --ignore-whitespace htdocs/custom/slycustom/patches/sly22.0-core-price-symbol.patch

# 2. 付款列表 arrayfields（模块加列）
git apply --ignore-whitespace htdocs/custom/slycustom/patches/sly22.0-paiement-arrayfields.patch
git apply --ignore-whitespace htdocs/custom/slycustom/patches/sly22.0-fourn-paiement-arrayfields.patch

# 3. 多币种与 linkedobject（按需）
git apply --ignore-whitespace htdocs/custom/slycustom/patches/sly22.0-compta-multicurrency.patch
git apply --ignore-whitespace htdocs/custom/slycustom/patches/sly22.0-commande.patch
git apply --ignore-whitespace htdocs/custom/slycustom/patches/sly22.0-fourn-linkedobject.patch
git apply --ignore-whitespace htdocs/custom/slycustom/patches/sly22.0-comm-propal-linkedobject.patch
# 可选：sly22.0-bank-treso.patch
```

其余补丁（remx、invoice/supplier 列表列位置、admin、api、misc、other-modules、langs）按业务与运维需要再追加。

---

## 三、slycustom 已实现的“零 core”能力

以下能力**已完全在 slycustom 模块内**实现，无需对 core 打补丁即可使用（仅需启用 slycustom 并配置）：

- **列表列**：发票/订单/采购订单/供应商发票的「来源订单」列（addListArrayFields + printFieldList* hooks）
- **发货单**：ShipsGo 更新、取消验证、批量更新（doActions/addMoreActionsButtons/formConfirm）
- **左侧菜单**：Shipment 从产品移到商业（menuLeftMenuItems）
- **订单生成文档**：「附加销售条款」选项（formBuilddocOptions + builddoc_order.php）
- **语言选择器**：顶部菜单与登录页（printTopRightMenu, getLoginPageOptions 等）
- **PDF 模板**：sly_invoice, sly_packinglist, sly_debitnote, cornas_SLY（module_parts models）

因此，**只要 core 提供少量 hook 注入点**（如 paiement/fourn paiement 的 arrayfields、remx 的 afterSplitDiscount），其余尽量用 slycustom 实现即可达到 core 最小化。

---

## 四、对 14.0 升级时的建议步骤

1. **确定基线**：以**官方 14.0** 为基线，保留或移植 **slycustom 模块**（含 hooks、PDF、ShipsGo、语言选择器等）。
2. **数据库**：仅执行 **sly22.0-expedition-extrafields.sql**（或 14.0 对应的 ShipsGo 表结构）。
3. **Core 补丁**：  
   - 14.0 上**没有**现成的 sly22.0-* 补丁（因针对 22.0 生成），需在 14.0 上**按逻辑移植**上述「最小补丁集」对应逻辑（见 PORT-TO-22.md 的策略 B）。  
   - 或：在 14.0 上使用 **sly14.0-*** 补丁（见 patches/README.md），但只应用与「最小集」对应的部分（如 core、compta、commande、fourn 中与多币种/arrayfields 相关的部分）。
4. **未在 patch 中的 core 文件**（§1.2）：逐文件对比 14.0 与当前 22.0 差异，只移植业务强依赖部分；其余考虑用配置或 slycustom 替代。
5. **语言包**：可单独维护 langs 或仅维护 slycustom 的 lang 文件，减少对 core langs 的修改。

按此方式，**core 修改最小化**，便于后续跟随官方 14.0/22.0 升级与合并。
