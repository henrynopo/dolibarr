$(document).ready(function(){

    $('#search_year').change(function(){
        year = $('#search_year').val();
        if(year!=-1){
            var data = {
              'year': year
            };
            $.ajax({
              type: "POST",
              url: "../charge_mois/check.php",
              data: data,
              dataType: 'json',
              success: function(found){
                $('#search_month').empty();
                 $('#search_month').append($('<option>', {
                      value: -1,
                      text: ''
                  }));
                  $.each(found, function(index, val) {
                      $('#search_month').append($('<option>', {
                          value: index,
                          text: val
                      }));
                  });
              }

            });
        }else
          $('#search_month').empty();

    });
});