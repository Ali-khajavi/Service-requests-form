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


		/**
		 * Send admin notification email for a new request.
		 */
		protected static function send_admin_new_request_email( $post_id ) {

			$post_id = (int) $post_id;
			if ( ! $post_id ) {
				return;
			}

			// Recipient: settings email if exists, else site admin email
			$to = '';
			if ( function_exists( 'get_option' ) ) {
				// If you already store an admin email option, use it (adjust option key if needed)
				$to = (string) get_option( 'srf_admin_email', '' );
			}
			if ( empty( $to ) || ! is_email( $to ) ) {
				$to = get_option( 'admin_email' );
			}

			if ( empty( $to ) || ! is_email( $to ) ) {
				return;
			}

			$service_title     = (string) get_post_meta( $post_id, '_sr_service_title', true );
			$name              = (string) get_post_meta( $post_id, '_sr_name', true );
			$company           = (string) get_post_meta( $post_id, '_sr_company', true );
			$email             = (string) get_post_meta( $post_id, '_sr_email', true );
			$phone             = (string) get_post_meta( $post_id, '_sr_phone', true );
			$shipping_address  = (string) get_post_meta( $post_id, '_sr_shipping_address', true );
			$description       = (string) get_post_meta( $post_id, '_sr_description', true );
			$status            = (string) get_post_meta( $post_id, '_sr_status', true );
			$file_ids          = get_post_meta( $post_id, '_sr_file_ids', true );

			if ( empty( $status ) ) {
				$status = 'new';
			}

			$edit_link = admin_url( 'post.php?post=' . $post_id . '&action=edit' );

			$subject = sprintf(
				/* translators: %1$d request id, %2$s service title */
				__( '[Service Request #%1$d] %2$s', 'service-requests-form' ),
				$post_id,
				$service_title ? $service_title : __( 'New Request', 'service-requests-form' )
			);

			$lines   = array();
			$lines[] = sprintf( __( 'A new Service Request has been submitted.', 'service-requests-form' ) );
			$lines[] = '';
			$lines[] = sprintf( __( 'Request ID: %d', 'service-requests-form' ), $post_id );
			$lines[] = sprintf( __( 'Status: %s', 'service-requests-form' ), $status );
			$lines[] = sprintf( __( 'Service: %s', 'service-requests-form' ), $service_title );
			$lines[] = '';
			$lines[] = sprintf( __( 'Name: %s', 'service-requests-form' ), $name );
			$lines[] = sprintf( __( 'Company: %s', 'service-requests-form' ), $company );
			$lines[] = sprintf( __( 'Email: %s', 'service-requests-form' ), $email );
			$lines[] = sprintf( __( 'Phone: %s', 'service-requests-form' ), $phone );
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

			// File links
			$lines[] = __( 'Uploaded Files:', 'service-requests-form' );

			if ( is_array( $file_ids ) && ! empty( $file_ids ) ) {
				foreach ( $file_ids as $aid ) {
					$aid = (int) $aid;
					if ( ! $aid ) continue;

					$url  = wp_get_attachment_url( $aid );
					$name = get_the_title( $aid );

					if ( $url ) {
						$lines[] = '- ' . ( $name ? $name : ( 'File #' . $aid ) ) . ': ' . $url;
					}
				}
			} else {
				$lines[] = '- ' . __( 'No files uploaded.', 'service-requests-form' );
			}

			$message = implode( "\n", $lines );

			$headers = array();
			$headers[] = 'Content-Type: text/plain; charset=UTF-8';

			// Reply-To customer email if valid
			if ( $email && is_email( $email ) ) {
				$headers[] = 'Reply-To: ' . $email;
			}

			wp_mail( $to, $subject, $message, $headers );
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

			// Make sure WP upload helpers exist
			if ( ! function_exists( 'wp_handle_upload' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}

			$allowed_ext = (array) self::get_allowed_extensions();
			$max_bytes   = (int) self::get_max_file_size_bytes();

			$files = isset( $_FILES['srf_files'] ) ? $_FILES['srf_files'] : null;
			$items = self::normalize_files_array( $files );

			$attachment_ids = array();
			$total_bytes    = 0;

			$user_id = (int) get_post_field( 'post_author', $post_id );
			if ( ! $user_id ) {
				$user_id = get_current_user_id();
			}

			// Nothing uploaded
			if ( empty( $items ) ) {
				return array( $attachment_ids, 0 );
			}

			// 1) Enforce 1GB TOTAL per user (sum this request first)
			$quota = (int) self::get_user_quota_bytes();
			$used  = (int) self::get_user_used_bytes( $user_id );

			$new_total = 0;
			foreach ( $items as $f ) {
				$err  = isset( $f['error'] ) ? (int) $f['error'] : UPLOAD_ERR_NO_FILE;
				$size = isset( $f['size'] ) ? (int) $f['size'] : 0;

				// Only count files that really uploaded OK
				if ( $err === UPLOAD_ERR_OK && $size > 0 ) {
					$new_total += $size;
				}
			}

			if ( $new_total > 0 && ( $used + $new_total ) > $quota ) {
				throw new Exception(
					__( 'Upload limit reached (1GB total). Please wait until your previous request is completed.', 'service-requests-form' )
				);
			}

			// 2) Per-file validation + upload
			foreach ( $items as $file ) {

				$err = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

				if ( $err === UPLOAD_ERR_NO_FILE ) {
					continue;
				}

				if ( $err !== UPLOAD_ERR_OK ) {
					throw new Exception( __( 'One of the uploaded files failed to upload. Please try again.', 'service-requests-form' ) );
				}

				$file_name  = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : '';
				$file_bytes = isset( $file['size'] ) ? (int) $file['size'] : 0;

				if ( $file_name === '' ) {
					throw new Exception( __( 'Uploaded file is missing a name.', 'service-requests-form' ) );
				}

				if ( $file_bytes > 0 && $file_bytes > $max_bytes ) {
					throw new Exception(
						sprintf(
							__( 'File "%s" is too large. Maximum allowed size is %d MB.', 'service-requests-form' ),
							$file_name,
							(int) ( $max_bytes / 1024 / 1024 )
						)
					);
				}

				// Extension check (whitelist)
				$ext = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );
				if ( empty( $ext ) || ! in_array( $ext, $allowed_ext, true ) ) {
					throw new Exception(
						sprintf(
							__( 'File type not allowed: "%s". Allowed: %s', 'service-requests-form' ),
							$file_name,
							implode( ', ', $allowed_ext )
						)
					);
				}

				/**
				 * Extra safety: check actual filetype/extension match (reduces spoofing)
				 * We still enforce your whitelist above.
				 */
				if ( ! empty( $file['tmp_name'] ) ) {
					$checked = wp_check_filetype_and_ext( $file['tmp_name'], $file_name );
					if ( ! empty( $checked['ext'] ) ) {
						$real_ext = strtolower( $checked['ext'] );
						if ( ! in_array( $real_ext, $allowed_ext, true ) ) {
							throw new Exception(
								sprintf(
									__( 'File type not allowed: "%s".', 'service-requests-form' ),
									$file_name
								)
							);
						}
					}
				}

				$overrides = array( 'test_form' => false );
				$movefile  = wp_handle_upload( $file, $overrides );

				if ( ! $movefile || isset( $movefile['error'] ) ) {
					throw new Exception( __( 'Upload failed. Please try again.', 'service-requests-form' ) );
				}

				$attachment = array(
					'post_mime_type' => isset( $movefile['type'] ) ? $movefile['type'] : '',
					'post_title'     => $file_name,
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

				// Store bytes for cleanup + storage management
				update_post_meta( $attach_id, '_srf_file_bytes', $file_bytes );

				$attachment_ids[] = (int) $attach_id;

				// Increase user used bytes as uploads succeed
				if ( $file_bytes > 0 ) {
					self::add_user_used_bytes( $user_id, $file_bytes );
					$total_bytes += $file_bytes;
				}
			}

			return array( $attachment_ids, $total_bytes );
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

				// Business role check
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
				} elseif ( class_exists( 'SR_Service_Data' ) && ! SR_Service_Data::is_valid_service_id( (int) $old_data['service'] ) ) {
					$errors[] = __( 'Selected service is not valid.', 'service-requests-form' );
				}

				// Required fields
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

				// Terms
				if ( empty( $old_data['terms'] ) || $old_data['terms'] !== '1' ) {
					$errors[] = __( 'You must accept the Terms & Conditions.', 'service-requests-form' );
				}

				// Shipping address
				$shipping_address = isset( $_POST['srf_shipping_address'] )
					? trim( sanitize_textarea_field( wp_unslash( $_POST['srf_shipping_address'] ) ) )
					: '';

				if ( $shipping_address === '' ) {
					$errors[] = __( 'Please set up your shipping address in My Account before submitting a request.', 'service-requests-form' );
				}

				// Phase 5 rule: must upload OR check "no file"
				$no_file_checked = ! empty( $_POST['srf_no_file'] );
				$names           = isset( $_FILES['srf_files']['name'] ) ? $_FILES['srf_files']['name'] : array();
				$has_any         = is_array( $names ) ? ( count( array_filter( $names ) ) > 0 ) : ! empty( $names );

				if ( ! $no_file_checked && ! $has_any ) {
					$errors[] = __( 'Please upload at least one file, or check "I don’t have a file yet / not needed".', 'service-requests-form' );
				}

				// Save request + upload files
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

						// Store request meta
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
							// Upload + attach
							list( $attachment_ids, $uploaded_bytes ) = self::handle_request_uploads( $post_id );

							// Always store as array
							if ( ! is_array( $attachment_ids ) ) {
								$attachment_ids = array();
							}

							update_post_meta( $post_id, '_sr_file_ids', $attachment_ids );

						} catch ( Exception $e ) {

							// Roll back: delete attachments
							if ( ! empty( $attachment_ids ) ) {
								foreach ( $attachment_ids as $aid ) {
									wp_delete_attachment( (int) $aid, true );
								}
							}

							// Roll back: restore quota
							if ( $uploaded_bytes > 0 ) {
								self::subtract_user_used_bytes( $user_id, $uploaded_bytes );
							}

							// Delete request post (avoid broken requests)
							wp_delete_post( $post_id, true );

							$errors[] = $e->getMessage();
						}

						// Success redirect
						if ( empty( $errors ) ) {

							// ✅ Admin email notification
							if ( method_exists( __CLASS__, 'send_admin_new_request_email' ) ) {
								self::send_admin_new_request_email( $post_id );
							}

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
