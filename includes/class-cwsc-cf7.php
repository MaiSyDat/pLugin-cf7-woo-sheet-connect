<?php
/**
 * Contact Form 7 Integration
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
     * Add “Google Sheets” tab in CF7 form edit page
     */
    public function add_editor_panels( $panels ) {
        $panels[ 'google-sheets' ] = array(
            'title' => __( 'Google Sheets', 'cf7-woo-sheet-connector' ),
            'callback' => array( $this, 'editor_panel_content' )
        );
        return $panels;
    }

    /**
     * “Google Sheets” interface
     */
    public function editor_panel_content( $contact_form ) {
        $form_id = $contact_form->id();
        // Get current setting form
        $settings = cwsc_get_effective_form_settings( $form_id );

         // Defaul value
        $settings = wp_parse_args( $settings, array(
            'enabled' => false,
            'spreadsheet_id' => '',
            'sheet_name' => '',
            'mapping' => array()
        ));

        // Check Google API is available
        $google_api_available = cwsc_is_google_api_available();
        $global_settings = cwsc_get_settings();
        $has_credentials = !empty( $global_settings['google_service_account'] );
        ?>
        <div class="cwsc-sheet-cf7-setting">
            <!-- form setting -->
            <fieldset>
                <legend><?php _e( 'Google Sheets settings', 'cf7-woo-sheet-connector' ); ?></legend>
                
                <table class="form-table cwsc-form-table">
                    <tr>
                        <th scope="row">
                            <label for="cwsc_enabled"><?php _e( 'Activate Google Sheets', 'cf7-woo-sheet-connector' ); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="cwsc_enabled" name="cwsc_enabled" value="1" <?php checked( $settings['enabled'] ); ?>>
                            <label for="cwsc_enabled"><?php _e( 'Send form data to Google Sheets', 'cf7-woo-sheet-connector' ); ?></label>
                        </td>
                    </tr>
                    
                    <tr class="cwsc-settings" <?php echo !$settings[ 'enabled' ] ? 'style="display:none;"' : ''; ?>>
                        <th scope="row">
                            <label for="cwsc_spreadsheet_id"><?php _e( 'ID Google Sheet', 'cf7-woo-sheet-connector' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="cwsc_spreadsheet_id" name="cwsc_spreadsheet_id" value="<?php echo esc_attr( $settings[ 'spreadsheet_id' ] ); ?>" class="large-text">
                            <p class="description">
                                <?php _e( 'Google Sheet ID from URL. Example: 1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms', 'cf7-woo-sheet-connector' ); ?>
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
                            <label for="cwsc_sheet_name"><?php _e( 'Sheet name', 'cf7-woo-sheet-connector' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="cwsc_sheet_name" name="cwsc_sheet_name" value="<?php echo esc_attr($settings['sheet_name']); ?>" class="regular-text">
                            <p class="description"><?php _e( 'Name of the sheet/tab in Google Sheets (default: Sheet1)', 'cf7-woo-sheet-connector' ); ?></p>
                        </td>
                    </tr>
                </table>
            </fieldset>
        </div>
        <?php
    }

    /**
     * Save setting form
     */
    public function save_contact_form( $contact_form ) {
        $form_id = $contact_form->id();
        
        // Get previously saved data to avoid overwriting
        $existing = get_post_meta( $form_id, '_cwsc_settings', true );
        if ( !is_array( $existing ) ) {
            $existing = array();
        }

        $settings = $existing;

       // Save the fields in the form
        if ( array_key_exists( 'cwsc_enabled', $_POST ) ) {
            $settings['enabled'] = isset( $_POST['cwsc_enabled'] );
        }
        if ( array_key_exists( 'cwsc_spreadsheet_id', $_POST ) ) {
            $settings['spreadsheet_id'] = sanitize_text_field( $_POST['cwsc_spreadsheet_id'] );
        }
        if ( array_key_exists( 'cwsc_sheet_name', $_POST ) ) {
            $settings['sheet_name'] = sanitize_text_field( $_POST['cwsc_sheet_name'] );
        }

        // Save to post meta
        update_post_meta( $form_id, '_cwsc_settings', $settings );
    }

    /**
     * Push data to Google Sheet
     */
    public function send_to_sheet( $contact_form ) {
        $form_id = $contact_form->id();
        $settings = get_post_meta( $form_id, '_cwsc_settings', true );
        

        // Return
        if ( empty( $settings['enabled'] ) || empty( $settings['spreadsheet_id'] ) ) {
            return;
        }

        // Get user send form data
        $submission = class_exists( 'WPCF7_Submission' ) ? WPCF7_Submission::get_instance() : null;

        try {
            // Initialize Google API client
            $google_client = new CWSC_Google_Client();
            
            // if != submission instance → fallback use $_POST
            $posted_data = $submission ? ( array ) $submission->get_posted_data() : ( array ) $_POST;

            // Prepare data to upload to sheet
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
     * Prepare form data for Google Sheets
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

        // Add helpful metadata columns only if not already present in form data
        $metadata_fields = array(
            'submit-time' => cwsc_get_current_timestamp(),
            'customer-source' => cwsc_get_referrer_source(),
            'order-link' => cwsc_get_current_url(),
            'buy-link' => cwsc_get_current_url()
        );
        
        foreach ($metadata_fields as $key => $value) {
            if (!isset($data[$key]) || empty($data[$key])) {
                $data[$key] = $value;
            }
        }

        return $data;
    }
}
