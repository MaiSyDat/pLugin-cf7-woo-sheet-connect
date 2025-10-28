    <?php
/**
 * Admin class for CF7 WooCommerce Sheet Connector
 */

if ( !defined( 'ABSPATH' ) ) {
    exit;
}

class CWSC_Admin {

    /**
     * Contructor
     */
    public function __construct() {
        add_action( 'admin_menu', array($this, 'add_admin_menu' ) );
        add_action( 'admin_init', array($this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        add_action( 'wp_ajax_cwsc_test_connection', array( $this, 'ajax_test_connection' ) );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __( 'Connect to Google API service', 'cf7-woo-sheet-connector' ),
            __( 'Connect to Google API service', 'cf7-woo-sheet-connector' ),
            'manage_options',
            'cwsc-settings',
            array( $this, 'settings_page' )
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting( 'cwsc_settings', 'cwsc_settings', array( $this, 'sanitize_settings' ) );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts( $hook ) {
        if ($hook !== 'settings_page_cwsc-settings') {
            return;
        }

        wp_enqueue_script( 'cwsc-admin', CWSC_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), CWSC_VERSION, true );
        wp_enqueue_style( 'cwsc-admin', CWSC_PLUGIN_URL . 'assets/css/admin.css', array(), CWSC_VERSION );

        wp_localize_script( 'cwsc-admin', 'cwsc_ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'cwsc_nonce' ),
            'strings' => array(
                'testing_connection' => __( 'Checking connection...', 'cf7-woo-sheet-connector' ),
                'connection_success' => __( 'Connection successful!', 'cf7-woo-sheet-connector' ),
                'connection_failed' => __( 'Connection failed!', 'cf7-woo-sheet-connector' ),
            )
        ));
    }

    /**
     * Settings page
     */
    public function settings_page() {
        $settings = cwsc_get_settings();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields( 'cwsc_settings' );
                do_settings_sections( 'cwsc_settings' );
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="google_service_account"><?php _e( 'Google Service Account', 'cf7-woo-sheet-connector' ); ?></label>
                        </th>
                        <td>
                            <textarea id="google_service_account" name="cwsc_settings[google_service_account]" rows="10" class="large-text code"><?php echo esc_textarea( $settings['google_service_account'] ?? '' ); ?></textarea>
                            <p class="description">
                                <?php _e( 'Paste the entire contents of the Google Service Account JSON file into the box above.', 'cf7-woo-sheet-connector' ); ?>
                                <br>
                                <a href="https://console.cloud.google.com/apis/credentials" target="_blank"><?php _e( 'Get credentials from Google Cloud Console', 'cf7-woo-sheet-connector' ); ?></a>
                            </p>
                        </td>
                    </tr>
                    <!-- Check connection -->
                    <tr>
                        <th scope="row"><?php _e( 'Check connection', 'cf7-woo-sheet-connector' ); ?></th>
                        <td>
                            <button type="button" id="test-connection" class="button button-secondary">
                                <?php _e( 'Check connection', 'cf7-woo-sheet-connector' ); ?>
                            </button>
                            <span id="connection-status"></span>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Sanitize data before saving settings
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();
        
        // Sanitize Google Service Account JSON
        if ( isset( $input['google_service_account'] ) ) {
            $json = $input['google_service_account'];
            if ( !empty( $json ) ) {
                // Validate JSON
                json_decode( $json );
                if ( json_last_error() === JSON_ERROR_NONE ) {
                    $sanitized['google_service_account'] = $json;
                } else {
                    add_settings_error( 'cwsc_settings', 'invalid_json', __( 'Invalid JSON format for Google Service Account.', 'cf7-woo-sheet-connector' ) );
                }
            }
        }

        return $sanitized;
    }


    /**
     * AJAX test connection
     */
    public function ajax_test_connection() {
        check_ajax_referer( 'cwsc_nonce', 'nonce' );
        
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die(__( 'You do not have sufficient permissions to access this page.', 'cf7-woo-sheet-connector' ) );
        }

        try {
            $google_client = new CWSC_Google_Client();
            $result = $google_client->test_connection();
            
            // Update settings with test result
            $settings = cwsc_get_settings();
            $settings[ 'test_connection_status' ] = $result;
            cwsc_update_settings( $settings );
            
            wp_send_json( $result );
        } catch ( Exception $e ) {
            wp_send_json_error( array( 'message' => $e->getMessage() ) );
        }
    }
}
