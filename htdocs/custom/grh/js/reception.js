(function($){

$( document ).ready(function(){

	var $autotge = 0;

	$('input[name=brut_]').change(function() {
	 if(this.value !=='' && $.isNumeric( this.value )){
	 	if($('input[name=dater_]').val() !=='' && $('input[name=lot_]').val()!=='')
	 		{
 			date = $('input[name=dater_]').val().split('/');
			date = date[2]+'-'+date[1]+'-'+date[0];
	 		var options = {
			'dater': date,
			'brut':this.value,
			'lot':$('input[name=lot_]').val()
		};

		$.post('../ajax/check_reception.php', options, function(response) {
				if(!response.check){
			
				}
				else{
					alert('vous avez déja insérer les mêmes valeurs pour ce lot, vérifiez bien svp!');
					$('input[name=lot_]').val("");
				} 
		}, 'json');
	 }
	}
	});

	$('input[name=ticket_pass_]').change(function() {
	 if(this.value !==''){
	 	if($('input[name=dater_]').val() !=='' && $('input[name=lot_]').val()!=='')
	 		{
 			date = $('input[name=dater_]').val().split('/');
			date = date[2]+'-'+date[1]+'-'+date[0];
	 		var options = {
			'dater': date,
			'ticket':this.value,
			'lot':$('input[name=lot_]').val()
		};

		$.post('../ajax/check_reception.php', options, function(response) {
				if(response.check){
					alert('vous avez déja insérer les mêmes valeurs pour ce lot, vérifiez bien svp!');
					$('input[name=lot_]').val("");
				} 
		}, 'json');
	 }
	}
	});
	
	setTimeout(function() { $('input[name="lot_"]').focus() }, 1000);
	$current_year = $("select[name='etat_year']" ).val();
	$('#tonnagei').hide();
	$('.tonnage').hide();
	$('#check_tonnage').click(function(){
		if(this.checked)
		{$('#tonnagei').show('slow');
	     $('.tonnage').show('slow');}
	     else{
	     	$('#tonnagei').hide('slow');
			$('.tonnage').hide('slow');
	     }
	     $("#header-fixed ").find('tr').first().children().each(function(i, e)
{
    
   $($("#table-1 > thead").find('tr').first().children()[i]).width($(e).width());
   $($("#table-1 > thead").find('tr').last().children()[i]).width($(e).width());
});
	});
	$('#tarei').hide();
	$('.tare').hide();
	$('#check_tare').click(function(){
		if(this.checked)
		{$('#tarei').show('slow');
	     $('#tareo').show('slow');
	     $('.tare').show('slow');}
	     else{
	     	$('#tarei').hide('slow');
			$('#tareo').hide('slow');
			$('.tare').hide('slow');
	     }
	     $("#header-fixed ").find('tr').first().children().each(function(i, e){
	   $($("#table-1 > thead").find('tr').first().children()[i]).width($(e).width());
	   $($("#table-1 > thead").find('tr').last().children()[i]).width($(e).width());});
	});

	$('#check_tareo').click(function(){
		if(!this.checked)
		{$('#tareo').show('slow');
	     $('.tareo').show('slow');}
	     else{
	     	$('#tareo').hide('slow');
			$('.tareo').hide('slow');
	     }
	     $("#header-fixed ").find('tr').first().children().each(function(i, e){
	   $($("#table-1 > thead").find('tr').first().children()[i]).width($(e).width());
	   $($("#table-1 > thead").find('tr').last().children()[i]).width($(e).width());});
	});


	$('#check_brut').click(function(){
		if(!this.checked)
		{$('#bruti').show('slow');
	     $('.brut').show('slow');}
	     else{
	     	$('#bruti').hide('slow');
			$('.brut').hide('slow');
	     }
	     $("#header-fixed ").find('tr').first().children().each(function(i, e){
	   $($("#table-1 > thead").find('tr').first().children()[i]).width($(e).width());
	   $($("#table-1 > thead").find('tr').last().children()[i]).width($(e).width());});
	});
	$('#vpi').hide();
	$('.vp').hide();
	$('#check_vp').click(function(){
		if(this.checked)
		{$('#vpi').show('slow');
	     $('.vp').show('slow');}
	     else{
	     	$('#vpi').hide('slow');
			$('.vp').hide('slow');
	     }
	     $("#header-fixed ").find('tr').first().children().each(function(i, e){
	   $($("#table-1 > thead").find('tr').first().children()[i]).width($(e).width());
	   $($("#table-1 > thead").find('tr').last().children()[i]).width($(e).width());});
	});
	$('#deftgei').hide();
	$('.deftge').hide();
	$('#check_deftge').click(function(){
		if(this.checked)
		{$('#deftgei').show();
	     $('.deftge').show();}
	     else{
	     	$('#deftgei').hide();
			$('.deftge').hide();
	     }
	     $("#header-fixed ").find('tr').first().children().each(function(i, e){
	   $($("#table-1 > thead").find('tr').first().children()[i]).width($(e).width());
	  });
	});

	$('input[name=lot_]').focus();
	if ($.fn.timepicker) {
		$('.timepicker').timepicker({
			showSecond: true,
			timeOnly: true
		});
	}
	
	$('#etat_year').change(function() {
	 $("input[name='search_dated']" ).val('');
	 $("input[name='search_datef']" ).val('');
	 $("select[name='search_lot']" ).val('');
		$current_year = this.value;
		var d = new Date($current_year+"-01-01");
		var f = new Date($current_year+"-12-31");
		$current_year = $current_year+':'+$current_year;
		 $("input[name='search_datef']").val( "" );
		 $("input[name='search_datef']").prop('required',false);
         $("input[name='search_dated']").val( "" );
         $("input[name='search_dated']").prop('required',false);
	        $("input[name='search_datef']").datepicker( "option", "yearRange", $current_year );
	        $("input[name='search_dated']").datepicker( "option", "yearRange", $current_year );
	        $("input[name='search_datef']").datepicker( "option", "minDate", d );
	        $("input[name='search_datef']").datepicker( "option", "maxDate", f );
	        $("input[name='search_dated']").datepicker( "option", "minDate", d );
	        $("input[name='search_dated']").datepicker( "option", "maxDate", f );
	    
	});
		var d = new Date($current_year+"-01-01");
		var f = new Date($current_year+"-12-31");
	$current_year = $current_year+':'+$current_year;
	$("input[name='search_dated']" ).datepicker({
		minDate: d,
		maxDate: f,
		yearRange: $current_year,
      onClose: function( selectedDate ) {
        $("input[name='search_datef']").datepicker( "option", "minDate", selectedDate );
        if(selectedDate)
      $("input[name='search_datef']").prop('required',true);
      }
    });
    $("input[name='search_datef']").datepicker({
    	minDate: d,
		maxDate: f,
		yearRange: $current_year,
      onClose: function( selectedDate ) {
        $("input[name='search_dated']").datepicker( "option", "maxDate", selectedDate );
        if(selectedDate)
        $("input[name='search_dated']").prop('required',true);
      }
    });
    
	if ($.fn.datepicker) {
		$('.datepicker22').datepicker();
	}

	$('#search_year').change(function() { 
		if( $.isNumeric( this.value ) && this.value>0){
			var options = {
			'year': this.value
		};

		$.post('../ajax/month_recpt.php', options, function(response) {
			$('#search_month').empty();
			 $('#search_month').append($('<option>', {
					    value: -1,
					    text: ''
					}));
			$.each(response.months, function(index, val) {
			    $('#search_month').append($('<option>', {
					    value: val,
					    text: val
					}));
			}); 
		}, 'json');
		}
		else
			$('#search_month').empty();
	});

	$('input[name=brut_]').change(function() { 
		var net = 0;
		if(this.value !=='' && $.isNumeric( this.value )){
			if($('input[name=tare_]').val() !=='' && $.isNumeric( $('input[name=tare_]').val() )){
				 net = parseInt(this.value)-parseInt($('input[name=tare_]').val());
				$('#net').text(net+' kg');
				if( $autotge == 1 )
					{$('input[name=entier_]').val(net);
					$('input[name=tge_]').val(net);
					$('input[name=entier_]').trigger( "change" );
					$('input[name=tge_]').trigger( "change" );}
				
			}
			else{
				net = parseInt(this.value);
				$('#net').text(this.value +' kg');
				if( $autotge == 1 )
					{$('input[name=entier_]').val(net);
					$('input[name=tge_]').val(net);
					$('input[name=entier_]').trigger( "change" );
					$('input[name=tge_]').trigger( "change" );}

			}
		}
		else{
			if(!$.isNumeric( this.value ))
				this.value = '';
			if($('input[name=tare_]').val() !=='' && $.isNumeric( $('input[name=tare_]').val() )){
				net = -1*parseInt($('input[name=tare_]').val());
				$('#net').text(net+' kg');
				if( $autotge == 1 )
					{$('input[name=entier_]').val(net);
					$('input[name=tge_]').val(net);
					$('input[name=entier_]').trigger( "change" );
					$('input[name=tge_]').trigger( "change" );}
			}
			else{
				$('#net').text('');
					if( $autotge == 1 )
					{$('input[name=entier_]').val('');
					$('input[name=tge_]').val('');
					$('input[name=entier_]').trigger( "change" );
					$('input[name=tge_]').trigger( "change" );}
			}
		}


		if($('input[name=dechet_]').val() !=='' && $.isNumeric( $('input[name=dechet_]').val() )){
			net = net+parseInt($('input[name=dechet_]').val());
			$('#net_rec').text(net +' kg');
			if($('input[name=tge_]').val() !=='' ){
				var deftge = net-$('input[name=tge_]').val();
				$('#deftge').text(deftge +' Kg');
				}	
			}
			else{
				if(net != 0){
					$('#net_rec').text(net +' kg');
					if($('input[name=tge_]').val() !=='' ){
					var deftge = net-$('input[name=tge_]').val();
					$('#deftge').text(deftge +' Kg');}
				}
				else
				{$('#net_rec').text('');
				$('#deftge').text('');}
		}

		if($('input[name=pu_]').val() !=='' && $.isNumeric( $('input[name=pu_]').val() )){
				net = net*parseFloat($('input[name=pu_]').val());
				$('#vt').text(net.toFixed(2) );
				if($('#vpd').text()!=='')
					net = net + parseInt($('#vpd').text());
				$('#vt').text(net.toFixed(2) );
				if($('#entier').text() !=='' && $.isNumeric( $('#entier').text() )){
				var vpe = parseInt($('#entier').text())*parseFloat($('input[name=pu_]').val());
			$('#vpe').text(vpe.toFixed(2) );}
		}
	});

	$('input[name=tare_]').change(function() { 
		var net = 0; 
		if(this.value !=='' && $.isNumeric( this.value )){
			if($('input[name=brut_]').val() !=='' && $.isNumeric( $('input[name=brut_]').val() )){
				 net = parseInt($('input[name=brut_]').val())-parseInt(this.value);
				$('#net').text(net+' kg');
				if( $autotge == 1 )
					{$('input[name=entier_]').val(net);
				     $('input[name=tge_]').val(net);
					$('input[name=entier_]').trigger( "change" );
					$('input[name=tge_]').trigger( "change" );}
			}
			else{
				net = -1*parseInt(this.value);
				$('#net').text(this.value +' kg');
				if( $autotge == 1 )
					{$('input[name=entier_]').val(net);
					$('input[name=tge_]').val(net);
					$('input[name=entier_]').trigger( "change" );
					$('input[name=tge_]').trigger( "change" );}
			}
		}
		else{
			 if(!$.isNumeric( this.value ))
				this.value = '';
			if($('input[name=brut_]').val() !=='' && $.isNumeric( $('input[name=brut_]').val() )){
				net = parseInt($('input[name=brut_]').val());
				$('#net').text(net+' kg');
				if( $autotge == 1 )
					{$('input[name=entier_]').val(net);
					$('input[name=tge_]').val(net);
					$('input[name=entier_]').trigger( "change" );
					$('input[name=tge_]').trigger( "change" );}
			}
			else{
				net = 0;
				$('#net').text('');
				if( $autotge == 1 )
					{$('input[name=entier_]').val('');
					$('input[name=entier_]').trigger( "change" );
					$('input[name=tge_]').val('');
					$('input[name=tge_]').trigger( "change" );}
			}
		}
		if($('input[name=dechet_]').val() !=='' && $.isNumeric( $('input[name=dechet_]').val() )){
				net = net+parseInt($('input[name=dechet_]').val());
				$('#net_rec').text(net +' kg');
				if($('input[name=tge_]').val() !=='' ){
				var deftge = net-$('input[name=tge_]').val();
				$('#deftge').text(deftge +' Kg');
				}	
			}
			else{
				if(net != 0)
				{$('#net_rec').text(net +' kg');
					if($('input[name=tge_]').val() !=='' ){
					var deftge = net-$('input[name=tge_]').val();
					$('#deftge').text(deftge +' Kg');
					}	
				}
				else
				{$('#net_rec').text('');
				$('#deftge').text('');}
		}
		if($('input[name=pu_]').val() !=='' && $.isNumeric( $('input[name=pu_]').val() )){
				net = net*parseFloat($('input[name=pu_]').val());
				$('#vt').text(net.toFixed(2) );
				if($('#vpd').text()!=='')
					net = net + parseInt($('#vpd').text());
				$('#vt').text(net.toFixed(2) );
				if($('#entier').text() !=='' && $.isNumeric( $('#entier').text() )){
				var vpe = parseInt($('#entier').text())*parseFloat($('input[name=pu_]').val());
			$('#vpe').text(vpe.toFixed(2) );}
		}
	});

	$('input[name=dechet_]').change(function() { 
		var entier = 0;
		if(this.value !=='' && $.isNumeric( this.value )){
			$('#dech').text(this.value +' Kg');
				/*var ent=parseInt($('#entier').text())-parseInt(this.value);
				$('#entier').text(ent +' Kg');*/
				
				if($('#entier').text() !=='')
					entier = parseInt($('#entier').text())+parseInt(this.value);
				else
					entier = parseInt(this.value);
				/*if($('input[name=pu_]').val() !=='' && $.isNumeric( $('input[name=pu_]').val() )){
					var vpe = parseInt($('#entier').text())*parseFloat($('input[name=pu_]').val());
					$('#vpe').text(vpe.toFixed(2) );
				}*/
				$('#net_rec').text(entier +' kg');
				if($('input[name=tge_]').val() !=='' ){
				var deftge = entier-$('input[name=tge_]').val();
				$('#deftge').text(deftge +' Kg');
				}	

			if($('input[name=pd_]').val() !=='' && $.isNumeric( $('input[name=pd_]').val() )){
				var vpd = parseInt(this.value)*parseFloat($('input[name=pd_]').val());
				$('#vpd').text(vpd.toFixed(2) );
				if($('#vpe').text()!=='')
					vpd = vpd + parseInt($('#vpe').text());
				$('#vt').text(vpd.toFixed(2) );
			}		
		}
		else{
			if(!$.isNumeric( this.value ))
				this.value = '';
			if($('#entier').text() !=='')
				{$('#net_rec').text($('#entier').text());
					if($('input[name=tge_]').val() !=='' ){
					var deftge = parseInt($('#entier').text())-$('input[name=tge_]').val();
					$('#deftge').text(deftge +' Kg');
				}	
				}
			else
				{$('#net_rec').text('');
				$('#deftge').text('');}
		}
	});

	$('input[name=entier_]').change(function() { 
		var entier = 0;
		if(this.value !=='' && $.isNumeric( this.value )){
			$('#entier').text(this.value +' Kg');
				
				
				if($('#dech').text() !=='')
					entier = parseInt($('#dech').text())+parseInt(this.value);
				else
					entier = parseInt(this.value);
				/*if($('input[name=pu_]').val() !=='' && $.isNumeric( $('input[name=pu_]').val() )){
					var vpe = parseInt($('#entier').text())*parseFloat($('input[name=pu_]').val());
					$('#vpe').text(vpe.toFixed(2) );
				}*/
				$('#net_rec').text(entier +' kg');
				if($('input[name=tge_]').val() !=='' ){
				var deftge = entier-$('input[name=tge_]').val();
				$('#deftge').text(deftge +' Kg');
				}	

			if($('input[name=pu_]').val() !=='' && $.isNumeric( $('input[name=pu_]').val() )){
					var vpe = parseInt($('#entier').text())*parseFloat($('input[name=pu_]').val());
					$('#vpe').text(vpe.toFixed(2) );
					if($('#vpd').text()!=='')
					vpe = vpe + parseInt($('#vpd').text());
				$('#vt').text(vpe.toFixed(2) );
				}
		}
		else{
			$('#entier').text('');
			if(!$.isNumeric( this.value ))
				this.value = '';
			if($('#dech').text() !=='')
				{$('#net_rec').text($('#dech').text());
					if($('input[name=tge_]').val() !=='' ){
					var deftge = parseInt($('#dech').text())-$('input[name=tge_]').val();
					$('#deftge').text(deftge +' Kg');
				}	
				}
			else
				{$('#net_rec').text('');
				$('#deftge').text('');}
		}
	});

	$('input[name=pu_]').change(function() { 
		if(this.value !=='' && $.isNumeric( this.value ) && $('#entier').text() !==''){
			var vpe = parseInt($('#entier').text())*parseFloat(this.value);
			$('#vpe').text(vpe.toFixed(2) );
			if($('#vpd').text()!=='')
				vpe = vpe + parseInt($('#vpd').text());
			$('#vt').text(vpe.toFixed(2) );

		}
		else{
			if(!$.isNumeric( this.value ))
				this.value = '';
			$('#vpe').text('');
			if($('#vpd').text()!=='')
				{vpe = vpe + parseInt($('#vpd').text());
				$('#vt').text(vpe.toFixed(2) );}
			else
				$('#vt').text('');	
		}

	});

$('input[name=lot_]').change(function() { 
		if($.isNumeric( this.value ) && $('input[name=dater_]').val() !==''){
			date = $('input[name=dater_]').val().split('/');
			dater = date[2]+'-'+date[1]+'-'+date[0];
			var options = {
			'dater_': dater,
			'lot_': this.value,
			'year_':date[2]
		};

		$.post('../ajax/lot_check.php', options, function(response) {
			 if(response.check ){
			 	var r = confirm("Ce lot "+$('input[name=lot_]').val()+" est déja mentionné précédemment et Cette date "+$('input[name=dater_]').val()+" est inférieur de la date de dérnier lot "+response.last_lot+",vous voulez le saisir ?");
			    if (r == false) {
			        $('input[name=lot_]').val('');
			        $('input[name=dater_]').val('');
			    } 
			 }else if(parseInt(response.last_lot) > parseInt($('input[name=lot_]').val())) {
			 	var r = confirm("Ce lot "+$('input[name=lot_]').val()+" est déja exists , dérnier lot "+response.last_lot+",vous voulez le saisir ?");
			 }
		}, 'json');

		}
		/*else{
				this.value = '';
		}*/

	});

$('input[name=dater_]').change(function() { 
		if($('input[name=dater_]').val() !=='' && $('input[name=lot_]').val()!=='')
	 		{
 			date = $('input[name=dater_]').val().split('/');
			dater = date[2]+'-'+date[1]+'-'+date[0];
	 		var options = {
			'dater_': dater,
			'lot_':$('input[name=lot_]').val(),
			'year_':date[2]
		};

		$.post('../ajax/dater_check.php', options, function(response) {
			 if(response.check){
			 	var r = confirm("Ce lot "+$('input[name=lot_]').val()+" est déja mentionné précédemment et Cette date "+$('input[name=dater_]').val()+" est inférieur de la date de dérnier lot "+response.last_lot+",vous voulez le saisir ?");
			    if (r == false) {
			        $('input[name=dater_]').val('');
			        $('input[name=lot_]').val('');
			    } 

			 }
			 if(response.check2){
			 	var r = confirm("Cette date "+$('input[name=dater_]').val()+" est inférieur de la date de dérnier lot "+response.last_lot+",vous voulez le saisir ?");
			    if (r == false) {
			        $('input[name=dater_]').val('');
			        $('input[name=lot_]').val('');
			    } 
			 }
			 if(parseInt(response.last_lot) >parseInt( $('input[name=lot_]').val())){
			 	var r = confirm("Ce lot "+$('input[name=lot_]').val()+" est déja exists , dérnier lot "+response.last_lot+",vous voulez le saisir ?");
			 }
		}, 'json');

		}
		else{
				this.value = '';
		}

	});


	$('#supplier_').change(function() {

		if( $.isNumeric( this.value ) && this.value>0 ){
			var options = {
				'supplier': this.value,
			};

			$.post('../ajax/price_chack.php', options, function(response) {

				$autotge = response.autotge;

				if(!$autotge)
				{
					$('input[name=entier_]').val('');
					$('input[name=tge_]').val('');
				 	$('input[name=entier_]').trigger( "change" );
				 	$('input[name=tge_]').trigger( "change" );
				}else{
					$net = 0;
					$net = parseInt($('input[name=brut_]').val())-parseInt($('input[name=tare_]').val());
					if($net!=0){
						$('input[name=entier_]').val($net);
						$('input[name=tge_]').val($net);
						$('input[name=entier_]').trigger( "change" );
						$('input[name=tge_]').trigger( "change" );
					}
				}

				$('input[name=pu_]').val(response.price);
				$('input[name=pu_]').trigger( "change" );
				$('input[name=pd_]').val(response.prix_dechet);
				$('input[name=pd_]').trigger( "change" );
				$('input[name=pp_]').val(response.prix_port);
				$('input[name=pp_]').trigger( "change" );
			}, 'json');
		}
		else{
			$('input[name=pu_]').val('');
			$('input[name=pd_]').val('');
			$('input[name=pp_]').val('');
		}
	});


	$('input[name=pd_]').change(function() { 
		var vpd = 0;
		if(this.value !=='' && $.isNumeric( this.value ) && $('input[name=dechet_]').val() !==''){
			 vpd = parseInt($('input[name=dechet_]').val())*parseFloat(this.value);
			$('#vpd').text(vpd.toFixed(2) );
			if($('#vpe').text()!=='')
				vpd = vpd + parseInt($('#vpe').text());
			$('#vt').text(vpd.toFixed(2) );
		}
		else{
			if(!$.isNumeric( this.value ))
				this.value = '';
			$('#vpd').text('');
			if($('#vpe').text()!=='')
				{vpd = vpd + parseInt($('#vpe').text());
				$('#vt').text(vpd.toFixed(2) );}
			else
				$('#vt').text('');
		}

	});

	$('input[name=tge_]').change(function() { 
		if(this.value !=='' && $.isNumeric( this.value ) ){
			 var tge = parseInt(this.value);
			 $('#tonnage').text(tge +' kg');
			 if($('input[name=pp_]').val() !=='' && $.isNumeric( $('input[name=pp_]').val() )){
				var tge = tge*parseFloat($('input[name=pp_]').val());
				$('#vp').text(tge.toFixed(2) );
			}	
			if($('#net_rec').text() !=='' ){
				var deftge = parseInt($('#net_rec').text())-tge;
				$('#deftge').text(deftge +' Kg');
			}	
		}
		else{
			if(!$.isNumeric( this.value ))
				this.value = '';
			 $('#tonnage').text('');
			 $('#deftge').text('');
		}
	});	

	$('input[name=pp_]').change(function() { 
		if(this.value !=='' && $.isNumeric( this.value ) && $('input[name=tge_]').val() !==''){
			var vp = parseInt($('input[name=tge_]').val())*parseFloat(this.value);
			$('#vp').text(vp.toFixed(2) );
		}
		else
			{$('#vp').text('');
			if(!$.isNumeric( this.value ))
			this.value = '';}
	});	

}) ;



})(jQuery);
/*function imprimer_bloc() {
// Définition de la zone à imprimer

var zone = document.getElementById("imprime").innerHTML;
// Ouverture du popup
var fen = window.open("", "", "height=1, width=1,toolbar=0, menubar=0,scrollbars=1,resizable=0,status=0, location=1, left=10, top=10");
 
// style du popup
fen.document.body.style.color = '#000000';
fen.document.body.style.backgroundColor = '#FFFFFF';
fen.document.body.style.padding = "20px";

// Ajout des données a imprimer
fen.document.title = "imprimere";
fen.document.body.innerHTML += " " + zone + " ";

// Impression du popup
fen.window.print();
//Fermeture du popup
fen.window.close();
return true;
}*/
function imprimer_bloc() {
// Définition de la zone à imprimer

var zone = document.getElementById("imprime").innerHTML;
// Ouverture du popup
var fen = window.open("", "", "height=0, width=0,toolbar=0, menubar=0,scrollbars=1,visible=0,resizable=1,status=0, location=1, left=1000, top=1000");
 
// style du popup
fen.document.body.style.color = '#000000';
fen.document.body.style.backgroundColor = '#FFFFFF';
fen.document.body.style.padding = "20px";
 
// Ajout des données a imprimer
fen.document.title = "imprimere";
fen.document.body.innerHTML += '<STYLE type="text/css" media="print"> @page { size: landscape; }</STYLE>';
fen.document.body.innerHTML += " " + zone + " ";

// Impression du popup
fen.window.print();
//Fermeture du popup
fen.window.close();
return true;
}