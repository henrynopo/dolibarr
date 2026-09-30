(function($){

$( document ).ready(function(){
	$('input[name=mat_]').change(function() { 
		if( this.value!=='' ){
			mat = this.value;
			var options = {
			'mat_' : mat.trim()
		};

		$.post('ajax/mat_check.php', options, function(response) {
				if(response.check)
					{$('input[name=mat_]').val('');
						alert('Cette marticule '+mat+' est deja saisée !');}
		}, 'json');
		}
	});
	

	

});


})(jQuery);