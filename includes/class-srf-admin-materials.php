<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SRF_Admin_Materials' ) ) {

	class SRF_Admin_Materials {

		public static function init() {
			add_action( 'admin_post_srf_save_material', array( __CLASS__, 'handle_save' ) );
			add_action( 'admin_post_srf_delete_material', array( __CLASS__, 'handle_delete' ) );
		}

		protected static function get_db() {
			return class_exists( 'SRF_Quote_DB' ) ? new SRF_Quote_DB() : null;
		}

		protected static function get_page_url( $args = array() ) {
			$url = admin_url( 'admin.php?page=' . SRF_Admin_Menu::MATERIALS_SLUG );

			if ( ! empty( $args ) ) {
				$url = add_query_arg( $args, $url );
			}

			return $url;
		}

		protected static function get_notices() {
			$notices = array();

			if ( isset( $_GET['message'] ) ) {
				$message = sanitize_key( wp_unslash( $_GET['message'] ) );

				$map = array(
					'created' => array(
						'type' => 'success',
						'text' => __( 'Material created successfully.', 'service-requests-form' ),
					),
					'updated' => array(
						'type' => 'success',
						'text' => __( 'Material updated successfully.', 'service-requests-form' ),
					),
					'deleted' => array(
						'type' => 'success',
						'text' => __( 'Material deleted successfully.', 'service-requests-form' ),
					),
					'failed' => array(
						'type' => 'error',
						'text' => __( 'The material could not be saved.', 'service-requests-form' ),
					),
					'deleted_failed' => array(
						'type' => 'error',
						'text' => __( 'The material could not be deleted.', 'service-requests-form' ),
					),
				);

				if ( isset( $map[ $message ] ) ) {
					$notices[] = $map[ $message ];
				}
			}

			if ( isset( $_GET['error'] ) ) {
				$error = sanitize_key( wp_unslash( $_GET['error'] ) );

				$error_map = array(
					'missing_name' => __( 'Material name is required.', 'service-requests-form' ),
					'bad_nonce'    => __( 'Security check failed. Please try again.', 'service-requests-form' ),
					'invalid_id'   => __( 'Invalid material ID.', 'service-requests-form' ),
					'duplicate'    => __( 'A material with the same slug already exists.', 'service-requests-form' ),
				);

				if ( isset( $error_map[ $error ] ) ) {
					$notices[] = array(
						'type' => 'error',
						'text' => $error_map[ $error ],
					);
				}
			}

			return $notices;
		}

		protected static function sanitize_status( $value ) {
			$value = sanitize_key( wp_unslash( (string) $value ) );
			return in_array( $value, array( 'active', 'inactive' ), true ) ? $value : 'inactive';
		}

		protected static function sanitize_payload( $input ) {
			$name = sanitize_text_field( wp_unslash( $input['name'] ?? '' ) );
			$slug = sanitize_title( wp_unslash( $input['slug'] ?? '' ) );

			if ( '' === $slug && '' !== $name ) {
				$slug = sanitize_title( $name );
			}

			return array(
				'name'                   => $name,
				'slug'                   => $slug,
				'description'            => sanitize_textarea_field( wp_unslash( $input['description'] ?? '' ) ),
				'price_per_gram'         => max( 0, (float) ( $input['price_per_gram'] ?? 0 ) ),
				'price_per_cm3'          => max( 0, (float) ( $input['price_per_cm3'] ?? 0 ) ),
				'density'                => max( 0, (float) ( $input['density'] ?? 0 ) ),
				'machine_time_factor'    => max( 0, (float) ( $input['machine_time_factor'] ?? 1 ) ),
				'surface_quality_factor' => max( 0, (float) ( $input['surface_quality_factor'] ?? 1 ) ),
				'wastage_factor'         => max( 0, (float) ( $input['wastage_factor'] ?? 1 ) ),
				'color_availability'     => sanitize_text_field( wp_unslash( $input['color_availability'] ?? '' ) ),
				'status'                 => self::sanitize_status( $input['status'] ?? 'inactive' ),
			);
		}

		public static function handle_save() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to do that.', 'service-requests-form' ) );
			}

			check_admin_referer( 'srf_save_material', 'srf_material_nonce' );

			$db = self::get_db();
			if ( ! $db ) {
				wp_safe_redirect( self::get_page_url( array( 'message' => 'failed' ) ) );
				exit;
			}

			$material_id = isset( $_POST['material_id'] ) ? absint( $_POST['material_id'] ) : 0;
			$data        = self::sanitize_payload( $_POST );

			if ( '' === $data['name'] ) {
				wp_safe_redirect( self::get_page_url( array(
					'action' => $material_id ? 'edit' : null,
					'id'     => $material_id ? $material_id : null,
					'error'  => 'missing_name',
				) ) );
				exit;
			}

			if ( '' === $data['slug'] ) {
				$data['slug'] = sanitize_title( $data['name'] );
			}

			$existing = $db->get_material_by_slug( $data['slug'] );
			if ( $existing && (int) $existing->id !== $material_id ) {
				wp_safe_redirect( self::get_page_url( array(
					'action' => $material_id ? 'edit' : null,
					'id'     => $material_id ? $material_id : null,
					'error'  => 'duplicate',
				) ) );
				exit;
			}

			if ( $material_id > 0 ) {
				$result = $db->update_material( $material_id, $data );
				wp_safe_redirect( self::get_page_url( array(
					'message' => $result ? 'updated' : 'failed',
				) ) );
				exit;
			}

			$insert_id = $db->insert_material( $data );

			wp_safe_redirect( self::get_page_url( array(
				'message' => $insert_id ? 'created' : 'failed',
			) ) );
			exit;
		}

		public static function handle_delete() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to do that.', 'service-requests-form' ) );
			}

			check_admin_referer( 'srf_delete_material', 'srf_material_delete_nonce' );

			$material_id = isset( $_POST['material_id'] ) ? absint( $_POST['material_id'] ) : 0;
			if ( $material_id <= 0 ) {
				wp_safe_redirect( self::get_page_url( array( 'error' => 'invalid_id' ) ) );
				exit;
			}

			$db = self::get_db();
			if ( ! $db ) {
				wp_safe_redirect( self::get_page_url( array( 'message' => 'deleted_failed' ) ) );
				exit;
			}

			$deleted = $db->delete_material( $material_id );

			wp_safe_redirect( self::get_page_url( array(
				'message' => $deleted ? 'deleted' : 'deleted_failed',
			) ) );
			exit;
		}

		public static function render_page() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$db = self::get_db();
			if ( ! $db ) {
				echo '<div class="wrap"><h1>' . esc_html__( 'Materials', 'service-requests-form' ) . '</h1><div class="notice notice-error"><p>' . esc_html__( 'Quote database is not available.', 'service-requests-form' ) . '</p></div></div>';
				return;
			}

			$materials = $db->get_materials();
			$edit_id   = isset( $_GET['action'], $_GET['id'] ) && 'edit' === sanitize_key( wp_unslash( $_GET['action'] ) )
				? absint( $_GET['id'] )
				: 0;

			$edit_material = null;
			if ( $edit_id > 0 ) {
				$edit_material = $db->get_material( $edit_id );
			}

			$page_url = self::get_page_url();
			$notices  = self::get_notices();
			?>
			<div class="wrap srf-materials-page">
				<style>
					.srf-admin-shell{max-width:1280px}
					.srf-admin-header{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin:8px 0 18px}
					.srf-admin-header h1{margin:0 0 6px}
					.srf-admin-description{margin:0;color:#667085}
					.srf-admin-card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:20px;margin-bottom:20px;box-shadow:0 1px 2px rgba(16,24,40,.04)}
					.srf-admin-card h2{margin:0 0 18px}
					.srf-grid-cols{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
					.srf-input-row label{display:block;font-weight:600;color:#344054;margin-bottom:6px}
					.srf-input-row input[type="text"],
					.srf-input-row input[type="number"],
					.srf-input-row textarea,
					.srf-input-row select{width:100%;max-width:none}
					.srf-input-row textarea{min-height:120px;resize:vertical}
					.srf-admin-actions{margin-top:16px;display:flex;gap:10px;flex-wrap:wrap}
					.srf-admin-table{width:100%;border-collapse:collapse}
					.srf-admin-table th,.srf-admin-table td{padding:14px 12px;border-bottom:1px solid #eaecf0;text-align:left;vertical-align:top}
					.srf-admin-table th{background:#f9fafb;color:#667085;font-size:12px;text-transform:uppercase;letter-spacing:.04em}
					.srf-admin-table tbody tr:last-child td{border-bottom:none}
					.srf-status-pill{display:inline-flex;align-items:center;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:600}
					.srf-status-pill--active{background:#ecfdf3;color:#027a48}
					.srf-status-pill--inactive{background:#f2f4f7;color:#344054}
					.srf-row-actions{display:flex;gap:8px;flex-wrap:wrap}
					@media (max-width: 900px){
						.srf-grid-cols{grid-template-columns:1fr}
					}
				</style>

				<div class="srf-admin-shell">
					<div class="srf-admin-header">
						<div>
							<h1><?php esc_html_e( 'Materials', 'service-requests-form' ); ?></h1>
							<p class="srf-admin-description"><?php esc_html_e( 'Define materials, pricing values, and factors that will feed the 3D quote engine.', 'service-requests-form' ); ?></p>
						</div>

						<?php if ( $edit_material ) : ?>
							<a class="button" href="<?php echo esc_url( $page_url ); ?>">
								<?php esc_html_e( 'Cancel Editing', 'service-requests-form' ); ?>
							</a>
						<?php endif; ?>
					</div>

					<?php foreach ( $notices as $notice ) : ?>
						<div class="notice notice-<?php echo esc_attr( 'success' === $notice['type'] ? 'success' : 'error' ); ?> is-dismissible">
							<p><?php echo esc_html( $notice['text'] ); ?></p>
						</div>
					<?php endforeach; ?>

					<div class="srf-admin-card">
						<h2>
							<?php
							echo esc_html(
								$edit_material
									? __( 'Edit Material', 'service-requests-form' )
									: __( 'Add Material', 'service-requests-form' )
							);
							?>
						</h2>

						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="srf_save_material" />
							<input type="hidden" name="material_id" value="<?php echo esc_attr( $edit_material ? (int) $edit_material->id : 0 ); ?>" />
							<?php wp_nonce_field( 'srf_save_material', 'srf_material_nonce' ); ?>

							<div class="srf-grid-cols">
								<div class="srf-input-row">
									<label for="srf_material_name"><?php esc_html_e( 'Name', 'service-requests-form' ); ?></label>
									<input type="text" id="srf_material_name" name="name" value="<?php echo esc_attr( $edit_material->name ?? '' ); ?>" required />
								</div>

								<div class="srf-input-row">
									<label for="srf_material_slug"><?php esc_html_e( 'Slug', 'service-requests-form' ); ?></label>
									<input type="text" id="srf_material_slug" name="slug" value="<?php echo esc_attr( $edit_material->slug ?? '' ); ?>" />
								</div>

								<div class="srf-input-row">
									<label for="srf_material_description"><?php esc_html_e( 'Description', 'service-requests-form' ); ?></label>
									<textarea id="srf_material_description" name="description"><?php echo esc_textarea( $edit_material->description ?? '' ); ?></textarea>
								</div>

								<div class="srf-input-row">
									<label for="srf_material_price_per_gram"><?php esc_html_e( 'Price per gram', 'service-requests-form' ); ?></label>
									<input type="number" min="0" step="0.0001" id="srf_material_price_per_gram" name="price_per_gram" value="<?php echo esc_attr( isset( $edit_material->price_per_gram ) ? $edit_material->price_per_gram : '' ); ?>" />
								</div>

								<div class="srf-input-row">
									<label for="srf_material_price_per_cm3"><?php esc_html_e( 'Price per cm³', 'service-requests-form' ); ?></label>
									<input type="number" min="0" step="0.0001" id="srf_material_price_per_cm3" name="price_per_cm3" value="<?php echo esc_attr( isset( $edit_material->price_per_cm3 ) ? $edit_material->price_per_cm3 : '' ); ?>" />
								</div>

								<div class="srf-input-row">
									<label for="srf_material_density"><?php esc_html_e( 'Density', 'service-requests-form' ); ?></label>
									<input type="number" min="0" step="0.0001" id="srf_material_density" name="density" value="<?php echo esc_attr( isset( $edit_material->density ) ? $edit_material->density : '' ); ?>" />
								</div>

								<div class="srf-input-row">
									<label for="srf_material_machine_time_factor"><?php esc_html_e( 'Machine time factor', 'service-requests-form' ); ?></label>
									<input type="number" min="0" step="0.01" id="srf_material_machine_time_factor" name="machine_time_factor" value="<?php echo esc_attr( isset( $edit_material->machine_time_factor ) ? $edit_material->machine_time_factor : '1' ); ?>" />
								</div>

								<div class="srf-input-row">
									<label for="srf_material_surface_quality_factor"><?php esc_html_e( 'Surface quality factor', 'service-requests-form' ); ?></label>
									<input type="number" min="0" step="0.01" id="srf_material_surface_quality_factor" name="surface_quality_factor" value="<?php echo esc_attr( isset( $edit_material->surface_quality_factor ) ? $edit_material->surface_quality_factor : '1' ); ?>" />
								</div>

								<div class="srf-input-row">
									<label for="srf_material_wastage_factor"><?php esc_html_e( 'Wastage factor', 'service-requests-form' ); ?></label>
									<input type="number" min="0" step="0.01" id="srf_material_wastage_factor" name="wastage_factor" value="<?php echo esc_attr( isset( $edit_material->wastage_factor ) ? $edit_material->wastage_factor : '1' ); ?>" />
								</div>

								<div class="srf-input-row">
									<label for="srf_material_color_availability"><?php esc_html_e( 'Available colors', 'service-requests-form' ); ?></label>
									<input type="text" id="srf_material_color_availability" name="color_availability" value="<?php echo esc_attr( $edit_material->color_availability ?? '' ); ?>" />
								</div>

								<div class="srf-input-row">
									<label for="srf_material_status"><?php esc_html_e( 'Status', 'service-requests-form' ); ?></label>
									<select id="srf_material_status" name="status">
										<option value="active" <?php selected( $edit_material->status ?? 'active', 'active' ); ?>><?php esc_html_e( 'Active', 'service-requests-form' ); ?></option>
										<option value="inactive" <?php selected( $edit_material->status ?? '', 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'service-requests-form' ); ?></option>
									</select>
								</div>
							</div>

							<div class="srf-admin-actions">
								<button type="submit" class="button button-primary">
									<?php echo esc_html( $edit_material ? __( 'Update Material', 'service-requests-form' ) : __( 'Save Material', 'service-requests-form' ) ); ?>
								</button>
							</div>
						</form>
					</div>

					<div class="srf-admin-card">
						<h2><?php esc_html_e( 'Material catalogue', 'service-requests-form' ); ?></h2>

						<table class="srf-admin-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Name', 'service-requests-form' ); ?></th>
									<th><?php esc_html_e( 'Slug', 'service-requests-form' ); ?></th>
									<th><?php esc_html_e( 'Price/gram', 'service-requests-form' ); ?></th>
									<th><?php esc_html_e( 'Price/cm³', 'service-requests-form' ); ?></th>
									<th><?php esc_html_e( 'Status', 'service-requests-form' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'service-requests-form' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php if ( empty( $materials ) ) : ?>
									<tr>
										<td colspan="6"><?php esc_html_e( 'No materials found yet.', 'service-requests-form' ); ?></td>
									</tr>
								<?php else : ?>
									<?php foreach ( $materials as $material ) : ?>
										<tr>
											<td><?php echo esc_html( $material->name ); ?></td>
											<td><?php echo esc_html( $material->slug ); ?></td>
											<td><?php echo esc_html( number_format_i18n( (float) $material->price_per_gram, 4 ) ); ?></td>
											<td><?php echo esc_html( number_format_i18n( (float) $material->price_per_cm3, 4 ) ); ?></td>
											<td>
												<span class="srf-status-pill srf-status-pill--<?php echo esc_attr( 'active' === $material->status ? 'active' : 'inactive' ); ?>">
													<?php echo esc_html( ucfirst( (string) $material->status ) ); ?>
												</span>
											</td>
											<td>
												<div class="srf-row-actions">
													<a class="button button-secondary" href="<?php echo esc_url( self::get_page_url( array(
														'action' => 'edit',
														'id'     => (int) $material->id,
													) ) ); ?>">
														<?php esc_html_e( 'Edit', 'service-requests-form' ); ?>
													</a>

													<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
														<input type="hidden" name="action" value="srf_delete_material" />
														<input type="hidden" name="material_id" value="<?php echo esc_attr( (int) $material->id ); ?>" />
														<?php wp_nonce_field( 'srf_delete_material', 'srf_material_delete_nonce' ); ?>
														<button type="submit" class="button button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Delete this material?', 'service-requests-form' ) ); ?>');">
															<?php esc_html_e( 'Delete', 'service-requests-form' ); ?>
														</button>
													</form>
												</div>
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
}