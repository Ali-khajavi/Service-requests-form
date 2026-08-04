<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SRF_Upload_Batch' ) ) {
	class SRF_Upload_Batch {
		const CPT = 'srf_upload_batch';
		const REST_NAMESPACE = 'srf/v1';
		const DEFAULT_ACTIVE_BATCH_LIMIT = 3;
		const DEFAULT_BATCH_TTL_SECONDS = 21600;

		public static function init() {
			add_action( 'init', array( __CLASS__, 'register_cpt' ) );
			add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
			add_action( 'srf_cleanup_upload_batches', array( __CLASS__, 'cleanup_expired_batches' ) );
			if ( ! wp_next_scheduled( 'srf_cleanup_upload_batches' ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'srf_cleanup_upload_batches' );
			}
		}

		public static function register_cpt() {
			register_post_type(
				self::CPT,
				array(
					'public' => false,
					'show_ui' => false,
					'show_in_menu' => false,
					'supports' => array(),
					'rewrite' => false,
					'query_var' => false,
					'can_export' => false,
					'capabilities' => array( 'create_posts' => false ),
				)
			);
		}

		public static function create( array $data ) {
			$uuid = wp_generate_uuid4();
			$token = wp_generate_password( 64, false, false );
			$files = isset( $data['files'] ) && is_array( $data['files'] ) ? $data['files'] : array();
			$owner_user_id = isset( $data['owner_user_id'] ) ? (int) $data['owner_user_id'] : 0;
			$owner_hash = isset( $data['owner_hash'] ) ? (string) $data['owner_hash'] : '';
			$form_type = isset( $data['form_type'] ) ? sanitize_key( (string) $data['form_type'] ) : 'service';
			$expires_at = isset( $data['expires_at'] ) ? (int) $data['expires_at'] : ( time() + self::DEFAULT_BATCH_TTL_SECONDS );
			$post_id = wp_insert_post(
				array(
					'post_type' => self::CPT,
					'post_status' => 'publish',
					'post_title' => 'Batch ' . $uuid,
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				return $post_id;
			}

			update_post_meta( $post_id, '_srf_batch_uuid', $uuid );
			update_post_meta( $post_id, '_srf_finalize_token_hash', wp_hash_password( $token ) );
			update_post_meta( $post_id, '_srf_batch_status', 'created' );
			update_post_meta( $post_id, '_srf_batch_files', $files );
			update_post_meta( $post_id, '_srf_batch_remote_folder_id', '' );
			update_post_meta( $post_id, '_srf_batch_owner_user_id', $owner_user_id );
			update_post_meta( $post_id, '_srf_batch_owner_hash', $owner_hash );
			update_post_meta( $post_id, '_srf_batch_form_type', $form_type );
			update_post_meta( $post_id, '_srf_batch_expires_at', $expires_at );
			update_post_meta( $post_id, '_srf_batch_consumed', 0 );
			update_post_meta( $post_id, '_srf_batch_data', $data );

			return array(
				'id' => (int) $post_id,
				'uuid' => $uuid,
				'token' => $token,
			);
		}

		public static function register_rest_routes() {
			register_rest_route(
				self::REST_NAMESPACE,
				'/upload-batches',
				array(
					'methods' => WP_REST_Server::CREATABLE,
					'callback' => array( __CLASS__, 'rest_create_batch' ),
					'permission_callback' => array( __CLASS__, 'permission_create_batch' ),
				)
			);

			register_rest_route(
				self::REST_NAMESPACE,
				'/upload-batches/(?P<id>\d+)/files',
				array(
					'methods' => WP_REST_Server::CREATABLE,
					'callback' => array( __CLASS__, 'rest_create_upload_session' ),
					'permission_callback' => array( __CLASS__, 'permission_manage_batch' ),
				)
			);

			register_rest_route(
				self::REST_NAMESPACE,
				'/upload-batches/(?P<id>\d+)/finalize',
				array(
					'methods' => WP_REST_Server::CREATABLE,
					'callback' => array( __CLASS__, 'rest_finalize_batch' ),
					'permission_callback' => array( __CLASS__, 'permission_manage_batch' ),
				)
			);

			register_rest_route(
				self::REST_NAMESPACE,
				'/upload-batches/(?P<id>\d+)',
				array(
					'methods' => WP_REST_Server::DELETABLE,
					'callback' => array( __CLASS__, 'rest_delete_batch' ),
					'permission_callback' => array( __CLASS__, 'permission_manage_batch' ),
				)
			);
		}

		public static function rest_create_batch( WP_REST_Request $request ) {
			$batch = self::build_batch_from_request( $request );
			if ( is_wp_error( $batch ) ) {
				return $batch;
			}

			return rest_ensure_response(
				array(
					'batch_id' => (int) $batch['id'],
					'batch_token' => (string) $batch['token'],
					'batch_uuid' => (string) $batch['uuid'],
				)
			);
		}

		public static function rest_upload_file( WP_REST_Request $request ) {
			$batch = self::get_authorized_batch( $request );
			if ( is_wp_error( $batch ) ) {
				return $batch;
			}

			$spec = self::normalize_file_spec_from_request( $request );
			if ( is_wp_error( $spec ) ) {
				return $spec;
			}

			$provider = SRF_Storage_Manager::instance()->get_provider();
			if ( ! ( $provider instanceof SRF_Microsoft_Storage_Provider ) ) {
				return new WP_Error( 'srf_storage_mode', __( 'Direct uploads are only enabled for Microsoft storage.', 'service-requests-form' ), array( 'status' => 400 ) );
			}

			$result = $provider->create_upload_session_for_batch_file( $batch, $spec );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return rest_ensure_response( $result );
		}

		public static function rest_create_upload_session( WP_REST_Request $request ) {
			return self::rest_upload_file( $request );
		}

		public static function rest_finalize_batch( WP_REST_Request $request ) {
			$batch = self::get_authorized_batch( $request );
			if ( is_wp_error( $batch ) ) {
				return $batch;
			}

			$provider = SRF_Storage_Manager::instance()->get_provider();
			if ( ! ( $provider instanceof SRF_Microsoft_Storage_Provider ) ) {
				return new WP_Error( 'srf_storage_mode', __( 'Direct uploads are only enabled for Microsoft storage.', 'service-requests-form' ), array( 'status' => 400 ) );
			}

			$verified = $provider->verify_batch_files( $batch );
			if ( is_wp_error( $verified ) ) {
				self::mark_failed( (int) $batch['id'] );
				return $verified;
			}

			update_post_meta( (int) $batch['id'], '_srf_batch_status', 'verified' );
			update_post_meta( (int) $batch['id'], '_srf_batch_files', $verified );

			return rest_ensure_response(
				array(
					'batch_id' => (int) $batch['id'],
					'batch_token' => (string) $batch['token'],
					'files' => $verified,
				)
			);
		}

		public static function rest_delete_batch( WP_REST_Request $request ) {
			$batch = self::get_authorized_batch( $request );
			if ( is_wp_error( $batch ) ) {
				return $batch;
			}

			self::cleanup_batch( (int) $batch['id'], 'cancelled' );

			return rest_ensure_response( array( 'deleted' => true ) );
		}

		public static function permission_create_batch( WP_REST_Request $request ) {
			return self::verify_permission_context( $request, '', false );
		}

		public static function permission_manage_batch( WP_REST_Request $request ) {
			return self::verify_permission_context( $request, (string) $request->get_param( 'id' ), true );
		}

		protected static function verify_permission_context( WP_REST_Request $request, $batch_id = '', $require_batch = false ) {
			if ( ! self::verify_request_nonce( $request ) ) {
				return new WP_Error( 'srf_batch_nonce', __( 'Security check failed.', 'service-requests-form' ), array( 'status' => 403 ) );
			}

			$form_type = self::sanitize_form_type( $request->get_param( 'form_type' ) );
			if ( $require_batch ) {
				$batch = self::get_batch_by_id( absint( $batch_id ) );
				if ( is_wp_error( $batch ) ) {
					return $batch;
				}
				if ( ! self::batch_matches_requester( $batch ) ) {
					return new WP_Error( 'srf_batch_owner', __( 'Batch ownership mismatch.', 'service-requests-form' ), array( 'status' => 403 ) );
				}
				if ( self::batch_expired( $batch ) ) {
					return new WP_Error( 'srf_batch_expired', __( 'Upload batch expired.', 'service-requests-form' ), array( 'status' => 410 ) );
				}
				return true;
			}

			if ( ! self::form_enabled_for_request( $form_type ) ) {
				return new WP_Error( 'srf_ms_disabled', __( 'Microsoft uploads are not enabled for this form.', 'service-requests-form' ), array( 'status' => 403 ) );
			}

			if ( ! self::requester_allowed( $form_type ) ) {
				return new WP_Error( 'srf_batch_owner', __( 'You are not allowed to create upload batches.', 'service-requests-form' ), array( 'status' => 403 ) );
			}

			return true;
		}

		protected static function build_batch_from_request( WP_REST_Request $request ) {
			$form_type = self::sanitize_form_type( $request->get_param( 'form_type' ) );
			if ( ! self::form_enabled_for_request( $form_type ) ) {
				return new WP_Error( 'srf_ms_disabled', __( 'Microsoft uploads are not enabled for this form.', 'service-requests-form' ), array( 'status' => 403 ) );
			}

			$files = self::normalize_file_specs( $request->get_param( 'files' ) );
			if ( is_wp_error( $files ) ) {
				return $files;
			}

			$owner = self::get_request_owner();
			if ( is_wp_error( $owner ) ) {
				return $owner;
			}
			if ( ! self::rate_limit_allows( $owner ) ) {
				return new WP_Error( 'srf_rate_limit', __( 'Please wait before creating another upload batch.', 'service-requests-form' ), array( 'status' => 429 ) );
			}

			$limit = self::get_active_batch_limit( $owner );
			if ( self::count_active_batches( $owner ) >= $limit ) {
				return new WP_Error( 'srf_batch_limit', __( 'Too many active upload batches.', 'service-requests-form' ), array( 'status' => 429 ) );
			}

			$total_bytes = 0;
			foreach ( $files as $file ) {
				$total_bytes += isset( $file['size'] ) ? (int) $file['size'] : 0;
			}

			$allowed = self::validate_manifest( $form_type, $files, $total_bytes );
			if ( is_wp_error( $allowed ) ) {
				return $allowed;
			}

			$batch = self::create(
				array(
					'form_type' => $form_type,
					'owner_user_id' => isset( $owner['user_id'] ) ? (int) $owner['user_id'] : 0,
					'owner_hash' => isset( $owner['hash'] ) ? (string) $owner['hash'] : '',
					'files' => $files,
					'expires_at' => time() + self::DEFAULT_BATCH_TTL_SECONDS,
				)
			);
			if ( is_wp_error( $batch ) ) {
				return $batch;
			}

			if ( ! empty( $owner['is_guest'] ) ) {
				self::ensure_guest_session_cookie( $owner['hash'] );
			}

			return $batch;
		}

		protected static function normalize_file_specs( $files ) {
			if ( ! is_array( $files ) || empty( $files ) ) {
				return new WP_Error( 'srf_batch_files', __( 'At least one file is required.', 'service-requests-form' ), array( 'status' => 400 ) );
			}

			$normalized = array();
			foreach ( $files as $index => $file ) {
				if ( ! is_array( $file ) ) {
					return new WP_Error( 'srf_batch_files', __( 'Invalid file metadata.', 'service-requests-form' ), array( 'status' => 400 ) );
				}
				$name = sanitize_file_name( isset( $file['name'] ) ? (string) $file['name'] : '' );
				$size = isset( $file['size'] ) ? max( 0, (int) $file['size'] ) : 0;
				$type = isset( $file['type'] ) ? sanitize_text_field( (string) $file['type'] ) : '';
				if ( '' === $name || $size <= 0 ) {
					return new WP_Error( 'srf_batch_empty', __( 'Empty files are not allowed.', 'service-requests-form' ), array( 'status' => 400 ) );
				}
				$normalized[] = array(
					'index' => (int) $index,
					'name' => $name,
					'size' => $size,
					'type' => $type,
					'extension' => strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ),
				);
			}

			return $normalized;
		}

		protected static function validate_manifest( $form_type, array $files, $total_bytes ) {
			$allowed_extensions = self::get_allowed_extensions_for_form( $form_type );
			$max_files = self::get_max_files_for_form( $form_type );
			$max_file_size = self::get_max_file_size_for_form( $form_type );
			$max_total_size = self::get_max_total_size_for_form( $form_type );
			$processing_limit = self::get_project_processing_limit();

			if ( count( $files ) > $max_files ) {
				return new WP_Error( 'srf_batch_limit', __( 'Too many files selected.', 'service-requests-form' ), array( 'status' => 400 ) );
			}

			if ( $total_bytes > $max_total_size ) {
				return new WP_Error( 'srf_batch_limit', __( 'The total batch size exceeds the allowed limit.', 'service-requests-form' ), array( 'status' => 400 ) );
			}

			if ( 'project' === $form_type && $total_bytes > $processing_limit ) {
				return new WP_Error( 'srf_batch_limit', __( 'The project processing limit would be exceeded.', 'service-requests-form' ), array( 'status' => 400 ) );
			}

			$owner = self::get_request_owner();
			if ( is_wp_error( $owner ) ) {
				return $owner;
			}
			$quota = self::get_user_quota_bytes( isset( $owner['user_id'] ) ? (int) $owner['user_id'] : 0 );
			if ( $quota > 0 ) {
				$used = self::get_user_used_bytes( isset( $owner['user_id'] ) ? (int) $owner['user_id'] : 0 );
				if ( $used + $total_bytes > $quota ) {
					return new WP_Error( 'srf_quota', __( 'Your storage quota would be exceeded.', 'service-requests-form' ), array( 'status' => 400 ) );
				}
			}

			foreach ( $files as $file ) {
				if ( ! in_array( $file['extension'], $allowed_extensions, true ) ) {
					return new WP_Error( 'srf_ext', __( 'Unsupported file type.', 'service-requests-form' ), array( 'status' => 400 ) );
				}
				if ( $file['size'] > $max_file_size ) {
					return new WP_Error( 'srf_size', __( 'A file exceeds the allowed size.', 'service-requests-form' ), array( 'status' => 400 ) );
				}
			}

			return true;
		}

		protected static function sanitize_form_type( $value ) {
			$value = sanitize_key( (string) $value );
			return in_array( $value, array( 'service', 'project' ), true ) ? $value : 'service';
		}

		protected static function form_enabled_for_request( $form_type ) {
			if ( ! class_exists( 'SRF_Storage_Manager' ) ) {
				return false;
			}
			return SRF_Storage_Manager::instance()->is_microsoft_enabled_for_form( $form_type );
		}

		protected static function requester_allowed( $form_type ) {
			if ( is_user_logged_in() ) {
				return true;
			}
			return 'project' === $form_type && class_exists( 'SR_Settings' ) && SR_Settings::project_guests_allowed();
		}

		protected static function get_request_owner() {
			if ( is_user_logged_in() ) {
				return array(
					'user_id' => get_current_user_id(),
					'hash' => 'u:' . get_current_user_id(),
					'is_guest' => false,
				);
			}

			if ( ! class_exists( 'SR_Settings' ) || ! SR_Settings::project_guests_allowed() ) {
				return new WP_Error( 'srf_guest', __( 'Guest project uploads are not enabled.', 'service-requests-form' ), array( 'status' => 403 ) );
			}

			$hash = isset( $_COOKIE['srf_guest_session'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['srf_guest_session'] ) ) : '';
			if ( '' === $hash ) {
				$hash = wp_hash( wp_generate_uuid4() );
			}

			return array(
				'user_id' => 0,
				'hash' => $hash,
				'is_guest' => true,
			);
		}

		protected static function ensure_guest_session_cookie( $hash ) {
			if ( headers_sent() ) {
				return;
			}
			setcookie( 'srf_guest_session', (string) $hash, time() + DAY_IN_SECONDS * 30, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
		}

		protected static function get_request_batch_token( WP_REST_Request $request ) {
			$token = (string) $request->get_param( 'batch_token' );
			if ( '' === $token ) {
				$token = (string) $request->get_header( 'x-srf-batch-token' );
			}
			return sanitize_text_field( $token );
		}

		protected static function get_authorized_batch( WP_REST_Request $request ) {
			$batch = self::get_batch_by_id( absint( $request->get_param( 'id' ) ) );
			if ( is_wp_error( $batch ) ) {
				return $batch;
			}
			if ( ! self::batch_matches_requester( $batch ) ) {
				return new WP_Error( 'srf_batch_owner', __( 'Batch ownership mismatch.', 'service-requests-form' ), array( 'status' => 403 ) );
			}
			if ( self::batch_expired( $batch ) ) {
				return new WP_Error( 'srf_batch_expired', __( 'Upload batch expired.', 'service-requests-form' ), array( 'status' => 410 ) );
			}
			return $batch;
		}

		protected static function batch_matches_requester( array $batch ) {
			$owner_user_id = (int) get_post_meta( (int) $batch['id'], '_srf_batch_owner_user_id', true );
			$owner_hash = (string) get_post_meta( (int) $batch['id'], '_srf_batch_owner_hash', true );
			if ( is_user_logged_in() ) {
				return $owner_user_id > 0 && $owner_user_id === get_current_user_id();
			}
			return '' !== $owner_hash && isset( $_COOKIE['srf_guest_session'] ) && hash_equals( $owner_hash, sanitize_text_field( wp_unslash( $_COOKIE['srf_guest_session'] ) ) );
		}

		protected static function batch_expired( array $batch ) {
			$expires_at = (int) get_post_meta( (int) $batch['id'], '_srf_batch_expires_at', true );
			return $expires_at > 0 && time() > $expires_at;
		}

		protected static function get_allowed_extensions_for_form( $form_type ) {
			$raw = class_exists( 'SR_Settings' ) ? (string) get_option( SR_Settings::OPTION_ALLOWED_EXTENSIONS, 'stl,obj,3mf' ) : 'stl,obj,3mf';
			$extensions = array();
			foreach ( explode( ',', $raw ) as $ext ) {
				$ext = ltrim( sanitize_file_name( trim( strtolower( $ext ) ) ), '.' );
				if ( '' !== $ext ) {
					$extensions[] = $ext;
				}
			}
			if ( 'project' === $form_type ) {
				$extensions = array_intersect( $extensions, array( 'stl', 'obj', '3mf' ) );
			}
			return array_values( array_unique( $extensions ) );
		}

		protected static function rate_limit_allows( array $owner ) {
			$key = 'srf_ms_rate_' . md5( ( isset( $owner['hash'] ) ? (string) $owner['hash'] : '0' ) . '|' . ( isset( $owner['user_id'] ) ? (int) $owner['user_id'] : 0 ) );
			if ( get_transient( $key ) ) {
				return false;
			}
			set_transient( $key, 1, 10 );
			return true;
		}

		protected static function get_max_files_for_form( $form_type ) {
			return 'project' === $form_type ? 10 : 8;
		}

		protected static function get_max_file_size_for_form( $form_type ) {
			return 'project' === $form_type ? ( 1024 * 1024 * 1024 ) : ( 100 * 1024 * 1024 );
		}

		protected static function get_max_total_size_for_form( $form_type ) {
			return 'project' === $form_type ? self::get_project_processing_limit() : ( 1024 * 1024 * 1024 );
		}

		protected static function get_project_processing_limit() {
			return class_exists( 'SR_Settings' ) ? max( 104857600, (int) get_option( SR_Settings::OPTION_MS_PROJECT_PROCESSING_MAX_BYTES, 536870912 ) ) : 536870912;
		}

		protected static function get_user_quota_bytes( $user_id ) {
			if ( ! $user_id ) {
				return 0;
			}
			return (int) get_user_meta( $user_id, 'srf_quota_bytes', true );
		}

		protected static function get_user_used_bytes( $user_id ) {
			if ( ! $user_id ) {
				return 0;
			}
			return (int) get_user_meta( $user_id, '_srf_storage_used_bytes', true );
		}

		protected static function get_active_batch_limit( array $owner ) {
			return (int) apply_filters( 'srf_ms_active_batch_limit', self::DEFAULT_ACTIVE_BATCH_LIMIT, $owner );
		}

		protected static function count_active_batches( array $owner ) {
			$args = array(
				'post_type' => self::CPT,
				'post_status' => 'publish',
				'fields' => 'ids',
				'posts_per_page' => -1,
				'no_found_rows' => true,
				'meta_query' => array(
					'relation' => 'OR',
					array(
						'key' => '_srf_batch_owner_user_id',
						'value' => isset( $owner['user_id'] ) ? (int) $owner['user_id'] : 0,
						'compare' => '=',
					),
					array(
						'key' => '_srf_batch_owner_hash',
						'value' => isset( $owner['hash'] ) ? (string) $owner['hash'] : '',
						'compare' => '=',
					),
				),
			);
			return count( get_posts( $args ) );
		}

		protected static function mark_failed( $batch_id ) {
			update_post_meta( (int) $batch_id, '_srf_batch_status', 'failed' );
			update_post_meta( (int) $batch_id, '_srf_batch_cleanup_pending', 1 );
		}

		public static function cleanup_expired_batches() {
			$batches = get_posts(
				array(
					'post_type' => self::CPT,
					'post_status' => 'publish',
					'fields' => 'ids',
					'posts_per_page' => -1,
					'no_found_rows' => true,
				)
			);
			foreach ( $batches as $batch_id ) {
				$expires_at = (int) get_post_meta( $batch_id, '_srf_batch_expires_at', true );
				$status = (string) get_post_meta( $batch_id, '_srf_batch_status', true );
				$pending_cleanup = (bool) get_post_meta( $batch_id, '_srf_batch_cleanup_pending', true );
				if ( ( $expires_at > 0 && time() > $expires_at && 'consumed' !== $status ) || $pending_cleanup ) {
					self::cleanup_batch( (int) $batch_id, 'expired' );
				}
			}
		}

		public static function get_batch_files( $batch_id ) {
			$files = get_post_meta( (int) $batch_id, '_srf_batch_files', true );
			return is_array( $files ) ? $files : array();
		}

		public static function get_batch_file_count( $batch_id ) {
			return count( self::get_batch_files( $batch_id ) );
		}

		public static function get_batch_file_total_bytes( $batch_id ) {
			$total = 0;
			foreach ( self::get_batch_files( $batch_id ) as $file ) {
				$total += isset( $file['size'] ) ? max( 0, (int) $file['size'] ) : 0;
			}
			return max( 0, (int) $total );
		}

		public static function cleanup_batch( $batch_id, $status = 'cancelled' ) {
			$batch_id = (int) $batch_id;
			if ( $batch_id <= 0 ) {
				return false;
			}

			$files = self::get_batch_files( $batch_id );
			$remaining = array();
			$cleanup_pending = false;
			if ( class_exists( 'SRF_Storage_Manager' ) ) {
				foreach ( $files as $file ) {
					if ( is_array( $file ) ) {
						$result = SRF_Storage_Manager::instance()->delete_descriptor( $file );
						if ( is_wp_error( $result ) ) {
							$file['_srf_cleanup_pending'] = 1;
							$remaining[] = $file;
						}
					}
				}
				$pending_folder = (string) get_post_meta( $batch_id, '_srf_batch_remote_folder_id', true );
				if ( '' !== $pending_folder ) {
					$drive_id = (string) get_option( SR_Settings::OPTION_MS_DRIVE_ID, '' );
					$folder_result = SRF_Storage_Manager::instance()->get_provider_by_key( 'microsoft' )->delete_folder( $drive_id, $pending_folder );
					if ( is_wp_error( $folder_result ) ) {
						$cleanup_pending = true;
					}
				}
			}
			update_post_meta( $batch_id, '_srf_batch_status', sanitize_key( (string) $status ) );
			if ( ! empty( $remaining ) || $cleanup_pending ) {
				update_post_meta( $batch_id, '_srf_batch_files', $remaining );
				update_post_meta( $batch_id, '_srf_batch_cleanup_pending', 1 );
				return true;
			}
			update_post_meta( $batch_id, '_srf_batch_files', array() );
			delete_post_meta( $batch_id, '_srf_batch_remote_folder_id' );
			delete_post_meta( $batch_id, '_srf_batch_cleanup_pending' );
			wp_delete_post( $batch_id, true );
			return true;
		}

		public static function get_batch_descriptor( $batch_id, $token ) {
			$batch_id = (int) $batch_id;
			$token = (string) $token;
			if ( $batch_id <= 0 || '' === $token ) {
				return new WP_Error( 'srf_batch_invalid', __( 'Invalid upload batch.', 'service-requests-form' ) );
			}

			$post = get_post( $batch_id );
			if ( ! $post || self::CPT !== $post->post_type ) {
				return new WP_Error( 'srf_batch_missing', __( 'Upload batch not found.', 'service-requests-form' ) );
			}

			$hash = (string) get_post_meta( $batch_id, '_srf_finalize_token_hash', true );
			if ( '' === $hash || ! wp_check_password( $token, $hash ) ) {
				return new WP_Error( 'srf_batch_token', __( 'Upload batch token is invalid.', 'service-requests-form' ) );
			}
			$expires_at = (int) get_post_meta( $batch_id, '_srf_batch_expires_at', true );
			if ( $expires_at > 0 && time() > $expires_at ) {
				return new WP_Error( 'srf_batch_expired', __( 'Upload batch expired.', 'service-requests-form' ) );
			}

			return array(
				'id' => $batch_id,
				'token' => $token,
				'uuid' => (string) get_post_meta( $batch_id, '_srf_batch_uuid', true ),
				'files' => self::get_batch_files( $batch_id ),
				'data' => (array) get_post_meta( $batch_id, '_srf_batch_data', true ),
			);
		}

		public static function get_batch_by_id( $batch_id ) {
			$batch_id = (int) $batch_id;
			$post = get_post( $batch_id );
			if ( ! $post || self::CPT !== $post->post_type ) {
				return new WP_Error( 'srf_batch_missing', __( 'Upload batch not found.', 'service-requests-form' ) );
			}

			return array(
				'id' => $batch_id,
				'uuid' => (string) get_post_meta( $batch_id, '_srf_batch_uuid', true ),
				'files' => self::get_batch_files( $batch_id ),
				'form_type' => (string) get_post_meta( $batch_id, '_srf_batch_form_type', true ),
				'expires_at' => (int) get_post_meta( $batch_id, '_srf_batch_expires_at', true ),
			);
		}

		protected static function verify_request_nonce( WP_REST_Request $request ) {
			$nonce = (string) $request->get_header( 'x-srf-nonce' );
			if ( '' === $nonce ) {
				$nonce = (string) $request->get_param( '_srf_nonce' );
			}

			return (bool) wp_verify_nonce( $nonce, 'srf_upload_batch' );
		}

		protected static function get_batch_from_request( WP_REST_Request $request ) {
			$batch_id = absint( $request->get_param( 'id' ) );
			$token = (string) $request->get_param( 'batch_token' );
			return self::get_batch_descriptor( $batch_id, $token );
		}

		protected static function normalize_file_spec_from_request( WP_REST_Request $request ) {
			$name = sanitize_file_name( (string) $request->get_param( 'name' ) );
			$size = max( 0, (int) $request->get_param( 'size' ) );
			$type = sanitize_text_field( (string) $request->get_param( 'type' ) );
			if ( '' === $name || $size <= 0 ) {
				return new WP_Error( 'srf_batch_file', __( 'File metadata is invalid.', 'service-requests-form' ), array( 'status' => 400 ) );
			}
			return array(
				'name' => $name,
				'size' => $size,
				'type' => $type,
				'extension' => strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ),
			);
		}

		protected static function delete_batch_files( $batch_id ) {
			$batch_id = (int) $batch_id;
			$files = self::get_batch_files( $batch_id );
			if ( empty( $files ) ) {
				return;
			}

			foreach ( $files as $file ) {
				if ( is_array( $file ) ) {
					SRF_Storage_Manager::instance()->delete_descriptor( $file );
				}
			}

			update_post_meta( $batch_id, '_srf_batch_files', array() );
		}

		protected static function normalize_single_file( array $file ) {
			return array(
				'name' => isset( $file['name'] ) ? (string) $file['name'] : '',
				'tmp_name' => isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '',
				'size' => isset( $file['size'] ) ? (int) $file['size'] : 0,
				'type' => isset( $file['type'] ) ? (string) $file['type'] : '',
				'error' => isset( $file['error'] ) ? (int) $file['error'] : 0,
			);
		}
	}
}
