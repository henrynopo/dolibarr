<?php
/* Copyright (C) 2026 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

/**
 * \file    abricot/script/fix_fk_societe_extrafield_usf.php
 * \brief   CLI one-shot fix: rewrite the legacy raw-SQL filter of the 'fk_societe'
 *          link extrafield (expensereport) to Universal Search Syntax (v23).
 *          The migrate_ndf script created it with 'statut=1', which v23 rejects
 *          ("Bad syntax") -> empty thirdparty list. Idempotent.
 *
 * Usage: php htdocs/custom/abricot/script/fix_fk_societe_extrafield_usf.php
 */

// Load Dolibarr environment.
$res = 0;
// Try master.inc.php when the module is installed into the "custom" directory
$root = realpath(__DIR__.'/../../../');
if (!$res && $root && file_exists($root.'/master.inc.php')) {
	$_SERVER['DOCUMENT_ROOT'] = $root;
	$res = @include $root.'/master.inc.php';
}
// Try master.inc.php when the module is installed into the Dolibarr root directory
$root = realpath(__DIR__.'/../../');
if (!$res && $root && file_exists($root.'/master.inc.php')) {
	$_SERVER['DOCUMENT_ROOT'] = $root;
	$res = @include $root.'/master.inc.php';
}
if (!$res) {
	fwrite(STDERR, "Unable to load Dolibarr environment: master.inc.php not found.\n");
	exit(1);
}

global $db;
if (!is_object($db)) {
	fwrite(STDERR, "variable globale \$db indisponible apres bootstrap\n");
	exit(1);
}

// Societe active column is 'status' (renamed from 'statut' in v16); link query aliases table as 't'.
$correctDesc = 'Societe:societe/class/societe.class.php:0:(t.status:=:1)';

$sql = "SELECT rowid, param FROM ".$db->prefix()."extrafields";
$sql .= " WHERE name = 'fk_societe' AND elementtype = 'expensereport' AND type = 'link'";

$resql = $db->query($sql);
if (!$resql) {
	dol_syslog("abricot fix_fk_societe_extrafield_usf: select failed - ".$db->lasterror(), LOG_ERR);
	fwrite(STDERR, "select failed: ".$db->lasterror()."\n");
	exit(1);
}

$error = 0;
$nbFixed = 0;
$db->begin();
while ($obj = $db->fetch_object($resql)) {
	$param = jsonOrUnserialize($obj->param);
	if (!is_array($param) || empty($param['options']) || !is_array($param['options'])) {
		continue;
	}
	$optionKeys = array_keys($param['options']);
	$desc = (string) $optionKeys[0];

	// Skip rows already using Universal Search Syntax.
	if ($desc === $correctDesc || strpos($desc, '(') !== false) {
		continue;
	}
	// Only rewrite a Societe link carrying a raw statut/status filter.
	if (!preg_match('#^Societe:societe/class/societe\.class\.php:.*(statut|status)\s*=\s*[0-9]#i', $desc)) {
		continue;
	}

	$newParam = array('options' => array($correctDesc => null));
	$sqlUpdate = "UPDATE ".$db->prefix()."extrafields";
	$sqlUpdate .= " SET param = '".$db->escape(serialize($newParam))."'";
	$sqlUpdate .= " WHERE rowid = ".((int) $obj->rowid);

	if ($db->query($sqlUpdate)) {
		$nbFixed++;
		echo "fixed extrafield fk_societe rowid ".((int) $obj->rowid)."\n";
		dol_syslog("abricot fix_fk_societe_extrafield_usf: fixed rowid ".((int) $obj->rowid), LOG_NOTICE);
	} else {
		$error++;
		dol_syslog("abricot fix_fk_societe_extrafield_usf: update failed rowid ".((int) $obj->rowid)." - ".$db->lasterror(), LOG_ERR);
		fwrite(STDERR, "update failed rowid ".((int) $obj->rowid).": ".$db->lasterror()."\n");
		break;
	}
}
$db->free($resql);

if ($error) {
	$db->rollback();
	fwrite(STDERR, $error." erreur(s) - rollback effectue.\n");
	exit(1);
}

$db->commit();
echo $nbFixed." ligne(s) corrigee(s).\n";
exit(0);
