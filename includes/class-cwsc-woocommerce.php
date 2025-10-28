<?php
/**
* Handling WooCommerce integration with Google Sheets
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
     * Add submenu in menu WooCommerce
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
     * Register option to save mapping data Woo <-> CF7
     */
    public function register_woocommerce_settings() {
        register_setting( 'cwsc_woo_settings', 'woo_cf7_field_mapping', array( $this, 'sanitize_mapping_settings' ) );
    }
    
    /**
     * Handle form submit when user saves mapping configuration
     */
    public function handle_form_submission() {
        if ( !current_user_can( 'manage_woocommerce' ) ) {
            return;
        }
        
        if ( isset( $_POST[ 'woo_cf7_field_mapping' ] ) && isset( $_POST[ 'option_page' ] ) && $_POST[ 'option_page' ] === 'cwsc_woo_settings' ) {
            $mapping_data = $_POST[ 'woo_cf7_field_mapping' ];
            $sanitized_data = $this->sanitize_mapping_settings( $mapping_data );
            
           // Save data to option
            update_option( 'woo_cf7_field_mapping', $sanitized_data );
            
            // Redirect to avoid resubmission
            wp_redirect( add_query_arg( array( 'page' => 'cwsc-woo-sheet', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
            exit;
        }
    }
    
    /**
     * Save mapping configuration with nonce validation
     */
    public function save_mapping_settings() {
        if ( !current_user_can( 'manage_woocommerce' ) ) {
            return;
        }
        
        if ( isset( $_POST[ 'woo_cf7_field_mapping' ] ) && isset( $_POST[ 'submit' ] ) ) {
            // Check nonce for security
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

        wp_localize_script('cwsc-woo-admin', 'cwsc_woo_ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'cwsc_woo_nonce' ),
            'strings' => array(
                'loading' => 'Loading...',
                'no_fields' => 'No fields were found in the selected form.',
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
        

        // Get list of CF7 forms
        $cf7_forms = array();
        $cf7_fields = array();
        if ( class_exists( 'WPCF7_ContactForm' ) ) {
            $forms = WPCF7_ContactForm::find();
            foreach ( $forms as $form ) {
                $cf7_forms[ $form->id() ] = $form->title();
            }
            
            // scan the selected form field
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

        // Get WooCommerce fields dynamically
        $woo_fields = $this->get_woocommerce_fields();
        ?>
        <div class="wrap cwsc-woo-setting">
            <div class="cwsc-woo-header">
                <h1><?php _e( 'Connect WooCommerce with Contact Form 7', 'cf7-woo-sheet-connector' ); ?></h1>
                <p><?php _e( 'Sync your WooCommerce orders to Google Sheets automatically through Contact Form 7.', 'cf7-woo-sheet-connector' ); ?></p>
            </div>
            
            <?php if ( isset( $_GET['saved'] ) && $_GET['saved'] == '1' ): ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong><?php _e( 'Configuration saved successfully!', 'cf7-woo-sheet-connector' ); ?></strong></p>
                </div>
            <?php endif; ?>
            
            <?php if ( empty( $cf7_forms ) ): ?>
                <div class="notice notice-warning">
                    <p><?php _e( 'No Contact Form 7 form found. Please create a form first.', 'cf7-woo-sheet-connector' ); ?></p>
                </div>
            <?php else: ?>
                <form method="post" action="">
                    <?php wp_nonce_field( 'cwsc_woo_save', 'cwsc_woo_nonce' ); ?>
                    
                    <div class="cwsc-woo-section">
                        <h2><?php _e( 'Form Selection', 'cf7-woo-sheet-connector' ); ?></h2>
                        
                        <table class="form-table cwsc-form-table">
                            <tr>
                                <th scope="row">
                                    <label for="cf7_form_id"><?php _e( 'Contact Form 7', 'cf7-woo-sheet-connector' ); ?></label>
                                </th>
                                <td>
                                    <select id="cf7_form_id" name="woo_cf7_field_mapping[form_id]" class="regular-text">
                                        <option value=""><?php _e( '-- Select form --', 'cf7-woo-sheet-connector' ); ?></option>
                                        <?php foreach ( $cf7_forms as $form_id => $form_title ): ?>
                                            <option value="<?php echo esc_attr($form_id); ?>" <?php selected( $selected_form_id, $form_id ); ?>>
                                                <?php echo esc_html( $form_title ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">
                                        <?php _e( 'Select the Contact Form 7 form to use for field mapping.', 'cf7-woo-sheet-connector' ); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div id="mapping-container"<?php echo empty( $selected_form_id ) ? ' class="hidden"' : ''; ?>>
                        <div class="cwsc-woo-section">
                            <h2><?php _e( 'Field Mapping', 'cf7-woo-sheet-connector' ); ?></h2>
                            <p><?php _e( 'Map WooCommerce order data fields with corresponding fields in Contact Form 7.', 'cf7-woo-sheet-connector' ); ?></p>
                            
                            <table class="widefat" id="mapping-table">
                                <thead>
                                    <tr>
                                        <th><?php _e( 'WooCommerce Fields', 'cf7-woo-sheet-connector' ); ?></th>
                                        <th><?php _e( 'Contact Form 7 Fields', 'cf7-woo-sheet-connector' ); ?></th>
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
                                                <select name="woo_cf7_field_mapping[mapping][<?php echo esc_attr( $woo_field ); ?>]" class="cf7-field-select regular-text">
                                                    <option value=""><?php _e( '-- Select CF7 Field --', 'cf7-woo-sheet-connector' ); ?></option>
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
                    </div>
                    
                    <div class="cwsc-woo-submit-wrapper">
                        <?php submit_button( __( 'Save Configuration', 'cf7-woo-sheet-connector' ), 'primary large' ); ?>
                    </div>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Get WooCommerce fields dynamically using hooks
     */
    private function get_woocommerce_fields() {
        $fields = array();
        
        // Get all checkout fields from WooCommerce
        $checkout_fields = array();
        
        if ( function_exists( 'WC' ) && WC()->checkout() ) {
            $checkout_fields = WC()->checkout()->get_checkout_fields();
        }
        
        // Add billing_full_name as a special combined field
        $fields['billing_full_name'] = 'Full name (first + last)';
        
        // Get billing fields
        if ( isset( $checkout_fields['billing'] ) && is_array( $checkout_fields['billing'] ) ) {
            foreach ( $checkout_fields['billing'] as $key => $field ) {
                $label = isset( $field['label'] ) && !empty( $field['label'] ) ? $field['label'] : $key;
                $fields[ $key ] = $label;
            }
        }
        
        // Add order information fields
        $order_fields = array(
            'order_id'          => 'Order ID',
            'order_date'        => 'Order Date',
            'order_total'       => 'Order Total',
            'order_status'      => 'Order Status',
            'payment_method'    => 'Payment Method',
            'customer_note'     => 'Customer Note',
            'product_names'     => 'Product Names',
            'product_quantities'=> 'Product Quantities',
            'product_details'   => 'Product Details',
        );
        
        $fields = array_merge( $fields, $order_fields );
        
        return $fields;
    }

    /**
     * AJAX processing to get the list of fields of CF7 form by ID
     */
    public function ajax_get_cf7_fields() {
        if ( !current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'You do not have permission to access this page.' ) );
        }

        $form_id = intval( $_POST['form_id'] );
        if ( empty( $form_id ) ) {
            wp_send_json_error( array( 'message' => 'Invalid form ID' ) );
        }

        $form = WPCF7_ContactForm::get_instance( $form_id );
        if ( !$form ) {
            wp_send_json_error(array( 'message' => 'Form not found' ) );
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
     * Clean mapping data before saving to database
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
     * Upload WooCommerce order data to Google Sheet
     */
    public function send_order_to_sheet( $order_id ) {
        if ( empty( $order_id ) ) {
            return;
        }

        // Get mapping configuration
        $mapping_settings = get_option( 'woo_cf7_field_mapping', array() );
        if ( empty( $mapping_settings['form_id'] ) || empty( $mapping_settings['mapping'] ) ) {
            return;
        }

        // Get order object
        $order = wc_get_order( $order_id );
        if ( !$order ) {
            return;
        }

        // Prevent duplicate sends on page reloads by using an order meta flag
        $already_sent = $order->get_meta( '_cwsc_sent_to_sheet' );
        if ( $already_sent ) {
            return;
        }

        // Get information from mapped CF7 form
        $form_id = $mapping_settings['form_id'];
        $form_settings = get_post_meta( $form_id, '_cwsc_settings', true );
        
        // If not enabled or no Spreadsheet ID then stop
        if ( empty( $form_settings['enabled'] ) || empty( $form_settings['spreadsheet_id'] ) ) {
            return;
        }

        try {
           // Prepare order data
            $order_data = $this->prepare_order_data( $order, $mapping_settings['mapping'] );
            
            // Send to Google Sheet via CWSC_Google_Client class
            $google_client = new CWSC_Google_Client();
            $result = $google_client->append_row(
                $form_settings['spreadsheet_id'],
                $form_settings['sheet_name'] ?: 'Sheet1',
                $order_data
            );

            // Mark as sent only after a successful append
            if ( $result ) {
                $order->update_meta_data( '_cwsc_sent_to_sheet', 1 );
                $order->save();
            }

        } catch (Exception $e) {
            // Error handling
        }
    }

    /**
     *Prepare order data according to mapping configuration
     */
    private function prepare_order_data( $order, $mapping ) {
        $data = array();
        
       // Iterate over each Woo field mapped to CF7
        foreach ( $mapping as $woo_field => $cf7_field ) {
            if ( empty( $cf7_field ) ) {
                continue;
            }

            $value = '';
            
            // Handle special cases first
            switch ( $woo_field ) {
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
                default:
                    // Handle billing_full_name specially (combine first + last)
                    if ( $woo_field === 'billing_full_name' ) {
                        $first_name = $order->get_billing_first_name();
                        $last_name = $order->get_billing_last_name();
                        $value = trim( $first_name . ' ' . $last_name );
                    }
                    // Handle billing and shipping fields dynamically
                    elseif ( strpos( $woo_field, 'billing_' ) === 0 ) {
                        $method_name = 'get_' . $woo_field;
                        if ( method_exists( $order, $method_name ) ) {
                            $value = $order->$method_name();
                        } else {
                            // Fallback for meta fields
                            $value = $order->get_meta( '_' . $woo_field );
                        }
                    } elseif ( strpos( $woo_field, 'shipping_' ) === 0 ) {
                        $method_name = 'get_' . $woo_field;
                        if ( method_exists( $order, $method_name ) ) {
                            $value = $order->$method_name();
                        } else {

                            $value = $order->get_meta( '_' . $woo_field );
                        }
                    }
                    break;
            }
            
            if ( !empty( $value ) ) {
                $data[ $cf7_field ] = ( string ) $value;
            }
        }

        // Add general meta information
        $metadata_fields = array(
            'submit-time' => cwsc_get_current_timestamp(),
            'customer-source' => cwsc_get_referrer_source(),
            'order-link' => cwsc_get_current_url(),
            'buy-link' => cwsc_get_current_url(),
            'source' => 'woocommerce',
            'order_id' => $order->get_id(),
        );
        
        foreach ($metadata_fields as $key => $value) {
            if (!isset($data[$key]) || empty($data[$key])) {
                $data[$key] = $value;
            }
        }

        return $data;
    }
}

