<?php
/**
 * Plugin Name: Service Requests Form
 * Plugin URI:  https://Semlingerpro.de
 * Description: Front-end service request form with admin management and service content dashboard.
 * Version:     0.6.4.1
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
	public  $version = '0.6.4.1';

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
		define( 'SRF_VERSION', $this->version );
		define( 'SRF_PLUGIN_FILE', __FILE__ );
		define( 'SRF_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
		define( 'SRF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		define( 'SRF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
	}

	/**
	 * Load files
	 * IMPORTANT: Admin Menu FIRST
	 */
	private function includes() {

		// Admin menu (parent menu)
		require_once SRF_PLUGIN_DIR . 'includes/class-srf-admin-menu.php';

		// Admin features
		require_once SRF_PLUGIN_DIR . 'includes/class-srf-admin-status.php';
		require_once SRF_PLUGIN_DIR . 'includes/class-srf-admin-storage.php';

		// Core
		require_once SRF_PLUGIN_DIR . 'includes/class-sr-cpt.php';
		require_once SRF_PLUGIN_DIR . 'includes/class-sr-services-cpt.php';
		require_once SRF_PLUGIN_DIR . 'includes/class-sr-service-data.php';
		require_once SRF_PLUGIN_DIR . 'includes/class-sr-settings.php';
		require_once SRF_PLUGIN_DIR . 'includes/class-sr-form-handler.php';

		// WooCommerce My Account
		require_once SRF_PLUGIN_DIR . 'includes/class-sr-myaccount.php';
	}

	private function init_hooks() {

		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Admin Menu
		if ( class_exists( 'SRF_Admin_Menu' ) ) {
			SRF_Admin_Menu::init();
		}

		// CPTs
		add_action( 'init', array( 'SR_CPT', 'register_cpt' ) );
		add_action( 'init', array( 'SR_Services_CPT', 'register_cpt' ) );

		// Service CPT admin UI
		add_action( 'add_meta_boxes', array( 'SR_Services_CPT', 'add_meta_boxes' ) );
		add_action( 'save_post_sr_service', array( 'SR_Services_CPT', 'save_service_meta' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( 'SR_Services_CPT', 'enqueue_admin_assets' ) );
		add_filter( 'manage_sr_service_posts_columns', array( 'SR_Services_CPT', 'add_admin_columns' ) );
		add_action( 'manage_sr_service_posts_custom_column', array( 'SR_Services_CPT', 'render_admin_columns' ), 10, 2 );

		// Admin tools
		if ( class_exists( 'SRF_Admin_Status' ) ) {
			SRF_Admin_Status::init();
		}

		if ( class_exists( 'SRF_Admin_Storage' ) ) {
			SRF_Admin_Storage::init();
		}

		// Frontend form
		if ( class_exists( 'SR_Form_Handler' ) ) {
			SR_Form_Handler::init();
		}

		// Woo My Account
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
 * Bootstrap
 */
function SRF() {
	return Service_Requests_Form::instance();
}
SRF();

/**
 * Create Business User role (used to allow submitting requests + uploading files)
 */
function srf_add_business_user_role() {

	// Do not recreate if it already exists
	if ( get_role( 'business_user' ) ) {
		return;
	}

	add_role(
		'business_user',
		__( 'Business User', 'service-requests-form' ),
		array(
			'read'         => true,
			'upload_files' => true,
		)
	);
}

/**
 * Activation
 */
function srf_activate_plugin() {

	// ✅ Ensure Business role exists
	srf_add_business_user_role();

	// Ensure endpoint is registered before flushing rewrite rules
	if ( class_exists( 'SRF_MyAccount' ) ) {
		SRF_MyAccount::add_endpoint();
	}

	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'srf_activate_plugin' );

/**
 * Deactivation
 */
function srf_deactivate_plugin() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'srf_deactivate_plugin' );
