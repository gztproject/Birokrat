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
   
    $('.set-paid').on('click', function(e){
        e.stopPropagation();
        $("#dateId").val($(this).val());
        $('#modalDate').data("DateTimePicker").date(moment(new Date(), 'dd. mm. yyyy'));
        $('#dateModal').modal('show');        
        $('#submitDate').on('click', function(){        
            var date = moment($('#modalDate').data("DateTimePicker").date()).tz('Europe/Belgrade');
            $.post("/dashboard/incomingInvoice/pay",
                {
                    id: $('#dateId').val(),
                    date: date.format(),
                    mode: $('#modalPaymentMethod').val()
                },
                function(){
                        $('#submitDate').off('click');
                        $('#dateModal').modal('hide');
                        location.reload();                    
                });
        })
    });

    $('.reject').on('click', function(e){
        e.stopPropagation();
        $("#rejectId").val($(this).val());
        $('#rejectReasonModal').modal('show');
        $('#submitReject').on('click', function(){
            if($('#rejectReason').val() == ""){
                alert("You must enter a reason.")
                return;
            }
            $.post("/dashboard/incomingInvoice/reject",
            {
                id: $('#rejectId').val(),
                reason: $('#rejectReason').val() 
            },
            function(data, status){
                $('#rejectReasonModal').modal('hide');
                location.reload();
            });
        });
    });

    $(".invoiceRow").on('click', function (e) {

        var id = $(this).data('id');
        var url = "";
        if (window.location.pathname.endsWith("dashboard"))
            url += "dashboard/";
        url += "incomingInvoice/" + id + "/show";
        window.location = url;
});
});