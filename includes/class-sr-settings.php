<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SR_Settings' ) ) {

    class SR_Settings {

        /**
         * Add settings page.
         */
        public static function add_settings_page() {
            add_options_page(
                __( 'Service Requests Settings', 'service-requests-form' ),
                __( 'Service Requests', 'service-requests-form' ),
                'manage_options',
                'service-requests-settings',
                array( __CLASS__, 'render_settings_page' )
            );
        }

        /**
         * Render settings page (placeholder).
         */
        public static function render_settings_page() {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }
            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'Service Requests Settings', 'service-requests-form' ); ?></h1>
                <p><?php esc_html_e( 'Settings will be added here in a later step.', 'service-requests-form' ); ?></p>
            </div>
            <?php
        }

    }

}
