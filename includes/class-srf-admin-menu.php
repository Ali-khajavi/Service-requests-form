<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SRF_Admin_Menu {

	const PARENT_SLUG       = 'srf-main';
	const MATERIALS_SLUG    = 'srf-materials';
	const PRINTERS_SLUG     = 'srf-printers';
	const QUOTE_ORDERS_SLUG = 'srf-quote-orders';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_parent_menu' ), 9 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
	}

	public static function register_parent_menu() {

		add_menu_page(
			__( 'Service and Subscription', 'service-requests-form' ),
			__( 'Service and Subscription', 'service-requests-form' ),
			'edit_posts',
			self::PARENT_SLUG,
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-clipboard',
			26
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Dashboard', 'service-requests-form' ),
			__( 'Dashboard', 'service-requests-form' ),
			'edit_posts',
			self::PARENT_SLUG
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
			__( 'Add New Service', 'service-requests-form' ),
			__( 'Add New Service', 'service-requests-form' ),
			'edit_posts',
			'post-new.php?post_type=sr_service'
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Materials', 'service-requests-form' ),
			__( 'Materials', 'service-requests-form' ),
			'manage_options',
			self::MATERIALS_SLUG,
			array( __CLASS__, 'render_materials_page' )
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Printers', 'service-requests-form' ),
			__( 'Printers', 'service-requests-form' ),
			'manage_options',
			self::PRINTERS_SLUG,
			array( __CLASS__, 'render_printers_page' )
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Orders', 'service-requests-form' ),
			__( 'Orders', 'service-requests-form' ),
			'edit_posts',
			self::QUOTE_ORDERS_SLUG,
			array( __CLASS__, 'render_quote_orders_page' )
		);

		// Storage submenu is registered by SRF_Admin_Storage under this parent slug.
		// Settings submenu is registered by SR_Settings under this parent slug.
	}

	public static function enqueue_admin_assets( $hook ) {
		$allowed_hooks = array(
			'toplevel_page_' . self::PARENT_SLUG,
			'service-and-subscription_page_' . self::MATERIALS_SLUG,
			'service-and-subscription_page_' . self::PRINTERS_SLUG,
			'service-and-subscription_page_' . self::QUOTE_ORDERS_SLUG,
			'service-and-subscription_page_srf-settings',
			'service-and-subscription_page_srf-storage',
		);

		if ( ! in_array( $hook, $allowed_hooks, true ) ) {
			return;
		}

		// Inline styles remain in page renderers for now.
	}

	protected static function get_counts() {

		$total_requests = 0;
		$request_counts = wp_count_posts( 'service_request' );
		if ( $request_counts && isset( $request_counts->publish ) ) {
			$total_requests = (int) $request_counts->publish;
		}

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

		foreach ( $rows as $row ) {
			$key = (string) $row['status'];
			if ( isset( $status_counts[ $key ] ) ) {
				$status_counts[ $key ] = (int) $row['cnt'];
			}
		}

		$total_services = 0;
		$service_counts = wp_count_posts( 'sr_service' );
		if ( $service_counts && isset( $service_counts->publish ) ) {
			$total_services = (int) $service_counts->publish;
		}

		$total_storage = 0;
		$users = get_users(
			array(
				'fields'  => array( 'ID' ),
				'number'  => 500,
				'orderby' => 'ID',
				'order'   => 'ASC',
			)
		);

		foreach ( $users as $user ) {
			$total_storage += (int) get_user_meta( $user->ID, '_srf_storage_used_bytes', true );
		}

		$total_materials = 0;
		$total_printers  = 0;

		if ( class_exists( 'SRF_Quote_DB' ) ) {
			$db = new SRF_Quote_DB();

			$materials = $db->get_materials();
			$printers  = $db->get_printers();

			$total_materials = is_array( $materials ) ? count( $materials ) : 0;
			$total_printers  = is_array( $printers ) ? count( $printers ) : 0;
		}

		return array(
			'total_requests'  => $total_requests,
			'new'             => $status_counts['new'],
			'in_progress'     => $status_counts['in_progress'],
			'done'            => $status_counts['done'],
			'total_services'  => $total_services,
			'total_storage'   => max( 0, (int) $total_storage ),
			'total_materials' => $total_materials,
			'total_printers'  => $total_printers,
		);
	}

	protected static function get_request_upload_bytes( $request_id ) {
		$request_id = (int) $request_id;
		if ( $request_id <= 0 ) {
			return 0;
		}

		$file_ids = class_exists( 'SRF_Request_Files' ) ? SRF_Request_Files::get_files( $request_id ) : array();
		if ( empty( $file_ids ) ) {
			return 0;
		}

		$total = 0;
		foreach ( $file_ids as $file ) {
			if ( is_array( $file ) ) {
				if ( isset( $file['size'] ) ) {
					$total += max( 0, (int) $file['size'] );
					continue;
				}
				$attachment_id = ! empty( $file['attachment_id'] ) ? (int) $file['attachment_id'] : 0;
				$path          = $attachment_id ? get_attached_file( $attachment_id ) : '';
				if ( $path && file_exists( $path ) ) {
					$size = @filesize( $path );
					if ( false !== $size ) {
						$total += (int) $size;
					}
				}
				continue;
			}

			$attachment_id = (int) $file;
			$path          = $attachment_id ? get_attached_file( $attachment_id ) : '';
			if ( $path && file_exists( $path ) ) {
				$size = @filesize( $path );
				if ( false !== $size ) {
					$total += (int) $size;
				}
			}
		}

		return max( 0, (int) $total );
	}

	protected static function badge_html( $status ) {
		$status = (string) $status;
		if ( '' === $status ) {
			$status = 'new';
		}

		$label = ucfirst( str_replace( '_', ' ', $status ) );
		$class = 'srf-badge srf-badge--' . sanitize_html_class( $status );

		return '<span class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span>';
	}

	public static function render_dashboard() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$counts = self::get_counts();

		$requests_url    = admin_url( 'edit.php?post_type=service_request' );
		$new_request_url = admin_url( 'post-new.php?post_type=service_request' );
		$services_url    = admin_url( 'edit.php?post_type=sr_service' );
		$new_service_url = admin_url( 'post-new.php?post_type=sr_service' );
		$materials_url   = admin_url( 'admin.php?page=' . self::MATERIALS_SLUG );
		$printers_url    = admin_url( 'admin.php?page=' . self::PRINTERS_SLUG );
		$orders_url      = admin_url( 'admin.php?page=' . self::QUOTE_ORDERS_SLUG );
		$settings_url    = admin_url( 'admin.php?page=srf-settings' );

		$storage_url = '';
		if ( class_exists( 'SRF_Admin_Storage' ) ) {
			$storage_url = admin_url( 'admin.php?page=srf-storage' );
		}

		$recent = get_posts(
			array(
				'post_type'   => 'service_request',
				'post_status' => 'publish',
				'numberposts' => 10,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);
		?>
		<div class="wrap srf-dashboard">
			<style>
				.srf-dashboard .srf-header{
					display:flex; align-items:center; justify-content:space-between;
					margin:10px 0 18px; gap:14px; flex-wrap:wrap;
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
					padding:6px 12px;
				}
				.srf-dashboard .srf-grid{
					display:grid;
					grid-template-columns:repeat(8, minmax(0,1fr));
					gap:12px;
					margin:10px 0 18px;
				}
				@media (max-width: 1400px){
					.srf-dashboard .srf-grid{ grid-template-columns:repeat(4, minmax(0,1fr)); }
				}
				@media (max-width: 782px){
					.srf-dashboard .srf-grid{ grid-template-columns:repeat(2, minmax(0,1fr)); }
				}
				.srf-card{
					background:#fff;
					border:1px solid #e5e7eb;
					border-radius:12px;
					padding:14px 14px 12px;
					box-shadow:0 1px 2px rgba(16,24,40,.06);
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
					margin-top:16px;
				}
				.srf-section h2{
					margin:0 0 10px;
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
					padding:3px 8px;
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
							<?php esc_html_e( 'Manage services, requests, files, storage, and 3D quote resources in one place.', 'service-requests-form' ); ?>
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
					<a class="button" href="<?php echo esc_url( $materials_url ); ?>">
						<?php esc_html_e( 'Materials', 'service-requests-form' ); ?>
					</a>
					<a class="button" href="<?php echo esc_url( $printers_url ); ?>">
						<?php esc_html_e( 'Printers', 'service-requests-form' ); ?>
					</a>
					<a class="button" href="<?php echo esc_url( $orders_url ); ?>">
						<?php esc_html_e( 'Orders', 'service-requests-form' ); ?>
					</a>
					<a class="button" href="<?php echo esc_url( $settings_url ); ?>">
						<?php esc_html_e( 'Settings', 'service-requests-form' ); ?>
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
					<div class="srf-card__label"><?php esc_html_e( 'Materials', 'service-requests-form' ); ?></div>
					<div class="srf-card__value"><?php echo esc_html( number_format_i18n( $counts['total_materials'] ) ); ?></div>
					<div class="srf-card__hint srf-muted"><?php esc_html_e( '3D quote materials', 'service-requests-form' ); ?></div>
				</div>

				<div class="srf-card">
					<div class="srf-card__label"><?php esc_html_e( 'Printers', 'service-requests-form' ); ?></div>
					<div class="srf-card__value"><?php echo esc_html( number_format_i18n( $counts['total_printers'] ) ); ?></div>
					<div class="srf-card__hint srf-muted"><?php esc_html_e( '3D quote printers', 'service-requests-form' ); ?></div>
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
								<th><?php esc_html_e( 'Uploads', 'service-requests-form' ); ?></th>
								<th><?php esc_html_e( 'Open', 'service-requests-form' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $recent ) ) : ?>
								<tr>
									<td colspan="6" class="srf-muted"><?php esc_html_e( 'No requests yet.', 'service-requests-form' ); ?></td>
								</tr>
							<?php else : ?>
								<?php foreach ( $recent as $post_item ) : ?>
									<?php
									$request_id    = (int) $post_item->ID;
									$service_title = (string) get_post_meta( $request_id, '_sr_service_title', true );
									$status        = (string) get_post_meta( $request_id, '_sr_status', true );
									if ( '' === $status ) {
										$status = 'new';
									}
									$edit_url = get_edit_post_link( $request_id, 'raw' );
									?>
									<tr>
										<td>
											<strong><?php echo esc_html( $post_item->post_title ); ?></strong><br>
											<span class="srf-muted">#<?php echo esc_html( $request_id ); ?></span>
										</td>
										<td><?php echo esc_html( $service_title ); ?></td>
										<td><?php echo self::badge_html( $status ); ?></td>
										<td><?php echo esc_html( get_date_from_gmt( $post_item->post_date_gmt, 'Y-m-d H:i' ) ); ?></td>
										<td>
											<?php
											$uploads_bytes = self::get_request_upload_bytes( $request_id );
											echo $uploads_bytes > 0 ? esc_html( size_format( $uploads_bytes ) ) : '&mdash;';
											?>
										</td>
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

	public static function render_materials_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( class_exists( 'SRF_Admin_Materials' ) && method_exists( 'SRF_Admin_Materials', 'render_page' ) ) {
			SRF_Admin_Materials::render_page();
			return;
		}

		self::render_placeholder_page(
			__( 'Materials', 'service-requests-form' ),
			__( 'The menu is now connected. The next merge step will add the full materials CRUD page here using the new SRF quote database tables.', 'service-requests-form' )
		);
	}

	public static function render_printers_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( class_exists( 'SRF_Admin_Printers' ) && method_exists( 'SRF_Admin_Printers', 'render_page' ) ) {
			SRF_Admin_Printers::render_page();
			return;
		}

		self::render_placeholder_page(
			__( 'Printers', 'service-requests-form' ),
			__( 'The menu is now connected. The next merge step will add the full printers CRUD page here using the new SRF quote database tables.', 'service-requests-form' )
		);
	}

	public static function render_quote_orders_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$requests_url = admin_url( 'edit.php?post_type=service_request' );
		$settings_url = admin_url( 'admin.php?page=srf-settings' );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Orders', 'service-requests-form' ); ?></h1>
			<p><?php esc_html_e( 'During the merge, quote/order handling stays connected to the Service Requests workflow and WooCommerce My Account request area.', 'service-requests-form' ); ?></p>

			<div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;max-width:900px;">
				<p style="margin-top:0;">
					<?php esc_html_e( 'Use the existing Service Requests list as the current order/request management screen. The dedicated quote-order table integration can be merged after materials, printers, and pricing are wired into SRF.', 'service-requests-form' ); ?>
				</p>

				<p style="margin-bottom:0;">
					<a class="button button-primary" href="<?php echo esc_url( $requests_url ); ?>">
						<?php esc_html_e( 'Open Service Requests', 'service-requests-form' ); ?>
					</a>
					<a class="button" href="<?php echo esc_url( $settings_url ); ?>">
						<?php esc_html_e( 'Open Settings', 'service-requests-form' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php
	}

	protected static function render_placeholder_page( $title, $message ) {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $title ); ?></h1>
			<div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;max-width:900px;">
				<p style="margin:0;"><?php echo esc_html( $message ); ?></p>
			</div>
		</div>
		<?php
	}
}
