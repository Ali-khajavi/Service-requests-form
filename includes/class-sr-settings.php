<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SR_Settings' ) ) {
	class SR_Settings {

		const PAGE_SLUG = 'srf-settings';

		const OPTION_CURRENCY            = 'srf_quote_currency';
		const OPTION_CURRENCY_SYMBOL     = 'srf_quote_currency_symbol';
		const OPTION_TAX_RATE            = 'srf_quote_tax_rate';
		const OPTION_SERVICE_FEE         = 'srf_quote_service_fee';
		const OPTION_SETUP_FEE           = 'srf_quote_setup_fee';
		const OPTION_PROFIT_MARGIN       = 'srf_quote_profit_margin';
		const OPTION_MAX_UPLOAD_SIZE     = 'srf_quote_max_upload_size';
		const OPTION_ALLOWED_EXTENSIONS  = 'srf_quote_allowed_extensions';
		const OPTION_GUEST_ORDERING      = 'srf_quote_guest_ordering'; // Legacy compatibility.
		const OPTION_DELETE_ON_UNINSTALL = 'srf_quote_delete_data_on_uninstall';
		const OPTION_NOTIFY_ADMIN_EMAIL  = 'srf_quote_notify_admin_email';
		const OPTION_COMING_SOON         = 'srf_coming_soon_enabled'; // Legacy compatibility.
		const OPTION_COMING_SOON_SERVICE = 'srf_coming_soon_service_enabled';
		const OPTION_COMING_SOON_PROJECT = 'srf_coming_soon_project_enabled';
		const OPTION_PROJECT_ACCESS_MODE = 'srf_project_access_mode';
		const OPTION_PROJECT_CHECKOUT    = 'srf_project_checkout_enabled';
		const OPTION_FRONTEND_LANGUAGE  = 'srf_frontend_language';
		const OPTION_ADMIN_LANGUAGE     = 'srf_admin_language';

		public static function init() {
			add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
			add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		}

		public static function register_menu() {
			$parent = class_exists( 'SRF_Admin_Menu' ) ? SRF_Admin_Menu::PARENT_SLUG : 'options-general.php';
			add_submenu_page(
				$parent,
				__( 'Service Requests Settings', 'service-requests-form' ),
				__( 'Settings', 'service-requests-form' ),
				'manage_options',
				self::PAGE_SLUG,
				array( __CLASS__, 'render_settings_page' )
			);
		}

		public static function register_settings() {
			$boolean_options = array(
				SRF_Google_Auth::OPTION_ENABLED         => false,
				self::OPTION_GUEST_ORDERING             => false,
				self::OPTION_DELETE_ON_UNINSTALL         => false,
				self::OPTION_COMING_SOON                 => false,
				self::OPTION_COMING_SOON_SERVICE         => false,
				self::OPTION_COMING_SOON_PROJECT         => false,
				self::OPTION_PROJECT_CHECKOUT            => true,
			);
			if ( class_exists( 'SRF_Print_Profiles' ) ) {
				$boolean_options[ SRF_Print_Profiles::OPTION_BAMBU_PRESETS_ENABLED ] = true;
			}

			foreach ( $boolean_options as $option => $default ) {
				register_setting(
					'srf_settings_group',
					$option,
					array(
						'type'              => 'boolean',
						'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
						'default'           => $default,
					)
				);
			}

			register_setting( 'srf_settings_group', SRF_Google_Auth::OPTION_CLIENT_ID, array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ) );
			register_setting( 'srf_settings_group', SRF_Google_Auth::OPTION_CLIENT_SECRET, array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ) );
			register_setting( 'srf_settings_group', SRF_Google_Auth::OPTION_REDIRECT_URI, array( 'type' => 'string', 'sanitize_callback' => 'esc_url_raw', 'default' => '' ) );

			register_setting( 'srf_settings_group', self::OPTION_CURRENCY, array( 'type' => 'string', 'sanitize_callback' => array( __CLASS__, 'sanitize_currency' ), 'default' => 'EUR' ) );
			register_setting( 'srf_settings_group', self::OPTION_CURRENCY_SYMBOL, array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '€' ) );

			foreach (
				array(
					self::OPTION_TAX_RATE      => 0,
					self::OPTION_SERVICE_FEE   => 5,
					self::OPTION_SETUP_FEE     => 0,
					self::OPTION_PROFIT_MARGIN => 20,
				) as $option => $default
			) {
				register_setting(
					'srf_settings_group',
					$option,
					array(
						'type'              => 'number',
						'sanitize_callback' => array( __CLASS__, 'sanitize_non_negative_float' ),
						'default'           => $default,
					)
				);
			}

			register_setting( 'srf_settings_group', self::OPTION_MAX_UPLOAD_SIZE, array( 'type' => 'integer', 'sanitize_callback' => array( __CLASS__, 'sanitize_positive_int' ), 'default' => 500 ) );
			register_setting( 'srf_settings_group', self::OPTION_ALLOWED_EXTENSIONS, array( 'type' => 'string', 'sanitize_callback' => array( __CLASS__, 'sanitize_extensions_csv' ), 'default' => 'stl,obj,3mf' ) );
			register_setting( 'srf_settings_group', self::OPTION_NOTIFY_ADMIN_EMAIL, array( 'type' => 'string', 'sanitize_callback' => 'sanitize_email', 'default' => '' ) );
			register_setting( 'srf_settings_group', self::OPTION_PROJECT_ACCESS_MODE, array( 'type' => 'string', 'sanitize_callback' => array( __CLASS__, 'sanitize_project_access_mode' ), 'default' => 'registered' ) );
			register_setting( 'srf_settings_group', self::OPTION_FRONTEND_LANGUAGE, array( 'type' => 'string', 'sanitize_callback' => array( __CLASS__, 'sanitize_language' ), 'default' => 'site' ) );
			register_setting( 'srf_settings_group', self::OPTION_ADMIN_LANGUAGE, array( 'type' => 'string', 'sanitize_callback' => array( __CLASS__, 'sanitize_language' ), 'default' => 'site' ) );

			if ( class_exists( 'SRF_Print_Profiles' ) ) {
				register_setting( 'srf_settings_group', SRF_Print_Profiles::OPTION_BAMBU_HOURLY_COST, array( 'type' => 'number', 'sanitize_callback' => array( __CLASS__, 'sanitize_non_negative_float' ), 'default' => 8 ) );
				register_setting( 'srf_settings_group', SRF_Print_Profiles::OPTION_BAMBU_MATERIAL_KG, array( 'type' => 'number', 'sanitize_callback' => array( __CLASS__, 'sanitize_non_negative_float' ), 'default' => 25 ) );
			}

			if ( class_exists( 'SRF_WooCommerce' ) ) {
				register_setting( 'srf_settings_group', SRF_WooCommerce::OPTION_FORM_PAGE_ID, array( 'type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 0 ) );
				register_setting( 'srf_settings_group', SRF_WooCommerce::OPTION_AFTER_SUBMIT, array( 'type' => 'string', 'sanitize_callback' => array( __CLASS__, 'sanitize_after_submit_target' ), 'default' => 'checkout' ) );
				register_setting( 'srf_settings_group', SRF_WooCommerce::OPTION_PROJECT_AFTER_SUBMIT, array( 'type' => 'string', 'sanitize_callback' => array( __CLASS__, 'sanitize_after_submit_target' ), 'default' => 'checkout' ) );
			}
		}

		public static function sanitize_checkbox( $value ) {
			return ! empty( $value ) ? 1 : 0;
		}

		public static function sanitize_non_negative_float( $value ) {
			return max( 0, is_scalar( $value ) ? (float) $value : 0 );
		}

		public static function sanitize_positive_int( $value ) {
			return max( 1, (int) $value );
		}

		public static function sanitize_currency( $value ) {
			$value = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $value ) );
			return $value ? substr( $value, 0, 3 ) : 'EUR';
		}

		public static function sanitize_after_submit_target( $value ) {
			$value = sanitize_key( (string) $value );
			return in_array( $value, array( 'cart', 'checkout' ), true ) ? $value : 'checkout';
		}

		public static function sanitize_project_access_mode( $value ) {
			$value = sanitize_key( (string) $value );
			return in_array( $value, array( 'everyone', 'public' ), true ) ? 'public' : 'registered';
		}

		public static function sanitize_language( $value ) {
			if ( class_exists( 'SRF_Language' ) ) {
				return SRF_Language::sanitize_language( $value );
			}

			$value = is_scalar( $value ) ? (string) $value : 'site';
			return in_array( $value, array( 'site', 'en_US', 'de_DE' ), true ) ? $value : 'site';
		}

		public static function sanitize_extensions_csv( $value ) {
			if ( is_array( $value ) ) {
				$value = implode( ',', $value );
			}
			$clean = array();
			foreach ( explode( ',', strtolower( (string) $value ) ) as $extension ) {
				$extension = ltrim( sanitize_file_name( trim( $extension ) ), '.' );
				if ( '' !== $extension ) {
					$clean[] = $extension;
				}
			}
			$clean = array_values( array_unique( $clean ) );
			return implode( ',', $clean ? $clean : array( 'stl', 'obj', '3mf' ) );
		}

		public static function get_project_access_mode() {
			$stored = get_option( self::OPTION_PROJECT_ACCESS_MODE, null );

			// Preserve the old guest-ordering preference when upgrading from a
			// release that did not yet have the explicit access-mode option.
			if ( null === $stored ) {
				return (bool) get_option( self::OPTION_GUEST_ORDERING, false ) ? 'public' : 'registered';
			}

			return self::sanitize_project_access_mode( $stored );
		}

		public static function project_guests_allowed() {
			return 'public' === self::get_project_access_mode();
		}

		public static function is_project_public() {
			return 'public' === self::get_project_access_mode();
		}

		public static function project_checkout_enabled() {
			return (bool) get_option( self::OPTION_PROJECT_CHECKOUT, true );
		}

		/** Backward-compatible method name used by an early 0.10.55 build. */
		public static function is_project_checkout_enabled() {
			return self::project_checkout_enabled();
		}

		public static function get_quote_settings() {
			$currency        = self::sanitize_currency( get_option( self::OPTION_CURRENCY, 'EUR' ) );
			$currency_symbol = (string) get_option( self::OPTION_CURRENCY_SYMBOL, '€' );

			// Checkout totals must use the WooCommerce store currency.
			if ( self::project_checkout_enabled() && function_exists( 'get_woocommerce_currency' ) ) {
				$currency = self::sanitize_currency( get_woocommerce_currency() );
				if ( function_exists( 'get_woocommerce_currency_symbol' ) ) {
					$currency_symbol = (string) get_woocommerce_currency_symbol( $currency );
				}
			}

			return array(
				'currency'                 => $currency,
				'currency_symbol'          => $currency_symbol,
				'tax_rate'                 => max( 0, (float) get_option( self::OPTION_TAX_RATE, 0 ) ),
				'service_fee'              => max( 0, (float) get_option( self::OPTION_SERVICE_FEE, 5 ) ),
				'setup_fee'                => max( 0, (float) get_option( self::OPTION_SETUP_FEE, 0 ) ),
				'profit_margin'            => max( 0, (float) get_option( self::OPTION_PROFIT_MARGIN, 20 ) ),
				'max_upload_size'          => max( 1, (int) get_option( self::OPTION_MAX_UPLOAD_SIZE, 500 ) ),
				'allowed_extensions'       => (string) get_option( self::OPTION_ALLOWED_EXTENSIONS, 'stl,obj,3mf' ),
				'project_access_mode'      => self::get_project_access_mode(),
				'guest_ordering'           => self::project_guests_allowed(),
				'project_checkout_enabled' => self::project_checkout_enabled(),
			);
		}

		public static function render_settings_page() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$settings             = self::get_quote_settings();
			$google_enabled       = (bool) get_option( SRF_Google_Auth::OPTION_ENABLED, false );
			$google_client_id     = (string) get_option( SRF_Google_Auth::OPTION_CLIENT_ID, '' );
			$google_client_secret = (string) get_option( SRF_Google_Auth::OPTION_CLIENT_SECRET, '' );
			$google_redirect_uri  = (string) get_option( SRF_Google_Auth::OPTION_REDIRECT_URI, '' );
			$coming_service       = (bool) get_option( self::OPTION_COMING_SOON_SERVICE, false );
			$coming_project       = (bool) get_option( self::OPTION_COMING_SOON_PROJECT, false );
			$delete_on_uninstall  = (bool) get_option( self::OPTION_DELETE_ON_UNINSTALL, false );
			$notify_admin_email   = (string) get_option( self::OPTION_NOTIFY_ADMIN_EMAIL, '' );
			$bambu_enabled        = class_exists( 'SRF_Print_Profiles' ) ? SRF_Print_Profiles::is_enabled() : false;
			$bambu_hourly         = class_exists( 'SRF_Print_Profiles' ) ? (float) get_option( SRF_Print_Profiles::OPTION_BAMBU_HOURLY_COST, 8 ) : 8;
			$bambu_material_kg     = class_exists( 'SRF_Print_Profiles' ) ? (float) get_option( SRF_Print_Profiles::OPTION_BAMBU_MATERIAL_KG, 25 ) : 25;
			$service_form_page_id = class_exists( 'SRF_WooCommerce' ) ? (int) get_option( SRF_WooCommerce::OPTION_FORM_PAGE_ID, 0 ) : 0;
			$service_after_submit = class_exists( 'SRF_WooCommerce' ) ? (string) get_option( SRF_WooCommerce::OPTION_AFTER_SUBMIT, 'checkout' ) : 'checkout';
			$project_after_submit = class_exists( 'SRF_WooCommerce' ) ? (string) get_option( SRF_WooCommerce::OPTION_PROJECT_AFTER_SUBMIT, 'checkout' ) : 'checkout';
			$bambu_action_url     = wp_nonce_url( admin_url( 'admin-post.php?action=srf_install_bambu_profiles' ), 'srf_install_bambu_profiles' );
			$frontend_language    = self::sanitize_language( get_option( self::OPTION_FRONTEND_LANGUAGE, 'site' ) );
			$admin_language       = self::sanitize_language( get_option( self::OPTION_ADMIN_LANGUAGE, 'site' ) );
			$language_choices     = class_exists( 'SRF_Language' ) ? SRF_Language::choices() : array( 'site' => __( 'Use WordPress language', 'service-requests-form' ), 'en_US' => __( 'English', 'service-requests-form' ), 'de_DE' => __( 'German (Deutsch)', 'service-requests-form' ) );
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Service Requests Settings', 'service-requests-form' ); ?></h1>
				<p><?php esc_html_e( 'Version 0.10.90 introduces a GPU-accelerated studio model viewer with selectable colours, smooth or flat shading, wireframe, a printer build plate, orientation controls, and build-volume guidance.', 'service-requests-form' ); ?></p>

				<?php if ( isset( $_GET['srf_bambu_installed'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
					<div class="notice notice-success is-dismissible"><p>
						<?php
						printf(
							esc_html__( 'Bambu starter data checked. Added %1$d material and %2$d printers. Existing rows were not overwritten.', 'service-requests-form' ),
							isset( $_GET['materials_added'] ) ? absint( $_GET['materials_added'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
							isset( $_GET['printers_added'] ) ? absint( $_GET['printers_added'] ) : 0 // phpcs:ignore WordPress.Security.NonceVerification.Recommended
						);
						?>
					</p></div>
				<?php endif; ?>

				<form method="post" action="options.php">
					<?php settings_fields( 'srf_settings_group' ); ?>

					<h2><?php esc_html_e( 'Plugin language', 'service-requests-form' ); ?></h2>
					<p><?php esc_html_e( 'Choose the language used by this plugin without changing the language of the rest of the website.', 'service-requests-form' ); ?></p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="srf_frontend_language"><?php esc_html_e( 'Frontend UI language', 'service-requests-form' ); ?></label></th>
							<td>
								<select id="srf_frontend_language" name="<?php echo esc_attr( self::OPTION_FRONTEND_LANGUAGE ); ?>">
									<?php foreach ( $language_choices as $language_value => $language_label ) : ?>
										<option value="<?php echo esc_attr( $language_value ); ?>" <?php selected( $frontend_language, $language_value ); ?>><?php echo esc_html( $language_label ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Controls the project form, predefined service form, customer account pages, validation messages, and customer-facing plugin text.', 'service-requests-form' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="srf_admin_language"><?php esc_html_e( 'Plugin admin language', 'service-requests-form' ); ?></label></th>
							<td>
								<select id="srf_admin_language" name="<?php echo esc_attr( self::OPTION_ADMIN_LANGUAGE ); ?>">
									<?php foreach ( $language_choices as $language_value => $language_label ) : ?>
										<option value="<?php echo esc_attr( $language_value ); ?>" <?php selected( $admin_language, $language_value ); ?>><?php echo esc_html( $language_label ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Controls Service Requests dashboard, settings, requests, services, materials, printers, and plugin notices in wp-admin.', 'service-requests-form' ); ?></p>
							</td>
						</tr>
					</table>
					<p class="description"><strong><?php esc_html_e( 'Save changes and reload the page to apply a new language.', 'service-requests-form' ); ?></strong></p>

					<h2><?php esc_html_e( 'Form availability', 'service-requests-form' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Predefined service form', 'service-requests-form' ); ?></th>
							<td><input type="hidden" name="<?php echo esc_attr( self::OPTION_COMING_SOON_SERVICE ); ?>" value="0" /><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_COMING_SOON_SERVICE ); ?>" value="1" <?php checked( $coming_service ); ?> /> <?php esc_html_e( 'Show a coming-soon screen instead of [service_request_form].', 'service-requests-form' ); ?></label></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( '3D project form', 'service-requests-form' ); ?></th>
							<td><input type="hidden" name="<?php echo esc_attr( self::OPTION_COMING_SOON_PROJECT ); ?>" value="0" /><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_COMING_SOON_PROJECT ); ?>" value="1" <?php checked( $coming_project ); ?> /> <?php esc_html_e( 'Show a coming-soon screen instead of [project_request_form].', 'service-requests-form' ); ?></label></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Who can order a custom print?', 'service-requests-form' ); ?></th>
							<td>
								<label><input type="radio" name="<?php echo esc_attr( self::OPTION_PROJECT_ACCESS_MODE ); ?>" value="registered" <?php checked( $settings['project_access_mode'], 'registered' ); ?> /> <?php esc_html_e( 'Registered website users only', 'service-requests-form' ); ?></label><br />
								<label><input type="radio" name="<?php echo esc_attr( self::OPTION_PROJECT_ACCESS_MODE ); ?>" value="public" <?php checked( $settings['project_access_mode'], 'public' ); ?> /> <?php esc_html_e( 'Everyone, including guests', 'service-requests-form' ); ?></label>
								<p class="description"><?php esc_html_e( 'Guest name and email are collected in the project form. WooCommerce collects billing and delivery information during checkout.', 'service-requests-form' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Project payment', 'service-requests-form' ); ?></th>
							<td>
								<input type="hidden" name="<?php echo esc_attr( self::OPTION_PROJECT_CHECKOUT ); ?>" value="0" />
								<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_PROJECT_CHECKOUT ); ?>" value="1" <?php checked( $settings['project_checkout_enabled'] ); ?> /> <?php esc_html_e( 'Require the calculated project amount to be paid through WooCommerce.', 'service-requests-form' ); ?></label>
								<p class="description"><?php esc_html_e( 'When enabled, the request remains Pending payment and the production notification is sent only after the WooCommerce order becomes paid.', 'service-requests-form' ); ?></p>
							</td>
						</tr>
					</table>

					<h2><?php esc_html_e( 'Project quote and upload defaults', 'service-requests-form' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr><th scope="row"><label for="srf_quote_currency"><?php esc_html_e( 'Currency code', 'service-requests-form' ); ?></label></th><td><input type="text" maxlength="3" id="srf_quote_currency" class="small-text" name="<?php echo esc_attr( self::OPTION_CURRENCY ); ?>" value="<?php echo esc_attr( $settings['currency'] ); ?>" /></td></tr>
						<tr><th scope="row"><label for="srf_quote_currency_symbol"><?php esc_html_e( 'Currency symbol', 'service-requests-form' ); ?></label></th><td><input type="text" id="srf_quote_currency_symbol" class="small-text" name="<?php echo esc_attr( self::OPTION_CURRENCY_SYMBOL ); ?>" value="<?php echo esc_attr( $settings['currency_symbol'] ); ?>" /></td></tr>
						<tr><th scope="row"><label for="srf_quote_tax_rate"><?php esc_html_e( 'Tax rate (%)', 'service-requests-form' ); ?></label></th><td><input type="number" min="0" step="0.01" id="srf_quote_tax_rate" class="small-text" name="<?php echo esc_attr( self::OPTION_TAX_RATE ); ?>" value="<?php echo esc_attr( $settings['tax_rate'] ); ?>" /></td></tr>
						<tr><th scope="row"><label for="srf_quote_service_fee"><?php esc_html_e( 'Service fee', 'service-requests-form' ); ?></label></th><td><input type="number" min="0" step="0.01" id="srf_quote_service_fee" class="small-text" name="<?php echo esc_attr( self::OPTION_SERVICE_FEE ); ?>" value="<?php echo esc_attr( $settings['service_fee'] ); ?>" /></td></tr>
						<tr><th scope="row"><label for="srf_quote_setup_fee"><?php esc_html_e( 'Setup fee', 'service-requests-form' ); ?></label></th><td><input type="number" min="0" step="0.01" id="srf_quote_setup_fee" class="small-text" name="<?php echo esc_attr( self::OPTION_SETUP_FEE ); ?>" value="<?php echo esc_attr( $settings['setup_fee'] ); ?>" /></td></tr>
						<tr><th scope="row"><label for="srf_quote_profit_margin"><?php esc_html_e( 'Profit margin (%)', 'service-requests-form' ); ?></label></th><td><input type="number" min="0" step="0.01" id="srf_quote_profit_margin" class="small-text" name="<?php echo esc_attr( self::OPTION_PROFIT_MARGIN ); ?>" value="<?php echo esc_attr( $settings['profit_margin'] ); ?>" /></td></tr>
						<tr><th scope="row"><label for="srf_quote_max_upload_size"><?php esc_html_e( 'Maximum total upload (MB)', 'service-requests-form' ); ?></label></th><td><input type="number" min="1" step="1" id="srf_quote_max_upload_size" class="small-text" name="<?php echo esc_attr( self::OPTION_MAX_UPLOAD_SIZE ); ?>" value="<?php echo esc_attr( $settings['max_upload_size'] ); ?>" /><p class="description"><?php esc_html_e( 'The web server and PHP upload limits can still be lower.', 'service-requests-form' ); ?></p></td></tr>
						<tr><th scope="row"><label for="srf_quote_allowed_extensions"><?php esc_html_e( 'Project model extensions', 'service-requests-form' ); ?></label></th><td><input type="text" id="srf_quote_allowed_extensions" class="regular-text" name="<?php echo esc_attr( self::OPTION_ALLOWED_EXTENSIONS ); ?>" value="<?php echo esc_attr( $settings['allowed_extensions'] ); ?>" /><p class="description"><?php esc_html_e( 'Automatic checkout pricing supports STL, OBJ, and 3MF. Keep these extensions enabled for the project-order workflow.', 'service-requests-form' ); ?></p></td></tr>
						<tr><th scope="row"><label for="srf_quote_notify_admin_email"><?php esc_html_e( 'Production notification email', 'service-requests-form' ); ?></label></th><td><input type="email" id="srf_quote_notify_admin_email" class="regular-text" name="<?php echo esc_attr( self::OPTION_NOTIFY_ADMIN_EMAIL ); ?>" value="<?php echo esc_attr( $notify_admin_email ); ?>" /><p class="description"><?php esc_html_e( 'Falls back to the WordPress administration email when blank.', 'service-requests-form' ); ?></p></td></tr>
					</table>

					<?php if ( class_exists( 'SRF_Print_Profiles' ) ) : ?>
						<h2><?php esc_html_e( 'Bambu Lab starter profiles', 'service-requests-form' ); ?></h2>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><?php esc_html_e( 'Enable Bambu presets', 'service-requests-form' ); ?></th>
								<td><input type="hidden" name="<?php echo esc_attr( SRF_Print_Profiles::OPTION_BAMBU_PRESETS_ENABLED ); ?>" value="0" /><label><input type="checkbox" name="<?php echo esc_attr( SRF_Print_Profiles::OPTION_BAMBU_PRESETS_ENABLED ); ?>" value="1" <?php checked( $bambu_enabled ); ?> /> <?php esc_html_e( 'Offer the built-in Bambu process list on Bambu printers.', 'service-requests-form' ); ?></label></td>
							</tr>
							<tr><th scope="row"><label for="srf_bambu_hourly_cost"><?php esc_html_e( 'Starter printer hourly cost', 'service-requests-form' ); ?></label></th><td><input type="number" min="0" step="0.01" id="srf_bambu_hourly_cost" class="small-text" name="<?php echo esc_attr( SRF_Print_Profiles::OPTION_BAMBU_HOURLY_COST ); ?>" value="<?php echo esc_attr( $bambu_hourly ); ?>" /> <?php echo esc_html( $settings['currency'] ); ?>/h</td></tr>
							<tr><th scope="row"><label for="srf_bambu_material_price_per_kg"><?php esc_html_e( 'Starter PLA price per kg', 'service-requests-form' ); ?></label></th><td><input type="number" min="0" step="0.01" id="srf_bambu_material_price_per_kg" class="small-text" name="<?php echo esc_attr( SRF_Print_Profiles::OPTION_BAMBU_MATERIAL_KG ); ?>" value="<?php echo esc_attr( $bambu_material_kg ); ?>" /> <?php echo esc_html( $settings['currency'] ); ?>/kg</td></tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Starter data', 'service-requests-form' ); ?></th>
								<td><a class="button" href="<?php echo esc_url( $bambu_action_url ); ?>"><?php esc_html_e( 'Install missing Bambu material and printers', 'service-requests-form' ); ?></a><p class="description"><?php esc_html_e( 'Adds Bambu PLA Basic plus X1 Carbon, P1S, P1P, A1, and A1 mini when missing. Existing material and printer rows are never overwritten. Configure real workshop costs under Materials and Printers before going live.', 'service-requests-form' ); ?></p></td>
							</tr>
						</table>
					<?php endif; ?>

					<?php if ( class_exists( 'SRF_WooCommerce' ) ) : ?>
						<h2><?php esc_html_e( 'WooCommerce routing', 'service-requests-form' ); ?></h2>
						<table class="form-table" role="presentation">
							<tr><th scope="row"><label for="srf_service_form_page_id"><?php esc_html_e( 'Predefined service form page', 'service-requests-form' ); ?></label></th><td><?php wp_dropdown_pages( array( 'name' => SRF_WooCommerce::OPTION_FORM_PAGE_ID, 'id' => 'srf_service_form_page_id', 'selected' => $service_form_page_id, 'show_option_none' => __( 'Select a page', 'service-requests-form' ), 'option_none_value' => 0 ) ); ?><p class="description"><?php esc_html_e( 'Select the page containing [service_request_form].', 'service-requests-form' ); ?></p></td></tr>
							<tr><th scope="row"><?php esc_html_e( 'After predefined service submit', 'service-requests-form' ); ?></th><td><label><input type="radio" name="<?php echo esc_attr( SRF_WooCommerce::OPTION_AFTER_SUBMIT ); ?>" value="checkout" <?php checked( $service_after_submit, 'checkout' ); ?> /> <?php esc_html_e( 'Checkout', 'service-requests-form' ); ?></label><br /><label><input type="radio" name="<?php echo esc_attr( SRF_WooCommerce::OPTION_AFTER_SUBMIT ); ?>" value="cart" <?php checked( $service_after_submit, 'cart' ); ?> /> <?php esc_html_e( 'Cart', 'service-requests-form' ); ?></label></td></tr>
							<tr><th scope="row"><?php esc_html_e( 'After project quote', 'service-requests-form' ); ?></th><td><label><input type="radio" name="<?php echo esc_attr( SRF_WooCommerce::OPTION_PROJECT_AFTER_SUBMIT ); ?>" value="checkout" <?php checked( $project_after_submit, 'checkout' ); ?> /> <?php esc_html_e( 'Checkout', 'service-requests-form' ); ?></label><br /><label><input type="radio" name="<?php echo esc_attr( SRF_WooCommerce::OPTION_PROJECT_AFTER_SUBMIT ); ?>" value="cart" <?php checked( $project_after_submit, 'cart' ); ?> /> <?php esc_html_e( 'Cart', 'service-requests-form' ); ?></label></td></tr>
						</table>
					<?php endif; ?>

					<h2><?php esc_html_e( 'Google login', 'service-requests-form' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr><th scope="row"><?php esc_html_e( 'Enable Google login', 'service-requests-form' ); ?></th><td><input type="hidden" name="<?php echo esc_attr( SRF_Google_Auth::OPTION_ENABLED ); ?>" value="0" /><label><input type="checkbox" name="<?php echo esc_attr( SRF_Google_Auth::OPTION_ENABLED ); ?>" value="1" <?php checked( $google_enabled ); ?> /> <?php esc_html_e( 'Show Google login and registration buttons.', 'service-requests-form' ); ?></label></td></tr>
						<tr><th scope="row"><label for="srf_google_client_id"><?php esc_html_e( 'Client ID', 'service-requests-form' ); ?></label></th><td><input type="text" id="srf_google_client_id" class="regular-text" name="<?php echo esc_attr( SRF_Google_Auth::OPTION_CLIENT_ID ); ?>" value="<?php echo esc_attr( $google_client_id ); ?>" /></td></tr>
						<tr><th scope="row"><label for="srf_google_client_secret"><?php esc_html_e( 'Client secret', 'service-requests-form' ); ?></label></th><td><input type="password" id="srf_google_client_secret" class="regular-text" name="<?php echo esc_attr( SRF_Google_Auth::OPTION_CLIENT_SECRET ); ?>" value="<?php echo esc_attr( $google_client_secret ); ?>" autocomplete="new-password" /></td></tr>
						<tr><th scope="row"><label for="srf_google_redirect_uri"><?php esc_html_e( 'Redirect URI override', 'service-requests-form' ); ?></label></th><td><input type="url" id="srf_google_redirect_uri" class="regular-text" name="<?php echo esc_attr( SRF_Google_Auth::OPTION_REDIRECT_URI ); ?>" value="<?php echo esc_attr( $google_redirect_uri ); ?>" /><p class="description"><?php esc_html_e( 'Leave blank to use the plugin-generated callback URL.', 'service-requests-form' ); ?></p></td></tr>
					</table>

					<h2><?php esc_html_e( 'Data retention', 'service-requests-form' ); ?></h2>
					<table class="form-table" role="presentation"><tr><th scope="row"><?php esc_html_e( 'Delete plugin data on uninstall', 'service-requests-form' ); ?></th><td><input type="hidden" name="<?php echo esc_attr( self::OPTION_DELETE_ON_UNINSTALL ); ?>" value="0" /><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_DELETE_ON_UNINSTALL ); ?>" value="1" <?php checked( $delete_on_uninstall ); ?> /> <?php esc_html_e( 'Permanently delete plugin requests, request uploads, services, generated products, quote tables, settings, and plugin user metadata when the plugin is uninstalled. Leave disabled to preserve all data.', 'service-requests-form' ); ?></label></td></tr></table>

					<?php submit_button(); ?>
				</form>
			</div>
			<?php
		}
	}
}
