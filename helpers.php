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
 * Get ip
 */
function cwsc_get_customer_ip() {
    $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
    
    foreach ( $ip_keys as $key ) {
        if ( array_key_exists( $key, $_SERVER ) === true ) {
            foreach ( explode( ',', $_SERVER[ $key ] ) as $ip ) {
                $ip = trim($ip);
                if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false) {
                    return $ip;
                }
            }
        }
    }
    
    return isset( $_SERVER[ 'REMOTE_ADDR' ] ) ? $_SERVER[ 'REMOTE_ADDR' ] : '';
}

/**
 * Check if Google API library is available
 */
function cwsc_is_google_api_available() {
    return file_exists( CWSC_PLUGIN_DIR . 'vendor/autoload.php' );
}

/**
 * Get referrer URL
 */
function cwsc_get_referrer() {
    return isset( $_SERVER[ 'HTTP_REFERER' ] ) ? $_SERVER[ 'HTTP_REFERER' ] : '';
}

/**
 * Get referrer source name from URL
 */
function cwsc_get_referrer_source() {
    $referrer = cwsc_get_referrer();
    
    if ( empty( $referrer ) ) {
        return '';
    }
    
    // Parse URL to get components
    $url_parts = parse_url( $referrer );
    $host = isset( $url_parts['host'] ) ? strtolower( $url_parts['host'] ) : '';
    $query = isset( $url_parts['query'] ) ? $url_parts['query'] : '';
    parse_str( $query, $query_params );
    
    // Check for Facebook source
    if ( isset( $query_params['fbclid'] ) || strpos( $host, 'facebook.com' ) !== false ) {
        return 'Facebook';
    }
    
    // Check for TikTok source
    if ( strpos( $query, 'ttclid' ) !== false || strpos( $host, 'tiktok.com' ) !== false ) {
        return 'TikTok';
    }
    
    // Check for Shopee source
    if ( strpos( $host, 'shopee' ) !== false || isset( $query_params['utm_source'] ) && strpos( $query_params['utm_source'], 'shopee' ) !== false ) {
        return 'Shopee';
    }
    
    // Check for Zalo source
    if ( strpos( $host, 'zalo' ) !== false || isset( $query_params['utm_source'] ) && strpos( $query_params['utm_source'], 'zalo' ) !== false ) {
        return 'Zalo';
    }
    
    // Check for Google source
    if ( strpos( $host, 'google.com' ) !== false || isset( $query_params['gclid'] ) ) {
        return 'Google';
    }
    
    // Check for Instagram source
    if ( strpos( $host, 'instagram.com' ) !== false ) {
        return 'Instagram';
    }
    
    // Check for UTM source parameter
    if ( isset( $query_params['utm_source'] ) ) {
        return ucfirst( $query_params['utm_source'] );
    }
    
    // Check for referrer domain
    if ( !empty( $host ) ) {
        // Extract main domain (e.g., "example.com" from "www.example.com")
        $domain = preg_replace( '/^www\./', '', $host );
        return ucfirst( $domain );
    }
    
    return $referrer;
}

/**
 * Get current page URL
 */
function cwsc_get_current_url() {
    global $wp;
    return home_url( add_query_arg( array(), $wp->request) );
}
