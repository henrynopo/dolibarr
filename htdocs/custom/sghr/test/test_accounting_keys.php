<?php
/**
 * \file        test/test_accounting_keys.php
 * \ingroup sghr
 * \brief       CLI verification of the payroll -> accounting journal key logic.
 *
 * Usage:  php htdocs/custom/sghr/test/test_accounting_keys.php
 *
 * This script is self-contained and does NOT require a database.
 *
 * Background:
 *   BookKeeping::create() (core accountancy module) refuses to post a line when a
 *   line with the same key already exists. The key is:
 *       doc_type + fk_doc + fk_docdet + numero_compte + label_operation (+ subledger)
 *   The payroll accounting trigger used to post with doc_type='sghr', fk_doc=0 and an
 *   empty label_operation, so every two lines sharing a GL account (gross salary and
 *   employer CPF both on the salary account, employee and employer CPF both on the CPF
 *   account) were seen as duplicates: the posting failed and the payroll approval was
 *   rejected with an empty "Approval failed" message.
 *
 * The test replays the key computation with the old and the new values and checks that
 * the new ones do not collide, including the re-approval and multi-employee cases.
 */

define('DOL_DOCUMENT_ROOT', realpath(__DIR__.'/../../..'));
define('MAIN_DB_PREFIX', 'llx_');

// Stubs for Dolibarr global functions (not available in CLI test context)
if (!function_exists('dol_syslog'))         { function dol_syslog($msg, $level = 0) {} }
if (!function_exists('getDolGlobalString')) { function getDolGlobalString($key, $default = '') { return $default; } }
if (!function_exists('isModEnabled'))       { function isModEnabled($mod) { return true; } }
if (!function_exists('setEventMessages'))   { function setEventMessages($mesg, $mesgs, $style = 'mesgs') {} }
if (!function_exists('dol_now'))            { function dol_now() { return 1750000000; } }

// ── Colour helpers ────────────────────────────────────────────────────────────
function pass($msg) { echo "\033[32m[PASS]\033[0m $msg\n"; }
function fail($msg) { echo "\033[31m[FAIL]\033[0m $msg\n"; }
function section($s) { echo "\n\033[1;34m=== $s ===\033[0m\n"; }

/**
 * Build the journal entries of one payslip (same list as the accounting trigger).
 *
 * @param   string  $glSalary    GL account of the salary expense
 * @param   string  $glCpf       GL account of the CPF payable
 * @param   string  $glSdl       GL account of the SDL payable
 * @param   string  $glBank      GL account of the bank / net salary
 * @param   float   $gross       Gross salary
 * @param   float   $empCpf      Employee CPF
 * @param   float   $erCpf       Employer CPF
 * @param   float   $sdl         SDL
 * @param   float   $netPay      Net pay
 * @param   string  $payRef      Payroll reference
 * @return  array   List of array(account, label, debit, credit)
 */
function build_entries($glSalary, $glCpf, $glSdl, $glBank, $gross, $empCpf, $erCpf, $sdl, $netPay, $payRef)
{
	$entries = array();
	if ($gross > 0) {
		$entries[] = array($glSalary, 'Salary '.$payRef, $gross, 0);
	}
	if ($empCpf > 0) {
		$entries[] = array($glCpf, 'CPF Employee '.$payRef, 0, $empCpf);
	}
	if ($erCpf > 0) {
		$entries[] = array($glSalary, 'Employer CPF '.$payRef, $erCpf, 0);
		$entries[] = array($glCpf,    'CPF Employer '.$payRef, 0, $erCpf);
	}
	if ($sdl > 0 && $glSdl) {
		$entries[] = array($glSalary, 'SDL '.$payRef, $sdl, 0);
		$entries[] = array($glSdl,    'SDL Payable '.$payRef, 0, $sdl);
	}
	if ($netPay > 0) {
		$entries[] = array($glBank, 'Net Salary '.$payRef, 0, $netPay);
	}
	return $entries;
}

/**
 * Replay the duplicate detection of BookKeeping::create().
 *
 * @param   array   $posted     Already posted lines (list of key arrays)
 * @param   array   $entry      Entry to post (account, label, debit, credit)
 * @param   int     $lineId     Payslip line id (fk_doc)
 * @param   bool    $oldKeys    true = old behaviour (fk_doc=0, empty label_operation)
 * @return  bool                true when the line would be refused as a duplicate
 */
function is_duplicate(array $posted, $entry, $lineId, $oldKeys)
{
	$account = $entry[0];
	$label   = $entry[1];
	$key = $oldKeys
		? array('sghr', 0, 0, $account, '')
		: array('sghr', $lineId, 0, $account, $label);

	foreach ($posted as $p) {
		if ($p === $key) {
			return true;
		}
	}
	return false;
}

// ── Test data ─────────────────────────────────────────────────────────────────
$GL_SALARY = '6100';
$GL_CPF    = '2000';
$GL_SDL    = '2100';
$GL_BANK   = '1200';

$gross  = 5000.0;
$empCpf = 850.0;
$erCpf  = 850.0;
$sdl    = 100.0;
$netPay = 3200.0;

$payRef = 'PAY-2026-10';
$lineId = 42;

section('1. Old keys (fk_doc=0, empty label_operation) collide');
$entries = build_entries($GL_SALARY, $GL_CPF, $GL_SDL, $GL_BANK, $gross, $empCpf, $erCpf, $sdl, $netPay, $payRef);
$posted = array();
$collisions = 0;
foreach ($entries as $i => $entry) {
	if (is_duplicate($posted, $entry, $lineId, true)) {
		$collisions++;
		fail('Entry #'.($i + 1).' ('.$entry[1].') is refused as a duplicate -> approval failed');
	}
	$posted[] = array('sghr', 0, 0, $entry[0], '');
}
if ($collisions > 0) {
	pass('Old keys reproduce the bug: '.$collisions.' duplicate collision(s) on a single payslip');
} else {
	fail('Old keys were expected to collide but did not - the test scenario is wrong');
}

section('2. New keys (fk_doc=payslip line, label_operation=label) do not collide');
$posted = array();
$collisions = 0;
foreach ($entries as $i => $entry) {
	if (is_duplicate($posted, $entry, $lineId, false)) {
		$collisions++;
		fail('Entry #'.($i + 1).' ('.$entry[1].') is refused as a duplicate');
	}
	$posted[] = array('sghr', $lineId, 0, $entry[0], $entry[1]);
}
if ($collisions === 0) {
	pass('New keys: all '.count($entries).' entries of the payslip are accepted');
} else {
	fail('New keys still collide on '.$collisions.' entry/entries');
}

section('3. Two employees of the same month do not collide');
$posted = array();
$collisions = 0;
foreach (array(42 => $payRef.' A', 43 => $payRef.' B') as $id => $ref) {
	$entries = build_entries($GL_SALARY, $GL_CPF, $GL_SDL, $GL_BANK, $gross, $empCpf, $erCpf, $sdl, $netPay, $ref);
	foreach ($entries as $entry) {
		if (is_duplicate($posted, $entry, $id, false)) {
			$collisions++;
			fail('Employee '.$id.' line ('.$entry[1].') is refused as a duplicate');
		}
		$posted[] = array('sghr', $id, 0, $entry[0], $entry[1]);
	}
}
if ($collisions === 0) {
	pass('New keys: a second employee of the same month is accepted');
} else {
	fail('New keys collide between employees on '.$collisions.' entry/entries');
}

section('4. Re-approval of the same payslip is detected as "already exists" (skipped, not failed)');
$entries = build_entries($GL_SALARY, $GL_CPF, $GL_SDL, $GL_BANK, $gross, $empCpf, $erCpf, $sdl, $netPay, $payRef);
$posted = array();
foreach ($entries as $entry) {
	$posted[] = array('sghr', $lineId, 0, $entry[0], $entry[1]);
}
$alreadyExists = 0;
foreach ($entries as $entry) {
	if (is_duplicate($posted, $entry, $lineId, false)) {
		$alreadyExists++;
	}
}
if ($alreadyExists === count($entries)) {
	pass('Re-approval: every entry is reported as already existing -> skipped by the trigger');
} else {
	fail('Re-approval: '.$alreadyExists.'/'.count($entries).' entries detected as already existing');
}

echo "\n\033[1;32mAll checks done.\033[0m\n";
