<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SRF_Admin_Menu {

	const PARENT_SLUG = 'srf-main';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_parent_menu' ), 9 );
	}

	public static function register_parent_menu() {

		// Parent menu
		add_menu_page(
			__( 'Service and Subscription', 'service-requests-form' ),
			__( 'Service and Subscription', 'service-requests-form' ),
			'edit_posts',
			self::PARENT_SLUG,
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-clipboard',
			26
		);

		// Submenus that point to CPT screens
		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Service Requests', 'service-requests-form' ),
			__( 'Service Requests', 'service-requests-form' ),
			'edit_posts',
			'edit.php?post_type=service_request'
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Add New Request', 'service-requests-form' ),
			__( 'Add New Request', 'service-requests-form' ),
			'edit_posts',
			'post-new.php?post_type=service_request'
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Services', 'service-requests-form' ),
			__( 'Services', 'service-requests-form' ),
			'edit_posts',
			'edit.php?post_type=sr_service'
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Add New Service', 'service-requests-form' ),
			__( 'Add New Service', 'service-requests-form' ),
			'edit_posts',
			'post-new.php?post_type=sr_service'
		);

		// Optional: keep Settings under this menu too (if you have a settings page)
		// add_submenu_page(self::PARENT_SLUG, 'Settings', 'Settings', 'manage_options', 'srf-settings', ...);
	}

	public static function render_dashboard() {
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Service and Subscription', 'service-requests-form' ) . '</h1>';
		echo '<p>' . esc_html__( 'Use the menu items on the left to manage services, requests, storage, and settings.', 'service-requests-form' ) . '</p>';
		echo '</div>';
	}
}
