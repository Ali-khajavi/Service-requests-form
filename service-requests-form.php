<?php
/**
 * Plugin Name: Service Requests Form
 * Plugin URI:  https://Semlingerpro.de
 * Description: Front-end service request form with admin management and service content dashboard.
 * Version:     0.5.7
 * Author:      Ali Khajavi
 * Author URI:  https://Semlingerpro.de
 * Text Domain: service-requests-form
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if ( ! class_exists( 'Service_Requests_Form' ) ) {

    final class Service_Requests_Form {

        /**
         * Plugin instance.
         *
         * @var Service_Requests_Form
         */
        private static $instance = null;

        /**
         * Plugin version.
         *
         * @var string
         */
        public $version = '0.5.7';

        /**
         * Singleton instance.
         *
         * @return Service_Requests_Form
         */
        public static function instance() {
            if ( is_null( self::$instance ) ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * Constructor.
         */
        private function __construct() {
            $this->define_constants();
            $this->includes();
            $this->init_hooks();
        }

        /**
         * Define plugin constants.
         */
        private function define_constants() {
            define( 'SRF_VERSION', $this->version );
            define( 'SRF_PLUGIN_FILE', __FILE__ );
            define( 'SRF_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
            define( 'SRF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
            define( 'SRF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
        }

        /**
         * Include required files.
         */
        private function includes() {
            require_once SRF_PLUGIN_DIR . 'includes/class-sr-cpt.php';
            require_once SRF_PLUGIN_DIR . 'includes/class-sr-form-handler.php';
            require_once SRF_PLUGIN_DIR . 'includes/class-sr-settings.php';

            // NEW: Services CPT (service content dashboard)
            require_once SRF_PLUGIN_DIR . 'includes/class-sr-services-cpt.php';

            // NEW: Service data helper (used by front-end templates)
            require_once SRF_PLUGIN_DIR . 'includes/class-sr-service-data.php';
        }


        /**
         * Initialize hooks.
         */
        private function init_hooks() {
            // Service Requests CPT
            add_action( 'init', array( 'SR_CPT', 'register_cpt' ) );

            // NEW: Services CPT (sr_service)
            add_action( 'init', array( 'SR_Services_CPT', 'register_cpt' ) );
            add_action( 'add_meta_boxes', array( 'SR_Services_CPT', 'add_meta_boxes' ) );
            add_action( 'save_post_sr_service', array( 'SR_Services_CPT', 'save_service_meta' ), 10, 2 );
            add_action( 'admin_enqueue_scripts', array( 'SR_Services_CPT', 'enqueue_admin_assets' ) );
            add_filter( 'manage_sr_service_posts_columns', array( 'SR_Services_CPT', 'add_admin_columns' ) );
            add_action( 'manage_sr_service_posts_custom_column', array( 'SR_Services_CPT', 'render_admin_columns' ), 10, 2 );

            // Shortcode / front-end
            add_action( 'init', array( 'SR_Form_Handler', 'init' ) );

            // Settings page
            add_action( 'admin_menu', array( 'SR_Settings', 'add_settings_page' ) );
        }

    }

}

/**
 * Returns the main instance.
 *
 * @return Service_Requests_Form
 */
function SRF() {
    return Service_Requests_Form::instance();
}

// Start the plugin.
SRF();
