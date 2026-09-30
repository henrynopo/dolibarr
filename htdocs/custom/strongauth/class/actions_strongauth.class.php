<?php
/* Copyright (C) 2026 HaoSG Group
 *
 * Hook implementations for StrongAuth.
 *
 * Hook responsibilities:
 *   - mainloginpage : render extra HTML on the login page (2FA prompt if needed,
 *                     SSO provider buttons, "trust this device" prompt, Passkey)
 *   - login         : afterLogin — verify second factor, bounce the user back
 *                     to the login page when a factor is missing
 *   - usercard      : render the user's auth-method management block
 *   - userlist      : show 2FA status badge in user lists
 *
 * SSO cookie pickup is NOT handled here: it goes through Dolibarr's native
 * authentication pipeline via core/login/functions_strongauth_sso.php
 * (module_parts['login']). This hook only enforces the second-factor policy
 * after Dolibarr has authenticated the primary identity.
 */

require_once __DIR__.'/../lib/strongauth.lib.php';

class ActionsStrongauth
{
    /** @var DoliDB */
    public $db;
    public $error    = '';
    public $errors   = array();
    public $results  = array();
    public $resprints = '';

    /** Max age (seconds) of a strongauth_2fa_passed marker set during enrollment. */
    const ENROLL_PASS_TTL = 600;

    public function __construct(DoliDB $db)
    {
        global $langs;
        $langs->load('strongauth@strongauth');
        $this->db = $db;
    }

    // =====================================================================
    // HOOK: mainloginpage
    // Renders:
    //   - the 6-digit TOTP input (when $_SESSION['strongauth_pending_2fa_userid'] is set)
    //   - a Passkey button that drives the WebAuthn ceremony and submits the form
    //   - a "Trust this device" checkbox (when the pending path allows it)
    //   - SSO provider buttons (first page-load only)
    // =====================================================================

    public function getLoginPageOptions($parameters, &$object, &$action, $hookmanager)
    {
        global $langs;

        $pendingPath = $_SESSION['strongauth_pending_path'] ?? array();

        // ============== Error banner (2FA failure OR policy rejection) ==============
        // Rendered outside the pending block on purpose: a policy-rejected
        // login (e.g. internal user on password auth) sets the error without
        // a pending 2FA state and must still explain itself.
        if (!empty($_SESSION['strongauth_2fa_error'])) {
            $this->resprints .= '
<div class="trinputlogin">
  <div class="tagtd nowraponall center valignmiddle tdinputlogin opacitymedium">
    <span class="fa fa-warning"></span>
    '.dol_escape_htmltag($langs->trans($_SESSION['strongauth_2fa_error'])).'
  </div>
</div>';
            unset($_SESSION['strongauth_2fa_error']);
        }

        // ============== 2FA pending: code input + passkey + trust checkbox ==============
        if (!empty($_SESSION['strongauth_pending_2fa_userid'])) {
            $factors = $pendingPath['available_factors'] ?? array();

            // The code box renders only when the pending path offers TOTP —
            // a passkey-only user has no authenticator secret, and an empty
            // code box reads as a 2FA demand they never enrolled. The same
            // box accepts a backup code (recovery path when the authenticator
            // is lost; Factor_BackupCode::verify consumes it on match).
            if (in_array('totp', $factors, true)) {
                $this->resprints .= '
<div class="trinputlogin">
  <div class="tagtd nowraponall center valignmiddle tdinputlogin">
    <span class="fa fa-clock"></span>
    <input type="text" id="securitycode" maxlength="16"
           placeholder="'.dol_escape_htmltag($langs->trans('StrongAuth6DigitPlaceholder')).'"
           name="strongauth_totp" autocomplete="one-time-code" autofocus
           class="flat input-icon-user minwidth150" />
  </div>
</div>';
            } elseif (in_array('webauthn', $factors, true)) {
                $this->resprints .= '
<div class="trinputlogin">
  <div class="tagtd center valignmiddle tdinputlogin">'
    .dol_escape_htmltag($langs->trans('StrongAuthUsePasskeyToContinue')).'
  </div>
</div>';
            }

            // Passkey (WebAuthn) second factor — only when the pending path offers it.
            if (in_array('webauthn', $factors, true)) {
                $this->resprints .= '
<div class="trinputlogin">
  <div class="tagtd center valignmiddle tdinputlogin">
    <input type="hidden" name="strongauth_webauthn" id="strongauth_webauthn_input" value="" />
    <button type="button" class="button" id="strongauth_passkey_btn">'
        .dol_escape_htmltag($langs->trans('StrongAuthFactorPasskey')).'</button>
  </div>
</div>
<script>
(function () {
    function b64urlToBytes(s) {
        s = s.replace(/-/g, "+").replace(/_/g, "/");
        while (s.length % 4) s += "=";
        var bin = atob(s), out = new Uint8Array(bin.length);
        for (var i = 0; i < bin.length; i++) out[i] = bin.charCodeAt(i);
        return out;
    }
    function bytesToB64url(bytes) {
        var bin = "";
        for (var i = 0; i < bytes.length; i++) bin += String.fromCharCode(bytes[i]);
        return btoa(bin).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/, "");
    }
    document.getElementById("strongauth_passkey_btn").addEventListener("click", async function () {
        var btn = this, form = btn.closest("form");
        btn.disabled = true;
        try {
            var r = await fetch("'.dol_escape_js(STRONGAUTH_MODULE_URL_ROOT.'/views/json.php').'", {
                method: "POST",
                credentials: "same-origin",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ action: "assert_begin", factor: "webauthn" })
            });
            var j = await r.json();
            if (!j.ok) throw new Error(j.error || "assert_begin failed");
            var opts = j.options;
            opts.challenge = b64urlToBytes(opts.challenge);
            if (opts.allowCredentials) {
                for (var i = 0; i < opts.allowCredentials.length; i++) {
                    opts.allowCredentials[i].id = b64urlToBytes(opts.allowCredentials[i].id);
                }
            }
            var cred = await navigator.credentials.get({ publicKey: opts });
            if (!cred) throw new Error("cancelled");
            document.getElementById("strongauth_webauthn_input").value = JSON.stringify({
                id: cred.id,
                rawId: bytesToB64url(new Uint8Array(cred.rawId)),
                type: cred.type,
                clientExtensionResults: cred.getClientExtensionResults ? cred.getClientExtensionResults() : {},
                response: {
                    clientDataJSON: bytesToB64url(new Uint8Array(cred.response.clientDataJSON)),
                    authenticatorData: bytesToB64url(new Uint8Array(cred.response.authenticatorData)),
                    signature: bytesToB64url(new Uint8Array(cred.response.signature)),
                    userHandle: cred.response.userHandle ? bytesToB64url(new Uint8Array(cred.response.userHandle)) : null
                }
            });
            form.submit();
        } catch (e) {
            btn.disabled = false;
        }
    });
})();
</script>';
            }

            // Device-trust opt-in checkbox — only when the path honors it.
            if (!empty($pendingPath['allow_device_trust'])) {
                $this->resprints .= '
<div class="trinputlogin">
  <div class="tagtd center valignmiddle tdinputlogin">
    <label><input type="checkbox" name="strongauth_trust_device" value="1"> '
        .dol_escape_htmltag($langs->trans('StrongAuthTrustDevice30d')).'</label>
  </div>
</div>';
            }

            // Prefill the username so the user only re-enters password + code.
            if (!empty($_SESSION['username'])) {
                $this->results['username'] = $_SESSION['username'];
            }
            return 0;
        }

        // ============== SSO provider buttons ==============
        // Only shown on the FIRST page-load (i.e. user has not yet submitted a
        // password). After a password submission the page is in 2FA-pending
        // mode and SSO buttons would be confusing — the user has already
        // committed to the local path.
        // ============== SSO-first mode ==============
        // With any IdP enabled, the login page leads with the SSO buttons and
        // collapses the username/password form behind a toggle — the internal
        // workforce is the majority. External / first-time users click the
        // link to reveal the classic form (password → forced TOTP wizard).
        // The form stays visible whenever we are NOT in first-load state:
        // a pending 2FA code, a submitted username, or no IdP at all.
        if (empty($_POST['username'])) {
            $providers = StrongAuth_ProviderRegistry::loginPageChoices();
            $buttons = '';
            foreach ($providers as $id => $provider) {
                if ($id === 'local') { continue; }
                $url = STRONGAUTH_MODULE_URL_ROOT.'/views/sso/login.php?provider='.urlencode($id);
                $icon = '';
                if (method_exists($provider, 'iconClass') && $provider->iconClass() !== '') {
                    $icon = '<i class="'.dol_escape_htmltag($provider->iconClass()).'" style="margin-right:10px;"></i>';
                }
                $buttons .= '<a class="button strongauth_sso_btn" href="'.dol_escape_htmltag($url).'">'
                    .$icon.dol_escape_htmltag($provider->label()).'</a>';
            }

            if ($buttons !== '') {
                $ssoFirst = empty($_SESSION['strongauth_pending_2fa_userid']);
                if ($ssoFirst) {
                    $this->resprints .= '
<style>
.strongauth_hidden { display: none !important; }
.strongauth_sso_panel { margin: 1em 0; }
.strongauth_sso_panel .strongauth_sso_btn {
    display:block; width:100%; box-sizing:border-box; padding:10px 14px; margin:6px 0;
    font-size:1.05em; text-align:left; text-decoration:none;
}
.strongauth_toggle_pw { display:block; margin-top:10px; font-size:.9em; }
</style>
<div class="strongauth_sso_panel">'.$buttons.'</div>
<a href="#" class="strongauth_toggle_pw" id="strongauth_toggle_pw">'.$langs->trans('StrongAuthUsePasswordInstead').'</a>
<script>
document.addEventListener("DOMContentLoaded", function () {
    var hide = [];
    ["username", "password", "securitycode", "tdpasswordlogin", "login-submit-wrapper"].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) { hide.push(el.closest(".trinputlogin") || el); }
    });
    hide.forEach(function (el) { el.classList.add("strongauth_hidden"); });
    var t = document.getElementById("strongauth_toggle_pw");
    if (!t) return;
    t.addEventListener("click", function (e) {
        e.preventDefault();
        hide.forEach(function (el) { el.classList.remove("strongauth_hidden"); });
        var p = document.querySelector(".strongauth_sso_panel");
        if (p) { p.style.display = "none"; }
        t.style.display = "none";
        var u = document.getElementById("username");
        if (u) { u.focus(); }
    });
});
</script>';
                } else {
                    // Pending 2FA state — keep the form visible, just append buttons.
                    $this->resprints .= '<div class="strongauth_sso_panel">'.$buttons.'</div>';
                }
            }
        }
        return 0;
    }

    // =====================================================================
    // HOOK: login (afterLogin)
    //
    // Runs inside Dolibarr's fresh-login block, AFTER $_SESSION['dol_login']
    // has been set and inside an open DB transaction. When a factor is
    // missing or wrong we MUST (in this order):
    //   1. $db->rollback()  — abort Dolibarr's login transaction
    //   2. session_reset()  — discard the in-memory session writes of this
    //                         request (incl. dol_login), reloading the state
    //                         from storage. Without this the next request
    //                         would resume as fully authenticated and skip
    //                         the second factor entirely.
    //   3. set our pending markers (they survive because they are written
    //                         AFTER the reset)
    //   4. redirect to the login page
    // This is the same mechanism the totp2fa module uses on this Dolibarr
    // version (SLY22.0.4).
    // =====================================================================

    public function afterLogin($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user, $langs, $db;

        if (!is_object($object) || empty($object->id)) {
            return 0;
        }
        $userId    = (int) $object->id;
        $username  = GETPOST('username', 'alphanohtml');

        // Recently-enrolled marker (set by the enrollment wizard after the user
        // proved a live code in the same session). Honored for a short window only.
        $passedAt = $_SESSION['strongauth_2fa_passed'][$userId] ?? 0;
        if ($passedAt && (time() - (int) $passedAt) < self::ENROLL_PASS_TTL) {
            return 0;
        }
        unset($_SESSION['strongauth_2fa_passed'][$userId]);

        // ============== Resolve path ==============
        $orchestrator = new StrongAuth_Orchestrator($this->db);
        $path = $orchestrator->resolveAuthPath($object);

        StrongAuth_Audit::log(
            $this->db,
            $userId,
            $username !== '' ? $username : null,
            StrongAuth_Audit::EVENT_LOGIN_OK,
            $path['primary'],
            'path='.$orchestrator->describePath($path).',authmode='.(string) ($parameters['dol_authmode'] ?? '')
        );

        // ============== Internal users must arrive via the IdP ==============
        // Dolibarr pre-fills a random password for every hand-created user, so
        // an internal-domain user CAN pass native password auth. If that
        // happens, the "internal employees use Entra ID (with its own MFA)"
        // policy is being bypassed — reject any password-based authmode and
        // point the user at the SSO buttons. Only the strongauth_sso cookie
        // path (IdP-asserted identity) may carry an internal user through.
        // EXCEPTION (configurable break-glass): with STRONGAUTH_ADMIN_BREAKGLASS
        // on (default), Dolibarr admins may always sign in with the local
        // password — never lock the system's own administrator out.
        $breakGlassOn = !empty($conf->global->STRONGAUTH_ADMIN_BREAKGLASS);
        if ($path['primary'] === 'entra' && !( !empty($object->admin) && $breakGlassOn )) {
            $authmode = (string) ($parameters['dol_authmode'] ?? '');
            if ($authmode !== 'strongauth_sso') {
                StrongAuth_Audit::log($this->db, $userId,
                    $username !== '' ? $username : null,
                    StrongAuth_Audit::EVENT_LOGIN_FAIL_PASSWORD, 'local',
                    'internal-domain user attempted password login (authmode='.$authmode.')');
                $this->rejectLogin($object, 'StrongAuthUseEntraIdInstead');
                return -1;
            }
        }

        // ============== SSO completed — honor IdP-asserted identity ==============
        // Any login authenticated through strongauth_sso carries a one-time
        // cookie whose DB row was issued only after full IdP verification and
        // email matching (handleCallback) — the IdP's MFA already IS the
        // second factor. This is checked by authmode alone, independent of
        // the user's classification, so a passkey/TOTP holder (factor_choice
        // path) or a mis-classified email can never be double-gated into the
        // factor prompt on an SSO login. EXCEPTION: STRONGAUTH_REQUIRE_2FA_IDP=1
        // opts into explicit step-up — keep the factor prompt in that case.
        $authmode = (string) ($parameters['dol_authmode'] ?? '');
        if ($authmode === 'strongauth_sso'
            && empty($conf->global->STRONGAUTH_REQUIRE_2FA_IDP)) {
            $_SESSION['strongauth_2fa_passed'][$userId] = time();
            StrongAuth_Audit::log($this->db, $userId,
                $username !== '' ? $username : null,
                StrongAuth_Audit::EVENT_LOGIN_OK, $path['primary'],
                'sso pass-through (authmode=strongauth_sso, IdP MFA is the second factor)');
            return 0;
        }

        // ============== Pass-through ==============
        if ($path['factor_strategy'] === 'pass_through') {
            $_SESSION['strongauth_2fa_passed'][$userId] = time();
            return 0;
        }

        // ============== Lockout ==============
        if (StrongAuth_Lockout::isLocked($this->db, $userId)) {
            StrongAuth_Audit::log($this->db, $userId,
                $username !== '' ? $username : null,
                StrongAuth_Audit::EVENT_LOGIN_LOCKED, $path['primary']);
            $this->failSecondFactor($object, 'StrongAuth2FALocked');
            return -1;
        }

        // ============== Device-trust bypass ==============
        // If the user has marked this browser as trusted AND their current 2FA
        // path would otherwise need a code, we skip it. SSO paths NEVER honor
        // device trust — the IdP itself is the second factor.
        if (!empty($path['allow_device_trust'])
            && StrongAuth_DeviceTrust::verify($userId)) {
            $_SESSION['strongauth_2fa_passed'][$userId] = time();
            StrongAuth_Audit::log($this->db, $userId, null,
                StrongAuth_Audit::EVENT_LOGIN_OK, 'device_trust', 'trusted');
            return 0;
        }

        // ============== Factor choice ==============
        if ($path['factor_strategy'] === 'factor_choice') {
            if (empty($_POST['strongauth_factor_choice']) && empty($_POST['strongauth_totp'])
                && empty($_POST['strongauth_backupcode']) && empty($_POST['strongauth_webauthn'])) {
                $this->requireFactor($object, $path);
                return -1;
            }
        }

        // ============== First-time enrollment ==============
        if ($path['must_enroll_totp']) {
            $this->abortLoginToEnroll($object);
            return -1;
        }

        // ============== Verify factor ==============
        // Three inputs may be presented:
        //   - strongauth_totp         (6-digit TOTP code)
        //   - strongauth_backupcode   (recovery code)
        //   - strongauth_webauthn     (Passkey assertion JSON — filled by the
        //                              login-page script after navigator.credentials.get)
        if (empty($_POST['strongauth_totp'])
            && empty($_POST['strongauth_backupcode'])
            && empty($_POST['strongauth_webauthn'])) {
            $this->requireFactor($object, $path);
            return -1;
        }

        $verified   = false;
        $factorUsed = null;

        if (!empty($_POST['strongauth_totp'])) {
            $factor = new Factor_TOTP();
            if ($factor->verify($userId, $_POST['strongauth_totp'])) {
                $verified = true;
                $factorUsed = 'totp';
            } elseif ((new Factor_BackupCode())->verify($userId, $_POST['strongauth_totp'])) {
                // The login box doubles as backup-code entry (there is no
                // second input on the login page) — a wrong TOTP may be a
                // valid backup code.
                $verified = true;
                $factorUsed = 'backupcode';
            } else {
                StrongAuth_Audit::log($this->db, $userId, null,
                    StrongAuth_Audit::EVENT_LOGIN_FAIL_2FA, 'totp');
                StrongAuth_Lockout::recordFailure($this->db, $userId);
            }
        } elseif (!empty($_POST['strongauth_backupcode'])) {
            $factor = new Factor_BackupCode();
            if ($factor->verify($userId, $_POST['strongauth_backupcode'])) {
                $verified = true;
                $factorUsed = 'backupcode';
            } else {
                StrongAuth_Audit::log($this->db, $userId, null,
                    StrongAuth_Audit::EVENT_LOGIN_FAIL_2FA, 'backupcode');
                StrongAuth_Lockout::recordFailure($this->db, $userId);
            }
        } elseif (!empty($_POST['strongauth_webauthn'])) {
            $factor = new Factor_WebAuthn();
            // Defense-in-depth: the pending path must actually offer webauthn
            // (external users never do, regardless of what they registered).
            if (!in_array('webauthn', $path['available_factors'], true)) {
                StrongAuth_Audit::log($this->db, $userId, null,
                    StrongAuth_Audit::EVENT_LOGIN_FAIL_2FA, 'webauthn',
                    'passkey presented but not in available_factors');
                StrongAuth_Lockout::recordFailure($this->db, $userId);
                $verified = false;
            } elseif ($factor->verify($userId, $_POST['strongauth_webauthn'])) {
                $verified = true;
                $factorUsed = 'webauthn';
            } else {
                StrongAuth_Audit::log($this->db, $userId, null,
                    StrongAuth_Audit::EVENT_LOGIN_FAIL_2FA, 'webauthn');
                StrongAuth_Lockout::recordFailure($this->db, $userId);
            }
        }

        if (!$verified) {
            if (StrongAuth_Lockout::isLocked($this->db, $userId)) {
                StrongAuth_Audit::log($this->db, $userId, null,
                    StrongAuth_Audit::EVENT_LOGIN_LOCKED, $factorUsed);
                $this->failSecondFactor($object, 'StrongAuth2FALocked');
            } else {
                $this->failSecondFactor($object);
            }
            return -1;
        }

        // ============== Success ==============
        StrongAuth_Lockout::recordSuccess($this->db, $userId);
        $_SESSION['strongauth_2fa_passed'][$userId] = time();
        unset($_SESSION['strongauth_pending_2fa_userid']);
        unset($_SESSION['strongauth_pending_path']);
        unset($_SESSION['strongauth_must_enroll_totp']);
        unset($_SESSION['username']);

        StrongAuth_Audit::log($this->db, $userId, null,
            StrongAuth_Audit::EVENT_FACTOR_USE, $factorUsed);

        $this->maybeIssueDeviceTrust($userId);

        // Warn the user when they used a backup code (encourage regenerating).
        if ($factorUsed === 'backupcode') {
            $remaining = (new Factor_BackupCode())->countUnused($userId);
            if ($remaining <= 2) {
                $_SESSION['strongauth_warn_low_backup_codes'] = $remaining;
            }
        }
        return 0;
    }

    // =====================================================================
    // HOOK: usercard
    // =====================================================================

    public function formObjectOptions($parameters, &$object, &$action, $hookmanager)
    {
        global $user, $langs, $conf;

        if (!is_object($object) || $action !== '' || empty($object->id)) {
            return 0;
        }

        $orchestrator = new StrongAuth_Orchestrator($this->db);
        $hasTotp    = $orchestrator->hasTotp((int) $object->id);
        $hasPasskey = $orchestrator->hasPasskey((int) $object->id);
        $hasIdp     = $orchestrator->hasAnyIdpBinding((int) $object->id);

        // Status text lives in the value column itself; a single low-key
        // "manage" link carries all operations (factors, passkey, bindings
        // are reachable from that page).
        $bits = array();
        $bits[] = $langs->trans('StrongAuthFactorTOTP').': '.($hasTotp ? '✓' : '—');
        if (!empty($conf->global->STRONGAUTH_ALLOW_WEBAUTHN)
            && $orchestrator->isInternalDomain($object->email ?? '')) {
            $bits[] = $langs->trans('StrongAuthFactorPasskey').': '.($hasPasskey ? '✓' : '—');
        }
        $bits[] = 'SSO: '.($hasIdp ? '✓' : '—');

        $canManage = ($user->id == $object->id)
                  || !empty($user->rights->strongauth->reset);

        $value = implode(' &nbsp; ', array_map('dol_escape_htmltag', $bits));
        if ($canManage) {
            $manageUrl = STRONGAUTH_MODULE_URL_ROOT.'/views/factor_manage.php?id='.(int) $object->id;
            $value .= ' &nbsp; <a href="'.dol_escape_htmltag($manageUrl).'">'
                    .dol_escape_htmltag($langs->trans('StrongAuthManage')).'</a>';
        }

        $this->resprints .= '<tr><td>'.$langs->trans('StrongAuthMethodLabel').'</td><td>'
            .$value.'</td></tr>';
        return 0;
    }

    // =====================================================================
    // HOOK: userlist
    // =====================================================================

    public function printFieldListFooter($parameters, &$object, &$action, $hookmanager)
    {
        // Reserved for future column injection. No-op for now.
        return 0;
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    /**
     * Abort the login and bounce the user back to the login page to present
     * the second factor. See afterLogin's docblock for why the order of
     * rollback / session_reset / marker-writes is critical.
     */
    private function requireFactor($user, array $path): void
    {
        $this->db->rollback();
        session_reset();
        $_SESSION['strongauth_pending_2fa_userid'] = (int) $user->id;
        $_SESSION['strongauth_pending_path']       = $path;
        $_SESSION['username']                      = GETPOST('username', 'alphanohtml');
        $_SESSION['dol_loginmesg']                 = '';
        header('Location: '.DOL_URL_ROOT.'/index.php');
        exit;
    }

    /** Same abort, used when a presented factor was wrong or the account is locked. */
    private function failSecondFactor($user, string $errorKey = 'StrongAuth2FAFailed'): void
    {
        $this->db->rollback();
        session_reset();
        $_SESSION['strongauth_pending_2fa_userid'] = (int) $user->id;
        $_SESSION['strongauth_2fa_error']          = $errorKey;
        $_SESSION['username']                      = GETPOST('username', 'alphanohtml');
        header('Location: '.DOL_URL_ROOT.'/index.php');
        exit;
    }

    /**
     * Reject the login WITHOUT entering the 2FA-pending state. Used for
     * policy refusals (e.g. internal-domain user on a password login): the
     * login page shows the error message and the SSO buttons, but NOT the
     * 6-digit code input.
     */
    private function rejectLogin($user, string $errorKey): void
    {
        $this->db->rollback();
        session_reset();
        $_SESSION['strongauth_2fa_error'] = $errorKey;
        header('Location: '.DOL_URL_ROOT.'/index.php');
        exit;
    }

    /** Abort the login and send the user to the enrollment wizard (pre-login page). */
    private function abortLoginToEnroll($user): void
    {
        $this->db->rollback();
        session_reset();
        $_SESSION['strongauth_must_enroll_totp']   = (int) $user->id;
        $_SESSION['strongauth_pending_2fa_userid'] = (int) $user->id;
        $_SESSION['strongauth_pending_path']       = array(
            'available_factors' => array('totp'),
            'allow_device_trust' => false,
        );
        header('Location: '.STRONGAUTH_MODULE_URL_ROOT.'/views/factor_enroll.php?step=1');
        exit;
    }

    /**
     * If the user checked "Trust this device", issue a cookie and persist
     * the trust row. Only honored when the configured policy allows it.
     */
    private function maybeIssueDeviceTrust(int $userId): void
    {
        global $conf;
        if (empty($conf->global->STRONGAUTH_DEVICE_TRUST_LOCAL)) {
            return;
        }
        if (empty($_POST['strongauth_trust_device'])) {
            return;
        }
        $token = StrongAuth_DeviceTrust::issue($userId);
        header(StrongAuth_DeviceTrust::cookieHeader($userId, $token), false);
    }
}
