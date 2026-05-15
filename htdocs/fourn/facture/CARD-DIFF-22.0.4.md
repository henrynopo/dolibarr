# fourn/facture/card.php：本仓库 vs 官方 22.0.4 差异说明

你已用官方 22.0.4 的 `card.php` 验证**创建采购发票成功**，说明问题出在 SLY 定制改动上。下面是对比与建议。

---

## 一、差异概览（按位置）

| 区域 | 官方 22.0.4 | 本仓库 (SLY) | 与「创建」关系 |
|------|-------------|--------------|----------------|
| **1. unlinkdiscount** | 直接 `unlink_invoice()` | 先检查发票状态、折扣归属，再 unlink + 更新金额 + 重生成 PDF | 不影响创建 |
| **2. setabsolutediscount** | 有 id 就 fetch+insert_discount+link_to_invoice+commit | 无 id 时存 session 并 `header Location` + `exit`；有 id 且草稿时才原逻辑 | 可能影响：无 id 时多了一次重定向 |
| **3. 从 PO 创建后** | `$id = $object->create($user);` 无后续判断 | 增加 `if ($id <= 0) { $error++; setEventMessages(...) }` | 理论上更安全，不直接导致失败 |
| **4. 成功分支 (else)** | `$db->commit();` → PDF → `header Location ?id=$id` → `exit` | 先 `$db->commit();` 再 `if (empty($id)\|\|$id<=0)` 不重定向，否则 PDF + 折扣 + 重定向 | **关键：先 commit 再判断 $id** |
| **5. 错误分支** | 只 `setEventMessages($object->error, ...)` | 分支显示 object / srcobject / 通用错误 | 不影响创建是否成功 |
| **6. 创建表单折扣** | 简单「折扣」下拉 | FACTURE_SUPPLIER_DEPOSITS_ARE_JUST_PAYMENTS 等过滤 + 多币种 | 不影响 INSERT |
| **7. 付款/多币种表格** | 单币种 price() | 多一列多币种、多处 price(..., currency) | 仅展示，不影响创建 |

---

## 二、可能导致「创建失败 / 白屏」的要点

### 1. 成功分支里「先 commit 再看 $id」（最可疑）

- **官方**：`} else { $db->commit();` → 生成 PDF → `header("Location: ...?id=".$id); exit;`
- **本仓库**：`} else { $db->commit();` → **再** `if (empty($id) || $id <= 0) { ... } else { PDF; header; exit; }`

说明：

- `FactureFournisseur::create()` 内部会 `$this->db->begin()` / `commit()` 或 `rollback()`。
- 若 **create() 失败**，内部会 `rollback()`，部分驱动下会把 **外层** card.php 的 `$db->begin()` 也一并回滚。
- 此时若仍走 `else`（例如某路径未正确置 `$error`），会执行 **card.php 的 `$db->commit()`**。
- 在「事务已被内部 rollback 掉」的情况下再 `commit()`，可能报错或行为异常，若 display_errors 关闭就会表现为**白屏**。

因此：**只要有可能在 create 失败时仍进入 else 并执行 commit，就存在白屏/异常风险。** 更稳妥的是：**先根据 $id 判断成功/失败，再决定是 commit 还是 rollback，且失败时绝不 commit。**

### 2. setabsolutediscount 在「无 id」时的 redirect + exit

- 在创建表单上选折扣时，SLY 会 `header("Location: ...?action=create&..."); exit;`
- 若此处 URL 或后续请求少了 `socid`/`origin`/`originid` 等，下一次提交创建时可能缺必填项，导致 create() 失败或校验失败。与「先 commit 再看 $id」叠加，也会增加白屏概率。

### 3. 其它

- 未发现对 `$object->socid`、`$object->ref_supplier` 等 **在 create 前** 的赋值与官方不一致的改动；即**插入数据内容**本身与官方一致，差异主要在**事务与分支逻辑**。

---

## 三、建议：创建流程与官方完全一致，只保留「安全」改动

为保证「创建采购发票」与官方 22.0.4 行为一致、不再出现创建失败/白屏，建议：

1. **整段「action=add」的创建与提交逻辑**（从 `$db->begin()` 到 `header Location` / `exit`）**与官方 22.0.4 完全一致**，即：
   - 不增加「先 commit 再判断 $id」的分支；
   - 不在成功分支里对 `empty($id) || $id <= 0` 做额外分支；
   - 从 PO 创建后可以保留「若 `$id <= 0` 则 `$error++` 并 setEventMessages」的**小改动**（仅加强错误提示），但**不要**改变「else 里先 commit 再 PDF 再 redirect」的顺序和结构。

2. **可保留的「安全」SLY 改动**（不碰 commit/redirect 逻辑）：
   - **unlinkdiscount**：状态与归属检查、unlink 后 update_price + 重生成 PDF、错误提示（不涉及创建路径）。
   - **setabsolutediscount**：仅保留「**有 id 且草稿**」时的应用折扣/抵用逻辑；**去掉**「无 id 时 session + header Location + exit」这一整段，避免创建前多一次重定向和参数丢失风险。
   - 错误分支里对 object / srcobject / 通用错误的区分显示（可选）。
   - 创建表单上的折扣过滤（FACTURE_SUPPLIER_DEPOSITS_ARE_JUST_PAYMENTS 等）及多币种展示（仅前端）。
   - 付款区块的多币种列、price(..., currency)、unlink 链接加 token 等**纯展示与安全**改动。

3. **不建议在未进一步验证前保留的改动**：
   - 在 else 分支里先 `$db->commit()` 再根据 `$id` 决定是否重定向、是否应用 session 折扣的逻辑；
   - 在无 id 时用 session 存折扣并 `header Location` + `exit` 的 setabsolutediscount 行为。

---

## 四、与官方 22.0.4 的逐块对比摘要

- **361–383 行 (unlinkdiscount)**  
  SLY：状态与归属检查 + update_price + PDF。  
 建议：可保留；与创建无关。

- **536–607 行 (setabsolutediscount)**  
  SLY：无 id 时 session + redirect + exit；有 id 且草稿时才原逻辑。  
 建议：去掉「无 id 时 redirect+exit」；仅保留「有 id 且草稿」时的原逻辑。

- **1191–1199 行 (从 PO create 后)**  
  SLY：`if ($id <= 0) { $error++; setEventMessages(...) }`。  
 建议：可保留（仅加强错误提示），且确保与官方相同的 `else { commit; PDF; header }` 结构。

- **1444–1472 行 (if $error / else)**  
  官方：`if ($error) { rollback; setEventMessages($object->error...); } else { commit; PDF; header; exit; }`。  
  SLY：错误分支细化；else 里先 commit 再 `if (empty($id)\|\|$id<=0)` 不重定向，否则 PDF+折扣+重定向。  
 建议：**else 整段恢复为官方**：`$db->commit();` → PDF → `header("Location: ...?id=".$id); exit;`，不再根据 $id 分叉。

- **2726–2780 行 (创建表单折扣)**  
  SLY：过滤与多币种。  
 建议：可保留；仅影响表单展示。

- **3874–4160 行 (付款/多币种表格)**  
  SLY：多币种列、price(..., currency)、unlink 加 token 等。  
 建议：可保留；仅展示与安全。

---

## 五、结论与下一步

- **创建成功与否**：在「数据内容」上与官方一致；问题集中在 **else 分支里先 commit 再根据 $id 分叉** 以及可能的 **setabsolutediscount 无 id 重定向**。
- **建议操作**：  
  - 保持 **创建与提交逻辑（含 commit/redirect）与官方 22.0.4 完全一致**。  
  - 仅选择性保留上述「安全」改动（unlinkdiscount、setabsolutediscount 仅对有 id 草稿、错误提示、创建表单过滤、多币种展示等）。  

这样即可在保留 SLY 展示与部分业务逻辑的同时，避免采购发票创建失败和白屏。若你愿意，我可以按「只保留安全改动」给出一份针对 `card.php` 的补丁或具体修改清单（逐行）。
