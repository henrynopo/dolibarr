<?php
/* Copyright (C) 2025 SLY Custom - Replacement for box_supplier_orders_awaiting_reception with fa-dolly icon (zero core patch). */

include_once DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';

class box_sly_supplierordersawaitingreception extends ModeleBoxes
{
	public $boxcode = "supplierordersawaitingreceptionsly";
	public $boximg = "object_order";
	public $boxlabel = "BoxLatestSupplierOrdersAwaitingReception";
	public $depends = array("fournisseur");

	public function __construct($db, $param = '')
	{
		global $user;
		$this->db = $db;
		$this->hidden = !($user->hasRight('fournisseur', 'commande', 'lire'));
	}

	public function loadBox($max = 5)
	{
		global $conf, $user, $langs;
		$langs->loadLangs(array("boxes", "sendings", "orders"));
		$this->max = $max;

		include_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';
		$supplierorderstatic = new CommandeFournisseur($this->db);
		include_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.class.php';
		$thirdpartystatic = new Fournisseur($this->db);

		$this->info_box_head = array('text' => $langs->trans("BoxTitleSupplierOrdersAwaitingReception", $max));

		if ($user->hasRight('fournisseur', 'commande', 'lire')) {
			$sql = "SELECT s.rowid as socid, s.nom as name, s.name_alias";
			$sql .= ", s.code_fournisseur, s.code_compta_fournisseur, s.fournisseur";
			$sql .= ", s.logo, s.email, s.entity";
			$sql .= ", c.rowid, c.ref, c.tms, c.date_commande, c.date_livraison as delivery_date";
			$sql .= ", c.total_ht";
			$sql .= ", c.total_tva";
			$sql .= ", c.total_ttc";
			if (isModEnabled('multicurrency')) {
				$sql .= ", c.multicurrency_total_ht";
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
			$sql .= " AND c.date_livraison IS NOT NULL";
			$sql .= " AND c.fk_statut IN (".CommandeFournisseur::STATUS_ORDERSENT.", ".CommandeFournisseur::STATUS_RECEIVED_PARTIALLY.")";
			if (empty($user->socid) && !$user->hasRight('societe', 'client', 'voir')) {
				$sql .= " AND s.rowid = sc.fk_soc AND sc.fk_user = ".((int) $user->id);
			}
			if ($user->socid) {
				$sql .= " AND s.rowid = ".((int) $user->socid);
			}
			if (getDolGlobalString('MAIN_LASTBOX_ON_OBJECT_DATE')) {
				$sql .= " ORDER BY c.date_commande DESC, c.ref DESC";
			} else {
				$sql .= " ORDER BY c.date_livraison ASC, c.fk_statut ASC";
			}
			$sql .= $this->db->plimit($max, 0);

			$result = $this->db->query($sql);
			if ($result) {
				$num = $this->db->num_rows($result);
				$line = 0;
				while ($line < $num) {
					$objp = $this->db->fetch_object($result);
					$date = $this->db->jdate($objp->date_commande);
					$delivery_date = $this->db->jdate($objp->delivery_date);
					$datem = $this->db->jdate($objp->tms);

					$supplierorderstatic->id = $objp->rowid;
					$supplierorderstatic->ref = $objp->ref;
					$supplierorderstatic->delivery_date = $delivery_date;
					$supplierorderstatic->statut = $objp->status;
					$supplierorderstatic->status = $objp->status;

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
					$amountText = price($objp->total_ht, 0, $langs, 0, -1, -1, $conf->currency);
					if (isModEnabled('multicurrency') && !empty($objp->multicurrency_code)) {
						$amountText = price($objp->multicurrency_total_ht, 0, $langs, 0, -1, -1, $objp->multicurrency_code);
					}
					$this->info_box_contents[$line][] = array(
						'td' => 'class="nowraponall right amount"',
						'text' => $amountText,
					);

					$delayIcon = '';
					if ($supplierorderstatic->hasDelay()) {
						$delayIcon = img_warning($langs->trans("Late"));
					}

					$this->info_box_contents[$line][] = array(
						'td' => 'class="right"',
						'text' => $delayIcon.'<span class="classfortooltip" title="'.$langs->trans('DateDeliveryPlanned').'"><i class="fa fa-dolly" ></i> '.($delivery_date ? dol_print_date($delivery_date, 'day', 'tzuserrel') : '').'</span>',
						'asis' => 1
					);

					$line++;
				}

				if ($num == 0) {
					$this->info_box_contents[$line][] = array(
						'td' => 'class="center"',
						'text' => '<span class="opacitymedium">'.$langs->trans("NoSupplierOrder").'</span>',
					);
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
