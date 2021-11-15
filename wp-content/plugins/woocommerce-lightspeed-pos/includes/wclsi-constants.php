<?php
global $wpdb;
define( 'WCLSI_VERSION', '1.9.1' );
define( 'WCLSI_DB_VERSION_OPTION', 'wclsi_db_version' );
define( 'WCLSI_MENU_NAME', __( 'Lightspeed', 'woocommerce-lightspeed-pos' ) );
define( 'WCLSI_ADMIN_PAGE_TITLE', __( 'Lightspeed', 'woocommerce-lightspeed-pos' ) );
define( 'WCLSI_ADMIN_URL', admin_url( 'admin.php?page=lightspeed-import-page' ) );
define( 'WCLSI_ADMIN_SETTINGS_URL', admin_url( 'admin.php?page=wc-settings&tab=integration&section=lightspeed-integration' ) );
define( 'WCLSI_CAT_CHUNK_PREFIX', 'wclsi_cat_chunk_' );
define( 'WCLSI_TOTAL_CAT_CHUNKS', 'wclsi_total_cat_chunks' );
define( 'WCLSI_PROD_CHUNK_PREFIX', 'wclsi_prod_chunk_' );
define( 'WCLSI_MATRIX_CHUNK_PREFIX', 'wclsi_matrix_chunk_' );
define( 'WCLSI_TOTAL_MATRIX_CHUNKS', 'wclsi_matrix_chunks' );
define( 'WCLSI_TOTAL_PROD_CHUNKS', 'wclsi_total_chunks' );
define( 'WCLSI_ATTRS_CACHE', 'wclsi_attrs_cache' );
define( 'WCLSI_SYNC_POST_META', '_wclsi_sync' );
define( 'WCLSI_LAST_SYNC_TIMESTAMP', 'wclsi_last_sync_timestamp' );
define( 'WCLSI_INVENTORY_SHOP_ID', 'ls_inventory_store' );
define( 'WCLSI_LOG', 'wclsi-sync-events-log' );
define( 'WCLSI_DEBUGGER_LOG', 'wclsi-debug-log' );
define( 'WCLSI_ERROR_LOG', 'wclsi-errors-log');
define( 'WCLSI_SCREEN_ID', 'woocommerce_page_lightspeed-import-page' );
define( 'WCLSI_SINGLE_ITEM_ID_POST_META', '_wclsi_item_id' );
define( 'WCLSI_MATRIX_ID_POST_META', '_wclsi_matrix_id' );
define( 'WCLSI_REFRESH_CONNECTOR_URL', 'https://connect.woocommerce.com/renew/lightspeed/' );
define( 'WCLSI_LAST_LOAD_TIMESTAMP', 'wclsi_load_timestamp' );
define( 'WCLSI_NEW_PROD_TRANSIENT', 'wclsi-sync-new-prods' );
define( 'WCLSI_LS_TO_WC_AUTOLOAD', 'ls_to_wc_auto_load' );
define( 'WCLSI_PRUNE_DELETED_VARIATIONS', 'wclsi_prune_deleted_variations' );
define( 'WCLSI_POLLER_SETTING', 'wclsi_poller_setting' );
define( 'WCLSI_AUTOLOAD_LS_ATTRS', 'wclsi_autoload_ls_attrs' );
define( 'WCLSI_IGNORE_ARCHIVED_LS_PRODS', 'wclsi_ignore_archived_ls_prods' );
define( 'WCLSI_IMPORT_ON_AUTOLOAD', 'wclsi_import_on_auto_load');
define( 'WCLSI_WC_SELECTIVE_SYNC', 'wclsi_wc_selective_sync' );
define( 'WCLSI_LS_SELECTIVE_SYNC', 'wclsi_ls_selective_sync' );
define( 'WCLSI_DOCS_URL', 'https://docs.woothemes.com/document/woocommerce-lightspeed-pos/' );
define( 'WCLSI_ACCOUNT_ID', 'wclsi_account_id' );
define( 'WCLSI_OAUTH_TOKEN', 'wclsi_oauth_token' );
define( 'WCLSI_REFRESH_TOKEN', 'wclsi_refresh_token' );
define( 'WCLSI_ITEM_POLL', 'wclsi_item_poll' );
define( 'WCLSI_API_BUCKET_LEVEL', 'wclsi_api_bucket_level' );
define( 'WCLSI_API_BUCKET_LEVEL_MAX', 'wclsi_api_bucket_level_max' );
define( 'WCLSI_API_DRIP_RATE', 'wclsi_api_drip_rate');
define( 'WCLSI_SYNCING_PROD_TO_LS', 'wclsi_syncing_prod_to_ls' );
define( 'WCLSI_ORDER_CANCELLED', 'wclsi_order_cancelled' );
define( 'WCLSI_NOTIFICATIONS', 'wclsi_notifications' );
define( 'WCLSI_REMOVE_UNCATEGORIZED_CAT', 'wclsi_remove_uncategorized_category' );

global $WCLSI_ITEM_TABLE, $WCLSI_ITEM_IMAGES_TABLE, $WCLSI_ITEM_SHOP_TABLE, $WCLSI_ITEM_PRICES_TABLE,
	   $WCLSI_ITEM_E_COMMERCE_TABLE, $WCLSI_ITEM_CATEGORIES_TABLE, $WCLSI_ITEM_ATTRIBUTE_SETS_TABLE,
	   $WCLSI_RELATED_ITEM_TABLES, $WCLSI_SINGLE_LOAD_RELATIONS, $WCLSI_MATRIX_LOAD_RELATIONS,
	   $WCLSI_ALL_TABLES, $WCLSI_objectL10n, $WC_PROD_SELECTIVE_SYNC_PROPERTIES, $LS_PROD_SELECTIVE_SYNC_PROPERTIES;

$WCLSI_ITEM_TABLE                = "{$wpdb->prefix}wclsi_items";
$WCLSI_ITEM_IMAGES_TABLE         = "{$wpdb->prefix}wclsi_item_images";
$WCLSI_ITEM_SHOP_TABLE           = "{$wpdb->prefix}wclsi_item_shops";
$WCLSI_ITEM_PRICES_TABLE         = "{$wpdb->prefix}wclsi_item_prices";
$WCLSI_ITEM_E_COMMERCE_TABLE     = "{$wpdb->prefix}wclsi_item_e_commerce";
$WCLSI_ITEM_CATEGORIES_TABLE     = "{$wpdb->prefix}wclsi_item_categories";
$WCLSI_ITEM_ATTRIBUTE_SETS_TABLE = "{$wpdb->prefix}wclsi_item_attribute_sets";

$WCLSI_RELATED_ITEM_TABLES = array(
	$WCLSI_ITEM_IMAGES_TABLE,
	$WCLSI_ITEM_SHOP_TABLE,
	$WCLSI_ITEM_PRICES_TABLE,
	$WCLSI_ITEM_E_COMMERCE_TABLE
);

$WCLSI_ALL_TABLES = array(
	$WCLSI_ITEM_TABLE,
	$WCLSI_ITEM_SHOP_TABLE,
	$WCLSI_ITEM_PRICES_TABLE,
	$WCLSI_ITEM_IMAGES_TABLE,
	$WCLSI_ITEM_E_COMMERCE_TABLE,
	$WCLSI_ITEM_CATEGORIES_TABLE,
	$WCLSI_ITEM_ATTRIBUTE_SETS_TABLE
);

$WCLSI_SINGLE_LOAD_RELATIONS = array(
	"ItemShops",
	"ItemECommerce",
	"Images",
	"Tags",
	"ItemAttributes",
	"CustomFieldValues"
);
$WCLSI_MATRIX_LOAD_RELATIONS = array(
	"ItemECommerce",
	"Tags",
	"Images"
);

$WCLSI_objectL10n = array(
	'reload_confirm'  => __( 'Products have already been loaded, are you sure you want to reload them?', 'woocommerce-lightspeed-pos' ),
	'importing_prods' => __( 'Importing Lightspeed products ... ', 'woocommerce-lightspeed-pos' ),
	'updating_prods'   => __( 'Updating Lightspeed products ... ', 'woocommerce-lightspeed-pos'),
	'dont_close'      => __( 'This may take a while... please do not close this window while products are being processed!', 'woocommerce-lightspeed-pos' ),
	'done_importing'  => sprintf( __( 'Import completed! Click <a href="%s">here</a> to view imported products', 'woocommerce-lightspeed-pos' ), admin_url( 'edit.php?post_type=product' ) ),
	'no_prods_error'  => __( 'Error: No products to import!', 'woocommerce-lightspeed-pos' ),
	'try_again'       => __( 'A connection could not be made to Lightspeed, please try again.', 'woocommerce-lightspeed-pos' ),
	'sync_error'      => __( 'Please import this item before attempting to sync it!', 'woocommerce-lightspeed-pos' ),
	'sync_success'    => __( 'Product successfully added to sync schedule.', 'woocommerce-lightspeed-pos' ),
	'relink_success'  => __( 'Relink successful! The associated Lightspeed product should now be viewable on the <a href="' . WCLSI_ADMIN_URL . '">Lightspeed Import page</a>.', 'woocommerce-lightspeed-pos' ),
	'sync_remove'     => __( 'Product successfully removed from sync schedule.', 'woocommerce-lightspeed-pos' ),
	'syncing'         => __( 'Syncing...', 'woocommerce-lightspeed-pos' ),
	'man_sync_success' => __( 'Successfully synced!', 'woocommerce-lightspeed-pos' ),
	'prod_processing_error' => __( 'Uh oh! There were some errors with processing some of the products.', 'woocommerce-lightspeed-pos'),
	'generic_error'    => __( 'Something went wrong! Please try again later.', 'woocommerce-lightspeed-pos' ),
	'provide_account_id'  => __( 'Please provide an account ID before submitting!', 'woocommerce-lightspeed-pos' ),
	'api_connection_good' => __( 'Connection successful!', 'woocommerce-lightspeed-pos' ),
	'api_connection_bad'  => __( 'A connection could not be made to your Lightspeed account!', 'woocommerce-lightspeed-pos' ),
	'incomplete_load' => __( 'Error: something went wrong with loading products from Lightspeed! Refresh to see if some products loaded successfully.', 'woocomerce-lightspeed-pos'),
	'loading_matrix_products' => __( 'Loading matrix products', 'woocomerce-lightspeed-pos'),
	'loading_categories' => __( 'Loading categories', 'woocomerce-lightspeed-pos'),
	'loading_item_attrs' => __( 'Loading item attribute sets', 'woocomerce-lightspeed-pos'),
	'upgrade_complete' => __( 'Upgrade successfully completed!', 'woocommerce-lightspeed-pos'),
	'bad_sync_to_ls' => __( 'The synchronization to Lightspeed experienced some issues. Please log into Lightspeed and verify your product was synced properly.'),
	'cat_cache_clear_success' => __( 'Category cache succesfully cleared!', 'woocommerce-lightspeed-pos'),
	'processing_order' => __( 'Processing products', 'woocommerce-lightspeed-pos'),
	'ls_out_of_stock' => __( 'We apologize, one or more of the items')
);

$WC_PROD_SELECTIVE_SYNC_PROPERTIES = array(
	'name' => 'Name',
	'sku' => 'SKU',
	'regular_price' => 'Regular Price',
	'sale_price' => 'Sale Price',
	'stock_quantity' => 'Stock Quantity',
	'images' => 'Images (both featured and gallery images)',
	'attributes' => 'Attributes (for Matrix/Variable products)',
	'short_description' => 'Short Description',
	'description' => 'Long Description',
	'weight' => 'Weight',
	'length' => 'Length',
	'width' => 'Width',
	'height' => 'Height',
);

$LS_PROD_SELECTIVE_SYNC_PROPERTIES = array(
	'description' => 'Name',
	'customSku' => 'SKU (maps to Custom SKU in LightSpeed)',
	'regular_price' => 'Regular Price',
	'sale_price' => 'Sale Price',
	'stock_quantity' => 'Stock Quantity',
	'shortDescription' => 'Short Description',
	'longDescription' => 'Long Description',
	'weight' => 'Weight',
	'length' => 'Length',
	'width' => 'Width',
	'height' => 'Height',
);
