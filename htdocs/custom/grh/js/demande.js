(function($){

$( document ).ready(function(){
	$current_year = $("select[name='etat_year']" ).val();
	
	if ($.fn.timepicker) {
		$('.timepicker').timepicker({
			showSecond: true,
			timeOnly: true
		});
	}
	
	$('#etat_year').change(function() { 
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