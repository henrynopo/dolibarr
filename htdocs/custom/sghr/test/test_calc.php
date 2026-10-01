<?php
/**
 * \file        test/test_calc.php
 * \ingroup sghr
 * \brief       CLI calculation engine verification script.
 *
 * Usage:  php htdocs/custom/sghr/test/test_calc.php
 *
 * This script is self-contained — it loads the calc class directly.
 * It does NOT require a database (rates are passed as mock arrays).
 * For SDL/SHG tests without DB, pass null to use hardcoded defaults.
 */

define('DOL_DOCUMENT_ROOT', realpath(__DIR__.'/../../..'));
define('MAIN_DB_PREFIX', 'llx_');

// Stubs for Dolibarr global functions (not available in CLI test context)
if (!function_exists('dol_syslog'))          { function dol_syslog($msg, $level = 0) {} }
if (!function_exists('getDolGlobalString'))  { function getDolGlobalString($key, $default = '') { return $default; } }
if (!function_exists('isModEnabled'))        { function isModEnabled($mod) { return true; } }
if (!defined('LOG_WARNING'))                 { define('LOG_WARNING', 300); }
if (!defined('LOG_INFO'))                   { define('LOG_INFO', 200); }
if (!defined('LOG_DEBUG'))                  { define('LOG_DEBUG', 100); }

require_once __DIR__.'/../class/payrollcalc.class.php';

// ── Colour helpers ────────────────────────────────────────────────────────────
function pass($msg) { echo "\033[32m[PASS]\033[0m $msg\n"; }
function fail($msg) { echo "\033[31m[FAIL]\033[0m $msg\n"; }
function section($s) { echo "\n\033[1;34m=== $s ===\033[0m\n"; }
function assertEq($got, $expected, $label)
{
	if (abs($got - $expected) < 0.005) {
		pass("$label => $got");
	} else {
		fail("$label: expected $expected got $got");
	}
}
function assertStrEq($got, $expected, $label)
{
	if ((string)$got === (string)$expected) {
		pass("$label => $got");
	} else {
		fail("$label: expected $expected got $got");
	}
}

// ── SDL Tests (without DB → uses hardcoded defaults) ──────────────────────────────────
section('SDL – Skills Development Levy (no DB = defaults)');
// Min $2 for wages < $800
assertEq(SghrCalc::calculateSDL(null, 400, '2025-03-01'),  2.00,  'SDL($400) = $2.00 min');
assertEq(SghrCalc::calculateSDL(null, 800, '2025-03-01'),  2.00,  'SDL($800) = $2.00 min');
assertEq(SghrCalc::calculateSDL(null, 2000, '2025-03-01'), 5.00,  'SDL($2000) = $5.00');
assertEq(SghrCalc::calculateSDL(null, 4500, '2025-03-01'), 11.25, 'SDL($4500) = $11.25 max');
assertEq(SghrCalc::calculateSDL(null, 9000, '2025-03-01'), 11.25, 'SDL($9000) = $11.25 max (capped)');

// ── SHG Tests (without DB → uses hardcoded defaults) ──────────────────────────────────
section('SHG – Self-Help Group Contributions (no DB = defaults)');
$shg = SghrCalc::calculateSHG(null, 1500, 'Chinese', false, array(), '2025-03-01');
assertEq($shg['cdac'],  0.50, 'CDAC($1500)');
assertEq($shg['total'], 0.50, 'SHG total Chinese($1500)');

// CDAC: official ">2000–3500" is inclusive of $3,500; next tier starts strictly above $3,500.
$shg = SghrCalc::calculateSHG(null, 3500, 'Chinese', false, array(), '2025-03-01');
assertEq($shg['cdac'],  1.00, 'CDAC($3500) still $1.00 bracket');
$shg = SghrCalc::calculateSHG(null, 3501, 'Chinese', false, array(), '2025-03-01');
assertEq($shg['cdac'],  1.50, 'CDAC($3501) $1.50 bracket');

$shg = SghrCalc::calculateSHG(null, 5200, 'Chinese', false, array(), '2025-03-01');
assertEq($shg['cdac'],  2.00, 'CDAC($5200)');

$shg = SghrCalc::calculateSHG(null, 8500, 'Chinese', false, array(), '2025-03-01');
assertEq($shg['cdac'],  3.00, 'CDAC($8500)');

$shg = SghrCalc::calculateSHG(null, 5000, 'Indian', false, array(), '2025-03-01');
assertEq($shg['sinda'], 9.00, 'SINDA($5000)'); // bracket $4,501–$7,500 → $9

$shg = SghrCalc::calculateSHG(null, 4500, 'Malay', true, array(), '2025-03-01'); // Muslim Malay
assertEq($shg['mbmf'],  19.50,'MBMF($4500) Muslim');

$shg = SghrCalc::calculateSHG(null, 3000, 'Eurasian', false, array(), '2025-03-01');
assertEq($shg['ecf'],   9.00, 'ECF($3000)');

// Opt-out test
$shg = SghrCalc::calculateSHG(null, 5000, 'Chinese', false, array('cdac' => true), '2025-03-01');
assertEq($shg['cdac'],  0.00, 'CDAC opted out');

// ── UPL Deduction Tests ───────────────────────────────────────────────────────
section('UPL – Unpaid Leave Deduction');
// $3000 basic, 22 working days, 2 UPL days → 3000/22*2 = 272.73
assertEq(SghrCalc::calculateUnpaidLeaveDeduction(3000, 22, 2), 272.73, 'UPL($3000,22days,2UPL)');
assertEq(SghrCalc::calculateUnpaidLeaveDeduction(5000, 26, 0), 0.00,   'UPL 0 days = $0');
assertEq(SghrCalc::calculateUnpaidLeaveDeduction(0,    22, 3), 0.00,   'UPL zero basic = $0');

// ── OT Pay Tests ──────────────────────────────────────────────────────────────
section('OT – Overtime Pay (MOM formula)');
// MOM hourly rate = Monthly Basic / 190.6667
// $2600 basic → hourly = 13.6364; OT 1.5× for 10h = 204.55
$ot = SghrCalc::calculateOvertimePay(2600, 10, 1.5);
assertEq($ot['hourly_rate'], 13.6364, 'OT Hourly Rate ($2600)');
assertEq($ot['ot_pay'],      204.55,  'OT Pay $2600 × 1.5 × 10h');

$ot = SghrCalc::calculateOvertimePay(3000, 8, 2.0);
assertEq($ot['ot_pay'], round(3000/190.6667 * 2.0 * 8, 2), 'OT Pay $3000 × 2.0 × 8h (PH)');

$ot = SghrCalc::calculateOvertimePay(0, 5, 1.5);
assertEq($ot['ot_pay'], 0.00, 'OT with zero basic = $0');

// ── Withholding Tax ───────────────────────────────────────────────────────────
section('Withholding Tax – Non-residents');
assertEq(SghrCalc::calculateWithholdingTax(5000, 'non_resident'), 750.00, 'WHT 15% on $5000');
assertEq(SghrCalc::calculateWithholdingTax(5000, 'resident'),     0.00,   'WHT $0 for resident');

// ── Age Calculation (CPF rule: age band changes from next month) ─────────────────
section('Age Calculation (CPF rule)');
// New signature: calcAge($dob, $payYear, $payMonth)
// CPF rule: if payslip month == birthday month, use previous age bracket
$age = SghrCalc::calcAge('1970-06-15', 2025, 3);   // June payslip, birthday June -> age 54
assertEq($age, 54, 'Age: born 1970-06-15 payslip 2025-03 = 54 (before birthday month)');
$age = SghrCalc::calcAge('1970-06-15', 2025, 6);   // June payslip, birthday June -> age 54
assertEq($age, 54, 'Age: born 1970-06-15 payslip 2025-06 = 54 (still in birthday month)');
$age = SghrCalc::calcAge('1970-06-15', 2025, 7);   // July payslip -> birthday month passed -> age 55
assertEq($age, 55, 'Age: born 1970-06-15 payslip 2025-07 = 55 (after birthday month)');
$age = SghrCalc::calcAge('1971-02-06', 2026, 2);   // Feb payslip, birthday Feb -> age 54
assertEq($age, 54, 'Age: born 1971-02-06 payslip 2026-02 = 54 (birthday month)');
$age = SghrCalc::calcAge('1971-02-06', 2026, 3);   // Mar payslip -> age 55
assertEq($age, 55, 'Age: born 1971-02-06 payslip 2026-03 = 55 (after birthday month)');

// ── SPR Tier Derivation (CPF month-boundary rule) ─────────────────────────────
section('SPR Tier – month-boundary rule');
// Example guidance: SPR on 13 Aug 2021 -> PR2 from 1 Sep 2022; PR3 from 1 Sep 2023
assertStrEq(SghrCalc::derivePRTier('2021-08-13', '2022-08-31'), 'PR1Y', 'SPR 2021-08-13 as at 2022-08-31 = PR1Y');
assertStrEq(SghrCalc::derivePRTier('2021-08-13', '2022-09-01'), 'PR2Y', 'SPR 2021-08-13 as at 2022-09-01 = PR2Y');
assertStrEq(SghrCalc::derivePRTier('2021-08-13', '2023-08-31'), 'PR2Y', 'SPR 2021-08-13 as at 2023-08-31 = PR2Y');
assertStrEq(SghrCalc::derivePRTier('2021-08-13', '2023-09-01'), 'PR3Y', 'SPR 2021-08-13 as at 2023-09-01 = PR3Y');

// ── CPF Calculation (using mock DB) ───────────────────────────────────────────
section('CPF – Mock DB Rate Lookup');
// We create a mock $db that returns a known rate row
class MockDb {
	public function query($sql)    { return true; }
	public function num_rows($r)   { return 1; }
	public function fetch_array($r) {
		return array(
			'employer_rate'     => '0.1700',
			'employee_rate'     => '0.2000',
			'ow_ceiling'        => '7400.00',
			'aw_annual_ceiling' => '102000.00',
			'oa_pct'            => '0.6217',
			'sa_pct'            => '0.2162',
			'ma_pct'            => '0.1621',
		);
	}
	public function escape($s) { return $s; }
}
$mockDb = new MockDb();

// SC ≤55: OW $4000, AW $0
$cpf = SghrCalc::calculateCPF($mockDb, 4000, 0, 35, 'SC', '2025-03-01', 0);
assertEq($cpf['employee'], 800.00,  'SC ≤55: Employee CPF on $4000 OW (20%)');
assertEq($cpf['employer'], 680.00,  'SC ≤55: Employer CPF on $4000 OW (17%)');

// OW ceiling cap: OW $9000 → capped at $7400
$cpf = SghrCalc::calculateCPF($mockDb, 9000, 0, 35, 'SC', '2025-03-01', 0);
assertEq($cpf['ow_cpf_base'], 7400.00, 'OW capped at ceiling $7400');
assertEq($cpf['employee'],    1480.00, 'Employee CPF on capped OW $7400');

// Freelancer: no CPF
$cpf = SghrCalc::calculateCPF($mockDb, 5000, 0, 35, 'FIN', '2025-03-01', 0);
assertEq($cpf['employee'], 0, 'FIN/Foreigner: no CPF');

echo "\n\033[1;32mAll tests completed.\033[0m\n";
