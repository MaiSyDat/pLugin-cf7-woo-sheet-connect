<?php
/**
* Lớp tích hợp WooCommerce
* Xử lý tích hợp WooCommerce với Google Trang tính
 */

if (!defined('ABSPATH')) {
    exit;
}

class CWSC_WooCommerce {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_woocommerce_menu' ) );
        add_action( 'admin_init', array( $this, 'register_woocommerce_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        add_action( 'wp_ajax_cwsc_get_cf7_fields', array( $this, 'ajax_get_cf7_fields' ) );
        add_action( 'woocommerce_thankyou', array( $this, 'send_order_to_sheet' ), 10, 1 );
        add_action( 'admin_init', array( $this, 'handle_form_submission' ) );
        add_action( 'admin_init', array( $this, 'save_mapping_settings' ) );
    }

    /**
     * Thêm một submenu mới trong menu WooCommerce
     */
    public function add_woocommerce_menu() {
        if ( !class_exists( 'WooCommerce' ) ) {
            return;
        }

        add_submenu_page(
            'woocommerce',
            __( 'Woo → Google Sheet', 'cf7-woo-sheet-connector' ),
            __( 'Woo → Google Sheet', 'cf7-woo-sheet-connector' ),
            'manage_woocommerce',
            'cwsc-woo-sheet',
            array( $this, 'woocommerce_settings_page' )
        );
    }

    /**
     * Đăng ký option để lưu dữ liệu mapping Woo <-> CF7
     */
    public function register_woocommerce_settings() {
        register_setting( 'cwsc_woo_settings', 'woo_cf7_field_mapping', array( $this, 'sanitize_mapping_settings' ) );
    }
    
    /**
     * Xử lý submit form khi người dùng lưu cấu hình mapping
     */
    public function handle_form_submission() {
        if ( !current_user_can( 'manage_woocommerce' ) ) {
            return;
        }
        
        if ( isset( $_POST[ 'woo_cf7_field_mapping' ] ) && isset( $_POST[ 'option_page' ] ) && $_POST[ 'option_page' ] === 'cwsc_woo_settings' ) {
            $mapping_data = $_POST[ 'woo_cf7_field_mapping' ];
            $sanitized_data = $this->sanitize_mapping_settings( $mapping_data );
            
            // Lưu dữ liệu vào option
            update_option( 'woo_cf7_field_mapping', $sanitized_data );
            
            // Redirect to avoid resubmission
            wp_redirect( add_query_arg( array( 'page' => 'cwsc-woo-sheet', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
            exit;
        }
    }
    
    /**
     * Lưu cấu hình mapping với xác thực nonce
     */
    public function save_mapping_settings() {
        if ( !current_user_can( 'manage_woocommerce' ) ) {
            return;
        }
        
        if ( isset( $_POST[ 'woo_cf7_field_mapping' ] ) && isset( $_POST[ 'submit' ] ) ) {
            // Kiểm tra nonce để bảo mật
            if ( !wp_verify_nonce( $_POST[ 'cwsc_woo_nonce' ], 'cwsc_woo_save' ) ) {
                wp_die( 'Security check failed' );
            }
            
            $mapping_data = $_POST[ 'woo_cf7_field_mapping' ];
            $sanitized_data = $this->sanitize_mapping_settings( $mapping_data );
            
            update_option( 'woo_cf7_field_mapping', $sanitized_data );
            
            // Redirect để tránh resubmit
            wp_redirect( add_query_arg( array( 'page' => 'cwsc-woo-sheet', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
            exit;
        }
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( $hook !== 'woocommerce_page_cwsc-woo-sheet' ) {
            return;
        }

        wp_enqueue_script( 'jquery' );
        wp_enqueue_script( 'cwsc-woo-admin', CWSC_PLUGIN_URL . 'assets/js/woo-admin.js', array( 'jquery' ), CWSC_VERSION, true );
        wp_enqueue_style( 'cwsc-woo-admin', CWSC_PLUGIN_URL . 'assets/css/woo-admin.css', array(), CWSC_VERSION );

        wp_localize_script('cwsc-woo-admin', 'cwsc_woo_ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'cwsc_woo_nonce' ),
            'strings' => array(
                'loading' => 'Đang tải...',
                'no_fields' => 'Không tìm thấy field nào trong form đã chọn',
            )
        ));
    }

    /**
     * WooCommerce settings page
     */
    public function woocommerce_settings_page() {
        // Lấy cấu hình mapping đã lưu  
        $mapping_settings = get_option( 'woo_cf7_field_mapping', array() );
        $selected_form_id = isset( $mapping_settings[ 'form_id' ] ) ? $mapping_settings[ 'form_id' ] : '';
        $mapping = isset( $mapping_settings[ 'mapping' ] ) ? $mapping_settings[ 'mapping' ] : array();
        

        // Lấy danh sách form CF7
        $cf7_forms = array();
        $cf7_fields = array();
        if ( class_exists( 'WPCF7_ContactForm' ) ) {
            $forms = WPCF7_ContactForm::find();
            foreach ( $forms as $form ) {
                $cf7_forms[ $form->id() ] = $form->title();
            }
            
            // Nếu người dùng đã chọn form thì quét field của form đó
            if ( !empty( $selected_form_id ) ) {
                $form = WPCF7_ContactForm::get_instance( $selected_form_id );
                if ( $form ) {
                    $form_tags = $form->scan_form_tags();
                    foreach ( $form_tags as $tag ) {
                        if ( !empty( $tag->name ) ) {
                            $cf7_fields[] = array(
                                'name' => $tag->name,
                                'label' => $tag->name
                            );
                        }
                    }
                }
            }
        }

        // Danh sách field mặc định của WooCommerce
        $woo_fields = array(
            'billing_first_name' => 'Tên',
            'billing_last_name' => 'Họ',
            'billing_phone' => 'Số điện thoại',
            'billing_email' => 'Email',
            'billing_address_1' => 'Địa chỉ',
            'order_total' => 'Tổng đơn hàng',
            'order_id' => 'Mã đơn hàng',
            'order_date' => 'Ngày đặt hàng',
            'payment_method' => 'Phương thức thanh toán',
            'order_status' => 'Trạng thái đơn hàng',
            'customer_note' => 'Ghi chú của khách hàng',
            'product_names' => 'Tên sản phẩm',
            'product_quantities' => 'Số lượng sản phẩm',
            'product_details' => 'Chi tiết sản phẩm',
        );
        ?>
        <div class="wrap">
            <h1>Kết nối WooCommerce với Contact Form 7</h1>
            
            <?php if ( isset( $_GET['saved'] ) && $_GET['saved'] == '1' ): ?>
                <div class="notice notice-success">
                    <p>Cấu hình đã được lưu thành công!</p>
                </div>
            <?php endif; ?>
            
            <?php if ( empty( $cf7_forms ) ): ?>
                <div class="notice notice-warning">
                    <p>Không tìm thấy form Contact Form 7 nào. Vui lòng tạo form trước.</p>
                </div>
            <?php else: ?>
                <form method="post" action="">
                    <?php wp_nonce_field( 'cwsc_woo_save', 'cwsc_woo_nonce' ); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="cf7_form_id">Chọn Form Contact Form 7</label>
                            </th>
                            <td>
                                <select id="cf7_form_id" name="woo_cf7_field_mapping[form_id]" class="regular-text">
                                    <option value="">-- Chọn form --</option>
                                    <?php foreach ( $cf7_forms as $form_id => $form_title ): ?>
                                        <option value="<?php echo esc_attr($form_id); ?>" <?php selected( $selected_form_id, $form_id ); ?>>
                                            <?php echo esc_html( $form_title ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Chọn form Contact Form 7 để sử dụng cho việc mapping field</p>
                            </td>
                        </tr>
                    </table>

                    <div id="mapping-container" <?php echo empty( $selected_form_id ) ? 'style="display:none;"' : ''; ?>>
                        <h2>Mapping Field</h2>
                        <p class="description">Ghép nối các trường dữ liệu trong đơn hàng WooCommerce với các trường tương ứng trong Contact Form 7.</p>
                        
                        <table class="widefat" id="mapping-table">
                            <thead>
                                <tr>
                                    <th>Trường WooCommerce</th>
                                    <th>Trường Contact Form 7</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $woo_fields as $woo_field => $woo_label ): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html( $woo_label ); ?></strong>
                                            <input type="hidden" name="woo_cf7_field_mapping[woo_fields][]" value="<?php echo esc_attr( $woo_field ); ?>">
                                        </td>
                                        <td>
                                            <select name="woo_cf7_field_mapping[mapping][<?php echo esc_attr( $woo_field ); ?>]" class="cf7-field-select">
                                                <option value="">-- Chọn trường CF7 tương ứng --</option>
                                                <?php foreach ($cf7_fields as $cf7_field): ?>
                                                    <option value="<?php echo esc_attr($cf7_field['name']); ?>" 
                                                            <?php selected( isset( $mapping[$woo_field] ) ? $mapping[$woo_field] : '', $cf7_field['name'] ); ?>>
                                                        <?php echo esc_html( $cf7_field['label'] ); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php submit_button( 'Lưu cấu hình' ); ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Xử lý AJAX lấy danh sách field của form CF7 theo ID
     */
    public function ajax_get_cf7_fields() {
        if ( !current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'Bạn không có quyền truy cập trang này.' ) );
        }

        $form_id = intval( $_POST['form_id'] );
        if ( empty( $form_id ) ) {
            wp_send_json_error( array( 'message' => 'ID form không hợp lệ' ) );
        }

        $form = WPCF7_ContactForm::get_instance( $form_id );
        if ( !$form ) {
            wp_send_json_error(array( 'message' => 'Không tìm thấy form' ) );
        }

        $fields = array();
        $form_tags = $form->scan_form_tags();
        
        foreach ( $form_tags as $tag ) {
            if ( !empty( $tag->name ) ) {
                $fields[] = array(
                    'name' => $tag->name,
                    'label' => $tag->name
                );
            }
        }

        wp_send_json_success( array( 'fields' => $fields ) );
    }

    /**
     * Làm sạch dữ liệu mapping trước khi lưu vào database
     */
    public function sanitize_mapping_settings( $input ) {
        $sanitized = array();
        
        if ( isset( $input['form_id'] ) ) {
            $sanitized['form_id'] = intval( $input['form_id'] );
        }
        
        if ( isset( $input['mapping'] ) && is_array( $input['mapping'] ) ) {
            $sanitized['mapping'] = array();
            foreach ( $input['mapping'] as $woo_field => $cf7_field ) {
                if ( !empty( $cf7_field ) ) { // Only save non-empty mappings
                    $sanitized[ 'mapping' ][ sanitize_text_field( $woo_field ) ] = sanitize_text_field( $cf7_field );
                }
            }
        }
        
        return $sanitized;
    }

    /**
     * Gửi dữ liệu đơn hàng WooCommerce lên Google Sheet
     */
    public function send_order_to_sheet( $order_id ) {
        if ( empty( $order_id ) ) {
            return;
        }

        // Lấy cấu hình mapping
        $mapping_settings = get_option( 'woo_cf7_field_mapping', array() );
        if ( empty( $mapping_settings['form_id'] ) || empty( $mapping_settings['mapping'] ) ) {
            return;
        }

        // Get order object
        $order = wc_get_order( $order_id );
        if ( !$order ) {
            return;
        }

        // Lấy thông tin form CF7 đã map
        $form_id = $mapping_settings['form_id'];
        $form_settings = get_post_meta( $form_id, '_cwsc_settings', true );
        
        // Nếu chưa bật hoặc chưa có Spreadsheet ID thì dừng
        if ( empty( $form_settings['enabled'] ) || empty( $form_settings['spreadsheet_id'] ) ) {
            return;
        }

        try {
            // Chuẩn bị dữ liệu đơn hàng
            $order_data = $this->prepare_order_data( $order, $mapping_settings['mapping'] );
            
            // Gửi lên Google Sheet thông qua lớp CWSC_Google_Client
            $google_client = new CWSC_Google_Client();
            $result = $google_client->append_row(
                $form_settings['spreadsheet_id'],
                $form_settings['sheet_name'] ?: 'Sheet1',
                $order_data
            );

        } catch (Exception $e) {
            // Error handling
        }
    }

    /**
     * Chuẩn bị dữ liệu đơn hàng theo cấu hình mapping
     */
    private function prepare_order_data( $order, $mapping ) {
        $data = array();
        
        // Duyệt từng field Woo được map sang CF7
        foreach ( $mapping as $woo_field => $cf7_field ) {
            if ( empty( $cf7_field ) ) {
                continue;
            }

            $value = '';
            
            switch ( $woo_field ) {
                case 'billing_first_name':
                    $value = $order->get_billing_first_name();
                    break;
                case 'billing_last_name':
                    $value = $order->get_billing_last_name();
                    break;
                case 'billing_phone':
                    $value = $order->get_billing_phone();
                    break;
                case 'billing_email':
                    $value = $order->get_billing_email();
                    break;
                case 'billing_address_1':
                    $value = $order->get_billing_address_1();
                    break;
                case 'order_total':
                    $value = $order->get_total();
                    break;
                case 'order_id':
                    $value = $order->get_id();
                    break;
                case 'order_date':
                    $value = $order->get_date_created()->format( 'Y-m-d H:i:s' );
                    break;
                case 'payment_method':
                    $value = $order->get_payment_method_title();
                    break;
                case 'order_status':
                    $value = $order->get_status();
                    break;
                case 'customer_note':
                    $value = $order->get_customer_note();
                    break;
                case 'product_names':
                    $product_names = array();
                    foreach ( $order->get_items() as $item ) {
                        $product_names[] = $item->get_name();
                    }
                    $value = implode( ', ', $product_names );
                    break;
                case 'product_quantities':
                    $quantities = array();
                    foreach ( $order->get_items() as $item ) {
                        $quantities[] = $item->get_quantity();
                    }
                    $value = implode(', ', $quantities);
                    break;
                case 'product_details':
                    $details = array();
                    foreach ( $order->get_items() as $item ) {
                        $details[] = $item->get_name() . ' (x' . $item->get_quantity() . ')';
                    }
                    $value = implode( ', ', $details );
                    break;
            }
            
            if ( !empty( $value ) ) {
                $data[ $cf7_field ] = ( string ) $value;
            }
        }

        // Thêm thông tin meta chung
        $data = array_merge(
            array(
                'submitted_at' => cwsc_get_current_timestamp(),
                'source' => 'woocommerce',
                'order_id' => $order->get_id(),
            ),
            $data
        );

        return $data;
    }
}
