<?php
/* Copyright (C) 2026 HaoSG Group
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 *  Module descriptor for StrongAuth
 *
 *  numera 570015 — change if conflicting
 *  Compatible with Dolibarr 22.0 / 23.0 / 24.0
 */
class modStrongauth extends DolibarrModules
{
    public function __construct($db)
    {
        global $langs, $conf;
        $this->db = $db;

        // ---- identity ----
        $this->numero       = 570015;
        $this->rights_class = 'strongauth';
        $this->family       = 'haopie';
        $this->name         = preg_replace('/^mod/i', '', str_replace('_', ' ', get_class($this)));
        $this->description  = 'StrongAuthDescription';
        $this->editor_name  = 'HaoSG Group';
        $this->editor_url   = 'https://haosg.com';
        $this->version      = '1.0.0';
        $this->long_version = '1.0.0 [ Dolibarr 22.0.x - 24.0.x ]';

        $this->const_name   = 'MAIN_MODULE_'.strtoupper($this->name);
        $this->special      = 0;
        $this->picto        = 'fa-shield-halved@strongauth';

        // ---- module parts ----
        // hooks   : login-flow + UI injection points (ActionsStrongauth)
        // login   : registers core/login/ so checkLoginPassEntity() can call
        //           check_user_password_strongauth_sso() — the SSO-cookie
        //           authentication function. Requires the admin to list
        //           "strongauth_sso" in the authentication method.
        $this->module_parts = array(
            'hooks' => array(
                'mainloginpage',
                'login',
                'usercard',
                'userlist',
            ),
            'login' => 1,
        );

        // ---- config page ----
        $this->config_page_url = array('setup.php@strongauth');

        // ---- dependencies / versions ----
        $this->depends           = array();
        $this->requiredby        = array();
        $this->phpmin            = array(8, 1);
        $this->need_dolibarr_version = array(22, 0);

        // ---- permissions ----
        $this->rights = array();
        $r = 0;
        $this->rights[$r][0] = 5700151;
        $this->rights[$r][1] = 'Reset 2FA for other users';
        $this->rights[$r][2] = 'a';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'reset';
        $this->rights[$r][5] = '';
        $r++;
        $this->rights[$r][0] = 5700152;
        $this->rights[$r][1] = 'View authentication audit log';
        $this->rights[$r][2] = 'r';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'audit';
        $this->rights[$r][5] = '';

        // ---- langs ----
        $this->langfiles = array('strongauth@strongauth');

        // ---- constant defaults ----
        $this->const = array(
            array('STRONGAUTH_REQUIRE_2FA_LOCAL', '1', 'chaine', 0, '', $conf->entity),
            array('STRONGAUTH_REQUIRE_2FA_IDP', '0', 'chaine', 0, '', $conf->entity),
            array('STRONGAUTH_LOCKOUT_MAX_FAILED', '5', 'chaine', 0, '', $conf->entity),
            array('STRONGAUTH_LOCKOUT_DURATION', '900', 'chaine', 0, '', $conf->entity),
            array('STRONGAUTH_ALLOW_WEBAUTHN', '0', 'chaine', 0, '', $conf->entity),
            array('STRONGAUTH_WEBAUTHN_RPID', '', 'chaine', 0, '', $conf->entity),
            array('STRONGAUTH_BACKUPCODE_COUNT', '10', 'chaine', 0, '', $conf->entity),
            array('STRONGAUTH_DEVICE_TRUST_LOCAL', '0', 'chaine', 0, '', $conf->entity),
            array('STRONGAUTH_ADMIN_BREAKGLASS', '1', 'chaine', 0, '', $conf->entity),
        );

        // ---- boxes, cron, menus ----
        $this->boxes    = array();
        $this->cronjobs = array();
        $this->menus    = array();
        $this->tabs     = array();
        $this->dictionaries = array();
    }

    /**
     *  Function called when module is enabled.
     *  Creates tables and the AES-256 encryption key (if absent).
     *
     *  Table creation is self-contained:
     *    1. Standard Dolibarr loader (_load_tables) — works when the custom
     *       document root is declared in conf/conf.php.
     *    2. Deployment-independent fallback — when that declaration is
     *       missing (common in hand-rolled installs) the standard loader
     *       silently finds nothing, so we locate the sql/ directory relative
     *       to this descriptor and execute the files ourselves. Idempotent
     *       thanks to CREATE TABLE IF NOT EXISTS.
     */
    public function init($options = '')
    {
        global $conf, $db;

        $sql = array();

        // ---- 1. Standard loader ----
        $this->_load_tables('/strongauth/sql/');

        // ---- 2. Fallback: ensure the schema exists regardless of deployment ----
        $sqlDir = dirname(__DIR__, 2).'/sql/';   // core/modules → module root
        if (!$this->saTableExists('strongauth_idp_config') && is_dir($sqlDir)) {
            foreach (glob($sqlDir.'llx_*.sql') as $sqlFile) {
                $this->saRunSqlFile($sqlFile);
            }
        }

        // DolibarrModules has no $this->entity — the current entity is in $conf.
        $entity = (int) $conf->entity;

        // ---- 3. Generate AES-256 key if absent ----
        // The master key MUST live at entity=0 so that every entity that enables
        // StrongAuth decrypts TOTP secrets with the same key. multicompany
        // shares `llx_user` rows across entities — without a shared key a TOTP
        // row written from one entity would be unreadable from another.
        $keyExists = $this->db->query("SELECT value FROM ".MAIN_DB_PREFIX."const"
            . " WHERE name = 'STRONGAUTH_TOTP_ENC_KEY' AND entity IN (0, ".$entity.")"
            . " ORDER BY entity ASC LIMIT 1");
        if ($keyExists && $this->db->num_rows($keyExists) == 0) {
            $key = base64_encode(random_bytes(32));
            dol_include_once('/core/lib/admin.lib.php');
            dolibarr_set_const($this->db, 'STRONGAUTH_TOTP_ENC_KEY', $key, '', 0, '', 0);
        }

        // ---- 4. Default issuer name from company info (entity-scoped is fine) ----
        $issuerExists = $this->db->query("SELECT value FROM ".MAIN_DB_PREFIX."const"
            . " WHERE name = 'STRONGAUTH_ISSUER_NAME' AND entity = ".$entity);
        if ($issuerExists && $this->db->num_rows($issuerExists) == 0) {
            $defaultIssuer = !empty($conf->global->MAIN_INFO_SOCIETE_NOM)
                ? $conf->global->MAIN_INFO_SOCIETE_NOM
                : 'Dolibarr';
            dol_include_once('/core/lib/admin.lib.php');
            dolibarr_set_const($this->db, 'STRONGAUTH_ISSUER_NAME', $defaultIssuer, '', 0, '', $entity);
        }

        return $this->_init($sql, $options);
    }

    /** @var string[] Tables the module owns — used by the schema self-check. */
    private static $saTables = array(
        'strongauth_totp', 'strongauth_backupcode', 'strongauth_idp_binding',
        'strongauth_webauthn', 'strongauth_audit', 'strongauth_lockout',
        'strongauth_idp_challenge', 'strongauth_webauthn_challenge',
        'strongauth_idp_config', 'strongauth_device_trust', 'strongauth_sso_session',
    );

    /** Whether a module table exists (prefix-aware). */
    private function saTableExists(string $shortName): bool
    {
        $res = $this->db->query("SHOW TABLES LIKE '".$this->db->escape(MAIN_DB_PREFIX.$shortName)."'");
        return $res && $this->db->num_rows($res) > 0;
    }

    /**
     * Names of missing module tables (prefix-aware) — public so the setup page
     * self-check can reuse it via the descriptor instance.
     * @return string[]
     */
    public function saMissingTables(): array
    {
        $missing = array();
        foreach (self::$saTables as $t) {
            if (!$this->saTableExists($t)) { $missing[] = MAIN_DB_PREFIX.$t; }
        }
        return $missing;
    }

    /**
     * Execute a .sql file: split on ';', apply the table prefix, run each
     * statement. Mirrors what Dolibarr's run_sql does for module files.
     */
    private function saRunSqlFile(string $path): void
    {
        $content = file_get_contents($path);
        if ($content === false) {
            dol_syslog('modStrongauth: cannot read '.$path, LOG_ERR);
            return;
        }
        // strip -- comments, apply prefix
        $lines = array();
        foreach (preg_split('/\R/', $content) as $line) {
            if (preg_match('/^\s*--/', $line)) { continue; }
            $lines[] = $line;
        }
        $body = str_replace('llx_', MAIN_DB_PREFIX, implode("\n", $lines));
        foreach (array_filter(array_map('trim', explode(';', $body))) as $stmt) {
            if ($stmt === '') { continue; }
            if (!$this->db->query($stmt)) {
                dol_syslog('modStrongauth: SQL failed: '.substr($stmt, 0, 120).'... : '.$this->db->lasterror, LOG_ERR);
            }
        }
    }

    public function remove($options = '')
    {
        $sql = array();
        // Tables and constants intentionally kept for forensic value.
        // Admin must manually clean llx_const.STRONGAUTH_TOTP_ENC_KEY and DB tables.
        return $this->_remove($sql, $options);
    }
}
