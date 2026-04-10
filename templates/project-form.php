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

$google_error_map = array(
	'google_disabled'      => __( 'Google login is currently unavailable.', 'service-requests-form' ),
	'google_missing_code'  => __( 'Google login was canceled or incomplete.', 'service-requests-form' ),
	'google_invalid_state' => __( 'Google login security validation failed. Please try again.', 'service-requests-form' ),
	'google_token_failed'  => __( 'Could not complete Google login. Please try again.', 'service-requests-form' ),
	'google_token_missing' => __( 'Could not verify your Google account. Please try again.', 'service-requests-form' ),
	'google_userinfo_failed' => __( 'Could not fetch your Google profile. Please try again.', 'service-requests-form' ),
	'google_profile_invalid' => __( 'Google account email is missing or not verified.', 'service-requests-form' ),
	'google_user_failed'   => __( 'Could not create or sign in your account. Please try again.', 'service-requests-form' ),
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

				<small class="srf-field__help srf-project-help">
					<?php echo esc_html( $is_business ? __( 'Business account limit: 10 GB.', 'service-requests-form' ) : __( 'Standard account limit: 1 GB.', 'service-requests-form' ) ); ?>
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
