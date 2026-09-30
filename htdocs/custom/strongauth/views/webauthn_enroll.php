<?php
/* Copyright (C) 2026 HaoSG Group
 *
 * WebAuthn passkey enrollment view.
 *
 * Flow:
 *   1. The page renders a "Register a passkey" button.
 *   2. On click, JS calls POST /custom/strongauth/views/json.php  action=register_begin
 *      to fetch the PublicKeyCredentialCreationOptions.
 *   3. JS calls navigator.credentials.create() and POSTs the resulting attestation
 *      back to action=register_finish.
 *   4. Server verifies the attestation and stores the credential.
 */

// Locate main.inc.php the standard way (views/ is 3 levels below htdocs root).
$res = 0;
if (!$res && file_exists(__DIR__.'/../../../main.inc.php')) $res = @include(__DIR__.'/../../../main.inc.php');
if (!$res) die('Include of main fails');
require_once __DIR__.'/../lib/strongauth.lib.php';

global $langs, $user, $conf;
$langs->load('strongauth@strongauth');

// Access guard: must be logged in. Passkey is for internal-domain users only.
if (empty($user->id)) {
    llxHeader();
    print '<div class="error">'.$langs->trans('StrongAuthLoginRequired').'</div>';
    llxFooter();
    exit;
}

$orchestrator = new StrongAuth_Orchestrator($db);
$internal     = $orchestrator->isInternalDomain($user->email ?? '');
$allowPass    = !empty($conf->global->STRONGAUTH_ALLOW_WEBAUTHN);
if (!$internal || !$allowPass) {
    llxHeader();
    print '<div class="warning">'.$langs->trans('StrongAuthPasskeyNotAvailable').'</div>';
    llxFooter();
    exit;
}

llxHeader();
$linkback = '<a href="'.DOL_URL_ROOT.'/user/card.php?id='.(int) $user->id.'">'.$langs->trans("StrongAuthBackToUserCard").'</a>';
print load_fiche_titre($langs->trans('StrongAuthEnrollPasskeyTitle'), $linkback, 'user');
print '<div class="strongauth-passkey-enroll">';
print '<p>'.$langs->trans('StrongAuthEnrollPasskeyIntro').'</p>';

print '<button id="strongauth-passkey-enroll-btn" class="button">'
    .dol_escape_htmltag($langs->trans('StrongAuthEnrollPasskeyButton')).'</button>';
print '<div id="strongauth-passkey-status" style="margin-top:1em"></div>';
print '</div>';

// Inline JavaScript drives the ceremony. base64url helpers are standard.
?>
<script>
(function(){
    function b64urlToBytes(s){
        s = s.replace(/-/g, '+').replace(/_/g, '/');
        while (s.length % 4) s += '=';
        const bin = atob(s);
        const out = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i++) out[i] = bin.charCodeAt(i);
        return out;
    }
    function bytesToB64url(bytes){
        let bin = '';
        for (let i = 0; i < bytes.length; i++) bin += String.fromCharCode(bytes[i]);
        return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    function jsonBase64UrlToBytes(obj){
        const json = JSON.stringify(obj);
        return new TextEncoder().encode(json);
    }
    function bytesToJsonBase64Url(bytes){
        const json = new TextDecoder().decode(bytes);
        return JSON.parse(json);
    }

    function setStatus(msg, ok){
        const el = document.getElementById('strongauth-passkey-status');
        if (!el) return;
        el.style.color = ok ? 'green' : 'red';
        el.textContent = msg;
    }

    async function begin(){
        const r = await fetch('<?php echo dol_escape_js(STRONGAUTH_MODULE_URL_ROOT.'/views/json.php'); ?>', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'register_begin', factor: 'webauthn' })
        });
        const j = await r.json();
        if (!j.ok) throw new Error(j.error || 'register_begin failed');
        const opts = j.options;

        // Convert all base64url fields to ArrayBuffer for the WebAuthn API.
        opts.challenge      = b64urlToBytes(opts.challenge);
        opts.user.id        = b64urlToBytes(opts.user.id);
        if (opts.excludeCredentials) {
            for (const c of opts.excludeCredentials) c.id = b64urlToBytes(c.id);
        }
        return opts;
    }

    async function finish(attestation){
        const r = await fetch('<?php echo dol_escape_js(STRONGAUTH_MODULE_URL_ROOT.'/views/json.php'); ?>', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'register_finish',
                factor: 'webauthn',
                attestation: {
                    id: attestation.id,
                    rawId: bytesToB64url(new Uint8Array(attestation.rawId)),
                    type: attestation.type,
                    clientExtensionResults: attestation.getClientExtensionResults ? attestation.getClientExtensionResults() : {},
                    response: {
                        clientDataJSON:    bytesToB64url(new Uint8Array(attestation.response.clientDataJSON)),
                        attestationObject: bytesToB64url(new Uint8Array(attestation.response.attestationObject)),
                        transports:        attestation.response.getTransports ? attestation.response.getTransports() : []
                    }
                }
            })
        });
        const j = await r.json();
        if (!j.ok) throw new Error(j.error || 'register_finish failed');
        return j.credential;
    }

    document.getElementById('strongauth-passkey-enroll-btn').addEventListener('click', async function(){
        try {
            setStatus('<?php echo dol_escape_js($langs->trans('StrongAuthPasskeyStatusPrompt')); ?>', true);
            const opts = await begin();
            const cred = await navigator.credentials.create({ publicKey: opts });
            if (!cred) throw new Error('user cancelled');
            await finish(cred);
            setStatus('<?php echo dol_escape_js($langs->trans('StrongAuthPasskeyStatusEnrolled')); ?>', true);
        } catch (e) {
            setStatus(e.message || 'error', false);
        }
    });
})();
</script>
<?php

llxFooter();
