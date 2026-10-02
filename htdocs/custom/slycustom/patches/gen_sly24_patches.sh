#!/bin/bash
# Regenerate sly24.0-*.patch from 24.0.1..HEAD of the sly24-port worktree.
# Run from repo root (parent of htdocs) with the worktree merged & resolved:
#   git -C <worktree> diff 24.0.1 -- <paths>  is what each line captures.
# Practical usage: run inside the worktree; BASE is the official tag commit.
set -e
BASE=24.0.1
PATCHDIR=htdocs/custom/slycustom/patches

# Paths per patch (each file in exactly one patch). Order matches APPLY-ON-24.
# core (price symbol + dol_eval guard + boxes + tpl + form classes)
git diff "$BASE" -- htdocs/core/lib/ htdocs/core/class/commonobject.class.php htdocs/core/class/commonpeople.class.php htdocs/core/class/discount.class.php htdocs/core/class/html.form.class.php htdocs/core/boxes/ htdocs/core/tpl/ htdocs/core/modules/payment/ > "$PATCHDIR/sly24.0-core.patch" || true

# menu
git diff "$BASE" -- htdocs/core/class/menubase.class.php > "$PATCHDIR/sly24.0-menu-parent-match.patch" || true

# compta multicurrency (paiement list arrayfields dropped: official since v24)
# ajaxpayment.php reverted to official 24.0.1 on 2026-10-02: patch tail block
# referenced undefined $multicurrency_result/$multicurrency_totalRemaining and
# wiped the correct multicurrency breakdown (official LRR branch already covers it)
git diff "$BASE" -- htdocs/compta/facture/card.php htdocs/compta/facture/tpl/ htdocs/compta/facture/class/factureligne.class.php htdocs/compta/index.php htdocs/compta/paiement.php htdocs/compta/paiement/card.php htdocs/compta/paiement/class/paiement.class.php htdocs/compta/bank/various_payment/ > "$PATCHDIR/sly24.0-compta-multicurrency.patch" || true

git diff "$BASE" -- htdocs/commande/ > "$PATCHDIR/sly24.0-commande.patch" || true
git diff "$BASE" -- htdocs/fourn/class/ htdocs/fourn/commande/ htdocs/fourn/facture/card.php htdocs/fourn/facture/paiement.php htdocs/fourn/facture/tpl/ htdocs/fourn/paiement/ > "$PATCHDIR/sly24.0-fourn-linkedobject.patch" || true
git diff "$BASE" -- htdocs/comm/propal/tpl/linkedobjectblock.tpl.php > "$PATCHDIR/sly24.0-comm-propal-linkedobject.patch" || true
git diff "$BASE" -- htdocs/comm/remx.php > "$PATCHDIR/sly24.0-remx.patch" || true
git diff "$BASE" -- htdocs/admin/ > "$PATCHDIR/sly24.0-admin.patch" || true
git diff "$BASE" -- htdocs/api/ > "$PATCHDIR/sly24.0-api.patch" || true
git diff "$BASE" -- htdocs/cron/ htdocs/delivery/ htdocs/don/ htdocs/public/cron/ htdocs/conf/ > "$PATCHDIR/sly24.0-misc-cron-other.patch" || true
git diff "$BASE" -- htdocs/adherents/ htdocs/contact/ htdocs/contrat/ htdocs/loan/ htdocs/salaries/ htdocs/societe/ htdocs/supplier_proposal/ htdocs/expedition/ htdocs/install/ htdocs/theme/ > "$PATCHDIR/sly24.0-other-modules.patch" || true
git diff "$BASE" -- htdocs/compta/bank/treso.php > "$PATCHDIR/sly24.0-bank-treso.patch" || true
git diff "$BASE" -- htdocs/compta/facture/list.php > "$PATCHDIR/sly24.0-invoice-list-source-order-position.patch" || true
git diff "$BASE" -- htdocs/fourn/facture/list.php > "$PATCHDIR/sly24.0-supplier-invoice-list-source-order-position.patch" || true
git diff "$BASE" -- htdocs/compta/facture/class/facture.class.php > "$PATCHDIR/sly24.0-facture-pdf-fallback.patch" || true
git diff "$BASE" -- htdocs/accountancy/class/bookkeeping.class.php > "$PATCHDIR/sly24.0-accountancy-sfrs.patch" || true
git diff "$BASE" -- htdocs/langs/ > "$PATCHDIR/sly24.0-langs.patch" || true

# Trim empty patches (git apply rejects empty) and force LF endings.
# CRLF patches break `git apply` on Linux (trailing CR lands in the
# +++ b/<file> header) — this bit d-test/d-test2 in 2026-09; patches/.gitattributes
# guards checkouts, this guards regeneration.
for f in "$PATCHDIR"/sly24.0-*.patch; do
  [ -s "$f" ] || { echo "Empty or missing: $f"; rm -f "$f"; continue; }
  if grep -q $'\r' "$f"; then
    tr -d '\r' < "$f" > "$f.lftmp" && mv "$f.lftmp" "$f" && echo "LF-normalised: $f"
  fi
done
echo "Done. Check $PATCHDIR/sly24.0-*.patch"
