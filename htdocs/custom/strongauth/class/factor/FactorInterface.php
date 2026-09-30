<?php
/* Copyright (C) 2026 HaoSG Group
 *
 * Common interface for all second-factor implementations.
 *
 * A factor is something the user presents AFTER the primary provider
 * has accepted their password / SSO assertion. Examples: TOTP code,
 * backup code, WebAuthn assertion.
 */

interface FactorInterface
{
    /**
     * Stable identifier used in llx_strongauth_audit.method and in URL parameters.
     */
    public function id(): string;

    /**
     * Human-readable name for UI rendering.
     */
    public function label(): string;

    /**
     * Whether this factor is currently enabled (admin-level switch + per-user prerequisites).
     */
    public function isAvailable(array $authPath): bool;

    /**
     * Verify a user-supplied credential.
     *
     * Implementations MUST:
     *   - be constant-time for code comparisons where feasible
     *   - record usage in last_used_at / consumed_at fields
     *   - never reveal whether the user EXISTS vs the credential is WRONG
     *
     * @param  int    $userId
     * @param  string $credential  Raw user input (TOTP code, backup code, base64url assertion, ...)
     * @param  array  $context     Extra context (e.g. $_POST / IP / user-agent)
     * @return bool
     */
    public function verify(int $userId, string $credential, array $context = array()): bool;
}
