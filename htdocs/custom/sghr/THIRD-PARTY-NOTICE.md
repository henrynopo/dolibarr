# THIRD-PARTY GPL NOTICE — SG HR & Payroll

This module incorporates, in unmodified-library-plus-patch form, three
third-party Dolibarr modules originally distributed under the
**GNU General Public License v3** (full text preserved in each
sub-directory's `COPYING` file):

| Sub-directory | Original module | Original author / editor | Absorbed in |
|---|---|---|---|
| `docsemployes/` | docsemployes | NextGestion (nextgestion.com) | sghr 2.0.0, phase 2 |
| `ecv/` | ecv | NextGestion (nextgestion.com) | sghr 2.0.0, phase 3 |
| `recrutement/` | recrutement | NextGestion (nextgestion.com) | sghr 2.1.0, phase 6 |

## Modifications applied (GPL §5a notice)

Per GPL v3 section 5(a), the following prominent modifications were made
to the incorporated code when it was merged into this module (detailed
per change in this repository's git history, commits 6a0d75b, 24f19516,
05a586574de / b583395):

- Directory relocation `custom/<module>/` -> `custom/sghr/<module>/` and
  corresponding path rewrites (`'/<module>/'` include paths, translation
  domains `<module>@<module>` -> `<module>@sghr`, asset URLs)
- `main.inc.php` bootstrap probes extended for the deeper directory level
- Permission checks rewritten to the sghr right tree
  (`rights-><module>->lire/creer/supprimer` ->
  `rights->sghr->{docs|cv|rec}->{read|write|delete}`)
- Module descriptor replaced: menus/tabs/hooks/css/js/rights
  declarations re-hosted in `core/modules/modSghr.class.php`
- ecv: PHP 8.1 optional-before-required parameter deprecation fixed in
  all 10 classes (`create($echo_sql=0,$insert)` -> `create($insert,$echo_sql=0)`
  with call sites reversed)
- ecv/grh legacy parse errors repaired (truncated `?>` remnant, missing
  array commas/semicolons, stray bracket) — see commit 9eaaf930

No upstream copyright notices were removed (the original files carried
none in their headers; the `COPYING` licence files are preserved
verbatim in each sub-directory).

The remainder of this module (payroll engine, employee profiles,
candidate conversion, module descriptor) is
Copyright (C) HaoSG Group and is licensed under GPL v3+ as well.
