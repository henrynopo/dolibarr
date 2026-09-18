<?php
/* Copyright (C) 2025 SLY Custom - Replacement for box_supplier_orders with multicurrency (zero core patch). */

include_once DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';

class box_sly_latestsupplierorders extends ModeleBoxes
{
	public $boxcode = "latestsupplierorderssly";
	public $boximg = "object_order";
	public $boxlabel = "BoxLatestSupplierOrders";
	public $depends = array("fournisseur");

	public function __construct($db, $param = '')
	{
		global $user;
		$this->db = $db;
		$this->hidden = !($user->hasRight('fournisseur', 'commande', 'lire'));
		$this->urltoaddentry = DOL_URL_ROOT.'/fourn/commande/card.php?action=create';
		$this->msgNoRecords = 'NoSupplierOrder';
	}

	public function loadBox($max = 5)
	{
		global $conf, $user, $langs;
		$langs->load("boxes");
		$this->max = $max;

		include_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';
		$supplierorderstatic = new CommandeFournisseur($this->db);
		include_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.class.php';
		$thirdpartystatic = new Fournisseur($this->db);

		$text = $langs->trans("BoxTitleLatest".(getDolGlobalString('MAIN_LASTBOX_ON_OBJECT_DATE') ? "" : "Modified")."SupplierOrders", $max);
		$this->info_box_head = array(
			'text' => $text.'<a class="paddingleft" href="'.DOL_URL_ROOT.'/fourn/commande/list.php?sortfield=cf.tms&sortorder=DESC"><span class="badge">...</span></a>'
		);

		if ($user->hasRight('fournisseur', 'commande', 'lire')) {
			$sql = "SELECT s.rowid as socid, s.nom as name, s.name_alias";
			$sql .= ", s.code_fournisseur, s.code_compta_fournisseur, s.fournisseur";
			$sql .= ", s.logo, s.email, s.entity";
			$sql .= ", c.rowid, c.ref, c.tms, c.date_commande";
			$sql .= ", c.total_ht";
			$sql .= ", c.total_tva";
			$sql .= ", c.total_ttc";
			if (isModEnabled('multicurrency')) {
				$sql .= ", c.multicurrency_total_ht";
				$sql .= ", c.multicurrency_total_tva";
				$sql .= ", c.multicurrency_total_ttc";
				$sql .= ", c.multicurrency_code";
			}
			$sql .= ", c.fk_statut as status";
			$sql .= " FROM ".MAIN_DB_PREFIX."societe as s";
			$sql .= ", ".MAIN_DB_PREFIX."commande_fournisseur as c";
			if (empty($user->socid) && !$user->hasRight('societe', 'client', 'voir')) {
				$sql .= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
			}
			$sql .= " WHERE c.fk_soc = s.rowid";
			$sql .= " AND c.entity IN (".getEntity('supplier_order').")";
			if (empty($user->socid) && !$user->hasRight('societe', 'client', 'voir')) {
				$sql .= " AND s.rowid = sc.fk_soc AND sc.fk_user = ".((int) $user->id);
			}
			if ($user->socid) {
				$sql .= " AND s.rowid = ".((int) $user->socid);
			}
			if (getDolGlobalString('MAIN_LASTBOX_ON_OBJECT_DATE')) {
				$sql .= " ORDER BY c.date_commande DESC, c.ref DESC ";
			} else {
				$sql .= " ORDER BY c.tms DESC, c.ref DESC ";
			}
			$sql .= $this->db->plimit($max, 0);

			$result = $this->db->query($sql);
			if ($result) {
				$num = $this->db->num_rows($result);
				$line = 0;
				while ($line < $num) {
					$objp = $this->db->fetch_object($result);
					$date = $this->db->jdate($objp->date_commande);
					$datem = $this->db->jdate($objp->tms);

					$supplierorderstatic->id = $objp->rowid;
					$supplierorderstatic->ref = $objp->ref;
					$supplierorderstatic->statut = $objp->status;
					$supplierorderstatic->status = $objp->status;
					$supplierorderstatic->date = $date;
					$supplierorderstatic->date_modification = $datem;

					$thirdpartystatic->id = $objp->socid;
					$thirdpartystatic->name = $objp->name;
					$thirdpartystatic->code_fournisseur = $objp->code_fournisseur;
					$thirdpartystatic->code_compta_fournisseur = $objp->code_compta_fournisseur;
					$thirdpartystatic->fournisseur = $objp->fournisseur;
					$thirdpartystatic->logo = $objp->logo;
					$thirdpartystatic->email = $objp->email;
					$thirdpartystatic->entity = $objp->entity;

					$this->info_box_contents[$line][] = array(
						'td' => 'class="nowraponall"',
						'text' => $supplierorderstatic->getNomUrl(1),
						'asis' => 1
					);
					$this->info_box_contents[$line][] = array(
						'td' => 'class="tdoverflowmax150 maxwidth150onsmartphone"',
						'text' => $thirdpartystatic->getNomUrl(1, 'supplier'),
						'asis' => 1,
					);
					$this->info_box_contents[$line][] = array(
						'td' => 'class="nowraponall right amount"',
						'text' => (!empty($objp->multicurrency_code) ? price($objp->multicurrency_total_ht, 0, $langs, 0, -1, -1, $objp->multicurrency_code) : price($objp->total_ht, 0, $langs, 0, -1, -1, $conf->currency)),
					);
					$this->info_box_contents[$line][] = array(
						'td' => 'class="center nowraponall" title="'.dol_escape_htmltag($langs->trans("DateModification").': '.dol_print_date($datem, 'dayhour', 'tzuserrel')).'"',
						'text' => dol_print_date($datem, 'day', 'tzuserrel'),
					);
					$this->info_box_contents[$line][] = array(
						'td' => 'class="right" width="18"',
						'text' => $supplierorderstatic->LibStatut($objp->status, 3),
					);
					$line++;
				}
				$this->db->free($result);
			} else {
				$this->info_box_contents[0][] = array(
					'td' => '',
					'maxlength' => 500,
					'text' => ($this->db->error().' sql='.$sql),
				);
			}
		} else {
			$this->info_box_contents[0][] = array(
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
