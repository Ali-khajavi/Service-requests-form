<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Built-in project-order process profiles and optional Bambu Lab starter data.
 *
 * The process profiles provide stable quoting inputs for the plugin's geometry
 * estimator. They do not run Bambu Studio and they do not generate G-code.
 */
if ( ! class_exists( 'SRF_Print_Profiles' ) ) {
	class SRF_Print_Profiles {

		const OPTION_BAMBU_PRESETS_ENABLED = 'srf_bambu_presets_enabled';
		const OPTION_BAMBU_HOURLY_COST     = 'srf_bambu_hourly_cost';
		const OPTION_BAMBU_MATERIAL_KG     = 'srf_bambu_material_price_per_kg';
		const OPTION_PRESETS_VERSION       = 'srf_bambu_presets_version';
		const PRESETS_VERSION              = '1.0.0';

		/**
		 * Register the explicit administrator action used by the settings page.
		 */
		public static function init() {
			add_action( 'admin_post_srf_install_bambu_profiles', array( __CLASS__, 'handle_install_bambu_profiles' ) );
		}

		/**
		 * Process the manual starter-data installation request.
		 */
		public static function handle_install_bambu_profiles() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You are not allowed to install printer profiles.', 'service-requests-form' ) );
			}

			check_admin_referer( 'srf_install_bambu_profiles' );
			$result = self::seed_bambu_defaults();
			$url    = add_query_arg(
				array(
					'page'                => class_exists( 'SR_Settings' ) ? SR_Settings::PAGE_SLUG : 'srf-settings',
					'srf_bambu_installed' => '1',
					'materials_added'     => (int) $result['material'],
					'printers_added'      => (int) $result['printers'],
				),
				admin_url( 'admin.php' )
			);
			wp_safe_redirect( $url );
			exit;
		}

		/**
		 * Built-in profiles shown in the project order form.
		 *
		 * The factors below are calibration values for the quote estimator. They
		 * intentionally avoid claiming one-to-one parity with a slicer profile.
		 *
		 * @return array<string,array<string,mixed>>
		 */
		public static function get_profiles() {
			return array(
				'bambu-008-extra-fine'   => self::profile( 'bambu-008-extra-fine', '0.08mm Extra Fine', 0.08, 15, 3, 7, 5, 'gyroid', 1.15, 1.08 ),
				'bambu-008-high-quality' => self::profile( 'bambu-008-high-quality', '0.08mm High Quality', 0.08, 15, 3, 8, 6, 'gyroid', 1.25, 1.10 ),
				'bambu-012-fine'         => self::profile( 'bambu-012-fine', '0.12mm Fine', 0.12, 15, 3, 6, 5, 'gyroid', 1.10, 1.06 ),
				'bambu-012-high-quality' => self::profile( 'bambu-012-high-quality', '0.12mm High Quality', 0.12, 15, 3, 7, 5, 'gyroid', 1.20, 1.08 ),
				'bambu-016-optimal'      => self::profile( 'bambu-016-optimal', '0.16mm Optimal', 0.16, 15, 2, 5, 4, 'gyroid', 1.00, 1.00 ),
				'bambu-016-high-quality' => self::profile( 'bambu-016-high-quality', '0.16mm High Quality', 0.16, 15, 3, 6, 5, 'gyroid', 1.12, 1.06 ),
				'bambu-020-standard'     => self::profile( 'bambu-020-standard', '0.20mm Standard', 0.20, 15, 2, 4, 3, 'grid', 1.00, 1.00 ),
				'bambu-020-strength'     => self::profile( 'bambu-020-strength', '0.20mm Strength', 0.20, 25, 6, 5, 5, 'gyroid', 1.20, 1.20 ),
				'bambu-024-draft'        => self::profile( 'bambu-024-draft', '0.24mm Draft', 0.24, 15, 2, 4, 3, 'grid', 0.95, 0.98 ),
				'bambu-028-extra-draft'  => self::profile( 'bambu-028-extra-draft', '0.28mm Extra Draft', 0.28, 15, 2, 3, 3, 'grid', 0.90, 0.96 ),
			);
		}

		protected static function profile( $key, $name, $layer_height, $infill, $wall_loops, $top_layers, $bottom_layers, $infill_pattern, $time_factor, $material_factor ) {
			return array(
				'key'             => (string) $key,
				'name'            => (string) $name,
				'label'           => (string) $name,
				'layer_height'    => (float) $layer_height,
				'infill'          => (int) $infill,
				'wall_loops'      => (int) $wall_loops,
				'wall_count'      => (int) $wall_loops, // Backward-compatible alias.
				'top_layers'      => (int) $top_layers,
				'bottom_layers'   => (int) $bottom_layers,
				'infill_pattern'  => (string) $infill_pattern,
				'time_factor'     => (float) $time_factor,
				'material_factor' => (float) $material_factor,
			);
		}

		public static function get_default_profile_key() {
			return 'bambu-020-standard';
		}

		public static function sanitize_profile_key( $key, $allow_custom = true ) {
			$key = sanitize_key( (string) $key );
			if ( $allow_custom && 'custom' === $key ) {
				return 'custom';
			}

			$profiles = self::get_profiles();
			return isset( $profiles[ $key ] ) ? $key : self::get_default_profile_key();
		}

		public static function get_profile( $key ) {
			$key = self::sanitize_profile_key( $key, true );
			if ( 'custom' === $key ) {
				return null;
			}

			$profiles = self::get_profiles();
			return isset( $profiles[ $key ] ) ? $profiles[ $key ] : null;
		}

		/**
		 * Backward-compatible aliases for development builds that used "preset".
		 */
		public static function get_preset( $key ) {
			return self::get_profile( $key );
		}

		public static function get_default_preset_key() {
			return self::get_default_profile_key();
		}

		public static function is_enabled() {
			return (bool) get_option( self::OPTION_BAMBU_PRESETS_ENABLED, true );
		}

		public static function is_bambu_printer( $printer ) {
			if ( ! is_object( $printer ) ) {
				return false;
			}

			$brand    = isset( $printer->brand ) ? (string) $printer->brand : '';
			$name     = isset( $printer->name ) ? (string) $printer->name : '';
			$model    = isset( $printer->model ) ? (string) $printer->model : '';
			$haystack = strtolower( $brand . ' ' . $name . ' ' . $model );

			return false !== strpos( $haystack, 'bambu' );
		}

		/**
		 * Human-readable Bambu Studio-style printer suffix.
		 */
		public static function get_printer_suffix( $printer ) {
			$model = is_object( $printer ) && isset( $printer->model ) ? trim( (string) $printer->model ) : '';
			$map   = array(
				'X1 Carbon' => 'BBL X1C',
				'X1C'       => 'BBL X1C',
				'P1S'       => 'BBL P1S',
				'P1P'       => 'BBL P1P',
				'A1'        => 'BBL A1',
				'A1 mini'   => 'BBL A1M',
			);

			if ( isset( $map[ $model ] ) ) {
				return $map[ $model ];
			}
			return $model ? 'BBL ' . $model : 'BBL X1C';
		}

		public static function get_display_name( $profile_key, $printer = null ) {
			$profile = self::get_profile( $profile_key );
			if ( ! $profile ) {
				return __( 'Custom settings', 'service-requests-form' );
			}
			return $profile['name'] . ' @' . self::get_printer_suffix( $printer );
		}

		/**
		 * Resolve cost-driving values on the server.
		 *
		 * Named profiles are authoritative for Bambu printers. Browser-modified
		 * layer and infill values are ignored for a named profile. Other printers
		 * use the submitted custom settings.
		 *
		 * @param string $profile_key Submitted profile key.
		 * @param array  $submitted   Sanitized user inputs.
		 * @param object $printer     Selected printer row.
		 * @return array<string,mixed>
		 */
		public static function resolve_options( $profile_key, array $submitted = array(), $printer = null ) {
			$is_bambu = self::is_enabled() && self::is_bambu_printer( $printer );
			$key      = $is_bambu ? self::sanitize_profile_key( $profile_key, true ) : 'custom';
			$profile  = self::get_profile( $key );
			$supports = ! empty( $submitted['supports'] );

			if ( $profile ) {
				return array_merge(
					$profile,
					array(
						'profile_key'  => $key,
						'profile_name' => self::get_display_name( $key, $printer ),
						'supports'     => $supports,
					)
				);
			}

			$layer_height  = isset( $submitted['layer_height'] ) ? (float) $submitted['layer_height'] : 0.20;
			$infill        = isset( $submitted['infill'] ) ? (int) $submitted['infill'] : 20;
			$wall_loops    = isset( $submitted['wall_loops'] ) ? (int) $submitted['wall_loops'] : 2;
			$top_layers    = isset( $submitted['top_layers'] ) ? (int) $submitted['top_layers'] : 4;
			$bottom_layers = isset( $submitted['bottom_layers'] ) ? (int) $submitted['bottom_layers'] : 3;
			$pattern       = isset( $submitted['infill_pattern'] ) ? sanitize_key( (string) $submitted['infill_pattern'] ) : 'grid';

			return array(
				'key'             => 'custom',
				'name'            => __( 'Custom settings', 'service-requests-form' ),
				'label'           => __( 'Custom settings', 'service-requests-form' ),
				'profile_key'     => 'custom',
				'profile_name'    => __( 'Custom settings', 'service-requests-form' ),
				'layer_height'    => max( 0.01, min( 1, $layer_height ) ),
				'infill'          => max( 0, min( 100, $infill ) ),
				'wall_loops'      => max( 1, min( 12, $wall_loops ) ),
				'wall_count'      => max( 1, min( 12, $wall_loops ) ),
				'top_layers'      => max( 0, min( 30, $top_layers ) ),
				'bottom_layers'   => max( 0, min( 30, $bottom_layers ) ),
				'infill_pattern'  => $pattern ? $pattern : 'grid',
				'time_factor'     => 1.0,
				'material_factor' => 1.0,
				'supports'        => $supports,
			);
		}

		/**
		 * Normalize a brand/model pair so "Bambu Lab" and "bambulab" do not
		 * create duplicate starter printers.
		 */
		protected static function normalize_printer_identity( $brand, $model ) {
			$brand = strtolower( (string) preg_replace( '/[^a-z0-9]+/i', '', (string) $brand ) );
			$model = strtolower( (string) preg_replace( '/[^a-z0-9]+/i', '', (string) $model ) );
			return $brand . '|' . $model;
		}

		/**
		 * Install missing starter rows without overwriting existing admin data.
		 *
		 * @return array{material:int,printers:int}
		 */
		public static function seed_bambu_defaults() {
			$result = array(
				'material' => 0,
				'printers' => 0,
			);

			if ( ! self::is_enabled() || ! class_exists( 'SRF_Quote_DB' ) ) {
				return $result;
			}

			$db       = new SRF_Quote_DB();
			$material = $db->get_material_by_slug( 'bambu-pla-basic' );

			if ( ! $material ) {
				$kg_price    = max( 0, (float) get_option( self::OPTION_BAMBU_MATERIAL_KG, 25 ) );
				$material_id = $db->insert_material(
					array(
						'name'                   => 'Bambu PLA Basic',
						'slug'                   => 'bambu-pla-basic',
						'description'            => __( 'Starter PLA row for Bambu Lab project quotes. Review its price, density, wastage, colours, and stock before accepting live orders.', 'service-requests-form' ),
						'price_per_gram'         => $kg_price / 1000,
						'price_per_cm3'          => 0,
						'density'                => 1.24,
						'machine_time_factor'    => 1.00,
						'surface_quality_factor' => 1.00,
						'wastage_factor'         => 1.08,
						'color_availability'     => __( 'Configure available colours in Materials', 'service-requests-form' ),
						'status'                 => 'active',
					)
				);
				$material             = $material_id ? $db->get_material( $material_id ) : null;
				$result['material']    = $material_id ? 1 : 0;
			}

			if ( ! $material || empty( $material->id ) ) {
				return $result;
			}

			$existing      = $db->get_printers();
			$existing_keys = array();
			foreach ( (array) $existing as $printer ) {
				$brand = isset( $printer->brand ) ? (string) $printer->brand : '';
				$model = isset( $printer->model ) ? (string) $printer->model : '';
				$existing_keys[ self::normalize_printer_identity( $brand, $model ) ] = true;
			}

			$hourly = max( 0, (float) get_option( self::OPTION_BAMBU_HOURLY_COST, 8 ) );
			$models = array(
				array( 'model' => 'X1 Carbon', 'name' => 'Bambu Lab X1 Carbon (0.4 mm)', 'family' => 'x1-series', 'volume' => array( 256, 256, 256 ), 'throughput' => 18, 'enclosed' => 1, 'ams' => 1 ),
				array( 'model' => 'P1S', 'name' => 'Bambu Lab P1S (0.4 mm)', 'family' => 'p1-series', 'volume' => array( 256, 256, 256 ), 'throughput' => 18, 'enclosed' => 1, 'ams' => 1 ),
				array( 'model' => 'P1P', 'name' => 'Bambu Lab P1P (0.4 mm)', 'family' => 'p1-series', 'volume' => array( 256, 256, 256 ), 'throughput' => 18, 'enclosed' => 0, 'ams' => 1 ),
				array( 'model' => 'A1', 'name' => 'Bambu Lab A1 (0.4 mm)', 'family' => 'a1-series', 'volume' => array( 256, 256, 256 ), 'throughput' => 16, 'enclosed' => 0, 'ams' => 1 ),
				array( 'model' => 'A1 mini', 'name' => 'Bambu Lab A1 mini (0.4 mm)', 'family' => 'a1-series', 'volume' => array( 180, 180, 180 ), 'throughput' => 12, 'enclosed' => 0, 'ams' => 1 ),
			);

			foreach ( $models as $model ) {
				$key = self::normalize_printer_identity( 'Bambu Lab', $model['model'] );
				if ( isset( $existing_keys[ $key ] ) ) {
					continue;
				}

				$printer_id = $db->insert_printer(
					array(
						'name'                          => $model['name'],
						'brand'                         => 'bambulab',
						'printer_family'                => $model['family'],
						'brand_settings_json'           => wp_json_encode(
							array(
								'family'               => $model['family'],
								'studio_printer_preset' => $model['model'] . ' 0.4 nozzle',
								'default_process_key'  => self::get_default_profile_key(),
								'ams_capable'          => (int) $model['ams'],
								'enclosed'             => (int) $model['enclosed'],
								'quote_source'         => 'srf-built-in-bambu-v1',
							)
						),
						'model'                         => $model['model'],
						'description'                   => __( 'Built-in Bambu Lab starter printer for the custom 3D project form. Calibrate throughput, hourly cost, setup time, and minimum charges for your own workshop.', 'service-requests-form' ),
						'technology'                    => 'fdm',
						'build_volume_x'                => $model['volume'][0],
						'build_volume_y'                => $model['volume'][1],
						'build_volume_z'                => $model['volume'][2],
						'nozzle_size'                   => 0.4,
						'default_speed'                 => $model['throughput'],
						'speed_unit'                    => 'cm3/h',
						'hourly_cost'                   => $hourly,
						'machine_efficiency_factor'     => 1.00,
						'setup_time_minutes'            => 10,
						'warmup_time_minutes'           => 6,
						'postprocess_time_minutes'      => 10,
						'min_layer_height'              => 0.08,
						'max_layer_height'              => 0.28,
						'supported_materials'           => wp_json_encode( array( (int) $material->id ) ),
						'default_material_id'           => (int) $material->id,
						'pricing_model'                 => 'hybrid',
						'minimum_job_price'             => 10,
						'minimum_material_charge'       => 2,
						'enable_infill'                 => 1,
						'enable_supports'               => 1,
						'enable_scale'                  => 1,
						'enable_quantity'               => 1,
						'enable_advanced_settings'      => 1,
						'fdm_infill_min'                => 0,
						'fdm_infill_max'                => 100,
						'fdm_support_factor'            => 1.12,
						'fdm_default_line_width'        => 0.42,
						'fdm_default_wall_count'        => 2,
						'fdm_default_top_layers'        => 4,
						'fdm_default_bottom_layers'     => 3,
						'fdm_default_infill_pattern'    => 'grid',
						'fdm_supported_infill_patterns' => wp_json_encode( array( 'grid', 'gyroid', 'lines', 'honeycomb', 'cubic' ) ),
						'allowed_file_formats'          => wp_json_encode( array( 'stl', 'obj', '3mf' ) ),
						'status'                        => 'active',
					)
				);

				if ( $printer_id ) {
					$result['printers']++;
					$existing_keys[ $key ] = true;
				}
			}

			update_option( self::OPTION_PRESETS_VERSION, self::PRESETS_VERSION, false );
			return $result;
		}
	}
}
