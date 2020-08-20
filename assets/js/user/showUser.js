$('#UserAddOrganization').on('click', function () {

    $.getJSON("/dashboard/organization/list",
        {},
        function (data, status) {
            $("#AddOrganizationList").html();
            data[0].data.organizations.forEach(function (el) {
                $("#AddOrganizationList").append($('<option>', {
                    value: el.id,
                    text: el.name,
                }));
            });
        });

    $('#addOrganizationModal').modal('show');
    $('#AddOrganization').on('click', function () {
        $.post("/admin/user/addOrganization",
            {
                userId: $('#userId').val(),
                organizationId: $('#AddOrganizationList').val(),
            },
            function (data, status) {
                $('#addOrganizationModal').modal('hide');
                $('#organizationList').append("<li><a href='/dashboard/organization/" + data[0].data.organization.id + "'> " + data[0].data.organization.fullAddress + "</a></li>");
            });
    });
});

// Handling the modal confirmation message.
$(document).on('submit', 'form[data-confirmation]', function (event) {
    var $form = $(this),
        $confirm = $('#confirmationModal');

    if ($confirm.data('result') !== 'yes') {
        //cancel submit event
        event.preventDefault();

        $confirm
            .off('click', '#btnYes')
            .on('click', '#btnYes', function () {
                $confirm.data('result', 'yes');
                $form.find('input[type="submit"]').attr('disabled', 'disabled');
                $form.submit();
            })
            .modal('show');
    }
});

