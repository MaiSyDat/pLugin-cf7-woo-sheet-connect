<?php
/**
 * Google API Client Class
 * Handles Google Sheets API operations
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
     * Load Google API và tạo kết nối
     */
    private function init_client() {
        try {
            // Check if Google API library exists
            if ( !file_exists( CWSC_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
                throw new Exception( 'Không tìm thấy thư viện Google API. Vui lòng chạy lệnh cài đặt composer.' );
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
     * Xác thực bằng JSON service account
     */
    private function set_authentication() {
        $service_account = isset( $this->settings[ 'google_service_account' ] ) ? $this->settings[ 'google_service_account' ] : '';

        if ( empty( $service_account ) ) {
            throw new Exception( 'Chưa cấu hình thông tin đăng nhập Tài khoản dịch vụ Google.' );
        }

        // Decode and set credentials
        $credentials = json_decode( $service_account, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            throw new Exception( 'Định dạng JSON của Tài khoản dịch vụ Google không hợp lệ.' );
        }

        $this->client->setAuthConfig( $credentials );
    }

    /**
     * kiểm tra kết nối Google API
     */
    public function test_connection() {
        try {
            // Test if we can create a client and authenticate
            if ( !$this->client || !$this->service ) {
                return array( 'success' => false, 'message' => 'Không thể khởi tạo ứng dụng khách Google API' );
            }
            
            // Try to get access token to test authentication
            $accessToken = $this->client->getAccessToken();
            
            if ( !$accessToken ) {
                // Thử lấy token bằng xác thực service account
                $this->client->fetchAccessTokenWithAssertion();
                $accessToken = $this->client->getAccessToken();
            }
            
            if ( !$accessToken ) {
                return array( 'success' => false, 'message' => 'Không thể lấy access token. Vui lòng kiểm tra thông tin tài khoản dịch vụ Google.' );
            }
            
            return array( 'success' => true, 'message' => 'Kết nối thành công - Xác thực Google API hoạt động' );
            
        } catch ( Exception $e ) {
            $error_message = $e->getMessage();
            
            // Provide more specific error messages
            if ( strpos( $error_message, 'invalid_grant' ) !== false ) {
                $error_message = 'Thông tin tài khoản dịch vụ không hợp lệ. Vui lòng kiểm tra file JSON.';
            } elseif ( strpos( $error_message, 'access_denied' ) !== false ) {
                $error_message = 'Từ chối truy cập. Hãy đảm bảo đã bật Google Sheets API và tài khoản dịch vụ có quyền phù hợp.';
            } elseif ( strpos( $error_message, 'not found' ) !== false ) {
                $error_message = 'Không tìm thấy tài khoản dịch vụ. Vui lòng kiểm tra thông tin JSON.';
            }
            
            return array( 'success' => false, 'message' => $error_message );
        }
    }

    /**
     * Thêm một dòng vào Google Sheet
     */
    public function append_row( $spreadsheet_id, $sheet_name, $data_array ) {
        try {
            if ( empty( $spreadsheet_id ) || empty( $sheet_name ) || empty( $data_array ) ) {
                throw new Exception('Thiếu các tham số cần thiết để thêm dữ liệu.');
            }

            // Đảm bảo headers tồn tại trước
            $this->ensure_headers_exist( $spreadsheet_id, $sheet_name, array_keys( $data_array ) );

            // Lấy headers hiện tại để sắp xếp dữ liệu đúng cột
            $headers = $this->get_sheet_headers( $spreadsheet_id, $sheet_name );
            if ( empty( $headers ) ) {
                $headers = array_keys( $data_array );
            }

            // Sắp xếp giá trị theo thứ tự headers
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

            // Sử dụng range đầy đủ để đảm bảo ghi đúng sheet
            $range = $sheet_name . '!A1';
            $result = $this->service->spreadsheets_values->append( $spreadsheet_id, $range, $body, $params );

            // Log lại số dòng cập nhật nếu có
            $updates = method_exists( $result, 'getUpdates' ) ? $result->getUpdates() : null;
            $updatedRows = $updates && method_exists( $updates, 'getUpdatedRows' ) ? ( int ) $updates->getUpdatedRows() : null;
            $updatedRange = $updates && method_exists( $updates, 'getUpdatedRange' ) ? ( string ) $updates->getUpdatedRange() : '';

            if ( $updatedRows === 0 ) {
                return array(
                    'success' => false,
                    'message' => 'Google API trả về 0 hàng đã cập nhật. Kiểm tra quyền của trang tính và tên trang tính.'
                );
            }

            return array(
                'success' => true,
                'message' => 'Dữ liệu đã được gửi thành công lên Google Sheet',
                'updated_rows' => $updatedRows,
                'updated_range' => $updatedRange
            );

        } catch ( Exception $e ) {
            return array(
                'success' => false,
                'message' => 'Không thể gửi dữ liệu lên Google Sheet: ' . $e->getMessage()
            );
        }
    }

    /**
     * lấy dòng tiêu đề (first row)
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
     * kiểm tra quyền truy cập
     */
    public function validate_sheet_access( $spreadsheet_id, $sheet_name ) {
        try {
            $spreadsheet = $this->service->spreadsheets->get( $spreadsheet_id );
            $sheets = $spreadsheet->getSheets();

            foreach ( $sheets as $sheet ) {
                if ( $sheet->getProperties()->getTitle() === $sheet_name ) {
                    return array( 'success' => true, 'message' => 'Đã xác thực quyền truy cập trang tính' );
                }
            }

            return array( 'success' => false, 'message' => 'Không tìm thấy tên trang tính trong bảng tính' );
        } catch ( Exception $e ) {
            return array( 'success' => false, 'message' => $e->getMessage() );
        }
    }

    /**
     * Đảm bảo headers tồn tại trong sheet
     */
    private function ensure_headers_exist( $spreadsheet_id, $sheet_name, $field_names ) {
        try {
            // Kiểm tra xem dòng đầu tiên có dữ liệu chưa
            $range = $sheet_name . '!1:1';
            $response = $this->service->spreadsheets_values->get( $spreadsheet_id, $range );
            $existing_headers = $response->getValues();
            
            // Nếu chưa có headers, thêm vào
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

    /**
     * Lấy thông tin spreadsheet
     */
    public function get_spreadsheet_info( $spreadsheet_id ) {
        try {
            $spreadsheet = $this->service->spreadsheets->get( $spreadsheet_id );
            $sheets = $spreadsheet->getSheets();
            $sheet_names = array();

            foreach ( $sheets as $sheet ) {
                $sheet_names[] = $sheet->getProperties()->getTitle();
            }

            return array(
                'success' => true,
                'title' => $spreadsheet->getProperties()->getTitle(),
                'sheet_names' => $sheet_names
            );
        } catch ( Exception $e ) {
            return array('success' => false, 'message' => $e->getMessage());
        }
    }
}
