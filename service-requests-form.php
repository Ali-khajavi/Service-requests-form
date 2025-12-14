<?php
/**
 * Plugin Name: Service Requests Form
 * Plugin URI:  https://Semlingerpro.de
 * Description: Front-end service request form with admin management and service content dashboard.
 * Version:     0.6.1
 * Author:      Ali Khajavi
 * Author URI:  https://Semlingerpro.de
 * Text Domain: service-requests-form
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Service_Requests_Form {

	private static $instance = null;
	public  $version = '0.6.1';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->setup();
		}
		return self::$instance;
	}

	private function setup() {
		$this->define_constants();
		$this->includes();
		$this->init_hooks();
	}

	private function define_constants() {
		if ( ! defined( 'SRF_VERSION' ) ) {
			define( 'SRF_VERSION', $this->version );
		}
		if ( ! defined( 'SRF_PLUGIN_FILE' ) ) {
			define( 'SRF_PLUGIN_FILE', __FILE__ );
		}
		if ( ! defined( 'SRF_PLUGIN_BASENAME' ) ) {
			define( 'SRF_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
		}
		if ( ! defined( 'SRF_PLUGIN_DIR' ) ) {
			define( 'SRF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		}
		if ( ! defined( 'SRF_PLUGIN_URL' ) ) {
			define( 'SRF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		}
	}

	private function includes() {
		require_once SRF_PLUGIN_DIR . 'includes/class-sr-cpt.php';
		require_once SRF_PLUGIN_DIR . 'includes/class-sr-services-cpt.php';
		require_once SRF_PLUGIN_DIR . 'includes/class-sr-service-data.php';
		require_once SRF_PLUGIN_DIR . 'includes/class-sr-settings.php';
		require_once SRF_PLUGIN_DIR . 'includes/class-sr-form-handler.php';

		// NEW: My Account integration (WooCommerce)
		require_once SRF_PLUGIN_DIR . 'includes/class-sr-myaccount.php';
	}

	private function init_hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// CPTs
		add_action( 'init', array( 'SR_CPT', 'register_cpt' ) );
		add_action( 'init', array( 'SR_Services_CPT', 'register_cpt' ) );

		add_action( 'add_meta_boxes', array( 'SR_Services_CPT', 'add_meta_boxes' ) );
		add_action( 'save_post_sr_service', array( 'SR_Services_CPT', 'save_service_meta' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( 'SR_Services_CPT', 'enqueue_admin_assets' ) );

		add_filter( 'manage_sr_service_posts_columns', array( 'SR_Services_CPT', 'add_admin_columns' ) );
		add_action( 'manage_sr_service_posts_custom_column', array( 'SR_Services_CPT', 'render_admin_columns' ), 10, 2 );

		// Form handler
		if ( class_exists( 'SR_Form_Handler' ) ) {
			SR_Form_Handler::init();
		}

		// My Account (only if WooCommerce)
		if ( class_exists( 'WooCommerce' ) && class_exists( 'SRF_MyAccount' ) ) {
			SRF_MyAccount::init();
		}
	}

	public function load_textdomain() {
		load_plugin_textdomain(
			'service-requests-form',
			false,
			dirname( SRF_PLUGIN_BASENAME ) . '/languages'
		);
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

// Start plugin.
SRF();

/**
 * Activation: flush rewrite rules (needed for My Account endpoint).
 */
function srf_activate_plugin() {
	// Ensure endpoint is registered before flush
	if ( class_exists( 'SRF_MyAccount' ) ) {
		SRF_MyAccount::add_endpoint();
	}
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'srf_activate_plugin' );

/**
 * Deactivation: flush rewrite rules.
 */
function srf_deactivate_plugin() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'srf_deactivate_plugin' );
