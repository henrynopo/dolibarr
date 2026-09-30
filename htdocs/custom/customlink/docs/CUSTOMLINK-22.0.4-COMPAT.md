# Customlink 模块与 Dolibarr 22.0.4 兼容性审查

本文档基于对 `custom/customlink` 模块的代码审查，列出与 Dolibarr 22.0.4 的兼容点与需修复项。

**升级已实施**：下列必须修复项与建议修复项已全部在代码中完成（路径、createtag、getNomUrl、fichelink、token、delivery、addlink $error 等）。若你部署时把模块放在 `htdocs/customlink/` 而非 `htdocs/custom/customlink/`，需将 `/custom/customlink/` 改回 `/customlink/`。

---

## 1. 模块概览

- **来源**：Patas-Monkey (customlink)
- **功能**：在 `llx_element_element` 中管理对象间自定义链接、标签（element_tag）、供应商发票分摊（facture_fourn_ventil）等；通过 hook 在卡片页增加「添加链接/标签」区块、在第三方/联系人/发票等增加 Tab。
- **当前结构**：模块位于 `htdocs/custom/customlink/`，包含 class、core/modules、core/lib、tabs、sql、langs 等。

---

## 2. 必须修复项（影响运行）

### 2.1 路径：模块在 `custom/` 下，所有 URL/路径须带 `custom/`

模块物理路径为 `htdocs/custom/customlink/`，但多处仍使用 `/customlink/`，在标准 22.0 安装下会 404 或找不到文件。

| 位置 | 当前值 | 建议改为 |
|------|--------|----------|
| **modCustomlink.class.php** | `$this->dirs = array("/customlink/temp")` | `array("/custom/customlink/temp")` |
| **modCustomlink.class.php** | `$this->_load_tables('/customlink/sql/')` | `$this->_load_tables('/custom/customlink/sql/')` |
| **modCustomlink.class.php** | 所有 menu `'url'=>'/customlink/...'` | `'/custom/customlink/...'` |
| **customlink.class.php** | `dol_buildpath('/customlink/fichelink.php?...', 1)`（getNomUrl） | `dol_buildpath('/custom/customlink/fichelink.php?...', 1)` |
| **customlink.class.php** | getNomUrlTag 中 fiche**tag** | 同上，改为 `/custom/customlink/fichetag.php` |
| **customlink.lib.php** | `dol_buildpath("/customlink", 1)` | `dol_buildpath("/custom/customlink", 1)` |
| **actions_customlink.class.php** | `dol_include_once("/customlink/...")`、`dol_buildpath("/customlink", 1)` | `/custom/customlink/...` |
| **addlink.php / fichelink.php 等** | `dol_include_once('/customlink/...')` | `dol_include_once('/custom/customlink/...')` |

说明：若你的部署把 `customlink` 放在 htdocs 根下（即 `htdocs/customlink/`），则保留 `/customlink/` 即可；否则在 22.0.4 下必须统一为 `custom/customlink`。

### 2.2 表 `llx_element_tag` 与 INSERT 不一致（createtag 会报错）

- **sql/llx_element_tag.sql** 中表结构**没有** `fk_category` 列。
- **customlink.class.php** 中 `createtag()` 的 INSERT 使用了 `fk_category`，且 VALUES 中 `'fr_FR', 0` 对应的是 `lang` 和 `fk_category`。

**修复**：在 `createtag()` 中去掉 `fk_category`，只插入表内存在的列，例如：

```php
$sql = "INSERT INTO ".MAIN_DB_PREFIX."element_tag (";
$sql.= "entity, tag, fk_element, element, lang) VALUES (";
$sql.= " 1, '".$this->db->escape($this->tag)."', ".(int) $this->fk_source;
$sql.= ", '".$this->db->escape($this->type_source)."'";
$sql.= ", 'fr_FR'";
$sql.= ")";
```

（并注意对 `tag`、`type_source` 使用 `$this->db->escape()` 以防 SQL 注入。）

### 2.3 getNomUrl 指向不存在的 `fiche.php`

- **customlink.class.php** 中 `getNomUrl()` 使用 `dol_buildpath('/customlink/fiche.php?id='.$this->rowid, 1)`。
- 实际卡片文件名为 **fichelink.php**，没有 fiche.php，会导致链接 404。

**修复**：将 `fiche.php` 改为 `fichelink.php`（并同时改为 `/custom/customlink/fichelink.php` 若已按 2.1 统一路径）。

### 2.4 fichelink.php 中误用权限与不存在的 update

- **fichelink.php** 第 183 行：`$user->rights->localise->creer` 应为 `$user->rights->customlink->creer`（明显复制粘贴错误）。
- 同页在 `$action == 'setUpdate'` 时调用 `$object->update()`，但 **Customlink 类没有定义 `update()` 方法**，会致命错误。

**修复**：  
- 将 `localise` 改为 `customlink`。  
- 要么在 `Customlink` 类中实现 `update()`（并保证与 `element_element` 的字段一致），要么移除/禁用 “setUpdate” 相关逻辑，避免调用不存在的 `update()`。

### 2.5 Token / 安全：使用 newToken()

- **customlink.lib.php** 第 109 行：`$_SESSION['newtoken']`  
- **actions_customlink.class.php** 第 43、72 行：同上  
- **fichelink.php** 多处：同上  

Dolibarr 22 推荐使用 `newToken()` 以兼容并保证 CSRF token 正确。

**修复**：全局将 `$_SESSION['newtoken']` 替换为 `newToken()`。

---

## 3. 建议修复项（兼容性与规范）

### 3.1 Societe::fetch 参数顺序（get_idsoc）

- **customlink.class.php** 中 `get_idsoc()` 调用：  
  `$companystatic->fetch('', $refsoc)`、`fetch('', '', $refsoc)`、`fetch('', '', '', $refsoc)`。
- Dolibarr 22 中 **Societe::fetch** 签名为：  
  `fetch($rowid, $ref = '', $ref_ext = '', $barcode = '', $idprof1 = '', ... $ref_alias = '', $is_client = 0, $is_supplier = 0)`。
- 用 `fetch('', '', '', $refsoc)` 会把第四参数当作 `$barcode`，而不是“内部 ref”；“内部 ref”应对应 `$ref` 或 `$ref_alias`（视 22 实现而定）。

**建议**：对照 22.0.4 的 `Societe::fetch()` 文档或源码，按“按名称/外部 ref/内部 ref”的查找意图，改用正确参数（例如用 `$ref` 或 `$ref_alias`），避免用第四位传“内部 ref”。

### 3.2 delivery 模块路径

- **customlink.class.php** 中 `getobjectclass()` 对 `delivery` 使用：  
  `$classpath = 'livraison/class'`。
- 当前 Dolibarr 中发货/交货模块目录为 **delivery**（如 `delivery/class/delivery.class.php`），不是 `livraison`。

**建议**：将 `livraison` 改为 `delivery`，并确认 `$subelement` 与类名与 22.0.4 一致（例如 `delivery`）。

### 3.3 Tabs 的 element 名称（22.0 规范）

- **modCustomlink.class.php** 的 tabs 使用：  
  `'invoice:+customlink:...'`、`'supplier_invoice:+customlink:...'`。
- 在 22.0 中，卡片/object 的 element 常为：客户发票 **facture**，供应商发票 **facture_fourn** 或 **invoice_supplier**；模块名与 element 可能不一致。

**建议**：在 22.0.4 下打开客户发票卡片、供应商发票卡片，确认其 `$object->element` 值，若为 `facture` 和 `invoice_supplier`，则把 tabs 中 `invoice` 改为 `facture`、`supplier_invoice` 改为 `invoice_supplier`（或当前 22 实际使用的 element），否则 Tab 可能不显示。

### 3.4 标题输出：print_titre vs load_fiche_titre

- **actions_customlink.class.php** 使用 `print_titre()`。
- 在 22 中更常见的是 `load_fiche_titre()`；`print_titre` 可能仍存在但多为兼容保留。

**建议**：新改动用 `load_fiche_titre()`，避免将来版本移除 `print_titre` 后报错。

### 3.5 addlink.php 无 CSRF token 与 $error 未初始化

- **addlink.php** 通过 GET/POST 直接执行“添加链接”并 `header("Location:...")`，但未校验 **token**，存在 CSRF 风险。
- 脚本使用 `$error++` 等，但 **$error 未初始化**，在严格 PHP 下可能 Notice。

**建议**：在文件开头 `$error = 0;`，并对执行写操作的请求要求 POST + `newToken()` 校验。

---

## 4. 与 22.0.4 的兼容结论

| 项目 | 状态 |
|------|------|
| 核心表 `llx_element_element` | 22.0 仍使用，兼容 |
| 权限 `$user->rights->customlink->lire/creer/supprimer` | 22.0 权限结构兼容 |
| Hook `showLinkedObjectBlock`、`commonobject` | 22.0 仍支持，兼容 |
| CommonObject、Form、dol_include_once、dol_buildpath | 兼容，但路径须为 `custom/customlink` |
| 模块描述符 DolibarrModules、menu、tabs、rights | 兼容，仅路径需统一 |
| getElementProperties、element 类型解析 | 22.0 仍存在，customlink 的 getobjectclass 逻辑可继续用，注意 delivery/facture 等路径与 22 一致 |

在完成上述**必须修复项**（路径、element_tag INSERT、fiche→fichelink、localise→customlink、update 存在性、token）后，模块可在 22.0.4 上正常启用与基本使用。**建议修复项**用于避免潜在错误并符合 22 规范，建议在测试环境中逐项验证（尤其是 Societe::fetch、tabs element 名称和 delivery 路径）。

---

## 5. 建议测试清单（22.0.4）

1. 启用模块后，菜单与所有链接是否指向 `custom/customlink/...` 且无 404。
2. 创建一条链接（addlink / fichelink 创建）：是否写入 `element_element` 且无 SQL 错误。
3. 创建标签（createtag）：是否不再报 “Unknown column 'fk_category'”，且标签列表正常。
4. 第三方/联系人/发票等卡片上 Customlink 的 Tab 是否出现且可打开。
5. 删除链接、删除标签是否正常。
6. 若使用供应商发票分摊（addventil/listeventilation）：在 22.0.4 下是否仍能正常写入与展示。

完成以上修改并测试通过后，可认为 customlink 在 22.0.4 上兼容可用。
