<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'SR_CPT' ) ) {

class SR_CPT {

	public static function register_cpt() {

		$labels = array(
			'name'          => __( 'Service Requests', 'service-requests-form' ),
			'singular_name' => __( 'Service Request', 'service-requests-form' ),
		);

		$args = array(
			'labels'          => $labels,
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => ( class_exists( 'SRF_Admin_Menu' ) ? SRF_Admin_Menu::PARENT_SLUG : true ),
			'supports'        => array( 'title', 'editor' ),
			'has_archive'     => false,
			'rewrite'         => false,
			'capability_type' => 'post',
			'show_in_rest'    => false,
		);

		register_post_type( 'service_request', $args );

		// =========================
		// Admin UI (columns/metabox/export)
		// =========================
		if ( is_admin() ) {

			add_filter( 'manage_service_request_posts_columns', array( __CLASS__, 'add_admin_columns' ) );
			add_action( 'manage_service_request_posts_custom_column', array( __CLASS__, 'render_admin_columns' ), 10, 2 );

			add_action( 'add_meta_boxes_service_request', array( __CLASS__, 'add_request_metaboxes' ) );

			add_filter( 'post_row_actions', array( __CLASS__, 'add_row_actions' ), 10, 2 );

			add_action( 'admin_post_srf_export_request', array( __CLASS__, 'handle_export_request' ) );
		}
	}

	// -------- Columns --------

	public static function add_admin_columns( $cols ) {

		// Keep checkbox + title first
		$new = array();
		foreach ( $cols as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'title' === $k ) {
				$new['srf_service']   = __( 'Service', 'service-requests-form' );
				$new['srf_variants']  = __( 'Variants', 'service-requests-form' );
				$new['srf_customer']  = __( 'Customer', 'service-requests-form' );
				$new['srf_email']     = __( 'Email', 'service-requests-form' );
				$new['srf_status']    = __( 'Status', 'service-requests-form' );
			}
		}

		return $new;
	}

	public static function render_admin_columns( $col, $post_id ) {

		if ( 'srf_service' === $col ) {
			echo esc_html( (string) get_post_meta( $post_id, '_sr_service_title', true ) );
			return;
		}

		if ( 'srf_variants' === $col ) {
			$variants = get_post_meta( $post_id, '_sr_variants', true );
			echo esc_html( self::format_variants_inline( $variants ) );
			return;
		}

		if ( 'srf_customer' === $col ) {
			echo esc_html( (string) get_post_meta( $post_id, '_sr_name', true ) );
			return;
		}

		if ( 'srf_email' === $col ) {
			$email = (string) get_post_meta( $post_id, '_sr_email', true );
			echo $email ? esc_html( $email ) : '—';
			return;
		}

		if ( 'srf_status' === $col ) {
			$status = (string) get_post_meta( $post_id, '_sr_status', true );
			echo esc_html( $status ? $status : 'new' );
			return;
		}
	}

	private static function format_variants_inline( $variants ) {
		if ( ! is_array( $variants ) || empty( $variants ) ) {
			return '—';
		}
		$parts = array();
		foreach ( $variants as $k => $v ) {
			$k = trim( (string) $k );
			$v = trim( (string) $v );
			if ( $k !== '' && $v !== '' ) {
				$parts[] = $k . ': ' . $v;
			}
		}
		return empty( $parts ) ? '—' : implode( ' | ', $parts );
	}

	// -------- Metabox --------

	public static function add_request_metaboxes() {
		add_meta_box(
			'srf_request_summary',
			__( 'Request Summary', 'service-requests-form' ),
			array( __CLASS__, 'render_request_summary_metabox' ),
			'service_request',
			'normal',
			'high'
		);
	}

	public static function render_request_summary_metabox( $post ) {

		$rid = (int) $post->ID;

		$service  = (string) get_post_meta( $rid, '_sr_service_title', true );
		$name     = (string) get_post_meta( $rid, '_sr_name', true );
		$company  = (string) get_post_meta( $rid, '_sr_company', true );
		$email    = (string) get_post_meta( $rid, '_sr_email', true );
		$phone    = (string) get_post_meta( $rid, '_sr_phone', true );
		$ship     = (string) get_post_meta( $rid, '_sr_shipping_address', true );
		$status   = (string) get_post_meta( $rid, '_sr_status', true );
		$variants = get_post_meta( $rid, '_sr_variants', true );

		$nonce = wp_create_nonce( 'srf_export_' . $rid );

		$export_html = admin_url( 'admin-post.php?action=srf_export_request&request_id=' . $rid . '&format=html&_wpnonce=' . $nonce );
		$export_mail = admin_url( 'admin-post.php?action=srf_export_request&request_id=' . $rid . '&format=email&_wpnonce=' . $nonce );

		echo '<p style="margin:0 0 10px;">';
		echo '<a class="button button-primary" href="' . esc_url( $export_html ) . '">' . esc_html__( 'Download (HTML)', 'service-requests-form' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( $export_mail ) . '">' . esc_html__( 'Email Template (HTML)', 'service-requests-form' ) . '</a> ';
		echo '<span style="margin-left:10px;color:#666;">' . esc_html__( 'Tip: open HTML and Print → Save as PDF', 'service-requests-form' ) . '</span>';
		echo '</p>';

		echo '<table class="widefat striped" style="max-width:100%;">';
		echo '<tbody>';
		echo '<tr><th style="width:180px;">' . esc_html__( 'Service', 'service-requests-form' ) . '</th><td>' . esc_html( $service ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Status', 'service-requests-form' ) . '</th><td>' . esc_html( $status ? $status : 'new' ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Customer', 'service-requests-form' ) . '</th><td>' . esc_html( $name ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Company', 'service-requests-form' ) . '</th><td>' . esc_html( $company ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Email', 'service-requests-form' ) . '</th><td>' . esc_html( $email ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Phone', 'service-requests-form' ) . '</th><td>' . esc_html( $phone ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Shipping', 'service-requests-form' ) . '</th><td>' . esc_html( $ship ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Variants', 'service-requests-form' ) . '</th><td>' . esc_html( self::format_variants_inline( $variants ) ) . '</td></tr>';
		echo '</tbody>';
		echo '</table>';
	}

	// -------- Row action links (in the list table) --------

	public static function add_row_actions( $actions, $post ) {

		if ( empty( $post ) || 'service_request' !== $post->post_type ) {
			return $actions;
		}

		$rid = (int) $post->ID;
		if ( ! current_user_can( 'edit_post', $rid ) ) {
			return $actions;
		}

		$nonce = wp_create_nonce( 'srf_export_' . $rid );

		$actions['srf_export_html'] = '<a href="' . esc_url(
			admin_url( 'admin-post.php?action=srf_export_request&request_id=' . $rid . '&format=html&_wpnonce=' . $nonce )
		) . '">' . esc_html__( 'Download HTML', 'service-requests-form' ) . '</a>';

		$actions['srf_export_email'] = '<a href="' . esc_url(
			admin_url( 'admin-post.php?action=srf_export_request&request_id=' . $rid . '&format=email&_wpnonce=' . $nonce )
		) . '">' . esc_html__( 'Email Template', 'service-requests-form' ) . '</a>';

		return $actions;
	}

	// -------- Export handler --------

	public static function handle_export_request() {

		$rid    = isset( $_GET['request_id'] ) ? absint( $_GET['request_id'] ) : 0;
		$format = isset( $_GET['format'] ) ? sanitize_key( $_GET['format'] ) : 'html';

		if ( ! $rid ) {
			wp_die( 'Missing request_id.' );
		}

		if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'srf_export_' . $rid ) ) {
			wp_die( 'Security check failed.' );
		}

		if ( ! current_user_can( 'edit_post', $rid ) ) {
			wp_die( 'Not allowed.' );
		}

		$html = self::build_export_html( $rid, $format );

		$filename = 'request-' . $rid . '-' . $format . '.html';

		nocache_headers();
		header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	public static function build_export_html( $rid, $format = 'html' ) {

		$post = get_post( $rid );
		if ( ! $post ) {
			return '<!doctype html><html><body>Request not found.</body></html>';
		}

		$service  = (string) get_post_meta( $rid, '_sr_service_title', true );
		$name     = (string) get_post_meta( $rid, '_sr_name', true );
		$company  = (string) get_post_meta( $rid, '_sr_company', true );
		$email    = (string) get_post_meta( $rid, '_sr_email', true );
		$phone    = (string) get_post_meta( $rid, '_sr_phone', true );
		$ship     = (string) get_post_meta( $rid, '_sr_shipping_address', true );
		$status   = (string) get_post_meta( $rid, '_sr_status', true );
		$variants = get_post_meta( $rid, '_sr_variants', true );
		$files    = class_exists( 'SRF_Request_Files' ) ? SRF_Request_Files::get_files( $rid ) : array();

		$desc = (string) $post->post_content;

		$variants_lines = array();
		if ( is_array( $variants ) ) {
			foreach ( $variants as $k => $v ) {
				$k = trim( (string) $k );
				$v = trim( (string) $v );
				if ( $k !== '' && $v !== '' ) $variants_lines[] = $k . ': ' . $v;
			}
		}

		$title = ( 'email' === $format )
			? 'Service Request (Email Template) #' . $rid
			: 'Service Request #' . $rid;

		ob_start();
		?>
		<!doctype html>
		<html>
		<head>
			<meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>">
			<meta name="viewport" content="width=device-width,initial-scale=1">
			<title><?php echo esc_html( $title ); ?></title>
			<style>
				body{font-family:Arial,Helvetica,sans-serif;line-height:1.45;padding:18px;color:#111;}
				.card{border:1px solid #ddd;border-radius:10px;padding:14px;margin:0 0 14px;}
				h1{font-size:18px;margin:0 0 10px;}
				table{width:100%;border-collapse:collapse;}
				th,td{border-bottom:1px solid #eee;padding:8px 6px;text-align:left;vertical-align:top;}
				th{width:180px;color:#444;}
				.small{color:#666;font-size:12px;}
				ul{margin:8px 0 0 18px;}
				.badge{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid #ddd;font-size:12px;}
				@media print {.no-print{display:none;}}
			</style>
		</head>
		<body>
			<div class="no-print" style="margin-bottom:10px;">
				<button onclick="window.print()">Print / Save as PDF</button>
				<span class="small">Tip: in the print dialog choose “Save as PDF”.</span>
			</div>

			<div class="card">
				<h1><?php echo esc_html( $title ); ?></h1>
				<div class="small">Created: <?php echo esc_html( get_the_date( '', $rid ) ); ?> — Status: <span class="badge"><?php echo esc_html( $status ? $status : 'new' ); ?></span></div>
			</div>

			<div class="card">
				<table>
					<tr><th>Service</th><td><?php echo esc_html( $service ); ?></td></tr>
					<tr><th>Customer</th><td><?php echo esc_html( $name ); ?></td></tr>
					<tr><th>Company</th><td><?php echo esc_html( $company ); ?></td></tr>
					<tr><th>Email</th><td><?php echo esc_html( $email ); ?></td></tr>
					<tr><th>Phone</th><td><?php echo esc_html( $phone ); ?></td></tr>
					<tr><th>Shipping</th><td><?php echo esc_html( $ship ); ?></td></tr>
					<tr><th>Variants</th><td><?php echo esc_html( empty( $variants_lines ) ? '—' : implode( ' | ', $variants_lines ) ); ?></td></tr>
					<tr><th>Description</th><td><?php echo nl2br( esc_html( $desc ) ); ?></td></tr>
				</table>
			</div>

			<div class="card">
				<strong>Uploaded files</strong>
				<?php if ( empty( $files ) ) : ?>
					<div class="small">No files uploaded.</div>
				<?php else : ?>
					<ul>
					<?php foreach ( $files as $file ) :
						if ( ! is_array( $file ) ) { continue; }
						$url  = ! empty( $file['attachment_id'] ) ? wp_get_attachment_url( (int) $file['attachment_id'] ) : '';
						$name = ! empty( $file['name'] ) ? (string) $file['name'] : ( ! empty( $url ) ? basename( $url ) : '' );
						$download_id = ! empty( $file['download_id'] ) ? (string) $file['download_id'] : '';
						?>
						<li>
							<?php if ( $url ) : ?>
								<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $name ? $name : ( 'File #' . $download_id ) ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $name ? $name : ( 'File #' . $download_id ) ); ?>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>

			<?php if ( 'email' === $format ) : ?>
				<div class="small">This layout is email-friendly HTML. Copy/paste into your email client.</div>
			<?php endif; ?>
		</body>
		</html>
		<?php
		return (string) ob_get_clean();
	}
}

}
