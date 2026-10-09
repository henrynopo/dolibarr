<!-- kb:title=Dolibarr 核心会计模块操作手册（官方 wiki 摘录） -->
<!-- kb:sources=复式记账（本地 v24.0.1 源码已核对，无外部依赖） https://wiki.dolibarr.org/index.php/Module_Double_Entry_Accounting | 简化会计 https://wiki.dolibarr.org/index.php/Module_Accounting_Simplified -->
<!-- kb:keywords=记账,复式记账,凭证,科目表,日记账,结账,期间,期初,fec,绑定,往来科目,设置,配置,怎么设置,如何设置,初始化,启用,停用,启用模块,accounting,bookkeeping,journal,voucher,period,closing,chart of accounts,ledger,setup,configure,how do i set up -->
# Dolibarr 核心会计模块（Double Entry Accounting）

官方文档：https://wiki.dolibarr.org/index.php/Module_Double_Entry_Accounting

> 核对说明：本文的 Dolibarr 侧内容对照本地 v24.0.1 源码核实；
> IRAS / SFRS / 公司法部分见 `03`、`04`、`05` 三份文件（各自带核对日期与来源）。

## 核心概念
- **会计科目表**（会计 > 设置 > 会计科目）：科目表来自 PCG 版本（法国的会计科目体系），
  在新加坡公司可以自行增删科目，科目编号必须**本公司唯一**，一旦有分录就不能再改编号。
  停用用「停用」，不要删除，否则历史分录的科目会变成孤儿。
- **日记账 Journal**：日记账不是硬编码的，是 `llx_accounting_journals` 表里的数据行，
  在 **会计 > 设置 > 日记账** 中增删改。每张分录行必须属于一个日记账，
  核心各日记账页（`accountancy/journal/*.php`）都是把 `code_journal` 当 URL 参数收进来的，
  所以**代码里找不到默认编号，编号完全由数据库里的那一行决定**。
  - 核心代码里唯一硬编码的一处是 `bookkeeping.class.php:1985` 的 `$this->code_journal = 'VT'`。
  - 本模块（EBK）默认用的三个编号：销售 `VT`、采购 `AC`、费用报销 `EX`
    （`EMBEDDEDBOOKKEEPING_JOURNAL_{SALES,PURCHASES,EXPENSE}`，可在配置页改）。
  - ⚠️ **`EX` 是本模块的建议值，不代表你的库里一定存在这个日记账。**
    回答"我该怎么设置"时，第一步应该是让用户去 **会计 > 设置 > 日记账**
    看清楚实际有哪些编号、每个编号是什么类型，再对号入座。
- **记账凭证 piece_num**：同一张凭证内所有分录行共用同一个凭证号，凭证号按日记账独立编号。
  一张凭证必须借贷相等才能过账。
- **期间 fiscalyear**（会计 > 设置 > 财务年度）：凭证日期必须落在已开启的期间内；
  期间「未开启」时凭证录不进去，「锁定」后不可修改。
- **FEC 导出**（会计 > 导出 > FEC）：用于报 IRAS / 会计师，格式为固定栏位的文本。

## 往来科目绑定
- 客户/供应商在 第三方 > 会计设置 里绑定「客户科目 / 供应商科目」后，销售与采购日记账
  能自动带出对方科目，不用每张发票手选。
- 产品/服务在 产品 > 会计设置 里绑定收入科目与成本科目；费用报销的行也可以绑定科目。

## 关键操作路径
- 手工录凭证：会计 > 记账 > 新建/编辑凭证（按日记账逐笔录入）
- 由发票生成：会计 > 记账 > 销售/采购日记账，选期间后按单据批量生成
- 审核发票后记账：会计 > 记账 > 发票，勾选已审核发票生成凭证

## 本模块配置页该怎么设置（常见提问）
用户在 EBK 配置页问"我这里应该怎么设置"时，按这个顺序回答：
1. **日记账代码**：打开 **会计 > 设置 > 日记账**，照抄那里**真实存在的**编号填进来，
   并确认该编号的类型（销售/采购/其他）与单据类型对得上。不要凭空建议 `VT`/`AC`/`EX`。
2. **AI Provider**：
   - `ai_module` —— 先去 **第三方模块 → AI → 设置** 配好服务与 API Key
     （写入 `AI_API_SERVICE` 与 `AI_API_<SERVICE>_KEY`），再回到这里选 `ai_module`。
   - `ebk_custom` —— 在本页面填 服务名 / API Key / URL / 模型，完全独立于核心 AI 模块。
   - `disabled` —— 关闭 AI。**不填 AI 也能手工记账**，AI 只是预填加速器。
3. **知识库**：勾选启用即可；`公司内部会计约定` 文本框写公司特有的规则
   （例如"运费一律计入 5100 运费科目"），每次提问都会附加。
4. 改完常量后若助手行为没变，**停用再启用一次模块**（常量在启用时写入）。

## 与本模块（EBK）的关系
本模块只是**显示与建议**核心会计模块的记账：它读取核心的记账凭证与科目表并给出建议，
不另建一套账。所有最终数据都写在核心表 `accounting_bookkeeping` 上。
