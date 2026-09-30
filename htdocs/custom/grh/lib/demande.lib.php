<?php
/**
 *  Return array head with list of tabs to view object informations
 *
 *  @param	Object	$object         demande
 *  @return array           		head
 */
function demande_prepare_head($id)
{
	global $langs, $conf, $user;

	$h 	  = 0;
	$head = array();

    /*$head[$h][0] = dol_buildpath('/grh/demande/index.php?id='.$id ,1);
    $head[$h][1] = $langs->trans("demacht");
    $head[$h][2] = 'demande';

    $h++;*/

    $head[$h][0] = dol_buildpath('/grh/commande/index.php?daId='.$id ,1);
	$head[$h][1] = $langs->trans('comacht');
	$head[$h][2] = 'commande';

    $h++;

    $head[$h][0] = dol_buildpath('/grh/reception_com/index.php?daId='.$id ,1);
    $head[$h][1] = $langs->trans('reception_com');
    $head[$h][2] = 'reception_com';

    $h++;

    $head[$h][0] = dol_buildpath('/grh/evaluation_supl/index.php?daId='.$id ,1);
    $head[$h][1] = $langs->trans('evaluation_supl');
    $head[$h][2] = 'evaluation_supl';
	
    // Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    // $this->tabs = array('entity:+tabname:Title:@mymodule:/mymodule/mypage.php?id=__ID__');   to add new tab
    // $this->tabs = array('entity:-tabname);   												to remove a tab
    complete_head_from_modules($conf,$langs,$object,$head,$h,'demacht');

	complete_head_from_modules($conf,$langs,$object,$head,$h,'demacht','remove');

	return $head;
}

?>
