<?php
$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 


dol_include_once('/recrutement/class/postes.class.php');
dol_include_once('/recrutement/class/candidatures.class.php');
dol_include_once('/recrutement/class/etiquettes.class.php');
dol_include_once('/recrutement/class/etapescandidature.class.php');
dol_include_once('/recrutement/lib/recrutement.lib.php');
dol_include_once('/core/class/html.form.class.php');


$langs->load('recrutement@recrutement');

$modname = $langs->trans("candidatures");
$etapes = new etapescandidature($db);
$candidatures = new candidatures($db);
$candidatures2 = new candidatures($db);
$employe = new User($db);
$etiquette = new etiquettes($db);


$limit  = $conf->liste_limit+1;
$page   = GETPOST("page",'int');
$page = is_numeric($page) ? $page : 0;
$page = $page == -1 ? 0 : $page;
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

$nbrtotal = $candidatures->fetchAll($sortorder, $sortfield, $limit, $offset, $filter);
$nbrtotalnofiltr = $candidatures2->fetchAll();


$morejs  = array();
llxHeader(array(), $modname,'','','','',$morejs,0,0);

print_barre_liste($modname, $page, $_SERVER["PHP_SELF"], "", $sortfield, $sortorder, "", $nbrtotal, $nbrtotalnofiltr);
// print_barre_liste($modname, $page, $_SERVER["PHP_SELF"], "", $sortfield, $sortorder, "", $nbrtotal, $nbrtotalnofiltr,'',0,'','',$limit);


// print '<link rel="stylesheet" href="https://www.jqwidgets.com/jquery-widgets-documentation/jqwidgts/styles/jqx.base.css" type="text/css" />';
print '<link rel="stylesheet" href="'.dol_buildpath('/recrutement/candidatures/css/kanban.css',2).'" type="text/css" />';

print '<script type="text/javascript" src="'.dol_buildpath('recrutement/candidatures/js/jqxcore.js',2).'"></script>';
print '<script type="text/javascript" src="'.dol_buildpath('recrutement/candidatures/js/jqxsortable.js',2).'"></script>';
print '<script type="text/javascript" src="'.dol_buildpath('recrutement/candidatures/js/jqxkanban.js',2).'"></script>';
print '<script type="text/javascript" src="'.dol_buildpath('recrutement/candidatures/js/jqxdata.js',2).'"></script>';

print '<div class="div_h">';

  print '<div style="float: left; margin-bottom: 8px; width:10%;">';
      print '<a class="icon_list" data-type="list" href="'.dol_buildpath('recrutement/candidatures/index.php',2).'"> <img  src="'.dol_buildpath('recrutement/img/list.png',2).'" style="height:30px" id="list" ></a>';
      print '<a class="icon_list" data-type="grid"> <img src="'.dol_buildpath('recrutement/img/grip.png',2).'" style="height:30px" id="grid" ></a> ';
  print '</div>';

  print '<div class="statusdetailcolorsback" style="display: block;">';
      $etapes->fetchAll();
      for ($i=0; $i <count($etapes->rows); $i++) { 
        $etape=$etapes->rows[$i];
        $candidat =  new candidatures($db);
        $candidat->fetchAll('','',0,0,' AND etape ='.$etape->rowid);
        $nb=count($candidat->rows);
        print '<span class="statusname STATUSPROPAL_0">';
          print '<span class="colorstatus" style="background:'.$etape->color.';"></span>';
          print $etape->label;
          print '<span class="labelstatus"><span class="counteleme">'.$nb.'</span></span>';
        print '</span>';
      }
  print '</div>';


  print '<div style="float: left; margin-bottom: 8px; width:20%">';
      print '<a href="card.php?action=add" class="butAction" id="add" >'.$langs->trans("Add").'</a>';
  print '</div>';

print '</div>';

print '<div id="kanban"></div>';
?>

<script>
  $(document).ready(function () {
            // var color_tag = [

                   
            // ];
            // console.log(color_tag);
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
                      for ($i=0; $i < $nb; $i++) {
                          $etape=$etapes->rows[$i] ;
                          $candidatures->fetchAll('','',0,0,'AND etape ='.$etape->rowid);
                          $nb_c=count($candidatures->rows);
                          for ($j=0; $j < $nb_c; $j++) { 
                          $tag='';
                              $candidature=$candidatures->rows[$j];
                              // $etiquettes=json_decode($candidature->etiquettes);
                              $etiquettes=explode(",", $candidature->etiquettes);
                              foreach($etiquettes as $key){
                                  $etiquette->fetch($key);
                                  if($tag == ''){
                                    $tag.=$etiquette->label;
                                  }else{
                                    $tag.=','.$etiquette->label;
                                  }
                              }
                              print '{ id: "'.$candidature->rowid.'", state: "etape_'.$etape->rowid.'", label: "<b data-id='.$candidature->rowid.' >'.$candidature->sujet.'</b><br>'.$candidature->email.'", tags: "'.$tag.'", hex: "'.$etape->color.'", resourceId: '.$candidature->rowid.' },';
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
                        $candidatures->fetchAll('','',0,0,'');
                          $nb_c=count($candidatures->rows);
                          for ($j=0; $j < $nb_c; $j++) { 
                              $candidature=$candidatures->rows[$j];
                              $employe->fetch($candidature->employe);
                              print '{ id: '.$candidature->rowid.', name: "'.$employe->firstname.' '.$employe->lastname.'", image: "'.dol_buildpath('/recrutement/img/user.png',2).'", common: true },';
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
                var resourcesDataAdapter = new $.jqx.dataAdapter(resourcesSource);
                return resourcesDataAdapter;
            }
            $('#kanban').jqxKanban({
                resources: resourcesAdapterFunc(),
                source: dataAdapter,
                height: 600,
                columns: [
                    <?php 
                        $etapes->fetchAll();
                        $nb=count($etapes->rows);
                        for ($i=0; $i < $nb; $i++) { 
                            $etape=$etapes->rows[$i];
                            print '{ text: "'.$langs->trans($etape->label).'", dataField: "etape_'.$etape->rowid.'" },';
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
            // $('#kanban').jqxKanban({
            //     width: getWidth('kanban'),
            //     resources: resourcesAdapterFunc(),
            //     source: dataAdapter,
            //     columns: [
            //         { text: "Backlog", dataField: "new" },
            //         { text: "In Progress", dataField: "work" },
            //         { text: "Done", dataField: "done" }
            //     ]
            // });
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
                    url:"<?php echo dol_buildpath('/recrutement/candidatures/info_contact.php?action_=change_etat',2); ?>",
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
            <?php
                $etiquette->fetchAll();
                $nb_tag=count($etiquette->rows);
                $candidatures->fetchAll();
                for ($j=0; $j < count($candidatures->rows); $j++) { 
                    $etape=$candidatures->rows[$j];
                    ?>

                    $('#kanban_<?php echo $etape->rowid ?>').find('.jqx-kanban-item-text').append('<a id="show_<?php echo $etape->rowid ?>" href="<?php echo dol_buildpath('/recrutement/candidatures/card.php?id='.$etape->rowid,2)?>" style="display:none"></a>');

                    $('div#kanban_<?php echo $etape->rowid ?>').find('.jqx-kanban-item-footer').find('.jqx-kanban-item-keyword').each(function(){
                        <?php 
                        for ($i=0; $i < $nb_tag; $i++) { 
                          $tag=$etiquette->rows[$i];
                          ?>
                          if($(this).text() == '<?php echo $tag->label ?>'){
                            $(this).css('background-color','<?php echo $tag->color ?>')
                            $(this).css('color','white');
                            $(this).css('border','0px');
                          }
                        <?php
                        }
                        ?>
                    });

                    $('#kanban_<?php echo $etape->rowid ?> .jqx-kanban-item-text').find('b').click(function(){
                        location.href=$('#show_<?php echo $etape->rowid ?>').attr('href');
                    });

                <?php
                }
            ?>
        });
        function show(id) {
            $id=$(id).data('id');
            console.log($('#show_'+$id).attr('href'));
            
            // $('#show_'+$id).href('click');
        }
</script>

<style>
  .jqx-kanban-column{
    /*width: 20% !important;*/
  }
  #kanban{
    width: 100% !important;
  }
  #add{
    float: right;
  }
  #grid{
    background-color:rgba(0, 0, 0, 0.15);
  }
  .jqx-kanban-column-container .jqx-widget-content .jqx-widget .jqx-sortable .jqx-disableselect{
        background-color: gainsboro !important;
  }
  a:hover {
     text-decoration: none; 
     color: black; 
     cursor: pointer;
  }
  .statusdetailcolorsback{
    float:left;
    width: 70%;
      text-align: center;
      display: none;
  }
  .statusdetailcolorsback .statusname {
      line-height: 15px;
      padding: 0 15px;
  }
  .statusdetailcolorsback .colorstatus {
      height: 12px;
      width: 41px;
      display: inline-block;
      border: 0.1px dashed #a9a9a9;
      margin-right: 3px;
  }
  .div_h{
    width: 100%;
  }
  .statusdetailcolorsback .counteleme {
      /*margin-right: 2px;*/
      font-weight: bold;
  }
  
  .statusdetailcolorsback .statusname {
      line-height: 15px;
      padding: 0 15px;
  }
  .jqx-kanban-item-text b:hover{
    cursor: pointer;
  }
</style>


<?php
llxFooter();
if (is_object($db)) $db->close();
?>