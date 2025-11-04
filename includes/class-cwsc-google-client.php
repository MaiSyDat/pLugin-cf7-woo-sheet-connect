<?php
/**
 * Google API Client Class
 */

if ( !defined( 'ABSPATH' ) ) {
    exit;
}

class CWSC_Google_Client {

    private $client;
    private $service;
    private $settings;

    public function __construct() {
        $this->settings = get_option( 'cwsc_settings', array() );
        $this->init_client();
    }

    /**
     * Load Google API and create connection
     */
    private function init_client() {
        try {
            // Check if Google API library exists
            if ( !file_exists( CWSC_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
                throw new Exception( 'Google API library not found. Please run composer install in /wp-content/plugins/cf7-woo-sheet-connector directory' );
            }

            require_once CWSC_PLUGIN_DIR . 'vendor/autoload.php';

            $this->client = new Google_Client();
            $this->client->setApplicationName( 'CF7 WooCommerce Sheet Connector' );
            $this->client->setScopes( [ Google_Service_Sheets::SPREADSHEETS ] );
            $this->client->setAccessType( 'offline' );

            // Set authentication
            $this->set_authentication();

            $this->service = new Google_Service_Sheets( $this->client );

        } catch ( Exception $e ) {
            throw $e;
        }
    }

    /**
     * Authenticate with JSON service account
     */
    private function set_authentication() {
        $service_account = isset( $this->settings[ 'google_service_account' ] ) ? $this->settings[ 'google_service_account' ] : '';

        if ( empty( $service_account ) ) {
            throw new Exception( 'Google Service Account credentials not configured.' );
        }

        // Decode and set credentials
        $credentials = json_decode( $service_account, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            throw new Exception( 'The Google Account Service JSON format is invalid.' );
        }

        $this->client->setAuthConfig( $credentials );
    }

    /**
     * test google api connection
     */
    public function test_connection() {
        try {
            // Test if we can create a client and authenticate
            if ( !$this->client || !$this->service ) {
                return array( 'success' => false, 'message' => 'Unable to initialize Google API client' );
            }
            
            // Try to get access token to test authentication
            $accessToken = $this->client->getAccessToken();
            
            if ( !$accessToken ) {
                // Try to get token using service account authentication
                $this->client->fetchAccessTokenWithAssertion();
                $accessToken = $this->client->getAccessToken();
            }
            
            if ( !$accessToken ) {
                return array( 'success' => false, 'message' => 'Unable to get access token. Please check your Google account service information.' );
            }
            
            return array( 'success' => true, 'message' => 'Connection successful - Google API authentication working' );
            
        } catch ( Exception $e ) {
            $error_message = $e->getMessage();
            
            // Provide more specific error messages
            if ( strpos( $error_message, 'invalid_grant' ) !== false ) {
                $error_message = 'Invalid service account information. Please check JSON file.';
            } elseif ( strpos( $error_message, 'access_denied' ) !== false ) {
                $error_message = 'Access denied. Make sure the Google Sheets API is enabled and the service account has the appropriate permissions.';
            } elseif ( strpos( $error_message, 'not found' ) !== false ) {
                $error_message = 'Service account not found. Please check JSON information.';
            }
            
            return array( 'success' => false, 'message' => $error_message );
        }
    }

    /**
     * Add a row to Google Sheet
     */
    public function append_row( $spreadsheet_id, $sheet_name, $data_array ) {
        try {
            if ( empty( $spreadsheet_id ) || empty( $sheet_name ) || empty( $data_array ) ) {
                throw new Exception('Missing parameters required to add data.');
            }

            // Make sure headers exist first
            $this->ensure_headers_exist( $spreadsheet_id, $sheet_name, array_keys( $data_array ) );

            // Get the current title to sort the column with the correct data
            $headers = $this->get_sheet_headers( $spreadsheet_id, $sheet_name );
            if ( empty( $headers ) ) {
                $headers = array_keys( $data_array );
            }

            // Sort values in headers order
            $rowValues = array();
            foreach ( $headers as $header ) {
                $rowValues[] = isset( $data_array[ $header ] ) ? ( string ) $data_array[ $header ] : '';
            }

            $body = new Google_Service_Sheets_ValueRange([
                'majorDimension' => 'ROWS',
                'values' => [ $rowValues ]
            ]);

            $params = [
                'valueInputOption'  => 'RAW',
                'insertDataOption'  => 'INSERT_ROWS'
            ];

            // Use full range to ensure correct sheet is written
            $range = $sheet_name . '!A1';
            $result = $this->service->spreadsheets_values->append( $spreadsheet_id, $range, $body, $params );

            // Log the number of updated lines if any
            $updates = method_exists( $result, 'getUpdates' ) ? $result->getUpdates() : null;
            $updatedRows = $updates && method_exists( $updates, 'getUpdatedRows' ) ? ( int ) $updates->getUpdatedRows() : null;
            $updatedRange = $updates && method_exists( $updates, 'getUpdatedRange' ) ? ( string ) $updates->getUpdatedRange() : '';

            if ( $updatedRows === 0 ) {
                return array(
                    'success' => false,
                    'message' => 'Google API returned 0 updated rows. Check sheet permissions and sheet name.'
                );
            }

            return array(
                'success' => true,
                'message' => 'Data has been successfully sent to Google Sheet',
                'updated_rows' => $updatedRows,
                'updated_range' => $updatedRange
            );

        } catch ( Exception $e ) {
            return array(
                'success' => false,
                'message' => 'Unable to upload data to Google Sheet: ' . $e->getMessage()
            );
        }
    }

    /**
     * get row header (first row)
     */
    public function get_sheet_headers( $spreadsheet_id, $sheet_name ) {
        try {
            $range = $sheet_name . '!1:1';
            $response = $this->service->spreadsheets_values->get( $spreadsheet_id, $range );
            $values = $response->getValues();

            if ( !empty($values[0] ) ) {
                return $values[0];
            }

            return array();
        } catch ( Exception $e ) {
            return array();
        }
    }

    /**
     * Make sure headers exist in the sheet
     */
    private function ensure_headers_exist( $spreadsheet_id, $sheet_name, $field_names ) {
        try {
           // Check if the first line has data
            $range = $sheet_name . '!1:1';
            $response = $this->service->spreadsheets_values->get( $spreadsheet_id, $range );
            $existing_headers = $response->getValues();
            
           // If headers don't exist, add them
            if ( empty( $existing_headers ) || empty( $existing_headers[0] ) ) {
                $headers_body = new Google_Service_Sheets_ValueRange([
                    'values' => array( $field_names )
                ]);
                
                $params = [
                    'valueInputOption' => 'RAW'
                ];
                
                $this->service->spreadsheets_values->update(
                    $spreadsheet_id, 
                    $sheet_name . '!A1', 
                    $headers_body, 
                    $params
                );
            }
        } catch ( Exception $e ) {

        }
    }
}
