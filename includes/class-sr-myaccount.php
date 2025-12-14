<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SRF_MyAccount {

	const ENDPOINT = 'service-requests';

	public static function init() {
		// Only run if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_action( 'init', array( __CLASS__, 'add_endpoint' ) );

		// Add endpoint to WC query vars (helps in some setups)
		add_filter( 'woocommerce_get_query_vars', array( __CLASS__, 'register_query_var' ) );

		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'add_menu_item' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( __CLASS__, 'render_page' ) );
	}

	public static function add_endpoint() {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
	}

	public static function register_query_var( $vars ) {
		$vars[ self::ENDPOINT ] = self::ENDPOINT;
		return $vars;
	}

	public static function add_menu_item( $items ) {
		// Insert after Orders if possible
		$new = array();
		foreach ( $items as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'orders' === $key ) {
				$new[ self::ENDPOINT ] = __( 'Service Requests', 'service-requests-form' );
			}
		}
		if ( ! isset( $new[ self::ENDPOINT ] ) ) {
			$new[ self::ENDPOINT ] = __( 'Service Requests', 'service-requests-form' );
		}
		return $new;
	}

	public static function render_page() {
		if ( ! is_user_logged_in() ) {
			echo '<p>' . esc_html__( 'Please log in to view your requests.', 'service-requests-form' ) . '</p>';
			return;
		}

		$user_id = get_current_user_id();

		// Details view: ?view=123
		$view_id = isset( $_GET['view'] ) ? absint( $_GET['view'] ) : 0;
		if ( $view_id ) {
			self::render_single_request( $user_id, $view_id );
			return;
		}

		self::render_list( $user_id );
	}

	protected static function render_list( $user_id ) {
		$q = new WP_Query( array(
			'post_type'      => 'service_request',
			'posts_per_page' => 50,
			'meta_key'       => '_sr_user_id',
			'meta_value'     => $user_id,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		) );

		echo '<h3>' . esc_html__( 'Service Requests', 'service-requests-form' ) . '</h3>';

		if ( ! $q->have_posts() ) {
			echo '<p>' . esc_html__( 'You have no requests yet.', 'service-requests-form' ) . '</p>';
			return;
		}

		echo '<table class="shop_table shop_table_responsive my_account_orders">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Date', 'service-requests-form' ) . '</th>';
		echo '<th>' . esc_html__( 'Service', 'service-requests-form' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'service-requests-form' ) . '</th>';
		echo '<th>' . esc_html__( 'Request ID', 'service-requests-form' ) . '</th>';
		echo '<th>' . esc_html__( 'Action', 'service-requests-form' ) . '</th>';
		echo '</tr></thead><tbody>';

		while ( $q->have_posts() ) {
			$q->the_post();

			$rid     = get_the_ID();
			$service = (string) get_post_meta( $rid, '_sr_service_title', true );
			$status  = (string) get_post_meta( $rid, '_sr_status', true );
			if ( '' === $status ) {
				$status = 'new';
			}

			$view_url = add_query_arg( array( 'view' => $rid ) );

			echo '<tr>';
			echo '<td>' . esc_html( get_the_date() ) . '</td>';
			echo '<td>' . esc_html( $service ) . '</td>';
			echo '<td>' . esc_html( ucfirst( $status ) ) . '</td>';
			echo '<td>#' . esc_html( $rid ) . '</td>';
			echo '<td><a class="button" href="' . esc_url( $view_url ) . '">' . esc_html__( 'View', 'service-requests-form' ) . '</a></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		wp_reset_postdata();
	}

	protected static function render_single_request( $user_id, $request_id ) {
		$post = get_post( $request_id );
		if ( ! $post || 'service_request' !== $post->post_type ) {
			echo '<p>' . esc_html__( 'Request not found.', 'service-requests-form' ) . '</p>';
			return;
		}

		$owner = (int) get_post_meta( $request_id, '_sr_user_id', true );
		if ( $owner !== (int) $user_id ) {
			echo '<p>' . esc_html__( 'You do not have permission to view this request.', 'service-requests-form' ) . '</p>';
			return;
		}

		$service  = (string) get_post_meta( $request_id, '_sr_service_title', true );
		$status   = (string) get_post_meta( $request_id, '_sr_status', true );
		$shipping = (string) get_post_meta( $request_id, '_sr_shipping_address', true );
		$desc     = (string) get_post_meta( $request_id, '_sr_description', true );

		if ( '' === $status ) {
			$status = 'new';
		}

		$file_ids = get_post_meta( $request_id, '_sr_file_ids', true );
		if ( ! is_array( $file_ids ) ) {
			$file_ids = array();
		}

		$back_url = remove_query_arg( 'view' );

		echo '<p><a href="' . esc_url( $back_url ) . '">&larr; ' . esc_html__( 'Back to list', 'service-requests-form' ) . '</a></p>';

		echo '<h3>' . esc_html__( 'Request Details', 'service-requests-form' ) . ' #' . esc_html( $request_id ) . '</h3>';

		echo '<p><strong>' . esc_html__( 'Service:', 'service-requests-form' ) . '</strong> ' . esc_html( $service ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Status:', 'service-requests-form' ) . '</strong> ' . esc_html( ucfirst( $status ) ) . '</p>';

		if ( $shipping ) {
			echo '<p><strong>' . esc_html__( 'Shipping Address:', 'service-requests-form' ) . '</strong> ' . esc_html( $shipping ) . '</p>';
		}

		if ( $desc ) {
			echo '<p><strong>' . esc_html__( 'Description:', 'service-requests-form' ) . '</strong><br>' . nl2br( esc_html( $desc ) ) . '</p>';
		}

		echo '<h4>' . esc_html__( 'Files', 'service-requests-form' ) . '</h4>';
		if ( empty( $file_ids ) ) {
			echo '<p>' . esc_html__( 'No files uploaded.', 'service-requests-form' ) . '</p>';
			return;
		}

		echo '<ul>';
		foreach ( $file_ids as $aid ) {
			$aid = absint( $aid );
			if ( ! $aid ) {
				continue;
			}
			$url  = wp_get_attachment_url( $aid );
			$name = get_the_title( $aid );
			if ( $url ) {
				echo '<li><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $name ? $name : basename( $url ) ) . '</a></li>';
			}
		}
		echo '</ul>';
	}
}
