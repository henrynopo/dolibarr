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

$('input[name=datec_]').change(function() { 
		year = this.value.split('/')[2];
		if( $.isNumeric( year ) && year>0){
			var options = {
			'year_lot': parseInt(year)
		};

		$.post('../ajax/month_fuel.php', options, function(response) {
				$('#lot_').empty();
				  $('#lot_').append($('<option>', {
						    value: '',
						    text: ''
						}));
				$.each(response.lots, function(index, val) {
				    $('#lot_').append($('<option>', {
						    value: val,
						    text: val
						}));
				}); 
				$('input[name=start_]').val(response.lastend);
		}, 'json');
		}
	});
	$('#lot_').change(function() { 
		if( $.isNumeric( this.value ) && this.value>0 && $('input[name=datec_]').val()!==''){
			year = $('input[name=datec_]').val();
			year = year.split('/')[2];
			var options = {
			'lot': this.value,
			'year_lot':parseInt(year)
		};

		$.post('../ajax/quantity_recpt.php', options, function(response) {
			$('input[name=tonnage_]').val(response.quantity);

			if($('#consomat').text()!=='')
				{var rendu = (parseInt($('#consomat').text())/parseInt(response.quantity))*1000000;
						 rendu = rendu.toFixed(2);
						$('#qntcntge').text(rendu+' kg');}
							
		}, 'json');
		}
		else{
			$('input[name=tonnage_]').val('');
			$('#qntcntge').text('');
		}
	});
$('#search_year').change(function() { 
		if( $.isNumeric( this.value ) && this.value>0){
			var options = {
			'year': this.value
		};

		$.post('../ajax/month_fuel.php', options, function(response) {
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

	$('input[name=start_]').change(function() { 
		if(this.value !=='' && $.isNumeric( this.value )  && $('input[name=end_]').val()!==''){
				 var consomat = parseFloat($('input[name=end_]').val())-parseFloat(this.value);
				 consomat = consomat.toFixed(1);
				$('#consomat').text(consomat);
				if($('input[name=tonnage_]').val()!==''){
					qct = (consomat/parseInt($('input[name=tonnage_]').val()))*1000000;
					 qct = qct.toFixed(2);
					 $('#qntcntge').text(qct);
				}
			if($('input[name=nbr_hr_]').val()!==''){
				var moyen = (consomat/parseFloat($('input[name=nbr_hr_]').val()))*1000;
				$('#moyen').text(moyen.toFixed());
			}
		}
		else{
			$('#consomat').text('');
			$('#moyen').text('');
			$('#qntcntge').text('');
			if(!$.isNumeric( this.value ))
			this.value = '';
		}
	});

$('input[name=end_]').change(function() { 
		if(this.value !=='' && $.isNumeric( this.value )  && $('input[name=start_]').val()!==''){
				 var consomat = parseFloat(this.value)-parseFloat($('input[name=start_]').val());
				 consomat = consomat.toFixed(1);
				$('#consomat').text(consomat);
				if($('input[name=tonnage_]').val()!==''){
					qct = (consomat/parseInt($('input[name=tonnage_]').val()))*1000000;
					 qct = qct.toFixed(2);
					 $('#qntcntge').text(qct);
				}
			if($('input[name=nbr_hr_]').val()!==''){
				var moyen = (consomat/parseFloat($('input[name=nbr_hr_]').val()))*1000;
				$('#moyen').text(moyen.toFixed());
			}
		}
		else{
			$('#consomat').text('');
			$('#moyen').text('');
			$('#qntcntge').text('');
			if(!$.isNumeric( this.value ))
			this.value = '';
		}
	});

$('input[name=nbr_hr_]').change(function() { 
		if(this.value !=='' && $.isNumeric( this.value )  && $('#consomat').text()!==''){
				var moyen = (parseFloat($('#consomat').text())/parseFloat($('input[name=nbr_hr_]').val()))*1000;
				$('#moyen').text(moyen.toFixed());
			}
		else{
			$('#moyen').text('');
			if(!$.isNumeric( this.value ))
			this.value = '';
		}
	});

$('input[name=tonnage_]').change(function() { 
		if(this.value !=='' && $.isNumeric( this.value )  && $('#consomat').text()!==''){
				qct = (parseFloat($('#consomat').text())/parseInt(this.value))*1000000;
					 qct = qct.toFixed(2);
					 $('#qntcntge').text(qct);
			}
		else{
			 $('#qntcntge').text('');
			if(!$.isNumeric( this.value ))
			this.value = '';
		}
	});


	

}) ;




})(jQuery);