(function($){

$( document ).ready(function(){

	$('input[name=name_]').change(function() { 
		if( this.value!==''){
			var options = {
			'name_': this.value
		};

		$.post('../ajax/check_name.php', options, function(response) {
			if(response.check) 
				{$('input[name=name_]').val('');
				alert('Ce mot est deja utilisé !');}
		}, 'json');
		}
	});


}) ;



})(jQuery);