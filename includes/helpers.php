<?php
/**
 * Helper functions for CF7 WooCommerce Sheet Connector
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Lấy cài đặt chung của plugin
 */
function cwsc_get_settings() {
    return get_option('cwsc_settings', array());
}

/**
 * Cập nhật lại cài đặt plugin
 */
function cwsc_update_settings($settings) {
    return update_option('cwsc_settings', $settings);
}

/**
 * Lấy config riêng từng form CF7
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

/**
 * Lấy thời gian hiện tại
 */
function cwsc_get_current_timestamp() {
    return current_time( 'mysql' );
}

/**
 * Lấy IP người gửi form
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
 * Get current page URL
 */
function cwsc_get_current_url() {
    global $wp;
    return home_url( add_query_arg( array(), $wp->request) );
}
