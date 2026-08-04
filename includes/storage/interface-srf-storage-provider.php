<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! interface_exists( 'SRF_Storage_Provider' ) ) {
	interface SRF_Storage_Provider {
		public function get_key();
		public function is_available();
		public function test_connection();
		public function get_request_files( $request_id );
		public function download_descriptor_to_tempfile( array $descriptor, $max_bytes = 0 );
		public function delete_descriptor( array $descriptor );
	}
}
