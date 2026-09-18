# slycustom 文档索引

各文档用途一览。14→22 迁移期的一次性文档（PORT-TO-22、UPGRADE-STRATEGY、TESTING-22、
CORE-CUSTOMIZATIONS-FULL-LIST、CORE-MINIMAL-REVIEW、BOXES-MULTICURRENCY-STATUS、
PATCHES-OFFICIAL-14、PDF-SLY-22.0.4-COMPAT、SLYCUSTOM-可实现功能、API-22.0.4-MERGE-AUDIT、
CORE-DIFF-REASONS-BY-MODULE、FILES-DIFFER-FROM-OFFICIAL-22.0.txt、
PDF-TEMPLATES-REMOVE-OLD、HOOKS-INDEX-PAGES）已于 2026-09 清理，需要时从 git 历史找回。

## 当前文档

| 文件 | 用途 |
|------|------|
| **README.md** | 模块主入口说明（功能、安装、目录结构、版本） |
| **ChangeLog** | 版本变更记录（2.0.0 起以本文件为准） |
| **docs/MENU-CLEANUP.md** | 菜单层级错位时的清理步骤（界面停用再启用 / CLI） |
| **docs/UPGRADE-14.0.5-TO-22.0.4.md** | 从 14.0.5 自定义版升级到 22.0.4 的路径与变更整理 |
| **docs/COMPUTED-FIELD-USAGE.md** | 扩展字段"计算字段"的用法与 dol_eval 语法限制（mode 2 白名单、无 `??`、无中缀括号/嵌套调用） |
| **docs/COMPAT-CHECKLIST.md** | Dolibarr 升级（23/24）兼容验证清单：模块用到的核心表/列、类 API、描述符机制、数据级依赖 |
| **docs/SLY-ACCOUNTING-DATE-RULES.md** | SLY 记账日期规则（SLY_AccountingDate 类配套） |
| **docs/SLY-DROPSHIPPING.md** | 代发（dropshipping）流程说明 |
| **admin/SETUP-TABS-VERIFY.md** | 设置页 Tab 版本的验证步骤 |
| **patches/README.md** | 14.0 补丁列表与应用方法 |
| **patches/APPLY-ON-22.md** | 22.0 补丁应用顺序与最小 core |
| **patches/PATCHES-BY-MODULE.md** | 按功能模块的补丁索引与迁移缺口 |
