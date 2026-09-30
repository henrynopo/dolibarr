<?php
/* Copyright (C) 2026 HaoSG Group
 *
 * Backup codes — one-time recovery codes generated alongside TOTP enrollment.
 *
 * Properties:
 *   - 10 codes per user by default (admin-configurable)
 *   - 8 hex characters per code (~32 bits of entropy, plenty against online guessing given rate limits)
 *   - Stored as bcrypt hashes — even with DB + AES key, attacker cannot recover plaintext codes
 *   - On successful consume: row marked used=0, used_at, used_ip, used_user_agent
 *   - Successful login with backup code triggers a warning event for the user to regenerate
 */

class Factor_BackupCode implements FactorInterface
{
    const CODE_LENGTH = 8;     // hex chars (4 bytes of randomness = 32 bits)

    public function id(): string
    {
        return 'backupcode';
    }

    public function label(): string
    {
        return 'Backup code';
    }

    public function isAvailable(array $authPath): bool
    {
        return in_array('backupcode', $authPath['available_factors'], true);
    }

    /**
     * Generate a fresh set of backup codes for a user. INVALIDATES any previously
     * unused codes (regeneration produces a new set; old ones are deleted).
     *
     * @return string[] Plaintext codes, to be shown once to the user.
     */
    public function generate(int $userId): array
    {
        global $conf, $user;
        $count = (int) ($conf->global->STRONGAUTH_BACKUPCODE_COUNT ?? 10);
        if ($count < 1)   $count = 1;
        if ($count > 50)  $count = 50;

        global $conf, $db;
        $db->begin();
        try {
            // Wipe any existing codes for this user (regeneration invalidates old).
            $db->query("DELETE FROM ".MAIN_DB_PREFIX."strongauth_backupcode"
                     . " WHERE fk_user = ".(int) $userId
                     . " AND entity = ".(int) $conf->entity);

            $plaintextList = array();
            $now = $db->idate(gmdate('Y-m-d H:i:s'));

            for ($i = 0; $i < $count; $i++) {
                $code   = bin2hex(random_bytes(self::CODE_LENGTH / 2));
                $hash   = password_hash($code, PASSWORD_BCRYPT);
                $sql = "INSERT INTO ".MAIN_DB_PREFIX."strongauth_backupcode"
                     . " (fk_user, code_hash, created_at, entity)"
                     . " VALUES ("
                     .(int) $userId.", "
                     ."'".$db->escape($hash)."', "
                     ."'".$now."', "
                     .(int) $conf->entity
                     .")";
                $db->query($sql);
                $plaintextList[] = $code;
            }

            StrongAuth_Audit::log(
                $db,
                $userId,
                null,
                StrongAuth_Audit::EVENT_BACKUPCODE_REGEN,
                'backupcode',
                'count='.$count.', actor='.($user->id ?? 'self')
            );

            $db->commit();
            return $plaintextList;
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * Verify a backup code. Consumes it on success (marks used=1).
     * Returns true only if the code matches an unused row AND the bcrypt verifies.
     *
     * @return bool
     */
    public function verify(int $userId, string $code, array $context = array()): bool
    {
        $code = dol_strtolower(trim($code ?? ''));
        if ($code === '' || strlen($code) !== self::CODE_LENGTH || !ctype_xdigit($code)) {
            return false;
        }

        global $conf, $db;
        $sql = "SELECT rowid, code_hash FROM ".MAIN_DB_PREFIX."strongauth_backupcode"
             . " WHERE fk_user = ".(int) $userId
             . " AND used = 0"
             . " AND entity = ".(int) $conf->entity;
        $res = $db->query($sql);
        if (!$res) {
            return false;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250);

        while ($row = $db->fetch_object($res)) {
            if (password_verify($code, $row->code_hash)) {
                // Consume this row.
                $upd = "UPDATE ".MAIN_DB_PREFIX."strongauth_backupcode SET"
                     . " used = 1,"
                     . " used_at = '".$db->idate(gmdate('Y-m-d H:i:s'))."',"
                     . " used_ip = '".$db->escape($ip)."',"
                     . " used_user_agent = '".$db->escape($ua)."'"
                     . " WHERE rowid = ".(int) $row->rowid;
                $db->query($upd);

                StrongAuth_Audit::log(
                    $db,
                    $userId,
                    null,
                    StrongAuth_Audit::EVENT_BACKUPCODE_USE,
                    'backupcode',
                    'rowid='.(int) $row->rowid
                );
                return true;
            }
        }
        return false;
    }

    /**
     * Returns count of unused codes for a user.
     */
    public function countUnused(int $userId): int
    {
        global $conf, $db;
        $sql = "SELECT COUNT(*) AS c FROM ".MAIN_DB_PREFIX."strongauth_backupcode"
             . " WHERE fk_user = ".(int) $userId
             . " AND used = 0"
             . " AND entity = ".(int) $conf->entity;
        $res = $db->query($sql);
        if (!$res) return 0;
        $row = $db->fetch_object($res);
        return (int) ($row->c ?? 0);
    }
}
