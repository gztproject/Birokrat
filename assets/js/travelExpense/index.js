import 'eonasdan-bootstrap-datetimepicker';
import moment from 'moment-timezone';
import queryString from 'query-string';

$(function(){
    var isAnyChecked = false;
    $('#travelExpensesTable').find('input[type="checkbox"]:checked').each(function(){
        isAnyChecked = true;
    });   
    if(isAnyChecked){
        $('#bookCheckedTEs').show();
        $('#bookVisibleTEs').hide();
    } 
    else{
        $('#bookCheckedTEs').hide();
        $('#bookVisibleTEs').show();
    } 
    $('#modalDate').datetimepicker({
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
    });
});

$( document ).ready(function(){
   $('#bookCheckedTEs').on('click', function(e){     
        $('#travelExpensesTable').find('input[type="checkbox"]:checked').each(function(){
            console.log("Booking TE id:" + this.value);
        });
    });

    $('#bookVisibleTEs').on('click', function(e){ 
        //e.stopPropagation();  
        var filter = queryString.parse(location.search);        
                   
        $("#dateId").val($(this).val());
        $('#modalDate').data("DateTimePicker").date(moment(new Date(), 'dd. mm. yyyy'));
        $('#dateModal').modal('show');        
        $('#submitDate').on('click', function(){        
            var date = moment($('#modalDate').data("DateTimePicker").date()).tz('Europe/Belgrade');
            $.post("/dashboard/travelExpense/bookInBundle/withFilter",
            {
                dateFrom: filter.dateFrom,
                dateTo: filter.dateTo,
                booked: filter.booked,
                unbooked: filter.unbooked,        
                date: date.format()
            },
            function(){
                    $('#submitDate').off('click');
                    $('#dateModal').modal('hide');
                    location.reload();                    
            });
        })        
    });

    $('.TECheckBox').on('change', function(e){
        var isAnyChecked = false;
        $('#travelExpensesTable').find('input[type="checkbox"]:checked').each(function(){
            isAnyChecked = true;
        });   
        if(isAnyChecked){
            $('#bookCheckedTEs').show();
            $('#bookVisibleTEs').hide();
        } 
        else{
            $('#bookCheckedTEs').hide();
            $('#bookVisibleTEs').show();
        } 
    });
});