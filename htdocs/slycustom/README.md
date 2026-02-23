# SLY Custom 模块

将 SLY14.0 独有功能以 Dolibarr **外部模块**形式提供，便于在**官方 Dolibarr 14.0** 上直接采用官方版本，仅启用本模块即可使用 SLY 定制，无需维护整份 SLY14.0 分支。

## 安装

1. 将 `slycustom` 目录放在 Dolibarr 的 `htdocs/` 下（与 `core`、`commande` 等同级）。
2. 登录后台 → **首页** → **配置** → **模块/应用**，在“外部模块”中找到 **SLY Custom**，启用。

## 本模块已包含的功能

### 1. PDF 模板（开箱即用）

- **客户订单**：`pdf_eratosthene_SLY`、`pdf_proforma_SLY`
- **发货单**：`pdf_espadon_SLY_PL`（装箱单）
- **客户发票**：`pdf_sponge_SLY_consignee`
- **采购订单**：`pdf_cornas_SLY`

启用模块后，在 **设置** 中为对应文档类型选择上述模板即可。

### 2. ShipsGo 物流（需配置）

- 类文件已放在 `slycustom/class/`（`ShipsGo_API.class.php`、`ShipsGo_Update.class.php`）。
- 发货单扩展字段（如 `tracking_number`、`sailingstatusid` 等）需在官方 14.0 的 **扩展字段** 中为“发货单”添加；若官方版本没有你当前使用的字段名，需自行添加或调整类内 SQL。
- 定时更新：可在 **首页 → 配置 → 计划任务** 中新增“方法”类型任务，类路径 `/slycustom/class/ShipsGo_Update.class.php`，类名 `ShipmentStatus`，方法 `updateships`（若类中提供可被 cron 调用的入口）。

### 3. SLY 导出入口

- 左侧菜单 **商业** 下会多出 **SLY Exports**，指向 `slycustom/exports/index.php`。
- 若你仍使用带 SLY 导出脚本的环境（如原 `htdocs/exports/export_all.php` 等），可从该页链接过去；若完全使用官方 14.0 且未复制这些脚本，需自行把需要的导出脚本复制到 `slycustom/exports/` 或其它可访问目录并在本模块中加链接。

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
│       ├── commande/doc/   # pdf_eratosthene_SLY, pdf_proforma_SLY
│       ├── expedition/doc/ # pdf_espadon_SLY_PL
│       ├── facture/doc/    # pdf_sponge_SLY_consignee
│       └── supplier_order/doc/ # pdf_cornas_SLY
├── exports/
│   └── index.php
├── langs/
│   ├── en_US/
│   └── zh_CN/
└── temp/
```

## 版本

- 模块版本：1.0.0
- 针对 Dolibarr：14.0.x（官方）
- 从 SLY14.0 抽取的 PDF 与 ShipsGo 逻辑与 SLY14.0 当前提交一致；后续可随 SLY 需求在模块内独立迭代。
