<?php
/* Copyright (C) 2025 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * SLY: Replacement for box_factures_imp with multicurrency support (zero core patch).
 */

require_once DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';

/**
 * SLY: Oldest unpaid customer bills box with multicurrency
 */
class box_sly_oldestunpaidcustomerbills extends ModeleBoxes
{
	public $boxcode = "oldestunpaidcustomerbillssly";
	public $boximg = "object_bill";
	public $boxlabel = "BoxOldestUnpaidCustomerBills";
	public $depends = array("facture");

	public function __construct($db, $param = '')
	{
		global $user;
		$this->db = $db;
		$this->hidden = !($user->hasRight('facture', 'lire'));
		$this->urltoaddentry = DOL_URL_ROOT.'/compta/facture/card.php?action=create';
		$this->msgNoRecords = 'NoUnpaidCustomerBills';
	}

	public function loadBox($max = 5)
	{
		global $conf, $user, $langs;

		$this->max = $max;

		include_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
		include_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

		$facturestatic = new Facture($this->db);
		$societestatic = new Societe($this->db);

		$langs->load("bills");

		$textHead = $langs->trans("BoxTitleOldestUnpaidCustomerBills");
		$this->info_box_head = array(
			'text' => $langs->trans("BoxTitleOldestUnpaidCustomerBills", $this->max).'<a class="paddingleft valignmiddle" href="'.DOL_URL_ROOT.'/compta/facture/list.php?search_status=1&sortfield=f.date_lim_reglement,f.ref&sortorder=ASC,ASC"><span class="badge">...</span></a>',
			'limit' => dol_strlen($textHead)
		);

		if ($user->hasRight('facture', 'lire')) {
			$sql1 = "SELECT s.rowid as socid, s.nom as name, s.name_alias, s.code_client, s.client";
			if (getDolGlobalString('MAIN_COMPANY_PERENTITY_SHARED')) {
				$sql1 .= ", spe.accountancy_code_customer as code_compta_client";
			} else {
				$sql1 .= ", s.code_compta as code_compta_client";
			}
			$sql1 .= ", s.logo, s.email, s.entity";
			$sql1 .= ", s.tva_intra, s.siren as idprof1, s.siret as idprof2, s.ape as idprof3, s.idprof4, s.idprof5, s.idprof6";
			$sql1 .= ", f.ref, f.date_lim_reglement as datelimit";
			$sql1 .= ", f.type";
			$sql1 .= ", f.datef as date";
			$sql1 .= ", f.total_ht";
			$sql1 .= ", f.total_tva";
			$sql1 .= ", f.total_ttc";
			if (isModEnabled('multicurrency')) {
				$sql1 .= ", f.multicurrency_total_ht";
				$sql1 .= ", f.multicurrency_code";
			}
			$sql1 .= ", f.paye, f.fk_statut as status, f.rowid as facid";
			$sql1 .= ", SUM(pf.amount) as am";
			$sql2 = " FROM ".MAIN_DB_PREFIX."societe as s";
			if (empty($user->socid) && !$user->hasRight('societe', 'client', 'voir')) {
				$sql2 .= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
			}
			$sql2 .= ", ".MAIN_DB_PREFIX."facture as f";
			// Multicompany: match per-entity accounting row to the invoice's entity (not only $conf->entity)
			if (getDolGlobalString('MAIN_COMPANY_PERENTITY_SHARED')) {
				$sql2 .= " LEFT JOIN ".MAIN_DB_PREFIX."societe_perentity as spe ON spe.fk_soc = s.rowid AND spe.entity = f.entity";
			}
			$sql2 .= " LEFT JOIN ".MAIN_DB_PREFIX."paiement_facture as pf ON f.rowid = pf.fk_facture";
			$sql2 .= " WHERE f.fk_soc = s.rowid";
			$sql2 .= " AND f.entity IN (".getEntity('invoice').")";
			$sql2 .= " AND f.paye = 0";
			$sql2 .= " AND fk_statut = 1";
			if (empty($user->socid) && !$user->hasRight('societe', 'client', 'voir')) {
				$sql2 .= " AND s.rowid = sc.fk_soc AND sc.fk_user = ".((int) $user->id);
			}
			if ($user->socid) {
				$sql2 .= " AND s.rowid = ".((int) $user->socid);
			}
			$sql3 = " GROUP BY s.rowid, s.nom, s.name_alias, s.code_client, s.client, s.logo, s.email, s.entity, s.tva_intra, s.siren, s.siret, s.ape, s.idprof4, s.idprof5, s.idprof6,";
			if (getDolGlobalString('MAIN_COMPANY_PERENTITY_SHARED')) {
				$sql3 .= " spe.accountancy_code_customer,";
			} else {
				$sql3 .= " s.code_compta,";
			}
			$sql3 .= " f.rowid, f.ref, f.date_lim_reglement,";
			$sql3 .= " f.type, f.datef, f.total_ht, f.total_tva, f.total_ttc, f.paye, f.fk_statut";
			if (isModEnabled('multicurrency')) {
				$sql3 .= ", f.multicurrency_total_ht, f.multicurrency_code";
			}
			$sql3 .= " ORDER BY date_lim_reglement ASC, f.ref ASC";
			$sql3 .= $this->db->plimit($this->max + 1, 0);

			$sql = $sql1.$sql2.$sql3;

			$result = $this->db->query($sql);
			if ($result) {
				$num = $this->db->num_rows($result);
				$line = 0;
				$l_due_date = $langs->trans('Late').' ('.strtolower($langs->trans('DateDue')).': %s)';

				while ($line < min($num, $this->max)) {
					$objp = $this->db->fetch_object($result);

					$date = $this->db->jdate($objp->date);
					$datelimit = $this->db->jdate($objp->datelimit);

					$facturestatic->id = $objp->facid;
					$facturestatic->ref = $objp->ref;
					$facturestatic->type = $objp->type;
					$facturestatic->total_ht = $objp->total_ht;
					$facturestatic->total_tva = $objp->total_tva;
					$facturestatic->total_ttc = $objp->total_ttc;
					$facturestatic->date = $date;
					$facturestatic->date_lim_reglement = $datelimit;
					$facturestatic->statut = $objp->status;
					$facturestatic->status = $objp->status;
					$facturestatic->paye = $objp->paye;
					$facturestatic->paid = $objp->paye;
					$facturestatic->alreadypaid = $objp->am;
					$facturestatic->totalpaid = $objp->am;

					$societestatic->id = $objp->socid;
					$societestatic->name = $objp->name;
					$societestatic->code_client = $objp->code_client;
					$societestatic->code_compta = $objp->code_compta_client;
					$societestatic->code_compta_client = $objp->code_compta_client;
					$societestatic->client = $objp->client;
					$societestatic->logo = $objp->logo;
					$societestatic->email = $objp->email;
					$societestatic->entity = $objp->entity;
					$societestatic->tva_intra = $objp->tva_intra;
					$societestatic->idprof1 = !empty($objp->idprof1) ? $objp->idprof1 : '';
					$societestatic->idprof2 = !empty($objp->idprof2) ? $objp->idprof2 : '';
					$societestatic->idprof3 = !empty($objp->idprof3) ? $objp->idprof3 : '';
					$societestatic->idprof4 = !empty($objp->idprof4) ? $objp->idprof4 : '';
					$societestatic->idprof5 = !empty($objp->idprof5) ? $objp->idprof5 : '';
					$societestatic->idprof6 = !empty($objp->idprof6) ? $objp->idprof6 : '';

					$late = '';
					if ($facturestatic->hasDelay()) {
						$late = img_warning(sprintf($l_due_date, dol_print_date($datelimit, 'day', 'tzuserrel')));
					}

					$amount = price($objp->total_ht, 0, $langs, 0, -1, -1, $conf->currency);
					if (isModEnabled('multicurrency') && !empty($objp->multicurrency_code)) {
						$amount = price($objp->multicurrency_total_ht, 0, $langs, 0, -1, -1, $objp->multicurrency_code);
					}

					$this->info_box_contents[$line][] = array(
						'td' => 'class="nowraponall"',
						'text' => $facturestatic->getNomUrl(1),
						'text2' => $late,
						'asis' => 1,
					);
					$this->info_box_contents[$line][] = array(
						'td' => 'class="tdoverflowmax150 maxwidth150onsmartphone"',
						'text' => $societestatic->getNomUrl(1, '', 44),
						'asis' => 1,
					);
					$this->info_box_contents[$line][] = array(
						'td' => 'class="nowraponall right amount"',
						'text' => $amount,
					);
					$this->info_box_contents[$line][] = array(
						'td' => 'class="center nowraponall" title="'.dol_escape_htmltag($langs->trans("DateDue").': '.dol_print_date($datelimit, 'day', 'tzuserrel')).'"',
						'text' => dol_print_date($datelimit, 'day', 'tzuserrel'),
					);
					$this->info_box_contents[$line][] = array(
						'td' => 'class="right" width="18"',
						'text' => $facturestatic->LibStatut($objp->paye, $objp->status, 3, $objp->am, $objp->type),
					);
					$line++;
				}
				if ($this->max < $num) {
					$this->info_box_contents[$line][] = array('td' => 'colspan="6"', 'text' => '...');
					$line++;
				}

				if ($num == 0) {
					// no total row
				} else {
					$sql = "SELECT SUM(f.total_ht) as total_ht ".$sql2;
					$result = $this->db->query($sql);
					$objp = $this->db->fetch_object($result);
					$totalamount = $objp->total_ht;

					$this->info_box_contents[$line][] = array(
						'tr' => 'class="liste_total"',
						'td' => 'class="liste_total"',
						'text' => $langs->trans("Total"),
					);
					$this->info_box_contents[$line][] = array(
						'td' => 'class="liste_total"',
						'text' => "&nbsp;",
					);
					$this->info_box_contents[$line][] = array(
						'td' => 'class="nowraponall right liste_total"',
						'text' => price($totalamount, 0, $langs, 0, -1, -1, $conf->currency),
					);
					$this->info_box_contents[$line][] = array(
						'td' => 'class="liste_total"',
						'text' => "&nbsp;",
					);
					$this->info_box_contents[$line][] = array(
						'td' => 'class="liste_total"',
						'text' => "&nbsp;",
					);
					$this->db->free($result);
				}
			} else {
				$this->info_box_contents[0][0] = array(
					'td' => '',
					'maxlength' => 500,
					'text' => ($this->db->error().' sql='.$sql),
				);
			}
		} else {
			$this->info_box_contents[0][0] = array(
				'td' => 'class="nohover left"',
				'text' => '<span class="opacitymedium">'.$langs->trans("ReadPermissionNotAllowed").'</span>'
			);
		}
	}

	public function showBox($head = null, $contents = null, $nooutput = 0)
	{
		return parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput);
	}
}
