<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SRF_Microsoft_Storage_Provider' ) ) {
	class SRF_Microsoft_Storage_Provider implements SRF_Storage_Provider {
		public function get_key() { return 'microsoft'; }

		public function is_available() {
			return $this->get_client() instanceof SRF_Microsoft_Graph_Client && $this->get_client()->is_configured();
		}

		public function test_connection() {
			$manager = SRF_Storage_Manager::instance();
			return $manager->test_microsoft_connection();
		}

		public function get_request_files( $request_id ) {
			return SRF_Request_Files::get_files( $request_id );
		}

		public function download_descriptor_to_tempfile( array $descriptor, $max_bytes = 0 ) {
			$client = $this->get_client();
			if ( ! ( $client instanceof SRF_Microsoft_Graph_Client ) ) {
				return new WP_Error( 'srf_ms_client', __( 'Microsoft Graph client is unavailable.', 'service-requests-form' ) );
			}

			$drive_id = isset( $descriptor['drive_id'] ) ? (string) $descriptor['drive_id'] : (string) SR_Settings::get_setting_value( SR_Settings::OPTION_MS_DRIVE_ID, '' );
			$file_id  = isset( $descriptor['remote_file_id'] ) ? (string) $descriptor['remote_file_id'] : ( isset( $descriptor['id'] ) ? (string) $descriptor['id'] : '' );
			if ( '' === $drive_id || '' === $file_id ) {
				return new WP_Error( 'srf_ms_descriptor', __( 'Remote file metadata is incomplete.', 'service-requests-form' ) );
			}

			$tmp = wp_tempnam( isset( $descriptor['name'] ) ? (string) $descriptor['name'] : 'srf-download' );
			if ( ! $tmp ) {
				return new WP_Error( 'srf_ms_tmp', __( 'Could not create a temporary download file.', 'service-requests-form' ) );
			}

			$token = $client->get_access_token();
			if ( is_wp_error( $token ) ) {
				@unlink( $tmp );
				return $token;
			}

			$url = 'https://graph.microsoft.com/v1.0/drives/' . rawurlencode( $drive_id ) . '/items/' . rawurlencode( $file_id ) . '/content';
			$response = wp_safe_remote_get(
				$url,
				array(
					'timeout' => 60,
					'headers' => array( 'Authorization' => 'Bearer ' . $token ),
					'stream' => true,
					'filename' => $tmp,
				)
			);

			if ( is_wp_error( $response ) ) {
				@unlink( $tmp );
				return $response;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( 200 !== $code && 201 !== $code ) {
				@unlink( $tmp );
				return new WP_Error( 'srf_ms_download', __( 'Microsoft file download failed.', 'service-requests-form' ) );
			}

			if ( $max_bytes > 0 && file_exists( $tmp ) && filesize( $tmp ) > $max_bytes ) {
				@unlink( $tmp );
				return new WP_Error( 'srf_ms_too_large', __( 'The downloaded file exceeds the allowed size.', 'service-requests-form' ) );
			}

			return $tmp;
		}

		public function delete_descriptor( array $descriptor ) {
			$client = $this->get_client();
			if ( ! ( $client instanceof SRF_Microsoft_Graph_Client ) ) {
				return new WP_Error( 'srf_ms_client', __( 'Microsoft Graph client is unavailable.', 'service-requests-form' ) );
			}

			$drive_id = isset( $descriptor['drive_id'] ) ? (string) $descriptor['drive_id'] : (string) SR_Settings::get_setting_value( SR_Settings::OPTION_MS_DRIVE_ID, '' );
			$file_id  = isset( $descriptor['remote_file_id'] ) ? (string) $descriptor['remote_file_id'] : ( isset( $descriptor['id'] ) ? (string) $descriptor['id'] : '' );
			if ( '' === $drive_id || '' === $file_id ) {
				return true;
			}

			$result = $client->delete( 'https://graph.microsoft.com/v1.0/drives/' . rawurlencode( $drive_id ) . '/items/' . rawurlencode( $file_id ) );
			if ( is_wp_error( $result ) && 404 === (int) $result->get_error_data( 'status' ) ) {
				return true;
			}
			return $result;
		}

		public function delete_folder( $drive_id, $folder_id ) {
			$client = $this->get_client();
			if ( ! ( $client instanceof SRF_Microsoft_Graph_Client ) ) {
				return new WP_Error( 'srf_ms_client', __( 'Microsoft Graph client is unavailable.', 'service-requests-form' ) );
			}
			$result = $client->delete( 'https://graph.microsoft.com/v1.0/drives/' . rawurlencode( (string) $drive_id ) . '/items/' . rawurlencode( (string) $folder_id ) );
			if ( is_wp_error( $result ) && 404 === (int) $result->get_error_data( 'status' ) ) {
				return true;
			}
			return $result;
		}

		public function create_upload_session_for_batch_file( array $batch, array $file ) {
			$client = $this->get_client();
			if ( ! ( $client instanceof SRF_Microsoft_Graph_Client ) ) {
				return new WP_Error( 'srf_ms_client', __( 'Microsoft Graph client is unavailable.', 'service-requests-form' ) );
			}

			$index = isset( $file['index'] ) ? (int) $file['index'] : -1;
			$manifest = isset( $batch['files'] ) && is_array( $batch['files'] ) ? $batch['files'] : array();
			if ( $index < 0 || ! isset( $manifest[ $index ] ) ) {
				return new WP_Error( 'srf_ms_manifest', __( 'Upload file metadata does not match the batch manifest.', 'service-requests-form' ) );
			}
			$expected = $manifest[ $index ];
			if ( sanitize_file_name( (string) $expected['name'] ) !== sanitize_file_name( (string) $file['name'] ) || (int) $expected['size'] !== (int) $file['size'] ) {
				return new WP_Error( 'srf_ms_manifest', __( 'Upload file metadata does not match the batch manifest.', 'service-requests-form' ) );
			}

			$root_folder_id = (string) SR_Settings::get_setting_value( SR_Settings::OPTION_MS_ROOT_FOLDER_ID, '' );
			$drive_id       = (string) SR_Settings::get_setting_value( SR_Settings::OPTION_MS_DRIVE_ID, '' );
			if ( '' === $root_folder_id || '' === $drive_id ) {
				return new WP_Error( 'srf_ms_config', __( 'Microsoft storage target is not configured.', 'service-requests-form' ) );
			}

			$folder_id = $this->ensure_batch_folder( $client, $drive_id, $root_folder_id, $batch );
			if ( is_wp_error( $folder_id ) ) {
				return $folder_id;
			}

			$extension = isset( $expected['extension'] ) ? (string) $expected['extension'] : pathinfo( (string) $file['name'], PATHINFO_EXTENSION );
			$target_name = $this->sanitize_upload_filename( (string) $batch['uuid'] . '-' . $index . ( $extension ? '.' . $extension : '' ) );
			$response = $client->create_upload_session(
				$drive_id,
				$folder_id,
				$target_name,
				array(
					'file' => array(
						'@microsoft.graph.conflictBehavior' => 'fail',
						'name' => $target_name,
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $data ) || empty( $data['uploadUrl'] ) ) {
				return new WP_Error( 'srf_ms_upload', __( 'Microsoft upload session could not be created.', 'service-requests-form' ) );
			}

			$descriptor = array(
				'provider' => 'microsoft',
				'download_id' => $target_name,
				'remote_file_id' => '',
				'drive_id' => $drive_id,
				'folder_id' => (string) $folder_id,
				'name' => $target_name,
				'size' => isset( $file['size'] ) ? max( 0, (int) $file['size'] ) : 0,
				'mime' => ! empty( $file['type'] ) ? sanitize_text_field( (string) $file['type'] ) : 'application/octet-stream',
				'uploadUrl' => (string) $data['uploadUrl'],
				'uploadExpiresAt' => isset( $data['expirationDateTime'] ) ? sanitize_text_field( (string) $data['expirationDateTime'] ) : '',
				'uploadMethod' => 'PUT',
				'chunkSize' => SR_Settings::sanitize_ms_chunk_bytes( SRF_Storage_Manager::instance()->get_microsoft_chunk_bytes() ),
			);

			$batch_files = SRF_Upload_Batch::get_batch_files( (int) $batch['id'] );
			$batch_files[ $index ] = array_merge( $expected, $descriptor );
			update_post_meta( (int) $batch['id'], '_srf_batch_files', $batch_files );
			update_post_meta( (int) $batch['id'], '_srf_batch_status', 'uploading' );

			return $descriptor;
		}

		public function verify_batch_files( array $batch ) {
			$client = $this->get_client();
			if ( ! ( $client instanceof SRF_Microsoft_Graph_Client ) ) {
				return new WP_Error( 'srf_ms_client', __( 'Microsoft Graph client is unavailable.', 'service-requests-form' ) );
			}

			$files = isset( $batch['files'] ) && is_array( $batch['files'] ) ? $batch['files'] : array();
			$verified = array();
			foreach ( $files as $file ) {
				if ( ! is_array( $file ) || empty( $file['name'] ) ) {
					return new WP_Error( 'srf_ms_manifest', __( 'Upload batch manifest is incomplete.', 'service-requests-form' ) );
				}

				$drive_id  = isset( $file['drive_id'] ) ? (string) $file['drive_id'] : '';
				$folder_id = isset( $file['folder_id'] ) ? (string) $file['folder_id'] : '';
				$name      = (string) $file['name'];
				if ( '' === $drive_id || '' === $folder_id || '' === $name ) {
					return new WP_Error( 'srf_ms_manifest', __( 'Upload batch target is invalid.', 'service-requests-form' ) );
				}

				$response = $client->get( 'https://graph.microsoft.com/v1.0/drives/' . rawurlencode( $drive_id ) . '/items/' . rawurlencode( $folder_id ) . ':/' . rawurlencode( $name ) );
				if ( is_wp_error( $response ) ) {
					return $response;
				}
				$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
				if ( ! is_array( $data ) || empty( $data['id'] ) || empty( $data['size'] ) || empty( $data['file'] ) ) {
					return new WP_Error( 'srf_ms_verify', __( 'Uploaded Microsoft file could not be verified.', 'service-requests-form' ) );
				}
				$verified[] = array_merge(
					$file,
					array(
						'remote_file_id' => (string) $data['id'],
						'item_id' => (string) $data['id'],
						'eTag' => isset( $data['eTag'] ) ? (string) $data['eTag'] : '',
						'size' => max( 0, (int) $data['size'] ),
						'name' => isset( $data['name'] ) ? sanitize_file_name( (string) $data['name'] ) : $name,
					)
				);
			}

			return $verified;
		}

		public function consume_batch_for_request( array $batch, $request_id ) {
			$request_id = (int) $request_id;
			if ( $request_id <= 0 ) {
				return new WP_Error( 'srf_request', __( 'Invalid request.', 'service-requests-form' ) );
			}

			$verified = $this->verify_batch_files( $batch );
			if ( is_wp_error( $verified ) ) {
				return $verified;
			}

			$drive_id = (string) SR_Settings::get_setting_value( SR_Settings::OPTION_MS_DRIVE_ID, '' );
			$root_id  = (string) SR_Settings::get_setting_value( SR_Settings::OPTION_MS_ROOT_FOLDER_ID, '' );
			if ( '' === $drive_id || '' === $root_id ) {
				return new WP_Error( 'srf_ms_config', __( 'Microsoft storage target is not configured.', 'service-requests-form' ) );
			}

			$client = $this->get_client();
			$final_folder_name = 'requests/' . $request_id;
			$final_folder_id = $this->ensure_named_folder( $client, $drive_id, $root_id, 'requests' );
			if ( is_wp_error( $final_folder_id ) ) {
				return $final_folder_id;
			}
			$final_folder_id = $this->ensure_named_folder( $client, $drive_id, $final_folder_id, (string) $request_id );
			if ( is_wp_error( $final_folder_id ) ) {
				return $final_folder_id;
			}

			$final_files = array();
			foreach ( $verified as $file ) {
				$item_id = (string) $file['item_id'];
				$name    = (string) $file['name'];
				$move = $client->request(
					'PATCH',
					'https://graph.microsoft.com/v1.0/drives/' . rawurlencode( $drive_id ) . '/items/' . rawurlencode( $item_id ),
					array(
						'headers' => array( 'Content-Type' => 'application/json' ),
						'body' => wp_json_encode(
							array(
								'parentReference' => array( 'id' => $final_folder_id ),
								'name' => $name,
							),
						),
					)
				);
				if ( is_wp_error( $move ) ) {
					return $move;
				}
				$move_data = json_decode( (string) wp_remote_retrieve_body( $move ), true );
				$final_files[] = array_merge(
					$file,
					array(
						'folder_id' => $final_folder_id,
						'remote_file_id' => isset( $move_data['id'] ) ? (string) $move_data['id'] : $item_id,
						'eTag' => isset( $move_data['eTag'] ) ? (string) $move_data['eTag'] : ( isset( $file['eTag'] ) ? (string) $file['eTag'] : '' ),
					)
				);
			}

			$pending_folder_id = (string) get_post_meta( (int) $batch['id'], '_srf_batch_remote_folder_id', true );
			if ( '' !== $pending_folder_id ) {
				$this->delete_folder( $drive_id, $pending_folder_id );
			}
			update_post_meta( (int) $batch['id'], '_srf_batch_status', 'consumed' );
			update_post_meta( (int) $batch['id'], '_srf_batch_consumed', 1 );
			delete_post_meta( (int) $batch['id'], '_srf_batch_remote_folder_id' );
			wp_delete_post( (int) $batch['id'], true );

			return $final_files;
		}

		protected function ensure_batch_folder( SRF_Microsoft_Graph_Client $client, $drive_id, $root_folder_id, array $batch ) {
			$batch_id = (int) ( $batch['id'] ?? 0 );
			if ( $batch_id <= 0 ) {
				return new WP_Error( 'srf_ms_batch', __( 'Upload batch is invalid.', 'service-requests-form' ) );
			}

			$existing = (string) get_post_meta( $batch_id, '_srf_batch_remote_folder_id', true );
			if ( '' !== $existing ) {
				return $existing;
			}

			$batch_uuid = (string) get_post_meta( $batch_id, '_srf_batch_uuid', true );
			$folder_name = $this->sanitize_upload_filename( 'srf-' . ( $batch_uuid ? $batch_uuid : wp_generate_uuid4() ) );
			$response = $client->post(
				'https://graph.microsoft.com/v1.0/drives/' . rawurlencode( $drive_id ) . '/items/' . rawurlencode( $root_folder_id ) . '/children',
				array(
					'headers' => array( 'Content-Type' => 'application/json' ),
					'body' => wp_json_encode(
						array(
							'name' => $folder_name,
							'folder' => new stdClass(),
							'@microsoft.graph.conflictBehavior' => 'rename',
						)
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $data ) || empty( $data['id'] ) ) {
				return new WP_Error( 'srf_ms_folder', __( 'Could not create the Microsoft upload folder.', 'service-requests-form' ) );
			}

			$folder_id = (string) $data['id'];
			update_post_meta( $batch_id, '_srf_batch_remote_folder_id', $folder_id );
			return $folder_id;
		}

		protected function ensure_named_folder( SRF_Microsoft_Graph_Client $client, $drive_id, $parent_folder_id, $folder_name ) {
			$folder_name = $this->sanitize_upload_filename( $folder_name );
			if ( '' === $folder_name ) {
				return new WP_Error( 'srf_ms_folder', __( 'Could not create the Microsoft folder.', 'service-requests-form' ) );
			}

			$resp = $client->post(
				'https://graph.microsoft.com/v1.0/drives/' . rawurlencode( $drive_id ) . '/items/' . rawurlencode( $parent_folder_id ) . '/children',
				array(
					'headers' => array( 'Content-Type' => 'application/json' ),
					'body' => wp_json_encode(
						array(
							'name' => $folder_name,
							'folder' => new stdClass(),
							'@microsoft.graph.conflictBehavior' => 'fail',
						)
					),
				)
			);
			if ( is_wp_error( $resp ) ) {
				return $resp;
			}
			$data = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
			if ( ! is_array( $data ) || empty( $data['id'] ) ) {
				return new WP_Error( 'srf_ms_folder', __( 'Could not create the Microsoft folder.', 'service-requests-form' ) );
			}
			return (string) $data['id'];
		}

		protected function sanitize_upload_filename( $name ) {
			$name = sanitize_file_name( (string) $name );
			return '' !== $name ? $name : 'upload.bin';
		}

		protected function get_client() {
			$manager = SRF_Storage_Manager::instance();
			return $manager->get_graph_client();
		}
	}
}
