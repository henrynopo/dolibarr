# EmbeddedBookkeeping

> 在 Dolibarr 客户发票 / 供应商发票 / 费用报销单卡片的**「会计分录」Tab**（Odoo Journal
> Entries 风格）查看每行科目并录入分录，可选 AI 预填。

## 概览

| 维度 | 说明 |
|---|---|
| 模块名 | EmbeddedBookkeeping |
| 模块 ID | 500200 |
| 权限类 | `embeddedbookkeeping` |
| 配置前缀 | `EMBEDDEDBOOKKEEPING_` |
| Tab | `ebkbookkeeping` × 三卡（descriptor 注册；**改后禁用→重启用**） |
| 钩子 | `invoicecard`、`invoicesuppliercard`、`expensereportcard`、`globalcard`（仅深链接按钮） |
| 数据表 | **复用** `llx_accounting_bookkeeping`（无新表） |
| 触发器 | 无（不监听 `BILL_VALIDATE` 等；所有写入均人工确认） |
| 核心改动 | 无（无 `patches/` 目录） |
| 兼容 | Dolibarr 22.0.x / 24.0.x |
| 多公司 | `entity IN (0, X)` 全局过滤 |

> 1.1.0 起：旧「按钮 + 隐藏弹窗」通道已删除（弹窗从未到达 DOM，且打开弹窗的 JS
> 依赖 AI provider —— 未配置 AI 时手工记账完全不可用）。入口收敛为卡片 1 个深链接
> 按钮 + Tab 页表单。

> ⚠ 银行账户 (`bankcard`) 和付款 (`paymentcard`) 当前只暴露 `formObjectOptions`
> 钩子，不足以挂 modal 与按钮 → 列为**未来工作**，不在本期范围。

## 安装

1. 把目录拷贝到 `htdocs/custom/embeddedbookkeeping/`。
2. 进入 Dolibarr → 设置 → 模块/应用，找到 **EmbeddedBookkeeping**，启用。
3. 进入 设置 → 模块 → EmbeddedBookkeeping，配置日记账代码（默认 `VT` 销售 / `AC` 采购）和 AI Provider。
4. 给目标用户分配权限：
   - `embeddedbookkeeping→bookkeeping→read`（看按钮）
   - `embeddedbookkeeping→bookkeeping→write`（写入）
   - `embeddedbookkeeping→ai→suggest`（AI 建议）
   - `embeddedbookkeeping→admin→setup`（admin 配置）

## 数据流

```
Tab 页 tabs/bookkeeping.php（?id=N&objecttype=customer_invoice|supplier_invoice|expense_report）
  A 区 lineRows()：单 JOIN（行 + fk_code_ventilation 绑定科目 + 科目表 4 路 rowid JOIN）
      未绑行 → 核心 AccountingAccount::getAccountingCodeToBind() 现推「建议」科目
  B 区 defaultDraft()：对方科目行 + 每行行（fk_docdet=行 rowid）+ VAT 按科目分组
      构造性平衡（存储值求和恒等式），残差折叠末行 + 警告
      → 表单 POST action=record（tab 自身 token）
      → EBKEntryDraft::fromPost() → validate()（MT 精度平衡 + 行唯一性）
      → EBKBookkeepingWriter::writeEntry()：$db->begin() → N × BookKeeping::create()
        （piece_num 由核心分配，逐行断言一致）→ 借贷复检 → commit
  C 区 postedEntries()：BookKeeping::fetchAll USF 过滤 → 按 piece_num 分组
      → 组头链接 /accountancy/bookkeeping/card.php?piece_num=N
```

REST API 的 `POST create` 仍走 `writePair()`（总额两条，向后兼容）。

## AI Provider

### `ai_module`（默认，唯一推荐）

复用 Dolibarr 原生 `htdocs/ai/` 模块。需要：
- `ai` 模块已启用
- 在 `ai/admin/setup.php` 至少配置一个 service（chatgpt / groq / mistral / google / custom / anthropic-compat）
- Provider key 存在

`AiModuleProvider::suggest()` 调用核心 `Ai::generateContent($userPrompt, 'auto', 'bookkeepingsuggest', ...)`，由核心 AI 模块从 `AI_CONFIGURATIONS_PROMPT.bookkeepingsuggest` 派发 prePrompt / postPrompt。记账分录提示词的编辑入口在本模块的 `admin/setup.php`「Provider & AI」Tab；其它 service / key / 模型配置都在系统 AI 模块自身的设置页。

## 写入审计

每条分录的 `extraparams` 列会写入 JSON：

```json
{
  "ebk_source": "manual" | "ai:ai_module",
  "ebk_form_version": 1,
  "ebk_entity": 1,
  "ebk_module": "embeddedbookkeeping@1.0.0",
  "ebk_confidence": 0.85,        // 仅 AI 路径
  "ebk_rationale": "..."         // 仅 AI 路径
}
```

这样未来做"AI 建议质量审计"无需 schema 变更。

## v22 兼容要点

| 坑 | 处理 |
|---|---|
| HookManager 22.0.4 只注入 `parameters['currentcontext']` | 每个 trait 头部检查 `currentcontext` 字符串包含 `invoicecard`（参考 slycustom `ActionsSlycustomWisePaymentTrait:34`） |
| `$db->lasterror` 是属性非方法 | 不直接调用；让 `BookKeeping::create()` 把错误放进 `$bk->errors[]` |
| `Facture::date` 仍是 timestamp | 直接用 `$object->date`，不读 `datef` 列 |
| 多币种字段必填 | `multicurrency_amount` / `multicurrency_code` 显式写入或 NULL |
| Token | 每个写入路径用 `newToken()` URL + `doActions` 校验 |
| AI Provider 接口 | 独立于 `htdocs/ai/` 模块；模块禁用时 `AiModuleProvider` 静默返回空 |

## 范围之外

- **不**自动入账：不监听 `BILL_VALIDATE` / `INVOICE_VALIDATE`
- **不**做核销 / 银行对账 / FEC 导出 / FX 换算
- **不**自动选 VAT 账户
- **不**在发票卡片新增 tab（仅按钮 + modal）
- **不**编辑已记账条目（用原生 `htdocs/accountancy/bookkeeping/card.php`）

## 验证清单

1. **禁用 → 重启用模块**（descriptor tabs 生效）；`llx_const` 出现 `MAIN_MODULE_EMBEDDEDBOOKKEEPING_TABS_0..2`
2. 客户发票卡：Tab 出现在核心 tab 后；卡片按钮区仅剩 1 个「记账/查看分录」深链接
3. A 区三态：已绑定（预绑定发票）/ 建议（未绑定）/ 缺失（红 + 警告条）
4. 供应商发票、报销单 Tab 同样渲染（报销行绑定来自 `expensereport_det.fk_code_ventilation`）
5. 权限矩阵：无 read → 无 Tab；只 read → A/C 区无表单；有 write → 表单；**无 AI 权限或
   provider 为 Null → AI 按钮禁用/隐藏，表单完全可用（bug 回归点）**
6. POST：改金额至不平衡 → 报错零写入；平衡 → 成功 + piece_num；DB 校验行行同
   piece_num、`Σdebit=Σcredit`（2 位）、行级行 `fk_docdet`=行 rowid；重复 POST →
   `EBKAlreadyBookkeeping`
7. 红字发票（负总额）→ 镜像分录平衡；旧 writePair 两条式分录在 C 区正常显示
8. 多公司：第二实体 → Tab 查询/科目/分录均限本实体；草稿单据无表单；过期 token 拒绝
9. REST API：`GET /api/index.php/embeddedbookkeeping/check?doc_type=customer_invoice&fk_doc=42` 正常

## REST API（AI Agent / 外部系统）

模块类 `class/api_embeddedbookkeeping.class.php` 挂载于 Restler：

| 方法 | 端点 | 权限 | 说明 |
|---|---|---|---|
| GET  | `/embeddedbookkeeping/check` | `bookkeeping→read` | 查是否已记账 |
| GET  | `/embeddedbookkeeping/listAccounts` | `bookkeeping→read` | 列候选科目 |
| POST | `/embeddedbookkeeping/suggest` | `ai→suggest` | 调 AI Provider |
| POST | `/embeddedbookkeeping/create` | `bookkeeping→write` | 写借贷平衡分录 |

错误码：`400` / `403` / `404` / `409`（已记账，幂等）/ `500`。

## 故障排查

| 现象 | 检查 |
|---|---|
| Tab 不显示 | 模块启用后是否**禁用→重启用**过（insert_tabs 常量）？用户是否有 `bookkeeping→read`？ |
| 深链接按钮不显示 | 同上权限；单据是否已 validate（已记账单据任何状态都显示「查看分录」） |
| A 区全是「缺失」 | 科目表 `CHARTOFACCOUNTS` 是否配置？产品/默认科目常量是否设置？先到核心「往来科目」绑定 |
| AI 预填点了没反应 | `EMBEDDEDBOOKKEEPING_AI_PROVIDER` 与密钥；浏览器控制台 AJAX 报错（bad_token → 强刷新页面） |
| 提交报「分录不平衡」 | 表单金额被改过——页面底部借/贷合计会显示 ⚠，调平后再提交 |
| `EBKPieceNumMismatch` | 检查 `ACCOUNTANCY_ENABLE_FKDOCDET` 常量（实验功能，建议关闭） |
| `EBKAlreadyBookkeeping` 一直触发 | `llx_accounting_bookkeeping` 已有该单据记录（用原生 bookkeeping/card.php 撤销或修改） |
| 写入报会计期间错误 | 单据日期落在已关闭的会计期间——用核心工具重开期间或调整单据日期 |

## 关键文件

| 文件 | 用途 |
|---|---|
| `core/modules/modEmbeddedBookkeeping.class.php` | 模块描述符（含 `$this->tabs` 三卡注册） |
| `tabs/bookkeeping.php` | 「会计分录」Tab 页（A 行科目 / B 分录表单 / C 已过账） |
| `class/actions_embeddedbookkeeping.class.php` | Hook 聚合器（仅 addMoreActionsButtons） |
| `class/ActionsEmbeddedBookkeepingUiTrait.php` | 卡片深链接按钮 |
| `class/EBKTabData.class.php` | 读侧助手（行科目/对方科目/VAT 分组/默认草稿/已过账） |
| `class/EBKEntryDraft.class.php` | N 行草稿值对象（validate + fromPost） |
| `class/EBKBookkeepingWriter.class.php` | writeEntry()（N 行 + 事务）+ writePair()（API） |
| `class/EBKBookkeepingAlreadyDone.class.php` | 已记账守卫 |
| `class/EBKAccountLookup.class.php` | 账户下拉数据源 |
| `class/EBKEntryProposal.class.php` | 总额对值对象（REST API writePair 路径） |
| `class/api_embeddedbookkeeping.class.php` | REST API |
| `class/ai/*` | AI Provider 工厂 + ai_module/null |
| `ajax/suggest_entries.php` | AI 建议 AJAX 端点（Tab 预填复用） |
| `admin/setup.php` | 配置页 |

## 许可

GPL-3.0+，同 Dolibarr 主项目。
