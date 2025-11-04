<?php
/**
 * Helper functions
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get the plugin's general settings
 */
function cwsc_get_settings() {
    return get_option('cwsc_settings', array());
}

/**
 * Update plugin settings again
 */
function cwsc_update_settings($settings) {
    return update_option('cwsc_settings', $settings);
}

/**
 * Get the configuration for each CF7 form
 */
function cwsc_get_effective_form_settings( $form_id ) {
    $form = get_post_meta( $form_id, '_cwsc_settings', true );
    if ( !is_array( $form ) ) {
        $form = array();
    }

    $enabled = !empty($form['enabled']);
    $spreadsheet_id = $form['spreadsheet_id'] ?? '';
    $sheet_name = $form['sheet_name'] ?? 'Sheet1';

    return array(
        'enabled' => $enabled,
        'spreadsheet_id' => $spreadsheet_id,
        'sheet_name' => $sheet_name
    );
}

// Get curren time
function cwsc_get_current_timestamp() {
    return current_time( 'mysql' );
}

/**
 * Check if Google API library is available
 */
function cwsc_is_google_api_available() {
    return file_exists( CWSC_PLUGIN_DIR . 'vendor/autoload.php' );
}

/**
 * Get customer source from cookie (set by JS)
 */
function cwsc_get_referrer_source() {
    if ( isset( $_COOKIE['cwsc_customer_source'] ) ) {
        return sanitize_text_field( $_COOKIE['cwsc_customer_source'] );
    }
    return 'Trực Tiếp Trên Web';
}

/**
 * Get initial URL from cookie (first URL user landed on in current session)
 * Only uses cookie if session ID is present (set by JS for current session)
 * Falls back to current URL if cookie not found or session ID missing (old cookie)
 */
function cwsc_get_initial_url() {
    // Check cookie with session ID validation
    if ( isset( $_COOKIE['cwsc_initial_url'] ) && !empty( $_COOKIE['cwsc_initial_url'] ) ) {
        $cookie_session_id = isset( $_COOKIE['cwsc_session_id'] ) ? $_COOKIE['cwsc_session_id'] : '';
        
        $initial_url = esc_url_raw( $_COOKIE['cwsc_initial_url'] );
        
        // Only return if it's a valid URL
        if ( filter_var( $initial_url, FILTER_VALIDATE_URL ) ) {
            // Don't use thank you page or checkout as initial URL
            if ( strpos( $initial_url, 'order-received' ) === false && strpos( $initial_url, 'checkout' ) === false ) {
                // If no session ID in cookie, it's an old cookie from previous session - don't use it
                if ( !empty( $cookie_session_id ) ) {
                    return $initial_url;
                }
            }
        }
    }
    
    // Fallback: try to get from referrer if available (and not checkout)
    $referrer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( $_SERVER['HTTP_REFERER'] ) : '';
    if ( !empty( $referrer ) && strpos( $referrer, 'order-received' ) === false && strpos( $referrer, 'checkout' ) === false ) {
        // Check if referrer is from same domain (not external)
        $referrer_host = parse_url( $referrer, PHP_URL_HOST );
        $current_host = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : '';
        if ( $referrer_host === $current_host || empty( $current_host ) ) {
            return $referrer;
        }
    }
    
    // Final fallback to current URL (fresh session, no cookie)
    global $wp;
    return home_url( add_query_arg( array(), $wp->request ) );
}

/**
 * Get current page URL
 */
function cwsc_get_current_url() {
    global $wp;
    return home_url( add_query_arg( array(), $wp->request) );
}
