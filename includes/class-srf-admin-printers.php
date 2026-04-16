<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SRF_Admin_Printers' ) ) {

	class SRF_Admin_Printers {

		public static function init() {
			add_action( 'admin_post_srf_save_printer', array( __CLASS__, 'handle_save' ) );
			add_action( 'admin_post_srf_delete_printer', array( __CLASS__, 'handle_delete' ) );
		}

		protected static function get_db() {
			return class_exists( 'SRF_Quote_DB' ) ? new SRF_Quote_DB() : null;
		}

		protected static function get_page_url( $args = array() ) {
			$url = admin_url( 'admin.php?page=' . SRF_Admin_Menu::PRINTERS_SLUG );

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
						'text' => __( 'Printer created successfully.', 'service-requests-form' ),
					),
					'updated' => array(
						'type' => 'success',
						'text' => __( 'Printer updated successfully.', 'service-requests-form' ),
					),
					'deleted' => array(
						'type' => 'success',
						'text' => __( 'Printer deleted successfully.', 'service-requests-form' ),
					),
					'failed' => array(
						'type' => 'error',
						'text' => __( 'The printer could not be saved.', 'service-requests-form' ),
					),
					'deleted_failed' => array(
						'type' => 'error',
						'text' => __( 'The printer could not be deleted.', 'service-requests-form' ),
					),
				);

				if ( isset( $map[ $message ] ) ) {
					$notices[] = $map[ $message ];
				}
			}

			if ( isset( $_GET['error'] ) ) {
				$error = sanitize_key( wp_unslash( $_GET['error'] ) );

				$error_map = array(
					'missing_name' => __( 'Printer name is required.', 'service-requests-form' ),
					'bad_nonce'    => __( 'Security check failed. Please try again.', 'service-requests-form' ),
					'invalid_id'   => __( 'Invalid printer ID.', 'service-requests-form' ),
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

		protected static function sanitize_supported_materials( $value ) {
			$ids = array();

			if ( is_array( $value ) ) {
				foreach ( $value as $item ) {
					$id = absint( $item );
					if ( $id > 0 ) {
						$ids[] = $id;
					}
				}
			}

			$ids = array_values( array_unique( $ids ) );

			return wp_json_encode( $ids );
		}

		protected static function decode_supported_materials( $value ) {
			if ( empty( $value ) ) {
				return array();
			}

			$decoded = json_decode( (string) $value, true );

			if ( ! is_array( $decoded ) ) {
				return array();
			}

			return array_values(
				array_filter(
					array_map( 'absint', $decoded )
				)
			);
		}

		protected static function sanitize_payload( $input ) {
			return array(
				'name'                => sanitize_text_field( wp_unslash( $input['name'] ?? '' ) ),
				'brand'               => sanitize_text_field( wp_unslash( $input['brand'] ?? '' ) ),
				'model'               => sanitize_text_field( wp_unslash( $input['model'] ?? '' ) ),
				'technology'          => sanitize_text_field( wp_unslash( $input['technology'] ?? '' ) ),
				'build_volume_x'      => max( 0, (float) ( $input['build_volume_x'] ?? 0 ) ),
				'build_volume_y'      => max( 0, (float) ( $input['build_volume_y'] ?? 0 ) ),
				'build_volume_z'      => max( 0, (float) ( $input['build_volume_z'] ?? 0 ) ),
				'default_speed'       => max( 0, (float) ( $input['default_speed'] ?? 0 ) ),
				'hourly_cost'         => max( 0, (float) ( $input['hourly_cost'] ?? 0 ) ),
				'min_layer_height'    => max( 0, (float) ( $input['min_layer_height'] ?? 0 ) ),
				'max_layer_height'    => max( 0, (float) ( $input['max_layer_height'] ?? 0 ) ),
				'supported_materials' => self::sanitize_supported_materials( $input['supported_materials'] ?? array() ),
				'status'              => self::sanitize_status( $input['status'] ?? 'inactive' ),
			);
		}

		public static function handle_save() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to do that.', 'service-requests-form' ) );
			}

			check_admin_referer( 'srf_save_printer', 'srf_printer_nonce' );

			$db = self::get_db();
			if ( ! $db ) {
				wp_safe_redirect( self::get_page_url( array( 'message' => 'failed' ) ) );
				exit;
			}

			$printer_id = isset( $_POST['printer_id'] ) ? absint( $_POST['printer_id'] ) : 0;
			$data       = self::sanitize_payload( $_POST );

			if ( '' === $data['name'] ) {
				wp_safe_redirect( self::get_page_url( array(
					'action' => $printer_id ? 'edit' : null,
					'id'     => $printer_id ? $printer_id : null,
					'error'  => 'missing_name',
				) ) );
				exit;
			}

			if ( $printer_id > 0 ) {
				$result = $db->update_printer( $printer_id, $data );
				wp_safe_redirect( self::get_page_url( array(
					'message' => $result ? 'updated' : 'failed',
				) ) );
				exit;
			}

			$insert_id = $db->insert_printer( $data );

			wp_safe_redirect( self::get_page_url( array(
				'message' => $insert_id ? 'created' : 'failed',
			) ) );
			exit;
		}

		public static function handle_delete() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to do that.', 'service-requests-form' ) );
			}

			check_admin_referer( 'srf_delete_printer', 'srf_printer_delete_nonce' );

			$printer_id = isset( $_POST['printer_id'] ) ? absint( $_POST['printer_id'] ) : 0;
			if ( $printer_id <= 0 ) {
				wp_safe_redirect( self::get_page_url( array( 'error' => 'invalid_id' ) ) );
				exit;
			}

			$db = self::get_db();
			if ( ! $db ) {
				wp_safe_redirect( self::get_page_url( array( 'message' => 'deleted_failed' ) ) );
				exit;
			}

			$deleted = $db->delete_printer( $printer_id );

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
				echo '<div class="wrap"><h1>' . esc_html__( 'Printers', 'service-requests-form' ) . '</h1><div class="notice notice-error"><p>' . esc_html__( 'Quote database is not available.', 'service-requests-form' ) . '</p></div></div>';
				return;
			}

			$printers      = $db->get_printers();
			$material_rows = $db->get_materials();
			$material_map  = array();

			if ( is_array( $material_rows ) ) {
				foreach ( $material_rows as $material ) {
					$material_map[ (int) $material->id ] = $material->name;
				}
			}

			$edit_id = isset( $_GET['action'], $_GET['id'] ) && 'edit' === sanitize_key( wp_unslash( $_GET['action'] ) )
				? absint( $_GET['id'] )
				: 0;

			$edit_printer = null;
			if ( $edit_id > 0 ) {
				$edit_printer = $db->get_printer( $edit_id );
			}

			$selected_materials = $edit_printer ? self::decode_supported_materials( $edit_printer->supported_materials ) : array();

			$page_url = self::get_page_url();
			$notices  = self::get_notices();
			?>
			<div class="wrap srf-printers-page">
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
					.srf-admin-actions{margin-top:16px;display:flex;gap:10px;flex-wrap:wrap}
					.srf-admin-table{width:100%;border-collapse:collapse}
					.srf-admin-table th,.srf-admin-table td{padding:14px 12px;border-bottom:1px solid #eaecf0;text-align:left;vertical-align:top}
					.srf-admin-table th{background:#f9fafb;color:#667085;font-size:12px;text-transform:uppercase;letter-spacing:.04em}
					.srf-admin-table tbody tr:last-child td{border-bottom:none}
					.srf-status-pill{display:inline-flex;align-items:center;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:600}
					.srf-status-pill--active{background:#ecfdf3;color:#027a48}
					.srf-status-pill--inactive{background:#f2f4f7;color:#344054}
					.srf-row-actions{display:flex;gap:8px;flex-wrap:wrap}
					.srf-checkbox-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:6px}
					.srf-checkbox-item{display:flex;align-items:center;gap:8px;background:#f9fafb;border:1px solid #eaecf0;border-radius:10px;padding:8px 10px}
					.srf-checkbox-item input{margin:0}
					@media (max-width: 1000px){
						.srf-checkbox-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
					}
					@media (max-width: 900px){
						.srf-grid-cols{grid-template-columns:1fr}
					}
					@media (max-width: 640px){
						.srf-checkbox-grid{grid-template-columns:1fr}
					}
				</style>

				<div class="srf-admin-shell">
					<div class="srf-admin-header">
						<div>
							<h1><?php esc_html_e( 'Printers', 'service-requests-form' ); ?></h1>
							<p class="srf-admin-description"><?php esc_html_e( 'Define printer capabilities, operating cost, and supported materials for the 3D quote engine.', 'service-requests-form' ); ?></p>
						</div>

						<?php if ( $edit_printer ) : ?>
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
								$edit_printer
									? __( 'Edit Printer', 'service-requests-form' )
									: __( 'Add Printer', 'service-requests-form' )
							);
							?>
						</h2>

						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="srf_save_printer" />
							<input type="hidden" name="printer_id" value="<?php echo esc_attr( $edit_printer ? (int) $edit_printer->id : 0 ); ?>" />
							<?php wp_nonce_field( 'srf_save_printer', 'srf_printer_nonce' ); ?>

							<div class="srf-grid-cols">
								<div class="srf-input-row">
									<label for="srf_printer_name"><?php esc_html_e( 'Name', 'service-requests-form' ); ?></label>
									<input type="text" id="srf_printer_name" name="name" value="<?php echo esc_attr( $edit_printer->name ?? '' ); ?>" required />
								</div>

								<div class="srf-input-row">
									<label for="srf_printer_brand"><?php esc_html_e( 'Brand', 'service-requests-form' ); ?></label>
									<input type="text" id="srf_printer_brand" name="brand" value="<?php echo esc_attr( $edit_printer->brand ?? '' ); ?>" />
								</div>

								<div class="srf-input-row">
									<label for="srf_printer_model"><?php esc_html_e( 'Model', 'service-requests-form' ); ?></label>
									<input type="text" id="srf_printer_model" name="model" value="<?php echo esc_attr( $edit_printer->model ?? '' ); ?>" />
								</div>

								<div class="srf-input-row">
									<label for="srf_printer_technology"><?php esc_html_e( 'Technology', 'service-requests-form' ); ?></label>
									<input type="text" id="srf_printer_technology" name="technology" value="<?php echo esc_attr( $edit_printer->technology ?? '' ); ?>" placeholder="<?php esc_attr_e( 'FDM, SLA, SLS…', 'service-requests-form' ); ?>" />
								</div>

								<div class="srf-input-row">
									<label for="srf_printer_build_volume_x"><?php esc_html_e( 'Build volume X', 'service-requests-form' ); ?></label>
									<input type="number" min="0" step="0.01" id="srf_printer_build_volume_x" name="build_volume_x" value="<?php echo esc_attr( isset( $edit_printer->build_volume_x ) ? $edit_printer->build_volume_x : '' ); ?>" />
								</div>

								<div class="srf-input-row">
									<label for="srf_printer_build_volume_y"><?php esc_html_e( 'Build volume Y', 'service-requests-form' ); ?></label>
									<input type="number" min="0" step="0.01" id="srf_printer_build_volume_y" name="build_volume_y" value="<?php echo esc_attr( isset( $edit_printer->build_volume_y ) ? $edit_printer->build_volume_y : '' ); ?>" />
								</div>

								<div class="srf-input-row">
									<label for="srf_printer_build_volume_z"><?php esc_html_e( 'Build volume Z', 'service-requests-form' ); ?></label>
									<input type="number" min="0" step="0.01" id="srf_printer_build_volume_z" name="build_volume_z" value="<?php echo esc_attr( isset( $edit_printer->build_volume_z ) ? $edit_printer->build_volume_z : '' ); ?>" />
								</div>

								<div class="srf-input-row">
									<label for="srf_printer_default_speed"><?php esc_html_e( 'Default speed', 'service-requests-form' ); ?></label>
									<input type="number" min="0" step="0.01" id="srf_printer_default_speed" name="default_speed" value="<?php echo esc_attr( isset( $edit_printer->default_speed ) ? $edit_printer->default_speed : '' ); ?>" />
								</div>

								<div class="srf-input-row">
									<label for="srf_printer_hourly_cost"><?php esc_html_e( 'Hourly cost', 'service-requests-form' ); ?></label>
									<input type="number" min="0" step="0.01" id="srf_printer_hourly_cost" name="hourly_cost" value="<?php echo esc_attr( isset( $edit_printer->hourly_cost ) ? $edit_printer->hourly_cost : '' ); ?>" />
								</div>

								<div class="srf-input-row">
									<label for="srf_printer_min_layer_height"><?php esc_html_e( 'Min layer height', 'service-requests-form' ); ?></label>
									<input type="number" min="0" step="0.0001" id="srf_printer_min_layer_height" name="min_layer_height" value="<?php echo esc_attr( isset( $edit_printer->min_layer_height ) ? $edit_printer->min_layer_height : '' ); ?>" />
								</div>

								<div class="srf-input-row">
									<label for="srf_printer_max_layer_height"><?php esc_html_e( 'Max layer height', 'service-requests-form' ); ?></label>
									<input type="number" min="0" step="0.0001" id="srf_printer_max_layer_height" name="max_layer_height" value="<?php echo esc_attr( isset( $edit_printer->max_layer_height ) ? $edit_printer->max_layer_height : '' ); ?>" />
								</div>

								<div class="srf-input-row">
									<label for="srf_printer_status"><?php esc_html_e( 'Status', 'service-requests-form' ); ?></label>
									<select id="srf_printer_status" name="status">
										<option value="active" <?php selected( $edit_printer->status ?? 'active', 'active' ); ?>><?php esc_html_e( 'Active', 'service-requests-form' ); ?></option>
										<option value="inactive" <?php selected( $edit_printer->status ?? '', 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'service-requests-form' ); ?></option>
									</select>
								</div>
							</div>

							<div class="srf-input-row" style="margin-top:16px;">
								<label><?php esc_html_e( 'Supported materials', 'service-requests-form' ); ?></label>

								<?php if ( empty( $material_map ) ) : ?>
									<p><?php esc_html_e( 'No materials available yet. Add materials first.', 'service-requests-form' ); ?></p>
								<?php else : ?>
									<div class="srf-checkbox-grid">
										<?php foreach ( $material_map as $material_id => $material_name ) : ?>
											<label class="srf-checkbox-item">
												<input
													type="checkbox"
													name="supported_materials[]"
													value="<?php echo esc_attr( $material_id ); ?>"
													<?php checked( in_array( (int) $material_id, $selected_materials, true ) ); ?>
												/>
												<span><?php echo esc_html( $material_name ); ?></span>
											</label>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</div>

							<div class="srf-admin-actions">
								<button type="submit" class="button button-primary">
									<?php echo esc_html( $edit_printer ? __( 'Update Printer', 'service-requests-form' ) : __( 'Save Printer', 'service-requests-form' ) ); ?>
								</button>
							</div>
						</form>
					</div>

					<div class="srf-admin-card">
						<h2><?php esc_html_e( 'Printer catalogue', 'service-requests-form' ); ?></h2>

						<table class="srf-admin-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Name', 'service-requests-form' ); ?></th>
									<th><?php esc_html_e( 'Technology', 'service-requests-form' ); ?></th>
									<th><?php esc_html_e( 'Build volume', 'service-requests-form' ); ?></th>
									<th><?php esc_html_e( 'Hourly cost', 'service-requests-form' ); ?></th>
									<th><?php esc_html_e( 'Supported materials', 'service-requests-form' ); ?></th>
									<th><?php esc_html_e( 'Status', 'service-requests-form' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'service-requests-form' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php if ( empty( $printers ) ) : ?>
									<tr>
										<td colspan="7"><?php esc_html_e( 'No printers found yet.', 'service-requests-form' ); ?></td>
									</tr>
								<?php else : ?>
									<?php foreach ( $printers as $printer ) : ?>
										<?php
										$supported_ids   = self::decode_supported_materials( $printer->supported_materials );
										$supported_names = array();

										foreach ( $supported_ids as $mid ) {
											if ( isset( $material_map[ $mid ] ) ) {
												$supported_names[] = $material_map[ $mid ];
											}
										}

										$build_volume = trim(
											implode(
												' × ',
												array_filter(
													array(
														(string) $printer->build_volume_x,
														(string) $printer->build_volume_y,
														(string) $printer->build_volume_z,
													),
													static function( $v ) {
														return '' !== $v && '0' !== $v && '0.00' !== $v;
													}
												)
											)
										);
										?>
										<tr>
											<td>
												<strong><?php echo esc_html( $printer->name ); ?></strong>
												<?php if ( ! empty( $printer->brand ) || ! empty( $printer->model ) ) : ?>
													<br><span style="color:#667085;"><?php echo esc_html( trim( $printer->brand . ' ' . $printer->model ) ); ?></span>
												<?php endif; ?>
											</td>
											<td><?php echo esc_html( $printer->technology ?: '—' ); ?></td>
											<td><?php echo esc_html( $build_volume ?: '—' ); ?></td>
											<td><?php echo esc_html( '' !== (string) $printer->hourly_cost ? number_format_i18n( (float) $printer->hourly_cost, 2 ) : '—' ); ?></td>
											<td><?php echo esc_html( ! empty( $supported_names ) ? implode( ', ', $supported_names ) : '—' ); ?></td>
											<td>
												<span class="srf-status-pill srf-status-pill--<?php echo esc_attr( 'active' === $printer->status ? 'active' : 'inactive' ); ?>">
													<?php echo esc_html( ucfirst( (string) $printer->status ) ); ?>
												</span>
											</td>
											<td>
												<div class="srf-row-actions">
													<a class="button button-secondary" href="<?php echo esc_url( self::get_page_url( array(
														'action' => 'edit',
														'id'     => (int) $printer->id,
													) ) ); ?>">
														<?php esc_html_e( 'Edit', 'service-requests-form' ); ?>
													</a>

													<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
														<input type="hidden" name="action" value="srf_delete_printer" />
														<input type="hidden" name="printer_id" value="<?php echo esc_attr( (int) $printer->id ); ?>" />
														<?php wp_nonce_field( 'srf_delete_printer', 'srf_printer_delete_nonce' ); ?>
														<button type="submit" class="button button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Delete this printer?', 'service-requests-form' ) ); ?>');">
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