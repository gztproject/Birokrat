import 'eonasdan-bootstrap-datetimepicker';
import 'typeahead.js';
import Bloodhound from "bloodhound-js";
import 'bootstrap-tagsinput';

$( document ).ready(function() {
    $('#travel_expense_date').datetimepicker({
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
                }
    });

    // Bootstrap-tagsinput initialization
    // http://bootstrap-tagsinput.github.io/bootstrap-tagsinput/examples/
    var $input = $('input[data-toggle="tagsinput"]');
    if ($input.length) {
        var source = new Bloodhound({
            local: $input.data('tags'),
            queryTokenizer: Bloodhound.tokenizers.whitespace,
            datumTokenizer: Bloodhound.tokenizers.whitespace
        });
        source.initialize();

        $input.tagsinput({
            trimValue: true,
            focusClass: 'focus',
            typeaheadjs: {
                name: 'tags',
                source: source.ttAdapter()
            }
        });
    }
});


var $collectionHolder;
var $addTravelStopButton = $('<tr class="table-primary"><td colspan="4"><a id="add-travel-stop" class="btn btn-sm btn-block btn-success"><i class="fa fa-plus" aria-hidden="true"></i></a></td></tr>');


jQuery(document).ready(function() {
    // Get the ul that holds the collection of tags
    $collectionHolder = $('tbody.travelStops');
    
    $collectionHolder.data('index', $collectionHolder.find(':input').length);
    addTravelStopForm($collectionHolder, $addTravelStopButton, 2);
   
    var value = $('#travel_expense_createTravelStopCommands_0_post option:contains("Škofja Loka")', $collectionHolder)[0].value;
    $('#travel_expense_createTravelStopCommands_0_post', $collectionHolder)[0].value = value;
    $('#travel_expense_createTravelStopCommands_0_distanceFromPrevious', $collectionHolder).val(0);
    

    $('#add-travel-stop').on('click', function(e) {
        // add a new tag form (see next code block)
        addTravelStopForm($collectionHolder, $addTravelStopButton, 1);        
    });
});

function addTravelStopForm($collectionHolder, $addRemoveTravelStopButtons, $number) {
    for(var i=0; i<$number;i++){
        // Get the data-prototype explained earlier
        var prototype = $collectionHolder.data('prototype');

        // get the new index
        var index = $collectionHolder.data('index');

        var newForm = prototype;
        
        // Replace '__name__' in the prototype's HTML to
        // instead be a number based on how many items we have
        newForm = newForm.replace(/__name__/g, index);
        
        // increase the index with one for the next item
        $collectionHolder.data('index', index + 1);
        

        // Display the form in the page in an li, before the "Add a tag" link li
        var $newFormLi = $('<tr class="travel-stop-tr-' + index + '"><td>' + $('#travel_expense_createTravelStopCommands_' + index + '_stopOrder', newForm).parent().html() + 
            '</td><td class="post-Selector" data-stop-index="' + index + '">' + $('#travel_expense_createTravelStopCommands_' + index + '_post' ,newForm).parent().html() + '</td><td>' + 
            $('#travel_expense_createTravelStopCommands_' + index + '_distanceFromPrevious', newForm).parent().html()+'</td><td><a id="remove-travel-stop'+ index +'" class="btn btn-sm btn-block btn-danger"><i class="fa fa-minus" aria-hidden="true"></i></a></td></tr>');        

        $('#travel_expense_createTravelStopCommands_' + index + '_stopOrder', $newFormLi).val(index+1); 
        $('#travel_expense_createTravelStopCommands_' + index + '_post', $newFormLi).val('');
        
        $collectionHolder.append($newFormLi);
        $collectionHolder.append($addRemoveTravelStopButtons);

        $('#remove-travel-stop'+index).on('click', function(e) {        
            removeTravelStopForm($collectionHolder, index);        
        }); 
    }
    $(".post-Selector").prop("onchange", null).off("change");

    $('.post-Selector').on('change', function(e){
        var index = e.currentTarget.dataset.stopIndex;        
        autoFillDistance($collectionHolder, index);
    });
}

function removeTravelStopForm($collectionHolder, index) {
    // ToDo: enable removing any stop (but for the last two), automatically recalculate distances and renumber travelStopNumbers (maybe also indices).
    if(index < 2)
    {
        alert("Can't delete last stop!");
        return;
    }
    //$collectionHolder.data('index', index);
    $('.travel-stop-tr-' + index, $collectionHolder).remove()
    
}

function autoFillDistance($collectionHolder, index){
    var origin = [];
    var destination = [];
    var rowCount = $('.post-Selector', $collectionHolder).length;
    
    if (index > 0)
    {        
        origin["country"]='Slovenija';
        destination["country"]='Slovenija';
        
        origin["city"] = $('#travel_expense_createTravelStopCommands_' + (parseInt(index) - 1) + '_post option:selected', $collectionHolder)[0].text;
        destination["city"] = $('#travel_expense_createTravelStopCommands_' + index + '_post option:selected', $collectionHolder)[0].text;
        
        origin["address"]='';
        destination["address"]='';

        getDistanceOSM(origin, destination, function(distance){
            $('#travel_expense_createTravelStopCommands_' + index + '_distanceFromPrevious', $collectionHolder).val((index == 0) ? 0 : distance/1000);
            if(rowCount > (parseInt(index) + 1))
            {        
                origin["country"]='Slovenija';
                destination["country"]='Slovenija';
        
                origin["city"] = $('#travel_expense_createTravelStopCommands_' + index + '_post option:selected', $collectionHolder)[0].text;
                destination["city"] = $('#travel_expense_createTravelStopCommands_' + (parseInt(index) + 1) + '_post option:selected', $collectionHolder)[0].text;
        
                origin["address"]='';
                destination["address"]='';

                getDistanceOSM(origin, destination, function(distance){
                    $('#travel_expense_travelStops_' + (parseInt(index)+1) + '_distanceFromPrevious', $collectionHolder).val((index == 0) ? 0 : distance/1000);   
                });
            }      
        });             
    }
}

function getDistanceOSM(origin, destination, callback){
    geocodeOsm(origin, destination, function(origin, destination){
        //alert(origin.lat +'\n'+ origin.lon +'\n'+ destination.lat +'\n'+ destination.lon)
        var url = 'http://engine.osrm.gzt.si/table/v1/driving/' + origin.lon + "," + origin.lat + ";" + destination.lon + "," + destination.lat + '?sources=0&destinations=1&annotations=distance';
        $.ajax(
        {
            url: url,
            dataType: 'json',
            success: function(response) {
                if (response.code!="Ok") {
                    alert('Error was: '+ code);
                } 
                else {      
                    //alert(response.routes[0].distance); 
                    callback(response.distances[0][0]); 
                }
            },
            error: function(error){
                console.log(error.responseText);
            }
        });
    });
}

function geocodeOsm(origin, destination, callback){
    //alert("Geocoding: origin: "+origin.city+" destination: "+destination.city);
    var originCoordinates = [];
    var destinationCoordinates = [];
    var baseUrl = 'https://nominatim.openstreetmap.org/search/?format=json&country=';

    var url = baseUrl + origin.country +'&city=' + origin.city;
    if(origin.address != '') 
        url += '&q=' + origin.address;
    $.getJSON(url, function(response, status) {
        if (status !== 'success') {
            console.log('Error was: ' + status);
        } 
        else {   
            originCoordinates['lon'] = response[0].lon;
            originCoordinates['lat'] = response[0].lat;          
            //alert('Place:'+ response[0].display_name +' Lat:'+response[0].lat +' Lon:'+ response[0].lon); 

            url = baseUrl + destination.country +'&city=' + destination.city;
            if(destination.address != '') 
                url += '&q=' + destination.address;
            $.getJSON(url, function(response, status) {
                if (status !== 'success') {
                    console.log('Error was: ' + status);
                } 
                else {   
                    destinationCoordinates['lon'] = response[0].lon;
                    destinationCoordinates['lat'] = response[0].lat;          
                    //alert('Place:'+ response[0].display_name +' Lat:'+response[0].lat +' Lon:'+ response[0].lon);   
                    callback(originCoordinates, destinationCoordinates);        
                }
            });          
        }
    });
}


// //obsolete - moved to OSM 'cause it's free :)
// function getDistance(origin, destination, callback){
//     var service = new google.maps.DistanceMatrixService();
    
//     service.getDistanceMatrix(
//     {
//         origins: [origin],
//         destinations: [destination],
//         travelMode: 'DRIVING',
//         unitSystem: google.maps.UnitSystem.METRIC,
//         avoidHighways: false,
//         avoidTolls: false
//     }, 
//     function (response, status) {
//         if (status !== 'OK') {
//             alert('Error was: ' + status);
//         } 
//         else {             
//             callback(response.rows[0].elements[0].distance.value);           
//         }
//     });
// }

