import modal from 'bootstrap';

$(document).ready(function(){
    $('#addAddressBtn').on('click', function(){          
        $('#addAddressModal').modal('show');
        $('#createAddress').on('click', function(){            
            $.post("/dashboard/address/new",
            {
                address: {
                    line1: $('#address_line1').val(),
                    line2: $('#address_line2').val(),
                    post: $('#address_post').val(),
                    _token:	$('#address__token').val()
                }
            },
            function(data, status){
                $('#addAddressModal').modal('hide');
                console.log(data);
                //location.reload();
            });
        });
    });
});
