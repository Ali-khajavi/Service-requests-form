<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** @var array $data */

$request_id   = (int) $data['request_id'];
$status       = (string) $data['status'];
$status_label = SRF_MyAccount::format_status_label( $status );
$status_class = 'srf-status-badge srf-status-badge--' . sanitize_html_class( $status );

$back_url = SRF_MyAccount::url_list();

echo '<div class="srf-myaccount srf-myaccount--single">';

echo '<p class="srf-myaccount__back"><a href="' . esc_url( $back_url ) . '">&larr; ' . esc_html__( 'Back to list', 'service-requests-form' ) . '</a></p>';

echo '<header class="srf-myaccount__header">';
echo '<h3 class="srf-myaccount__title">' . esc_html__( 'Request', 'service-requests-form' ) . ' #' . esc_html( $request_id ) . '</h3>';
echo '<span class="' . esc_attr( $status_class ) . '">' . esc_html( $status_label ) . '</span>';
echo '</header>';

echo '<div class="srf-myaccount__meta">';
echo '<p><strong>' . esc_html__( 'Service:', 'service-requests-form' ) . '</strong> ' . esc_html( $data['service'] ) . '</p>';
echo '<p><strong>' . esc_html__( 'Created:', 'service-requests-form' ) . '</strong> ' . esc_html( $data['date'] ) . '</p>';

if ( ! empty( $data['company'] ) ) echo '<p><strong>' . esc_html__( 'Company:', 'service-requests-form' ) . '</strong> ' . esc_html( $data['company'] ) . '</p>';
if ( ! empty( $data['name'] ) )    echo '<p><strong>' . esc_html__( 'Name:', 'service-requests-form' ) . '</strong> ' . esc_html( $data['name'] ) . '</p>';
if ( ! empty( $data['email'] ) )   echo '<p><strong>' . esc_html__( 'Email:', 'service-requests-form' ) . '</strong> ' . esc_html( $data['email'] ) . '</p>';
if ( ! empty( $data['phone'] ) )   echo '<p><strong>' . esc_html__( 'Phone:', 'service-requests-form' ) . '</strong> ' . esc_html( $data['phone'] ) . '</p>';

if ( ! empty( $data['shipping'] ) ) {
	echo '<p><strong>' . esc_html__( 'Shipping Address:', 'service-requests-form' ) . '</strong><br>' . nl2br( esc_html( $data['shipping'] ) ) . '</p>';
}
echo '</div>';

if ( ! empty( $data['message'] ) ) {
	echo '<div class="srf-myaccount__message">';
	echo '<h4>' . esc_html__( 'Message', 'service-requests-form' ) . '</h4>';
	echo '<div class="srf-myaccount__message-body">' . nl2br( esc_html( $data['message'] ) ) . '</div>';
	echo '</div>';
}

$file_ids = is_array( $data['file_ids'] ) ? array_filter( array_map( 'absint', $data['file_ids'] ) ) : array();

echo '<div class="srf-myaccount__files">';
echo '<h4>' . esc_html__( 'Files', 'service-requests-form' ) . '</h4>';

if ( empty( $file_ids ) ) {
	echo '<p>' . esc_html__( 'No files uploaded.', 'service-requests-form' ) . '</p>';
	echo '</div></div>';
	return;
}

echo '<ul class="srf-myaccount__file-list">';
foreach ( $file_ids as $aid ) {

	$path = get_attached_file( $aid );
	$name = get_the_title( $aid );
	if ( ! $name ) $name = $path ? basename( (string) $path ) : ( 'File #' . $aid );

	$bytes = (int) get_post_meta( $aid, '_srf_file_bytes', true );
	if ( $bytes <= 0 && $path && file_exists( $path ) ) $bytes = (int) filesize( $path );

	$nonce = wp_create_nonce( 'srf_download_' . $request_id . '_' . $aid );

	$download_url = add_query_arg(
		array(
			'srf_download' => $aid,
			'srf_request'  => $request_id,
			'srf_nonce'    => $nonce,
		),
		SRF_MyAccount::url_view( $request_id )
	);

	echo '<li class="srf-myaccount__file">';
	echo '<a class="srf-myaccount__file-link" href="' . esc_url( $download_url ) . '">' . esc_html( $name ) . '</a>';
	if ( $bytes > 0 ) echo ' <span class="srf-myaccount__file-size">(' . esc_html( size_format( $bytes ) ) . ')</span>';
	echo '</li>';
}
echo '</ul>';

echo '</div></div>';
