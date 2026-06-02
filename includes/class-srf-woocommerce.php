<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SRF_WooCommerce' ) ) {
	class SRF_WooCommerce {
		protected static $adding_service_to_cart = false;

		const OPTION_FORM_PAGE_ID = 'srf_service_form_page_id';
		const OPTION_AFTER_SUBMIT = 'srf_service_after_submit';
		const OPTION_CATEGORY_ID  = 'srf_service_product_category_id';

		const META_PRODUCT_ID = '_sr_wc_product_id';
		const META_BASE_PRICE = '_sr_service_base_price';
		const META_DIRECT_PURCHASABLE = '_sr_service_direct_purchasable';

		public static function init() {
			add_action( 'save_post_sr_service', array( __CLASS__, 'sync_product_from_service' ), 30, 2 );
			add_filter( 'woocommerce_is_purchasable', array( __CLASS__, 'service_product_not_directly_purchasable' ), 10, 2 );
			add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate_service_product_add_to_cart' ), 10, 5 );
			add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render_service_product_request_button' ), 31 );
			add_filter( 'woocommerce_loop_add_to_cart_link', array( __CLASS__, 'replace_loop_add_to_cart_link' ), 10, 3 );
			add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply_cart_item_prices' ), 20 );
			add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'display_cart_item_meta' ), 10, 2 );
			add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'add_order_line_item_meta' ), 10, 4 );
			add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'link_order_to_requests' ), 20, 3 );
			add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'mark_requests_paid' ) );
			add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'mark_requests_paid' ) );
		}

		public static function is_available() {
			return class_exists( 'WooCommerce' ) && function_exists( 'WC' );
		}

		public static function get_form_page_url( $service_id = 0 ) {
			$page_id = (int) get_option( self::OPTION_FORM_PAGE_ID, 0 );
			$url = $page_id > 0 ? get_permalink( $page_id ) : home_url( '/' );
			if ( ! $url ) {
				$url = home_url( '/' );
			}
			if ( $service_id > 0 ) {
				$url = add_query_arg( 'srf_service', (int) $service_id, $url );
			}
			return $url;
		}

		public static function get_base_price( $service_id ) {
			return max( 0, (float) get_post_meta( (int) $service_id, self::META_BASE_PRICE, true ) );
		}

		public static function is_service_direct_purchasable( $service_id ) {
			return 'yes' === get_post_meta( (int) $service_id, self::META_DIRECT_PURCHASABLE, true );
		}

		public static function calculate_service_price( $service_id, $selected_variants = array(), $quantity = 1 ) {
			$service_id = (int) $service_id;
			$quantity = max( 1, (int) $quantity );
			$total = self::get_base_price( $service_id );
			$extras = array();
			$variant_defs = class_exists( 'SR_Services_CPT' ) ? SR_Services_CPT::get_variations( $service_id ) : array();
			if ( is_array( $variant_defs ) && is_array( $selected_variants ) ) {
				foreach ( $variant_defs as $group ) {
					$key = isset( $group['key'] ) ? (string) $group['key'] : '';
					$chosen = isset( $selected_variants[ $key ] ) ? (string) $selected_variants[ $key ] : '';
					if ( '' === $key || '' === $chosen ) {
						continue;
					}
					$prices = isset( $group['prices'] ) && is_array( $group['prices'] ) ? $group['prices'] : array();
					$extra = isset( $prices[ $chosen ] ) ? (float) $prices[ $chosen ] : 0;
					if ( $extra > 0 ) {
						$total += $extra;
						$extras[] = array( 'label' => $key . ': ' . $chosen, 'amount' => $extra );
					}
				}
			}
			$unit_total = max( 0, $total );
			return array(
				'base'       => self::get_base_price( $service_id ),
				'extras'     => $extras,
				'unit_total' => $unit_total,
				'quantity'   => $quantity,
				'total'      => max( 0, $unit_total * $quantity ),
			);
		}

		public static function ensure_category() {
			$cat_id = (int) get_option( self::OPTION_CATEGORY_ID, 0 );
			if ( $cat_id > 0 && term_exists( $cat_id, 'product_cat' ) ) {
				return $cat_id;
			}
			$term = term_exists( 'Services', 'product_cat' );
			if ( ! $term ) {
				$term = wp_insert_term( 'Services', 'product_cat', array( 'slug' => 'services' ) );
			}
			if ( ! is_wp_error( $term ) ) {
				$cat_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
				update_option( self::OPTION_CATEGORY_ID, $cat_id, false );
				return $cat_id;
			}
			return 0;
		}

		public static function sync_product_from_service( $post_id, $post ) {
			if ( ! self::is_available() || ! $post || 'sr_service' !== $post->post_type || 'auto-draft' === $post->post_status ) {
				return;
			}
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}
			$product_id = (int) get_post_meta( $post_id, self::META_PRODUCT_ID, true );
			$product_post = $product_id ? get_post( $product_id ) : null;
			if ( ! $product_post || 'product' !== $product_post->post_type ) {
				$product_id = wp_insert_post( array(
					'post_type' => 'product',
					'post_status' => 'publish',
					'post_title' => get_the_title( $post_id ),
					'post_content' => $post->post_content,
				) );
				if ( is_wp_error( $product_id ) || ! $product_id ) {
					return;
				}
				update_post_meta( $post_id, self::META_PRODUCT_ID, (int) $product_id );
				update_post_meta( $product_id, '_sr_service_id', (int) $post_id );
			}
			wp_update_post( array( 'ID' => $product_id, 'post_title' => get_the_title( $post_id ), 'post_content' => $post->post_content, 'post_status' => 'publish' ) );
			wp_set_object_terms( $product_id, 'simple', 'product_type' );
			$cat_id = self::ensure_category();
			if ( $cat_id ) {
				wp_set_object_terms( $product_id, array( $cat_id ), 'product_cat', false );
			}
			$price = self::get_base_price( $post_id );
			update_post_meta( $product_id, '_regular_price', wc_format_decimal( $price ) );
			update_post_meta( $product_id, '_price', wc_format_decimal( $price ) );
			update_post_meta( $product_id, '_virtual', 'yes' );
			update_post_meta( $product_id, '_sold_individually', 'no' );
			update_post_meta( $product_id, '_stock_status', 'instock' );
			update_post_meta( $product_id, '_manage_stock', 'no' );
			update_post_meta( $product_id, '_catalog_visibility', 'visible' );
			update_post_meta( $product_id, '_sr_service_direct_purchasable', self::is_service_direct_purchasable( $post_id ) ? 'yes' : 'no' );
			if ( has_post_thumbnail( $post_id ) ) {
				set_post_thumbnail( $product_id, get_post_thumbnail_id( $post_id ) );
			}
		}

		public static function get_product_id_for_service( $service_id ) {
			$product_id = (int) get_post_meta( (int) $service_id, self::META_PRODUCT_ID, true );
			if ( $product_id > 0 && get_post_type( $product_id ) === 'product' ) {
				return $product_id;
			}
			return 0;
		}

		public static function service_product_not_directly_purchasable( $purchasable, $product ) {
			// Service products must remain purchasable for WooCommerce cart/checkout validation.
			// Direct shop purchases are controlled by validate_service_product_add_to_cart().
			return $purchasable;
		}

		public static function validate_service_product_add_to_cart( $passed, $product_id, $quantity, $variation_id = 0, $variations = array() ) {
			$service_id = (int) get_post_meta( (int) $product_id, '_sr_service_id', true );
			if ( $service_id <= 0 ) {
				return $passed;
			}

			if ( self::$adding_service_to_cart || self::is_service_direct_purchasable( $service_id ) ) {
				return $passed;
			}

			if ( function_exists( 'wc_add_notice' ) ) {
				wc_add_notice( __( 'Please submit the service request form before purchasing this service.', 'service-requests-form' ), 'error' );
			}
			return false;
		}

		public static function render_service_product_request_button() {
			global $product;
			if ( ! $product ) { return; }
			$service_id = (int) get_post_meta( $product->get_id(), '_sr_service_id', true );
			if ( $service_id > 0 && ! self::is_service_direct_purchasable( $service_id ) ) {
				echo '<p><a class="button alt" href="' . esc_url( self::get_form_page_url( $service_id ) ) . '">' . esc_html__( 'Submit service request', 'service-requests-form' ) . '</a></p>';
			}
		}

		public static function replace_loop_add_to_cart_link( $html, $product, $args ) {
			$service_id = $product ? (int) get_post_meta( $product->get_id(), '_sr_service_id', true ) : 0;
			if ( $service_id > 0 && ! self::is_service_direct_purchasable( $service_id ) ) {
				return '<a class="button" href="' . esc_url( self::get_form_page_url( $service_id ) ) . '">' . esc_html__( 'Request service', 'service-requests-form' ) . '</a>';
			}
			return $html;
		}

		public static function add_request_to_cart( $request_id, $service_id, $selected_variants = array(), $quantity = 1 ) {
			if ( ! self::is_available() || ! WC()->cart ) {
				return false;
			}
			$quantity = max( 1, (int) $quantity );
			$product_id = self::get_product_id_for_service( $service_id );
			if ( ! $product_id ) {
				self::sync_product_from_service( $service_id, get_post( $service_id ) );
				$product_id = self::get_product_id_for_service( $service_id );
			}
			if ( ! $product_id ) {
				return false;
			}
			$price = self::calculate_service_price( $service_id, $selected_variants, $quantity );
			WC()->cart->empty_cart();
			self::$adding_service_to_cart = true;
			$key = WC()->cart->add_to_cart( $product_id, $quantity, 0, array(), array(
				'srf_request_id' => (int) $request_id,
				'srf_service_id' => (int) $service_id,
				'srf_variants' => is_array( $selected_variants ) ? $selected_variants : array(),
				'srf_quantity' => $quantity,
				'srf_price' => isset( $price['unit_total'] ) ? (float) $price['unit_total'] : (float) $price['total'],
				'srf_price_breakdown' => $price,
			) );
			self::$adding_service_to_cart = false;
			return (bool) $key;
		}

		public static function get_after_submit_url() {
			$target = (string) get_option( self::OPTION_AFTER_SUBMIT, 'checkout' );
			return ( 'cart' === $target && function_exists( 'wc_get_cart_url' ) ) ? wc_get_cart_url() : wc_get_checkout_url();
		}

		public static function apply_cart_item_prices( $cart ) {
			if ( is_admin() && ! defined( 'DOING_AJAX' ) ) { return; }
			if ( ! $cart ) { return; }
			foreach ( $cart->get_cart() as $cart_item ) {
				if ( isset( $cart_item['srf_price'], $cart_item['data'] ) && is_object( $cart_item['data'] ) ) {
					$cart_item['data']->set_price( (float) $cart_item['srf_price'] );
				}
			}
		}

		public static function display_cart_item_meta( $item_data, $cart_item ) {
			if ( ! empty( $cart_item['srf_request_id'] ) ) {
				$item_data[] = array( 'name' => __( 'Service Request', 'service-requests-form' ), 'value' => '#' . (int) $cart_item['srf_request_id'] );
			}
			if ( ! empty( $cart_item['srf_quantity'] ) ) {
				$item_data[] = array( 'name' => __( 'Quantity', 'service-requests-form' ), 'value' => (int) $cart_item['srf_quantity'] );
			}
			if ( ! empty( $cart_item['srf_variants'] ) && is_array( $cart_item['srf_variants'] ) ) {
				foreach ( $cart_item['srf_variants'] as $k => $v ) {
					$item_data[] = array( 'name' => (string) $k, 'value' => (string) $v );
				}
			}
			return $item_data;
		}

		public static function add_order_line_item_meta( $item, $cart_item_key, $values, $order ) {
			if ( empty( $values['srf_request_id'] ) ) { return; }
			$item->add_meta_data( __( 'Service Request ID', 'service-requests-form' ), (int) $values['srf_request_id'], true );
			if ( ! empty( $values['srf_quantity'] ) ) {
				$item->add_meta_data( __( 'Quantity', 'service-requests-form' ), (int) $values['srf_quantity'], true );
			}
			if ( ! empty( $values['srf_variants'] ) && is_array( $values['srf_variants'] ) ) {
				foreach ( $values['srf_variants'] as $k => $v ) {
					$item->add_meta_data( (string) $k, (string) $v, true );
				}
			}
		}

		public static function link_order_to_requests( $order_id, $posted_data, $order ) {
			if ( ! $order ) { $order = wc_get_order( $order_id ); }
			if ( ! $order ) { return; }
			foreach ( $order->get_items() as $item ) {
				$request_id = (int) $item->get_meta( __( 'Service Request ID', 'service-requests-form' ), true );
				if ( $request_id > 0 ) {
					update_post_meta( $request_id, '_sr_wc_order_id', (int) $order_id );
					update_post_meta( $request_id, '_sr_status', 'pending-payment' );
				}
			}
		}

		public static function mark_requests_paid( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) { return; }
			foreach ( $order->get_items() as $item ) {
				$request_id = (int) $item->get_meta( __( 'Service Request ID', 'service-requests-form' ), true );
				if ( $request_id > 0 ) {
					update_post_meta( $request_id, '_sr_wc_order_id', (int) $order_id );
					update_post_meta( $request_id, '_sr_status', 'paid' );
				}
			}
		}
	}
}
