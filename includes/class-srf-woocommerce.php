<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SRF_WooCommerce' ) ) {
	class SRF_WooCommerce {
		protected static $adding_service_to_cart = false;

		const OPTION_FORM_PAGE_ID        = 'srf_service_form_page_id';
		const OPTION_AFTER_SUBMIT        = 'srf_service_after_submit';
		const OPTION_PROJECT_AFTER_SUBMIT= 'srf_project_after_submit';
		const OPTION_CATEGORY_ID         = 'srf_service_product_category_id';
		const OPTION_PROJECT_PRODUCT_ID  = 'srf_project_quote_product_id';

		const META_PRODUCT_ID          = '_sr_wc_product_id';
		const META_BASE_PRICE          = '_sr_service_base_price';
		const META_DIRECT_PURCHASABLE  = '_sr_service_direct_purchasable';
		const META_PROJECT_PRODUCT     = '_srf_project_quote_product';
		const ORDER_META_REQUEST_ID    = '_srf_request_id';
		const ORDER_META_REQUEST_TYPE  = '_srf_request_type';

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
			add_action( 'woocommerce_payment_complete', array( __CLASS__, 'mark_requests_paid' ) );
			add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'mark_requests_paid' ) );
			add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'mark_requests_paid' ) );
			add_action( 'woocommerce_order_status_on-hold', array( __CLASS__, 'mark_requests_on_hold' ) );
			add_action( 'woocommerce_order_status_failed', array( __CLASS__, 'mark_requests_payment_failed' ) );
			add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'mark_requests_cancelled' ) );
			add_action( 'woocommerce_order_status_refunded', array( __CLASS__, 'mark_requests_refunded' ) );
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

		/**
		 * Create the hidden physical product used as a secure WooCommerce carrier
		 * for project quote totals.
		 */
		public static function ensure_project_product() {
			if ( ! self::is_available() ) {
				return 0;
			}

			$product_id = (int) get_option( self::OPTION_PROJECT_PRODUCT_ID, 0 );
			$product = $product_id > 0 ? get_post( $product_id ) : null;
			if ( ! $product || 'product' !== $product->post_type ) {
				$product_id = wp_insert_post(
					array(
						'post_type'    => 'product',
						'post_status'  => 'publish',
						'post_title'   => __( 'Custom 3D Print Project', 'service-requests-form' ),
						'post_content' => __( 'Hidden checkout item generated from a securely calculated 3D print project quote.', 'service-requests-form' ),
					)
				);
				if ( is_wp_error( $product_id ) || ! $product_id ) {
					return 0;
				}
				update_option( self::OPTION_PROJECT_PRODUCT_ID, (int) $product_id, false );
			}

			wp_update_post(
				array(
					'ID'          => (int) $product_id,
					'post_status' => 'publish',
					'post_title'  => __( 'Custom 3D Print Project', 'service-requests-form' ),
				)
			);
			wp_set_object_terms( $product_id, 'simple', 'product_type' );
			update_post_meta( $product_id, self::META_PROJECT_PRODUCT, 'yes' );
			update_post_meta( $product_id, '_regular_price', '0' );
			update_post_meta( $product_id, '_price', '0' );
			// Printed models are shipped goods. Keeping this carrier physical lets
			// WooCommerce collect a delivery address and apply configured shipping.
			update_post_meta( $product_id, '_virtual', 'no' );
			update_post_meta( $product_id, '_sold_individually', 'no' );
			update_post_meta( $product_id, '_stock_status', 'instock' );
			update_post_meta( $product_id, '_manage_stock', 'no' );
			update_post_meta( $product_id, '_catalog_visibility', 'hidden' );
			// The quote engine already includes the configured tax component. Keep
			// this carrier product non-taxable so WooCommerce cannot apply tax twice.
			update_post_meta( $product_id, '_tax_status', 'none' );

			if ( function_exists( 'wc_get_product' ) ) {
				$product_object = wc_get_product( $product_id );
				if ( $product_object ) {
					$product_object->set_catalog_visibility( 'hidden' );
					$product_object->set_virtual( false );
					$product_object->set_sold_individually( false );
					$product_object->set_tax_status( 'none' );
					$product_object->set_regular_price( '0' );
					$product_object->set_price( '0' );
					$product_object->save();
				}
			}

			if ( function_exists( 'wc_delete_product_transients' ) ) {
				wc_delete_product_transients( $product_id );
			}
			return (int) $product_id;
		}

		public static function service_product_not_directly_purchasable( $purchasable, $product ) {
			// Keep products technically purchasable so internal add_to_cart() works.
			// Direct additions are blocked by validate_service_product_add_to_cart().
			return $purchasable;
		}

		public static function validate_service_product_add_to_cart( $passed, $product_id, $quantity, $variation_id = 0, $variations = array() ) {
			$is_project_product = 'yes' === get_post_meta( (int) $product_id, self::META_PROJECT_PRODUCT, true );
			if ( $is_project_product ) {
				if ( self::$adding_service_to_cart ) {
					return $passed;
				}
				if ( function_exists( 'wc_add_notice' ) ) {
					wc_add_notice( __( 'Please upload a model and calculate a project quote before purchasing this item.', 'service-requests-form' ), 'error' );
				}
				return false;
			}

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
			if ( $product && 'yes' === get_post_meta( $product->get_id(), self::META_PROJECT_PRODUCT, true ) ) {
				return '';
			}
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
			$line_total = isset( $price['total'] ) ? (float) $price['total'] : ( isset( $price['unit_total'] ) ? (float) $price['unit_total'] * $quantity : 0 );
			// Keep unrelated cart contents intact.
			self::$adding_service_to_cart = true;
			$key = false;
			try {
				$key = WC()->cart->add_to_cart( $product_id, 1, 0, array(), array(
					'srf_request_type' => 'service',
					'srf_request_id' => (int) $request_id,
					'srf_service_id' => (int) $service_id,
					'srf_variants' => is_array( $selected_variants ) ? $selected_variants : array(),
					'srf_quantity' => $quantity,
					'srf_price' => $line_total,
					'srf_price_total' => $line_total,
					'srf_price_breakdown' => $price,
				) );
			} catch ( Throwable $error ) {
				if ( function_exists( 'srf_log' ) ) {
					srf_log( 'WooCommerce service cart error: ' . $error->getMessage() );
				}
			} finally {
				self::$adding_service_to_cart = false;
			}
			return (bool) $key;
		}

		public static function add_project_request_to_cart( $request_id, array $quote ) {
			$request_id = (int) $request_id;
			if ( $request_id <= 0 || 'service_request' !== get_post_type( $request_id ) || ! self::is_available() || ! WC()->cart ) {
				return false;
			}

			if ( 'project' !== (string) get_post_meta( $request_id, '_sr_request_type', true ) ) {
				return false;
			}

			$request_status = (string) get_post_meta( $request_id, '_sr_status', true );
			if ( in_array( $request_status, array( 'paid', 'refunded', 'in_progress', 'done' ), true ) ) {
				return false;
			}

			$product_id = self::ensure_project_product();
			if ( ! $product_id ) {
				return false;
			}

			// Stored server output is the only source of truth for the cart amount.
			$total = max( 0, (float) get_post_meta( $request_id, '_sr_total_price', true ) );
			if ( $total <= 0 ) {
				return false;
			}

			// Prevent duplicate cart lines when a customer refreshes after submission.
			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
				if ( (int) ( $cart_item['srf_request_id'] ?? 0 ) === $request_id ) {
					WC()->cart->remove_cart_item( $cart_item_key );
				}
			}

			$cart_data = array(
				'srf_request_type'     => 'project',
				'srf_request_id'       => $request_id,
				'srf_quantity'         => max( 1, (int) get_post_meta( $request_id, '_sr_quantity', true ) ),
				'srf_price'            => $total,
				'srf_price_total'      => $total,
				'srf_project_title'    => (string) get_post_meta( $request_id, '_sr_project_title', true ),
				'srf_material_name'    => (string) get_post_meta( $request_id, '_sr_material_name', true ),
				'srf_printer_name'     => (string) get_post_meta( $request_id, '_sr_printer_name', true ),
				'srf_print_profile'    => (string) get_post_meta( $request_id, '_sr_print_profile_name', true ),
				'srf_estimated_minutes'=> (int) get_post_meta( $request_id, '_sr_estimated_print_minutes', true ),
				'srf_quote_version'     => (string) get_post_meta( $request_id, '_sr_quote_calculation_version', true ),
				'srf_quote'            => $quote,
				'srf_unique_key'       => wp_generate_uuid4(),
			);

			self::$adding_service_to_cart = true;
			$key = false;
			try {
				$key = WC()->cart->add_to_cart( $product_id, 1, 0, array(), $cart_data );
			} catch ( Throwable $error ) {
				if ( function_exists( 'srf_log' ) ) {
					srf_log( 'WooCommerce project cart error: ' . $error->getMessage() );
				}
			} finally {
				self::$adding_service_to_cart = false;
			}
			if ( $key ) {
				update_post_meta( $request_id, '_sr_quote_locked_at', current_time( 'mysql' ) );
			}
			return (bool) $key;
		}

		public static function get_after_submit_url() {
			$target = (string) get_option( self::OPTION_AFTER_SUBMIT, 'checkout' );
			return ( 'cart' === $target && function_exists( 'wc_get_cart_url' ) ) ? wc_get_cart_url() : wc_get_checkout_url();
		}

		public static function get_project_after_submit_url() {
			$target = (string) get_option( self::OPTION_PROJECT_AFTER_SUBMIT, 'checkout' );
			return ( 'cart' === $target && function_exists( 'wc_get_cart_url' ) ) ? wc_get_cart_url() : wc_get_checkout_url();
		}

		public static function apply_cart_item_prices( $cart ) {
			if ( is_admin() && ! defined( 'DOING_AJAX' ) ) { return; }
			if ( ! $cart ) { return; }
			foreach ( $cart->get_cart() as $cart_item ) {
				if ( ! isset( $cart_item['data'] ) || ! is_object( $cart_item['data'] ) ) {
					continue;
				}

				$price = null;
				$request_id = (int) ( $cart_item['srf_request_id'] ?? 0 );
				if ( $request_id > 0 && 'project' === (string) ( $cart_item['srf_request_type'] ?? '' ) ) {
					// Source of truth is server-side request meta, not browser/cart payload.
					$stored = get_post_meta( $request_id, '_sr_total_price', true );
					if ( '' !== (string) $stored ) {
						$price = max( 0, (float) $stored );
					}
				}
				if ( null === $price ) {
					$price = isset( $cart_item['srf_price_total'] ) ? (float) $cart_item['srf_price_total'] : ( isset( $cart_item['srf_price'] ) ? (float) $cart_item['srf_price'] : null );
				}
				if ( null !== $price ) {
					$cart_item['data']->set_price( max( 0, $price ) );
				}
			}
		}

		public static function display_cart_item_meta( $item_data, $cart_item ) {
			$request_type = (string) ( $cart_item['srf_request_type'] ?? 'service' );
			if ( ! empty( $cart_item['srf_request_id'] ) ) {
				$item_data[] = array(
					'name'  => 'project' === $request_type ? __( 'Project Request', 'service-requests-form' ) : __( 'Service Request', 'service-requests-form' ),
					'value' => '#' . (int) $cart_item['srf_request_id'],
				);
			}
			if ( 'project' === $request_type ) {
				foreach ( array(
					__( 'Project', 'service-requests-form' ) => $cart_item['srf_project_title'] ?? '',
					__( 'Material', 'service-requests-form' ) => $cart_item['srf_material_name'] ?? '',
					__( 'Printer', 'service-requests-form' ) => $cart_item['srf_printer_name'] ?? '',
					__( 'Print profile', 'service-requests-form' ) => $cart_item['srf_print_profile'] ?? '',
				) as $label => $value ) {
					if ( '' !== (string) $value ) {
						$item_data[] = array( 'name' => $label, 'value' => (string) $value );
					}
				}
				$minutes = (int) ( $cart_item['srf_estimated_minutes'] ?? 0 );
				if ( $minutes > 0 ) {
					$item_data[] = array( 'name' => __( 'Estimated print time', 'service-requests-form' ), 'value' => self::format_minutes( $minutes ) );
				}
			}
			if ( ! empty( $cart_item['srf_quantity'] ) ) {
				$item_data[] = array( 'name' => __( 'Print quantity', 'service-requests-form' ), 'value' => (int) $cart_item['srf_quantity'] );
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
			$request_id = (int) $values['srf_request_id'];
			$request_type = (string) ( $values['srf_request_type'] ?? 'service' );

			$item->add_meta_data( self::ORDER_META_REQUEST_ID, $request_id, true );
			$item->add_meta_data( self::ORDER_META_REQUEST_TYPE, $request_type, true );
			$item->add_meta_data( __( 'Service Request ID', 'service-requests-form' ), $request_id, true );

			if ( 'project' === $request_type ) {
				$item->add_meta_data( __( 'Request type', 'service-requests-form' ), __( 'Custom 3D print project', 'service-requests-form' ), true );
				foreach ( array(
					__( 'Project', 'service-requests-form' ) => $values['srf_project_title'] ?? '',
					__( 'Material', 'service-requests-form' ) => $values['srf_material_name'] ?? '',
					__( 'Printer', 'service-requests-form' ) => $values['srf_printer_name'] ?? '',
					__( 'Print profile', 'service-requests-form' ) => $values['srf_print_profile'] ?? '',
				) as $label => $value ) {
					if ( '' !== (string) $value ) {
						$item->add_meta_data( $label, (string) $value, true );
					}
				}
				$minutes = (int) ( $values['srf_estimated_minutes'] ?? 0 );
				if ( $minutes > 0 ) {
					$item->add_meta_data( __( 'Estimated print time', 'service-requests-form' ), self::format_minutes( $minutes ), true );
				}
				$quoted_amount = max( 0, (float) ( $values['srf_price_total'] ?? 0 ) );
				if ( $quoted_amount > 0 ) {
					$currency_code   = (string) $order->get_currency();
					$currency_symbol = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol( $currency_code ) : '';
					$item->add_meta_data( __( 'Server-verified quote', 'service-requests-form' ), $currency_symbol . wc_format_decimal( $quoted_amount, 2 ) . ' ' . $currency_code, true );
				}
				$quote_version = sanitize_text_field( (string) ( $values['srf_quote_version'] ?? '' ) );
				if ( '' !== $quote_version ) {
					$item->add_meta_data( __( 'Quote calculation version', 'service-requests-form' ), $quote_version, true );
				}
			}

			if ( ! empty( $values['srf_quantity'] ) ) {
				$item->add_meta_data( __( 'Print quantity', 'service-requests-form' ), (int) $values['srf_quantity'], true );
			}
			if ( ! empty( $values['srf_variants'] ) && is_array( $values['srf_variants'] ) ) {
				foreach ( $values['srf_variants'] as $k => $v ) {
					$item->add_meta_data( (string) $k, (string) $v, true );
				}
			}
		}

		protected static function get_request_id_from_item( $item ) {
			$request_id = (int) $item->get_meta( self::ORDER_META_REQUEST_ID, true );
			if ( $request_id <= 0 ) {
				$request_id = (int) $item->get_meta( __( 'Service Request ID', 'service-requests-form' ), true );
			}
			return $request_id;
		}

		public static function link_order_to_requests( $order_id, $posted_data, $order ) {
			if ( ! $order ) {
				$order = wc_get_order( $order_id );
			}
			if ( ! $order ) {
				return;
			}

			$order_status = (string) $order->get_status();
			$order_paid   = is_callable( array( $order, 'is_paid' ) ) && $order->is_paid();

			foreach ( $order->get_items() as $item ) {
				$request_id = self::get_request_id_from_item( $item );
				if ( $request_id <= 0 || 'service_request' !== get_post_type( $request_id ) ) {
					continue;
				}

				update_post_meta( $request_id, '_sr_wc_order_id', (int) $order_id );
				update_post_meta( $request_id, '_sr_status', $order_paid ? 'paid' : 'pending-payment' );
				update_post_meta( $request_id, '_sr_payment_status', $order_status );
				update_post_meta( $request_id, '_sr_wc_order_total', (float) $order->get_total() );

				$customer_id  = (int) $order->get_customer_id();
				$current_owner = (int) get_post_meta( $request_id, '_sr_user_id', true );
				if ( $customer_id > 0 && $current_owner <= 0 ) {
					update_post_meta( $request_id, '_sr_user_id', $customer_id );
					wp_update_post( array( 'ID' => $request_id, 'post_author' => $customer_id ) );
				}

				$name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
				if ( '' !== $name ) {
					update_post_meta( $request_id, '_sr_name', sanitize_text_field( $name ) );
				}
				if ( is_email( $order->get_billing_email() ) ) {
					update_post_meta( $request_id, '_sr_email', sanitize_email( $order->get_billing_email() ) );
				}
				if ( '' !== (string) $order->get_billing_phone() ) {
					update_post_meta( $request_id, '_sr_phone', sanitize_text_field( $order->get_billing_phone() ) );
				}
				if ( '' !== (string) $order->get_billing_company() ) {
					update_post_meta( $request_id, '_sr_company', sanitize_text_field( $order->get_billing_company() ) );
				}

				$shipping = self::format_order_address( $order, 'shipping' );
				if ( '' === $shipping ) {
					$shipping = self::format_order_address( $order, 'billing' );
				}
				if ( '' !== $shipping ) {
					update_post_meta( $request_id, '_sr_shipping_address', $shipping );
				}
			}

			if ( $order_paid ) {
				self::update_requests_from_order( $order_id, 'paid', $order_status ? $order_status : 'paid' );
			}
		}

		public static function mark_requests_paid( $order_id ) {
			self::update_requests_from_order( $order_id, 'paid', 'paid' );
		}

		public static function mark_requests_payment_failed( $order_id ) {
			self::update_requests_from_order( $order_id, 'payment-failed', 'failed' );
		}

		public static function mark_requests_on_hold( $order_id ) {
			self::update_requests_from_order( $order_id, 'pending-payment', 'on-hold' );
		}

		public static function mark_requests_cancelled( $order_id ) {
			self::update_requests_from_order( $order_id, 'cancelled', 'cancelled' );
		}

		public static function mark_requests_refunded( $order_id ) {
			self::update_requests_from_order( $order_id, 'refunded', 'refunded' );
		}

		protected static function update_requests_from_order( $order_id, $request_status, $payment_status ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				return;
			}
			foreach ( $order->get_items() as $item ) {
				$request_id = self::get_request_id_from_item( $item );
				if ( $request_id <= 0 || 'service_request' !== get_post_type( $request_id ) ) {
					continue;
				}
				update_post_meta( $request_id, '_sr_wc_order_id', (int) $order_id );
				update_post_meta( $request_id, '_sr_status', (string) $request_status );
				update_post_meta( $request_id, '_sr_payment_status', (string) $payment_status );
				update_post_meta( $request_id, '_sr_wc_order_total', (float) $order->get_total() );

				if ( 'paid' === $request_status ) {
					update_post_meta( $request_id, '_sr_paid_at', current_time( 'mysql' ) );
					update_post_meta( $request_id, '_sr_paid_total', (float) $order->get_total() );
					$is_project = 'project' === (string) get_post_meta( $request_id, '_sr_request_type', true );
					if ( $is_project && ! get_post_meta( $request_id, '_sr_paid_notification_sent', true ) ) {
						$sent = false;
						if ( class_exists( 'SR_Form_Handler' ) && method_exists( 'SR_Form_Handler', 'send_admin_new_request_email_public' ) ) {
							$sent = (bool) SR_Form_Handler::send_admin_new_request_email_public( $request_id );
						}
						update_post_meta( $request_id, '_sr_paid_notification_sent', current_time( 'mysql' ) );
						update_post_meta( $request_id, '_sr_paid_notification_result', $sent ? 'sent' : 'attempted' );
					}
				}
			}
		}

		protected static function format_order_address( $order, $type = 'shipping' ) {
			$prefix = 'shipping' === $type ? 'get_shipping_' : 'get_billing_';
			$fields = array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'postcode', 'city', 'state', 'country' );
			$data = array();
			foreach ( $fields as $field ) {
				$method = $prefix . $field;
				$data[ $field ] = is_callable( array( $order, $method ) ) ? trim( (string) $order->{$method}() ) : '';
			}
			$parts = array_filter(
				array(
					trim( $data['first_name'] . ' ' . $data['last_name'] ),
					$data['company'],
					$data['address_1'],
					$data['address_2'],
					trim( $data['postcode'] . ' ' . $data['city'] ),
					$data['state'],
					$data['country'],
				)
			);
			return sanitize_text_field( implode( ', ', $parts ) );
		}

		protected static function format_minutes( $minutes ) {
			$minutes = max( 0, (int) $minutes );
			$hours = (int) floor( $minutes / 60 );
			$remaining = $minutes % 60;
			if ( $hours > 0 ) {
				return sprintf( _n( '%1$d hour %2$d min', '%1$d hours %2$d min', $hours, 'service-requests-form' ), $hours, $remaining );
			}
			return sprintf( _n( '%d minute', '%d minutes', $minutes, 'service-requests-form' ), $minutes );
		}
	}
}
