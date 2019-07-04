$( document ).ajaxError(function( event, request, settings) {
    $('#notificationBody').html("<li>Error requesting page " + settings.url + "</li>");
  $('#notificationModal').modal('show');
});

jQuery(document).ready(function() {
    $('.sendInvoice').on('click', function(){
        var invId = this.id; 
        $('#emailModal').modal('show');        

        $('#sendEmailBtn').on('click', function(){   
            $.post("/dashboard/invoice/send",
            {
                id: invId,
                email: $('#emailInput').val(),
                subject: $('#subjectInput').val(),
                body: $('#bodyInput').val()
            },
            function(data, status){  
                if(data[0]['status']!="ok" || status != "success"){
                    $('#notificationBody').html('<div class="alert alert-dismissible alert-danger"><strong>Error sending invoice:</strong></br><p>' + data[0] ? data[0]['data'][0] : status + '</p></div>');                    
                    $('#notificationModal').modal('show');                     
                }
                else{
                    $('#notificationBody').html('<div class="alert alert-dismissible alert-success"><strong>Success!</strong></br><p>'+ data[0]['data'][0] + '</p></div>');
                    $('#emailModal').modal('hide'); 
                    $('#notificationModal').modal('show');                   
                }
            });
        }); 
    });
});
