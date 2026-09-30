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


	$('input[name=datev_]').change(function() { 
		year = this.value.split('/')[2];
		if( $.isNumeric( year ) && year>0){
			var options = {
			'year_lot': parseInt(year)
		};

		$.post('../ajax/month_eval.php', options, function(response) {
				$('#lot_').empty();
				$('input[name=quantity_]').val('');
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
		}, 'json');
		}
	});
	$('#search_year').change(function() { 
		if( $.isNumeric( this.value ) && this.value>0){
			var options = {
			'year': this.value
		};

		$.post('../ajax/month_eval.php', options, function(response) {
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

	$('#lot_').change(function() { 
		if( $.isNumeric( this.value ) && this.value>0 && $('input[name=datev_]').val()!==''){
			year = $('input[name=datev_]').val();
			year = year.split('/')[2];
			var options = {
			'lot': this.value,
			'year_lot':parseInt(year)
		};

		$.post('../ajax/quantity_recpt.php', options, function(response) {
			$('input[name=quantity_]').val(response.quantity);

			if($('input[name=farine_]').val()!=='')
				{var rendu = (parseInt($('input[name=farine_]').val())/parseInt(response.quantity))*100;
						 rendu = rendu.toFixed(2);
						$('#rndf').text(rendu+'%');}
			if($('input[name=huile_]').val()!=='')
				{var rendu = (parseInt($('input[name=huile_]').val())/parseInt(response.quantity))*100;
				 rendu = rendu.toFixed(2);
				$('#rndh').text(rendu+'%');}
			if($('input[name=cpf_]').val()!=='' && $('input[name=farine_]').val()!==''){
				 var valeur = parseFloat($('input[name=cpf_]').val())*parseInt($('input[name=farine_]').val());
					$('#vpf').text(valeur.toFixed(2));}
							
		}, 'json');
		}
		else{
			$('input[name=farine_]').val('');
			$('input[name=huile_]').val('');
			$('input[name=cpf_]').val('');
			$('#rndh').text('');
			$('#rndf').text('');
			$('#vpf').text('');
		}
	});

	$('input[name=farine_]').change(function() { 
		if(this.value !=='' && $.isNumeric( this.value )  && $('input[name=quantity_]').val()!==''){
				 var rendu = (parseInt(this.value)/parseInt($('input[name=quantity_]').val()))*100;
				 rendu = rendu.toFixed(2);
				$('#rndf').text(rendu+'%');
			if($('input[name=cpf_]').val()!==''){
				var valeur = parseInt(this.value)*parseFloat($('input[name=cpf_]').val());
				$('#vpf').text(valeur.toFixed(2));
			}
		} 
		else{
			if(!$.isNumeric( this.value ))
				this.value = '';
			$('#rndf').text('');
		}
	});

	$('input[name=huile_]').change(function() { 
		if(this.value !=='' && $.isNumeric( this.value ) && $('input[name=quantity_]').val()!==''){
				 var rendu = (parseInt(this.value)/parseInt($('input[name=quantity_]').val()))*100;
				 rendu = rendu.toFixed(2);
				$('#rndh').text(rendu+'%');
		}
		else{
			if(!$.isNumeric( this.value ))
				this.value = '';
			$('#rndh').text('');
		}
	});

	$('input[name=cpf_]').change(function() { 
		if(this.value !=='' && $.isNumeric( this.value ) && $('input[name=farine_]').val()!==''){
				 var valeur = parseFloat(this.value)*parseInt($('input[name=farine_]').val());
				$('#vpf').text(valeur.toFixed(2));
		}
		else{
			if(!$.isNumeric( this.value ))
				this.value = '';
			$('#vpf').text('');
		}
	});

}) ;



})(jQuery);