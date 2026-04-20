<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SRF_Quote_DB' ) ) {

	class SRF_Quote_DB {

		/** @var wpdb */
		protected $wpdb;

		const MATERIALS_TABLE = 'srf_quote_materials';
		const PRINTERS_TABLE  = 'srf_quote_printers';

		public function __construct() {
			global $wpdb;
			$this->wpdb = $wpdb;
		}

		public function get_materials_table() {
			return $this->wpdb->prefix . self::MATERIALS_TABLE;
		}

		public function get_printers_table() {
			return $this->wpdb->prefix . self::PRINTERS_TABLE;
		}

		public function get_last_error() {
			return (string) $this->wpdb->last_error;
		}

		public static function install() {
			$self = new self();
			$self->create_tables();
		}

		public function create_tables() {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			$charset_collate = $this->wpdb->get_charset_collate();
			$materials_table = $this->get_materials_table();
			$printers_table  = $this->get_printers_table();

			$sql = "
			CREATE TABLE {$materials_table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				name varchar(191) NOT NULL,
				slug varchar(191) NOT NULL,
				description text DEFAULT NULL,
				price_per_gram decimal(10,4) NOT NULL DEFAULT '0.0000',
				price_per_cm3 decimal(10,4) NOT NULL DEFAULT '0.0000',
				density decimal(10,4) NOT NULL DEFAULT '0.0000',
				machine_time_factor decimal(6,3) NOT NULL DEFAULT '1.000',
				surface_quality_factor decimal(6,3) NOT NULL DEFAULT '1.000',
				wastage_factor decimal(6,3) NOT NULL DEFAULT '1.000',
				color_availability varchar(255) DEFAULT NULL,
				supported_finishes longtext DEFAULT NULL,
				supported_support_materials longtext DEFAULT NULL,
				default_support_material varchar(191) DEFAULT NULL,
				supported_color_modes longtext DEFAULT NULL,
				support_material_map longtext DEFAULT NULL,
				status varchar(20) NOT NULL DEFAULT 'inactive',
				created_at datetime DEFAULT NULL,
				updated_at datetime DEFAULT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY slug (slug),
				KEY status (status),
				KEY name (name)
			) {$charset_collate};

			CREATE TABLE {$printers_table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				name varchar(191) NOT NULL,
				brand varchar(191) DEFAULT NULL,
				printer_family varchar(191) DEFAULT NULL,
				brand_settings_json longtext DEFAULT NULL,
				model varchar(191) DEFAULT NULL,
				description text DEFAULT NULL,
				technology varchar(100) DEFAULT NULL,
				build_volume_x decimal(10,2) DEFAULT NULL,
				build_volume_y decimal(10,2) DEFAULT NULL,
				build_volume_z decimal(10,2) DEFAULT NULL,
				xy_resolution decimal(10,4) DEFAULT NULL,
				nozzle_size decimal(10,4) DEFAULT NULL,
				min_feature_size decimal(10,4) DEFAULT NULL,
				max_part_weight decimal(10,2) DEFAULT NULL,
				default_speed decimal(10,2) DEFAULT NULL,
				speed_unit varchar(50) DEFAULT NULL,
				hourly_cost decimal(10,2) DEFAULT NULL,
				machine_efficiency_factor decimal(6,3) NOT NULL DEFAULT '1.000',
				setup_time_minutes decimal(10,2) DEFAULT NULL,
				warmup_time_minutes decimal(10,2) DEFAULT NULL,
				postprocess_time_minutes decimal(10,2) DEFAULT NULL,
				min_layer_height decimal(8,4) DEFAULT NULL,
				max_layer_height decimal(8,4) DEFAULT NULL,
				supported_materials longtext DEFAULT NULL,
				default_material_id bigint(20) unsigned DEFAULT NULL,
				supported_service_profile_ids longtext DEFAULT NULL,
				default_service_profile_id bigint(20) unsigned DEFAULT NULL,
				supported_application_profiles longtext DEFAULT NULL,
				supported_finishes longtext DEFAULT NULL,
				supported_support_materials longtext DEFAULT NULL,
				default_support_material varchar(191) DEFAULT NULL,
				support_material_map longtext DEFAULT NULL,
				supported_color_modes longtext DEFAULT NULL,
				pricing_model varchar(50) DEFAULT NULL,
				minimum_job_price decimal(10,2) DEFAULT NULL,
				minimum_material_charge decimal(10,2) DEFAULT NULL,
				margin_override decimal(6,3) DEFAULT NULL,
				enable_infill tinyint(1) NOT NULL DEFAULT 0,
				enable_supports tinyint(1) NOT NULL DEFAULT 0,
				enable_structure tinyint(1) NOT NULL DEFAULT 0,
				enable_application_profile tinyint(1) NOT NULL DEFAULT 0,
				enable_finish_selection tinyint(1) NOT NULL DEFAULT 0,
				enable_color_selection tinyint(1) NOT NULL DEFAULT 0,
				enable_scale tinyint(1) NOT NULL DEFAULT 1,
				enable_quantity tinyint(1) NOT NULL DEFAULT 1,
				enable_advanced_settings tinyint(1) NOT NULL DEFAULT 0,
				fdm_infill_min decimal(5,2) DEFAULT NULL,
				fdm_infill_max decimal(5,2) DEFAULT NULL,
				fdm_support_factor decimal(6,3) DEFAULT NULL,
				fdm_default_line_width decimal(8,4) DEFAULT NULL,
				fdm_default_print_speed decimal(10,2) DEFAULT NULL,
				fdm_default_travel_speed decimal(10,2) DEFAULT NULL,
				fdm_max_print_speed decimal(10,2) DEFAULT NULL,
				fdm_default_wall_count int(11) DEFAULT NULL,
				fdm_default_top_layers int(11) DEFAULT NULL,
				fdm_default_bottom_layers int(11) DEFAULT NULL,
				fdm_default_infill_pattern varchar(100) DEFAULT NULL,
				fdm_supported_infill_patterns longtext DEFAULT NULL,
				fdm_support_overhang_angle decimal(8,4) DEFAULT NULL,
				fdm_support_interface_factor decimal(6,3) DEFAULT NULL,
				fdm_cooling_factor decimal(6,3) DEFAULT NULL,
				fdm_retraction_factor decimal(6,3) DEFAULT NULL,
				fdm_bed_adhesion_type varchar(100) DEFAULT NULL,
				fdm_bridge_optimization tinyint(1) NOT NULL DEFAULT 0,
				resin_curing_factor decimal(6,3) DEFAULT NULL,
				resin_shrinkage_percent decimal(6,3) DEFAULT NULL,
				resin_default_wall_thickness decimal(8,4) DEFAULT NULL,
				resin_support_density_factor decimal(6,3) DEFAULT NULL,
				resin_support_removal_factor decimal(6,3) DEFAULT NULL,
				resin_default_exposure_time decimal(10,4) DEFAULT NULL,
				resin_bottom_exposure_time decimal(10,4) DEFAULT NULL,
				resin_lift_speed decimal(10,4) DEFAULT NULL,
				resin_lift_distance decimal(10,4) DEFAULT NULL,
				resin_orientation_factor decimal(6,3) DEFAULT NULL,
				resin_support_touchpoint_factor decimal(6,3) DEFAULT NULL,
				resin_support_tip_size decimal(8,4) DEFAULT NULL,
				resin_hollow_factor decimal(6,3) DEFAULT NULL,
				resin_drain_hole_factor decimal(6,3) DEFAULT NULL,
				resin_drain_hole_min_diameter decimal(8,4) DEFAULT NULL,
				resin_shrinkage_compensation decimal(6,3) DEFAULT NULL,
				resin_cure_compensation_factor decimal(6,3) DEFAULT NULL,
				resin_default_shell_thickness decimal(8,4) DEFAULT NULL,
				resin_post_cure_factor decimal(6,3) DEFAULT NULL,
				resin_cleaning_difficulty_factor decimal(6,3) DEFAULT NULL,
				polyjet_profile_cost_factor decimal(6,3) DEFAULT NULL,
				polyjet_profile_time_factor decimal(6,3) DEFAULT NULL,
				polyjet_finish_cost_factor decimal(6,3) DEFAULT NULL,
				polyjet_finish_time_factor decimal(6,3) DEFAULT NULL,
				polyjet_support_material_factor decimal(6,3) DEFAULT NULL,
				polyjet_tray_packing_factor decimal(6,3) DEFAULT NULL,
				polyjet_surface_quality_factor decimal(6,3) DEFAULT NULL,
				polyjet_postprocess_factor decimal(6,3) DEFAULT NULL,
				polyjet_failure_factor decimal(6,3) DEFAULT NULL,
				polyjet_color_mixing_factor decimal(6,3) DEFAULT NULL,
				polyjet_material_switching_factor decimal(6,3) DEFAULT NULL,
				polyjet_cleaning_factor decimal(6,3) DEFAULT NULL,
				polyjet_application_profile_override_factor decimal(6,3) DEFAULT NULL,
				polyjet_support_cleanup_difficulty_factor decimal(6,3) DEFAULT NULL,
				polyjet_layer_resolution_microns decimal(10,4) DEFAULT NULL,
				polyjet_build_style varchar(100) DEFAULT NULL,
				polyjet_voxel_control_factor decimal(6,3) DEFAULT NULL,
				multi_material_enabled tinyint(1) NOT NULL DEFAULT 0,
				color_printing_enabled tinyint(1) NOT NULL DEFAULT 0,
				supports_hollow_models tinyint(1) NOT NULL DEFAULT 0,
				supports_full_color_workflow tinyint(1) NOT NULL DEFAULT 0,
				supports_biocompatible_workflow tinyint(1) NOT NULL DEFAULT 0,
				supports_transparent_materials tinyint(1) NOT NULL DEFAULT 0,
				supports_flexible_materials tinyint(1) NOT NULL DEFAULT 0,
				max_materials_per_job int(11) DEFAULT NULL,
				min_wall_thickness decimal(8,4) DEFAULT NULL,
				max_quantity_per_job int(11) DEFAULT NULL,
				allowed_file_formats longtext DEFAULT NULL,
				status varchar(20) NOT NULL DEFAULT 'inactive',
				created_at datetime DEFAULT NULL,
				updated_at datetime DEFAULT NULL,
				PRIMARY KEY (id),
				KEY status (status),
				KEY name (name),
				KEY technology (technology),
				KEY default_material_id (default_material_id)
			) {$charset_collate};
			";

			dbDelta( $sql );
		}

		protected function maybe_bootstrap_tables() {
			static $checked = false;

			if ( $checked ) {
				return;
			}

			$checked = true;

			$tables = array(
				$this->get_materials_table(),
				$this->get_printers_table(),
			);

			foreach ( $tables as $table ) {
				$exists = $this->wpdb->get_var(
					$this->wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
				);

				if ( $exists !== $table ) {
					$this->create_tables();
					break;
				}
			}
		}

		protected function prepare_timestamp_data( $data, $is_insert = false ) {
			$now = current_time( 'mysql' );

			if ( $is_insert && empty( $data['created_at'] ) ) {
				$data['created_at'] = $now;
			}

			$data['updated_at'] = $now;

			return $data;
		}

		/* =========================================================
		 * Materials
		 * ======================================================= */

		public function insert_material( $data ) {
			$this->maybe_bootstrap_tables();

			$defaults = array(
				'name'                   => '',
				'slug'                   => '',
				'description'            => '',
				'price_per_gram'         => 0,
				'price_per_cm3'          => 0,
				'density'                => 0,
				'machine_time_factor'    => 1,
				'surface_quality_factor' => 1,
				'wastage_factor'         => 1,
				'color_availability'     => '',
				'status'                 => 'active',
			);

			$data = wp_parse_args( $data, $defaults );
			$data = $this->prepare_timestamp_data( $data, true );

			$inserted = $this->wpdb->insert( $this->get_materials_table(), $data );

			return false === $inserted ? 0 : (int) $this->wpdb->insert_id;
		}

		public function update_material( $id, $data ) {
			$this->maybe_bootstrap_tables();

			$id = (int) $id;
			if ( $id <= 0 ) {
				return false;
			}

			$data = $this->prepare_timestamp_data( $data, false );

			return false !== $this->wpdb->update(
				$this->get_materials_table(),
				$data,
				array( 'id' => $id )
			);
		}

		public function delete_material( $id ) {
			$this->maybe_bootstrap_tables();

			$id = (int) $id;
			if ( $id <= 0 ) {
				return false;
			}

			return false !== $this->wpdb->delete(
				$this->get_materials_table(),
				array( 'id' => $id )
			);
		}

		public function get_material( $id ) {
			$this->maybe_bootstrap_tables();

			$id = (int) $id;
			if ( $id <= 0 ) {
				return null;
			}

			return $this->wpdb->get_row(
				$this->wpdb->prepare(
					"SELECT * FROM {$this->get_materials_table()} WHERE id = %d",
					$id
				)
			);
		}

		public function get_material_by_slug( $slug ) {
			$this->maybe_bootstrap_tables();

			$slug = sanitize_title( $slug );
			if ( '' === $slug ) {
				return null;
			}

			return $this->wpdb->get_row(
				$this->wpdb->prepare(
					"SELECT * FROM {$this->get_materials_table()} WHERE slug = %s",
					$slug
				)
			);
		}

		public function get_materials( $args = array() ) {
			$this->maybe_bootstrap_tables();

			$args = wp_parse_args(
				$args,
				array(
					'status' => '',
					'slug'   => '',
				)
			);

			$where  = array();
			$params = array();

			if ( '' !== $args['status'] ) {
				$where[]  = 'status = %s';
				$params[] = $args['status'];
			}

			if ( '' !== $args['slug'] ) {
				$where[]  = 'slug = %s';
				$params[] = $args['slug'];
			}

			$sql = "SELECT * FROM {$this->get_materials_table()}";
			if ( ! empty( $where ) ) {
				$sql .= ' WHERE ' . implode( ' AND ', $where );
			}
			$sql .= ' ORDER BY name ASC';

			if ( ! empty( $params ) ) {
				return $this->wpdb->get_results( $this->wpdb->prepare( $sql, $params ) );
			}

			return $this->wpdb->get_results( $sql );
		}

		/* =========================================================
		 * Printers
		 * ======================================================= */

		public function insert_printer( $data ) {
			$this->maybe_bootstrap_tables();

			$defaults = array(
				'name'                          => '',
				'brand'                         => '',
				'model'                         => '',
				'description'                   => '',
				'technology'                    => '',
				'build_volume_x'                => null,
				'build_volume_y'                => null,
				'build_volume_z'                => null,
				'xy_resolution'                 => null,
				'nozzle_size'                   => null,
				'min_feature_size'              => null,
				'max_part_weight'               => null,
				'default_speed'                 => null,
				'speed_unit'                    => '',
				'hourly_cost'                   => null,
				'machine_efficiency_factor'     => 1,
				'setup_time_minutes'            => null,
				'warmup_time_minutes'           => null,
				'postprocess_time_minutes'      => null,
				'min_layer_height'              => null,
				'max_layer_height'              => null,
				'supported_materials'           => '',
				'default_material_id'           => null,
				'supported_service_profile_ids'=> '',
				'default_service_profile_id'   => null,
				'supported_application_profiles'=> '',
				'supported_finishes'            => '',
				'supported_support_materials'   => '',
				'default_support_material'      => '',
				'support_material_map'          => '',
				'supported_color_modes'         => '',
				'pricing_model'                 => '',
				'minimum_job_price'             => null,
				'minimum_material_charge'       => null,
				'margin_override'               => null,
				'enable_infill'                 => 0,
				'enable_supports'               => 0,
				'enable_structure'              => 0,
				'enable_application_profile'    => 0,
				'enable_finish_selection'       => 0,
				'enable_color_selection'        => 0,
				'enable_scale'                  => 1,
				'enable_quantity'               => 1,
				'enable_advanced_settings'      => 0,
				'fdm_infill_min'                => null,
				'fdm_infill_max'                => null,
				'fdm_support_factor'            => null,
				'fdm_default_line_width'        => null,
				'fdm_default_print_speed'       => null,
				'fdm_default_travel_speed'      => null,
				'fdm_max_print_speed'           => null,
				'fdm_default_wall_count'        => null,
				'fdm_default_top_layers'        => null,
				'fdm_default_bottom_layers'     => null,
				'fdm_default_infill_pattern'    => '',
				'fdm_supported_infill_patterns' => '',
				'fdm_support_overhang_angle'    => null,
				'fdm_support_interface_factor'  => null,
				'fdm_cooling_factor'            => null,
				'fdm_retraction_factor'         => null,
				'fdm_bed_adhesion_type'         => '',
				'fdm_bridge_optimization'       => 0,
				'resin_curing_factor'           => null,
				'resin_shrinkage_percent'       => null,
				'resin_default_wall_thickness'  => null,
				'resin_support_density_factor'  => null,
				'resin_support_removal_factor'  => null,
				'resin_default_exposure_time'   => null,
				'resin_bottom_exposure_time'    => null,
				'resin_lift_speed'              => null,
				'resin_lift_distance'           => null,
				'resin_orientation_factor'      => null,
				'resin_support_touchpoint_factor' => null,
				'resin_support_tip_size'        => null,
				'resin_hollow_factor'           => null,
				'resin_drain_hole_factor'       => null,
				'resin_drain_hole_min_diameter' => null,
				'resin_shrinkage_compensation'  => null,
				'resin_cure_compensation_factor' => null,
				'resin_default_shell_thickness' => null,
				'resin_post_cure_factor'        => null,
				'resin_cleaning_difficulty_factor' => null,
				'polyjet_profile_cost_factor'   => null,
				'polyjet_profile_time_factor'   => null,
				'polyjet_finish_cost_factor'    => null,
				'polyjet_finish_time_factor'    => null,
				'polyjet_support_material_factor' => null,
				'polyjet_tray_packing_factor'   => null,
				'polyjet_surface_quality_factor' => null,
				'polyjet_postprocess_factor'    => null,
				'polyjet_failure_factor'        => null,
				'polyjet_color_mixing_factor'   => null,
				'polyjet_material_switching_factor' => null,
				'polyjet_cleaning_factor'       => null,
				'polyjet_application_profile_override_factor' => null,
				'polyjet_support_cleanup_difficulty_factor' => null,
				'polyjet_layer_resolution_microns' => null,
				'polyjet_build_style'           => '',
				'polyjet_voxel_control_factor'  => null,
				'multi_material_enabled'        => 0,
				'color_printing_enabled'        => 0,
				'supports_hollow_models'        => 0,
				'supports_full_color_workflow'  => 0,
				'supports_biocompatible_workflow' => 0,
				'supports_transparent_materials' => 0,
				'supports_flexible_materials'   => 0,
				'max_materials_per_job'         => null,
				'min_wall_thickness'            => null,
				'max_quantity_per_job'          => null,
				'allowed_file_formats'          => '',
				'status'                        => 'active',
			);

			$data = wp_parse_args( $data, $defaults );
			$data = $this->prepare_timestamp_data( $data, true );

			$inserted = $this->wpdb->insert( $this->get_printers_table(), $data );

			return false === $inserted ? 0 : (int) $this->wpdb->insert_id;
		}

		public function update_printer( $id, $data ) {
			$this->maybe_bootstrap_tables();

			$id = (int) $id;
			if ( $id <= 0 ) {
				return false;
			}

			$data = $this->prepare_timestamp_data( $data, false );

			return false !== $this->wpdb->update(
				$this->get_printers_table(),
				$data,
				array( 'id' => $id )
			);
		}

		public function delete_printer( $id ) {
			$this->maybe_bootstrap_tables();

			$id = (int) $id;
			if ( $id <= 0 ) {
				return false;
			}

			return false !== $this->wpdb->delete(
				$this->get_printers_table(),
				array( 'id' => $id )
			);
		}

		public function get_printer( $id ) {
			$this->maybe_bootstrap_tables();

			$id = (int) $id;
			if ( $id <= 0 ) {
				return null;
			}

			return $this->wpdb->get_row(
				$this->wpdb->prepare(
					"SELECT * FROM {$this->get_printers_table()} WHERE id = %d",
					$id
				)
			);
		}

		public function get_printers( $args = array() ) {
			$this->maybe_bootstrap_tables();

			$args = wp_parse_args(
				$args,
				array(
					'status'     => '',
					'technology' => '',
				)
			);

			$where  = array();
			$params = array();

			if ( '' !== $args['status'] ) {
				$where[]  = 'status = %s';
				$params[] = $args['status'];
			}

			if ( '' !== $args['technology'] ) {
				$where[]  = 'technology = %s';
				$params[] = $args['technology'];
			}

			$sql = "SELECT * FROM {$this->get_printers_table()}";
			if ( ! empty( $where ) ) {
				$sql .= ' WHERE ' . implode( ' AND ', $where );
			}
			$sql .= ' ORDER BY name ASC';

			if ( ! empty( $params ) ) {
				return $this->wpdb->get_results( $this->wpdb->prepare( $sql, $params ) );
			}

			return $this->wpdb->get_results( $sql );
		}

		public function get_active_material_choices() {
			$items   = $this->get_materials( array( 'status' => 'active' ) );
			$choices = array();

			foreach ( $items as $item ) {
				$choices[ (int) $item->id ] = $item->name;
			}

			return $choices;
		}

		public function get_active_printer_choices() {
			$items   = $this->get_printers( array( 'status' => 'active' ) );
			$choices = array();

			foreach ( $items as $item ) {
				$choices[ (int) $item->id ] = $item->name;
			}

			return $choices;
		}
	}
}
