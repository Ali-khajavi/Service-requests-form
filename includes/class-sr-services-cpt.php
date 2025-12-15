<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SR_Services_CPT' ) ) {

    class SR_Services_CPT {

        /**
         * Meta key for gallery attachment IDs.
         */
        const META_GALLERY_IDS = '_sr_service_gallery_ids';

        /**
         * Register the Services custom post type.
         */
        public static function register_cpt() {

            $labels = array(
                'name'                  => __( 'Services', 'service-requests-form' ),
                'singular_name'         => __( 'Service', 'service-requests-form' ),
                'menu_name'             => __( 'Services', 'service-requests-form' ),
                'name_admin_bar'        => __( 'Service', 'service-requests-form' ),
                'add_new'               => __( 'Add New', 'service-requests-form' ),
                'add_new_item'          => __( 'Add New Service', 'service-requests-form' ),
                'new_item'              => __( 'New Service', 'service-requests-form' ),
                'edit_item'             => __( 'Edit Service', 'service-requests-form' ),
                'view_item'             => __( 'View Service', 'service-requests-form' ),
                'all_items'             => __( 'All Services', 'service-requests-form' ),
                'search_items'          => __( 'Search Services', 'service-requests-form' ),
                'not_found'             => __( 'No services found.', 'service-requests-form' ),
                'not_found_in_trash'    => __( 'No services found in Trash.', 'service-requests-form' ),
            );

            $parent_slug = ( class_exists( 'SRF_Admin_Menu' ) ? SRF_Admin_Menu::PARENT_SLUG : true );

            $args = array(
                'labels'          => $labels,
                'public'          => false,
                'show_ui'         => true,
                'show_in_menu'    => ( class_exists( 'SRF_Admin_Menu' ) ? SRF_Admin_Menu::PARENT_SLUG : true ),
                'supports'        => array( 'title', 'editor', 'thumbnail' ),
                'has_archive'     => false,
                'rewrite'         => false,
                'capability_type' => 'post',
            );


            register_post_type( 'sr_service', $args );
        }

        /**
         * Register meta boxes.
         */
        public static function add_meta_boxes() {
            add_meta_box(
                'sr_service_gallery',
                __( 'Service Gallery / Slider', 'service-requests-form' ),
                array( __CLASS__, 'render_gallery_metabox' ),
                'sr_service',
                'normal',
                'default'
            );
        }

        /**
         * Render gallery meta box.
         *
         * @param WP_Post $post
         */
        public static function render_gallery_metabox( $post ) {

            wp_nonce_field( 'sr_service_gallery_nonce_action', 'sr_service_gallery_nonce' );

            $gallery_ids = get_post_meta( $post->ID, self::META_GALLERY_IDS, true );
            if ( ! is_array( $gallery_ids ) ) {
                $gallery_ids = array();
            }

            // Hidden input to store IDs as comma separated list (for JS)
            $ids_value = ! empty( $gallery_ids ) ? implode( ',', array_map( 'intval', $gallery_ids ) ) : '';

            ?>
            <p>
                <?php esc_html_e( 'Select one or more images to display as a slider for this service.', 'service-requests-form' ); ?>
            </p>

            <input type="hidden" id="sr-service-gallery-ids" name="sr_service_gallery_ids" value="<?php echo esc_attr( $ids_value ); ?>" />

            <button type="button" class="button" id="sr-service-gallery-button">
                <?php esc_html_e( 'Select / Edit Gallery', 'service-requests-form' ); ?>
            </button>

            <div id="sr-service-gallery-preview" style="margin-top: 15px; display: flex; flex-wrap: wrap; gap: 10px;">
                <?php
                if ( ! empty( $gallery_ids ) ) {
                    foreach ( $gallery_ids as $attachment_id ) {
                        $thumb = wp_get_attachment_image( $attachment_id, array( 80, 80 ), true, array( 'style' => 'border:1px solid #ddd;' ) );
                        if ( $thumb ) {
                            echo '<div class="sr-service-gallery-item" style="width:80px;height:80px;overflow:hidden;">' . $thumb . '</div>';
                        }
                    }
                }
                ?>
            </div>

            <p class="description" style="margin-top:10px;">
                <?php esc_html_e( 'These images will be used in the front-end slider for this service.', 'service-requests-form' ); ?>
            </p>
            <?php
        }

        /**
         * Save service meta (gallery IDs).
         *
         * @param int     $post_id
         * @param WP_Post $post
         */
        public static function save_service_meta( $post_id, $post ) {

            // Check nonce
            if ( ! isset( $_POST['sr_service_gallery_nonce'] ) ||
                 ! wp_verify_nonce( $_POST['sr_service_gallery_nonce'], 'sr_service_gallery_nonce_action' ) ) {
                return;
            }

            // Autosave? Bail.
            if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
                return;
            }

            // Check user capability
            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                return;
            }

            // Save gallery IDs
            if ( isset( $_POST['sr_service_gallery_ids'] ) ) {
                $ids_raw = sanitize_text_field( wp_unslash( $_POST['sr_service_gallery_ids'] ) );
                $ids     = array_filter( array_map( 'absint', explode( ',', $ids_raw ) ) );

                update_post_meta( $post_id, self::META_GALLERY_IDS, $ids );
            } else {
                delete_post_meta( $post_id, self::META_GALLERY_IDS );
            }
        }

        /**
         * Enqueue admin assets for sr_service edit screens.
         *
         * @param string $hook
         */
        public static function enqueue_admin_assets( $hook ) {
            global $post;

            // Load only on sr_service edit/add screens
            if ( $hook === 'post-new.php' || $hook === 'post.php' ) {
                if ( isset( $post ) && $post->post_type === 'sr_service' ) {

                    // Media library
                    wp_enqueue_media();

                    // Admin JS
                    wp_enqueue_script(
                        'srf-admin-service-gallery',
                        SRF_PLUGIN_URL . 'assets/js/admin.js',
                        array( 'jquery' ),
                        SRF_VERSION,
                        true
                    );

                    // Admin CSS (optional but nice)
                    wp_enqueue_style(
                        'srf-admin-service-style',
                        SRF_PLUGIN_URL . 'assets/css/admin.css',
                        array(),
                        SRF_VERSION
                    );
                }
            }
        }

        /**
         * Add custom columns to sr_service list table.
         *
         * @param array $columns
         *
         * @return array
         */
        public static function add_admin_columns( $columns ) {
            $new = array();

            // Keep checkbox
            if ( isset( $columns['cb'] ) ) {
                $new['cb'] = $columns['cb'];
                unset( $columns['cb'] );
            }

            // Add thumbnail column
            $new['sr_service_thumb'] = __( 'Thumbnail', 'service-requests-form' );

            // Keep title
            $new['title'] = __( 'Service', 'service-requests-form' );

            // Gallery info
            $new['sr_service_images'] = __( 'Images', 'service-requests-form' );

            // Merge any remaining columns (date, etc.)
            return array_merge( $new, $columns );
        }

        /**
         * Render custom column content.
         *
         * @param string $column
         * @param int    $post_id
         */
        public static function render_admin_columns( $column, $post_id ) {

            if ( $column === 'sr_service_thumb' ) {
                $gallery_ids = get_post_meta( $post_id, self::META_GALLERY_IDS, true );
                $thumb_id    = null;

                if ( is_array( $gallery_ids ) && ! empty( $gallery_ids ) ) {
                    $thumb_id = $gallery_ids[0];
                } elseif ( has_post_thumbnail( $post_id ) ) {
                    $thumb_id = get_post_thumbnail_id( $post_id );
                }

                if ( $thumb_id ) {
                    echo wp_get_attachment_image( $thumb_id, array( 50, 50 ), true );
                } else {
                    echo '&mdash;';
                }
            }

            if ( $column === 'sr_service_images' ) {
                $gallery_ids = get_post_meta( $post_id, self::META_GALLERY_IDS, true );
                if ( is_array( $gallery_ids ) && ! empty( $gallery_ids ) ) {
                    printf(
                        /* translators: %d: number of images */
                        esc_html__( '%d image(s)', 'service-requests-form' ),
                        count( $gallery_ids )
                    );
                } else {
                    esc_html_e( 'No images', 'service-requests-form' );
                }
            }
        }

        /**
         * Helper: get gallery IDs array for a service.
         *
         * @param int $service_id
         *
         * @return int[]
         */
        public static function get_gallery_ids( $service_id ) {
            $ids = get_post_meta( $service_id, self::META_GALLERY_IDS, true );
            return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
        }
    }

}
