<?php
/* Copyright (C) 2025 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * SLY: Replacement for box_factures with multicurrency support (zero core patch).
 */

include_once DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';

/**
 * SLY: Last customer bills box with multicurrency
 */
class box_sly_lastcustomerbills extends ModeleBoxes
{
	public $boxcode = "lastcustomerbillssly";
	public $boximg = "object_bill";
	public $boxlabel = "BoxLastCustomerBills";
	public $depends = array("facture");

	public function __construct($db, $param = '')
	{
		global $user;
		$this->db = $db;
		$this->hidden = !$user->hasRight('facture', 'lire');
		$this->urltoaddentry = DOL_URL_ROOT.'/compta/facture/card.php?action=create';
		$this->msgNoRecords = 'NoRecordedInvoices';
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

		$text = $langs->trans("BoxTitleLast".(getDolGlobalString('MAIN_LASTBOX_ON_OBJECT_DATE') ? "" : "Modified")."CustomerBills", $max);
		$this->info_box_head = array(
			'text' => $text.'<a class="paddingleft" href="'.DOL_URL_ROOT.'/compta/facture/list.php?sortfield=f.tms&sortorder=DESC"><span class="badge">...</span></a>',
			'limit' => dol_strlen($text)
		);

		if ($user->hasRight('facture', 'lire')) {
			$sql = "SELECT f.rowid as facid";
			$sql .= ", f.ref, f.type, f.total_ht";
			$sql .= ", f.total_tva";
			$sql .= ", f.total_ttc";
			if (isModEnabled('multicurrency')) {
				$sql .= ", f.multicurrency_total_ht";
				$sql .= ", f.multicurrency_code";
			}
			$sql .= ", f.datef as date";
			$sql .= ", f.paye, f.fk_statut as status, f.datec, f.tms";
			$sql .= ", f.date_lim_reglement as datelimite";
			$sql .= ", s.rowid as socid, s.nom as name, s.name_alias";
			$sql .= ", s.code_client, s.code_compta, s.client";
			$sql .= ", s.logo, s.email, s.entity";
			$sql .= ", s.tva_intra, s.siren as idprof1, s.siret as idprof2, s.ape as idprof3, s.idprof4, s.idprof5, s.idprof6";
			$sql .= ", SUM(pf.amount) as am";
			$sql .= " FROM ".MAIN_DB_PREFIX."facture as f";
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."paiement_facture as pf ON f.rowid = pf.fk_facture,";
			$sql .= " ".MAIN_DB_PREFIX."societe as s";
			if (empty($user->socid) && !$user->hasRight('societe', 'client', 'voir')) {
				$sql .= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
			}
			$sql .= " WHERE f.fk_soc = s.rowid";
			$sql .= " AND f.fk_statut > 0";
			$sql .= " AND f.entity IN (".getEntity('invoice').")";
			if (empty($user->socid) && !$user->hasRight('societe', 'client', 'voir')) {
				$sql .= " AND s.rowid = sc.fk_soc AND sc.fk_user = ".((int) $user->id);
			}
			if ($user->socid) {
				$sql .= " AND s.rowid = ".((int) $user->socid);
			}
			$sql .= " GROUP BY s.rowid, s.nom, s.name_alias, s.code_client, s.code_compta, s.client, s.logo, s.email, s.entity, s.tva_intra, s.siren, s.siret, s.ape, s.idprof4, s.idprof5, s.idprof6,";
			$sql .= " f.rowid, f.ref, f.type, f.total_ht, f.total_tva, f.total_ttc, f.datef, f.paye, f.fk_statut, f.datec, f.tms, f.date_lim_reglement";
			if (isModEnabled('multicurrency')) {
				$sql .= ", f.multicurrency_total_ht, f.multicurrency_code";
			}
			if (getDolGlobalString('MAIN_LASTBOX_ON_OBJECT_DATE')) {
				$sql .= " ORDER BY f.datef DESC, f.ref DESC ";
			} else {
				$sql .= " ORDER BY f.tms DESC, f.ref DESC ";
			}
			$sql .= $this->db->plimit($max, 0);

			$result = $this->db->query($sql);
			if ($result) {
				$num = $this->db->num_rows($result);
				$line = 0;
				$l_due_date = $langs->trans('Late').' ('.$langs->trans('DateDue').': %s)';

				while ($line < $num) {
					$objp = $this->db->fetch_object($result);

					$datelimite = $this->db->jdate($objp->datelimite);
					$datem = $this->db->jdate($objp->tms);

					$facturestatic->id = $objp->facid;
					$facturestatic->ref = $objp->ref;
					$facturestatic->type = $objp->type;
					$facturestatic->total_ht = $objp->total_ht;
					$facturestatic->total_tva = $objp->total_tva;
					$facturestatic->total_ttc = $objp->total_ttc;
					$facturestatic->statut = $objp->status;
					$facturestatic->status = $objp->status;
					$facturestatic->date = $this->db->jdate($objp->date);
					$facturestatic->date_lim_reglement = $this->db->jdate($objp->datelimite);
					$facturestatic->paye = $objp->paye;
					$facturestatic->alreadypaid = $objp->am;

					$societestatic->id = $objp->socid;
					$societestatic->name = $objp->name;
					$societestatic->code_client = $objp->code_client;
					$societestatic->code_compta = $objp->code_compta;
					$societestatic->code_compta_client = $objp->code_compta;
					$societestatic->client = $objp->client;
					$societestatic->logo = $objp->logo;
					$societestatic->email = $objp->email;
					$societestatic->entity = $objp->entity;
					$societestatic->tva_intra = $objp->tva_intra;
					$societestatic->idprof1 = $objp->idprof1;
					$societestatic->idprof2 = $objp->idprof2;
					$societestatic->idprof3 = $objp->idprof3;
					$societestatic->idprof4 = $objp->idprof4;
					$societestatic->idprof5 = $objp->idprof5;
					$societestatic->idprof6 = $objp->idprof6;

					$late = '';
					if ($facturestatic->hasDelay()) {
						$late = img_warning(sprintf($l_due_date, dol_print_date($datelimite, 'day', 'tzuserrel')));
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
						'td' => 'class="tdoverflowmax200"',
						'text' => $societestatic->getNomUrl(1, '', 40),
						'asis' => 1,
					);
					$this->info_box_contents[$line][] = array(
						'td' => 'class="right nowraponall amount"',
						'text' => $amount,
					);
					$this->info_box_contents[$line][] = array(
						'td' => 'class="center nowraponall" title="'.dol_escape_htmltag($langs->trans("DateModification").': '.dol_print_date($datem, 'dayhour', 'tzuserrel')).'"',
						'text' => dol_print_date($datem, 'day', 'tzuserrel'),
					);
					$this->info_box_contents[$line][] = array(
						'td' => 'class="right" width="18"',
						'text' => $facturestatic->LibStatut($objp->paye, $objp->status, 3, $objp->am),
					);
					$line++;
				}
				$this->db->free($result);
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
