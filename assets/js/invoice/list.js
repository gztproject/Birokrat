

$(document).ready(function(){  
    
    $('.set-issued').on('click', function(){        
        $.post("/dashboard/invoice/issue",
            {
                id: $(this).val()
            },
            function(data, status){
                location.reload();
            });
    });

    $('.set-paid').on('click', function(){
        $.post("/dashboard/invoice/pay",
            {
                id: $(this).val()
            },
            function(data, status){
                location.reload();
            });
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