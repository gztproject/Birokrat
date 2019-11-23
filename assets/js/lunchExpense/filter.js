import 'eonasdan-bootstrap-datetimepicker';
import moment from 'moment';
import withQuery from 'with-query';
import queryString from 'query-string';

$(function() {
    $('#dateFieldFrom').datetimepicker({
        format: 'DD. MM. YYYY',
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
        },
        showTodayButton: true,
        showClear: true,
        locale:'sl',
        //defaultDate: moment().startOf('month'),

    });

    $('#dateFieldTo').datetimepicker({
        format: 'DD. MM. YYYY',
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
        },
        showTodayButton: true,
        showClear: true,
        locale:'sl',
        useCurrent: false //Important! See issue #1075   
    });
    
    $("#dateFieldFrom").on("dp.change", function (e) {
        $('#dateFieldTo').data("DateTimePicker").minDate(e.date);
    });
    
    if(typeof queryString.parse(location.search)['unbooked'] == 'undefined')
        $('#showUnbooked').prop('checked', true);

    if(typeof queryString.parse(location.search)['dateFrom'] !== 'undefined')
        $('#dateFieldFrom').data("DateTimePicker").date(moment.unix(queryString.parse(location.search)['dateFrom']));
    if(typeof queryString.parse(location.search)['dateTo'] !== 'undefined')
        $('#dateFieldTo').data("DateTimePicker").date(moment.unix(queryString.parse(location.search)['dateTo']));
    if(typeof queryString.parse(location.search)['unbooked'] !== 'undefined')
        $('#showUnbooked').prop('checked', queryString.parse(location.search)['unbooked']=='true');    
    if(typeof queryString.parse(location.search)['booked'] !== 'undefined')
        $('#showBooked').prop('checked', queryString.parse(location.search)['booked']=='true');

});

$( document ).ready(function(){
    $('#dateFieldFrom').on('dp.change', function(e){
        if(e.oldDate){
            var url = withQuery(document.location.href,{
                dateFrom: e.date.isValid?moment(e.date).format('X'):undefined,
                page: undefined
            });        
            document.location = url;
        }

    });

    $('#dateFieldTo').on('dp.change', function(e){
        if(e.oldDate){
            var url = withQuery(document.location.href,{
                dateTo: e.date.isValid?moment(e.date).format('X'):undefined,
                page: undefined
            });        
            document.location = url;
        }
    });

    $('#showUnbooked').on('change', function(e){
        //console.log(this.checked);
        var url = withQuery(document.location.href,{                
                unbooked: this.checked,
                page: undefined
            });        
            document.location = url;
    });

    $('#showBooked').on('change', function(e){
        //console.log(this.checked);
        var url = withQuery(document.location.href,{                
                booked: this.checked,
                page: undefined
            });        
            document.location = url;
    });

    $('#clearFilterBtn').on('click', function(e){
        var url = location.href.replace(location.search, "");
        document.location = url;
    });

    $('#selectAllBtn').on('click', function(e){
        var isAnyChecked = false;
        $('#lunchExpensesTable').find('input[type="checkbox"]:checked').each(function(){
            isAnyChecked = true;
        });

        $('#lunchExpensesTable').find('input[type="checkbox"]').each(function(){
            this.checked = !isAnyChecked;
            $(this).trigger("change");
        });
    });
});


