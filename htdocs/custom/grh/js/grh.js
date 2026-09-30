$(document).ready(function() {
	// $(".datepicker22").datepicker();
	$(".datepicker22").datepicker({
		dateFormat: 'dd/mm/yy'
	});

	 textarea_autosize($("#observation_txt"));
	 textarea_autosize($("#risques_txt"));

});
function textarea_autosize(x){
    $(x).each(function(textarea) {
        $(this).css('resize', 'none');
    }).on('input', function () {
        $(this).css('height', 'auto');
        $(this).height($(this)[0].scrollHeight);
    });
}