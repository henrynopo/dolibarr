<?php
/**
 *	\file       htdocs/custom/embeddedbookkeeping/ajax/chat.php
 *	\ingroup    embeddedbookkeeping
 *	\brief      Universal chat endpoint for the EBK floating AI assistant.
 *              Works on any Dolibarr page (EBK tab, accounting module, etc.)
 *              that includes the widget JS.
 *
 *             POST body (JSON):
 *               { "question": "...", "context": "...", "page": {...}, "token": "..." }
 *             OR (legacy EBK-specific):
 *               { "question": "...", "context": "...", "doc_id": N, "doc_type": "...", "token": "..." }
 *
 *             Returns: { "ok": bool, "answer": string, "error": string, "debug": {} }
 */

// --- Dolibarr bootstrap ---------------------------------------------------
$res = 0;
if (!$res && file_exists(__DIR__.'/../../../main.inc.php')) {
    $res = @include __DIR__.'/../../../main.inc.php';
}
if (!$res && file_exists(__DIR__.'/../../../../main.inc.php')) {
    $res = @include __DIR__.'/../../../../main.inc.php';
}
if (!$res) {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: application/json');
    echo json_encode(array('ok' => false, 'error' => 'main_include_failed'));
    exit;
}

if (empty($user) || empty($user->id)) {
    header('HTTP/1.1 401 Unauthorized');
    header('Content-Type: application/json');
    echo json_encode(array('ok' => false, 'error' => 'not_logged_in'));
    exit;
}

global $conf, $langs, $db, $user;

// --- Input parsing -------------------------------------------------------
$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$question = isset($payload['question']) ? trim((string) $payload['question']) : '';
$context  = isset($payload['context']) ? trim((string) $payload['context']) : '';
$pageInfo = isset($payload['page']) && is_array($payload['page']) ? $payload['page'] : array();
$docId    = isset($payload['doc_id']) ? (int) $payload['doc_id'] : 0;
$docType  = isset($payload['doc_type']) ? (string) $payload['doc_type'] : '';
$token    = isset($payload['token']) ? (string) $payload['token'] : (string) GETPOST('token', 'none');

if ($question === '' && $context === '') {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: application/json');
    echo json_encode(array('ok' => false, 'error' => 'question_or_context_required'));
    exit;
}

if ($token === '' || $token !== newToken()) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: application/json');
    echo json_encode(array('ok' => false, 'error' => 'bad_token'));
    exit;
}

if (!isModEnabled('embeddedbookkeeping')) {
    header('HTTP/1.1 503 Service Unavailable');
    header('Content-Type: application/json');
    echo json_encode(array('ok' => false, 'error' => 'module_disabled'));
    exit;
}

if (empty($user->rights->embeddedbookkeeping->ai_suggest) && empty($user->admin)) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: application/json');
    echo json_encode(array('ok' => false, 'error' => 'no_permission'));
    exit;
}

$langs->loadLangs(array('embeddedbookkeeping@embeddedbookkeeping'));

// Ref / card URL are resolved client-side by the hook and travel with the
// payload - used to label the answer without a second lookup.
$docRef = isset($payload['doc_ref']) ? (string) $payload['doc_ref'] : '';
$docUrl = isset($payload['doc_url']) ? (string) $payload['doc_url'] : '';

// --- Build page context block -------------------------------------------
$contextBlock = '';

// 1. The document currently open on screen (invoice / expense report).
$docBlock = '';
if ($docId > 0 && in_array($docType, array('customer_invoice', 'supplier_invoice', 'expense_report'), true)) {
    require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
    require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
    require_once DOL_DOCUMENT_ROOT.'/expensereport/class/expensereport.class.php';
    require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/EBKTabData.class.php';

    if ($docType === 'customer_invoice') {
        $obj = new Facture($db);
    } elseif ($docType === 'supplier_invoice') {
        $obj = new FactureFournisseur($db);
    } else {
        $obj = new ExpenseReport($db);
    }

    if ($obj->fetch($docId) > 0) {
        $obj->fetch_thirdparty();
        if (method_exists($obj, 'fetch_lines')) {
            $obj->fetch_lines();
        }
        if (empty($docRef)) {
            $docRef = (string) $obj->ref;
        }
        $docBlock = buildDocBlock($obj, $docType);
    } else {
        $docBlock = "【当前单据】\n(单据 ID ".$docId." 未找到或无权访问)\n\n";
    }
}

// 2. The company's REAL chart of accounts. Without it the model invents
//    generic account names ("差旅交通费"…) instead of the codes that
//    actually exist here - the whole point of the assistant.
$chartBlock = buildChartBlock($db);

$pageTitle = isset($pageInfo['title']) ? trim((string) $pageInfo['title']) : '';
$pageSection = isset($pageInfo['section']) ? trim((string) $pageInfo['section']) : '';
$pageExtra = isset($pageInfo['extra']) ? trim((string) $pageInfo['extra']) : '';
$pageUrlFromClient = isset($pageInfo['url']) ? trim((string) $pageInfo['url']) : '';
$pageUrl = $pageUrlFromClient !== '' ? $pageUrlFromClient : (isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '');

// 3. The core Accountancy page on screen. Only relevant when no commercial
//    document was resolved above - the two are mutually exclusive.
$accountingBlock = '';
if ($docId <= 0) {
    $accountingBlock = buildAccountingBlock($db, $pageUrl);
}

$contextBlock = $docBlock.$accountingBlock.$chartBlock;

// 4. Generic page context (from pageInfo)
if ($pageTitle !== '' || $pageSection !== '') {
    $contextBlock .= "【当前页面】\n";
    if ($pageSection !== '') $contextBlock .= "模块: {$pageSection}\n";
    if ($pageTitle !== '') $contextBlock .= "标题: {$pageTitle}\n";
    if ($docRef !== '') $contextBlock .= "单据编号: {$docRef}\n";
    if ($docUrl !== '') $contextBlock .= "单据链接: {$docUrl}\n";
    if ($pageUrl !== '' && $pageUrl !== $docUrl) $contextBlock .= "页面 URL: {$pageUrl}\n";
    if ($pageExtra !== '') $contextBlock .= $pageExtra."\n";
    $contextBlock .= "\n";
}

// User-selected text
if ($context !== '') {
    $contextBlock .= "【用户在页面上选中的内容】\n".$context."\n\n";
}

// --- Build system prompt (ZH bilingual accounting assistant) --------------
$sysPrompt = <<<'PROMPT'
你是本公司的资深会计助手，服务于 Dolibarr ERP 的「会计」模块（复式记账 Double Entry）。

# 你的首要任务
当用户给你一张单据（客户发票 / 供应商发票 / 费用报销单）并询问"应该记到哪个科目"时，
你必须基于随附的【当前单据】明细和【本公司会计科目表】给出**具体可用的记账建议**。

# 铁律（必须遵守）
1. **只能使用【本公司会计科目表】中真实存在的科目编号。** 严禁编造科目号、严禁给出
   科目表中不存在的科目名称。你没有截图、没有别的公司数据 —— 唯一的真相来源就是上面
   那份科目表清单。
2. **不要索要明细。** 如果【当前单据】里有明细，就直接依据明细回答，不要再要求用户
   提供费用名称、金额、是否含税、是否可抵扣增值税等信息 —— 这些明细里都有。
3. **给出完整、借贷平衡的分录建议**，格式如下：

   建议分录（单据 ER26109）：
   借 6222 差旅费                1,234.00
   借 2220 增值税进项税           86.38
       贷 4216 员工报销应付款        1,320.38

4. 每一行都写明：科目编号 + 科目名称 + 金额，并标明借方或贷方。
5. 增值税按单据实际税率计算；若该行不可抵扣（NPR / 非抵扣），说明理由并计入费用科目。
6. 借贷合计必须相等，若不等请指出差额原因。
7. 若明细中有多个不同性质的项目，**逐行给建议**，不要笼统归为"其他费用"。
8. 置信度低或科目表里找不到合适科目时，明确说出"科目表中没有完全对应的科目，
   建议新增/由管理员确认"，并给出最接近的候选科目。

# 输出格式
- 用与用户提问相同的语言回答（中文问中文答，英文问英文答）。
- 先给结论（分录表格），再给简短的判断理由（每条一两句）。
- 不要写长篇大论，用户要的是能直接照着录的分录。

# 核心会计模块页面
当上下文里出现【当前记账凭证】或【当前页面】（核心会计模块 accountancy）时：
- 先判断这张凭证/这个科目的状态：借贷是否平衡、科目是否用错、辅助核算是否缺失、
  摘要是否完整、日期落在哪个会计期间、是否已经过账或被锁定。
- 有问题就给出具体修正建议（改成哪个科目、补什么辅助核算、摘要写什么）。
- 用户如果让你"填一下这张凭证"，用科目编号 + 借方金额 + 贷方金额的格式给出，
  不要输出无法执行的自然语言指令。
- 不要把整份科目表重新念一遍。

# 通用会计问题
以下问题可以正常作答，并优先引用官方文档：
- 会计科目表 / 往来科目绑定 / 产品默认科目 / VAT 账户 / 日记账配置
- 期间开启关闭、凭证录入、FEC 导出
- 发票审核与记账绑定、贷记单、定金发票
- 费用报销单的提交与审批流程

# 回答准则
- 【参考资料】是本模块自带的离线知识库（Dolibarr 用法、新加坡会计准则、IRAS 规则）。
  引用其中的结论时，**必须写出来源标题**，例如「依据《IRAS GST（新加坡消费税）》」。
- 知识库是离线整理的资料。涉及**税率、申报期限、门槛金额**这类会变的内容，
  必须提示用户「以 IRAS/ACRA 最新公告为准」，并给出官方网址。
- 不知道就说不知道，绝不编造科目编号、税率、截止日期或准则条文号。
- 涉及金额、纳税判断、申报的结论，注明这是会计辅助意见，最终由会计师确认。

官方文档：
- 复式记账：https://wiki.dolibarr.org/index.php/Module_Double_Entry_Accounting
- 简化会计：https://wiki.dolibarr.org/index.php/Module_Accounting_Simplified
- 费用报销模块：https://wiki.dolibarr.org/index.php/Module_Expense_Report
PROMPT;

// 5. Offline knowledge base (Dolibarr usage + Singapore standards + IRAS rules).
//    Scored against the question so an unrelated question ships nothing.
//    Appended to the system prompt, not the user message, so it reads as
//    standing reference material rather than something the user said.
require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/EBKKnowledgeBase.class.php';
$kbBlock = EBKKnowledgeBase::buildBlock($question, $contextBlock, $conf);
if ($kbBlock !== '') {
    $sysPrompt .= "\n".$kbBlock;
}

// --- Call AI via EBKAiChatHelper ----------------------------------------
require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/ai/EBKAiChatHelper.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/ai/EBKAiProviderFactory.class.php';

$provider = EBKAiProviderFactory::resolve();
$tStart = microtime(true);
$answer = EBKAiChatHelper::answer($provider, $question, $contextBlock, $sysPrompt, $db, $conf, $langs);
$elapsed = microtime(true) - $tStart;

$llmFailed = ($answer === '');
if ($llmFailed) {
    // EBKAiChatHelper::lastError() says WHICH thing failed (missing constant vs
    // upstream 401 vs empty body) — a single generic "check your config" sent the
    // user hunting through the whole setup page for a setting that wasn't at fault.
    $why = trim((string) EBKAiChatHelper::lastError());
    if ($why === '') {
        $why = '未知的失败原因，请查看 error_log 中 [EBK] 相关记录。';
    }
    dol_syslog('[EBK] assistant answer failed: '.$why, LOG_WARNING);
    // NOTE: double quotes. This used to be a single-quoted string, so the "\n"
    // reached the browser literally and the user saw "：\n1. 确认 ..." on one line.
    $answer = "抱歉，AI 助手暂时无法回答您的问题。\n\n原因：".$why."\n\n"
        . "排查顺序：\n"
        . "1. 配置 → 模块 → EmbeddedBookkeeping → AI Provider 选 ai_module 或 ebk_custom\n"
        . "2. ai_module 模式：设置 → 第三方 → AI，先把核心 AI 模块的服务与 Key 配好\n"
        . "3. ebk_custom 模式：在 EBK 配置页填服务 / Key / 模型（ebk_custom 不依赖核心 AI 模块）\n"
        . "4. 若原因里带「上游接口报错」，那是模型服务返回的原文（401/429/模型名不对等）";
}

// Audit trail. Reuses the core ai module's ai_log_request() helper (ai/lib/ai.lib.php)
// instead of writing a second log table of our own: the question goes into
// llx_ai_request_log with provider='ebk', so the admin audits the assistant in
// the same place as the core AI Assistant and the MCP server (ai/admin/log_viewer.php).
// The helper is a no-op unless AI_LOG_REQUESTS is on, and it truncates both
// payloads at 60 000 chars, so nothing unbounded lands in the DB.
if (!empty($user->id) && function_exists('ai_log_request')) {
    require_once DOL_DOCUMENT_ROOT.'/ai/lib/ai.lib.php';
    ai_log_request(
        $db,
        $user,
        $question,
        array('tool' => 'ebk_assistant', 'arguments' => array('doc_type' => $docType, 'doc_id' => $docId, 'page' => $pageSection)),
        'ebk',
        $elapsed,
        1.0,
        $llmFailed ? 'Error' : 'Success',
        $llmFailed ? trim((string) EBKAiChatHelper::lastError()) : '',
        $contextBlock,
        $answer
    );
}

// ---------------------------------------------------------------------------
// Context builders
// ---------------------------------------------------------------------------

/**
 * Render the document currently open on screen into a flat, LLM-readable
 * block: header (type / ref / dates / third party / totals) then one line
 * per document line with amounts, VAT rate and the account currently bound
 * to it.
 *
 * Reuses EBKTabData::lineRows() so the numbers the model sees are exactly
 * the ones the EBK tab renders (CLAUDE.md §5 — no parallel implementation).
 *
 * @param  CommonObject $obj     Fetched Facture | FactureFournisseur | ExpenseReport (lines loaded)
 * @param  string       $docType 'customer_invoice'|'supplier_invoice'|'expense_report'
 * @return string
 */
function buildDocBlock($obj, $docType)
{
    global $db, $conf;

    $typeLabel = 'customer_invoice' === $docType ? '客户发票'
        : ('supplier_invoice' === $docType ? '供应商发票' : '费用报销单');

    $currency = !empty($obj->multicurrency_code) ? $obj->multicurrency_code : (isset($conf->currency) ? $conf->currency : '');

    $out  = "【当前单据】\n";
    $out .= "类型: {$typeLabel}\n";
    $out .= "编号: ".(isset($obj->ref) ? $obj->ref : '')."\n";
    $out .= "状态: ".(isset($obj->status) ? $obj->status : '')."（1=草稿 2=已验证 3=已付款 4=已关闭）\n";
    $out .= "单据日期: ".(isset($obj->date) ? $obj->date : '')."\n";
    if (isset($obj->date_modification) && !empty($obj->date_modification)) {
        $out .= "到期日: ".$obj->date_modification."\n";
    }
    if (is_object($obj->thirdparty)) {
        $out .= "第三方: ".$obj->thirdparty->name."\n";
    }
    if (isset($obj->user_author_id) && $obj->user_author_id > 0) {
        $out .= "制单人 user_id: ".$obj->user_author_id."\n";
    }
    $out .= "金额: 不含税 ".(isset($obj->total_ht) ? $obj->total_ht : 0);
    $out .= " / 税额 ".(isset($obj->total_tva) ? $obj->total_tva : 0);
    $out .= " / 含税 ".(isset($obj->total_ttc) ? $obj->total_ttc : 0)." ".$currency."\n";

    // Counterparty / user account currently bound in Accountancy.
    $counter = EBKTabData::counterRow($db, $obj, $docType);
    if (!empty($counter['account'])) {
        $out .= "对方往来科目: ".$counter['account']." ".$counter['label'];
        if (!empty($counter['subledger_label'])) {
            $out .= "（辅助核算: ".$counter['subledger_label']."）";
        }
        $out .= "\n";
    }

    // Lines.
    $lineRows = EBKTabData::lineRows($db, $obj, $docType);
    if (empty($lineRows)) {
        $out .= "\n(该单据没有明细行)\n\n";
        return $out;
    }

    $out .= "\n【明细行】共 ".count($lineRows)." 行\n";

    // On expense reports the expense TYPE (llx_c_type_fees) is the single best
    // signal for account suggestion ("餐饮" -> 630x). Fetched in one query.
    $feeTypes = array();
    if ($docType === 'expense_report') {
        $ids = array();
        foreach ($lineRows as $r) {
            if (!empty($r['fk_c_type_fees'])) {
                $ids[] = (int) $r['fk_c_type_fees'];
            }
        }
        $ids = array_unique($ids);
        if (!empty($ids)) {
            $sql = "SELECT ctf.id, ctf.code, ctf.label FROM ".MAIN_DB_PREFIX."c_type_fees AS ctf";
            $sql .= " WHERE ctf.id IN (".implode(',', $ids).")";
            $resql = $db->query($sql);
            if ($resql) {
                while ($o = $db->fetch_object($resql)) {
                    $feeTypes[(int) $o->id] = trim($o->code.' '.$o->label);
                }
                $db->free($resql);
            }
        }
    }

    foreach ($lineRows as $i => $r) {
        $no = $i + 1;
        $desc = trim((string) $r['description']);
        if ($desc === '') {
            $desc = '(无描述)';
        }
        $desc = dol_trunc($desc, 120, 'right', 'UTF-8', 1);

        $bound = '';
        if ($r['status'] === 'bound') {
            $bound = $r['bound_number'].' '.$r['bound_label'];
        } elseif ($r['status'] === 'suggested' && $r['suggested_number'] !== '') {
            $bound = $r['suggested_number'].' '.$r['suggested_label'].'（系统建议，尚未绑定）';
        } else {
            $bound = '未绑定';
        }

        $out .= "\n行 ".$no.": ".$desc."\n";
        if ($docType === 'expense_report' && !empty($r['fk_c_type_fees'])
            && isset($feeTypes[(int) $r['fk_c_type_fees']])) {
            $out .= "  费用类型: ".$feeTypes[(int) $r['fk_c_type_fees']]."\n";
        }
        $out .= "  数量: ".$r['qty']." / 不含税: ".$r['total_ht'];
        $out .= " / 税率: ".rtrim(rtrim(number_format((float) $r['tva_tx'], 2, '.', ''), '0'), '.');
        $out .= "% / 税额: ".$r['total_tva'];
        $out .= " / 含税: ".$r['total_ttc']."\n";
        $out .= "  当前科目: ".$bound."\n";
        if (!empty($r['npr'])) {
            $out .= "  注意: 该行标记为 NPR / 不可抵扣增值税\n";
        }
    }

    // VAT breakdown, so the model can post the tax legs correctly.
    $vat = EBKTabData::vatGroups($db, $lineRows, $docType, is_object($obj->thirdparty) ? $obj->thirdparty : null, null);
    if (!empty($vat)) {
        $out .= "\n【增值税汇总】\n";
        foreach ($vat as $g) {
            $out .= "  ".$g['account'].' '.($g['label'] ?: '(未设 VAT 科目)')
                .' — '.($g['label_operation'] ?: 'VAT').': '.$g['amount']."\n";
        }
    }

    $out .= "\n";
    return $out;
}

/**
 * Extract a query-string parameter from the PAGE url.
 *
 * Deliberately NOT GETPOST(): this endpoint is an AJAX call, so GETPOST() would
 * read chat.php's own query string, not the page the user is looking at. The
 * payload carries the page url precisely so the server can resolve the object
 * the user actually has on screen.
 *
 * @param  string $url
 * @param  string $name
 * @return string
 */
function paramFromPageUrl($url, $name)
{
    $qs = parse_url((string) $url, PHP_URL_QUERY);
    if (!$qs) {
        return '';
    }
    $pairs = array();
    parse_str($qs, $pairs);
    return isset($pairs[$name]) && is_scalar($pairs[$name]) ? (string) $pairs[$name] : '';
}

/**
 * Render the core Accountancy page currently open into LLM-readable text.
 *
 * The assistant is injected on every page (printCommonFooter), but on anything
 * that is not an invoice / expense report there is no doc_id to resolve, so
 * without this the model knew only the page name. This reads the accounting
 * object actually on screen.
 *
 * Objects are loaded through the core classes (Bookkeeping, AccountingAccount)
 * rather than raw SQL, so the entity / rights filtering core already applies is
 * reused instead of duplicated (CLAUDE.md §5).
 *
 * @param  DoliDB $db
 * @param  string $url  Current page URL (from the payload's page.url)
 * @return string       Empty string when the page is not an accounting page
 */
function buildAccountingBlock($db, $url)
{
    $path = parse_url((string) $url, PHP_URL_PATH);
    if (!is_string($path) || strpos($path, '/accountancy/') === false) {
        return '';
    }

    // ---- a single bookkeeping voucher (accountancy/bookkeeping/card.php?id=N) ----
    if (preg_match('#/accountancy/bookkeeping/card\.php#', $path)) {
        $bkId = (int) paramFromPageUrl($url, 'id');
        if ($bkId <= 0) {
            return "【当前页面】记账凭证卡片（未指定凭证号）\n\n";
        }
        require_once DOL_DOCUMENT_ROOT.'/accountancy/class/bookkeeping.class.php';
        $bk = new Bookkeeping($db);
        if ($bk->fetch($bkId) <= 0) {
            return "【当前页面】记账凭证卡片（凭证 ID ".$bkId." 未找到或无权访问）\n\n";
        }

        // fetch() alone leaves linesmvt EMPTY - the core card page loads the
        // movements with a second call (accountancy/bookkeeping/card.php does
        // exactly this). Without it every voucher would look line-less.
        $bk->fetchAllPerMvt((string) $bk->piece_num, (string) GETPOST('mode', 'aZ09'));

        $out  = "【当前记账凭证】\n";
        $out .= "凭证号 piece_num: ".(string) $bk->piece_num."\n";
        $out .= "记账日期: ".(string) $bk->doc_date."\n";
        $out .= "日记账: ".(string) $bk->code_journal." ".(string) $bk->journal_label."\n";
        $out .= "来源单据: ".(string) $bk->doc_type." ".(string) $bk->doc_ref."\n";
        if (!empty($bk->thirdparty_code)) {
            $out .= "第三方: ".(string) $bk->thirdparty_code."\n";
        }

        $lines = (is_array($bk->linesmvt) && !empty($bk->linesmvt)) ? $bk->linesmvt : $bk->lines;
        if (empty($lines)) {
            $out .= "(该凭证没有分录行)\n\n";
            return $out;
        }

        $out .= "\n【分录行】共 ".count($lines)." 行\n";
        $td = 0;
        $tc = 0;
        $i = 0;
        foreach ($lines as $line) {
            $i++;
            $acc = isset($line->numero_compte) ? (string) $line->numero_compte : '';
            $lab = isset($line->label_compte) ? (string) $line->label_compte : '';
            $op  = isset($line->label_operation) ? (string) $line->label_operation : '';
            $deb = isset($line->debit) ? (float) $line->debit : 0.0;
            $cre = isset($line->credit) ? (float) $line->credit : 0.0;
            $sub = isset($line->subledger_account) ? (string) $line->subledger_account : '';
            $subl = isset($line->subledger_label) ? (string) $line->subledger_label : '';
            $td += $deb;
            $tc += $cre;

            $out .= "\n行 ".$i.": ".$acc." ".$lab."\n";
            if ($op !== '') {
                $out .= "  摘要: ".$op."\n";
            }
            $out .= "  借方: ".$deb." / 贷方: ".$cre."\n";
            if ($sub !== '') {
                $out .= "  辅助核算: ".$sub.($subl !== '' ? ' '.$subl : '')."\n";
            }
        }
        $out .= "\n合计: 借 ".$td." / 贷 ".$tc."\n";
        if (abs($td - $tc) > 0.005) {
            $out .= "*** 借贷不平衡，差额 ".(round($td - $tc, 2))." ***\n";
        }
        $out .= "\n";
        return $out;
    }

    // ---- a chart-of-accounts entry (accountancy/admin/account.php?id=N) ----
    if (preg_match('#/accountancy/admin/account\.php#', $path)) {
        $accId = (int) paramFromPageUrl($url, 'id');
        if ($accId <= 0) {
            return "【当前页面】会计科目表\n\n";
        }
        require_once DOL_DOCUMENT_ROOT.'/accountancy/class/accountingaccount.class.php';
        $acc = new AccountingAccount($db);
        if ($acc->fetch($accId) <= 0) {
            return "【当前页面】会计科目（ID ".$accId." 未找到或无权访问）\n\n";
        }
        $out  = "【当前会计科目】\n";
        $out .= "科目编号: ".(string) $acc->account_number."\n";
        $out .= "科目名称: ".(string) $acc->label."\n";
        if (!empty($acc->labelshort)) {
            $out .= "简称: ".(string) $acc->labelshort."\n";
        }
        if (!empty($acc->account_parent)) {
            $out .= "上级科目: ".(string) $acc->account_parent."\n";
        }
        if (!empty($acc->account_category_label)) {
            $out .= "科目分类: ".(string) $acc->account_category_label."\n";
        }
        $out .= "状态: ".(!empty($acc->active) ? '启用' : '停用')."\n";
        $out .= "\n";
        return $out;
    }

    // ---- any other accounting page: pass the active filters through ----
    // The accounting screens are filter-driven (journal code, period, account),
    // so the query string is the most useful thing we can hand over.
    $out = "【当前页面】核心会计模块\n";
    $out .= "路径: ".$path."\n";

    $qs = parse_url((string) $url, PHP_URL_QUERY);
    if ($qs) {
        $pairs = array();
        parse_str($qs, $pairs);
        $interesting = array();
        foreach ($pairs as $k => $v) {
            // Never echo anything that could be a credential. This used to be a
            // three-key deny list (token/saction/page_y) which let token2, csrf_token
            // or sid straight into the prompt - so match on shape instead: any key
            // that smells like a secret or a session is dropped, whatever it is called.
            if (preg_match('/token|session|sessid|(^|[_.-])sid($|[_.-])|password|passwd|secret|api_?key|hash/i', (string) $k)) {
                continue;
            }
            // pure navigation noise (page_x, saction, backtopage…)
            if ($k === 'saction' || $k === 'backtopage' || preg_match('/^page_[xy]$/', (string) $k)) {
                continue;
            }
            if (!is_scalar($v) || (string) $v === '') {
                continue;
            }
            $interesting[] = $k.'='.(string) $v;
        }
        if (!empty($interesting)) {
            $out .= "页面筛选条件: ".implode(' / ', $interesting)."\n";
        }
    }
    $out .= "\n";
    return $out;
}

/**
 * Render the company's active chart of accounts as `number label` lines.
 *
 * The accounting module itself stores the code sheet in llx_accounting_account,
 * scoped to the chart selected by the CHARTOFACCOUNTS global (CLAUDE.md §4 —
 * multicompany: entity IN (0, current)). Passing this to the model is what
 * turns "差旅交通费" from a guess into a real account number of this company.
 *
 * @param  DoliDB $db
 * @return string
 */
function buildChartBlock($db)
{
    global $conf;

    $entity = isset($conf->entity) ? (int) $conf->entity : 1;

    $sql = "SELECT a.account_number, a.label, a.labelshort";
    $sql .= " FROM ".MAIN_DB_PREFIX."accounting_account AS a";
    $sql .= " WHERE a.active = 1 AND a.entity IN (0, ".$entity.")";
    // Restrict to the chart selected in Accountancy setup when there is one.
    $chartId = (int) dol_getIdFromCode($db, getDolGlobalString('CHARTOFACCOUNTS'), 'accounting_system', 'rowid', 'pcg_version');
    if ($chartId > 0) {
        $sql .= " AND a.fk_pcg_version IN (SELECT pcg_version FROM ".MAIN_DB_PREFIX."accounting_system WHERE rowid = ".$chartId.")";
    }
    $sql .= " ORDER BY a.account_number ASC";

    $resql = $db->query($sql);
    if (!$resql) {
        return "【本公司会计科目表】\n(读取会计科目表失败: ".$db->lasterror.")\n\n";
    }

    $rows = array();
    while ($o = $db->fetch_object($resql)) {
        $rows[] = (string) $o->account_number.'  '.(string) $o->label;
    }
    $db->free($resql);

    if (empty($rows)) {
        return "【本公司会计科目表】\n(会计科目表为空或未启用，请先在 会计 > 设置 > 会计科目 中导入)\n\n";
    }

    return "【本公司会计科目表】共 ".count($rows)." 个启用科目（只能从中选择）\n"
        .implode("\n", $rows)."\n\n";
}

// --- JSON response -------------------------------------------------------
$out = array(
    'ok'     => true,
    'answer' => $answer,
    'error'  => '',
);

if (!empty($conf->global->EMBEDDEDBOOKKEEPING_AI_DEBUG) && !empty($user->admin)) {
    $out['debug'] = array(
        'provider'   => is_object($provider) ? get_class($provider) : 'unknown',
        'doc_id'     => $docId,
        'doc_type'   => $docType,
        'doc_ref'    => $docRef,
        'page'       => $pageInfo,
        'ctx_len'    => strlen($contextBlock),
        'server_ts'  => time(),
    );
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
exit;
