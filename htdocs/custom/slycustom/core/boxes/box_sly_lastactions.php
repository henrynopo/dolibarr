<?php
/* Copyright (C) 2025 SLY Custom - Replacement for box_actions with fk_user_done (zero core patch). */

include_once DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';

class box_sly_lastactions extends ModeleBoxes
{
	public $boxcode = "lastactionssly";
	public $boximg = "object_action";
	public $boxlabel = "BoxOldestActions";
	public $depends = array("agenda");
	public $enabled = 1;

	public function __construct($db, $param = '')
	{
		global $user;
		$this->db = $db;
		$this->enabled = isModEnabled('agenda');
		$this->hidden = !($user->hasRight('agenda', 'myactions', 'read'));
		$this->urltoaddentry = DOL_URL_ROOT.'/comm/action/card.php?action=create';
		$this->msgNoRecords = 'NoActionsToDo';
	}

	public function loadBox($max = 5)
	{
		global $user, $langs;
		$this->max = $max;
		include_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
		include_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
		$societestatic = new Societe($this->db);
		$actionstatic = new ActionComm($this->db);

		$this->info_box_head = array('text' => $langs->trans("BoxTitleOldestActionsToDo", $max));

		if ($user->hasRight('agenda', 'myactions', 'read')) {
			$sql = "SELECT a.id, a.label, a.datep as dp, a.percent as percentage";
			$sql .= ", ta.code";
			$sql .= ", ta.libelle as type_label";
			$sql .= ", s.rowid as socid, s.nom as name, s.name_alias";
			$sql .= ", s.code_client, s.code_compta as code_compta_client, s.client";
			$sql .= ", s.logo, s.email, s.entity";
			$sql .= " FROM ".MAIN_DB_PREFIX."c_actioncomm AS ta, ".MAIN_DB_PREFIX."actioncomm AS a";
			if (empty($user->socid) && !$user->hasRight('societe', 'client', 'voir')) {
				$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe_commerciaux as sc ON a.fk_soc = sc.fk_soc";
			}
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON a.fk_soc = s.rowid";
			$sql .= " WHERE a.fk_action = ta.id";
			$sql .= " AND a.entity IN (".getEntity('actioncomm').")";
			$sql .= " AND a.percent >= 0 AND a.percent < 100";
			if (empty($user->socid) && !$user->hasRight('societe', 'client', 'voir')) {
				$sql .= " AND (a.fk_soc IS NULL OR sc.fk_user = ".((int) $user->id).")";
			}
			if ($user->socid) {
				$sql .= " AND s.rowid = ".((int) $user->socid);
			}
			if (!$user->hasRight('agenda', 'allactions', 'read')) {
				$sql .= " AND (a.fk_user_author = ".((int) $user->id)." OR a.fk_user_action = ".((int) $user->id)." OR a.fk_user_done = ".((int) $user->id).")";
			}
			$sql .= " ORDER BY a.datep ASC";
			$sql .= $this->db->plimit($max, 0);

			$result = $this->db->query($sql);
			if ($result) {
				$now = dol_now();
				$delay_warning = getDolGlobalInt('MAIN_DELAY_ACTIONS_TODO') * 24 * 60 * 60;
				$num = $this->db->num_rows($result);
				$line = 0;
				while ($line < $num) {
					$late = '';
					$objp = $this->db->fetch_object($result);
					$datelimite = $this->db->jdate($objp->dp);
					$actionstatic->id = $objp->id;
					$actionstatic->label = $objp->label;
					$actionstatic->type_label = $objp->type_label;
					$actionstatic->code = $objp->code;
					$societestatic->id = $objp->socid;
					$societestatic->name = $objp->name;
					$societestatic->code_client = $objp->code_client;
					$societestatic->code_compta_client = $objp->code_compta_client;
					$societestatic->client = $objp->client;
					$societestatic->logo = $objp->logo;
					$societestatic->email = $objp->email;
					$societestatic->entity = $objp->entity;
					if ($objp->percentage >= 0 && $objp->percentage < 100 && $datelimite < ($now - $delay_warning)) {
						$late = img_warning($langs->trans("Late"));
					}
					$this->info_box_contents[$line][0] = array(
						'td' => 'class="tdoverflowmax200"',
						'text' => $actionstatic->getNomUrl(1),
						'text2' => $late,
						'asis' => 1
					);
					$this->info_box_contents[$line][1] = array(
						'td' => 'class="tdoverflowmax150 maxwidth150onsmartphone"',
						'text' => $societestatic->getNomUrl(1),
						'asis' => 1
					);
					$this->info_box_contents[$line][2] = array(
						'td' => 'class="center nowraponall"',
						'text' => dol_print_date($datelimite, 'day', 'tzuserrel')
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
