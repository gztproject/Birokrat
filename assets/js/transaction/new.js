import 'eonasdan-bootstrap-datetimepicker';
import moment from 'moment';

$(function() {
    // Datetime picker initialization.
    // See http://eonasdan.github.io/bootstrap-datetimepicker/
    $('#transaction_date').datetimepicker({
        locale: 'sl',
        format: 'dd. mm. yyyy',
        icons: {
                    time: "fa fa-clock",
                    date: "fa fa-calendar",
                    up: "fa fa-arrow-up",
                    down: "fa fa-arrow-down",
                    previous: "fa fa-arrow-left",
                    next: "fa fa-arrow-right",
                    today:"fa fa-calendar-day",
                    clear:"fa fa-backspace",
                    close:"fa fa-times"
                }
    });    
});

jQuery(document).ready(function() { 

    if($('#transaction_date').data("DateTimePicker").date() == null)
    {
        $('#transaction_date').data("DateTimePicker").date(moment());
    }

    $("#transaction_presets").on('change', function() {
        var debit=$("#transaction_presets").find(":selected").data('debit');
        var credit=$("#transaction_presets").find(":selected").data('credit');
        var description=$("#transaction_presets").find(":selected").data('description');
        $('#transaction_debitKonto option:contains("'+debit+'")').prop('selected', true);
        $('#transaction_creditKonto option:contains("'+credit+'")').prop('selected', true);
        $('#transaction_description').val(description);
    });
    
});