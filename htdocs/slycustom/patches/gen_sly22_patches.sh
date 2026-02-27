#!/bin/bash
# Regenerate sly22.0-*.patch from 22.0.4..HEAD (excluding slycustom).
# Run from repo root (parent of htdocs).
set -e
BASE=22.0.4
PATCHDIR=htdocs/slycustom/patches

# Paths per patch (each file in exactly one patch). Order matches APPLY-ON-22.
# core (except menubase)
git diff "$BASE"..HEAD -- htdocs/core/lib/ htdocs/core/class/commonobject.class.php htdocs/core/class/commonpeople.class.php htdocs/core/class/discount.class.php htdocs/core/class/html.form.class.php htdocs/core/boxes/ htdocs/core/tpl/ htdocs/core/modules/payment/ > "$PATCHDIR/sly22.0-core-price-symbol.patch" || true

# menu
git diff "$BASE"..HEAD -- htdocs/core/class/menubase.class.php > "$PATCHDIR/sly22.0-menu-parent-match.patch" || true

# compta (split)
git diff "$BASE"..HEAD -- htdocs/compta/paiement/list.php > "$PATCHDIR/sly22.0-paiement-arrayfields.patch" || true
git diff "$BASE"..HEAD -- htdocs/comm/remx.php > "$PATCHDIR/sly22.0-remx-hooks.patch" || true
git diff "$BASE"..HEAD -- htdocs/compta/ajaxpayment.php htdocs/compta/facture/card.php htdocs/compta/facture/tpl/linkedobjectblock.tpl.php htdocs/compta/facture/class/factureligne.class.php htdocs/compta/index.php htdocs/compta/paiement.php htdocs/compta/paiement/class/paiement.class.php htdocs/compta/bank/various_payment/ > "$PATCHDIR/sly22.0-compta-multicurrency.patch" || true
git diff "$BASE"..HEAD -- htdocs/commande/ > "$PATCHDIR/sly22.0-commande.patch" || true
git diff "$BASE"..HEAD -- htdocs/fourn/paiement/list.php > "$PATCHDIR/sly22.0-fourn-paiement-arrayfields.patch" || true
git diff "$BASE"..HEAD -- htdocs/fourn/class/ htdocs/fourn/commande/ htdocs/fourn/facture/card.php htdocs/fourn/facture/paiement.php htdocs/fourn/facture/tpl/ > "$PATCHDIR/sly22.0-fourn-linkedobject.patch" || true
git diff "$BASE"..HEAD -- htdocs/comm/propal/tpl/linkedobjectblock.tpl.php > "$PATCHDIR/sly22.0-comm-propal-linkedobject.patch" || true
git diff "$BASE"..HEAD -- htdocs/admin/ > "$PATCHDIR/sly22.0-admin.patch" || true
git diff "$BASE"..HEAD -- htdocs/api/ > "$PATCHDIR/sly22.0-api.patch" || true
git diff "$BASE"..HEAD -- htdocs/cron/ htdocs/delivery/ htdocs/don/ htdocs/public/cron/ htdocs/conf/ > "$PATCHDIR/sly22.0-misc-cron-other.patch" || true
git diff "$BASE"..HEAD -- htdocs/adherents/ htdocs/contact/ htdocs/contrat/ htdocs/loan/ htdocs/salaries/ htdocs/societe/ htdocs/supplier_proposal/ htdocs/expedition/ htdocs/install/ htdocs/theme/ > "$PATCHDIR/sly22.0-other-modules.patch" || true
git diff "$BASE"..HEAD -- htdocs/compta/bank/treso.php > "$PATCHDIR/sly22.0-bank-treso.patch" || true
git diff "$BASE"..HEAD -- htdocs/compta/facture/list.php > "$PATCHDIR/sly22.0-invoice-list-source-order-position.patch" || true
git diff "$BASE"..HEAD -- htdocs/fourn/facture/list.php > "$PATCHDIR/sly22.0-supplier-invoice-list-source-order-position.patch" || true
git diff "$BASE"..HEAD -- htdocs/compta/facture/class/facture.class.php > "$PATCHDIR/sly22.0-facture-pdf-fallback.patch" || true
git diff "$BASE"..HEAD -- htdocs/langs/ > "$PATCHDIR/sly22.0-langs.patch" || true

# Trim empty patches (leave 0-byte or single-line files as-is; git apply rejects empty)
for f in "$PATCHDIR"/sly22.0-*.patch; do
  [ -s "$f" ] || { echo "Empty or missing: $f"; : > "$f"; }
done
echo "Done. Check $PATCHDIR/sly22.0-*.patch"
