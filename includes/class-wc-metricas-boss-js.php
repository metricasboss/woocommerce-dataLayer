<?php
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

/**
 * WC_Metricas_Boss_JS class
 *
 * JS for recording Google Analytics info
 */
class WC_Metricas_Boss_JS {

	/** @var object Class Instance */
	private static $instance;

	/** @var array Inherited Analytics options */
	private static $options;

	/**
	 * Get the class instance
	 */
	public static function get_instance( $options = array() ) {
		return null === self::$instance ? ( self::$instance = new self( $options ) ) : self::$instance;
	}

	/**
	 * Constructor
	 * Takes our options from the parent class so we can later use them in the JS snippets
	 */
	public function __construct( $options = array() ) {
		self::$options = $options;
	}

	/**
	 * Return one of our options
	 * @param  string $option Key/name for the option
	 * @return string         Value of the option
	 */
	public static function get( $option ) {
		return self::$options[$option];
	}

	/**
	 * Returns the tracker variable this integration should use
	 */
	public static function tracker_var() {
		return apply_filters( 'woocommerce_ga_tracker_variable', 'ga' );
	}

	/**
	 * Generic GA / header snippet for opt out
	 */
	public static function header() {
		
		$userId = wp_get_current_user()->ID !== 0 ? wp_get_current_user()->ID : null;
		
		return "<script type='text/javascript'>
			var dataLayer = window.dataLayer || [];
				dataLayer.push({
					'userId': '".$userId."',
					'pageCategory': '".self::get_page_category()."',
				});
		</script>";
	}

	/**
	 * Enqueues JavaScript to insert product into impressions arrays to prepare impressions Object
	 *
	 * @param WC_Product $product
	 * @param int $position
	 */
	public static function insert_product_impression($product, $position, $list ) {
        if(empty($list)) {
			if ( isset( $_GET['s'] ) ) {
				$list = "Search Results";
			} else {
				$list = "Product List";
			}
		}

		if($position == 1) {
			wc_enqueue_js("var impressions = []");
		}


		wc_enqueue_js("
			impressions.push({
				'id': '" . esc_js( $product->get_id() ) . "',
				'name': '" . esc_js( $product->get_title() ) . "',
				'category': " . self::product_get_category_line( $product ) . "
				'list': '" . esc_js( $list ) . "',
				'position': " . esc_js( $position ) . ",
				'price': ".esc_js(self::product_get_price($product->get_price())) .",
			})
		");
	}

	/**
	 * Enqueues JavaScript to build productClick event
	 *
	 * @param WC_Product $product
	 * @param int $position
	 */
	public static function insert_product_click( $product, $position, $list ) {
		if(empty($list)) {
			if ( isset( $_GET['s'] ) ) {
				$list = "Search Results";
			} else {
				$list = "Product List";
			}
		}

		wc_enqueue_js( "
			$( '.products .post-" . esc_js( $product->get_id() ) . " a' ).on('click', function() {
				if ( true === $(this).hasClass( 'add_to_cart_button' ) ) {
					return;
				}
				
				dataLayer.push({
					'event': 'productClick',
					'ecommerce': {
						'click': {
							'actionField': {'list': '". $list ."'},
							'products': [ {
								'id': '" . esc_js( $product->get_id() ) . "',
								'name': '" . esc_js( $product->get_title() ) . "',
								'category': " . self::product_get_category_line( $product ) . "
								'position': " . esc_js( $position ) . ",
								'price': ".esc_js(self::product_get_price($product->get_price())) .",
							} ],
						}
					}
				} );
			});
		" );
	}

	/**
	 * Enqueues JavaScript to build productClick event
	 *
	 * @param WC_Product $product
	 * @param int $position
	 */
	public static function product_detail( $product ) {
		wc_enqueue_js("
			dataLayer.push({
				'ecommerce': {
					'detail': {
						'actionField': {'list': 'Product Page'},
						'products':[{
							'id': '" . esc_js( $product->get_id() ) . "',
							'name': '" . esc_js( $product->get_title() ) . "',
							'category': " . self::product_get_category_line( $product ) . "
							'price': ".esc_js(self::product_get_price(($product->get_price()))) ."
						}]
					}
				}

			})
		");
	}

	/**
	* listing impressions
 	*/
	 public static function listing_impression() {

		wc_enqueue_js("
			dataLayer.push({
				'ecommerce': {
					'impressions': impressions
				}

			})
		");	
	}

	/**
 	* Enqueue JavaScript for Add to cart tracking
	*
	* @param object $product object
	* @param string $selector jQuery selector for binding click event
	*/
	public static function add_to_cart( $product, $selector ) {

		wc_enqueue_js("
			$( '" . $selector . "' ).on( 'click', function() {
				dataLayer.push({
					'event': 'addToCart',
					'ecommerce': {
						'add': {
							'products':[{
								'id': '" . esc_js( $product->get_id() ) . "',
								'name': '" . esc_js( $product->get_title() ) . "',
								'category': " . self::product_get_category_line( $product ) . "
								'price': ".esc_js(self::product_get_price(($product->get_price()) )).",
								'quantity': $( 'input.qty' ).val() ? $( 'input.qty' ).val() : '1'
							}]
						}
					}
	
				})
			});
		");
	}

	/**
 	* Enqueue JavaScript for Remove from cart tracking
	*
	* @param object $product object
	*/
	public static function remove_from_cart() {

		wc_enqueue_js("
			$( '.remove' ).on( 'click', function() {
				dataLayer.push({
					'event': 'removeFromCart',
					'ecommerce': {
						'remove': {
							'products':[{
								'id': ($(this).data('product_sku')) ? ($(this).data('product_sku')) : ('#' + $(this).data('product_id')),
								'quantity': $(this).parent().parent().find( '.qty' ).val() ? $(this).parent().parent().find( '.qty' ).val() : '1',
							}]
						}
					}
	
				})
			});
		");
	}
	


	/**
	 * Enqueues JavaScript to build checkout event
	 *
	 * @param Cart $cart
	 
	 */
	public static function checkout_process( $cart ) {
		wc_enqueue_js("
			var products_checkout = window.products_checkout || [];
		");
		// Loop over $cart items
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$product = $cart_item['data'];
			
			wc_enqueue_js("
				products_checkout.push({
					'id': '" . esc_js( $product->get_id() ) . "',
					'name': '" . esc_js( $product->get_title() ) . "',
					'category': " . self::product_get_category_line( $product ) . "
					'price': ".esc_js(self::product_get_price(($product->get_price()) )).",
					'quantity': parseInt(". $cart_item['quantity'] .")
				})
			");
		}


		wc_enqueue_js("
			dataLayer.push({
				'event': 'checkout',
				'ecommerce': {
					'checkout': {
						'actionField': {'step': 1},
						'products': products_checkout
					}
				}

			})
		");
	}

	public static function get_page_category() {
		$pageCategory = 'Other';
		if( is_home() ) {
			$pageCategory = 'Home';
		}

		if( is_product() ) {
			$pageCategory = 'Product';
		}
		
		if( is_product_category() ) {
			$pageCategory = 'Category';
		}

		if( is_cart() ) {
			$pageCategory = 'Cart';
		}
		
		if(is_checkout() || is_order_received_page()) {
			$pageCategory = 'Checkout';
		}

		if(is_view_order_page()) {
			$pageCategory = 'Orders';
		}

		return $pageCategory;
	}

	/**
	 * Loads the correct Google Tag Manager container code
	 * @return string    Google Tag Manager loading code
	 */
	public static function load_gtm( $order = false ) {
		
		$gtm_container_id = self::get( 'gtm_container_id' );
		
		if ( isset($gtm_container_id) ) {
			return self::load_google_tag_manager();
		} 
	
	}


	/**
	 * Loads the Google Tag manager container code
	 * @return string Google tag manager container snippet
	 */
	public static function load_google_tag_manager() {
		
		$code = "(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
		new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
		j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
		'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
		})(window,document,'script','dataLayer','".self::get( 'gtm_container_id' )."');";

		$code = apply_filters( 'woocommerce_gtm_snippet_output', $code );

		return $code;
	}

	/**
	 * Loads the Google Tag manager container code
	 * @return string Google tag manager container snippet
	 */
	public static function load_google_tag_manager_body() {
		
		$code = "<noscript><iframe src='https://www.googletagmanager.com/ns.html?id=".self::get( 'gtm_container_id' )."' height='0' width='0' style='display:none;visibility:hidden'></iframe></noscript>";

		$code = apply_filters( 'woocommerce_gtm_snippet_body_output', $code );

		return $code;
	}

	/**
	 * Used to pass transaction data to Google Tag Manager
	 * @param object $order WC_Order Object
	 * @return string Purchase Custom event of Google tag manager
	 */
	function purchase( $order ) {

		if ( 'yes' == self::get( 'gtm_ecommerce_tracking_enabled' ) ) {
			wc_enqueue_js("var products_purchase = window.products_purchase || [];	");
			if ( $order->get_items()) {
				foreach ( $order->get_items() as $product ) {
					wc_enqueue_js("
						products_purchase.push({
							'id': '" . esc_js( $product->get_id() ) . "',
							'name': '" . esc_js( $product->get_name() ) . "',
							'category': " . self::product_get_category_line( $product ) . "
							'price': ".esc_js(self::product_get_price(($product->get_total()) )).",
							'quantity': ". $product->get_quantity() ."
						})
					");
				}
			}
			
			wc_enqueue_js("
				var dataLayer = window.dataLayer || [];	
				dataLayer.push({
					'ecommerce': {
						'purchase': {
							'actionField': {
								'id': '" . esc_js( $order->get_order_number() ) . "',
								'affiliation': '" . esc_js( get_bloginfo( 'name' ) ) . "',
								'revenue':  ". esc_js( $order->get_total() ) ." ,
								'tax': ". esc_js( $order->get_total_tax() ) .",
								'shipping': ". esc_js( $order->get_total_shipping() ) .",
								'coupon': '". esc_js( !empty($order->get_coupon_codes()) ?  $order->get_coupon_codes()[0] : '') ."'
							},
							'products': products_purchase
						}
					}

				})
			");

		}
	}


	/**
	 * Returns a 'category' JSON line based on $product
	 * @param  object $product  Product to pull info for
	 * @return string          Line of JSON
	 */
	private static function product_get_category_line( $_product ) {

		$out            = array();
		$variation_data = version_compare( WC_VERSION, '3.0', '<' ) ? $_product->variation_data : ( $_product->is_type( 'variation' ) ? wc_get_product_variation_attributes( $_product->get_id() ) : '' );
		$categories     = get_the_terms( $_product->get_id(), 'product_cat' );

		if ( is_array( $variation_data ) && ! empty( $variation_data ) ) {
			$parent_product = wc_get_product( version_compare( WC_VERSION, '3.0', '<' ) ? $_product->parent->id : $_product->get_parent_id() );
			$categories = get_the_terms( $parent_product->get_id(), 'product_cat' );
		}

		if ( $categories ) {
			foreach ( $categories as $category ) {
				$out[] = $category->name;
			}
		}

		return "'" . esc_js( join( "/", $out ) ) . "',";
	}

	/**
	* Returns a price JSON line based on $product
	* @param  object $price  Price Product to pull info for
	* @return int  Line of JSON
	*/
	private static function product_get_price($price) {
		return $price > 0 ? $price : 0;
	}
}
