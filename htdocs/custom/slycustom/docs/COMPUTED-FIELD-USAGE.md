# Dolibarr computed field usage and avoiding PHP warnings

## What is a computed field?

In **Setup → Dictionary → Extra fields**, you can set a field as **Computed**: its value is given by a **formula** (PHP expression) evaluated with `dol_eval()`. The formula can use the current object, e.g. `$object` or `$objectoffield`, and their properties.

## Formula rules (mode 2 – computed)

The formula is evaluated with `dol_eval(..., 1, 1, '2')`. You **must** respect:

1. **No function calls**  
   Do not use `empty()`, `isset()`, `??`, or any function. Only operators, property access, and array access are allowed (plus a whitelist of method calls like `->method(...)`).

2. **Ternary `? :`**  
   The character `?` is allowed only with a space before and after: use ` condition ? value1 : value2 `.

3. **Parentheses**  
   Only certain parentheses are allowed (e.g. after `&&`, `||`, at start, or in `->method(...)`). **Arbitrary grouping like `( ( a - b ) / 86400 )` can be rejected**; avoid extra nested parentheses.

4. **Comparison**  
   `<` and `<=` must have a space before and after.

5. **Allowed characters**  
   Only letters, digits, and a fixed set of symbols (e.g. `^$_+-.*>&|=!?():"\',/@` and in mode 2 also `<[]`).

## Why “Undefined array key” appears (PHP 8.1+)

If your formula uses `$object->array_options['options_atd']` (or similar) and that key is **not set** on the object (e.g. the extrafield was never filled), PHP 8.1+ raises a warning: **Undefined array key "options_xxx"**.

You cannot fix this **inside the formula** without breaking the rules above:

- `empty()` / `isset()` → forbidden (function call).
- `??` → forbidden (triggers the “? must have space” check).
- `@` → can trigger “call of a function or method” style checks in some versions.

So the formula must stay simple, e.g.:

```text
$object->array_options['options_atd'] ? $object->array_options['options_atd']/86400-$object->array_options['options_etd']/86400 : ''
```

## How to avoid the warning

**Recommended:** ensure the keys exist **before** the formula is evaluated, so the expression never hits an undefined key. Note that `dol_eval()` resolves the formula's `$object` to the **page global `$object`** (see `global $object` in `dol_eval()`), not to the object being fetched — so the keys must be pre-set on whatever object the page has as its global `$object`.

1. **SLY Custom (no core patch)**  
   The module `slycustom` ensures the shipment date keys exist on the page's global object before core evaluates the formulas:
   - In **card** (`doActions`): `ensureShipmentDateOptionKeys($object)` when the main object is an expedition/shipment, and also when it is a **facture** — creating an invoice from a shipment (compta/facture/card.php) fetches the Expedition, whose `fetch_optionals()` then evaluates the delay formulas against the page global (the invoice), so the invoice needs the keys too.
   - In **list** (`printFieldListValue`): `ensureShipmentDateOptionKeys($object)` on the list page's `$object` — raw rows carry extrafields as plain `options_*` properties, not in `array_options`, so pre-setting keys on the row object would be ineffective.
   - Core files (`core/lib/functions.lib.php`) are **not** modified; the fix is entirely in `custom/slycustom/class/actions_slycustom.class.php` and the list facade trait.

2. **Keep formulas simple**  
   Use the original form (no `empty`, no `??`, no extra parentheses), e.g.:
   - ATD/ETD:  
     `$object->array_options['options_atd'] ? $object->array_options['options_atd']/86400-$object->array_options['options_etd']/86400 : ''`
   - ATA/ETA:  
     `$object->array_options['options_ata'] ? $object->array_options['options_ata']/86400-$object->array_options['options_eta']/86400 : ''`

With slycustom enabled, these formulas no longer trigger “Undefined array key” because the missing keys are initialised to `0` before evaluation.

## Summary

| Goal                         | Action                                                                 |
|-----------------------------|------------------------------------------------------------------------|
| Use computed field correctly| Follow the formula rules above (no functions, `?` with spaces, etc.).  |
| Avoid Undefined array key   | Use slycustom (ensureShipmentDateOptionKeys in list/card hooks); do **not** patch core. |
