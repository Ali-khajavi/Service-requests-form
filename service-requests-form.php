<?php
/**
 * Plugin Name: Service Requests Form
 * Plugin URI:  https://Semlingerpro.de
 * Description: Front-end service request form with admin management and service content dashboard.
 * Version:     0.7.6
 * Author:      Ali Khajavi
 * Author URI:  https://Semlingerpro.de
 * Text Domain: service-requests-form
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Service_Requests_Form {

	/** @var Service_Requests_Form|null */
	private static $instance = null;

	/** @var string */
	public $version = '0.7.6';

	private function __construct() {}
	private function __clone() {}
	public function __wakeup() {}

/** @return Service_Requests_Form */
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

		// Core / CPT
		require_once SRF_PLUGIN_DIR . 'includes/class-sr-cpt.php';
		require_once SRF_PLUGIN_DIR . 'includes/class-sr-services-cpt.php';
		require_once SRF_PLUGIN_DIR . 'includes/class-sr-service-data.php';

		// Admin
		require_once SRF_PLUGIN_DIR . 'includes/class-srf-admin-menu.php';
		require_once SRF_PLUGIN_DIR . 'includes/class-srf-admin-status.php';
		require_once SRF_PLUGIN_DIR . 'includes/class-srf-admin-storage.php';

		// Main
		require_once SRF_PLUGIN_DIR . 'includes/class-sr-form-handler.php';
		require_once SRF_PLUGIN_DIR . 'includes/class-sr-myaccount.php';
		require_once SRF_PLUGIN_DIR . 'includes/class-sr-settings.php';
	}

	private function init_hooks() {

		// Translations
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ), 0 );

		// CPTs
		add_action( 'init', array( 'SR_CPT', 'register_cpt' ) );
		add_action( 'init', array( 'SR_Services_CPT', 'register_cpt' ) );

		// Service CPT admin UI
		add_action( 'add_meta_boxes', array( 'SR_Services_CPT', 'add_meta_boxes' ) );
		add_action( 'save_post_sr_service', array( 'SR_Services_CPT', 'save_service_meta' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( 'SR_Services_CPT', 'enqueue_admin_assets' ) );
		add_filter( 'manage_sr_service_posts_columns', array( 'SR_Services_CPT', 'add_admin_columns' ) );
		add_action( 'manage_sr_service_posts_custom_column', array( 'SR_Services_CPT', 'render_admin_columns' ), 10, 2 );

		// Admin Menu
		if ( class_exists( 'SRF_Admin_Menu' ) ) {
			SRF_Admin_Menu::init();
		}

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

		// My Account
		add_action( 'plugins_loaded', array( $this, 'init_myaccount' ), 20 );

		// One-time rewrite flush after plugin updates (prevents legacy endpoint rules like EP_ROOT).
		add_action( 'admin_init', array( $this, 'maybe_flush_rewrite_rules' ), 20 );
	}

	/**
	 * Flush rewrite rules once after version changes.
	 *
	 * Why: earlier versions registered the endpoint with broader flags, which can leave
	 * legacy rewrite rules behind until a flush happens. This forces a single flush
	 * after updates so the My Account URLs stay under /my-account/.
	 */
	public function maybe_flush_rewrite_rules() {
		if ( ! is_admin() ) {
			return;
		}

		$stored = (string) get_option( 'srf_rewrite_version', '' );
		if ( $stored === (string) $this->version ) {
			return;
		}

		// Ensure endpoint rules are registered before flushing.
		if ( class_exists( 'SRF_MyAccount' ) && method_exists( 'SRF_MyAccount', 'add_endpoints' ) ) {
			SRF_MyAccount::add_endpoints();
		}

		flush_rewrite_rules();
		update_option( 'srf_rewrite_version', (string) $this->version, false );
	}

	public function init_myaccount() {
		if ( class_exists( 'SRF_MyAccount' ) ) {
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

// ======================================================
// Debug logging helper (safe)
// ======================================================
function srf_log( $msg ) {
	if ( ! ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( empty( $_GET['srf_debug'] ) ) {
		return;
	}
	error_log( '[SRF] ' . $msg );
}

/**
 * Create Business User role (used to allow submitting requests + uploading files)
 */
function srf_add_business_user_role() {

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
function srf_activate_plugin( $network_wide = false ) {

	srf_add_business_user_role();

	// Ensure endpoints are registered before flushing rewrite rules
	$myacc_file = SRF_PLUGIN_DIR . 'includes/class-sr-myaccount.php';
	if ( ! class_exists( 'SRF_MyAccount' ) && file_exists( $myacc_file ) ) {
		require_once $myacc_file;
	}

	if ( class_exists( 'SRF_MyAccount' ) && method_exists( 'SRF_MyAccount', 'add_endpoints' ) ) {
		SRF_MyAccount::add_endpoints();
	}

	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'srf_activate_plugin' );

/**
 * Deactivation
 */
function srf_deactivate_plugin( $network_wide = false ) {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'srf_deactivate_plugin' );
