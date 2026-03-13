---
name: dolibarr-dev
description: Develop and customize Dolibarr with SLY Custom module and core patches. Use when editing PHP in htdocs, maintaining slycustom, applying or creating patches, or working with Dolibarr 22.0 / 14.0 compatibility, PDF modules, lang files (en_US for system, en_SG for SLY/Singapore—based on en_UK; fr_FR, zh_CN), or ShipsGo/expedition/invoice/order features.
---

# Dolibarr / SLY Custom 开发

**命名**：SLY 为公司简称，书写时**一律使用大写 SLY**（文档、注释、界面文案等）。仅代码与路径中的模块名、文件名保持小写（如 `slycustom`、`pdf_sly_order`）。

## 项目结构

**优先参考 Dolibarr 官方最新规则**：目录与模块组织以 [Dolibarr 开发者文档](https://wiki.dolibarr.org/index.php/Developer_documentation) 及 [dolibarr.org 开发文档](https://dolibarr.org/documentation-home.php) 为准；有冲突时以官方约定为先，再叠加 SLY 约定。

- **htdocs/**：Dolibarr 核心与模块代码；与 `core`、`commande`、`compta` 等同级；外部模块（如 slycustom）也置于 htdocs 下，遵循官方模块/附加组件结构。
- **htdocs/slycustom/**：SLY 外部模块（PDF 模板、ShipsGo、列表列、语言选择器等）；尽量把定制放在此处，减少对 core 的修改。
- **htdocs/slycustom/patches/**：针对官方 14.0/22.0 的 core 补丁；在**仓库根目录**（与 htdocs 同级）应用：`git apply --ignore-whitespace htdocs/slycustom/patches/xxx.patch`。22.0 说明见 `patches/APPLY-ON-22.md`、`patches/PATCHES-BY-MODULE.md`。

## 代码与补丁约定

- **PHP**：遵循 Dolibarr 既有风格（类在 `class/`，库在 `lib/`，hook 在模块的 `class/actions_*.class.php`）。
- **修改 core**：优先用 hook/trigger；必须改 core 时用补丁管理，从**仓库根**应用。
- **新功能**：优先使用系统层级最新可用的 function、变量等，而不是新建；尽量保持通用性，便于后续 PR 到新版本、开放给更多人使用。
- **语言与本地化**：SLY 为新加坡公司，主语言为 **en_SG**。系统层级用 `en_US`；新加坡本地化或 SLY 自定义文案/翻译以 **en_SG** 为准。en_SG 以 **en_UK** 为模版在 `htdocs/langs/en_SG/` 维护。其他语言如 fr_FR、zh_CN 照常；大范围语言改动可做成 `sly22.0-langs.patch` 等。
- **PDF 模板**：放在 `slycustom/core/modules/<module>/doc/`，类名与文件名与 Dolibarr 约定一致（如 `pdf_sly_order`、`pdf_sly_invoice`）。
- **SLY 定制文档与标注**：slycustom 下的 README、补丁说明、代码注释、docblock、界面/文档中的说明等，**一律使用英文**，保持通用性，便于后续 PR 或开放给他人使用。

## 关键文档（优先查阅）

| 文档 | 用途 |
|------|------|
| slycustom/README.md | 模块功能、安装、22.0 部署概要 |
| slycustom/PORT-TO-22.md | 22.0 移植策略 |
| slycustom/patches/APPLY-ON-22.md | 22.0 补丁应用顺序与最小 core |
| slycustom/patches/PATCHES-BY-MODULE.md | 按模块的补丁索引与迁移缺口 |
| slycustom/patches/README.md | 补丁列表与在官方 14.0 上的应用方法 |

## 常用操作

- **应用单个补丁**（在仓库根）：`git apply --ignore-whitespace htdocs/slycustom/patches/sly22.0-xxx.patch`
- **检查补丁**：`git apply --stat htdocs/slycustom/patches/xxx.patch`
- **生成补丁**：在对应 core 修改提交后，从仓库根对指定路径做 `git diff` 并保存为 `slycustom/patches/` 下新 patch 文件，并在 APPLY-ON-22.md / PATCHES-BY-MODULE.md 中更新说明。

## 回复语言

当用户用中文提问时，用简体中文回复；技术术语（如 class、patch、hook）可保留英文。
