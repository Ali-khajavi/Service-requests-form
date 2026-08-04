<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SRF_Microsoft_Graph_Client' ) ) {
	class SRF_Microsoft_Graph_Client {
		protected $tenant_id;
		protected $client_id;
		protected $client_secret;
		protected $timeout = 30;

		public function __construct( $tenant_id, $client_id, $client_secret, $timeout = 30 ) {
			$this->tenant_id = sanitize_text_field( (string) $tenant_id );
			$this->client_id = sanitize_text_field( (string) $client_id );
			$this->client_secret = (string) $client_secret;
			$this->timeout = max( 5, (int) $timeout );
		}

		public function is_configured() {
			return '' !== $this->tenant_id && '' !== $this->client_id && '' !== $this->client_secret;
		}

		public function get_access_token() {
			if ( ! $this->is_configured() ) {
				return new WP_Error( 'srf_ms_config', __( 'Microsoft storage is not fully configured.', 'service-requests-form' ) );
			}

			$cache_key = 'srf_ms_token_' . md5( $this->tenant_id . '|' . $this->client_id );
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) && ! empty( $cached['token'] ) ) {
				return (string) $cached['token'];
			}

			$url = 'https://login.microsoftonline.com/' . rawurlencode( $this->tenant_id ) . '/oauth2/v2.0/token';
			$response = wp_remote_post(
				$url,
				array(
					'timeout' => $this->timeout,
					'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
					'body' => array(
						'client_id' => $this->client_id,
						'client_secret' => $this->client_secret,
						'scope' => 'https://graph.microsoft.com/.default',
						'grant_type' => 'client_credentials',
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			if ( 200 !== $code || ! is_array( $body ) || empty( $body['access_token'] ) ) {
				return new WP_Error( 'srf_ms_token_failed', $this->sanitize_error_message( $body, __( 'Could not acquire a Microsoft access token.', 'service-requests-form' ) ) );
			}

			$expires_in = isset( $body['expires_in'] ) ? max( 60, (int) $body['expires_in'] ) : 3600;
			set_transient( $cache_key, array( 'token' => (string) $body['access_token'] ), max( 60, $expires_in - 300 ) );
			return (string) $body['access_token'];
		}

		public function request( $method, $url, array $args = array(), $retry_auth = true ) {
			$token = $this->get_access_token();
			if ( is_wp_error( $token ) ) {
				return $token;
			}

			$args = wp_parse_args(
				$args,
				array(
					'timeout' => $this->timeout,
					'headers' => array(),
				)
			);

			$args['headers']['Authorization'] = 'Bearer ' . $token;
			$args['method'] = strtoupper( $method );
			$response = wp_remote_request( $url, $args );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( 401 === $code && $retry_auth ) {
				delete_transient( 'srf_ms_token_' . md5( $this->tenant_id . '|' . $this->client_id ) );
				return $this->request( $method, $url, $args, false );
			}

			if ( $code >= 400 ) {
				$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
				return new WP_Error(
					'srf_ms_http_' . $code,
					$this->sanitize_error_message( $body, sprintf( __( 'Microsoft Graph request failed with HTTP %d.', 'service-requests-form' ), $code ) ),
					array( 'status' => $code, 'retry_after' => (int) wp_remote_retrieve_header( $response, 'retry-after' ) )
				);
			}

			return $response;
		}

		public function get( $url, array $args = array() ) {
			return $this->request( 'GET', $url, $args );
		}

		public function post( $url, array $args = array() ) {
			return $this->request( 'POST', $url, $args );
		}

		public function delete( $url, array $args = array() ) {
			return $this->request( 'DELETE', $url, $args );
		}

		public function create_upload_session( $drive_id, $folder_id, $name, array $item = array() ) {
			$drive_id  = (string) $drive_id;
			$folder_id = (string) $folder_id;
			$name      = sanitize_file_name( (string) $name );

			if ( '' === $drive_id || '' === $folder_id || '' === $name ) {
				return new WP_Error( 'srf_ms_session', __( 'Missing Microsoft upload session parameters.', 'service-requests-form' ) );
			}

			$payload = array(
				'item' => array_merge(
					array(
						'@microsoft.graph.conflictBehavior' => 'fail',
						'name' => $name,
					),
					$item
				),
			);

			return $this->post(
				'https://graph.microsoft.com/v1.0/drives/' . rawurlencode( $drive_id ) . '/items/' . rawurlencode( $folder_id ) . ':/' . rawurlencode( $name ) . ':/createUploadSession',
				array(
					'headers' => array( 'Content-Type' => 'application/json' ),
					'body' => wp_json_encode( $payload ),
				)
			);
		}

		protected function sanitize_error_message( $body, $fallback ) {
			if ( is_array( $body ) ) {
				if ( ! empty( $body['error']['message'] ) ) {
					return sanitize_text_field( (string) $body['error']['message'] );
				}
				if ( ! empty( $body['message'] ) ) {
					return sanitize_text_field( (string) $body['message'] );
				}
			}
			return sanitize_text_field( (string) $fallback );
		}
	}
}
