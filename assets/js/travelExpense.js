import 'eonasdan-bootstrap-datetimepicker';
import 'typeahead.js';
import Bloodhound from "bloodhound-js";
import 'bootstrap-tagsinput';

$(function() {
    // Datetime picker initialization.
    // See http://eonasdan.github.io/bootstrap-datetimepicker/
    $('#datetimepicker').datetimepicker({
        icons: {
            locale: 'si',
            time: 'fa fa-clock-o',
            date: 'fa fa-calendar',
            up: 'fa fa-chevron-up',
            down: 'fa fa-chevron-down',
            previous: 'fa fa-chevron-left',
            next: 'fa fa-chevron-right',
            today: 'fa fa-check-circle-o',
            clear: 'fa fa-trash',
            close: 'fa fa-remove'
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
   
    var value = $('#travel_expense_travelStops_0_post option:contains("Škofja Loka")', $collectionHolder)[0].value;
    $('#travel_expense_travelStops_0_post', $collectionHolder)[0].value = value;
    $('#travel_expense_travelStops_0_distanceFromPrevious', $collectionHolder).val(0);
    

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
        var $newFormLi = $('<tr class="travel-stop-tr-' + index + '"><td>' + $('#travel_expense_travelStops_' + index + '_stopOrder', newForm).parent().html() + 
            '</td><td class="post-Selector" data-stop-index="' + index + '">' + $('#travel_expense_travelStops_' + index + '_post' ,newForm).parent().html() + '</td><td>' + 
            $('#travel_expense_travelStops_' + index + '_distanceFromPrevious', newForm).parent().html()+'</td><td><a id="remove-travel-stop'+ index +'" class="btn btn-sm btn-block btn-danger"><i class="fa fa-minus" aria-hidden="true"></i></a></td></tr>');        

        $('#travel_expense_travelStops_' + index + '_stopOrder', $newFormLi).val(index+1); 
        $('#travel_expense_travelStops_' + index + '_post', $newFormLi).val('');
        
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
    if(rowCount > (parseInt(index) + 1))
    {        
        origin["country"]='Slovenija';
        destination["country"]='Slovenija';
        
        origin["city"] = $('#travel_expense_travelStops_' + index + '_post option:selected', $collectionHolder)[0].text;
        destination["city"] = $('#travel_expense_travelStops_' + (parseInt(index) + 1) + '_post option:selected', $collectionHolder)[0].text;
        
        origin["address"]='';
        destination["address"]='';

        getDistanceOSM(origin, destination, function(distance){
            $('#travel_expense_travelStops_' + (parseInt(index)+1) + '_distanceFromPrevious', $collectionHolder).val((index == 0) ? 0 : distance/1000);   
        });   
        // getDistance(origin, destination, function(distance){
        //     $('#travel_expense_travelStops_' + (parseInt(index)+1) + '_distanceFromPrevious', $collectionHolder).val((index == 0) ? 0 : distance/1000);   
        // });  
    }    
    if (index > 0)
    {        
        origin["country"]='Slovenija';
        destination["country"]='Slovenija';
        
        origin["city"] = $('#travel_expense_travelStops_' + (parseInt(index) - 1) + '_post option:selected', $collectionHolder)[0].text;
        destination["city"] = $('#travel_expense_travelStops_' + index + '_post option:selected', $collectionHolder)[0].text;
        
        origin["address"]='';
        destination["address"]='';

        getDistanceOSM(origin, destination, function(distance){
            $('#travel_expense_travelStops_' + index + '_distanceFromPrevious', $collectionHolder).val((index == 0) ? 0 : distance/1000);   
        }); 
        
        
        // getDistance(origin, destination, function(distance){
        //     $('#travel_expense_travelStops_' + index + '_distanceFromPrevious', $collectionHolder).val((index == 0) ? 0 : distance/1000);   
        // });             
    }
}


/* Only post: https://nominatim.openstreetmap.org/search?format=json&city=Škofja Loka&country=Slovenija
       Address:   https://nominatim.openstreetmap.org/search?format=json&city=Škofja Loka&country=Slovenija&q=Groharjevo naselje 8

    =>

    [
    {
        "place_id": "61460287",
        "licence": "Data © OpenStreetMap contributors, ODbL 1.0. https://osm.org/copyright",
        "osm_type": "node",
        "osm_id": "5053206923",
        "boundingbox": [
            "46.1723716",
            "46.1724716",
            "14.3041488",
            "14.3042488"
        ],
        "lat": "46.1724216",
        "lon": "14.3041988",
        "display_name": "8, Groharjevo naselje, Podlubnik, Škofja Loka, Gorenjska, 4220, Slovenija",
        "class": "place",
        "type": "house",
        "importance": 0.31100000000000005
    }
    ]
    + Ljubljana...
    
    =>    
    http://router.project-osrm.org/route/v1/driving/14.3041988,46.1724216;14.5462620077364,46.06558745
    =>
    {
    "routes": [
        {
            "geometry": "cayxGuyhvAqt@{iAxPij@vi@hNtJuDfGyPd@_}@iG}{@hSg|@tSgk@nTyUdJ}d@kIwfC~GoN~LiHly@{wA`k@aD`IeYfWkTx`@}v@nHmk@liBifAzgAscBre@fOlxAXf`AfRo@cHoWuLcUmf@gs@s}CcFk}@bJaaC`o@ocCbb@yr@~LzGzGt]",
            "legs": [
                {
                    "summary": "",
                    "weight": 2422.4,
                    "duration": 2217.4,
                    "steps": [],
                    "distance": 31166.6
                }
            ],
            "weight_name": "routability",
            "weight": 2422.4,
            "duration": 2217.4,
            "distance": 31166.6
        }
    ],
    "waypoints": [
        {
            "hint": "eyssibMrLIkXAAAAAwAAAB8AAAAAAAAAP-VWQbCEwD8wZY9BAAAAABcAAAADAAAAHwAAAAAAAACBpwAArUTaAFaJwALHQ9oABonAAgEAnwk8GrCu",
            "distance": 19.86255613275875,
            "name": "",
            "location": [
                14.304429,
                46.172502
            ]
        },
        {
            "hint": "cc0nif___38CAAAADQAAAAAAAAA8AAAAOaytP6eb_j8AAAAAPuRxQQIAAAAHAAAAAAAAAB0AAACBpwAAfPXdAHvnvgJW9d0As-e-AgAADwQ8GrCu",
            "distance": 6.883789797450628,
            "name": "",
            "location": [
                14.5463,
                46.065531
            ]
        }
    ],
    "code": "Ok"
}
   */

function getDistanceOSM(origin, destination, callback){
    geocodeOsm(origin, destination, function(origin, destination){
        //alert(origin.lat +'\n'+ origin.lon +'\n'+ destination.lat +'\n'+ destination.lon)
        var url = 'http://router.project-osrm.org/route/v1/driving/' + origin.lon + "," + origin.lat + ";" + destination.lon + "," + destination.lat;
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
                    callback(response.routes[0].distance); 
                }
            },
            error: function(error){
                alert(error.responseText);
            }
        });
    });
}

function geocodeOsm(origin, destination, callback){
    var originCoordinates = [];
    var destinationCoordinates = [];
    var baseUrl = 'https://nominatim.openstreetmap.org/search/?format=json&country=';

    var url = baseUrl + origin.country +'&city=' + origin.city;
    if(origin.address != '') 
        url += '&q=' + origin.address;
    $.getJSON(url, function(response, status) {
        if (status !== 'success') {
            alert('Error was: ' + status);
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
                    alert('Error was: ' + status);
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

