import 'eonasdan-bootstrap-datetimepicker';
import moment from 'moment-timezone';

$( document ).ajaxError(function( event, request, settings) {
    $('#notificationBody').html("<li>Error requesting page " + settings.url + "</li>");
  $('#notificationModal').modal('show');
});

$(function() {
    $('#modalDate').datetimepicker({
        locale: 'sl',
        format: 'dd. mm. yyyy',
    });   
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

    $('.set-issued').on('click', function(e){ 
        e.stopPropagation();        
        $("#dateId").val($(this).val());
        $('#modalDate').data("DateTimePicker").date(moment(new Date(), 'dd. mm. yyyy'));
        $('#dateModal').modal('show');        
        $('#submitDate').on('click', function(){        
            var date = moment($('#modalDate').data("DateTimePicker").date()).tz('Europe/Belgrade');
            $.post("/dashboard/invoice/issue",
                {
                    id: $('#dateId').val(),
                    date: date.format()
                },
                function(){
                        $('#submitDate').off('click');
                        $('#dateModal').modal('hide');
                        location.reload();                    
                });
        })
    });

    $('.set-paid').on('click', function(e){
        e.stopPropagation();
        $("#dateId").val($(this).val());
        $('#modalDate').data("DateTimePicker").date(moment(new Date(), 'dd. mm. yyyy'));
        $('#dateModal').modal('show');        
        $('#submitDate').on('click', function(){        
            var date = moment($('#modalDate').data("DateTimePicker").date()).tz('Europe/Belgrade');
            $.post("/dashboard/invoice/pay",
                {
                    id: $('#dateId').val(),
                    date: date.format()
                },
                function(){
                        $('#submitDate').off('click');
                        $('#dateModal').modal('hide');
                        location.reload();                    
                });
        })
    });
});
