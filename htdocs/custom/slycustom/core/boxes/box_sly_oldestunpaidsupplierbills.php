<?php
/* Copyright (C) 2025 SLY Custom
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * SLY: Replacement for box_factures_fourn_imp with multicurrency support (zero core patch).
 */

include_once DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';

/**
 * SLY: Oldest unpaid supplier bills box with multicurrency
 */
class box_sly_oldestunpaidsupplierbills extends ModeleBoxes
{
	public $boxcode = "oldestunpaidsupplierbillssly";
	public $boximg = "object_bill";
	public $boxlabel = "BoxOldestUnpaidSupplierBills";
	public $depends = array("facture", "fournisseur");

	public function __construct($db, $param = '')
	{
		global $user;
		$this->db = $db;
		$this->hidden = !($user->hasRight('fournisseur', 'facture', 'lire'));
	}

	public function loadBox($max = 5)
	{
		global $conf, $user, $langs;

		$this->max = $max;

		include_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
		include_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.class.php';

		$facturestatic = new FactureFournisseur($this->db);
		$thirdpartystatic = new Fournisseur($this->db);

		$langs->load("bills");

		$textHead = $langs->trans("BoxTitleOldestUnpaidSupplierBills");
		$this->info_box_head = array(
			'text' => $langs->trans("BoxTitleOldestUnpaidSupplierBills", $this->max).'<a class="paddingleft valignmiddle" href="'.DOL_URL_ROOT.'/fourn/facture/list.php?search_status=1&sortfield=f.date_lim_reglement,f.ref&sortorder=ASC,ASC"><span class="badge">...</span></a>',
			'limit' => dol_strlen($textHead)
		);

		if ($user->hasRight('fournisseur', 'facture', 'lire')) {
			$sql1 = "SELECT s.rowid as socid, s.nom as name, s.name_alias";
			$sql1 .= ", s.code_fournisseur, s.code_compta_fournisseur, s.fournisseur";
			$sql1 .= ", s.logo, s.email, s.entity, s.tva_intra, s.siren, s.siret, s.ape, s.idprof4, s.idprof5, s.idprof6";
			$sql1 .= ", f.rowid as facid, f.ref, f.ref_supplier, f.date_lim_reglement as datelimite";
			$sql1 .= ", f.datef as df";
			$sql1 .= ", f.total_ht";
			$sql1 .= ", f.total_tva";
			$sql1 .= ", f.total_ttc";
			if (isModEnabled('multicurrency')) {
				$sql1 .= ", f.multicurrency_total_ht";
				$sql1 .= ", f.multicurrency_code";
			}
			$sql1 .= ", f.paye, f.fk_statut as status, f.type";
			$sql1 .= ", f.tms";
			$sql1 .= ", SUM(pf.amount) as am";
			$sql2 = " FROM ".MAIN_DB_PREFIX."societe as s";
			$sql2 .= ",".MAIN_DB_PREFIX."facture_fourn as f";
			$sql2 .= " LEFT JOIN ".MAIN_DB_PREFIX."paiementfourn_facturefourn as pf ON f.rowid = pf.fk_facturefourn";
			if (empty($user->socid) && !$user->hasRight('societe', 'client', 'voir')) {
				$sql2 .= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
			}
			$sql2 .= " WHERE f.fk_soc = s.rowid";
			$sql2 .= " AND f.entity IN (".getEntity('supplier_invoice').")";
			$sql2 .= " AND f.paye = 0";
			$sql2 .= " AND fk_statut = 1";
			if (empty($user->socid) && !$user->hasRight('societe', 'client', 'voir')) {
				$sql2 .= " AND s.rowid = sc.fk_soc AND sc.fk_user = ".((int) $user->id);
			}
			if ($user->socid) {
				$sql2 .= " AND s.rowid = ".((int) $user->socid);
			}
			$sql3 = " GROUP BY s.rowid, s.nom, s.name_alias, s.code_fournisseur, s.code_compta_fournisseur, s.fournisseur, s.logo, s.email, s.entity, s.tva_intra, s.siren, s.siret, s.ape, s.idprof4, s.idprof5, s.idprof6,";
			$sql3 .= " f.rowid, f.ref, f.ref_supplier, f.date_lim_reglement,";
			$sql3 .= " f.type, f.datef, f.total_ht, f.total_tva, f.total_ttc, f.paye, f.fk_statut, f.tms";
			if (isModEnabled('multicurrency')) {
				$sql3 .= ", f.multicurrency_total_ht, f.multicurrency_code";
			}
			$sql3 .= " ORDER BY datelimite ASC, f.ref_supplier ASC";
			$sql3 .= $this->db->plimit($this->max + 1, 0);

			$sql = $sql1.$sql2.$sql3;

			$result = $this->db->query($sql);
			if ($result) {
				$num = $this->db->num_rows($result);
				$line = 0;
				$l_due_date = $langs->trans('Late').' ('.strtolower($langs->trans('DateDue')).': %s)';

				while ($line < min($num, $this->max)) {
					$objp = $this->db->fetch_object($result);

					$datelimite = $this->db->jdate($objp->datelimite);
					$date = $this->db->jdate($objp->df);
					$datem = $this->db->jdate($objp->tms);

					$facturestatic->id = $objp->facid;
					$facturestatic->ref = $objp->ref;
					$facturestatic->type = $objp->type;
					$facturestatic->total_ht = $objp->total_ht;
					$facturestatic->total_tva = $objp->total_tva;
					$facturestatic->total_ttc = $objp->total_ttc;
					$facturestatic->date = $date;
					$facturestatic->date_echeance = $datelimite;
					$facturestatic->statut = $objp->status;
					$facturestatic->status = $objp->status;
					$facturestatic->paye = $objp->paye;
					$facturestatic->paid = $objp->paye;
					$facturestatic->alreadypaid = $objp->am;
					$facturestatic->totalpaid = $objp->am;

					$thirdpartystatic->id = $objp->socid;
					$thirdpartystatic->name = $objp->name;
					$thirdpartystatic->name_alias = $objp->name_alias;
					$thirdpartystatic->code_fournisseur = $objp->code_fournisseur;
					$thirdpartystatic->code_compta_fournisseur = $objp->code_compta_fournisseur;
					$thirdpartystatic->fournisseur = $objp->fournisseur;
					$thirdpartystatic->logo = $objp->logo;
					$thirdpartystatic->email = $objp->email;
					$thirdpartystatic->entity = $objp->entity;
					$thirdpartystatic->tva_intra = $objp->tva_intra;
					$thirdpartystatic->idprof1 = !empty($objp->idprof1) ? $objp->idprof1 : '';
					$thirdpartystatic->idprof2 = !empty($objp->idprof2) ? $objp->idprof2 : '';
					$thirdpartystatic->idprof3 = !empty($objp->idprof3) ? $objp->idprof3 : '';
					$thirdpartystatic->idprof4 = !empty($objp->idprof4) ? $objp->idprof4 : '';
					$thirdpartystatic->idprof5 = !empty($objp->idprof5) ? $objp->idprof5 : '';
					$thirdpartystatic->idprof6 = !empty($objp->idprof6) ? $objp->idprof6 : '';

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
						'td' => 'class="tdoverflowmax150 maxwidth150onsmartphone"',
						'text' => $thirdpartystatic->getNomUrl(1, '', 44),
						'asis' => 1,
					);
					$this->info_box_contents[$line][] = array(
						'td' => 'class="nowraponall right amount"',
						'text' => $amount,
					);
					$this->info_box_contents[$line][] = array(
						'td' => 'class="center nowraponall" title="'.dol_escape_htmltag($langs->trans("DateDue").': '.dol_print_date($datelimite, 'day', 'tzuserrel')).'"',
						'text' => dol_print_date($datelimite, 'day', 'tzuserrel'),
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
					$this->info_box_contents[$line][0] = array(
						'td' => 'class="center" colspan="3"',
						'text' => '<span class="opacitymedium">'.$langs->trans("NoUnpaidSupplierBills").'</span>',
					);
				} else {
					$sql = "SELECT SUM(f.total_ht) as total_ht ".$sql2;
					$result = $this->db->query($sql);
					$objp = $this->db->fetch_object($result);
					$totalamount = $objp->total_ht;

					$this->info_box_contents[$line][] = array(
						'tr' => 'class="liste_total_wrap"',
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
