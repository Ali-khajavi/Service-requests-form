<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SRF_Local_Storage_Provider' ) ) {
	class SRF_Local_Storage_Provider implements SRF_Storage_Provider {
		public function get_key() { return 'local'; }
		public function is_available() { return true; }
		public function test_connection() { return true; }
		public function get_request_files( $request_id ) { return SRF_Request_Files::get_files( $request_id ); }
		public function download_descriptor_to_tempfile( array $descriptor, $max_bytes = 0 ) { return new WP_Error( 'srf_local_only', __( 'Local storage does not use remote downloads.', 'service-requests-form' ) ); }
		public function delete_descriptor( array $descriptor ) { return true; }
	}
}
