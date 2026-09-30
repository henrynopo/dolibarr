<?php
/* Copyright (C) 2026 HaoSG Group
 *
 * PublicKeyCredentialSourceRepository backed by llx_strongauth_webauthn.
 *
 * webauthn-framework 4.x drives the ceremony validators through this
 * interface: lookups during authentication, exclusion list during
 * registration, and persistence right after a successful attestation.
 * The full PublicKeyCredentialSource is stored as JSON (source_json) so the
 * round-trip is lossless; legacy per-field columns are kept for forensics.
 */

use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialSourceRepository;
use Webauthn\PublicKeyCredentialUserEntity;

class StrongAuth_WebauthnRepo implements PublicKeyCredentialSourceRepository
{
    private function db(): DoliDB
    {
        global $db;
        return $db;
    }

    public function findOneByCredentialId(string $publicKeyCredentialId): ?PublicKeyCredentialSource
    {
        $sql = "SELECT source_json FROM ".MAIN_DB_PREFIX."strongauth_webauthn"
             . " WHERE credential_id = '".$this->db()->escape($publicKeyCredentialId)."'"
             . " LIMIT 1";
        $res = $this->db()->query($sql);
        if (!$res || $this->db()->num_rows($res) === 0) {
            return null;
        }
        $row = $this->db()->fetch_object($res);
        try {
            return PublicKeyCredentialSource::createFromArray(json_decode($row->source_json, true));
        } catch (Throwable $e) {
            dol_syslog('StrongAuth_WebauthnRepo: bad source_json for credential: '.$e->getMessage(), LOG_ERR);
            return null;
        }
    }

    public function findAllForUserEntity(PublicKeyCredentialUserEntity $userEntity): array
    {
        $sql = "SELECT source_json FROM ".MAIN_DB_PREFIX."strongauth_webauthn"
             . " WHERE fk_user = ".(int) $userEntity->getId()
             . " AND entity = ".(int) $this->entity();
        $res = $this->db()->query($sql);
        $out = array();
        if ($res) {
            while ($obj = $this->db()->fetch_object($res)) {
                try {
                    $out[] = PublicKeyCredentialSource::createFromArray(json_decode($obj->source_json, true));
                } catch (Throwable $e) {
                    dol_syslog('StrongAuth_WebauthnRepo: skipping bad source_json: '.$e->getMessage(), LOG_WARNING);
                }
            }
        }
        return $out;
    }

    public function saveCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource): void
    {
        global $conf;
        $db = $this->db();
        // spomky returns the credential id as RAW BYTES (non-UTF-8). Everything
        // else in this module speaks base64url — encode before storing, or the
        // column holds binary that no lookup can ever match (and json_encode
        // of the raw bytes silently yields an empty HTTP response).
        $rawId   = $publicKeyCredentialSource->getPublicKeyCredentialId();
        $credId  = preg_match('/^[\x20-\x7e]*$/', $rawId)
            ? $rawId                                  // already ASCII (b64url)
            : Factor_WebAuthn::b64urlEncode($rawId);  // raw bytes → b64url
        $fkUser  = (int) $publicKeyCredentialSource->getUserHandle();        // we store userId as handle
        $json    = json_encode($publicKeyCredentialSource);
        $now     = $db->idate(gmdate('Y-m-d H:i:s'));

        $existing = $db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."strongauth_webauthn"
            . " WHERE credential_id = '".$db->escape($credId)."' LIMIT 1");
        if ($existing && $db->num_rows($existing) > 0) {
            $row = $db->fetch_object($existing);
            $db->query("UPDATE ".MAIN_DB_PREFIX."strongauth_webauthn SET"
                . " fk_user = ".$fkUser.","
                . " source_json = '".$db->escape($json)."',"
                . " sign_count = ".(int) $publicKeyCredentialSource->getCounter().","
                . " last_used_at = '".$now."'"
                . " WHERE rowid = ".(int) $row->rowid);
            return;
        }
        $db->query("INSERT INTO ".MAIN_DB_PREFIX."strongauth_webauthn"
            . " (fk_user, credential_id, source_json, sign_count, name, user_agent, created_at, entity)"
            . " VALUES ("
            . $fkUser.","
            . "'".$db->escape($credId)."',"
            . "'".$db->escape($json)."',"
            . (int) $publicKeyCredentialSource->getCounter().","
            . "'".$db->escape('Passkey '.date('Y-m-d'))."',"
            . "'".$db->escape(substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255))."',"
            . "'".$now."',"
            . (int) $this->entity().")");
    }

    /** Raw rows for the module's own queries (badge, page lists). */
    public function rowsForUser(int $userId): array
    {
        $sql = "SELECT rowid, credential_id, name, created_at, last_used_at FROM ".MAIN_DB_PREFIX."strongauth_webauthn"
             . " WHERE fk_user = ".(int) $userId
             . " AND entity = ".(int) $this->entity();
        $res = $this->db()->query($sql);
        $out = array();
        if ($res) {
            while ($obj = $this->db()->fetch_object($res)) { $out[] = $obj; }
        }
        return $out;
    }

    /** Delete one credential (owner or strongauth->reset; caller enforces). */
    public function deleteCredential(int $userId, int $rowid): bool
    {
        global $conf;
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."strongauth_webauthn"
             . " WHERE rowid = ".(int) $rowid
             . " AND fk_user = ".(int) $userId
             . " AND entity = ".(int) $this->entity();
        $ok = (bool) $this->db()->query($sql);
        if ($ok) {
            StrongAuth_Audit::log($this->db(), $userId, null,
                StrongAuth_Audit::EVENT_FACTOR_RESET, 'webauthn',
                'credential rowid='.$rowid.' deleted');
        }
        return $ok;
    }

    private function entity(): int
    {
        global $conf;
        return (int) $conf->entity;
    }
}
