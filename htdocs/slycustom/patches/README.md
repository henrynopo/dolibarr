# SLY14.0 核心补丁说明

除 **slycustom 模块**外，SLY14.0 相对官方 Dolibarr 14.0 的核心修改已按模块拆成以下补丁，便于在官方 14.0 上按需应用或日后升级时重打。

**注意**：这些补丁是针对 **官方 14.0** 生成的。若应用到其它版本（如 15.0、22.0），可能产生冲突，需手工合并或按 `CORE-CUSTOMIZATIONS-FULL-LIST.md` 逐项移植。

---

## 补丁文件列表

| 文件 | 内容 | 大致大小 |
|------|------|----------|
| **sly14.0-vs-official14.0-FULL.patch** | 除 slycustom 外的**全部**修改（含代码 + **语言包** + build/install/scripts 等） | ~2.9 MB |
| **sly14.0-langs.patch** | 仅语言包：htdocs/langs/ 下 en_US、fr_FR、zh_CN 等 | ~1.27 MB |
| **sly14.0-expedition-shipsgo.patch** | 发货模块：ShipsGo 集成、列表订单列、批量更新状态等 | ~52 KB |
| **sly14.0-compta.patch** | 财务：发票/付款多币种、列表合计、自动关闭、来源订单等 | ~209 KB |
| **sly14.0-commande.patch** | 客户订单：销售代表、来源采购订单、多币种、排序、草稿改客户等 | ~31 KB |
| **sly14.0-fourn.patch** | 采购/供应商：采购代表、来源销售订单、列表合计、禁止 PDF、付款多币种等 | ~123 KB |
| **sly14.0-comm.patch** | 商业/报价：remx 折扣拆分、报价相关 | ~62 KB |
| **sly14.0-core.patch** | 核心：lib、class、boxes、triggers、Workflow、PDF、多币种符号等 | ~594 KB |
| **sly14.0-accountancy-admin-api.patch** | 会计、后台、API 相关 | ~86 KB |
| **sly14.0-other-modules.patch** | 其它模块：accountancy/adherents/contact/contrat/product/projet/reception/societe/ticket/user 等 | ~290 KB |
| **sly14.0-misc.patch** | 杂项：install、main.inc、theme、modulebuilder、public、includes 等 | ~170 KB |

---

## 如何应用

### 在官方 14.0 源码根目录下（与 htdocs 同级）

```bash
# 确保当前是干净的官方 14.0
git status   # 应无未提交修改

# 应用单个模块（示例：仅 ShipsGo）
git apply --ignore-whitespace htdocs/slycustom/patches/sly14.0-expedition-shipsgo.patch

# 应用多个模块
git apply --ignore-whitespace htdocs/slycustom/patches/sly14.0-expedition-shipsgo.patch
git apply --ignore-whitespace htdocs/slycustom/patches/sly14.0-compta.patch
# ...

# 若有冲突会报错，需手工编辑后 git add 再继续
# 查看未应用的补丁：git apply --stat xxx.patch
```

### 一次性应用全部（含语言包）

```bash
git apply --ignore-whitespace htdocs/slycustom/patches/sly14.0-vs-official14.0-FULL.patch
```

FULL 补丁已包含语言包；若只想单独打语言包，可只应用：

```bash
git apply --ignore-whitespace htdocs/slycustom/patches/sly14.0-langs.patch
```

若大量冲突，可改用「按模块逐个应用」便于定位。

---

## 数据库变更（ShipsGo）

补丁只包含代码，**不包含**数据库结构。ShipsGo 所需的 `llx_expedition_extrafields` 列请执行本目录下：

- **sly14.0-expedition-extrafields.sql**（执行一次即可；表前缀按你系统的 `MAIN_DB_PREFIX` 修改）

或参考上一级目录的 **PATCHES-OFFICIAL-14.md** 中的 SQL 段落。

---

## 语言包

语言包已纳入补丁：

- **sly14.0-langs.patch**：仅 `htdocs/langs/` 的修改（en_US、fr_FR、zh_CN 等约 95 个文件），可单独应用。
- **sly14.0-vs-official14.0-FULL.patch**：已包含上述语言包，一次性应用即含代码与语言包。
