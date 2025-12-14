<?php
/**
 * Plugin Name: Service Requests Form
 * Plugin URI:  https://Semlingerpro.de
 * Description: Front-end service request form with admin management and service content dashboard.
 * Version:     0.5.9
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
        public $version = '0.5.9';

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

/**
 * Frontend scripts/styles for the form page.
 */
function srf_enqueue_frontend_scripts() {

    // Determine if we are on a page where the form is present
    $should_load = false;

    // Option A: specific page slug (keep your original intent)
    if ( is_page( 'your-form-page-slug' ) ) {
        $should_load = true;
    }

    // Option B: shortcode present (safe check)
    if ( ! $should_load && is_singular() ) {
        global $post;
        if ( $post instanceof WP_Post && ! empty( $post->post_content ) ) {
            if ( has_shortcode( $post->post_content, 'srf_form' ) ) {
                $should_load = true;
            }
        }
    }

    if ( ! $should_load ) {
        return;
    }

    // Enqueue JS (keep jquery dependency since your earlier snippet used it; harmless even if unused)
    wp_enqueue_script(
        'srf-frontend-js',
        plugin_dir_url( __FILE__ ) . 'assets/js/frontend.js',
        array( 'jquery' ),
        SRF_VERSION,
        true
    );

    // Enqueue CSS
    wp_enqueue_style(
        'srf-frontend-css',
        plugin_dir_url( __FILE__ ) . 'assets/css/frontend.css',
        array(),
        SRF_VERSION
    );

    // IMPORTANT: CPT slug should match your registration / hooks.
    // Your hooks clearly indicate CPT = "sr_service"
    $service_posts = get_posts( array(
        'post_type'      => 'sr_service',
        'numberposts'    => -1,
        'post_status'    => 'publish',
        'orderby'        => 'menu_order title',
        'order'          => 'ASC',
        'no_found_rows'  => true,
        'suppress_filters' => false,
    ) );

    $services = array();

    foreach ( $service_posts as $post ) {

        // Images stored as attachment IDs in post meta "service_images"
        $images    = array();
        $image_ids = get_post_meta( $post->ID, 'service_images', true );

        if ( $image_ids && is_array( $image_ids ) ) {
            foreach ( $image_ids as $image_id ) {
                $image_url = wp_get_attachment_image_url( $image_id, 'large' );
                if ( ! $image_url ) {
                    continue;
                }

                // Provide structure expected by your JS slider: {url, alt}
                $images[] = array(
                    'url' => $image_url,
                    'alt' => get_post_meta( $image_id, '_wp_attachment_image_alt', true ),
                );
            }
        }

        $services[ (string) $post->ID ] = array(
            'id'      => (string) $post->ID,
            'title'   => get_the_title( $post ),
            'content' => apply_filters( 'the_content', $post->post_content ),
            'images'  => $images,
        );
    }

    // Localize services as "srfServices"
    wp_localize_script( 'srf-frontend-js', 'srfServices', $services );

    // OPTIONAL: also provide window.srfServices alias for code that expects it
    wp_add_inline_script(
        'srf-frontend-js',
        'window.srfServices = window.srfServices || (typeof srfServices !== "undefined" ? srfServices : {});',
        'before'
    );
}
add_action( 'wp_enqueue_scripts', 'srf_enqueue_frontend_scripts' );


// Add this after the class definition
add_action( 'wp_enqueue_scripts', function() {

    // Keep your original function, but DO NOT globally dequeue Elementor frontend
    // because it can break the entire site.
    // If you still need to prevent conflicts, only do it on the SRF form page.
    if ( defined( 'ELEMENTOR_VERSION' ) ) {

        $is_srf_context = false;

        if ( is_page( 'your-form-page-slug' ) ) {
            $is_srf_context = true;
        } elseif ( is_singular() ) {
            global $post;
            if ( $post instanceof WP_Post && has_shortcode( $post->post_content, 'srf_form' ) ) {
                $is_srf_context = true;
            }
        }

        // Only if you're absolutely sure you must disable Elementor on the form page:
        // (Comment these out unless you have a confirmed conflict that requires it.)
        /*
        if ( $is_srf_context ) {
            wp_dequeue_style( 'elementor-frontend' );
            wp_dequeue_script( 'elementor-frontend' );
        }
        */
    }
}, 20 );
