jQuery(document).ready(function($) {
    
    // Handle the "Test Connection" button for Google API
    $('#test-connection').on('click', function() {
        var button = $(this);
        var statusSpan = $('#connection-status');
        var originalText = button.text();
        
        // When the user clicks, show "Testing..." state
        button.prop('disabled', true).text(cwsc_ajax.strings.testing_connection);
        statusSpan.html('');
        
        // Send AJAX request to the server to test connection with Google API
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
                // Re-enable button after testing is complete
                button.prop('disabled', false).text(originalText);
            }
        });
    });
    

    // Form validation before submission
    $('form').on('submit', function(e) {
        var spreadsheetId = $('#cwsc_spreadsheet_id').val();
        var sheetName = $('#cwsc_sheet_name').val();
        
        // Validate Google Spreadsheet ID format
        if (spreadsheetId && !isValidSpreadsheetId(spreadsheetId)) {
            e.preventDefault();
            return false;
        }
        
        // Validate Sheet Name format
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
        // Sheet names cannot contain certain special characters
        return !(/[\\\/\?\*\[\]]/.test(name)) && name.length > 0;
    }
    
    // Copy the Spreadsheet ID to clipboard
    $('.copy-spreadsheet-id').on('click', function() {
        var id = $(this).data('id');
        navigator.clipboard.writeText(id);
    });
});
