# SLY Custom 模块

将 SLY 独有功能以 Dolibarr **外部模块**形式提供，支持**官方 Dolibarr 14.0 与 22.0**。在官方版上启用本模块即可使用 SLY PDF、ShipsGo、列表来源订单列、语言选择器等；无需维护整份 SLY 分支。22.0 上配合少量 core 补丁可恢复多币种、linkedobject 等完整能力（见 `patches/APPLY-ON-22.md`、`patches/PATCHES-BY-MODULE.md`）。

## 安装

1. 将 `slycustom` 目录放在 Dolibarr 的 `htdocs/` 下（与 `core`、`commande` 等同级）。
2. 登录后台 → **首页** → **配置** → **模块/应用**，在“外部模块”中找到 **SLY Custom**，启用。

## 本模块已包含的功能

### 1. PDF 模板（开箱即用）

- **客户订单**：`pdf_sly_order`、`pdf_sly_proforma`
- **发货单**：`pdf_sly_packinglist`（装箱单）
- **客户发票**：`pdf_sly_invoice`
- **采购发票 / Debit Note**：`pdf_sly_debitnote`（SLY Debit Note，我们发给供应商的借记通知单；**请勿设为默认**，仅在需要向供应商发送时手动生成 PDF）
- **采购订单**：`pdf_cornas_SLY`

启用模块后，在 **设置** 中为对应文档类型选择上述模板即可。若发票或装箱单模板在设置页/下拉中不显示，请先**禁用** SLY Custom 再**重新启用**一次，以便将模板注册到系统中。

### 2. ShipsGo 物流（需配置）

- 类文件已放在 `slycustom/class/`（`ShipsGo_API.class.php`、`ShipsGo_Update.class.php`）。
- 发货单扩展字段（如 `tracking_number`、`sailingstatusid` 等）需在官方 14.0 的 **扩展字段** 中为“发货单”添加；若官方版本没有你当前使用的字段名，需自行添加或调整类内 SQL。
- 定时更新：可在 **首页 → 配置 → 计划任务** 中新增“方法”类型任务，类路径 `/slycustom/class/ShipsGo_Update.class.php`，类名 `ShipmentStatus`，方法 `updateships`（若类中提供可被 cron 调用的入口）。

### 3. SLY 导出入口

- 左侧菜单 **商业** 下会多出 **SLY Exports**，指向 `slycustom/exports/index.php`。
- **工具 (Tools)** 下为三级菜单：**SLY Export** → **SLY ALL-in-One** → 五个详情（SO Details、SO Invoice Details、Shipment Details、PO Details、PO Invoice Details）。若 22.0 上未打 `sly22.0-menu-parent-match.patch`，层级可能错位，打补丁后需清理残余菜单：见 **docs/MENU-CLEANUP.md**（界面停用再启用，或 CLI `cli_reset_sly_menus.php`）。
- 若你仍使用带 SLY 导出脚本的环境（如原 `htdocs/exports/export_all.php` 等），可从该页链接过去；若完全使用官方 14.0 且未复制这些脚本，需自行把需要的导出脚本复制到 `slycustom/exports/` 或其它可访问目录并在本模块中加链接。

### 4. 列表与界面（22.0 上启用即生效，14.0 需 core 支持）

- **列表来源订单列**：客户发票/订单、采购订单、供应商发票列表可显示「来源订单」列并筛选（由模块 hook 提供；22.0 上若打了 `patches/APPLY-ON-22.md` 中的列表列位置补丁则列紧跟在 Ref 后）。
- **语言选择器**：顶部菜单语言下拉、可选登录页语言选择；在 **设置 → 其它** 或 SLY Custom 设置中配置。
- **订单生成文档**：销售订单生成文档时可勾选「附加销售条款」并选模板（`builddoc_order.php`）。
- **Shipment 菜单位置**：发货单从「产品/服务」移至「商业」左侧菜单（仅 22.0，由 hook 实现）。

## 22.0 部署说明（策略 B 移植，零 core 补丁）

若部署在**官方 Dolibarr 22.0.x** 上，本模块采用**零 core 补丁**方式：

- **Phase 3（仪表盘 boxes）**：slycustom 提供 5 个替代 widget（订单多币种、待办 fk_user_done、生日 gmt、采购订单多币种、待收货 fa-dolly）。用户需在 **首页 → 配置 → 仪表盘** 中停用原 core widget、启用 SLY 的 widget。
- **Phase 4（ShipsGo）**：已迁入 slycustom 模块，零 core 补丁。

详见 **PORT-TO-22.md**（移植策略）、**patches/APPLY-ON-22.md**（22.0 补丁应用顺序）、**patches/PATCHES-BY-MODULE.md**（按模块索引与迁移缺口）。**从 14.0.5 自定义版到 22.0.4 的升级路径与变更整理**见 **docs/UPGRADE-14.0.5-TO-22.0.4.md**。**迁移全部功能**（CORE 清单中所有定制）见 **CORE-CUSTOMIZATIONS-FULL-LIST.md** 与 **patches/PATCHES-BY-MODULE.md**。

---

## 与官方 14.0 的兼容性说明

- **本模块只提供**：上述 PDF 模板、ShipsGo 类与导出入口、以及通过 Dolibarr 标准机制（模块、hooks）能实现的部分。
- **SLY14.0 中大量对核心文件的直接修改**（例如：多币种显示、列表列、工作流、付款逻辑、订单/发票列表来源订单等）**无法通过“仅安装本模块”在官方 14.0 上完整复现**，因为：
  - 官方核心没有为所有这些行为提供 hook/trigger 扩展点；
  - 若要在官方版上实现相同行为，只有两种选择：
    1. **在官方 14.0 上打少量核心补丁**，并随官方升级时手动合并或重打；
    2. **继续使用 SLY14.0 分支**，定期把官方 14.0 的修复合并进来（如 `git merge upstream/14.0`）。

建议做法：

- 若你**主要需要 SLY 的 PDF 与 ShipsGo**：使用官方 14.0 + 本模块即可，后续升级官方即可获得安全与 bug 修复。
- 若你**强依赖 SLY 对列表、多币种、工作流等的核心改动**：可保留 SLY14.0 分支，同时把本模块放入该分支的 `htdocs/slycustom`，以后逐步把能迁移的逻辑迁到模块中，减少对核心的直接修改，便于日后合并官方 14.0。

## 目录结构（简要）

```
slycustom/
├── README.md
├── admin/
│   └── setup.php
├── class/
│   ├── ShipsGo_API.class.php
│   └── ShipsGo_Update.class.php
├── core/
│   └── modules/
│       ├── modSlyCustom.class.php
│       ├── commande/doc/       # pdf_sly_order, pdf_sly_proforma
│       ├── expedition/doc/     # pdf_sly_packinglist
│       ├── facture/doc/        # pdf_sly_invoice
│       ├── supplier_order/doc/ # pdf_cornas_SLY
│       └── supplier_invoice/doc/ # pdf_sly_debitnote (SLY Debit Note，仅手动生成)
├── exports/
│   └── index.php
├── langs/
│   ├── en_US/
│   └── zh_CN/
└── temp/
```

## 文档索引

本目录下有多份 .md 说明（移植、补丁、core 最小化等）。各文件用途及去重说明见 **docs/README-MD-FILES.md**。

## 版本

- 模块版本：1.0.0
- 针对 Dolibarr：14.0.x（官方）
- 从 SLY14.0 抽取的 PDF 与 ShipsGo 逻辑与 SLY14.0 当前提交一致；后续可随 SLY 需求在模块内独立迭代。
