<?php
/* Copyright (C) 2026 HaoSG Group
 *
 * StrongAuth bootstrap — single entry point for class loading.
 *
 * Dolibarr does not autoload custom module classes: its root composer knows
 * nothing about htdocs/custom/*, and the module's own composer.json only takes
 * effect after `composer install` inside this directory. Every entry file
 * (hook class, views, admin pages, login function) must require this file
 * FIRST. It:
 *   1. defines the module's DOCUMENT_ROOT / URL_ROOT constants (once)
 *   2. pulls in composer-managed third-party libraries when installed
 *   3. dol_include_once() every module class, interfaces before implementors
 */

if (!defined('STRONGAUTH_MODULE_DOCUMENT_ROOT')) {
    if (file_exists(DOL_DOCUMENT_ROOT.'/custom/strongauth/core/modules/modStrongauth.class.php')) {
        define('STRONGAUTH_MODULE_DOCUMENT_ROOT', DOL_DOCUMENT_ROOT.'/custom/strongauth');
        define('STRONGAUTH_MODULE_URL_ROOT',     DOL_URL_ROOT.'/custom/strongauth');
    } else {
        define('STRONGAUTH_MODULE_DOCUMENT_ROOT', DOL_DOCUMENT_ROOT.'/strongauth');
        define('STRONGAUTH_MODULE_URL_ROOT',     DOL_URL_ROOT.'/strongauth');
    }
}

// Deployment revision marker — bumped on every login-flow change. Shown in
// admin/setup.php and by temporary diagnostics probes; proves which code
// version the web server is actually executing (file-sync / OPcache checks).
if (!defined('STRONGAUTH_CODE_REV')) {
    define('STRONGAUTH_CODE_REV', '2026-09-21-r5');
}

// Third-party dependencies (robthree/twofactorauth, firebase/php-jwt,
// web-token/jwt-library, spomky-labs/webauthn-framework). Deploy with
// `composer install` inside custom/strongauth/ — admin/setup.php warns
// when this file is missing.
if (file_exists(__DIR__.'/../vendor/autoload.php')) {
    require_once __DIR__.'/../vendor/autoload.php';
}

// Interfaces first — PHP resolves `implements` at class-definition time.
require_once __DIR__.'/../class/provider/ProviderInterface.php';
require_once __DIR__.'/../class/factor/FactorInterface.php';

// Core helpers.
require_once __DIR__.'/../class/StrongAuth_Crypto.class.php';
require_once __DIR__.'/../class/StrongAuth_Audit.class.php';
require_once __DIR__.'/../class/StrongAuth_Lockout.class.php';
require_once __DIR__.'/../class/StrongAuth_DeviceTrust.class.php';
require_once __DIR__.'/../class/StrongAuth_Orchestrator.class.php';
require_once __DIR__.'/../class/StrongAuth_OIDCClient.class.php';
require_once __DIR__.'/../class/StrongAuth_WebauthnRepo.class.php';

// Primary-auth providers. Abstract OIDC base MUST load before its subclasses.
// Apple sign-in was deliberately removed: SSO is restricted to corporate
// directories (Entra ID, Google Workspace) whose emails are admin-assigned;
// personal-identity IdPs widen the bypass surface.
require_once __DIR__.'/../class/provider/Provider_LocalPassword.class.php';
require_once __DIR__.'/../class/provider/Provider_OIDCBase.class.php';
require_once __DIR__.'/../class/provider/Provider_EntraID.class.php';
require_once __DIR__.'/../class/provider/Provider_Google.class.php';
require_once __DIR__.'/../class/provider/Provider_GenericOIDC.class.php';
require_once __DIR__.'/../class/StrongAuth_ProviderRegistry.class.php';

// Second factors.
require_once __DIR__.'/../class/factor/Factor_TOTP.class.php';
require_once __DIR__.'/../class/factor/Factor_BackupCode.class.php';
require_once __DIR__.'/../class/factor/Factor_WebAuthn.class.php';
