<?php
/**
 * \file        patches/apply_previewdocuments_hotfix.php
 * \ingroup    previewdocuments
 * \brief       Idempotent hot-fix for "Undefined variable $customtxt" in js/previewdocuments.js.php
 *
 * Usage on the production server:
 *     php /path/to/erp/custom/previewdocuments/patches/apply_previewdocuments_hotfix.php
 *     php .../apply_previewdocuments_hotfix.php /absolute/path/to/previewdocuments.js.php
 *
 * Why a script instead of a patch file:
 *   The deployed copy of js/previewdocuments.js.php on the server is not identical to
 *   the repository version (line numbers differ by ~9), so a unified diff cannot be
 *   applied reliably. This script works on the *content* instead of on line numbers,
 *   is idempotent (safe to run twice) and keeps a .bak backup.
 *
 * What it does:
 *   1. Replaces every "DOL_MAIN_URL_ROOT.$customtxt" by a null-safe expression
 *      "DOL_MAIN_URL_ROOT.(isset($customtxt) ? $customtxt : '')".
 *   2. Guarantees an initialisation of $customtxt near the top of the file, so the
 *      variable is defined even when the optional blocks are commented out.
 *   3. Checks the PHP syntax of the result and restores the backup if it is broken.
 *
 * @return int 0=ok 1=error
 */

if (PHP_SAPI !== 'cli') {
    die("This script must be run from the command line.\n");
}

$defaultFile = dirname(__DIR__).'/js/previewdocuments.js.php';
$file = isset($argv[1]) && trim($argv[1]) !== '' ? $argv[1] : $defaultFile;

echo "Target file: ".$file."\n";

if (!is_file($file) || !is_readable($file)) {
    echo "[ERROR] File not found or not readable: ".$file."\n";
    exit(1);
}

$original = file_get_contents($file);
if ($original === false) {
    echo "[ERROR] Unable to read the file.\n";
    exit(1);
}

$content = $original;
$changes = 0;

// ── 1. Null-safe use of $customtxt ────────────────────────────────────────────
$patterns = array(
    'DOL_MAIN_URL_ROOT.$customtxt' => 'DOL_MAIN_URL_ROOT.(isset($customtxt) ? $customtxt : \'\')',
    'DOL_MAIN_URL_ROOT . $customtxt' => 'DOL_MAIN_URL_ROOT.(isset($customtxt) ? $customtxt : \'\')',
    'DOL_MAIN_URL_ROOT.$customtxt;' => 'DOL_MAIN_URL_ROOT.(isset($customtxt) ? $customtxt : \'\');',
);
foreach ($patterns as $from => $to) {
    $count = 0;
    $content = str_replace($from, $to, $content, $count);
    $changes += $count;
    if ($count > 0) {
        echo "[OK] Replaced ".$count." occurrence(s) of '".$from."'\n";
    }
}

// ── 2. Make sure $customtxt is initialised ────────────────────────────────────
// Only the top level scope matters: an assignment inside a function is a local
// variable and does not protect the top level echo statements.
$firstFunction = strpos($content, 'function ');
$topScope = $firstFunction === false ? $content : substr($content, 0, $firstFunction);
if (!preg_match('/\$customtxt\s*=\s*[\'"]/', $topScope)) {
    // Insert the initialisation right after the main.inc.php include block.
    $anchor = '$langs->load(\'previewdocuments@previewdocuments\');';
    $init = "// Ensure \$customtxt is always defined, even if optional logic below is commented out\n"
        . "if (!isset(\$customtxt)) {\n\t\$customtxt = '';\n}\n";
    if (strpos($content, $anchor) !== false) {
        $content = str_replace($anchor, $init."\n".$anchor, $content);
        echo "[OK] Added the initialisation of \$customtxt\n";
        $changes++;
    } else {
        echo "[WARN] Could not find the insertion point (\$langs->load('previewdocuments@previewdocuments')).\n";
        echo "       The null-safe replacement above is still enough to remove the warnings.\n";
    }
}

// ── 3. Write the file (with backup) and check the syntax ──────────────────────
if ($changes === 0) {
    echo "[OK] Nothing to do: the file is already patched.\n";
    exit(0);
}

$backup = $file.'.bak-'.date('Ymd-His');
if (!@copy($file, $backup)) {
    echo "[ERROR] Unable to create the backup file ".$backup."\n";
    exit(1);
}
echo "[OK] Backup: ".$backup."\n";

if (@file_put_contents($file, $content) === false) {
    echo "[ERROR] Unable to write the file (check the permissions).\n";
    exit(1);
}

// Syntax check
$tmp = sys_get_temp_dir().'/pvdocs_check_'.getmypid().'.php';
file_put_contents($tmp, $content);
$cmd = escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($tmp);
$out = array();
$rc = 0;
exec($cmd, $out, $rc);
@unlink($tmp);

if ($rc !== 0) {
    echo "[ERROR] Syntax check failed, restoring the backup:\n".implode("\n", $out)."\n";
    @copy($backup, $file);
    exit(1);
}

echo "[OK] File patched and syntax checked (".count($out)." line(s) checked).\n";
echo "[DONE] Reload a page using the previewdocuments popup: the warnings must be gone.\n";
exit(0);
