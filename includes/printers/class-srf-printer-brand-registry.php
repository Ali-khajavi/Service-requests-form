<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SRF_Printer_Brand_Registry' ) ) {
	class SRF_Printer_Brand_Registry {
		public static function get_brand_options() {
			return array(
				'' => __( 'Select printer brand', 'service-requests-form' ),
				'bambulab' => 'Bambu Lab',
				'stratasys' => 'Stratasys',
				'formlabs' => 'Formlabs',
			);
		}

		public static function get_family_options( $brand ) {
			$brand = sanitize_key( (string) $brand );
			$map = array(
				'bambulab' => array(
					'' => __( 'Select Bambu family', 'service-requests-form' ),
					'x1-series' => __( 'X1 Series', 'service-requests-form' ),
					'p1-series' => __( 'P1 Series', 'service-requests-form' ),
					'a1-series' => __( 'A1 Series', 'service-requests-form' ),
				),
				'stratasys' => array(
					'' => __( 'Select Stratasys family', 'service-requests-form' ),
					'polyjet_dental' => __( 'PolyJet / Dental', 'service-requests-form' ),
					'fdm' => __( 'FDM', 'service-requests-form' ),
				),
				'formlabs' => array(
					'' => __( 'Select Formlabs family', 'service-requests-form' ),
					'formlabs_resin' => __( 'SLA / Resin', 'service-requests-form' ),
					'formlabs_dental' => __( 'Dental / Medical', 'service-requests-form' ),
				),
			);
			return isset( $map[ $brand ] ) ? $map[ $brand ] : array( '' => __( 'Select family', 'service-requests-form' ) );
		}

		public static function get_brand_definitions() {
			static $definitions = null;
			if ( null !== $definitions ) {
				return $definitions;
			}
			$definitions = array();
			$base = __DIR__ . '/brands/';
			foreach ( array( 'bambulab', 'stratasys', 'formlabs' ) as $brand_key ) {
				$file = $base . $brand_key . '.php';
				if ( file_exists( $file ) ) {
					$data = require $file;
					if ( is_array( $data ) ) {
						$definitions[ $brand_key ] = $data;
					}
				}
			}
			return $definitions;
		}

		public static function decode_settings( $value ) {
			if ( empty( $value ) ) {
				return array();
			}
			$decoded = json_decode( (string) $value, true );
			return is_array( $decoded ) ? $decoded : array();
		}

		public static function sanitize_settings( $brand, $input ) {
			$definitions = self::get_brand_definitions();
			$brand = sanitize_key( (string) $brand );
			if ( empty( $definitions[ $brand ] ) ) {
				return '';
			}
			$clean = array();
			foreach ( $definitions[ $brand ]['sections'] as $section ) {
				foreach ( $section['fields'] as $field ) {
					$key = $field['key'];
					$raw = isset( $input[ $key ] ) ? $input[ $key ] : null;
					switch ( $field['type'] ) {
						case 'checkbox':
							$clean[ $key ] = empty( $raw ) ? 0 : 1;
							break;
						case 'select':
							$options = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
							$choice = sanitize_key( wp_unslash( (string) $raw ) );
							$clean[ $key ] = array_key_exists( $choice, $options ) ? $choice : '';
							break;
						case 'number':
							if ( '' === (string) $raw || null === $raw ) {
								$clean[ $key ] = '';
							} else {
								$number = (float) $raw;
								if ( isset( $field['min'] ) && '' !== $field['min'] && $number < (float) $field['min'] ) {
									$number = (float) $field['min'];
								}
								$clean[ $key ] = $number;
							}
							break;
						default:
							$clean[ $key ] = sanitize_text_field( wp_unslash( (string) $raw ) );
					}
				}
			}
			return wp_json_encode( $clean );
		}

		public static function render_brand_panels( $selected_brand, $settings ) {
			$definitions = self::get_brand_definitions();
			foreach ( $definitions as $brand_key => $brand ) {
				$panel_style = $selected_brand === $brand_key ? '' : 'display:none;';
				echo '<div class="srf-brand-panel" data-printer-brand-panel="' . esc_attr( $brand_key ) . '" style="' . esc_attr( $panel_style ) . '">';
				echo '<div class="srf-brand-intro"><h4>' . esc_html( $brand['label'] ) . '</h4><p>' . esc_html( $brand['description'] ) . '</p></div>';
				foreach ( $brand['sections'] as $section ) {
					echo '<div class="srf-brand-section">';
					echo '<h4 style="margin:18px 0 10px;">' . esc_html( $section['label'] ) . '</h4>';
					echo '<div class="srf-grid-cols-3">';
					foreach ( $section['fields'] as $field ) {
						$value = isset( $settings[ $field['key'] ] ) ? $settings[ $field['key'] ] : '';
						echo '<div class="srf-input-row">';
						echo '<label>' . esc_html( $field['label'] ) . '</label>';
						$name = 'brand_settings[' . esc_attr( $field['key'] ) . ']';
						$type = $field['type'];
						if ( 'checkbox' === $type ) {
							echo '<label class="srf-toggle-card"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1" ' . checked( ! empty( $value ), true, false ) . ' /><span><strong>' . esc_html__( 'Enabled', 'service-requests-form' ) . '</strong>' . ( ! empty( $field['help'] ) ? '<small>' . esc_html( $field['help'] ) . '</small>' : '' ) . '</span></label>';
						} elseif ( 'select' === $type ) {
							echo '<select name="' . esc_attr( $name ) . '">';
							foreach ( $field['options'] as $option_key => $option_label ) {
								echo '<option value="' . esc_attr( $option_key ) . '" ' . selected( (string) $value, (string) $option_key, false ) . '>' . esc_html( $option_label ) . '</option>';
							}
							echo '</select>';
						} else {
							echo '<input type="' . esc_attr( 'number' === $type ? 'number' : 'text' ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"';
							if ( 'number' === $type ) {
								if ( isset( $field['min'] ) ) { echo ' min="' . esc_attr( $field['min'] ) . '"'; }
								if ( isset( $field['step'] ) ) { echo ' step="' . esc_attr( $field['step'] ) . '"'; }
							}
							echo ' />';
						}
						echo '</div>';
					}
					echo '</div></div>';
				}
				echo '</div>';
			}
		}
	}
}
