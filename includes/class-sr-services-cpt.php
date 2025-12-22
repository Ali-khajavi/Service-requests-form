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
		 * Meta key for service variations (array of rows: [label,value]).
		 */
		const META_VARIATIONS = '_sr_service_variations';

		/**
		 * Hook everything.
		 */
		public static function init() {

			add_action( 'init', array( __CLASS__, 'register_cpt' ) );

			add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );

			// Save meta (gallery + variations)
			add_action( 'save_post_sr_service', array( __CLASS__, 'save_service_meta' ), 10, 2 );

			// Admin assets
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );

			// Admin columns
			add_filter( 'manage_sr_service_posts_columns', array( __CLASS__, 'add_admin_columns' ) );
			add_action( 'manage_sr_service_posts_custom_column', array( __CLASS__, 'render_admin_columns' ), 10, 2 );
		}

		/**
		 * Register the Services custom post type.
		 */
		public static function register_cpt() {

			$labels = array(
				'name'               => __( 'Services', 'service-requests-form' ),
				'singular_name'      => __( 'Service', 'service-requests-form' ),
				'menu_name'          => __( 'Services', 'service-requests-form' ),
				'name_admin_bar'     => __( 'Service', 'service-requests-form' ),
				'add_new'            => __( 'Add New', 'service-requests-form' ),
				'add_new_item'       => __( 'Add New Service', 'service-requests-form' ),
				'new_item'           => __( 'New Service', 'service-requests-form' ),
				'edit_item'          => __( 'Edit Service', 'service-requests-form' ),
				'view_item'          => __( 'View Service', 'service-requests-form' ),
				'all_items'          => __( 'All Services', 'service-requests-form' ),
				'search_items'       => __( 'Search Services', 'service-requests-form' ),
				'not_found'          => __( 'No services found.', 'service-requests-form' ),
				'not_found_in_trash' => __( 'No services found in Trash.', 'service-requests-form' ),
			);

			$args = array(
				'labels'          => $labels,
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => ( class_exists( 'SRF_Admin_Menu' ) ? SRF_Admin_Menu::PARENT_SLUG : true ),
				'supports'        => array( 'title', 'editor', 'thumbnail' ),
				'has_archive'     => false,
				'rewrite'         => false,
				'capability_type' => 'post',
				'show_in_rest'    => false,
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

			add_meta_box(
				'sr_service_variations',
				__( 'Service Variations', 'service-requests-form' ),
				array( __CLASS__, 'render_variations_metabox' ),
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
						$thumb = wp_get_attachment_image(
							$attachment_id,
							array( 80, 80 ),
							true,
							array( 'style' => 'border:1px solid #ddd;' )
						);
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
		 * Render variations meta box.
		 *
		 * Stored as array:
		 * [
		 *   ['label' => 'Upper jaw', 'value' => 'upper_jaw'],
		 *   ...
		 * ]
		 *
		 * @param WP_Post $post
		 */
		public static function render_variations_metabox( $post ) {

			wp_nonce_field( 'sr_service_variations_nonce_action', 'sr_service_variations_nonce' );

			$rows = get_post_meta( $post->ID, self::META_VARIATIONS, true );
			if ( ! is_array( $rows ) ) {
				$rows = array();
			}

			?>
			<p class="description">
				<?php esc_html_e( 'Add variations for this service (optional). Example: Upper jaw / Lower jaw.', 'service-requests-form' ); ?>
			</p>

			<div id="sr-service-variations-wrap">
				<?php foreach ( $rows as $i => $row ) :
					$label = isset( $row['label'] ) ? (string) $row['label'] : '';
					$value = isset( $row['value'] ) ? (string) $row['value'] : '';
					?>
					<div class="sr-service-var-row" style="display:flex;gap:10px;margin:10px 0;align-items:center;">
						<input
							style="flex:1"
							type="text"
							name="sr_service_variations[<?php echo esc_attr( $i ); ?>][label]"
							placeholder="<?php echo esc_attr__( 'Label (e.g. Upper jaw)', 'service-requests-form' ); ?>"
							value="<?php echo esc_attr( $label ); ?>"
						/>
						<input
							style="flex:1"
							type="text"
							name="sr_service_variations[<?php echo esc_attr( $i ); ?>][value]"
							placeholder="<?php echo esc_attr__( 'Value (e.g. upper_jaw)', 'service-requests-form' ); ?>"
							value="<?php echo esc_attr( $value ); ?>"
						/>
						<button type="button" class="button sr-service-var-remove" aria-label="<?php echo esc_attr__( 'Remove', 'service-requests-form' ); ?>">×</button>
					</div>
				<?php endforeach; ?>
			</div>

			<button type="button" class="button button-primary" id="sr-service-var-add">
				<?php esc_html_e( 'Add variation', 'service-requests-form' ); ?>
			</button>

			<script>
			(function(){
				var wrap = document.getElementById('sr-service-variations-wrap');
				var btn  = document.getElementById('sr-service-var-add');
				if (!wrap || !btn) return;

				function reindex() {
					var rows = wrap.querySelectorAll('.sr-service-var-row');
					rows.forEach(function(row, idx){
						var inputs = row.querySelectorAll('input');
						inputs.forEach(function(inp){
							inp.name = inp.name.replace(/sr_service_variations\[\d+\]/, 'sr_service_variations['+idx+']');
						});
					});
				}

				wrap.addEventListener('click', function(e){
					if (e.target && e.target.classList.contains('sr-service-var-remove')) {
						e.preventDefault();
						var row = e.target.closest('.sr-service-var-row');
						if (row) row.remove();
						reindex();
					}
				});

				btn.addEventListener('click', function(e){
					e.preventDefault();
					var idx = wrap.querySelectorAll('.sr-service-var-row').length;

					var row = document.createElement('div');
					row.className = 'sr-service-var-row';
					row.style.cssText = 'display:flex;gap:10px;margin:10px 0;align-items:center;';

					row.innerHTML =
						'<input style="flex:1" type="text" name="sr_service_variations['+idx+'][label]" placeholder="<?php echo esc_js( __( 'Label (e.g. Upper jaw)', 'service-requests-form' ) ); ?>" />' +
						'<input style="flex:1" type="text" name="sr_service_variations['+idx+'][value]" placeholder="<?php echo esc_js( __( 'Value (e.g. upper_jaw)', 'service-requests-form' ) ); ?>" />' +
						'<button type="button" class="button sr-service-var-remove" aria-label="<?php echo esc_js( __( 'Remove', 'service-requests-form' ) ); ?>">×</button>';

					wrap.appendChild(row);
				});
			})();
			</script>
			<?php
		}

		/**
		 * Save service meta (gallery IDs + variations).
		 *
		 * @param int     $post_id
		 * @param WP_Post $post
		 */
		public static function save_service_meta( $post_id, $post ) {

			// Safety: correct post type.
			if ( ! $post || $post->post_type !== 'sr_service' ) {
				return;
			}

			// Autosave? Bail.
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}

			// Check user capability.
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}

			/**
			 * 1) Save gallery IDs
			 */
			if (
				isset( $_POST['sr_service_gallery_nonce'] ) &&
				wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sr_service_gallery_nonce'] ) ), 'sr_service_gallery_nonce_action' )
			) {
				if ( isset( $_POST['sr_service_gallery_ids'] ) ) {
					$ids_raw = sanitize_text_field( wp_unslash( $_POST['sr_service_gallery_ids'] ) );
					$ids     = array_filter( array_map( 'absint', explode( ',', $ids_raw ) ) );

					update_post_meta( $post_id, self::META_GALLERY_IDS, $ids );
				} else {
					delete_post_meta( $post_id, self::META_GALLERY_IDS );
				}
			}

			/**
			 * 2) Save variations
			 */
			if (
				isset( $_POST['sr_service_variations_nonce'] ) &&
				wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sr_service_variations_nonce'] ) ), 'sr_service_variations_nonce_action' )
			) {
				$rows  = ( isset( $_POST['sr_service_variations'] ) && is_array( $_POST['sr_service_variations'] ) )
					? (array) $_POST['sr_service_variations']
					: array();

				$clean = array();

				foreach ( $rows as $r ) {
					$label = isset( $r['label'] ) ? sanitize_text_field( wp_unslash( $r['label'] ) ) : '';
					$value = isset( $r['value'] ) ? sanitize_key( wp_unslash( $r['value'] ) ) : '';

					if ( $label === '' || $value === '' ) {
						continue;
					}

					$clean[] = array(
						'label' => $label,
						'value' => $value,
					);
				}

				if ( empty( $clean ) ) {
					delete_post_meta( $post_id, self::META_VARIATIONS );
				} else {
					update_post_meta( $post_id, self::META_VARIATIONS, $clean );
				}
			}
		}

		/**
		 * Enqueue admin assets for sr_service edit screens.
		 *
		 * @param string $hook
		 */
		public static function enqueue_admin_assets( $hook ) {
			global $post;

			if ( $hook === 'post-new.php' || $hook === 'post.php' ) {
				if ( isset( $post ) && $post->post_type === 'sr_service' ) {

					wp_enqueue_media();

					wp_enqueue_script(
						'srf-admin-service-gallery',
						SRF_PLUGIN_URL . 'assets/js/admin.js',
						array( 'jquery' ),
						SRF_VERSION,
						true
					);

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

			if ( isset( $columns['cb'] ) ) {
				$new['cb'] = $columns['cb'];
				unset( $columns['cb'] );
			}

			$new['sr_service_thumb']   = __( 'Thumbnail', 'service-requests-form' );
			$new['title']              = __( 'Service', 'service-requests-form' );
			$new['sr_service_images']  = __( 'Images', 'service-requests-form' );
			$new['sr_service_variants'] = __( 'Variants', 'service-requests-form' );

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
				return;
			}

			if ( $column === 'sr_service_images' ) {
				$gallery_ids = get_post_meta( $post_id, self::META_GALLERY_IDS, true );
				if ( is_array( $gallery_ids ) && ! empty( $gallery_ids ) ) {
					printf(
						esc_html__( '%d image(s)', 'service-requests-form' ),
						count( $gallery_ids )
					);
				} else {
					esc_html_e( 'No images', 'service-requests-form' );
				}
				return;
			}

			if ( $column === 'sr_service_variants' ) {
				$vars = get_post_meta( $post_id, self::META_VARIATIONS, true );
				if ( is_array( $vars ) && ! empty( $vars ) ) {
					echo esc_html( (string) count( $vars ) );
				} else {
					echo '&mdash;';
				}
				return;
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

		/**
		 * Helper: get variations array for a service.
		 *
		 * @param int $service_id
		 *
		 * @return array[]
		 */
		public static function get_variations( $service_id ) {
			$vars = get_post_meta( $service_id, self::META_VARIATIONS, true );
			return is_array( $vars ) ? $vars : array();
		}
	}

}
