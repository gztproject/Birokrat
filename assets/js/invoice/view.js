$( document ).ajaxError(function( event, request, settings) {
    $('#notificationBody').html("<li>Error requesting page " + settings.url + "</li>");
  $('#notificationModal').modal('show');
});

jQuery(document).ready(function() {
    $('.sendInvoice').on('click', function(){         
        $.post("/dashboard/invoice/send",
        {
            id: this.id
        },
        function(data, status){  
            if(data[0]['status']=="ok"){
            }
            else{
                $('#notificationBody').html("<li>Error sending invoice: " + data[0]['data'][0] + "</li>");
                $('#notificationModal').modal('show');
            }
        }); 
    });
});
