<?php
/**
 * Contact Form 7 Integration Class
 */

if ( !defined( 'ABSPATH' ) ) {
    exit;
}

class CWSC_CF7 {

    public function __construct() {
        add_filter( 'wpcf7_editor_panels', array( $this, 'add_editor_panels' ) );
        add_action( 'wpcf7_save_contact_form', array( $this, 'save_contact_form' ) );
        add_action( 'wpcf7_mail_sent', array( $this, 'send_to_sheet' ), 10, 1 );
    }

    /**
     * Thêm tab “Google Sheets” trong trang chỉnh sửa form CF7
     */
    public function add_editor_panels( $panels ) {
        $panels[ 'google-sheets' ] = array(
            'title' => __( 'Google Sheets', 'cf7-woo-sheet-connector' ),
            'callback' => array( $this, 'editor_panel_content' )
        );
        return $panels;
    }

    /**
     * Giao diện panel “Google Sheets”
     */
    public function editor_panel_content( $contact_form ) {
        $form_id = $contact_form->id();
        // Lấy cài đặt hiện tại của form
        $settings = cwsc_get_effective_form_settings( $form_id );

         // Gán giá trị mặc định nếu chưa có
        $settings = wp_parse_args( $settings, array(
            'enabled' => false,
            'spreadsheet_id' => '',
            'sheet_name' => '',
            'mapping' => array()
        ));

        // // Kiểm tra xem Google API có khả dụng không
        $google_api_available = cwsc_is_google_api_available();

        // Kiểm tra xem đã có thông tin Service Account JSON chưa
        $global_settings = cwsc_get_settings();
        $has_credentials = !empty( $global_settings['google_service_account'] );
        ?>
        <h2><?php _e('Tích hợp Google Sheets', 'cf7-woo-sheet-connector'); ?></h2>
        
         <!-- Báo lỗi nếu chưa cài composer -->
        <?php if ( !$google_api_available ): ?>
            <div class="notice notice-error inline">
                <p><?php _e( 'Không tìm thấy thư viện Google API. Vui lòng chạy composer install trong thư mục plugin.', 'cf7-woo-sheet-connector' ); ?></p>
            </div>

        <!-- Báo nếu chưa cấu hình thông tin service account -->
        <?php elseif ( !$has_credentials ): ?>
            <div class="notice notice-warning inline">
                <p>
                    <?php _e( 'Vui lòng cấu hình thông tin xác thực Google Service Account tại', 'cf7-woo-sheet-connector' ); ?>
                    <a href="<?php echo admin_url( 'options-general.php?page=cwsc-settings' ); ?>"><?php _e('Cài đặt → Kết nối Google Sheet', 'cf7-woo-sheet-connector' ); ?></a>
                </p>
            </div>
        <?php endif; ?>

        <!-- Phần form cài đặt -->
        <fieldset>
            <legend><?php _e( 'Cài đặt Google Sheets', 'cf7-woo-sheet-connector' ); ?></legend>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="cwsc_enabled"><?php _e( 'Kích hoạt tích hợp Google Sheets', 'cf7-woo-sheet-connector' ); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="cwsc_enabled" name="cwsc_enabled" value="1" <?php checked( $settings['enabled'] ); ?>>
                        <label for="cwsc_enabled"><?php _e( 'Gửi dữ liệu form lên Google Sheets', 'cf7-woo-sheet-connector' ); ?></label>
                    </td>
                </tr>
                
                <tr class="cwsc-settings" <?php echo !$settings[ 'enabled' ] ? 'style="display:none;"' : ''; ?>>
                    <th scope="row">
                        <label for="cwsc_spreadsheet_id"><?php _e( 'ID Google Sheet', 'cf7-woo-sheet-connector' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="cwsc_spreadsheet_id" name="cwsc_spreadsheet_id" value="<?php echo esc_attr( $settings[ 'spreadsheet_id' ] ); ?>" class="large-text">
                        <p class="description">
                            <?php _e( 'ID của Google Sheet từ URL. Ví dụ: 1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms', 'cf7-woo-sheet-connector' ); ?>
                        </p>
                        <?php if (!empty($settings['spreadsheet_id'])): ?>
                            <p>
                                <a href="<?php echo esc_url( 'https://docs.google.com/spreadsheets/d/' . $settings['spreadsheet_id'] ); ?>" target="_blank" class="button button-secondary">
                                    <?php _e( 'Xem Google Sheet', 'cf7-woo-sheet-connector' ); ?>
                                </a>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
                
                <tr class="cwsc-settings" <?php echo !$settings[ 'enabled' ] ? 'style="display:none;"' : ''; ?>>
                    <th scope="row">
                        <label for="cwsc_sheet_name"><?php _e( 'Tên Sheet', 'cf7-woo-sheet-connector' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="cwsc_sheet_name" name="cwsc_sheet_name" value="<?php echo esc_attr($settings['sheet_name']); ?>" class="regular-text">
                        <p class="description"><?php _e( 'Tên của sheet/tab trong Google Sheet (mặc định: Sheet1)', 'cf7-woo-sheet-connector' ); ?></p>
                    </td>
                </tr>
            </table>
        </fieldset>
        <?php
    }

    /**
     * Lưu thông tin cấu hình form
     */
    public function save_contact_form( $contact_form ) {
        $form_id = $contact_form->id();
        
        // Lấy dữ liệu đã lưu trước đó để không bị ghi đè
        $existing = get_post_meta( $form_id, '_cwsc_settings', true );
        if ( !is_array( $existing ) ) {
            $existing = array();
        }

        $settings = $existing;

        // Lưu lại các trường trong form
        if ( array_key_exists( 'cwsc_enabled', $_POST ) ) {
            $settings['enabled'] = isset( $_POST['cwsc_enabled'] );
        }
        if ( array_key_exists( 'cwsc_spreadsheet_id', $_POST ) ) {
            $settings['spreadsheet_id'] = sanitize_text_field( $_POST['cwsc_spreadsheet_id'] );
        }
        if ( array_key_exists( 'cwsc_sheet_name', $_POST ) ) {
            $settings['sheet_name'] = sanitize_text_field( $_POST['cwsc_sheet_name'] );
        }

        // Lưu lại vào post meta
        update_post_meta( $form_id, '_cwsc_settings', $settings );
    }

    /**
     * Đẩy dữ liệu lên Google Sheet
     */
    public function send_to_sheet( $contact_form ) {
        $form_id = $contact_form->id();
        $settings = get_post_meta( $form_id, '_cwsc_settings', true );
        

        // Return ếu chưa bật hoặc thiếu Spreadsheet ID
        if ( empty( $settings['enabled'] ) || empty( $settings['spreadsheet_id'] ) ) {
            return;
        }

        // Lấy dữ liệu form người dùng gửi
        $submission = class_exists( 'WPCF7_Submission' ) ? WPCF7_Submission::get_instance() : null;

        try {
            // Khởi tạo client Google API
            $google_client = new CWSC_Google_Client();
            
            // Nếu không có submission instance → fallback dùng $_POST
            $posted_data = $submission ? ( array ) $submission->get_posted_data() : ( array ) $_POST;

            // Chuẩn bị dữ liệu gửi lên sheet
            $data = $this->prepare_form_data( $contact_form, $posted_data, $settings );
            $result = $google_client->append_row(
                $settings[ 'spreadsheet_id' ],
                $settings[ 'sheet_name' ] ?: 'Sheet1',
                $data
            );

        } catch ( Exception $e ) {
        }
    }

    /**
     * Chuẩn bị dữ liệu form cho Google Sheets
     */
    private function prepare_form_data( $contact_form, $posted_data, $settings ) {
        $posted_data = ( array ) $posted_data;
        $data = array();

        // Exclude CF7 internal fields and non-user inputs
        $excluded_prefixes = array( '_wpcf7' );
        $excluded_keys = array( 'g-recaptcha-response', 'h-captcha-response' );

        foreach ( $posted_data as $key => $value ) {
            $is_excluded = false;
            foreach ( $excluded_prefixes as $prefix ) {
                if ( strpos($key, $prefix) === 0 ) {
                    $is_excluded = true;
                    break;
                }
            }
            if ( $is_excluded || in_array( $key, $excluded_keys, true ) ) {
                continue;
            }

            if ( is_array( $value ) ) {
                $value = implode( ', ', array_filter( array_map( 'strval', $value ), 'strlen' ) );
            }

            $data[$key] = (string) $value;
        }

        // Add helpful metadata columns
        $data = array_merge(
            array(
                'submitted_at' => cwsc_get_current_timestamp(),
                'page_url' => cwsc_get_current_url(),
                'referrer' => cwsc_get_referrer(),
                'ip_address' => cwsc_get_customer_ip()
            ),
            $data
        );

        return $data;
    }
}
