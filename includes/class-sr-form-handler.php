<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SR_Form_Handler' ) ) {

	class SR_Form_Handler {

		// ===== Phase 5 constants =====
		const USER_QUOTA_BYTES = 1073741824; // 1GB

		public static function get_user_quota_bytes_public() {
			return self::get_user_quota_bytes();
		}

		public static function cleanup_request_files_public( $post_id, $user_id = 0 ) {
			self::cleanup_request_files( $post_id, $user_id );
		}

		public static function on_request_done( $post_id, $user_id = 0 ) {
			self::cleanup_request_files( (int) $post_id, (int) $user_id );
		}

		protected static function cleanup_request_files( $post_id, $user_id = 0 ) {
			$file_ids = get_post_meta( $post_id, '_sr_file_ids', true );
			if ( ! is_array( $file_ids ) ) {
				$file_ids = array();
			}

			if ( ! $user_id ) {
				$user_id = (int) get_post_meta( $post_id, '_sr_user_id', true );
				if ( ! $user_id ) {
					$user_id = (int) get_post_field( 'post_author', $post_id );
				}
			}

			$bytes_to_subtract = 0;

			foreach ( $file_ids as $aid ) {
				$aid = (int) $aid;
				if ( ! $aid ) continue;

				$bytes = (int) get_post_meta( $aid, '_srf_file_bytes', true );
				if ( $bytes <= 0 ) {
					$path = get_attached_file( $aid );
					if ( $path && file_exists( $path ) ) {
						$bytes = (int) filesize( $path );
					}
				}
				$bytes_to_subtract += max( 0, $bytes );

				wp_delete_attachment( $aid, true );
			}

			// Reset request meta
			delete_post_meta( $post_id, '_sr_file_ids' );

			// Free user storage
			if ( $user_id && $bytes_to_subtract > 0 ) {
				self::subtract_user_used_bytes( $user_id, $bytes_to_subtract );
			}

			// If request is done, you wanted storage empty for next request
			if ( $user_id ) {
				update_user_meta( $user_id, '_srf_storage_used_bytes', 0 );
			}
		}

		// Exact hard whitelist requested
		public static function hard_allowed_extensions() {
			return array( 'stl','obj','step','stp','iges','igs','zip','rar','7z','pdf','jpg','jpeg','png' );
		}

		public static function init() {
			add_shortcode( 'service_request_form', array( __CLASS__, 'render_form_shortcode' ) );
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
			add_action( 'srf_request_marked_done', array( __CLASS__, 'on_request_done' ), 10, 2 );
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

		// ===== Storage tracking =====

		protected static function get_user_used_bytes( $user_id ) {
			$used = (int) get_user_meta( $user_id, '_srf_storage_used_bytes', true );
			return max( 0, $used );
		}

		protected static function add_user_used_bytes( $user_id, $bytes ) {
			$used = self::get_user_used_bytes( $user_id );
			update_user_meta( $user_id, '_srf_storage_used_bytes', $used + (int) $bytes );
		}

		protected static function subtract_user_used_bytes( $user_id, $bytes ) {
			$used = self::get_user_used_bytes( $user_id );
			$new  = max( 0, $used - (int) $bytes );
			update_user_meta( $user_id, '_srf_storage_used_bytes', $new );
		}

		protected static function normalize_files_array( $files ) {
			$normalized = array();

			if ( empty( $files ) || empty( $files['name'] ) ) {
				return $normalized;
			}

			if ( is_array( $files['name'] ) ) {
				$count = count( $files['name'] );
				for ( $i = 0; $i < $count; $i++ ) {
					if ( empty( $files['name'][ $i ] ) ) {
						continue;
					}
					$normalized[] = array(
						'name'     => $files['name'][ $i ],
						'type'     => $files['type'][ $i ],
						'tmp_name' => $files['tmp_name'][ $i ],
						'error'    => $files['error'][ $i ],
						'size'     => $files['size'][ $i ],
					);
				}
			} else {
				$normalized[] = $files;
			}

			return $normalized;
		}

		/**
		 * Phase 5: Upload attachments safely (whitelist + 1GB quota).
		 * Returns [attachment_ids, total_bytes]
		 *
		 * @throws Exception
		 */
		protected static function handle_request_uploads( $post_id ) {

			$allowed_ext = self::get_allowed_extensions();
			$max_bytes   = self::get_max_file_size_bytes();

			$files = isset( $_FILES['srf_files'] ) ? $_FILES['srf_files'] : null;
			$items = self::normalize_files_array( $files );

			$attachment_ids = array();

			$user_id = (int) get_post_field( 'post_author', $post_id );
			if ( ! $user_id ) {
				$user_id = get_current_user_id();
			}

			// 1) Enforce 1GB TOTAL per user (sum this request first)
			$quota = self::get_user_quota_bytes();
			$used  = self::get_user_used_bytes( $user_id );

			$new_total = 0;
			foreach ( $items as $f ) {
				if ( empty( $f['error'] ) && ! empty( $f['size'] ) ) {
					$new_total += (int) $f['size'];
				}
			}

			if ( $new_total > 0 && ( $used + $new_total ) > $quota ) {
				throw new Exception(
					__( 'Upload limit reached (1GB total). Please wait until your previous request is completed.', 'service-requests-form' )
				);
			}

			// 2) Per-file validation + upload
			foreach ( $items as $file ) {

				if ( ! empty( $file['error'] ) ) {
					if ( (int) $file['error'] === UPLOAD_ERR_NO_FILE ) {
						continue;
					}
					throw new Exception( __( 'One of the uploaded files failed to upload. Please try again.', 'service-requests-form' ) );
				}

				if ( ! empty( $file['size'] ) && (int) $file['size'] > $max_bytes ) {
					throw new Exception(
						sprintf(
							__( 'File "%s" is too large. Maximum allowed size is %d MB.', 'service-requests-form' ),
							sanitize_file_name( $file['name'] ),
							(int) ( $max_bytes / 1024 / 1024 )
						)
					);
				}

				$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
				if ( empty( $ext ) || ! in_array( $ext, $allowed_ext, true ) ) {
					throw new Exception(
						sprintf(
							__( 'File type not allowed: "%s". Allowed: %s', 'service-requests-form' ),
							sanitize_file_name( $file['name'] ),
							implode( ', ', $allowed_ext )
						)
					);
				}

				$overrides = array( 'test_form' => false );
				$movefile  = wp_handle_upload( $file, $overrides );

				if ( ! $movefile || isset( $movefile['error'] ) ) {
					throw new Exception( __( 'Upload failed. Please try again.', 'service-requests-form' ) );
				}

				$attachment = array(
					'post_mime_type' => isset( $movefile['type'] ) ? $movefile['type'] : '',
					'post_title'     => sanitize_file_name( $file['name'] ),
					'post_content'   => '',
					'post_status'    => 'inherit',
				);

				$attach_id = wp_insert_attachment( $attachment, $movefile['file'], $post_id );
				if ( is_wp_error( $attach_id ) || ! $attach_id ) {
					throw new Exception( __( 'Could not attach uploaded file to the request.', 'service-requests-form' ) );
				}

				$attach_data = wp_generate_attachment_metadata( $attach_id, $movefile['file'] );
				if ( is_array( $attach_data ) ) {
					wp_update_attachment_metadata( $attach_id, $attach_data );
				}

				// ✅ Store bytes for clean subtraction later
				$file_bytes = ! empty( $file['size'] ) ? (int) $file['size'] : 0;
				update_post_meta( $attach_id, '_srf_file_bytes', $file_bytes );

				$attachment_ids[] = (int) $attach_id;

				// ✅ Increase user used bytes as uploads succeed
				if ( $file_bytes > 0 ) {
					self::add_user_used_bytes( $user_id, $file_bytes );
				}
			}

			return $attachment_ids;
		}

		public static function render_form_shortcode( $atts = array(), $content = '' ) {

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

				// Phase 5 rule: must upload OR check "no file"
				$no_file_checked = ! empty( $_POST['srf_no_file'] );
				$names = isset( $_FILES['srf_files']['name'] ) ? $_FILES['srf_files']['name'] : array();
				$has_any = is_array( $names ) ? ( count( array_filter( $names ) ) > 0 ) : ! empty( $names );

				if ( ! $no_file_checked && ! $has_any ) {
					$errors[] = __( 'Please upload at least one file, or check "I don’t have a file yet / not needed".', 'service-requests-form' );
				}

				if ( empty( $errors ) ) {
					$service_id    = (int) $old_data['service'];
					$service_title = get_the_title( $service_id );

					$user_id = get_current_user_id();

					$title = sprintf(
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
						update_post_meta( $post_id, '_sr_status', 'new' );

						// Phase 5 upload
						$attachment_ids = array();
						$uploaded_bytes = 0;

						try {
							list( $attachment_ids, $uploaded_bytes ) = self::handle_request_uploads( $post_id, $user_id );
							update_post_meta( $post_id, '_sr_file_ids', $attachment_ids );
						} catch ( Exception $e ) {
							// Roll back: delete attachments + restore quota + delete request post
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
							$redirect_url = add_query_arg( 'srf_submitted', '1', get_permalink() );
							wp_safe_redirect( $redirect_url );
							exit;
						}
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
