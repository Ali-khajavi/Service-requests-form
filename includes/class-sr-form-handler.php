<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SR_Form_Handler' ) ) {

	class SR_Form_Handler {

		// ========= Defaults / Phase 5 =========
		const DEFAULT_USER_QUOTA_BYTES = 1073741824; // 1GB
		const DEFAULT_MAX_FILE_BYTES   = 104857600;  // 100MB

		// Storage meta (canonical + back-compat)
		const USER_USED_META_KEY        = '_srf_storage_used_bytes';
		const USER_USED_META_KEY_LEGACY = 'srf_used_bytes';

		/**
		 * Public wrappers
		 * - Keep upload logic protected, expose wrappers for other classes (SRF_MyAccount).
		 * - Keep SRF single source of truth for validation/quota.
		 */

		public static function init() {
			add_shortcode( 'service_request_form', array( __CLASS__, 'shortcode_service_request_form' ) );
			add_shortcode( 'project_request_form', array( __CLASS__, 'shortcode_project_request_form' ) );
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
			add_filter( 'upload_mimes', array( __CLASS__, 'allow_project_upload_mimes' ) );
		}

		public static function register_assets() {

			if ( ! defined( 'SRF_PLUGIN_URL' ) || ! defined( 'SRF_PLUGIN_DIR' ) || ! defined( 'SRF_VERSION' ) ) {
				return;
			}

			$css_rel = 'assets/css/frontend.css';
			$js_rel  = 'assets/js/frontend.js';

			wp_register_style(
				'srf-frontend-css',
				SRF_PLUGIN_URL . $css_rel,
				array(),
				SRF_VERSION
			);

			wp_register_script(
				'srf-frontend-js',
				SRF_PLUGIN_URL . $js_rel,
				array(),
				SRF_VERSION,
				true
			);
		}

		protected static function enqueue_frontend_base_assets() {

			if ( ! wp_style_is( 'srf-frontend-css', 'registered' ) ) {
				self::register_assets();
			}

			if ( ! wp_script_is( 'srf-frontend-js', 'registered' ) ) {
				self::register_assets();
			}

			wp_enqueue_style( 'srf-frontend-css' );
			wp_enqueue_script( 'srf-frontend-js' );
		}

		protected static function enqueue_service_request_assets() {
			self::enqueue_frontend_base_assets();
			self::localize_frontend_script();
			self::inject_service_data();
		}

		protected static function enqueue_project_request_assets() {
			self::enqueue_frontend_base_assets();
			self::localize_frontend_script();
			self::inject_service_data();
		}

		protected static function localize_frontend_script() {
			static $localized = false;

			if ( $localized ) {
				return;
			}

			$can_submit = self::current_user_can_submit();

			wp_localize_script(
				'srf-frontend-js',
				'srfFrontend',
				array(
					'can_submit'    => $can_submit,
					'popup_title'   => __( 'Business account required', 'service-requests-form' ),
					'popup_message' => __( 'To submit a service request you must have a Business account. Please contact our IT team to open a Business account.', 'service-requests-form' ),
					'popup_button'  => __( 'OK', 'service-requests-form' ),
				)
			);

			$localized = true;
		}

		protected static function inject_service_data() {
			static $injected = false;

			if ( $injected ) {
				return;
			}

			$services_data = array();
			if ( class_exists( 'SR_Service_Data' ) ) {
				$services_data = SR_Service_Data::get_all_services_data();
			}

			$js_service_map = array();
			foreach ( $services_data as $service_id => $service ) {
				$js_service_map[ (string) $service_id ] = array(
					'id'       => (string) $service_id,
					'title'    => isset( $service['title'] ) ? (string) $service['title'] : '',
					'content'  => isset( $service['content'] ) ? (string) $service['content'] : '',
					'images'   => isset( $service['images'] ) ? (array) $service['images'] : array(),
					'variants' => isset( $service['variants'] ) ? (array) $service['variants'] : ( isset( $service['variations'] ) ? (array) $service['variations'] : array() ),
					'video'    => isset( $service['video'] ) && is_array( $service['video'] ) ? array(
						'url'         => isset( $service['video']['url'] ) ? (string) $service['video']['url'] : '',
						'title'       => isset( $service['video']['title'] ) ? (string) $service['video']['title'] : '',
						'description' => isset( $service['video']['description'] ) ? (string) $service['video']['description'] : '',
						'embed'       => isset( $service['video']['embed'] ) ? (string) $service['video']['embed'] : '',
					) : array(),
				);
			}

			wp_add_inline_script(
				'srf-frontend-js',
				'window.srfServiceData = window.srfServiceData || {};'
				. 'Object.assign(window.srfServiceData, ' . wp_json_encode( $js_service_map ) . ');',
				'before'
			);

			$injected = true;
		}

		protected static function get_project_upload_limit_label() {
			return self::current_user_is_business() ? '10 GB' : '1 GB';
		}

		protected static function get_project_allowed_extensions() {
			return array(
				'stl',
				'3mf',
				'obj',
				'step',
				'stp',
				'iges',
				'igs',
				'dxf',
				'pdf',
				'jpg',
				'jpeg',
				'png',
				'zip',
			);
		}

		protected static function get_project_allowed_extensions_label() {
			return implode( ', ', self::get_project_allowed_extensions() );
		}

		protected static function get_project_active_materials() {
			if ( ! class_exists( 'SRF_Quote_DB' ) ) {
				return array();
			}

			$db        = new SRF_Quote_DB();
			$materials = $db->get_materials( array( 'status' => 'active' ) );

			return is_array( $materials ) ? $materials : array();
		}

		protected static function get_project_active_printers() {
			if ( ! class_exists( 'SRF_Quote_DB' ) ) {
				return array();
			}

			$db       = new SRF_Quote_DB();
			$printers = $db->get_printers( array( 'status' => 'active' ) );

			if ( ! is_array( $printers ) ) {
				return array();
			}

			foreach ( $printers as $printer ) {
				$printer->supported_material_ids = array();
				$printer->supported_service_profile_ids = array();

				if ( ! empty( $printer->supported_materials ) ) {
					$decoded = json_decode( (string) $printer->supported_materials, true );
					if ( is_array( $decoded ) ) {
						$printer->supported_material_ids = array_values(
							array_filter(
								array_map( 'absint', $decoded )
							)
						);
					}
				}

				if ( ! empty( $printer->supported_service_profile_ids ) ) {
					$decoded_profiles = json_decode( (string) $printer->supported_service_profile_ids, true );
					if ( is_array( $decoded_profiles ) ) {
						$printer->supported_service_profile_ids = array_values(
							array_filter(
								array_map( 'absint', $decoded_profiles )
							)
						);
					} else {
						$printer->supported_service_profile_ids = array();
					}
				}
			}

			return $printers;
		}

		protected static function get_project_material_by_id( $material_id ) {
			$material_id = (int) $material_id;
			if ( $material_id <= 0 || ! class_exists( 'SRF_Quote_DB' ) ) {
				return null;
			}

			$db       = new SRF_Quote_DB();
			$material = $db->get_material( $material_id );

			if ( ! $material || empty( $material->id ) || 'active' !== (string) $material->status ) {
				return null;
			}

			return $material;
		}

		protected static function get_project_printer_by_id( $printer_id ) {
			$printer_id = (int) $printer_id;
			if ( $printer_id <= 0 || ! class_exists( 'SRF_Quote_DB' ) ) {
				return null;
			}

			$db      = new SRF_Quote_DB();
			$printer = $db->get_printer( $printer_id );

			if ( ! $printer || empty( $printer->id ) || 'active' !== (string) $printer->status ) {
				return null;
			}

			$printer->supported_material_ids = array();
			$printer->supported_service_profile_ids = array();

			if ( ! empty( $printer->supported_materials ) ) {
				$decoded = json_decode( (string) $printer->supported_materials, true );
				if ( is_array( $decoded ) ) {
					$printer->supported_material_ids = array_values(
						array_filter(
							array_map( 'absint', $decoded )
						)
					);
				}
			}

			if ( ! empty( $printer->supported_service_profile_ids ) ) {
				$decoded_profiles = json_decode( (string) $printer->supported_service_profile_ids, true );
				if ( is_array( $decoded_profiles ) ) {
					$printer->supported_service_profile_ids = array_values(
						array_filter(
							array_map( 'absint', $decoded_profiles )
						)
					);
				}
			}

			return $printer;
		}

		protected static function get_project_quote_settings() {
			$defaults = array(
				'currency'        => 'EUR',
				'currency_symbol' => '€',
				'tax_rate'        => 0,
				'service_fee'     => 5,
				'setup_fee'       => 0,
				'profit_margin'   => 20,
			);

			if ( class_exists( 'SR_Settings' ) && method_exists( 'SR_Settings', 'get_quote_settings' ) ) {
				$settings = SR_Settings::get_quote_settings();

				if ( is_array( $settings ) ) {
					return wp_parse_args( $settings, $defaults );
				}
			}

			return $defaults;
		}

		public static function allow_project_upload_mimes( $mimes ) {
			$mimes['stl']  = 'model/stl';
			$mimes['3mf']  = 'application/vnd.ms-package.3dmanufacturing-3dmodel+xml';
			$mimes['obj']  = 'text/plain';
			$mimes['step'] = 'application/step';
			$mimes['stp']  = 'application/step';
			$mimes['iges'] = 'model/iges';
			$mimes['igs']  = 'model/iges';
			$mimes['dxf']  = 'image/vnd.dxf';
			$mimes['pdf']  = 'application/pdf';
			$mimes['jpg']  = 'image/jpeg';
			$mimes['jpeg'] = 'image/jpeg';
			$mimes['png']  = 'image/png';
			$mimes['zip']  = 'application/zip';

			return $mimes;
		}

		protected static function validate_project_uploaded_file( $file ) {
			$allowed_exts = self::get_project_allowed_extensions();

			$filename = isset( $file['name'] ) ? (string) $file['name'] : '';
			$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

			if ( ! $ext || ! in_array( $ext, $allowed_exts, true ) ) {
				throw new Exception(
					sprintf(
						__( 'File type not allowed: %s. Allowed formats: %s', 'service-requests-form' ),
						$filename,
						self::get_project_allowed_extensions_label()
					)
				);
			}

			$checked = wp_check_filetype_and_ext(
				isset( $file['tmp_name'] ) ? $file['tmp_name'] : '',
				$filename
			);

			if ( empty( $checked['ext'] ) || empty( $checked['type'] ) ) {
				throw new Exception(
					sprintf(
						__( 'Unsafe or invalid file detected: %s', 'service-requests-form' ),
						$filename
					)
				);
			}
		}

		// ===============================
		// Settings / quota helpers
		// ===============================
		protected static function get_user_quota_bytes( $user_id ) {
			$user_id = (int) $user_id;
			if ( ! $user_id ) {
				return self::DEFAULT_USER_QUOTA_BYTES;
			}

			// Optional: per-user override
			$quota = (int) get_user_meta( $user_id, 'srf_quota_bytes', true );
			if ( $quota > 0 ) {
				return $quota;
			}
			return self::DEFAULT_USER_QUOTA_BYTES;
		}

		/**
		 * Public wrapper used by admin pages (Storage tab) and other classes.
		 */
		public static function get_user_quota_bytes_public( $user_id = 0 ) {
			$user_id = (int) $user_id;
			if ( $user_id <= 0 ) {
				return self::DEFAULT_USER_QUOTA_BYTES;
			}
			return self::get_user_quota_bytes( $user_id );
		}

		protected static function get_user_used_bytes( $user_id ) {
			$user_id = (int) $user_id;
			if ( ! $user_id ) {
				return 0;
			}

			// Canonical key first
			$used = (int) get_user_meta( $user_id, self::USER_USED_META_KEY, true );
			if ( $used > 0 ) {
				return $used;
			}

			// Back-compat: legacy key
			$legacy = (int) get_user_meta( $user_id, self::USER_USED_META_KEY_LEGACY, true );
			if ( $legacy > 0 ) {
				// Soft-migrate
				update_user_meta( $user_id, self::USER_USED_META_KEY, $legacy );
				return $legacy;
			}

			return 0;
		}

		protected static function add_user_used_bytes( $user_id, $bytes ) {
			$user_id = (int) $user_id;
			$bytes   = (int) $bytes;
			if ( ! $user_id || $bytes <= 0 ) {
				return;
			}

			$used = self::get_user_used_bytes( $user_id );
			$new  = max( 0, $used + $bytes );

			// Write both keys (canonical + legacy) for compatibility
			update_user_meta( $user_id, self::USER_USED_META_KEY, $new );
			update_user_meta( $user_id, self::USER_USED_META_KEY_LEGACY, $new );
		}

		protected static function subtract_user_used_bytes( $user_id, $bytes ) {
			$user_id = (int) $user_id;
			$bytes   = (int) $bytes;
			if ( ! $user_id || $bytes <= 0 ) {
				return;
			}

			$used = self::get_user_used_bytes( $user_id );
			$new  = max( 0, $used - $bytes );

			update_user_meta( $user_id, self::USER_USED_META_KEY, $new );
			update_user_meta( $user_id, self::USER_USED_META_KEY_LEGACY, $new );
		}

		protected static function get_max_file_bytes() {
			$mb = (int) get_option( 'srf_max_file_size_mb', 0 );
			if ( $mb <= 0 ) {
				return self::DEFAULT_MAX_FILE_BYTES;
			}
			return max( 1, $mb ) * 1024 * 1024;
		}

		protected static function get_allowed_extensions() {
			$raw = (string) get_option( 'srf_allowed_file_types', '' );
			$raw = trim( $raw );

			if ( $raw === '' ) {
				// fallback default
				return array( 'stl', 'obj','mtl' , 'ply', 'zip', 'rar', '7z', 'step', 'stp', 'igs', 'iges', 'png', 'jpg', 'jpeg', 'pdf','3mf'  );
			}

			$parts = array_map( 'trim', explode( ',', $raw ) );
			$out   = array();

			foreach ( $parts as $p ) {
				$p = strtolower( preg_replace( '/[^a-z0-9]/i', '', $p ) );
				if ( $p !== '' ) {
					$out[] = $p;
				}
			}

			return array_values( array_unique( $out ) );
		}

		protected static function extension_is_allowed( $filename ) {
			$filename = (string) $filename;
			$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
			if ( $ext === '' ) {
				return false;
			}
			$allowed = self::get_allowed_extensions();
			return in_array( $ext, $allowed, true );
		}

		protected static function ensure_user_quota( $user_id, $bytes_to_add ) {
			$user_id      = (int) $user_id;
			$bytes_to_add = (int) $bytes_to_add;

			if ( $bytes_to_add <= 0 ) {
				return;
			}

			$quota = self::get_user_quota_bytes( $user_id );
			$used  = self::get_user_used_bytes( $user_id );

			if ( ( $used + $bytes_to_add ) > $quota ) {
				throw new Exception(
					sprintf(
						__( 'Storage quota exceeded. You have used %1$s of %2$s.', 'service-requests-form' ),
						size_format( $used ),
						size_format( $quota )
					)
				);
			}
		}

		// ===============================
		// Upload handling
		// ===============================
		protected static function normalize_files_array( $files ) {
			$out = array();

			if ( ! is_array( $files ) || empty( $files['name'] ) ) {
				return $out;
			}

			if ( is_array( $files['name'] ) ) {
				$count = count( $files['name'] );
				for ( $i = 0; $i < $count; $i++ ) {
					$out[] = array(
						'name'     => $files['name'][ $i ],
						'type'     => isset( $files['type'][ $i ] ) ? $files['type'][ $i ] : '',
						'tmp_name' => isset( $files['tmp_name'][ $i ] ) ? $files['tmp_name'][ $i ] : '',
						'error'    => isset( $files['error'][ $i ] ) ? $files['error'][ $i ] : 0,
						'size'     => isset( $files['size'][ $i ] ) ? $files['size'][ $i ] : 0,
					);
				}
			} else {
				$out[] = $files;
			}

			return $out;
		}

		/**
		 * Handles uploads and returns [attachment_ids, total_uploaded_bytes].
		 * Throws Exception on validation/quota errors.
		 */
		protected static function current_user_is_business() {
			if ( ! is_user_logged_in() ) {
				return false;
			}

			$user  = wp_get_current_user();
			$roles = is_array( $user->roles ) ? $user->roles : array();

			return (bool) array_intersect( $roles, array( 'business_user', 'administrator' ) );
		}

		protected static function get_project_upload_limit_bytes() {
			if ( self::current_user_is_business() ) {
				return 10 * 1024 * 1024 * 1024;
			}

			return 1 * 1024 * 1024 * 1024;
		}

		protected static function get_current_user_request_profile_data() {
			$user_id = get_current_user_id();
			$user    = get_userdata( $user_id );

			if ( ! $user ) {
				return array(
					'name'    => '',
					'company' => '',
					'email'   => '',
					'phone'   => '',
				);
			}

			$name = trim( get_user_meta( $user_id, 'billing_first_name', true ) . ' ' . get_user_meta( $user_id, 'billing_last_name', true ) );
			if ( $name === '' ) {
				$name = $user->display_name;
			}

			$company = (string) get_user_meta( $user_id, 'billing_company', true );
			$email   = (string) $user->user_email;
			$phone   = (string) get_user_meta( $user_id, 'billing_phone', true );

			return array(
				'name'    => trim( $name ),
				'company' => trim( $company ),
				'email'   => trim( $email ),
				'phone'   => trim( $phone ),
			);
		}

		protected static function get_current_user_shipping_address() {
			if ( ! is_user_logged_in() || ! class_exists( 'WC_Customer' ) ) {
				return '';
			}

			$customer = new WC_Customer( get_current_user_id() );
			if ( ! $customer ) {
				return '';
			}

			$parts = array(
				$customer->get_shipping_first_name() . ' ' . $customer->get_shipping_last_name(),
				$customer->get_shipping_company(),
				$customer->get_shipping_address_1(),
				$customer->get_shipping_address_2(),
				trim( $customer->get_shipping_postcode() . ' ' . $customer->get_shipping_city() ),
				$customer->get_shipping_country(),
			);

			$parts = array_filter( array_map( 'trim', $parts ) );
			return trim( implode( ', ', $parts ), " ,\t\n\r\0\x0B" );
		}

		protected static function get_my_account_url() {
			if ( function_exists( 'wc_get_page_permalink' ) ) {
				$url = wc_get_page_permalink( 'myaccount' );
				if ( $url ) {
					return $url;
				}
			}

			return home_url( '/my-account/' );
		}

		protected static function render_service_login_gate() {
			$request_uri    = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
			$current_url    = esc_url_raw( home_url( $request_uri ) );
			$my_account_url = self::get_my_account_url();
			$google_error   = isset( $_GET['srf_google_error'] ) ? sanitize_key( wp_unslash( $_GET['srf_google_error'] ) ) : '';

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

			ob_start();
			?>
			<div class="srf-wrapper srf-service-login-gate">
				<div class="srf-project-auth__box" data-srf-auth-state="guest">
					<h3><?php esc_html_e( 'Sign in to continue', 'service-requests-form' ); ?></h3>
					<p><?php esc_html_e( 'Please log in before submitting a service request.', 'service-requests-form' ); ?></p>

					<?php if ( $google_error && isset( $google_error_map[ $google_error ] ) ) : ?>
						<div class="srf-project-auth__notice"><?php echo esc_html( $google_error_map[ $google_error ] ); ?></div>
					<?php endif; ?>

					<form class="srf-project-auth__form srf-project-auth__form--login" action="<?php echo esc_url( site_url( 'wp-login.php', 'login_post' ) ); ?>" method="post">
						<p class="login-username">
							<label for="srf-service-login-user"><?php esc_html_e( 'Email or username', 'service-requests-form' ); ?></label>
							<input type="text" name="log" id="srf-service-login-user" class="input" value="" autocomplete="username" />
						</p>
						<p class="login-password">
							<label for="srf-service-login-pass"><?php esc_html_e( 'Password', 'service-requests-form' ); ?></label>
							<input type="password" name="pwd" id="srf-service-login-pass" class="input" value="" autocomplete="current-password" />
						</p>
						<p class="login-remember">
							<label><input name="rememberme" type="checkbox" value="forever" /> <?php esc_html_e( 'Remember me', 'service-requests-form' ); ?></label>
						</p>
						<input type="hidden" name="redirect_to" value="<?php echo esc_url( remove_query_arg( 'srf_google_error', $current_url ) ); ?>" />
						<input type="hidden" name="testcookie" value="1" />
						<p class="login-submit">
							<button type="submit" name="wp-submit" class="button button-primary"><?php esc_html_e( 'Login', 'service-requests-form' ); ?></button>
						</p>
					</form>

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
			</div>
			<?php
			return ob_get_clean();
		}

		protected static function get_service_profile_completion_errors() {
			$profile          = self::get_current_user_request_profile_data();
			$shipping_address = self::get_current_user_shipping_address();
			$errors           = array();

			if ( empty( $profile['name'] ) ) {
				$errors[] = __( 'Name is missing from your profile.', 'service-requests-form' );
			}
			if ( empty( $profile['company'] ) ) {
				$errors[] = __( 'Company is missing from your profile.', 'service-requests-form' );
			}
			if ( empty( $profile['email'] ) || ! is_email( $profile['email'] ) ) {
				$errors[] = __( 'A valid email address is missing from your profile.', 'service-requests-form' );
			}
			if ( empty( $profile['phone'] ) ) {
				$errors[] = __( 'Phone number is missing from your profile.', 'service-requests-form' );
			}
			if ( empty( $shipping_address ) ) {
				$errors[] = __( 'Shipping address is missing from your profile.', 'service-requests-form' );
			}

			return $errors;
		}

		protected static function handle_request_uploads( $post_id, $custom_max_bytes = 0 ) {
			$post_id = (int) $post_id;

			if ( empty( $_FILES['srf_files'] ) ) {
				return array( array(), 0 );
			}

			$user_id = get_current_user_id();
			$max = (int) $custom_max_bytes > 0 ? (int) $custom_max_bytes : self::get_max_file_bytes();
			$items   = self::normalize_files_array( $_FILES['srf_files'] );

			$total_bytes = 0;
			foreach ( $items as $f ) {
				$size = isset( $f['size'] ) ? (int) $f['size'] : 0;
				$total_bytes += max( 0, $size );
			}

			// Quota check (before uploading)
			if ( $user_id ) {
				self::ensure_user_quota( $user_id, $total_bytes );
			}

			$attachment_ids = array();

			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			foreach ( $items as $file ) {

				$name  = isset( $file['name'] ) ? (string) $file['name'] : '';
				$err   = isset( $file['error'] ) ? (int) $file['error'] : 0;
				$size  = isset( $file['size'] ) ? (int) $file['size'] : 0;

				if ( $name === '' ) {
					continue;
				}

				if ( $err !== UPLOAD_ERR_OK ) {
					throw new Exception( sprintf( __( 'Upload error for "%s".', 'service-requests-form' ), $name ) );
				}

				if ( $size <= 0 ) {
					throw new Exception( sprintf( __( 'File "%s" is empty.', 'service-requests-form' ), $name ) );
				}

				if ( $size > $max ) {
					throw new Exception(
						sprintf(
							__( 'File "%1$s" exceeds the maximum size (%2$s).', 'service-requests-form' ),
							$name,
							size_format( $max )
						)
					);
				}

				if ( ! self::extension_is_allowed( $name ) ) {
					throw new Exception(
						sprintf(
							__( 'File type not allowed: "%s".', 'service-requests-form' ),
							$name
						)
					);
				}

				$overrides = array(
					'test_form' => false,
					'mimes'     => null, // allow WP to decide; we enforce extension above
				);
				self::validate_project_uploaded_file( $file );
				$uploaded = wp_handle_upload( $file, $overrides );

				if ( ! is_array( $uploaded ) || ! empty( $uploaded['error'] ) ) {
					$msg = is_array( $uploaded ) && ! empty( $uploaded['error'] ) ? $uploaded['error'] : __( 'Unknown upload error.', 'service-requests-form' );
					throw new Exception( sprintf( __( 'Upload failed for "%1$s": %2$s', 'service-requests-form' ), $name, $msg ) );
				}

				$filetype = wp_check_filetype( $uploaded['file'], null );

				$attachment = array(
					'post_mime_type' => isset( $filetype['type'] ) ? $filetype['type'] : 'application/octet-stream',
					'post_title'     => sanitize_file_name( $name ),
					'post_content'   => '',
					'post_status'    => 'inherit',
				);

				$attach_id = wp_insert_attachment( $attachment, $uploaded['file'], $post_id );

				if ( is_wp_error( $attach_id ) ) {
					@unlink( $uploaded['file'] );
					throw new Exception( sprintf( __( 'Could not create attachment for "%s".', 'service-requests-form' ), $name ) );
				}

				$attach_data = wp_generate_attachment_metadata( $attach_id, $uploaded['file'] );
				wp_update_attachment_metadata( $attach_id, $attach_data );

				$attachment_ids[] = (int) $attach_id;
			}

			// Quota increment only after success
			if ( $user_id && $total_bytes > 0 ) {
				self::add_user_used_bytes( $user_id, $total_bytes );
			}

			return array( $attachment_ids, $total_bytes );
		}

		// ===============================
		// Email
		// ===============================
		protected static function send_admin_new_request_email( $post_id ) {

			$post_id = (int) $post_id;
			if ( ! $post_id ) {
				return;
			}

			$to = (string) get_option( 'srf_admin_email', '' );
			if ( empty( $to ) || ! is_email( $to ) ) {
				$to = (string) get_option( 'admin_email' );
			}
			if ( empty( $to ) || ! is_email( $to ) ) {
				return;
			}

			$service_title    = (string) get_post_meta( $post_id, '_sr_service_title', true );
			$name             = (string) get_post_meta( $post_id, '_sr_name', true );
			$company          = (string) get_post_meta( $post_id, '_sr_company', true );
			$email            = (string) get_post_meta( $post_id, '_sr_email', true );
			$phone            = (string) get_post_meta( $post_id, '_sr_phone', true );
			$shipping_address = (string) get_post_meta( $post_id, '_sr_shipping_address', true );
			$description      = (string) get_post_meta( $post_id, '_sr_description', true );
			$status           = (string) get_post_meta( $post_id, '_sr_status', true );
			$file_ids         = get_post_meta( $post_id, '_sr_file_ids', true );
			$variants         = get_post_meta( $post_id, '_sr_variants', true );
			$request_type     = (string) get_post_meta( $post_id, '_sr_request_type', true );

			if ( empty( $status ) ) {
				$status = 'new';
			}

			$edit_link = admin_url( 'post.php?post=' . $post_id . '&action=edit' );

			$subject = sprintf(
				__( '[Service Request #%1$d] %2$s', 'service-requests-form' ),
				$post_id,
				$service_title ? $service_title : __( 'New Request', 'service-requests-form' )
			);

			$lines   = array();
			$lines[] = __( 'A new Service Request has been submitted.', 'service-requests-form' );
			$lines[] = '';
			$lines[] = sprintf( __( 'Request ID: %d', 'service-requests-form' ), $post_id );
			$lines[] = sprintf( __( 'Request Type: %s', 'service-requests-form' ), $request_type ? $request_type : 'service' );
			$lines[] = sprintf( __( 'Status: %s', 'service-requests-form' ), $status );
			$lines[] = sprintf( __( 'Service: %s', 'service-requests-form' ), $service_title ? $service_title : __( 'Project Request', 'service-requests-form' ) );
			$lines[] = '';

			$lines[] = __( 'Variants:', 'service-requests-form' );
			if ( is_array( $variants ) && ! empty( $variants ) ) {
				foreach ( $variants as $vk => $vv ) {
					$lines[] = '- ' . (string) $vk . ': ' . (string) $vv;
				}
			} else {
				$lines[] = '- ' . __( 'None', 'service-requests-form' );
			}

			$lines[] = '';
			$lines[] = sprintf( __( 'Name: %s', 'service-requests-form' ), $name ? $name : '-' );
			$lines[] = sprintf( __( 'Company: %s', 'service-requests-form' ), $company ? $company : '-' );
			$lines[] = sprintf( __( 'Email: %s', 'service-requests-form' ), $email ? $email : '-' );
			$lines[] = sprintf( __( 'Phone: %s', 'service-requests-form' ), $phone ? $phone : '-' );
			$lines[] = '';
			$lines[] = __( 'Shipping Address:', 'service-requests-form' );
			$lines[] = $shipping_address ? $shipping_address : '-';
			$lines[] = '';
			$lines[] = __( 'Project Description:', 'service-requests-form' );
			$lines[] = $description ? $description : '-';
			$lines[] = '';
			$lines[] = __( 'Admin Link:', 'service-requests-form' );
			$lines[] = $edit_link;
			$lines[] = '';
			$lines[] = __( 'Uploaded Files:', 'service-requests-form' );

			if ( is_array( $file_ids ) && ! empty( $file_ids ) ) {
				foreach ( $file_ids as $aid ) {
					$aid = (int) $aid;
					if ( ! $aid ) {
						continue;
					}

					$url   = wp_get_attachment_url( $aid );
					$name2 = get_the_title( $aid );
					if ( $url ) {
						$lines[] = '- ' . ( $name2 ? $name2 : ( 'File #' . $aid ) ) . ': ' . $url;
					}
				}
			} else {
				$lines[] = '- ' . __( 'No files uploaded.', 'service-requests-form' );
			}

			$message = implode( "\n", $lines );

			$site_name  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			$from_email = (string) get_option( 'admin_email' );

			$headers   = array();
			$headers[] = 'Content-Type: text/plain; charset=UTF-8';

			if ( $from_email && is_email( $from_email ) ) {
				$headers[] = 'From: ' . $site_name . ' <' . $from_email . '>';
			}

			if ( $email && is_email( $email ) ) {
				$reply_name = $name ? $name : __( 'Customer', 'service-requests-form' );
				$headers[]  = 'Reply-To: ' . $reply_name . ' <' . $email . '>';
			}

			$sent = wp_mail( $to, $subject, $message, $headers );

			update_post_meta( $post_id, '_sr_admin_email_to', $to );
			update_post_meta( $post_id, '_sr_admin_email_subject', $subject );
			update_post_meta( $post_id, '_sr_admin_email_sent', $sent ? '1' : '0' );
			update_post_meta( $post_id, '_sr_admin_email_sent_at', current_time( 'mysql' ) );
		}

		// ===============================
		// Cleanup (called when marked done)
		// ===============================
		protected static function cleanup_request_files( $post_id, $user_id = 0 ) {

			$post_id = (int) $post_id;
			$user_id = (int) $user_id;

			$file_ids = get_post_meta( $post_id, '_sr_file_ids', true );
			if ( ! is_array( $file_ids ) || empty( $file_ids ) ) {
				return;
			}

			$total = 0;

			foreach ( $file_ids as $aid ) {
				$aid = (int) $aid;
				if ( ! $aid ) {
					continue;
				}

				$file = get_attached_file( $aid );
				if ( $file && file_exists( $file ) ) {
					$total += (int) filesize( $file );
				}

				wp_delete_attachment( $aid, true );
			}

			delete_post_meta( $post_id, '_sr_file_ids' );

			if ( $user_id && $total > 0 ) {
				self::subtract_user_used_bytes( $user_id, $total );
			}
		}

		// Public wrapper for cleanup, used by admin-status class if needed
		public static function cleanup_request_files_public( $post_id, $user_id = 0 ) {
			self::cleanup_request_files( $post_id, $user_id );
		}

		// ===============================
		// Shortcode Detailed Form Handler
		// ===============================
		public static function shortcode_service_request_form() {
			self::enqueue_service_request_assets();

			if ( ! is_user_logged_in() ) {
				return self::render_service_login_gate();
			}

			$profile_data     = self::get_current_user_request_profile_data();
			$shipping_address = self::get_current_user_shipping_address();
			$profile_errors   = self::get_service_profile_completion_errors();
			$my_account_url   = self::get_my_account_url();

			$errors   = array();
			$old_data = array();
			$success  = false;

			$services = class_exists( 'SR_Service_Data' )
				? SR_Service_Data::get_services_for_dropdown()
				: array();

			$selected_service_id = ! empty( $services ) ? (int) $services[0]['id'] : null;
			if ( isset( $_GET['srf_service'] ) ) {
				$requested_service_id = absint( wp_unslash( $_GET['srf_service'] ) );
				if ( $requested_service_id > 0 && ( ! class_exists( 'SR_Service_Data' ) || SR_Service_Data::is_valid_service_id( $requested_service_id ) ) ) {
					$selected_service_id = $requested_service_id;
				}
			}

			// Show success message after redirect
			if ( isset( $_GET['srf_submitted'] ) && $_GET['srf_submitted'] === '1' ) {
				$success = true;
			}

			// Handle submit
			if ( ! empty( $_POST['srf_form_submitted'] ) ) {

				$old_data = array(
					'service'     => isset( $_POST['srf_service'] ) ? (int) $_POST['srf_service'] : 0,
					'quantity'    => isset( $_POST['srf_quantity'] ) ? (string) max( 1, (int) wp_unslash( $_POST['srf_quantity'] ) ) : '1',
					'description' => isset( $_POST['srf_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['srf_description'] ) ) : '',
					'no_file'     => ! empty( $_POST['srf_no_file'] ) ? '1' : '0',
					'terms'       => ! empty( $_POST['srf_terms'] ) ? '1' : '0',
				);

				$old_data['name']    = isset( $profile_data['name'] ) ? (string) $profile_data['name'] : '';
				$old_data['company'] = isset( $profile_data['company'] ) ? (string) $profile_data['company'] : '';
				$old_data['email']   = isset( $profile_data['email'] ) ? (string) $profile_data['email'] : '';
				$old_data['phone']   = isset( $profile_data['phone'] ) ? (string) $profile_data['phone'] : '';

				// Variant selections (key => chosen value), posted as srf_variants[index][key/value]
				$selected_variants = array();
				if ( isset( $_POST['srf_variants'] ) && is_array( $_POST['srf_variants'] ) ) {
					foreach ( (array) $_POST['srf_variants'] as $row ) {
						$key = isset( $row['key'] ) ? trim( sanitize_text_field( wp_unslash( $row['key'] ) ) ) : '';
						$val = isset( $row['value'] ) ? trim( sanitize_text_field( wp_unslash( $row['value'] ) ) ) : '';
						if ( $key !== '' && $val !== '' ) {
							$selected_variants[ $key ] = $val;
						}
					}
				}
				$old_data['variants'] = $selected_variants;

				if ( ! empty( $old_data['service'] ) ) {
					$selected_service_id = (int) $old_data['service'];
				}

				// Permission
				if ( ! self::current_user_can_submit() ) {
					$errors[] = __( 'Only Business accounts can submit a service request. Please contact our IT team to open a Business account.', 'service-requests-form' );
				}

				// Nonce
				if ( empty( $_POST['srf_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['srf_nonce'] ) ), 'srf_submit_request' ) ) {
					$errors[] = __( 'Security check failed. Please refresh the page and try again.', 'service-requests-form' );
				}

				// Service validation
				if ( empty( $old_data['service'] ) ) {
					$errors[] = __( 'Please choose a service.', 'service-requests-form' );
				} elseif ( class_exists( 'SR_Service_Data' ) && method_exists( 'SR_Service_Data', 'is_valid_service_id' ) && ! SR_Service_Data::is_valid_service_id( (int) $old_data['service'] ) ) {
					$errors[] = __( 'Selected service is not valid.', 'service-requests-form' );
				}

				if ( (int) $old_data['quantity'] < 1 ) {
					$errors[] = __( 'Please enter a valid quantity.', 'service-requests-form' );
				}

				// Required profile fields are loaded from the logged-in user profile, not from editable form inputs.
				if ( ! empty( $profile_errors ) ) {
					$errors = array_merge( $errors, $profile_errors );
				}

				if ( empty( $old_data['description'] ) ) $errors[] = __( 'Project description is required.', 'service-requests-form' );

				// Variants validation (if service defines variant groups)
				if ( empty( $errors ) && ! empty( $old_data['service'] ) ) {
					$variant_defs = array();
					if ( class_exists( 'SR_Services_CPT' ) && method_exists( 'SR_Services_CPT', 'get_variations' ) ) {
						$variant_defs = SR_Services_CPT::get_variations( (int) $old_data['service'] );
					} else {
						$variant_defs = get_post_meta( (int) $old_data['service'], '_sr_service_variations', true );
					}

					// Normalize definitions into groups: [ ['key'=>..., 'values'=>[...] ], ... ]
					$groups = array();
					if ( is_array( $variant_defs ) ) {
						foreach ( $variant_defs as $row ) {
							// New format
							if ( isset( $row['key'] ) && isset( $row['values'] ) && is_array( $row['values'] ) ) {
								$key  = trim( sanitize_text_field( $row['key'] ) );
								$vals = array();
								foreach ( $row['values'] as $v ) {
									$v = trim( sanitize_text_field( $v ) );
									if ( $v !== '' ) $vals[] = $v;
								}
								if ( $key !== '' && ! empty( $vals ) ) {
									$groups[] = array(
										'key'    => $key,
										'values' => array_values( array_unique( $vals ) ),
									);
								}
								continue;
							}

							// Back-compat: old rows label/value
							if ( isset( $row['label'] ) ) {
								$lbl = trim( sanitize_text_field( $row['label'] ) );
								if ( $lbl !== '' ) {
									$groups[] = array(
										'key'    => __( 'Variant', 'service-requests-form' ),
										'values' => array( $lbl ),
									);
								}
							}
						}
					}

					if ( ! empty( $groups ) ) {
						$selected = isset( $old_data['variants'] ) && is_array( $old_data['variants'] ) ? $old_data['variants'] : array();
						foreach ( $groups as $g ) {
							$key     = isset( $g['key'] ) ? (string) $g['key'] : '';
							$allowed = isset( $g['values'] ) && is_array( $g['values'] ) ? $g['values'] : array();
							$chosen  = isset( $selected[ $key ] ) ? (string) $selected[ $key ] : '';

							if ( $key === '' ) {
								continue;
							}
							if ( $chosen === '' ) {
								$errors[] = sprintf( __( 'Please choose %s.', 'service-requests-form' ), $key );
								continue;
							}
							if ( ! empty( $allowed ) && ! in_array( $chosen, $allowed, true ) ) {
								$errors[] = sprintf( __( 'Invalid option selected for %s.', 'service-requests-form' ), $key );
							}
						}
					}
				}

				// Terms
				if ( $old_data['terms'] !== '1' ) {
					$errors[] = __( 'You must accept the Terms & Conditions.', 'service-requests-form' );
				}
				// Shipping address is loaded from the logged-in user profile, not from POST.
				$shipping_address = self::get_current_user_shipping_address();

				if ( $shipping_address === '' ) {
					$errors[] = __( 'Please set up your shipping address in My Account before submitting a request.', 'service-requests-form' );
				}

				// Must upload OR check "no file"
				$no_file_checked = ! empty( $_POST['srf_no_file'] );
				$names           = isset( $_FILES['srf_files']['name'] ) ? $_FILES['srf_files']['name'] : array();
				$has_any         = is_array( $names ) ? ( count( array_filter( $names ) ) > 0 ) : ! empty( $names );

				if ( ! $no_file_checked && ! $has_any ) {
					$errors[] = __( 'Please upload at least one file, or check "I don’t have a file yet / not needed".', 'service-requests-form' );
				}

				// Save request
				if ( empty( $errors ) ) {

					$service_id    = (int) $old_data['service'];
					$service_title = get_the_title( $service_id );
					if ( ! $service_title ) {
						$service_title = 'Service #' . $service_id;
					}

					$user_id = get_current_user_id();

					$title = sprintf(
						'Request - %s - %s',
						$service_title,
						$old_data['name']
					);

					$post_id = wp_insert_post(
						array(
							'post_type'    => 'service_request',
							'post_status'  => 'publish',
							'post_title'   => $title,
							'post_content' => $old_data['description'],
							'post_author'  => $user_id,
						),
						true
					);

					if ( is_wp_error( $post_id ) ) {
						$errors[] = __( 'Could not save your request. Please try again.', 'service-requests-form' );
					} else {

						// Meta
						update_post_meta( $post_id, '_sr_service_id', $service_id );
						update_post_meta( $post_id, '_sr_service_title', $service_title );
						update_post_meta( $post_id, '_sr_name', $old_data['name'] );
						update_post_meta( $post_id, '_sr_company', $old_data['company'] );
						update_post_meta( $post_id, '_sr_email', $old_data['email'] );
						update_post_meta( $post_id, '_sr_phone', $old_data['phone'] );
						update_post_meta( $post_id, '_sr_shipping_address', $shipping_address );
						update_post_meta( $post_id, '_sr_description', $old_data['description'] );
						update_post_meta( $post_id, '_sr_no_file', ( $old_data['no_file'] === '1' ) ? 1 : 0 );
						update_post_meta( $post_id, '_sr_terms_accepted', 1 );
						update_post_meta( $post_id, '_sr_user_id', $user_id );
						update_post_meta( $post_id, '_sr_status', 'new' );

					$quantity = max( 1, (int) $old_data['quantity'] );
					$price_data = class_exists( 'SRF_WooCommerce' ) ? SRF_WooCommerce::calculate_service_price( $service_id, $selected_variants, $quantity ) : array( 'base' => 0, 'extras' => array(), 'unit_total' => 0, 'total' => 0, 'quantity' => $quantity );
					update_post_meta( $post_id, '_sr_price_base', isset( $price_data['base'] ) ? (float) $price_data['base'] : 0 );
					update_post_meta( $post_id, '_sr_price_extras', isset( $price_data['extras'] ) ? $price_data['extras'] : array() );
					update_post_meta( $post_id, '_sr_price_total', isset( $price_data['total'] ) ? (float) $price_data['total'] : 0 );
					update_post_meta( $post_id, '_sr_quantity', $quantity );

						// Selected variants (key => value)
						if ( ! empty( $selected_variants ) && is_array( $selected_variants ) ) {
							update_post_meta( $post_id, '_sr_variants', $selected_variants );
						} else {
							delete_post_meta( $post_id, '_sr_variants' );
						}

						$attachment_ids = array();
						$uploaded_bytes = 0;

						try {
							list( $attachment_ids, $uploaded_bytes ) = self::handle_request_uploads( $post_id );
							if ( ! is_array( $attachment_ids ) ) {
								$attachment_ids = array();
							}
							update_post_meta( $post_id, '_sr_file_ids', $attachment_ids );

						} catch ( Exception $e ) {

							// Roll back attachments
							if ( ! empty( $attachment_ids ) ) {
								foreach ( $attachment_ids as $aid ) {
									wp_delete_attachment( (int) $aid, true );
								}
							}

							// Roll back quota
							if ( $uploaded_bytes > 0 ) {
								self::subtract_user_used_bytes( $user_id, $uploaded_bytes );
							}

							// Delete request
							wp_delete_post( $post_id, true );

							$errors[] = $e->getMessage();
						}

					if ( empty( $errors ) ) {

						self::send_admin_new_request_email( $post_id );

						$cart_added = false;
						if ( class_exists( 'SRF_WooCommerce' ) && SRF_WooCommerce::is_available() ) {
							$cart_added = SRF_WooCommerce::add_request_to_cart( $post_id, $service_id, $selected_variants, $quantity );
						}

						if ( $cart_added ) {
							update_post_meta( $post_id, '_sr_status', 'pending-payment' );
						}

						if ( $cart_added && class_exists( 'SRF_WooCommerce' ) ) {
							$redirect_url = SRF_WooCommerce::get_after_submit_url();
						} elseif ( class_exists( 'SRF_MyAccount' ) && method_exists( 'SRF_MyAccount', 'url_list' ) ) {
							$redirect_url = SRF_MyAccount::url_list( array( 'srf_submitted' => '1' ) );
						} else {
							$redirect_url = add_query_arg( 'srf_submitted', '1', get_permalink() );
						}

						self::safe_redirect( $redirect_url );
						}
					}
				}
			}

			// Right panel
			$selected_service_data = null;
			if ( $selected_service_id && class_exists( 'SR_Service_Data' ) ) {
				$selected_service_data = SR_Service_Data::get_service_data( $selected_service_id );
			}

			if ( ! empty( $profile_errors ) && empty( $_POST['srf_form_submitted'] ) ) {
				ob_start();
				?>
				<div class="srf-wrapper">
					<div class="srf-form__errors">
						<p><?php esc_html_e( 'Please complete your account profile before submitting a service request.', 'service-requests-form' ); ?></p>
						<ul>
							<?php foreach ( $profile_errors as $profile_error ) : ?>
								<li><?php echo esc_html( $profile_error ); ?></li>
							<?php endforeach; ?>
						</ul>
						<p><a class="srf-button" href="<?php echo esc_url( $my_account_url ); ?>"><?php esc_html_e( 'Go to My Account', 'service-requests-form' ); ?></a></p>
					</div>
				</div>
				<?php
				return ob_get_clean();
			}

			ob_start();
			?>
			<div class="srf-wrapper">
				<div class="srf-layout">
					<div class="srf-layout__form">
						<?php
						self::load_template(
							'form.php',
							array(
								'services'            => $services,
								'selected_service_id' => $selected_service_id,
								'errors'              => $errors,
								'old_data'            => $old_data,
								'success'             => $success,
								'profile_data'        => $profile_data,
								'shipping_address'    => $shipping_address,
								'my_account_url'      => $my_account_url,
							)
						);
						?>
					</div>

					<div class="srf-layout__service-info">
						<?php
						self::load_template(
							'service-info.php',
							array(
								'selected_service_data' => $selected_service_data,
							)
						);
						?>
					</div>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}

		// ===============================
		// Shortcode Project Request Form (simplified form for project submissions, without service selection and variants)
		// ===============================

		public static function shortcode_project_request_form() {
			self::enqueue_project_request_assets();
			
			$errors   = array();
			$old_data = array(
				'title'        => '',
				'description'  => '',
				'terms'        => '0',
				'material_id'  => '',
				'printer_id'   => '',
				'layer_height' => '0.20',
				'infill'       => '20',
				'shell_mode'   => 'solid',
				'scale'        => '100',
				'quantity'     => '1',
				'notes'        => '',
			);
			$success = false;

			$dashboard_url = '';
			if ( class_exists( 'SRF_MyAccount' ) && method_exists( 'SRF_MyAccount', 'url_list' ) ) {
				$dashboard_url = SRF_MyAccount::url_list();
			}
			$materials = self::get_project_active_materials();
			$printers  = self::get_project_active_printers();

			if ( isset( $_GET['srf_project_submitted'] ) && $_GET['srf_project_submitted'] === '1' ) {
				$success = true;
			}

			$is_project_login_post = isset( $_POST['log'], $_POST['pwd'] ) || isset( $_POST['wp-submit'] );

			if ( ! empty( $_POST['srf_project_form_submitted'] ) && ! $is_project_login_post ) {

				$old_data = array(
					'title'        => isset( $_POST['srf_project_title'] ) ? sanitize_text_field( wp_unslash( $_POST['srf_project_title'] ) ) : '',
					'description'  => isset( $_POST['srf_project_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['srf_project_description'] ) ) : '',
					'terms'        => ! empty( $_POST['srf_terms'] ) ? '1' : '0',
					'material_id'  => isset( $_POST['srf_material_id'] ) ? (string) absint( $_POST['srf_material_id'] ) : '',
					'printer_id'   => isset( $_POST['srf_printer_id'] ) ? (string) absint( $_POST['srf_printer_id'] ) : '',
					'service_profile_id' => isset( $_POST['srf_service_profile_id'] ) ? (string) absint( $_POST['srf_service_profile_id'] ) : '',
					'profile_variations' => isset( $_POST['srf_profile_variations'] ) && is_array( $_POST['srf_profile_variations'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['srf_profile_variations'] ) ) : array(),
					'layer_height' => isset( $_POST['srf_layer_height'] ) ? (string) max( 0, (float) wp_unslash( $_POST['srf_layer_height'] ) ) : '0.20',
					'infill'       => isset( $_POST['srf_infill'] ) ? (string) max( 0, min( 100, (int) wp_unslash( $_POST['srf_infill'] ) ) ) : '20',
					'shell_mode'   => isset( $_POST['srf_shell_mode'] ) && 'hollow' === sanitize_key( wp_unslash( $_POST['srf_shell_mode'] ) ) ? 'hollow' : 'solid',
					'scale'        => isset( $_POST['srf_scale'] ) ? (string) max( 10, min( 500, (int) wp_unslash( $_POST['srf_scale'] ) ) ) : '100',
					'quantity'     => isset( $_POST['srf_quantity'] ) ? (string) max( 1, (int) wp_unslash( $_POST['srf_quantity'] ) ) : '1',
					'notes'        => isset( $_POST['srf_quote_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['srf_quote_notes'] ) ) : '',
				);

				if ( empty( $_POST['srf_project_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['srf_project_nonce'] ) ), 'srf_submit_project_request' ) ) {
					$errors[] = __( 'Security check failed. Please refresh the page and try again.', 'service-requests-form' );
				}

				if ( ! is_user_logged_in() ) {
					$errors[] = __( 'Please log in or register before continuing.', 'service-requests-form' );
				}

				if ( empty( $old_data['title'] ) ) {
					$errors[] = __( 'Project title is required.', 'service-requests-form' );
				}

				if ( empty( $old_data['description'] ) ) {
					$errors[] = __( 'Project description is required.', 'service-requests-form' );
				}

				if ( $old_data['terms'] !== '1' ) {
					$errors[] = __( 'You must accept the Terms & Conditions.', 'service-requests-form' );
				}

				$selected_material = null;
				$selected_printer  = null;

				if ( empty( $old_data['material_id'] ) ) {
					$errors[] = __( 'Please select a material.', 'service-requests-form' );
				} else {
					$selected_material = self::get_project_material_by_id( (int) $old_data['material_id'] );
					if ( ! $selected_material ) {
						$errors[] = __( 'The selected material is not available.', 'service-requests-form' );
					}
				}

				if ( empty( $old_data['printer_id'] ) ) {
					$errors[] = __( 'Please select a printer.', 'service-requests-form' );
				} else {
					$selected_printer = self::get_project_printer_by_id( (int) $old_data['printer_id'] );
					if ( ! $selected_printer ) {
						$errors[] = __( 'The selected printer is not available.', 'service-requests-form' );
					}
				}

				if ( $selected_material && $selected_printer && ! empty( $selected_printer->supported_material_ids ) ) {
					if ( ! in_array( (int) $selected_material->id, $selected_printer->supported_material_ids, true ) ) {
						$errors[] = __( 'The selected printer does not support the selected material.', 'service-requests-form' );
					}
				}

				$selected_service_profile = null;
				if ( ! empty( $old_data['service_profile_id'] ) ) {
					$service_profile_id = absint( $old_data['service_profile_id'] );
					if ( ! class_exists( 'SR_Service_Data' ) || ! SR_Service_Data::is_valid_service_id( $service_profile_id ) ) {
						$errors[] = __( 'The selected service profile is not available.', 'service-requests-form' );
					} elseif ( $selected_printer && ! empty( $selected_printer->supported_service_profile_ids ) && ! in_array( $service_profile_id, $selected_printer->supported_service_profile_ids, true ) ) {
						$errors[] = __( 'The selected printer does not support that service profile.', 'service-requests-form' );
					} else {
						$selected_service_profile = SR_Service_Data::get_service_data( $service_profile_id );
						if ( ! $selected_service_profile ) {
							$errors[] = __( 'The selected service profile could not be loaded.', 'service-requests-form' );
						}
					}
				}

				if ( $selected_printer ) {
					$layer_height = (float) $old_data['layer_height'];
					$min_layer    = isset( $selected_printer->min_layer_height ) ? (float) $selected_printer->min_layer_height : 0;
					$max_layer    = isset( $selected_printer->max_layer_height ) ? (float) $selected_printer->max_layer_height : 0;

					if ( $min_layer > 0 && $layer_height < $min_layer ) {
						$errors[] = sprintf(
							__( 'Layer height is below the minimum supported by the selected printer (%s mm).', 'service-requests-form' ),
							number_format_i18n( $min_layer, 2 )
						);
					}

					if ( $max_layer > 0 && $layer_height > $max_layer ) {
						$errors[] = sprintf(
							__( 'Layer height is above the maximum supported by the selected printer (%s mm).', 'service-requests-form' ),
							number_format_i18n( $max_layer, 2 )
						);
					}
				}

				$names   = isset( $_FILES['srf_files']['name'] ) ? $_FILES['srf_files']['name'] : array();
				$has_any = is_array( $names ) ? ( count( array_filter( $names ) ) > 0 ) : ! empty( $names );

				if ( ! $has_any ) {
					$errors[] = __( 'Please upload at least one file.', 'service-requests-form' );
				}

				if ( empty( $errors ) ) {
					$user_id      = get_current_user_id();
					$profile_data = self::get_current_user_request_profile_data();

					$post_id = wp_insert_post(
						array(
							'post_type'    => 'service_request',
							'post_status'  => 'publish',
							'post_title'   => $old_data['title'],
							'post_content' => $old_data['description'],
							'post_author'  => $user_id,
						),
						true
					);

					if ( is_wp_error( $post_id ) ) {
						$errors[] = __( 'Could not save your project request. Please try again.', 'service-requests-form' );
					} else {
						update_post_meta( $post_id, '_sr_request_type', 'project' );
						update_post_meta( $post_id, '_sr_project_title', $old_data['title'] );
						update_post_meta( $post_id, '_sr_service_title', 'Project Request' );
						update_post_meta( $post_id, '_sr_description', $old_data['description'] );
						update_post_meta( $post_id, '_sr_name', $profile_data['name'] );
						update_post_meta( $post_id, '_sr_company', $profile_data['company'] );
						update_post_meta( $post_id, '_sr_email', $profile_data['email'] );
						update_post_meta( $post_id, '_sr_phone', $profile_data['phone'] );
						update_post_meta( $post_id, '_sr_user_id', $user_id );
						update_post_meta( $post_id, '_sr_status', 'new' );

						update_post_meta( $post_id, '_sr_no_file', 0 );
						update_post_meta( $post_id, '_sr_material_id', (int) $old_data['material_id'] );
						update_post_meta( $post_id, '_sr_printer_id', (int) $old_data['printer_id'] );
						update_post_meta( $post_id, '_sr_layer_height', (float) $old_data['layer_height'] );
						update_post_meta( $post_id, '_sr_infill', (int) $old_data['infill'] );
						update_post_meta( $post_id, '_sr_shell_mode', $old_data['shell_mode'] );
						update_post_meta( $post_id, '_sr_scale', (int) $old_data['scale'] );
						update_post_meta( $post_id, '_sr_quantity', (int) $old_data['quantity'] );
						update_post_meta( $post_id, '_sr_quote_notes', $old_data['notes'] );

						if ( ! empty( $old_data['service_profile_id'] ) ) {
							update_post_meta( $post_id, '_sr_service_profile_id', (int) $old_data['service_profile_id'] );
						}
						if ( ! empty( $old_data['profile_variations'] ) ) {
							update_post_meta( $post_id, '_sr_profile_variations', $old_data['profile_variations'] );
						}

						if ( ! empty( $selected_material ) ) {
							update_post_meta( $post_id, '_sr_material_name', (string) $selected_material->name );
						}

						if ( ! empty( $selected_printer ) ) {
							update_post_meta( $post_id, '_sr_printer_name', (string) $selected_printer->name );
						}
						if ( ! empty( $selected_service_profile ) ) {
							update_post_meta( $post_id, '_sr_service_profile_title', (string) $selected_service_profile['title'] );
						}

						$attachment_ids = array();
						$uploaded_bytes = 0;

						try {
							list( $attachment_ids, $uploaded_bytes ) = self::handle_request_uploads( $post_id, self::get_project_upload_limit_bytes() );
							update_post_meta( $post_id, '_sr_file_ids', is_array( $attachment_ids ) ? $attachment_ids : array() );
						} catch ( Exception $e ) {

							if ( ! empty( $attachment_ids ) ) {
								foreach ( $attachment_ids as $aid ) {
									wp_delete_attachment( (int) $aid, true );
								}
							}

							if ( $uploaded_bytes > 0 ) {
								self::subtract_user_used_bytes( $user_id, $uploaded_bytes );
							}

							wp_delete_post( $post_id, true );
							$errors[] = $e->getMessage();
						}

						if ( empty( $errors ) ) {
							$attachment_paths = array();
							if ( ! empty( $attachment_ids ) ) {
								foreach ( $attachment_ids as $attachment_id ) {
									$file_path = get_attached_file( (int) $attachment_id );
									if ( $file_path ) {
										$attachment_paths[] = $file_path;
									}
								}
							}

							try {
								if ( ! class_exists( 'SRF_Project_Pricing' ) ) {
									throw new Exception( __( 'The project pricing engine is not available.', 'service-requests-form' ) );
								}

								$quote = SRF_Project_Pricing::calculate_final_quote(
									$attachment_paths,
									$selected_material,
									$selected_printer,
									self::get_project_quote_settings(),
									array(
										'layer_height' => (float) $old_data['layer_height'],
										'infill'       => (int) $old_data['infill'],
										'shell_mode'   => $old_data['shell_mode'],
										'scale'        => (int) $old_data['scale'],
										'quantity'     => (int) $old_data['quantity'],
									)
								);

								update_post_meta( $post_id, '_sr_model_count', (int) $quote['model_count'] );
								update_post_meta( $post_id, '_sr_model_formats', implode( ',', (array) $quote['model_formats'] ) );
								update_post_meta( $post_id, '_sr_model_triangles', (int) $quote['model_triangles'] );
								update_post_meta( $post_id, '_sr_model_bounds_mm', (array) $quote['model_bounds_mm'] );
								update_post_meta( $post_id, '_sr_model_volume_cm3', (float) $quote['model_volume_cm3'] );
								update_post_meta( $post_id, '_sr_effective_volume_cm3', (float) $quote['effective_volume_cm3'] );
								update_post_meta( $post_id, '_sr_adjusted_volume_cm3', (float) $quote['adjusted_volume_cm3'] );
								update_post_meta( $post_id, '_sr_estimated_weight_g', (float) $quote['estimated_weight_g'] );
								update_post_meta( $post_id, '_sr_unit_material_cost', (float) $quote['unit_material_cost'] );
								update_post_meta( $post_id, '_sr_unit_printer_cost', (float) $quote['unit_printer_cost'] );
								update_post_meta( $post_id, '_sr_material_cost', (float) $quote['items_material_total'] );
								update_post_meta( $post_id, '_sr_printer_cost', (float) $quote['items_printer_total'] );
								update_post_meta( $post_id, '_sr_service_fee', (float) $quote['service_fee'] );
								update_post_meta( $post_id, '_sr_setup_fee', (float) $quote['setup_fee'] );
								update_post_meta( $post_id, '_sr_profit_margin_percent', (float) $quote['profit_margin_percent'] );
								update_post_meta( $post_id, '_sr_profit_margin_amount', (float) $quote['profit_margin_amount'] );
								update_post_meta( $post_id, '_sr_tax_rate', (float) $quote['tax_rate'] );
								update_post_meta( $post_id, '_sr_tax_amount', (float) $quote['tax_amount'] );
								update_post_meta( $post_id, '_sr_subtotal_before_margin', (float) $quote['subtotal_before_margin'] );
								update_post_meta( $post_id, '_sr_subtotal_with_margin', (float) $quote['subtotal_with_margin'] );
								update_post_meta( $post_id, '_sr_total_price', (float) $quote['total_price'] );
								update_post_meta( $post_id, '_sr_currency', (string) $quote['currency'] );
								update_post_meta( $post_id, '_sr_currency_symbol', (string) $quote['currency_symbol'] );
								update_post_meta( $post_id, '_sr_quote_snapshot', wp_json_encode( $quote ) );
							} catch ( Exception $e ) {
								if ( ! empty( $attachment_ids ) ) {
									foreach ( $attachment_ids as $aid ) {
										wp_delete_attachment( (int) $aid, true );
									}
								}
								if ( $uploaded_bytes > 0 ) {
									self::subtract_user_used_bytes( $user_id, $uploaded_bytes );
								}
								wp_delete_post( $post_id, true );
								$errors[] = $e->getMessage();
							}
						}

						if ( empty( $errors ) ) {
							self::send_admin_new_request_email( $post_id );

							$redirect_url = add_query_arg(
								array(
									'srf_project_submitted' => '1',
								),
								get_permalink()
							);

							self::safe_redirect( $redirect_url );
						}
					}
				}
			}

			ob_start();
			self::load_template(
				'project-form.php',
				array(
					'errors'             => $errors,
					'old_data'           => $old_data,
					'success'            => $success,
					'dashboard_url'      => $dashboard_url,
					'upload_limit'       => self::get_project_upload_limit_label(),
					'upload_limit_bytes' => self::get_project_upload_limit_bytes(),
					'allowed_formats'    => self::get_project_allowed_extensions_label(),
					'is_business'        => self::current_user_is_business(),
					'materials'          => $materials,
					'printers'           => $printers,
					'quote_settings'     => self::get_project_quote_settings(),
				)
			);
			return ob_get_clean();
		}

		// ===============================
		// Helpers
		// ===============================
		protected static function current_user_can_submit() {
		if ( ! is_user_logged_in() ) return false;
		$roles = (array) wp_get_current_user()->roles;
		return array_intersect($roles, array('business_user','administrator'));
		}

		protected static function load_template( $template_name, $vars = array() ) {
			$template_path = trailingslashit( SRF_PLUGIN_DIR ) . 'templates/' . ltrim( (string) $template_name, '/' );
			if ( ! file_exists( $template_path ) ) {
				return;
			}
			if ( ! empty( $vars ) && is_array( $vars ) ) {
				extract( $vars, EXTR_SKIP );
			}
			include $template_path;
		}

		protected static function safe_redirect( $url ) {
			$url = esc_url_raw( $url );

			if ( ! headers_sent() ) {
				wp_safe_redirect( $url );
				exit;
			}

			echo '<script>window.location.href=' . wp_json_encode( $url ) . ';</script>';
			echo '<noscript><meta http-equiv="refresh" content="0;url=' . esc_attr( $url ) . '"></noscript>';
			exit;
		}
	}
}
