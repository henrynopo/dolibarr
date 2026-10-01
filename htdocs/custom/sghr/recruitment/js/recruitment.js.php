<?php 

if (!defined('NOREQUIRESOC')) {
	define('NOREQUIRESOC', '1');
}
if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', 1);
}
if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', 1);
}
if (!defined('NOLOGIN')) {
	define('NOLOGIN', 1);
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', 1);
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', 1);
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}

// define('ISLOADEDBYSTEELSHEET', '1');
// session_cache_limiter('public');

$res=0;
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // sghr subdir depth
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php"); // sghr sub-subdir depth
if (! $res && file_exists("../../../../../main.inc.php")) $res=@include("../../../../../main.inc.php"); // sghr deeper
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 

// Define mime type
top_httphead('text/javascript');


dol_include_once('/core/lib/functions.lib.php');
dol_include_once('/sghr/recruitment/class/Candidate.class.php');
dol_include_once('/core/class/html.formother.class.php');


$candidature = new Candidate($db);
$formother = new FormOther($db);
// Define mime type
global $langs;
$var = false;

?>

jQuery(document).ready(function() {
 $('#validatesumitbutton').click(function(){
		$('input[type="submit"]').click();
	});
	// $( ".datepickerncon" ).datepicker({
	//     dateFormat: 'dd/mm/yy'
	// });
	$('.select_degrees').select2();
	$('.select_majors').select2();
	$('.tablerecrutement #select_srch_nom').select2();
	$('.tablerecrutement #select_srch_poste').select2();
	$('#add_major').click(function(){
		id = $('#tab_level').find('tr').length;
		$('#tab_level').append('<tr><td style="text-align:center;" class=""><select onchange="GetMajors(this)" class="select_degrees"  name="leveled['+id+'][degree]"><?php echo $candidature->select_degrees(0); ?> </select></td><td style="text-align:center;" class=""><select class="select_majors" name="leveled['+id+'][major]"></select></td><td style="text-align:center;"><input type="number" name="leveled['+id+'][year]" min="0" class="select_years maxwidth75imp" value="<?php echo date('Y')?>"></td><td style="text-align:center;" class="width50"><a class="deletelevel" onclick="DeleteLevel(this)"><?php echo img_delete()?></a></td></tr>');
    	$('.select_degrees').select2();
		$('.select_majors').select2();
    });


	
});

$(window).on('load', function() {
	$(".datepickerncon").datepicker("destroy");
	$('.datepickerncon').removeClass('hasDatepicker');
	$("input.datepickerncon").datepicker({
		dateFormat: "dd/mm/yy"
	});

	// $('.timepicker99').timepicker({
	//     format: 'H:i',
	// });
	
});

function GetMajors(x) {
	val = $(x).val();
	$.ajax({
		data:{'fk_degree':val},
		url:'<?php echo dol_buildpath("/sghr/recruitment/candidates/ajax/getMajors.php",2) ?>',
		type:'POST',
        success:function(data){
            $(x).parents('tr').find('.select_majors').html(data);
        }
	});
}

function DeleteLevel(x) {
	$(x).parents('tr').remove();
}

function RemoveLevel(x) {
	Dataremove = $('.dataremove').val();
	if(Dataremove) newdata = Dataremove+','+$(x).data('id');
	else newdata = $(x).data('id');
	$('.dataremove').val(newdata);
	DeleteLevel($(x));
}

function textarea_autosize(){
  	$("textarea").each(function(textarea) {
		$(this).height($(this)[0].scrollHeight);
		$(this).css('resize', 'none');
  	});
}

function get_status_appreciation(input) {
	$status=$(input).data('status');
	$id=$(input).val();
	if($id > 2){
		$('.appreciationdetail').addClass('greenbg');
		$('.appreciationdetail').removeClass('redbg');
	}else{
	 	$('.appreciationdetail').removeClass('greenbg');
		$('.appreciationdetail').addClass('redbg');
	}
	$('.appreciationdetail').html($status);
}