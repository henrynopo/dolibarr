# SLY 24.0 补丁备份与回退指南（Pack + Bundle Layer）

本文说明 admin/patches.php 为「sly24.0-*.patch」系列建立的**完整备份与回退机制**，
以及 v22 → v24 升级过程中如何随时把 core 文件还原到任意中间状态。

**2.2.4 起所有回退源都是本地预备份（per-file cp + per-patch pack tar.gz + cross-patch bundle tar.gz），外加「用户手工上传干净 24.0.1 bundle tar.gz」作为无 per-patch 备份时的兜底（无网络、无 git）。** 适用版本：slycustom ≥ 2.2.4 / Dolibarr 24.x。

---

## 一、备份层叠——三层本地快照 + 一份 per-patch pack + 一份 cross-patch bundle

| # | 位置 | 形式 | 何时建立 | 回退粒度 | 回退方式 |
|---|---|---|---|---|---|
| 1 | `documents/slycustom/patch-backups/per-patch/<name>/<rel>` | per-file cp | **首次**成功 apply（stable，永不覆盖） | 单 patch × 单文件 | cp 回（per-row Restore / Reset）|
| 2 | `documents/slycustom/patch-backups/dolibarr_<ver>_<ts>/<rel>` | per-file cp（session） | 每次 apply（按 ts 区分） | 单 session × 单文件 | cp 回 |
| 5a | `documents/slycustom/patch-backups/packs/sly24.0-<name>_<ts>.tar.gz` | **单文件 tar.gz**（per-patch pack）| 每次成功 apply | 单 patch × 全部文件（一次性）| `tar -xzf` 一键恢复 |
| 5b | `documents/slycustom/patch-backups/packs/sly24.0-bundle_<ts>.tar.gz` | **单文件 tar.gz**（cross-patch bundle）| **每次 applyall 开始**自动建立 | 全部 17 patches 触碰的 union 文件（一次性）| `tar -xzf` 一键回到 pre-applyall 状态 |

**所有层在正常 `slyPatchApply()` / `applyall` 流中自动建立**（用户无操作）。
bundle 也可以从 UI 顶部「创建 Bundle」按钮手动触发——独立于 applyall 流程，不 apply 任何 patch。

> 旧版本（≤ 2.2.2）还有「整目录 cp -a 第 4 层」与「per-file reset 第 3 层」，依赖 git tag 24.0.1 与网络下载。2.2.3 起这两层已删除——**回退完全靠预打包备份，无外部依赖**。

### 为什么需要 bundle（第 5b 层）—— cross-patch 重叠问题

sly24.0-*.patch 系列里**多个 patch 会触碰同一文件**。例如 `htdocs/main.inc.php` 可能被
sly24.0-core、sly24.0-remx、sly24.0-other-modules、sly24.0-api 中多个 patch 修改。

per-file 与 per-patch pack 都是**「apply 该 patch 之前 cp 一下」**——
对第一个被触发的 patch 而言，「之前」= pristine 24.0.1 状态；
对第二个被触发的 patch 而言，「之前」=**已被第一个 patch 改过的状态**。

所以：

- 单 patch Restore（per-row Restore 按钮，源 = per-patch 备份）= 把该文件还原回「该 patch apply 前」
- 单 patch Restore 但其文件被**多个 patch 触碰**= 还原到「中段不正确的状态」

bundle 是 applyall 开始前**一次性快照**，**所有 17 patch 触碰的文件 union 去重**，**反映 pre-applyall 的统一状态**——无视 patch 触碰次序，回退时给出一个一致的「pre-applyall 状态」。

**回退方式选择**：

| 场景 | 用 |
|---|---|
| 某个 patch 有 bug 想只回退该 patch | 单 row Restore 按钮（per-patch pack） |
| 文件被多 patch 触碰 + 任一 patch 有问题 | **Bundle Restore 按钮**（cross-patch bundle）|
| applyall 后整个 24 上线失败 | **Bundle Restore**（一键回到 24.0.1 干净） |
| per-patch 备份误删 | Bundle 仍可恢复；或 per-patch 第 1 层的永不覆盖 cp |
| 首次部署前先确保 24.0.1 干净基线 | 全新 git clone + install/upgrade（**这是工具链外的一次性人工准备**）|

---

## 二、UI 上的备份入口（admin/patches.php）

### 每行（每个 patch）

每行现在有 **5 个按钮**（按状态的可见性已收敛）：

| 按钮 | 何时显示 | 作用 |
|---|---|---|
| **Apply** | status=not applied | 干净正向 apply；首次 apply 后建立 per-file + pack |
| **Reset** | status=conflict | 从 per-file 回退该行，并重 apply；per-file 缺失则报错（不会自动下载）|
| **Restore** | status=applied & per-file 存在 | 从 per-file 回退该行；行回到 not applied |
| **Download pack** | 该 patch 有至少一个 pack | GET 下载该 patch 最新的 tar.gz（带 Content-Disposition: attachment） |
| **Restore from pack** | 该 patch 有 pack & status in {applied, conflict} | POST（CSRF）；解 tar.gz 一次性回退该行；行回到 not applied |

外加页面**底部三个 bulk 按钮**：

- **Apply all (N)**：顺序 apply 所有未应用的 patches（起始自动建 bundle）。
- **Reset conflicting files + apply all**：先扫所有 patch 的 status=conflict，把冲突 patch
  影响的所有文件 union 起来，从各自的逐项备份 cp 回这些文件，
  再走正常 apply-all。**只动冲突 patch 的文件**，其他文件（customisations、钩子、
  非冲突 patch 改动）完全不动。任一冲突 patch 没有逐项备份则整体失败并报错。
- **Create bundle / Restore from bundle**：手动建/恢复 cross-patch bundle（详见 §一）。

### 顶部 info 横幅

显示「已建立打包备份：N 个 tar.gz，X KB」，帮助快速核对当前数据集状态。

---

## 三、pack 文件结构详解

每个 pack 是一个 tar.gz，文件名格式：

```
sly24.0-<name>_YYYY-MM-DD_HHMMSS.tar.gz
```

包内**所有路径以 tar 根为基准**（relative path），不包含 `..` 或绝对路径，
所以 `tar -xzf <pack> -C <DOL_DOCUMENT_ROOT>` 是安全的就地恢复。

查看一个 pack 的内容（不解包）：

```bash
tar -tzf $DOL_DATA_ROOT/slycustom/patch-backups/packs/sly24.0-core_<ts>.tar.gz
```

恢复（危险操作——会覆盖 DOL_DOCUMENT_ROOT 下对应路径的文件）：

```bash
cd $DOL_DATA_ROOT
tar -xzf $DOL_DATA_ROOT/slycustom/patch-backups/packs/sly24.0-core_<ts>.tar.gz
```

下载 pack（浏览器）：

```
GET /custom/slycustom/admin/patches.php?action=downloadpack&pack=sly24.0-core_<ts>.tar.gz&token=<newtoken>
```

---

## 四、推荐的 v22 → v24 升级工作流

### 4.1. 准备

```bash
# 1. 备份整个 htdocs（操作前留个保险）
cp -a htdocs /path/to/offline-backup/htdocs.bak.before-sl24

# 2. 上传 24.0.1 全新代码（覆盖核心）
# 3. 重启 php-fpm 清 OPcache（24 vs 22 字节码差异巨大）
```

### 4.2. install / upgrade

浏览器打开 `https://<domain>/install/index.php`：

- 若 $conf 中 MAIN_VERSION_LAST_UPGRADE < 24.0.1：进入升级向导，按提示走完
- 升级完成后 llx_const.MAIN_VERSION_LAST_UPGRADE 变为 24.0.1
- slycustom 模块如果之前 disable 了，现在可以重新启用

### 4.3. 启用 slycustom

进入 `设置 → 模块/应用 → 第三方模块`：

1. 启用 slycustom —— 创建表 + 注册钩子
2. 启用后立即进入 **设置 → SLY 定制 → 常规 → 打开补丁工具**（admin/patches.php）

### 4.4. 应用 sly24.0-*.patch 系列

在 admin/patches.php：

1. 顶部 info 横幅：初始为 0 个 pack（正常——还没 apply 过）
2. 点 **Apply all (17)**：按依赖顺序应用所有 patches
3. 每次成功 apply：自动写一个 pack，外加 per-file 备份
4. 应用完后，再次刷新页面应看到「17 tar.gz，~X KB」摘要，N（status 计数）= 0
5. 若中途某个 patch 报 conflict，行变红，看到下方红色按钮「Reset conflicting files + apply all」——
   点一下会自动恢复冲突文件 + 重 apply 全套

### 4.5. 验证

清单（每个验证完才能进入生产流量）：

- [ ] `/custom/slycustom/admin/patches.php` 顶部：「17 tar.gz」，无 conflict
- [ ] 主页 dashboard 渲染正常，无 500 / noop
- [ ] `/commande/list.php`：多币种列与 SLY 卡片正常
- [ ] `/compta/facture/list.php`：原币列、来源订单列正常
- [ ] Wise webhook 收单测试一笔、流入核对队列

### 4.6. 随时回退方案

如果上线后某一处出问题：

**单 patch 回退**（最快）：

```text
admin/patches.php → 该行 → Restore 按钮
```

或下载 prod 的 pack 回滚：

```text
prod: GET downloadpack?pack=sly24.0-core_<ts>.tar.gz
d-test: 重启到 24.0.1 → 上传 pack 到 documents/slycustom/patch-backups/packs/
       → admin/patches.php → 该行 → Restore from pack 按钮
```

**完全回退到 v24.0.1 干净状态**（最暴烈）：

```text
admin/patches.php → 红色 Restore from bundle 按钮
（前提是已建立过 bundle——首次 applyall 时会自动建）
```

或手动：

```bash
git checkout 24.0.1 -- htdocs/    # 有 git tag 的工作树
```

**整个 htdocs 回退到升级前**（最彻底，需要 git tag 不在工具链内）：

```bash
cp -a /path/to/offline-backup/htdocs.bak.before-sl24/* htdocs/
```

> 2.2.3 之前的 `slyPatchResetCore2401` / `slyPatchDownloadOfficialTarball` 整目录回退路径已删除。
> 现在所有回退都是「per-file 局部」+「bundle 整体」两个粒度——零外部依赖。

---

## 五、当逐项备份缺失或被污染时——上传 Bundle 兜底（2.2.4 新增，2.2.9 强化）

### 5.1 适用场景

d-test / prod 出现以下**死锁**状态：

- 所有（或多个）sly24.0-*.patch 显示 **conflict**（htdocs 已偏离干净 24.0.1）；
- 这些冲突 patch 的**逐项备份目录为空**（被 2.2.2 之前版本 apply、或备份被误删）；
- **或逐项备份存在但内容不纯净**（例如 bundle 误从 sly24 开发工作树打包——已含全部
  定制代码。此时每次 Restore/Reset 都把污染内容写回，永远修不好；d-test2 2026-09
  事故即此形态）；
- bulk reset（resetapplyall_smart）拒绝执行并报「以下补丁没有逐项备份」。

此时橙色「上传 Bundle tar.gz」卡片是**唯一恢复路径**（2.2.9 起冲突状态下总是显示，
无论备份是否存在）。

### 5.2 操作步骤

**推荐（2026-09 起）**：直接使用本地预生成的保证纯净 bundle——每个文件按
17 个 patch 的 target 清单从 git tag `24.0.1` 单独导出（`git show`，不经过
任何工作树，杜绝污染），并已逐条目校验：

> `dolibarr/sly24.0-pristine2401.tar.gz`（仓库根目录，107 个目标文件，
> sha256 `7dbda9b5c3e220ff210f651315bbb096e6643b0879c0d7d58885ce0c7e1af4a0`，
> 确定性打包：按文件名排序、owner=0、固定 mtime。已逐条目与 git tag `24.0.1`
> 比对，107/107 逐字节一致、纯 LF、无目录条目。）

⚠️ **旧 bundle 已作废（2026-09-28 发现污染）**：旧 sha256
`cce72aef7c0f…a90e18aa` 的文件虽名为 pristine，实际内容是 sly24 开发工作树
（101/101 文件与 git 24.0.1 不同——含全部定制代码）。用旧 bundle 做过
Restore/Reset 的环境会把污染内容写回，必须用新 bundle 重新 Restore/Reset 一次。
教训：sha 相等只证明「和上次同一份文件」，不证明内容纯净；bundle 必须逐条目
对照独立权威（git tag）校验后才可信。

跳到下面第 3 步直接上传。

手工准备（无法获得上述文件时）：

1. 在 admin/patches.php 底部橙色「📤 上传 Bundle tar.gz（兜底）」卡片中，点击
   **「下载 target file 清单」**——得到 `sly24.0-target-list.txt`（全部 17 patch
   触碰文件的 union，一行一个 htdocs 相对路径）。

2. 在一台有干净 Dolibarr 24.0.1 代码的机器上（git clone `24.0.1` tag 或解压官方
   tarball 均可——**这一步是工具链外的一次性人工准备，工具本身不联网、不碰 git**）：

   ```bash
   cd /path/to/clean/dolibarr/htdocs
   tar -czf /tmp/sly24.0-bundle_manual.tar.gz --no-recursion -T /path/to/sly24.0-target-list.txt
   ```

   注意：bundle 里**只装清单列出的文件**，路径以 htdocs 根为基准（与工具自动建的
   bundle 格式一致；`htdocs/<path>` 前缀布局 2.2.9 起也可接受）。
   **切勿用 sly24 开发工作树（Temp/d24test 等）打包——那棵树已含全部定制代码。**

3. 回到 admin/patches.php，选择该 tar.gz 上传。工具会：
   - 校验 basename（`sly24.0-*.tar.gz`）+ 大小（≤ 16MB）；
   - `tar -tzf` 预检每条 entry（拒绝 `..` / 绝对路径）后解压到临时目录；
   - **仅接受白名单内文件**（清单外的 archive 内容一律丢弃）；
   - 把每个文件 cp 回 `DOL_DOCUMENT_ROOT`（覆盖脏的 htdocs 文件）；
   - **同步按 patch 拆分写入 `patch-backups/per-patch/<name>/`**（2.2.9 起**覆盖
     重建**——已有备份会被上传内容替换，日志显示 "Re-seeded (overwritten) N"；
     上传即声明"回到纯净基线"，正是这一步清除被污染的备份层）。

4. 上传成功后刷新页面：conflict 行应变为 notapplied（干净 24.0.1 基线），此时点
   「应用全部补丁」走正常 applyall 即可。

### 5.3 安全边界

- 仅管理员 + CSRF token + DOL_VERSION 24.x guard；
- 上传大小上限 16MB（实际 bundle 5–10MB；故意低于 d-test post_max_size=32M，
  避免整个 POST 在 PHP 协议层被拒、$_FILES 为空的误导性报错）；
- 解包前 `tar -tvzf` 预检：拒绝 symlink / hardlink / 设备条目、拒绝 `..` /
  绝对路径（与 pack / bundle 恢复同一套检查）；
- 逐 entry 白名单（17 patch target union）——上传的 tar.gz **不可能**触碰清单
  之外的任何 htdocs 文件；`slyPatchRestoreFromPack`（pack / bundle 恢复）同样
  套用该白名单。

---

## 七、排障：所有 patch 显示 conflict（slycustom ≥ 2.2.5）

**第一步永远是看补丁表格上方的红色诊断框**——它直接显示 git/patch 引擎的原始
报错。历史上踩过的两个系统性根因：

| 根因 | 诊断框特征 | 状态 |
|---|---|---|
| **patch 文件是 CRLF 行尾**（Windows autocrlf=true + 文件复制部署） | `error: <file>: No such file or directory`（文件名尾带不可见 CR）；正反向 check 都失败；**干净树也全 conflict** | 2.2.5 已双层修复：仓库内 LF + .gitattributes；运行时自动规范化（工具免疫） |
| **路径前缀错位**（patch 头是 `a/htdocs/...` 仓库根相对，引擎却在 htdocs 内以 -p1 运行） | `error: htdocs/core/xxx: No such file or directory`——服务器根本没有 `<htdocs>/htdocs/` 嵌套目录 | 2.2.6 已修复：引擎 -p2；target 解析剥 `htdocs/` 前缀（备份/打包/白名单全链路对齐） |
| **git dubious ownership**（htdocs 是 git 仓库且属主 ≠ php-fpm 用户） | `fatal: detected dubious ownership in repository at ...` | 2.2.5 已修复：引擎自动带 `-c safe.directory=<htdocs>` |
| 树真的偏离了 patch 基线 | 具体某文件的 `patch does not apply` / 上下文不匹配 | 用 Reset（有逐项备份时）或 §五 上传 bundle 兜底 |

**bundle 文件在哪里**：`<DOL_DATA_ROOT>/slycustom/patch-backups/packs/sly24.0-bundle_<ts>.tar.gz`
（d-test2 即 `/home/slyfoodc/data-test2/slycustom/patch-backups/packs/`）。页面顶部
info 横幅显示最新一份的文件名与时间。

---

## 八、空间预算

d-test 估算：
- sly24.0-core patch 影响 ~90 个文件：pack 约 200–500 KB
- sly24.0-remx patch 影响 ~100 个文件：pack 约 300 KB
- sly24.0-other-modules patch 影响 ~300 个文件：pack 约 800 KB–1.5 MB
- 全部 17 个 packs：约 **5–10 MB**（按压缩率 ~70%）

进 DOL_DATA_ROOT/slycustom/patch-backups/packs/，
远小于 d-test 文档总配额（典型 5–10 GB）。
