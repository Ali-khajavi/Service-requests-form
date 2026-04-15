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
				model varchar(191) DEFAULT NULL,
				technology varchar(100) DEFAULT NULL,
				build_volume_x decimal(10,2) DEFAULT NULL,
				build_volume_y decimal(10,2) DEFAULT NULL,
				build_volume_z decimal(10,2) DEFAULT NULL,
				default_speed decimal(10,2) DEFAULT NULL,
				hourly_cost decimal(10,2) DEFAULT NULL,
				min_layer_height decimal(8,4) DEFAULT NULL,
				max_layer_height decimal(8,4) DEFAULT NULL,
				supported_materials longtext DEFAULT NULL,
				status varchar(20) NOT NULL DEFAULT 'inactive',
				created_at datetime DEFAULT NULL,
				updated_at datetime DEFAULT NULL,
				PRIMARY KEY (id),
				KEY status (status),
				KEY name (name),
				KEY technology (technology)
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
				'name'                => '',
				'brand'               => '',
				'model'               => '',
				'technology'          => '',
				'build_volume_x'      => null,
				'build_volume_y'      => null,
				'build_volume_z'      => null,
				'default_speed'       => null,
				'hourly_cost'         => null,
				'min_layer_height'    => null,
				'max_layer_height'    => null,
				'supported_materials' => '',
				'status'              => 'active',
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