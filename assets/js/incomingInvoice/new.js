import 'eonasdan-bootstrap-datetimepicker';
import moment from 'moment';

$(function() {
    // Datetime picker initialization.
    // See http://eonasdan.github.io/bootstrap-datetimepicker/
    $('#incoming_invoice_dateOfIssue').datetimepicker({
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

    $('#incoming_invoice_dueDate').datetimepicker({
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

    $('#incoming_invoice_dueDate').on('dp.change', function(){
       var dueDate = moment($(this).data("DateTimePicker").date());
       var issueDate = moment($('#incoming_invoice_dateOfIssue').data("DateTimePicker").date());
       var days = dueDate.diff(issueDate, 'days');       
       $('#incoming_invoice_dueInDays').val(days);
    });

    $('#incoming_invoice_dateOfIssue').on('dp.change', function(){
        var issueDate = moment($(this).data("DateTimePicker").date());
        
        var days = $('#invoice_dueInDays').val();    
        var date = issueDate.add(days, 'days').format('L');  
        $('#incoming_invoice_dueDate').data("DateTimePicker").date(date);
        
    });

    $('#incoming_invoice_dueInDays').on('change', function(){
        var issueDate = moment($('#incoming_invoice_dateOfIssue').data("DateTimePicker").date());
        var date = issueDate.add($(this).val(), 'days').format('L');       
        $('#incoming_invoice_dueDate').data("DateTimePicker").date(date);
    });        
});