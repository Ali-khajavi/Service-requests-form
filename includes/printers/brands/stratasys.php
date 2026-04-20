<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'label' => 'Stratasys',
	'description' => __( 'Brand-specific parameters for Stratasys systems such as J5, J850, F-series, and other industrial workflows.', 'service-requests-form' ),
	'sections' => array(
		'platform' => array(
			'label' => __( 'Machine platform and workflow', 'service-requests-form' ),
			'fields' => array(
				array( 'key' => 'family', 'label' => __( 'Stratasys family', 'service-requests-form' ), 'type' => 'select', 'options' => SRF_Printer_Brand_Registry::get_family_options( 'stratasys' ) ),
				array( 'key' => 'grabcad_profile', 'label' => __( 'GrabCAD / workflow profile', 'service-requests-form' ), 'type' => 'text' ),
				array( 'key' => 'build_mode', 'label' => __( 'Build mode', 'service-requests-form' ), 'type' => 'select', 'options' => array( '' => __( 'Select mode', 'service-requests-form' ), 'balanced' => __( 'Balanced', 'service-requests-form' ), 'high_speed' => __( 'High speed', 'service-requests-form' ), 'high_quality' => __( 'High quality', 'service-requests-form' ) ) ),
				array( 'key' => 'tray_capacity_factor', 'label' => __( 'Tray capacity factor', 'service-requests-form' ), 'type' => 'number', 'step' => '0.001', 'min' => '0' ),
			),
		),
		'polyjet' => array(
			'label' => __( 'PolyJet and material behavior', 'service-requests-form' ),
			'fields' => array(
				array( 'key' => 'polyjet_layer_resolution_microns', 'label' => __( 'Layer resolution (microns)', 'service-requests-form' ), 'type' => 'number', 'step' => '0.1', 'min' => '0' ),
				array( 'key' => 'polyjet_support_material_factor', 'label' => __( 'Support material factor', 'service-requests-form' ), 'type' => 'number', 'step' => '0.001', 'min' => '0' ),
				array( 'key' => 'polyjet_tray_packing_factor', 'label' => __( 'Tray packing factor', 'service-requests-form' ), 'type' => 'number', 'step' => '0.001', 'min' => '0' ),
				array( 'key' => 'polyjet_surface_quality_factor', 'label' => __( 'Surface quality factor', 'service-requests-form' ), 'type' => 'number', 'step' => '0.001', 'min' => '0' ),
				array( 'key' => 'polyjet_finish_cost_factor', 'label' => __( 'Finish cost factor', 'service-requests-form' ), 'type' => 'number', 'step' => '0.001', 'min' => '0' ),
				array( 'key' => 'polyjet_finish_time_factor', 'label' => __( 'Finish time factor', 'service-requests-form' ), 'type' => 'number', 'step' => '0.001', 'min' => '0' ),
				array( 'key' => 'polyjet_postprocess_factor', 'label' => __( 'Post-process factor', 'service-requests-form' ), 'type' => 'number', 'step' => '0.001', 'min' => '0' ),
				array( 'key' => 'polyjet_failure_factor', 'label' => __( 'Failure / risk factor', 'service-requests-form' ), 'type' => 'number', 'step' => '0.001', 'min' => '0' ),
			),
		),
		'capabilities' => array(
			'label' => __( 'Capabilities and stations', 'service-requests-form' ),
			'fields' => array(
				array( 'key' => 'supports_digital_materials', 'label' => __( 'Supports digital materials', 'service-requests-form' ), 'type' => 'checkbox', 'help' => __( 'Enable material-mixing workflows such as Vero combinations.', 'service-requests-form' ) ),
				array( 'key' => 'supports_full_color', 'label' => __( 'Supports full-color builds', 'service-requests-form' ), 'type' => 'checkbox', 'help' => __( 'Use for J8-series full-color workflows.', 'service-requests-form' ) ),
				array( 'key' => 'wash_station_required', 'label' => __( 'Dedicated wash / cleanup station required', 'service-requests-form' ), 'type' => 'checkbox' ),
				array( 'key' => 'biocompatible_track', 'label' => __( 'Biocompatible track available', 'service-requests-form' ), 'type' => 'checkbox' ),
			),
		),
	),
);
