<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Google Analytics Integration
 *
 * Allows tracking code to be inserted into store pages.
 *
 * @class   WC_Metricas_Boss
 * @extends WC_Integration
 */
class WC_Metricas_Boss extends WC_Integration {

	/**
	 * Init and hook in the integration.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->id                    = 'metricas_boss';
		$this->method_title          = __( 'Google Tag Manager', 'woocommerce-metricas-boss-integration' );
		$this->method_description    = __( 'Google Tag Manager is a free service offered by Google that generates detailed statistics about the visitors to a website.', 'woocommerce-google-analytics-integration' );
		$this->dismissed_info_banner = get_option( 'woocommerce_dismissed_info_banner' );

		// Load the settings
		$this->init_form_fields();
		$this->init_settings();
		$constructor = $this->init_options();


		// Contains snippets/JS tracking code
		include_once( 'class-wc-metricas-boss-js.php' );
		WC_Metricas_Boss_JS::get_instance( $constructor );

		// Display an info banner on how to configure WooCommerce
		if ( is_admin() ) {
			include_once( 'class-wc-metricas-boss-info-banner.php' );
			WC_Metricas_Boss_Info_Banner::get_instance( $this->dismissed_info_banner, $this->gtm_container_id );
		}


		// Admin Options
		add_filter( 'woocommerce_tracker_data', array( $this, 'track_options' ) );
		add_action( 'woocommerce_update_options_integration_metricas_boss', array( $this, 'process_admin_options') );
		add_action( 'woocommerce_update_options_integration_metricas_boss', array( $this, 'show_options_info') );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_assets') );
		
		// Tracking code
			// Product impressions
			add_action( 'woocommerce_after_shop_loop_item', array( $this, 'insert_product_impression' ) );
			add_action( 'woocommerce_after_shop_loop_item', array( $this, 'insert_product_click' ) );
			add_action( 'woocommerce_product_loop_end', array( $this, 'listing_impression' ) );
			
			add_action( 'woocommerce_after_single_product', array( $this, 'product_detail' ) );

		
			add_action( 'wp_head', array( $this, 'tracking_code_display' ), 999999 );

		
			add_action( 'wp_body_open', array($this, 'tracking_code_display_body'), 999999);
		
		// utm_nooverride parameter for Google AdWords
		add_filter( 'woocommerce_get_return_url', array( $this, 'utm_nooverride' ) );
	}

	/**
	 * Loads all of our options for this plugin
	 * @return array An array of options that can be passed to other classes
	 */
	public function init_options() {
		$options = array(
			'gtm_container_id',
			'gtm_standard_data_enabled',
			'gtm_ecommerce_tracking_enabled',
			'gtm_ecommerce_enhanced_tracking_enabled'
		);

		$constructor = array();
		foreach ( $options as $option ) {
			$constructor[ $option ] = $this->$option = $this->get_option( $option );
		}

		return $constructor;
	}

	/**
	 * Tells WooCommerce which settings to display under the "integration" tab
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'gtm_container_id' => array(
				'title'       => __( 'Google tag Manager Container ID', 'woocommerce-metricas-boss-integration' ),
				'description' => __( 'Log into your Google Tag manager account to find your ID. e.g. <code>GTM-XXXXX</code>', 'woocommerce-metricas-boss-integration' ),
				'type'        => 'text',
				'placeholder' => 'GTM-XXXXX',
				'default'     => get_option( 'woocommerce_gtm_container_id' ) // Backwards compat
			),
			'gtm_standard_data_enabled' => array(
				'title'         => __( 'Tracking Options', 'woocommerce-metricas-boss-integration' ),
				'label'         => __( 'Enable Standard Tracking', 'woocommerce-metricas-boss-integration' ),
				'description'   =>  __( 'This options set in dataLayer data such as userId and PageCategory to create content group in google analytics', 'woocommerce-metricas-boss-integration' ),
				'type'          => 'checkbox',
				'checkboxgroup' => 'start',
				'default'       => get_option( 'woocommerce_gtm_standard_data_enabled' ) ? get_option( 'woocommerce_gtm_standard_data_enabled' ) : 'no'  // Backwards compat
			),
			'gtm_ecommerce_tracking_enabled' => array(
				'label' 			=> __( 'Purchase Transactions', 'woocommerce-metricas-boss-integration' ),
				'description' 			=> __( 'This requires a payment gateway that redirects to the thank you/order received page after payment. Orders paid with gateways which do not do this will not be tracked.', 'woocommerce-metricas-boss-integration' ),
				'type' 				=> 'checkbox',
				'checkboxgroup'		=> 'start',
				'default' 			=> get_option( 'woocommerce_gtm_ecommerce_tracking_enabled' ) ? get_option( 'woocommerce_gtm_ecommerce_tracking_enabled' ) : 'yes'  // Backwards compat
			),
			'gtm_ecommerce_enhanced_tracking_enabled' => array(
				'label' 			=> __( 'Enable Enhanced Commerce actions', 'woocommerce-metricas-boss-integration' ),
				'description' 			=> __( 'This requires a payment gateway that redirects to the thank you/order received page after payment. Orders paid with gateways which do not do this will not be tracked.', 'woocommerce-metricas-boss-integration' ),
				'type' 				=> 'checkbox',
				'checkboxgroup'		=> 'start',
				'default' 			=> get_option( 'gtm_ecommerce_enhanced_tracking_enabled' ) ? get_option( 'gtm_ecommerce_enhanced_tracking_enabled' ) : 'yes'  // Backwards compat
			),
		);
	}

	/**
	 * Shows some additional help text after saving the Google Analytics settings
	 */
	function show_options_info() {
		$this->method_description .= "<div class='notice notice-info'><p>" . __( 'Please allow Google Analytics 24 hours to start displaying results.', 'woocommerce-metricas-boss-integration' ) . "</p></div>";

		if ( isset( $_REQUEST['woocommerce_google_analytics_gtm_ecommerce_tracking_enabled'] ) && true === (bool) $_REQUEST['woocommerce_google_analytics_gtm_ecommerce_tracking_enabled'] ) {
			$this->method_description .= "<div class='notice notice-info'><p>" . __( 'Please note, for transaction tracking to work properly, you will need to use a payment gateway that redirects the customer back to a WooCommerce order received/thank you page.', 'woocommerce-metricas-boss-integration' ) . "</div>";
		}
	}

	/**
	 * Hooks into woocommerce_tracker_data and tracks some of the analytic settings (just enabled|disabled status)
	 * only if you have opted into WooCommerce tracking
	 * http://www.woothemes.com/woocommerce/usage-tracking/
	 */
	function track_options( $data ) {
		$data['wc-metricas-boss'] = array(
			'gtm_standard_data_enabled'   		=> $this->gtm_standard_data_enabled,
			'ga_404_tracking_enabled'           => $this->ga_404_tracking_enabled,
			'gtm_ecommerce_tracking_enabled'  	=> $this->gtm_ecommerce_tracking_enabled,
			'gtm_ecommerce_enhanced_tracking_enabled' => $this->gtm_ecommerce_enhanced_tracking_enabled
		);

		return $data;
	}

	/**
	 *
	 */
	function load_admin_assets() {
		$screen = get_current_screen();
		if ( 'woocommerce_page_wc-settings' !== $screen->id ) {
			return;
		}

		if ( empty( $_GET['tab'] ) ) {
			return;
		}

		if ( 'integration' !== $_GET['tab'] ) {
			return;
		}

		wp_enqueue_script( 'wc-google-analytics-admin-enhanced-settings', plugins_url( '/assets/js/admin-enhanced-settings.js', dirname( __FILE__ ) ), array(), WC_METRICAS_BOSS_INTEGRATION_VERSION, true );
	}
	
	/**
	 * Display the tracking codes
	 * Acts as a controller to figure out which code to display
	 */
	public function tracking_code_display() {
		global $wp;
		$display_ecommerce_tracking = false;

		if ( $this->disable_tracking( 'all' ) ) {
			
			return;
		}
		
		// Check if is order received page and stop when the products and not tracked

		if ( is_order_received_page() && 'yes' === $this->gtm_ecommerce_tracking_enabled ) {
			
			$order_id = isset( $wp->query_vars['order-received'] ) ? $wp->query_vars['order-received'] : 0;
			
			if ( 0 < $order_id && 1 != get_post_meta( $order_id, '_ga_tracked', true ) ) {
				$display_ecommerce_tracking = true;
				
				echo $this->get_ecommerce_tracking_code( $order_id );
			}
		}
		
		if ( is_woocommerce() || is_cart() || ( is_checkout() && ! $display_ecommerce_tracking ) ) {
			$display_ecommerce_tracking = true;
			echo $this->get_standard_tracking_code();
		}

		if ( ! $display_ecommerce_tracking && 'yes' === $this->gtm_standard_data_enabled ) {
			echo $this->get_standard_tracking_code();
		}
	}

	/**
	 * Display the tracking codes
	 * Acts as a controller to figure out which code to display
	 */
	public function tracking_code_display_body() {
		
		global $wp;
		$display_ecommerce_tracking = false;

		if ( $this->disable_tracking( 'all' ) ) {
			
			return;
		}
		
		echo $this->get_standard_tracking_code_body();
	}
	

	/**
	 * Standard Google Tag Manager Container
	 */
	protected function get_standard_tracking_code() {
		return "<!-- WooCommerce Métricas Boss Integration -->
		" . WC_Metricas_Boss_JS::get_instance()->header() ."
		<script type='text/javascript'>" . WC_Metricas_Boss_JS::get_instance()->load_gtm() . "</script>
		<!-- /WooCommerce Métricas Boss Integration -->";
	}


	/**
	 * Standard Google Tag Manager Container
	 */
	protected function get_standard_tracking_code_body() {
		return WC_Metricas_Boss_JS::get_instance()->load_google_tag_manager_body();
	}

	/**
	 * eCommerce tracking
	 *
	 * @param int $order_id
	 */
	protected function get_ecommerce_tracking_code( $order_id ) {
		// Get the order and output tracking code.
		$order = wc_get_order( $order_id );

		// Make sure we have a valid order object.
		if ( ! $order ) {
			return '';
		}

		$code = WC_Metricas_Boss_JS::get_instance()->load_gtm();
		$code .= WC_Metricas_Boss_JS::get_instance()->purchase( $order );

		// Mark the order as tracked.
		update_post_meta( $order_id, '_ga_tracked', 1 );

		return "
			<!-- WooCommerce Métricas Boss Integration -->
			" . WC_Metricas_Boss_JS::get_instance()->header() . "
			<script type='text/javascript'>$code</script>
			<!-- /WooCommerce Métricas Boss Integration -->
		";
	}

	/**
	 * Check if tracking is disabled
	 *
	 * @param string $type The setting to check
	 *
	 * @return bool True if tracking for a certain setting is disabled
	 */
	private function disable_tracking( $type ) {
		if ( is_admin() || current_user_can( 'manage_options' ) || ( ! $this->gtm_container_id ) || 'no' === $type || apply_filters( 'woocommerce_ga_disable_tracking', false, $type ) ) {
			return true;
		}
	}

	/**
	 * Google Analytics event tracking for single product add to cart
	 *
	 * @return void
	 */
	public function add_to_cart() {
		if ( $this->disable_tracking( $this->ga_event_tracking_enabled ) ) {
			return;
		}
		if ( ! is_single() ) {
			return;
		}

		global $product;

		if ( ! $this->disable_tracking( $this->ga_enhanced_ecommerce_tracking_enabled ) ) {
			$code = "" . WC_Metricas_Boss_JS::get_instance()->tracker_var() . "( 'ec:addProduct', {";
			$code .= "'id': '" . esc_js( $product->get_sku() ? $product->get_sku() : ( '#' . $product->get_id() ) ) . "',";
			$code .= "'name': '" . esc_js( $product->get_title() ) . "',";
			$code .= "'quantity': $( 'input.qty' ).val() ? $( 'input.qty' ).val() : '1'";
			$code .= "} );";
			$parameters['enhanced'] = $code;
		}

		WC_Metricas_Boss_JS::get_instance()->event_tracking_code( $parameters, '.single_add_to_cart_button' );
	}

	/**
	 * Enhanced Analytics event tracking for removing a product from the cart
	 */
	public function remove_from_cart() {
		if ( $this->disable_tracking( $this->ga_use_universal_analytics ) ) {
			return;
		}

		if ( $this->disable_tracking( $this->ga_enhanced_ecommerce_tracking_enabled ) ) {
			return;
		}

		if ( $this->disable_tracking( $this->ga_enhanced_remove_from_cart_enabled ) ) {
			return;
		}

		WC_Metricas_Boss_JS::get_instance()->remove_from_cart();
	}

	/**
	 * Adds the product ID and SKU to the remove product link if not present
	 */
	public function remove_from_cart_attributes( $url, $key ) {
		if ( strpos( $url,'data-product_id' ) !== false ) {
			return $url;
		}

		if ( ! is_object( WC()->cart ) ) {
			return $url;
		}

		$item = WC()->cart->get_cart_item( $key );
		$product = $item['data'];

		if ( ! is_object( $product ) ) {
			return $url;
		}

		$url = str_replace( 'href=', 'data-product_id="' . esc_attr( $product->get_id() ) . '" data-product_sku="' . esc_attr( $product->get_sku() )  . '" href=', $url );
		return $url;
	}

	/**
	 * Google Analytics event tracking for loop add to cart
	 *
	 * @return void
	 */
	public function loop_add_to_cart() {
		if ( $this->disable_tracking( $this->ga_event_tracking_enabled ) ) {
			return;
		}

		// Add single quotes to allow jQuery to be substituted into _trackEvent parameters
		$parameters = array();
		$parameters['category'] = "'" . __( 'Products', 'woocommerce-google-analytics-integration' ) . "'";
		$parameters['action']   = "'" . __( 'Add to Cart', 'woocommerce-google-analytics-integration' ) . "'";
		$parameters['label']    = "($(this).data('product_sku')) ? ($(this).data('product_sku')) : ('#' + $(this).data('product_id'))"; // Product SKU or ID

		if ( ! $this->disable_tracking( $this->ga_enhanced_ecommerce_tracking_enabled ) ) {
			$code = "" . WC_Metricas_Boss_JS::get_instance()->tracker_var() . "( 'ec:addProduct', {";
			$code .= "'id': ($(this).data('product_sku')) ? ($(this).data('product_sku')) : ('#' + $(this).data('product_id')),";
			$code .= "'quantity': $(this).data('quantity')";
			$code .= "} );";
			$parameters['enhanced'] = $code;
		}

		WC_Metricas_Boss_JS::get_instance()->event_tracking_code( $parameters, '.add_to_cart_button:not(.product_type_variable, .product_type_grouped)' );
	}

	/**
	 * Insert product in a list of products impressions
	 */
	public function insert_product_impression() {

		if ( $this->disable_tracking( $this->gtm_ecommerce_enhanced_tracking_enabled ) ) {
			return;
		}


		global $product, $woocommerce_loop;
		WC_Metricas_Boss_JS::get_instance()->insert_product_impression( $product, $woocommerce_loop['loop'] );
	}

	/**
	 * Insert product in a list of products impressions
	 */
	public function listing_impression() {

		if ( $this->disable_tracking( $this->gtm_ecommerce_enhanced_tracking_enabled ) ) {
			return;
		}


		WC_Metricas_Boss_JS::get_instance()->listing_impression();
	}

	/**
 	* Measure a product click from a listing page
	*/
	public function insert_product_click() {

		if ( $this->disable_tracking( $this->gtm_ecommerce_enhanced_tracking_enabled ) ) {
			return;
		}


		global $product, $woocommerce_loop;
		WC_Metricas_Boss_JS::get_instance()->insert_product_click( $product, $woocommerce_loop['loop'] );
	}

	/**
	 * Measure a product detail view
	 */
	public function product_detail() {

		if ( $this->disable_tracking( $this->gtm_ecommerce_enhanced_tracking_enabled ) ) {
			return;
		}

		global $product;
		WC_Metricas_Boss_JS::get_instance()->product_detail( $product );
	}

	/**
	 * Tracks when the checkout form is loaded
	 */
	public function checkout_process( $checkout ) {
		if ( $this->disable_tracking( $this->ga_use_universal_analytics ) ) {
			return;
		}

		if ( $this->disable_tracking( $this->ga_enhanced_ecommerce_tracking_enabled ) ) {
			return;
		}

		if ( $this->disable_tracking( $this->ga_enhanced_checkout_process_enabled ) ) {
			return;
		}

		WC_Metricas_Boss_JS::get_instance()->checkout_process( WC()->cart->get_cart() );
	}

	/**
	 * Add the utm_nooverride parameter to any return urls. This makes sure Google Adwords doesn't mistake the offsite gateway as the referrer.
	 *
	 * @param  string $return_url WooCommerce Return URL
	 *
	 * @return string URL
	 */
	public function utm_nooverride( $return_url ) {
		// We don't know if the URL already has the parameter so we should remove it just in case
		$return_url = remove_query_arg( 'utm_nooverride', $return_url );

		// Now add the utm_nooverride query arg to the URL
		$return_url = add_query_arg( 'utm_nooverride', '1', $return_url );

		return $return_url;
	}
}
