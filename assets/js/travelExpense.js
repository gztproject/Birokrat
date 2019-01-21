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

jQuery(document).ready(function() {
    // Get the ul that holds the collection of tags
    $collectionHolder = $('tbody.travelStops');

    // count the current form inputs we have (e.g. 2), use that as the new
    // index when inserting a new item (e.g. 2)
    $collectionHolder.data('index', $collectionHolder.find(':input').length);
    addTravelStopForm($collectionHolder);

    $collectionHolder.on('change', function(e) {
        // add a new tag form (see next code block)
        addTravelStopForm($collectionHolder);
    });
});

function addTravelStopForm($collectionHolder) {
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
    var $newFormLi = $('<tr><td>' + $('#travel_expense_travelStops_' + index + '_stopOrder', newForm).parent().html() + 
        '</td><td>' + $('#travel_expense_travelStops_' + index + '_post' ,newForm).parent().html() + '</td><td>' + 
        $('#travel_expense_travelStops_' + index + '_distanceFromPrevious', newForm).parent().html()+'</td></tr>');

    $('#travel_expense_travelStops_' + index + '_stopOrder', $newFormLi).val(index);           
    $('#travel_expense_travelStops_' + index + '_distanceFromPrevious', $newFormLi).val((index == 0) ? 0 : Math.floor(Math.random() * 101));  

    $collectionHolder.append($newFormLi);
}
