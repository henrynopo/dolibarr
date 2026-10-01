<?php
/* Change Tiers
 * Copyright (C) 2018       Inovea-conseil.com     <info@inovea-conseil.com>
 */

/**
 * \file    class/actions_switchthirdparty.class.php
 * \ingroup switchthirdparty
 * \brief   ActionsChangetiers
 *
 */

require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
dol_include_once('/comm/action/class/actioncomm.class.php');

/**
 * Class ActionsSwitchThirdParty
 */
class ActionsSwitchThirdParty
{
    /**
     * @var DoliDB Database handler
     */
    private $db;

    /**
     *  Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }
    
    function doActions($parameters, &$object, &$action, $hookmanager)
    {
        
        global $langs, $user, $conf,$socid;
        require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
        $langs->load("switchthirdparty@switchthirdparty");
        $jsscript = '';
        $actions_exclude = array('create', 'modif');
        if ($action == 'switchthirdparty' && GETPOST('socid')) {

            if ($conf->global->EVENT_CHANGE_THIRDPARTY) {
                $actioncomm = new ActionComm($this->db);

                $actioncomm->type_code = 'AC_OTH_AUTO'; // Event insert into agenda automatically

                $actioncomm->socid = (!empty($object->fk_soc) ? $object->fk_soc : $object->socid);// To link to a company
                $actioncomm->contact_id = 0;

                $actioncomm->code = 'CH_tier';
                $actioncomm->label = $langs->trans("ThirdpartyChangeEvent");
                $actioncomm->datep = dol_now();
                $actioncomm->datef = $actioncomm->datep;
                $actioncomm->percentage = -1; // Not applicable
                $actioncomm->authorid = $user->id; // User saving action
                $actioncomm->userownerid = $user->id; // Owner of action
                $actioncomm->fk_element = $object->id;
                $actioncomm->elementtype = $object->element;
                $even = $actioncomm->create($user);
//                $object->fk_event = $even;

                if ($object->element == 'order_supplier'){
                    if ((int)DOL_VERSION < 14){
                    $sql = "UPDATE " . MAIN_DB_PREFIX.$object->table_element . " SET fk_soc = " . GETPOST('socid') . " WHERE rowid = " . $object->id;
                    $resql=$this->db->query($sql);
                        if ($resql)
                        {
                            $this->db->commit();
                        } else {
                            $this->db->rollback();
                            dol_print_error($this->db);
                            return 0;
                        }
                    }
                }

                if (!$object->element == 'order_supplier' && !(int)DOL_VERSION < 14){
                    $object->update($user);
                }

            }


            if ($object->element == 'invoice_supplier') {
                $object->fk_soc = GETPOST('socid');
            } else {
                $object->socid = GETPOST('socid');
            }
            $sql = "UPDATE " . MAIN_DB_PREFIX.$object->table_element . " SET fk_soc = " . GETPOST('socid') . " WHERE rowid = " . $object->id;
            $resql=$this->db->query($sql);
            if ($resql)
            {
                $this->db->commit();
            } else {
                $this->db->rollback();
                dol_print_error($this->db);
                return 0;
            }

//            if ($object->element == 'invoice_supplier') {
//                $object->fk_soc = GETPOST('socid');
//            } else {
//                $object->socid = GETPOST('socid');
//            }
//            if (method_exists($object, 'update')) {
//                if ((int)DOL_VERSION >= '7') {
//                    $object->update($user);
//                } else {
//                    $object->update();
//                }
//            } else {
//                $sql = "UPDATE " . MAIN_DB_PREFIX.$object->table_element . " SET fk_soc = " . GETPOST('socid') . " WHERE rowid = " . $object->id;
//                $resql=$this->db->query($sql);
//                if ($resql)
//                {
//                    $this->db->commit();
//                } else {
//                    $this->db->rollback();
//                    dol_print_error($this->db);
//                    return 0;
//                }
//            }
            $object->delete_linked_contact('external');

            $object->generateDocument('', $langs);
            unset($_POST['socid']);
            $socid = 0;
        }
        
        return 0;
    }

    function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager){
        require_once DOL_DOCUMENT_ROOT . '/expedition/class/expedition.class.php';
        return $this->printCommonFooter($parameters, $object, $action, $hookmanager);

    }

    function printCommonFooter($parameters, &$object, &$action, $hookmanager) {
        
        global $langs, $user, $conf;
        require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
        require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';
        require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';
        require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
        require_once DOL_DOCUMENT_ROOT . '/supplier_proposal/class/supplier_proposal.class.php';
        require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.facture.class.php';
        require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.commande.class.php';
        require_once DOL_DOCUMENT_ROOT . '/expedition/class/expedition.class.php';
        require_once DOL_DOCUMENT_ROOT . '/contrat/class/contrat.class.php';

        if ((float) DOL_VERSION >= '14') {
            require_once DOL_DOCUMENT_ROOT . '/reception/class/reception.class.php';
        }

        $idparam = 'id';
        $idextparam = 'socid';
        // Dolibarr 18+ expects the Universal Filter Syntax in select_company()/select_thirdparty_list().
        // Dolibarr 24 removed the legacy raw-SQL fallback: a filter like 'client>0' now makes
        // forgeSQLFromUniversalSearchCriteria() return an error string that is embedded into the SQL
        // and kills the page with DB_ERROR_SYNTAX.
        if ((float) DOL_VERSION >= 18) {
            $paramcompany = '(s.client:>:0)';
        } else {
            $paramcompany = 'client>0';
        }
        
        switch ($parameters['currentcontext']) {
            case 'ordercard' : 
                $object = new Commande($this->db);
                $object->fetch(GETPOST('id'));
                break;
            case 'propalcard' : 
                $object = new Propal($this->db);
                $object->fetch(GETPOST('id'));
                break;
            case 'invoicecard' : 
                $object = new Facture($this->db);
                $facid = GETPOST('facid');
                $id = GETPOST('id');
                $ref = GETPOST('ref');
                if(!empty($facid)) {
                    $object->fetch($facid);
                    $idparam = 'facid';
                } elseif (!empty($id)) {
                    $object->fetch($id);
                    $idparam = 'id';
                } elseif (!empty($ref)) {
                    $object->fetch('', $ref);
                    $ref = $object->id;
                    $idparam = 'ref';
                }
                break;
            case 'supplier_proposalcard' : 
                $object = new SupplierProposal($this->db);
                $object->fetch(GETPOST('id'));
                $paramcompany = ((float) DOL_VERSION >= 18) ? '(s.fournisseur:=:1)' : 'fournisseur=1';
                break;
            case 'ordersuppliercard' : 
                $object = new CommandeFournisseur($this->db);
                $object->fetch(GETPOST('id'));
                $paramcompany = ((float) DOL_VERSION >= 18) ? '(s.fournisseur:=:1)' : 'fournisseur=1';
                break;
            case 'invoicesuppliercard' : 
                $object = new FactureFournisseur($this->db);
                $facid = GETPOST('facid');
                $id = GETPOST('id');
                if(!empty($facid)) {
                    $object->fetch($facid);
                    $idparam = 'facid';
                } elseif (!empty($id)) {
                    $object->fetch($id);
                    $idparam = 'id';
                }
                $paramcompany = ((float) DOL_VERSION >= 18) ? '(s.fournisseur:=:1)' : 'fournisseur=1';
                break;
            case 'expeditioncard' :
                $object = new Expedition($this->db);
                $object->fetch(GETPOST('id'));
                $idparam = 'id';
                break;
            case 'contractcard':
                $object = new Contrat($this->db);
                $object->fetch(GETPOST('id'));
                break;
            case 'receptioncard':
                $object = new Reception($this->db);
                $object->fetch(GETPOST('id'));
                $paramcompany = ((float) DOL_VERSION >= 18) ? '(s.fournisseur:=:1)' : 'fournisseur=1';
                break;
        }
        
        $jsscript = '';
        
        $actions_exclude = array('create', 'modif');
        $element_authorized = array();
        // Null-safe: $user->rights->switchthirdparty may be null if module perms not loaded
        $changetiersRights = (isset($user->rights->switchthirdparty) && is_object($user->rights->switchthirdparty)) ? $user->rights->switchthirdparty : null;

        if ($changetiersRights && !empty($changetiersRights->proposal)) {
            $element_authorized[] = 'propal';
        }
        if ($changetiersRights && !empty($changetiersRights->order)) {
            $element_authorized[] = 'commande';
        }
        if ($changetiersRights && !empty($changetiersRights->expedition)) {
            $element_authorized[] = 'expedition';
            $element_authorized[] = 'shipping';
        }
        if ($changetiersRights && !empty($changetiersRights->contract)) {
            $element_authorized[] = 'contrat';
        }
        if ($changetiersRights && !empty($changetiersRights->invoice) && ($object->status == $object::STATUS_DRAFT || !empty($conf->global->FACTURE_CHANGE_THIRDPARTY_NOT_ONLY_DRAFT)) && (!empty($facid) || !empty($ref) || !empty($id))) { // Lock modification only in draft mode
            $element_authorized[] = 'facture';
        }
        if ($changetiersRights && !empty($changetiersRights->supplier_proposal)) {
            $element_authorized[] = 'supplier_proposal';
        }
        if ($changetiersRights && !empty($changetiersRights->supplier_order)) {
            $element_authorized[] = 'order_supplier';
        }
        if ($changetiersRights && !empty($changetiersRights->reception) && (float) DOL_VERSION >= '14') {
            $element_authorized[] = 'reception';
        }
        if ($changetiersRights && !empty($changetiersRights->supplier_invoice)) {
            $element_authorized[] = 'invoice_supplier';
        }

        if (($action == ''
            || !in_array($action, $actions_exclude))
            && (in_array($object->element, $element_authorized) && $conf->global->{strtoupper($object->element).'_CHANGE_THIRDPARTY'})
        ) {
            $jsscript .= '<script>';
            $form = new Form($this->db);

            // Select changement tiers
            $formtiers = '<form method="post" action="'.$_SERVER['PHP_SELF'] . '?'.$idparam.'=' . GETPOST($idparam).'">' . PHP_EOL;
            $formtiers .=  '<input type="hidden" name="action" value="changetiers">' . PHP_EOL;
            $formtiers .=  '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">' . PHP_EOL;
            $formtiers .=  $form->select_company($object->{$idextparam}, 'socid', $paramcompany) . PHP_EOL;
            $formtiers .=  '<input type="submit" class="button valignmiddle" value="'.$langs->trans("Modify").'">' . PHP_EOL;
            $formtiers .=  '<input type="submit" name="cancel" class="button valignmiddle" id="changetierscancelbtn" value="'.$langs->trans("Cancel").'">' . PHP_EOL;
            $formtiers .=  '</form>' . PHP_EOL;

            $matches = null;
            $returnValue = preg_match_all('#<script(.*?)>(.*?)</script>#is', $formtiers, $matches);

            $jsscript .= 'var urlConf = "' . $_SERVER['PHP_SELF'] . '";' . PHP_EOL;

            $jsscript .= 'var changeTiers = true;' . PHP_EOL;
            if((int) DOL_VERSION > 7) {
                if ((float) getDolGlobalString('EASYA_VERSION', '0') >= 2022.5) {
                    $jsscript .= 'var pictoChangeTiers = "<span class=\''.$conf->global->MAIN_FONTAWESOME_ICON_STYLE.' fa-pencil-alt valignmiddle\' style=\'color: #444; font-size: 1em; margin-left:5px !important;\' alt=\'Modifier\' title=\'Modifier\'></span>";' . PHP_EOL;
                } else {
                    $jsscript .= 'var pictoChangeTiers = "<span class=\'fa fa-pencil-alt marginleftonly pictoedit\' style=\'color: #444; font-size: 1em; margin-left:5px !important;\' alt=\'Modifier\' title=\'Modifier\'></span>";' . PHP_EOL;
                }
            } else {
                $jsscript .= 'var pictoChangeTiers = "<img class=\'valigntextbottom\' src=\''.DOL_URL_ROOT.'/theme/eldy/img/edit.png\'>";' . PHP_EOL;
            }
            $jsscript .=   "var formTiers = `" . PHP_EOL
                    .preg_replace('#<script(.*?)>(.*?)</script>#is', '', $formtiers).PHP_EOL
                    . " ` ;" . PHP_EOL;



            if (isset($matches[2]) && isset($matches[2][0])) {
                $jsscript .=   "var scriptTiers = `" . PHP_EOL
                        .$matches[2][0].PHP_EOL
                        . " ` ;" . PHP_EOL;
            }
            
            $jsscript .= '</script>';
        
        }
        
        echo $jsscript;
        return 0;
    }
}
