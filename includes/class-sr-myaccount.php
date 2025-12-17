<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SRF_MyAccount {

	/**
	 * Endpoints:
	 * - /my-account/service-requests/         => list
	 * - /my-account/service-request/{id}/     => single
	 */
	const ENDPOINT_LIST = 'service-requests';
	const ENDPOINT_VIEW = 'service-request';

	public static function init() {
		srf_log( 'SRF_MyAccount::init() called' );


		/**
		 * Always register rewrite endpoints + public query vars.
		 * This must NOT depend on WooCommerce load order.
		 */
		add_action( 'init', array( __CLASS__, 'add_endpoints' ) );

		// Make WP recognize endpoint vars
		add_filter( 'query_vars', function( $vars ) {
			$vars[] = self::ENDPOINT_LIST;
			$vars[] = self::ENDPOINT_VIEW;
			return $vars;
		}, 0 );

		// Only add WooCommerce-specific UI/hooks if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_filter( 'woocommerce_get_query_vars', array( __CLASS__, 'register_query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'add_menu_item' ) );

		add_action( 'template_redirect', array( __CLASS__, 'handle_post_actions' ) );

		add_action( 'woocommerce_account_' . self::ENDPOINT_LIST . '_endpoint', array( __CLASS__, 'render_list_page' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT_VIEW . '_endpoint', array( __CLASS__, 'render_single_page' ) );

		// Secure download handler
		add_action( 'template_redirect', array( __CLASS__, 'maybe_handle_download' ), 9 );

		add_action( 'wp', function() {
			if ( ! function_exists('is_account_page') || ! is_account_page() ) return;

			global $wp;
			srf_log( 'WP matched_rule: ' . ( isset($wp->matched_rule) ? $wp->matched_rule : '(none)' ) );
			srf_log( 'WP matched_query: ' . ( isset($wp->matched_query) ? $wp->matched_query : '(none)' ) );
			srf_log( 'Query var service-requests: ' . var_export( get_query_var( self::ENDPOINT_LIST, null ), true ) );
			srf_log( 'Query var service-request: ' . var_export( get_query_var( self::ENDPOINT_VIEW, null ), true ) );
		}, 20 );

	}


	public static function add_endpoints() {
		srf_log( 'add_endpoints(): registering rewrite endpoints (' . self::ENDPOINT_LIST . ', ' . self::ENDPOINT_VIEW . ')' );

		add_rewrite_endpoint( self::ENDPOINT_LIST, EP_ROOT | EP_PAGES );
		add_rewrite_endpoint( self::ENDPOINT_VIEW, EP_ROOT | EP_PAGES );
	}

	public static function register_query_vars( $vars ) {
		$vars[ self::ENDPOINT_LIST ] = self::ENDPOINT_LIST;
		$vars[ self::ENDPOINT_VIEW ] = self::ENDPOINT_VIEW;
		return $vars;
	}

	public static function add_menu_item( $items ) {
		$new = array();

		foreach ( $items as $key => $label ) {
			$new[ $key ] = $label;

			if ( 'orders' === $key ) {
				$new[ self::ENDPOINT_LIST ] = __( 'Service Requests', 'service-requests-form' );
			}
		}

		if ( ! isset( $new[ self::ENDPOINT_LIST ] ) ) {
			$new[ self::ENDPOINT_LIST ] = __( 'Service Requests', 'service-requests-form' );
		}

		return $new;
	}

	/**
	 * List page
	 */
	public static function render_list_page() {
		if ( ! is_user_logged_in() ) {
			echo '<p>' . esc_html__( 'Please log in to view your requests.', 'service-requests-form' ) . '</p>';
			return;
		}

		
		$view_id = ! empty( $_GET['srf_view'] ) ? absint( $_GET['srf_view'] ) : 0;


$user_id  = get_current_user_id();
		$page     = isset( $_GET['srpage'] ) ? max( 1, absint( $_GET['srpage'] ) ) : 1;
		$per_page = (int) apply_filters( 'srf_myaccount_requests_per_page', 15 );

		$q = new WP_Query( array(
			'post_type'      => 'service_request',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'meta_key'       => '_sr_user_id',
			'meta_value'     => $user_id,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );

		$create_url = (string) get_option( 'srf_request_form_url', '' );

		self::load_template(
			'myaccount/service-requests.php',
			array(
				'query'      => $q,
				'page'       => $page,
				'per_page'   => $per_page,
				'create_url' => $create_url,
				'view_id'    => $view_id,
			)
		);

		wp_reset_postdata();
	}

	/**
	 * Single request page
	 */
	
	/**
	 * Render a single request by ID (used from list page query arg fallback).
	 */
	protected static function render_single_by_id( $request_id ) {

		if ( ! is_user_logged_in() ) {
			echo '<p>' . esc_html__( 'Please log in to view your request.', 'service-requests-form' ) . '</p>';
			return;
		}

		$user_id    = get_current_user_id();
		$request_id = absint( $request_id );

		if ( ! $request_id ) {
			echo '<p>' . esc_html__( 'Request not found.', 'service-requests-form' ) . '</p>';
			return;
		}

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

		$data = array(
			'request_id' => $request_id,
			'date'       => get_the_date( '', $request_id ),
			'service'    => (string) get_post_meta( $request_id, '_sr_service_title', true ),
			'status'     => (string) get_post_meta( $request_id, '_sr_status', true ),
			'name'       => (string) get_post_meta( $request_id, '_sr_name', true ),
			'company'    => (string) get_post_meta( $request_id, '_sr_company', true ),
			'email'      => (string) get_post_meta( $request_id, '_sr_email', true ),
			'phone'      => (string) get_post_meta( $request_id, '_sr_phone', true ),
			'shipping'   => (string) get_post_meta( $request_id, '_sr_shipping_address', true ),
			'message'    => (string) get_post_meta( $request_id, '_sr_description', true ),
			'file_ids'   => get_post_meta( $request_id, '_sr_file_ids', true ),
		);

		if ( empty( $data['status'] ) ) {
			$data['status'] = 'new';
		}
		if ( ! is_array( $data['file_ids'] ) ) {
			$data['file_ids'] = array();
		}

		self::load_template(
			'myaccount/service-request.php',
			array(
				'data' => $data,
			)
		);
	}

public static function render_single_page() {
		self::render_single_by_id( absint( get_query_var( self::ENDPOINT_VIEW ) ) );
	}


	/**
	 * Secure download handler:
	 * /my-account/service-request/{request_id}/?srf_download={attachment_id}&srf_request={request_id}&srf_nonce={nonce}
	 */
	public static function maybe_handle_download() {

		if ( ! is_user_logged_in() ) {
			return;
		}

		if ( empty( $_GET['srf_download'] ) || empty( $_GET['srf_nonce'] ) ) {
			return;
		}

		$attachment_id = absint( $_GET['srf_download'] );
		$nonce         = sanitize_text_field( wp_unslash( $_GET['srf_nonce'] ) );

		$request_id = 0;
		if ( ! empty( $_GET['srf_request'] ) ) {
			$request_id = absint( $_GET['srf_request'] );
		} else {
			$request_id = absint( get_query_var( self::ENDPOINT_VIEW ) );
		}

		if ( ! $attachment_id || ! $request_id ) {
			return;
		}

		if ( ! wp_verify_nonce( $nonce, 'srf_download_' . $request_id . '_' . $attachment_id ) ) {
			wp_die( esc_html__( 'Invalid download link.', 'service-requests-form' ), 403 );
		}

		$user_id = get_current_user_id();

		$post = get_post( $request_id );
		if ( ! $post || 'service_request' !== $post->post_type ) {
			wp_die( esc_html__( 'Request not found.', 'service-requests-form' ), 404 );
		}

		$owner = (int) get_post_meta( $request_id, '_sr_user_id', true );
		if ( $owner !== (int) $user_id ) {
			wp_die( esc_html__( 'Access denied.', 'service-requests-form' ), 403 );
		}

		$file_ids = get_post_meta( $request_id, '_sr_file_ids', true );
		if ( ! is_array( $file_ids ) ) {
			$file_ids = array();
		}
		$file_ids = array_map( 'absint', $file_ids );

		if ( ! in_array( $attachment_id, $file_ids, true ) ) {
			wp_die( esc_html__( 'File not found.', 'service-requests-form' ), 404 );
		}

		$path = get_attached_file( $attachment_id );
		if ( ! $path || ! file_exists( $path ) ) {
			wp_die( esc_html__( 'File not found.', 'service-requests-form' ), 404 );
		}

		nocache_headers();

		$filename = basename( $path );
		$mime     = get_post_mime_type( $attachment_id );
		if ( ! $mime ) {
			$mime = 'application/octet-stream';
		}

		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: attachment; filename="' . rawurlencode( $filename ) . '"' );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );

		while ( ob_get_level() ) {
			ob_end_clean();
		}

		readfile( $path );
		exit;
	}

	public static function format_status_label( $status ) {
		$status = (string) $status;
		if ( $status === '' ) {
			$status = 'new';
		}

		$map = array(
			'new'         => __( 'New', 'service-requests-form' ),
			'in_progress' => __( 'In progress', 'service-requests-form' ),
			'done'        => __( 'Done', 'service-requests-form' ),
			'failed'      => __( 'Failed', 'service-requests-form' ),
		);

		return isset( $map[ $status ] ) ? $map[ $status ] : ucfirst( str_replace( '_', ' ', $status ) );
	}

	public static function get_upload_summary( $request_id ) {
		$file_ids = get_post_meta( $request_id, '_sr_file_ids', true );
		if ( ! is_array( $file_ids ) ) {
			$file_ids = array();
		}

		$file_ids = array_filter( array_map( 'absint', $file_ids ) );

		$total_bytes = 0;
		foreach ( $file_ids as $aid ) {
			$bytes = (int) get_post_meta( $aid, '_srf_file_bytes', true );
			if ( $bytes <= 0 ) {
				$path = get_attached_file( $aid );
				if ( $path && file_exists( $path ) ) {
					$bytes = (int) filesize( $path );
				}
			}
			$total_bytes += max( 0, $bytes );
		}

		return array(
			'count' => count( $file_ids ),
			'bytes' => max( 0, (int) $total_bytes ),
		);
	}

	/**
	 * Theme override support:
	 * - child-theme/service-requests-form/myaccount/service-requests.php
	 * - child-theme/service-requests-form/myaccount/service-request.php
	 */
	protected static function load_template( $relative_path, $vars = array() ) {

		$relative_path = ltrim( (string) $relative_path, '/' );

		$theme_path  = trailingslashit( get_stylesheet_directory() ) . 'service-requests-form/' . $relative_path;
		$plugin_path = trailingslashit( SRF_PLUGIN_DIR ) . 'templates/' . $relative_path;

		$path = file_exists( $theme_path ) ? $theme_path : $plugin_path;

		if ( ! file_exists( $path ) ) {
			echo '<p>' . esc_html__( 'Template not found.', 'service-requests-form' ) . '</p>';
			return;
		}

		if ( is_array( $vars ) && ! empty( $vars ) ) {
			extract( $vars, EXTR_SKIP );
		}

		include $path;
	}


/**
 * Handle POST actions on My Account service-requests endpoint (edit request in modal).
 */
public static function handle_post_actions() {

	if ( ! is_user_logged_in() ) {
		return;
	}

	// Only handle on the Service Requests endpoint
	if ( function_exists( 'is_wc_endpoint_url' ) && ! is_wc_endpoint_url( self::ENDPOINT_LIST ) ) {
		return;
	}

	if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
		return;
	}

	$action = isset( $_POST['srf_action'] ) ? sanitize_text_field( wp_unslash( $_POST['srf_action'] ) ) : '';
	if ( $action !== 'update_request' ) {
		return;
	}

	if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'srf_edit_request' ) ) {
		wc_add_notice( __( 'Security check failed. Please try again.', 'service-requests-form' ), 'error' );
		wp_safe_redirect( self::url_list() );
		exit;
	}

	$old_id = isset( $_POST['request_id'] ) ? absint( $_POST['request_id'] ) : 0;
	if ( ! $old_id ) {
		wc_add_notice( __( 'Invalid request.', 'service-requests-form' ), 'error' );
		wp_safe_redirect( self::url_list() );
		exit;
	}

	$user_id = get_current_user_id();
	$owner   = (int) get_post_meta( $old_id, '_sr_user_id', true );

	if ( $owner !== $user_id ) {
		wc_add_notice( __( 'You are not allowed to edit this request.', 'service-requests-form' ), 'error' );
		wp_safe_redirect( self::url_list() );
		exit;
	}

	// New description/content
	$new_desc = isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '';

	// Copy basic post fields
	$old_post = get_post( $old_id );
	if ( ! $old_post ) {
		wc_add_notice( __( 'Request not found.', 'service-requests-form' ), 'error' );
		wp_safe_redirect( self::url_list() );
		exit;
	}

	$new_id = wp_insert_post( array(
		'post_type'    => $old_post->post_type,
		'post_status'  => $old_post->post_status,
		'post_title'   => $old_post->post_title,
		'post_content' => $new_desc,
	), true );

	if ( is_wp_error( $new_id ) ) {
		wc_add_notice( __( 'Could not save your changes.', 'service-requests-form' ), 'error' );
		wp_safe_redirect( self::url_list( array( 'srf_view' => $old_id ) ) );
		exit;
	}

	// Copy metas
	$meta_keys = array(
		'_sr_user_id',
		'_sr_name',
		'_sr_company',
		'_sr_email',
		'_sr_phone',
		'_sr_shipping_address',
		'_sr_service_title',
		'_sr_status',
	);

	foreach ( $meta_keys as $k ) {
		$val = get_post_meta( $old_id, $k, true );
		if ( $val !== '' && $val !== null ) {
			update_post_meta( $new_id, $k, $val );
		}
	}

	// Store new description meta
	update_post_meta( $new_id, '_sr_description', wp_strip_all_tags( $new_desc ) );

	// Remove old uploaded files to free storage
	$old_files = (array) get_post_meta( $old_id, '_sr_file_ids', true );
	if ( ! empty( $old_files ) ) {
		foreach ( $old_files as $fid ) {
			$fid = absint( $fid );
			if ( $fid ) {
				wp_delete_attachment( $fid, true );
			}
		}
	}
	delete_post_meta( $old_id, '_sr_file_ids' );

	// Handle new uploads (reuse plugin uploader if available)
	if ( class_exists( 'SR_Form_Handler' ) && method_exists( 'SR_Form_Handler', 'handle_request_uploads' ) ) {
		SR_Form_Handler::handle_request_uploads( $new_id );
	}

	// Delete old request completely
	wp_delete_post( $old_id, true );

	wc_add_notice( __( 'Request updated successfully.', 'service-requests-form' ), 'success' );
	wp_safe_redirect( self::url_list() );
	exit;
}


	public static function url_list( $args = array() ) {

		$endpoint = self::ENDPOINT_LIST; // e.g. 'service-requests'
		$myacc    = wc_get_page_permalink( 'myaccount' );

		// If pretty permalinks enabled, use normal endpoint URL.
		if ( get_option( 'permalink_structure' ) ) {
			$base = wc_get_account_endpoint_url( $endpoint );
		} else {
			// No rewrites: use query-arg endpoints (Woo supports ?endpoint=1).
			$base = add_query_arg( array( $endpoint => 1 ), $myacc );
		}

		return ! empty( $args ) ? add_query_arg( $args, $base ) : $base;
	}

	public static function url_view( $request_id ) {

		// Always build from url_list() so it works with or without rewrites.
		return self::url_list( array(
			'srf_view' => absint( $request_id ),
		) );
	}
}
