<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SR_CPT' ) ) {

    class SR_CPT {

        /**
         * Register custom post type.
         */
        public static function register_cpt() {

            $labels = array(
                'name'                  => __( 'Service Requests', 'service-requests-form' ),
                'singular_name'         => __( 'Service Request', 'service-requests-form' ),
                'menu_name'             => __( 'Service Requests', 'service-requests-form' ),
                'name_admin_bar'        => __( 'Service Request', 'service-requests-form' ),
                'add_new'               => __( 'Add New', 'service-requests-form' ),
                'add_new_item'          => __( 'Add New Request', 'service-requests-form' ),
                'new_item'              => __( 'New Request', 'service-requests-form' ),
                'edit_item'             => __( 'Edit Request', 'service-requests-form' ),
                'view_item'             => __( 'View Request', 'service-requests-form' ),
                'all_items'             => __( 'All Requests', 'service-requests-form' ),
                'search_items'          => __( 'Search Requests', 'service-requests-form' ),
                'not_found'             => __( 'No requests found.', 'service-requests-form' ),
                'not_found_in_trash'    => __( 'No requests found in Trash.', 'service-requests-form' ),
            );

            $args = array(
                'labels'             => $labels,
                'public'             => false,
                'show_ui'            => true,
                'show_in_menu'       => true,
                'menu_position'      => 26,
                'menu_icon'          => 'dashicons-clipboard',
                'capability_type'    => 'post',
                'supports'           => array( 'title', 'editor', 'author' ),
                'has_archive'        => false,
                'rewrite'            => false,
            );

            register_post_type( 'service_request', $args );
        }
    }

}
