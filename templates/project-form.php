<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $errors ) || ! is_array( $errors ) ) {
	$errors = array();
}

if ( ! isset( $old_data ) || ! is_array( $old_data ) ) {
	$old_data = array();
}

$old = function( $key, $default = '' ) use ( $old_data ) {
	return isset( $old_data[ $key ] ) ? $old_data[ $key ] : $default;
};

$dashboard_url   = isset( $dashboard_url ) ? (string) $dashboard_url : '';
$upload_limit    = isset( $upload_limit ) ? (string) $upload_limit : '1 GB';
$is_business     = ! empty( $is_business );
$allowed_formats = isset( $allowed_formats ) ? (string) $allowed_formats : '';
$request_uri     = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
$current_url     = esc_url_raw( home_url( $request_uri ) );
$my_account_url  = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url();
$google_error    = isset( $_GET['srf_google_error'] ) ? sanitize_key( wp_unslash( $_GET['srf_google_error'] ) ) : '';
$materials       = isset( $materials ) && is_array( $materials ) ? $materials : array();
$printers        = isset( $printers ) && is_array( $printers ) ? $printers : array();
$service_profiles_data = isset( $service_profiles_data ) && is_array( $service_profiles_data ) ? $service_profiles_data : array();

$quote_settings  = isset( $quote_settings ) && is_array( $quote_settings ) ? $quote_settings : array();

$currency_symbol = isset( $quote_settings['currency_symbol'] ) ? (string) $quote_settings['currency_symbol'] : '€';
$tax_rate        = isset( $quote_settings['tax_rate'] ) ? (float) $quote_settings['tax_rate'] : 0;
$service_fee     = isset( $quote_settings['service_fee'] ) ? (float) $quote_settings['service_fee'] : 5;
$setup_fee       = isset( $quote_settings['setup_fee'] ) ? (float) $quote_settings['setup_fee'] : 0;
$profit_margin   = isset( $quote_settings['profit_margin'] ) ? (float) $quote_settings['profit_margin'] : 20;


$google_error_map = array(
	'google_disabled'        => __( 'Google login is currently unavailable.', 'service-requests-form' ),
	'google_missing_code'    => __( 'Google login was canceled or incomplete.', 'service-requests-form' ),
	'google_invalid_state'   => __( 'Google login security validation failed. Please try again.', 'service-requests-form' ),
	'google_token_failed'    => __( 'Could not complete Google login. Please try again.', 'service-requests-form' ),
	'google_token_missing'   => __( 'Could not verify your Google account. Please try again.', 'service-requests-form' ),
	'google_userinfo_failed' => __( 'Could not fetch your Google profile. Please try again.', 'service-requests-form' ),
	'google_profile_invalid' => __( 'Google account email is missing or not verified.', 'service-requests-form' ),
	'google_user_failed'     => __( 'Could not create or sign in your account. Please try again.', 'service-requests-form' ),
);
?>

<div class="srf-project-wrapper">

	<?php if ( ! empty( $errors ) ) : ?>
		<div class="srf-form__errors">
			<ul>
				<?php foreach ( $errors as $err ) : ?>
					<li><?php echo esc_html( $err ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $success ) ) : ?>
		<div class="srf-project-success" data-srf-project-success data-dashboard-url="<?php echo esc_url( $dashboard_url ); ?>">
			<div class="srf-project-success__fireworks" aria-hidden="true">
				<span></span><span></span><span></span><span></span><span></span><span></span>
			</div>

			<div class="srf-project-success__box">
				<h2><?php esc_html_e( 'Submission confirmed', 'service-requests-form' ); ?></h2>
				<p><?php esc_html_e( 'Your files were uploaded successfully and your request has been received.', 'service-requests-form' ); ?></p>

				<div class="srf-project-final-note">
					<p><?php esc_html_e( 'Our team works Monday to Friday, 9:00am - 15:00pm.', 'service-requests-form' ); ?></p>
					<p><?php esc_html_e( 'Your request will be processed as soon as possible, within up to 3 working days.', 'service-requests-form' ); ?></p>
					<p><?php esc_html_e( 'We will contact you using the information in your user profile. You can also check your request status in your dashboard.', 'service-requests-form' ); ?></p>
				</div>
			</div>
		</div>
	<?php else : ?>

	<form class="srf-form srf-project-form" method="post" enctype="multipart/form-data" data-srf-project-form>
		<div data-srf-service-profiles-json style="display:none"><?php echo wp_json_encode( $service_profiles_data ); ?></div>
		<div class="srf-project-steps">
			<div class="srf-project-step is-active" data-step="1">1. Project Title</div>
			<div class="srf-project-step" data-step="2">2. Terms & Upload 3D Model</div>
			<div class="srf-project-step" data-step="3">3. Confirmed</div>
		</div>

		<div class="srf-project-panel srf-project-panel--step1 is-active" data-srf-step-panel="1">
			<div class="srf-project-grid">
				<div class="srf-project-main">
					<h2 class="srf-project-panel__title"><?php esc_html_e( 'Project Title', 'service-requests-form' ); ?></h2>
					<p class="srf-project-panel__intro"><?php esc_html_e( 'Enter the project name and optional details so our team can understand your request quickly.', 'service-requests-form' ); ?></p>

					<div class="srf-form__field">
						<label for="srf-project-title">
							<?php esc_html_e( 'Project title', 'service-requests-form' ); ?> <span class="srf-required">*</span>
						</label>
						<input
							type="text"
							id="srf-project-title"
							name="srf_project_title"
							value="<?php echo esc_attr( $old( 'title' ) ); ?>"
							required
						/>
					</div>

					<div class="srf-form__field">
						<label for="srf-project-description">
							<?php esc_html_e( 'Description', 'service-requests-form' ); ?>
						</label>
						<textarea
							id="srf-project-description"
							name="srf_project_description"
							rows="7"
						><?php echo esc_textarea( $old( 'description' ) ); ?></textarea>
					</div>
				</div>

				<div class="srf-project-auth">
					<!-- Step 1 auth panel: local login + optional Google login/register -->
					<?php if ( is_user_logged_in() ) : ?>
						<div class="srf-project-auth__box srf-project-auth__box--loggedin" data-srf-auth-state="logged-in">
							<h3><?php esc_html_e( 'Account verified', 'service-requests-form' ); ?></h3>
							<p><?php esc_html_e( 'You are logged in and can continue to the next step.', 'service-requests-form' ); ?></p>
						</div>
					<?php else : ?>
						<div class="srf-project-auth__box" data-srf-auth-state="guest">
							<h3><?php esc_html_e( 'Sign in to continue', 'service-requests-form' ); ?></h3>
							<p><?php esc_html_e( 'Use a simple login or continue directly with Google.', 'service-requests-form' ); ?></p>

							<?php if ( $google_error && isset( $google_error_map[ $google_error ] ) ) : ?>
								<div class="srf-project-auth__notice"><?php echo esc_html( $google_error_map[ $google_error ] ); ?></div>
							<?php endif; ?>

							<div class="srf-project-auth__form srf-project-auth__form--login">
								<?php
								wp_login_form(
									array(
										'echo'           => true,
										'redirect'       => esc_url( remove_query_arg( 'srf_google_error', $current_url ) ),
										'form_id'        => 'srf-project-login-form',
										'label_username' => __( 'Email or username', 'service-requests-form' ),
										'label_password' => __( 'Password', 'service-requests-form' ),
										'label_remember' => __( 'Remember me', 'service-requests-form' ),
										'label_log_in'   => __( 'Login', 'service-requests-form' ),
										'remember'       => true,
									)
								);
								?>
							</div>

							<?php if ( class_exists( 'SRF_Google_Auth' ) && SRF_Google_Auth::is_enabled() ) : ?>
								<div class="srf-project-auth__divider"><span><?php esc_html_e( 'or', 'service-requests-form' ); ?></span></div>
								<div class="srf-project-auth__google-actions">
									<?php
									echo SRF_Google_Auth::render_google_button( $current_url, 'login', __( 'Continue with Google', 'service-requests-form' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									echo SRF_Google_Auth::render_google_button( $current_url, 'register', __( 'Register with Google', 'service-requests-form' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									?>
								</div>
							<?php endif; ?>

							<div class="srf-project-auth__register-link">
								<a href="<?php echo esc_url( $my_account_url ); ?>"><?php esc_html_e( 'Visit registration form', 'service-requests-form' ); ?></a>
							</div>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<div class="srf-form__actions srf-form__actions--project-step1">
				<button type="button" class="srf-button" data-srf-next-step="1">
					<span class="srf-button__label"><?php esc_html_e( 'Next', 'service-requests-form' ); ?></span>
				</button>
			</div>
		</div>

		<div class="srf-project-panel srf-project-panel--step2" data-srf-step-panel="2">
			<h2 class="srf-project-panel__title"><?php esc_html_e( 'Terms & Upload 3D Model', 'service-requests-form' ); ?></h2>
			<p class="srf-project-panel__intro"><?php esc_html_e( 'Accept the terms and upload your 3D files before submitting the request.', 'service-requests-form' ); ?></p>

			<div class="srf-form__field srf-form__field--checkbox srf-project-check-field srf-project-card">
				<label class="srf-project-checkbox-label" for="srf-terms">
					<input type="checkbox" id="srf-terms" name="srf_terms" value="1" <?php checked( $old( 'terms' ), '1' ); ?> required />
					<span><?php esc_html_e( 'I accept the Terms & Conditions.', 'service-requests-form' ); ?> <span class="srf-required">*</span></span>
				</label>
			</div>

			<div class="srf-form__field srf-project-upload-field srf-project-card">
				<label for="srf-files">
					<?php esc_html_e( 'Upload 3D model file(s)', 'service-requests-form' ); ?> <span class="srf-required">*</span>
				</label>

				<div class="srf-project-file-input-wrap">
					<input type="file" id="srf-files" name="srf_files[]" multiple required />
				</div>

				<small class="srf-field__help srf-project-help">
					<?php
					echo esc_html(
						sprintf(
							__( 'Maximum upload size: %s', 'service-requests-form' ),
							$upload_limit
						)
					);
					?>
				</small>

				<?php if ( $allowed_formats !== '' ) : ?>
					<small class="srf-field__help srf-project-help">
						<?php
						echo esc_html(
							sprintf(
								__( 'Accepted file formats: %s', 'service-requests-form' ),
								$allowed_formats
							)
						);
						?>
					</small>
				<?php endif; ?>

				<div class="srf-project-workspace" data-srf-project-workspace>
					<div class="srf-project-workspace__viewer">
						<div class="srf-3d-viewer srf-project-card" data-srf-3d-viewer>
							<div class="srf-3d-viewer__header">
								<h3 class="srf-3d-viewer__title"><?php esc_html_e( '3D Preview', 'service-requests-form' ); ?></h3>
								<p class="srf-3d-viewer__intro"><?php esc_html_e( 'After selecting a supported 3D file, the preview will appear here.', 'service-requests-form' ); ?></p>
							</div>

							<div class="srf-3d-viewer__canvas-wrap" aria-live="polite">
								<canvas
									class="srf-3d-viewer__canvas"
									role="img"
									aria-label="<?php esc_attr_e( '3D model preview canvas', 'service-requests-form' ); ?>"
								></canvas>
								<div class="srf-3d-viewer__placeholder" data-srf-3d-placeholder>
									<?php esc_html_e( 'No model loaded yet.', 'service-requests-form' ); ?>
								</div>
							</div>

							<div class="srf-3d-viewer__toolbar">
								<div class="srf-3d-viewer__status" data-srf-3d-status data-state="info">
									<?php esc_html_e( 'Viewer ready. Upload an STL or OBJ file to preview it.', 'service-requests-form' ); ?>
								</div>

								<div class="srf-3d-viewer__controls" aria-label="<?php esc_attr_e( '3D viewer controls', 'service-requests-form' ); ?>">
									<button type="button" class="srf-button srf-button--secondary srf-3d-viewer__button" data-action="zoom-in"><?php esc_html_e( 'Zoom In', 'service-requests-form' ); ?></button>
									<button type="button" class="srf-button srf-button--secondary srf-3d-viewer__button" data-action="zoom-out"><?php esc_html_e( 'Zoom Out', 'service-requests-form' ); ?></button>
									<button type="button" class="srf-button srf-button--secondary srf-3d-viewer__button" data-action="view-front"><?php esc_html_e( 'Front', 'service-requests-form' ); ?></button>
									<button type="button" class="srf-button srf-button--secondary srf-3d-viewer__button" data-action="view-left"><?php esc_html_e( 'Left', 'service-requests-form' ); ?></button>
									<button type="button" class="srf-button srf-button--secondary srf-3d-viewer__button" data-action="view-right"><?php esc_html_e( 'Right', 'service-requests-form' ); ?></button>
									<button type="button" class="srf-button srf-button--secondary srf-3d-viewer__button" data-action="view-top"><?php esc_html_e( 'Top', 'service-requests-form' ); ?></button>
									<button type="button" class="srf-button srf-button--secondary srf-3d-viewer__button" data-action="view-iso"><?php esc_html_e( 'Iso', 'service-requests-form' ); ?></button>
									<button type="button" class="srf-button srf-button--secondary srf-3d-viewer__button" data-action="fit-view"><?php esc_html_e( 'Fit View', 'service-requests-form' ); ?></button>
									<button type="button" class="srf-button srf-button--secondary srf-3d-viewer__button" data-action="reset-view"><?php esc_html_e( 'Reset View', 'service-requests-form' ); ?></button>
								</div>
							</div>

							<div class="srf-3d-viewer__meta">
								<div class="srf-3d-viewer__meta-item">
									<span class="srf-3d-viewer__meta-label"><?php esc_html_e( 'File', 'service-requests-form' ); ?></span>
									<strong data-field="filename">—</strong>
								</div>
								<div class="srf-3d-viewer__meta-item">
									<span class="srf-3d-viewer__meta-label"><?php esc_html_e( 'Format', 'service-requests-form' ); ?></span>
									<strong data-field="format">—</strong>
								</div>
								<div class="srf-3d-viewer__meta-item">
									<span class="srf-3d-viewer__meta-label"><?php esc_html_e( 'Triangles', 'service-requests-form' ); ?></span>
									<strong data-field="triangles">—</strong>
								</div>
								<div class="srf-3d-viewer__meta-item">
									<span class="srf-3d-viewer__meta-label"><?php esc_html_e( 'Bounds', 'service-requests-form' ); ?></span>
									<strong data-field="bounds">—</strong>
								</div>
							</div>
						</div>
					</div>

					<aside class="srf-project-workspace__sidebar">
						<div class="srf-project-quote-options srf-project-card" data-srf-quote-options>
							<div class="srf-project-quote-options__header">
								<h3 class="srf-project-quote-options__title"><?php esc_html_e( 'Print settings', 'service-requests-form' ); ?></h3>
								<p class="srf-project-quote-options__intro"><?php esc_html_e( 'Choose the material, printer, and print parameters for this 3D request.', 'service-requests-form' ); ?></p>
							</div>

							<div class="srf-project-quote-options__grid">
								<div class="srf-form__field">
									<label for="srf-material-id">
										<?php esc_html_e( 'Material', 'service-requests-form' ); ?> <span class="srf-required">*</span>
									</label>
									<select id="srf-material-id" name="srf_material_id" required>
										<option value=""><?php esc_html_e( 'Select material', 'service-requests-form' ); ?></option>
										<?php foreach ( $materials as $material ) : ?>
											<option
												value="<?php echo esc_attr( (int) $material->id ); ?>"
												data-price-per-gram="<?php echo esc_attr( (float) $material->price_per_gram ); ?>"
												data-price-per-cm3="<?php echo esc_attr( (float) $material->price_per_cm3 ); ?>"
												data-density="<?php echo esc_attr( (float) $material->density ); ?>"
												data-machine-factor="<?php echo esc_attr( (float) $material->machine_time_factor ); ?>"
												data-surface-factor="<?php echo esc_attr( (float) $material->surface_quality_factor ); ?>"
												data-wastage-factor="<?php echo esc_attr( (float) $material->wastage_factor ); ?>"
												<?php selected( $old( 'material_id' ), (string) (int) $material->id ); ?>
											>
												<?php echo esc_html( $material->name ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>

								<div class="srf-form__field">
									<label for="srf-printer-id">
										<?php esc_html_e( 'Printer', 'service-requests-form' ); ?> <span class="srf-required">*</span>
									</label>
									<select id="srf-printer-id" name="srf_printer_id" required>
										<option value=""><?php esc_html_e( 'Select printer', 'service-requests-form' ); ?></option>
										<?php foreach ( $printers as $printer ) : ?>
											<?php
											$supported_ids = array();
											if ( ! empty( $printer->supported_material_ids ) && is_array( $printer->supported_material_ids ) ) {
												$supported_ids = array_map( 'intval', $printer->supported_material_ids );
											}
											?>
											<option
												value="<?php echo esc_attr( (int) $printer->id ); ?>"
												data-supported-materials="<?php echo esc_attr( wp_json_encode( $supported_ids ) ); ?>"
												data-hourly-cost="<?php echo esc_attr( (float) $printer->hourly_cost ); ?>"
												data-default-speed="<?php echo esc_attr( (float) $printer->default_speed ); ?>"
												data-min-layer-height="<?php echo esc_attr( (float) $printer->min_layer_height ); ?>"
												data-max-layer-height="<?php echo esc_attr( (float) $printer->max_layer_height ); ?>"
												<?php selected( $old( 'printer_id' ), (string) (int) $printer->id ); ?>
											>
												<?php echo esc_html( $printer->name ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>

								<div class="srf-form__field srf-form__field--half">
									<label for="srf-layer-height"><?php esc_html_e( 'Layer height (mm)', 'service-requests-form' ); ?></label>
									<input type="number" min="0" step="0.01" id="srf-layer-height" name="srf_layer_height" value="<?php echo esc_attr( $old( 'layer_height', '0.20' ) ); ?>" />
								</div>

								<div class="srf-form__field srf-form__field--half">
									<label for="srf-infill"><?php esc_html_e( 'Infill (%)', 'service-requests-form' ); ?></label>
									<input type="number" min="0" max="100" step="1" id="srf-infill" name="srf_infill" value="<?php echo esc_attr( $old( 'infill', '20' ) ); ?>" />
								</div>

								<div class="srf-form__field">
									<label for="srf-shell-mode"><?php esc_html_e( 'Structure', 'service-requests-form' ); ?></label>
									<select id="srf-shell-mode" name="srf_shell_mode">
										<option value="solid" <?php selected( $old( 'shell_mode', 'solid' ), 'solid' ); ?>><?php esc_html_e( 'Solid', 'service-requests-form' ); ?></option>
										<option value="hollow" <?php selected( $old( 'shell_mode', 'solid' ), 'hollow' ); ?>><?php esc_html_e( 'Hollow', 'service-requests-form' ); ?></option>
									</select>
								</div>

								<div class="srf-form__field srf-form__field--half">
									<label for="srf-scale"><?php esc_html_e( 'Scale (%)', 'service-requests-form' ); ?></label>
									<input type="number" min="10" max="500" step="1" id="srf-scale" name="srf_scale" value="<?php echo esc_attr( $old( 'scale', '100' ) ); ?>" />
								</div>

								<div class="srf-form__field srf-form__field--half">
									<label for="srf-quantity"><?php esc_html_e( 'Quantity', 'service-requests-form' ); ?></label>
									<input type="number" min="1" step="1" id="srf-quantity" name="srf_quantity" value="<?php echo esc_attr( $old( 'quantity', '1' ) ); ?>" />
								</div>
							</div>

							<div class="srf-form__field">
								<label for="srf-quote-notes"><?php esc_html_e( 'Print notes', 'service-requests-form' ); ?></label>
								<textarea id="srf-quote-notes" name="srf_quote_notes" rows="5"><?php echo esc_textarea( $old( 'notes' ) ); ?></textarea>
							</div>
						</div>
					</aside>
				</div>

				<div
					class="srf-project-summary srf-project-card"
					data-srf-quote-summary
					data-currency-symbol="<?php echo esc_attr( $currency_symbol ); ?>"
					data-tax-rate="<?php echo esc_attr( $tax_rate ); ?>"
					data-service-fee="<?php echo esc_attr( $service_fee ); ?>"
					data-setup-fee="<?php echo esc_attr( $setup_fee ); ?>"
					data-profit-margin="<?php echo esc_attr( $profit_margin ); ?>"
				>
					<h3 class="srf-project-summary__title"><?php esc_html_e( 'Summary', 'service-requests-form' ); ?></h3>

					<div class="srf-project-summary__grid">
						<div class="srf-project-summary__item">
							<span><?php esc_html_e( 'Material', 'service-requests-form' ); ?></span>
							<strong data-srf-summary-material>—</strong>
						</div>
						<div class="srf-project-summary__item">
							<span><?php esc_html_e( 'Printer', 'service-requests-form' ); ?></span>
							<strong data-srf-summary-printer>—</strong>
						</div>
						<div class="srf-project-summary__item">
							<span><?php esc_html_e( 'Layer height', 'service-requests-form' ); ?></span>
							<strong data-srf-summary-layer>—</strong>
						</div>
						<div class="srf-project-summary__item">
							<span><?php esc_html_e( 'Quantity', 'service-requests-form' ); ?></span>
							<strong data-srf-summary-quantity>—</strong>
						</div>
						<div class="srf-project-summary__item">
							<span><?php esc_html_e( 'Estimated volume', 'service-requests-form' ); ?></span>
							<strong data-srf-price-volume>—</strong>
						</div>
						<div class="srf-project-summary__item">
							<span><?php esc_html_e( 'Estimated material cost', 'service-requests-form' ); ?></span>
							<strong data-srf-price-material>—</strong>
						</div>
						<div class="srf-project-summary__item">
							<span><?php esc_html_e( 'Estimated printer cost', 'service-requests-form' ); ?></span>
							<strong data-srf-price-printer>—</strong>
						</div>
						<div class="srf-project-summary__item">
							<span><?php esc_html_e( 'Service fee', 'service-requests-form' ); ?></span>
							<strong data-srf-price-service>—</strong>
						</div>
						<div class="srf-project-summary__item">
							<span><?php esc_html_e( 'Setup fee', 'service-requests-form' ); ?></span>
							<strong data-srf-price-setup>—</strong>
						</div>
						<div class="srf-project-summary__item">
							<span><?php esc_html_e( 'Tax', 'service-requests-form' ); ?></span>
							<strong data-srf-price-tax>—</strong>
						</div>
						<div class="srf-project-summary__item srf-project-summary__item--total">
							<span><?php esc_html_e( 'Estimated total', 'service-requests-form' ); ?></span>
							<strong data-srf-price-total>—</strong>
						</div>
					</div>
				</div>

				</div>
			</div>

			<div class="srf-project-final-note">
				<p><?php esc_html_e( 'Our team works Monday to Friday, 9:00am - 15:00pm.', 'service-requests-form' ); ?></p>
				<p><?php esc_html_e( 'Your request will be processed as soon as possible, within up to 3 working days.', 'service-requests-form' ); ?></p>
				<p><?php esc_html_e( 'We will contact you using the information in your user profile. You can also check your request status in your dashboard.', 'service-requests-form' ); ?></p>
			</div>

			<input type="hidden" name="srf_project_form_submitted" value="1" />
			<?php wp_nonce_field( 'srf_submit_project_request', 'srf_project_nonce' ); ?>

			<div class="srf-form__actions srf-form__actions--project">
				<button type="button" class="srf-button srf-button--secondary srf-project-btn srf-project-btn--back" data-srf-prev-step="2">
					<span class="srf-button__label"><?php esc_html_e( 'Back', 'service-requests-form' ); ?></span>
				</button>

				<button type="submit" class="srf-button srf-project-btn srf-project-btn--submit">
					<span class="srf-button__label"><?php esc_html_e( 'Submit request', 'service-requests-form' ); ?></span>
				</button>
			</div>
		</div>
	</form>

	<?php endif; ?>
</div>