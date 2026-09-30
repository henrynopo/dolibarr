<?php
/* Copyright (C) 2026 HaoSG Group
 *
 * Factor_WebAuthn — WebAuthn / Passkey second factor
 *
 * Built on web-auth/webauthn-framework 4.x architecture:
 *   PublicKeyCredentialLoader        — parse browser JSON into objects
 *   AuthenticatorAttestationResponseValidator — registration ceremony
 *   AuthenticatorAssertionResponseValidator   — authentication ceremony
 *   StrongAuth_WebauthnRepo          — credential storage (llx_strongauth_webauthn)
 *
 * Platform support (standard W3C WebAuthn, no vendor lock):
 *   Windows Hello (TPM), macOS/iOS Touch ID & Face ID (iCloud Keychain),
 *   Android (Google Password Manager), FIDO2 hardware keys.
 *
 * Restriction: internal-domain users only (orchestrator + json.php + this
 * factor's callers enforce it in three layers).
 *
 * Ceremony: challenges live in llx_strongauth_webauthn_challenge with a 5-min
 * TTL and are consumed on use; the login-page script submits the assertion
 * through the login form so afterLogin verifies it in one place.
 */

use Cose\Algorithms;
use Nyholm\Psr7\ServerRequest;
use Webauthn\AttestationStatement\AttestationObjectLoader;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialLoader;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\TokenBinding\TokenBindingNotSupportedHandler;

class Factor_WebAuthn implements FactorInterface
{
    const CHALLENGE_TTL = 300;       // seconds

    public function id(): string
    {
        return 'webauthn';
    }

    public function label(): string
    {
        return 'Passkey (WebAuthn)';
    }

    public function isAvailable(array $authPath): bool
    {
        return in_array('webauthn', $authPath['available_factors'] ?? array(), true);
    }

    // =================================================================
    // FactorInterface::verify — assertion submitted via the login form
    // =================================================================

    public function verify(int $userId, string $credential, array $context = array()): bool
    {
        $payload = json_decode($credential, true);
        if (!is_array($payload) || empty($payload['id']) || empty($payload['response'])) {
            return false;
        }

        $challenge = $this->consumeChallenge($userId, 'authenticate');
        if ($challenge === null) {
            dol_syslog('Factor_WebAuthn::verify: no active challenge for user '.$userId, LOG_WARNING);
            return false;
        }

        try {
            $loader = $this->loader();
            $publicKeyCredential = $loader->load(json_encode($payload, JSON_UNESCAPED_SLASHES));
            $response = $publicKeyCredential->getResponse();
            if (!$response instanceof AuthenticatorAssertionResponse) {
                return false;
            }

            // Rebuild the request options the browser was prompted with.
            $requestOptions = PublicKeyCredentialRequestOptions::create($challenge)
                ->setRpId($this->relyingPartyId())
                ->setUserVerification('preferred');
            foreach ($this->repo()->findAllForUserEntity($this->userEntity($userId)) as $source) {
                $requestOptions->allowCredential(
                    new PublicKeyCredentialDescriptor(
                        PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                        $source->getPublicKeyCredentialId()
                    )
                );
            }

            $validator = $this->assertionValidator();
            $verified = $validator->check(
                $publicKeyCredential->getRawId(),   // base64url credential id
                $response,
                $requestOptions,
                $this->serverRequest(),
                (string) $userId                    // userHandle binding — credential must belong to this user
            );

            $this->touchLastUsed($publicKeyCredential->getRawId(), $verified->getCounter());
            StrongAuth_Audit::log(
                $this->db(),
                $userId,
                null,
                StrongAuth_Audit::EVENT_FACTOR_USE,
                'webauthn',
                'credential_id='.substr($publicKeyCredential->getRawId(), 0, 12).'...'
            );
            return true;
        } catch (Throwable $e) {
            dol_syslog('Factor_WebAuthn::verify: '.$e->getMessage(), LOG_WARNING);
            return false;
        }
    }

    // =================================================================
    // Registration (called from views/webauthn_enroll.php via json.php)
    // =================================================================

    /**
     * Begin registration: PublicKeyCredentialCreationOptions for
     * navigator.credentials.create(). The JSON encoding of the options object
     * carries all binary fields base64url-encoded as the browser expects.
     */
    public function beginRegistration(int $userId, string $username, string $displayName): array
    {
        $challenge = self::randomChallenge();
        $this->persistChallenge($userId, $challenge, 'register');

        $userEntity = new PublicKeyCredentialUserEntity(
            $username,
            (string) $userId,
            $displayName !== '' ? $displayName : $username
        );
        $rpEntity = new PublicKeyCredentialRpEntity(
            $this->relyingPartyName(),
            $this->relyingPartyId()
        );

        $opts = PublicKeyCredentialCreationOptions::create(
            $rpEntity,
            $userEntity,
            $challenge,
            self::pubKeyParams()
        )
            ->setAuthenticatorSelection(
                AuthenticatorSelectionCriteria::create()->setUserVerification('preferred')
            )
            ->setAttestation(self::ATTESTATION_MODE);

        foreach ($this->repo()->findAllForUserEntity($userEntity) as $source) {
            $opts->excludeCredential(
                new PublicKeyCredentialDescriptor(
                    PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                    $source->getPublicKeyCredentialId()
                )
            );
        }

        return json_decode(json_encode($opts, JSON_UNESCAPED_SLASHES), true);
    }

    /** Attestation mode: "none" for passkeys; device identity is out of scope. */
    private const ATTESTATION_MODE = 'none';

    /** @return PublicKeyCredentialParameters[] */
    private static function pubKeyParams(): array
    {
        return array(
            new PublicKeyCredentialParameters('public-key', Algorithms::COSE_ALGORITHM_ES256),
            new PublicKeyCredentialParameters('public-key', Algorithms::COSE_ALGORITHM_RS256),
        );
    }

    /**
     * Finish registration: verify the attestation, persist via the repo.
     *
     * @return array{credential_id:string}
     */
    public function finishRegistration(int $userId, string $attestationJson): array
    {
        global $conf;
        $challenge = $this->consumeChallenge($userId, 'register');
        if ($challenge === null) {
            throw new RuntimeException('webauthn: no active registration challenge');
        }

        $loader = $this->loader();
        $publicKeyCredential = $loader->load($attestationJson);
        $response = $publicKeyCredential->getResponse();
        if (!$response instanceof AuthenticatorAttestationResponse) {
            throw new RuntimeException('webauthn: response is not an attestation');
        }

        // Rebuild the creation options the browser was prompted with.
        $userEntity = $this->userEntity($userId);
        $creationOptions = PublicKeyCredentialCreationOptions::create(
            new PublicKeyCredentialRpEntity($this->relyingPartyName(), $this->relyingPartyId()),
            $userEntity,
            $challenge,
            self::pubKeyParams()
        )->setAttestation(self::ATTESTATION_MODE);

        $source = $this->attestationValidator()->check(
            $response,
            $creationOptions,
            $this->serverRequest()
        );

        // The validator's source carries the userHandle we bound in the
        // creation options ((string) $userId) — persist as-is.
        $this->repo()->saveCredentialSource($source);

        StrongAuth_Audit::log(
            $this->db(),
            $userId,
            null,
            StrongAuth_Audit::EVENT_FACTOR_ENROLL,
            'webauthn',
            'attestation='.$source->getAttestationType()
        );

        // spomky hands out the credential id as RAW BYTES — encode for every
        // consumer (HTTP response, DB lookups all speak base64url).
        $rawId = $source->getPublicKeyCredentialId();
        $credId = preg_match('/^[\x20-\x7e]*$/', $rawId) ? $rawId : self::b64urlEncode($rawId);
        return array('credential_id' => $credId);
    }

    /**
     * Begin authentication: PublicKeyCredentialRequestOptions for
     * navigator.credentials.get().
     */
    public function beginAssertion(int $userId): array
    {
        $challenge = self::randomChallenge();
        $this->persistChallenge($userId, $challenge, 'authenticate');

        $opts = PublicKeyCredentialRequestOptions::create($challenge)
            ->setRpId($this->relyingPartyId())
            ->setUserVerification('preferred')
            ->setTimeout(60000);
        foreach ($this->repo()->findAllForUserEntity($this->userEntity($userId)) as $source) {
            $opts->allowCredential(
                new PublicKeyCredentialDescriptor(
                    PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                    $source->getPublicKeyCredentialId()
                )
            );
        }

        return json_decode(json_encode($opts, JSON_UNESCAPED_SLASHES), true);
    }

    // =================================================================
    // Internals
    // =================================================================

    private ?StrongAuth_WebauthnRepo $repoInstance = null;
    private function repo(): StrongAuth_WebauthnRepo
    {
        if ($this->repoInstance === null) {
            $this->repoInstance = new StrongAuth_WebauthnRepo();
        }
        return $this->repoInstance;
    }

    private ?PublicKeyCredentialLoader $loaderInstance = null;
    private function loader(): PublicKeyCredentialLoader
    {
        if ($this->loaderInstance === null) {
            $attestationManager = AttestationStatementSupportManager::create();
            $attestationManager->add(new NoneAttestationStatementSupport());
            $this->loaderInstance = PublicKeyCredentialLoader::create(
                AttestationObjectLoader::create($attestationManager)
            );
        }
        return $this->loaderInstance;
    }

    private function attestationValidator(): \Webauthn\AuthenticatorAttestationResponseValidator
    {
        $attestationManager = AttestationStatementSupportManager::create();
        $attestationManager->add(new NoneAttestationStatementSupport());
        return \Webauthn\AuthenticatorAttestationResponseValidator::create(
            $attestationManager,
            $this->repo(),
            new TokenBindingNotSupportedHandler(),
            ExtensionOutputCheckerHandler::create()
        );
    }

    private function assertionValidator(): \Webauthn\AuthenticatorAssertionResponseValidator
    {
        return \Webauthn\AuthenticatorAssertionResponseValidator::create(
            $this->repo(),
            new TokenBindingNotSupportedHandler(),
            ExtensionOutputCheckerHandler::create(),
            null   // algorithm manager: default
        );
    }

    /** PSR-7 request describing THIS http origin — used for origin/rpId checks. */
    private function serverRequest(): ServerRequest
    {
        $origin = $this->currentOrigin();
        return new ServerRequest('POST', $origin, array('Origin' => $origin));
    }

    private function userEntity(int $userId): PublicKeyCredentialUserEntity
    {
        global $db;
        $u = new User($db);
        $login = 'user'.$userId;
        $name = $login;
        if ($u->fetch($userId) > 0) {
            $login = $u->login ?: $login;
            $name = trim(($u->firstname ?? '').' '.($u->lastname ?? '')) ?: $login;
        }
        return new PublicKeyCredentialUserEntity($login, (string) $userId, $name);
    }

    private function relyingPartyId(): string
    {
        global $conf;
        $configured = $conf->global->STRONGAUTH_WEBAUTHN_RPID ?? '';
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $host = preg_replace('/:\d+$/', '', $host);
        return $host !== '' ? $host : 'localhost';
    }

    private function relyingPartyName(): string
    {
        global $conf;
        $name = $conf->global->STRONGAUTH_ISSUER_NAME ?? '';
        return $name !== '' ? $name : 'Dolibarr';
    }

    private function currentOrigin(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme.'://'.$host;
    }

    private function touchLastUsed(string $credIdB64, int $counter): void
    {
        $db = $this->db();
        $db->query("UPDATE ".MAIN_DB_PREFIX."strongauth_webauthn"
            ." SET last_used_at = '".$db->idate(gmdate('Y-m-d H:i:s'))."',"
            ." sign_count = ".(int) $counter
            ." WHERE credential_id = '".$db->escape($credIdB64)."'");
    }

    private function persistChallenge(int $userId, string $challenge, string $purpose): void
    {
        $db = $this->db();
        $cutoff = $db->idate(gmdate('Y-m-d H:i:s', time() - self::CHALLENGE_TTL));
        $db->query(
            "DELETE FROM ".MAIN_DB_PREFIX."strongauth_webauthn_challenge"
            ." WHERE fk_user = ".(int) $userId
            ." AND purpose = '".$db->escape($purpose)."'"
            ." AND created_at < '".$cutoff."'"
        );
        $db->query(
            "INSERT INTO ".MAIN_DB_PREFIX."strongauth_webauthn_challenge"
            ." (fk_user, challenge, purpose, created_at)"
            ." VALUES ("
            .(int) $userId.","
            ."'".$db->escape($challenge)."',"
            ."'".$db->escape($purpose)."',"
            ."'".$db->idate(gmdate('Y-m-d H:i:s'))."')"
        );
    }

    /** @return string|null the base64url challenge */
    private function consumeChallenge(int $userId, string $purpose): ?string
    {
        $db = $this->db();
        $cutoff = $db->idate(gmdate('Y-m-d H:i:s', time() - self::CHALLENGE_TTL));
        $sql = "SELECT rowid, challenge FROM ".MAIN_DB_PREFIX."strongauth_webauthn_challenge"
             . " WHERE fk_user = ".(int) $userId
             . " AND purpose = '".$db->escape($purpose)."'"
             . " AND consumed_at IS NULL"
             . " AND created_at > '".$cutoff."'"
             . " ORDER BY rowid DESC LIMIT 1";
        $res = $db->query($sql);
        if (!$res || $db->num_rows($res) === 0) {
            return null;
        }
        $row = $db->fetch_array($res);
        $db->query(
            "UPDATE ".MAIN_DB_PREFIX."strongauth_webauthn_challenge"
            ." SET consumed_at = '".$db->idate(gmdate('Y-m-d H:i:s'))."'"
            ." WHERE rowid = ".(int) $row['rowid']
        );
        return (string) $row['challenge'];
    }

    private function db(): DoliDB
    {
        global $db;
        return $db;
    }

    public static function randomChallenge(): string
    {
        return self::b64urlEncode(random_bytes(32));
    }

    public static function b64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function b64urlDecode(string $data): string
    {
        $padded = strtr($data, '-_', '+/');
        $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);
        $out = base64_decode($padded, true);
        if ($out === false) {
            throw new RuntimeException('b64url decode failed');
        }
        return $out;
    }
}
