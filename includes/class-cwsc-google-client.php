<?php
/**
 * Google API Client Class
 * 
 * Lightweight implementation using JWT authentication (no external dependencies).
 * Based on Fast Google Indexing API plugin's JWT approach.
 */

if ( !defined( 'ABSPATH' ) ) {
    exit;
}

class CWSC_Google_Client {

    /**
     * Google OAuth 2.0 token endpoint.
     *
     * @var string
     */
    private $token_endpoint = 'https://oauth2.googleapis.com/token';

    /**
     * Google Sheets API v4 base endpoint.
     *
     * @var string
     */
    private $sheets_endpoint = 'https://sheets.googleapis.com/v4/spreadsheets';

    /**
     * Settings array.
     *
     * @var array
     */
    private $settings;

    /**
     * Cached service account data.
     *
     * @var array|null
     */
    private static $cached_service_account = null;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->settings = get_option( 'cwsc_settings', array() );
    }

    /**
     * Get service account credentials.
     *
     * @return array|\WP_Error Service account data or WP_Error on failure.
     */
    private function get_service_account() {
        $service_account_json = isset( $this->settings['google_service_account'] ) ? $this->settings['google_service_account'] : '';

        if ( empty( $service_account_json ) ) {
            return new \WP_Error( 'no_credentials', __( 'Google Service Account credentials not configured.', 'cf7-woo-sheet-connector' ) );
        }

        // Use cached data if available and unchanged.
        if ( null !== self::$cached_service_account && self::$cached_service_account['json'] === $service_account_json ) {
            return self::$cached_service_account['data'];
        }

        // Decode and validate JSON.
        $service_account = json_decode( $service_account_json, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new \WP_Error( 'invalid_json', __( 'The Google Account Service JSON format is invalid.', 'cf7-woo-sheet-connector' ) );
        }

        if ( ! isset( $service_account['private_key'] ) || ! isset( $service_account['client_email'] ) ) {
            return new \WP_Error( 'missing_fields', __( 'Service account JSON is missing required fields (private_key or client_email).', 'cf7-woo-sheet-connector' ) );
        }

        // Cache the parsed data.
        self::$cached_service_account = array(
            'json' => $service_account_json,
            'data' => $service_account,
        );

        return $service_account;
    }

    /**
     * Get OAuth 2.0 access token using JWT.
     *
     * @return string|\WP_Error Access token or WP_Error on failure.
     */
    private function get_access_token() {
        $service_account = $this->get_service_account();
        if ( is_wp_error( $service_account ) ) {
            return $service_account;
        }

        // Create JWT for authentication.
        $jwt = $this->create_jwt( $service_account );
        if ( is_wp_error( $jwt ) ) {
            return $jwt;
        }

        // Request access token.
        $response = wp_remote_post(
            $this->token_endpoint,
            array(
                'body'    => array(
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion'  => $jwt,
                ),
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        if ( 200 !== $response_code ) {
            return new \WP_Error( 'token_error', sprintf( __( 'Failed to get access token: %s', 'cf7-woo-sheet-connector' ), $response_body ) );
        }

        $data = json_decode( $response_body, true );
        if ( ! isset( $data['access_token'] ) ) {
            return new \WP_Error( 'token_error', __( 'Invalid token response from Google.', 'cf7-woo-sheet-connector' ) );
        }

        return $data['access_token'];
    }

    /**
     * Create JWT (JSON Web Token) for Google OAuth 2.0.
     *
     * @param array $service_account Service account data.
     * @return string|\WP_Error JWT token or WP_Error on failure.
     */
    private function create_jwt( $service_account ) {
        $private_key  = $service_account['private_key'];
        $client_email = $service_account['client_email'];

        if ( empty( $private_key ) || empty( $client_email ) ) {
            return new \WP_Error( 'jwt_error', __( 'Missing private key or client email in service account.', 'cf7-woo-sheet-connector' ) );
        }

        // Check if OpenSSL extension is available.
        if ( ! extension_loaded( 'openssl' ) ) {
            return new \WP_Error( 'openssl_missing', __( 'OpenSSL extension is required for JWT authentication.', 'cf7-woo-sheet-connector' ) );
        }

        // Google Sheets API scope.
        $scope_string = 'https://www.googleapis.com/auth/spreadsheets';

        // JWT Header.
        $header = array(
            'alg' => 'RS256',
            'typ' => 'JWT',
        );

        // JWT Claim Set.
        $now       = time();
        $claim_set = array(
            'iss'   => $client_email,
            'scope' => $scope_string,
            'aud'   => $this->token_endpoint,
            'exp'   => $now + 3600, // Token expires in 1 hour.
            'iat'   => $now,
        );

        // Encode header and claim set.
        $encoded_header    = $this->base64_url_encode( wp_json_encode( $header ) );
        $encoded_claim_set = $this->base64_url_encode( wp_json_encode( $claim_set ) );

        // Create signature.
        $signature_input = $encoded_header . '.' . $encoded_claim_set;
        $signature       = '';

        // Sign with private key.
        $private_key_resource = openssl_pkey_get_private( $private_key );
        if ( false === $private_key_resource ) {
            return new \WP_Error( 'jwt_error', __( 'Invalid private key format.', 'cf7-woo-sheet-connector' ) );
        }

        $signature_success = openssl_sign( $signature_input, $signature, $private_key_resource, OPENSSL_ALGO_SHA256 );
        // Only free key resource on PHP versions before 8.0 (deprecated in PHP 8.0+).
        if ( PHP_VERSION_ID < 80000 ) {
            openssl_free_key( $private_key_resource );
        }

        if ( ! $signature_success ) {
            return new \WP_Error( 'jwt_error', __( 'Failed to sign JWT.', 'cf7-woo-sheet-connector' ) );
        }

        $encoded_signature = $this->base64_url_encode( $signature );

        // Return complete JWT.
        return $signature_input . '.' . $encoded_signature;
    }

    /**
     * Base64 URL encode (RFC 4648).
     *
     * @param string $data Data to encode.
     * @return string Encoded string.
     */
    private function base64_url_encode( $data ) {
        return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
    }

    /**
     * Test Google API connection.
     *
     * @return array Array with 'success' and 'message' keys.
     */
    public function test_connection() {
        try {
            // Get access token to test authentication.
            $access_token = $this->get_access_token();
            
            if ( is_wp_error( $access_token ) ) {
                $error_message = $access_token->get_error_message();
                
                // Provide more specific error messages.
                if ( strpos( $error_message, 'invalid_grant' ) !== false ) {
                    $error_message = __( 'Invalid service account information. Please check JSON file.', 'cf7-woo-sheet-connector' );
                } elseif ( strpos( $error_message, 'access_denied' ) !== false ) {
                    $error_message = __( 'Access denied. Make sure the Google Sheets API is enabled and the service account has the appropriate permissions.', 'cf7-woo-sheet-connector' );
                } elseif ( strpos( $error_message, 'not found' ) !== false ) {
                    $error_message = __( 'Service account not found. Please check JSON information.', 'cf7-woo-sheet-connector' );
                }
                
                return array( 'success' => false, 'message' => $error_message );
            }
            
            return array( 'success' => true, 'message' => __( 'Connection successful - Google API authentication working', 'cf7-woo-sheet-connector' ) );
            
        } catch ( Exception $e ) {
            return array( 'success' => false, 'message' => $e->getMessage() );
        }
    }

    /**
     * Get sheet headers (first row).
     *
     * @param string $spreadsheet_id Spreadsheet ID.
     * @param string $sheet_name Sheet name.
     * @return array Array of header values or empty array on failure.
     */
    public function get_sheet_headers( $spreadsheet_id, $sheet_name ) {
        try {
            $access_token = $this->get_access_token();
            if ( is_wp_error( $access_token ) ) {
                return array();
            }

            // Build API endpoint URL.
            $range = urlencode( $sheet_name . '!1:1' );
            $url = $this->sheets_endpoint . '/' . $spreadsheet_id . '/values/' . $range;

            // Make API request.
            $response = wp_remote_get(
                $url,
                array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $access_token,
                        'Content-Type'  => 'application/json',
                    ),
                    'timeout' => 30,
                )
            );

            if ( is_wp_error( $response ) ) {
                return array();
            }

            $response_code = wp_remote_retrieve_response_code( $response );
            if ( 200 !== $response_code ) {
                return array();
            }

            $response_body = wp_remote_retrieve_body( $response );
            $data = json_decode( $response_body, true );

            if ( isset( $data['values'] ) && ! empty( $data['values'][0] ) ) {
                return $data['values'][0];
            }

            return array();
        } catch ( Exception $e ) {
            return array();
        }
    }

    /**
     * Ensure headers exist in the sheet.
     *
     * @param string $spreadsheet_id Spreadsheet ID.
     * @param string $sheet_name Sheet name.
     * @param array  $field_names Array of field names to use as headers.
     * @return void
     */
    private function ensure_headers_exist( $spreadsheet_id, $sheet_name, $field_names ) {
        try {
            // Check if headers exist.
            $existing_headers = $this->get_sheet_headers( $spreadsheet_id, $sheet_name );
            
            // If headers don't exist, add them.
            if ( empty( $existing_headers ) ) {
                $access_token = $this->get_access_token();
                if ( is_wp_error( $access_token ) ) {
                    return;
                }

                // Build API endpoint URL.
                $range = urlencode( $sheet_name . '!A1' );
                $url = $this->sheets_endpoint . '/' . $spreadsheet_id . '/values/' . $range . '?valueInputOption=RAW';

                // Prepare request body.
                $body = array(
                    'values' => array( $field_names ),
                );

                // Make API request to update headers.
                wp_remote_post(
                    $url,
                    array(
                        'headers' => array(
                            'Authorization' => 'Bearer ' . $access_token,
                            'Content-Type'  => 'application/json',
                        ),
                        'body'    => wp_json_encode( $body ),
                        'timeout' => 30,
                    )
                );
            }
        } catch ( Exception $e ) {
            // Silently fail - headers might already exist.
        }
    }

    /**
     * Add a row to Google Sheet.
     *
     * @param string $spreadsheet_id Spreadsheet ID.
     * @param string $sheet_name Sheet name.
     * @param array  $data_array Associative array of data to append.
     * @return array Array with 'success', 'message', and optional 'updated_rows', 'updated_range'.
     */
    public function append_row( $spreadsheet_id, $sheet_name, $data_array ) {
        try {
            if ( empty( $spreadsheet_id ) || empty( $sheet_name ) || empty( $data_array ) ) {
                throw new Exception( __( 'Missing parameters required to add data.', 'cf7-woo-sheet-connector' ) );
            }

            // Get access token.
            $access_token = $this->get_access_token();
            if ( is_wp_error( $access_token ) ) {
                return array(
                    'success' => false,
                    'message' => $access_token->get_error_message(),
                );
            }

            // Make sure headers exist first.
            $this->ensure_headers_exist( $spreadsheet_id, $sheet_name, array_keys( $data_array ) );

            // Get the current headers to sort the column with the correct data.
            $headers = $this->get_sheet_headers( $spreadsheet_id, $sheet_name );
            if ( empty( $headers ) ) {
                $headers = array_keys( $data_array );
            }

            // Sort values in headers order.
            $row_values = array();
            foreach ( $headers as $header ) {
                $row_values[] = isset( $data_array[ $header ] ) ? (string) $data_array[ $header ] : '';
            }

            // Build API endpoint URL.
            $range = urlencode( $sheet_name . '!A1' );
            $url = $this->sheets_endpoint . '/' . $spreadsheet_id . '/values/' . $range . ':append?valueInputOption=RAW&insertDataOption=INSERT_ROWS';

            // Prepare request body.
            $body = array(
                'values' => array( $row_values ),
            );

            // Make API request.
            $response = wp_remote_post(
                $url,
                array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $access_token,
                        'Content-Type'  => 'application/json',
                    ),
                    'body'    => wp_json_encode( $body ),
                    'timeout' => 30,
                )
            );

            if ( is_wp_error( $response ) ) {
                return array(
                    'success' => false,
                    'message' => sprintf( __( 'Unable to upload data to Google Sheet: %s', 'cf7-woo-sheet-connector' ), $response->get_error_message() ),
                );
            }

            $response_code = wp_remote_retrieve_response_code( $response );
            $response_body = wp_remote_retrieve_body( $response );

            if ( 200 !== $response_code ) {
                return array(
                    'success' => false,
                    'message' => sprintf( __( 'Google API returned error: %s', 'cf7-woo-sheet-connector' ), $response_body ),
                );
            }

            $data = json_decode( $response_body, true );
            $updated_rows = isset( $data['updates']['updatedRows'] ) ? (int) $data['updates']['updatedRows'] : 0;
            $updated_range = isset( $data['updates']['updatedRange'] ) ? (string) $data['updates']['updatedRange'] : '';

            if ( 0 === $updated_rows ) {
                return array(
                    'success' => false,
                    'message' => __( 'Google API returned 0 updated rows. Check sheet permissions and sheet name.', 'cf7-woo-sheet-connector' ),
                );
            }

            return array(
                'success'      => true,
                'message'      => __( 'Data has been successfully sent to Google Sheet', 'cf7-woo-sheet-connector' ),
                'updated_rows' => $updated_rows,
                'updated_range' => $updated_range,
            );

        } catch ( Exception $e ) {
            return array(
                'success' => false,
                'message' => sprintf( __( 'Unable to upload data to Google Sheet: %s', 'cf7-woo-sheet-connector' ), $e->getMessage() ),
            );
        }
    }
}
