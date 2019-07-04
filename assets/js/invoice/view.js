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
                $('#notificationBody').html('<div class="alert alert-dismissible alert-success"><strong>Success!</strong></br><p>'+ data[0]['data'][0] + '</p></div>');
                $('#notificationModal').modal('show');
            }
            else{
                $('#notificationBody').html('<div class="alert alert-dismissible alert-danger"><strong>Error sending invoice:</strong></br><p>' + data[0]['data'][0] + '</p></div>');
                $('#notificationModal').modal('show');
            }
        }); 
    });
});
