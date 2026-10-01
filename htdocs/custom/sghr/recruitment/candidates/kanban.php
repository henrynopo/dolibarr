<?php
$res=0;
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // sghr subdir depth
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php"); // sghr sub-subdir depth
if (! $res && file_exists("../../../../../main.inc.php")) $res=@include("../../../../../main.inc.php"); // sghr deeper
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/sghr/recruitment/class/JobPosition.class.php');
dol_include_once('/sghr/recruitment/class/Candidate.class.php');
dol_include_once('/sghr/recruitment/class/Tag.class.php');
dol_include_once('/sghr/recruitment/class/CandidateStage.class.php');
dol_include_once('/sghr/recruitment/lib/recrutement.lib.php');
dol_include_once('/core/class/html.form.class.php');

require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';


$modname = $langs->trans("Candidate");

$langs->load('recruitment@sghr');

$etapes         = new CandidateStage($db);
$Candidate   = new Candidate($db);
$candidatures2  = new Candidate($db);
$employe        = new User($db);
$etiquette      = new Tag($db);
$JobPosition         = new JobPosition($db);
$selectyear         = GETPOST('selectyear');

if (!empty($selectyear) && $selectyear != -1 ) {
  $filter .= " AND YEAR(date_depot) = '".$selectyear."'";
}
elseif($selectyear == -1){
  $filter.='';
}

$gridorlist = '';
if(isset($_GET['gridorlist'])){
  if ($_GET['gridorlist'] == "GRID") {
    $res = dolibarr_set_const($db, 'RECRUTEMENT_OPTION_CHOOSE_GRIDORLIST', 'GRID', 'chaine', 0, '', $conf->entity);
    $gridorlist = "GRID";
  } else {
    $res = dolibarr_set_const($db, 'RECRUTEMENT_OPTION_CHOOSE_GRIDORLIST', 'LIST', 'chaine', 0, '', $conf->entity);
    $gridorlist = "LIST";
  } 
}else{
  if(dolibarr_get_const($db,'RECRUTEMENT_OPTION_CHOOSE_GRIDORLIST',$conf->entity))
    $gridorlist = dolibarr_get_const($db,'RECRUTEMENT_OPTION_CHOOSE_GRIDORLIST',$conf->entity);
}

if ($gridorlist == "LIST"){
    header('Location: '.dol_buildpath('/sghr/recruitment/candidates/index.php',2));
}

$limit  = $conf->liste_limit+1;
$page   = GETPOST("page",'int');
$page = is_numeric($page) ? $page : 0;
$page = $page == -1 ? 0 : $page;
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

$nbrtotal = $Candidate->fetchAll($sortorder, $sortfield, $limit, $offset, $filter);
$nbrtotalnofiltr = $candidatures2->fetchAll();


$arretiquette = $etiquette->getEtiquetteByRowid();

// print_r($arretiquette);

$morejs  = array();
llxHeader(array(), $modname,'','','','',$morejs,0,0);

print_barre_liste($modname, $page, $_SERVER["PHP_SELF"], "", $sortfield, $sortorder, "", $nbrtotal, $nbrtotalnofiltr);
// print_barre_liste($modname, $page, $_SERVER["PHP_SELF"], "", $sortfield, $sortorder, "", $nbrtotal, $nbrtotalnofiltr,'',0,'','',$limit);


// print '<link rel="stylesheet" href="https://www.jqwidgets.com/jquery-widgets-documentation/jqwidgts/styles/jqx.base.css" type="text/css" />';
print '<link rel="stylesheet" href="'.dol_buildpath('/sghr/recruitment/candidates/css/kanban.css',2).'" type="text/css" />';
print '<script type="text/javascript" src="'.dol_buildpath('/sghr/recruitment/candidates/js/jqxcore.js',2).'"></script>';
print '<script type="text/javascript" src="'.dol_buildpath('/sghr/recruitment/candidates/js/jqxsortable.js',2).'"></script>';
print '<script type="text/javascript" src="'.dol_buildpath('/sghr/recruitment/candidates/js/jqxkanban.js',2).'"></script>';
print '<script type="text/javascript" src="'.dol_buildpath('/sghr/recruitment/candidates/js/jqxdata.js',2).'"></script>';

print '<form method="get" action="'.$_SERVER["PHP_SELF"].'" class="kanban_recrut">'."\n";
  print '<div style="float: left; margin-bottom: 8px; width:100%;">';
      print '<div style="width:10%; float:left;" >';
          print '<a class="icon_list" data-type="list" href="'.dol_buildpath('/sghr/recruitment/candidates/index.php?gridorlist=LIST',2).'"> <img  src="'.dol_buildpath('/sghr/recruitment/img/list.png',2).'" style="height:30px" id="list" ></a>';
          print '<a class="icon_list" data-type="grid" href="'.dol_buildpath('/sghr/recruitment/candidates/kanban.php?gridorlist=GRID',2).'"> <img src="'.dol_buildpath('/sghr/recruitment/img/grip.png',2).'" style="height:30px" id="grid" ></a> ';
      print '</div>';

      print '<div class="statusdetailcolorsback" style="">';
          $etapes->fetchAll();
          $arr_etapes=[];
          for ($i=0; $i <count($etapes->rows); $i++) { 
            $etape=$etapes->rows[$i];
            $arr_etapes[$etape->rowid]=0;
            for ($j=0; $j < count($Candidate->rows) ; $j++) { 
              $candidat=$Candidate->rows[$j];
              if($candidat->etape == $etape->rowid){ $arr_etapes[$etape->rowid]++; };
            }
              print '<span class="statusname STATUSPROPAL_0">';
                print '<span class="colorstatus" style="background:'.$etape->color.';"></span>';
                print '<span class="labelstatus"><span class="counteleme">'.$arr_etapes[$etape->rowid].'</span></span>&nbsp';
                print $langs->trans($etape->label);
              print '</span>';
          }
          // print_r($arr_etapes);die();
      print '</div>';

       print '<div style="width:20%; float:right;" >';
          print '<a href="card.php?action=add" class="butAction" id="add" >'.$langs->trans("Add").'</a>';
      print '</div>';
  print '</div>';

  print '<div style="width:100%; float:left" >';
      print'<select style="float:left;" id="selectyear" name="selectyear">';
        $years = $Candidate->getYears("date_depot");
        // die($selectyear);
        print'<option value="-1" >'.$langs->trans("Toutes").'</option>';
        krsort($years);
        foreach ($years as $key => $value) {
          $slctd2="";
          if($key == $selectyear){
            $slctd2="selected";
          }
          print'<option value="'.$key.'" '.$slctd2.'>'.$key.'</option>';
        }
      print'</select>';
      print '<input type="image" name="button_search" src="'.img_picto($langs->trans("Search"),'search.png','','',1).'" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
  print '</div>';
print '</form>';
print '<div style="clear:both;"></div>';
print '<div id="kanban"></div>';
?>

<script>
  $(document).ready(function () {

            var fields = [
                { name: "id", type: "string" },
                { name: "status", map: "state", type: "string" },
                { name: "text", map: "label", type: "string" },
                { name: "tags", type: "string" },
                { name: "color", map: "hex", type: "string" },
                { name: "resourceId", type: "number" }
            ];
            var source =
             {
                 localData: [
                    <?php
                      $etapes->fetchAll();
                      $nb=count($etapes->rows);
                      $countrec = 0;
                      for ($i=0; $i < $nb; $i++) {
                          $etape=$etapes->rows[$i] ;
                          // $Candidate->fetchAll('','',0,0,'AND etape ='.$etape->rowid);
                          $nb_c=count($Candidate->rows);
                          for ($j=0; $j < $nb_c; $j++) { 
                              $candidature=$Candidate->rows[$j];

                              $tag='';
                              if($candidature->etape == $etape->rowid ){
                                $Tag=explode(",", $candidature->Tag);
                                $etiqarr = array();
                                if($Tag){
                                  foreach($Tag as $key){
                                      $tag .= $langs->trans($arretiquette[$key]['label']).":".$arretiquette[$key]['color'].",";
                                  }
                                }
                                $tag = trim($tag,",");
                                // $sujet =  trim(preg_replace('/\s+/', ' ', (addslashes($candidature->sujet))));
                                $sujet =  trim(preg_replace('/\s+/', ' ', (addslashes($candidature->nom.' '.$candidature->prenom))));
                                $email =  trim(preg_replace('/\s+/', ' ', (addslashes($candidature->email))));
                                $JobPosition->fetch($candidature->poste);
                                $poste =  trim(preg_replace('/\s+/', ' ', (addslashes($JobPosition->label))));

                                print '{ id: "'.$candidature->rowid.'", state: "etape_'.$etape->rowid.'", label: "<b>'.$sujet.'</b><br><span class=\'poste\'>'.$poste.'</span><br><span class=\'email\'>'.$email.'</span>", tags: "'.$tag.'", hex: "'.$etape->color.'", resourceId: '.$candidature->rowid.' },';
                                $countrec++;

                              }
                          }

                      }
                    ?>
                 ],
                 dataType: "array",
                 dataFields: fields
             };
            var dataAdapter = new $.jqx.dataAdapter(source);
            var resourcesAdapterFunc = function () {
                var resourcesSource =
                {
                    localData: [
                        <?php
                            $Candidate->fetchAll('','',0,0,'');
                            $nb_c=count($Candidate->rows);
                            for ($j=0; $j < $nb_c; $j++) { 
                              $candidature=$Candidate->rows[$j];
                              if($candidature->responsable){
                                  $user_ = new User($db);
                                  $user_->fetch($candidature->responsable);
                                  // print $contact->firstname.' '.$contact->lastname;
                                  $name = $langs->trans('responsable_recut').' : \n';
                                  $name .= $langs->trans("Name").' : '.$user_->firstname.' '.$user_->lastname ;
                                  if (! empty($user_->login))
                                      $name.= '('.$user_->login.') \n';
                                  else 
                                      $name .= '\n';
                                  if (! empty($user_->email))
                                      $name.= $langs->trans("EMail").': '.$user_->email;
                                  if (! empty($user_->admin))
                                      $name .= '\n'.$langs->trans("Administrator").' : '.yn($user_->admin).'\n';
                                  $src = DOL_URL_ROOT.'/viewimage.php?modulepart=userphoto&entity='.$conf->entity.'&file='.$candidature->responsable.'/'.$user_->photo.'&perm=download';
                                  print '{ id: "'.$candidature->rowid.'", name: "'.html_entity_decode($name).'", image: "'.$src.'", common: true },';
                              }else{
                                  print '{ id: "'.$candidature->rowid.'", name: "'.$langs->trans("aucun_resp").'", image: "'.dol_buildpath('/sghr/recruitment/img/user.png',2).'", common: true },';
                              }
                          }
                        ?>
                          
                    ],
                    dataType: "array",
                    dataFields: [
                         { name: "id", type: "number" },
                         { name: "name", type: "string" },
                         { name: "image", type: "string" },
                         { name: "common", type: "boolean" }
                    ]
                };
                    console.log(resourcesSource);
                var resourcesDataAdapter = new $.jqx.dataAdapter(resourcesSource);
                return resourcesDataAdapter;
            }

            $('#kanban').jqxKanban({
                resources: resourcesAdapterFunc(),
                source: dataAdapter,
                height: 590,
                columns: [
                    <?php 
                        // $etapes->fetchAll();
                        $nb=count($etapes->rows);
                        for ($i=0; $i < $nb; $i++) { 
                            $etape=$etapes->rows[$i];
                            print '{ text: "'.preg_replace( "/\r|\n/", "", $langs->trans($etape->label) ).'", dataField: "etape_'.$etape->rowid.'" },';
                        }
                    ?>
                ]
            });

            var log = new Array();
            var updateLog = function () {
                var count = 0;
                var str = "";
                for (var i = log.length - 1; i >= 0; i--) {
                    str += log[i] + "<br/>";
                    count++;
                    if (count > 10)
                        break;
                }
                $("#log").html(str);
            }

            $('#kanban').on('itemMoved', function (event) {
                var args = event.args;
                var itemId = args.itemId;
                var oldParentId = args.oldParentId;
                var newParentId = args.newParentId;
                var itemData = args.itemData;
                var oldColumn = args.oldColumn;
                var newColumn = args.newColumn;
               
                $id_candidat=itemId;
                $id_old=oldColumn['dataField'].split('_');
                $id_new=newColumn['dataField'].split('_');
                $id_old=$id_old[1];
                $id_new=$id_new[1];
                  $.ajax({
                    data:{'id_candidat':$id_candidat,'id_etat':$id_new},
                    url:"<?php echo dol_buildpath('/sghr/recruitment/candidates/info_contact.php?action_=change_etat',2); ?>",
                    type:'POST',
                    dataType: "json",
                    success:function($data){
                        $('#kanban_'+$data['id_candidat']).find('.jqx-kanban-item-color-status').css('background-color','5px solid '+$data['color']);
                    }
                  });

                log.push("itemMoved is raised");
                updateLog();
            });
            $('#kanban').on('columnCollapsed', function (event) {
                var args = event.args;
                var column = args.column;
                // log.push("columnCollapsed is raised");
                updateLog();
            });
            $('#kanban').on('columnExpanded', function (event) {
                var args = event.args;
                var column = args.column;
                log.push("columnExpanded is raised");
                updateLog();
            });
            $('#kanban').on('itemAttrClicked', function (event) {
              console.log(event);
                var args = event.args;
                var itemId = args.itemId;
                var attribute = args.attribute; // template, colorStatus, content, keyword, text, avatar
                log.push("itemAttrClicked is raised");
                updateLog();
            });
            $('#kanban').on('columnAttrClicked', function (event) {
                var args = event.args;
                var column = args.column;
                var cancelToggle = args.cancelToggle; // false by default. Set to true to cancel toggling dynamically.
                var attribute = args.attribute; // title, button
                log.push("columnAttrClicked is raised");
                updateLog();
            });



            $('.jqx-kanban-item-text').find('b').click(function(){
                $id=$(this).parent().parent().attr('id');
                $id=$id.split('_');
                location.href="<?php echo dol_buildpath('/sghr/recruitment/candidates/card.php?id=',2)?>"+$id[1];

                // location.href=$('#show_8').attr('href');
            });

        });
</script>

<?php
llxFooter();
if (is_object($db)) $db->close();
?>