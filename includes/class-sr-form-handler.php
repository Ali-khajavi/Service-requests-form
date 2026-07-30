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
		 * Limits the filetype override below to the already validated project
		 * upload currently being moved by wp_handle_upload().
		 *
		 * 3MF files are ZIP containers, so PHP/fileinfo commonly reports them as
		 * application/zip. Without this scoped override WordPress can reject a
		 * structurally valid 3MF file because its detected MIME differs from the
		 * model MIME registered for the extension.
		 *
		 * @var bool
		 */
		protected static $project_upload_validation_active = false;

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
			add_action( 'srf_request_marked_done', array( __CLASS__, 'cleanup_request_files_public' ), 10, 2 );
		}

		public static function register_assets() {
			if ( ! defined( 'SRF_PLUGIN_URL' ) || ! defined( 'SRF_VERSION' ) ) {
				return;
			}

			wp_register_style(
				'srf-frontend-css',
				SRF_PLUGIN_URL . 'assets/css/frontend.css',
				array(),
				SRF_VERSION
			);
			wp_register_style(
				'srf-project-stepper-css',
				SRF_PLUGIN_URL . 'assets/css/project-stepper-0.10.90.css',
				array( 'srf-frontend-css' ),
				SRF_VERSION
			);
			wp_register_script(
				'srf-frontend-js',
				SRF_PLUGIN_URL . 'assets/js/frontend.js',
				array(),
				SRF_VERSION,
				true
			);
			wp_register_script(
				'srf-project-viewer-js',
				SRF_PLUGIN_URL . 'assets/js/project-viewer-webgl.js',
				array(),
				SRF_VERSION,
				true
			);
			wp_register_script(
				'srf-project-js',
				SRF_PLUGIN_URL . 'assets/js/project.js',
				array( 'srf-project-viewer-js' ),
				SRF_VERSION,
				true
			);
		}

		protected static function enqueue_frontend_base_assets() {
			if ( ! wp_style_is( 'srf-frontend-css', 'registered' ) ) {
				self::register_assets();
			}
			wp_enqueue_style( 'srf-frontend-css' );
		}

		protected static function enqueue_service_request_assets() {
			self::enqueue_frontend_base_assets();
			if ( ! wp_script_is( 'srf-frontend-js', 'registered' ) ) {
				self::register_assets();
			}
			wp_enqueue_script( 'srf-frontend-js' );
			self::localize_service_script();
			self::inject_service_data();
		}

		protected static function enqueue_project_request_assets() {
			self::enqueue_frontend_base_assets();
			if ( ! wp_style_is( 'srf-project-stepper-css', 'registered' ) ) {
				self::register_assets();
			}
			wp_enqueue_style( 'srf-project-stepper-css' );
			if ( ! wp_script_is( 'srf-project-js', 'registered' ) ) {
				self::register_assets();
			}
			wp_enqueue_script( 'srf-project-js' );
			self::localize_project_script();
		}

		protected static function is_coming_soon_enabled( $context = 'service' ) {
			$legacy = (bool) get_option( 'srf_coming_soon_enabled', false );

			if ( 'project' === $context ) {
				$value = get_option( 'srf_coming_soon_project_enabled', null );
				return null === $value ? $legacy : (bool) $value;
			}

			$value = get_option( 'srf_coming_soon_service_enabled', null );
			return null === $value ? $legacy : (bool) $value;
		}

		protected static function render_coming_soon_banner( $context = 'service' ) {
			$context = (string) $context;
			$title   = __( 'Coming Soon', 'service-requests-form' );
			$message = ( 'project' === $context )
				? __( 'The project request form is being prepared. Please check back soon.', 'service-requests-form' )
				: __( 'The service request form is being prepared. Please check back soon.', 'service-requests-form' );

			$logo = function_exists( 'get_custom_logo' ) ? get_custom_logo() : '';
			if ( empty( $logo ) ) {
				$logo = '<div class="srf-coming-soon__site-name">' . esc_html( get_bloginfo( 'name' ) ) . '</div>';
			}

			ob_start();
			?>
			<div class="srf-wrapper srf-coming-soon-wrap">
				<div class="srf-coming-soon" role="status" aria-live="polite">
					<div class="srf-coming-soon__brand"><?php echo $logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
					<div class="srf-coming-soon__badge"><?php echo esc_html( $title ); ?></div>
					<h2 class="srf-coming-soon__title"><?php echo esc_html( $title ); ?></h2>
					<p class="srf-coming-soon__message"><?php echo esc_html( $message ); ?></p>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}

		protected static function localize_service_script() {
			static $localized = false;
			if ( $localized ) {
				return;
			}

			wp_localize_script(
				'srf-frontend-js',
				'srfFrontend',
				array(
					'can_submit'     => self::current_user_can_submit(),
					'popup_title'    => __( 'Login required', 'service-requests-form' ),
					'popup_message'  => __( 'Please log in to submit a service request.', 'service-requests-form' ),
					'popup_button'   => __( 'OK', 'service-requests-form' ),
					'close'          => __( 'Close', 'service-requests-form' ),
					'view_larger'    => __( 'View larger image', 'service-requests-form' ),
					'variants'       => __( 'Variants', 'service-requests-form' ),
					'show_more'      => __( 'Show more', 'service-requests-form' ),
					'show_less'      => __( 'Show less', 'service-requests-form' ),
					'choose_service'               => __( 'Please choose a service', 'service-requests-form' ),
					'price_label'                  => __( 'Price', 'service-requests-form' ),
					'no_model_loaded'              => __( 'No model loaded yet.', 'service-requests-form' ),
					'viewer_ready'                 => __( 'Viewer ready. Upload an STL or OBJ file to preview it.', 'service-requests-form' ),
					'preview_available_other'       => __( 'Preview is available for STL and OBJ files. Other files can still be uploaded.', 'service-requests-form' ),
					'viewer_loading'               => __( 'Loading 3D preview…', 'service-requests-form' ),
					'preview_stl_obj_only'          => __( 'Preview is currently available for STL and OBJ files.', 'service-requests-form' ),
					'viewer_preview_ready'          => __( '3D preview ready. Drag to rotate, use Shift+drag to pan, and use the wheel or zoom buttons.', 'service-requests-form' ),
					'viewer_load_failed'            => __( 'The viewer could not load this model.', 'service-requests-form' ),
					'preview_failed'                => __( 'Preview failed.', 'service-requests-form' ),
					'stl_parse_failed'              => __( 'This STL file could not be parsed.', 'service-requests-form' ),
					'obj_parse_failed'              => __( 'This OBJ file could not be parsed.', 'service-requests-form' ),
					'project_title_required'        => __( 'Please enter the project title first.', 'service-requests-form' ),
					'project_description_required'  => __( 'Please enter the project description first.', 'service-requests-form' ),
					'project_login_required'        => __( 'Please log in or register first to continue to the upload step.', 'service-requests-form' ),
					'model_upload_required'         => __( 'Please upload a 3D model first.', 'service-requests-form' ),
					'model_uploaded'                => __( 'Model uploaded', 'service-requests-form' ),
					'variation_number'              => __( 'Variation %d', 'service-requests-form' ),
					'select_named'                  => __( 'Select %s', 'service-requests-form' ),
					'select_profile_service'        => __( 'Select profile service', 'service-requests-form' ),
					'service_number'                => __( 'Service #%d', 'service-requests-form' ),
				)
			);
			$localized = true;
		}

		protected static function localize_project_script() {
			static $localized = false;
			if ( $localized ) {
				return;
			}

			$profiles = class_exists( 'SRF_Print_Profiles' ) ? SRF_Print_Profiles::get_profiles() : array();
			$profiles_enabled = ! class_exists( 'SRF_Print_Profiles' ) || SRF_Print_Profiles::is_enabled();
			$default_profile  = class_exists( 'SRF_Print_Profiles' ) ? SRF_Print_Profiles::get_default_profile_key() : 'custom';
			wp_localize_script(
				'srf-project-js',
				'srfProject',
				array(
					'workerUrl'       => SRF_PLUGIN_URL . 'assets/js/model-worker.js',
					'previewTriangles'=> 160000,
					'accessMode'      => self::get_project_access_mode(),
					'checkoutEnabled' => self::is_project_checkout_enabled(),
					'profiles'        => $profiles,
					'profilesEnabled' => $profiles_enabled,
					'defaultProfile'  => $default_profile,
					'messages'        => array(
						'parsing'                   => __( 'Analysing the model in the background…', 'service-requests-form' ),
						'previewReady'              => __( 'Preview ready. The server will verify the final amount before payment.', 'service-requests-form' ),
						'previewError'              => __( 'The browser preview could not be created. You can still submit the file for secure server-side analysis.', 'service-requests-form' ),
						'threeMf'                   => __( '3MF is securely analysed after submission. Instant browser preview is available for STL and OBJ.', 'service-requests-form' ),
						'fileRequired'              => __( 'Select at least one STL, OBJ, or 3MF model.', 'service-requests-form' ),
						'calculating'               => __( 'Preparing the secure quote and checkout…', 'service-requests-form' ),
						'unknownEstimate'           => __( 'The final amount will be calculated securely after submission.', 'service-requests-form' ),
						'doesNotFit'                => __( 'The selected model does not fit this printer at the current scale.', 'service-requests-form' ),
						'completeRequired'          => __( 'Complete the required fields before continuing.', 'service-requests-form' ),
						'waitAnalysis'              => __( 'Please wait while the model is analysed.', 'service-requests-form' ),
						'couldNotReadFile'          => __( 'Could not read the file.', 'service-requests-form' ),
						'previewStopped'            => __( 'The background model analyser stopped unexpectedly.', 'service-requests-form' ),
						'analysisCancelled'         => __( 'Model analysis was cancelled.', 'service-requests-form' ),
						'analysisUnsupported'       => __( 'Background model analysis is not supported in this browser.', 'service-requests-form' ),
						'selectPreviewModel'        => __( 'Select an STL or OBJ model', 'service-requests-form' ),
						'viewerScale'               => __( 'Scale', 'service-requests-form' ),
						'previewBed'                => __( 'Preview bed', 'service-requests-form' ),
						'viewerFitUnknown'          => __( 'Select a printer for build-volume guidance', 'service-requests-form' ),
						'viewerFits'                => __( 'Fits the selected build volume', 'service-requests-form' ),
						'viewerDoesNotFit'          => __( 'Does not fit the selected build volume', 'service-requests-form' ),
						'useSelectButton'           => __( 'Use the Select models button to add these files in this browser.', 'service-requests-form' ),
						'fileTotalExceeds'          => __( 'The selected files total %1$s, above the %2$s upload limit.', 'service-requests-form' ),
						'waiting'                   => __( 'Waiting', 'service-requests-form' ),
						'serverAnalysis'            => __( 'Server analysis', 'service-requests-form' ),
						'largeServerAnalysis'       => __( 'Large file: server analysis', 'service-requests-form' ),
						'analysing'                 => __( 'Analysing…', 'service-requests-form' ),
						'ready'                     => __( 'Ready', 'service-requests-form' ),
						'profileLocked'             => __( 'This named Bambu process controls layer height, infill, walls, and top/bottom layers. Choose Custom settings to edit them.', 'service-requests-form' ),
						'customSettings'            => __( 'Custom settings', 'service-requests-form' ),
						'invalidLayer'              => __( 'The selected layer height is outside this printer’s supported range.', 'service-requests-form' ),
						'selectPrinterBuild'        => __( 'Select a printer to check build volume.', 'service-requests-form' ),
						'buildCheckDuringAnalysis'  => __( 'Build-volume check occurs during secure server analysis.', 'service-requests-form' ),
						'instantEstimateCheckout'   => __( 'Instant geometry estimate. The uploaded files are recalculated securely on the server before this amount is placed in checkout.', 'service-requests-form' ),
						'instantEstimateSaved'      => __( 'Instant geometry estimate. The server recalculates and stores the final quote when you submit.', 'service-requests-form' ),
						'fitsScale'                 => __( 'Fits the selected build volume at %s%% scale.', 'service-requests-form' ),
						'buildCheckServer'          => __( 'Build-volume check will be completed securely on the server.', 'service-requests-form' ),
						'workerErrors'              => array(
							'The model does not contain readable triangles.' => __( 'The model does not contain readable triangles.', 'service-requests-form' ),
							'The STL file is too small.' => __( 'The STL file is too small.', 'service-requests-form' ),
							'The binary STL structure is invalid.' => __( 'The binary STL structure is invalid.', 'service-requests-form' ),
							'This OBJ is too complex for an instant browser preview. It can still be analysed securely on the server.' => __( 'This OBJ is too complex for an instant browser preview. It can still be analysed securely on the server.', 'service-requests-form' ),
							'The OBJ file does not contain readable vertices and faces.' => __( 'The OBJ file does not contain readable vertices and faces.', 'service-requests-form' ),
							'No model data was supplied.' => __( 'No model data was supplied.', 'service-requests-form' ),
							'Instant preview supports STL and OBJ. This model will be analysed securely on the server.' => __( 'Instant preview supports STL and OBJ. This model will be analysed securely on the server.', 'service-requests-form' ),
							'The model could not be analysed in the browser.' => __( 'The model could not be analysed in the browser.', 'service-requests-form' ),
						),
					),
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
			return size_format( self::get_project_upload_limit_bytes() );
		}

		protected static function get_project_allowed_extensions() {
			$supported = class_exists( 'SRF_Project_Pricing' ) ? SRF_Project_Pricing::get_supported_extensions() : array( 'stl', 'obj', '3mf' );
			$raw       = class_exists( 'SR_Settings' ) ? (string) get_option( SR_Settings::OPTION_ALLOWED_EXTENSIONS, 'stl,obj,3mf' ) : 'stl,obj,3mf';
			$configured = array();
			foreach ( explode( ',', strtolower( $raw ) ) as $extension ) {
				$extension = ltrim( sanitize_file_name( trim( $extension ) ), '.' );
				if ( in_array( $extension, $supported, true ) ) {
					$configured[] = $extension;
				}
			}
			$configured = array_values( array_unique( $configured ) );
			return $configured ? $configured : $supported;
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
				$raw_supported_profile_ids = isset( $printer->supported_service_profile_ids ) ? $printer->supported_service_profile_ids : '';
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

				if ( ! empty( $raw_supported_profile_ids ) ) {
					$decoded_profiles = json_decode( (string) $raw_supported_profile_ids, true );
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

			$raw_supported_profile_ids = isset( $printer->supported_service_profile_ids ) ? $printer->supported_service_profile_ids : '';
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

			if ( ! empty( $raw_supported_profile_ids ) ) {
				$decoded_profiles = json_decode( (string) $raw_supported_profile_ids, true );
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
			foreach ( self::get_project_mime_map() as $extension => $mime ) {
				$mimes[ $extension ] = $mime;
			}
			return $mimes;
		}

		/**
		 * Normalize the filetype only for a project model that has already passed
		 * extension, size, upload-error, and basic structure validation.
		 *
		 * @param array       $data      WordPress filetype result.
		 * @param string      $file      Full path to the temporary file.
		 * @param string      $filename  Original filename.
		 * @param string[]|null $mimes   Allowed MIME map.
		 * @param string|false $real_mime MIME detected by PHP/fileinfo.
		 * @return array
		 */
		public static function normalize_project_filetype( $data, $file, $filename, $mimes, $real_mime = false ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
			if ( ! self::$project_upload_validation_active ) {
				return $data;
			}

			$extension = strtolower( pathinfo( (string) $filename, PATHINFO_EXTENSION ) );
			$mime_map  = self::get_project_mime_map();

			if ( ! isset( $mime_map[ $extension ] ) ) {
				return $data;
			}

			return array(
				'ext'             => $extension,
				'type'            => $mime_map[ $extension ],
				'proper_filename' => false,
			);
		}

		protected static function get_project_mime_map() {
			return array(
				'stl' => 'model/stl',
				'obj' => 'text/plain',
				'3mf' => 'application/vnd.ms-package.3dmanufacturing-3dmodel+xml',
			);
		}

		/**
		 * Validate the extension and a small amount of file structure before the
		 * model is moved into the media library. The pricing parser performs the
		 * complete geometry validation after upload.
		 */
		protected static function validate_project_uploaded_file( $file ) {
			$filename = isset( $file['name'] ) ? (string) $file['name'] : '';
			$tmp_name = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
			$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

			if ( ! $ext || ! in_array( $ext, self::get_project_allowed_extensions(), true ) ) {
				throw new Exception(
					sprintf(
						__( 'File type not allowed: %1$s. Allowed model formats: %2$s.', 'service-requests-form' ),
						$filename,
						self::get_project_allowed_extensions_label()
					)
				);
			}
			if ( '' === $tmp_name || ! is_readable( $tmp_name ) ) {
				throw new Exception( sprintf( __( 'The uploaded file could not be read: %s.', 'service-requests-form' ), $filename ) );
			}

			$size = (int) @filesize( $tmp_name );
			if ( $size <= 0 ) {
				throw new Exception( sprintf( __( 'File "%s" is empty.', 'service-requests-form' ), $filename ) );
			}

			if ( 'stl' === $ext ) {
				$handle = @fopen( $tmp_name, 'rb' );
				$head   = $handle ? fread( $handle, 84 ) : '';
				if ( $handle ) {
					fclose( $handle );
				}
				$is_ascii  = 0 === strncasecmp( ltrim( (string) $head ), 'solid', 5 );
				$is_binary = false;
				if ( strlen( $head ) >= 84 ) {
					$count      = unpack( 'Vfaces', substr( $head, 80, 4 ) );
					$face_count = isset( $count['faces'] ) ? (int) $count['faces'] : 0;
					$is_binary  = $face_count > 0 && ( 84 + ( $face_count * 50 ) ) <= $size;
				}
				if ( ! $is_ascii && ! $is_binary ) {
					throw new Exception( sprintf( __( 'The STL structure is invalid: %s.', 'service-requests-form' ), $filename ) );
				}
			} elseif ( 'obj' === $ext ) {
				/*
				 * Do not inspect only the beginning of an OBJ. Exporters normally write
				 * every vertex before the first face, so a detailed model can have several
				 * megabytes of valid vertex records before any "f" record appears. The
				 * former 1 MB sample therefore rejected valid files that the browser had
				 * already previewed successfully. Scan records incrementally instead; this
				 * uses constant memory and stops as soon as both structures are found.
				 */
				$handle = @fopen( $tmp_name, 'rb' );
				if ( ! $handle ) {
					throw new Exception( sprintf( __( 'The uploaded file could not be read: %s.', 'service-requests-form' ), $filename ) );
				}

				$has_vertex = false;
				$has_face   = false;
				$lines_seen = 0;
				$max_lines  = 4000000;

				try {
					while ( false !== ( $line = fgets( $handle ) ) ) {
						$lines_seen++;
						if ( 1 === $lines_seen ) {
							$line = preg_replace( '/^\xEF\xBB\xBF/', '', $line );
						}

						if ( ! $has_vertex && preg_match( '/^\s*v\s+[-+]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][-+]?\d+)?\s+[-+]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][-+]?\d+)?\s+[-+]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][-+]?\d+)?(?:\s|$)/', $line ) ) {
							$has_vertex = true;
						}
						if ( ! $has_face && preg_match( '/^\s*f\s+[^\s#]+\s+[^\s#]+\s+[^\s#]+(?:\s|$)/', $line ) ) {
							$has_face = true;
						}

						if ( $has_vertex && $has_face ) {
							break;
						}
						if ( $lines_seen >= $max_lines ) {
							break;
						}
					}
				} finally {
					fclose( $handle );
				}

				if ( ! $has_vertex || ! $has_face ) {
					throw new Exception( sprintf( __( 'The OBJ file does not contain readable vertices and faces: %s.', 'service-requests-form' ), $filename ) );
				}
			} elseif ( '3mf' === $ext ) {
				$handle = @fopen( $tmp_name, 'rb' );
				$magic  = $handle ? fread( $handle, 2 ) : '';
				if ( $handle ) {
					fclose( $handle );
				}
				if ( 'PK' !== $magic ) {
					throw new Exception( sprintf( __( 'The 3MF package is invalid: %s.', 'service-requests-form' ), $filename ) );
				}
				if ( class_exists( 'ZipArchive' ) ) {
					$zip = new ZipArchive();
					if ( true !== $zip->open( $tmp_name ) ) {
						throw new Exception( sprintf( __( 'The 3MF package could not be opened: %s.', 'service-requests-form' ), $filename ) );
					}
					$has_model = false;
					for ( $i = 0; $i < $zip->numFiles; $i++ ) {
						$name = $zip->getNameIndex( $i );
						if ( is_string( $name ) && preg_match( '/\.model$/i', $name ) ) {
							$has_model = true;
							break;
						}
					}
					$zip->close();
					if ( ! $has_model ) {
						throw new Exception( sprintf( __( 'The 3MF package does not contain a model: %s.', 'service-requests-form' ), $filename ) );
					}
				}
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

			$user = get_userdata( $user_id );
			$roles = ( $user && is_array( $user->roles ) ) ? $user->roles : array();
			if ( array_intersect( $roles, array( 'business_user', 'administrator' ) ) ) {
				return 10 * 1024 * 1024 * 1024;
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
				if ( $quota <= self::DEFAULT_USER_QUOTA_BYTES ) {
					throw new Exception(
						__( 'Over 1 GB storage is only possible for Business accounts. Please contact our IT team.', 'service-requests-form' )
					);
				}

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
			$account_limit = self::current_user_is_business()
				? 10 * 1024 * 1024 * 1024
				: 1 * 1024 * 1024 * 1024;

			$configured_mb = class_exists( 'SR_Settings' )
				? (int) get_option( SR_Settings::OPTION_MAX_UPLOAD_SIZE, 500 )
				: 500;
			$configured_limit = max( 1, $configured_mb ) * 1024 * 1024;
			$server_limit = function_exists( 'wp_max_upload_size' ) ? (int) wp_max_upload_size() : $account_limit;

			return max( 1, min( $account_limit, $configured_limit, $server_limit ) );
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

		protected static function render_service_login_gate( $context = 'service' ) {
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
					<p><?php echo esc_html( 'project' === $context ? __( 'Please log in before ordering a custom 3D print.', 'service-requests-form' ) : __( 'Please log in before submitting a service request.', 'service-requests-form' ) ); ?></p>

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

		protected static function handle_request_uploads( $post_id, $custom_max_bytes = 0, $project_only = false ) {
			$post_id = (int) $post_id;
			$project_only = (bool) $project_only;

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

			if ( $project_only && $total_bytes > $max ) {
				throw new Exception(
					sprintf(
						__( 'The combined model upload exceeds the maximum total size (%s).', 'service-requests-form' ),
						size_format( $max )
					)
				);
			}

			// Quota check (before uploading)
			if ( $user_id ) {
				self::ensure_user_quota( $user_id, $total_bytes );
			}

			$attachment_ids = array();

			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			try {
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

				$allowed = $project_only
					? in_array( strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ), self::get_project_allowed_extensions(), true )
					: self::extension_is_allowed( $name );

				if ( ! $allowed ) {
					throw new Exception(
						sprintf(
							__( 'File type not allowed: "%s".', 'service-requests-form' ),
							$name
						)
					);
				}

				$overrides = array(
					'test_form' => false,
					'mimes'     => $project_only ? self::get_project_mime_map() : null,
				);
				if ( $project_only ) {
					self::validate_project_uploaded_file( $file );
				}
				if ( $project_only ) {
					self::$project_upload_validation_active = true;
					add_filter( 'wp_check_filetype_and_ext', array( __CLASS__, 'normalize_project_filetype' ), 10, 5 );
				}

				try {
					$uploaded = wp_handle_upload( $file, $overrides );
				} finally {
					if ( $project_only ) {
						remove_filter( 'wp_check_filetype_and_ext', array( __CLASS__, 'normalize_project_filetype' ), 10 );
						self::$project_upload_validation_active = false;
					}
				}

				if ( ! is_array( $uploaded ) || ! empty( $uploaded['error'] ) ) {
					$msg = is_array( $uploaded ) && ! empty( $uploaded['error'] ) ? $uploaded['error'] : __( 'Unknown upload error.', 'service-requests-form' );
					throw new Exception( sprintf( __( 'Upload failed for "%1$s": %2$s', 'service-requests-form' ), $name, $msg ) );
				}

				$filetype = wp_check_filetype( $uploaded['file'], $project_only ? self::get_project_mime_map() : null );

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
			} catch ( Exception $e ) {
				// Do not leave partially uploaded attachments behind when a later file
				// fails validation or media-library insertion.
				foreach ( $attachment_ids as $attachment_id ) {
					wp_delete_attachment( (int) $attachment_id, true );
				}
				throw $e;
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
		/**
		 * Public notification entry point used by the WooCommerce payment hooks.
		 *
		 * @return bool Whether wp_mail() accepted the message.
		 */
		public static function send_admin_new_request_email_public( $post_id ) {
			return self::send_admin_new_request_email( $post_id );
		}

		/**
		 * Send the administrator a service notification or a paid-project
		 * production notification.
		 *
		 * Project requests that require checkout are intentionally not emailed
		 * here until WooCommerce reports the order as paid.
		 *
		 * @return bool Whether wp_mail() accepted the message.
		 */
		protected static function send_admin_new_request_email( $post_id ) {

			$post_id = (int) $post_id;
			if ( ! $post_id ) {
				return false;
			}

			$to = '';
			if ( class_exists( 'SR_Settings' ) ) {
				$to = (string) get_option( SR_Settings::OPTION_NOTIFY_ADMIN_EMAIL, '' );
			}
			if ( empty( $to ) || ! is_email( $to ) ) {
				$to = (string) get_option( 'srf_admin_email', '' ); // Legacy option.
			}
			if ( empty( $to ) || ! is_email( $to ) ) {
				$to = (string) get_option( 'admin_email' );
			}
			if ( empty( $to ) || ! is_email( $to ) ) {
				return false;
			}

			$request_type     = (string) get_post_meta( $post_id, '_sr_request_type', true );
			$is_project       = 'project' === $request_type;
			$service_title    = (string) get_post_meta( $post_id, '_sr_service_title', true );
			$project_title    = (string) get_post_meta( $post_id, '_sr_project_title', true );
			$name             = (string) get_post_meta( $post_id, '_sr_name', true );
			$company          = (string) get_post_meta( $post_id, '_sr_company', true );
			$email            = (string) get_post_meta( $post_id, '_sr_email', true );
			$phone            = (string) get_post_meta( $post_id, '_sr_phone', true );
			$shipping_address = (string) get_post_meta( $post_id, '_sr_shipping_address', true );
			$description      = (string) get_post_meta( $post_id, '_sr_description', true );
			$status           = (string) get_post_meta( $post_id, '_sr_status', true );
			$file_ids         = get_post_meta( $post_id, '_sr_file_ids', true );
			$variants         = get_post_meta( $post_id, '_sr_variants', true );
			$order_id         = (int) get_post_meta( $post_id, '_sr_wc_order_id', true );

			if ( empty( $status ) ) {
				$status = 'new';
			}
			if ( '' === $project_title ) {
				$project_title = get_the_title( $post_id );
			}

			$edit_link  = admin_url( 'post.php?post=' . $post_id . '&action=edit' );
			$order_link = $order_id > 0 ? admin_url( 'post.php?post=' . $order_id . '&action=edit' ) : '';

			if ( $is_project ) {
				$subject = sprintf(
					'paid' === $status
						? __( '[Paid 3D Print Project #%1$d] %2$s', 'service-requests-form' )
						: __( '[3D Print Project #%1$d] %2$s', 'service-requests-form' ),
					$post_id,
					$project_title ? $project_title : __( 'Custom 3D Print', 'service-requests-form' )
				);
			} else {
				$subject = sprintf(
					__( '[Service Request #%1$d] %2$s', 'service-requests-form' ),
					$post_id,
					$service_title ? $service_title : __( 'New Request', 'service-requests-form' )
				);
			}

			$lines   = array();
			$lines[] = $is_project && 'paid' === $status
				? __( 'A custom 3D print project has been paid and is ready for production review.', 'service-requests-form' )
				: ( $is_project
					? __( 'A custom 3D print project and secure quote have been submitted.', 'service-requests-form' )
					: __( 'A new service request has been submitted.', 'service-requests-form' ) );
			$lines[] = '';
			$lines[] = sprintf( __( 'Request ID: %d', 'service-requests-form' ), $post_id );
			$lines[] = sprintf( __( 'Request Type: %s', 'service-requests-form' ), $is_project ? __( 'Custom 3D print project', 'service-requests-form' ) : __( 'Predefined service', 'service-requests-form' ) );
			$lines[] = sprintf( __( 'Status: %s', 'service-requests-form' ), $status );
			$lines[] = sprintf( __( 'Service / Project: %s', 'service-requests-form' ), $is_project ? $project_title : $service_title );

			if ( $order_id > 0 ) {
				$lines[] = sprintf( __( 'WooCommerce Order: #%d', 'service-requests-form' ), $order_id );
				if ( $order_link ) {
					$lines[] = sprintf( __( 'Order Link: %s', 'service-requests-form' ), $order_link );
				}
			}

			if ( $is_project ) {
				$printer       = (string) get_post_meta( $post_id, '_sr_printer_name', true );
				$material      = (string) get_post_meta( $post_id, '_sr_material_name', true );
				$profile       = (string) get_post_meta( $post_id, '_sr_print_profile_name', true );
				$quantity      = max( 1, (int) get_post_meta( $post_id, '_sr_quantity', true ) );
				$scale         = max( 10, (int) get_post_meta( $post_id, '_sr_scale', true ) );
				$minutes       = max( 0, (int) get_post_meta( $post_id, '_sr_estimated_print_minutes', true ) );
				$total_price   = (float) get_post_meta( $post_id, '_sr_total_price', true );
				$currency      = (string) get_post_meta( $post_id, '_sr_currency', true );
				$currency_sign = (string) get_post_meta( $post_id, '_sr_currency_symbol', true );
				$layer_height  = (float) get_post_meta( $post_id, '_sr_layer_height', true );
				$infill        = (int) get_post_meta( $post_id, '_sr_infill', true );
				$supports      = '1' === (string) get_post_meta( $post_id, '_sr_supports', true );

				$lines[] = '';
				$lines[] = __( 'Production Configuration:', 'service-requests-form' );
				$lines[] = sprintf( __( 'Printer: %s', 'service-requests-form' ), $printer ? $printer : '-' );
				$lines[] = sprintf( __( 'Material: %s', 'service-requests-form' ), $material ? $material : '-' );
				$lines[] = sprintf( __( 'Print profile: %s', 'service-requests-form' ), $profile ? $profile : '-' );
				$lines[] = sprintf( __( 'Layer height: %s mm', 'service-requests-form' ), number_format_i18n( $layer_height, 2 ) );
				$lines[] = sprintf( __( 'Infill: %d%%', 'service-requests-form' ), $infill );
				$lines[] = sprintf( __( 'Supports: %s', 'service-requests-form' ), $supports ? __( 'Yes', 'service-requests-form' ) : __( 'No', 'service-requests-form' ) );
				$lines[] = sprintf( __( 'Scale: %d%%', 'service-requests-form' ), $scale );
				$lines[] = sprintf( __( 'Quantity: %d', 'service-requests-form' ), $quantity );
				if ( $minutes > 0 ) {
					$lines[] = sprintf( __( 'Estimated print time: %s', 'service-requests-form' ), self::format_project_minutes( $minutes ) );
				}
				if ( $total_price > 0 ) {
					$formatted_total = number_format_i18n( $total_price, 2 );
					$lines[] = sprintf(
						__( 'Server-verified quote: %1$s%2$s %3$s', 'service-requests-form' ),
						$currency_sign,
						$formatted_total,
						$currency
					);
				}
			} else {
				$lines[] = '';
				$lines[] = __( 'Variants:', 'service-requests-form' );
				if ( is_array( $variants ) && ! empty( $variants ) ) {
					foreach ( $variants as $variant_key => $variant_value ) {
						$lines[] = '- ' . (string) $variant_key . ': ' . (string) $variant_value;
					}
				} else {
					$lines[] = '- ' . __( 'None', 'service-requests-form' );
				}
			}

			$lines[] = '';
			$lines[] = __( 'Customer:', 'service-requests-form' );
			$lines[] = sprintf( __( 'Name: %s', 'service-requests-form' ), $name ? $name : '-' );
			$lines[] = sprintf( __( 'Company: %s', 'service-requests-form' ), $company ? $company : '-' );
			$lines[] = sprintf( __( 'Email: %s', 'service-requests-form' ), $email ? $email : '-' );
			$lines[] = sprintf( __( 'Phone: %s', 'service-requests-form' ), $phone ? $phone : '-' );
			$lines[] = '';
			$lines[] = __( 'Shipping Address:', 'service-requests-form' );
			$lines[] = $shipping_address ? $shipping_address : '-';
			$lines[] = '';
			$lines[] = __( 'Description:', 'service-requests-form' );
			$lines[] = $description ? $description : '-';
			$lines[] = '';
			$lines[] = __( 'Admin Link:', 'service-requests-form' );
			$lines[] = $edit_link;
			$lines[] = '';
			$lines[] = __( 'Uploaded Files:', 'service-requests-form' );

			if ( is_array( $file_ids ) && ! empty( $file_ids ) ) {
				foreach ( $file_ids as $attachment_id ) {
					$attachment_id = (int) $attachment_id;
					if ( ! $attachment_id ) {
						continue;
					}

					$url       = wp_get_attachment_url( $attachment_id );
					$file_name = get_the_title( $attachment_id );
					if ( $url ) {
						$lines[] = '- ' . ( $file_name ? $file_name : ( 'File #' . $attachment_id ) ) . ': ' . $url;
					}
				}
			} else {
				$lines[] = '- ' . __( 'No files uploaded.', 'service-requests-form' );
			}

			$message = implode( "\n", $lines );

			$site_name  = sanitize_text_field( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
			$from_email = (string) get_option( 'admin_email' );
			$headers    = array( 'Content-Type: text/plain; charset=UTF-8' );

			if ( $from_email && is_email( $from_email ) ) {
				$headers[] = 'From: ' . $site_name . ' <' . $from_email . '>';
			}
			if ( $email && is_email( $email ) ) {
				$reply_name = sanitize_text_field( $name ? $name : __( 'Customer', 'service-requests-form' ) );
				$headers[]  = 'Reply-To: ' . $reply_name . ' <' . $email . '>';
			}

			$sent = (bool) wp_mail( $to, $subject, $message, $headers );

			update_post_meta( $post_id, '_sr_admin_email_to', $to );
			update_post_meta( $post_id, '_sr_admin_email_subject', $subject );
			update_post_meta( $post_id, '_sr_admin_email_sent', $sent ? '1' : '0' );
			update_post_meta( $post_id, '_sr_admin_email_kind', $is_project && 'paid' === $status ? 'paid-project' : ( $is_project ? 'project-quote' : 'service-request' ) );
			update_post_meta( $post_id, '_sr_admin_email_sent_at', current_time( 'mysql' ) );

			return $sent;
		}

		protected static function format_project_minutes( $minutes ) {
			$minutes = max( 0, (int) $minutes );
			$hours   = (int) floor( $minutes / 60 );
			$rest    = $minutes % 60;
			if ( $hours > 0 ) {
				return sprintf( _n( '%1$d hour %2$d min', '%1$d hours %2$d min', $hours, 'service-requests-form' ), $hours, $rest );
			}
			return sprintf( _n( '%d minute', '%d minutes', $minutes, 'service-requests-form' ), $minutes );
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
			self::enqueue_frontend_base_assets();
			if ( self::is_coming_soon_enabled( 'service' ) ) {
				return self::render_coming_soon_banner( 'service' );
			}

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
					$errors[] = __( 'Please log in to submit a service request.', 'service-requests-form' );
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
								$required = ! isset( $row['required'] ) || ! empty( $row['required'] );
								$vals = array();
								foreach ( $row['values'] as $v ) {
									$v = trim( sanitize_text_field( $v ) );
									if ( $v !== '' ) $vals[] = $v;
								}
								if ( $key !== '' && ! empty( $vals ) ) {
									$groups[] = array(
										'key'      => $key,
										'values'   => array_values( array_unique( $vals ) ),
										'required' => $required,
									);
								}
								continue;
							}

							// Back-compat: old rows label/value
							if ( isset( $row['label'] ) ) {
								$lbl = trim( sanitize_text_field( $row['label'] ) );
								if ( $lbl !== '' ) {
									$groups[] = array(
										'key'      => __( 'Variant', 'service-requests-form' ),
										'values'   => array( $lbl ),
										'required' => true,
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
							$required = ! isset( $g['required'] ) || ! empty( $g['required'] );

							if ( $key === '' ) {
								continue;
							}
							if ( $chosen === '' ) {
								if ( ! $required ) {
									continue;
								}
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
							list( $attachment_ids, $uploaded_bytes ) = self::handle_request_uploads( $post_id, self::get_user_quota_bytes( $user_id ) );
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
							'upload_limit_bytes'  => self::get_user_quota_bytes( get_current_user_id() ),
							'upload_limit_label'  => size_format( self::get_user_quota_bytes( get_current_user_id() ) ),
							'upload_used_bytes'   => self::get_user_used_bytes( get_current_user_id() ),
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
			self::enqueue_frontend_base_assets();
			if ( self::is_coming_soon_enabled( 'project' ) ) {
				return self::render_coming_soon_banner( 'project' );
			}

			$project_public       = self::is_project_public_access();
			if ( ! $project_public && ! is_user_logged_in() ) {
				return self::render_service_login_gate( 'project' );
			}

			self::enqueue_project_request_assets();

			$checkout_requested   = self::is_project_checkout_enabled();
			$woocommerce_available = class_exists( 'SRF_WooCommerce' ) && method_exists( 'SRF_WooCommerce', 'is_available' ) && SRF_WooCommerce::is_available();
			$checkout_enabled     = $checkout_requested && $woocommerce_available;
			$profiles             = class_exists( 'SRF_Print_Profiles' ) ? SRF_Print_Profiles::get_profiles() : array();
			$default_profile      = class_exists( 'SRF_Print_Profiles' ) ? SRF_Print_Profiles::get_default_profile_key() : 'custom';
			$current_profile      = is_user_logged_in() ? self::get_current_user_request_profile_data() : array( 'name' => '', 'company' => '', 'email' => '', 'phone' => '' );

			$errors   = array();
			$old_data = array(
				'title'          => '',
				'description'    => '',
				'name'           => isset( $current_profile['name'] ) ? (string) $current_profile['name'] : '',
				'company'        => isset( $current_profile['company'] ) ? (string) $current_profile['company'] : '',
				'email'          => isset( $current_profile['email'] ) ? (string) $current_profile['email'] : '',
				'phone'          => isset( $current_profile['phone'] ) ? (string) $current_profile['phone'] : '',
				'terms'          => '0',
				'material_id'    => '',
				'printer_id'     => '',
				'print_profile'  => $default_profile,
				'layer_height'   => '0.20',
				'infill'         => '15',
				'wall_loops'     => '2',
				'top_layers'     => '4',
				'bottom_layers'  => '3',
				'infill_pattern' => 'grid',
				'supports'       => '0',
				'shell_mode'     => 'solid',
				'scale'          => '100',
				'quantity'       => '1',
				'notes'          => '',
			);
			$success         = false;
			$payment_warning = '';

			$dashboard_url = '';
			if ( is_user_logged_in() && class_exists( 'SRF_MyAccount' ) && method_exists( 'SRF_MyAccount', 'url_list' ) ) {
				$dashboard_url = SRF_MyAccount::url_list();
			}

			$materials = self::get_project_active_materials();
			$printers  = self::get_project_active_printers();

			if ( isset( $_GET['srf_project_submitted'] ) && '1' === (string) wp_unslash( $_GET['srf_project_submitted'] ) ) {
				$success = true;
			}
			if ( isset( $_GET['srf_project_payment'] ) && 'unavailable' === sanitize_key( wp_unslash( $_GET['srf_project_payment'] ) ) ) {
				$payment_warning = __( 'The request and secure quote were saved, but payment could not be started. Our team will contact you, or you can try again after WooCommerce is available.', 'service-requests-form' );
			}

			$is_project_login_post = isset( $_POST['log'], $_POST['pwd'] ) || isset( $_POST['wp-submit'] );

			if ( ! empty( $_POST['srf_project_form_submitted'] ) && ! $is_project_login_post ) {
				$old_data = array(
					'title'          => isset( $_POST['srf_project_title'] ) ? sanitize_text_field( wp_unslash( $_POST['srf_project_title'] ) ) : '',
					'description'    => isset( $_POST['srf_project_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['srf_project_description'] ) ) : '',
					'name'           => isset( $_POST['srf_guest_name'] ) ? sanitize_text_field( wp_unslash( $_POST['srf_guest_name'] ) ) : '',
					'company'        => isset( $_POST['srf_guest_company'] ) ? sanitize_text_field( wp_unslash( $_POST['srf_guest_company'] ) ) : '',
					'email'          => isset( $_POST['srf_guest_email'] ) ? sanitize_email( wp_unslash( $_POST['srf_guest_email'] ) ) : '',
					'phone'          => isset( $_POST['srf_guest_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['srf_guest_phone'] ) ) : '',
					'terms'          => ! empty( $_POST['srf_terms'] ) ? '1' : '0',
					'material_id'    => isset( $_POST['srf_material_id'] ) ? (string) absint( $_POST['srf_material_id'] ) : '',
					'printer_id'     => isset( $_POST['srf_printer_id'] ) ? (string) absint( $_POST['srf_printer_id'] ) : '',
					'print_profile'  => isset( $_POST['srf_print_profile'] ) ? sanitize_key( wp_unslash( $_POST['srf_print_profile'] ) ) : $default_profile,
					'layer_height'   => isset( $_POST['srf_layer_height'] ) ? (string) max( 0.01, min( 1, (float) wp_unslash( $_POST['srf_layer_height'] ) ) ) : '0.20',
					'infill'         => isset( $_POST['srf_infill'] ) ? (string) max( 0, min( 100, (int) wp_unslash( $_POST['srf_infill'] ) ) ) : '15',
					'wall_loops'     => isset( $_POST['srf_wall_loops'] ) ? (string) max( 1, min( 12, (int) wp_unslash( $_POST['srf_wall_loops'] ) ) ) : '2',
					'top_layers'     => isset( $_POST['srf_top_layers'] ) ? (string) max( 0, min( 30, (int) wp_unslash( $_POST['srf_top_layers'] ) ) ) : '4',
					'bottom_layers'  => isset( $_POST['srf_bottom_layers'] ) ? (string) max( 0, min( 30, (int) wp_unslash( $_POST['srf_bottom_layers'] ) ) ) : '3',
					'infill_pattern' => isset( $_POST['srf_infill_pattern'] ) ? sanitize_key( wp_unslash( $_POST['srf_infill_pattern'] ) ) : 'grid',
					'supports'       => ! empty( $_POST['srf_supports'] ) ? '1' : '0',
					'shell_mode'     => isset( $_POST['srf_shell_mode'] ) && 'hollow' === sanitize_key( wp_unslash( $_POST['srf_shell_mode'] ) ) ? 'hollow' : 'solid',
					'scale'          => isset( $_POST['srf_scale'] ) ? (string) max( 10, min( 500, (int) wp_unslash( $_POST['srf_scale'] ) ) ) : '100',
					'quantity'       => isset( $_POST['srf_quantity'] ) ? (string) max( 1, min( 999, (int) wp_unslash( $_POST['srf_quantity'] ) ) ) : '1',
					'notes'          => isset( $_POST['srf_quote_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['srf_quote_notes'] ) ) : '',
				);

				if ( is_user_logged_in() ) {
					$old_data['name']    = isset( $current_profile['name'] ) ? (string) $current_profile['name'] : '';
					$old_data['company'] = isset( $current_profile['company'] ) ? (string) $current_profile['company'] : '';
					$old_data['email']   = isset( $current_profile['email'] ) ? (string) $current_profile['email'] : '';
					$old_data['phone']   = isset( $current_profile['phone'] ) ? (string) $current_profile['phone'] : '';
				}

				if ( ! empty( $_POST['srf_company_website'] ) ) {
					$errors[] = __( 'The request could not be submitted.', 'service-requests-form' );
				}

				if ( empty( $_POST['srf_project_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['srf_project_nonce'] ) ), 'srf_submit_project_request' ) ) {
					$errors[] = __( 'Security check failed. Please refresh the page and try again.', 'service-requests-form' );
				}

				if ( ! self::current_user_can_submit_project() ) {
					$errors[] = __( 'Please log in or ask the site administrator to enable public project requests.', 'service-requests-form' );
				}

				if ( ! is_user_logged_in() && $project_public ) {
					if ( empty( $old_data['name'] ) ) {
						$errors[] = __( 'Your name is required.', 'service-requests-form' );
					}
					if ( empty( $old_data['email'] ) || ! is_email( $old_data['email'] ) ) {
						$errors[] = __( 'A valid email address is required.', 'service-requests-form' );
					}
				}

				if ( empty( $old_data['title'] ) ) {
					$errors[] = __( 'Project title is required.', 'service-requests-form' );
				}
				if ( empty( $old_data['description'] ) ) {
					$errors[] = __( 'Project description is required.', 'service-requests-form' );
				}
				if ( '1' !== $old_data['terms'] ) {
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

				if ( $selected_material && $selected_printer && ! empty( $selected_printer->supported_material_ids ) && ! in_array( (int) $selected_material->id, $selected_printer->supported_material_ids, true ) ) {
					$errors[] = __( 'The selected printer does not support the selected material.', 'service-requests-form' );
				}

				$resolved_print = array(
					'profile_key'     => 'custom',
					'profile_name'    => __( 'Custom settings', 'service-requests-form' ),
					'layer_height'    => (float) $old_data['layer_height'],
					'infill'          => (int) $old_data['infill'],
					'wall_loops'      => (int) $old_data['wall_loops'],
					'top_layers'      => (int) $old_data['top_layers'],
					'bottom_layers'   => (int) $old_data['bottom_layers'],
					'infill_pattern'  => $old_data['infill_pattern'],
					'time_factor'     => 1.0,
					'material_factor' => 1.0,
					'supports'        => '1' === $old_data['supports'],
				);

				if ( $selected_printer && class_exists( 'SRF_Print_Profiles' ) ) {
					$resolved_print = SRF_Print_Profiles::resolve_options(
						$old_data['print_profile'],
						array(
							'layer_height'   => $old_data['layer_height'],
							'infill'         => $old_data['infill'],
							'wall_loops'     => $old_data['wall_loops'],
							'top_layers'     => $old_data['top_layers'],
							'bottom_layers'  => $old_data['bottom_layers'],
							'infill_pattern' => $old_data['infill_pattern'],
							'supports'       => '1' === $old_data['supports'],
						),
						$selected_printer
					);
				}

				$old_data['print_profile']  = (string) $resolved_print['profile_key'];
				$old_data['layer_height']   = (string) $resolved_print['layer_height'];
				$old_data['infill']         = (string) $resolved_print['infill'];
				$old_data['wall_loops']     = (string) $resolved_print['wall_loops'];
				$old_data['top_layers']     = (string) $resolved_print['top_layers'];
				$old_data['bottom_layers']  = (string) $resolved_print['bottom_layers'];
				$old_data['infill_pattern'] = (string) $resolved_print['infill_pattern'];

				if ( $selected_printer ) {
					$layer_height = (float) $resolved_print['layer_height'];
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
				$has_any = is_array( $names ) ? count( array_filter( $names ) ) > 0 : ! empty( $names );
				if ( ! $has_any ) {
					$errors[] = __( 'Please upload at least one file.', 'service-requests-form' );
				}

				if ( empty( $errors ) ) {
					$user_id = get_current_user_id();
					$profile_data = is_user_logged_in()
						? self::get_current_user_request_profile_data()
						: array(
							'name'    => $old_data['name'],
							'company' => $old_data['company'],
							'email'   => $old_data['email'],
							'phone'   => $old_data['phone'],
						);

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
						update_post_meta( $post_id, '_sr_access_mode', $project_public ? 'public' : 'registered' );
						update_post_meta( $post_id, '_sr_payment_required', $checkout_requested ? '1' : '0' );
						update_post_meta( $post_id, '_sr_no_file', 0 );
						update_post_meta( $post_id, '_sr_material_id', (int) $old_data['material_id'] );
						update_post_meta( $post_id, '_sr_printer_id', (int) $old_data['printer_id'] );
						update_post_meta( $post_id, '_sr_material_name', (string) $selected_material->name );
						update_post_meta( $post_id, '_sr_printer_name', (string) $selected_printer->name );
						update_post_meta( $post_id, '_sr_print_profile_key', (string) $resolved_print['profile_key'] );
						update_post_meta( $post_id, '_sr_print_profile_name', (string) $resolved_print['profile_name'] );
						update_post_meta( $post_id, '_sr_layer_height', (float) $resolved_print['layer_height'] );
						update_post_meta( $post_id, '_sr_infill', (int) $resolved_print['infill'] );
						update_post_meta( $post_id, '_sr_wall_loops', (int) $resolved_print['wall_loops'] );
						update_post_meta( $post_id, '_sr_top_layers', (int) $resolved_print['top_layers'] );
						update_post_meta( $post_id, '_sr_bottom_layers', (int) $resolved_print['bottom_layers'] );
						update_post_meta( $post_id, '_sr_infill_pattern', (string) $resolved_print['infill_pattern'] );
						update_post_meta( $post_id, '_sr_supports', ! empty( $resolved_print['supports'] ) ? '1' : '0' );
						update_post_meta( $post_id, '_sr_shell_mode', $old_data['shell_mode'] );
						update_post_meta( $post_id, '_sr_scale', (int) $old_data['scale'] );
						update_post_meta( $post_id, '_sr_quantity', (int) $old_data['quantity'] );
						update_post_meta( $post_id, '_sr_quote_notes', $old_data['notes'] );

						$attachment_ids = array();
						$uploaded_bytes = 0;
						$quote          = array();

						try {
							list( $attachment_ids, $uploaded_bytes ) = self::handle_request_uploads( $post_id, self::get_project_upload_limit_bytes(), true );
							update_post_meta( $post_id, '_sr_file_ids', is_array( $attachment_ids ) ? $attachment_ids : array() );

							$attachment_paths = array();
							foreach ( $attachment_ids as $attachment_id ) {
								$file_path = get_attached_file( (int) $attachment_id );
								if ( $file_path ) {
									$attachment_paths[] = $file_path;
								}
							}

							if ( ! class_exists( 'SRF_Project_Pricing' ) ) {
								throw new Exception( __( 'The project pricing engine is not available.', 'service-requests-form' ) );
							}

							$quote = SRF_Project_Pricing::calculate_final_quote(
								$attachment_paths,
								$selected_material,
								$selected_printer,
								self::get_project_quote_settings(),
								array(
									'profile_key'     => $resolved_print['profile_key'],
									'profile_name'    => $resolved_print['profile_name'],
									'layer_height'    => $resolved_print['layer_height'],
									'infill'          => $resolved_print['infill'],
									'wall_loops'      => $resolved_print['wall_loops'],
									'top_layers'      => $resolved_print['top_layers'],
									'bottom_layers'   => $resolved_print['bottom_layers'],
									'infill_pattern'  => $resolved_print['infill_pattern'],
									'time_factor'     => $resolved_print['time_factor'],
									'material_factor' => $resolved_print['material_factor'],
									'supports'        => $resolved_print['supports'],
									'shell_mode'      => $old_data['shell_mode'],
									'scale'           => (int) $old_data['scale'],
									'quantity'        => (int) $old_data['quantity'],
								)
							);

							if ( $checkout_enabled && (float) $quote['total_price'] <= 0 ) {
								throw new Exception( __( 'A payable quote could not be created. Please ask the site administrator to configure material and printer costs.', 'service-requests-form' ) );
							}

							$quote_meta = array(
								'_sr_model_count'                => (int) $quote['model_count'],
								'_sr_model_formats'              => implode( ',', (array) $quote['model_formats'] ),
								'_sr_model_triangles'            => (int) $quote['model_triangles'],
								'_sr_model_bounds_mm'            => (array) $quote['model_bounds_mm'],
								'_sr_scaled_bounds_mm'           => (array) $quote['scaled_bounds_mm'],
								'_sr_model_volume_cm3'           => (float) $quote['model_volume_cm3'],
								'_sr_effective_volume_cm3'       => (float) $quote['effective_volume_cm3'],
								'_sr_adjusted_volume_cm3'        => (float) $quote['adjusted_volume_cm3'],
								'_sr_estimated_weight_g'         => (float) $quote['estimated_weight_g'],
								'_sr_unit_print_hours'           => (float) $quote['unit_print_hours'],
								'_sr_estimated_print_hours'      => (float) $quote['estimated_print_hours'],
								'_sr_estimated_print_minutes'    => (int) $quote['estimated_print_minutes'],
								'_sr_unit_material_cost'         => (float) $quote['unit_material_cost'],
								'_sr_unit_printer_cost'          => (float) $quote['unit_printer_cost'],
								'_sr_material_cost'              => (float) $quote['items_material_total'],
								'_sr_printer_cost'               => (float) $quote['items_printer_total'],
								'_sr_service_fee'                => (float) $quote['service_fee'],
								'_sr_setup_fee'                  => (float) $quote['setup_fee'],
								'_sr_profit_margin_percent'      => (float) $quote['profit_margin_percent'],
								'_sr_profit_margin_amount'       => (float) $quote['profit_margin_amount'],
								'_sr_tax_rate'                   => (float) $quote['tax_rate'],
								'_sr_tax_amount'                 => (float) $quote['tax_amount'],
								'_sr_subtotal_before_margin'     => (float) $quote['subtotal_before_margin'],
								'_sr_subtotal_with_margin'       => (float) $quote['subtotal_with_margin'],
								'_sr_total_price'                => (float) $quote['total_price'],
								'_sr_price_total'                => (float) $quote['total_price'], // Compatibility with existing account templates/integrations.
								'_sr_currency'                   => (string) $quote['currency'],
								'_sr_currency_symbol'            => (string) $quote['currency_symbol'],
								'_sr_quote_calculation_version'  => (string) $quote['calculation_version'],
								'_sr_quote_snapshot'             => wp_json_encode( $quote ),
							);
							foreach ( $quote_meta as $meta_key => $meta_value ) {
								update_post_meta( $post_id, $meta_key, $meta_value );
							}
						} catch ( Exception $e ) {
							foreach ( $attachment_ids as $aid ) {
								wp_delete_attachment( (int) $aid, true );
							}
							if ( $uploaded_bytes > 0 ) {
								self::subtract_user_used_bytes( $user_id, $uploaded_bytes );
							}
							wp_delete_post( $post_id, true );
							$errors[] = $e->getMessage();
						}

						if ( empty( $errors ) ) {
							$redirect_args = array( 'srf_project_submitted' => '1' );

							if ( $checkout_enabled && class_exists( 'SRF_WooCommerce' ) && method_exists( 'SRF_WooCommerce', 'add_project_request_to_cart' ) ) {
								$added = SRF_WooCommerce::add_project_request_to_cart( $post_id, $quote );
								if ( $added ) {
									update_post_meta( $post_id, '_sr_status', 'pending-payment' );
									update_post_meta( $post_id, '_sr_checkout_started_at', current_time( 'mysql' ) );
									// Production notification is sent only after WooCommerce
									// confirms payment (processing/completed/payment_complete).
									self::safe_redirect( SRF_WooCommerce::get_project_after_submit_url() );
								}

								update_post_meta( $post_id, '_sr_status', 'quote-ready' );
								update_post_meta( $post_id, '_sr_quote_ready_at', current_time( 'mysql' ) );
								$redirect_args['srf_project_payment'] = 'unavailable';
							} elseif ( $checkout_requested ) {
								update_post_meta( $post_id, '_sr_status', 'quote-ready' );
								update_post_meta( $post_id, '_sr_quote_ready_at', current_time( 'mysql' ) );
								$redirect_args['srf_project_payment'] = 'unavailable';
							} else {
								update_post_meta( $post_id, '_sr_status', 'quote-ready' );
								update_post_meta( $post_id, '_sr_quote_ready_at', current_time( 'mysql' ) );
							}

							self::send_admin_new_request_email( $post_id );
							$redirect_args['srf_request_id'] = (int) $post_id;
							self::safe_redirect( add_query_arg( $redirect_args, get_permalink() ) );
						}
					}
				}
			}

			ob_start();
			self::load_template(
				'project-form.php',
				array(
					'errors'                 => $errors,
					'old_data'               => $old_data,
					'success'                => $success,
					'payment_warning'        => $payment_warning,
					'dashboard_url'          => $dashboard_url,
					'upload_limit'           => self::get_project_upload_limit_label(),
					'upload_limit_bytes'     => self::get_project_upload_limit_bytes(),
					'allowed_formats'        => self::get_project_allowed_extensions_label(),
					'is_business'            => self::current_user_is_business(),
					'project_public'         => $project_public,
					'project_access_mode'    => $project_public ? 'public' : 'registered',
					'checkout_enabled'       => $checkout_enabled,
					'checkout_requested'     => $checkout_requested,
					'woocommerce_available'  => $woocommerce_available,
					'materials'              => $materials,
					'printers'               => $printers,
					'print_profiles'         => $profiles,
					'quote_settings'         => self::get_project_quote_settings(),
				)
			);
			return ob_get_clean();
		}
		// ===============================
		// Helpers
		// ===============================
		protected static function get_project_access_mode() {
			if ( class_exists( 'SR_Settings' ) && method_exists( 'SR_Settings', 'get_project_access_mode' ) ) {
				return SR_Settings::get_project_access_mode();
			}
			return 'registered';
		}

		protected static function is_project_public_access() {
			return 'public' === self::get_project_access_mode();
		}

		protected static function current_user_can_submit_project() {
			return is_user_logged_in() || self::is_project_public_access();
		}

		protected static function is_project_checkout_enabled() {
			if ( class_exists( 'SR_Settings' ) && method_exists( 'SR_Settings', 'is_project_checkout_enabled' ) ) {
				return SR_Settings::is_project_checkout_enabled();
			}
			return false;
		}

		protected static function current_user_can_submit() {
			return is_user_logged_in();
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
