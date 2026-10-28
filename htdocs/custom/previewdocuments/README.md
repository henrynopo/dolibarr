# previewdocuments

Custom Dolibarr module: document preview / download popup used by several business
modules (invoices, orders, shipments, employee documents…).

The module is self-contained: it does not require the `slycustom` module, and it
does **not** modify any Dolibarr core file. Changes are deployed by uploading the
changed files of this module.

## Hot-fix: "Undefined variable $customtxt" (2026-10-28)

### Symptom

```
PHP Warning: Undefined variable $customtxt in
/home/haosg/HaoBiz/erp/custom/previewdocuments/js/previewdocuments.js.php on line 369
```

### Cause

`js/previewdocuments.js.php` builds the URL of the loading spinner with
`DOL_MAIN_URL_ROOT.$customtxt`. The `$customtxt` variable is only assigned inside
optional blocks that are commented out in the module, so the variable is undefined
at the top level of the file and PHP 8 raises a warning at each of the 3 use sites.
The warnings are logged on every page that loads the preview popup.

### Fix (two possible ways)

**A. Upload the fixed file** (recommended when the deployed copy is identical to
the repository one):

```
htdocs/custom/previewdocuments/js/previewdocuments.js.php
```

The fix does two things:

1. every use site becomes null-safe —
   `DOL_MAIN_URL_ROOT.(isset($customtxt) ? $customtxt : '')`;
2. an initialisation is guaranteed at the top of the file —
   `if (!isset($customtxt)) { $customtxt = ''; }`.

**B. Run the idempotent hot-fix script** (recommended when the copy deployed on the
server is *not* identical to the repository one — the line numbers then differ and
a diff cannot be applied reliably):

```
php /path/to/erp/custom/previewdocuments/test/apply_customtxt_hotfix.php
```

The script:

- works on the file **content**, not on line numbers;
- is **idempotent** (running it twice reports "Nothing to do");
- keeps a timestamped backup (`previewdocuments.js.php.bak-YYYYmmdd-HHMMSS`);
- checks the PHP syntax of the result and restores the backup if it is broken.

Usage: `php apply_customtxt_hotfix.php [absolute/path/to/previewdocuments.js.php]`
(the path defaults to `custom/previewdocuments/js/previewdocuments.js.php` next to
the script).

### Verification

Reload a page that opens the preview popup and check `error_log`: the
`Undefined variable $customtxt` warnings must be gone.
