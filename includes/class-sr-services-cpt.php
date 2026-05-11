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
		 * Meta key for service variant groups:
		 * [
		 *   [
		 *     'key'    => 'Height',
		 *     'values' => ['2m','3m','7.5m']
		 *   ],
		 *   ...
		 * ]
		 */
		const META_VARIATIONS = '_sr_service_variations';
		const META_VIDEO_URL  = '_sr_service_video_url';
		const META_VIDEO_TITLE = '_sr_service_video_title';
		const META_VIDEO_DESCRIPTION = '_sr_service_video_description';
		const META_BASE_PRICE = '_sr_service_base_price';
		const META_DIRECT_PURCHASABLE = '_sr_service_direct_purchasable';

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
				'sr_service_pricing',
				__( 'Service Pricing / WooCommerce Product', 'service-requests-form' ),
				array( __CLASS__, 'render_pricing_metabox' ),
				'sr_service',
				'side',
				'high'
			);

			add_meta_box(
				'sr_service_variations',
				__( 'Service Variations', 'service-requests-form' ),
				array( __CLASS__, 'render_variations_metabox' ),
				'sr_service',
				'normal',
				'default'
			);

			add_meta_box(
				'sr_service_video',
				__( 'Service Video', 'service-requests-form' ),
				array( __CLASS__, 'render_video_metabox' ),
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
		 * Render pricing meta box.
		 *
		 * @param WP_Post $post
		 */
		public static function render_pricing_metabox( $post ) {
			wp_nonce_field( 'sr_service_pricing_nonce_action', 'sr_service_pricing_nonce' );
			$price = (float) get_post_meta( $post->ID, self::META_BASE_PRICE, true );
			$direct_purchasable = 'yes' === get_post_meta( $post->ID, self::META_DIRECT_PURCHASABLE, true );
			$product_id = (int) get_post_meta( $post->ID, '_sr_wc_product_id', true );
			?>
			<p>
				<label for="sr_service_base_price"><strong><?php esc_html_e( 'Base price', 'service-requests-form' ); ?></strong></label>
				<input type="number" min="0" step="0.01" id="sr_service_base_price" name="sr_service_base_price" value="<?php echo esc_attr( $price ); ?>" style="width:100%;" />
			</p>
			<p>
				<label for="sr_service_direct_purchasable">
					<input type="checkbox" id="sr_service_direct_purchasable" name="sr_service_direct_purchasable" value="yes" <?php checked( $direct_purchasable ); ?> />
					<strong><?php esc_html_e( 'Purchasable directly in WooCommerce shop', 'service-requests-form' ); ?></strong>
				</label>
			</p>
			<p class="description">
				<?php esc_html_e( 'Unchecked: customers must submit the service request form first. Checked: customers can also buy this service directly from the shop/product page.', 'service-requests-form' ); ?>
			</p>
			<p class="description">
				<?php esc_html_e( 'The base price is synced to the linked WooCommerce product. Variant extra costs are added after the service request form is submitted.', 'service-requests-form' ); ?>
			</p>
			<?php if ( $product_id && get_post_type( $product_id ) === 'product' ) : ?>
				<p><a class="button" href="<?php echo esc_url( get_edit_post_link( $product_id ) ); ?>"><?php esc_html_e( 'Edit linked product', 'service-requests-form' ); ?></a></p>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'The WooCommerce product will be created automatically when this service is saved.', 'service-requests-form' ); ?></p>
			<?php endif; ?>
			<?php
		}

		/**
		 * Render variations meta box.
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
				<?php esc_html_e( 'Add variant groups for this service (optional). To add extra cost, write values like: 2m|0, 3m|20, Express|50.', 'service-requests-form' ); ?>
			</p>

			<div id="sr-service-variations-wrap">
				<?php foreach ( $rows as $i => $row ) :
					$key    = isset( $row['key'] ) ? (string) $row['key'] : '';
					$values = '';

					if ( isset( $row['values'] ) && is_array( $row['values'] ) ) {
						
						$parts = array();
						$prices = isset( $row['prices'] ) && is_array( $row['prices'] ) ? $row['prices'] : array();
						foreach ( $row['values'] as $vv ) {
							$vv = sanitize_text_field( $vv );
							$extra = isset( $prices[ $vv ] ) ? (float) $prices[ $vv ] : 0;
							$parts[] = $extra > 0 ? $vv . '|' . $extra : $vv;
						}
						$values = implode( ', ', $parts );
					} elseif ( isset( $row['values'] ) && is_string( $row['values'] ) ) {
						$values = (string) $row['values'];
					}
					?>
					<div class="sr-service-var-row" style="margin:12px 0;padding:12px;border:1px solid #e5e5e5;border-radius:6px;">
						<div style="display:flex;gap:10px;align-items:center;">
							<input
								style="flex:1"
								type="text"
								name="sr_service_variations[<?php echo esc_attr( $i ); ?>][key]"
								placeholder="<?php echo esc_attr__( 'Key (e.g. Height)', 'service-requests-form' ); ?>"
								value="<?php echo esc_attr( $key ); ?>"
							/>
							<button type="button" class="button sr-service-var-remove" aria-label="<?php echo esc_attr__( 'Remove', 'service-requests-form' ); ?>">×</button>
						</div>

						<textarea
							style="width:100%;margin-top:10px;"
							rows="2"
							name="sr_service_variations[<?php echo esc_attr( $i ); ?>][values]"
							placeholder="<?php echo esc_attr__( 'Values (comma separated) e.g. 2m|0, 3m|20, 7.5m|50', 'service-requests-form' ); ?>"
						><?php echo esc_textarea( $values ); ?></textarea>
					</div>
				<?php endforeach; ?>
			</div>

			<p>
				<button type="button" class="button button-primary" id="sr-service-add-variation">
					<?php esc_html_e( 'Add variant group', 'service-requests-form' ); ?>
				</button>
			</p>

			<script>
			(function(){
				var wrap = document.getElementById('sr-service-variations-wrap');
				var btn  = document.getElementById('sr-service-add-variation');
				if (!wrap || !btn) return;

				function wireRow(row){
					var rm = row.querySelector('.sr-service-var-remove');
					if (rm){
						rm.addEventListener('click', function(){
							row.remove();
							reindex();
						});
					}
				}

				function reindex() {
					var rows = wrap.querySelectorAll('.sr-service-var-row');
					rows.forEach(function(row, idx){
						row.querySelectorAll('input, textarea').forEach(function(el){
							if (!el.name) return;
							el.name = el.name.replace(/sr_service_variations\[\d+\]/, 'sr_service_variations['+idx+']');
						});
					});
				}

				wrap.querySelectorAll('.sr-service-var-row').forEach(wireRow);

				btn.addEventListener('click', function(e){
					e.preventDefault();

					var idx = wrap.querySelectorAll('.sr-service-var-row').length;

					var html =
						'<div style="display:flex;gap:10px;align-items:center;">' +
							'<input style="flex:1" type="text" name="sr_service_variations['+idx+'][key]" placeholder="<?php echo esc_js( __( 'Key (e.g. Height)', 'service-requests-form' ) ); ?>" />' +
							'<button type="button" class="button sr-service-var-remove" aria-label="<?php echo esc_js( __( 'Remove', 'service-requests-form' ) ); ?>">×</button>' +
						'</div>' +
						'<textarea style="width:100%;margin-top:10px;" rows="2" name="sr_service_variations['+idx+'][values]" placeholder="<?php echo esc_js( __( 'Values (comma separated) e.g. 2m|0, 3m|20, 7.5m|50', 'service-requests-form' ) ); ?>"></textarea>';

					var row = document.createElement('div');
					row.className = 'sr-service-var-row';
					row.style.margin = '12px 0';
					row.style.padding = '12px';
					row.style.border = '1px solid #e5e5e5';
					row.style.borderRadius = '6px';
					row.innerHTML = html;

					wrap.appendChild(row);
					wireRow(row);
					reindex();
				});
			})();
			</script>
			<?php
		}

		/**
		 * Render service video meta box.
		 *
		 * @param WP_Post $post
		 */
		public static function render_video_metabox( $post ) {
			wp_nonce_field( 'sr_service_video_nonce_action', 'sr_service_video_nonce' );

			$video_url   = (string) get_post_meta( $post->ID, self::META_VIDEO_URL, true );
			$video_title = (string) get_post_meta( $post->ID, self::META_VIDEO_TITLE, true );
			$video_desc  = (string) get_post_meta( $post->ID, self::META_VIDEO_DESCRIPTION, true );
			?>
			<p class="description">
				<?php esc_html_e( 'Add an optional service video that appears at the top of the service information area on the frontend.', 'service-requests-form' ); ?>
			</p>

			<p>
				<label for="sr_service_video_url"><strong><?php esc_html_e( 'Video URL', 'service-requests-form' ); ?></strong></label><br />
				<input
					type="url"
					id="sr_service_video_url"
					name="sr_service_video_url"
					value="<?php echo esc_attr( $video_url ); ?>"
					placeholder="<?php echo esc_attr__( 'https://...', 'service-requests-form' ); ?>"
					style="width:100%;max-width:680px;"
				/>
			</p>

			<p>
				<label for="sr_service_video_title"><strong><?php esc_html_e( 'Video title', 'service-requests-form' ); ?></strong></label><br />
				<input
					type="text"
					id="sr_service_video_title"
					name="sr_service_video_title"
					value="<?php echo esc_attr( $video_title ); ?>"
					style="width:100%;max-width:680px;"
				/>
			</p>

			<p>
				<label for="sr_service_video_description"><strong><?php esc_html_e( 'Video description', 'service-requests-form' ); ?></strong></label><br />
				<textarea
					id="sr_service_video_description"
					name="sr_service_video_description"
					rows="4"
					style="width:100%;max-width:680px;"
				><?php echo esc_textarea( $video_desc ); ?></textarea>
			</p>
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
			 * 0) Save pricing
			 */
			if (
				isset( $_POST['sr_service_pricing_nonce'] ) &&
				wp_verify_nonce(
					sanitize_text_field( wp_unslash( $_POST['sr_service_pricing_nonce'] ) ),
					'sr_service_pricing_nonce_action'
				)
			) {
				$base_price = isset( $_POST['sr_service_base_price'] ) ? (float) wp_unslash( $_POST['sr_service_base_price'] ) : 0;
				update_post_meta( $post_id, self::META_BASE_PRICE, max( 0, $base_price ) );
				$direct_purchasable = isset( $_POST['sr_service_direct_purchasable'] ) && 'yes' === sanitize_text_field( wp_unslash( $_POST['sr_service_direct_purchasable'] ) );
				update_post_meta( $post_id, self::META_DIRECT_PURCHASABLE, $direct_purchasable ? 'yes' : 'no' );
			}

			/**
			 * 1) Save gallery IDs
			 */
			if (
				isset( $_POST['sr_service_gallery_nonce'] ) &&
				wp_verify_nonce(
					sanitize_text_field( wp_unslash( $_POST['sr_service_gallery_nonce'] ) ),
					'sr_service_gallery_nonce_action'
				)
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
				wp_verify_nonce(
					sanitize_text_field( wp_unslash( $_POST['sr_service_variations_nonce'] ) ),
					'sr_service_variations_nonce_action'
				)
			) {

				$rows  = ( isset( $_POST['sr_service_variations'] ) && is_array( $_POST['sr_service_variations'] ) )
					? (array) $_POST['sr_service_variations']
					: array();

				$clean = array();

				foreach ( $rows as $r ) {
					$key_raw    = isset( $r['key'] ) ? sanitize_text_field( wp_unslash( $r['key'] ) ) : '';
					$values_raw = isset( $r['values'] ) ? (string) wp_unslash( $r['values'] ) : '';

					$key_raw = trim( $key_raw );
					if ( $key_raw === '' ) {
						continue;
					}

					$vals = array();

					$prices = array();
					if ( trim( $values_raw ) !== '' ) {
						$parts = array_map( 'trim', explode( ',', $values_raw ) );
						foreach ( $parts as $p ) {
							$p = sanitize_text_field( $p );
							if ( $p === '' ) {
								continue;
							}
							$extra = 0;
							if ( strpos( $p, '|' ) !== false ) {
								list( $p, $extra_raw ) = array_map( 'trim', explode( '|', $p, 2 ) );
								$p = sanitize_text_field( $p );
								$extra = max( 0, (float) str_replace( ',', '.', $extra_raw ) );
							}
							if ( $p !== '' ) {
								$vals[] = $p;
								$prices[ $p ] = $extra;
							}
						}
					}

					if ( empty( $vals ) ) {
						continue;
					}

					$unique_vals = array_values( array_unique( $vals ) );
					$clean_prices = array();
					foreach ( $unique_vals as $uv ) {
						$clean_prices[ $uv ] = isset( $prices[ $uv ] ) ? max( 0, (float) $prices[ $uv ] ) : 0;
					}
					$clean[] = array(
						'key'    => $key_raw,
						'values' => $unique_vals,
						'prices' => $clean_prices,
					);
				}

				if ( empty( $clean ) ) {
					delete_post_meta( $post_id, self::META_VARIATIONS );
				} else {
					update_post_meta( $post_id, self::META_VARIATIONS, $clean );
				}
			}

			/**
			 * 3) Save service video fields
			 */
			if (
				isset( $_POST['sr_service_video_nonce'] ) &&
				wp_verify_nonce(
					sanitize_text_field( wp_unslash( $_POST['sr_service_video_nonce'] ) ),
					'sr_service_video_nonce_action'
				)
			) {
				$video_url   = isset( $_POST['sr_service_video_url'] ) ? esc_url_raw( wp_unslash( $_POST['sr_service_video_url'] ) ) : '';
				$video_title = isset( $_POST['sr_service_video_title'] ) ? sanitize_text_field( wp_unslash( $_POST['sr_service_video_title'] ) ) : '';
				$video_desc  = isset( $_POST['sr_service_video_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['sr_service_video_description'] ) ) : '';

				if ( $video_url !== '' ) {
					update_post_meta( $post_id, self::META_VIDEO_URL, $video_url );
				} else {
					delete_post_meta( $post_id, self::META_VIDEO_URL );
				}

				if ( $video_title !== '' ) {
					update_post_meta( $post_id, self::META_VIDEO_TITLE, $video_title );
				} else {
					delete_post_meta( $post_id, self::META_VIDEO_TITLE );
				}

				if ( $video_desc !== '' ) {
					update_post_meta( $post_id, self::META_VIDEO_DESCRIPTION, $video_desc );
				} else {
					delete_post_meta( $post_id, self::META_VIDEO_DESCRIPTION );
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

			$new['sr_service_thumb']    = __( 'Thumbnail', 'service-requests-form' );
			$new['title']               = __( 'Service', 'service-requests-form' );
			$new['sr_service_images']   = __( 'Images', 'service-requests-form' );
			$new['sr_service_price']    = __( 'Base price', 'service-requests-form' );
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

			if ( $column === 'sr_service_price' ) {
				$price = (float) get_post_meta( $post_id, self::META_BASE_PRICE, true );
				echo esc_html( function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( $price ) ) : number_format_i18n( $price, 2 ) );
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

		/**
		 * Helper: get service video data.
		 *
		 * @param int $service_id
		 *
		 * @return array
		 */
		public static function get_video_data( $service_id ) {
			return array(
				'url'         => (string) get_post_meta( $service_id, self::META_VIDEO_URL, true ),
				'title'       => (string) get_post_meta( $service_id, self::META_VIDEO_TITLE, true ),
				'description' => (string) get_post_meta( $service_id, self::META_VIDEO_DESCRIPTION, true ),
			);
		}
	}
}
