(function($){

$( document ).ready(function(){
	
	if ($.fn.timepicker) {
		$('.timepicker').timepicker({
			showSecond: true,
			timeOnly: true
		});
	}
	
	if ($.fn.datepicker) {
		$('.datepicker22').datepicker();
	}


	$('select[name=search_year]').change(function() { 
		year = this.value;
		if( $.isNumeric( year ) && year>0){
			var options = {
			'year_lot': parseInt(year)
		};

		$.post('../ajax/month_eval.php', options, function(response) {
				$('#lot_').empty();
				$('input[name=quantity_]').val('');
				$.each(response.lots, function(index, val) {
				    $('#lot_').append($('<option>', {
						    value: val,
						    text: val
						}));
				}); 
		}, 'json');
		}
	});
	
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
fen.document.body.innerHTML += '<STYLE type="text/css" media="print"> @page { size: landscape; }</STYLE>';
fen.document.body.innerHTML += " " + zone + " ";

// Impression du popup
fen.window.print();
//Fermeture du popup
fen.window.close();
return true;
}