<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SR_Settings' ) ) {
	class SR_Settings {

		const PAGE_SLUG = 'srf-settings';

		const OPTION_CURRENCY             = 'srf_quote_currency';
		const OPTION_CURRENCY_SYMBOL      = 'srf_quote_currency_symbol';
		const OPTION_TAX_RATE             = 'srf_quote_tax_rate';
		const OPTION_SERVICE_FEE          = 'srf_quote_service_fee';
		const OPTION_SETUP_FEE            = 'srf_quote_setup_fee';
		const OPTION_PROFIT_MARGIN        = 'srf_quote_profit_margin';
		const OPTION_MAX_UPLOAD_SIZE      = 'srf_quote_max_upload_size';
		const OPTION_ALLOWED_EXTENSIONS   = 'srf_quote_allowed_extensions';
		const OPTION_GUEST_ORDERING       = 'srf_quote_guest_ordering';
		const OPTION_DELETE_ON_UNINSTALL  = 'srf_quote_delete_data_on_uninstall';
		const OPTION_NOTIFY_ADMIN_EMAIL   = 'srf_quote_notify_admin_email';

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

			/* =========================================================
			 * Existing Google Login settings
			 * ======================================================= */

			register_setting(
				'srf_settings_group',
				SRF_Google_Auth::OPTION_ENABLED,
				array(
					'type'              => 'boolean',
					'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
					'default'           => false,
				)
			);

			register_setting(
				'srf_settings_group',
				SRF_Google_Auth::OPTION_CLIENT_ID,
				array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => '',
				)
			);

			register_setting(
				'srf_settings_group',
				SRF_Google_Auth::OPTION_CLIENT_SECRET,
				array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => '',
				)
			);

			register_setting(
				'srf_settings_group',
				SRF_Google_Auth::OPTION_REDIRECT_URI,
				array(
					'type'              => 'string',
					'sanitize_callback' => 'esc_url_raw',
					'default'           => '',
				)
			);

			/* =========================================================
			 * 3D Quote general settings merged into SRF
			 * ======================================================= */

			register_setting(
				'srf_settings_group',
				self::OPTION_CURRENCY,
				array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => 'EUR',
				)
			);

			register_setting(
				'srf_settings_group',
				self::OPTION_CURRENCY_SYMBOL,
				array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => '€',
				)
			);

			register_setting(
				'srf_settings_group',
				self::OPTION_TAX_RATE,
				array(
					'type'              => 'number',
					'sanitize_callback' => array( __CLASS__, 'sanitize_non_negative_float' ),
					'default'           => 0,
				)
			);

			register_setting(
				'srf_settings_group',
				self::OPTION_SERVICE_FEE,
				array(
					'type'              => 'number',
					'sanitize_callback' => array( __CLASS__, 'sanitize_non_negative_float' ),
					'default'           => 5,
				)
			);

			register_setting(
				'srf_settings_group',
				self::OPTION_SETUP_FEE,
				array(
					'type'              => 'number',
					'sanitize_callback' => array( __CLASS__, 'sanitize_non_negative_float' ),
					'default'           => 0,
				)
			);

			register_setting(
				'srf_settings_group',
				self::OPTION_PROFIT_MARGIN,
				array(
					'type'              => 'number',
					'sanitize_callback' => array( __CLASS__, 'sanitize_non_negative_float' ),
					'default'           => 20,
				)
			);

			register_setting(
				'srf_settings_group',
				self::OPTION_MAX_UPLOAD_SIZE,
				array(
					'type'              => 'integer',
					'sanitize_callback' => array( __CLASS__, 'sanitize_positive_int' ),
					'default'           => 500,
				)
			);

			register_setting(
				'srf_settings_group',
				self::OPTION_ALLOWED_EXTENSIONS,
				array(
					'type'              => 'string',
					'sanitize_callback' => array( __CLASS__, 'sanitize_extensions_csv' ),
					'default'           => 'stl,obj,3mf',
				)
			);

			register_setting(
				'srf_settings_group',
				self::OPTION_GUEST_ORDERING,
				array(
					'type'              => 'boolean',
					'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
					'default'           => true,
				)
			);

			register_setting(
				'srf_settings_group',
				self::OPTION_DELETE_ON_UNINSTALL,
				array(
					'type'              => 'boolean',
					'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
					'default'           => false,
				)
			);

			register_setting(
				'srf_settings_group',
				self::OPTION_NOTIFY_ADMIN_EMAIL,
				array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_email',
					'default'           => '',
				)
			);

			if ( class_exists( 'SRF_WooCommerce' ) ) {
				register_setting(
					'srf_settings_group',
					SRF_WooCommerce::OPTION_FORM_PAGE_ID,
					array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'default'           => 0,
					)
				);

				register_setting(
					'srf_settings_group',
					SRF_WooCommerce::OPTION_AFTER_SUBMIT,
					array(
						'type'              => 'string',
						'sanitize_callback' => array( __CLASS__, 'sanitize_after_submit_target' ),
						'default'           => 'checkout',
					)
				);
			}
		}

		public static function sanitize_checkbox( $value ) {
			return ! empty( $value ) ? 1 : 0;
		}

		public static function sanitize_non_negative_float( $value ) {
			$value = is_scalar( $value ) ? (float) $value : 0;
			return max( 0, $value );
		}

		public static function sanitize_positive_int( $value ) {
			$value = (int) $value;
			return max( 1, $value );
		}

		public static function sanitize_after_submit_target( $value ) {
			$value = sanitize_key( (string) $value );
			return in_array( $value, array( 'cart', 'checkout' ), true ) ? $value : 'checkout';
		}

		public static function sanitize_extensions_csv( $value ) {
			if ( is_array( $value ) ) {
				$value = implode( ',', $value );
			}

			$parts = array_filter(
				array_map(
					'trim',
					explode( ',', strtolower( (string) $value ) )
				)
			);

			$clean = array();
			foreach ( $parts as $ext ) {
				$ext = sanitize_file_name( $ext );
				$ext = ltrim( $ext, '.' );

				if ( '' === $ext ) {
					continue;
				}

				$clean[] = $ext;
			}

			$clean = array_values( array_unique( $clean ) );

			if ( empty( $clean ) ) {
				$clean = array( 'stl', 'obj', '3mf' );
			}

			return implode( ',', $clean );
		}

		public static function get_option_value( $option_name, $default = '' ) {
			return get_option( $option_name, $default );
		}

		public static function get_quote_settings() {
			return array(
				'currency'               => (string) get_option( self::OPTION_CURRENCY, 'EUR' ),
				'currency_symbol'        => (string) get_option( self::OPTION_CURRENCY_SYMBOL, '€' ),
				'tax_rate'               => (float) get_option( self::OPTION_TAX_RATE, 0 ),
				'service_fee'            => (float) get_option( self::OPTION_SERVICE_FEE, 5 ),
				'setup_fee'              => (float) get_option( self::OPTION_SETUP_FEE, 0 ),
				'profit_margin'          => (float) get_option( self::OPTION_PROFIT_MARGIN, 20 ),
				'max_upload_size'        => (int) get_option( self::OPTION_MAX_UPLOAD_SIZE, 500 ),
				'allowed_extensions'     => (string) get_option( self::OPTION_ALLOWED_EXTENSIONS, 'stl,obj,3mf' ),
				'guest_ordering'         => (bool) get_option( self::OPTION_GUEST_ORDERING, true ),
				'delete_data_on_uninstall' => (bool) get_option( self::OPTION_DELETE_ON_UNINSTALL, false ),
				'notify_admin_email'     => (string) get_option( self::OPTION_NOTIFY_ADMIN_EMAIL, '' ),
			);
		}

		public static function get_allowed_extensions_array() {
			$csv = (string) get_option( self::OPTION_ALLOWED_EXTENSIONS, 'stl,obj,3mf' );
			$parts = array_filter( array_map( 'trim', explode( ',', strtolower( $csv ) ) ) );
			return array_values( array_unique( $parts ) );
		}

		public static function render_settings_page() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$default_redirect = add_query_arg( SRF_Google_Auth::QUERY_CALLBACK, '1', home_url( '/' ) );

			$currency            = (string) get_option( self::OPTION_CURRENCY, 'EUR' );
			$currency_symbol     = (string) get_option( self::OPTION_CURRENCY_SYMBOL, '€' );
			$tax_rate            = (float) get_option( self::OPTION_TAX_RATE, 0 );
			$service_fee         = (float) get_option( self::OPTION_SERVICE_FEE, 5 );
			$setup_fee           = (float) get_option( self::OPTION_SETUP_FEE, 0 );
			$profit_margin       = (float) get_option( self::OPTION_PROFIT_MARGIN, 20 );
			$max_upload_size     = (int) get_option( self::OPTION_MAX_UPLOAD_SIZE, 500 );
			$allowed_extensions  = (string) get_option( self::OPTION_ALLOWED_EXTENSIONS, 'stl,obj,3mf' );
			$guest_ordering      = (bool) get_option( self::OPTION_GUEST_ORDERING, true );
			$delete_on_uninstall = (bool) get_option( self::OPTION_DELETE_ON_UNINSTALL, false );
			$notify_admin_email  = (string) get_option( self::OPTION_NOTIFY_ADMIN_EMAIL, '' );
			$service_form_page_id = class_exists( 'SRF_WooCommerce' ) ? (int) get_option( SRF_WooCommerce::OPTION_FORM_PAGE_ID, 0 ) : 0;
			$service_after_submit = class_exists( 'SRF_WooCommerce' ) ? (string) get_option( SRF_WooCommerce::OPTION_AFTER_SUBMIT, 'checkout' ) : 'checkout';
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Service Requests Settings', 'service-requests-form' ); ?></h1>

				<form method="post" action="options.php">
					<?php settings_fields( 'srf_settings_group' ); ?>

					<h2><?php esc_html_e( 'Google Login', 'service-requests-form' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Enable Google Login', 'service-requests-form' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( SRF_Google_Auth::OPTION_ENABLED ); ?>" value="1" <?php checked( (bool) get_option( SRF_Google_Auth::OPTION_ENABLED, false ) ); ?> />
									<?php esc_html_e( 'Show Google login/register buttons in project form step 1.', 'service-requests-form' ); ?>
								</label>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="srf_google_client_id"><?php esc_html_e( 'Google Client ID', 'service-requests-form' ); ?></label>
							</th>
							<td>
								<input type="text" id="srf_google_client_id" class="regular-text" name="<?php echo esc_attr( SRF_Google_Auth::OPTION_CLIENT_ID ); ?>" value="<?php echo esc_attr( get_option( SRF_Google_Auth::OPTION_CLIENT_ID, '' ) ); ?>" />
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="srf_google_client_secret"><?php esc_html_e( 'Google Client Secret', 'service-requests-form' ); ?></label>
							</th>
							<td>
								<input type="text" id="srf_google_client_secret" class="regular-text" name="<?php echo esc_attr( SRF_Google_Auth::OPTION_CLIENT_SECRET ); ?>" value="<?php echo esc_attr( get_option( SRF_Google_Auth::OPTION_CLIENT_SECRET, '' ) ); ?>" />
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="srf_google_redirect_uri"><?php esc_html_e( 'Redirect URI', 'service-requests-form' ); ?></label>
							</th>
							<td>
								<input type="url" id="srf_google_redirect_uri" class="regular-text code" name="<?php echo esc_attr( SRF_Google_Auth::OPTION_REDIRECT_URI ); ?>" value="<?php echo esc_attr( get_option( SRF_Google_Auth::OPTION_REDIRECT_URI, '' ) ); ?>" placeholder="<?php echo esc_attr( $default_redirect ); ?>" />
								<p class="description">
									<?php
									printf(
										esc_html__( 'If empty, default is: %s', 'service-requests-form' ),
										$default_redirect
									);
									?>
								</p>
							</td>
						</tr>
					</table>

					<hr />

					<h2><?php esc_html_e( '3D Quote General Settings', 'service-requests-form' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="srf_quote_currency"><?php esc_html_e( 'Currency', 'service-requests-form' ); ?></label>
							</th>
							<td>
								<input type="text" id="srf_quote_currency" class="regular-text" name="<?php echo esc_attr( self::OPTION_CURRENCY ); ?>" value="<?php echo esc_attr( $currency ); ?>" />
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="srf_quote_currency_symbol"><?php esc_html_e( 'Currency symbol', 'service-requests-form' ); ?></label>
							</th>
							<td>
								<input type="text" id="srf_quote_currency_symbol" class="regular-text" name="<?php echo esc_attr( self::OPTION_CURRENCY_SYMBOL ); ?>" value="<?php echo esc_attr( $currency_symbol ); ?>" />
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="srf_quote_tax_rate"><?php esc_html_e( 'Tax rate (%)', 'service-requests-form' ); ?></label>
							</th>
							<td>
								<input type="number" min="0" step="0.01" id="srf_quote_tax_rate" class="small-text" name="<?php echo esc_attr( self::OPTION_TAX_RATE ); ?>" value="<?php echo esc_attr( $tax_rate ); ?>" />
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="srf_quote_service_fee"><?php esc_html_e( 'Service fee', 'service-requests-form' ); ?></label>
							</th>
							<td>
								<input type="number" min="0" step="0.01" id="srf_quote_service_fee" class="small-text" name="<?php echo esc_attr( self::OPTION_SERVICE_FEE ); ?>" value="<?php echo esc_attr( $service_fee ); ?>" />
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="srf_quote_setup_fee"><?php esc_html_e( 'Setup fee', 'service-requests-form' ); ?></label>
							</th>
							<td>
								<input type="number" min="0" step="0.01" id="srf_quote_setup_fee" class="small-text" name="<?php echo esc_attr( self::OPTION_SETUP_FEE ); ?>" value="<?php echo esc_attr( $setup_fee ); ?>" />
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="srf_quote_profit_margin"><?php esc_html_e( 'Profit margin (%)', 'service-requests-form' ); ?></label>
							</th>
							<td>
								<input type="number" min="0" step="0.01" id="srf_quote_profit_margin" class="small-text" name="<?php echo esc_attr( self::OPTION_PROFIT_MARGIN ); ?>" value="<?php echo esc_attr( $profit_margin ); ?>" />
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="srf_quote_max_upload_size"><?php esc_html_e( 'Max upload size (MB)', 'service-requests-form' ); ?></label>
							</th>
							<td>
								<input type="number" min="1" step="1" id="srf_quote_max_upload_size" class="small-text" name="<?php echo esc_attr( self::OPTION_MAX_UPLOAD_SIZE ); ?>" value="<?php echo esc_attr( $max_upload_size ); ?>" />
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="srf_quote_allowed_extensions"><?php esc_html_e( 'Allowed extensions (comma separated)', 'service-requests-form' ); ?></label>
							</th>
							<td>
								<input type="text" id="srf_quote_allowed_extensions" class="regular-text" name="<?php echo esc_attr( self::OPTION_ALLOWED_EXTENSIONS ); ?>" value="<?php echo esc_attr( $allowed_extensions ); ?>" />
								<p class="description">
									<?php esc_html_e( 'Example: stl,obj,3mf,step,stp,iges,igs', 'service-requests-form' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Allow guest quoting', 'service-requests-form' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_GUEST_ORDERING ); ?>" value="1" <?php checked( $guest_ordering ); ?> />
									<?php esc_html_e( 'Allow guests to use the 3D quote flow.', 'service-requests-form' ); ?>
								</label>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Delete plugin data on uninstall', 'service-requests-form' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION_DELETE_ON_UNINSTALL ); ?>" value="1" <?php checked( $delete_on_uninstall ); ?> />
									<?php esc_html_e( 'Delete quote-related settings and tables when the plugin is uninstalled.', 'service-requests-form' ); ?>
								</label>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="srf_quote_notify_admin_email"><?php esc_html_e( 'Admin notification email', 'service-requests-form' ); ?></label>
							</th>
							<td>
								<input type="email" id="srf_quote_notify_admin_email" class="regular-text" name="<?php echo esc_attr( self::OPTION_NOTIFY_ADMIN_EMAIL ); ?>" value="<?php echo esc_attr( $notify_admin_email ); ?>" />
							</td>
						</tr>
					</table>


					<?php if ( class_exists( 'SRF_WooCommerce' ) ) : ?>
						<hr />
						<h2><?php esc_html_e( 'WooCommerce Service Products', 'service-requests-form' ); ?></h2>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="srf_service_form_page_id"><?php esc_html_e( 'Service request form page', 'service-requests-form' ); ?></label></th>
								<td>
									<?php
									wp_dropdown_pages( array(
										'name'              => SRF_WooCommerce::OPTION_FORM_PAGE_ID,
										'id'                => 'srf_service_form_page_id',
										'selected'          => $service_form_page_id,
										'show_option_none'  => __( 'Select a page', 'service-requests-form' ),
										'option_none_value' => 0,
									) );
									?>
									<p class="description"><?php esc_html_e( 'Choose the page that contains [service_request_form]. Service product buttons will open this page with the selected service.', 'service-requests-form' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'After service form submit', 'service-requests-form' ); ?></th>
								<td>
									<label><input type="radio" name="<?php echo esc_attr( SRF_WooCommerce::OPTION_AFTER_SUBMIT ); ?>" value="checkout" <?php checked( $service_after_submit, 'checkout' ); ?> /> <?php esc_html_e( 'Go directly to checkout', 'service-requests-form' ); ?></label><br />
									<label><input type="radio" name="<?php echo esc_attr( SRF_WooCommerce::OPTION_AFTER_SUBMIT ); ?>" value="cart" <?php checked( $service_after_submit, 'cart' ); ?> /> <?php esc_html_e( 'Go to cart', 'service-requests-form' ); ?></label>
								</td>
							</tr>
						</table>
					<?php endif; ?>

					<?php submit_button(); ?>
				</form>
			</div>
			<?php
		}
	}
}