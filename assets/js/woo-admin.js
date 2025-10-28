jQuery(document).ready(function($) {
    'use strict';

    // When the user changes the selected CF7 form in the dropdown
    $('#cf7_form_id').on('change', function() {
        var formId = $(this).val();
        var mappingContainer = $('#mapping-container');
        
        if (formId) {
            // Show mapping section
            mappingContainer.removeClass('hidden');
            
            // Reset all mapped selects to default to avoid stale data
            $('.cf7-field-select').each(function() {
                $(this).val('');
            });
            
            // Fetch the list of CF7 fields for the selected form
            $.ajax({
                url: cwsc_woo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'cwsc_get_cf7_fields',
                    form_id: formId
                },
                success: function(response) {
                    if (response.success && response.data.fields.length > 0) {
                        // Build the select options list
                        var options = '<option value="">-- Select CF7 Field --</option>';
                        $.each(response.data.fields, function(index, field) {
                            options += '<option value="' + field.name + '">' + field.label + '</option>';
                        });
                        
                        // Update all mapping select boxes
                        $('.cf7-field-select').each(function() {
                            $(this).html(options).val(''); // reset value
                        });

                        // Hide duplicate options (ensure consistency)
                        updateFieldVisibility();
                    } else {
                        var options = '<option value="">' + cwsc_woo_ajax.strings.no_fields + '</option>';
                        $('.cf7-field-select').html(options);
                    }
                },
                error: function() {
                    var options = '<option value="">Error loading fields</option>';
                    $('.cf7-field-select').html(options);
                }
            });
        } else {
            // Hide mapping section if no form selected
            mappingContainer.addClass('hidden');
        }
    });

    // Initialize if a form is already selected on page load
    if ($('#cf7_form_id').val()) {
        // Ensure visibility state and hide duplicate fields on first load
        $('#mapping-container').removeClass('hidden');
        updateFieldVisibility();
    }

    // Function to hide CF7 fields that are already selected elsewhere
    function updateFieldVisibility() {
        var selectedValues = [];
        
        // Collect all selected CF7 field values
        $('.cf7-field-select').each(function() {
            var val = $(this).val();
            if (val && val !== '') {
                selectedValues.push(val);
            }
        });
        
        // Update each select box to hide duplicates
        $('.cf7-field-select').each(function() {
            var currentVal = $(this).val();
            var $options = $(this).find('option');
            
            $options.each(function() {
                var $option = $(this);
                var optionVal = $option.val();
                
                // Hide the option if it’s already selected in another dropdown
                if (optionVal && optionVal !== currentVal && selectedValues.indexOf(optionVal) !== -1) {
                    $option.hide();
                } else {
                    $option.show();
                }
            });
        });
    }

    // When the user changes any mapping selection
    $(document).on('change', '.cf7-field-select', function() {
        // Refresh visibility across all dropdowns
        updateFieldVisibility();
    });
});
