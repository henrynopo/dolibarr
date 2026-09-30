$(document).ready(function(){
    $('.montant_autres').hide();
    $( ".dtpick" ).datepicker({
        dateFormat: 'yy-mm-dd'
    });
    $('#cat').change(function(){
        cat = $('#cat').val();
        text = $('#cat option:selected').text().toLowerCase();
        if(cat != -1)
            $('.add').removeAttr("disabled");
        else{
            $('.add').attr("disabled","disabled");
        }
        if(text == "autres" ){
            $('.montant_autres').show();
        }else{
            $('.montant_autres #mont').val('');
            $('.montant_autres').hide();
        }
    });

    $('#addtr').click(function(){
        $('#tblajo #tbody').append( '<tr><td align="center"><input id="line_'+$('#tblajo #tbody tr').length+'" type="text" class="dtpick" name="datec[]" required="required" /></td><td align="center"><input type="number" value="" name="bon[]" /></td><td align="center"><input type="number" step="0.1" value="" name="prix[]" class="prix" /></td><td align="center"><input type="number" value="" name="quantity[]" class="quantity" /></td></tr>' );
        $('#nbr_charge').val($('#tblajo #tbody tr').length);
        var cn = $('#tblajo #tbody tr').length;
        
        for (var i = 0;  i <= cn; i++) {
            $( "#line_"+i ).datepicker({
                dateFormat: 'yy-mm-dd'
            });
        }
        
    });





/*


    $('.catall').hide();
        cat = $('#category').val();
        fn_check();
    $('#category').change(function(){
        cat = $('#category').val();
        fn_check();
    });

    function fn_check(){
        
        if(cat != -1){
            $('.catall').show();
            if(cat == 1){
                    $('.cat').show();
                    $('.cat input').attr("required","required");
                    $('.catall .prix').change(function(){ fn_calc(); });
                    $('.catall .quantity').change(function(){ fn_calc(); });
                    $('.catall .montant').attr("readonly","readonly");
            }else if(cat == 2){
                    $('.cat').show();
                    $('.cat input').attr("required","required");
                    $('.catall .prix').change(function(){ fn_calc(); });
                    $('.catall .quantity').on('input',function(e){ alert('Changed!'); });
                    $('.catall .quantity').change(function(){ fn_calc(); });
                    $('.catall .montant').attr("readonly","readonly");
            }else if(cat == 3){
                    $('.cat').hide();
                    $('.cat input').removeAttr("required");
                    $('.catall .montant').removeAttr("readonly");
            }
            $('.add').removeAttr("disabled");
        }else{
            $('.catall').hide();
            $('.add').attr("disabled","disabled");
        }

    }
    function fn_calc(){
        prix = $('.catall .prix').val();
        quantity = $('.catall .quantity').val();
        $('.catall .montant').val(prix*quantity);
    }
*/



});