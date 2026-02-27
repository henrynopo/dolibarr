# 如何确认 SLY Custom 设置页已是「带 Tab」版本

## 1. 在浏览器里快速验证（推荐）

1. 打开 **SLY Custom Setup** 页面。
2. 在页面空白处 **右键 → 查看网页源代码**（或 Ctrl+U / Cmd+Option+U）。
3. 在源代码里 **搜索**（Ctrl+F / Cmd+F）：
   ```text
   SLYCUSTOM_SETUP_TABS_V2
   ```
   - **能搜到**：说明服务器上的 `setup.php` 已是带 Tab 的新版。
   - **搜不到**：说明当前访问的仍是旧版文件，需要把本地的 `slycustom/admin/setup.php` 部署到服务器。

注释里还会看到当前 tab，例如：`<!-- SLYCUSTOM_SETUP_TABS_V2 tab=(general) -->`。

---

## 2. 在服务器/本机查看 PHP 文件内容

在 **服务器** 或 **本地仓库** 里打开：

```text
htdocs/slycustom/admin/setup.php
```

确认是否包含下面这些内容（新版才有）：

| 内容 | 大致位置 |
|------|----------|
| `$tab = GETPOST('tab', 'aZ09');` | 约第 211 行 |
| `if (!in_array($tab, array('general', 'pdf', 'boxes', 'langpicker'), true))` | 紧接着上一行 |
| `$taburl = DOL_URL_ROOT.'/slycustom/admin/setup.php';` | 约第 216 行 |
| `if ($tab == 'general') {` | 约第 237 行 |
| `if ($tab == 'pdf') {` | 约第 295 行 |
| `if ($tab == 'boxes') {` | 约第 314 行 |
| `if ($tab == 'langpicker') {` | 约第 337 行 |
| `SLYCUSTOM_SETUP_TABS_V2`（注释） | 约第 231 行 |

**命令行快速检查**（在项目根或 `htdocs` 下执行）：

```bash
grep -n "GETPOST('tab'" slycustom/admin/setup.php
grep -n "SLYCUSTOM_SETUP_TABS_V2" slycustom/admin/setup.php
```

若两行都有输出，说明当前这份 `setup.php` 是新版。

---

## 3. 若确认是新版但依然看不到 Tab

- 清除浏览器缓存后重试，或使用无痕/隐私模式打开设置页。
- 用带参数的地址直接测 Tab 是否生效：
  - General: `.../slycustom/admin/setup.php?tab=general`
  - PDF: `.../slycustom/admin/setup.php?tab=pdf`
  若地址里改 `tab=pdf` 后页面只显示「PDF 模板」区块、不显示 ShipsGo 等，说明 Tab 逻辑已生效，多半是样式/主题把 tab 栏藏住了，需要再排查主题或自定义 CSS。
