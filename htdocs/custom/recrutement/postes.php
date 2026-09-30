<?php
$res=0;
if (! $res && file_exists("../main.inc.php")) $res=@include("../main.inc.php");       // For root directory
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php"); // For "custom" 

dol_include_once('/core/class/html.form.class.php');

dol_include_once('/recrutement/class/postes.class.php');
dol_include_once('/recrutement/class/candidatures.class.php');
dol_include_once('/recrutement/lib/recrutement.lib.php');


// print '<script src="https://cdnjs.cloudflare.com/ajax/libs/hammer.js/2.0.8/hammer.min.js"></script>';
// print '<script src="https://cdnjs.cloudflare.com/ajax/libs/web-animations/2.3.1/web-animations.min.js"></script>';
// print '<script src="https://unpkg.com/muuri@0.6.3/dist/muuri.min.js"></script>';

$langs->load('recrutement@recrutement');

$modname = $langs->trans("postes");


$form         = new Form($db);
$postes       = new postes($db);
$candidatures = new candidatures($db);


$var 				= true;
$sortfield 			= ($_GET['sortfield']) ? $_GET['sortfield'] : "rowid";
$sortorder 			= ($_GET['sortorder']) ? $_GET['sortorder'] : "DESC";
$id 				= $_GET['id'];
$action   			= $_GET['action'];

$morejs  = array();
llxHeader(array(), $modname,'','','','',$morejs,0,0);

print_fiche_titre($modname);
// $head = recrutementAdminPrepareHead($id);

// dol_fiche_head(
//         $head,
//         'postes',
//         '', 
//         0,
//         "recrutement@recrutement"
//     );

$limit 	= $conf->liste_limit+1;

$page 	= GETPOST("page",'int');
$page = is_numeric($page) ? $page : 0;
$page = $page == -1 ? 0 : $page;
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;



print '<div style="float: right; margin: 8px;">';
	print '<a href="card.php?action=add" class="butAction" >'.$langs->trans("Add").'</a><br>';
	print '<div align="center" style="margin-top:10px;">';
		print '<a class="icon_list" data-type="list"> <img  src="'.dol_buildpath('/recrutement/img/list.png',2).'" style="height:30px" ></a>';
		print '<a class="icon_list" data-type="grid"> <img src="'.dol_buildpath('/recrutement/img/grip.png',2).'" style="height:30px"></a> ';
	print '</div>';
print '</div>';

$postes->fetchAll($sortorder, $sortfield, $limit, $offset);

$nb=count($postes->rows);

print ' <div class="board" style="width:100% !important">';
for ($i=0; $i < $nb; $i++) { 
	$item=$postes->rows[$i];
   	print ' <div class="board-column todo" style="width:100% !important">';
	    // print ' <div class="board-column-header">;To do</div>';
	    print ' <div class="board-column-content-wrapper"  style="width:100% !important">';
	       	print ' <div class="board-column-content" style="width:100% !important">';
		       	print ' <div class="board-item" >';

		       		print ' <div class="board-item-content" style="margin:0 20px 20px">';
		       			print '<div class="item-content">';
			       			print '<div > <span>'.$item->label.'</span> <a href="./card.php?id='.$item->id.'&action=edit"><img align="right" src="'.DOL_MAIN_URL_ROOT.'/theme/md/img/edit.png"></a> <br><br></div>';
			       			
			       			if($item->status == "Recrutement en cours"){
			       				print '<div > <a href="'.dol_buildpath('/recrutement/candidatures/candidature.php?id_poste='.$item->id,2).'" class="button" ><b>'.$langs->trans("candidatures").'</b></a> </div>';
			       			}
			       			if($item->status == "Recrutement arrêté"){
			       				print '<a class="button" style="background-color:green !important;color:white !important;  " data-id="'.$item->rowid.'" id="lancer" >'.$langs->trans('lancer').'</a>';
			       			}
		       			print '</div>';

		       			print '<div class="bottom">';
		       				print '<div style="float:left"> <a href="./cv/index.php?poste='.$item->id.'"> <img src="'.DOL_MAIN_URL_ROOT.'/theme/eldy/img/object_dir.png"></a></div>';

		       				$candidatures->fetchAll('','',0,0,' AND poste ='.$item->rowid.' AND refuse = 0');
							$nb_candidature=count($candidatures->rows);
		       				print '<div style="text-align:right;"><span style="font-size:14px;">'.$nb_candidature.' '.$langs->trans("employe_recrute").'</span></div>';
		       			print '</div>';
		       		print '</div>';

		       	print'</div>';
	   		print ' </div>';
	    print ' </div>';
  	print '</div>';
}
print '</div>';



?>
<style>
	.candidature{
		color: rgb(255, 255, 255);
	    background-color: rgb(0, 109, 107);
	    border-color: rgb(0, 96, 94);
	}
	.board {
	  position: relative;
	  margin-left: 1%;
	}
	.board-column {
	  width: 30%;
	  border-radius: 3px;
	}
	
	.board-column.todo .board-column-header {
	  background: #4A9FF9;
	}
	.board-column.working .board-column-header {
	  background: #f9944a;
	}
	.board-column.done .board-column-header {
	  background: #2ac06d;
	}
	
	.board-item-content {
	  background: #fff;
	  border-radius: 4px;
	  font-size: 15px;
	  border: 1px solid rgba(0,0,0,0.2);
	 /* -webkit-box-shadow: 0px 1px 3px 0 rgba(0,0,0,0.2);
	  box-shadow: 0px 1px 3px 0 rgba(0,0,0,0.2);*/
	}
	.board-item{
		width:24%; 
		float:left;
	}

	.item-content{
	  padding: 10px 20px 20px 20px;
	}
	.button{
		/*background: rgb(60,70,100); */
	    text-decoration: none;
	    /*font-weight: bold;*/
	    background: rgb(60,70,100);
	    margin: 0em 0.9em !important;
	    padding: 0.4em 0.5em;
	    font-family: roboto,arial,tahoma,verdana,helvetica;
	    display: inline-block;
	    text-align: center;
	    cursor: pointer;
	    color: white !important;
	    border-radius: 5px;
	     /*border-color: rgba(0, 0, 0, 0.15) rgba(0, 0, 0, 0.15) rgba(0, 0, 0, 0.25); */
	}
	.bottom{
		background: rgba(0,0,0,0.2);
	  	padding: 5px;
	}
	@media (max-width: 600px) {
	  .board-item-content {
	    text-align: center;
	  }
	  .board-item-content span {
	    display: none;
	  }
	}
	.icon_list{
		cursor: pointer;
		text-decoration: none;
	}
	.icon_list:hover{
		text-decoration: none;
		padding: 5px;

	}

	.icon_list:hover img{
		background-color: rgba(0, 0, 0, 0.15);
	}
</style>

<script>
	$(function(){
		$('#lancer').click(function(){
            $id=$('#lancer').data('id');
            $.ajax({
                data:{'poste':$id,},
                url:"<?php echo dol_buildpath('/recrutement/candidatures/info_contact.php?action_=lancer',2) ?>",
                type:'POST',
                success:function($data){
                    if($data == 'Ok'){
                        $('#lancer').css('display','none');
                        $('#arret').css('display','block');
                    }
                        location.reload();
                }
            });
        });
        $('.icon_list').click(function(){
        	$type=$(this).data('type');
        	console.log($ty)
        });
	});
</script>





