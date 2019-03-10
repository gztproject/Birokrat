
$('#addAddressBtn').on('click', function(){          
    $('#addAddressModal').modal('show');
    $('#createAddressBtn').on('click', function(){            
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
            $('#clientAddress').append($('<option>', {
                value: data[0].data.address.id,
                text: data[0].data.address.fullAddress
            }));
        });
    });
});
