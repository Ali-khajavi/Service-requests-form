<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SRF_Storage_Manager' ) ) {
	class SRF_Storage_Manager {
		protected static $instance;
		protected $local_provider;
		protected $microsoft_provider;

		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		public function __construct() {
			$this->local_provider = new SRF_Local_Storage_Provider();
			$this->microsoft_provider = new SRF_Microsoft_Storage_Provider();
		}

		public function get_graph_client() {
			if ( ! class_exists( 'SR_Settings' ) ) {
				return null;
			}

			$tenant = SR_Settings::get_setting_value( SR_Settings::OPTION_MS_TENANT_ID, '' );
			$client = SR_Settings::get_setting_value( SR_Settings::OPTION_MS_CLIENT_ID, '' );
			$secret = SR_Settings::get_microsoft_client_secret();

			if ( '' === $tenant || '' === $client || '' === $secret ) {
				return null;
			}

			return new SRF_Microsoft_Graph_Client( $tenant, $client, $secret );
		}

		public function get_provider_key() {
			if ( class_exists( 'SR_Settings' ) && 'microsoft' === (string) get_option( SR_Settings::OPTION_STORAGE_PROVIDER, 'local' ) && $this->microsoft_provider->is_available() ) {
				return 'microsoft';
			}

			return 'local';
		}

		public function get_provider() {
			return 'microsoft' === $this->get_provider_key() ? $this->microsoft_provider : $this->local_provider;
		}

		public function get_provider_by_key( $key ) {
			return 'microsoft' === sanitize_key( (string) $key ) ? $this->microsoft_provider : $this->local_provider;
		}

		public function get_provider_for_descriptor( array $descriptor ) {
			return $this->get_provider_by_key( isset( $descriptor['provider'] ) ? $descriptor['provider'] : 'local' );
		}

		public function get_request_files( $request_id ) {
			return $this->get_provider()->get_request_files( $request_id );
		}

		public function get_batch_descriptor( $batch_id, $token ) {
			return SRF_Upload_Batch::get_batch_descriptor( $batch_id, $token );
		}

		public function get_batch_files( $batch_id, $token ) {
			$batch = $this->get_batch_descriptor( $batch_id, $token );
			if ( is_wp_error( $batch ) ) {
				return $batch;
			}

			return isset( $batch['files'] ) && is_array( $batch['files'] ) ? $batch['files'] : array();
		}

		public function create_upload_session_for_batch_file( array $batch, array $file ) {
			return $this->microsoft_provider->create_upload_session_for_batch_file( $batch, $file );
		}

		public function verify_batch_files( array $batch ) {
			return $this->microsoft_provider->verify_batch_files( $batch );
		}

		public function consume_batch_for_request( array $batch, $request_id ) {
			return $this->microsoft_provider->consume_batch_for_request( $batch, $request_id );
		}

		public function cleanup_batch( $batch_id, $status = 'cancelled' ) {
			return SRF_Upload_Batch::cleanup_batch( $batch_id, $status );
		}

		public function download_descriptor_to_tempfile( array $descriptor, $max_bytes = 0 ) {
			$provider = $this->get_provider_for_descriptor( $descriptor );
			return $provider->download_descriptor_to_tempfile( $descriptor, $max_bytes );
		}

		public function delete_descriptor( array $descriptor ) {
			$provider = $this->get_provider_for_descriptor( $descriptor );
			return $provider->delete_descriptor( $descriptor );
		}

		public function is_microsoft_enabled_for_form( $form_type ) {
			$form_type = sanitize_key( (string) $form_type );
			if ( ! class_exists( 'SR_Settings' ) ) {
				return false;
			}

			if ( 'project' === $form_type ) {
				return (bool) get_option( SR_Settings::OPTION_MS_ENABLE_PROJECT_UPLOADS, false );
			}

			return (bool) get_option( SR_Settings::OPTION_MS_ENABLE_SERVICE_UPLOADS, false );
		}

		public function get_microsoft_chunk_bytes() {
			return class_exists( 'SR_Settings' ) ? (int) get_option( SR_Settings::OPTION_MS_UPLOAD_CHUNK_BYTES, 10485760 ) : 10485760;
		}

		public function test_microsoft_connection() {
			$client = $this->get_graph_client();
			if ( ! ( $client instanceof SRF_Microsoft_Graph_Client ) ) {
				return new WP_Error( 'srf_ms_client', __( 'Microsoft Graph client is unavailable.', 'service-requests-form' ) );
			}

			$tenant = SR_Settings::get_setting_value( SR_Settings::OPTION_MS_TENANT_ID, '' );
			$site_id = SR_Settings::get_setting_value( SR_Settings::OPTION_MS_SITE_ID, '' );
			$drive_id = SR_Settings::get_setting_value( SR_Settings::OPTION_MS_DRIVE_ID, '' );
			$root_id = SR_Settings::get_setting_value( SR_Settings::OPTION_MS_ROOT_FOLDER_ID, '' );

			$token = $client->get_access_token();
			if ( is_wp_error( $token ) ) {
				return $token;
			}

			$report = array(
				'tenant' => $tenant,
				'site' => '',
				'drive' => '',
				'root' => '',
				'probe' => '',
			);

			if ( $site_id ) {
				$site_resp = $client->get( 'https://graph.microsoft.com/v1.0/sites/' . rawurlencode( $site_id ) );
				if ( is_wp_error( $site_resp ) ) {
					return $site_resp;
				}
				$site_data = json_decode( (string) wp_remote_retrieve_body( $site_resp ), true );
				$report['site'] = is_array( $site_data ) && ! empty( $site_data['displayName'] ) ? sanitize_text_field( (string) $site_data['displayName'] ) : '';
			}

			if ( $drive_id ) {
				$drive_resp = $client->get( 'https://graph.microsoft.com/v1.0/drives/' . rawurlencode( $drive_id ) );
				if ( is_wp_error( $drive_resp ) ) {
					return $drive_resp;
				}
				$drive_data = json_decode( (string) wp_remote_retrieve_body( $drive_resp ), true );
				$report['drive'] = is_array( $drive_data ) && ! empty( $drive_data['name'] ) ? sanitize_text_field( (string) $drive_data['name'] ) : '';
			}

			if ( $root_id && $drive_id ) {
				$root_resp = $client->get( 'https://graph.microsoft.com/v1.0/drives/' . rawurlencode( $drive_id ) . '/items/' . rawurlencode( $root_id ) );
				if ( is_wp_error( $root_resp ) ) {
					return $root_resp;
				}
				$root_data = json_decode( (string) wp_remote_retrieve_body( $root_resp ), true );
				$report['root'] = is_array( $root_data ) && ! empty( $root_data['name'] ) ? sanitize_text_field( (string) $root_data['name'] ) : '';
			}

			if ( $drive_id && $root_id ) {
				$probe_name = 'srf-connection-test-' . wp_generate_password( 8, false, false );
				$probe_resp = $client->post(
					'https://graph.microsoft.com/v1.0/drives/' . rawurlencode( $drive_id ) . '/items/' . rawurlencode( $root_id ) . '/children',
					array(
						'headers' => array( 'Content-Type' => 'application/json' ),
						'body' => wp_json_encode(
							array(
								'name' => $probe_name,
								'folder' => new stdClass(),
								'@microsoft.graph.conflictBehavior' => 'rename',
							)
						),
					)
				);

				if ( is_wp_error( $probe_resp ) ) {
					return $probe_resp;
				}

				$probe_data = json_decode( (string) wp_remote_retrieve_body( $probe_resp ), true );
				if ( ! is_array( $probe_data ) || empty( $probe_data['id'] ) ) {
					return new WP_Error( 'srf_ms_probe', __( 'Microsoft upload test folder could not be created.', 'service-requests-form' ) );
				}

				$report['probe'] = sanitize_text_field( $probe_name );
				$delete_resp = $client->delete( 'https://graph.microsoft.com/v1.0/drives/' . rawurlencode( $drive_id ) . '/items/' . rawurlencode( (string) $probe_data['id'] ) );
				if ( is_wp_error( $delete_resp ) ) {
					return $delete_resp;
				}
			}

			return $report;
		}
	}
}
