<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SRF_Google_Auth' ) ) {
	class SRF_Google_Auth {

		const OPTION_ENABLED       = 'srf_google_enabled';
		const OPTION_CLIENT_ID     = 'srf_google_client_id';
		const OPTION_CLIENT_SECRET = 'srf_google_client_secret';
		const OPTION_REDIRECT_URI  = 'srf_google_redirect_uri';
		const QUERY_CALLBACK       = 'srf_google_callback';
		const STATE_PREFIX         = 'srf_google_state_';

		public static function init() {
			add_action( 'template_redirect', array( __CLASS__, 'maybe_handle_callback' ), 1 );
		}

		public static function is_enabled() {
			return (bool) get_option( self::OPTION_ENABLED, false ) && self::client_id() !== '' && self::client_secret() !== '';
		}

		public static function client_id() {
			return trim( (string) get_option( self::OPTION_CLIENT_ID, '' ) );
		}

		public static function client_secret() {
			return trim( (string) get_option( self::OPTION_CLIENT_SECRET, '' ) );
		}

		public static function redirect_uri() {
			$saved = trim( (string) get_option( self::OPTION_REDIRECT_URI, '' ) );
			if ( $saved !== '' ) {
				return esc_url_raw( $saved );
			}

			return esc_url_raw( add_query_arg( self::QUERY_CALLBACK, '1', home_url( '/' ) ) );
		}

		public static function auth_url( $redirect_to = '', $intent = 'login' ) {
			if ( ! self::is_enabled() ) {
				return '';
			}

			$state_token = wp_generate_password( 32, false, false );
			$state_data  = array(
				'redirect_to' => self::sanitize_redirect_target( $redirect_to ),
				'intent'      => ( $intent === 'register' ) ? 'register' : 'login',
				'created_at'  => time(),
			);

			set_transient( self::STATE_PREFIX . $state_token, $state_data, 15 * MINUTE_IN_SECONDS );

			$params = array(
				'client_id'     => self::client_id(),
				'redirect_uri'  => self::redirect_uri(),
				'response_type' => 'code',
				'scope'         => 'openid email profile',
				'state'         => $state_token,
				'prompt'        => 'select_account',
			);

			return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
		}

		public static function render_google_button( $redirect_to = '', $intent = 'login', $label = '' ) {
			$url = self::auth_url( $redirect_to, $intent );
			if ( $url === '' ) {
				return '';
			}

			$text = $label !== '' ? $label : __( 'Continue with Google', 'service-requests-form' );

			return '<a class="srf-google-btn" href="' . esc_url( $url ) . '">'
				. '<span class="srf-google-btn__icon" aria-hidden="true">G</span>'
				. '<span class="srf-google-btn__text">' . esc_html( $text ) . '</span>'
				. '</a>';
		}

		public static function maybe_handle_callback() {
			if ( empty( $_GET[ self::QUERY_CALLBACK ] ) ) {
				return;
			}

			$default_redirect = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' );

			if ( ! self::is_enabled() ) {
				self::safe_redirect_with_notice( $default_redirect, 'google_disabled' );
			}

			$code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
			$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';

			if ( $code === '' || $state === '' ) {
				self::safe_redirect_with_notice( $default_redirect, 'google_missing_code' );
			}

			$state_data = get_transient( self::STATE_PREFIX . $state );
			delete_transient( self::STATE_PREFIX . $state );
			if ( ! is_array( $state_data ) ) {
				self::safe_redirect_with_notice( $default_redirect, 'google_invalid_state' );
			}

			$redirect_to = isset( $state_data['redirect_to'] ) ? self::sanitize_redirect_target( $state_data['redirect_to'] ) : $default_redirect;

			$token_res = wp_remote_post(
				'https://oauth2.googleapis.com/token',
				array(
					'timeout' => 20,
					'body'    => array(
						'code'          => $code,
						'client_id'     => self::client_id(),
						'client_secret' => self::client_secret(),
						'redirect_uri'  => self::redirect_uri(),
						'grant_type'    => 'authorization_code',
					),
				)
			);

			if ( is_wp_error( $token_res ) ) {
				self::safe_redirect_with_notice( $redirect_to, 'google_token_failed' );
			}

			$token_body = json_decode( wp_remote_retrieve_body( $token_res ), true );
			$access_tok = is_array( $token_body ) && isset( $token_body['access_token'] ) ? (string) $token_body['access_token'] : '';
			if ( $access_tok === '' ) {
				self::safe_redirect_with_notice( $redirect_to, 'google_token_missing' );
			}

			$userinfo_res = wp_remote_get(
				'https://openidconnect.googleapis.com/v1/userinfo',
				array(
					'timeout' => 20,
					'headers' => array(
						'Authorization' => 'Bearer ' . $access_tok,
					),
				)
			);

			if ( is_wp_error( $userinfo_res ) ) {
				self::safe_redirect_with_notice( $redirect_to, 'google_userinfo_failed' );
			}

			$profile = json_decode( wp_remote_retrieve_body( $userinfo_res ), true );
			$email   = is_array( $profile ) && isset( $profile['email'] ) ? sanitize_email( $profile['email'] ) : '';
			$sub     = is_array( $profile ) && isset( $profile['sub'] ) ? sanitize_text_field( $profile['sub'] ) : '';
			$verified = is_array( $profile ) && ! empty( $profile['email_verified'] );

			if ( $email === '' || $sub === '' || ! $verified ) {
				self::safe_redirect_with_notice( $redirect_to, 'google_profile_invalid' );
			}

			$user = self::resolve_user( $email, $sub, $profile );
			if ( ! $user instanceof WP_User ) {
				self::safe_redirect_with_notice( $redirect_to, 'google_user_failed' );
			}

			update_user_meta( $user->ID, 'seml_google_sub', $sub );

			wp_set_current_user( $user->ID );
			wp_set_auth_cookie( $user->ID, true );

			self::safe_redirect( $redirect_to );
		}

		protected static function resolve_user( $email, $sub, $profile ) {
			$by_sub = get_users(
				array(
					'number'     => 1,
					'count_total'=> false,
					'meta_key'   => 'seml_google_sub',
					'meta_value' => $sub,
				)
			);

			if ( ! empty( $by_sub ) && $by_sub[0] instanceof WP_User ) {
				return $by_sub[0];
			}

			$existing = get_user_by( 'email', $email );
			if ( $existing instanceof WP_User ) {
				return $existing;
			}

			$base_username = sanitize_user( current( explode( '@', $email ) ), true );
			if ( $base_username === '' ) {
				$base_username = 'googleuser';
			}
			$username = $base_username;
			$i = 1;
			while ( username_exists( $username ) ) {
				$username = $base_username . $i;
				$i++;
			}

			$random_password = wp_generate_password( 24, true, true );
			$role = class_exists( 'WooCommerce' ) ? 'customer' : 'subscriber';
			$user_id = wp_insert_user(
				array(
					'user_login'   => $username,
					'user_email'   => $email,
					'user_pass'    => $random_password,
					'display_name' => isset( $profile['name'] ) ? sanitize_text_field( $profile['name'] ) : $username,
					'first_name'   => isset( $profile['given_name'] ) ? sanitize_text_field( $profile['given_name'] ) : '',
					'last_name'    => isset( $profile['family_name'] ) ? sanitize_text_field( $profile['family_name'] ) : '',
					'role'         => $role,
				)
			);

			if ( is_wp_error( $user_id ) || ! $user_id ) {
				return null;
			}

			return get_user_by( 'id', (int) $user_id );
		}

		protected static function sanitize_redirect_target( $url ) {
			$url = trim( (string) $url );
			if ( $url === '' ) {
				return function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' );
			}

			$home_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
			$target_host = wp_parse_url( $url, PHP_URL_HOST );

			if ( $target_host && $home_host && strtolower( $target_host ) !== strtolower( $home_host ) ) {
				return function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' );
			}

			return esc_url_raw( $url );
		}

		protected static function safe_redirect_with_notice( $url, $error_code ) {
			$target = add_query_arg( 'srf_google_error', sanitize_key( $error_code ), $url );
			self::safe_redirect( $target );
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
