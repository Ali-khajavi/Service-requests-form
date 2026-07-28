<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'label'       => 'Bambu Lab',
	'description' => __( 'Bambu Lab FDM printer profile fields used by the project-order form and its built-in process presets.', 'service-requests-form' ),
	'sections'    => array(
		'platform' => array(
			'label'  => __( 'Bambu Studio platform', 'service-requests-form' ),
			'fields' => array(
				array( 'key' => 'family', 'label' => __( 'Bambu Lab family', 'service-requests-form' ), 'type' => 'select', 'options' => SRF_Printer_Brand_Registry::get_family_options( 'bambulab' ) ),
				array( 'key' => 'studio_printer_preset', 'label' => __( 'Bambu Studio printer preset', 'service-requests-form' ), 'type' => 'text' ),
				array( 'key' => 'default_process_key', 'label' => __( 'Default process preset key', 'service-requests-form' ), 'type' => 'select', 'options' => array(
					''                   => __( 'Use plugin default', 'service-requests-form' ),
					'bambu-008-extra-fine'    => '0.08mm Extra Fine',
					'bambu-008-high-quality'  => '0.08mm High Quality',
					'bambu-012-fine'          => '0.12mm Fine',
					'bambu-012-high-quality'  => '0.12mm High Quality',
					'bambu-016-optimal'       => '0.16mm Optimal',
					'bambu-016-high-quality'  => '0.16mm High Quality',
					'bambu-020-standard'      => '0.20mm Standard',
					'bambu-020-strength'      => '0.20mm Strength',
					'bambu-024-draft'         => '0.24mm Draft',
					'bambu-028-extra-draft'   => '0.28mm Extra Draft',
				) ),
			),
		),
		'capabilities' => array(
			'label'  => __( 'Capabilities', 'service-requests-form' ),
			'fields' => array(
				array( 'key' => 'ams_capable', 'label' => __( 'AMS / AMS lite capable', 'service-requests-form' ), 'type' => 'checkbox' ),
				array( 'key' => 'enclosed', 'label' => __( 'Enclosed build chamber', 'service-requests-form' ), 'type' => 'checkbox' ),
			),
		),
	),
);
