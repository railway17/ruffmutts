<?php
/**
 * Removes all the options associated with the LightSpeed <> WooCommerce integration plugin
 * Note in multisite looping through blogs to delete options on each blog does not scale. You'll just have to leave them.
 */

//if uninstall not called from WordPress exit
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) )
	exit();

require_once( 'includes/wclsi-constants.php' );

// Lightspeed simple product cache
$total_prod_chunks = get_option( WCLSI_TOTAL_PROD_CHUNKS );
if ( false !== $total_prod_chunks ) {
	for( $i = 0; $i < $total_prod_chunks; $i++ ) {
		delete_option( WCLSI_PROD_CHUNK_PREFIX . $i );
	}
}

// Lightspeed matrix product cache
$total_matrix_chunks = get_option( WCLSI_TOTAL_MATRIX_CHUNKS );
if ( false !== $total_matrix_chunks ) {
	for( $j = 0; $j < $total_matrix_chunks; $j++ ) {
		delete_option( WCLSI_MATRIX_CHUNK_PREFIX . $j );
	}
}

// Lightspeed category product cache
$total_cat_chunks = get_option( WCLSI_TOTAL_CAT_CHUNKS );
if ( false !== $total_cat_chunks ) {
	for( $k = 0; $k < $total_cat_chunks; $k++ ) {
		delete_option( WCLSI_CAT_CHUNK_PREFIX . $k );
	}
}

delete_option( WCLSI_ATTRS_CACHE );               // record of item attribute sets
delete_option( WCLSI_TOTAL_MATRIX_CHUNKS );       // record of # of matrix chunks
delete_option( WCLSI_TOTAL_PROD_CHUNKS );         // record of # of product chunks
delete_option( WCLSI_TOTAL_CAT_CHUNKS );          // record of # of category chunks
delete_option( 'wclsi_last_sync_timestamp' ); // last auto-sync date
delete_option( 'wclsi_cat_cache' );           // imported category cache - deprecated as of v1.3.1
delete_option( 'wclsi_load_timestamp' );      // timestamp for load
delete_option( 'wclsi_shop_data' );           // Shop data, name & timezone
delete_option( 'wclsi_account_id' );          // LightSpeed Account ID
delete_option( 'wclsi_initialized' );         // whether the plugin has been initialized, used to display errors
delete_option( 'wclsi_import_progress' );     // used in ajax functions
delete_option( 'wclsi_version' );             // the version
delete_option( 'wclsi_upgraded_1_1_3' );      // v1.1.3 upgrade flag
delete_option( 'wclsi_upgraded_1_3_1' );      // v1.3.1 upgrade flag
delete_option( 'wclsi_upgraded_1_4_3' );      // v1.4.3 upgrade flag
delete_option( 'wclsi_db_version' );
delete_option( 'wclsi_refresh_token' );
delete_option( 'wclsi_oauth_token' );
delete_option( 'wclsi_expires_in' );
