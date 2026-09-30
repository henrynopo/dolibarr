$(document).ready(function(){
    $(function(){
        $(".datepicker22").datepicker({
            dateFormat: 'yy-mm-dd'
        });
    });
    // function check_user(){
    //     date = $('.checkd').val();
    //     user = $('#idutilisateur').val();

    //     if(date !="" && user!=-1){
    //         var data = {
    //           'role' : 'check_user',
    //           'date': date,
    //           'user': user
    //         };
    //         $.ajax({
    //           type: "POST",
    //           url: "../cnss_utilisateur/check.php",
    //           data: data,
    //           dataType: 'json',
    //           success: function(found){
    //             console.log(found);
    //             if(found == 1){
    //                     $('.valider').attr("disabled","disabled");
    //                     alert('L\'utilisateur sélectionné est déjà pointé sur ce mois !');
    //                 $('.checkd').val('');
    //             }else
    //                 $('.valider').removeAttr("disabled");
    //           }
    //         });
    //     }
    // }

    // $('.checkd').change(function(){
    //   $('#id_utilisateur').val();
    //   check_user();
    // });
    // $('#idutilisateur').change(function(){
    //   check_user();
    // });



    $('#search_year').change(function(){
      
        year = $('#search_year').val();

        if(year!=-1){
            var data = {
              'role' : 'get_month',
              'year': year
            };
            $.ajax({
              type: "POST",
              url: "../cnss_utilisateur/check.php",
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