<?php
/**
 * Admin Status Meta Box for Service Requests
 *
 * Adds a status selector (New / In Progress / Done)
 * and triggers cleanup when a request is marked as Done.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SRF_Admin_Status {

	/**
	 * Initialize hooks
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post_service_request', array( __CLASS__, 'save_status' ), 10, 2 );
	}

	/**
	 * Register the meta box
	 */
	public static function add_meta_box() {
		add_meta_box(
			'srf-request-status',
			__( 'Request Status', 'service-requests-form' ),
			array( __CLASS__, 'render_meta_box' ),
			'service_request',
			'side',
			'high'
		);
	

		add_meta_box(
			'srf-request-files',
			__( 'Request Files', 'service-requests-form' ),
			array( __CLASS__, 'render_files_meta_box' ),
			'service_request',
			'side',
			'default'
		);
	}


	/**
	 * Render the meta box UI
	 *
	 * @param WP_Post $post
	 */
	public static function render_meta_box( $post ) {

		$status = get_post_meta( $post->ID, '_sr_status', true );
		if ( ! $status ) {
			$status = 'new';
		}

		wp_nonce_field( 'srf_save_status', 'srf_status_nonce' );
		?>
		<p>
			<label for="srf_request_status" style="font-weight:600;">
				<?php esc_html_e( 'Status', 'service-requests-form' ); ?>
			</label>
		</p>

		<select name="srf_request_status" id="srf_request_status" style="width:100%;">
			<option value="new" <?php selected( $status, 'new' ); ?>>
				<?php esc_html_e( 'New', 'service-requests-form' ); ?>
			</option>
			<option value="in_progress" <?php selected( $status, 'in_progress' ); ?>>
				<?php esc_html_e( 'In progress', 'service-requests-form' ); ?>
			</option>
			<option value="done" <?php selected( $status, 'done' ); ?>>
				<?php esc_html_e( 'Done', 'service-requests-form' ); ?>
			</option>
		</select>
		<?php
	}


	/**
	 * Render the uploaded files meta box (download/view links + sizes).
	 *
	 * @param WP_Post $post
	 */
	public static function render_files_meta_box( $post ) {

		$file_ids = get_post_meta( $post->ID, '_sr_file_ids', true );
		if ( ! is_array( $file_ids ) ) {
			$file_ids = array();
		}

		$file_ids = array_filter( array_map( 'absint', $file_ids ) );

		if ( empty( $file_ids ) ) {
			echo '<p>' . esc_html__( 'No files uploaded for this request.', 'service-requests-form' ) . '</p>';
			return;
		}

		$total = 0;

		echo '<ul style="margin:0; padding-left:18px;">';

		foreach ( $file_ids as $aid ) {
			$url  = wp_get_attachment_url( $aid );
			$path = get_attached_file( $aid );
			$name = get_the_title( $aid );
			if ( ! $name ) {
				$name = basename( (string) $path );
			}

			$size = 0;
			if ( $path && file_exists( $path ) ) {
				$fs = @filesize( $path );
				if ( false !== $fs ) {
					$size = (int) $fs;
				}
			}

			$total += $size;

			echo '<li style="margin: 0 0 6px;">';
			if ( $url ) {
				echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $name ) . '</a>';
			} else {
				echo esc_html( $name );
			}
			if ( $size > 0 ) {
				echo ' <span style="color:#666;">(' . esc_html( size_format( $size ) ) . ')</span>';
			}
			echo '</li>';
		}

		echo '</ul>';

		echo '<p style="margin-top:10px;"><strong>' . esc_html__( 'Total:', 'service-requests-form' ) . '</strong> ' . esc_html( size_format( $total ) ) . '</p>';
	}


	/**
	 * Save status and trigger cleanup when DONE
	 *
	 * @param int     $post_id
	 * @param WP_Post $post
	 */
	public static function save_status( $post_id, $post ) {

		// Safety checks
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( $post->post_type !== 'service_request' ) {
			return;
		}

		if ( ! isset( $_POST['srf_status_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['srf_status_nonce'], 'srf_save_status' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['srf_request_status'] ) ) {
			return;
		}

		// Sanitize and save
		$status = sanitize_text_field( wp_unslash( $_POST['srf_request_status'] ) );
		update_post_meta( $post_id, '_sr_status', $status );

		/**
		 * When status becomes DONE:
		 * - delete uploaded files
		 * - free user storage quota
		 */
		if ( 'done' === $status ) {
			$user_id = (int) get_post_meta( $post_id, '_sr_user_id', true );

			/**
			 * Hook handled by SR_Form_Handler
			 */
			do_action( 'srf_request_marked_done', $post_id, $user_id );
		}
	}
}
