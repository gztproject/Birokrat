var $class = $('#konto_filter_kontoClasses');
var $category = $('#konto_filter_kontoCategories');

// When class gets selected ...
$class.change(function() {
  // ... retrieve the corresponding form.
  var $form = $(this).closest('form');
  // Simulate form data, but only include the selected class value.
  var data = {};
  data[$class.attr('name')] = $class.val();
  // Submit data via AJAX to the form's action path.
  $.ajax({
    url : $form.attr('action'),
    type: $form.attr('method'),
    data : data,
    success: function(html) {
      // Replace current category field ...
      $('#konto_filter_kontoCategories').replaceWith(
        // ... with the returned one from the AJAX response.
        $(html).find('#konto_filter_kontoCategories')
      );      
      $category = $('#konto_filter_kontoCategories');
      // Category field now displays the appropriate categories.      
    }
  });
});



// When category gets selected ...
$('#CategoryFilterForm').on('change',  $category, function() {   
  // ... retrieve the corresponding form.
  var $form = $(this).closest('form');
  // Simulate form data, but only include the selected class value.
  var data = {};
  data[$category.attr('name')] = $category.val();
  // Submit data via AJAX to the form's action path.
  $.ajax({
    url : $form.attr('action'),
    type: $form.attr('method'),
    data : data,
    success: function(html) {
      // Replace current konto table...
      $('#konto_table').replaceWith(
        // ... with the returned one from the AJAX response.
        html
      );      
    }
  });
});