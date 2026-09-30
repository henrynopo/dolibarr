# EmbeddedBookkeeping

> 在 Dolibarr **客户发票 / 供应商发票 / 费用报销单**卡片上的**「会计分录」Tab**
> 直接查看每行发票行的会计科目并录入分录（Odoo Journal Entries 风格），可选 AI 预填。
> 完全复用核心 `llx_accounting_bookkeeping` 表，**不修改 Dolibarr 任何核心文件**。

## 概览

| 维度 | 说明 |
|---|---|
| 模块 ID | 500200 |
| 权限类 | `embeddedbookkeeping` |
| Tab | `ebkbookkeeping` 挂三卡（descriptor `$this->tabs`，**改后需禁用→重启用模块**） |
| 钩子 | `invoicecard`、`invoicesuppliercard`、`expensereportcard`、`globalcard`（仅卡片深链接按钮） |
| 数据表 | 复用 `llx_accounting_bookkeeping`（无新表） |
| 核心改动 | 无（无 `patches/`） |
| 兼容 | Dolibarr 22.0.x / 24.0.x |
| 多公司 | `entity IN (0, X)` 全局过滤 |
| 触发器 | 无（不自动入账；所有写入均人工确认） |

## Tab 结构（1.1.0，Odoo 风格）

- **A. 行级科目表**：每行发票行 → 已绑定（`fk_code_ventilation`，绿）/ 建议（核心
  `AccountingAccount::getAccountingCodeToBind()` 现推，蓝）/ 缺失（红）。
- **B. 分录表单**（未记账 + write 权限 + 状态合格时）：对方科目行 + 每行贷/借行
  （`fk_docdet` = 行 rowid）+ 按税科目分组的 VAT 行；实时借/贷合计；页面内 POST
  （自己的 token，不依赖任何卡片钩子）。**AI 仅作预填加速器，未配置 AI 表单完全可用。**
- **C. 已过账分录**：按 `piece_num` 分组，链接原生 `accountancy/bookkeeping/card.php`。

## 安装

1. 拷贝 `custom/embeddedbookkeeping/` 到 `htdocs/custom/`。
2. Dolibarr → 设置 → 模块/应用 → 启用 **EmbeddedBookkeeping**。
3. 设置 → 模块 → EmbeddedBookkeeping → 配置：
   - 日记账代码：销售 `VT` / 采购 `AC` / 费用报销 `EX`
   - AI Provider：`disabled` / `ai_module` / `claude`
   - Anthropic Key（`claude` 模式时填写，会被 Dolibarr 自动加密）
4. 给用户分配权限（管理员默认自动获得）：
   - `embeddedbookkeeping→bookkeeping→read` — 看到按钮
   - `embeddedbookkeeping→bookkeeping→write` — 写入分录
   - `embeddedbookkeeping→ai→suggest` — 请求 AI 建议
   - `embeddedbookkeeping→admin→setup` — 配置页

## 数据流

```
卡片按钮（深链接）→ Tab 页 tabs/bookkeeping.php
  A 区：EBKTabData::lineRows() 单 JOIN（行 + 绑定科目 + 科目表 rowid）
  B 区：EBKTabData::defaultDraft() 组装 N 行平衡草稿 → 表单 → POST action=record
        → EBKEntryDraft::fromPost() → validate() → EBKBookkeepingWriter::writeEntry()
        → $db->begin() … N × BookKeeping::create()（piece_num 由核心分配并断言一致）
          … 借贷 MT 精度复检 → commit
  C 区：BookKeeping::fetchAll（USF 精确过滤）按 piece_num 分组展示
```

AI 预填：Tab 内按钮 → `POST /custom/embeddedbookkeeping/ajax/suggest_entries.php`
→ `EBKAiProviderFactory::resolve()` → 返回 JSON 预填空缺科目下拉；失败非阻塞提示。

## REST API（AI Agent / 外部系统）

模块自带 Restler 类 `class/api_embeddedbookkeeping.class.php`，挂载于
`/api/index.php/embeddedbookkeeping/...`：

| 方法 | 端点 | 权限 | 用途 |
|---|---|---|---|
| GET  | `/embeddedbookkeeping/check?doc_type=...&fk_doc=...` | `bookkeeping→read` | 查询是否已记账 |
| GET  | `/embeddedbookkeeping/listAccounts?pcg_type=INCOME,EXPENSE` | `bookkeeping→read` | 列出候选科目 |
| POST | `/embeddedbookkeeping/suggest?side=customer\|supplier\|expense&fk_doc=...` | `ai→suggest` | 调 AI Provider 给建议 |
| POST | `/embeddedbookkeeping/create?doc_type=...&fk_doc=...&debit_account=...&credit_account=...&amount=...` | `bookkeeping→write` | 写入一对平衡分录 |

错误码：`400`（输入不合法）、`403`（无权限）、`404`（单据未找到）、`409`（已记账）、
`500`（写入失败）。

## 文档

- [`docs/README.md`](docs/README.md) — 完整说明（Provider、写入审计、v22 兼容、故障排查）
- [`ChangeLog.md`](ChangeLog.md) — 版本历史

## 范围之外

- 不监听 `BILL_VALIDATE` / `INVOICE_VALIDATE` / expense approve（不自动入账）
- 不做核销 / 银行对账 / FEC 导出 / FX 换算
- 分期开票（situation <100%）暂走核心日记账
- 不编辑已记账条目（用原生 `htdocs/accountancy/bookkeeping/card.php`）
- `bankcard` / `paymentcard` 仅暴露 `formObjectOptions`，不足以挂表单 — 列为未来工作

## 许可

GPL-3.0+，同 Dolibarr 主项目。
