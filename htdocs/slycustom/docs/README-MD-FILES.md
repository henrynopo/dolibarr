# slycustom 下 .md 文件说明与去重建议

本文档列出各 .md 的用途，并标出**重复/可删**项，便于整理。

---

## 一、建议保留（各有明确用途）

| 文件 | 用途 |
|------|------|
| **README.md** | 模块主入口说明 |
| **CORE-CUSTOMIZATIONS-FULL-LIST.md** | 14.0 相对官方的**完整核心定制清单**（权威，被 PORT-TO-22、PATCHES-BY-MODULE 等引用） |
| **CORE-MINIMAL-REVIEW.md** | core 修改**审查与最小化**方案、未覆盖项、最小补丁集 |
| **patches/README.md** | **14.0** 补丁列表与如何应用、数据库 ShipsGo |
| **patches/APPLY-ON-22.md** | **22.0** 补丁应用顺序、完整 bash、按模块查看表、最小 Core、数据库 |
| **patches/PATCHES-BY-MODULE.md** | **按功能模块**索引：14.0/22.0 补丁对应、缺口、应用顺序、最小集 |
| **PORT-TO-22.md** | 14→22 **移植策略**（为何不能直接打 14.0 补丁、策略 A/B、补丁对应、单文件移植步骤） |
| **SLYCUSTOM-可实现功能.md** | 哪些功能可在**模块内实现** vs 必须 core、hook 名与实现方式（设计参考） |
| **PATCHES-OFFICIAL-14.md** | 在**官方 14.0** 上仅用 PDF + ShipsGo 时的**最小补丁**与 SQL（14.0 专用） |
| **UPGRADE-STRATEGY.md** | **升级到最新版**的两种思路、推荐做法、仓库/分支建议（偏策略与运维） |
| **BOXES-MULTICURRENCY-STATUS.md** | 仪表盘 box **多币种支持**检查清单（技术状态记录） |
| **PDF-SLY-22.0.4-COMPAT.md** | SLY PDF 与 **22.0.4 兼容性**（架构差异、getDolGlobal、_pagehead 等）（技术） |
| **TESTING-22.md** | 在 **22.0** 上测试 slycustom 的步骤（部署、启用、可测内容） |
| **admin/SETUP-TABS-VERIFY.md** | 设置页是否为**带 Tab 版本**的验证步骤（操作） |

---

## 二、重复较多、可考虑删除

| 文件 | 与谁重复 / 重叠内容 | 建议 |
|------|----------------------|------|
| **PATCHES-OFFICIAL-22.md** | 与 **patches/APPLY-ON-22.md**、**patches/PATCHES-BY-MODULE.md**、**MIGRATION-FULL-PLAN.md** 重叠：移植阶段表、补丁列表、应用顺序、数据库 SQL、各 phase 说明。应用顺序与命令已集中在 APPLY-ON-22；按模块与缺口已集中在 PATCHES-BY-MODULE。 | **可删**。若想保留“移植进度”可把简短状态表合并进 PATCHES-BY-MODULE 或 APPLY-ON-22 开头。 |
| **MIGRATION-FULL-PLAN.md** | 与 **PORT-TO-22.md**、**patches/PATCHES-BY-MODULE.md** 重叠：迁移阶段、状态、补丁对应、各阶段明细。PORT-TO-22 已包含策略与补丁对应；PATCHES-BY-MODULE 已包含按模块的 14/22 对应与缺口。 | **可删**。总览性的“阶段+状态”已在 PATCHES-BY-MODULE 和 APPLY-ON-22 中体现。 |

---

## 三、删除后如何找内容

- **22.0 补丁应用顺序与命令** → `patches/APPLY-ON-22.md`
- **按功能模块看 14/22 补丁与缺口** → `patches/PATCHES-BY-MODULE.md`
- **14→22 移植策略与步骤** → `PORT-TO-22.md`
- **core 最小化与未覆盖项** → `CORE-MINIMAL-REVIEW.md`
- **14.0 完整定制清单** → `CORE-CUSTOMIZATIONS-FULL-LIST.md`

---

## 四、总结

- **建议删除**：`PATCHES-OFFICIAL-22.md`、`MIGRATION-FULL-PLAN.md`（共 2 个）。
- **其余 14 个** .md 建议保留；若希望进一步精简，可再考虑将 TESTING-22 合并进 README，或把 UPGRADE-STRATEGY 缩成 README 的一小节（一般不删，因策略单独成文更清晰）。
