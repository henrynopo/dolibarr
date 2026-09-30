$(document).ready(function(){
    
    solde = $('#solde').text();
    solde = solde.replace(/\,/g,"");
    solde = parseFloat(solde)-parseFloat($('#montant').val());


    $('#id_fournisseur').change(function(){
      check_solde();
    });
    $('#dateav').change(function(){
      check_solde();
    });

    function check_solde(){

        id_fournisseur = $('#id_fournisseur').val();
        ayear = $('#dateav').val();
        if(id_fournisseur!=-1 && ayear != ""){
            var data = {
              'id_fournisseur': id_fournisseur,
              'ayear' : ayear
            };
            $.ajax({
              type: "POST",
              url: "../avances_fournisseur/check.php",
              data: data,
              dataType: 'json',
              success: function(found){
                $('#solde').html(found);
                solde = found.replace(/\,/g,"");
              }
            });
        }
    }

    $('#montant').change(function(){
        montant = $('#montant').val();
        if(montant){
            total = parseFloat(solde)+parseFloat(montant);
            $('#solde').html(total);          
        }else{
            $('#solde').html(solde);          
        }
    });

    $('#srch_year').change(function(){
        year = $('#srch_year').val();
        if(year!=-1){
            var data = {
              'year': year
            };
            $.ajax({
              type: "POST",
              url: "../avances_fournisseur/check.php",
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

});