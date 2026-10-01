// jQuery(document).ready(function() {
$(window).on('load', function() {
  $(".datepickerecvmod").datepicker("destroy");
  $('.datepickerecvmod').removeClass('hasDatepicker');
  $("input.datepickerecvmod").datepicker({
      dateFormat: "dd/mm/yy"
  });
});

function textarea_autosize(){
  $(".form_ecv textarea").each(function(textarea) {
    $(this).height($(this)[0].scrollHeight);
    $(this).css('resize', 'none');
  });
}