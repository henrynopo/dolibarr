<?php
/* Copyright (C) 2026 HaoSG Group
 *
 * RFC 6238 TOTP implementation.
 *
 * Specifications (hard-coded — admin cannot weaken these):
 *   - 6 digits
 *   - 30-second period
 *   - SHA-256 (NIST-recommended; SHA-1 fallback for legacy imported secrets only)
 *   - ±1 step tolerance window (handles clock drift up to ±30s)
 *
 * The TOTP secret is stored AES-256-GCM encrypted under the module's master key.
 * Decryption happens here at verify() time; plaintext never persists.
 *
 * Authenticator App compatibility:
 *   - Google Authenticator, Microsoft Authenticator, Authy, 1Password, Bitwarden, KeePassXC
 *   - The otpauth:// provisioning URI is RFC 6238 standard; any compliant app reads it
 */

class Factor_TOTP implements FactorInterface
{
    const DIGITS     = 6;
    const PERIOD     = 30;
    const ALGORITHM  = 'SHA256';
    const WINDOW     = 1;     // ±1 step = ±30s tolerance

    public function id(): string
    {
        return 'totp';
    }

    public function label(): string
    {
        return 'Authenticator app (TOTP)';
    }

    public function isAvailable(array $authPath): bool
    {
        return in_array('totp', $authPath['available_factors'], true);
    }

    /**
     * Generate a fresh base32 secret for enrollment.
     * RobThree 2.x takes BITS (not characters): 160 bits = 32 base32 chars,
     * the RFC 4226-recommended entropy.
     */
    public function generateSecret(): string
    {
        return $this->tfa()->createSecret(160);
    }

    /**
     * Build the otpauth:// provisioning URI for QR-code scanning.
     *
     * Format example:
     *   otpauth://totp/HaoSG:zhang.san%40haosg.com?secret=ABCD...&issuer=HaoSG&algorithm=SHA256&digits=6&period=30
     *
     * Assembled here because RobThree 2.x no longer exposes a URL builder.
     */
    public function getProvisioningUri(string $secret, string $username): string
    {
        $issuer = $this->issuerName();
        $label  = rawurlencode($issuer.':'.$username);
        $query  = http_build_query(array(
            'secret'     => $secret,
            'issuer'     => $issuer,
            'algorithm'  => self::ALGORITHM,
            'digits'     => self::DIGITS,
            'period'     => self::PERIOD,
        ), '', '&', PHP_QUERY_RFC3986);
        return 'otpauth://totp/'.$label.'?'.$query;
    }

    /**
     * Generate a QR-code image as a base64-encoded PNG data URI, fully
     * offline: Dolibarr ships TCPDF whose 2D-barcode renderer includes a
     * QR encoder (requires ext-gd for PNG output).
     */
    public function getQrCodeDataUri(string $provisioningUri): string
    {
        $tcpdfBarcodes = DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/tcpdf_barcodes_2d.php';
        if (!is_file($tcpdfBarcodes)) {
            throw new RuntimeException('TCPDF barcode library not found — cannot render TOTP QR code');
        }
        require_once $tcpdfBarcodes;
        $barcode = new TCPDF2DBarcode($provisioningUri, 'QRCODE,M');
        return 'data:image/png;base64,'.base64_encode($barcode->getBarcodePngData(4, 4));
    }

    /**
     * Verify a code against a not-yet-persisted secret — used during the
     * first-time enrollment wizard to confirm the user can actually reproduce
     * a code from the QR before we write the secret to DB.
     */
    public function verifyEnrollmentCode(string $secret, string $code): bool
    {
        return (bool) $this->tfa()->verifyCode($secret, $code, self::WINDOW);
    }

    /**
     * Verify a 6-digit TOTP code against the user's enrolled secret.
     *
     * Behavior:
     *   - Pulls the user's row from llx_strongauth_totp
     *   - Decrypts the secret with AES-GCM
     *   - Tries verifyCode() with the configured algorithm (SHA256)
     *   - If that fails AND the row has algorithm='SHA1' (legacy import), retries with SHA1
     *   - On success, bumps last_used_at as a forensic signal
     *
     * @return bool True if accepted, false otherwise
     */
    public function verify(int $userId, string $code, array $context = array()): bool
    {
        $code = preg_replace('/\s+/', '', $code ?? '');
        if ($code === '' || !ctype_digit($code) || strlen($code) !== self::DIGITS) {
            return false;
        }

        $row = $this->fetchSecretRow($userId);
        if ($row === null) {
            return false;   // user has no enrolled TOTP — caller should not have invoked us
        }

        $secret = StrongAuth_Crypto::decrypt($row['secret_enc']);
        if ($secret === null) {
            // Master key rotated, ciphertext corrupted, or row tampered with.
            dol_syslog('Factor_TOTP::verify: failed to decrypt secret for user '.$userId, LOG_ERR);
            return false;
        }

        $algo = strtoupper($row['algorithm'] ?? self::ALGORITHM);
        if (!in_array($algo, ['SHA1', 'SHA256', 'SHA512'], true)) {
            $algo = self::ALGORITHM;
        }

        $tfa = $this->makeTfa($algo);
        $accepted = (bool) $tfa->verifyCode($secret, $code, self::WINDOW);

        // Legacy SHA-1 fallback: only consult if the primary algorithm didn't match.
        if (!$accepted && $algo !== 'SHA1' && $row['algorithm'] === 'SHA1') {
            $tfaSha1 = $this->makeTfa('SHA1');
            $accepted = (bool) $tfaSha1->verifyCode($secret, $code, self::WINDOW);
        }

        if ($accepted) {
            $this->touchLastUsed($userId);
        }
        return $accepted;
    }

    // -----------------------------------------------------------------------
    // Enrollment & management
    // -----------------------------------------------------------------------

    /**
     * Enroll a freshly generated secret for a user.
     */
    public function enroll(int $userId, string $secret): array
    {
        global $conf, $user;       // actor (admin or self); entity context

        $cipher = StrongAuth_Crypto::encrypt($secret);
        $now    = $this->db()->idate(gmdate('Y-m-d H:i:s'));

        // Upsert: replace any existing secret (re-enrollment).
        $existing = $this->fetchSecretRow($userId);
        if ($existing) {
            $sql = "UPDATE ".MAIN_DB_PREFIX."strongauth_totp SET"
                 . " secret_enc = '".$this->db()->escape($cipher)."',"
                 . " algorithm = '".self::ALGORITHM."',"
                 . " digits = ".self::DIGITS.","
                 . " period = ".self::PERIOD.","
                 . " enabled = 1,"
                 . " enrolled_at = '".$now."'"
                 . " WHERE rowid = ".(int) $existing['rowid'];
        } else {
            $sql = "INSERT INTO ".MAIN_DB_PREFIX."strongauth_totp"
                 . " (fk_user, secret_enc, issuer, enrolled_at, algorithm, digits, period, enabled, entity)"
                 . " VALUES ("
                 .(int) $userId.","
                 ."'".$this->db()->escape($cipher)."',"
                 ."'".$this->db()->escape($this->issuerName())."',"
                 ."'".$now."',"
                 ."'".self::ALGORITHM."',"
                 .self::DIGITS.","
                 .self::PERIOD.","
                 ."1,"
                 .(int) $conf->entity
                 .")";
        }
        $this->db()->query($sql);

        StrongAuth_Audit::log(
            $this->db(),
            $userId,
            null,
            StrongAuth_Audit::EVENT_FACTOR_ENROLL,
            'totp',
            'actor='.($user->id ?? 'system')
        );

        return array('secret' => $secret, 'cipher_preview' => substr($cipher, 0, 12).'...');
    }

    /**
     * Disable TOTP for the user (admin or self reset).
     */
    public function disable(int $userId, ?int $actorUserId = null): bool
    {
        global $conf;
        $sql = "UPDATE ".MAIN_DB_PREFIX."strongauth_totp SET enabled = 0"
             . " WHERE fk_user = ".(int) $userId
             . " AND entity = ".(int) $conf->entity;
        $ok = (bool) $this->db()->query($sql);

        StrongAuth_Audit::log(
            $this->db(),
            $userId,
            null,
            StrongAuth_Audit::EVENT_FACTOR_RESET,
            'totp',
            'actor='.($actorUserId ?? 'self')
        );
        return $ok;
    }

    // -----------------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------------

    private function fetchSecretRow(int $userId): ?array
    {
        global $conf;
        $sql = "SELECT rowid, secret_enc, algorithm, enabled FROM ".MAIN_DB_PREFIX."strongauth_totp"
             . " WHERE fk_user = ".(int) $userId
             . " AND entity = ".(int) $conf->entity
             . " AND enabled = 1"
             . " LIMIT 1";
        $res = $this->db()->query($sql);
        if (!$res || $this->db()->num_rows($res) === 0) {
            return null;
        }
        return $this->db()->fetch_array($res);
    }

    private function touchLastUsed(int $userId): void
    {
        global $conf;
        $now = $this->db()->idate(gmdate('Y-m-d H:i:s'));
        $this->db()->query("UPDATE ".MAIN_DB_PREFIX."strongauth_totp SET last_used_at = '".$now."'"
             . " WHERE fk_user = ".(int) $userId
             . " AND entity = ".(int) $conf->entity);
    }

    private function issuerName(): string
    {
        global $conf;
        $name = $conf->global->STRONGAUTH_ISSUER_NAME ?? '';
        return $name !== '' ? $name : 'Dolibarr';
    }

    private static ?\RobThree\Auth\TwoFactorAuth $tfaInstance = null;
    private function tfa(): \RobThree\Auth\TwoFactorAuth
    {
        if (self::$tfaInstance === null) {
            self::$tfaInstance = $this->makeTfa(self::ALGORITHM);
        }
        return self::$tfaInstance;
    }

    /**
     * RobThree 2.x requires the algorithm as its Algorithm enum, not a string.
     */
    private function makeTfa(string $algo): \RobThree\Auth\TwoFactorAuth
    {
        $enum = \RobThree\Auth\Algorithm::tryFrom(strtolower($algo)) ?? \RobThree\Auth\Algorithm::Sha256;
        return new \RobThree\Auth\TwoFactorAuth(
            $this->issuerName(),
            self::DIGITS,
            self::PERIOD,
            $enum
        );
    }

    private function db(): DoliDB
    {
        // The factor is instantiated with no DB — read from globals on demand.
        global $db;
        return $db;
    }
}
