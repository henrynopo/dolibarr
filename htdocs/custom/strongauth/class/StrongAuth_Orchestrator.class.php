<?php
/* Copyright (C) 2026 HaoSG Group
 *
 * StrongAuth_Orchestrator
 *
 * Decides for a given user:
 *   - which provider handles primary authentication (local / entra / google / ...)
 *   - whether a second factor is required
 *   - which factors the user may use (TOTP / WebAuthn / both / none)
 *   - whether device trust cookies are honored
 *
 * The orchestrator does NOT itself perform authentication. It only resolves
 * the AuthPath; the actual verification lives in the providers and factors.
 */

class StrongAuth_Orchestrator
{
    /** @var DoliDB */
    private $db;

    public function __construct(DoliDB $db)
    {
        $this->db = $db;
    }

    /**
     * Resolve the authentication path for a Dolibarr user.
     *
     * @param  User $user  The user object (already fetched; need not be authenticated yet)
     * @return array{
     *   primary: string,                          // 'local' | 'entra' | 'google' | 'apple' | 'oidc'
     *   factor_strategy: string,                  // 'pass_through' | 'factor_choice' | 'force_totp' | 'force_backup'
     *   available_factors: string[],              // subset of ['totp', 'backupcode', 'webauthn']
     *   force_2fa: bool,
     *   allow_device_trust: bool,
     *   allow_passkey: bool,
     *   has_totp: bool,
     *   has_passkey: bool,
     *   must_enroll_totp: bool,                   // first-time external user: must register TOTP
     *   must_save_backup: bool,                   // first-time external user: must save backup codes
     * }
     */
    public function resolveAuthPath(User $user): array
    {
        global $conf;

        $email    = dol_strtolower($user->email ?? '');
        $internal = $this->isInternalDomain($email);

        $hasTotp    = $this->hasTotp((int) $user->id);
        $hasPasskey = $this->hasPasskey((int) $user->id);
        $allowPass  = !empty($conf->global->STRONGAUTH_ALLOW_WEBAUTHN);
        $hasIdp     = $this->hasAnyIdpBinding((int) $user->id);
        $requireIdp = !empty($conf->global->STRONGAUTH_REQUIRE_2FA_IDP);
        $requireLocal = !empty($conf->global->STRONGAUTH_REQUIRE_2FA_LOCAL);
        $isAdmin    = !empty($user->admin);

        // ============== ADMIN BREAK-GLASS (optional) ==============
        // By default Dolibarr admins are exempt from policy lock-outs (e.g.
        // internal-domain classification while SSO is not yet configured).
        // STRONGAUTH_ADMIN_BREAKGLASS=0 removes the exemption: admins follow
        // the same rules as everyone else. Only turn it off once SSO is fully
        // verified — an IdP outage then locks admins out too (recovery =
        // temporarily clear the internal domains constant in the database).
        $isAdmin = !empty($user->admin)
                   && !empty($conf->global->STRONGAUTH_ADMIN_BREAKGLASS);

        if ($isAdmin) {
            // Break-glass exempts admins from the IdP-only rule only — an
            // enrolled factor is still honored (the admin has the device by
            // definition; without factors it falls back to pass-through so a
            // freshly created admin can never be locked out). IdP-asserted
            // logins never reach this table's factor strategy: afterLogin
            // passes strongauth_sso sessions through directly.
            if ($hasTotp || ($allowPass && $hasPasskey)) {
                $strategy   = $hasTotp ? 'force_totp' : 'factor_choice';
                $available  = array_values(array_filter(array_merge(
                    $hasTotp ? ['totp'] : [],
                    $hasPasskey ? ['webauthn'] : []
                )));
            } else {
                $strategy   = 'pass_through';
                $available  = array();
            }
            return array(
                'primary'             => $internal ? 'entra' : 'local',
                'factor_strategy'     => $strategy,
                'available_factors'   => $available,
                'force_2fa'           => false,
                'allow_device_trust'  => false,
                'allow_passkey'       => $allowPass,
                'has_totp'            => $hasTotp,
                'has_passkey'         => $hasPasskey,
                'must_enroll_totp'    => false,
                'must_save_backup'    => false,
                'is_admin'            => true,
            );
        }

        // ============== INTERNAL USERS (Entra ID domain) ==============
        if ($internal) {
            // SSO already authenticates with the IdP's own MFA — that IS the
            // second factor for internal users. Enrolling a passkey must NOT
            // silently change the login contract to "passkey every time";
            // step-up (a StrongAuth factor ON TOP of the IdP MFA) happens
            // only when STRONGAUTH_REQUIRE_2FA_IDP is explicitly enabled.
            $stepUp     = $requireIdp && ($hasTotp || $hasPasskey);
            $strategy   = $stepUp ? 'factor_choice' : 'pass_through';
            return array(
                'primary'             => 'entra',
                'factor_strategy'     => $strategy,
                'available_factors'   => array_values(array_filter(
                    array_merge($hasTotp ? ['totp'] : [], ($allowPass || $stepUp) && $hasPasskey ? ['webauthn'] : [])
                )),
                'force_2fa'           => $requireIdp,
                'allow_device_trust'  => false,
                'allow_passkey'       => $allowPass,
                'has_totp'            => $hasTotp,
                'has_passkey'         => $hasPasskey,
                'must_enroll_totp'    => false,
                'must_save_backup'    => false,
            );
        }

        // ============== EXTERNAL USERS (local password path) ==============
        // TOTP is the ONLY allowed second factor; backup codes are recovery-only.
        // STRONGAUTH_REQUIRE_2FA_LOCAL governs the enrollment mandate:
        //   - on  and no TOTP  → forced enrollment on next login
        //   - off and no TOTP  → pass-through (2FA optional for this user)
        //   - TOTP enrolled    → always verified (opting out does not weaken
        //                        an already-enrolled factor)
        if (!$hasTotp && !$requireLocal) {
            return array(
                'primary'             => 'local',
                'factor_strategy'     => 'pass_through',
                'available_factors'   => array(),
                'force_2fa'           => false,
                'allow_device_trust'  => false,
                'allow_passkey'       => false,
                'has_totp'            => false,
                'has_passkey'         => false,
                'must_enroll_totp'    => false,
                'must_save_backup'    => false,
            );
        }
        $strategy = $hasTotp ? 'force_totp' : 'enroll_first';
        $available = ['totp'];
        if ($hasTotp) {
            $available[] = 'backupcode';
        }

        return array(
            'primary'             => 'local',
            'factor_strategy'     => $strategy,
            'available_factors'   => $available,
            'force_2fa'           => $requireLocal,
            'allow_device_trust'  => !empty($conf->global->STRONGAUTH_DEVICE_TRUST_LOCAL),
            'allow_passkey'       => false,           // external users never get Passkey
            'has_totp'            => $hasTotp,
            'has_passkey'         => false,
            'must_enroll_totp'    => !$hasTotp,
            'must_save_backup'    => $hasTotp && !$this->hasUnusedBackupCodes((int) $user->id),
        );
    }

    /**
     * Returns true if the email belongs to one of the configured internal domains.
     */
    public function isInternalDomain(string $email): bool
    {
        global $conf;
        if ($email === '') {
            return false;
        }
        // Normalize the address too — Dolibarr may store mixed-case emails and
        // callers pass $user->email verbatim.
        $email = dol_strtolower($email);
        $domains = $this->internalDomains();
        foreach ($domains as $d) {
            $d = trim($d);
            if ($d !== '' && str_ends_with($email, '@'.dol_strtolower($d))) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return string[]
     */
    public function internalDomains(): array
    {
        global $conf;
        $raw = $conf->global->STRONGAUTH_INTERNAL_DOMAINS ?? '';
        if ($raw === '') {
            return array();
        }
        return array_map('trim', explode(',', $raw));
    }

    /**
     * Whether the user has at least one TOTP secret row with enabled=1.
     */
    public function hasTotp(int $userId): bool
    {
        global $conf;
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."strongauth_totp"
             . " WHERE fk_user = ".(int) $userId
             . " AND entity = ".(int) $conf->entity
             . " AND enabled = 1 LIMIT 1";
        $res = $this->db->query($sql);
        return $res && $this->db->num_rows($res) > 0;
    }

    /**
     * Whether the user has at least one passkey credential.
     */
    public function hasPasskey(int $userId): bool
    {
        global $conf;
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."strongauth_webauthn"
             . " WHERE fk_user = ".(int) $userId
             . " AND entity = ".(int) $conf->entity
             . " LIMIT 1";
        $res = $this->db->query($sql);
        return $res && $this->db->num_rows($res) > 0;
    }

    /**
     * Whether the user has at least one unused backup code.
     */
    public function hasUnusedBackupCodes(int $userId): bool
    {
        global $conf;
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."strongauth_backupcode"
             . " WHERE fk_user = ".(int) $userId
             . " AND used = 0"
             . " AND entity = ".(int) $conf->entity
             . " LIMIT 1";
        $res = $this->db->query($sql);
        return $res && $this->db->num_rows($res) > 0;
    }

    /**
     * Whether the user has any IdP binding record (i.e., has logged in via SSO at least once).
     */
    public function hasAnyIdpBinding(int $userId): bool
    {
        global $conf;
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."strongauth_idp_binding"
             . " WHERE fk_user = ".(int) $userId
             . " AND entity = ".(int) $conf->entity
             . " LIMIT 1";
        $res = $this->db->query($sql);
        return $res && $this->db->num_rows($res) > 0;
    }

    /**
     * Build a short, human-readable summary of the resolved path — for the audit log.
     */
    public function describePath(array $path): string
    {
        $factors = implode('+', $path['available_factors']);
        return $path['primary'].'|'.$path['factor_strategy'].'|'.$factors;
    }
}
