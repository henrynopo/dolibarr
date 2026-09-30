(function($){

$( document ).ready(function(){
	if ($.fn.timepicker) {
		$('.timepicker').timepicker({
			showSecond: true,
			timeOnly: true
		});
	}
	
	$("input[name='dated_']" ).datepicker({
		onClose: function( selectedDate ) {
        $("input[name='datef_']").datepicker( "option", "minDate", selectedDate );
    		}
        });
    $("input[name='datef_']").datepicker({
    	onClose: function( selectedDate ) {
        $("input[name='dated_']").datepicker( "option", "maxDate", selectedDate );
    		}
        });

    $("input[name='search_dated']" ).datepicker({
		onClose: function( selectedDate ) {
        $("input[name='search_datef']").datepicker( "option", "minDate", selectedDate );
       }
    });
    $("input[name='search_datef']").datepicker({
    	onClose: function( selectedDate ) {
        $("input[name='search_dated']").datepicker( "option", "maxDate", selectedDate );
        }
    });
	if ($.fn.datepicker) {
		$('.datepicker22').datepicker();
	}

	

}) ;




})(jQuery);