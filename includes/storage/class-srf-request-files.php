<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SRF_Request_Files' ) ) {
	class SRF_Request_Files {
		public static function get_files( $request_id ) {
			$request_id = absint( $request_id );
			if ( $request_id <= 0 ) {
				return array();
			}

			$files = array();

			$file_ids = get_post_meta( $request_id, '_sr_file_ids', true );
			if ( is_array( $file_ids ) ) {
				foreach ( array_filter( array_map( 'absint', $file_ids ) ) as $attachment_id ) {
					$path = get_attached_file( $attachment_id );
					$files[] = array(
						'provider' => 'local',
						'attachment_id' => $attachment_id,
						'download_id' => (string) $attachment_id,
						'name' => $path ? basename( $path ) : get_the_title( $attachment_id ),
						'path' => $path,
						'size' => $path && file_exists( $path ) ? (int) filesize( $path ) : 0,
						'mime' => (string) get_post_mime_type( $attachment_id ),
					);
				}
			}

			$remote_files = get_post_meta( $request_id, '_sr_remote_files', true );
			if ( is_array( $remote_files ) ) {
				foreach ( $remote_files as $file ) {
					if ( ! is_array( $file ) ) {
						continue;
					}
					$file['provider'] = isset( $file['provider'] ) ? sanitize_key( (string) $file['provider'] ) : 'microsoft';
					if ( empty( $file['download_id'] ) ) {
						$file['download_id'] = ! empty( $file['remote_file_id'] ) ? (string) $file['remote_file_id'] : ( ! empty( $file['id'] ) ? (string) $file['id'] : '' );
					}
					$file['name'] = isset( $file['name'] ) ? sanitize_file_name( (string) $file['name'] ) : '';
					$file['size'] = isset( $file['size'] ) ? max( 0, (int) $file['size'] ) : 0;
					$file['mime'] = isset( $file['mime'] ) ? sanitize_text_field( (string) $file['mime'] ) : 'application/octet-stream';
					$files[] = $file;
				}
			}

			return $files;
		}

		public static function find_file( $request_id, $download_id ) {
			$download_id = (string) $download_id;
			if ( '' === $download_id ) {
				return null;
			}

			foreach ( self::get_files( $request_id ) as $file ) {
				if ( ! is_array( $file ) ) {
					continue;
				}

				if ( isset( $file['download_id'] ) && (string) $file['download_id'] === $download_id ) {
					return $file;
				}
			}

			return null;
		}
	}
}
