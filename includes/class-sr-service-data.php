<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'SR_Service_Data' ) ) {

    /**
     * Helper methods for working with sr_service posts.
     */
    class SR_Service_Data {

        /**
         * Get services for dropdown (ID + label).
         *
         * @return array[]
         */
        public static function get_services_for_dropdown() {
            $args = array(
                'post_type'      => 'sr_service',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'orderby'        => 'menu_order title',
                'order'          => 'ASC',
            );

            $posts = get_posts( $args );
            $services = array();

            foreach ( $posts as $post ) {
				// Featured image (thumbnail) for service picker UI.
				$thumb_url = get_the_post_thumbnail_url( $post, 'medium' );
				if ( ! $thumb_url ) {
					$thumb_url = '';
				}

                $services[] = array(
                    'id'    => $post->ID,
					'title' => get_the_title( $post ),
					'thumb' => $thumb_url,
                );
            }

            return $services;
        }

        /**
         * Get full data for a single service (title, content, images).
         *
         * @param int $service_id
         *
         * @return array|null
         */
        public static function get_service_data( $service_id ) {
            $service_id = absint( $service_id );
            if ( ! $service_id ) {
                return null;
            }

            $post = get_post( $service_id );
            if ( ! $post || $post->post_type !== 'sr_service' || $post->post_status !== 'publish' ) {
                return null;
            }

            $title   = get_the_title( $post );
			$thumb_url = get_the_post_thumbnail_url( $post, 'medium' );
			if ( ! $thumb_url ) {
				$thumb_url = '';
			}
            

            $raw = $post->post_content;

            // Prevent shortcodes (or Elementor blocks) from rendering a second form
            $raw = strip_shortcodes( $raw );

            // Keep simple formatting only
            $content = wp_kses_post( wpautop( $raw ) );


            // Gallery images
            if ( ! class_exists( 'SR_Services_CPT' ) ) {
                $images = array();
            } else {
                $ids    = SR_Services_CPT::get_gallery_ids( $service_id );
                $images = array();

                foreach ( $ids as $attachment_id ) {
                    $url = wp_get_attachment_image_url( $attachment_id, 'large' );
                    if ( ! $url ) {
                        continue;
                    }
                    $alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
                    if ( '' === $alt ) {
                        $alt = $title;
                    }

                    $images[] = array(
                        'id'  => $attachment_id,
                        'url' => $url,
                        'alt' => $alt,
                    );
                }
            }

			// Variant groups (key => values) for the service
			$variant_groups = array();
			if ( class_exists( 'SR_Services_CPT' ) && method_exists( 'SR_Services_CPT', 'get_variations' ) ) {
				$raw_vars = SR_Services_CPT::get_variations( $service_id );
				if ( is_array( $raw_vars ) ) {
					foreach ( $raw_vars as $row ) {
						// New format: ['key' => '...', 'values' => ['...']]
						if ( isset( $row['key'] ) && isset( $row['values'] ) && is_array( $row['values'] ) ) {
							$k = trim( sanitize_text_field( $row['key'] ) );
							$vals = array();
							foreach ( $row['values'] as $v ) {
								$v = trim( sanitize_text_field( $v ) );
								if ( $v !== '' ) $vals[] = $v;
							}
							if ( $k !== '' && ! empty( $vals ) ) {
								$unique_vals = array_values( array_unique( $vals ) );
								$raw_prices = isset( $row['prices'] ) && is_array( $row['prices'] ) ? $row['prices'] : array();
								$prices = array();
								foreach ( $unique_vals as $uv ) {
									$prices[ $uv ] = isset( $raw_prices[ $uv ] ) ? max( 0, (float) $raw_prices[ $uv ] ) : 0;
								}
								$variant_groups[] = array(
									'key'    => $k,
									'values' => $unique_vals,
									'prices' => $prices,
								);
							}
							continue;
						}

						// Back-compat: old format rows ['label' => 'Upper jaw', 'value' => 'upper_jaw']
						if ( isset( $row['label'] ) ) {
							$lbl = trim( sanitize_text_field( $row['label'] ) );
							if ( $lbl !== '' ) {
								$variant_groups[] = array(
									'key'    => __( 'Variant', 'service-requests-form' ),
									'values' => array( $lbl ),
								);
							}
						}
					}
				}
			}

			$video_url         = '';
			$video_title       = '';
			$video_description = '';
			$video_embed       = '';

			if ( class_exists( 'SR_Services_CPT' ) && method_exists( 'SR_Services_CPT', 'get_video_data' ) ) {
				$video = SR_Services_CPT::get_video_data( $service_id );
				if ( is_array( $video ) ) {
					$video_url         = isset( $video['url'] ) ? (string) $video['url'] : '';
					$video_title       = isset( $video['title'] ) ? (string) $video['title'] : '';
					$video_description = isset( $video['description'] ) ? (string) $video['description'] : '';
				}
			}

			if ( $video_url !== '' ) {
				$oembed = wp_oembed_get( $video_url );
				if ( is_string( $oembed ) && $oembed !== '' ) {
					$video_embed = $oembed;
				} else {
					$video_path = wp_parse_url( $video_url, PHP_URL_PATH );
					$ext = is_string( $video_path ) ? strtolower( pathinfo( $video_path, PATHINFO_EXTENSION ) ) : '';
					if ( in_array( $ext, array( 'mp4', 'webm', 'ogg' ), true ) ) {
						$video_embed = '<video controls preload="metadata" src="' . esc_url( $video_url ) . '"></video>';
					}
				}
			}

            return array(
                'id'      => $service_id,
                'title'   => $title,
				'thumb'   => $thumb_url,
                'content' => $content,
                'images'  => $images,
                'variants'=> $variant_groups,
				'base_price' => class_exists( 'SRF_WooCommerce' ) ? SRF_WooCommerce::get_base_price( $service_id ) : 0,
				'video'   => array(
					'url'         => $video_url,
					'title'       => $video_title,
					'description' => $video_description,
					'embed'       => $video_embed,
				),
            );
        }


        public static function is_valid_service_id( $service_id ) {
            $service_id = absint( $service_id );
            if ( ! $service_id ) {
                return false;
            }

            $post = get_post( $service_id );
            return ( $post && $post->post_type === 'sr_service' );
        }


        /**
         * Get all services data keyed by ID (useful later for JS).
         *
         * @return array
         */
        public static function get_all_services_data() {
            $services = self::get_services_for_dropdown();
            $data     = array();

            foreach ( $services as $service ) {
                $service_data = self::get_service_data( $service['id'] );
                if ( $service_data ) {
                    $data[ $service['id'] ] = $service_data;
                }
            }

            return $data;
        }
    }
}
