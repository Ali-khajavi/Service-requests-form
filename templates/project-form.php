<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$errors = isset( $errors ) && is_array( $errors ) ? $errors : array();
$old_data = isset( $old_data ) && is_array( $old_data ) ? $old_data : array();
$old = static function( $key, $default = '' ) use ( $old_data ) {
	return array_key_exists( $key, $old_data ) ? $old_data[ $key ] : $default;
};

$dashboard_url          = isset( $dashboard_url ) ? (string) $dashboard_url : '';
$upload_limit           = isset( $upload_limit ) ? (string) $upload_limit : '500 MB';
$upload_limit_bytes     = isset( $upload_limit_bytes ) ? max( 1, (int) $upload_limit_bytes ) : 524288000;
$allowed_formats        = isset( $allowed_formats ) ? strtolower( (string) $allowed_formats ) : 'stl, obj, 3mf';
$allowed_csv            = preg_replace( '/\s+/', '', $allowed_formats );
$materials              = isset( $materials ) && is_array( $materials ) ? $materials : array();
$printers               = isset( $printers ) && is_array( $printers ) ? $printers : array();
$print_profiles         = isset( $print_profiles ) && is_array( $print_profiles ) ? $print_profiles : array();
$quote_settings         = isset( $quote_settings ) && is_array( $quote_settings ) ? $quote_settings : array();
$project_public         = ! empty( $project_public );
$checkout_enabled       = ! empty( $checkout_enabled );
$checkout_requested     = ! empty( $checkout_requested );
$woocommerce_available  = ! empty( $woocommerce_available );
$payment_warning        = isset( $payment_warning ) ? (string) $payment_warning : '';
$currency_symbol        = isset( $quote_settings['currency_symbol'] ) ? (string) $quote_settings['currency_symbol'] : '€';
$tax_rate               = isset( $quote_settings['tax_rate'] ) ? (float) $quote_settings['tax_rate'] : 0;
$service_fee            = isset( $quote_settings['service_fee'] ) ? (float) $quote_settings['service_fee'] : 0;
$setup_fee              = isset( $quote_settings['setup_fee'] ) ? (float) $quote_settings['setup_fee'] : 0;
$profit_margin          = isset( $quote_settings['profit_margin'] ) ? (float) $quote_settings['profit_margin'] : 0;
$terms_url               = (string) get_option( 'srf_terms_url', '' );
$initial_step            = ! empty( $errors ) && ! empty( $_POST['srf_project_form_submitted'] ) ? 2 : 1; // phpcs:ignore WordPress.Security.NonceVerification.Missing
$submit_label            = $checkout_enabled ? __( 'Continue to secure payment', 'service-requests-form' ) : __( 'Submit print request', 'service-requests-form' );
$default_profile         = class_exists( 'SRF_Print_Profiles' ) ? SRF_Print_Profiles::get_default_profile_key() : 'custom';
$selected_profile        = (string) $old( 'print_profile', $default_profile );
$logged_in               = is_user_logged_in();
?>

<div class="srf-project-wrapper">
	<?php if ( ! empty( $errors ) ) : ?>
		<div class="srf-form__errors" role="alert" aria-live="assertive">
			<h2><?php esc_html_e( 'Please review the following', 'service-requests-form' ); ?></h2>
			<ul>
				<?php foreach ( $errors as $error ) : ?>
					<li><?php echo esc_html( $error ); ?></li>
				<?php endforeach; ?>
			</ul>
			<p><?php esc_html_e( 'For security, browsers do not retain selected model files after a server validation error. Please select the models again in step 2.', 'service-requests-form' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $payment_warning ) : ?>
		<div class="srf-project-notice srf-project-notice--warning" role="status"><?php echo esc_html( $payment_warning ); ?></div>
	<?php endif; ?>

	<?php if ( ! empty( $success ) ) : ?>
		<section class="srf-project-success" data-srf-project-success data-dashboard-url="<?php echo esc_url( $dashboard_url ); ?>">
			<div class="srf-project-success__box">
				<span class="srf-project-success__icon" aria-hidden="true">✓</span>
				<h2><?php esc_html_e( 'Print request received', 'service-requests-form' ); ?></h2>
				<p><?php esc_html_e( 'Your model files and securely calculated quote were saved. The team will review the project and contact you using the details supplied.', 'service-requests-form' ); ?></p>
				<?php if ( $dashboard_url ) : ?>
					<p><a class="srf-button" href="<?php echo esc_url( $dashboard_url ); ?>"><?php esc_html_e( 'View my requests', 'service-requests-form' ); ?></a></p>
				<?php endif; ?>
			</div>
		</section>
	<?php else : ?>
		<form
			class="srf-form srf-project-form"
			method="post"
			enctype="multipart/form-data"
			data-srf-form-type="project"
			data-srf-project-form
			data-initial-step="<?php echo esc_attr( $initial_step ); ?>"
			data-project-public="<?php echo $project_public ? '1' : '0'; ?>"
			data-checkout-enabled="<?php echo $checkout_enabled ? '1' : '0'; ?>"
			data-max-upload-bytes="<?php echo esc_attr( $upload_limit_bytes ); ?>"
			data-allowed-extensions="<?php echo esc_attr( $allowed_csv ); ?>"
			novalidate
		>
			<header class="srf-project-hero">
				<div>
					<p class="srf-project-hero__eyebrow"><?php esc_html_e( 'Custom 3D printing', 'service-requests-form' ); ?></p>
					<h1><?php esc_html_e( 'Upload a model, configure the print, and receive an instant estimate', 'service-requests-form' ); ?></h1>
					<p><?php esc_html_e( 'The browser creates a lightweight preview for STL and OBJ while the final checkout amount is always recalculated from the uploaded files on the server.', 'service-requests-form' ); ?></p>
				</div>
				<div class="srf-project-hero__trust">
					<span><?php esc_html_e( 'Server-verified pricing', 'service-requests-form' ); ?></span>
					<span><?php esc_html_e( 'Secure WooCommerce checkout', 'service-requests-form' ); ?></span>
				</div>
			</header>

			<div
				class="srf-project-steps srf-project-steps--single-row"
				role="navigation"
				aria-label="<?php esc_attr_e( 'Project order steps', 'service-requests-form' ); ?>"
				data-srf-project-stepper="0.10.90"
				style="display:flex !important;flex-flow:row nowrap !important;gap:clamp(8px,1.4vw,20px) !important;align-items:stretch !important;justify-content:flex-start !important;width:100% !important;max-width:100% !important;min-width:0 !important;overflow:visible !important;"
			>
				<div class="srf-project-step-slot" style="display:flex !important;flex:1 1 0 !important;flex-basis:0 !important;width:0 !important;min-width:0 !important;max-width:none !important;margin:0 !important;padding:0 !important;">
					<button type="button" class="srf-project-step srf-project-step--single-row is-active" data-srf-step-go="1" aria-current="step" style="flex:1 1 auto !important;width:100% !important;min-width:0 !important;max-width:100% !important;margin:0 !important;box-sizing:border-box !important;float:none !important;clear:none !important;">
						<span class="srf-project-step__number">1</span>
						<span><strong><?php esc_html_e( 'Project', 'service-requests-form' ); ?></strong><small><?php esc_html_e( 'Name and requirements', 'service-requests-form' ); ?></small></span>
					</button>
				</div>
				<div class="srf-project-step-slot" style="display:flex !important;flex:1 1 0 !important;flex-basis:0 !important;width:0 !important;min-width:0 !important;max-width:none !important;margin:0 !important;padding:0 !important;">
					<button type="button" class="srf-project-step srf-project-step--single-row" data-srf-step-go="2" style="flex:1 1 auto !important;width:100% !important;min-width:0 !important;max-width:100% !important;margin:0 !important;box-sizing:border-box !important;float:none !important;clear:none !important;">
						<span class="srf-project-step__number">2</span>
						<span><strong><?php esc_html_e( 'Model', 'service-requests-form' ); ?></strong><small><?php esc_html_e( 'Upload and preview', 'service-requests-form' ); ?></small></span>
					</button>
				</div>
				<div class="srf-project-step-slot" style="display:flex !important;flex:1 1 0 !important;flex-basis:0 !important;width:0 !important;min-width:0 !important;max-width:none !important;margin:0 !important;padding:0 !important;">
					<button type="button" class="srf-project-step srf-project-step--single-row" data-srf-step-go="3" style="flex:1 1 auto !important;width:100% !important;min-width:0 !important;max-width:100% !important;margin:0 !important;box-sizing:border-box !important;float:none !important;clear:none !important;">
						<span class="srf-project-step__number">3</span>
						<span><strong><?php esc_html_e( 'Print & price', 'service-requests-form' ); ?></strong><small><?php esc_html_e( 'Configure and pay', 'service-requests-form' ); ?></small></span>
					</button>
				</div>
			</div>

			<section class="srf-project-panel is-active" data-srf-step-panel="1" aria-hidden="false">
				<div class="srf-project-panel__heading">
					<p class="srf-project-panel__step"><?php esc_html_e( 'Step 1 of 3', 'service-requests-form' ); ?></p>
					<h2><?php esc_html_e( 'Tell us about the project', 'service-requests-form' ); ?></h2>
					<p><?php esc_html_e( 'Use a clear project name and explain the purpose, critical dimensions, finish, colour, deadline, and any assembly requirements.', 'service-requests-form' ); ?></p>
				</div>

				<div class="srf-project-grid srf-project-grid--details">
					<div class="srf-project-card">
						<div class="srf-form__field">
							<label for="srf-project-title"><?php esc_html_e( 'Project name', 'service-requests-form' ); ?> <span class="srf-required">*</span></label>
							<input type="text" id="srf-project-title" name="srf_project_title" value="<?php echo esc_attr( $old( 'title' ) ); ?>" maxlength="180" required autocomplete="off" />
						</div>
						<div class="srf-form__field">
							<label for="srf-project-description"><?php esc_html_e( 'Project description', 'service-requests-form' ); ?> <span class="srf-required">*</span></label>
							<textarea id="srf-project-description" name="srf_project_description" rows="8" maxlength="6000" required><?php echo esc_textarea( $old( 'description' ) ); ?></textarea>
							<p class="srf-form__help"><?php esc_html_e( 'Do not include passwords, medical records, or other sensitive personal information.', 'service-requests-form' ); ?></p>
						</div>
					</div>

					<aside class="srf-project-card srf-project-contact-card">
						<?php if ( $logged_in ) : ?>
							<p class="srf-project-contact-card__badge"><?php esc_html_e( 'Signed-in customer', 'service-requests-form' ); ?></p>
							<h3><?php echo esc_html( $old( 'name', wp_get_current_user()->display_name ) ); ?></h3>
							<dl>
								<dt><?php esc_html_e( 'Email', 'service-requests-form' ); ?></dt><dd><?php echo esc_html( $old( 'email', wp_get_current_user()->user_email ) ); ?></dd>
								<?php if ( $old( 'company' ) ) : ?><dt><?php esc_html_e( 'Company', 'service-requests-form' ); ?></dt><dd><?php echo esc_html( $old( 'company' ) ); ?></dd><?php endif; ?>
								<?php if ( $old( 'phone' ) ) : ?><dt><?php esc_html_e( 'Phone', 'service-requests-form' ); ?></dt><dd><?php echo esc_html( $old( 'phone' ) ); ?></dd><?php endif; ?>
							</dl>
							<p class="srf-form__help"><?php esc_html_e( 'WooCommerce will confirm billing and delivery details during checkout.', 'service-requests-form' ); ?></p>
						<?php elseif ( $project_public ) : ?>
							<p class="srf-project-contact-card__badge"><?php esc_html_e( 'Guest order', 'service-requests-form' ); ?></p>
							<h3><?php esc_html_e( 'Contact details', 'service-requests-form' ); ?></h3>
							<div class="srf-form__field">
								<label for="srf-guest-name"><?php esc_html_e( 'Name', 'service-requests-form' ); ?> <span class="srf-required">*</span></label>
								<input type="text" id="srf-guest-name" name="srf_guest_name" value="<?php echo esc_attr( $old( 'name' ) ); ?>" maxlength="180" required autocomplete="name" />
							</div>
							<div class="srf-form__field">
								<label for="srf-guest-email"><?php esc_html_e( 'Email', 'service-requests-form' ); ?> <span class="srf-required">*</span></label>
								<input type="email" id="srf-guest-email" name="srf_guest_email" value="<?php echo esc_attr( $old( 'email' ) ); ?>" maxlength="190" required autocomplete="email" />
							</div>
							<div class="srf-form__field">
								<label for="srf-guest-company"><?php esc_html_e( 'Company', 'service-requests-form' ); ?></label>
								<input type="text" id="srf-guest-company" name="srf_guest_company" value="<?php echo esc_attr( $old( 'company' ) ); ?>" maxlength="180" autocomplete="organization" />
							</div>
							<div class="srf-form__field">
								<label for="srf-guest-phone"><?php esc_html_e( 'Phone', 'service-requests-form' ); ?></label>
								<input type="tel" id="srf-guest-phone" name="srf_guest_phone" value="<?php echo esc_attr( $old( 'phone' ) ); ?>" maxlength="80" autocomplete="tel" />
							</div>
						<?php endif; ?>
					</aside>
				</div>

				<div class="srf-project-step-error" data-srf-step-error="1" role="alert" hidden></div>
				<div class="srf-form__actions srf-form__actions--project">
					<span></span>
					<button type="button" class="srf-button srf-project-btn" data-srf-next-step="2"><?php esc_html_e( 'Continue to model upload', 'service-requests-form' ); ?></button>
				</div>
			</section>

			<section class="srf-project-panel" data-srf-step-panel="2" aria-hidden="true" hidden>
				<div class="srf-project-panel__heading">
					<p class="srf-project-panel__step"><?php esc_html_e( 'Step 2 of 3', 'service-requests-form' ); ?></p>
					<h2><?php esc_html_e( 'Upload and inspect the 3D model', 'service-requests-form' ); ?></h2>
					<p><?php esc_html_e( 'STL and OBJ files are analysed in a background browser worker so large meshes do not block the page. 3MF files are analysed securely on the server.', 'service-requests-form' ); ?></p>
				</div>

				<div class="srf-project-workspace">
					<div class="srf-project-card srf-project-upload-card">
						<label class="srf-project-dropzone" data-srf-dropzone for="srf-project-files">
							<span class="srf-project-dropzone__icon" aria-hidden="true">⬆</span>
							<strong><?php esc_html_e( 'Drop 3D models here', 'service-requests-form' ); ?></strong>
							<span><?php esc_html_e( 'or select files from your device', 'service-requests-form' ); ?></span>
							<span class="srf-button srf-button--secondary"><?php esc_html_e( 'Select models', 'service-requests-form' ); ?></span>
							<?php $srf_direct_uploads = class_exists( 'SRF_Storage_Manager' ) && SRF_Storage_Manager::instance()->is_microsoft_enabled_for_form( 'project' ) && SRF_Storage_Manager::instance()->get_provider() instanceof SRF_Microsoft_Storage_Provider; ?>
							<input id="srf-project-files" class="srf-project-file-input" type="file" name="srf_files[]" accept=".stl,.obj,.3mf" multiple required data-srf-model-files <?php echo $srf_direct_uploads ? 'disabled="disabled"' : ''; ?> />
							<input type="hidden" name="srf_upload_batch_id" value="<?php echo esc_attr( (int) $old( 'upload_batch_id', 0 ) ); ?>" data-srf-upload-batch-id />
							<input type="hidden" name="srf_upload_batch_token" value="<?php echo esc_attr( (string) $old( 'upload_batch_token', '' ) ); ?>" data-srf-upload-batch-token />
						</label>
						<p class="srf-form__help"><?php echo esc_html( sprintf( __( 'Accepted: %1$s. Maximum combined upload: %2$s. Use millimetres and upload closed, printable meshes.', 'service-requests-form' ), strtoupper( $allowed_formats ), $upload_limit ) ); ?></p>
						<div class="srf-project-file-notice" data-srf-file-notice role="status" aria-live="polite" hidden></div>
						<div class="srf-project-analysis-progress" data-srf-analysis-progress hidden>
							<progress max="1" value="0" data-srf-analysis-progress-bar></progress>
							<span data-srf-analysis-progress-text></span>
						</div>
						<ul class="srf-project-file-list" data-srf-file-list hidden></ul>
					</div>

					<div class="srf-project-card srf-project-viewer" data-srf-model-viewer>
						<div class="srf-project-viewer__header">
							<div>
								<h3><?php esc_html_e( 'Studio model preview', 'service-requests-form' ); ?></h3>
								<p><?php esc_html_e( 'GPU-accelerated solid preview with studio lighting, colour, build plate, and inspection modes.', 'service-requests-form' ); ?></p>
							</div>
							<div class="srf-project-viewer__controls" aria-label="<?php esc_attr_e( 'Preview views', 'service-requests-form' ); ?>">
								<button type="button" data-srf-view="front"><?php esc_html_e( 'Front', 'service-requests-form' ); ?></button>
								<button type="button" data-srf-view="left"><?php esc_html_e( 'Left', 'service-requests-form' ); ?></button>
								<button type="button" data-srf-view="top"><?php esc_html_e( 'Top', 'service-requests-form' ); ?></button>
								<button type="button" data-srf-view="iso"><?php esc_html_e( 'Iso', 'service-requests-form' ); ?></button>
								<button type="button" data-srf-view="fit"><?php esc_html_e( 'Fit', 'service-requests-form' ); ?></button>
							</div>
						</div>

						<div class="srf-project-viewer__toolbar">
							<div class="srf-project-viewer__tool-group" role="group" aria-label="<?php esc_attr_e( 'Model colour', 'service-requests-form' ); ?>">
								<span class="srf-project-viewer__tool-label"><?php esc_html_e( 'Model colour', 'service-requests-form' ); ?></span>
								<div class="srf-project-viewer__swatches">
									<button type="button" class="srf-viewer-swatch is-active" data-srf-model-color="white" aria-pressed="true" aria-label="<?php esc_attr_e( 'White model', 'service-requests-form' ); ?>" title="<?php esc_attr_e( 'White', 'service-requests-form' ); ?>"><span style="--srf-swatch:#efede5"></span></button>
									<button type="button" class="srf-viewer-swatch" data-srf-model-color="grey" aria-pressed="false" aria-label="<?php esc_attr_e( 'Grey model', 'service-requests-form' ); ?>" title="<?php esc_attr_e( 'Grey', 'service-requests-form' ); ?>"><span style="--srf-swatch:#8f9ba8"></span></button>
									<button type="button" class="srf-viewer-swatch" data-srf-model-color="black" aria-pressed="false" aria-label="<?php esc_attr_e( 'Black model', 'service-requests-form' ); ?>" title="<?php esc_attr_e( 'Black', 'service-requests-form' ); ?>"><span style="--srf-swatch:#15191d"></span></button>
									<button type="button" class="srf-viewer-swatch" data-srf-model-color="blue" aria-pressed="false" aria-label="<?php esc_attr_e( 'Blue model', 'service-requests-form' ); ?>" title="<?php esc_attr_e( 'Blue', 'service-requests-form' ); ?>"><span style="--srf-swatch:#2672c9"></span></button>
									<button type="button" class="srf-viewer-swatch" data-srf-model-color="red" aria-pressed="false" aria-label="<?php esc_attr_e( 'Red model', 'service-requests-form' ); ?>" title="<?php esc_attr_e( 'Red', 'service-requests-form' ); ?>"><span style="--srf-swatch:#c92d27"></span></button>
									<button type="button" class="srf-viewer-swatch" data-srf-model-color="green" aria-pressed="false" aria-label="<?php esc_attr_e( 'Green model', 'service-requests-form' ); ?>" title="<?php esc_attr_e( 'Green', 'service-requests-form' ); ?>"><span style="--srf-swatch:#2b8d50"></span></button>
									<button type="button" class="srf-viewer-chip" data-srf-model-color="filament" aria-pressed="false"><?php esc_html_e( 'Filament', 'service-requests-form' ); ?></button>
									<button type="button" class="srf-viewer-chip" data-srf-model-color="embedded" aria-pressed="false" hidden disabled><?php esc_html_e( 'File colours', 'service-requests-form' ); ?></button>
								</div>
							</div>

							<div class="srf-project-viewer__tool-group" role="group" aria-label="<?php esc_attr_e( 'Surface display', 'service-requests-form' ); ?>">
								<span class="srf-project-viewer__tool-label"><?php esc_html_e( 'Surface', 'service-requests-form' ); ?></span>
								<div class="srf-project-viewer__button-row">
									<button type="button" class="is-active" data-srf-shading="smooth" aria-pressed="true"><?php esc_html_e( 'Smooth', 'service-requests-form' ); ?></button>
									<button type="button" data-srf-shading="flat" aria-pressed="false"><?php esc_html_e( 'Flat', 'service-requests-form' ); ?></button>
									<button type="button" data-srf-viewer-toggle="wireframe" aria-pressed="false"><?php esc_html_e( 'Wireframe', 'service-requests-form' ); ?></button>
									<button type="button" class="is-active" data-srf-viewer-toggle="bed" aria-pressed="true"><?php esc_html_e( 'Build plate', 'service-requests-form' ); ?></button>
								</div>
							</div>

							<div class="srf-project-viewer__tool-group" role="group" aria-label="<?php esc_attr_e( 'Model orientation', 'service-requests-form' ); ?>">
								<span class="srf-project-viewer__tool-label"><?php esc_html_e( 'Orientation', 'service-requests-form' ); ?></span>
								<div class="srf-project-viewer__button-row">
									<button type="button" class="is-active" data-srf-orient="auto" aria-pressed="true"><?php esc_html_e( 'Auto', 'service-requests-form' ); ?></button>
									<button type="button" data-srf-orient="x" aria-label="<?php esc_attr_e( 'Rotate 90 degrees around X', 'service-requests-form' ); ?>">X +90°</button>
									<button type="button" data-srf-orient="y" aria-label="<?php esc_attr_e( 'Rotate 90 degrees around Y', 'service-requests-form' ); ?>">Y +90°</button>
									<button type="button" data-srf-orient="z" aria-label="<?php esc_attr_e( 'Rotate 90 degrees around Z', 'service-requests-form' ); ?>">Z +90°</button>
								</div>
							</div>
						</div>

						<div class="srf-project-viewer__stage">
							<canvas data-srf-model-canvas aria-label="<?php esc_attr_e( 'Interactive 3D model preview', 'service-requests-form' ); ?>"></canvas>
							<div class="srf-project-viewer__empty" data-srf-viewer-empty>
								<span aria-hidden="true">◫</span>
								<strong><?php esc_html_e( 'Studio preview ready', 'service-requests-form' ); ?></strong>
								<small><?php esc_html_e( 'Select an STL or OBJ model to inspect it in 3D.', 'service-requests-form' ); ?></small>
							</div>
							<div class="srf-project-viewer__hud" aria-live="polite">
								<span class="srf-project-viewer__engine">WebGL</span>
								<span data-srf-viewer-scale><?php esc_html_e( 'Scale 100%', 'service-requests-form' ); ?></span>
								<span data-srf-viewer-build><?php esc_html_e( 'Preview bed', 'service-requests-form' ); ?></span>
								<span data-srf-viewer-fit data-fit="unknown"><?php esc_html_e( 'Select a printer for build-volume guidance', 'service-requests-form' ); ?></span>
							</div>
							<div class="srf-project-viewer__context-lost" role="status"><?php esc_html_e( 'The graphics context was interrupted. The preview will restore automatically.', 'service-requests-form' ); ?></div>
						</div>

						<dl class="srf-project-model-meta" data-srf-model-meta>
							<div><dt><?php esc_html_e( 'File', 'service-requests-form' ); ?></dt><dd data-field="filename">—</dd></div>
							<div><dt><?php esc_html_e( 'Format', 'service-requests-form' ); ?></dt><dd data-field="format">—</dd></div>
							<div><dt><?php esc_html_e( 'Triangles', 'service-requests-form' ); ?></dt><dd data-field="triangles">—</dd></div>
							<div><dt><?php esc_html_e( 'Bounds', 'service-requests-form' ); ?></dt><dd data-field="bounds">—</dd></div>
							<div><dt><?php esc_html_e( 'Closed volume', 'service-requests-form' ); ?></dt><dd data-field="volume">—</dd></div>
						</dl>
					</div>
				</div>

				<div class="srf-project-step-error" data-srf-step-error="2" role="alert" hidden></div>
				<div class="srf-form__actions srf-form__actions--project">
					<button type="button" class="srf-button srf-button--secondary" data-srf-prev-step="1"><?php esc_html_e( 'Back', 'service-requests-form' ); ?></button>
					<button type="button" class="srf-button" data-srf-next-step="3"><?php esc_html_e( 'Continue to print setup', 'service-requests-form' ); ?></button>
				</div>
			</section>

			<section class="srf-project-panel" data-srf-step-panel="3" aria-hidden="true" hidden>
				<div class="srf-project-panel__heading">
					<p class="srf-project-panel__step"><?php esc_html_e( 'Step 3 of 3', 'service-requests-form' ); ?></p>
					<h2><?php esc_html_e( 'Choose the print setup and review the price', 'service-requests-form' ); ?></h2>
					<p><?php esc_html_e( 'Select a printer, compatible material, and process. Named Bambu profiles use fixed cost-driving values and are verified again on the server.', 'service-requests-form' ); ?></p>
				</div>

				<?php if ( empty( $printers ) || empty( $materials ) ) : ?>
					<div class="srf-project-notice srf-project-notice--error" role="alert">
						<?php esc_html_e( 'No active printer or material is available. An administrator must configure starter data under Service Requests → Settings, Materials, and Printers.', 'service-requests-form' ); ?>
					</div>
				<?php endif; ?>

				<div class="srf-project-checkout-grid">
					<div class="srf-project-card srf-project-configurator">
						<div class="srf-project-configurator__grid">
							<div class="srf-form__field">
								<label for="srf-printer-id"><?php esc_html_e( 'Printer', 'service-requests-form' ); ?> <span class="srf-required">*</span></label>
								<select id="srf-printer-id" name="srf_printer_id" required data-srf-quote-input>
									<option value=""><?php esc_html_e( 'Select a printer', 'service-requests-form' ); ?></option>
									<?php foreach ( $printers as $printer ) : ?>
										<?php
										$supported_ids = ! empty( $printer->supported_material_ids ) && is_array( $printer->supported_material_ids ) ? array_values( array_map( 'intval', $printer->supported_material_ids ) ) : array();
										$is_bambu = class_exists( 'SRF_Print_Profiles' ) && SRF_Print_Profiles::is_bambu_printer( $printer );
										$suffix = class_exists( 'SRF_Print_Profiles' ) ? SRF_Print_Profiles::get_printer_suffix( $printer ) : '';
										?>
										<option
											value="<?php echo esc_attr( (int) $printer->id ); ?>"
											data-brand="<?php echo esc_attr( (string) ( $printer->brand ?? '' ) ); ?>"
											data-model="<?php echo esc_attr( (string) ( $printer->model ?? '' ) ); ?>"
									data-technology="<?php echo esc_attr( (string) ( $printer->technology ?? 'fdm' ) ); ?>"
											data-printer-suffix="<?php echo esc_attr( $suffix ); ?>"
											data-is-bambu="<?php echo $is_bambu ? '1' : '0'; ?>"
											data-supported-materials="<?php echo esc_attr( wp_json_encode( $supported_ids ) ); ?>"
											data-default-material-id="<?php echo esc_attr( (int) ( $printer->default_material_id ?? 0 ) ); ?>"
											data-build-x="<?php echo esc_attr( (float) ( $printer->build_volume_x ?? 0 ) ); ?>"
											data-build-y="<?php echo esc_attr( (float) ( $printer->build_volume_y ?? 0 ) ); ?>"
											data-build-z="<?php echo esc_attr( (float) ( $printer->build_volume_z ?? 0 ) ); ?>"
											data-nozzle-size="<?php echo esc_attr( (float) ( $printer->nozzle_size ?? 0.4 ) ); ?>"
											data-line-width="<?php echo esc_attr( (float) ( $printer->fdm_default_line_width ?? 0 ) ); ?>"
											data-min-layer-height="<?php echo esc_attr( (float) ( $printer->min_layer_height ?? 0 ) ); ?>"
											data-max-layer-height="<?php echo esc_attr( (float) ( $printer->max_layer_height ?? 0 ) ); ?>"
											data-default-speed="<?php echo esc_attr( (float) ( $printer->default_speed ?? 0 ) ); ?>"
											data-speed-unit="<?php echo esc_attr( (string) ( $printer->speed_unit ?? '' ) ); ?>"
											data-hourly-cost="<?php echo esc_attr( (float) ( $printer->hourly_cost ?? 0 ) ); ?>"
											data-efficiency="<?php echo esc_attr( (float) ( $printer->machine_efficiency_factor ?? 1 ) ); ?>"
											data-setup-minutes="<?php echo esc_attr( (float) ( $printer->setup_time_minutes ?? 0 ) ); ?>"
											data-warmup-minutes="<?php echo esc_attr( (float) ( $printer->warmup_time_minutes ?? 0 ) ); ?>"
											data-postprocess-minutes="<?php echo esc_attr( (float) ( $printer->postprocess_time_minutes ?? 0 ) ); ?>"
											data-minimum-job-price="<?php echo esc_attr( (float) ( $printer->minimum_job_price ?? 0 ) ); ?>"
											data-minimum-material-charge="<?php echo esc_attr( (float) ( $printer->minimum_material_charge ?? 0 ) ); ?>"
											data-margin-override="<?php echo isset( $printer->margin_override ) ? esc_attr( (string) $printer->margin_override ) : ''; ?>"
											data-pricing-model="<?php echo esc_attr( (string) ( $printer->pricing_model ?? 'hybrid' ) ); ?>"
											data-support-factor="<?php echo esc_attr( (float) ( $printer->fdm_support_factor ?? 1.12 ) ); ?>"
											<?php selected( (string) $old( 'printer_id' ), (string) (int) $printer->id ); ?>
										><?php echo esc_html( $printer->name ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>

							<div class="srf-form__field">
								<label for="srf-material-id"><?php esc_html_e( 'Material', 'service-requests-form' ); ?> <span class="srf-required">*</span></label>
								<select id="srf-material-id" name="srf_material_id" required data-srf-quote-input>
									<option value=""><?php esc_html_e( 'Select a material', 'service-requests-form' ); ?></option>
									<?php foreach ( $materials as $material ) : ?>
										<option
											value="<?php echo esc_attr( (int) $material->id ); ?>"
											data-price-per-gram="<?php echo esc_attr( (float) ( $material->price_per_gram ?? 0 ) ); ?>"
											data-price-per-cm3="<?php echo esc_attr( (float) ( $material->price_per_cm3 ?? 0 ) ); ?>"
											data-density="<?php echo esc_attr( (float) ( $material->density ?? 0 ) ); ?>"
											data-machine-factor="<?php echo esc_attr( (float) ( $material->machine_time_factor ?? 1 ) ); ?>"
											data-surface-factor="<?php echo esc_attr( (float) ( $material->surface_quality_factor ?? 1 ) ); ?>"
											data-wastage-factor="<?php echo esc_attr( (float) ( $material->wastage_factor ?? 1 ) ); ?>"
											data-color-availability="<?php echo esc_attr( (string) ( $material->color_availability ?? '' ) ); ?>"
											<?php selected( (string) $old( 'material_id' ), (string) (int) $material->id ); ?>
										><?php echo esc_html( $material->name ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>

							<div class="srf-form__field srf-project-configurator__wide">
								<label for="srf-print-profile"><?php esc_html_e( 'Process profile', 'service-requests-form' ); ?> <span class="srf-required">*</span></label>
								<select id="srf-print-profile" name="srf_print_profile" required data-srf-quote-input>
									<option value="custom" <?php selected( $selected_profile, 'custom' ); ?>><?php esc_html_e( 'Custom settings', 'service-requests-form' ); ?></option>
									<?php foreach ( $print_profiles as $profile_key => $profile ) : ?>
										<option value="<?php echo esc_attr( $profile_key ); ?>" <?php selected( $selected_profile, $profile_key ); ?>><?php echo esc_html( $profile['name'] ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="srf-form__help"><?php esc_html_e( 'Bambu process names follow the familiar Studio-style list. They are estimator presets, not imported slicer or G-code profiles.', 'service-requests-form' ); ?></p>
							</div>
						</div>

						<details class="srf-project-advanced" data-srf-advanced-settings>
							<summary><?php esc_html_e( 'Advanced print settings', 'service-requests-form' ); ?></summary>
							<div class="srf-project-profile-notice" data-srf-profile-notice hidden></div>
							<div class="srf-project-advanced__grid">
								<div class="srf-form__field"><label for="srf-layer-height"><?php esc_html_e( 'Layer height (mm)', 'service-requests-form' ); ?></label><input type="number" id="srf-layer-height" name="srf_layer_height" min="0.01" max="1" step="0.01" value="<?php echo esc_attr( $old( 'layer_height', '0.20' ) ); ?>" data-srf-quote-input /></div>
								<div class="srf-form__field"><label for="srf-infill"><?php esc_html_e( 'Infill (%)', 'service-requests-form' ); ?></label><input type="number" id="srf-infill" name="srf_infill" min="0" max="100" step="1" value="<?php echo esc_attr( $old( 'infill', '15' ) ); ?>" data-srf-quote-input /></div>
								<div class="srf-form__field"><label for="srf-wall-loops"><?php esc_html_e( 'Wall loops', 'service-requests-form' ); ?></label><input type="number" id="srf-wall-loops" name="srf_wall_loops" min="1" max="12" step="1" value="<?php echo esc_attr( $old( 'wall_loops', '2' ) ); ?>" data-srf-quote-input /></div>
								<div class="srf-form__field"><label for="srf-top-layers"><?php esc_html_e( 'Top layers', 'service-requests-form' ); ?></label><input type="number" id="srf-top-layers" name="srf_top_layers" min="0" max="30" step="1" value="<?php echo esc_attr( $old( 'top_layers', '4' ) ); ?>" data-srf-quote-input /></div>
								<div class="srf-form__field"><label for="srf-bottom-layers"><?php esc_html_e( 'Bottom layers', 'service-requests-form' ); ?></label><input type="number" id="srf-bottom-layers" name="srf_bottom_layers" min="0" max="30" step="1" value="<?php echo esc_attr( $old( 'bottom_layers', '3' ) ); ?>" data-srf-quote-input /></div>
								<div class="srf-form__field"><label for="srf-infill-pattern"><?php esc_html_e( 'Infill pattern', 'service-requests-form' ); ?></label><select id="srf-infill-pattern" name="srf_infill_pattern" data-srf-quote-input><option value="grid" <?php selected( $old( 'infill_pattern', 'grid' ), 'grid' ); ?>><?php esc_html_e( 'Grid', 'service-requests-form' ); ?></option><option value="gyroid" <?php selected( $old( 'infill_pattern' ), 'gyroid' ); ?>><?php esc_html_e( 'Gyroid', 'service-requests-form' ); ?></option><option value="lines" <?php selected( $old( 'infill_pattern' ), 'lines' ); ?>><?php esc_html_e( 'Lines', 'service-requests-form' ); ?></option><option value="cubic" <?php selected( $old( 'infill_pattern' ), 'cubic' ); ?>><?php esc_html_e( 'Cubic', 'service-requests-form' ); ?></option><option value="honeycomb" <?php selected( $old( 'infill_pattern' ), 'honeycomb' ); ?>><?php esc_html_e( 'Honeycomb', 'service-requests-form' ); ?></option></select></div>
							</div>
						</details>

						<div class="srf-project-configurator__grid srf-project-configurator__grid--secondary">
							<div class="srf-form__field"><label for="srf-shell-mode"><?php esc_html_e( 'Structure', 'service-requests-form' ); ?></label><select id="srf-shell-mode" name="srf_shell_mode" data-srf-quote-input><option value="solid" <?php selected( $old( 'shell_mode', 'solid' ), 'solid' ); ?>><?php esc_html_e( 'Shell + selected infill', 'service-requests-form' ); ?></option><option value="hollow" <?php selected( $old( 'shell_mode' ), 'hollow' ); ?>><?php esc_html_e( 'Shell only / hollow', 'service-requests-form' ); ?></option></select></div>
							<div class="srf-form__field"><label for="srf-scale"><?php esc_html_e( 'Scale (%)', 'service-requests-form' ); ?></label><input type="number" id="srf-scale" name="srf_scale" min="10" max="500" step="1" value="<?php echo esc_attr( $old( 'scale', '100' ) ); ?>" data-srf-quote-input /></div>
							<div class="srf-form__field"><label for="srf-quantity"><?php esc_html_e( 'Quantity', 'service-requests-form' ); ?></label><input type="number" id="srf-quantity" name="srf_quantity" min="1" max="999" step="1" value="<?php echo esc_attr( $old( 'quantity', '1' ) ); ?>" data-srf-quote-input /></div>
							<div class="srf-form__field srf-form__field--checkbox"><label><input type="checkbox" name="srf_supports" value="1" <?php checked( $old( 'supports', '0' ), '1' ); ?> data-srf-quote-input /> <span><?php esc_html_e( 'Generate support structures', 'service-requests-form' ); ?></span></label></div>
						</div>

						<div class="srf-form__field">
							<label for="srf-quote-notes"><?php esc_html_e( 'Print notes', 'service-requests-form' ); ?></label>
							<textarea id="srf-quote-notes" name="srf_quote_notes" rows="5" maxlength="4000"><?php echo esc_textarea( $old( 'notes' ) ); ?></textarea>
						</div>
					</div>

					<aside
						class="srf-project-card srf-project-summary"
						data-srf-quote-summary
						data-currency-symbol="<?php echo esc_attr( $currency_symbol ); ?>"
						data-tax-rate="<?php echo esc_attr( $tax_rate ); ?>"
						data-service-fee="<?php echo esc_attr( $service_fee ); ?>"
						data-setup-fee="<?php echo esc_attr( $setup_fee ); ?>"
						data-profit-margin="<?php echo esc_attr( $profit_margin ); ?>"
					>
						<div class="srf-project-summary__header"><div><p><?php esc_html_e( 'Live estimate', 'service-requests-form' ); ?></p><h3><?php esc_html_e( 'Project total', 'service-requests-form' ); ?></h3></div><strong data-srf-price-total>—</strong></div>
						<div class="srf-project-estimate-status" data-srf-estimate-status data-type="info"><?php esc_html_e( 'Select a model, printer, and material to see an estimate.', 'service-requests-form' ); ?></div>
						<dl class="srf-project-summary__selection">
							<div><dt><?php esc_html_e( 'Printer', 'service-requests-form' ); ?></dt><dd data-srf-summary-printer>—</dd></div>
							<div><dt><?php esc_html_e( 'Material', 'service-requests-form' ); ?></dt><dd data-srf-summary-material>—</dd></div>
							<div><dt><?php esc_html_e( 'Process', 'service-requests-form' ); ?></dt><dd data-srf-summary-profile>—</dd></div>
							<div><dt><?php esc_html_e( 'Layer', 'service-requests-form' ); ?></dt><dd data-srf-summary-layer>—</dd></div>
							<div><dt><?php esc_html_e( 'Quantity', 'service-requests-form' ); ?></dt><dd data-srf-summary-quantity>—</dd></div>
						</dl>
						<dl class="srf-project-summary__costs">
							<div><dt><?php esc_html_e( 'Estimated printed volume', 'service-requests-form' ); ?></dt><dd data-srf-price-volume>—</dd></div>
							<div><dt><?php esc_html_e( 'Estimated material weight', 'service-requests-form' ); ?></dt><dd data-srf-price-weight>—</dd></div>
							<div><dt><?php esc_html_e( 'Material', 'service-requests-form' ); ?></dt><dd data-srf-price-material>—</dd></div>
							<div><dt><?php esc_html_e( 'Machine time', 'service-requests-form' ); ?></dt><dd data-srf-price-printer>—</dd></div>
							<div><dt><?php esc_html_e( 'Fees and margin', 'service-requests-form' ); ?></dt><dd data-srf-price-fees>—</dd></div>
							<div><dt><?php esc_html_e( 'Tax', 'service-requests-form' ); ?></dt><dd data-srf-price-tax>—</dd></div>
							<div><dt><?php esc_html_e( 'Estimated print time', 'service-requests-form' ); ?></dt><dd data-srf-price-time>—</dd></div>
						</dl>
						<div class="srf-project-fit-status" data-srf-fit-status data-fit="unknown"><?php esc_html_e( 'Build-volume check occurs after model analysis.', 'service-requests-form' ); ?></div>
						<p class="srf-project-summary__disclaimer"><?php esc_html_e( 'This is a geometry-based production estimate, not a Bambu Studio slicing result. The server is authoritative and may reject open, damaged, unsupported, or oversized models before checkout.', 'service-requests-form' ); ?></p>
					</aside>
				</div>

				<div class="srf-project-card srf-project-terms">
					<label>
						<input type="checkbox" name="srf_terms" value="1" <?php checked( $old( 'terms', '0' ), '1' ); ?> required />
						<span>
							<?php
							if ( $terms_url ) {
								echo wp_kses_post( sprintf( __( 'I confirm that I have the right to manufacture these files and accept the <a href="%s" target="_blank" rel="noopener noreferrer">Terms & Conditions</a>.', 'service-requests-form' ), esc_url( $terms_url ) ) );
							} else {
								esc_html_e( 'I confirm that I have the right to manufacture these files and accept the Terms & Conditions.', 'service-requests-form' );
							}
							?>
						</span>
					</label>
				</div>

				<?php if ( $checkout_requested && ! $woocommerce_available ) : ?>
					<div class="srf-project-notice srf-project-notice--warning" role="status"><?php esc_html_e( 'Online payment is enabled in plugin settings, but WooCommerce is not currently available. The quote can still be saved and the team will contact the customer.', 'service-requests-form' ); ?></div>
				<?php endif; ?>

				<div class="srf-project-step-error" data-srf-step-error="3" role="alert" hidden></div>
				<div class="srf-form__actions srf-form__actions--project">
					<button type="button" class="srf-button srf-button--secondary" data-srf-prev-step="2"><?php esc_html_e( 'Back', 'service-requests-form' ); ?></button>
					<button type="submit" class="srf-button srf-project-submit" data-srf-project-submit <?php disabled( empty( $printers ) || empty( $materials ) ); ?>><span data-srf-submit-label><?php echo esc_html( $submit_label ); ?></span></button>
				</div>
			</section>

			<div class="srf-project-honeypot" aria-hidden="true"><label for="srf-company-website"><?php esc_html_e( 'Website', 'service-requests-form' ); ?></label><input type="text" id="srf-company-website" name="srf_company_website" value="" tabindex="-1" autocomplete="off" /></div>
			<input type="hidden" name="srf_project_form_submitted" value="1" />
			<?php wp_nonce_field( 'srf_submit_project_request', 'srf_project_nonce' ); ?>

			<div class="srf-project-submit-overlay" data-srf-submit-overlay hidden role="status" aria-live="polite">
				<span class="srf-project-spinner" aria-hidden="true"></span>
				<strong><?php esc_html_e( 'Uploading models and calculating the secure quote…', 'service-requests-form' ); ?></strong>
				<small><?php esc_html_e( 'Keep this page open. Large models may take a little longer on the server.', 'service-requests-form' ); ?></small>
			</div>
		</form>
	<?php endif; ?>
</div>
