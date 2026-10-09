<?php
/* Copyright (C) 2026 EmbeddedBookkeeping
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 *	\file       htdocs/custom/embeddedbookkeeping/class/actions_embeddedbookkeeping.class.php
 *	\ingroup    embeddedbookkeeping
 *	\brief      Hook actions for EmbeddedBookkeeping module. Since 1.1.0 the
 *	             bookkeeping entry point lives in the "Accounting entries" TAB
 *	             (tabs/bookkeeping.php, registered via the descriptor); the only
 *	             card hook left is addMoreActionsButtons, rendering one deep-link
 *	             button to that tab.
 *
 *	             The former button + hidden-modal flow (formConfirm / doActions /
 *	             formObjectOptions traits) was removed: the modal never reached
 *	             the page ($parameters passed by value) and its only opener JS
 *	             required a working AI provider — blocking manual bookkeeping
 *	             entirely when AI was not configured.
 */

/**
 *	Class ActionsEmbeddedBookkeeping
 */

require_once DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/class/ActionsEmbeddedBookkeepingUiTrait.php';

class ActionsEmbeddedBookkeeping
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/** @var string|null Output for hooks that print (set by HookManager into resprints) */
	public $resprints;

	/** @var array Hook return data (rarely used here) */
	public $results = array();

	/** @var int Hook priority (higher = earlier); kept neutral to not collide with slycustom (also 50) */
	public $priority = 50;

	use ActionsEmbeddedBookkeepingUiTrait;

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Hook for `addMoreActionsButtons`.
	 *
	 * @param array        $parameters
	 * @param CommonObject $object
	 * @param string       $action
	 * @param string       $hookname
	 * @return int
	 */
	public function addMoreActionsButtons($parameters, $object, $action, $hookname)
	{
		return $this->uiAddMoreActionsButtons($parameters, $object, $action, $hookname);
	}

	/**
	 * Hook: printCommonFooter — fires from llxFooter() on every Dolibarr page.
	 * Injects the EBK floating AI assistant widget (accounting scope only).
	 *
	 * @param array  $parameters  Hook parameters (contains 'zone')
	 * @param mixed  $object     Always null (printCommonFooter passes null)
	 * @param string $action     Current action
	 * @param string $hookname   Hook name
	 * @return int               0 on success
	 */
	public function printCommonFooter($parameters, $object, $action, $hookname)
	{
		global $conf, $user;

		// Only render for users with AI suggest permission OR admins
		$hasAiPerm = !empty($user->rights->embeddedbookkeeping->ai_suggest);
		$isAdmin = !empty($user->admin);
		if (!$hasAiPerm && !$isAdmin) {
			return 0;
		}

		// Load widget JS once (guard prevents double-injection across multiple hooks)
		static $widgetLoaded = false;
		if ($widgetLoaded) {
			return 0;
		}
		$widgetLoaded = true;

		// Detect page section from URL
		$url = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
		$section = 'Dolibarr';
		if (strpos($url, '/accountancy/') !== false) {
			if (strpos($url, 'bookkeeping') !== false) $section = '会计记账';
			elseif (strpos($url, 'closure') !== false) $section = '会计结账';
			elseif (strpos($url, 'journal') !== false) $section = '会计日记账';
			elseif (strpos($url, 'export') !== false) $section = '会计导出';
			elseif (strpos($url, 'admin') !== false) $section = '会计管理';
			else $section = '会计模块';
		} elseif (strpos($url, '/compta/facture') !== false) {
			$section = '客户发票';
		} elseif (strpos($url, '/fourn/facture') !== false) {
			$section = '供应商发票';
		} elseif (strpos($url, '/expensereport') !== false) {
			$section = '费用报销';
		} elseif (strpos($url, '/custom/embeddedbookkeeping') !== false) {
			$section = '嵌入式记账';
		}

		// Doc context (doc_id + doc_type) if an object is on the page
		$docId   = 0;
		$docType = '';
		if (is_object($object) && !empty($object->id)) {
			$docId = (int) $object->id;
			$docType = $object->element ?? ($object->table_element ?? '');
			if ($docType === 'facture') $docType = 'customer_invoice';
			elseif ($docType === 'facture_fournisseur') $docType = 'supplier_invoice';
			elseif ($docType === 'expensereport') $docType = 'expense_report';
			elseif (!in_array($docType, array('customer_invoice','supplier_invoice','expense_report'), true)) {
				$docType = '';
			}
		}

		// printCommonFooter is called from llxFooter() with $object === null, so on
		// the invoice / expense-report CARDS we must recover the document identity
		// from the URL + GET params ourselves. Without this the AI assistant had no
		// idea which document the user was looking at and answered in the abstract.
		if ($docId <= 0 && $docType === '') {
			$script = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '';
			$pathFromCustom = strpos($script, '/custom/');
			if ($pathFromCustom !== false) {
				$script = substr($script, $pathFromCustom);
			}
			$getId = (int) GETPOST('id', 'int');
			if ($getId > 0) {
				if (preg_match('#/compta/facture/card\.php#', $script)) {
					$docId = $getId;
					$docType = 'customer_invoice';
				} elseif (preg_match('#/fourn/facture/card\.php#', $script)) {
					$docId = $getId;
					$docType = 'supplier_invoice';
				} elseif (preg_match('#/expensereport/card\.php#', $script)) {
					$docId = $getId;
					$docType = 'expense_report';
				}
			}
			// The EBK "Accounting entries" tab is loaded in an iframe/tab with
			// objecttype spelled out in the query string.
			if ($docId <= 0 && strpos($script, '/embeddedbookkeeping/tabs/bookkeeping.php') !== false) {
				$getType = (string) GETPOST('objecttype', 'alphanohtml');
				if ($getId > 0 && in_array($getType, array('customer_invoice', 'supplier_invoice', 'expense_report'), true)) {
					$docId = $getId;
					$docType = $getType;
				}
			}
		}

		// Ref of the document, when we already know which one it is. Lets the AI
		// quote the number (ER26109, FA-2026-001…) without a second lookup.
		$docRef = '';
		if ($docId > 0 && $docType !== '') {
			$docRef = self::fetchDocRef($docType, $docId);
		}

		// URL of the card, so the AI can be told what the user is looking at.
		$docUrl = '';
		if ($docId > 0 && $docType !== '') {
			$cardByType = array(
				'customer_invoice' => '/compta/facture/card.php',
				'supplier_invoice' => '/fourn/facture/card.php',
				'expense_report'   => '/expensereport/card.php',
			);
			$docUrl = DOL_URL_ROOT.$cardByType[$docType].'?id='.$docId;
		}
		?>
<?php
		// Cache-bust the widget JS on its mtime. Without this a browser that
		// cached an older widget.js keeps running it after an update, which
		// looks exactly like the assistant "not working" on some pages.
		$widgetUrl = dol_buildpath('/custom/embeddedbookkeeping/js/widget.js', 1);
		$widgetFile = DOL_DOCUMENT_ROOT.'/custom/embeddedbookkeeping/js/widget.js';
		if (is_readable($widgetFile)) {
			$widgetUrl .= '?v='.(int) filemtime($widgetFile);
		}
		?>
<script src="<?php echo $widgetUrl; ?>"></script>
<script>
(function () {
    if (!window.EBKAiWidget) return;
    window.EBKAiWidget.init({
        endpoint: <?php echo json_encode(dol_buildpath('/custom/embeddedbookkeeping/ajax/chat.php', 1)); ?>,
        token: <?php echo json_encode(newToken()); ?>,
        doc_id: <?php echo (int) $docId; ?>,
        doc_type: <?php echo json_encode($docType); ?>,
        doc_ref: <?php echo json_encode($docRef); ?>,
        doc_url: <?php echo json_encode($docUrl); ?>,
        context: {
            section: <?php echo json_encode($section); ?>,
            title: <?php echo json_encode($docRef !== '' ? $docRef : (is_object($object) && !empty($object->ref) ? (string) $object->ref : '')); ?>,
            url: <?php echo json_encode(isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : ''); ?>
        }
    });
})();
</script>
		<?php
		return 0;
	}

	/**
	 * Read the human-readable reference (FA-2026-0001, ER26109, …) of a document
	 * without loading the whole business object. Only used to label the AI chat.
	 *
	 * @param  string $docType  'customer_invoice'|'supplier_invoice'|'expense_report'
	 * @param  int    $docId    Rowid of the document
	 * @return string           Reference, or '' when not found
	 */
	private static function fetchDocRef($docType, $docId)
	{
		global $db;

		$tableByType = array(
			'customer_invoice' => 'facture',
			'supplier_invoice' => 'facture_fourn',
			'expense_report'   => 'expensereport',
		);
		if (!isset($tableByType[$docType])) {
			return '';
		}

		$sql = "SELECT ref FROM ".MAIN_DB_PREFIX.$tableByType[$docType]." WHERE rowid = ".(int) $docId;
		$resql = $db->query($sql);
		if ($resql) {
			$obj = $db->fetch_object($resql);
			$db->free($resql);
			if ($obj && !empty($obj->ref)) {
				return (string) $obj->ref;
			}
		}
		return '';
	}
}
