<?php
/*
Plugin Name: 	    Admin Columns Pro - WooCommerce
Version: 		    3.5.10
Description: 	    Extra columns for the WooCommerce Product, Orders, Customers and Coupon list tables.
Author:             AdminColumns.com
Author URI:         https://www.admincolumns.com
Plugin URI:         https://www.admincolumns.com
Text Domain: 		codepress-admin-columns
WC tested up to:    5.0.0
Requires PHP:       5.6.20
*/

use ACA\WC\Dependencies;
use ACA\WC\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! is_admin() ) {
	return;
}

require_once __DIR__ . '/classes/Dependencies.php';

add_action( 'after_setup_theme', function () {
	$dependencies = new Dependencies( plugin_basename( __FILE__ ), '3.5.10' );
	$dependencies->requires_acp( '5.4.3' );
	$dependencies->requires_php( '5.6.20' );

	if ( ! class_exists( 'WooCommerce', false ) ) {
		$dependencies->add_missing_plugin( 'WooCommerce', $dependencies->get_search_url( 'WooCommerce' ) );
	}

	if ( $dependencies->has_missing() ) {
		return;
	}

	$class_map = __DIR__ . '/config/autoload-classmap.php';

	if ( is_readable( $class_map ) ) {
		AC\Autoloader::instance()->register_class_map( require $class_map );
	} else {
		AC\Autoloader::instance()->register_prefix( 'ACA\WC', __DIR__ . '/classes' );
	}

	$addon = new WooCommerce( __FILE__ );
	$addon->register();
} );

function ac_addon_wc() {
	return new WooCommerce( __FILE__ );
}

function ac_addon_wc_helper() {
	return new ACA\WC\Helper();
}

// remove update notice for forked plugins
function remove_ac_addon_woocommerce_notifications($value) {

    if ( isset( $value ) && is_object( $value ) ) {
        unset( $value->response[ plugin_basename(__FILE__) ] );
    }

    return $value;
}
add_filter( 'site_transient_update_plugins', 'remove_ac_addon_woocommerce_notifications' );