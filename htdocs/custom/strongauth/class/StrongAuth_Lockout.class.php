<?php
/* Copyright (C) 2026 HaoSG Group
 *
 * Per-user account lockout after repeated TOTP failures.
 *
 * Strategy:
 *   - On every failed TOTP attempt, increment failed_count.
 *   - On first failure (count goes 0→1), record first_fail_at.
 *   - When failed_count exceeds STRONGAUTH_LOCKOUT_MAX_FAILED, set locked_until
 *     to now + STRONGAUTH_LOCKOUT_DURATION seconds and clear the counter.
 *   - Successful verification clears failed_count (counter is reset on success).
 *
 * State table: llx_strongauth_lockout
 *
 * The lockout is GLOBAL per user (not per entity): failure counters and the
 * locked_until flag are enforced across all multicompany entities, so an
 * attacker cannot reset the counter by hopping between entity login pages.
 */

class StrongAuth_Lockout
{
    /**
     * Returns true if the user is currently locked out.
     */
    public static function isLocked(DoliDB $db, int $userId): bool
    {

        $until = self::getLockedUntil($db, $userId);
        if ($until === null) {
            return false;
        }
        return strtotime($until) > time();
    }

    /**
     * Record a TOTP verification failure. May trigger a new lockout if the
     * configured threshold is reached. Returns true if the failure caused a
     * lockout (so the caller can log EVENT_LOGIN_LOCKED).
     */
    public static function recordFailure(DoliDB $db, int $userId): bool
    {
        global $conf;

        $max  = (int) ($conf->global->STRONGAUTH_LOCKOUT_MAX_FAILED ?? 5);
        $dur  = (int) ($conf->global->STRONGAUTH_LOCKOUT_DURATION  ?? 900);
        if ($max < 1)   $max = 1;
        if ($dur < 60)  $dur = 60;
        if ($dur > 86400) $dur = 86400;

        $db->begin();
        try {
            $row = self::fetchRow($db, $userId);
            $now = $db->idate(gmdate('Y-m-d H:i:s'));
            $justLocked = false;

            if ($row === null) {
                $db->query("INSERT INTO ".MAIN_DB_PREFIX."strongauth_lockout"
                         . " (fk_user, failed_count, first_fail_at, locked_until, entity)"
                         . " VALUES (".$userId.", 1, '".$now."', NULL, ".(int) $conf->entity.")");
                $newCount = 1;
            } else {
                $newCount = ((int) $row->failed_count) + 1;
                $first    = !empty($row->first_fail_at) ? "'".$db->escape($row->first_fail_at)."'" : "'".$now."'";
                $lockSql  = '';
                if ($newCount >= $max) {
                    $unlockAt = $db->idate(gmdate('Y-m-d H:i:s', time() + $dur));
                    $lockSql  = ", locked_until = '".$unlockAt."'";
                    $justLocked = true;
                    // Reset counter so user gets a fresh quota after the lockout expires.
                    $newCount = 0;
                }
                $db->query("UPDATE ".MAIN_DB_PREFIX."strongauth_lockout SET"
                         . " failed_count = ".$newCount.","
                         . " first_fail_at = ".$first.$lockSql
                         . " WHERE fk_user = ".$userId);
            }
            $db->commit();
            return $justLocked;
        } catch (Throwable $e) {
            $db->rollback();
            dol_syslog('StrongAuth_Lockout::recordFailure failed: '.$e->getMessage(), LOG_ERR);
            return false;
        }
    }

    /**
     * Record a successful verification — clear the counter.
     */
    public static function recordSuccess(DoliDB $db, int $userId): void
    {
        global $conf;
        $db->query("UPDATE ".MAIN_DB_PREFIX."strongauth_lockout SET"
                 . " failed_count = 0,"
                 . " first_fail_at = NULL"
                 . " WHERE fk_user = ".$userId
                 . " AND entity = ".(int) $conf->entity);
    }

    /**
     * Force-clear a lockout (admin override).
     */
    public static function clear(DoliDB $db, int $userId): void
    {
        global $conf;
        $db->query("UPDATE ".MAIN_DB_PREFIX."strongauth_lockout SET"
                 . " failed_count = 0,"
                 . " first_fail_at = NULL,"
                 . " locked_until = NULL"
                 . " WHERE fk_user = ".$userId
                 . " AND entity = ".(int) $conf->entity);
    }

    private static function getLockedUntil(DoliDB $db, int $userId): ?string
    {
        $row = self::fetchRow($db, $userId);
        if ($row === null || empty($row->locked_until)) {
            return null;
        }
        return (string) $row->locked_until;
    }

    private static function fetchRow(DoliDB $db, int $userId): ?\stdClass
    {
        global $conf;
        $res = $db->query("SELECT failed_count, first_fail_at, locked_until"
                         ." FROM ".MAIN_DB_PREFIX."strongauth_lockout"
                         ." WHERE fk_user = ".$userId
                         ." AND entity = ".(int) $conf->entity);
        if (!$res || !$db->num_rows($res)) {
            return null;
        }
        return $db->fetch_object($res);
    }
}
