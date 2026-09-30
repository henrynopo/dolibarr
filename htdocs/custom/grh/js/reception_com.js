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

	

}) ;




})(jQuery);