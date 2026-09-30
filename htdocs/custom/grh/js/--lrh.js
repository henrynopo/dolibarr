$(function(){
	if ($.fn.timepicker) {
		$('.timepicker').timepicker({
			showSecond: true,
			timeOnly: true
		});
	}

	if ($.fn.datepicker) {
		$('.datepicker22').datepicker();
	}

	$('input[name=datep_]').change(function() { 
		date = this.value.split('/');
		date = date[2]+'-'+date[1]+'-'+date[0];
		if( date!=='' && $('input[name=nom_]')!==''){
			nom = $('select[name=nom_]').val();
			var options = {
			'date_': date,
			'nom_': nom
		};

		$.post('../ajax/date_check.php', options, function(response) {
				if(response.check)
					{$('input[name=datep_]').val('');
						alert('Cette date est deja pointée !');}
		}, 'json');
		}
	});

	$('select[name=nom_]').change(function() { 
		if(this.value!=='' && $('input[name=datep_]').val()!==''){
		date = $('input[name=datep_]').val();
		date = date.split('/');
		date = date[2]+'-'+date[1]+'-'+date[0];
		nom = this.value;
			var options = {
			'date_': date,
			'nom_': nom
		};

		$.post('../ajax/date_check.php', options, function(response) {
				if(response.check)
					{$('input[name=datep_]').val('');
						alert('Cette date est deja pointée !');}
		}, 'json');
		}
	});

});