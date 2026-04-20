<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'label' => 'Formlabs',
	'description' => __( 'Brand-specific parameters for Formlabs SLA and dental workflows.', 'service-requests-form' ),
	'sections' => array(
		'platform' => array(
			'label' => __( 'Machine platform and workflow', 'service-requests-form' ),
			'fields' => array(
				array( 'key' => 'family', 'label' => __( 'Formlabs family', 'service-requests-form' ), 'type' => 'select', 'options' => SRF_Printer_Brand_Registry::get_family_options( 'formlabs' ) ),
				array( 'key' => 'preform_profile', 'label' => __( 'PreForm profile', 'service-requests-form' ), 'type' => 'text' ),
				array( 'key' => 'tank_generation', 'label' => __( 'Resin tank generation', 'service-requests-form' ), 'type' => 'text' ),
				array( 'key' => 'build_platform_type', 'label' => __( 'Build platform type', 'service-requests-form' ), 'type' => 'text' ),
			),
		),
		'resin' => array(
			'label' => __( 'Resin print behavior', 'service-requests-form' ),
			'fields' => array(
				array( 'key' => 'resin_default_exposure_time', 'label' => __( 'Default exposure time', 'service-requests-form' ), 'type' => 'number', 'step' => '0.0001', 'min' => '0' ),
				array( 'key' => 'resin_bottom_exposure_time', 'label' => __( 'Bottom exposure time', 'service-requests-form' ), 'type' => 'number', 'step' => '0.0001', 'min' => '0' ),
				array( 'key' => 'resin_lift_speed', 'label' => __( 'Lift speed', 'service-requests-form' ), 'type' => 'number', 'step' => '0.0001', 'min' => '0' ),
				array( 'key' => 'resin_lift_distance', 'label' => __( 'Lift distance', 'service-requests-form' ), 'type' => 'number', 'step' => '0.0001', 'min' => '0' ),
				array( 'key' => 'resin_orientation_factor', 'label' => __( 'Orientation factor', 'service-requests-form' ), 'type' => 'number', 'step' => '0.001', 'min' => '0' ),
				array( 'key' => 'resin_support_density_factor', 'label' => __( 'Support density factor', 'service-requests-form' ), 'type' => 'number', 'step' => '0.001', 'min' => '0' ),
				array( 'key' => 'resin_support_touchpoint_factor', 'label' => __( 'Support touchpoint factor', 'service-requests-form' ), 'type' => 'number', 'step' => '0.001', 'min' => '0' ),
				array( 'key' => 'resin_post_cure_factor', 'label' => __( 'Post-cure factor', 'service-requests-form' ), 'type' => 'number', 'step' => '0.001', 'min' => '0' ),
			),
		),
		'dental' => array(
			'label' => __( 'Dental and medical workflow', 'service-requests-form' ),
			'fields' => array(
				array( 'key' => 'supports_biocompatible_track', 'label' => __( 'Supports biocompatible materials', 'service-requests-form' ), 'type' => 'checkbox' ),
				array( 'key' => 'supports_hollow_models', 'label' => __( 'Supports hollow models', 'service-requests-form' ), 'type' => 'checkbox' ),
				array( 'key' => 'resin_default_wall_thickness', 'label' => __( 'Default wall thickness (mm)', 'service-requests-form' ), 'type' => 'number', 'step' => '0.0001', 'min' => '0' ),
				array( 'key' => 'resin_drain_hole_min_diameter', 'label' => __( 'Drain hole min diameter (mm)', 'service-requests-form' ), 'type' => 'number', 'step' => '0.0001', 'min' => '0' ),
				array( 'key' => 'wash_time_minutes', 'label' => __( 'Wash time (minutes)', 'service-requests-form' ), 'type' => 'number', 'step' => '0.01', 'min' => '0' ),
				array( 'key' => 'cure_time_minutes', 'label' => __( 'Cure time (minutes)', 'service-requests-form' ), 'type' => 'number', 'step' => '0.01', 'min' => '0' ),
			),
		),
	),
);
