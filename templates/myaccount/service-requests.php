<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** @var WP_Query $query */
/** @var int $page */
/** @var int $per_page */
/** @var string $create_url */
/** @var int $view_id */

echo '<div class="srf-myaccount srf-myaccount--list">';

echo '<div class="srf-myaccount__header">';
echo '<h3 class="srf-myaccount__title">' . esc_html__( 'Service Requests', 'service-requests-form' ) . '</h3>';

if ( ! empty( $create_url ) ) {
	echo '<a class="button srf-myaccount__create" href="' . esc_url( $create_url ) . '">' . esc_html__( 'Create new request', 'service-requests-form' ) . '</a>';
}
echo '</div>';

/**
 * Modal (opened when ?srf_view=ID is present)
 */
if ( ! empty( $view_id ) ) {

	$view_post = get_post( $view_id );
	$owner_id  = (int) get_post_meta( $view_id, '_sr_user_id', true );

	if ( $view_post && $owner_id === get_current_user_id() ) {

		$desc = (string) $view_post->post_content;

		// Uploaded files for this request
		$file_ids = get_post_meta( $view_id, '_sr_file_ids', true );
		if ( ! is_array( $file_ids ) ) {
			$file_ids = array();
		}
		$file_ids = array_filter( array_map( 'absint', $file_ids ) );

		echo '<div class="srf-modal is-open" id="srf-request-modal" role="dialog" aria-modal="true">';
		echo '<div class="srf-modal__overlay" data-srf-close></div>';
		echo '<div class="srf-modal__panel">';
		echo '<button type="button" class="srf-modal__close" data-srf-close aria-label="' . esc_attr__( 'Close', 'service-requests-form' ) . '">&times;</button>';

		echo '<h3 class="srf-modal__title">' . esc_html__( 'Edit Request', 'service-requests-form' ) . ' #' . esc_html( $view_id ) . '</h3>';

		// ✅ Show existing uploads with secure download links
		echo '<div class="srf-modal__uploads">';
		echo '<h4 style="margin:10px 0 6px;">' . esc_html__( 'Your uploaded files', 'service-requests-form' ) . '</h4>';

		if ( empty( $file_ids ) ) {

			echo '<p style="margin:0 0 12px;">' . esc_html__( 'No files uploaded.', 'service-requests-form' ) . '</p>';

		} else {

			echo '<ul style="margin:0 0 12px; padding-left:18px;">';

			foreach ( $file_ids as $aid ) {
				$aid = (int) $aid;
				if ( ! $aid ) {
					continue;
				}

				$filename = get_the_title( $aid );
				if ( ! $filename ) {
					$url = wp_get_attachment_url( $aid );
					$filename = $url ? basename( $url ) : ( 'File #' . $aid );
				}

				$nonce = wp_create_nonce( 'srf_download_' . $view_id . '_' . $aid );

				$download_url = SRF_MyAccount::url_list( array(
					'srf_download' => $aid,
					'srf_request'  => $view_id,
					'srf_nonce'    => $nonce,
				) );

				echo '<li>';
				echo '<a href="' . esc_url( $download_url ) . '">' . esc_html( $filename ) . '</a>';
				echo '</li>';
			}

			echo '</ul>';
		}
		echo '</div>';

		// ✅ Edit form
		echo '<form method="post" enctype="multipart/form-data" class="srf-modal__form">';
		echo '<input type="hidden" name="srf_action" value="update_request" />';
		echo '<input type="hidden" name="request_id" value="' . esc_attr( $view_id ) . '" />';
		wp_nonce_field( 'srf_edit_request' );

		echo '<p><label><strong>' . esc_html__( 'Description', 'service-requests-form' ) . '</strong></label><br />';
		echo '<textarea name="description" rows="6" style="width:100%;">' . esc_textarea( $desc ) . '</textarea></p>';

		echo '<p><label><strong>' . esc_html__( 'Upload new file(s)', 'service-requests-form' ) . '</strong></label><br />';
		echo '<input type="file" name="srf_files[]" multiple /></p>';

		echo '<p style="margin-top:14px;">';
		echo '<button type="submit" class="button button-primary">' . esc_html__( 'Save changes', 'service-requests-form' ) . '</button> ';
		echo '<a class="button" href="' . esc_url( SRF_MyAccount::url_list() ) . '">' . esc_html__( 'Cancel', 'service-requests-form' ) . '</a>';
		echo '</p>';

		echo '</form>';

		echo '</div></div>';

		// Minimal inline script to close modal
		echo '<script>(function(){var m=document.getElementById("srf-request-modal");if(!m)return;function close(){window.location.href=' . wp_json_encode( SRF_MyAccount::url_list() ) . ';}m.addEventListener("click",function(e){if(e.target&&e.target.hasAttribute("data-srf-close"))close();});document.addEventListener("keydown",function(e){if(e.key==="Escape")close();});})();</script>';

		// Minimal styles (so modal works even without theme CSS)
		echo '<style>
		.srf-modal{position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;}
		.srf-modal__overlay{position:absolute;inset:0;background:rgba(0,0,0,.55);}
		.srf-modal__panel{position:relative;max-width:760px;width:92%;background:#fff;border-radius:12px;padding:18px;z-index:2;box-shadow:0 10px 40px rgba(0,0,0,.35);max-height:90vh;overflow:auto;}
		.srf-modal__close{position:absolute;right:12px;top:10px;border:0;background:transparent;font-size:28px;line-height:1;cursor:pointer;}
		</style>';
	}
}

if ( ! $query->have_posts() ) {
	echo '<p>' . esc_html__( 'You have no requests yet.', 'service-requests-form' ) . '</p>';
	echo '</div>';
	return;
}

echo '<table class="shop_table shop_table_responsive my_account_orders srf-myaccount__table">';
echo '<thead><tr>';
echo '<th>' . esc_html__( 'Date', 'service-requests-form' ) . '</th>';
echo '<th>' . esc_html__( 'Service', 'service-requests-form' ) . '</th>';
echo '<th>' . esc_html__( 'Status', 'service-requests-form' ) . '</th>';
echo '<th>' . esc_html__( 'Uploads', 'service-requests-form' ) . '</th>';
echo '<th>' . esc_html__( 'Request', 'service-requests-form' ) . '</th>';
echo '<th>' . esc_html__( 'Action', 'service-requests-form' ) . '</th>';
echo '</tr></thead><tbody>';

while ( $query->have_posts() ) {
	$query->the_post();

	$rid     = get_the_ID();
	$service = (string) get_post_meta( $rid, '_sr_service_title', true );
	$status  = (string) get_post_meta( $rid, '_sr_status', true );
	if ( '' === $status ) {
		$status = 'new';
	}

	$summary = SRF_MyAccount::get_upload_summary( $rid );

	$status_label = SRF_MyAccount::format_status_label( $status );
	$status_class = 'srf-status-badge srf-status-badge--' . sanitize_html_class( $status );

	$uploads_text = '—';
	if ( $summary['count'] > 0 ) {
		$uploads_text = sprintf(
			_n( '%1$d file (%2$s)', '%1$d files (%2$s)', $summary['count'], 'service-requests-form' ),
			(int) $summary['count'],
			size_format( (int) $summary['bytes'] )
		);
	}

	// ✅ MUST remain inside My Account endpoint URL
	$view_url = SRF_MyAccount::url_view( $rid );

	echo '<tr>';
	echo '<td data-title="' . esc_attr__( 'Date', 'service-requests-form' ) . '">' . esc_html( get_the_date() ) . '</td>';
	echo '<td data-title="' . esc_attr__( 'Service', 'service-requests-form' ) . '">' . esc_html( $service ) . '</td>';
	echo '<td data-title="' . esc_attr__( 'Status', 'service-requests-form' ) . '"><span class="' . esc_attr( $status_class ) . '">' . esc_html( $status_label ) . '</span></td>';
	echo '<td data-title="' . esc_attr__( 'Uploads', 'service-requests-form' ) . '">' . esc_html( $uploads_text ) . '</td>';
	echo '<td data-title="' . esc_attr__( 'Request', 'service-requests-form' ) . '">#' . esc_html( $rid ) . '</td>';
	echo '<td data-title="' . esc_attr__( 'Action', 'service-requests-form' ) . '"><a class="button" href="' . esc_url( $view_url ) . '">' . esc_html__( 'View', 'service-requests-form' ) . '</a></td>';
	echo '</tr>';
}

echo '</tbody></table>';

$total_pages = (int) $query->max_num_pages;
if ( $total_pages > 1 ) {
	$base = SRF_MyAccount::url_list( array( 'srpage' => '%#%' ) );

	$links = paginate_links( array(
		'base'      => $base,
		'format'    => '',
		'current'   => max( 1, (int) $page ),
		'total'     => $total_pages,
		'type'      => 'list',
		'prev_text' => '&larr;',
		'next_text' => '&rarr;',
	) );

	if ( $links ) {
		echo '<nav class="woocommerce-pagination srf-myaccount__pagination">' . wp_kses_post( $links ) . '</nav>';
	}
}

echo '</div>';
