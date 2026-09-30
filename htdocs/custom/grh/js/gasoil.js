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
	setTimeout(function() {$('input[name="datec_"]').datepicker().focus(function() {$('input[name="datec_"]').datepicker("show");}).focus(); }, 1000);
	$('input[name=quantity_]').change(function() { 
		if(this.value !=='' && !$.isNumeric(this.value))
			$('input[name=quantity_]').val('');
	});

	$('#mat_').change(function() { 
		if( this.value!=='-1' && parseInt($('#lot_').val())>=0 && $('input[name=datec_]').val()!=='' ){
			date = $('input[name=datec_]').val();
			date = date.split('/');
			date = date[2]+'-'+date[1]+'-'+date[0];
			lot = $('#lot_').val();
			var options = {
			'lot_': parseInt(lot),
			'date_' : date,
			'mat_' : this.value
		};

		$.post('../ajax/lot_date_check.php', options, function(response) {
				if(response.check)
					{$('#mat_').val('-1');
						if(parseInt(lot) == 0)
							lot = 'neant';
						alert('Cette date '+date+' est deja pointée pour cet Matricule: '+this.value+' avec ce Lot: '+lot+'!');}
		}, 'json');
		}
	});

	$('#lot_').change(function() { 
		if( this.value!=='-1' && $('#mat_').val()!=='-1' && $('input[name=datec_]').val()!=='' ){
			date = $('input[name=datec_]').val();
			date = date.split('/');
			date = date[2]+'-'+date[1]+'-'+date[0];
			mat = $('#mat_').val();
			lot = this.value;
			var options = {
			'mat_': mat,
			'date_' : date,
			'lot_' : lot
		};

		$.post('../ajax/lot_date_check.php', options, function(response) {
				if(response.check)
					{$('#mat_').val('-1');
						if(parseInt(lot) == 0 )
							lot = 'neant';
						alert('Cette date '+date+' est deja pointée pour cet Matricule: '+mat+' avec ce Lot: '+lot+'!');}
		}, 'json');
		}
	});

	$('input[name=datec_]').change(function() { 
		year = this.value.split('/')[2];
		
		if( $.isNumeric( year ) && year>0){
			var options = {
			'year_lot': parseInt(year)
		};

		$.post('../ajax/month_gasoil.php', options, function(response) {
				$('#lot_').empty();
				 
				$.each(response.lots, function(index, val) {
				    $('#lot_').append($('<option>', {
						    value: index,
						    text: val
						}));
				}); 
		}, 'json');
		}
	});

	$('#search_year').change(function() { 
			if( $.isNumeric( this.value ) && this.value>0){
				var options = {
				'year': this.value
			};

			$.post('../ajax/month_gasoil.php', options, function(response) {
				$('#search_month').empty();
				 $('#search_month').append($('<option>', {
						    value: -1,
						    text: ''
						}));
				$.each(response.months, function(index, val) {
				    $('#search_month').append($('<option>', {
						    value: val,
						    text: val
						}));
				}); 
			}, 'json');
			}
		});
	

}) ;



})(jQuery);

function change_year_search(that){
	
	if( $.isNumeric( that.value ) && that.value>0){
				var options = {
				'year': that.value
			};
			$.post('../ajax/month_gasoil.php', options, function(response) {
				if($('select[name=search_month]').length>1){
					$('#search_month').remove();
				}
				$('#search_month').empty();
				 $('#search_month').append($('<option>', {
						    value: -1,
						    text: ''
						}));
				$.each(response.months, function(index, val) {

				    $('#search_month').append($('<option>', {
						    value: index,
						    text: val
						}));
				}); 
			}, 'json');
			}
		else{
			if($('select[name=search_month]').length>1){
					$('#search_month').remove();
				}
				$('#search_month').empty();
		}
}