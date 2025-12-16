<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** @var WP_Query $query */
/** @var int $page */
/** @var int $per_page */
/** @var string $create_url */

echo '<div class="srf-myaccount srf-myaccount--list">';

echo '<div class="srf-myaccount__header">';
echo '<h3 class="srf-myaccount__title">' . esc_html__( 'Service Requests', 'service-requests-form' ) . '</h3>';

if ( ! empty( $create_url ) ) {
	echo '<a class="button srf-myaccount__create" href="' . esc_url( $create_url ) . '">' . esc_html__( 'Create new request', 'service-requests-form' ) . '</a>';
}
echo '</div>';

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
	if ( '' === $status ) $status = 'new';

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
