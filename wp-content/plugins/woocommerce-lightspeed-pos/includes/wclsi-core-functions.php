<?php
/**
 * Core functions useful for various parts of the plugin
 */

/**
 * Add logger for debugging
 */
function wclsi_load_wc_logger() {
	global $WCLSI_WC_Logger;
	$WCLSI_WC_Logger = new WC_Logger();
}
add_action('init', 'wclsi_load_wc_logger');

/**
 * Creates an new wp_attachment based off of an existing file. The file
 * has to be located in wp-content/uploads in order for the function to work.
 *
 * @param $filename - path and name of the file to attach
 * @param $parent_id - PostID to attach the attachment to
 * @param $content
 * @param $auth_id - Author of attachment
 * @param $include_files - Whether to include image.php and media.php; may be inefficient if
 *                         the function gets called frequently
 *
 * @return int - The attachment ID
 */
function wc_ls_create_attachment($filename, $parent_id, $content = '', $auth_id = null, $include_files = true ) {

	$auth_id = empty( $auth_id ) ? get_current_user_id() : $auth_id;

	$wp_filetype = wp_check_filetype(basename($filename), null );
	$wp_upload_dir = wp_upload_dir();
	$post = get_post( $parent_id );
	$attachment = array(
		'guid' => $wp_upload_dir['url'] . '/' . basename( $filename ),
		'post_mime_type' => $wp_filetype['type'],
		'post_title' => get_the_title( $parent_id ),
		'post_content' => empty( $post->post_content ) ? basename( $filename ) : $post->post_content,
		'post_status' => 'inherit',
		'post_author' => $auth_id
	);
	$attach_id = wp_insert_attachment( $attachment, $filename, $parent_id );

	if ( $include_files ) {
		// you must first include the image.php file
		// for the function wp_generate_attachment_metadata() to work
		require_once( ABSPATH . 'wp-admin/includes/image.php' );
		require_once( ABSPATH . 'wp-admin/includes/media.php' );
	}

	$attach_data = wp_generate_attachment_metadata( $attach_id, $filename );
	wp_update_attachment_metadata( $attach_id, $attach_data );
	return $attach_id;
}

/**
 * Returns an array of the LightSpeed prod IDs.
 * @return array
 */
function wclsi_get_ls_prod_ids() {
	global $wpdb, $WCLSI_ITEM_TABLE;

	$prod_ids = $wpdb->get_col( "SELECT id from $WCLSI_ITEM_TABLE WHERE item_id > 0 AND item_matrix_id = 0" );
	$matrix_ids = $wpdb->get_col( "SELECT id from $WCLSI_ITEM_TABLE WHERE item_id IS NULL AND item_matrix_id > 0" );

	return array( 'prod_ids' => $prod_ids, 'matrix_ids' => $matrix_ids );
}

/**
 * Given a LightSpeed product, returns its inventory value.
 * WooCommerce inventory takes precedence.
 * @param WCLSI_Lightspeed_Prod $item
 * @param bool $skip_wc_stock - if the wclsi_item is linked, it will always default to the wc_stock
 * @return int
 */
function wclsi_get_lightspeed_inventory( WCLSI_Lightspeed_Prod $item, $skip_wc_stock = false ) {
	global $WCLSI_API;
	
	if ( $item->wc_prod_id > 0 && !$skip_wc_stock ) {
		$inventory = (int) get_post_meta( $item->wc_prod_id, '_stock', true );
	} elseif ( !is_null( $item->item_shops ) ) {
		if ( isset( $WCLSI_API->settings[ WCLSI_INVENTORY_SHOP_ID ] ) ){
			$primary_shop_id = $WCLSI_API->settings[ WCLSI_INVENTORY_SHOP_ID ];
			
			// Some products may not have inventory set in all item shops			
			if ( isset( $item->item_shops[ $primary_shop_id ] ) ) {
				$item_shop = $item->item_shops[ $primary_shop_id ];
				$inventory = (int) $item_shop->qoh;  
			} else {
				$inventory = 0;
			}
		} else {
			// Pop the first shop off the array in case there is no primary shop id
			$item_shop = array_pop( $item->item_shops );
			$inventory = (int) $item_shop->qoh;
		}
	} else {
		$inventory = 0;
	}

	return apply_filters( 'wclsi_get_lightspeed_inventory', $inventory, $item );
}

/**
 * Same as wclsi_get_lightspeed_inventory() but used for raw LS API item responses
 * @param $ls_api_item
 * @return mixed|void
 */
function get_lightspeed_inventory( $ls_api_item ) {
	if ( property_exists( $ls_api_item, 'ItemShops' ) ) {
		global $WCLSI_API;

		if ( isset( $WCLSI_API->settings[ WCLSI_INVENTORY_SHOP_ID ] ) ){
			$primary_shop_id = $WCLSI_API->settings[ WCLSI_INVENTORY_SHOP_ID ];

			// Get the primary shop
			$primary_shop = null;
			foreach( $ls_api_item->ItemShops->ItemShop as $item_shop ) {
				if ( $item_shop->shopID == $primary_shop_id ) {
					$primary_shop = $item_shop;
					break;
				}
			}

			if ( !is_null( $primary_shop ) ) {
				$inventory = (int) $primary_shop->qoh;
			} else {
				// We should throw an error here instead of setting the inventory to 0! 
				$inventory = 0;
			}
		} else {
			// Pop the first shop off the array in case there is no primary shop id (for single stores)
			$single_item_shop = array_pop( $ls_api_item->ItemShops );
			$inventory = (int) $single_item_shop->qoh;
		}
	} else {
		$inventory = 0;
	}

	return apply_filters( 'get_lightspeed_inventory', $inventory, $ls_api_item );
}

/**
 * Returns the matrix products that belong to a specific matrix id
 * @param $matrix_id
 * @param $matrix_prod
 * @return array
 */
function wclsi_get_matrix_prods( $matrix_id, $matrix_prod = null ) {

	$matrix_prods = array();
	if( isset( $matrix_prod->wc_prod_id ) ){
		$variations = get_children(
			array(
				'post_parent' => $matrix_prod->wc_prod_id,
				'post_type'   => 'product_variation'
			)
		);

		if( !empty( $variations ) ){
			foreach ( $variations as $post_id => $variation ) {
				$matrix_prods[] = get_post_meta( $post_id, '_wclsi_ls_obj', true );
			}
		}
	} elseif ( $matrix_id > 0 ) {
		global $wpdb, $WCLSI_ITEM_TABLE;
		$matrix_ids = $wpdb->get_col("SELECT id FROM $WCLSI_ITEM_TABLE WHERE item_matrix_id=$matrix_id AND item_id>0");
		foreach ( $matrix_ids as $id ) {
			$matrix_prods[] = new WCLSI_Lightspeed_Prod( $id );
		}
	}

	return $matrix_prods;
}

/**
 * Given a LightSpeed prod, returns the API path for that product.
 * Also accepts an array of Lightspeed single item IDs.
 *
 * @param $prod_or_prod_ids object|array
 * @return array
 */
function wclsi_get_prod_api_path( $prod_or_prod_ids ) {
	global $WCLSI_SINGLE_LOAD_RELATIONS, $WCLSI_MATRIX_LOAD_RELATIONS;

	$search_string = '';
	$search_params = array();

	if( is_array( $prod_or_prod_ids ) ){
		$search_params = array(
			'load_relations' => json_encode( $WCLSI_SINGLE_LOAD_RELATIONS ),
			'itemID' => 'IN,' . json_encode( $prod_or_prod_ids )
		);
		$search_string = '/Item';
	} else if ( wclsi_is_simple_product( $prod_or_prod_ids ) ) {
		$search_params = array(
			'load_relations' => json_encode( $WCLSI_SINGLE_LOAD_RELATIONS ),
		);
		$search_string = "/Item/{$prod_or_prod_ids->item_id}";
	} elseif ( wclsi_is_matrix_product( $prod_or_prod_ids ) ) {
		$search_params = array(
			'load_relations' => json_encode( $WCLSI_MATRIX_LOAD_RELATIONS ),
		);
		$search_string = "/ItemMatrix/{$prod_or_prod_ids->item_matrix_id}";
	}
	return array( 'path' => $search_string, 'params' => $search_params );
}

/**
 * Predicate function to test whether a lightspeed object is a simple product
 * @param $item
 *
 * @return bool
 */
function wclsi_is_simple_product( $item ) {
	return isset( $item->item_id ) && $item->item_id > 0;
}

/**
 * Predicate function to test whether a lightspeed object is a matrix product
 * @param $item
 *
 * @return bool
 */
function wclsi_is_matrix_product( $item ){
	return !isset( $item->item_id ) && isset( $item->item_matrix_id ) && $item->item_matrix_id > 0;
}

function wclsi_oauth_enabled(){
	return (bool) get_option( 'wclsi_oauth_token' );
}

function wclsi_table_exists( $table_name ){
	$cache_result = wp_cache_get( "wclsi_table_exists_{$table_name}", "wclsi_table_queries" );
	if( false !== $cache_result ) {
		return $cache_result;
	}

	global $wpdb;
	$sql = $wpdb->prepare( "SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = %s;", $table_name );
	$row = $wpdb->get_row($sql);

	$result = !is_null($row);

	wp_cache_add( "wclsi_table_exists_{$table_name}", $result, "wclsi_table_queries" );

	return $result;
}

function wclsi_table_empty( $table_name ) {
	global $wpdb;
	$sql = esc_sql( "SELECT COUNT(*) as count FROM $table_name" );
	return $wpdb->get_var($sql) == 0;
}

function wclsi_format_empty_vals( &$args ) {
	foreach( $args as $key => $val ) {
		if ( $val == '' || empty((array) $val)) {
			$args[ $key ] = null;
		}

		if ( is_object( $val ) && count( get_object_vars( $val ) ) == 0 ) {
			$args[ $key ] = null;
		}
	}
}

function wclsi_get_ls_sku( $ls_prod ) {
	if( isset( $ls_prod->custom_sku ) && !empty( $ls_prod->custom_sku ) ) {
		return $ls_prod->custom_sku;
	}

	if( isset( $ls_prod->manufacturer_sku ) && !empty( $ls_prod->manufacturer_sku ) ) {
		return $ls_prod->manufacturer_sku;
	}

	if( isset( $ls_prod->system_sku ) && !empty( $ls_prod->system_sku ) ) {
		return $ls_prod->system_sku;
	}

	return '';
}

function wclsi_span_tooltip( $tooltip_msg = '' ) {
	$tooltip_src = esc_url(  WC()->plugin_url() . '/assets/images/help.png' );
	$tooltip_html =
		"<span class='tips' data-tip='$tooltip_msg' >" .
			"<img class='help_tip' src='$tooltip_src' height='16' width='16' >" .
		"</span>";

	return $tooltip_html;
}

function wclsi_verify_nonce() {
	$nonce = $_POST['wclsi_nonce'];
	if ( ! wp_verify_nonce( $nonce, 'wclsi_ajax' ) ) {
		header( "HTTP/1.0 409 Security Check." );
		exit;
	}
}

function wclsi_get_wpdb_field_type( $value ) {
	if ( is_string( $value ) ) {
		if ( is_numeric( $value ) ) {
			if (strpos($value, '.') !== false) {
				return '%f';
			} else {
				return '%d';
			}
		} else {
			return '%s';
		}
	} elseif ( is_float($value) ) {
		return '%f';
	} elseif ( is_int( $value ) ) {
		return '%d';
	}

	return '%s';
}

function set_wc_product_stock_status(WC_Product $wc_product, $quantity = null ) {

	if ( is_null( $quantity ) ) {
		$quantity = $wc_product->get_stock_quantity();
	}

	if ( $quantity > 0 ) {
		$wc_product->set_stock_status( 'instock' );
		wp_remove_object_terms( $wc_product->get_id(), array( 'outofstock' ), 'product_visibility' );
	} else {
		$wc_product->set_stock_status( 'outofstock' );
		wp_set_post_terms( $wc_product->get_id(), array( 'outofstock' ), 'product_visibility' );
	}
}

function wclsi_api_locked() {
	return isset( $_POST['wclsi_nonce'] );
}

function wclsi_get_bucket_status() {
	$bucket_level = get_transient( WCLSI_API_BUCKET_LEVEL ) ?: 0;
	$max_level = get_transient( WCLSI_API_BUCKET_LEVEL_MAX ) ?: 60;
	return (float) ($bucket_level / $max_level);
}

/**
 * Get the wait time (in MS) before the next request. Based off of LS's leaky bucket API.
 */
function wclsi_get_api_wait_time_ms() {
	$calls = wp_cache_get( 'wclsi_wait_time_calls' ) ?: 0;
	$bucket_level = get_transient( WCLSI_API_BUCKET_LEVEL );
	$wait_time = ceil( ( $bucket_level + 1 ) / 10 ) * 1000;
	$multiplier = 1 + ( $bucket_level / 100 );
	wp_cache_set( 'wclsi_wait_time_calls', $calls++ );
	return (int) ($wait_time * $multiplier) + $calls;
}

function wclsi_skip_job () {
	// Don't run the poller if there aren't any loaded items
	if ( WCLSI_Lightspeed_Prod::get_item_count() == 0 ) { return true; };

	// Don't run the poller unless we're connected
	if ( !wclsi_oauth_enabled() ) { return true; }

	// Skip if any wclsi-related requests are being run
	if ( wclsi_api_locked() ) { return true; }

	// Skip if there is an update in progress
	if ( WCLSI_Synchronizer::update_in_progress() ) { return true; }
	
	// Skip if we are syncing a product to Lightspeed
	if( wp_cache_get( WCLSI_SYNCING_PROD_TO_LS ) ) { return true; }

	// Skip if the LS bucket level is nearing 60/60 in progress
	$bucket_level = get_transient( WCLSI_API_BUCKET_LEVEL );
	if( WP_DEBUG ) {
		global $WCLSI_WC_Logger;
		$WCLSI_WC_Logger->add(WCLSI_DEBUGGER_LOG, PHP_EOL . "Background Job Bucket Level Check: {$bucket_level}/60" . PHP_EOL);
	}
	
	if ( wclsi_get_bucket_status() > 0.8 ) { return true; }

	return false;
}

/**
 * Given an array of WC_Products, returns a filtered array of WCLSI_Lightspeed_Prods that correspond to the wc_prod_id
 * column in the wclsi_items table.
 * @param array $wc_prods
 * @param string $key_index - item_id by default, can be id or another property of WCLSI_Lightspeed_Product instance
 * @return array
 */
function get_syncable_wc_prods($wc_prods, $key_index = 'item_id' ) {
	$wclsi_prods = array();
	foreach( $wc_prods as $wc_prod ) {
		if ( is_a( $wc_prod, 'WC_Product' ) && WCLSI_Lightspeed_Prod::is_linked( $wc_prod->get_id() ) ) {
			$syncable = get_post_meta( $wc_prod->get_id(), WCLSI_SYNC_POST_META, true );
			if( $syncable ) {
				$ls_item = new WCLSI_Lightspeed_Prod();
				$ls_item->init_via_wc_prod_id( $wc_prod->get_id(), true );
				$wclsi_prods[ $ls_item->{$key_index} ] = $ls_item;   
			}
		}
	}
	
	return $wclsi_prods;
}

/**
 * @param string $error_msg
 * @param mixed $error_data
 * @param bool $show_admin_error
 * @return string
 */
function wclsi_log_error( $error_msg, $error_data = null, $show_admin_error = false ) {
	global $WCLSI_WC_Logger;
	$formatted_msg = sprintf("%s\n%s\n%s\n", $error_msg, print_r( $error_data, true ), wclsi_get_stack_trace());
	$WCLSI_WC_Logger->add( WCLSI_ERROR_LOG, $formatted_msg );

	if ( $show_admin_error ) {
		if ( is_admin() ) {
			add_settings_error('wclsi_settings', 'wclsi_error', $error_msg );
		}
	}

	return $formatted_msg;
}

function wclsi_get_stack_trace() {
	ob_start();
	debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20);
	return ob_get_clean();
}
