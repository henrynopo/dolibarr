jQuery(document).ready(function() {
	$('#validatesumitbutton').click(function(){
        $('input[type="submit"]').click();
    });
    // $( ".datepickerncon" ).datepicker({
    //     dateFormat: 'dd/mm/yy'
    // });
    $('#select_degrees').select2();
    $('#select_majors').select2();
    $('#add_major').click(function(){
        
    });
    
});

$(window).on('load', function() {
    $(".datepickerncon").datepicker("destroy");
    $('.datepickerncon').removeClass('hasDatepicker');
    $("input.datepickerncon").datepicker({
        dateFormat: "dd/mm/yy"
    });
    // $('.timepicker99').timepicker({
    //     format: 'H:i',
    // });
    
});

function textarea_autosize(){
  $("textarea").each(function(textarea) {
    $(this).height($(this)[0].scrollHeight);
    $(this).css('resize', 'none');
  });
}

function get_status_appreciation(input) {
    $status=$(input).data('status');
    $id=$(input).val();
    // console.log($status);
    if($id > 2){
        // $('.appreciationdetail').css('background-color','#4abf4a');
        $('.appreciationdetail').addClass('greenbg');
        $('.appreciationdetail').removeClass('redbg');
    }else{
    	// $('.appreciationdetail').css('background-color','#ea6060');
    	$('.appreciationdetail').removeClass('greenbg');
        $('.appreciationdetail').addClass('redbg');
    }
    $('.appreciationdetail').html($status);
}