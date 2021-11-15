<?php
/**
 * Class WCLSI_Test_Setup
 *
 * @package Woocommerce_Lightspeed_Integration
 */

class WCLSI_Test_Setup {

	static $matrix_item_id = null;
	static $single_item_id = null;
	static $single_item_id_with_empty_props = null;
	static $variation_ids  = [];
	static $matrix_item_response = null;
	static $single_item_response = null;
	static $single_item_with_empty_props_response = null;
	static $variation_items_response = null;

	/**
	 *  setup methods
	 */

	static function seed_single_item() {
		self::$single_item_id = WCLSI_Lightspeed_Prod::insert_ls_api_item( self::load_single_item_xml() );
		return self::$single_item_id;
	}

	static function seed_single_item_with_empty_props() {
		self::$single_item_id_with_empty_props =
			WCLSI_Lightspeed_Prod::insert_ls_api_item( self::load_single_item_with_empty_props_xml() );
		return self::$single_item_id_with_empty_props;
	}

	static function seed_matrix_item() {
		self::$matrix_item_id = WCLSI_Lightspeed_Prod::insert_ls_api_item( self::load_matrix_item_xml() );
		return self::$matrix_item_id;
	}

	static function seed_item_variations() {
		self::$variation_ids = [];
		foreach( self::load_item_variations_xml()->Item as $api_variation_item) {
			self::$variation_ids[] = WCLSI_Lightspeed_Prod::insert_ls_api_item( $api_variation_item );
		}
		return self::$variation_ids;
	}

	static function teardown_wclsi_data(){
		self::truncate_wclsi_tables();
	}

	static function seed_items(){
		self::seed_single_item();
		self::seed_single_item_with_empty_props();
		self::seed_item_variations();
		self::seed_matrix_item();
	}

	 static function seed_categories(){
		$ls_api_cat_result = self::load_cat_xml();
		foreach( $ls_api_cat_result->Category as $ls_cat) {
			LSI_Import_Categories::insert_ls_api_cat( $ls_cat );
		}

		$ls_import_cat_utility = new LSI_Import_Categories;
		$ls_import_cat_utility->generate_ls_categories();
	}

    static function seed_item_attributes(){
		$ls_api_item_attr_sets_result = self::load_item_attribute_sets_xml();

		foreach( $ls_api_item_attr_sets_result->ItemAttributeSet as $item_attr_set ) {
			WCLSI_Item_Attributes::insert_or_update_item_attribute_set( $item_attr_set );
		}
	}

	static function load_single_item_xml() {
		$result = simplexml_load_file( dirname( __FILE__ ) . '/fixtures/item.xml' );
		self::$single_item_response = json_decode( json_encode( $result ) );
		return self::$single_item_response;
	}

	static function load_single_item_with_empty_props_xml() {
		$result = simplexml_load_file( dirname( __FILE__ ) . '/fixtures/item.empty_props.xml' );
		self::$single_item_with_empty_props_response = json_decode( json_encode( $result ) );
		return self::$single_item_with_empty_props_response;
	}

	static function load_matrix_item_xml() {
		$result = simplexml_load_file( dirname( __FILE__ ) . '/fixtures/item_matrix.xml' );
		self::$matrix_item_response = json_decode( json_encode( $result ) );
		return self::$matrix_item_response;
	}

	static function load_item_variations_xml() {
		$result = simplexml_load_file( dirname( __FILE__ ) . '/fixtures/item_variations.xml' );
		self::$variation_items_response = json_decode( json_encode( $result ) );
		return self::$variation_items_response;
	}

	static function load_cat_xml() {
		$result = simplexml_load_file( dirname( __FILE__ ) . '/fixtures/item_categories.xml' );
		return json_decode( json_encode( $result ) );
	}

	static function load_item_attribute_sets_xml() {
		$result = simplexml_load_file( dirname( __FILE__ ) . '/fixtures/item_attribute_sets.xml' );
		return json_decode( json_encode( $result ) );
	}

	static function truncate_wclsi_tables(){
		global $wpdb, $WCLSI_ALL_TABLES;
		foreach( $WCLSI_ALL_TABLES as $table) {
			$wpdb->query( "TRUNCATE TABLE $table" );
		}

		get_categories();
	}
}
