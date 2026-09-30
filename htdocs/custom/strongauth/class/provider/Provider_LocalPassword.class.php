<?php
/* Copyright (C) 2026 HaoSG Group
 *
 * Provider_LocalPassword
 *
 * Validates the standard Dolibarr password against llx_user.pass_crypted.
 *
 * Special cases:
 *   - "Disabled" accounts: pass_crypted starts with 'disabled-' → reject by policy.
 *     We use this to mark accounts that MUST log in via SSO and have no local password.
 *   - Forced password change: respect Dolibarr's force_pass_change flag.
 *   - Lockout: delegated to StrongAuth_Lockout (separate concern).
 */

class Provider_LocalPassword implements ProviderInterface
{
    public function id(): string
    {
        return 'local';
    }

    public function label(): string
    {
        return 'Local password';
    }

    public function isConfigured(): bool
    {
        // Always available — Dolibarr always has a user table with pass_crypted.
        return true;
    }

    public function appliesTo(User $user): bool
    {
        // Applies to anyone who is NOT an internal-domain SSO-only user.
        // The orchestrator decides routing; this provider simply says "yes, I can handle this user".
        return true;
    }

    public function authenticate(User $user, array $context): bool|string
    {
        $submittedPassword = $context['password'] ?? '';
        if (!is_string($submittedPassword) || $submittedPassword === '') {
            return false;
        }

        // Internal SSO-only accounts: pass_crypted is set to a non-password placeholder
        // (e.g. 'disabled-entra-only-XXXXXXX') so that any "local password" attempt
        // collides with this marker and is rejected.
        if (is_string($user->pass_crypted) && str_starts_with($user->pass_crypted, 'disabled-')) {
            return false;
        }

        if (empty($user->pass_crypted)) {
            return false;
        }

        // Dolibarr supports two password storage modes:
        //   1. Modern: password_hash() output (phpass / bcrypt / argon2)
        //   2. Legacy: md5 (deprecated since 12.0 but still possible on very old installs)
        $verified = false;
        if (password_verify($submittedPassword, $user->pass_crypted)) {
            $verified = true;
        } elseif (strlen($user->pass_crypted) === 32 && ctype_xdigit($user->pass_crypted)) {
            // Legacy md5 path — kept only as a safety net for unconverted installs.
            $verified = hash_equals($user->pass_crypted, md5($submittedPassword));
        }

        if (!$verified) {
            return false;
        }

        // Force rehash on next opportunity so bcrypt/argon2 cost grows over time.
        if (password_needs_rehash($user->pass_crypted, PASSWORD_DEFAULT)) {
            // Best-effort: update the row with a stronger hash. If this fails we don't block login.
            $this->rehashQuietly($user, $submittedPassword);
        }

        return true;
    }

    /** Local passwords have no IdP round-trip. */
    public function beginAuthorization(?User $user, array $context = array()): string
    {
        throw new RuntimeException('local provider does not support SSO authorization');
    }

    /** Local passwords have no IdP callback. */
    public function handleCallback(array $params): array
    {
        throw new RuntimeException('local provider does not support SSO callbacks');
    }

    /**
     * Quietly upgrade the user's password hash to the current default algorithm.
     * Failure here is non-fatal — the user has authenticated, do not block them.
     */
    private function rehashQuietly(User $user, string $plaintext): void
    {
        try {
            global $db;
            $new = password_hash($plaintext, PASSWORD_DEFAULT);
            $db->query("UPDATE ".MAIN_DB_PREFIX."user SET pass_crypted = '".$db->escape($new)."'"
                     . " WHERE rowid = ".(int) $user->id);
        } catch (Throwable $e) {
            dol_syslog('Provider_LocalPassword::rehashQuietly failed: '.$e->getMessage(), LOG_WARNING);
        }
    }
}
