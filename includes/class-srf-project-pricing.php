<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SRF_Project_Pricing' ) ) {
	class SRF_Project_Pricing {
		protected static $supported_extensions = array( 'stl', 'obj', '3mf' );
		protected static $model_extensions     = array( 'stl', 'obj', '3mf', 'step', 'stp', 'iges', 'igs' );

		public static function get_supported_extensions() {
			return self::$supported_extensions;
		}

		public static function get_model_extensions() {
			return self::$model_extensions;
		}

		public static function calculate_final_quote( array $file_paths, $material, $printer, array $quote_settings, array $options = array() ) {
			$model_paths = array();
			$unsupported = array();

			foreach ( $file_paths as $path ) {
				$path = (string) $path;
				if ( '' === $path || ! is_readable( $path ) ) {
					continue;
				}

				$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
				if ( ! in_array( $ext, self::$model_extensions, true ) ) {
					continue;
				}

				if ( ! in_array( $ext, self::$supported_extensions, true ) ) {
					$unsupported[] = strtoupper( $ext );
					continue;
				}

				$model_paths[] = $path;
			}

			if ( ! empty( $unsupported ) ) {
				throw new RuntimeException(
					sprintf(
						__( 'Automatic final pricing is currently available only for STL, OBJ, and 3MF files. Unsupported model format(s): %s.', 'service-requests-form' ),
						implode( ', ', array_values( array_unique( $unsupported ) ) )
					)
				);
			}

			if ( empty( $model_paths ) ) {
				throw new RuntimeException( __( 'A printable 3D model file is required for final price calculation.', 'service-requests-form' ) );
			}

			$metrics = self::collect_metrics( $model_paths );
			if ( $metrics['volume_mm3'] <= 0 ) {
				throw new RuntimeException( __( 'The uploaded 3D model could not be analyzed for final price calculation.', 'service-requests-form' ) );
			}

			$layer_height = max( 0.01, (float) ( $options['layer_height'] ?? 0.2 ) );
			$infill       = max( 0, min( 100, (int) ( $options['infill'] ?? 20 ) ) );
			$shell_mode   = ( isset( $options['shell_mode'] ) && 'hollow' === (string) $options['shell_mode'] ) ? 'hollow' : 'solid';
			$scale        = max( 10, min( 500, (int) ( $options['scale'] ?? 100 ) ) );
			$quantity     = max( 1, (int) ( $options['quantity'] ?? 1 ) );

			$actual_volume_cm3 = $metrics['volume_mm3'] / 1000;
			$scale_factor      = pow( $scale / 100, 3 );
			$shell_factor      = ( 'hollow' === $shell_mode ) ? 0.55 : 1;
			$infill_factor     = 0.2 + ( $infill / 100 ) * 0.8;
			$effective_cm3     = $actual_volume_cm3 * $scale_factor * $shell_factor * $infill_factor;

			$wastage_factor = max( 0, (float) ( $material->wastage_factor ?? 1 ) );
			$surface_factor = max( 0, (float) ( $material->surface_quality_factor ?? 1 ) );
			$machine_factor = max( 0.01, (float) ( $material->machine_time_factor ?? 1 ) );
			$density        = max( 0, (float) ( $material->density ?? 0 ) );
			$price_per_gram = max( 0, (float) ( $material->price_per_gram ?? 0 ) );
			$price_per_cm3  = max( 0, (float) ( $material->price_per_cm3 ?? 0 ) );

			$adjusted_cm3 = $effective_cm3 * $wastage_factor;
			$estimated_g  = ( $density > 0 ) ? ( $adjusted_cm3 * $density ) : 0;

			$material_from_volume = $adjusted_cm3 * $price_per_cm3;
			$material_from_weight = $estimated_g * $price_per_gram;
			$unit_material_cost   = max( $material_from_volume, $material_from_weight ) * $surface_factor;

			$default_speed = max( 0.01, (float) ( $printer->default_speed ?? 0 ) );
			$hourly_cost   = max( 0, (float) ( $printer->hourly_cost ?? 0 ) );
			$unit_hours    = ( $adjusted_cm3 / $default_speed ) * $machine_factor * ( 0.2 / $layer_height );
			$unit_printer_cost = $unit_hours * $hourly_cost;

			$service_fee   = max( 0, (float) ( $quote_settings['service_fee'] ?? 0 ) );
			$setup_fee     = max( 0, (float) ( $quote_settings['setup_fee'] ?? 0 ) );
			$profit_margin = max( 0, (float) ( $quote_settings['profit_margin'] ?? 0 ) );
			$tax_rate      = max( 0, (float) ( $quote_settings['tax_rate'] ?? 0 ) );

			$items_material_total = $unit_material_cost * $quantity;
			$items_printer_total  = $unit_printer_cost * $quantity;
			$items_subtotal       = $items_material_total + $items_printer_total;
			$order_fees           = $service_fee + $setup_fee;
			$subtotal_before_margin = $items_subtotal + $order_fees;
			$margin_amount        = $subtotal_before_margin * ( $profit_margin / 100 );
			$subtotal_with_margin = $subtotal_before_margin + $margin_amount;
			$tax_amount           = $subtotal_with_margin * ( $tax_rate / 100 );
			$final_total          = $subtotal_with_margin + $tax_amount;

			return array(
				'currency'               => (string) ( $quote_settings['currency'] ?? 'EUR' ),
				'currency_symbol'        => (string) ( $quote_settings['currency_symbol'] ?? '€' ),
				'model_count'            => (int) $metrics['model_count'],
				'model_formats'          => $metrics['formats'],
				'model_triangles'        => (int) $metrics['triangle_count'],
				'model_bounds_mm'        => $metrics['bounds_mm'],
				'model_volume_cm3'       => round( $actual_volume_cm3, 5 ),
				'effective_volume_cm3'   => round( $effective_cm3, 5 ),
				'adjusted_volume_cm3'    => round( $adjusted_cm3, 5 ),
				'estimated_weight_g'     => round( $estimated_g, 5 ),
				'unit_material_cost'     => round( $unit_material_cost, 2 ),
				'unit_printer_cost'      => round( $unit_printer_cost, 2 ),
				'items_material_total'   => round( $items_material_total, 2 ),
				'items_printer_total'    => round( $items_printer_total, 2 ),
				'service_fee'            => round( $service_fee, 2 ),
				'setup_fee'              => round( $setup_fee, 2 ),
				'profit_margin_percent'  => round( $profit_margin, 4 ),
				'profit_margin_amount'   => round( $margin_amount, 2 ),
				'tax_rate'               => round( $tax_rate, 4 ),
				'tax_amount'             => round( $tax_amount, 2 ),
				'subtotal_before_margin' => round( $subtotal_before_margin, 2 ),
				'subtotal_with_margin'   => round( $subtotal_with_margin, 2 ),
				'total_price'            => round( $final_total, 2 ),
				'quantity'               => $quantity,
				'layer_height'           => round( $layer_height, 4 ),
				'infill'                 => $infill,
				'shell_mode'             => $shell_mode,
				'scale'                  => $scale,
			);
		}

		protected static function collect_metrics( array $model_paths ) {
			$totals = array(
				'volume_mm3'    => 0.0,
				'triangle_count' => 0,
				'model_count'   => 0,
				'formats'       => array(),
				'bounds_mm'     => array( 'x' => 0.0, 'y' => 0.0, 'z' => 0.0 ),
			);

			foreach ( $model_paths as $path ) {
				$metric = self::parse_model_file( $path );
				$totals['volume_mm3']     += $metric['volume_mm3'];
				$totals['triangle_count'] += $metric['triangle_count'];
				$totals['model_count']++;
				$totals['formats'][] = strtoupper( $metric['format'] );
				$totals['bounds_mm']['x'] = max( $totals['bounds_mm']['x'], $metric['bounds_mm']['x'] );
				$totals['bounds_mm']['y'] = max( $totals['bounds_mm']['y'], $metric['bounds_mm']['y'] );
				$totals['bounds_mm']['z'] = max( $totals['bounds_mm']['z'], $metric['bounds_mm']['z'] );
			}

			$totals['formats'] = array_values( array_unique( $totals['formats'] ) );
			return $totals;
		}

		protected static function parse_model_file( $path ) {
			$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
			switch ( $ext ) {
				case 'stl':
					return self::parse_stl_file( $path );
				case 'obj':
					return self::parse_obj_file( $path );
				case '3mf':
					return self::parse_3mf_file( $path );
			}

			throw new RuntimeException( sprintf( 'Unsupported model file: %s', basename( $path ) ) );
		}

		protected static function parse_stl_file( $path ) {
			$contents = file_get_contents( $path );
			if ( false === $contents || '' === $contents ) {
				throw new RuntimeException( sprintf( 'Could not read STL file: %s', basename( $path ) ) );
			}

			if ( self::looks_like_binary_stl( $contents ) ) {
				return self::parse_binary_stl_contents( $contents );
			}

			return self::parse_ascii_stl_contents( $contents );
		}

		protected static function looks_like_binary_stl( $contents ) {
			if ( strlen( $contents ) < 84 ) {
				return false;
			}

			$face_data = unpack( 'Vfaces', substr( $contents, 80, 4 ) );
			$faces = isset( $face_data['faces'] ) ? (int) $face_data['faces'] : 0;
			$expected = 84 + ( $faces * 50 );
			if ( $expected === strlen( $contents ) ) {
				return true;
			}

			$header = strtolower( trim( substr( $contents, 0, 80 ) ) );
			return 0 !== strpos( $header, 'solid' );
		}

		protected static function parse_binary_stl_contents( $contents ) {
			$faces = unpack( 'Vfaces', substr( $contents, 80, 4 ) );
			$face_count = isset( $faces['faces'] ) ? (int) $faces['faces'] : 0;
			$offset = 84;
			$volume = 0.0;
			$bounds = self::create_bounds();

			for ( $i = 0; $i < $face_count; $i++ ) {
				$offset += 12;
				$v1 = self::unpack_float_vertex( $contents, $offset );
				$offset += 12;
				$v2 = self::unpack_float_vertex( $contents, $offset );
				$offset += 12;
				$v3 = self::unpack_float_vertex( $contents, $offset );
				$offset += 12;
				$offset += 2;

				self::expand_bounds( $bounds, $v1 );
				self::expand_bounds( $bounds, $v2 );
				self::expand_bounds( $bounds, $v3 );
				$volume += self::signed_triangle_volume( $v1, $v2, $v3 );
			}

			return array(
				'format'         => 'stl',
				'volume_mm3'     => abs( $volume ),
				'triangle_count' => $face_count,
				'bounds_mm'      => self::bounds_dimensions( $bounds ),
			);
		}

		protected static function unpack_float_vertex( $contents, $offset ) {
			$vals = unpack( 'gx/gy/gz', substr( $contents, $offset, 12 ) );
			return array(
				'x' => isset( $vals['x'] ) ? (float) $vals['x'] : 0.0,
				'y' => isset( $vals['y'] ) ? (float) $vals['y'] : 0.0,
				'z' => isset( $vals['z'] ) ? (float) $vals['z'] : 0.0,
			);
		}

		protected static function parse_ascii_stl_contents( $contents ) {
			if ( ! preg_match_all( '/vertex\s+([-+]?\d*\.?\d+(?:e[-+]?\d+)?)\s+([-+]?\d*\.?\d+(?:e[-+]?\d+)?)\s+([-+]?\d*\.?\d+(?:e[-+]?\d+)?)/i', $contents, $matches, PREG_SET_ORDER ) ) {
				throw new RuntimeException( 'This STL file could not be parsed.' );
			}

			$volume = 0.0;
			$bounds = self::create_bounds();
			$count  = count( $matches );
			if ( 0 !== $count % 3 ) {
				throw new RuntimeException( 'This STL file is incomplete.' );
			}

			for ( $i = 0; $i < $count; $i += 3 ) {
				$v1 = array( 'x' => (float) $matches[ $i ][1], 'y' => (float) $matches[ $i ][2], 'z' => (float) $matches[ $i ][3] );
				$v2 = array( 'x' => (float) $matches[ $i + 1 ][1], 'y' => (float) $matches[ $i + 1 ][2], 'z' => (float) $matches[ $i + 1 ][3] );
				$v3 = array( 'x' => (float) $matches[ $i + 2 ][1], 'y' => (float) $matches[ $i + 2 ][2], 'z' => (float) $matches[ $i + 2 ][3] );

				self::expand_bounds( $bounds, $v1 );
				self::expand_bounds( $bounds, $v2 );
				self::expand_bounds( $bounds, $v3 );
				$volume += self::signed_triangle_volume( $v1, $v2, $v3 );
			}

			return array(
				'format'         => 'stl',
				'volume_mm3'     => abs( $volume ),
				'triangle_count' => (int) ( $count / 3 ),
				'bounds_mm'      => self::bounds_dimensions( $bounds ),
			);
		}

		protected static function parse_obj_file( $path ) {
			$handle = fopen( $path, 'rb' );
			if ( ! $handle ) {
				throw new RuntimeException( sprintf( 'Could not read OBJ file: %s', basename( $path ) ) );
			}

			$vertices = array();
			$bounds   = self::create_bounds();
			$volume   = 0.0;
			$triangles = 0;

			while ( false !== ( $line = fgets( $handle ) ) ) {
				$line = trim( $line );
				if ( '' === $line || '#' === substr( $line, 0, 1 ) ) {
					continue;
				}

				$parts = preg_split( '/\s+/', $line );
				if ( empty( $parts[0] ) ) {
					continue;
				}

				if ( 'v' === $parts[0] && count( $parts ) >= 4 ) {
					$vertex = array(
						'x' => (float) $parts[1],
						'y' => (float) $parts[2],
						'z' => (float) $parts[3],
					);
					$vertices[] = $vertex;
					self::expand_bounds( $bounds, $vertex );
					continue;
				}

				if ( 'f' === $parts[0] && count( $parts ) >= 4 ) {
					$indexes = array();
					for ( $i = 1, $len = count( $parts ); $i < $len; $i++ ) {
						$index = self::parse_obj_index( $parts[ $i ], count( $vertices ) );
						if ( $index >= 0 && isset( $vertices[ $index ] ) ) {
							$indexes[] = $index;
						}
					}

					if ( count( $indexes ) < 3 ) {
						continue;
					}

					for ( $j = 1, $face_len = count( $indexes ) - 1; $j < $face_len; $j++ ) {
						$v1 = $vertices[ $indexes[0] ];
						$v2 = $vertices[ $indexes[ $j ] ];
						$v3 = $vertices[ $indexes[ $j + 1 ] ];
						$volume += self::signed_triangle_volume( $v1, $v2, $v3 );
						$triangles++;
					}
				}
			}

			fclose( $handle );

			if ( empty( $vertices ) || $triangles <= 0 ) {
				throw new RuntimeException( 'This OBJ file could not be parsed.' );
			}

			return array(
				'format'         => 'obj',
				'volume_mm3'     => abs( $volume ),
				'triangle_count' => $triangles,
				'bounds_mm'      => self::bounds_dimensions( $bounds ),
			);
		}

		protected static function parse_obj_index( $token, $total ) {
			$parts = explode( '/', (string) $token );
			$raw = (int) $parts[0];
			if ( 0 === $raw ) {
				return -1;
			}
			return $raw > 0 ? $raw - 1 : $total + $raw;
		}

		protected static function parse_3mf_file( $path ) {
			if ( ! class_exists( 'ZipArchive' ) ) {
				throw new RuntimeException( 'ZipArchive is required to parse 3MF files.' );
			}

			$zip = new ZipArchive();
			if ( true !== $zip->open( $path ) ) {
				throw new RuntimeException( sprintf( 'Could not open 3MF file: %s', basename( $path ) ) );
			}

			$model_xml = '';
			for ( $i = 0; $i < $zip->numFiles; $i++ ) {
				$name = $zip->getNameIndex( $i );
				if ( is_string( $name ) && preg_match( '/\.model$/i', $name ) ) {
					$model_xml = $zip->getFromIndex( $i );
					if ( is_string( $model_xml ) && '' !== $model_xml ) {
						break;
					}
				}
			}
			$zip->close();

			if ( '' === $model_xml ) {
				throw new RuntimeException( 'This 3MF file does not contain a readable model definition.' );
			}

			$dom = new DOMDocument();
			libxml_use_internal_errors( true );
			$loaded = $dom->loadXML( $model_xml );
			libxml_clear_errors();
			if ( ! $loaded ) {
				throw new RuntimeException( 'This 3MF file could not be parsed.' );
			}

			$xpath = new DOMXPath( $dom );
			$xpath->registerNamespace( 'm', 'http://schemas.microsoft.com/3dmanufacturing/core/2015/02' );
			$model = $xpath->query( '/m:model' )->item( 0 );
			if ( ! $model ) {
				throw new RuntimeException( 'This 3MF file has no model node.' );
			}

			$unit_factor = self::unit_scale_to_mm( $model->attributes->getNamedItem( 'unit' ) ? $model->attributes->getNamedItem( 'unit' )->nodeValue : 'millimeter' );
			$objects = array();
			foreach ( $xpath->query( '/m:model/m:resources/m:object' ) as $object_node ) {
				$object_id = (int) $object_node->attributes->getNamedItem( 'id' )->nodeValue;
				$mesh_node = $xpath->query( 'm:mesh', $object_node )->item( 0 );
				$components_node = $xpath->query( 'm:components', $object_node )->item( 0 );

				if ( $mesh_node ) {
					$vertices = array();
					foreach ( $xpath->query( 'm:vertices/m:vertex', $mesh_node ) as $vertex_node ) {
						$vertices[] = array(
							'x' => (float) $vertex_node->attributes->getNamedItem( 'x' )->nodeValue * $unit_factor,
							'y' => (float) $vertex_node->attributes->getNamedItem( 'y' )->nodeValue * $unit_factor,
							'z' => (float) $vertex_node->attributes->getNamedItem( 'z' )->nodeValue * $unit_factor,
						);
					}

					$triangles = array();
					foreach ( $xpath->query( 'm:triangles/m:triangle', $mesh_node ) as $triangle_node ) {
						$triangles[] = array(
															'v1' => (int) $triangle_node->attributes->getNamedItem( 'v1' )->nodeValue,
															'v2' => (int) $triangle_node->attributes->getNamedItem( 'v2' )->nodeValue,
															'v3' => (int) $triangle_node->attributes->getNamedItem( 'v3' )->nodeValue,
						);
					}

					$objects[ $object_id ] = array(
						'type' => 'mesh',
						'vertices' => $vertices,
						'triangles' => $triangles,
					);
				} elseif ( $components_node ) {
					$components = array();
					foreach ( $xpath->query( 'm:component', $components_node ) as $component_node ) {
						$components[] = array(
							'objectid' => (int) $component_node->attributes->getNamedItem( 'objectid' )->nodeValue,
							'transform' => self::parse_3mf_transform( $component_node->attributes->getNamedItem( 'transform' ) ? $component_node->attributes->getNamedItem( 'transform' )->nodeValue : '' ),
						);
					}
					$objects[ $object_id ] = array(
						'type' => 'components',
						'components' => $components,
					);
				}
			}

			$instances = array();
			foreach ( $xpath->query( '/m:model/m:build/m:item' ) as $item_node ) {
				$instances[] = array(
					'objectid' => (int) $item_node->attributes->getNamedItem( 'objectid' )->nodeValue,
					'transform' => self::parse_3mf_transform( $item_node->attributes->getNamedItem( 'transform' ) ? $item_node->attributes->getNamedItem( 'transform' )->nodeValue : '' ),
				);
			}

			if ( empty( $instances ) ) {
				foreach ( $objects as $object_id => $object ) {
					if ( 'mesh' === $object['type'] ) {
						$instances[] = array(
							'objectid' => (int) $object_id,
							'transform' => self::identity_matrix(),
						);
					}
				}
			}

			$bounds = self::create_bounds();
			$volume = 0.0;
			$triangles = 0;
			$visited = array();

			foreach ( $instances as $instance ) {
				$result = self::accumulate_3mf_object( $instance['objectid'], $instance['transform'], $objects, $bounds, $visited );
				$volume += $result['volume_mm3'];
				$triangles += $result['triangle_count'];
			}

			if ( $triangles <= 0 ) {
				throw new RuntimeException( 'This 3MF file does not contain printable triangles.' );
			}

			return array(
				'format'         => '3mf',
				'volume_mm3'     => abs( $volume ),
				'triangle_count' => $triangles,
				'bounds_mm'      => self::bounds_dimensions( $bounds ),
			);
		}

		protected static function accumulate_3mf_object( $object_id, array $transform, array $objects, array &$bounds, array &$visited ) {
			$key = $object_id . ':' . md5( wp_json_encode( $transform ) );
			if ( isset( $visited[ $key ] ) ) {
				return $visited[ $key ];
			}

			if ( empty( $objects[ $object_id ] ) ) {
				return array( 'volume_mm3' => 0.0, 'triangle_count' => 0 );
			}

			$object = $objects[ $object_id ];
			$result = array( 'volume_mm3' => 0.0, 'triangle_count' => 0 );

			if ( 'mesh' === $object['type'] ) {
				foreach ( $object['triangles'] as $triangle ) {
					$v1 = self::apply_3mf_transform( $object['vertices'][ $triangle['v1'] ], $transform );
					$v2 = self::apply_3mf_transform( $object['vertices'][ $triangle['v2'] ], $transform );
					$v3 = self::apply_3mf_transform( $object['vertices'][ $triangle['v3'] ], $transform );
					self::expand_bounds( $bounds, $v1 );
					self::expand_bounds( $bounds, $v2 );
					self::expand_bounds( $bounds, $v3 );
					$result['volume_mm3'] += self::signed_triangle_volume( $v1, $v2, $v3 );
					$result['triangle_count']++;
				}
			} elseif ( 'components' === $object['type'] ) {
				foreach ( $object['components'] as $component ) {
					$child_transform = self::multiply_3mf_transforms( $transform, $component['transform'] );
					$child = self::accumulate_3mf_object( $component['objectid'], $child_transform, $objects, $bounds, $visited );
					$result['volume_mm3'] += $child['volume_mm3'];
					$result['triangle_count'] += $child['triangle_count'];
				}
			}

			$visited[ $key ] = $result;
			return $result;
		}

		protected static function unit_scale_to_mm( $unit ) {
			switch ( strtolower( (string) $unit ) ) {
				case 'micron': return 0.001;
				case 'centimeter': return 10.0;
				case 'meter': return 1000.0;
				case 'inch': return 25.4;
				case 'foot': return 304.8;
				case 'millimeter':
				default:
					return 1.0;
			}
		}

		protected static function parse_3mf_transform( $raw ) {
			$raw = trim( (string) $raw );
			if ( '' === $raw ) {
				return self::identity_matrix();
			}
			$values = preg_split( '/\s+/', $raw );
			$values = array_map( 'floatval', array_slice( $values, 0, 12 ) );
			if ( count( $values ) !== 12 ) {
				return self::identity_matrix();
			}
			return array(
				array( $values[0], $values[1], $values[2], $values[3] ),
				array( $values[4], $values[5], $values[6], $values[7] ),
				array( $values[8], $values[9], $values[10], $values[11] ),
				array( 0.0, 0.0, 0.0, 1.0 ),
			);
		}

		protected static function identity_matrix() {
			return array(
				array( 1.0, 0.0, 0.0, 0.0 ),
				array( 0.0, 1.0, 0.0, 0.0 ),
				array( 0.0, 0.0, 1.0, 0.0 ),
				array( 0.0, 0.0, 0.0, 1.0 ),
			);
		}

		protected static function multiply_3mf_transforms( array $a, array $b ) {
			$result = self::identity_matrix();
			for ( $i = 0; $i < 4; $i++ ) {
				for ( $j = 0; $j < 4; $j++ ) {
					$sum = 0.0;
					for ( $k = 0; $k < 4; $k++ ) {
						$sum += $a[ $i ][ $k ] * $b[ $k ][ $j ];
					}
					$result[ $i ][ $j ] = $sum;
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
			return (
				$v1['x'] * ( ( $v2['y'] * $v3['z'] ) - ( $v2['z'] * $v3['y'] ) ) +
				$v1['y'] * ( ( $v2['z'] * $v3['x'] ) - ( $v2['x'] * $v3['z'] ) ) +
				$v1['z'] * ( ( $v2['x'] * $v3['y'] ) - ( $v2['y'] * $v3['x'] ) )
			) / 6.0;
		}

		protected static function create_bounds() {
			return array(
				'minX' => INF,
				'minY' => INF,
				'minZ' => INF,
				'maxX' => -INF,
				'maxY' => -INF,
				'maxZ' => -INF,
			);
		}

		protected static function expand_bounds( array &$bounds, array $vertex ) {
			$bounds['minX'] = min( $bounds['minX'], $vertex['x'] );
			$bounds['minY'] = min( $bounds['minY'], $vertex['y'] );
			$bounds['minZ'] = min( $bounds['minZ'], $vertex['z'] );
			$bounds['maxX'] = max( $bounds['maxX'], $vertex['x'] );
			$bounds['maxY'] = max( $bounds['maxY'], $vertex['y'] );
			$bounds['maxZ'] = max( $bounds['maxZ'], $vertex['z'] );
		}

		protected static function bounds_dimensions( array $bounds ) {
			if ( ! is_finite( $bounds['minX'] ) ) {
				return array( 'x' => 0.0, 'y' => 0.0, 'z' => 0.0 );
			}
			return array(
				'x' => round( max( 0, $bounds['maxX'] - $bounds['minX'] ), 4 ),
				'y' => round( max( 0, $bounds['maxY'] - $bounds['minY'] ), 4 ),
				'z' => round( max( 0, $bounds['maxZ'] - $bounds['minZ'] ), 4 ),
			);
		}
	}
}
