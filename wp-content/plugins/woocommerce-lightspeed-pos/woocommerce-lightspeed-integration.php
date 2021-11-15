<?php
/**
 * Plugin Name: WooCommerce LightSpeed POS
 * Plugin URI: http://woothemes.com/products/woocommerce-lightspeed-pos/
 * Description: WooCommerce LightSpeed POS allows you to integrate and import inventory from LightSpeed to WooCommerce and sync inventory across both systems. You need to sign up for a LightSpeed account to use this extension.
 * Version: 1.9.1
 * Author: WooCommerce
 * Author URI: http://woocommerce.com/
 * Developer: Rafi Y/RafiLabs
 * Developer URI: http://rafilabs.com/
 * Text Domain: woocommerce-lightspeed-pos
 * Domain Path: /languages
 * Requires at least: 4.5
 * Tested up to: 5.5.3
 *
 * Woo: 1210883:c839f6c97c36a944de1391cf5086874d
 * WC requires at least: 2.5.2
 * WC tested up to: 4.7.0
 * Stable tag: 1.9.1
 *
 * Copyright: © 2020 RafiLabs.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Required functions
 */
if ( ! function_exists( 'woothemes_queue_update' ) ) {
	require_once( 'woo-includes/woo-functions.php' );
}

require_once( 'includes/wclsi-constants.php' );

// Scripts & Migrations
foreach (glob( dirname( __FILE__ ) . "/db/scripts/*.php" ) as $file) { 
	require_once( $file ); 
}

$migration_files = glob( dirname( __FILE__ ) . "/db/migrations/*.php" );
foreach ($migration_files as $file) {
	$classes = get_declared_classes();
	require_once( $file );
	$diff = array_diff( get_declared_classes(), $classes );
	$migration_class = reset( $diff );
	if( method_exists( $migration_class, 'run_migration') ) {
		$migration = new $migration_class();
		$migration->run_migration();
	}
}

// Define global accessors
/**
 * @global LSI_Init_Settings $WCLSI_API Lightspeed API settings & interface
 */
global $WCLSI_API;

/**
 * @global LSI_Import_Products $WCLSI_PRODS Interface for import Lightspeed Products
 */
global $WCLSI_PRODS;

/**
 * @global WCLSI_Synchronizer $WCLSI_SYNCER Syncing logic
 */
global $WCLSI_SYNCER;

/**
 * @global WC_Logger Logger handle
 */
global $WCLSI_WC_Logger;

/**
 * @global WCLSI_Check_For_New_Prods Background job for loading new products
 */
global $WCLSI_Check_For_New_Prods;

/**
 * @global WCLSI_Woo_Prod_Page handles hooks when the WooCommerce product page is rendered
 */
global $WCLSI_Woo_Prod_Page;
/**
 * Plugin updates
 */
woothemes_queue_update( plugin_basename( __FILE__ ), 'c839f6c97c36a944de1391cf5086874d', '1210883' );

// Unschedule sync schedule if the plugin disabled
register_deactivation_hook( __FILE__, array( 'WC_LS_Integration', 'wclsi_unschedule_as_events' ) );

if ( ! class_exists( 'WC_LS_Integration' ) ):

	class WC_LS_Integration {

		public function __construct() {
			add_action( 'woocommerce_loaded', array( $this, 'init' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'wclsi_action_links' ) );
		}

		function wclsi_action_links( $links ) {
			$href = add_query_arg(
				array(
					'page'    => 'wc-settings',
					'tab'     => 'integration',
					'section' => 'lightspeed-integration'
				),
				admin_url( 'admin.php' )
			);

			$links[] = sprintf( '<a href="%s">%s</a>', $href, __( 'Settings', 'woocommerce-lightspeed-pos' ) );

			return $links;
		}

		public function init() {

			// Checks if WooCommerce is installed.
			if ( class_exists( 'WC_Integration' ) ) {

				include_once 'includes/class-wp-mosapicall.php';
				include_once 'includes/class-wp-moscurl.php';
				include_once 'includes/wclsi-core-functions.php';
				include_once 'includes/class-wclsi-item-image.php';
				include_once 'includes/class-wclsi-item-price.php';
				include_once 'includes/class-wclsi-item-shop.php';
				include_once 'includes/class-wclsi-item-e-commerce.php';
				include_once 'includes/class-wclsi-lightspeed-prod.php';
				include_once 'includes/class-wclsi-item-attributes.php';
				include_once 'includes/class-wclsi-init-settings.php';
				include_once 'includes/class-wclsi-import-cats.php';
				include_once 'includes/class-wclsi-import-prod.php';
				include_once 'includes/class-wclsi-woo-prod-page.php';
				include_once 'includes/class-wclsi-import-page.php';
				include_once 'includes/class-wclsi-import-table.php';
				include_once 'includes/class-wclsi-sync.php';
				include_once 'includes/class-wclsi-api-navigator.php';
				include_once 'includes/background-jobs/class-wclsi-item-poller.php';
				include_once 'includes/background-jobs/class-wclsi-check-for-new-prods.php';
				include_once 'includes/class-wclsi-upgrade-routines.php';

				// Register the integration.
				add_filter( 'woocommerce_integrations', array( $this, 'add_integration' ) );

				// Perform upgrade routines if necessary
				WC_LS_Upgrade_Routines::perform_upgrades();

				// If wclsi_daily_sync still exists, remove it
				$timestamp = wp_next_scheduled( 'wclsi_daily_sync' );
				if( false !== $timestamp ) {
					wp_unschedule_event( $timestamp, 'wclsi_daily_sync' );
				}
			}
		}

		/**
		 * Add a new integration to WooCommerce.
		 */
		public function add_integration( $integrations ) {
			$integrations[] = 'LSI_Init_Settings';
			return $integrations;
		}

		public static function wclsi_unschedule_as_events() {
			as_unschedule_action( 'check_for_new_ls_prods' );
			as_unschedule_action( 'wclsi_poll' );
		}
	}

	new WC_LS_Integration();

endif;
