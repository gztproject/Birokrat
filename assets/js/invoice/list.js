import 'eonasdan-bootstrap-datetimepicker';
import moment from 'moment-timezone';
import modal from 'bootstrap';

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
    $('.set-issued').on('click', function(){         
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

    $('.set-paid').on('click', function(){
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

    $('.cancel').on('click', function(){          
        $("#cancelId").val($(this).val());
        $('#cancelReasonModal').modal('show');
        $('#submitCancel').on('click', function(){
            if($('#cancelReason').val() == ""){
                alert("You must enter a reason.")
                return;
            }
            $.post("/dashboard/invoice/cancel",
            {
                id: $('#cancelId').val(),
                reason: $('#cancelReason').val() 
            },
            function(data, status){
                $('#cancelReasonModal').modal('hide');
                location.reload();
            });
        });
    });
});