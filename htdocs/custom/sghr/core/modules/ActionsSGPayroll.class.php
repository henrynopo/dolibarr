<?php
/* Copyright (C) 2026 HBC Group - Singapore Payroll Module
 * License: GPL v3+
 */

/**
 * Class ActionsSghr
 * 
 * Hook actions to inject Singapore Payroll tabs into core User cards.
 */
class ActionsSghr
{
	/**
	 * Overwrite or add tabs to the group of tabs.
	 *
	 * @param   array() $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject $object    The object the tabs are for
	 * @param   string &$action         The current action
	 * @param   HookManager $hookmanager The hookmanager
	 * @return  int                     0 if OK, < 0 if KO
	 */
	public function addTabs($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $conf, $user;

		$context = explode(':', $parameters['context']);

		// dol_syslog("SGPayroll addTabs hook called with context: ".$parameters['context'], LOG_DEBUG);

		if (in_array('usercard', $context) || in_array('employeecard', $context)) {
			$fk_user = $object->id;
			if ($fk_user > 0) {
				$langs->load("sghr@sghr");
				
				$link = dol_buildpath('/sghr/employee_card.php', 1).'?fk_user='.$fk_user;
				
				$parameters['tabs'][] = array(
					'title' => $langs->trans("SingaporePayroll"),
					'url'   => $link,
					'lang'  => 'sghr@sghr',
					'position' => 200, // Move to the end of the first block
					'id'    => 'sghr'
				);
			}
		}

		return 0;
	}
}
