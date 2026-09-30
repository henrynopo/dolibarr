<?php

if (!defined('NOREQUIRESOC'))    define('NOREQUIRESOC', 1);
if (!defined('NOCSRFCHECK'))     define('NOCSRFCHECK', 1);
if (!defined('NOTOKENRENEWAL'))  define('NOTOKENRENEWAL', 1);

if (!defined('NOREQUIREHTML'))   define('NOREQUIREHTML', 1);
if (!defined('NOREQUIREAJAX'))   define('NOREQUIREAJAX', 1);

// define('ISLOADEDBYSTEELSHEET', '1');
// session_cache_limiter('public');



$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");       // For root directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php"); // For "custom" 

// Ensure $customtxt is always defined, even if optional logic below is commented out
$customtxt = '';

$langs->load('previewdocuments@previewdocuments');
top_httphead('text/javascript; charset=UTF-8');

?>



function previewdocuments_set_link() {

	$('#id-right a[href]:not(.relative_div_)').each(function() {

		

		var url = $(this).attr('href');

		var mime = $(this).attr('mime');

		var modulep = '';

		if (url.indexOf('document.php?') != -1 

			&& url.indexOf('action=delete') == -1 

			&& url.indexOf('action=edit') == -1 

			&& url.indexOf('image/png') == -1 

			&& !mime)

		{


			if( 

		 	url.toLowerCase().indexOf('.pdf')!=-1 || 
		 	
			url.toLowerCase().indexOf('.docx')!=-1 || 

			url.toLowerCase().indexOf('.dotx')!=-1 || 

			url.toLowerCase().indexOf('.dotm')!=-1 || 

			url.toLowerCase().indexOf('.doc')!=-1 || 

			url.toLowerCase().indexOf('.docm')!=-1 || 

			url.toLowerCase().indexOf('.xls')!=-1 || 

			url.toLowerCase().indexOf('.xlsx')!=-1 || 

			url.toLowerCase().indexOf('.xlsb')!=-1 || 

			url.toLowerCase().indexOf('.xlsm')!=-1 ||

			url.toLowerCase().indexOf('.pptx')!=-1 || 

			url.toLowerCase().indexOf('.ppt')!=-1 ) {



				filename = $(this).text();

				if(filename == '') filename = $(this).find('img').attr('alt');

				if(filename)

				filename = filename.replace(/'/g, "\\'");



				url = url.replace("&amp;", "&");

			
				console.log(url.indexOf("full_path_nc"));
				if(url.indexOf("full_path_nc") != -1 && url.indexOf("full_name_nc") != -1){
					name_file = url.split("full_name_nc=");
					name_file = name_file[1].split("&data_nc");
					name_new = name_file[0];


					big_url = url.split("full_path_nc=");

					big_url = big_url[1].split("&full_name_nc");

					big_url = big_url[0].split("/");
					big = big_url.pop();
					big_url = big_url.toString();	
					big_url = big_url.replace(/\,/g,'/');
					var urldoc = big_url;

				}else{

					modulep = url.split('modulepart=');
					modulep = modulep[1].split('&');
					modulep = modulep[0];

					if(modulep == 'project'){
						modulep = 'projet';
					}else if(modulep == 'facture_fournisseur'){
						modulep = 'fournisseur/facture';
					}else if(modulep == 'propal'){
						modulep = 'propale';
					}else if(modulep == 'commande_fournisseur'){
						modulep = 'fournisseur/commande';
					}

					console.log('modulep : '+modulep);

					url_root = "<?php echo $dolibarr_main_url_root; ?>";
					url_documents = "<?php echo $dolibarr_main_data_root; ?>";

					big_url = url.split("file=");

					big_url = big_url[1].split("/");
					big = big_url.pop();
					big_url = big_url.toString();	
					big_url = big_url.replace(/\,/g,'/');


					var urldoc = url_documents+"/"+modulep+"/"+big_url;

					name_file = url.split("file=");

					name_file = name_file[1];

					name_file = url.split("/");
					name_new = name_file[name_file.length-1];

					if(name_file.indexOf('$entity=')){
						name_file = url.split("$entity=");
						name_file = name_file[0];
					}
				}

			

				var extention = name_new.split(".");

				extention = extention[1];



				url = "javascript:previewdocuments_pop('"+name_new+"', '"+extention+"','"+urldoc+"')";

				// url = "javascript:previewdocuments_pop('https://view.officeapps.live.com/op/embed.aspx?src="+last_url_nc+"', '"+name_new+"', '"+last_url_nc+"','"+extention+"','"+urldoc+"')";


				console.log('url 1 : '+url);

				if( url.toLowerCase().indexOf('.pdf')!=-1 ) {
					url = "javascript:previewdocuments_pop_pdf('"+name_new+"', '"+extention+"','"+urldoc+"')";
				} 
				// else {

				// 	url = "javascript:previewdocuments_pop('https://view.officeapps.live.com/op/embed.aspx?src="+last_url_nc+"', '"+name_new+"')";

				// }

				console.log('url 2 : '+url);

				link = '&nbsp;<a class="added_nc" href="'+url+'"><?php echo img_object($langs->trans('Preview'),'previewdocuments@previewdocuments') ?></a>';

				console.log('link 3 : '+link);

				if( url.toLowerCase().indexOf('.pdf')!=-1 ) {
					$('.documentpreview').html('<?php echo img_object($langs->trans('Preview'),'previewdocuments@previewdocuments') ?>');

				}else{
					$(this).after(link);
				}

			}

			// if( url.toLowerCase().indexOf('.png')!=-1 || url.toLowerCase().indexOf('.jpg')!=-1 || url.toLowerCase().indexOf('.jpeg')!=-1 ) {

			

			// 	src_file = window.location.origin+url;

			// 	link = '&nbsp;&nbsp;<a class="added_nc lightbox_trigger_nc_" href="'+src_file+'"><?php echo img_object($langs->trans('Preview'),'previewdocuments@previewdocuments') ?></a>';

				

			// 	$(this).after(link);

			// }



		}



		$(this).addClass("relative_div_");

		$(".added_nc").addClass("relative_div_");

	});

}


function previewdocuments_pop_pdf(filename, extention=null, urldoc=null) {

	console.log("urlfile : "+urlfile);

	$('#previewdocuments').remove();



	<?php

		// $dircustom = dol_buildpath('/previewdocuments/');

		// $customtxt = "";

		// if (!is_dir($dircustom)) {

		// 	$customtxt = $dolibarr_main_url_root_alt;

		// }

	?>

	// $('#previewdocuments iframe').attr('src', '<div style="text-align:center;"><?php echo dol_buildpath('/previewdocuments/img/loading.gif',2) ?></div>');

	// $('div#previewdocuments').css({"background":"url(<?php echo dol_buildpath('/previewdocuments/img/loading.gif',2) ?>) center center no-repeat"});

	var data = {

		// 'urlfile' : urlfile,

		'filename' : filename,

		'extention' : extention,

		'urldoc' : urldoc,

		'action' : "readThisFile"

	};

	$.ajax({

		type: "POST",

		url: "<?php echo dol_buildpath('/previewdocuments/check.php',1); ?>",

		data: data, 

		dataType: 'json',

		success: function(found){

			if (found != "") {

				if($('#previewdocuments').length==0) {

					$('body').append('<div id="previewdocuments"><iframe src="#" style="display:none;" width="100%" height="100%" allowfullscreen webkitallowfullscreen frameborder=0></iframe></div>');

				}

				// $('div#previewdocuments').css({"background":"url(<?php echo dol_buildpath(' /previewdocuments/img/loading.gif',2) ?>) center center no-repeat"});

				// console.log("new link : "+found);

				$('#previewdocuments').dialog({

					title: "<?php echo preg_replace( "/\r|\n/", "", $langs->trans('PreviewOf')); ?> " + filename

					,width:'80%'

					,height:600

					,modal:true

					,resizable: true

					,close:function() {

						$('#previewdocuments iframe').attr('src', '#');

					}

				});

				var result = found;

				// console.log("result :"+result);

				$('#previewdocuments iframe').attr('src', result);

				setTimeout(function() { $("#previewdocuments iframe").show(); }, 500);

			}

		}

	});



	// $('#previewdocuments').dialog({

	// 	title: "<?php echo preg_replace( "/\r|\n/", "", $langs->trans('PreviewOf')); ?> " + filename

	// 	,width:'80%'

	// 	,height:600

	// 	,modal:true

	// 	,resizable: false

	// 	,close:function() {

	// 		$('#previewdocuments iframe').attr('src', '#');

	// 	}

	// });

	

	// $('#previewdocuments iframe').attr('src', url);
}


function previewdocuments_pop(filename, extention=null, urldoc=null) {

	// console.log("urlfile : "+urlfile);

	$('#previewdocuments').remove();



	<?php

		if (!isset($customtxt)) {

			$customtxt = '';

		}

		// $dircustom = DOL_DOCUMENT_ROOT.'/previewdocuments/';

		// $customtxt = "";

		// if (!is_dir($dircustom)) {

		// 	$customtxt = $dolibarr_main_url_root_alt;

		// }

	?>

	// $('#previewdocuments iframe').attr('src', '<div style="text-align:center;"><?php echo DOL_MAIN_URL_ROOT.$customtxt; ?>/previewdocuments/img/loading.gif</div>');

	// $('div#previewdocuments').css({"background":"url(<?php echo DOL_MAIN_URL_ROOT.$customtxt; ?>/previewdocuments/img/loading.gif) center center no-repeat"});

	var data = {

		// 'urlfile' : urlfile,

		'filename' : filename,

		'extention' : extention,

		'urldoc' : urldoc,

		'action' : "readThisFile"

	};

	$.ajax({

		type: "POST",

		url: "<?php echo dol_buildpath('/previewdocuments/check.php',1); ?>",

		data: data, 

		success: function(found){
			console.log(found);
			if (found != "") {
				if($('#previewdocuments').length==0) {

					$('body').append('<div id="previewdocuments"><iframe src="#" style="display:none;" width="100%" height="100%" allowfullscreen webkitallowfullscreen frameborder=0></iframe></div>');

				}

				// $('div#previewdocuments').css({"background":"url(<?php echo DOL_MAIN_URL_ROOT.$customtxt; ?>/previewdocuments/img/loading.gif) center center no-repeat"});

				// console.log("new link : "+found);

				$('#previewdocuments').dialog({

					title: "<?php echo preg_replace( "/\r|\n/", "", $langs->trans('PreviewOf')); ?> " + filename

					,width:'80%'

					,height:600

					,modal:true

					,resizable: true

					,close:function() {

						$('#previewdocuments iframe').attr('src', '#');

					}

				});

				var result = 'https://view.officeapps.live.com/op/embed.aspx?src='+found;

				// console.log("result :"+result);

				$('#previewdocuments iframe').attr('src', result);

				setTimeout(function() { $("#previewdocuments iframe").show(); }, 500);

			}

		}

	});



	// $('#previewdocuments').dialog({

	// 	title: "<?php echo preg_replace( "/\r|\n/", "", $langs->trans('PreviewOf')); ?> " + filename

	// 	,width:'80%'

	// 	,height:600

	// 	,modal:true

	// 	,resizable: false

	// 	,close:function() {

	// 		$('#previewdocuments iframe').attr('src', '#');

	// 	}

	// });

	

	// $('#previewdocuments iframe').attr('src', url);

	

}





function preview_images_pop() {

	$('.lightbox_trigger_nc_').click(function(e) {

		e.preventDefault();

		var image_href = $(this).attr("href");

	    $('.lightbox_nc .content_img').html('<img src="' + image_href + '" />');

	    $('.lightbox_nc').show();

    });

}

$(window).on('load', function() {
	
	$('body').append('<div class="lightbox_nc" style="display:none;"><p>X</p><div class="content_img"><img src="" /></div></div>');

	var top_menu = ($('#tmenu_tooltip').height() + 10);

	$('.lightbox_nc p').css({"margin-top":top_menu+"px","margin-right":top_menu+"px","padding-right":"30px"});

	$('.lightbox_nc,.lightbox_nc p').click(function() {$('.lightbox_nc').hide();});

	previewdocuments_set_link();

	preview_images_pop();

});

