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


	$('select[name=yearc_]').change(function() { 
		year = this.value;
		if( $.isNumeric( year ) && year>0 && year != $('input[name=current_year]').val() ){
			var options = {
			'year': parseInt(year)
		};

		$.post('../ajax/year_stock_check.php', options, function(response) {
				if(response.check){
					alert('Cette année est deja insérée !');
					$('select[name=yearc_]').val("");
				}
		}, 'json');
		}
	});
	
}) ;


})(jQuery);