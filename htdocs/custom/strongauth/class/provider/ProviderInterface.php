<?php
/* Copyright (C) 2026 HaoSG Group
 *
 * Common interface for primary authentication providers.
 *
 * A provider handles the FIRST factor: establishing "who the user is".
 * It does NOT handle second factors — those are delegates' job.
 *
 * After a provider accepts, the orchestrator decides whether a second factor
 * is required and routes to the appropriate FactorInterface implementation.
 */

interface ProviderInterface
{
    /**
     * Stable identifier used in audit logs and config (e.g. 'local', 'entra', 'google').
     */
    public function id(): string;

    /**
     * Human-readable label for UI rendering.
     */
    public function label(): string;

    /**
     * Whether this provider is configured and active for this Dolibarr instance.
     */
    public function isConfigured(): bool;

    /**
     * Whether this provider should be tried for the given user.
     *
     * For example:
     *   - Provider_LocalPassword: always true
     *   - Provider_EntraID:        true only if user's email is in an internal domain
     */
    public function appliesTo(User $user): bool;

    /**
     * Begin the authentication flow.
     *
     * For local password: validates the submitted password; returns true/false.
     * For SSO providers:  redirects to the IdP; returns a marker (e.g. 'redirect')
     *                     and the controller is responsible for handling the return trip.
     *
     * @return bool|string  true=accepted, false=rejected, 'redirect'=awaiting IdP
     */
    public function authenticate(User $user, array $context): bool|string;

    /**
     * Begin an SSO authorization round-trip. Only meaningful for redirect-
     * based (OIDC) providers; local-password providers throw.
     *
     * @param  User|null $user    Optional user for login_hint
     * @param  array     $context Optional 'login_hint' / 'redirect_after'
     * @return string             URL to redirect the browser to
     */
    public function beginAuthorization(?User $user, array $context = array()): string;

    /**
     * Handle the IdP callback (state/code validation, token exchange, ID-token
     * verification, user resolution, session-token issuance). Only meaningful
     * for redirect-based providers; local-password providers throw.
     *
     * @return array{fk_user:int, subject:string, issuer:string, amr:string, acr:string,
     *               email:string, display_name:string, session_token:string, expires_at:int, claims:array}
     */
    public function handleCallback(array $params): array;
}
