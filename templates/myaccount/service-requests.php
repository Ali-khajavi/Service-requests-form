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

		$desc          = (string) $view_post->post_content;
		$status        = (string) get_post_meta( $view_id, '_sr_status', true );
		$request_type  = (string) get_post_meta( $view_id, '_sr_request_type', true );
		$service_id    = (int) get_post_meta( $view_id, '_sr_service_id', true );
		$service_title = (string) get_post_meta( $view_id, '_sr_service_title', true );
		$project_title = (string) get_post_meta( $view_id, '_sr_project_title', true );

		if ( '' === $status ) {
			$status = 'new';
		}
		if ( '' === $request_type ) {
			$request_type = 'service';
		}

		$is_project  = ( 'project' === $request_type );
		$is_editable = ( 'new' === strtolower( $status ) );

		$name             = (string) get_post_meta( $view_id, '_sr_name', true );
		$company          = (string) get_post_meta( $view_id, '_sr_company', true );
		$email            = (string) get_post_meta( $view_id, '_sr_email', true );
		$phone            = (string) get_post_meta( $view_id, '_sr_phone', true );
		$shipping_address = (string) get_post_meta( $view_id, '_sr_shipping_address', true );
		$quote_notes      = (string) get_post_meta( $view_id, '_sr_quote_notes', true );
		$price_total      = (float) get_post_meta( $view_id, '_sr_total_price', true );
		if ( $price_total <= 0 ) {
			$price_total = (float) get_post_meta( $view_id, '_sr_price_total', true );
		}
		$order_id         = (int) get_post_meta( $view_id, '_sr_wc_order_id', true );

		$material_name = (string) get_post_meta( $view_id, '_sr_material_name', true );
		$printer_name  = (string) get_post_meta( $view_id, '_sr_printer_name', true );
		$material_id   = (int) get_post_meta( $view_id, '_sr_material_id', true );
		$printer_id    = (int) get_post_meta( $view_id, '_sr_printer_id', true );

		if ( '' === trim( $material_name ) && $material_id > 0 && class_exists( 'SRF_Quote_DB' ) ) {
			$db = new SRF_Quote_DB();
			$material = $db->get_material( $material_id );
			if ( $material && ! empty( $material->name ) ) {
				$material_name = (string) $material->name;
			}
		}

		if ( '' === trim( $printer_name ) && $printer_id > 0 && class_exists( 'SRF_Quote_DB' ) ) {
			$db = isset( $db ) && $db instanceof SRF_Quote_DB ? $db : new SRF_Quote_DB();
			$printer = $db->get_printer( $printer_id );
			if ( $printer && ! empty( $printer->name ) ) {
				$printer_name = (string) $printer->name;
			}
		}

		$estimated_minutes = max( 0, (int) get_post_meta( $view_id, '_sr_estimated_print_minutes', true ) );
		$estimated_time    = '';
		if ( $estimated_minutes > 0 ) {
			$hours     = (int) floor( $estimated_minutes / 60 );
			$remaining = $estimated_minutes % 60;
			$estimated_time = $hours > 0
				? sprintf( _n( '%1$d hour %2$d min', '%1$d hours %2$d min', $hours, 'service-requests-form' ), $hours, $remaining )
				: sprintf( _n( '%d minute', '%d minutes', $remaining, 'service-requests-form' ), $remaining );
		}

		$project_details = array(
			'material'       => $material_name,
			'printer'        => $printer_name,
			'profile'        => (string) get_post_meta( $view_id, '_sr_print_profile_name', true ),
			'layer_height'   => (string) get_post_meta( $view_id, '_sr_layer_height', true ),
			'infill'         => (string) get_post_meta( $view_id, '_sr_infill', true ),
			'wall_loops'     => (string) get_post_meta( $view_id, '_sr_wall_loops', true ),
			'top_layers'     => (string) get_post_meta( $view_id, '_sr_top_layers', true ),
			'bottom_layers'  => (string) get_post_meta( $view_id, '_sr_bottom_layers', true ),
			'infill_pattern' => (string) get_post_meta( $view_id, '_sr_infill_pattern', true ),
			'supports'       => '1' === (string) get_post_meta( $view_id, '_sr_supports', true ) ? __( 'Yes', 'service-requests-form' ) : __( 'No', 'service-requests-form' ),
			'shell_mode'     => (string) get_post_meta( $view_id, '_sr_shell_mode', true ),
			'scale'          => (string) get_post_meta( $view_id, '_sr_scale', true ),
			'quantity'       => (string) get_post_meta( $view_id, '_sr_quantity', true ),
			'estimated_time' => $estimated_time,
		);

		// Current selected variants on request.
		$variants = get_post_meta( $view_id, '_sr_variants', true );
		if ( ! is_array( $variants ) ) {
			$variants = array();
		}

		// Variant definitions from the service.
		$variant_defs = array();
		if ( class_exists( 'SR_Services_CPT' ) && method_exists( 'SR_Services_CPT', 'get_variations' ) ) {
			$variant_defs = SR_Services_CPT::get_variations( $service_id );
		} else {
			$variant_defs = get_post_meta( $service_id, '_sr_service_variations', true );
		}
		$groups = is_array( $variant_defs ) ? $variant_defs : array();

		// Uploaded files for this request.
		$file_ids = get_post_meta( $view_id, '_sr_file_ids', true );
		if ( ! is_array( $file_ids ) ) {
			$file_ids = array();
		}
		$file_ids = array_filter( array_map( 'absint', $file_ids ) );

		$status_label = SRF_MyAccount::format_status_label( $status );
		$status_css   = sanitize_html_class( str_replace( '_', '-', strtolower( $status ) ) );
		$type_label   = $is_project ? __( 'Open 3D Project', 'service-requests-form' ) : __( 'Configured Service', 'service-requests-form' );

		$render_detail_row = static function( $label, $value ) {
			if ( is_array( $value ) ) {
				$value = implode( ', ', array_filter( array_map( 'strval', $value ) ) );
			}
			$value = trim( (string) $value );
			if ( '' === $value ) {
				$value = '—';
			}
			echo '<div class="srf-detail-grid__row">';
			echo '<div class="srf-detail-grid__label">' . esc_html( $label ) . '</div>';
			echo '<div class="srf-detail-grid__value">' . esc_html( $value ) . '</div>';
			echo '</div>';
		};

		echo '<div class="srf-modal is-open" id="srf-request-modal" role="dialog" aria-modal="true">';
		echo '<div class="srf-modal__overlay" data-srf-close></div>';
		echo '<div class="srf-modal__panel">';
		echo '<button type="button" class="srf-modal__close" data-srf-close aria-label="' . esc_attr__( 'Close', 'service-requests-form' ) . '">&times;</button>';

		echo '<h3 class="srf-modal__title">' . esc_html__( 'Request Details', 'service-requests-form' ) . ' #' . esc_html( $view_id ) . '</h3>';
		echo '<div class="srf-modal__meta-badges">';
		echo '<span class="srf-pill srf-pill--type">' . esc_html( $type_label ) . '</span>';
		echo '<span class="srf-pill srf-pill--status srf-pill--status-' . esc_attr( $status_css ) . '">' . esc_html( $status_label ) . '</span>';
		if ( $is_editable ) {
			echo '<span class="srf-pill srf-pill--edit">' . esc_html__( 'Description editable', 'service-requests-form' ) . '</span>';
		} else {
			echo '<span class="srf-pill srf-pill--readonly">' . esc_html__( 'Read only', 'service-requests-form' ) . '</span>';
		}
		echo '</div>';
		if ( $price_total > 0 || $order_id > 0 ) {
			echo '<div class="srf-modal__payment">';
			if ( $price_total > 0 ) {
				echo '<p><strong>' . esc_html__( 'Service price', 'service-requests-form' ) . ':</strong> ' . esc_html( function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( $price_total ) ) : number_format_i18n( $price_total, 2 ) ) . '</p>';
			}
			if ( $order_id > 0 ) {
				$order_url = function_exists( 'wc_get_endpoint_url' ) ? wc_get_endpoint_url( 'view-order', $order_id, wc_get_page_permalink( 'myaccount' ) ) : '';
				echo '<p><strong>' . esc_html__( 'WooCommerce order', 'service-requests-form' ) . ':</strong> ';
				if ( $order_url ) {
					echo '<a href="' . esc_url( $order_url ) . '">#' . esc_html( $order_id ) . '</a>';
				} else {
					echo '#' . esc_html( $order_id );
				}
				echo '</p>';
			}
			echo '</div>';
		}

		$export_nonce = wp_create_nonce( 'srf_export_' . $view_id );
		$export_html_url = SRF_MyAccount::url_list( array(
			'srf_export' => $view_id,
			'format'     => 'html',
			'srf_nonce'  => $export_nonce,
		) );
		$export_email_url = SRF_MyAccount::url_list( array(
			'srf_export' => $view_id,
			'format'     => 'email',
			'srf_nonce'  => $export_nonce,
		) );

		echo '<div class="srf-modal__exports">';
		echo '<a class="button" href="' . esc_url( $export_html_url ) . '">' . esc_html__( 'Download HTML', 'service-requests-form' ) . '</a>';
		echo '<a class="button" href="' . esc_url( $export_email_url ) . '">' . esc_html__( 'Email Template', 'service-requests-form' ) . '</a>';
		echo '</div>';

		echo '<div class="srf-detail-card">';
		echo '<h4 class="srf-detail-card__title">' . esc_html__( 'Request Overview', 'service-requests-form' ) . '</h4>';
		echo '<div class="srf-detail-grid">';
		$render_detail_row( __( 'Request ID', 'service-requests-form' ), '#' . $view_id );
		$render_detail_row( __( 'Date', 'service-requests-form' ), get_the_date( '', $view_id ) );
		$render_detail_row( __( 'Request Type', 'service-requests-form' ), $type_label );
		$render_detail_row( __( 'Status', 'service-requests-form' ), $status_label );
		$render_detail_row( __( 'Service', 'service-requests-form' ), $service_title ? $service_title : ( $is_project ? __( 'Project Request', 'service-requests-form' ) : '—' ) );
		if ( ! $is_project ) {
			$render_detail_row( __( 'Quantity', 'service-requests-form' ), (string) max( 1, (int) get_post_meta( $view_id, '_sr_quantity', true ) ) );
		}
		if ( $is_project ) {
			$render_detail_row( __( 'Project Title', 'service-requests-form' ), $project_title ? $project_title : $view_post->post_title );
		}
		echo '</div>';
		echo '</div>';

		if ( $is_project ) {
			echo '<div class="srf-detail-card">';
			echo '<h4 class="srf-detail-card__title">' . esc_html__( '3D Project Details', 'service-requests-form' ) . '</h4>';
			echo '<div class="srf-detail-grid">';
			$render_detail_row( __( 'Printer', 'service-requests-form' ), $project_details['printer'] );
			$render_detail_row( __( 'Material', 'service-requests-form' ), $project_details['material'] );
			$render_detail_row( __( 'Print Profile', 'service-requests-form' ), $project_details['profile'] );
			$render_detail_row( __( 'Layer Height', 'service-requests-form' ), '' !== $project_details['layer_height'] ? $project_details['layer_height'] . ' mm' : '' );
			$render_detail_row( __( 'Infill', 'service-requests-form' ), '' !== $project_details['infill'] ? $project_details['infill'] . '%' : '' );
			$render_detail_row( __( 'Infill Pattern', 'service-requests-form' ), $project_details['infill_pattern'] );
			$render_detail_row( __( 'Wall Loops', 'service-requests-form' ), $project_details['wall_loops'] );
			$render_detail_row( __( 'Top / Bottom Layers', 'service-requests-form' ), ( '' !== $project_details['top_layers'] || '' !== $project_details['bottom_layers'] ) ? $project_details['top_layers'] . ' / ' . $project_details['bottom_layers'] : '' );
			$render_detail_row( __( 'Supports', 'service-requests-form' ), $project_details['supports'] );
			$render_detail_row( __( 'Shell Mode', 'service-requests-form' ), $project_details['shell_mode'] );
			$render_detail_row( __( 'Scale', 'service-requests-form' ), '' !== $project_details['scale'] ? $project_details['scale'] . '%' : '' );
			$render_detail_row( __( 'Quantity', 'service-requests-form' ), $project_details['quantity'] );
			$render_detail_row( __( 'Estimated Print Time', 'service-requests-form' ), $project_details['estimated_time'] );
			echo '</div>';
			if ( '' !== trim( $quote_notes ) ) {
				echo '<div class="srf-detail-text">';
				echo '<h5>' . esc_html__( 'Printing Notes', 'service-requests-form' ) . '</h5>';
				echo '<div class="srf-detail-text__body">' . nl2br( esc_html( $quote_notes ) ) . '</div>';
				echo '</div>';
			}
			echo '</div>';
		} else {
			echo '<div class="srf-detail-card">';
			echo '<h4 class="srf-detail-card__title">' . esc_html__( 'Service Options', 'service-requests-form' ) . '</h4>';
			if ( ! empty( $variants ) ) {
				echo '<div class="srf-detail-grid">';
				foreach ( $variants as $variant_key => $variant_value ) {
					$render_detail_row( $variant_key, $variant_value );
				}
				echo '</div>';
			} else {
				echo '<p class="srf-empty-note">' . esc_html__( 'No service options were stored for this request.', 'service-requests-form' ) . '</p>';
			}
			echo '</div>';
		}

		echo '<div class="srf-detail-card">';
		echo '<h4 class="srf-detail-card__title">' . esc_html__( 'Customer Details', 'service-requests-form' ) . '</h4>';
		echo '<div class="srf-detail-grid">';
		$render_detail_row( __( 'Name', 'service-requests-form' ), $name );
		$render_detail_row( __( 'Company', 'service-requests-form' ), $company );
		$render_detail_row( __( 'Email', 'service-requests-form' ), $email );
		$render_detail_row( __( 'Phone', 'service-requests-form' ), $phone );
		$render_detail_row( __( 'Shipping Address', 'service-requests-form' ), $shipping_address );
		echo '</div>';
		echo '</div>';

		echo '<div class="srf-detail-card">';
		echo '<h4 class="srf-detail-card__title">' . esc_html__( 'Uploaded Files', 'service-requests-form' ) . '</h4>';
		if ( empty( $file_ids ) ) {
			echo '<p class="srf-empty-note">' . esc_html__( 'No files uploaded.', 'service-requests-form' ) . '</p>';
		} else {
			echo '<ul class="srf-file-list">';
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
				echo '<li><a href="' . esc_url( $download_url ) . '">' . esc_html( $filename ) . '</a></li>';
			}
			echo '</ul>';
		}
		echo '</div>';

		echo '<div class="srf-detail-card">';
		echo '<h4 class="srf-detail-card__title">' . esc_html__( 'Request Description', 'service-requests-form' ) . '</h4>';

		if ( $is_editable ) {
			echo '<form method="post" class="srf-modal__form">';
			echo '<input type="hidden" name="srf_action" value="update_request" />';
			echo '<input type="hidden" name="request_id" value="' . esc_attr( $view_id ) . '" />';
			wp_nonce_field( 'srf_edit_request' );
			echo '<p class="srf-help-note">' . esc_html__( 'You can still update your description while the request status is New. File replacement and 3D model changes are not available here.', 'service-requests-form' ) . '</p>';
			echo '<textarea name="description" rows="7" class="srf-description-field" style="width:100%;">' . esc_textarea( $desc ) . '</textarea>';
			echo '<p style="margin-top:14px;">';
			echo '<button type="submit" class="button button-primary">' . esc_html__( 'Save description', 'service-requests-form' ) . '</button> ';
			echo '<a class="button" href="' . esc_url( SRF_MyAccount::url_list() ) . '">' . esc_html__( 'Close', 'service-requests-form' ) . '</a>';
			echo '</p>';
			echo '</form>';
		} else {
			echo '<div class="srf-detail-text__body">' . ( '' !== trim( $desc ) ? nl2br( esc_html( $desc ) ) : '—' ) . '</div>';
			echo '<p class="srf-help-note">' . esc_html__( 'This request is no longer editable from your account because work has already started.', 'service-requests-form' ) . '</p>';
		}
		echo '</div>';

		echo '</div></div>';

		echo '<script>(function(){var m=document.getElementById("srf-request-modal");if(!m)return;function close(){window.location.href=' . wp_json_encode( SRF_MyAccount::url_list() ) . ';}m.addEventListener("click",function(e){if(e.target&&e.target.hasAttribute("data-srf-close"))close();});document.addEventListener("keydown",function(e){if(e.key==="Escape")close();});})();</script>';

		echo '<style>
		.srf-modal{position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;}
		.srf-modal__overlay{position:absolute;inset:0;background:rgba(0,0,0,.55);}
		.srf-modal__panel{position:relative;max-width:900px;width:94%;background:#fff;border-radius:12px;padding:22px;z-index:2;box-shadow:0 10px 40px rgba(0,0,0,.35);max-height:90vh;overflow:auto;}
		.srf-modal__close{position:absolute;right:12px;top:10px;border:0;background:transparent;font-size:28px;line-height:1;cursor:pointer;}
		.srf-modal__meta-badges,.srf-modal__exports{margin:10px 0 16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;}
		.srf-pill{display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:600;background:#f1f1f1;}
		.srf-pill--edit{background:#e8f7ea;color:#1e6b35;}
		.srf-pill--readonly{background:#f5f5f5;color:#555;}
		.srf-pill--type{background:#eef4ff;color:#264b8f;}
		.srf-pill--status-new{background:#eee9ff;color:#5b4db3;}
		.srf-pill--status-quote-ready{background:#e9f2ff;color:#245aa5;}
		.srf-pill--status-pending-payment{background:#fff4cc;color:#9a6a00;}
		.srf-pill--status-paid{background:#e7f7ee;color:#1f7a45;}
		.srf-pill--status-in-progress{background:#fff1e5;color:#b85b00;}
		.srf-pill--status-done{background:#e7f7ee;color:#1f7a45;}
		.srf-pill--status-payment-failed,.srf-pill--status-cancelled,.srf-pill--status-refunded{background:#fdecec;color:#a63232;}
		.srf-status-badge{display:inline-flex;align-items:center;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:600;background:#f1f1f1;}
		.srf-status-badge--new{background:#eee9ff;color:#5b4db3;}
		.srf-status-badge--quote-ready{background:#e9f2ff;color:#245aa5;}
		.srf-status-badge--pending-payment{background:#fff4cc;color:#9a6a00;}
		.srf-status-badge--paid{background:#e7f7ee;color:#1f7a45;}
		.srf-status-badge--in-progress{background:#fff1e5;color:#b85b00;}
		.srf-status-badge--done{background:#e7f7ee;color:#1f7a45;}
		.srf-status-badge--payment-failed,.srf-status-badge--cancelled,.srf-status-badge--refunded{background:#fdecec;color:#a63232;}
		.srf-detail-card{border:1px solid #e6e6e6;border-radius:10px;padding:16px;margin:0 0 16px;background:#fff;}
		.srf-detail-card__title{margin:0 0 12px;font-size:18px;}
		.srf-detail-grid{display:grid;grid-template-columns:minmax(160px,220px) 1fr;gap:10px 16px;}
		.srf-detail-grid__row{display:contents;}
		.srf-detail-grid__label{font-weight:600;color:#222;}
		.srf-detail-grid__value{color:#444;word-break:break-word;}
		.srf-detail-text h5{margin:14px 0 8px;font-size:15px;}
		.srf-detail-text__body{white-space:pre-wrap;line-height:1.55;color:#333;}
		.srf-file-list{margin:0;padding-left:18px;}
		.srf-file-list li{margin:0 0 6px;}
		.srf-empty-note,.srf-help-note{margin:0;color:#555;line-height:1.55;}
		.srf-description-field{max-width:100%;}
		@media (max-width: 640px){.srf-detail-grid{grid-template-columns:1fr;}.srf-modal__panel{padding:18px;}}
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
echo '<th>' . esc_html__( 'Price', 'service-requests-form' ) . '</th>';
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

	$summary     = SRF_MyAccount::get_upload_summary( $rid );
	$price_total = (float) get_post_meta( $rid, '_sr_total_price', true );
	if ( $price_total <= 0 ) {
		$price_total = (float) get_post_meta( $rid, '_sr_price_total', true );
	}
	$price_text   = $price_total > 0 ? ( function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( $price_total ) ) : number_format_i18n( $price_total, 2 ) ) : '—';

	$status_label = SRF_MyAccount::format_status_label( $status );
	$status_class = 'srf-status-badge srf-status-badge--' . sanitize_html_class( str_replace( '_', '-', strtolower( $status ) ) );

	$uploads_text = '—';
	if ( $summary['count'] > 0 ) {
		$uploads_text = sprintf(
			_n( '%1$d file (%2$s)', '%1$d files (%2$s)', $summary['count'], 'service-requests-form' ),
			(int) $summary['count'],
			size_format( (int) $summary['bytes'] )
		);
	}

	$view_url = SRF_MyAccount::url_view( $rid );

	echo '<tr>';
	echo '<td data-title="' . esc_attr__( 'Date', 'service-requests-form' ) . '">' . esc_html( get_the_date() ) . '</td>';
	echo '<td data-title="' . esc_attr__( 'Service', 'service-requests-form' ) . '">' . esc_html( $service ) . '</td>';
	echo '<td data-title="' . esc_attr__( 'Status', 'service-requests-form' ) . '"><span class="' . esc_attr( $status_class ) . '">' . esc_html( $status_label ) . '</span></td>';
	echo '<td data-title="' . esc_attr__( 'Uploads', 'service-requests-form' ) . '">' . esc_html( $uploads_text ) . '</td>';
	echo '<td data-title="' . esc_attr__( 'Price', 'service-requests-form' ) . '">' . esc_html( $price_text ) . '</td>';
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
