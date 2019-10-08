$('#addPostBtn').on('click', function(){  
    //Set the country
    console.log($('#country_name')[0].innerText);
    $('#post_country option').filter(function() { 
        return ($(this).text() == $('#country_name')[0].innerText);
    }).prop('selected', true);
    
    $('#addPostModal').modal('show');
    $('#submitPostBtn').on('click', function(){            
        $.post("/dashboard/codesheets/post/new",
        {
            post: {
                name: $('#post_name').val(),
                code: $('#post_code').val(),
                codeInternational: $('#post_codeInternational').val(),
                country: $('#post_country').val(),
                _token:	$('#post__token').val()
            }
        },
        function(data, status){
            $('#addPostModal').modal('hide');
            location.reload();        
        });
    });
});
