(function($){

$( document ).ready(function(){
	var tableOffset = $("#table-1").offset().top;
var tableOffset1 = $("#table-1").offset().left;
var $body = $("#table-1 > tbody").clone();

var $fixedHeader = $("#header-fixed").append($body);
$("#header-fixed").width($('#table-1').width());
$("#table-1 > tbody").hide();
$("#header-fixed").show();
$("#table-1 > thead").find('tr').last().children().each(function(i, e)
{
    
   $($("#header-fixed").find('tr').children()[i]).width($(e).width());
});

$("#header-fixed ").find('tr').first().children().each(function(i, e)
{
    
   $($("#table-1 > thead").find('tr').first().children()[i]).width($(e).width());
   $($("#table-1 > thead").find('tr').last().children()[i]).width($(e).width());
});

	

});


})(jQuery);