import 'eonasdan-bootstrap-datetimepicker';
import moment from 'moment';

$(function() {
    // Datetime picker initialization.
    // See http://eonasdan.github.io/bootstrap-datetimepicker/
    $('#invoice_dateOfIssue').datetimepicker({
        locale: 'si',
        format: 'dd. mm. yyyy',
    });

    $('#invoice_dueDate').datetimepicker({
        locale: 'si',
        format: 'dd. mm. yyyy',
    });

     $('#invoice_dateServiceRenderedFrom').datetimepicker({
        locale: 'si', 
        format: 'dd. mm. yyyy',
    });

     $('#invoice_dateServiceRenderedTo').datetimepicker({
        locale: 'si',
        format: 'dd. mm. yyyy',
        useCurrent: false //Important! See issue #1075        
    });

    $("#invoice_dateServiceRenderedFrom").on("dp.change", function (e) {
            $('#invoice_dateServiceRenderedTo').data("DateTimePicker").minDate(e.date);
        });
        $("#invoice_dateServiceRenderedTo").on("dp.change", function (e) {
            $('#invoice_dateServiceRenderedFrom').data("DateTimePicker").maxDate(e.date);
        });

});

var $collectionHolder;
var $addInvoiceItemButton = $('<tr class="table-primary"><td colspan="6"><a id="add-invoice-item" class="btn btn-sm btn-block btn-success"><i class="fa fa-plus" aria-hidden="true"></i></a></td></tr>');


jQuery(document).ready(function() {
    // Get the ul that holds the collection of tags
    $collectionHolder = $('tbody.invoiceItems');
    
    $collectionHolder.data('index', $collectionHolder.find(':input').length);
    addInvoiceItemForm($collectionHolder, $addInvoiceItemButton, 1);
        
    $('#add-invoice-item').on('click', function() {
        // add a new tag form (see next code block)
        addInvoiceItemForm($collectionHolder, $addInvoiceItemButton, 1);        
    });

    $('#invoice_dueDate').on('dp.change', function(){
       var dueDate = moment($(this).data("DateTimePicker").date());
       var issueDate = moment($('#invoice_dateOfIssue').data("DateTimePicker").date());
       var days = dueDate.diff(issueDate, 'days');       
       $('#invoice_dueInDays').val(days);
    });

    $('#invoice_dateOfIssue').on('dp.change', function(){
        var issueDate = moment($(this).data("DateTimePicker").date());
        $('#invoice_dateServiceRenderedTo').data("DateTimePicker").date(issueDate.format('L'));
        
        var days = $('#invoice_dueInDays').val();    
        var date = issueDate.add(days, 'days').format('L');  
        $('#invoice_dueDate').data("DateTimePicker").date(date);
        
    });

    $('#invoice_dueInDays').on('change', function(){
        var issueDate = moment($('#invoice_dateOfIssue').data("DateTimePicker").date());
        var date = issueDate.add($(this).val(), 'days').format('L');       
        $('#invoice_dueDate').data("DateTimePicker").date(date);
    });
    
});

function addInvoiceItemForm($collectionHolder, $addRemoveInvoiceItemButtons, $number) {
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
        var $newFormLi = $('<tr class="invoice-item-tr-' + index + '">'+
        '<td>' + $('#invoice_invoiceItems_' + index + '_code', newForm).parent().html() + '</td>'+
        '<td data-item-index="' + index + '">' + $('#invoice_invoiceItems_' + index + '_name' ,newForm).parent().html() + '</td>'+
        '<td>' + $('#invoice_invoiceItems_' + index + '_quantity', newForm).parent().html()+'</td>'+
        '<td>' + $('#invoice_invoiceItems_' + index + '_unit', newForm).parent().html()+'</td>'+
        '<td>' + $('#invoice_invoiceItems_' + index + '_price', newForm).parent().html()+'</td>'+
        '<td>' + $('#invoice_invoiceItems_' + index + '_discount', newForm).parent().html()+'</td>'+
        '<td><a id="remove-invoice-item'+ index +'" class="btn btn-sm btn-block btn-danger"><i class="fa fa-minus" aria-hidden="true"></i></a></td></tr>');        
        
        $collectionHolder.append($newFormLi);
        $collectionHolder.append($addRemoveInvoiceItemButtons);

        $('#remove-invoice-item'+index).on('click', function(e) {        
            removeInvoiceItemForm($collectionHolder, index);        
        }); 
    }    
}

function removeInvoiceItemForm($collectionHolder, index) {
    if(index < 1)
    {
        alert("Can't delete last item!");
        return;
    }
    //$collectionHolder.data('index', index);
    $('.invoice-item-tr-' + index, $collectionHolder).remove()
    
}