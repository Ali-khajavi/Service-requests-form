<?php
/**
 * Plugin-only language selection.
 *
 * WordPress normally chooses translations from the site/user locale. This
 * class lets administrators override the Service Requests Form text domain
 * independently for frontend requests and plugin admin screens.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SRF_Language' ) ) {
	class SRF_Language {

		const OPTION_FRONTEND_LANGUAGE = 'srf_frontend_language';
		const OPTION_ADMIN_LANGUAGE    = 'srf_admin_language';

		/** @var string */
		const DOMAIN = 'service-requests-form';

		/**
		 * Register the locale override before the plugin text domain is loaded.
		 */
		public static function init() {
			// Keep this filter for WordPress just-in-time translation loading and
			// third-party calls that ask WordPress for this plugin's locale.
			add_filter( 'plugin_locale', array( __CLASS__, 'filter_plugin_locale' ), 10, 2 );
		}

		/**
		 * Supported language settings.
		 *
		 * @return array<string,string>
		 */
		public static function choices() {
			return array(
				'site'  => __( 'Use WordPress language', self::DOMAIN ),
				'en_US' => __( 'English', self::DOMAIN ),
				'de_DE' => __( 'German (Deutsch)', self::DOMAIN ),
			);
		}

		/**
		 * Keep only a supported plugin language value.
		 *
		 * @param mixed $value Raw option value.
		 * @return string
		 */
		public static function sanitize_language( $value ) {
			$value = is_scalar( $value ) ? (string) $value : 'site';
			return in_array( $value, array( 'site', 'en_US', 'de_DE' ), true ) ? $value : 'site';
		}

		/**
		 * Return the configured language for the current request context.
		 *
		 * @return string site, en_US, or de_DE.
		 */
		public static function get_current_setting() {
			$option = self::is_plugin_admin_context() ? self::OPTION_ADMIN_LANGUAGE : self::OPTION_FRONTEND_LANGUAGE;
			return self::sanitize_language( get_option( $option, 'site' ) );
		}

		/**
		 * Detect plugin-admin requests without treating frontend submissions as
		 * administrator UI merely because WordPress routes some requests through
		 * wp-admin.
		 *
		 * @return bool
		 */
		protected static function is_plugin_admin_context() {
			if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
				return false;
			}

			if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
				return false;
			}

			return is_admin();
		}

		/**
		 * Load the exact catalogue selected for this request.
		 *
		 * Relying only on plugin_locale is not sufficient on every supported
		 * WordPress release because just-in-time translation loading may already
		 * have registered the site locale for the domain. Loading the selected MO
		 * file directly makes the independent frontend/admin selectors reliable.
		 *
		 * @return bool True when the selected catalogue was loaded or English was
		 *              intentionally selected; false when loading failed.
		 */
		public static function load_textdomain() {
			$setting = self::get_current_setting();

			if ( 'site' === $setting ) {
				// Remove an early catalogue but leave the domain reloadable, then use
				// WordPress' normal site/user-locale resolution.
				if ( function_exists( 'unload_textdomain' ) ) {
					unload_textdomain( self::DOMAIN, true );
				}

				return load_plugin_textdomain(
					self::DOMAIN,
					false,
					dirname( SRF_PLUGIN_BASENAME ) . '/languages'
				);
			}

			if ( 'en_US' === $setting ) {
				// English is the source language. Marking the domain as deliberately
				// unloaded prevents just-in-time loading from restoring a German
				// catalogue on a German WordPress site or for a German admin user.
				if ( function_exists( 'unload_textdomain' ) ) {
					unload_textdomain( self::DOMAIN );
				}

				return true;
			}

			$mofile = trailingslashit( SRF_PLUGIN_DIR ) . 'languages/' . self::DOMAIN . '-' . $setting . '.mo';
			if ( ! is_readable( $mofile ) ) {
				return false;
			}

			// Clear any early/JIT catalogue first. Do not pass the selected locale as
			// load_textdomain()'s optional third argument: modern WordPress stores
			// translations by the request locale, so the explicit German MO must be
			// registered in the current request's locale slot to provide a plugin-only
			// override without changing WordPress, WooCommerce, the theme, or plugins.
			if ( function_exists( 'unload_textdomain' ) ) {
				unload_textdomain( self::DOMAIN, true );
			}

			return load_textdomain( self::DOMAIN, $mofile );
		}

		/**
		 * Override only this plugin's locale.
		 *
		 * @param string $locale Current locale.
		 * @param string $domain Text domain.
		 * @return string
		 */
		public static function filter_plugin_locale( $locale, $domain ) {
			if ( self::DOMAIN !== $domain ) {
				return $locale;
			}

			$setting = self::get_current_setting();
			return 'site' === $setting ? $locale : $setting;
		}
	}
}
