<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SRF_Admin_Menu {

	const PARENT_SLUG = 'srf-main';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_parent_menu' ), 9 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
	}

	public static function register_parent_menu() {

		// Parent menu
		add_menu_page(
			__( 'Service and Subscription', 'service-requests-form' ),
			__( 'Service and Subscription', 'service-requests-form' ),
			'edit_posts',
			self::PARENT_SLUG,
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-clipboard',
			26
		);

		// Submenus that point to CPT screens
		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Dashboard', 'service-requests-form' ),
			__( 'Dashboard', 'service-requests-form' ),
			'edit_posts',
			self::PARENT_SLUG
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Service Requests', 'service-requests-form' ),
			__( 'Service Requests', 'service-requests-form' ),
			'edit_posts',
			'edit.php?post_type=service_request'
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Add New Request', 'service-requests-form' ),
			__( 'Add New Request', 'service-requests-form' ),
			'edit_posts',
			'post-new.php?post_type=service_request'
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Services', 'service-requests-form' ),
			__( 'Services', 'service-requests-form' ),
			'edit_posts',
			'edit.php?post_type=sr_service'
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Add New Service', 'service-requests-form' ),
			__( 'Add New Service', 'service-requests-form' ),
			'edit_posts',
			'post-new.php?post_type=sr_service'
		);

		// NOTE: Storage submenu is registered by SRF_Admin_Storage under this parent slug.
		// Settings submenu can be added similarly if you have a settings page.
	}

	public static function enqueue_admin_assets( $hook ) {
		// Only load on our dashboard page
		if ( 'toplevel_page_' . self::PARENT_SLUG !== $hook ) {
			return;
		}

		// We use small inline CSS in render_dashboard(). No external CSS required.
	}

	protected static function get_counts() {

		// Total requests
		$total_requests = (int) wp_count_posts( 'service_request' )->publish;

		// Status counts (meta _sr_status)
		global $wpdb;
		$meta_key = '_sr_status';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_value AS status, COUNT(*) AS cnt
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = %s
				   AND p.post_type = %s
				   AND p.post_status = %s
				 GROUP BY meta_value",
				$meta_key,
				'service_request',
				'publish'
			),
			ARRAY_A
		);

		$status_counts = array(
			'new'         => 0,
			'in_progress' => 0,
			'done'        => 0,
		);

		foreach ( $rows as $r ) {
			$key = (string) $r['status'];
			if ( isset( $status_counts[ $key ] ) ) {
				$status_counts[ $key ] = (int) $r['cnt'];
			}
		}

		// Services count
		$total_services = 0;
		$svc_counts = wp_count_posts( 'sr_service' );
		if ( $svc_counts && isset( $svc_counts->publish ) ) {
			$total_services = (int) $svc_counts->publish;
		}

		// Total storage used by all users (sum user meta)
		$total_storage = 0;
		$users = get_users( array(
			'fields'     => array( 'ID' ),
			'number'     => 500,
			'orderby'    => 'ID',
			'order'      => 'ASC',
		) );

		foreach ( $users as $u ) {
			$total_storage += (int) get_user_meta( $u->ID, '_srf_storage_used_bytes', true );
		}

		return array(
			'total_requests' => $total_requests,
			'new'            => $status_counts['new'],
			'in_progress'    => $status_counts['in_progress'],
			'done'           => $status_counts['done'],
			'total_services' => $total_services,
			'total_storage'  => max( 0, (int) $total_storage ),
		);
	}

	protected static function badge_html( $status ) {
		$status = (string) $status;
		if ( $status === '' ) $status = 'new';

		$label = ucfirst( str_replace( '_', ' ', $status ) );
		$class = 'srf-badge srf-badge--' . sanitize_html_class( $status );

		return '<span class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span>';
	}

	public static function render_dashboard() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$counts = self::get_counts();

		$requests_url     = admin_url( 'edit.php?post_type=service_request' );
		$new_request_url  = admin_url( 'post-new.php?post_type=service_request' );
		$services_url     = admin_url( 'edit.php?post_type=sr_service' );
		$new_service_url  = admin_url( 'post-new.php?post_type=sr_service' );

		$storage_url = '';
		if ( class_exists( 'SRF_Admin_Storage' ) ) {
			// Storage is a submenu under our parent slug
			$storage_url = admin_url( 'admin.php?page=srf-storage' );
		}

		// Recent requests
		$recent = get_posts( array(
			'post_type'      => 'service_request',
			'post_status'    => 'publish',
			'numberposts'    => 10,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );

		?>
		<div class="wrap srf-dashboard">
			<style>
				.srf-dashboard .srf-header{
					display:flex; align-items:center; justify-content:space-between;
					margin: 10px 0 18px;
				}
				.srf-dashboard .srf-title{
					display:flex; align-items:center; gap:12px;
				}
				.srf-dashboard .srf-title .dashicons{
					font-size:32px; width:32px; height:32px;
				}
				.srf-dashboard .srf-subtitle{
					color:#667085; margin-top:4px;
				}
				.srf-dashboard .srf-actions{
					display:flex; gap:8px; flex-wrap:wrap;
				}
				.srf-dashboard .srf-actions .button{
					padding: 6px 12px;
				}
				.srf-dashboard .srf-grid{
					display:grid;
					grid-template-columns: repeat(6, minmax(0,1fr));
					gap:12px;
					margin: 10px 0 18px;
				}
				@media (max-width: 1300px){
					.srf-dashboard .srf-grid{ grid-template-columns: repeat(3, minmax(0,1fr)); }
				}
				@media (max-width: 782px){
					.srf-dashboard .srf-grid{ grid-template-columns: repeat(2, minmax(0,1fr)); }
				}
				.srf-card{
					background:#fff;
					border:1px solid #e5e7eb;
					border-radius:12px;
					padding:14px 14px 12px;
					box-shadow: 0 1px 2px rgba(16,24,40,.06);
				}
				.srf-card .srf-card__label{
					color:#667085;
					font-size:12px;
					margin-bottom:6px;
				}
				.srf-card .srf-card__value{
					font-size:24px;
					font-weight:700;
					line-height:1.1;
				}
				.srf-card .srf-card__hint{
					color:#667085;
					font-size:12px;
					margin-top:8px;
				}
				.srf-section{
					margin-top: 16px;
				}
				.srf-section h2{
					margin: 0 0 10px;
				}
				.srf-table{
					background:#fff;
					border:1px solid #e5e7eb;
					border-radius:12px;
					overflow:hidden;
				}
				.srf-table table{
					width:100%;
					border-collapse:collapse;
				}
				.srf-table th, .srf-table td{
					padding:10px 12px;
					border-bottom:1px solid #eef2f6;
					vertical-align:middle;
				}
				.srf-table th{
					text-align:left;
					background:#f9fafb;
					color:#344054;
					font-weight:600;
				}
				.srf-table tr:last-child td{
					border-bottom:none;
				}
				.srf-badge{
					display:inline-flex;
					align-items:center;
					padding: 3px 8px;
					border-radius:999px;
					font-size:12px;
					font-weight:600;
					background:#eef2f6;
					color:#344054;
				}
				.srf-badge--new{ background:#eef2ff; color:#3730a3; }
				.srf-badge--in_progress{ background:#fff7ed; color:#9a3412; }
				.srf-badge--done{ background:#ecfdf3; color:#027a48; }
				.srf-muted{ color:#667085; }
			</style>

			<div class="srf-header">
				<div class="srf-title">
					<span class="dashicons dashicons-clipboard"></span>
					<div>
						<h1 style="margin:0;"><?php esc_html_e( 'Service and Subscription', 'service-requests-form' ); ?></h1>
						<div class="srf-subtitle">
							<?php echo esc_html__( 'Manage services, requests, files, and storage in one place.', 'service-requests-form' ); ?>
						</div>
					</div>
				</div>

				<div class="srf-actions">
					<a class="button button-primary" href="<?php echo esc_url( $new_request_url ); ?>">
						<?php esc_html_e( 'Add Request', 'service-requests-form' ); ?>
					</a>
					<a class="button" href="<?php echo esc_url( $requests_url ); ?>">
						<?php esc_html_e( 'View Requests', 'service-requests-form' ); ?>
					</a>
					<a class="button" href="<?php echo esc_url( $new_service_url ); ?>">
						<?php esc_html_e( 'Add Service', 'service-requests-form' ); ?>
					</a>
					<a class="button" href="<?php echo esc_url( $services_url ); ?>">
						<?php esc_html_e( 'View Services', 'service-requests-form' ); ?>
					</a>
					<?php if ( $storage_url ) : ?>
						<a class="button" href="<?php echo esc_url( $storage_url ); ?>">
							<?php esc_html_e( 'Storage', 'service-requests-form' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>

			<div class="srf-grid">
				<div class="srf-card">
					<div class="srf-card__label"><?php esc_html_e( 'Total Requests', 'service-requests-form' ); ?></div>
					<div class="srf-card__value"><?php echo esc_html( number_format_i18n( $counts['total_requests'] ) ); ?></div>
					<div class="srf-card__hint srf-muted"><?php esc_html_e( 'All published requests', 'service-requests-form' ); ?></div>
				</div>

				<div class="srf-card">
					<div class="srf-card__label"><?php esc_html_e( 'New', 'service-requests-form' ); ?></div>
					<div class="srf-card__value"><?php echo esc_html( number_format_i18n( $counts['new'] ) ); ?></div>
					<div class="srf-card__hint"><?php echo self::badge_html( 'new' ); ?></div>
				</div>

				<div class="srf-card">
					<div class="srf-card__label"><?php esc_html_e( 'In Progress', 'service-requests-form' ); ?></div>
					<div class="srf-card__value"><?php echo esc_html( number_format_i18n( $counts['in_progress'] ) ); ?></div>
					<div class="srf-card__hint"><?php echo self::badge_html( 'in_progress' ); ?></div>
				</div>

				<div class="srf-card">
					<div class="srf-card__label"><?php esc_html_e( 'Done', 'service-requests-form' ); ?></div>
					<div class="srf-card__value"><?php echo esc_html( number_format_i18n( $counts['done'] ) ); ?></div>
					<div class="srf-card__hint"><?php echo self::badge_html( 'done' ); ?></div>
				</div>

				<div class="srf-card">
					<div class="srf-card__label"><?php esc_html_e( 'Services', 'service-requests-form' ); ?></div>
					<div class="srf-card__value"><?php echo esc_html( number_format_i18n( $counts['total_services'] ) ); ?></div>
					<div class="srf-card__hint srf-muted"><?php esc_html_e( 'Active service entries', 'service-requests-form' ); ?></div>
				</div>

				<div class="srf-card">
					<div class="srf-card__label"><?php esc_html_e( 'Total Storage Used', 'service-requests-form' ); ?></div>
					<div class="srf-card__value"><?php echo esc_html( size_format( $counts['total_storage'] ) ); ?></div>
					<div class="srf-card__hint srf-muted"><?php esc_html_e( 'Sum of all users usage', 'service-requests-form' ); ?></div>
				</div>
			</div>

			<div class="srf-section">
				<h2><?php esc_html_e( 'Recent Requests', 'service-requests-form' ); ?></h2>

				<div class="srf-table">
					<table>
						<thead>
							<tr>
								<th><?php esc_html_e( 'Request', 'service-requests-form' ); ?></th>
								<th><?php esc_html_e( 'Service', 'service-requests-form' ); ?></th>
								<th><?php esc_html_e( 'Status', 'service-requests-form' ); ?></th>
								<th><?php esc_html_e( 'Date', 'service-requests-form' ); ?></th>
								<th><?php esc_html_e( 'Open', 'service-requests-form' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $recent ) ) : ?>
								<tr>
									<td colspan="5" class="srf-muted"><?php esc_html_e( 'No requests yet.', 'service-requests-form' ); ?></td>
								</tr>
							<?php else : ?>
								<?php foreach ( $recent as $p ) :
									$rid     = (int) $p->ID;
									$service = (string) get_post_meta( $rid, '_sr_service_title', true );
									$status  = (string) get_post_meta( $rid, '_sr_status', true );
									if ( '' === $status ) $status = 'new';
									$edit_url = get_edit_post_link( $rid, 'raw' );
									?>
									<tr>
										<td>
											<strong><?php echo esc_html( $p->post_title ); ?></strong><br>
											<span class="srf-muted">#<?php echo esc_html( $rid ); ?></span>
										</td>
										<td><?php echo esc_html( $service ); ?></td>
										<td><?php echo self::badge_html( $status ); ?></td>
										<td><?php echo esc_html( get_date_from_gmt( $p->post_date_gmt, 'Y-m-d H:i' ) ); ?></td>
										<td>
											<?php if ( $edit_url ) : ?>
												<a class="button button-small" href="<?php echo esc_url( $edit_url ); ?>">
													<?php esc_html_e( 'Open', 'service-requests-form' ); ?>
												</a>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>

			</div>
		</div>
		<?php
	}
}
