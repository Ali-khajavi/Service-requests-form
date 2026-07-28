<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SRF_Project_Pricing' ) ) {
	/**
	 * Geometry-based project quote engine.
	 *
	 * This engine deliberately does not claim slicer/G-code accuracy. It derives
	 * a repeatable commercial estimate from closed-mesh geometry, material data,
	 * machine throughput, and the selected process preset. The same formula is
	 * mirrored in assets/js/project.js for a fast preview; this PHP result is the
	 * authoritative checkout price.
	 */
	class SRF_Project_Pricing {
		const FORMULA_VERSION  = '2.1';
		const MAX_TRIANGLES    = 4000000;
		const MAX_VERTICES     = 2000000;
		const MAX_3MF_XML_BYTES = 134217728; // 128 MB uncompressed.
		const MAX_3MF_ENTRIES  = 20000;

		protected static $supported_extensions = array( 'stl', 'obj', '3mf' );
		protected static $model_extensions     = array( 'stl', 'obj', '3mf', 'step', 'stp', 'iges', 'igs' );

		public static function get_supported_extensions() {
			return self::$supported_extensions;
		}

		public static function get_model_extensions() {
			return self::$model_extensions;
		}

		/**
		 * Calculate an authoritative quote from uploaded model files.
		 *
		 * @throws RuntimeException When geometry cannot be priced or does not fit.
		 */
		public static function calculate_final_quote( array $file_paths, $material, $printer, array $quote_settings, array $options = array() ) {
			if ( ! is_object( $material ) || ! is_object( $printer ) ) {
				throw new RuntimeException( __( 'A valid material and printer are required for pricing.', 'service-requests-form' ) );
			}

			$model_paths = array();
			$unsupported = array();
			foreach ( $file_paths as $path ) {
				$path = (string) $path;
				if ( '' === $path || ! is_readable( $path ) ) {
					continue;
				}
				$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
				if ( ! in_array( $extension, self::$model_extensions, true ) ) {
					continue;
				}
				if ( ! in_array( $extension, self::$supported_extensions, true ) ) {
					$unsupported[] = strtoupper( $extension );
					continue;
				}
				$model_paths[] = $path;
			}

			if ( $unsupported ) {
				throw new RuntimeException(
					sprintf(
						__( 'Automatic fixed pricing supports STL, OBJ, and 3MF files. Unsupported printable format(s): %s.', 'service-requests-form' ),
						implode( ', ', array_values( array_unique( $unsupported ) ) )
					)
				);
			}
			if ( ! $model_paths ) {
				throw new RuntimeException( __( 'Upload at least one STL, OBJ, or 3MF model for automatic pricing.', 'service-requests-form' ) );
			}

			$metrics = self::collect_metrics( $model_paths );
			if ( $metrics['volume_mm3'] <= 0 || $metrics['triangle_count'] <= 0 ) {
				throw new RuntimeException( __( 'The model does not contain a measurable closed volume. Repair the mesh and upload it again.', 'service-requests-form' ) );
			}

			$submitted_profile = isset( $options['profile_key'] ) ? (string) $options['profile_key'] : ( isset( $options['process_preset'] ) ? (string) $options['process_preset'] : 'custom' );
			if ( class_exists( 'SRF_Print_Profiles' ) ) {
				$resolved = SRF_Print_Profiles::resolve_options( $submitted_profile, $options, $printer );
			} else {
				$resolved = array(
					'profile_key'     => 'custom',
					'profile_name'    => __( 'Custom settings', 'service-requests-form' ),
					'layer_height'    => isset( $options['layer_height'] ) ? (float) $options['layer_height'] : 0.20,
					'infill'          => isset( $options['infill'] ) ? (int) $options['infill'] : 20,
					'wall_loops'      => isset( $options['wall_loops'] ) ? (int) $options['wall_loops'] : ( isset( $options['wall_count'] ) ? (int) $options['wall_count'] : 2 ),
					'top_layers'      => isset( $options['top_layers'] ) ? (int) $options['top_layers'] : 4,
					'bottom_layers'   => isset( $options['bottom_layers'] ) ? (int) $options['bottom_layers'] : 3,
					'infill_pattern'  => isset( $options['infill_pattern'] ) ? sanitize_key( (string) $options['infill_pattern'] ) : 'grid',
					'time_factor'     => isset( $options['time_factor'] ) ? (float) $options['time_factor'] : 1,
					'material_factor' => isset( $options['material_factor'] ) ? (float) $options['material_factor'] : 1,
					'supports'        => ! empty( $options['supports'] ),
				);
			}

			$profile_key     = sanitize_key( (string) ( $resolved['profile_key'] ?? 'custom' ) );
			$profile_name    = sanitize_text_field( (string) ( $resolved['profile_name'] ?? __( 'Custom settings', 'service-requests-form' ) ) );
			$layer_height    = max( 0.01, min( 1.00, (float) ( $resolved['layer_height'] ?? 0.20 ) ) );
			$infill          = max( 0, min( 100, (int) ( $resolved['infill'] ?? 20 ) ) );
			$wall_loops      = max( 1, min( 12, (int) ( $resolved['wall_loops'] ?? ( $resolved['wall_count'] ?? 2 ) ) ) );
			$top_layers      = max( 0, min( 30, (int) ( $resolved['top_layers'] ?? 4 ) ) );
			$bottom_layers   = max( 0, min( 30, (int) ( $resolved['bottom_layers'] ?? 3 ) ) );
			$infill_pattern  = sanitize_key( (string) ( $resolved['infill_pattern'] ?? 'grid' ) );
			$time_factor     = max( 0.1, min( 5, (float) ( $resolved['time_factor'] ?? 1 ) ) );
			$material_factor = max( 0.1, min( 5, (float) ( $resolved['material_factor'] ?? 1 ) ) );
			$shell_mode      = ( isset( $options['shell_mode'] ) && 'hollow' === sanitize_key( (string) $options['shell_mode'] ) ) ? 'hollow' : 'solid';
			$supports        = ! empty( $resolved['supports'] );
			$scale           = max( 10, min( 500, (int) ( $options['scale'] ?? 100 ) ) );
			$quantity        = max( 1, min( 999, (int) ( $options['quantity'] ?? 1 ) ) );

			$min_layer = max( 0, (float) ( $printer->min_layer_height ?? 0 ) );
			$max_layer = max( 0, (float) ( $printer->max_layer_height ?? 0 ) );
			if ( $min_layer > 0 && $layer_height < $min_layer ) {
				throw new RuntimeException( sprintf( __( 'The selected layer height is below this printer minimum (%s mm).', 'service-requests-form' ), number_format_i18n( $min_layer, 2 ) ) );
			}
			if ( $max_layer > 0 && $layer_height > $max_layer ) {
				throw new RuntimeException( sprintf( __( 'The selected layer height is above this printer maximum (%s mm).', 'service-requests-form' ), number_format_i18n( $max_layer, 2 ) ) );
			}

			$scale_linear = $scale / 100;
			$scale_area   = $scale_linear * $scale_linear;
			$scale_volume = $scale_area * $scale_linear;
			self::assert_models_fit_printer( $metrics['models'], $printer, $scale_linear );

			$solid_volume_cm3 = ( $metrics['volume_mm3'] / 1000 ) * $scale_volume;
			$surface_area_mm2 = $metrics['surface_area_mm2'] * $scale_area;
			$line_width       = max( 0, (float) ( $printer->fdm_default_line_width ?? 0 ) );
			if ( $line_width <= 0 ) {
				$line_width = max( 0.1, (float) ( $printer->nozzle_size ?? 0.4 ) * 1.05 );
			}

			// Approximate shell material from surface area and configured shells.
			$wall_thickness_mm        = $line_width * $wall_loops;
			$cap_equivalent_mm        = $layer_height * ( $top_layers + $bottom_layers ) * 0.10;
			$shell_equivalent_mm      = max( $line_width, $wall_thickness_mm + $cap_equivalent_mm );
			$shell_volume_cm3         = ( $surface_area_mm2 * $shell_equivalent_mm ) / 1000;
			$shell_volume_cm3         = min( $solid_volume_cm3, max( 0, $shell_volume_cm3 ) );
			$interior_volume_cm3      = max( 0, $solid_volume_cm3 - $shell_volume_cm3 );
			$infill_fraction          = $infill / 100;
			$printed_volume_cm3       = ( 'hollow' === $shell_mode ) ? $shell_volume_cm3 : ( $shell_volume_cm3 + ( $interior_volume_cm3 * $infill_fraction ) );
			$support_factor           = $supports ? max( 1, (float) ( $printer->fdm_support_factor ?? 1.12 ) ) : 1;
			$printed_with_support_cm3 = $printed_volume_cm3 * $support_factor;

			$wastage_factor = max( 0.01, (float) ( $material->wastage_factor ?? 1 ) );
			$surface_factor = max( 0.01, (float) ( $material->surface_quality_factor ?? 1 ) );
			$machine_factor = max( 0.01, (float) ( $material->machine_time_factor ?? 1 ) );
			$density        = max( 0, (float) ( $material->density ?? 0 ) );
			$price_per_gram = max( 0, (float) ( $material->price_per_gram ?? 0 ) );
			$price_per_cm3  = max( 0, (float) ( $material->price_per_cm3 ?? 0 ) );

			$adjusted_cm3            = $printed_with_support_cm3 * $wastage_factor;
			$estimated_g             = $density > 0 ? $adjusted_cm3 * $density : 0;
			$material_by_volume      = $adjusted_cm3 * $price_per_cm3;
			$material_by_weight      = $estimated_g * $price_per_gram;
			$unit_material_cost      = max( $material_by_volume, $material_by_weight ) * $surface_factor * $material_factor;
			$items_material_total    = $unit_material_cost * $quantity;
			$minimum_material        = max( 0, (float) ( $printer->minimum_material_charge ?? 0 ) );
			$material_min_adjustment = max( 0, ( $minimum_material * $quantity ) - $items_material_total );
			$items_material_total   += $material_min_adjustment;

			$throughput_cm3_h = self::resolve_throughput_cm3_h( $printer );
			$hourly_cost      = max( 0, (float) ( $printer->hourly_cost ?? 0 ) );
			$efficiency       = max( 0.05, (float) ( $printer->machine_efficiency_factor ?? 1 ) );
			$layer_factor     = pow( 0.20 / max( 0.04, $layer_height ), 0.65 );

			$unit_print_hours = ( $printed_with_support_cm3 / $throughput_cm3_h )
				* $machine_factor
				* $efficiency
				* $layer_factor
				* $time_factor;
			$unit_print_hours = max( 0.01, $unit_print_hours );

			$fixed_minutes = max( 0, (float) ( $printer->setup_time_minutes ?? 0 ) )
				+ max( 0, (float) ( $printer->warmup_time_minutes ?? 0 ) )
				+ max( 0, (float) ( $printer->postprocess_time_minutes ?? 0 ) );
			$fixed_machine_hours = $fixed_minutes / 60;
			$total_print_hours    = ( $unit_print_hours * $quantity ) + $fixed_machine_hours;
			$items_printer_total  = $total_print_hours * $hourly_cost;

			$pricing_model = sanitize_key( (string) ( $printer->pricing_model ?? 'hybrid' ) );
			if ( in_array( $pricing_model, array( 'material', 'material_only' ), true ) ) {
				$items_printer_total = 0;
			} elseif ( in_array( $pricing_model, array( 'machine_time', 'time_only' ), true ) ) {
				$items_material_total    = 0;
				$material_min_adjustment = 0;
			}

			$service_fee = max( 0, (float) ( $quote_settings['service_fee'] ?? 0 ) );
			$setup_fee   = max( 0, (float) ( $quote_settings['setup_fee'] ?? 0 ) );
			$tax_rate    = max( 0, (float) ( $quote_settings['tax_rate'] ?? 0 ) );
			$margin      = max( 0, (float) ( $quote_settings['profit_margin'] ?? 0 ) );
			if ( isset( $printer->margin_override ) && '' !== (string) $printer->margin_override && is_numeric( $printer->margin_override ) ) {
				$margin = max( 0, (float) $printer->margin_override );
			}

			$items_subtotal         = $items_material_total + $items_printer_total;
			$subtotal_before_margin = $items_subtotal + $service_fee + $setup_fee;
			$margin_amount          = $subtotal_before_margin * ( $margin / 100 );
			$subtotal_with_margin   = $subtotal_before_margin + $margin_amount;
			$minimum_job_price      = max( 0, (float) ( $printer->minimum_job_price ?? 0 ) );
			$minimum_job_adjustment = max( 0, ( $minimum_job_price * $quantity ) - $subtotal_with_margin );
			$taxable_subtotal       = $subtotal_with_margin + $minimum_job_adjustment;
			$tax_amount             = $taxable_subtotal * ( $tax_rate / 100 );
			$final_total            = $taxable_subtotal + $tax_amount;

			$scaled_bounds = array(
				'x' => round( $metrics['bounds_mm']['x'] * $scale_linear, 4 ),
				'y' => round( $metrics['bounds_mm']['y'] * $scale_linear, 4 ),
				'z' => round( $metrics['bounds_mm']['z'] * $scale_linear, 4 ),
			);

			$rounded_unit_hours  = round( $unit_print_hours, 4 );
			$rounded_total_hours = round( $total_print_hours, 4 );
			$result = array(
				'formula_version'             => self::FORMULA_VERSION,
				'calculation_version'         => self::FORMULA_VERSION,
				'estimate_type'               => 'geometry-heuristic',
				'currency'                    => (string) ( $quote_settings['currency'] ?? 'EUR' ),
				'currency_symbol'             => (string) ( $quote_settings['currency_symbol'] ?? '€' ),
				'model_count'                 => (int) $metrics['model_count'],
				'model_formats'               => $metrics['formats'],
				'model_triangles'             => (int) $metrics['triangle_count'],
				'model_bounds_mm'             => $metrics['bounds_mm'],
				'scaled_bounds_mm'            => $scaled_bounds,
				'model_volume_cm3'            => round( $metrics['volume_mm3'] / 1000, 5 ),
				'model_surface_area_cm2'      => round( $metrics['surface_area_mm2'] / 100, 5 ),
				'effective_volume_cm3'        => round( $printed_volume_cm3, 5 ),
				'printed_with_support_cm3'    => round( $printed_with_support_cm3, 5 ),
				'adjusted_volume_cm3'         => round( $adjusted_cm3, 5 ),
				'estimated_weight_g'          => round( $estimated_g, 5 ),
				'throughput_cm3_h'            => round( $throughput_cm3_h, 4 ),
				'unit_print_hours'            => $rounded_unit_hours,
				'estimated_unit_print_hours'  => $rounded_unit_hours,
				'fixed_machine_hours'         => round( $fixed_machine_hours, 4 ),
				'estimated_print_hours'       => $rounded_total_hours,
				'estimated_total_print_hours' => $rounded_total_hours,
				'estimated_print_minutes'     => (int) ceil( $total_print_hours * 60 ),
				'unit_material_cost'          => round( $unit_material_cost, 2 ),
				'unit_printer_cost'           => round( $unit_print_hours * $hourly_cost, 2 ),
				'items_material_total'        => round( $items_material_total, 2 ),
				'material_minimum_adjustment' => round( $material_min_adjustment, 2 ),
				'items_printer_total'         => round( $items_printer_total, 2 ),
				'service_fee'                 => round( $service_fee, 2 ),
				'setup_fee'                   => round( $setup_fee, 2 ),
				'profit_margin_percent'       => round( $margin, 4 ),
				'profit_margin_amount'        => round( $margin_amount, 2 ),
				'minimum_job_adjustment'      => round( $minimum_job_adjustment, 2 ),
				'tax_rate'                    => round( $tax_rate, 4 ),
				'tax_amount'                  => round( $tax_amount, 2 ),
				'subtotal_before_margin'      => round( $subtotal_before_margin, 2 ),
				'subtotal_with_margin'        => round( $subtotal_with_margin, 2 ),
				'taxable_subtotal'            => round( $taxable_subtotal, 2 ),
				'total_price'                 => round( max( 0, $final_total ), 2 ),
				'quantity'                    => $quantity,
				'profile_key'                 => $profile_key,
				'profile_name'                => $profile_name,
				'process_preset'              => $profile_key,
				'process_preset_name'         => $profile_name,
				'layer_height'                => round( $layer_height, 4 ),
				'infill'                      => $infill,
				'wall_loops'                  => $wall_loops,
				'wall_count'                  => $wall_loops,
				'top_layers'                  => $top_layers,
				'bottom_layers'               => $bottom_layers,
				'infill_pattern'              => $infill_pattern,
				'time_factor'                 => round( $time_factor, 4 ),
				'material_factor'             => round( $material_factor, 4 ),
				'shell_mode'                  => $shell_mode,
				'supports'                    => $supports ? 1 : 0,
				'scale'                       => $scale,
			);

			return $result;
		}
		protected static function resolve_throughput_cm3_h( $printer ) {
			$speed = max( 0, (float) ( $printer->default_speed ?? 0 ) );
			$unit  = strtolower( preg_replace( '/\s+/', '', (string) ( $printer->speed_unit ?? '' ) ) );
			if ( false !== strpos( $unit, 'cm3' ) || false !== strpos( $unit, 'cm³' ) ) {
				return max( 0.25, $speed );
			}
			if ( $speed > 0 && $speed <= 30 ) {
				return max( 0.25, $speed );
			}
			$technology = sanitize_key( (string) ( $printer->technology ?? 'fdm' ) );
			if ( in_array( $technology, array( 'fdm', 'fff' ), true ) && $speed > 30 ) {
				return max( 2, min( 30, $speed * 0.05 ) );
			}
			return in_array( $technology, array( 'sla', 'dlp', 'msla', 'resin' ), true ) ? 5 : 8;
		}

		protected static function assert_models_fit_printer( array $models, $printer, $scale_linear ) {
			$build = array(
				max( 0, (float) ( $printer->build_volume_x ?? 0 ) ),
				max( 0, (float) ( $printer->build_volume_y ?? 0 ) ),
				max( 0, (float) ( $printer->build_volume_z ?? 0 ) ),
			);
			if ( min( $build ) <= 0 ) {
				return;
			}
			foreach ( $models as $model ) {
				$dims = array(
					(float) $model['bounds_mm']['x'] * $scale_linear,
					(float) $model['bounds_mm']['y'] * $scale_linear,
					(float) $model['bounds_mm']['z'] * $scale_linear,
				);
				if ( ! self::dimensions_fit_with_rotation( $dims, $build ) ) {
					throw new RuntimeException(
						sprintf(
							__( 'Model "%1$s" is %2$s × %3$s × %4$s mm at the selected scale and does not fit this printer build volume (%5$s × %6$s × %7$s mm), even after rotating it.', 'service-requests-form' ),
							(string) $model['name'],
							number_format_i18n( $dims[0], 1 ),
							number_format_i18n( $dims[1], 1 ),
							number_format_i18n( $dims[2], 1 ),
							number_format_i18n( $build[0], 1 ),
							number_format_i18n( $build[1], 1 ),
							number_format_i18n( $build[2], 1 )
						)
					);
				}
			}
		}

		protected static function dimensions_fit_with_rotation( array $dims, array $build ) {
			$permutations = array(
				array( 0, 1, 2 ), array( 0, 2, 1 ), array( 1, 0, 2 ),
				array( 1, 2, 0 ), array( 2, 0, 1 ), array( 2, 1, 0 ),
			);
			foreach ( $permutations as $p ) {
				if ( $dims[ $p[0] ] <= $build[0] + 0.001 && $dims[ $p[1] ] <= $build[1] + 0.001 && $dims[ $p[2] ] <= $build[2] + 0.001 ) {
					return true;
				}
			}
			return false;
		}

		protected static function collect_metrics( array $model_paths ) {
			$totals = array(
				'volume_mm3'      => 0.0,
				'surface_area_mm2'=> 0.0,
				'triangle_count'  => 0,
				'model_count'     => 0,
				'formats'         => array(),
				'bounds_mm'       => array( 'x' => 0.0, 'y' => 0.0, 'z' => 0.0 ),
				'models'          => array(),
			);
			foreach ( $model_paths as $path ) {
				$metric = self::parse_model_file( $path );
				$metric['name'] = basename( $path );
				$totals['volume_mm3']       += $metric['volume_mm3'];
				$totals['surface_area_mm2'] += $metric['surface_area_mm2'];
				$totals['triangle_count']   += $metric['triangle_count'];
				$totals['model_count']++;
				$totals['formats'][] = strtoupper( $metric['format'] );
				$totals['bounds_mm']['x'] = max( $totals['bounds_mm']['x'], $metric['bounds_mm']['x'] );
				$totals['bounds_mm']['y'] = max( $totals['bounds_mm']['y'], $metric['bounds_mm']['y'] );
				$totals['bounds_mm']['z'] = max( $totals['bounds_mm']['z'], $metric['bounds_mm']['z'] );
				$totals['models'][] = $metric;
				if ( $totals['triangle_count'] > self::MAX_TRIANGLES ) {
					throw new RuntimeException( __( 'The uploaded models contain too many triangles to analyse safely in one request.', 'service-requests-form' ) );
				}
			}
			$totals['formats'] = array_values( array_unique( $totals['formats'] ) );
			return $totals;
		}

		protected static function parse_model_file( $path ) {
			switch ( strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
				case 'stl': return self::parse_stl_file( $path );
				case 'obj': return self::parse_obj_file( $path );
				case '3mf': return self::parse_3mf_file( $path );
			}
			throw new RuntimeException( sprintf( __( 'Unsupported model file: %s', 'service-requests-form' ), basename( $path ) ) );
		}

		protected static function parse_stl_file( $path ) {
			$handle = fopen( $path, 'rb' );
			if ( ! $handle ) {
				throw new RuntimeException( sprintf( __( 'Could not read STL file: %s', 'service-requests-form' ), basename( $path ) ) );
			}
			$header = fread( $handle, 84 );
			$size   = filesize( $path );
			$is_binary = false;
			$face_count = 0;
			if ( strlen( $header ) >= 84 ) {
				$face_data  = unpack( 'Vfaces', substr( $header, 80, 4 ) );
				$face_count = isset( $face_data['faces'] ) ? (int) $face_data['faces'] : 0;
				$expected_size = 84 + ( $face_count * 50 );
				// Accept harmless trailing bytes while still rejecting impossible headers.
				$is_binary = $face_count > 0 && $expected_size <= (int) $size;
			}
			if ( $is_binary && $face_count > self::MAX_TRIANGLES ) {
				fclose( $handle );
				throw new RuntimeException( __( 'This STL contains too many triangles to analyse safely.', 'service-requests-form' ) );
			}
			rewind( $handle );
			try {
				$result = $is_binary ? self::parse_binary_stl_stream( $handle, $face_count ) : self::parse_ascii_stl_stream( $handle );
			} finally {
				fclose( $handle );
			}
			return $result;
		}

		protected static function parse_binary_stl_stream( $handle, $face_count ) {
			if ( 84 !== strlen( fread( $handle, 84 ) ) ) {
				throw new RuntimeException( __( 'This binary STL header is incomplete.', 'service-requests-form' ) );
			}
			$volume = 0.0;
			$area   = 0.0;
			$bounds = self::create_bounds();
			$read_faces = 0;
			for ( $i = 0; $i < $face_count; $i++ ) {
				$record = fread( $handle, 50 );
				if ( 50 !== strlen( $record ) ) {
					throw new RuntimeException( __( 'This binary STL is incomplete.', 'service-requests-form' ) );
				}
				$v1 = self::unpack_float_vertex( $record, 12 );
				$v2 = self::unpack_float_vertex( $record, 24 );
				$v3 = self::unpack_float_vertex( $record, 36 );
				self::expand_bounds( $bounds, $v1 ); self::expand_bounds( $bounds, $v2 ); self::expand_bounds( $bounds, $v3 );
				$volume += self::signed_triangle_volume( $v1, $v2, $v3 );
				$area   += self::triangle_area( $v1, $v2, $v3 );
				$read_faces++;
			}
			return self::metric_result( 'stl', $volume, $area, $read_faces, $bounds );
		}

		protected static function parse_ascii_stl_stream( $handle ) {
			$vertices = array();
			$volume = 0.0;
			$area = 0.0;
			$triangles = 0;
			$bounds = self::create_bounds();
			while ( false !== ( $line = fgets( $handle ) ) ) {
				if ( ! preg_match( '/^\s*vertex\s+([-+]?\d*\.?\d+(?:e[-+]?\d+)?)\s+([-+]?\d*\.?\d+(?:e[-+]?\d+)?)\s+([-+]?\d*\.?\d+(?:e[-+]?\d+)?)/i', $line, $match ) ) {
					continue;
				}
				$vertex = array( 'x' => (float) $match[1], 'y' => (float) $match[2], 'z' => (float) $match[3] );
				self::expand_bounds( $bounds, $vertex );
				$vertices[] = $vertex;
				if ( 3 === count( $vertices ) ) {
					$volume += self::signed_triangle_volume( $vertices[0], $vertices[1], $vertices[2] );
					$area   += self::triangle_area( $vertices[0], $vertices[1], $vertices[2] );
					$triangles++;
					if ( $triangles > self::MAX_TRIANGLES ) {
						throw new RuntimeException( __( 'This STL contains too many triangles to analyse safely.', 'service-requests-form' ) );
					}
					$vertices = array();
				}
			}
			if ( $vertices ) {
				throw new RuntimeException( __( 'This ASCII STL is incomplete.', 'service-requests-form' ) );
			}
			return self::metric_result( 'stl', $volume, $area, $triangles, $bounds );
		}

		protected static function unpack_float_vertex( $contents, $offset ) {
			$vals = unpack( 'gx/gy/gz', substr( $contents, $offset, 12 ) );
			return array( 'x' => (float) ( $vals['x'] ?? 0 ), 'y' => (float) ( $vals['y'] ?? 0 ), 'z' => (float) ( $vals['z'] ?? 0 ) );
		}

		protected static function parse_obj_file( $path ) {
			$handle = fopen( $path, 'rb' );
			if ( ! $handle ) {
				throw new RuntimeException( sprintf( __( 'Could not read OBJ file: %s', 'service-requests-form' ), basename( $path ) ) );
			}
			$vertices = array();
			$bounds = self::create_bounds();
			$volume = 0.0;
			$area = 0.0;
			$triangles = 0;
			try {
				while ( false !== ( $line = fgets( $handle ) ) ) {
					$line = trim( $line );
					if ( '' === $line || '#' === substr( $line, 0, 1 ) ) { continue; }
					$parts = preg_split( '/\s+/', $line );
					if ( 'v' === ( $parts[0] ?? '' ) && count( $parts ) >= 4 ) {
						$vertex = array( 'x' => (float) $parts[1], 'y' => (float) $parts[2], 'z' => (float) $parts[3] );
						$vertices[] = $vertex;
						if ( count( $vertices ) > self::MAX_VERTICES ) {
							throw new RuntimeException( __( 'This OBJ contains too many vertices to analyse safely.', 'service-requests-form' ) );
						}
						self::expand_bounds( $bounds, $vertex );
					} elseif ( 'f' === ( $parts[0] ?? '' ) && count( $parts ) >= 4 ) {
						$indexes = array();
						for ( $i = 1; $i < count( $parts ); $i++ ) {
							$index = self::parse_obj_index( $parts[ $i ], count( $vertices ) );
							if ( $index >= 0 && isset( $vertices[ $index ] ) ) { $indexes[] = $index; }
						}
						for ( $j = 1; $j < count( $indexes ) - 1; $j++ ) {
							$v1 = $vertices[ $indexes[0] ]; $v2 = $vertices[ $indexes[ $j ] ]; $v3 = $vertices[ $indexes[ $j + 1 ] ];
							$volume += self::signed_triangle_volume( $v1, $v2, $v3 );
							$area += self::triangle_area( $v1, $v2, $v3 );
							$triangles++;
							if ( $triangles > self::MAX_TRIANGLES ) {
								throw new RuntimeException( __( 'This OBJ contains too many triangles to analyse safely.', 'service-requests-form' ) );
							}
						}
					}
				}
			} finally {
				fclose( $handle );
			}
			return self::metric_result( 'obj', $volume, $area, $triangles, $bounds );
		}

		protected static function parse_obj_index( $token, $total ) {
			$parts = explode( '/', (string) $token );
			$raw = (int) $parts[0];
			if ( 0 === $raw ) { return -1; }
			return $raw > 0 ? $raw - 1 : $total + $raw;
		}

		protected static function parse_3mf_file( $path ) {
			if ( ! class_exists( 'ZipArchive' ) || ! class_exists( 'DOMDocument' ) || ! class_exists( 'DOMXPath' ) ) {
				throw new RuntimeException( __( 'PHP ZipArchive and DOM extensions are required to analyse 3MF files.', 'service-requests-form' ) );
			}

			$zip = new ZipArchive();
			if ( true !== $zip->open( $path ) ) {
				throw new RuntimeException( sprintf( __( 'Could not open 3MF file: %s', 'service-requests-form' ), basename( $path ) ) );
			}

			$model_xml = '';
			try {
				if ( $zip->numFiles > self::MAX_3MF_ENTRIES ) {
					throw new RuntimeException( __( 'The 3MF package contains too many entries to analyse safely.', 'service-requests-form' ) );
				}

				$indexes = array();
				$preferred = $zip->locateName( '3D/3dmodel.model', defined( 'ZipArchive::FL_NOCASE' ) ? ZipArchive::FL_NOCASE : 0 );
				if ( false !== $preferred ) {
					$indexes[] = (int) $preferred;
				}
				for ( $i = 0; $i < $zip->numFiles; $i++ ) {
					$name = $zip->getNameIndex( $i );
					if ( ! is_string( $name ) || ! preg_match( '/\.model$/i', $name ) ) {
						continue;
					}
					if ( ! in_array( $i, $indexes, true ) ) {
						$indexes[] = $i;
					}
				}

				foreach ( $indexes as $index ) {
					$stat = $zip->statIndex( $index );
					if ( is_array( $stat ) && isset( $stat['size'] ) && (int) $stat['size'] > self::MAX_3MF_XML_BYTES ) {
						throw new RuntimeException( __( 'The 3MF model definition is too large to analyse safely.', 'service-requests-form' ) );
					}
					$candidate = $zip->getFromIndex( $index );
					if ( is_string( $candidate ) && '' !== $candidate ) {
						$model_xml = $candidate;
						break;
					}
				}
			} finally {
				$zip->close();
			}

			if ( ! is_string( $model_xml ) || '' === $model_xml ) {
				throw new RuntimeException( __( 'This 3MF file does not contain a readable model definition.', 'service-requests-form' ) );
			}
			if ( strlen( $model_xml ) > self::MAX_3MF_XML_BYTES ) {
				throw new RuntimeException( __( 'The 3MF model definition is too large to analyse safely.', 'service-requests-form' ) );
			}

			$dom      = new DOMDocument();
			$previous = libxml_use_internal_errors( true );
			$loaded   = $dom->loadXML( $model_xml, LIBXML_NONET | LIBXML_COMPACT );
			libxml_clear_errors();
			libxml_use_internal_errors( $previous );
			if ( ! $loaded ) {
				throw new RuntimeException( __( 'This 3MF file could not be parsed.', 'service-requests-form' ) );
			}

			$xpath = new DOMXPath( $dom );
			$xpath->registerNamespace( 'm', 'http://schemas.microsoft.com/3dmanufacturing/core/2015/02' );
			$model = $xpath->query( '/m:model' )->item( 0 );
			if ( ! $model ) {
				throw new RuntimeException( __( 'This 3MF file has no model node.', 'service-requests-form' ) );
			}

			$unit_node   = $model->attributes ? $model->attributes->getNamedItem( 'unit' ) : null;
			$unit_factor = self::unit_scale_to_mm( $unit_node ? $unit_node->nodeValue : 'millimeter' );
			$objects     = array();
			$vertex_total = 0;
			$triangle_total = 0;
			$component_total = 0;

			foreach ( $xpath->query( '/m:model/m:resources/m:object' ) as $object_node ) {
				if ( count( $objects ) > 100000 ) {
					throw new RuntimeException( __( 'The 3MF contains too many object resources to analyse safely.', 'service-requests-form' ) );
				}
				$id_node = $object_node->attributes ? $object_node->attributes->getNamedItem( 'id' ) : null;
				if ( ! $id_node ) {
					continue;
				}
				$object_id       = (int) $id_node->nodeValue;
				$mesh_node       = $xpath->query( 'm:mesh', $object_node )->item( 0 );
				$components_node = $xpath->query( 'm:components', $object_node )->item( 0 );

				if ( $mesh_node ) {
					$vertices = array();
					foreach ( $xpath->query( 'm:vertices/m:vertex', $mesh_node ) as $vertex_node ) {
						$vertex_total++;
						if ( $vertex_total > self::MAX_VERTICES ) {
							throw new RuntimeException( __( 'The 3MF contains too many vertices to analyse safely.', 'service-requests-form' ) );
						}
						$x = (float) $vertex_node->getAttribute( 'x' ) * $unit_factor;
						$y = (float) $vertex_node->getAttribute( 'y' ) * $unit_factor;
						$z = (float) $vertex_node->getAttribute( 'z' ) * $unit_factor;
						if ( ! is_finite( $x ) || ! is_finite( $y ) || ! is_finite( $z ) ) {
							throw new RuntimeException( __( 'The 3MF contains invalid vertex coordinates.', 'service-requests-form' ) );
						}
						$vertices[] = array( 'x' => $x, 'y' => $y, 'z' => $z );
					}

					$triangles = array();
					foreach ( $xpath->query( 'm:triangles/m:triangle', $mesh_node ) as $triangle_node ) {
						$triangle_total++;
						if ( $triangle_total > self::MAX_TRIANGLES ) {
							throw new RuntimeException( __( 'The 3MF contains too many triangles to analyse safely.', 'service-requests-form' ) );
						}
						$triangles[] = array(
							'v1' => (int) $triangle_node->getAttribute( 'v1' ),
							'v2' => (int) $triangle_node->getAttribute( 'v2' ),
							'v3' => (int) $triangle_node->getAttribute( 'v3' ),
						);
					}
					$objects[ $object_id ] = array( 'type' => 'mesh', 'vertices' => $vertices, 'triangles' => $triangles );
				} elseif ( $components_node ) {
					$components = array();
					foreach ( $xpath->query( 'm:component', $components_node ) as $component_node ) {
						$component_total++;
						if ( $component_total > self::MAX_TRIANGLES ) {
							throw new RuntimeException( __( 'The 3MF contains too many component references to analyse safely.', 'service-requests-form' ) );
						}
						$components[] = array(
							'objectid' => (int) $component_node->getAttribute( 'objectid' ),
							'transform' => self::parse_3mf_transform( $component_node->getAttribute( 'transform' ), $unit_factor ),
						);
					}
					$objects[ $object_id ] = array( 'type' => 'components', 'components' => $components );
				}
			}

			$instances = array();
			foreach ( $xpath->query( '/m:model/m:build/m:item' ) as $item_node ) {
				if ( count( $instances ) >= 100000 ) {
					throw new RuntimeException( __( 'The 3MF build contains too many items to analyse safely.', 'service-requests-form' ) );
				}
				$instances[] = array(
					'objectid' => (int) $item_node->getAttribute( 'objectid' ),
					'transform' => self::parse_3mf_transform( $item_node->getAttribute( 'transform' ), $unit_factor ),
				);
			}
			if ( ! $instances ) {
				foreach ( $objects as $object_id => $object ) {
					if ( 'mesh' === $object['type'] ) {
						$instances[] = array( 'objectid' => $object_id, 'transform' => self::identity_matrix() );
					}
				}
			}

			$bounds              = self::create_bounds();
			$volume              = 0.0;
			$area                = 0.0;
			$triangles           = 0;
			$processed_triangles = 0;
			foreach ( $instances as $instance ) {
				$result = self::accumulate_3mf_object(
					$instance['objectid'],
					$instance['transform'],
					$objects,
					$bounds,
					array(),
					0,
					$processed_triangles
				);
				$volume    += $result['volume_mm3'];
				$area      += $result['surface_area_mm2'];
				$triangles += $result['triangle_count'];
			}

			return self::metric_result( '3mf', $volume, $area, $triangles, $bounds );
		}

		protected static function accumulate_3mf_object( $object_id, array $transform, array $objects, array &$bounds, array $path, $depth, &$processed_triangles ) {
			if ( $depth > 40 || isset( $path[ $object_id ] ) || empty( $objects[ $object_id ] ) ) {
				return array( 'volume_mm3' => 0.0, 'surface_area_mm2' => 0.0, 'triangle_count' => 0 );
			}
			$path[ $object_id ] = true;
			$object = $objects[ $object_id ];
			$result = array( 'volume_mm3' => 0.0, 'surface_area_mm2' => 0.0, 'triangle_count' => 0 );
			if ( 'mesh' === $object['type'] ) {
				$orientation = self::determinant_3mf_transform( $transform ) < 0 ? -1.0 : 1.0;
				foreach ( $object['triangles'] as $triangle ) {
					$processed_triangles++;
					if ( $processed_triangles > self::MAX_TRIANGLES ) {
						throw new RuntimeException( __( 'The 3MF expands to too many triangles to analyse safely.', 'service-requests-form' ) );
					}
					if ( ! isset( $object['vertices'][ $triangle['v1'] ], $object['vertices'][ $triangle['v2'] ], $object['vertices'][ $triangle['v3'] ] ) ) { continue; }
					$v1 = self::apply_3mf_transform( $object['vertices'][ $triangle['v1'] ], $transform );
					$v2 = self::apply_3mf_transform( $object['vertices'][ $triangle['v2'] ], $transform );
					$v3 = self::apply_3mf_transform( $object['vertices'][ $triangle['v3'] ], $transform );
					self::expand_bounds( $bounds, $v1 ); self::expand_bounds( $bounds, $v2 ); self::expand_bounds( $bounds, $v3 );
					$result['volume_mm3'] += self::signed_triangle_volume( $v1, $v2, $v3 ) * $orientation;
					$result['surface_area_mm2'] += self::triangle_area( $v1, $v2, $v3 );
					$result['triangle_count']++;
				}
			} else {
				foreach ( $object['components'] as $component ) {
					$child = self::accumulate_3mf_object( $component['objectid'], self::multiply_3mf_transforms( $transform, $component['transform'] ), $objects, $bounds, $path, $depth + 1, $processed_triangles );
					$result['volume_mm3'] += $child['volume_mm3']; $result['surface_area_mm2'] += $child['surface_area_mm2']; $result['triangle_count'] += $child['triangle_count'];
				}
			}
			return $result;
		}

		protected static function metric_result( $format, $volume, $area, $triangles, array $bounds ) {
			if ( $triangles <= 0 ) {
				throw new RuntimeException( sprintf( __( 'This %s file does not contain printable triangles.', 'service-requests-form' ), strtoupper( $format ) ) );
			}
			return array(
				'format'           => $format,
				'volume_mm3'       => abs( (float) $volume ),
				'surface_area_mm2' => max( 0, (float) $area ),
				'triangle_count'   => (int) $triangles,
				'bounds_mm'        => self::bounds_dimensions( $bounds ),
			);
		}

		protected static function unit_scale_to_mm( $unit ) {
			switch ( strtolower( (string) $unit ) ) {
				case 'micron': return 0.001;
				case 'centimeter': return 10.0;
				case 'meter': return 1000.0;
				case 'inch': return 25.4;
				case 'foot': return 304.8;
				default: return 1.0;
			}
		}

		protected static function parse_3mf_transform( $raw, $unit_factor = 1.0 ) {
			$values = preg_split( '/\s+/', trim( (string) $raw ) );
			$values = array_map( 'floatval', array_slice( $values, 0, 12 ) );
			if ( 12 !== count( $values ) ) { return self::identity_matrix(); }
			$unit_factor = max( 0.0, (float) $unit_factor );
			// 3MF serialises a row-vector 4x3 affine matrix. The pricing
			// engine applies column vectors, so transpose it here. Translation
			// values use the model unit and must be converted to millimetres.
			return array(
				array( $values[0], $values[3], $values[6], $values[9] * $unit_factor ),
				array( $values[1], $values[4], $values[7], $values[10] * $unit_factor ),
				array( $values[2], $values[5], $values[8], $values[11] * $unit_factor ),
				array( 0.0, 0.0, 0.0, 1.0 ),
			);
		}

		/**
		 * Determinant of the affine transform's 3x3 linear component.
		 * Used to prevent mirrored 3MF instances from cancelling normal ones.
		 */
		protected static function determinant_3mf_transform( array $matrix ) {
			return
				$matrix[0][0] * ( $matrix[1][1] * $matrix[2][2] - $matrix[1][2] * $matrix[2][1] )
				- $matrix[0][1] * ( $matrix[1][0] * $matrix[2][2] - $matrix[1][2] * $matrix[2][0] )
				+ $matrix[0][2] * ( $matrix[1][0] * $matrix[2][1] - $matrix[1][1] * $matrix[2][0] );
		}

		protected static function identity_matrix() {
			return array( array( 1.0, 0.0, 0.0, 0.0 ), array( 0.0, 1.0, 0.0, 0.0 ), array( 0.0, 0.0, 1.0, 0.0 ), array( 0.0, 0.0, 0.0, 1.0 ) );
		}

		protected static function multiply_3mf_transforms( array $a, array $b ) {
			$result = self::identity_matrix();
			for ( $i = 0; $i < 4; $i++ ) {
				for ( $j = 0; $j < 4; $j++ ) {
					$result[ $i ][ $j ] = 0.0;
					for ( $k = 0; $k < 4; $k++ ) { $result[ $i ][ $j ] += $a[ $i ][ $k ] * $b[ $k ][ $j ]; }
				}
			}
			return $result;
		}

		protected static function apply_3mf_transform( array $vertex, array $matrix ) {
			return array(
				'x' => $matrix[0][0] * $vertex['x'] + $matrix[0][1] * $vertex['y'] + $matrix[0][2] * $vertex['z'] + $matrix[0][3],
				'y' => $matrix[1][0] * $vertex['x'] + $matrix[1][1] * $vertex['y'] + $matrix[1][2] * $vertex['z'] + $matrix[1][3],
				'z' => $matrix[2][0] * $vertex['x'] + $matrix[2][1] * $vertex['y'] + $matrix[2][2] * $vertex['z'] + $matrix[2][3],
			);
		}

		protected static function signed_triangle_volume( array $v1, array $v2, array $v3 ) {
			return ( $v1['x'] * ( $v2['y'] * $v3['z'] - $v2['z'] * $v3['y'] ) + $v1['y'] * ( $v2['z'] * $v3['x'] - $v2['x'] * $v3['z'] ) + $v1['z'] * ( $v2['x'] * $v3['y'] - $v2['y'] * $v3['x'] ) ) / 6.0;
		}

		protected static function triangle_area( array $v1, array $v2, array $v3 ) {
			$ax = $v2['x'] - $v1['x']; $ay = $v2['y'] - $v1['y']; $az = $v2['z'] - $v1['z'];
			$bx = $v3['x'] - $v1['x']; $by = $v3['y'] - $v1['y']; $bz = $v3['z'] - $v1['z'];
			$cx = $ay * $bz - $az * $by; $cy = $az * $bx - $ax * $bz; $cz = $ax * $by - $ay * $bx;
			return 0.5 * sqrt( $cx * $cx + $cy * $cy + $cz * $cz );
		}

		protected static function create_bounds() {
			return array( 'minX' => INF, 'minY' => INF, 'minZ' => INF, 'maxX' => -INF, 'maxY' => -INF, 'maxZ' => -INF );
		}

		protected static function expand_bounds( array &$bounds, array $vertex ) {
			$bounds['minX'] = min( $bounds['minX'], $vertex['x'] ); $bounds['minY'] = min( $bounds['minY'], $vertex['y'] ); $bounds['minZ'] = min( $bounds['minZ'], $vertex['z'] );
			$bounds['maxX'] = max( $bounds['maxX'], $vertex['x'] ); $bounds['maxY'] = max( $bounds['maxY'], $vertex['y'] ); $bounds['maxZ'] = max( $bounds['maxZ'], $vertex['z'] );
		}

		protected static function bounds_dimensions( array $bounds ) {
			if ( ! is_finite( $bounds['minX'] ) ) { return array( 'x' => 0.0, 'y' => 0.0, 'z' => 0.0 ); }
			return array(
				'x' => round( max( 0, $bounds['maxX'] - $bounds['minX'] ), 4 ),
				'y' => round( max( 0, $bounds['maxY'] - $bounds['minY'] ), 4 ),
				'z' => round( max( 0, $bounds['maxZ'] - $bounds['minZ'] ), 4 ),
			);
		}
	}
}
