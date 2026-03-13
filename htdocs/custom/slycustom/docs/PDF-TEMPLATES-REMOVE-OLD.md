# 为什么“旧的”PDF 模板仍然存在？

Dolibarr 的 PDF 模板会在**两个地方**出现，只删文件不会从界面上消失。

## 1. 模板列表从哪来？

- **下拉框（生成 PDF 时选的模板）**  
  来自数据库表 `llx_document_model`。  
  只有在这里被“启用”的模板才会出现在下拉框里。

- **设置页的模板列表（如 首页 → 设置 → 发货/发票 → 文档模板）**  
  来自**扫描目录**：  
  - `htdocs/core/modules/expedition/doc/`、`htdocs/core/modules/facture/doc/`（核心）  
  - 以及 `htdocs/custom/slycustom/core/modules/.../doc/`（若已配置 MAIN_MODULE_SLYCUSTOM_MODELS）  
  只要目录里还存在 `pdf_xxx.modules.php`，就会在设置页显示，和数据库无关。

所以：**只删掉 .php 文件，不会自动从下拉框里消失**——下拉框只看数据库。  
反过来，**只删数据库里的记录，设置页仍会显示**——只要文件还在。

## 2. 你“全部删掉”的可能是？

- 若删的是 **slycustom** 里某些旧模板文件：  
  只要数据库里还有对应 `nom` 的 `document_model` 记录，下拉框里仍会出现该模板名（选时可能报错）。
- 若希望不再看到 **Dolibarr 自带的** 模板（如 rouget、espadon、merou、sponge、crabe、octopus）：  
  这些文件在 `core/` 下，不建议删；应通过“禁用”从下拉框移除。

## 3. 只保留 SLY 模板该怎么做？

### 方法 A：在设置页里禁用（推荐）

1. **发货单**  
   - 首页 → 设置 → 发货 → 文档模板  
   - 对不需要的模板（如 rouget、espadon、merou、espadon_SLY_PL 等）点**绿色开关**，改为“禁用”。

2. **客户发票**  
   - 首页 → 设置 → 发票 → 文档模板  
   - 同样对不需要的模板（如 crabe、sponge、octopus、sponge_SLY_consignee 等）点绿色开关禁用。

禁用后，这些模板会从 `llx_document_model` 中删除，**下拉框里就不会再出现**。  
设置页仍会列出所有扫描到的文件（包括 core 的），但会显示为“已禁用”。

### 方法 B：从数据库里直接删掉旧模板名（可选）

若你已删除了某些 .php 文件，但下拉框里仍出现对应名字，可以用下面的 SQL 清理（按需去掉注释并执行）。

```sql
-- 从“发货单”可用模板中移除（只保留 sly_packinglist）
DELETE FROM llx_document_model
WHERE type = 'shipping'
  AND entity IN (0, 1)   -- 按你的 entity 调整
  AND nom IN ('rouget', 'merou', 'espadon', 'espadon_SLY_PL');
-- 不要删 sly_packinglist

-- 从“客户发票”可用模板中移除（只保留 sly_invoice）
DELETE FROM llx_document_model
WHERE type = 'invoice'
  AND entity IN (0, 1)
  AND nom IN ('crabe', 'sponge', 'octopus', 'sponge_SLY_consignee');
-- 不要删 sly_invoice
```

执行前请先备份数据库。`entity` 需与你的环境一致（多公司时可能有 0, 1, 2...）。

### 方法 C：不再在设置页看到旧 SLY 模板文件

若你只想保留：

- 发货：`sly_packinglist`（并可保留或删除 `espadon_SLY_PL`）
- 发票：`sly_invoice`（并可保留或删除 `sponge_SLY_consignee`）

那可以**直接删除**不再使用的 .php 文件，例如：

- `custom/slycustom/core/modules/expedition/doc/pdf_espadon_SLY_PL.modules.php`
- `custom/slycustom/core/modules/facture/doc/pdf_sponge_SLY_consignee.modules.php`

删除后，设置页就不会再扫描到它们。  
若这些模板名仍在 `llx_document_model` 里，再用**方法 B** 的 SQL 清掉，下拉框里也不会再出现。

## 4. 小结

- **下拉框** = 只看数据库 `llx_document_model`。  
- **设置页列表** = 扫描 core + slycustom 里的 `pdf_*.modules.php`。  
- 只删文件 → 下拉框仍可能有旧名（且可能报错）。  
- 只在设置页点“禁用” → 可从下拉框移除，且不动 core 文件。  
- 想彻底不看到旧 SLY 模板：删对应 .php + 用 SQL 删对应 `document_model` 记录（或先禁用再删文件）。
