(function($){

$( document ).ready(function(){
	
	$current_year = $("select[name='etat_year']" ).val();
	
	if ($.fn.timepicker) {
		$('.timepicker').timepicker({
			showSecond: true,
			timeOnly: true
		});
	}
	
	$('#etat_year').change(function() { 
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


	$('input[name=datev_]').change(function() { 
		year = this.value.split('/')[2];
		if( $.isNumeric( year ) && year>0){
			var options = {
			'year_lot': parseInt(year)
		};

		$.post('../ajax/month_eval.php', options, function(response) {
				$('#lot_').empty();
				$('input[name=quantity_]').val('');
				 $('#lot_').append($('<option>', {
						    value: '',
						    text: ''
						}));
				$.each(response.lots, function(index, val) {
				    $('#lot_').append($('<option>', {
						    value: val,
						    text: val
						}));
				}); 
				if(response.lots.length === 0)
					alert('tous les lots de cette année sont deja evalués');
		}, 'json');
		}
	});
	$('#search_year').change(function() { 
		if( $.isNumeric( this.value ) && this.value>0){
			var options = {
			'year': this.value
		};

		$.post('../ajax/month_eval.php', options, function(response) {
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
	});

	$('#lot_').change(function() { 
		if( $.isNumeric( this.value ) && this.value>0 && $('input[name=datev_]').val()!==''){
			year = $('input[name=datev_]').val();
			year = year.split('/')[2];
			var options = {
			'lot': this.value,
			'year_lot':parseInt(year)
		};

		$.post('../ajax/quantity_recpt.php', options, function(response) {
			if(!response.check){
			$('input[name=quantity_]').val(response.quantity);

			if($('input[name=farine_]').val()!=='')
				{var rendu = (parseInt($('input[name=farine_]').val())/parseInt(response.quantity))*100;
						 rendu = rendu.toFixed(2);
						$('#rndf').text(rendu+'%');}
			if($('input[name=huile_]').val()!=='')
				{var rendu = (parseInt($('input[name=huile_]').val())/parseInt(response.quantity))*100;
				 rendu = rendu.toFixed(2);
				$('#rndh').text(rendu+'%');}
			if($('input[name=cpf_]').val()!=='' && $('input[name=farine_]').val()!==''){
				 var valeur = parseFloat($('input[name=cpf_]').val())*parseInt($('input[name=farine_]').val());
					$('#vpf').text(valeur.toFixed(2));}
					}
			else{
				alert('Cet lot est deja evalué !');
				$('#lot_').val("");
			}
							
		}, 'json');
		}
		else{
			$('input[name=quantity_]').val('');
			$('input[name=farine_]').val('');
			$('input[name=huile_]').val('');
			$('input[name=cpf_]').val('');
			$('#rndh').text('');
			$('#rndf').text('');
			$('#vpf').text('');
		}
	});

	$('input[name=farine_]').change(function() { 
		if(this.value !=='' && $.isNumeric( this.value )  && $('input[name=quantity_]').val()!==''){
				 var rendu = (parseInt(this.value)/parseInt($('input[name=quantity_]').val()))*100;
				 rendu = rendu.toFixed(2);
				$('#rndf').text(rendu+'%');
			if($('input[name=cpf_]').val()!==''){
				var valeur = parseInt(this.value)*parseFloat($('input[name=cpf_]').val());
				$('#vpf').text(valeur.toFixed(2));
			}
		} 
		else{
			if(!$.isNumeric( this.value ))
				this.value = '';
			$('#rndf').text('');
		}
	});

	$('input[name=huile_]').change(function() { 
		if(this.value !=='' && $.isNumeric( this.value ) && $('input[name=quantity_]').val()!==''){
				 var rendu = (parseInt(this.value)/parseInt($('input[name=quantity_]').val()))*100;
				 rendu = rendu.toFixed(2);
				$('#rndh').text(rendu+'%');
		}
		else{
			if(!$.isNumeric( this.value ))
				this.value = '';
			$('#rndh').text('');
		}
	});

	$('input[name=cpf_]').change(function() { 
		if(this.value !=='' && $.isNumeric( this.value ) && $('input[name=farine_]').val()!==''){
				 var valeur = parseFloat(this.value)*parseInt($('input[name=farine_]').val());
				$('#vpf').text(valeur.toFixed(2));
		}
		else{
			if(!$.isNumeric( this.value ))
				this.value = '';
			$('#vpf').text('');
		}
	});


var year =$('select[name=etat_year]').val();
			
			var options = {
			'year': parseInt(year)
}



	$.post('../ajax/graph.php', options, function(response) {
			 
var test = [
  {
            key: "quantité",
            values:	response.qtedata
            },

            {
            key: "Farine",
            values:	response.farinedata
            },
             {
            key: "Huile",
            values:	response.huiledata
            }
           

            ]
                       console.log(test);
   var chart;
    nv.addGraph(function() {
        chart = nv.models.multiBarChart()
            .duration(300)
            .margin({bottom: 100, left: 100})
            .rotateLabels(-45)
            .groupSpacing(0.1)
            .showControls(false)
            
        ;

        chart.reduceXTicks(false).staggerLabels(true);

        chart.xAxis
            .axisLabelDistance(35)
            .showMaxMin(false)
        ;

        chart.yAxis
            .axisLabel("Milliers")
            .axisLabelDistance(-5)
            .tickFormat(d3.format(',.01f'))
        ;

        chart.dispatch.on('renderEnd', function(){
            nv.log('Render Complete');
        });

        d3.select('#chart1 svg')
            .datum(test)
            .call(chart);

        nv.utils.windowResize(chart.update);

        chart.dispatch.on('stateChange', function(e) {
            nv.log('New State:', JSON.stringify(e));
        });
        chart.state.dispatch.on('change', function(state){
            nv.log('state', JSON.stringify(state));
        });

        return chart;
    });

   
		}, 'json');

		$('#etat_year').change(function() { 

var year =$('select[name=etat_year]').val();
			
			var options = {
			'year': parseInt(year)
}



	$.post('../ajax/graph.php', options, function(response) {
			 
var test = [
  {
            key: "quantité",
            values:	response.qtedata
            },

            {
            key: "Farine",
            values:	response.farinedata
            },
             {
            key: "Huile",
            values:	response.huiledata
            }
           

            ]
                       console.log(test);


   var chart;
    nv.addGraph(function() {
        chart = nv.models.multiBarChart()
            .duration(300)
            .margin({bottom: 100, left: 70})
            .rotateLabels(45)
            .groupSpacing(0.1)
        ;

        chart.reduceXTicks(false).staggerLabels(true);

        chart.xAxis
            .axisLabelDistance(35)
            .showMaxMin(false)
        ;

        chart.yAxis
            .axisLabel("Milliers")
            .axisLabelDistance(-5)
            .tickFormat(d3.format(',.01f'))
        ;

        chart.dispatch.on('renderEnd', function(){
            nv.log('Render Complete');
        });

        d3.select('#chart1 svg')
            .datum(test)
            .call(chart);

        nv.utils.windowResize(chart.update);

        chart.dispatch.on('stateChange', function(e) {
            nv.log('New State:', JSON.stringify(e));
        });
        chart.state.dispatch.on('change', function(state){
            nv.log('state', JSON.stringify(state));
        });

        return chart;
    });

   
		}, 'json');
			 });




}) ;



})(jQuery);

function imprimer_bloc() {
// Définition de la zone à imprimer

var zone = document.getElementById("imprim").innerHTML;



// Ouverture du popup
var fen = window.open("", "chart1", "height=0, width=0,toolbar=0, menubar=0,scrollbars=1,visible=0,resizable=1,status=0, location=1, left=1000, top=1000");
 
// style du popup
fen.document.body.style.color = '#000000';
fen.document.body.style.backgroundColor = '#FFFFFF';
fen.document.body.style.padding = "20px";
 var url = window.location.href; ;
 url2 = url.split("/evaluation")[0];
// Ajout des données a imprimer
fen.document.title = "imprimere";
//fen.document.body.innerHTML += " " + zone + " ";

// Impression du popup
 fen.document.head.innerHTML = '<title>IMPRIMER</title><link rel="stylesheet" type="text/css" href="'+url2+'/css/grh.css"><style>#chart1 {width: 120%;}.nvd3 text {font: 11px Arial,sans-serif;} @media print {@page {size: landscape}</style>'; 
    fen.document.body.innerHTML = '<table style="width:1200px"><tr><td width="100%">' + zone + '</td></tr></table></body></html>';
   /*fen.document.write('<html><head><title>IMPRIMER</title>');
   fen.document.write('<link rel="stylesheet" type="text/css" href="../css/grh.css"><style>#chart1 {width: 120%;}</style>');
           fen.document.write('</head><body><table style="width:1200px"><tr><td width="100%">');

            fen.document.write(zone);

            fen.document.write('</td></tr></table></body></html>');*/
fen.document.close();
 // necessary for IE >= 10
fen.focus();
   fen.print();
   fen.close();

         // necessary for IE >= 10

       /* fen.print();
        fen.close();*/
return true;
}

function imprimer_bloc2() {
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