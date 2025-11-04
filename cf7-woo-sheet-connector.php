<?php
/**
 * Plugin Name: CF7 Google Sheet Connector
 * Plugin URI: https://example.com/cf7-sheet-connector
 * Description: Connect Contact Form 7 to Google Sheets with automatic field mapping.
 * Version: 1.0.0
 * Author: Mai Sỹ Đạt
 * License: GPL v2 or later
 * Text Domain: cf7-woo-sheet-connector
 */


if ( !defined( 'ABSPATH' ) ) {
    exit;
}

// Define constants
define( 'CWSC_PLUGIN_FILE', __FILE__ );
define( 'CWSC_PLUGIN_DIR', plugin_dir_path( __FILE__) );
define( 'CWSC_PLUGIN_URL', plugin_dir_url( __FILE__) );
define( 'CWSC_VERSION', '1.0.0' );

/**
 * Main class
 */
class CF7_Sheet_Connector {

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Get single instance
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action( 'plugins_loaded', array( $this, 'init' ) );
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
    }

    /**
     * Init plugin
     */
    public function init() {
        // Check requirements
        if ( !$this->check_requirements() ) {
            return;
        }

        // Include required files
        $this->include_files();


        // init components
        $this->init_components();

        // Enqueue admin assets centrally
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        // Enqueue frontend assets
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_front_assets' ) );
    }

    /**
     * Check plugin requirements
     */
    private function check_requirements() {
        $required_plugins = array();
        
        if ( !class_exists( 'WPCF7' ) ) {
            $required_plugins[] = 'Contact Form 7';
        }

        if ( !empty( $required_plugins ) ) {
            add_action( 'admin_notices', function() use ( $required_plugins ) {
                echo '<div class="notice notice-error"><p>';
                printf(
                    __( 'CF7 Google Sheet Connector requires the following plugins: %s', 'cf7-woo-sheet-connector' ),
                    implode( ', ', $required_plugins )
                );
                echo '</p></div>';
            });
            return false;
        }
        return true;
    }

    /**
     * Include required files
     */
    private function include_files() {
        require_once CWSC_PLUGIN_DIR . 'includes/class-cwsc-google-client.php';
        require_once CWSC_PLUGIN_DIR . 'helpers.php';
        require_once CWSC_PLUGIN_DIR . 'includes/class-cwsc-admin.php';
        require_once CWSC_PLUGIN_DIR . 'includes/class-cwsc-cf7.php';
        require_once CWSC_PLUGIN_DIR . 'includes/class-cwsc-woocommerce.php';
    }

    /**
     * Init components
     */
    private function init_components() {
        // Initialize admin
        if ( is_admin() ) {
            new CWSC_Admin();
        }

        // Initialize CF7 integration
        new CWSC_CF7();

        // Initialize WooCommerce integration
        if ( class_exists( 'WooCommerce' ) ) {
            new CWSC_WooCommerce();
        }
    }

    /**
     * Enqueue admin stylesheet for our plugin screens (centralized)
     */
    public function enqueue_admin_assets( $hook ) {
        if ( !function_exists( 'get_current_screen' ) ) {
            return;
        }
        $screen = get_current_screen();

        $is_admin_settings = ( $hook === 'settings_page_cwsc-settings' );
        $is_woo_page = ( $hook === 'woocommerce_page_cwsc-woo-sheet' );
        $is_cf7 = ( $screen && isset( $screen->post_type ) && $screen->post_type === 'wpcf7_contact_form' )
            || ( is_string( $hook ) && strpos( $hook, 'wpcf7' ) !== false );

        if ( $is_admin_settings || $is_woo_page || $is_cf7 ) {
            wp_enqueue_style( 'cwsc-admin', CWSC_PLUGIN_URL . 'assets/css/admin.css', array(), CWSC_VERSION );
        }
    }

    /**
     * Enqueue frontend script to capture source, order-link and buy-link
     */
    public function enqueue_front_assets() {
        // Load only on frontend
        if ( is_admin() ) {
            return;
        }

        wp_enqueue_script( 'cwsc-frontend', CWSC_PLUGIN_URL . 'assets/js/frontend.js', array(), CWSC_VERSION, true );

        $localize = array(
            'cart_links' => array(),
        );

        if ( class_exists( 'WooCommerce' ) && function_exists( 'WC' ) && WC()->cart ) {
            $cart = WC()->cart->get_cart();
            $links = array();
            foreach ( $cart as $cart_item_key => $cart_item ) {
                if ( ! empty( $cart_item['product_id'] ) ) {
                    $permalink = get_permalink( $cart_item['product_id'] );
                    if ( $permalink ) {
                        $links[] = $permalink;
                    }
                }
            }
            $localize['cart_links'] = array_values( array_unique( $links ) );
        }

        wp_localize_script( 'cwsc-frontend', 'cwsc_frontend', $localize );
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options
        add_option('cwsc_settings', array(
            'google_service_account' => '',
            'test_connection_status' => '',
            'last_sync_status' => ''
        ));
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // updating
    }
}

// Initialize the plugin
CF7_Sheet_Connector::get_instance();
