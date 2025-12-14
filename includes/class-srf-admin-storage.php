<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SRF_Admin_Storage {

	const SUBMENU_SLUG = 'srf-storage';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 20 );
		add_action( 'admin_post_srf_clear_user_storage', array( __CLASS__, 'handle_clear_user_storage' ) );
	}

	public static function register_menu() {
		if ( ! class_exists( 'SRF_Admin_Menu' ) ) {
			return;
		}

		add_submenu_page(
			SRF_Admin_Menu::PARENT_SLUG,
			__( 'Storage', 'service-requests-form' ),
			__( 'Storage', 'service-requests-form' ),
			'manage_options',
			self::SUBMENU_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$quota = SR_Form_Handler::get_user_quota_bytes_public();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Service Request Storage', 'service-requests-form' ) . '</h1>';
		echo '<p>' . esc_html__( 'Each user is limited to 1GB total upload storage. When a request is marked DONE, files are deleted and the user storage is freed.', 'service-requests-form' ) . '</p>';

		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'User', 'service-requests-form' ) . '</th>';
		echo '<th>' . esc_html__( 'Used', 'service-requests-form' ) . '</th>';
		echo '<th>' . esc_html__( 'Quota', 'service-requests-form' ) . '</th>';
		echo '<th>' . esc_html__( 'Action', 'service-requests-form' ) . '</th>';
		echo '</tr></thead><tbody>';

		$users = get_users( array(
			'fields' => array( 'ID', 'display_name', 'user_email' ),
			'number' => 200,
		) );

		if ( empty( $users ) ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No users found.', 'service-requests-form' ) . '</td></tr>';
		} else {
			foreach ( $users as $u ) {
				$used = (int) get_user_meta( $u->ID, '_srf_storage_used_bytes', true );
				$used = max( 0, $used );

				echo '<tr>';
				echo '<td>' . esc_html( $u->display_name ) . '<br><small>' . esc_html( $u->user_email ) . '</small></td>';
				echo '<td>' . esc_html( size_format( $used ) ) . '</td>';
				echo '<td>' . esc_html( size_format( $quota ) ) . '</td>';
				echo '<td>';

				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;">';
				echo '<input type="hidden" name="action" value="srf_clear_user_storage">';
				echo '<input type="hidden" name="user_id" value="' . (int) $u->ID . '">';
				wp_nonce_field( 'srf_clear_user_storage_' . (int) $u->ID, 'srf_nonce' );
				submit_button( __( 'Clear storage', 'service-requests-form' ), 'secondary', 'submit', false, array(
					'onclick' => "return confirm('This will delete ALL service request files for this user and reset their used storage. Continue?');",
				) );
				echo '</form>';

				echo '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
		echo '</div>';
	}

	public static function handle_clear_user_storage() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		if ( ! $user_id ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::SUBMENU_SLUG ) );
			exit;
		}

		if ( empty( $_POST['srf_nonce'] ) || ! wp_verify_nonce( $_POST['srf_nonce'], 'srf_clear_user_storage_' . $user_id ) ) {
			wp_die( 'Bad nonce' );
		}

		// Delete files for all requests owned by this user
		$q = new WP_Query( array(
			'post_type'      => 'service_request',
			'posts_per_page' => -1,
			'meta_key'       => '_sr_user_id',
			'meta_value'     => $user_id,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		) );

		if ( ! empty( $q->posts ) ) {
			foreach ( $q->posts as $request_id ) {
				SR_Form_Handler::cleanup_request_files_public( (int) $request_id, $user_id );
			}
		}

		update_user_meta( $user_id, '_srf_storage_used_bytes', 0 );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SUBMENU_SLUG . '&cleared=1' ) );
		exit;
	}
}
