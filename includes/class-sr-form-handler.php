<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SR_Form_Handler' ) ) {

	class SR_Form_Handler {

		public static function init() {
			add_shortcode( 'service_request_form', array( __CLASS__, 'render_form_shortcode' ) );
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		}

		public static function enqueue_assets() {
			$css_rel = file_exists( SRF_PLUGIN_DIR . 'assets/css/frontend.css' )
				? 'assets/css/frontend.css'
				: 'frontend.css';

			$js_rel = file_exists( SRF_PLUGIN_DIR . 'assets/js/frontend.js' )
				? 'assets/js/frontend.js'
				: 'frontend.js';

			wp_enqueue_style(
				'srf-frontend',
				SRF_PLUGIN_URL . $css_rel,
				array(),
				SRF_VERSION
			);

			wp_enqueue_script(
				'srf-frontend-js',
				SRF_PLUGIN_URL . $js_rel,
				array(),
				SRF_VERSION,
				true
			);

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

			$services_data = array();
			if ( class_exists( 'SR_Service_Data' ) ) {
				$services_data = SR_Service_Data::get_all_services_data();
			}

			$js_service_map = array();
			foreach ( $services_data as $service_id => $service ) {
				$js_service_map[ (string) $service_id ] = array(
					'id'      => (string) $service_id,
					'title'   => isset( $service['title'] ) ? $service['title'] : '',
					'content' => isset( $service['content'] ) ? $service['content'] : '',
					'images'  => isset( $service['images'] ) ? $service['images'] : array(),
				);
			}

			wp_add_inline_script(
				'srf-frontend-js',
				'window.srfServiceData = window.srfServiceData || {};'
				. 'Object.assign(window.srfServiceData, ' . wp_json_encode( $js_service_map ) . ');',
				'before'
			);
		}

		public static function render_form_shortcode( $atts = array(), $content = '' ) {
			$atts = shortcode_atts( array(), $atts, 'service_request_form' );

			$errors   = array();
			$old_data = array();
			$success  = false;

			$services = class_exists( 'SR_Service_Data' )
				? SR_Service_Data::get_services_for_dropdown()
				: array();

			$selected_service_id = ! empty( $services ) ? (int) $services[0]['id'] : null;

			if ( isset( $_GET['srf_submitted'] ) && $_GET['srf_submitted'] === '1' ) {
				$success = true;
			}

			if ( ! empty( $_POST['srf_form_submitted'] ) ) {

				$old_data = array(
					'service'     => isset( $_POST['srf_service'] ) ? (int) $_POST['srf_service'] : '',
					'name'        => isset( $_POST['srf_name'] ) ? sanitize_text_field( wp_unslash( $_POST['srf_name'] ) ) : '',
					'company'     => isset( $_POST['srf_company'] ) ? sanitize_text_field( wp_unslash( $_POST['srf_company'] ) ) : '',
					'email'       => isset( $_POST['srf_email'] ) ? sanitize_email( wp_unslash( $_POST['srf_email'] ) ) : '',
					'phone'       => isset( $_POST['srf_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['srf_phone'] ) ) : '',
					'description' => isset( $_POST['srf_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['srf_description'] ) ) : '',
					'no_file'     => ! empty( $_POST['srf_no_file'] ) ? '1' : '0',
					'terms'       => ! empty( $_POST['srf_terms'] ) ? '1' : '0',
				);

				if ( ! empty( $old_data['service'] ) ) {
					$selected_service_id = (int) $old_data['service'];
				}

				if ( ! self::current_user_can_submit() ) {
					$errors[] = __( 'Only Business accounts can submit a service request. Please contact our IT team to open a Business account.', 'service-requests-form' );
				}

				if ( empty( $_POST['srf_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['srf_nonce'] ) ), 'srf_submit_request' ) ) {
					$errors[] = __( 'Security check failed. Please refresh the page and try again.', 'service-requests-form' );
				}

				if ( empty( $old_data['service'] ) ) {
					$errors[] = __( 'Please choose a service.', 'service-requests-form' );
				} elseif ( class_exists( 'SR_Service_Data' ) && ! SR_Service_Data::is_valid_service_id( (int) $old_data['service'] ) ) {
					$errors[] = __( 'Selected service is not valid.', 'service-requests-form' );
				}

				if ( empty( $old_data['name'] ) ) {
					$errors[] = __( 'Name is required.', 'service-requests-form' );
				}

				if ( empty( $old_data['company'] ) ) {
					$errors[] = __( 'Company is required.', 'service-requests-form' );
				}

				if ( empty( $old_data['phone'] ) ) {
					$errors[] = __( 'Phone is required.', 'service-requests-form' );
				}

				if ( empty( $old_data['email'] ) || ! is_email( $old_data['email'] ) ) {
					$errors[] = __( 'A valid email is required.', 'service-requests-form' );
				}

				if ( empty( $old_data['description'] ) ) {
					$errors[] = __( 'Project description is required.', 'service-requests-form' );
				}

				if ( empty( $old_data['terms'] ) || $old_data['terms'] !== '1' ) {
					$errors[] = __( 'You must accept the Terms & Conditions.', 'service-requests-form' );
				}

				$shipping_address = isset( $_POST['srf_shipping_address'] )
					? trim( sanitize_textarea_field( wp_unslash( $_POST['srf_shipping_address'] ) ) )
					: '';

				if ( $shipping_address === '' ) {
					$errors[] = __( 'Please set up your shipping address in My Account before submitting a request.', 'service-requests-form' );
				}

				if ( empty( $errors ) ) {
					$service_id    = (int) $old_data['service'];
					$service_title = get_the_title( $service_id );

					$user_id = get_current_user_id();
					$title   = sprintf(
						'Request - %s - %s',
						$service_title ? $service_title : '#' . $service_id,
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
						update_post_meta( $post_id, '_sr_service_id', $service_id );
						update_post_meta( $post_id, '_sr_service_title', $service_title );
						update_post_meta( $post_id, '_sr_name', $old_data['name'] );
						update_post_meta( $post_id, '_sr_company', $old_data['company'] );
						update_post_meta( $post_id, '_sr_email', $old_data['email'] );
						update_post_meta( $post_id, '_sr_phone', $old_data['phone'] );
						update_post_meta( $post_id, '_sr_shipping_address', $shipping_address );
						update_post_meta( $post_id, '_sr_description', $old_data['description'] );
						update_post_meta( $post_id, '_sr_no_file', $old_data['no_file'] === '1' ? 1 : 0 );
						update_post_meta( $post_id, '_sr_terms_accepted', 1 );
						update_post_meta( $post_id, '_sr_user_id', $user_id );

						$redirect_url = add_query_arg( 'srf_submitted', '1', get_permalink() );
						wp_safe_redirect( $redirect_url );
						exit;
					}
				}
			}

			$selected_service_data = null;
			if ( $selected_service_id && class_exists( 'SR_Service_Data' ) ) {
				$selected_service_data = SR_Service_Data::get_service_data( $selected_service_id );
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

		protected static function current_user_can_submit() {
			if ( ! is_user_logged_in() ) {
				return false;
			}

			$user  = wp_get_current_user();
			$roles = (array) $user->roles;

			return in_array( 'business_user', $roles, true );
		}

		protected static function load_template( $template_name, $vars = array() ) {
			$template_path = SRF_PLUGIN_DIR . 'templates/' . $template_name;

			if ( ! file_exists( $template_path ) ) {
				return;
			}

			if ( ! empty( $vars ) && is_array( $vars ) ) {
				extract( $vars, EXTR_SKIP );
			}

			include $template_path;
		}
	}
}
