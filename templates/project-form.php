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

$dashboard_url = isset( $dashboard_url ) ? (string) $dashboard_url : '';
$upload_limit  = isset( $upload_limit ) ? (string) $upload_limit : '1 GB';
$is_business   = ! empty( $is_business );
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
			<div class="srf-project-step is-active" data-step="1">1. Details</div>
			<div class="srf-project-step" data-step="2">2. Terms & Upload</div>
			<div class="srf-project-step" data-step="3">3. Confirmed</div>
		</div>

		<div class="srf-project-panel srf-project-panel--step1 is-active" data-srf-step-panel="1">
			<div class="srf-project-grid">
				<div class="srf-project-main">
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
					<?php if ( is_user_logged_in() ) : ?>
						<div class="srf-project-auth__box srf-project-auth__box--loggedin">
							<h3><?php esc_html_e( 'Account verified', 'service-requests-form' ); ?></h3>
							<p><?php esc_html_e( 'You are logged in and can continue to the next step.', 'service-requests-form' ); ?></p>
						</div>
					<?php else : ?>
						<div class="srf-project-auth__box">
							<h3><?php esc_html_e( 'Login or register', 'service-requests-form' ); ?></h3>
							<p><?php esc_html_e( 'To continue to the upload step, you need an account.', 'service-requests-form' ); ?></p>
							<div class="srf-project-auth__form">
								<?php echo do_shortcode( '[woocommerce_my_account]' ); ?>
							</div>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<div class="srf-form__actions">
				<button type="button" class="srf-button" data-srf-next-step="1">
					<?php esc_html_e( 'Next', 'service-requests-form' ); ?>
				</button>
			</div>
		</div>

		<div class="srf-project-panel srf-project-panel--step2" data-srf-step-panel="2">
			<div class="srf-form__field srf-form__field--checkbox">
				<label>
					<input type="checkbox" name="srf_terms" value="1" <?php checked( $old( 'terms' ), '1' ); ?> required />
					<?php esc_html_e( 'I accept the Terms & Conditions.', 'service-requests-form' ); ?>
				</label>
			</div>

			<div class="srf-form__field">
				<label for="srf-files">
					<?php esc_html_e( 'Upload file(s)', 'service-requests-form' ); ?> <span class="srf-required">*</span>
				</label>
				<input type="file" id="srf-files" name="srf_files[]" multiple required />
				<small class="srf-field__help">
					<?php
					echo esc_html(
						sprintf(
							__( 'Maximum upload size for your account: %s', 'service-requests-form' ),
							$upload_limit
						)
					);
					?>
				</small>
				<small class="srf-field__help">
					<?php echo esc_html( $is_business ? __( 'Business account detected: up to 10 GB.', 'service-requests-form' ) : __( 'Standard account detected: up to 1 GB.', 'service-requests-form' ) ); ?>
				</small>
			</div>

			<div class="srf-project-final-note">
				<p><?php esc_html_e( 'Our team works Monday to Friday, 9:00am - 15:00pm.', 'service-requests-form' ); ?></p>
				<p><?php esc_html_e( 'Your request will be processed as soon as possible, within up to 3 working days.', 'service-requests-form' ); ?></p>
				<p><?php esc_html_e( 'We will contact you using the information in your user profile. You can also check your request status in your dashboard.', 'service-requests-form' ); ?></p>
			</div>

			<input type="hidden" name="srf_project_form_submitted" value="1" />
			<?php wp_nonce_field( 'srf_submit_project_request', 'srf_project_nonce' ); ?>

			<div class="srf-form__actions srf-form__actions--project">
				<button type="button" class="srf-button srf-button--secondary" data-srf-prev-step="2">
					<?php esc_html_e( 'Back', 'service-requests-form' ); ?>
				</button>

				<button type="submit" class="srf-button">
					<?php esc_html_e( 'Submit request', 'service-requests-form' ); ?>
				</button>
			</div>
		</div>
	</form>

	<?php endif; ?>
</div>