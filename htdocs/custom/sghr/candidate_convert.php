<?php
/**
 * Copyright (C) 2026 HaoSG Group
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * Candidate -> SG HR employee conversion (sghr phase 4, inspired by
 * recrutement's candidatures/fiche_employe.php). recrutement exposes no
 * standard hook on its candidacy card, so this standalone page drives the
 * flow from the sghr side: pick a candidate, review the prefilled
 * employee form, create the Dolibarr user (or reuse one with the same
 * email) plus the SghrEmployee profile, then land on the employee card.
 */

if (!defined('NOLOGIN')) {
	$res = 0;
	if (!$res && file_exists(__DIR__.'/../../main.inc.php')) $res = @include __DIR__.'/../../main.inc.php';
	if (!$res && file_exists(__DIR__.'/../../../main.inc.php')) $res = @include __DIR__.'/../../../main.inc.php';
	if (!$res) die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
dol_include_once('/sghr/class/employee.class.php');

$langs->loadLangs(array('sghr@sghr', 'users'));

// HR full-edit right required: conversion creates users and employee records
if (!$user->hasRight('sghr', 'employee', 'write')) {
	accessforbidden();
}

$action  = GETPOST('action', 'aZ09');
$fromCand = GETPOSTINT('from_candidate');

$fkTableCand = MAIN_DB_PREFIX.'candidatures';
$fkTableUser = MAIN_DB_PREFIX.'user';

/**
 * Fetch one candidate row (nom/prenom/email/tel). Returns object|null.
 */
function sghrFetchCandidate($db, $id)
{
	$sql = "SELECT rowid, nom, prenom, email, tel FROM ".MAIN_DB_PREFIX."candidatures WHERE rowid = ".((int) $id);
	$resql = $db->query($sql);
	if ($resql && $db->num_rows($resql) > 0) {
		return $db->fetch_object($resql);
	}
	return null;
}

// ── CONVERT ────────────────────────────────────────────────────────────────
if ($action === 'convert' && $fromCand > 0) {
	if (!verifyToken(GETPOST('token', 'aZ09'))) {
		setEventMessages($langs->trans('InvalidToken'), null, 'errors');
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	}

	$cand = sghrFetchCandidate($db, $fromCand);
	if (empty($cand)) {
		setEventMessages($langs->trans('ErrorRecordNotFound'), null, 'errors');
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	}

	$firstname = GETPOST('firstname', 'alphanohtml');
	$lastname  = GETPOST('lastname', 'alphanohtml');
	$email     = GETPOST('email', 'alphanohtml');
	$login     = GETPOST('login', 'alphanohtml');
	if ($email === '') $email = $cand->email;
	if ($login === '' && $email !== '') $login = preg_replace('/[^a-z0-9._-]/i', '', strtok($email, '@'));

	// Reuse an existing user with the same email in this entity when present,
	// otherwise create one (same pattern as employee_card.php action=add).
	$fkUser = 0;
	$sqlU = "SELECT rowid FROM ".MAIN_DB_PREFIX."user WHERE email = '".$db->escape($email)."' AND entity IN (0,".(int) $conf->entity.") LIMIT 1";
	$resqlU = $db->query($sqlU);
	if ($resqlU && ($objU = $db->fetch_object($resqlU))) {
		$fkUser = (int) $objU->rowid;
	}

	$db->begin();
	if ($fkUser <= 0) {
		$newUser = new User($db);
		$newUser->firstname = $firstname;
		$newUser->lastname  = $lastname;
		$newUser->email     = $email;
		$newUser->login     = $login;
		$newUser->pass      = ''; // random password; HR resets before first login
		$newUser->statut    = 1;
		$newUser->entity    = $conf->entity;
		$resUser = $newUser->create($user);
		if ($resUser <= 0) {
			$db->rollback();
			setEventMessages($newUser->error, $newUser->errors, 'errors');
			$action = 'view';
			$fkUser = -1;
		} else {
			$db->commit();
			$fkUser = (int) $resUser;
		}
	}

	if ($fkUser > 0) {
		$emp = new SghrEmployee($db);
		$emp->fetchByUser($fkUser);
		$alreadyExisted = !empty($emp->fk_user);
		if (!$alreadyExisted) {
			$emp->fk_user = $fkUser;
			$emp->pr_start_date = dol_mktime(0, 0, 0, (int) dol_print_date(dol_now(), '%m'), (int) dol_print_date(dol_now(), '%d'), (int) dol_print_date(dol_now(), '%Y'));
			// Provenance note so payroll can trace hires back to recruitment
			$emp->note = trim(($emp->note ? $emp->note."\n" : '').'Converted from recrutement candidate #'.$fromCand.' on '.dol_print_date(dol_now(), 'day'));
		}
		$resSave = $emp->save($user);
		if ($resSave > 0 || $alreadyExisted) {
			setEventMessages($langs->trans('SghrCandidateConverted', $lastname.' '.$firstname), null, 'mesgs');
			header('Location: '.dol_buildpath('/sghr/employee_card.php', 1).'?fk_user='.$fkUser);
			exit;
		}
		setEventMessages($emp->error, null, 'errors');
	}
}

// ── VIEW ───────────────────────────────────────────────────────────────────
llxHeader('', $langs->trans('SghrCandidateConvertTitle'));

print load_fiche_titre($langs->trans('SghrCandidateConvertTitle'), '', 'user');

// recrutement is now part of sghr (absorbed phase 6); no module check needed

// Step 1: candidate picker
if ($fromCand <= 0) {
	$cands = array();
	$sql = "SELECT c.rowid, c.nom, c.prenom, c.email FROM ".MAIN_DB_PREFIX."candidatures c ORDER BY c.rowid DESC LIMIT 500";
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$cands[] = $obj;
		}
	}

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('Candidate').'</td><td>'.$langs->trans('Email').'</td><td class="right">'.$langs->trans('Action').'</td></tr>';
	if (empty($cands)) {
		print '<tr><td colspan="3" class="opacitymedium">'.$langs->trans('NoRecordFound').'</td></tr>';
	}
	foreach ($cands as $c) {
		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag(trim($c->prenom.' '.$c->nom)).'</td>';
		print '<td>'.dol_escape_htmltag($c->email).'</td>';
		print '<td class="right"><a class="button butAction" href="'.$_SERVER["PHP_SELF"].'?from_candidate='.((int) $c->rowid).'">'.$langs->trans('SghrConvertToEmployee').'</a></td>';
		print '</tr>';
	}
	print '</table></form>';
} else {
	// Step 2: prefilled confirmation form
	$cand = sghrFetchCandidate($db, $fromCand);
	if (empty($cand)) {
		print '<div class="error">'.$langs->trans('ErrorRecordNotFound').'</div>';
	} else {
		$defLogin = preg_replace('/[^a-z0-9._-]/i', '', strtok($cand->email, '@'));
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?from_candidate='.((int) $fromCand).'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="convert">';
		print '<input type="hidden" name="from_candidate" value="'.((int) $fromCand).'">';
		print '<table class="border centpercent">';
		print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('SghrCandidateConvertFor', dol_escape_htmltag(trim($cand->prenom.' '.$c->nom))).' (#'.(int) $cand->rowid.')</td></tr>';
		print '<tr><td>'.$langs->trans('Firstname').'</td><td><input name="firstname" value="'.dol_escape_htmltag($cand->prenom).'"></td></tr>';
		print '<tr><td>'.$langs->trans('Lastname').'</td><td><input name="lastname" value="'.dol_escape_htmltag($cand->nom).'"></td></tr>';
		print '<tr><td>'.$langs->trans('Email').'</td><td><input name="email" value="'.dol_escape_htmltag($cand->email).'"></td></tr>';
		print '<tr><td>'.$langs->trans('Login').'</td><td><input name="login" value="'.dol_escape_htmltag($defLogin).'"></td></tr>';
		print '</table>';
		print '<br><div class="center">';
		print '<input type="submit" class="button" value="'.$langs->trans('SghrConvertConfirm').'">';
		print '&nbsp;<a class="button butActionRefused" href="'.dol_buildpath('/sghr/candidate_convert.php', 1).'">'.$langs->trans('Cancel').'</a>';
		print '</div></form>';
	}
}

llxFooter();
$db->close();
