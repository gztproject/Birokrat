import 'eonasdan-bootstrap-datetimepicker';
import moment from 'moment';

$(function() {
    // Datetime picker initialization.
    // See http://eonasdan.github.io/bootstrap-datetimepicker/
    $('#invoice_dateOfIssue').datetimepicker({
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

    $('#invoice_dueDate').datetimepicker({
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

     $('#invoice_dateServiceRenderedFrom').datetimepicker({
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

     $('#invoice_dateServiceRenderedTo').datetimepicker({
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
                },
        useCurrent: false //Important! See issue #1075        
    });

    $("#invoice_dateServiceRenderedFrom").on("dp.change", function (e) {
        $('#invoice_dateServiceRenderedTo').data("DateTimePicker").minDate(e.date);
    });
    $("#invoice_dateServiceRenderedTo").on("dp.change", function (e) {
        $('#invoice_dateServiceRenderedFrom').data("DateTimePicker").maxDate(e.date);
    });
    refreshInvNumber();
    refreshDefaultDueInDays();    
});

var $collectionHolder;
var $addInvoiceItemButton = $('<tr class="table-primary"><td colspan="7"><a id="add-invoice-item" class="btn btn-sm btn-block btn-success"><i class="fa fa-plus" aria-hidden="true"></i></a></td></tr>');


jQuery(document).ready(function() {
    // Get the ul that holds the collection of tags
    $collectionHolder = $('tbody.invoiceItems');
    
    $collectionHolder.data('index', $collectionHolder.find('tr').length);   

    if($collectionHolder.find('tr').length == 0)
    {         
        addInvoiceItemForm($collectionHolder, $addInvoiceItemButton, 1);
        setItemValue(0,0);
    }
    else
    {        
        for(var i=0;i<$collectionHolder.find('tr').length;i++)
        {        
            $('#remove-invoice-item-' + i).on('click', function(e) {   
                var index = e.target.id.split('-')[3];      
                removeInvoiceItemForm($collectionHolder, index); 
                setTotalPrice(calculateTotal());       
            });             
            $('#invoice_invoiceItemCommands_' + i + '_quantity').on('keydown', function(e){
                var index = e.target.id.split('_')[2]; 
                setTimeout(function () {       
                    setItemValue(index, calculateValue(index));
                });
            });
            $('#invoice_invoiceItemCommands_' + i + '_price').on('keydown', function(e){
                var index = e.target.id.split('_')[2]; 
                setTimeout(function () {       
                    setItemValue(index, calculateValue(index));
                });
            });
            $('#invoice_invoiceItemCommands_' + i + '_discount').on('keydown', function(e){
                var index = e.target.id.split('_')[2];
                setTimeout(function () {
                    setItemValue(index, calculateValue(index));   
                });            
            });

            $('#iiValue_' + i).on('change', function(){                 
                setTotalPrice(calculateTotal());
            });
        }
        $collectionHolder.append($addInvoiceItemButton);
        setTotalPrice(calculateTotal());
    }
        
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

    $('#invoice_issuer').on('change', function(){
        refreshInvNumber();
        refreshDefaultDueInDays();
    }); 

    $('#invoice_discount').on('keydown', function(){ 
        setTimeout(function () {                
            setTotalPrice(calculateTotal());
        });
    });
    
});

function refreshInvNumber(){
    $.post("/dashboard/invoice/getNewNumber",
        {           
            issuerId: $('#invoice_issuer option:selected').val()
        },
        function(data, status){  
            if(data[0]['status']=="ok")          
                $('#invoice_number').val(data[0]['data'][0]);
            else{
                $('#notificationBody').html("<li>Error getting invoice number: " + data[0]['data'][0] + "</li>");
                $('#notificationModal').modal('show');
            }
        }); 
}

function refreshDefaultDueInDays(){
    $.post("/dashboard/invoice/getDefaultDueInDays",
        {           
            issuerId: $('#invoice_issuer option:selected').val()
        },
        function(data, status){  
            if(data[0]['status']=="ok"){        
                $('#invoice_dueInDays').val(data[0]['data'][0]);
                $('#invoice_dueInDays').change();
            }
            else{
                $('#notificationBody').html("<li>Error getting invoice number: " + data[0]['data'][0] + "</li>");
                $('#notificationModal').modal('show');
            }
        }); 
}

function calculateValue(index){
    var qty = $('#invoice_invoiceItemCommands_'+index+'_quantity').val();    
    qty = qty=="" ? 1 : (qty.replace(',','.'))*1;
    var price = $('#invoice_invoiceItemCommands_'+index+'_price').val();
    price = price=="" ? 0 : (price.replace(',','.'))*1;
    var discount = $('#invoice_invoiceItemCommands_'+index+'_discount').val();
    discount = discount=="" ? 0 : (discount.replace(',','.'))*1;
    return (qty*price)*(1-(discount/100));
}

function calculateTotal(){
    var subtotal = 0;

    $('.valueInput').each(function() {
        subtotal += $(this).val().replace(',','.').replace(' ', '').replace('€', '')*1    
    });
    
    var discount = $('#invoice_discount').val();
    discount = discount=="" ? 0 : (discount.replace(',','.'))*1;
    return subtotal*(1-(discount/100));
}

function setItemValue(index, value){
    $("#iiValue_"+index).val(formatPrice(value) + " €");
    $("#iiValue_"+index).change();
}

function setTotalPrice(value){
    $('#totalPrice').val(formatPrice(value));
}

function formatPrice(value)
{
    value += '';
    var x = value.split('.');
    var x1 = x[0];
    var x2 = x.length > 1 ? (',' + (x[1].length == 1 ? x[1] + '0' : x[1][0] + (x[1].length == 2 ? x[1][1] : ( x[1][2]*1 >= 5 ? (x[1][1]*1 + 1) : x[1][1] )))) : ',00';
    var rgx = /(\d+)(\d{3})/;
    while (rgx.test(x1)) {
        x1 = x1.replace(rgx, '$1' + ' ' + '$2');
    }
    return x1 + x2;
}


function addInvoiceItemForm($collectionHolder, $addRemoveInvoiceItemButtons, $number) {
    for(var i=0; i<$number;i++){
        // Get the data-prototype explained earlier
        var prototype = $collectionHolder.data('prototype');

        // get the new index
        var index = $collectionHolder.data('index')*1;

        var newForm = prototype;
        
        // Replace '__name__' in the prototype's HTML to
        // instead be a number based on how many items we have
        newForm = newForm.replace(/__name__/g, index);
        
        // increase the index with one for the next item
        $collectionHolder.data('index', index + 1);
        

        // Display the form in the page in an li, before the "Add a tag" link li
        var $newFormLi = $('<tr class="invoice-item-tr-' + index + '">'+
        '<td class="codeInput">' + $('#invoice_invoiceItemCommands_' + index + '_code', newForm).parent().html() + '</td>'+
        '<td class="nameInput" data-item-index="' + index + '">' + $('#invoice_invoiceItemCommands_' + index + '_name' ,newForm).parent().html() + '</td>'+
        '<td class="quantityInput">' + $('#invoice_invoiceItemCommands_' + index + '_quantity', newForm).parent().html()+'</td>'+
        '<td class="unitInput">' + $('#invoice_invoiceItemCommands_' + index + '_unit', newForm).parent().html()+'</td>'+
        '<td class="priceInput">' + $('#invoice_invoiceItemCommands_' + index + '_price', newForm).parent().html()+'</td>'+
        '<td class="discountInput">' + $('#invoice_invoiceItemCommands_' + index + '_discount', newForm).parent().html()+'</td>'+
        '<td class="valueInput"><input id="iiValue_'+index+'" class="valueInput" type="text" placeholder="0,00" readonly=""></td>'+
        '<td class="removeBtn"><a id="remove-invoice-item-'+ index +'" class="btn btn-sm btn-block btn-danger removeBtn"><i class="fa fa-minus" aria-hidden="true"></i></a></td></tr>');        
        
        $collectionHolder.append($newFormLi);
        $collectionHolder.append($addRemoveInvoiceItemButtons);

        $('#remove-invoice-item-'+index).on('click', function() {        
            removeInvoiceItemForm($collectionHolder, index); 
            setTotalPrice(calculateTotal());       
        }); 

        $('#invoice_invoiceItemCommands_' + index + '_quantity').on('keydown', function(e){
            var index = e.target.id.split('_')[2]; 
            setTimeout(function () {       
                setItemValue(index, calculateValue(index));
            });
        });
        $('#invoice_invoiceItemCommands_' + index + '_price').on('keydown', function(e){
            var index = e.target.id.split('_')[2]; 
            setTimeout(function () {       
                setItemValue(index, calculateValue(index));
            });
        });
        $('#invoice_invoiceItemCommands_' + index + '_discount').on('keydown', function(e){
            var index = e.target.id.split('_')[2];
            setTimeout(function () {
                setItemValue(index, calculateValue(index));   
            });            
        });

        $('#iiValue_' + index).on('change', function(){                 
            setTotalPrice(calculateTotal());
        });

        $('#invoice_invoiceItemCommands_' + index + '_code').val(index+1);
        $('#invoice_invoiceItemCommands_' + index + '_quantity').val(1);
        $('#invoice_invoiceItemCommands_' + index + '_unit').val('x');
        $('#invoice_invoiceItemCommands_' + index + '_discount').val(0);

        setItemValue(index,0);
    }    
}

function removeInvoiceItemForm($collectionHolder, index) {
    if(index < 1)
    {
        alert("Can't delete last item!");
        return;
    }
    $collectionHolder.data('index', index);
    $('.invoice-item-tr-' + index, $collectionHolder).remove()
    
}