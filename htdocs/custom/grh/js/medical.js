
$( document ).ready(function(){

	$('select[name=fk_user_]').change(function() {
	 if(this.value !=='' && $.isNumeric( this.value )){
	 	var options = {
			'fk_user':this.value
		};

		$.post('../ajax/check_medical.php', options, function(response) {
				if(response.check){
					alert('vous avez déja créer un dossier médical pour cet utilisateur!');
					$('select[name=fk_user_]').val('');
			
				} 
		}, 'json');
	 }
	});


	//$current_year = $("select[name='etat_year']" ).val();
	if ($.fn.timepicker) {
		$('.timepicker').timepicker({
			showSecond: true,
			timeOnly: true
		});
	}
	
	
	/*$('#etat_year').change(function() { 
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
	$current_year = $current_year+':'+$current_year;*/
	$("input[name='search_dated']" ).datepicker({
		/*minDate: d,
		maxDate: f,
		yearRange: $current_year,*/
      onClose: function( selectedDate ) {
        $("input[name='search_datef']").datepicker( "option", "minDate", selectedDate );
        /*if(selectedDate)
      $("input[name='search_datef']").prop('required',true);*/
      }
    });
    $("input[name='search_datef']").datepicker({
    	/*minDate: d,
		maxDate: f,
		yearRange: $current_year,*/
      onClose: function( selectedDate ) {
        $("input[name='search_dated']").datepicker( "option", "maxDate", selectedDate );
        /*if(selectedDate)
        $("input[name='search_dated']").prop('required',true);*/
      }
    });
    
	if ($.fn.datepicker) {
		$('.datepicker22').datepicker();
	}

	//-----------------Med : 09/11------------------------

	$('#search_year_create').change(function(){
      
        year = $('#search_year_create').val();

        if(year!=-1){
            var data = {
              'role' : 'get_month',
              'year': year
            };
            $.ajax({
              type: "POST",
              url: "../medical/check.php",
              data: data,
              dataType: 'json',
              success: function(found){
                $('#search_month_create').empty();
                 $('#search_month_create').append($('<option>', {
                      value: -1,
                      text: ''
                  }));
                  $.each(found, function(index, val) {
                      $('#search_month_create').append($('<option>', {
                          value: index,
                          text: val
                      }));
                  });
              }

            });
        }else
          $('#search_month_create').empty();

    });


    $('#search_year_close').change(function(){
      
        year = $('#search_year_close').val();

        if(year!=-1){
            var data = {
              'role' : 'get_month',
              'year': year
            };
            $.ajax({
              type: "POST",
              url: "../medical/checkf.php",
              data: data,
              dataType: 'json',
              success: function(found){
                $('#search_month_close').empty();
                 $('#search_month_close').append($('<option>', {
                      value: -1,
                      text: ''
                  }));
                  $.each(found, function(index, val) {
                      $('#search_month_close').append($('<option>', {
                          value: index,
                          text: val
                      }));
                  });
              }

            });
        }else
          $('#search_month_close').empty();

    });
	//----------------------------------------------------

}) ;

