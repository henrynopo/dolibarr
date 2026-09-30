$(document).ready(function(){
  
    // $('#id_fournisseur').change(function(){
      
    //     id = $('#id_fournisseur').val();


    //     if(id!=-1){
    //     $('#lien_rib').attr("href","/intranet/htdocs/societe/rib.php?socid="+id+"&action=create");

    //         var data = {
    //           'id': id
    //         };
    //         $.ajax({
    //           type: "POST",
    //           url: "../ordres_virements/check.php",
    //           data: data,
    //           dataType: 'json',
    //           success: function(found){
    //             $('#n_compte_forn').empty();
    //              $('#n_compte_forn').append($('<option>', {
    //                   value: -1,
    //                   text: ''
    //               }));
    //               $.each(found, function(index, val) {
    //                   $('#n_compte_forn').append($('<option>', {
    //                       value: index,
    //                       text: val
    //                   }));
    //               });
    //           }

    //         });
    //     }else{
    //       $('#lien_rib').removeAttr("href");
    //       $('#n_compte_forn').empty();
    //     }
    // });


    $('#srch_year').change(function(){
      
        year = $('#srch_year').val();

        if(year!=-1){
            var data = {
              'year': year
            };
            $.ajax({
              type: "POST",
              url: "../ordres_virements/check.php",
              data: data,
              dataType: 'json',
              success: function(found){
                $('#srch_month').empty();
                 $('#srch_month').append($('<option>', {
                      value: -1,
                      text: ''
                  }));
                  $.each(found, function(index, val) {
                      $('#srch_month').append($('<option>', {
                          value: index,
                          text: val
                      }));
                  });
              }

            });
        }else
          $('#srch_month').empty();

    });

    $('#nbr_ordre').change(function(){
        nbr_ordre = $('#nbr_ordre').val();
        if(nbr_ordre>=0){
            var data = {
              'nbr_ordre': nbr_ordre
            };
            $.ajax({
              type: "POST",
              url: "../ordres_virements/check.php",
              data: data,
              dataType: 'json',
              success: function(found){
                if (found != -1) {
                  $('#nbr_ordre').val('');
                  $('#nbrmsg').text("  La valeur : "+nbr_ordre + "  Déja Exist Choisr un Autrue Nombre, ");
                }else
                  $('#nbrmsg').text('');
              }

            });
        }else{
          $('#nbr_ordre').empty();
        }
    });
});