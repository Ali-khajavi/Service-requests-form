<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SR_Settings' ) ) {
	class SR_Settings {

		const PAGE_SLUG = 'srf-settings';

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
		}

		public static function sanitize_checkbox( $value ) {
			return ! empty( $value ) ? 1 : 0;
		}

		public static function render_settings_page() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$default_redirect = add_query_arg( SRF_Google_Auth::QUERY_CALLBACK, '1', home_url( '/' ) );
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Service Requests Settings', 'service-requests-form' ); ?></h1>
				<h2><?php esc_html_e( 'Google Login', 'service-requests-form' ); ?></h2>

				<form method="post" action="options.php">
					<?php settings_fields( 'srf_settings_group' ); ?>

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
							<th scope="row"><label for="srf_google_client_id"><?php esc_html_e( 'Google Client ID', 'service-requests-form' ); ?></label></th>
							<td>
								<input type="text" id="srf_google_client_id" class="regular-text" name="<?php echo esc_attr( SRF_Google_Auth::OPTION_CLIENT_ID ); ?>" value="<?php echo esc_attr( get_option( SRF_Google_Auth::OPTION_CLIENT_ID, '' ) ); ?>" />
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="srf_google_client_secret"><?php esc_html_e( 'Google Client Secret', 'service-requests-form' ); ?></label></th>
							<td>
								<input type="text" id="srf_google_client_secret" class="regular-text" name="<?php echo esc_attr( SRF_Google_Auth::OPTION_CLIENT_SECRET ); ?>" value="<?php echo esc_attr( get_option( SRF_Google_Auth::OPTION_CLIENT_SECRET, '' ) ); ?>" />
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="srf_google_redirect_uri"><?php esc_html_e( 'Redirect URI', 'service-requests-form' ); ?></label></th>
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

					<?php submit_button(); ?>
				</form>
			</div>
			<?php
		}
	}
}
