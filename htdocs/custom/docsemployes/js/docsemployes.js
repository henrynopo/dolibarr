jQuery(document).ready(function() {
	$("#srch_fk_user").select2();
	$("#fk_type_document").select2();
	$("#srch_fk_type_document").select2();
});
$(window).on('load', function() {
    $(".datepickerdatefrformat").datepicker("destroy");
    $('.datepickerdatefrformat').removeClass('hasDatepicker');
    $("input.datepickerdatefrformat").datepicker({
        dateFormat: "dd/mm/yy"
    });    
});

function setmaxdatpicker(){
$("#debut").datepicker("option", "maxDate", $("#fin").val());
}
function setmindatpicker(){
$("#fin").datepicker("option", "minDate", $("#debut").val());
}