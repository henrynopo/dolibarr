(function($){

$( document ).ready(function(){
$('select[name=delay_]').change(function() {
		if( $.isNumeric( this.value ) ){
			if(parseInt(this.value)==0)
				$('input[name=note_]').val('3');
			else if(parseInt(this.value)>0 && parseInt(this.value)<3)
				$('input[name=note_]').val('2');
			else if(parseInt(this.value)>2)
				$('input[name=note_]').val('1');
		}
		else {if(!$.isNumeric( this.value ))
							this.value = '';
			$('input[name=note_]').val('');}
			quality = ($('input[name=quality_]').val()!=='')? parseInt($('input[name=quality_]').val()): 0 ;
			bl = ($('input[name=bl_]').val()!=='')? parseInt($('input[name=bl_]').val()): 0 ;
			note = ($('input[name=note_]').val()!=='')? parseInt($('input[name=note_]').val()): 0 ;
			total = note+ quality+ bl ;
			$('#total').text(total);
			if(total<5)
				$('#class').text('B');
			else
				$('#class').text('A');

	});

$('input[name=quality_]').change(function() {
		if( $.isNumeric( this.value ) ){
			note = ($('input[name=note_]').val()!=='')? parseInt($('input[name=note_]').val()): 0 ;
			bl = ($('input[name=bl_]').val()!=='')? parseInt($('input[name=bl_]').val()): 0 ;
			total = parseInt(this.value) + note+ bl ;
			$('#total').text(total);
			if(total<5)
				$('#class').text('B');
			else
				$('#class').text('A');
		}
		else {if(!$.isNumeric( this.value ))
							this.value = '';
			quality = ($('input[name=quality_]').val()!=='')? parseInt($('input[name=quality_]').val()): 0 ;
			bl = ($('input[name=bl_]').val()!=='')? parseInt($('input[name=bl_]').val()): 0 ;
			note = ($('input[name=note_]').val()!=='')? parseInt($('input[name=note_]').val()): 0 ;
			total = note+ quality+ bl ;
			$('#total').text(total);
			if(total<5)
				$('#class').text('B');
			else
				$('#class').text('A');}
	});

$('input[name=bl_]').change(function() {
		if( $.isNumeric( this.value ) ){
			note = ($('input[name=note_]').val()!=='')? parseInt($('input[name=note_]').val()): 0 ;
			quality = ($('input[name=quality_]').val()!=='')? parseInt($('input[name=quality_]').val()): 0 ;
			total = parseInt(this.value) + note+ quality ;
			$('#total').text(total);
			if(total<5)
				$('#class').text('B');
			else
				$('#class').text('A');
		}else 
		{if(!$.isNumeric( this.value ))
							this.value = '';
			quality = ($('input[name=quality_]').val()!=='')? parseInt($('input[name=quality_]').val()): 0 ;
			bl = ($('input[name=bl_]').val()!=='')? parseInt($('input[name=bl_]').val()): 0 ;
			note = ($('input[name=note_]').val()!=='')? parseInt($('input[name=note_]').val()): 0 ;
			total = note+ quality+ bl ;
			$('#total').text(total);
			if(total<5)
				$('#class').text('B');
			else
				$('#class').text('A');}
	});

}) ;



})(jQuery);