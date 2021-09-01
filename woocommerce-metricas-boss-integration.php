<?php
/**
 * Plugin Name: WooCommerce Métricas Boss Integration
 * Plugin URI: http://wordpress.org/plugins/woocommerce-metricas-boss-integration/
 * Description: Implements datalayer structuring for implementing third-party tags
 * Author: Métricas Boss
 * Author URI: https://metricasboss.com.br
 * Version: 1.0.0
 * WC requires at least: 2.1
 * WC tested up to: 4.2
 * Tested up to: 5.4
 * License: GPLv2 or later
 * Text Domain: woocommerce-metricas-boss-integration
 * Domain Path: languages/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Metricas_Boss_Integration' ) ) {
	define( 'WC_METRICAS_BOSS_INTEGRATION_VERSION', '1.0.0' ); // WRCS: DEFINED_VERSION.

	/**
	 * WooCommerce Métricas Boss Integration main class.
	 */
	class WC_Metricas_Boss_Integration {

		/**
		 * Instance of this class.
		 *
		 * @var object
		 */
		protected static $instance = null;

		/**
		 * Initialize the plugin.
		*/
		public function __construct() {
			if ( ! class_exists( 'WooCommerce' ) ) {
				return;
			}

			// Load plugin text domain
			add_action( 'init', array( $this, 'load_plugin_textdomain' ) );
			add_action( 'init', array( $this, 'show_ga_pro_notices' ) );

			// Checks with WooCommerce is installed.
			if ( class_exists( 'WC_Integration' ) && defined( 'WOOCOMMERCE_VERSION' ) && version_compare( WOOCOMMERCE_VERSION, '2.1-beta-1', '>=' ) ) {
				include_once 'includes/class-wc-metricas-boss.php';

				// Register the integration.
				add_filter( 'woocommerce_integrations', array( $this, 'add_integration' ) );
			} else {
				add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			}

			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_links' ) );
		}

		public function plugin_links( $links ) {
			$settings_url = add_query_arg(
				array(
					'page' => 'wc-settings',
					'tab' => 'integration',
					'section' => 'metricas_boss',
				),
				admin_url( 'admin.php' )
			);

			$plugin_links = array(
				'<a href="' . esc_url( $settings_url ) . '">' . __( 'Settings', 'woocommerce-metricas-boss-integration' ) . '</a>',
				'<a href="https://wordpress.org/support/plugin/woocommerce-metricas-boss-integration">' . __( 'Support', 'woocommerce-metricas-boss-integration' ) . '</a>',
			);

			return array_merge( $plugin_links, $links );
		}

		/**
		 * Return an instance of this class.
		 *
		 * @return object A single instance of this class.
		 */
		public static function get_instance() {
			// If the single instance hasn't been set, set it now.
			if ( null == self::$instance ) {
				self::$instance = new self;
			}

			return self::$instance;
		}

		/**
		 * Load the plugin text domain for translation.
		 *
		 * @return void
		 */
		public function load_plugin_textdomain() {
			$locale = apply_filters( 'plugin_locale', get_locale(), 'woocommerce-metricas-boss-integration' );

			load_textdomain( 'woocommerce-metricas-boss-integration', trailingslashit( WP_LANG_DIR ) . 'woocommerce-metricas-boss-integration/woocommerce-metricas-boss-integration-' . $locale . '.mo' );
			load_plugin_textdomain( 'woocommerce-metricas-boss-integration', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		}

		/**
		 * WooCommerce fallback notice.
		 *
		 * @return string
		 */
		public function woocommerce_missing_notice() {
			echo '<div class="error"><p>' . sprintf( __( 'WooCommerce Google Analytics depends on the last version of %s to work!', 'woocommerce-metricas-boss-integration' ), '<a href="http://www.woothemes.com/woocommerce/" target="_blank">' . __( 'WooCommerce', 'woocommerce-metricas-boss-integration' ) . '</a>' ) . '</p></div>';
		}

		/**
		 * Add a new integration to WooCommerce.
		 *
		 * @param  array $integrations WooCommerce integrations.
		 *
		 * @return array               Google Analytics integration.
		 */
		public function add_integration( $integrations ) {
			$integrations[] = 'WC_Metricas_Boss';

			return $integrations;
		}

		/**
		 * Logic for Google Analytics Pro notices.
		 */
		public function show_ga_pro_notices() {
			// Notice was already shown
			if ( get_option( 'woocommerce_metricas_boss_pro_notice_shown', false ) ) {
				return;
			}

			$completed_orders = wc_orders_count( 'completed' );

			// Only show the notice if there are 10 <= completed orders <= 100.
			if ( ! ( 10 <= $completed_orders && $completed_orders <= 100 ) ) {
				return;
			}

			$notice_html  = '<strong>' . esc_html__( 'Get detailed insights into your sales with Google Analytics Pro', 'woocommerce-metricas-boss-integration' ) . '</strong><br><br>';

			/* translators: 1: href link to GA pro */
			$notice_html .= sprintf( __( 'Add advanced tracking for your sales funnel, coupons and more. [<a href="%s" target="_blank">Learn more</a> &gt;]', 'woocommerce-metricas-boss-integration' ), 'https://woocommerce.com/products/woocommerce-metricas-boss-pro/?utm_source=product&utm_medium=upsell&utm_campaign=google%20analytics%20free%20to%20pro%20extension%20upsell' );

			WC_Admin_Notices::add_custom_notice( 'woocommerce_google_analytics_pro_notice', $notice_html );
			update_option( 'woocommerce_metricas_boss_pro_notice_shown', true );
		}
	}

	add_action( 'plugins_loaded', array( 'WC_Metricas_Boss_Integration', 'get_instance' ), 0 );

}
