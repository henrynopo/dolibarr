# SLY Custom 模块

将 SLY 独有功能以 Dolibarr **外部模块**形式提供，支持**官方 Dolibarr 14.0 与 22.0**。在官方版上启用本模块即可使用 SLY PDF、ShipsGo、列表来源订单列、语言选择器等；无需维护整份 SLY 分支。22.0 上配合少量 core 补丁可恢复多币种、linkedobject 等完整能力（见 `patches/APPLY-ON-22.md`、`patches/PATCHES-BY-MODULE.md`）。

## 安装

1. 将 `slycustom` 目录放在 Dolibarr 的 `htdocs/custom/` 下（即 `htdocs/custom/slycustom/`，与其它自定义模块同级）。
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

- 类文件已放在 `custom/slycustom/class/`（`ShipsGo_API.class.php`、`ShipsGo_Update.class.php`）。
- 发货单扩展字段（如 `tracking_number`、`sailingstatusid` 等）需在官方 14.0 的 **扩展字段** 中为“发货单”添加；若官方版本没有你当前使用的字段名，需自行添加或调整类内 SQL。
- 定时更新：模块激活时已注册 ShipsGo 定时任务（默认停用），到 **首页 → 配置 → 计划任务** 中启用即可；Wise 的"收款明细补全"任务同理（每 15 分钟，参数 `实体,每次处理条数`，如 `1,10`）。

### 3. SLY 导出入口

- 左侧菜单 **商业** 下会多出 **SLY Exports**，指向 `custom/slycustom/exports/index.php`。
- **工具 (Tools)** 下为三级菜单：**SLY Export** → **SLY ALL-in-One** → 五个详情（SO Details、SO Invoice Details、Shipment Details、PO Details、PO Invoice Details）。24.0.1 上 `sly24.0-menu-parent-match.patch` 已包含此 hook 点，无需手工打补丁；若菜单层级仍有错位，可参考历史 [archive/APPLY-ON-22.md](patches/archive/APPLY-ON-22.md) 思路，并清理残余菜单：见 **docs/MENU-CLEANUP.md**（界面停用再启用，或 CLI `cli_reset_sly_menus.php`）。
- 若你仍使用带 SLY 导出脚本的环境（如原 `htdocs/exports/export_all.php` 等），可从该页链接过去；若完全使用官方 14.0 且未复制这些脚本，需自行把需要的导出脚本复制到 `custom/slycustom/exports/` 或其它可访问目录并在本模块中加链接。

### 4. 列表与界面（22.0 上启用即生效，14.0 需 core 支持）

- **列表来源订单列**：客户发票/订单、采购订单、供应商发票列表可显示「来源订单」列并筛选（由模块 hook 提供；22.0 上若打了 `patches/APPLY-ON-22.md` 中的列表列位置补丁则列紧跟在 Ref 后）。
- **语言选择器**：顶部菜单语言下拉、可选登录页语言选择；在 **设置 → 其它** 或 SLY Custom 设置中配置。
- **订单生成文档**：销售订单生成文档时可勾选「附加销售条款」并选模板（`builddoc_order.php`）。
- **Shipment 菜单位置**：发货单从「产品/服务」移至「商业」左侧菜单（仅 22.0，由 hook 实现）。

### 5. Wise 收款集成（需配置）

设置入口：**设置 → 模块/应用 → SLY Custom 设置 → "Wise 收款" 标签**；核对入口：**工具 → Wise 收款核对**（需授权 slycustom 的 "Wise incoming payment reconciliation" 权限，管理员自动可访问页面）。

- **收纳入账**（`WISE_INCOMING_ENABLED`，默认开）：Wise `balances#credit` webhook 推送进入 `llx_slycustom_wise_incoming` 待核对队列；报文不含付款参考号，由系统调用对账单 API（±5 分钟窗口）回补参考号/对方/手续费；匹配引擎按 **SO 编号 → 关联客户发票**（`llx_element_element`）给候选；**核对页**（`wise/reconcile.php`）中人工勾选分摊、确认后生成付款 + 银行流水 + 自动关票；仅金额匹配的候选带警告标记。cron 每轮先做 2 天对账单兜底同步（找回被 4xx 丢弃的事件），再补明细。
- **付款准备**（`WISE_OUTGOING_ENABLED`，默认关）：供应商发票卡片出现 **"通过 Wise 付款"** → `wise/prepare.php` 预览（收款人 IBAN、金额币种、**发给供应商的参考号 = 供应商自己的订单号**，优先级 PO ref_supplier → 发票 ref_supplier → 我方 ref）→ 确认创建**未注资**转账（幂等 uuid 防重复）；在 **Wise 后台人工注资 = 审批**（5 个工作日未注资自动取消）；`transfers#state-change` 推送（需另订阅）或页面手动刷新推进状态，`outgoing_payment_sent` 自动生成供应商付款 + 银行流水 + 自动关票（webhook 丢失时页面有手动兜底按钮）。映射表 `llx_slycustom_wise_transfer`。
- 接收端 `custom/slycustom/webhook/wise.php`（无登录；RSA-SHA256 验签公钥存 `DOL_DATA_ROOT/wise_webhook/verification_key.pem`，拿不到公钥时启用"来源 IP 白名单"过渡加固——含旧版 AWS 出口段）。
- SO 编号提取正则自动从订单编号掩码（`COMMANDE_<NAME>_MASK`）派生，`WISE_SO_REF_PATTERN` 仅作手工覆盖。
- 多公司：配置常量按实体保存，webhook 按 payload 的 profile_id 路由实体；多币种：外币收款走原币通道，本币收外币发票走本币+发票汇率通道。

## 22.0 部署说明（策略 B 移植，零 core 补丁）

若部署在**官方 Dolibarr 22.0.x** 上，本模块采用**零 core 补丁**方式：

- **Phase 3（仪表盘 boxes）**：slycustom 提供 2 个替代 widget（最近动态、生日；多币种发票类 widget 已改用官方 core 能力）。用户可在 **首页 → 配置 → 仪表盘** 中按需停用原 core widget、启用 SLY 的 widget。
- **Phase 4（ShipsGo）**：已迁入 slycustom 模块，零 core 补丁。

补丁应用顺序见 **patches/APPLY-ON-22.md**，按模块索引见 **patches/PATCHES-BY-MODULE.md**，从 14.0.5 自定义版到 22.0.4 的升级路径见 **docs/UPGRADE-14.0.5-TO-22.0.4.md**（14→22 迁移期的历史文档已于 2026-09 清理，需要时从 git 历史找回）。

---

## 与官方 14.0 / 22.0 的兼容性说明

- **22.0（当前主力）**：列表来源订单列、多币种显示、Shipment 菜单、ShipsGo、Wise 收款核对等均通过模块 hooks / 外部机制实现，零 core 补丁；个别能力仍需少量补丁（见 `patches/APPLY-ON-22.md`）。
- **14.0（旧线）**：本模块提供 PDF 模板、ShipsGo、导出入口及标准 hook 能实现的部分；SLY14.0 分支中大量对核心的直接修改（多币种显示、列表列、工作流、付款逻辑等）无法仅靠本模块在官方 14.0 上复现——要么打少量 core 补丁（`patches/` 下），要么继续使用 SLY14.0 分支并定期合并官方修复。

## 目录结构（简要）

```
custom/slycustom/
├── README.md / ChangeLog          # 说明与版本变更（2.0.0 起以 ChangeLog 为准）
├── admin/setup.php                # 设置页（常规 / ShipsGo / PDF / 仪表盘 / 销售条款 / Wise）
├── class/
│   ├── actions_slycustom.class.php + Actions*Trait.php   # 全部界面 hooks
│   ├── ShipsGo_API / ShipsGo_Update                       # ShipsGo 客户端与回写
│   ├── Wise_API / Wise_Incoming                           # Wise 客户端 / 收款队列+匹配+入账
│   └── SLY_AccountingDate.class.php
├── core/
│   ├── modules/modSlyCustom.class.php       # 模块描述符（cron：ShipsGo 同步、Wise 明细补全）
│   ├── modules/commande|facture|expedition|supplier_order|supplier_invoice/doc/  # SLY PDF 模板
│   ├── triggers/                 # 触发器
│   └── boxes/                    # 仪表盘 widget（最近动态、生日）
├── exports/                      # 工具菜单 SLY 导出页
├── sql/llx_slycustom_wise.sql    # Wise 事件/待核对队列表（幂等，模块激活时自动建）
├── webhook/
│   ├── shipsgo.php               # ShipsGo 推送接收端（HMAC）
│   └── wise.php                  # Wise 推送接收端（RSA 验签 / IP 白名单）
├── langs/ (en_US / en_SG / zh_CN)
├── docs/ · patches/
├── builddoc_order.php · orderstatus.php · cli_*.php
└── temp/
```

## 文档索引

本目录下有多份 .md 说明（移植、补丁、core 最小化等）。各文件用途及去重说明见 **docs/README-MD-FILES.md**。

## 版本

- 模块版本：**2.1.0**（变更明细见 **ChangeLog**；2.0.0 起由该文件管理）
- 针对 Dolibarr：**14.0 与 22.0**（22.0 为主力）
- 2.1.0：新增 Wise 付款准备（未注资转账 + Vendor Order reference + 状态回写自动记供应商付款）。
- 2.0.0：Wise 收款集成端到端（webhook 收款核对、SO 匹配、银行映射、核对页、对账单兜底 cron）。
