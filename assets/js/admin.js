jQuery(document).ready(function($) {
    
    // Xử lý nút kiểm tra kết nối gg
    $('#test-connection').on('click', function() {
        var button = $(this);
        var statusSpan = $('#connection-status');
        var originalText = button.text();
        
        // Khi người dùng bấm hiển thị trạng thái đang kiểm tra
        button.prop('disabled', true).text(cwsc_ajax.strings.testing_connection);
        statusSpan.html('');
        
        // Gửi AJAX tới server để test kết nối với Google API
        $.ajax({
            url: cwsc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'cwsc_test_connection',
                nonce: cwsc_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    statusSpan.html('<span style="color: green;">' + cwsc_ajax.strings.connection_success + '</span>');
                } else {
                    statusSpan.html('<span style="color: red;">' + cwsc_ajax.strings.connection_failed + '</span>');
                    if (response.data && response.data.message) {
                        statusSpan.append('<br><small>' + response.data.message + '</small>');
                    }
                }
            },
            error: function() {
                statusSpan.html('<span style="color: red;">' + cwsc_ajax.strings.connection_failed + '</span>');
            },
            complete: function() {
                // Bật lại nút
                button.prop('disabled', false).text(originalText);
            }
        });
    });
    

    // Form validation
    $('form').on('submit', function(e) {
        var spreadsheetId = $('#cwsc_spreadsheet_id').val();
        var sheetName = $('#cwsc_sheet_name').val();
        
        // Kiểm tra định dạng Spreadsheet ID
        if (spreadsheetId && !isValidSpreadsheetId(spreadsheetId)) {
            e.preventDefault();
            return false;
        }
        
        // Kiểm tra định dạng Sheet Name
        if (sheetName && !isValidSheetName(sheetName)) {
            e.preventDefault();
            return false;
        }
    });
    
    // Helper functions
    function isValidSpreadsheetId(id) {
        // Basic validation for Google Spreadsheet ID format
        return /^[a-zA-Z0-9-_]+$/.test(id) && id.length > 10;
    }
    
    function isValidSheetName(name) {
        // Sheet names cannot contain certain characters
        return !(/[\\\/\?\*\[\]]/.test(name)) && name.length > 0;
    }
    
    // Copy to clipboard functionality for spreadsheet ID
    $('.copy-spreadsheet-id').on('click', function() {
        var id = $(this).data('id');
        navigator.clipboard.writeText(id);
    });
});
