<?php
/**
 * Uninstall cleanup for Service Requests Form
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete all posts of a CPT, and optionally delete attachments referenced by meta keys.
 *
 * @param string $post_type
 * @param array  $attachment_meta_keys Meta keys that store attachment IDs arrays (e.g. gallery IDs).
 */
function srf_delete_cpt_posts_and_attachments( $post_type, $attachment_meta_keys = array() ) {
	$ids = get_posts( array(
		'post_type'      => $post_type,
		'post_status'    => 'any',
		'fields'         => 'ids',
		'posts_per_page' => -1,
		'no_found_rows'  => true,
	) );

	if ( empty( $ids ) ) {
		return;
	}

	foreach ( $ids as $post_id ) {

		// Delete attachments referenced in specific meta keys (arrays of IDs)
		if ( ! empty( $attachment_meta_keys ) ) {
			foreach ( $attachment_meta_keys as $meta_key ) {
				$maybe_ids = get_post_meta( $post_id, $meta_key, true );
				if ( is_array( $maybe_ids ) ) {
					foreach ( $maybe_ids as $att_id ) {
						$att_id = absint( $att_id );
						if ( $att_id ) {
							// true = force delete (bypass trash)
							wp_delete_attachment( $att_id, true );
						}
					}
				}
			}
		}

		// Delete attachments uploaded for service_request (stored in _sr_file_ids)
		if ( $post_type === 'service_request' ) {
			$file_ids = get_post_meta( $post_id, '_sr_file_ids', true );
			if ( is_array( $file_ids ) ) {
				foreach ( $file_ids as $att_id ) {
					$att_id = absint( $att_id );
					if ( $att_id ) {
						wp_delete_attachment( $att_id, true );
					}
				}
			}
		}

		// Finally delete the post
		wp_delete_post( $post_id, true ); // true = force delete
	}
}

/**
 * 1) Delete all service requests + their uploaded files
 */
srf_delete_cpt_posts_and_attachments( 'service_request' );

/**
 * 2) Delete all services
 * IMPORTANT:
 * - This also deletes gallery images stored in _sr_service_gallery_ids
 * - If you reuse those images elsewhere on your site, comment this out or remove the meta key below.
 */
srf_delete_cpt_posts_and_attachments( 'sr_service', array( '_sr_service_gallery_ids' ) );

/**
 * 3) Delete plugin options (settings)
 * Add more option names here if you create them later.
 */
$option_keys = array(
	'srf_admin_email',
	'srf_allowed_file_types',
	'srf_max_file_size_mb',
	'srf_terms_url',
);

foreach ( $option_keys as $key ) {
	delete_option( $key );
}

// If you ever store site-wide options in multisite, you can also delete_site_option() similarly.
