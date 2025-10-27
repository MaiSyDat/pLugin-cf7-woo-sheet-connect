jQuery(document).ready(function($) {
    'use strict';

    // Khi người dùng thay đổi form CF7 trong dropdown
    $('#cf7_form_id').on('change', function() {
        var formId = $(this).val();
        var mappingContainer = $('#mapping-container');
        
        if (formId) {
            // Show mapping
            mappingContainer.show();
            
            // Lấy danh sách các field của form này
            $.ajax({
                url: cwsc_woo_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'cwsc_get_cf7_fields',
                    form_id: formId
                },
                success: function(response) {
                    if (response.success && response.data.fields.length > 0) {
                        // Tạo danh sách option cho select box
                        var options = '<option value="">-- Chọn field CF7 --</option>';
                        $.each(response.data.fields, function(index, field) {
                            options += '<option value="' + field.name + '">' + field.label + '</option>';
                        });
                        
                        // Cập nhật tất cả các select box trong bảng mapping
                        $('.cf7-field-select').each(function() {
                            var currentValue = $(this).val();
                            $(this).html(options);
                            if (currentValue) {
                                $(this).val(currentValue);
                            }
                        });
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
            // Hiden mapping
            mappingContainer.hide();
        }
    });

    // Khởi tạo nếu biểu mẫu đã được chọn
    if ($('#cf7_form_id').val()) {
        // Lưu trữ các giá trị hiện tại trước khi tải các tùy chọn mới
        var currentValues = {};
        $('.cf7-field-select').each(function() {
            var name = $(this).attr('name');
            var value = $(this).val();
            if (value) {
                currentValues[name] = value;
            }
        });
        
        $('#cf7_form_id').trigger('change');
        
        // Khôi phục giá trị sau khi tải
        setTimeout(function() {
            $.each(currentValues, function(name, value) {
                $('select[name="' + name + '"]').val(value);
            });
            // Ẩn các field đã được chọn
            updateFieldVisibility();
        }, 1000);
    }

    // Hàm ẩn các field CF7 đã được chọn
    function updateFieldVisibility() {
        var selectedValues = [];
        
        // Thu thập tất cả các giá trị đã được chọn
        $('.cf7-field-select').each(function() {
            var val = $(this).val();
            if (val && val !== '') {
                selectedValues.push(val);
            }
        });
        
        // Cập nhật tất cả các select box
        $('.cf7-field-select').each(function() {
            var currentVal = $(this).val();
            var $options = $(this).find('option');
            
            $options.each(function() {
                var $option = $(this);
                var optionVal = $option.val();
                
                // Ẩn option nếu nó đã được chọn trong select box khác
                if (optionVal && optionVal !== currentVal && selectedValues.indexOf(optionVal) !== -1) {
                    $option.hide();
                } else {
                    $option.show();
                }
            });
        });
    }

    // Khi người dùng thay đổi selection trong mapping
    $(document).on('change', '.cf7-field-select', function() {
        // Cập nhật lại visibility của tất cả options
        updateFieldVisibility();
    });
});
