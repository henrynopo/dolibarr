# CLAUDE.md — Abricot

ATM Consulting's **shared library module** for Dolibarr: a collection of base classes and
helpers that other ATM custom modules build on. It ships almost no end-user feature of its
own — its value is what depends on it, so a change here can break many modules at once.

Minimum Dolibarr version: **16.0**.

## Structure — historical layout, not ModuleBuilder

| Path | Role |
|---|---|
| `core/modules/modAbricot.class.php` | Module descriptor (`$this->version` lives here) |
| `includes/class/` | The library itself (see below) |
| `includes/lib/` | Shared functions (`abricot.lib.php`, `admin.lib.php`) — **not** `lib/` |
| `script/` | Standalone CLI scripts: one-shot migrations and repairs |
| `admin/`, `tpl/`, `langs/`, `img/`, `doc/` | Setup page, templates, translations, assets, docs |

Directories are `includes/class` and `includes/lib`, not the ModuleBuilder `class/` and
`lib/`. Tooling that assumes the standard layout reports them as missing.

## Key classes

- `class.seedobject.php` — **SeedObject**, the legacy ATM ORM. Base class of most ATM
  business objects. `init_db_by_vars()` creates tables, extrafields and indexes at module
  activation: a bug here breaks the activation of every dependent module.
- `class.listview.php`, `class.list.tbs.php` — TListview, list rendering (TBS templates).
- `class.form.core.php`, `class.tbl.php`, `class.tools.php` — form, table and misc helpers.

## `script/` — every file is its own entry point

Each script bootstraps Dolibarr on its own. Two rules for any script added or touched here:

1. **At least two attempts** to include `master.inc.php` — the module may be installed in
   the Dolibarr root (`htdocs/abricot/`) or under `htdocs/custom/` (the README states both
   are supported). A single hardcoded path is a Dolistore packaging non-conformity and
   breaks the other placement.
2. Prefer anchoring the attempts on **`__DIR__`** over the CWD-relative form used by the
   older scripts: a relative path breaks as soon as the script is launched from another
   directory.

No `chdir()` is needed after including `master.inc.php`: its relative
`require_once 'filefunc.inc.php'` resolves from the directory of `master.inc.php` itself.

Scripts touch production data. Read what one writes before running it, and check whether it
claims to be idempotent.

## Commands

```sh
php -l <file>                                        # syntax check
phpcbf --standard=<dolibarr>/dev/setup/codesniffer/ruleset.xml <file>
php script/<name>.php                                # run a maintenance script (see above)
```

## Conventions

- Branches, commit messages, version bump and `ChangeLog.md` entry: team rules in
  `~/.config/team-ai/rules/`. This repo's history uses `FIX : <description>` messages and
  short `FIX/<slug>` branches rather than the four-segment shape.
- **Known debt**: the `ChangeLog.md` of `3.10` and `main` have diverged — the same fixes
  carry different patch numbers on each line (`main` runs one ahead). Check both before
  writing an entry or opening a pullup; a ChangeLog merge into `main` will conflict.
- Code, comments and log messages in English.
