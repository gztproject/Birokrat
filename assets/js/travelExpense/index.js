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
});

$( document ).ready(function(){
   $('#bookCheckedTEs').on('click', function(e){     
        $('#travelExpensesTable').find('input[type="checkbox"]:checked').each(function(){
            console.log("Booking TE id:" + this.value);
        });
    });

    $('#bookVisibleTEs').on('click', function(e){  
        var filter = queryString.parse(location.search);
        console.log(filter);
        
        
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