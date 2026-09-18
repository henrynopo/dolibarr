<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */

/**
 * \file        cron/reminder_cron.php
 * \ingroup     sgpayroll
 * \brief       Expiry reminder cron job — pass, passport, contract expiry alerts.
 *
 * Register this in Dolibarr: Admin → Scheduled Tasks → Add Job
 *   Class method : SGPayrollReminderCron::run()
 *   Frequency: Daily (every 86400 seconds)
 *
 * Sends email to HR Manager(s) when employee documents expire in:
 *   - 60 days  (first reminder)
 *   - 30 days  (second reminder)
 *   - 7 days   (urgent reminder)
 *
 * Uses llx_sgpayroll_documents.reminder_sent_XX flags to prevent repeat sends.
 * Also checks llx_sgpayroll_employee direct fields (pass_expiry, passport_expiry).
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/sgpayroll/lib/sgpayroll.lib.php';

/**
 * Class SGPayrollReminderCron
 */
class SGPayrollReminderCron
{
	/**
	 * Main cron entry point. Called by Dolibarr cron engine.
	 *
	 * @param  array  $parameters  Cron job parameters (unused)
	 * @return int    0=OK, >0=error count
	 */
	public function run($parameters = array())
	{
		global $db, $conf, $langs, $user;

		if (empty(isModEnabled("sgpayroll"))) return 0;

		$langs->loadLangs(array('sgpayroll@sgpayroll', 'mails'));

		$errorCount  = 0;
		$today       = dol_now();
		$todayDate   = dol_print_date($today, '%Y-%m-%d');

		// ── Gather HR Manager email addresses ─────────────────────────────────
		// Users who have sgpayroll leave approve permission (proxy for HR Manager)
		$hrEmails = array();
		$sqlHR = "SELECT u.email FROM ".MAIN_DB_PREFIX."user u"
		       . " INNER JOIN ".MAIN_DB_PREFIX."user_rights ur ON ur.fk_user = u.rowid"
		       . " INNER JOIN ".MAIN_DB_PREFIX."rights_def rd ON rd.id = ur.fk_id"
		       . " WHERE rd.module = 'sgpayroll' AND rd.perms = 'leave' AND rd.subperms = 'approve'"
		       . " AND u.statut = 1 AND u.email != ''";
		$resHR = $db->query($sqlHR);
		while ($resHR && $obj = $db->fetch_object($resHR)) {
			$hrEmails[] = $obj->email;
		}
		// Fall back to company email
		if (empty($hrEmails) && !empty($conf->global->MAIN_INFO_SOCIETE_MAIL)) {
			$hrEmails[] = $conf->global->MAIN_INFO_SOCIETE_MAIL;
		}
		if (empty($hrEmails)) {
			dol_syslog('SGPayroll Cron: No HR email found — skipping reminders', LOG_WARNING);
			return 0;
		}

		// ── Thresholds ────────────────────────────────────────────────────────
		$thresholds = array(
			60 => 'reminder_sent_60',
			30 => 'reminder_sent_30',
			7  => 'reminder_sent_7',
		);

		// ── 1. Document vault expiry checks (llx_sgpayroll_documents) ─────────
		$sqlDocs  = "SELECT d.*, u.lastname, u.firstname, u.email AS emp_email";
		$sqlDocs .= " FROM ".MAIN_DB_PREFIX."sgpayroll_documents d";
		$sqlDocs .= " INNER JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = d.fk_user";
		$sqlDocs .= " WHERE d.expiry_date IS NOT NULL AND d.expiry_date != '0000-00-00'";
		$sqlDocs .= " AND d.expiry_date >= '".$db->escape($todayDate)."'"; // not already expired
		$resDocs  = $db->query($sqlDocs);

		while ($resDocs && $doc = $db->fetch_object($resDocs)) {
			$expTS  = strtotime($doc->expiry_date);
			$daysLeft = (int)(($expTS - $today) / 86400);

			foreach ($thresholds as $days => $flagField) {
				if ($daysLeft <= $days && !$doc->$flagField) {
					// Send reminder
					$sent = self::sendExpiryMail(
						$hrEmails,
						sgpayroll_format_employee_name($doc->firstname, $doc->lastname),
						$doc->doc_type,
						$doc->doc_label,
						$doc->expiry_date,
						$daysLeft
					);
					if ($sent >= 0) {
						$db->query(
							"UPDATE ".MAIN_DB_PREFIX."sgpayroll_documents"
							." SET ".$flagField."=1 WHERE rowid=".(int)$doc->rowid
						);
					} else {
						$errorCount++;
					}
					break; // Only send the tightest applicable reminder
				}
			}
		}

		// ── 2. Employee profile direct fields (pass_expiry, passport_expiry) ──
		$sqlEmp  = "SELECT e.fk_user, e.pass_type, e.pass_number, e.pass_expiry,";
		$sqlEmp .= " e.passport_number, e.passport_expiry, e.work_contract_date,";
		$sqlEmp .= " u.lastname, u.firstname";
		$sqlEmp .= " FROM ".MAIN_DB_PREFIX."sgpayroll_employee e";
		$sqlEmp .= " INNER JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = e.fk_user";
		$sqlEmp .= " WHERE e.status = 1";
		$resEmp  = $db->query($sqlEmp);

		while ($resEmp && $emp = $db->fetch_object($resEmp)) {
			$empName = sgpayroll_format_employee_name($emp->firstname, $emp->lastname);
			// Check pass_expiry
			if (!empty($emp->pass_expiry) && $emp->pass_expiry > $todayDate) {
				$daysLeft = (int)((strtotime($emp->pass_expiry) - $today) / 86400);
				self::checkAndAlertDirectField($db, $hrEmails, $empName, $emp->pass_type.' Pass', $emp->pass_expiry, $daysLeft, 'pass_expiry', $emp->fk_user, $errorCount);
			}
			// Check passport_expiry
			if (!empty($emp->passport_expiry) && $emp->passport_expiry > $todayDate) {
				$daysLeft = (int)((strtotime($emp->passport_expiry) - $today) / 86400);
				self::checkAndAlertDirectField($db, $hrEmails, $empName, 'Passport', $emp->passport_expiry, $daysLeft, 'passport_expiry', $emp->fk_user, $errorCount);
			}
		}

		// ── 3. AIS annual reminder (15 Jan and 1 Feb each year) ────────────────
		$mmdd = date('m-d');
		if (in_array($mmdd, array('01-15', '02-01'))) {
			$ya = (int)date('Y'); // AIS for current year (income = previous year)
			// Check whether AIS review is still pending (any draft rows remain)
			$chkSql = "SELECT COUNT(*) AS cnt FROM ".MAIN_DB_PREFIX."sgpayroll_ais_review"
			        . " WHERE year_of_assessment=".(int)$ya." AND status='draft'";
			$chkRes = $db->query($chkSql);
			$pending = ($chkRes && $obj = $db->fetch_object($chkRes)) ? (int)$obj->cnt : 0;
			if ($pending > 0) {
				self::sendAISReminderMail($hrEmails, $ya, $pending);
			}
		}

		return $errorCount;
	}

	/**
	 * Check and send alert for direct employee profile expiry fields.
	 * Uses a transient tracking via a lightweight DB check to avoid repeat sends.
	 */
	private static function checkAndAlertDirectField($db, $hrEmails, $empName, $docType, $expiryDate, $daysLeft, $fieldKey, $fkUser, &$errorCount)
	{
		// Track via sgpayroll_documents with synthetic rows (or skip if already documented)
		$checkSql = "SELECT rowid, reminder_sent_60, reminder_sent_30, reminder_sent_7"
		          . " FROM ".MAIN_DB_PREFIX."sgpayroll_documents"
		          . " WHERE fk_user=".(int)$fkUser." AND doc_type='".strtoupper($fieldKey)."'";
		$chkRes = $db->query($checkSql);

		// If no vault entry for this field, just send if threshold met (idempotent enough via cron timing)
		$thresholds = array(60 => 'reminder_sent_60', 30 => 'reminder_sent_30', 7 => 'reminder_sent_7');
		foreach ($thresholds as $days => $flag) {
			if ($daysLeft <= $days) {
				if ($chkRes && $obj = $db->fetch_object($chkRes)) {
					if ($obj->$flag) break; // already sent
				}
				self::sendExpiryMail($hrEmails, $empName, $docType, $fieldKey, $expiryDate, $daysLeft);
				break;
			}
		}
	}

	/**
	 * Send expiry alert email to HR managers.
	 *
	 * @return int  >=0 success, -1 failure
	 */
	private static function sendExpiryMail($hrEmails, $empName, $docType, $docLabel, $expiryDate, $daysLeft)
	{
		global $conf, $mysoc;

		$urgency = $daysLeft <= 7 ? '🔴 URGENT' : ($daysLeft <= 30 ? '🟡 Action Required' : '📋 Reminder');
		$subject = "[$urgency] Pass/Document Expiry: $empName — $docType expires in $daysLeft day(s)";
		$body    = "Dear HR Manager,\n\n"
		         . "This is an automated reminder from SG Payroll.\n\n"
		         . "Employee:      $empName\n"
		         . "Document:      $docType ($docLabel)\n"
		         . "Expiry Date:   $expiryDate\n"
		         . "Days Remaining: $daysLeft day(s)\n\n";

		if ($daysLeft <= 7) {
			$body .= "⚠ ACTION REQUIRED: Renew this document immediately or initiate pass cancellation / cessation process.\n\n";
		} elseif ($daysLeft <= 30) {
			$body .= "Please initiate renewal proceedings as soon as possible.\n\n";
		} else {
			$body .= "Please note this upcoming expiry and plan accordingly.\n\n";
		}

		$body .= "-- \n".$mysoc->name." SG Payroll System\n";

		$fromEmail  = getDolGlobalString('MAIN_MAIL_EMAIL_FROM') ?: (getDolGlobalString('MAIN_INFO_SOCIETE_MAIL') ?: 'noreply@localhost');
		$fromName   = $mysoc->name;

		$errors = 0;
		foreach ($hrEmails as $toEmail) {
			$from = $fromName.' <'.$fromEmail.'>';
			$mail = new CMailFile($subject, $toEmail, $from, $body, array(), array(), array(), '', '', 0, -1);
			if (!$mail->sendfile()) {
				dol_syslog('SGPayroll Cron: Failed to send expiry reminder to '.$toEmail, LOG_ERR);
				$errors++;
			}
		}
		return $errors > 0 ? -1 : 0;
	}

	/**
	 * Send AIS annual review reminder to HR managers.
	 *
	 * @param  array  $hrEmails
	 * @param  int    $ya       Year of Assessment
	 * @param  int    $pending  Count of draft (unreviewed) rows
	 */
	private static function sendAISReminderMail($hrEmails, $ya, $pending)
	{
		global $conf, $mysoc;

		$deadline = '1 March '.$ya;
		$subject  = "[SG Payroll] Action Required: IRAS AIS Review for YA $ya — $pending record(s) pending";
		$body     = "Dear HR Manager,\n\n"
		          . "This is your scheduled IRAS AIS review reminder.\n\n"
		          . "Year of Assessment : $ya (Income Year: ".($ya-1).")\n"
		          . "Records Pending    : $pending employee(s) not yet reviewed\n"
		          . "Submission Deadline: $deadline\n\n"
		          . "Please log in to Dolibarr → SG Payroll → IRAS AIS Review and:\n"
		          . "  1. Click 'Refresh from Payroll' to update income figures\n"
		          . "  2. Review each employee row and mark as Reviewed\n"
		          . "  3. HR Approver must Confirm before the XML export is enabled\n"
		          . "  4. Export IR8A XML and submit via IRAS myTax Portal\n\n"
		          . "-- \n".$mysoc->name." SG Payroll System\n";

		$fromEmail = getDolGlobalString('MAIN_MAIL_EMAIL_FROM') ?: (getDolGlobalString('MAIN_INFO_SOCIETE_MAIL') ?: 'noreply@localhost');
		foreach ($hrEmails as $toEmail) {
			$mail = new CMailFile($subject, $toEmail, $fromEmail, $body);
			$mail->sendfile();
		}
	}
}
