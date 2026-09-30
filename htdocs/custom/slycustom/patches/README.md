# SLY Core 补丁说明

SLY 定制相对官方 Dolibarr 的 core 层修改，按目标版本拆成补丁系列存放于本目录。**slycustom 模块之外的全部 core 定制均以此处的 patch 文件为权威来源。**

## 目录结构

| 内容 | 说明 |
|---|---|
| `sly24.0-*.patch`（17 个） | **当前系列**：针对官方 24.0.1。应用顺序、淘汰清单、hook 化分析、冲突裁决与静默丢失补回记录见 [APPLY-ON-24.md](APPLY-ON-24.md)。由 `gen_sly24_patches.sh` 从定制工作树 `git diff 24.0.1` 生成分模块导出 |
| `archive/` | **14.0 / 22.0 历史补丁**（sly14.0-* + sly22.0-* 全套、APPLY-ON-22.md、gen_sly22_patches.sh、original_orderstatus_14.0.php、ref-expedition-*.diff、若干一次性 extrafields 修复 SQL）。仅供考古，不再维护、不要应用 |
| `PATCHES-BY-MODULE.md` | 按功能模块索引 14.0/22.0 补丁对应关系与迁移缺口（14→22 时代文档，背景参考） |
| `gen_sly24_patches.sh` | 当前系列的再生成脚本（在含定制的源码树内跑） |

## 数据库（24.0.1）

24.0.1 启用 slycustom 模块时由 `sql/llx_slycustom_*.sql` 自动建表/补扩展字段；如手工安装，请用模块内的 `sql/*.sql`，**不要**再跑 `archive/` 下任何 `sly22.0-*extrafields.sql`（22 的扩展字段定义可能与 24 字典冲突）。

## 应用方法

在**干净的官方源码根目录**（与 htdocs 同级）：

```bash
git apply --ignore-whitespace htdocs/custom/slycustom/patches/sly24.0-<name>.patch
```

按 [APPLY-ON-24.md](APPLY-ON-24.md) 第二节的顺序逐个应用；语言包（langs）如遇冲突用 `--3way` 并保留 SLY 翻译。
