<?php
$res=0;
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // sghr subdir depth
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php"); // sghr sub-subdir depth
if (! $res && file_exists("../../../../../main.inc.php")) $res=@include("../../../../../main.inc.php"); // sghr deeper
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 


dol_include_once('/sghr/recruitment/class/JobPosition.class.php');
dol_include_once('/sghr/recruitment/class/Candidate.class.php');
dol_include_once('/sghr/recruitment/class/Tag.class.php');
dol_include_once('/sghr/recruitment/class/CandidateStage.class.php');
dol_include_once('/sghr/recruitment/lib/recrutement.lib.php');
dol_include_once('/core/class/html.form.class.php');


$langs->load('recruitment@sghr');

$modname = $langs->trans("Candidate");
$etapes = new CandidateStage($db);
$Candidate = new Candidate($db);
$employe = new User($db);
$etiquette = new Tag($db);


$morejs  = array();
llxHeader(array(), $modname,'','','','',$morejs,0,0);

print_fiche_titre($modname);


print '<link rel="stylesheet" href="https://www.jqwidgets.com/jquery-widgets-documentation/jqwidgets/styles/jqx.base.css" type="text/css" />';
// print '<script type="text/javascript" src="https://www.jqwidgets.com/jquery-widgets-documentation/scripts/jquery-1.11.1.min.js"></script>';
// print '<script type="text/javascript" src="https://www.jqwidgets.com/jquery-widgets-documentation/jqwidgets/jqxcore.js"></script>';
// print '<script type="text/javascript" src="https://www.jqwidgets.com/jquery-widgets-documentation/jqwidgets/jqxsortable.js"></script>';
// print '<script type="text/javascript" src="https://www.jqwidgets.com/jquery-widgets-documentation/jqwidgets/jqxkanban.js"></script>';
// print '<script type="text/javascript" src="https://www.jqwidgets.com/jquery-widgets-documentation/jqwidgets/jqxdata.js"></script>';

print '<script type="text/javascript" src="'.dol_buildpath('/sghr/recruitment/candidates/js/jqxcore.js',2).'"></script>';
print '<script type="text/javascript" src="'.dol_buildpath('/sghr/recruitment/candidates/js/jqxsortable.js',2).'"></script>';
print '<script type="text/javascript" src="'.dol_buildpath('/sghr/recruitment/candidates/js/jqxkanban.js',2).'"></script>';
print '<script type="text/javascript" src="'.dol_buildpath('/sghr/recruitment/candidates/js/jqxdata.js',2).'"></script>';

print '<div style="float: left; margin-bottom: 8px; width:100%;">';
  print '<div  style="" >';
    print '<a class="icon_list" data-type="list" href="'.dol_buildpath('/sghr/recruitment/candidates/index.php',2).'"> <img  src="'.dol_buildpath('/sghr/recruitment/img/list.png',2).'" style="height:30px" id="list" ></a>';
    print '<a class="icon_list" data-type="grid"> <img src="'.dol_buildpath('/sghr/recruitment/img/grip.png',2).'" style="height:30px" id="grid" ></a> ';
    print '<a href="card.php?action=add" class="butAction" id="add" >'.$langs->trans("Add").'</a>';

  print '</div>';
print '</div>';

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
                      for ($i=0; $i < $nb; $i++) {
                          $etape=$etapes->rows[$i] ;
                          $Candidate->fetchAll('','',0,0,'AND etape ='.$etape->rowid);
                          $nb_c=count($Candidate->rows);
                          for ($j=0; $j < $nb_c; $j++) { 
                          $tag='';
                              $candidature=$Candidate->rows[$j];
                              $Tag=json_decode($candidature->Tag);
                              foreach($Tag as $key){
                                  $etiquette->fetch($key);
                                  $tag.=$etiquette->label.', ';
                              }
                              print '{ id: "'.$candidature->rowid.'", state: "'.$etape->rowid.'", label: "<b>'.$candidature->sujet.'</b><br>'.$candidature->email.'", tags: "'.$tag.'", hex: "'.$etape->color.'", resourceId: '.$candidature->rowid.' },';
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
                        $Candidate->fetchAll('','',0,0,'AND employe != 0 AND employe IS NOT NULL');
                          $nb_c=count($Candidate->rows);
                          for ($j=0; $j < $nb_c; $j++) { 
                              $candidature=$Candidate->rows[$j];
                              $employe->fetch($candidature->employe);
                              print '{ id: '.$candidature->rowid.', name: "'.$employe->firstname.' '.$employe->lastname.'", image: "'.dol_buildpath('/sghr/recruitment/img/user.png',2).'", common: true },';
                          }
                        ?>
                          // { id: 1, name: "Andrew Fuller", image: "https://www.jqwidgets.com/jquery-widgets-documentation/images/andrew.png" },
                          // { id: 2, name: "Janet Leverling", image: "https://www.jqwidgets.com/jquery-widgets-documentation/images/janet.png" },
                          // { id: 3, name: "Steven Buchanan", image: "https://www.jqwidgets.com/jquery-widgets-documentation/images/steven.png" },
                          // { id: 4, name: "Nancy Davolio", image: "https://www.jqwidgets.com/jquery-widgets-documentation/images/nancy.png" },
                          // { id: 5, name: "Michael Buchanan", image: "https://www.jqwidgets.com/jquery-widgets-documentation/images/Michael.png" },
                          // { id: 6, name: "Margaret Buchanan", image: "https://www.jqwidgets.com/jquery-widgets-documentation/images/margaret.png" },
                          // { id: 7, name: "Robert Buchanan", image: "https://www.jqwidgets.com/jquery-widgets-documentation/images/robert.png" },
                          // { id: 8, name: "Laura Buchanan", image: "https://www.jqwidgets.com/jquery-widgets-documentation/images/Laura.png" },
                          // { id: 9, name: "Laura Buchanan", image: "https://www.jqwidgets.com/jquery-widgets-documentation/images/Anne.png" }
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
                columns: [
                    <?php 
                        $etapes->fetchAll();
                        $nb=count($etapes->rows);
                        for ($i=0; $i < $nb; $i++) { 
                            $etape=$etapes->rows[$i];
                            print '{ text: "'.$langs->trans($etape->label).'", dataField: "'.$etape->rowid.'" },';
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
                console.log(itemId);
                // console.log(oldParentId);
                // console.log(newParentId);
                // console.log(oldColumn);
                // console.log(newColumn);
                $id_candidat=itemId;
                $id_old=oldColumn['dataField'];
                $id_new=newColumn['dataField'];

                 $.ajax({
                    data:{'id_candidat':$id_candidat,'id_etat':$id_new},
                    url:"<?php echo dol_buildpath('/sghr/recruitment/candidates/info_contact.php?action_=change_etat',2) ?>",
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
                log.push("columnCollapsed is raised");
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
        });
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
</style>

<!-- <script>
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
                          { id: "1161", state: "new", label: "Combine Orders", tags: "orders, combine", hex: "#5dc3f0", resourceId: 3 },
                          { id: "1645", state: "work", label: "Change Billing Address", tags: "billing", hex: "#f19b60", resourceId: 1 },
                          { id: "9213", state: "new", label: "One item added to the cart", tags: "cart", hex: "#5dc3f0", resourceId: 3 },
                          { id: "6546", state: "done", label: "Edit Item Price", tags: "price, edit", hex: "#5dc3f0", resourceId: 4 },
                          { id: "9034", state: "new", label: "Login 404 issue", tags: "issue, login", hex: "#6bbd49" }
                 ],
                 dataType: "array",
                 dataFields: fields
             };
            var dataAdapter = new $.jqx.dataAdapter(source);
            var resourcesAdapterFunc = function () {
                var resourcesSource =
                {
                    localData: [
                          { id: 0, name: "No name", image: "../../jqwidgets/styles/images/common.png", common: true },
                          { id: 1, name: "Andrew Fuller", image: "../../images/andrew.png" },
                          { id: 2, name: "Janet Leverling", image: "../../images/janet.png" },
                          { id: 3, name: "Steven Buchanan", image: "../../images/steven.png" },
                          { id: 4, name: "Nancy Davolio", image: "../../images/nancy.png" },
                          { id: 5, name: "Michael Buchanan", image: "../../images/Michael.png" },
                          { id: 6, name: "Margaret Buchanan", image: "../../images/margaret.png" },
                          { id: 7, name: "Robert Buchanan", image: "../../images/robert.png" },
                          { id: 8, name: "Laura Buchanan", image: "../../images/Laura.png" },
                          { id: 9, name: "Laura Buchanan", image: "../../images/Anne.png" }
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
            $('#kanban').jqxKanban({
                width: getWidth('kanban'),
                resources: resourcesAdapterFunc(),
                source: dataAdapter,
                columns: [
                    { text: "Backlog", dataField: "new" },
                    { text: "In Progress", dataField: "work" },
                    { text: "Done", dataField: "done" }
                ]
            });
            $('#kanban').on('itemMoved', function (event) {
                var args = event.args;
                var itemId = args.itemId;
                var oldParentId = args.oldParentId;
                var newParentId = args.newParentId;
                var itemData = args.itemData;
                var oldColumn = args.oldColumn;
                var newColumn = args.newColumn;
                log.push("itemMoved is raised");
                updateLog();
            });
            $('#kanban').on('columnCollapsed', function (event) {
                var args = event.args;
                var column = args.column;
                log.push("columnCollapsed is raised");
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
</script> -->
<?php
llxFooter();
if (is_object($db)) $db->close();
?>