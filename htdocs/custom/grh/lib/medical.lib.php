<?php
/**
 *  Return array head with list of tabs to view object informations
 *
 *  @param	Object	$object         medical
 *  @return array           		head
 */
function medical_prepare_head($id)
{
	global $langs, $conf, $user;

	$h 	  = 0;
	$head = array();

    $head[$h][0] = dol_buildpath('/grh/medical/index.php?id='.$id,1);
    $head[$h][1] = $langs->trans("medical");
    $head[$h][2] = 'medical';

    $h++;

 //    $head[$h][0] = dol_buildpath('/grh/medical/antpersnl/index.php?daId='.$id,1);
	// $head[$h][1] = $langs->trans('antpersnl');
	// $head[$h][2] = 'antpersnl';

 //    $h++;

 //    $head[$h][0] = dol_buildpath('/grh/medical/vacination/index.php?daId='.$id,1);
 //    $head[$h][1] = $langs->trans('vacination');
 //    $head[$h][2] = 'vacination';

 //    $h++;

 //    $head[$h][0] = dol_buildpath('/grh/medical/antfamily/index.php?daId='.$id,1);
 //    $head[$h][1] = $langs->trans('antfamily');
 //    $head[$h][2] = 'antfamily';
    
 //   $h++;

 //    $head[$h][0] = dol_buildpath('/grh/medical/exmclinic/index.php?daId='.$id,1);
 //    $head[$h][1] = $langs->trans('exmclinic');
 //    $head[$h][2] = 'exmclinic';
    
 //    $h++;

 //    $head[$h][0] = dol_buildpath('/grh/medical/aparielres/index.php?daId='.$id,1);
 //    $head[$h][1] = $langs->trans('apariel');
 //    $head[$h][2] = 'aparielres';
    
 //    $h++;

 //    $head[$h][0] = dol_buildpath('/grh/medical/neropsy/index.php?daId='.$id,1);
 //    $head[$h][1] = $langs->trans('neropsy');
 //    $head[$h][2] = 'neropsy';
    
 //    $h++;

 //    $head[$h][0] = dol_buildpath('/grh/medical/exmcomp/index.php?daId='.$id,1);
 //    $head[$h][1] = $langs->trans('exmcomp');
 //    $head[$h][2] = 'exmcomp';
    
 //    $h++;

    $head[$h][0] = dol_buildpath('/grh/medical/exmcontrl/index.php?daId='.$id,1);
    $head[$h][1] = $langs->trans('exmcontrl');
    $head[$h][2] = 'exmcontrl';
    
    $h++;

    $head[$h][0] = dol_buildpath('/grh/medical/certificatm/index.php?daId='.$id,1);
    $head[$h][1] = $langs->trans('certificatm');
    $head[$h][2] = 'certificatm';
	
    // Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    // $this->tabs = array('entity:+tabname:Title:@mymodule:/mymodule/mypage.php?id=__ID__');   to add new tab
    // $this->tabs = array('entity:-tabname);   												to remove a tab
    complete_head_from_modules($conf,$langs,$object,$head,$h,'demacht');

	complete_head_from_modules($conf,$langs,$object,$head,$h,'demacht','remove');

	return $head;
}

function medical_apariel_prepare_head($id)
{
    global $langs, $conf, $user;

    $h    = 0;
    $head = array();

    $head[$h][0] = dol_buildpath('/grh/medical/aparielres/index.php?daId='.$id,1);
    $head[$h][1] = $langs->trans("aparielres");
    $head[$h][2] = 'aparielres';

    $h++;

    $head[$h][0] = dol_buildpath('/grh/medical/aparielcir/index.php?daId='.$id,1);
    $head[$h][1] = $langs->trans("aparielcir");
    $head[$h][2] = 'aparielcir';

    $h++
    ;$head[$h][0] = dol_buildpath('/grh/medical/aparieldig/index.php?daId='.$id,1);
    $head[$h][1] = $langs->trans("aparieldig");
    $head[$h][2] = 'aparieldig';

    $h++
    ;$head[$h][0] = dol_buildpath('/grh/medical/aparielgu/index.php?daId='.$id,1);
    $head[$h][1] = $langs->trans("aparielgu");
    $head[$h][2] = 'aparielgu';

    $h++
    ;$head[$h][0] = dol_buildpath('/grh/medical/apariellm/index.php?daId='.$id,1);
    $head[$h][1] = $langs->trans("apariellm");
    $head[$h][2] = 'apariellm';

    $h++
    ;$head[$h][0] = dol_buildpath('/grh/medical/aparielend/index.php?daId='.$id,1);
    $head[$h][1] = $langs->trans("aparielend");
    $head[$h][2] = 'aparielend';
    
    // Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    // $this->tabs = array('entity:+tabname:Title:@mymodule:/mymodule/mypage.php?id=__ID__');   to add new tab
    // $this->tabs = array('entity:-tabname);                                                   to remove a tab
    complete_head_from_modules($conf,$langs,$object,$head,$h,'demacht');

    complete_head_from_modules($conf,$langs,$object,$head,$h,'demacht','remove');

    return $head;
}

?>
