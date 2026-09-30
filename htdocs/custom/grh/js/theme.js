(function($){

$( document ).ready(function(){
	var side = ($("#id-container .side-nav").width() + 4);
	var all = $("#id-container").width();
	var w_left = $("#id-left").width();
	$("#id-right").css({
	    opacity: 1
	});
	$( '#id-container > #id-right').css("width","calc(100vw - "+w_left+"px !important)");
});
})(jQuery);