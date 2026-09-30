# 从 SLY14.0（14.0.5 自定义版）到 22.0.4 的升级路径与变更整理

本文档说明从「自定义 14.0.5（SLY14.0 分支）」到「官方 22.0.4 + 当前 SLY 自定义」的升级路径、代码变动来源及本次本地仓库整理方式。

---

## 一、概述

- **起点**：SLY14.0 分支（基于官方 Dolibarr 14.0.5 的 SLY 定制）。
- **终点**：官方 22.0.4 + **slycustom** 模块 + 核心补丁（`sly22.0-*.patch`），即当前 **SLY22.0** 分支状态。
- **本文档**：描述升级步骤、14.0 与 22.0 补丁/模块对应关系，以及本次整合时对冲突、提交、补丁的整理说明。

---

## 二、升级路径（步骤摘要）

1. **基准**：以官方 **22.0.4**（tag `22.0.4`）为干净源码基准。
2. **应用核心补丁**：在源码根目录（与 `htdocs` 同级）按 [patches/APPLY-ON-22.md](../patches/APPLY-ON-22.md) 中顺序依次应用 `htdocs/custom/slycustom/patches/sly22.0-*.patch`（共 18 个）。
3. **拷贝 slycustom 模块**：将 `htdocs/custom/slycustom/` 整个目录放入官方 22.0.4 的 `htdocs/custom/` 下。
4. **数据库**：执行一次 `patches/sly22.0-expedition-extrafields.sql`（ShipsGo 扩展字段）；若从 14.0 数据升级，另按官方说明执行 14→15→…→22 的 upgrade.php 或迁移步骤。
5. **配置**：在 设置 → 模块/应用 中启用 **SLY Custom**；按需在 仪表盘 中启用 SLY 的 boxes、在 设置 中选择 SLY PDF 模板等。

---

## 三、代码变动来源（与 14.0.5 的对应）

- **14.0 定制**：以「sly14.0-*.patch + slycustom」形式保留在仓库；22.0 上**不再直接使用** 14.0 的 patch，而是使用针对 22.0.4 生成的 **sly22.0-*.patch**。
- **对照表**：SLY14.0 的 patch（sly14.0-*）与 22.0 的 patch（sly22.0-*）、slycustom 的对应关系见 [patches/PATCHES-BY-MODULE.md](../patches/PATCHES-BY-MODULE.md) 与 [CORE-CUSTOMIZATIONS-FULL-LIST.md](../CORE-CUSTOMIZATIONS-FULL-LIST.md)。
- **按模块**：
  - **core**：sly22.0-core-price-symbol.patch、sly22.0-menu-parent-match.patch（货币符号、菜单父节点匹配、boxes、lib、tpl 等）。
  - **compta**：sly22.0-paiement-arrayfields、compta-multicurrency、bank-treso、invoice-list-source-order-position、facture-pdf-fallback。
  - **commande**：sly22.0-commande.patch；列表「来源采购订单」等由 slycustom hook 提供。
  - **fourn**：sly22.0-fourn-paiement-arrayfields、fourn-linkedobject、supplier-invoice-list-source-order-position。
  - **comm**：sly22.0-remx-hooks、comm-propal-linkedobject。
  - **expedition**：仅 DB（sly22.0-expedition-extrafields.sql）；ShipsGo 业务在 slycustom。
  - **admin / api / misc / other-modules**：sly22.0-admin、api、misc-cron-other、other-modules。
  - **langs**：sly22.0-langs.patch（en_US、fr_FR、zh_CN 等）。

---

## 四、本地仓库整理说明（本次整合）

- **语言包冲突**：已解决所有 `htdocs/langs/` 下 UU（未合并）文件，统一保留 SLY 翻译侧（`git checkout --ours` 后 `git add`）。
- **提交**：当前 SLY22.0 的改动已按「核心 → compta → commande/expedition/comm → fourn → admin/api/cron/conf → 其它模块 → install → 语言包 → theme → slycustom」分批提交。
- **补丁**：`sly22.0-*.patch` 已由当前 SLY22.0 相对 **22.0.4** 重新生成（脚本 `patches/gen_sly22_patches.sh`），保证「干净 22.0.4 + 按 APPLY-ON-22 顺序打补丁 = 当前核心树（不含 slycustom）」；已在临时 worktree 中校验全部 patch 可顺序应用且结果与 HEAD 一致。

---

## 五、后续升级（22.0.4 → 更新版）

1. 拉取官方新 tag/分支（如 22.0.5 或 23.0）到本地。
2. 按 [patches/APPLY-ON-22.md](../patches/APPLY-ON-22.md) 顺序重新应用同一批 `sly22.0-*.patch`；若有冲突，则修正 patch 或按 [PORT-TO-22.md](../PORT-TO-22.md) 做逻辑移植。
3. 必要时重新运行 `patches/gen_sly22_patches.sh`（需在对应基准 tag 下从当前分支 diff 生成），以更新 patch 集与文档。
4. 在 [README.md](../README.md) 或本升级文档中保持指向本文档的链接，便于日后查阅。

---

## 相关文档

- [PORT-TO-22.md](../PORT-TO-22.md) — 移植策略与为何不能直接打 14.0 补丁
- [patches/APPLY-ON-22.md](../patches/APPLY-ON-22.md) — 22.0 补丁应用顺序与最小 Core
- [patches/PATCHES-BY-MODULE.md](../patches/PATCHES-BY-MODULE.md) — 按模块的 14.0/22.0 补丁对应与迁移缺口
- [CORE-CUSTOMIZATIONS-FULL-LIST.md](../CORE-CUSTOMIZATIONS-FULL-LIST.md) — 全部核心定制清单
