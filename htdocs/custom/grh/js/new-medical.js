
$( document ).ready(function(){

//-----------------Med : 09/11------------------------

  var now = new Date();
  var day = ("0" + now.getDate()).slice(-2);
  var month = ("0" + (now.getMonth() + 1)).slice(-2);
  var today = (day)+"/"+(month)+"/"+now.getFullYear() ;

  $("#datecnow").attr("value", today);
  $('#status_').change(function() { 

    $status_value = this.value;
    if($status_value == 2){
          $('#datefdm').show();
          // $('#datefnow').val(today);
          $("#datefnow").attr("value", today);
  		}else{
      		$('#datefdm').hide();
          $("#datefnow").attr("value", "");

  		}
	});

// var m_datefdm = 0;

  var m_datefdm = $("#m-datefdm").attr("value");
  
  $('.m_status #status_').change(function() { 
    $status_value = this.value;
    var last_datef;
    if (m_datefdm == "" ) {
      last_datef = today;
    }else{
      last_datef = m_datefdm;
    }
    if($status_value == 2){
          $('#datefdm').show();
          $("#m-datefdm").attr("value", last_datef);
      }else{
          $('#datefdm').hide();
          $("#m-datefdm").attr("value", "");
      }
  });
//----------------------------------------------------

}) ;

