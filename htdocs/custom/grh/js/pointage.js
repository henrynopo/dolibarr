(function($){

$( document ).ready(function(){
	
	$("#header-fixed ").find('tr').first().children().each(function(i, e)
	{
   		$($("#table-1 > thead").find('tr').last().children()[i]).width($(e).width());
	});


	 $('input[type=radio][name=radio]').change(function() {
        if (this.value == 'both') {
            $('.declar').show();
            $('.nodeclar').show();
        }
        else if (this.value == 'declar') {
             $('.declar').show();
            $('.nodeclar').hide();
        }
        else if (this.value == 'nodeclar') {
             $('.declar').hide();
            $('.nodeclar').show();
        }
    });
	$current_year = $("select[name='periodyears']" ).val();
	$('#emptyAll').click(function() { 
		if (confirm("êtes-vous sûr de vouloir vider tous les valeurs?"))
		$('#header-fixed').find('input:text').val(0);        
	});
	$('#lblBlurWeekEnd').click(function() { 
		if( $("input[name='show']").val()=='0')
			$("input[name='show']").val('1');
		else
			$("input[name='show']").val('0');
	    
	});

	if ($.fn.timepicker) {
		$('.timepicker').timepicker({
			showSecond: true,
			timeOnly: true
		});
	}
	
	$('#periodyears').change(function() { 
		$current_year = this.value;
		var d = new Date($current_year+"-01-01");
		var f = new Date($current_year+"-12-31");
		$current_year = $current_year+':'+$current_year;
		 $("input[name='search_datef']").val( "" );
		 $("input[name='search_datef']").prop('required',false);
         $("input[name='search_dated']").val( "" );
         $("input[name='search_dated']").prop('required',false);
	        $("input[name='search_datef']").datepicker( "option", "yearRange", $current_year );
	        $("input[name='search_dated']").datepicker( "option", "yearRange", $current_year );
	        $("input[name='search_datef']").datepicker( "option", "minDate", d );
	        $("input[name='search_datef']").datepicker( "option", "maxDate", f );
	        $("input[name='search_dated']").datepicker( "option", "minDate", d );
	        $("input[name='search_dated']").datepicker( "option", "maxDate", f );
	    
	});
		var d = new Date($current_year+"-01-01");
		var f = new Date($current_year+"-12-31");
	$current_year = $current_year+':'+$current_year;
	$("input[name='search_dated']" ).datepicker({
		minDate: d,
		maxDate: f,
		yearRange: $current_year,
      onClose: function( selectedDate ) {
        $("input[name='search_datef']").datepicker( "option", "minDate", selectedDate );
        if(selectedDate)
      $("input[name='search_datef']").prop('required',true);
      }
    });
    $("input[name='search_datef']").datepicker({
    	minDate: d,
		maxDate: f,
		yearRange: $current_year,
      onClose: function( selectedDate ) {
        $("input[name='search_dated']").datepicker( "option", "maxDate", selectedDate );
        if(selectedDate)
        $("input[name='search_dated']").prop('required',true);
      }
    });
    
	if ($.fn.datepicker) {
		$('.datepicker22').datepicker();
	}


	

}) ;



})(jQuery);

function imprimer_bloc() {
// Définition de la zone à imprimer

var zone = document.getElementById("imprime").innerHTML;



// Ouverture du popup
var fen = window.open("", "", "height=0, width=0,toolbar=0, menubar=0,scrollbars=1,visible=0,resizable=1,status=0, location=1, left=1000, top=1000");
 
// style du popup
fen.document.body.style.color = '#000000';
fen.document.body.style.backgroundColor = '#FFFFFF';
fen.document.body.style.padding = "20px";
 
// Ajout des données a imprimer
fen.document.title = "imprimere";
fen.document.body.innerHTML += " " + zone + " ";

// Impression du popup

   fen.document.write('<html><head><title>IMPRIMER</title>');
   fen.document.write('<link rel="stylesheet" type="text/css" href="../css/grh.css">');
           fen.document.write('</head><body >');
            fen.document.write('<STYLE type="text/css" media="print"> @page { size: landscape; }</STYLE>');

            fen.document.write(zone);
            fen.document.write('</body></html>');
            fen.document.close(); // necessary for IE >= 10
            fen.onload=function(){ // necessary if the div contain images

                fen.focus(); // necessary for IE >= 10
                fen.print();
                fen.close();
            };
/*fen.window.print();
//Fermeture du popup
fen.window.close();*/
return true;
}