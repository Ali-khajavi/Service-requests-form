<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/printers/class-srf-printer-brand-registry.php';

if ( ! class_exists( 'SRF_Admin_Printers' ) ) {

	class SRF_Admin_Printers {

		public static function init() {
			add_action( 'admin_post_srf_save_printer', array( __CLASS__, 'handle_save' ) );
			add_action( 'admin_post_srf_delete_printer', array( __CLASS__, 'handle_delete' ) );
		}

		protected static function get_db() {
			return class_exists( 'SRF_Quote_DB' ) ? new SRF_Quote_DB() : null;
		}

		protected static function get_page_url( $args = array() ) {
			$url = admin_url( 'admin.php?page=' . SRF_Admin_Menu::PRINTERS_SLUG );

			if ( ! empty( $args ) ) {
				$url = add_query_arg( $args, $url );
			}

			return $url;
		}

		protected static function get_notices() {
			$notices = array();

			if ( isset( $_GET['message'] ) ) {
				$message = sanitize_key( wp_unslash( $_GET['message'] ) );

				$map = array(
					'created' => array(
						'type' => 'success',
						'text' => __( 'Printer created successfully.', 'service-requests-form' ),
					),
					'updated' => array(
						'type' => 'success',
						'text' => __( 'Printer updated successfully.', 'service-requests-form' ),
					),
					'deleted' => array(
						'type' => 'success',
						'text' => __( 'Printer deleted successfully.', 'service-requests-form' ),
					),
					'failed' => array(
						'type' => 'error',
						'text' => __( 'The printer could not be saved.', 'service-requests-form' ),
					),
					'deleted_failed' => array(
						'type' => 'error',
						'text' => __( 'The printer could not be deleted.', 'service-requests-form' ),
					),
				);

				if ( isset( $map[ $message ] ) ) {
					$notices[] = $map[ $message ];
				}
			}

			if ( isset( $_GET['error'] ) ) {
				$error = sanitize_key( wp_unslash( $_GET['error'] ) );

				$error_map = array(
					'missing_name' => __( 'Printer name is required.', 'service-requests-form' ),
					'bad_nonce'    => __( 'Security check failed. Please try again.', 'service-requests-form' ),
					'invalid_id'   => __( 'Invalid printer ID.', 'service-requests-form' ),
				);

				if ( isset( $error_map[ $error ] ) ) {
					$notices[] = array(
						'type' => 'error',
						'text' => $error_map[ $error ],
					);
				}
			}

			return $notices;
		}

		protected static function get_technology_options() {
			return array(
				''           => __( 'Select technology', 'service-requests-form' ),
				'fdm'        => 'FDM / FFF',
				'sla'        => 'SLA',
				'dlp'        => 'DLP',
				'polyjet'    => 'PolyJet',
				'sls'        => 'SLS',
				'binder_jet' => 'Binder Jet',
				'metal'      => 'Metal',
				'dental'     => 'Dental',
			);
		}

		protected static function get_speed_unit_options() {
			return array(
				''       => __( 'Select unit', 'service-requests-form' ),
				'mm_s'   => 'mm/s',
				'mm3_s'  => 'mm3/s',
				'cm3_h'  => 'cm3/h',
				'parts_h'=> 'parts/h',
			);
		}

		protected static function get_pricing_model_options() {
			return array(
				''             => __( 'Select pricing model', 'service-requests-form' ),
				'volume_based' => __( 'Volume based', 'service-requests-form' ),
				'time_based'   => __( 'Time based', 'service-requests-form' ),
				'hybrid'       => __( 'Hybrid', 'service-requests-form' ),
			);
		}

		protected static function get_polyjet_build_style_options() {
			return array(
				''             => __( 'Select build style', 'service-requests-form' ),
				'high_speed'   => __( 'High speed', 'service-requests-form' ),
				'high_quality' => __( 'High quality', 'service-requests-form' ),
				'balanced'     => __( 'Balanced', 'service-requests-form' ),
			);
		}

		protected static function sanitize_status( $value ) {
			$value = sanitize_key( wp_unslash( (string) $value ) );
			return in_array( $value, array( 'active', 'inactive' ), true ) ? $value : 'inactive';
		}

		protected static function sanitize_bool_flag( $value ) {
			return empty( $value ) ? 0 : 1;
		}

		protected static function sanitize_supported_materials( $value ) {
			$ids = array();

			if ( is_array( $value ) ) {
				foreach ( $value as $item ) {
					$id = absint( $item );
					if ( $id > 0 ) {
						$ids[] = $id;
					}
				}
			}

			$ids = array_values( array_unique( $ids ) );

			return wp_json_encode( $ids );
		}

		protected static function decode_supported_materials( $value ) {
			if ( empty( $value ) ) {
				return array();
			}

			$decoded = json_decode( (string) $value, true );

			if ( ! is_array( $decoded ) ) {
				return array();
			}

			return array_values(
				array_filter(
					array_map( 'absint', $decoded )
				)
			);
		}


		protected static function sanitize_id_list( $value ) {
			$ids = array();
			if ( is_array( $value ) ) {
				foreach ( $value as $item ) {
					$id = absint( $item );
					if ( $id > 0 ) { $ids[] = $id; }
				}
			}
			return wp_json_encode( array_values( array_unique( $ids ) ) );
		}

		protected static function decode_id_list( $value ) {
			if ( empty( $value ) ) { return array(); }
			$decoded = json_decode( (string) $value, true );
			if ( ! is_array( $decoded ) ) { return array(); }
			return array_values( array_filter( array_map( 'absint', $decoded ) ) );
		}

		protected static function get_service_profile_options() {
			$options = array();

			if ( class_exists( 'SR_Service_Data' ) && method_exists( 'SR_Service_Data', 'get_services_for_dropdown' ) ) {
				$services = SR_Service_Data::get_services_for_dropdown();
				if ( is_array( $services ) ) {
					foreach ( $services as $service ) {
						$service_id = isset( $service['id'] ) ? absint( $service['id'] ) : 0;
						$title      = isset( $service['title'] ) ? sanitize_text_field( (string) $service['title'] ) : '';
						if ( $service_id > 0 && '' !== $title ) {
							$options[ $service_id ] = $title;
						}
					}
				}
			}

			if ( empty( $options ) ) {
				$posts = get_posts(
					array(
						'post_type'      => 'sr_service',
						'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
						'posts_per_page' => -1,
						'orderby'        => 'title',
						'order'          => 'ASC',
						'suppress_filters' => false,
					)
				);
				foreach ( $posts as $post ) {
					$options[ (int) $post->ID ] = get_the_title( $post );
				}
			}

			return $options;
		}

		protected static function sanitize_text_list( $value ) {
			if ( is_array( $value ) ) {
				$items = $value;
			} else {
				$normalized = str_replace( array( "\r\n", "\r" ), "\n", (string) $value );
				$normalized = str_replace( ',', "\n", $normalized );
				$items      = explode( "\n", $normalized );
			}

			$clean = array();
			foreach ( $items as $item ) {
				$item = sanitize_text_field( wp_unslash( (string) $item ) );
				if ( '' !== $item ) {
					$clean[] = $item;
				}
			}

			$clean = array_values( array_unique( $clean ) );

			return wp_json_encode( $clean );
		}

		protected static function decode_text_list( $value ) {
			if ( empty( $value ) ) {
				return array();
			}

			$decoded = json_decode( (string) $value, true );
			if ( ! is_array( $decoded ) ) {
				return array();
			}

			return array_values(
				array_filter(
					array_map( 'sanitize_text_field', $decoded ),
					static function( $item ) {
						return '' !== $item;
					}
				)
			);
		}

		protected static function format_text_list_for_textarea( $value ) {
			$list = self::decode_text_list( $value );
			return implode( "\n", $list );
		}

		protected static function sanitize_json_text( $value ) {
			$value = trim( (string) wp_unslash( $value ) );
			if ( '' === $value ) {
				return '';
			}

			$decoded = json_decode( $value, true );
			if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
				return '';
			}

			return wp_json_encode( $decoded );
		}

		protected static function pretty_json_text( $value ) {
			if ( empty( $value ) ) {
				return '';
			}

			$decoded = json_decode( (string) $value, true );
			if ( ! is_array( $decoded ) ) {
				return '';
			}

			return wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		}

		protected static function sanitize_decimal_or_null( $value, $min = 0 ) {
			if ( '' === (string) $value || null === $value ) {
				return null;
			}

			$number = (float) $value;
			if ( $number < $min ) {
				$number = $min;
			}

			return $number;
		}

		protected static function sanitize_int_or_null( $value, $min = 0 ) {
			if ( '' === (string) $value || null === $value ) {
				return null;
			}

			$number = (int) $value;
			if ( $number < $min ) {
				$number = $min;
			}

			return $number;
		}

		protected static function sanitize_choice( $value, $choices ) {
			$value = sanitize_key( wp_unslash( (string) $value ) );
			return array_key_exists( $value, $choices ) ? $value : '';
		}

		protected static function sanitize_payload( $input ) {
			$technology_choices = self::get_technology_options();
			$speed_units        = self::get_speed_unit_options();
			$pricing_models     = self::get_pricing_model_options();
			$polyjet_build_styles = self::get_polyjet_build_style_options();

			$brand_key      = sanitize_key( wp_unslash( (string) ( $input['brand'] ?? '' ) ) );
			$brand_settings = class_exists( 'SRF_Printer_Brand_Registry' ) ? SRF_Printer_Brand_Registry::sanitize_settings( $brand_key, $input['brand_settings'] ?? array() ) : '';

			return array(
				'name'                           => sanitize_text_field( wp_unslash( $input['name'] ?? '' ) ),
				'brand'                          => sanitize_text_field( wp_unslash( $input['brand'] ?? '' ) ),
				'printer_family'                 => sanitize_text_field( wp_unslash( $input['printer_family'] ?? '' ) ),
				'brand_settings_json'            => $brand_settings,
				'model'                          => sanitize_text_field( wp_unslash( $input['model'] ?? '' ) ),
				'description'                    => sanitize_textarea_field( wp_unslash( $input['description'] ?? '' ) ),
				'technology'                     => self::sanitize_choice( $input['technology'] ?? '', $technology_choices ),
				'build_volume_x'                 => self::sanitize_decimal_or_null( $input['build_volume_x'] ?? null, 0 ),
				'build_volume_y'                 => self::sanitize_decimal_or_null( $input['build_volume_y'] ?? null, 0 ),
				'build_volume_z'                 => self::sanitize_decimal_or_null( $input['build_volume_z'] ?? null, 0 ),
				'xy_resolution'                  => self::sanitize_decimal_or_null( $input['xy_resolution'] ?? null, 0 ),
				'nozzle_size'                    => self::sanitize_decimal_or_null( $input['nozzle_size'] ?? null, 0 ),
				'min_feature_size'               => self::sanitize_decimal_or_null( $input['min_feature_size'] ?? null, 0 ),
				'max_part_weight'                => self::sanitize_decimal_or_null( $input['max_part_weight'] ?? null, 0 ),
				'default_speed'                  => self::sanitize_decimal_or_null( $input['default_speed'] ?? null, 0 ),
				'speed_unit'                     => self::sanitize_choice( $input['speed_unit'] ?? '', $speed_units ),
				'hourly_cost'                    => self::sanitize_decimal_or_null( $input['hourly_cost'] ?? null, 0 ),
				'machine_efficiency_factor'      => self::sanitize_decimal_or_null( $input['machine_efficiency_factor'] ?? 1, 0 ),
				'setup_time_minutes'             => self::sanitize_decimal_or_null( $input['setup_time_minutes'] ?? null, 0 ),
				'warmup_time_minutes'            => self::sanitize_decimal_or_null( $input['warmup_time_minutes'] ?? null, 0 ),
				'postprocess_time_minutes'       => self::sanitize_decimal_or_null( $input['postprocess_time_minutes'] ?? null, 0 ),
				'min_layer_height'               => self::sanitize_decimal_or_null( $input['min_layer_height'] ?? null, 0 ),
				'max_layer_height'               => self::sanitize_decimal_or_null( $input['max_layer_height'] ?? null, 0 ),
				'supported_materials'            => self::sanitize_supported_materials( $input['supported_materials'] ?? array() ),
				'default_material_id'            => self::sanitize_int_or_null( $input['default_material_id'] ?? null, 0 ),
				'supported_service_profile_ids'  => self::sanitize_id_list( $input['supported_service_profile_ids'] ?? array() ),
				'default_service_profile_id'     => self::sanitize_int_or_null( $input['default_service_profile_id'] ?? null, 0 ),
				'supported_application_profiles' => self::sanitize_text_list( $input['supported_application_profiles'] ?? '' ),
				'supported_finishes'             => self::sanitize_text_list( $input['supported_finishes'] ?? '' ),
				'supported_support_materials'    => self::sanitize_text_list( $input['supported_support_materials'] ?? '' ),
				'default_support_material'       => sanitize_text_field( wp_unslash( $input['default_support_material'] ?? '' ) ),
				'support_material_map'           => self::sanitize_json_text( $input['support_material_map'] ?? '' ),
				'supported_color_modes'          => self::sanitize_text_list( $input['supported_color_modes'] ?? '' ),
				'pricing_model'                  => self::sanitize_choice( $input['pricing_model'] ?? '', $pricing_models ),
				'minimum_job_price'              => self::sanitize_decimal_or_null( $input['minimum_job_price'] ?? null, 0 ),
				'minimum_material_charge'        => self::sanitize_decimal_or_null( $input['minimum_material_charge'] ?? null, 0 ),
				'margin_override'                => self::sanitize_decimal_or_null( $input['margin_override'] ?? null, 0 ),
				'enable_infill'                  => self::sanitize_bool_flag( $input['enable_infill'] ?? 0 ),
				'enable_supports'                => self::sanitize_bool_flag( $input['enable_supports'] ?? 0 ),
				'enable_structure'               => self::sanitize_bool_flag( $input['enable_structure'] ?? 0 ),
				'enable_application_profile'     => self::sanitize_bool_flag( $input['enable_application_profile'] ?? 0 ),
				'enable_finish_selection'        => self::sanitize_bool_flag( $input['enable_finish_selection'] ?? 0 ),
				'enable_color_selection'         => self::sanitize_bool_flag( $input['enable_color_selection'] ?? 0 ),
				'enable_scale'                   => self::sanitize_bool_flag( $input['enable_scale'] ?? 0 ),
				'enable_quantity'                => self::sanitize_bool_flag( $input['enable_quantity'] ?? 0 ),
				'enable_advanced_settings'       => self::sanitize_bool_flag( $input['enable_advanced_settings'] ?? 0 ),
				'fdm_infill_min'                 => self::sanitize_decimal_or_null( $input['fdm_infill_min'] ?? null, 0 ),
				'fdm_infill_max'                 => self::sanitize_decimal_or_null( $input['fdm_infill_max'] ?? null, 0 ),
				'fdm_support_factor'             => self::sanitize_decimal_or_null( $input['fdm_support_factor'] ?? null, 0 ),
				'fdm_default_line_width'         => self::sanitize_decimal_or_null( $input['fdm_default_line_width'] ?? null, 0 ),
				'fdm_default_print_speed'        => self::sanitize_decimal_or_null( $input['fdm_default_print_speed'] ?? null, 0 ),
				'fdm_default_travel_speed'       => self::sanitize_decimal_or_null( $input['fdm_default_travel_speed'] ?? null, 0 ),
				'fdm_max_print_speed'            => self::sanitize_decimal_or_null( $input['fdm_max_print_speed'] ?? null, 0 ),
				'fdm_default_wall_count'         => self::sanitize_int_or_null( $input['fdm_default_wall_count'] ?? null, 0 ),
				'fdm_default_top_layers'         => self::sanitize_int_or_null( $input['fdm_default_top_layers'] ?? null, 0 ),
				'fdm_default_bottom_layers'      => self::sanitize_int_or_null( $input['fdm_default_bottom_layers'] ?? null, 0 ),
				'fdm_default_infill_pattern'     => sanitize_text_field( wp_unslash( $input['fdm_default_infill_pattern'] ?? '' ) ),
				'fdm_supported_infill_patterns'  => self::sanitize_text_list( $input['fdm_supported_infill_patterns'] ?? '' ),
				'fdm_support_overhang_angle'     => self::sanitize_decimal_or_null( $input['fdm_support_overhang_angle'] ?? null, 0 ),
				'fdm_support_interface_factor'   => self::sanitize_decimal_or_null( $input['fdm_support_interface_factor'] ?? null, 0 ),
				'fdm_cooling_factor'             => self::sanitize_decimal_or_null( $input['fdm_cooling_factor'] ?? null, 0 ),
				'fdm_retraction_factor'          => self::sanitize_decimal_or_null( $input['fdm_retraction_factor'] ?? null, 0 ),
				'fdm_bed_adhesion_type'          => sanitize_text_field( wp_unslash( $input['fdm_bed_adhesion_type'] ?? '' ) ),
				'fdm_bridge_optimization'        => self::sanitize_bool_flag( $input['fdm_bridge_optimization'] ?? 0 ),
				'resin_curing_factor'            => self::sanitize_decimal_or_null( $input['resin_curing_factor'] ?? null, 0 ),
				'resin_shrinkage_percent'        => self::sanitize_decimal_or_null( $input['resin_shrinkage_percent'] ?? null, 0 ),
				'resin_default_wall_thickness'   => self::sanitize_decimal_or_null( $input['resin_default_wall_thickness'] ?? null, 0 ),
				'resin_support_density_factor'   => self::sanitize_decimal_or_null( $input['resin_support_density_factor'] ?? null, 0 ),
				'resin_support_removal_factor'   => self::sanitize_decimal_or_null( $input['resin_support_removal_factor'] ?? null, 0 ),
				'resin_default_exposure_time'    => self::sanitize_decimal_or_null( $input['resin_default_exposure_time'] ?? null, 0 ),
				'resin_bottom_exposure_time'     => self::sanitize_decimal_or_null( $input['resin_bottom_exposure_time'] ?? null, 0 ),
				'resin_lift_speed'               => self::sanitize_decimal_or_null( $input['resin_lift_speed'] ?? null, 0 ),
				'resin_lift_distance'            => self::sanitize_decimal_or_null( $input['resin_lift_distance'] ?? null, 0 ),
				'resin_orientation_factor'       => self::sanitize_decimal_or_null( $input['resin_orientation_factor'] ?? null, 0 ),
				'resin_support_touchpoint_factor'=> self::sanitize_decimal_or_null( $input['resin_support_touchpoint_factor'] ?? null, 0 ),
				'resin_support_tip_size'         => self::sanitize_decimal_or_null( $input['resin_support_tip_size'] ?? null, 0 ),
				'resin_hollow_factor'            => self::sanitize_decimal_or_null( $input['resin_hollow_factor'] ?? null, 0 ),
				'resin_drain_hole_factor'        => self::sanitize_decimal_or_null( $input['resin_drain_hole_factor'] ?? null, 0 ),
				'resin_drain_hole_min_diameter'  => self::sanitize_decimal_or_null( $input['resin_drain_hole_min_diameter'] ?? null, 0 ),
				'resin_shrinkage_compensation'   => self::sanitize_decimal_or_null( $input['resin_shrinkage_compensation'] ?? null, 0 ),
				'resin_cure_compensation_factor' => self::sanitize_decimal_or_null( $input['resin_cure_compensation_factor'] ?? null, 0 ),
				'resin_default_shell_thickness'  => self::sanitize_decimal_or_null( $input['resin_default_shell_thickness'] ?? null, 0 ),
				'resin_post_cure_factor'         => self::sanitize_decimal_or_null( $input['resin_post_cure_factor'] ?? null, 0 ),
				'resin_cleaning_difficulty_factor' => self::sanitize_decimal_or_null( $input['resin_cleaning_difficulty_factor'] ?? null, 0 ),
				'polyjet_profile_cost_factor'    => self::sanitize_decimal_or_null( $input['polyjet_profile_cost_factor'] ?? null, 0 ),
				'polyjet_profile_time_factor'    => self::sanitize_decimal_or_null( $input['polyjet_profile_time_factor'] ?? null, 0 ),
				'polyjet_finish_cost_factor'     => self::sanitize_decimal_or_null( $input['polyjet_finish_cost_factor'] ?? null, 0 ),
				'polyjet_finish_time_factor'     => self::sanitize_decimal_or_null( $input['polyjet_finish_time_factor'] ?? null, 0 ),
				'polyjet_support_material_factor' => self::sanitize_decimal_or_null( $input['polyjet_support_material_factor'] ?? null, 0 ),
				'polyjet_tray_packing_factor'    => self::sanitize_decimal_or_null( $input['polyjet_tray_packing_factor'] ?? null, 0 ),
				'polyjet_surface_quality_factor' => self::sanitize_decimal_or_null( $input['polyjet_surface_quality_factor'] ?? null, 0 ),
				'polyjet_postprocess_factor'     => self::sanitize_decimal_or_null( $input['polyjet_postprocess_factor'] ?? null, 0 ),
				'polyjet_failure_factor'         => self::sanitize_decimal_or_null( $input['polyjet_failure_factor'] ?? null, 0 ),
				'polyjet_color_mixing_factor'    => self::sanitize_decimal_or_null( $input['polyjet_color_mixing_factor'] ?? null, 0 ),
				'polyjet_material_switching_factor' => self::sanitize_decimal_or_null( $input['polyjet_material_switching_factor'] ?? null, 0 ),
				'polyjet_cleaning_factor'        => self::sanitize_decimal_or_null( $input['polyjet_cleaning_factor'] ?? null, 0 ),
				'polyjet_application_profile_override_factor' => self::sanitize_decimal_or_null( $input['polyjet_application_profile_override_factor'] ?? null, 0 ),
				'polyjet_support_cleanup_difficulty_factor' => self::sanitize_decimal_or_null( $input['polyjet_support_cleanup_difficulty_factor'] ?? null, 0 ),
				'polyjet_layer_resolution_microns' => self::sanitize_decimal_or_null( $input['polyjet_layer_resolution_microns'] ?? null, 0 ),
				'polyjet_build_style'            => self::sanitize_choice( $input['polyjet_build_style'] ?? '', $polyjet_build_styles ),
				'polyjet_voxel_control_factor'   => self::sanitize_decimal_or_null( $input['polyjet_voxel_control_factor'] ?? null, 0 ),
				'multi_material_enabled'         => self::sanitize_bool_flag( $input['multi_material_enabled'] ?? 0 ),
				'color_printing_enabled'         => self::sanitize_bool_flag( $input['color_printing_enabled'] ?? 0 ),
				'supports_hollow_models'         => self::sanitize_bool_flag( $input['supports_hollow_models'] ?? 0 ),
				'supports_full_color_workflow'   => self::sanitize_bool_flag( $input['supports_full_color_workflow'] ?? 0 ),
				'supports_biocompatible_workflow' => self::sanitize_bool_flag( $input['supports_biocompatible_workflow'] ?? 0 ),
				'supports_transparent_materials' => self::sanitize_bool_flag( $input['supports_transparent_materials'] ?? 0 ),
				'supports_flexible_materials'    => self::sanitize_bool_flag( $input['supports_flexible_materials'] ?? 0 ),
				'max_materials_per_job'          => self::sanitize_int_or_null( $input['max_materials_per_job'] ?? null, 0 ),
				'min_wall_thickness'             => self::sanitize_decimal_or_null( $input['min_wall_thickness'] ?? null, 0 ),
				'max_quantity_per_job'           => self::sanitize_int_or_null( $input['max_quantity_per_job'] ?? null, 0 ),
				'allowed_file_formats'           => self::sanitize_text_list( $input['allowed_file_formats'] ?? '' ),
				'status'                         => self::sanitize_status( $input['status'] ?? 'inactive' ),
			);
		}

		protected static function render_checkbox( $name, $label, $checked, $help = '' ) {
			?>
			<label class="srf-toggle-card">
				<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( ! empty( $checked ) ); ?> />
				<span>
					<strong><?php echo esc_html( $label ); ?></strong>
					<?php if ( $help ) : ?>
						<small><?php echo esc_html( $help ); ?></small>
					<?php endif; ?>
				</span>
			</label>
			<?php
		}

		protected static function render_page_title( $edit_printer ) {
			echo esc_html(
				$edit_printer
					? __( 'Edit Printer', 'service-requests-form' )
					: __( 'Add Printer', 'service-requests-form' )
			);
		}

		public static function handle_save() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to do that.', 'service-requests-form' ) );
			}

			check_admin_referer( 'srf_save_printer', 'srf_printer_nonce' );

			$db = self::get_db();
			if ( ! $db ) {
				wp_safe_redirect( self::get_page_url( array( 'message' => 'failed' ) ) );
				exit;
			}

			$printer_id = isset( $_POST['printer_id'] ) ? absint( $_POST['printer_id'] ) : 0;
			$data       = self::sanitize_payload( $_POST );

			if ( '' === $data['name'] ) {
				wp_safe_redirect( self::get_page_url( array(
					'action' => $printer_id ? 'edit' : null,
					'id'     => $printer_id ? $printer_id : null,
					'error'  => 'missing_name',
				) ) );
				exit;
			}

			if ( $printer_id > 0 ) {
				$result = $db->update_printer( $printer_id, $data );
				wp_safe_redirect( self::get_page_url( array(
					'message' => $result ? 'updated' : 'failed',
				) ) );
				exit;
			}

			$insert_id = $db->insert_printer( $data );

			wp_safe_redirect( self::get_page_url( array(
				'message' => $insert_id ? 'created' : 'failed',
			) ) );
			exit;
		}

		public static function handle_delete() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to do that.', 'service-requests-form' ) );
			}

			check_admin_referer( 'srf_delete_printer', 'srf_printer_delete_nonce' );

			$printer_id = isset( $_POST['printer_id'] ) ? absint( $_POST['printer_id'] ) : 0;
			if ( $printer_id <= 0 ) {
				wp_safe_redirect( self::get_page_url( array( 'error' => 'invalid_id' ) ) );
				exit;
			}

			$db = self::get_db();
			if ( ! $db ) {
				wp_safe_redirect( self::get_page_url( array( 'message' => 'deleted_failed' ) ) );
				exit;
			}

			$deleted = $db->delete_printer( $printer_id );

			wp_safe_redirect( self::get_page_url( array(
				'message' => $deleted ? 'deleted' : 'deleted_failed',
			) ) );
			exit;
		}

		public static function render_page() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$db = self::get_db();
			if ( ! $db ) {
				echo '<div class="wrap"><h1>' . esc_html__( 'Printers', 'service-requests-form' ) . '</h1><div class="notice notice-error"><p>' . esc_html__( 'Quote database is not available.', 'service-requests-form' ) . '</p></div></div>';
				return;
			}

			$printers      = $db->get_printers();
			$material_rows = $db->get_materials();
			$material_map  = array();

			if ( is_array( $material_rows ) ) {
				foreach ( $material_rows as $material ) {
					$material_map[ (int) $material->id ] = $material->name;
				}
			}

			$edit_id = isset( $_GET['action'], $_GET['id'] ) && 'edit' === sanitize_key( wp_unslash( $_GET['action'] ) )
				? absint( $_GET['id'] )
				: 0;

			$edit_printer = null;
			if ( $edit_id > 0 ) {
				$edit_printer = $db->get_printer( $edit_id );
			}

			$selected_materials        = $edit_printer ? self::decode_supported_materials( $edit_printer->supported_materials ) : array();
			$service_map                = self::get_service_profile_options();
			$selected_service_profiles  = $edit_printer ? self::decode_id_list( $edit_printer->supported_service_profile_ids ?? '' ) : array();
			$page_url                   = self::get_page_url();
			$notices                    = self::get_notices();
			$technology_options         = self::get_technology_options();
			$speed_unit_options         = self::get_speed_unit_options();
			$pricing_models             = self::get_pricing_model_options();
			$brand_options              = class_exists( 'SRF_Printer_Brand_Registry' ) ? SRF_Printer_Brand_Registry::get_brand_options() : array();
			$brand_settings             = class_exists( 'SRF_Printer_Brand_Registry' ) && $edit_printer ? SRF_Printer_Brand_Registry::decode_settings( $edit_printer->brand_settings_json ?? '' ) : array();
			?>
			<div class="wrap srf-printers-page">
				<style>
					.srf-admin-shell{max-width:1380px}
					.srf-admin-header{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin:8px 0 18px}
					.srf-admin-header h1{margin:0 0 6px}
					.srf-admin-description{margin:0;color:#667085}
					.srf-admin-card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:20px;margin-bottom:20px;box-shadow:0 1px 2px rgba(16,24,40,.04)}
					.srf-admin-card h2{margin:0 0 18px}
					.srf-admin-card h3{margin:0 0 14px;font-size:15px}
					.srf-grid-cols{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
					.srf-grid-cols-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}
					.srf-input-row label{display:block;font-weight:600;color:#344054;margin-bottom:6px}
					.srf-input-row input[type="text"],.srf-input-row input[type="number"],.srf-input-row textarea,.srf-input-row select{width:100%;max-width:none}
					.srf-input-row small{display:block;margin-top:6px;color:#667085}
					.srf-admin-actions{margin-top:18px;display:flex;gap:10px;flex-wrap:wrap}
					.srf-admin-table{width:100%;border-collapse:collapse}
					.srf-admin-table th,.srf-admin-table td{padding:14px 12px;border-bottom:1px solid #eaecf0;text-align:left;vertical-align:top}
					.srf-admin-table th{background:#f9fafb;color:#667085;font-size:12px;text-transform:uppercase;letter-spacing:.04em}
					.srf-admin-table tbody tr:last-child td{border-bottom:none}
					.srf-status-pill{display:inline-flex;align-items:center;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:600}
					.srf-status-pill--active{background:#ecfdf3;color:#027a48}
					.srf-status-pill--inactive{background:#f2f4f7;color:#344054}
					.srf-row-actions{display:flex;gap:8px;flex-wrap:wrap}
					.srf-checkbox-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:6px}
					.srf-checkbox-item{display:flex;align-items:center;gap:8px;background:#f9fafb;border:1px solid #eaecf0;border-radius:10px;padding:8px 10px}
					.srf-toggle-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
					.srf-toggle-card{display:flex;gap:10px;align-items:flex-start;background:#f9fafb;border:1px solid #eaecf0;border-radius:12px;padding:12px}
					.srf-toggle-card input{margin-top:2px}
					.srf-toggle-card strong{display:block;color:#111827}
					.srf-toggle-card small{display:block;margin-top:4px;color:#667085;line-height:1.4}
					.srf-form-section{margin-top:22px;padding-top:18px;border-top:1px solid #f0f2f5}
					.srf-kv{display:grid;grid-template-columns:140px 1fr;gap:8px 12px}
					.srf-kv dt{color:#667085}
					.srf-kv dd{margin:0}
					.srf-brand-panel{background:#f9fafb;border:1px solid #eaecf0;border-radius:14px;padding:16px;margin-top:14px}
					.srf-brand-section{margin-top:16px;padding-top:16px;border-top:1px solid #eceff3}
					@media (max-width: 1100px){.srf-toggle-grid,.srf-checkbox-grid,.srf-grid-cols-3{grid-template-columns:repeat(2,minmax(0,1fr))}}
					@media (max-width: 900px){.srf-grid-cols,.srf-grid-cols-3,.srf-toggle-grid,.srf-checkbox-grid{grid-template-columns:1fr}}
				</style>

				<div class="srf-admin-shell">
					<div class="srf-admin-header">
						<div>
							<h1><?php esc_html_e( 'Printers', 'service-requests-form' ); ?></h1>
							<p class="srf-admin-description"><?php esc_html_e( 'Create a full printer definition that controls capabilities, validation, UI fields, and quote behavior for every 3D printer technology.', 'service-requests-form' ); ?></p>
						</div>

						<?php if ( $edit_printer ) : ?>
							<a class="button" href="<?php echo esc_url( $page_url ); ?>"><?php esc_html_e( 'Cancel Editing', 'service-requests-form' ); ?></a>
						<?php endif; ?>
					</div>

					<?php foreach ( $notices as $notice ) : ?>
						<div class="notice notice-<?php echo esc_attr( 'success' === $notice['type'] ? 'success' : 'error' ); ?> is-dismissible"><p><?php echo esc_html( $notice['text'] ); ?></p></div>
					<?php endforeach; ?>

					<div class="srf-admin-card">
						<h2><?php self::render_page_title( $edit_printer ); ?></h2>

						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="srf_save_printer" />
							<input type="hidden" name="printer_id" value="<?php echo esc_attr( $edit_printer ? (int) $edit_printer->id : 0 ); ?>" />
							<?php wp_nonce_field( 'srf_save_printer', 'srf_printer_nonce' ); ?>

							<div class="srf-form-section" style="margin-top:0;padding-top:0;border-top:none;">
								<h3><?php esc_html_e( 'General information', 'service-requests-form' ); ?></h3>
								<div class="srf-grid-cols">
									<div class="srf-input-row"><label for="srf_printer_name"><?php esc_html_e( 'Name', 'service-requests-form' ); ?></label><input type="text" id="srf_printer_name" name="name" value="<?php echo esc_attr( $edit_printer->name ?? '' ); ?>" required /></div>
									<div class="srf-input-row"><label for="srf_printer_brand"><?php esc_html_e( 'Brand', 'service-requests-form' ); ?></label><select id="srf_printer_brand" name="brand"><?php foreach ( $brand_options as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $edit_printer->brand ?? '', $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select><small><?php esc_html_e( 'Brands are loaded step by step from the printers folder. Start with Stratasys or Formlabs, then extend later.', 'service-requests-form' ); ?></small></div>
									<div class="srf-input-row"><label for="srf_printer_family"><?php esc_html_e( 'Printer family', 'service-requests-form' ); ?></label><input type="text" id="srf_printer_family" name="printer_family" value="<?php echo esc_attr( $edit_printer->printer_family ?? '' ); ?>" /><small><?php esc_html_e( 'Example: J5 DentaJet, J850 Prime, Form 3B+, Form 4B.', 'service-requests-form' ); ?></small></div><div class="srf-input-row"><label for="srf_printer_model"><?php esc_html_e( 'Model', 'service-requests-form' ); ?></label><input type="text" id="srf_printer_model" name="model" value="<?php echo esc_attr( $edit_printer->model ?? '' ); ?>" /></div>
									<div class="srf-input-row"><label for="srf_printer_technology"><?php esc_html_e( 'Technology', 'service-requests-form' ); ?></label><select id="srf_printer_technology" name="technology"><?php foreach ( $technology_options as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $edit_printer->technology ?? '', $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></div>
									<div class="srf-input-row"><label for="srf_printer_status"><?php esc_html_e( 'Status', 'service-requests-form' ); ?></label><select id="srf_printer_status" name="status"><option value="active" <?php selected( $edit_printer->status ?? 'active', 'active' ); ?>><?php esc_html_e( 'Active', 'service-requests-form' ); ?></option><option value="inactive" <?php selected( $edit_printer->status ?? '', 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'service-requests-form' ); ?></option></select></div>
									<div class="srf-input-row"><label for="srf_printer_description"><?php esc_html_e( 'Description', 'service-requests-form' ); ?></label><textarea id="srf_printer_description" name="description" rows="4"><?php echo esc_textarea( $edit_printer->description ?? '' ); ?></textarea></div>
								</div>
							</div>

							<div class="srf-form-section">
								<h3><?php esc_html_e( 'Brand-specific printer profile', 'service-requests-form' ); ?></h3>
								<p style="margin-top:0;color:#667085;"><?php esc_html_e( 'After the admin chooses a brand, the plugin loads that brand profile from the printers folder. Stratasys is separated first, Formlabs is added next, and more brands can be plugged in later without growing one giant file.', 'service-requests-form' ); ?></p>
								<?php if ( class_exists( 'SRF_Printer_Brand_Registry' ) ) : ?>
									<?php SRF_Printer_Brand_Registry::render_brand_panels( $edit_printer->brand ?? '', $brand_settings ); ?>
								<?php endif; ?>
							</div>
							<div class="srf-form-section">
								<h3><?php esc_html_e( 'Build and hardware limits', 'service-requests-form' ); ?></h3>
								<div class="srf-grid-cols-3">
									<div class="srf-input-row"><label>Build volume X (mm)</label><input type="number" min="0" step="0.01" name="build_volume_x" value="<?php echo esc_attr( $edit_printer->build_volume_x ?? '' ); ?>" /></div>
									<div class="srf-input-row"><label>Build volume Y (mm)</label><input type="number" min="0" step="0.01" name="build_volume_y" value="<?php echo esc_attr( $edit_printer->build_volume_y ?? '' ); ?>" /></div>
									<div class="srf-input-row"><label>Build volume Z (mm)</label><input type="number" min="0" step="0.01" name="build_volume_z" value="<?php echo esc_attr( $edit_printer->build_volume_z ?? '' ); ?>" /></div>
									<div class="srf-input-row"><label>XY resolution (mm)</label><input type="number" min="0" step="0.0001" name="xy_resolution" value="<?php echo esc_attr( $edit_printer->xy_resolution ?? '' ); ?>" /></div>
									<div class="srf-input-row"><label>Nozzle size (mm)</label><input type="number" min="0" step="0.0001" name="nozzle_size" value="<?php echo esc_attr( $edit_printer->nozzle_size ?? '' ); ?>" /></div>
									<div class="srf-input-row"><label>Min feature size (mm)</label><input type="number" min="0" step="0.0001" name="min_feature_size" value="<?php echo esc_attr( $edit_printer->min_feature_size ?? '' ); ?>" /></div>
									<div class="srf-input-row"><label>Min layer height (mm)</label><input type="number" min="0" step="0.0001" name="min_layer_height" value="<?php echo esc_attr( $edit_printer->min_layer_height ?? '' ); ?>" /></div>
									<div class="srf-input-row"><label>Max layer height (mm)</label><input type="number" min="0" step="0.0001" name="max_layer_height" value="<?php echo esc_attr( $edit_printer->max_layer_height ?? '' ); ?>" /></div>
									<div class="srf-input-row"><label>Max part weight (g)</label><input type="number" min="0" step="0.01" name="max_part_weight" value="<?php echo esc_attr( $edit_printer->max_part_weight ?? '' ); ?>" /></div>
								</div>
							</div>

							<div class="srf-form-section">
								<h3><?php esc_html_e( 'Cost and time model', 'service-requests-form' ); ?></h3>
								<div class="srf-grid-cols-3">
									<div class="srf-input-row"><label>Hourly cost</label><input type="number" min="0" step="0.01" name="hourly_cost" value="<?php echo esc_attr( $edit_printer->hourly_cost ?? '' ); ?>" /></div>
									<div class="srf-input-row"><label>Default speed</label><input type="number" min="0" step="0.01" name="default_speed" value="<?php echo esc_attr( $edit_printer->default_speed ?? '' ); ?>" /></div>
									<div class="srf-input-row"><label>Speed unit</label><select name="speed_unit"><?php foreach ( $speed_unit_options as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $edit_printer->speed_unit ?? '', $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></div>
									<div class="srf-input-row"><label>Machine efficiency factor</label><input type="number" min="0" step="0.001" name="machine_efficiency_factor" value="<?php echo esc_attr( $edit_printer->machine_efficiency_factor ?? '1' ); ?>" /></div>
									<div class="srf-input-row"><label>Setup time (min/job)</label><input type="number" min="0" step="0.01" name="setup_time_minutes" value="<?php echo esc_attr( $edit_printer->setup_time_minutes ?? '' ); ?>" /></div>
									<div class="srf-input-row"><label>Warmup time (min)</label><input type="number" min="0" step="0.01" name="warmup_time_minutes" value="<?php echo esc_attr( $edit_printer->warmup_time_minutes ?? '' ); ?>" /></div>
									<div class="srf-input-row"><label>Post-process time (min)</label><input type="number" min="0" step="0.01" name="postprocess_time_minutes" value="<?php echo esc_attr( $edit_printer->postprocess_time_minutes ?? '' ); ?>" /></div>
									<div class="srf-input-row"><label>Pricing model</label><select name="pricing_model"><?php foreach ( $pricing_models as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $edit_printer->pricing_model ?? '', $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></div>
									<div class="srf-input-row"><label>Minimum job price</label><input type="number" min="0" step="0.01" name="minimum_job_price" value="<?php echo esc_attr( $edit_printer->minimum_job_price ?? '' ); ?>" /></div>
									<div class="srf-input-row"><label>Minimum material charge</label><input type="number" min="0" step="0.01" name="minimum_material_charge" value="<?php echo esc_attr( $edit_printer->minimum_material_charge ?? '' ); ?>" /></div>
									<div class="srf-input-row"><label>Margin override (%)</label><input type="number" min="0" step="0.001" name="margin_override" value="<?php echo esc_attr( $edit_printer->margin_override ?? '' ); ?>" /></div>
								</div>
							</div>

							<div class="srf-form-section">
								<h3><?php esc_html_e( 'Material compatibility', 'service-requests-form' ); ?></h3>
								<div class="srf-grid-cols">
									<div class="srf-input-row">
										<label for="srf_supported_materials"><?php esc_html_e( 'Supported materials', 'service-requests-form' ); ?></label>
										<?php if ( empty( $material_map ) ) : ?>
											<p><?php esc_html_e( 'No materials available yet. Add materials first.', 'service-requests-form' ); ?></p>
										<?php else : ?>
											<select id="srf_supported_materials" name="supported_materials[]" multiple size="8">
												<?php foreach ( $material_map as $material_id => $material_name ) : ?>
													<option value="<?php echo esc_attr( $material_id ); ?>" <?php selected( in_array( (int) $material_id, $selected_materials, true ) ); ?>><?php echo esc_html( $material_name ); ?></option>
												<?php endforeach; ?>
											</select>
											<small><?php esc_html_e( 'Use Ctrl/Cmd to select multiple materials supported by this printer.', 'service-requests-form' ); ?></small>
										<?php endif; ?>
									</div>
									<div class="srf-input-row">
										<label for="srf_default_material_id"><?php esc_html_e( 'Default material', 'service-requests-form' ); ?></label>
										<select id="srf_default_material_id" name="default_material_id">
											<option value=""><?php esc_html_e( 'No default material', 'service-requests-form' ); ?></option>
											<?php foreach ( $material_map as $material_id => $material_name ) : ?>
												<option value="<?php echo esc_attr( $material_id ); ?>" <?php selected( (int) ( $edit_printer->default_material_id ?? 0 ), (int) $material_id ); ?>><?php echo esc_html( $material_name ); ?></option>
											<?php endforeach; ?>
										</select>
										<small><?php esc_html_e( 'Material colors, finishes, and support definitions are managed in the Materials tab. Here you only decide which materials this printer accepts.', 'service-requests-form' ); ?></small>
									</div>
								</div>
							</div>

							<div class="srf-form-section">
								<h3><?php esc_html_e( 'Profiles (profile services)', 'service-requests-form' ); ?></h3>
								<div class="srf-grid-cols">
									<div class="srf-input-row">
										<label for="srf_supported_service_profile_ids"><?php esc_html_e( 'Supported service profiles', 'service-requests-form' ); ?></label>
										<?php if ( empty( $service_map ) ) : ?>
											<p><?php esc_html_e( 'No services available yet. Add or publish services first.', 'service-requests-form' ); ?></p>
										<?php else : ?>
											<select id="srf_supported_service_profile_ids" name="supported_service_profile_ids[]" multiple size="8">
												<?php foreach ( $service_map as $service_id => $service_title ) : ?>
													<option value="<?php echo esc_attr( $service_id ); ?>" <?php selected( in_array( (int) $service_id, $selected_service_profiles, true ) ); ?>><?php echo esc_html( $service_title ); ?></option>
												<?php endforeach; ?>
											</select>
											<small><?php esc_html_e( 'These service profiles come from the Services tab. When the frontend is wired, choosing one of them for a printer can expose that service variations inside Project Step 2.', 'service-requests-form' ); ?></small>
										<?php endif; ?>
									</div>
									<div class="srf-input-row">
										<label for="srf_default_service_profile_id"><?php esc_html_e( 'Default service profile', 'service-requests-form' ); ?></label>
										<select id="srf_default_service_profile_id" name="default_service_profile_id">
											<option value=""><?php esc_html_e( 'No default service profile', 'service-requests-form' ); ?></option>
											<?php foreach ( $service_map as $service_id => $service_title ) : ?>
												<option value="<?php echo esc_attr( $service_id ); ?>" <?php selected( (int) ( $edit_printer->default_service_profile_id ?? 0 ), (int) $service_id ); ?>><?php echo esc_html( $service_title ); ?></option>
											<?php endforeach; ?>
										</select>
									</div>
								</div>
							</div>

							<div class="srf-form-section">
								<h3><?php esc_html_e( 'UI field controls', 'service-requests-form' ); ?></h3>
								<div class="srf-toggle-grid">
									<?php self::render_checkbox( 'enable_infill', 'Enable infill field', ! empty( $edit_printer->enable_infill ), 'Useful for FDM workflows.' ); ?>
									<?php self::render_checkbox( 'enable_supports', 'Enable supports field', ! empty( $edit_printer->enable_supports ), 'Show support choices in the user UI.' ); ?>
									<?php self::render_checkbox( 'enable_structure', 'Enable structure field', ! empty( $edit_printer->enable_structure ), 'For hollow / solid or similar structural presets.' ); ?>
									<?php self::render_checkbox( 'enable_application_profile', 'Enable application profile', ! empty( $edit_printer->enable_application_profile ), 'Recommended for PolyJet and dental flows.' ); ?>
									<?php self::render_checkbox( 'enable_finish_selection', 'Enable finish selection', ! empty( $edit_printer->enable_finish_selection ), 'Matte / glossy and similar finish choices.' ); ?>
									<?php self::render_checkbox( 'enable_color_selection', 'Enable color selection', ! empty( $edit_printer->enable_color_selection ), 'Show material color or shade in the frontend.' ); ?>
									<?php self::render_checkbox( 'enable_scale', 'Enable scale field', ! empty( $edit_printer->enable_scale ) || ! $edit_printer, 'Allow user scaling in the frontend.' ); ?>
									<?php self::render_checkbox( 'enable_quantity', 'Enable quantity field', ! empty( $edit_printer->enable_quantity ) || ! $edit_printer, 'Allow ordering multiple units.' ); ?>
									<?php self::render_checkbox( 'enable_advanced_settings', 'Enable advanced settings', ! empty( $edit_printer->enable_advanced_settings ), 'Reserve space for future expert options.' ); ?>
								</div>
							</div>


							<div class="srf-form-section">
								<h3><?php esc_html_e( 'Capabilities and validation', 'service-requests-form' ); ?></h3>
								<div class="srf-grid-cols-3">
									<div class="srf-input-row"><label>Min wall thickness (mm)</label><input type="number" min="0" step="0.0001" name="min_wall_thickness" value="<?php echo esc_attr( $edit_printer->min_wall_thickness ?? '' ); ?>" /></div>
									<div class="srf-input-row"><label>Max materials per job</label><input type="number" min="0" step="1" name="max_materials_per_job" value="<?php echo esc_attr( $edit_printer->max_materials_per_job ?? '' ); ?>" /></div>
									<div class="srf-input-row"><label>Max quantity per job</label><input type="number" min="0" step="1" name="max_quantity_per_job" value="<?php echo esc_attr( $edit_printer->max_quantity_per_job ?? '' ); ?>" /></div>
									<div class="srf-input-row" style="grid-column: span 3;"><label>Allowed file formats</label><textarea name="allowed_file_formats" rows="4"><?php echo esc_textarea( self::format_text_list_for_textarea( $edit_printer->allowed_file_formats ?? '' ) ); ?></textarea><small><?php esc_html_e( 'One per line. Example: stl, obj, 3mf, step', 'service-requests-form' ); ?></small></div>
								</div>
								<div class="srf-toggle-grid" style="margin-top:14px;">
									<?php self::render_checkbox( 'multi_material_enabled', 'Enable multi-material jobs', ! empty( $edit_printer->multi_material_enabled ), 'For PolyJet, multimaterial, or full-color capable systems.' ); ?>
									<?php self::render_checkbox( 'color_printing_enabled', 'Enable color printing', ! empty( $edit_printer->color_printing_enabled ), 'Allow color-aware UI and pricing rules.' ); ?>
									<?php self::render_checkbox( 'supports_hollow_models', 'Supports hollow models', ! empty( $edit_printer->supports_hollow_models ), 'This printer can run hollow-model workflows.' ); ?>
									<?php self::render_checkbox( 'supports_full_color_workflow', 'Supports full-color workflow', ! empty( $edit_printer->supports_full_color_workflow ), 'Useful for color-enabled PolyJet or binder-jet systems.' ); ?>
									<?php self::render_checkbox( 'supports_biocompatible_workflow', 'Supports biocompatible workflow', ! empty( $edit_printer->supports_biocompatible_workflow ), 'Enable medical / dental certified workflow rules.' ); ?>
									<?php self::render_checkbox( 'supports_transparent_materials', 'Supports transparent materials', ! empty( $edit_printer->supports_transparent_materials ), 'Allow transparent or clear materials in UI rules.' ); ?>
									<?php self::render_checkbox( 'supports_flexible_materials', 'Supports flexible materials', ! empty( $edit_printer->supports_flexible_materials ), 'Allow elastomer or flexible material workflows.' ); ?>
								</div>
							</div>
							<div class="srf-admin-actions"><button type="submit" class="button button-primary"><?php echo esc_html( $edit_printer ? __( 'Update Printer', 'service-requests-form' ) : __( 'Save Printer', 'service-requests-form' ) ); ?></button></div>
						</form>
					</div>

					<div class="srf-admin-card">
						<h2><?php esc_html_e( 'Printer catalogue', 'service-requests-form' ); ?></h2>
						<table class="srf-admin-table">
							<thead><tr><th><?php esc_html_e( 'Printer', 'service-requests-form' ); ?></th><th><?php esc_html_e( 'Technology', 'service-requests-form' ); ?></th><th><?php esc_html_e( 'Limits', 'service-requests-form' ); ?></th><th><?php esc_html_e( 'Pricing', 'service-requests-form' ); ?></th><th><?php esc_html_e( 'UI profile', 'service-requests-form' ); ?></th><th><?php esc_html_e( 'Status', 'service-requests-form' ); ?></th><th><?php esc_html_e( 'Actions', 'service-requests-form' ); ?></th></tr></thead>
							<tbody>
							<?php if ( empty( $printers ) ) : ?>
								<tr><td colspan="7"><?php esc_html_e( 'No printers found yet.', 'service-requests-form' ); ?></td></tr>
							<?php else : ?>
								<?php foreach ( $printers as $printer ) : ?>
									<?php
									$supported_ids   = self::decode_supported_materials( $printer->supported_materials );
									$supported_names = array();
									foreach ( $supported_ids as $mid ) {
										if ( isset( $material_map[ $mid ] ) ) {
											$supported_names[] = $material_map[ $mid ];
										}
									}
									$build_volume = trim( implode( ' × ', array_filter( array( (string) $printer->build_volume_x, (string) $printer->build_volume_y, (string) $printer->build_volume_z ), static function( $v ) { return '' !== $v && '0' !== $v && '0.00' !== $v; } ) ) );
									$ui_flags = array();
									foreach ( array( 'enable_infill' => 'Infill', 'enable_supports' => 'Supports', 'enable_structure' => 'Structure', 'enable_application_profile' => 'Profile', 'enable_finish_selection' => 'Finish', 'enable_color_selection' => 'Color' ) as $flag => $label ) {
										if ( ! empty( $printer->{$flag} ) ) {
											$ui_flags[] = $label;
										}
									}
									?>
									<tr>
										<td><strong><?php echo esc_html( $printer->name ); ?></strong><?php if ( ! empty( $printer->brand ) || ! empty( $printer->model ) ) : ?><br><span style="color:#667085;"><?php echo esc_html( trim( $printer->brand . ' ' . $printer->model ) ); ?></span><?php endif; ?><?php if ( ! empty( $supported_names ) ) : ?><br><span style="color:#667085;"><?php echo esc_html( implode( ', ', array_slice( $supported_names, 0, 4 ) ) ); ?><?php echo count( $supported_names ) > 4 ? esc_html__( '…', 'service-requests-form' ) : ''; ?></span><?php endif; ?></td>
										<td><?php echo esc_html( $printer->technology ?: '—' ); ?></td>
										<td><dl class="srf-kv"><dt><?php esc_html_e( 'Build', 'service-requests-form' ); ?></dt><dd><?php echo esc_html( $build_volume ?: '—' ); ?></dd><dt><?php esc_html_e( 'Layer', 'service-requests-form' ); ?></dt><dd><?php echo esc_html( ( $printer->min_layer_height ?: '—' ) . ' / ' . ( $printer->max_layer_height ?: '—' ) ); ?></dd></dl></td>
										<td><dl class="srf-kv"><dt><?php esc_html_e( 'Hourly', 'service-requests-form' ); ?></dt><dd><?php echo esc_html( '' !== (string) $printer->hourly_cost ? number_format_i18n( (float) $printer->hourly_cost, 2 ) : '—' ); ?></dd><dt><?php esc_html_e( 'Model', 'service-requests-form' ); ?></dt><dd><?php echo esc_html( $printer->pricing_model ?: '—' ); ?></dd></dl></td>
										<td><?php echo esc_html( ! empty( $ui_flags ) ? implode( ', ', $ui_flags ) : '—' ); ?></td>
										<td><span class="srf-status-pill srf-status-pill--<?php echo esc_attr( 'active' === $printer->status ? 'active' : 'inactive' ); ?>"><?php echo esc_html( ucfirst( (string) $printer->status ) ); ?></span></td>
										<td><div class="srf-row-actions"><a class="button button-secondary" href="<?php echo esc_url( self::get_page_url( array( 'action' => 'edit', 'id' => (int) $printer->id ) ) ); ?>"><?php esc_html_e( 'Edit', 'service-requests-form' ); ?></a><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;"><input type="hidden" name="action" value="srf_delete_printer" /><input type="hidden" name="printer_id" value="<?php echo esc_attr( (int) $printer->id ); ?>" /><?php wp_nonce_field( 'srf_delete_printer', 'srf_printer_delete_nonce' ); ?><button type="submit" class="button button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'Delete this printer?', 'service-requests-form' ) ); ?>');"><?php esc_html_e( 'Delete', 'service-requests-form' ); ?></button></form></div></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>
			<script>(function(){var brand=document.getElementById('srf_printer_brand');if(!brand)return;function sync(){document.querySelectorAll('[data-printer-brand-panel]').forEach(function(panel){panel.style.display=(panel.getAttribute('data-printer-brand-panel')===brand.value)?'':'none';});}brand.addEventListener('change',sync);sync();})();</script>
			</div>
			<?php
		}
	}
}
