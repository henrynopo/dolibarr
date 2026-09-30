(function($){

$( document ).ready(function(){

	$('input[name=thm_]').change(function() {
      if(this.value !=0 && $.isNumeric( this.value ))
        $('input[name=salary_]').val(0);
  });
$('input[name=salary_]').change(function() {
      if(this.value !=0 && $.isNumeric( this.value ))
        $('input[name=thm_]').val(0);
  });


}) ;



})(jQuery);