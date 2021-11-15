<?php
/**
 * Class LSI_Import_Products_Test
 *
 * @package Woocommerce_Lightspeed_Integration
 */

class LSI_Import_Products_Test extends WP_UnitTestCase {
	/**
	 * @var WCLSI_Lightspeed_Prod
	 */
	protected $wclsi_single_prod = null;

	/**
	 * @var WCLSI_Lightspeed_Prod
	 */
	protected $wclsi_matrix_prod = null;

	/**
	 * @array
	 */
	protected $wclsi_matrix_variations = [];

	/**
	 * @var WC_Product_Variation
	 */
	protected $wc_product_variable = null;

	/**
	 * @var WC_Product_Simple
	 */
	protected $wc_product_simple = null;

	/**
	 * @var LSI_Import_Products
	 */
	static $import_utility;

	/**
	 * Test items
	 */
	static $single_item_id = null;
	static $matrix_item_id = null;
	static $variation_ids = [];

	/**
	 * Setup
	 */
	public static function setUpBeforeClass() {
		WCLSI_Test_Setup::seed_categories();
		WCLSI_Test_Setup::seed_item_attributes();
		self::$import_utility = new LSI_Import_Products;
	}

	function setUp(){
		WCLSI_Test_Setup::seed_items();
		self::$single_item_id = WCLSI_Test_Setup::$single_item_id;
		self::$matrix_item_id = WCLSI_Test_Setup::$matrix_item_id;
		self::$variation_ids  = WCLSI_Test_Setup::$variation_ids;

		// Load variations first
		foreach( self::$variation_ids as $variation_id ) {
			$this->wclsi_matrix_variations[$variation_id] = new WCLSI_Lightspeed_Prod( $variation_id );
		}

		$this->wclsi_single_prod = new WCLSI_Lightspeed_Prod( self::$single_item_id );
		$this->wclsi_matrix_prod = new WCLSI_Lightspeed_Prod( self::$matrix_item_id );
	}

	function tearDown(){
		if ( !is_null( $this->wclsi_single_prod ) ) {
			$this->wclsi_single_prod->delete();
			$this->wclsi_single_prod = null;
		}

		if ( !is_null( $this->wclsi_matrix_prod ) ) {
			$this->wclsi_matrix_prod->delete(true);
			$this->wclsi_matrix_variations = [];
			$this->wclsi_matrix_prod = null;
		}

		// Clear out all Woo products
		$wc_products = wc_get_products([]);
		if ( !empty( $wc_products ) ) {
			foreach( $wc_products as $wc_product ) {
				$wc_product->delete(true);
			}
		}

		$this->wc_product_variable = null;
		$this->wc_product_simple   = null;
	}

	public static function tearDownAfterClass(){
		WCLSI_Test_Setup::teardown_wclsi_data();
	}

	function import_product($prod, $sync = false, $img_flag = true) {
		return self::$import_utility->import_item( $prod, $sync, $img_flag );
	}

	function update_product ($prod ) {
		return self::$import_utility->update_wc_prod( $prod );
	}

	/*********
	 * Tests *
	 *********/

	/**
	 * Single item
	 */
	function test_import_single_product(){
		$prod_id = $this->import_product( $this->wclsi_single_prod );

		$this->wc_product_simple = wc_get_product( $prod_id );
		$wclsi_item_id_post_meta = get_post_meta( $prod_id, WCLSI_SINGLE_ITEM_ID_POST_META, true );

		$this->assertNotNull( $this->wc_product_simple );
		$this->assertEquals( $wclsi_item_id_post_meta, $this->wclsi_single_prod->item_id );
		$this->assertEquals( $this->wc_product_simple->get_title(), $this->wclsi_single_prod->description );
		$this->assertEquals(
			$this->wc_product_simple->get_regular_price(),
			$this->wclsi_single_prod->prices[0]->amount
		);
		$this->assertEquals(
			$this->wc_product_simple->get_sale_price(),
			$this->wclsi_single_prod->prices[1]->amount
		);
	}

	function test_single_product_update() {
		$prod_id = $this->import_product( $this->wclsi_single_prod );
		$this->wc_product_simple = wc_get_product( $prod_id );

		$this->wclsi_single_prod->reload();
		$item_e_commerce = $this->wclsi_single_prod->item_e_commerce;

		$this->assertEquals( $this->wclsi_single_prod->wc_prod_id, $prod_id );
		$this->assertEquals( $this->wc_product_simple->get_title(), $this->wclsi_single_prod->description );
		$this->assertEquals( $this->wc_product_simple->get_description(), $item_e_commerce->long_description );
		$this->assertEquals( $this->wc_product_simple->get_short_description(), $item_e_commerce->short_description );

		$update_api_item = WCLSI_Test_Setup::$single_item_with_empty_props_response;
		WCLSI_Lightspeed_Prod::update_via_api_item( $update_api_item, $this->wclsi_single_prod );

		$this->wc_product_simple->set_description('foo');
		$this->wc_product_simple->set_short_description('bar');
		$this->wc_product_simple->save();

		$this->update_product( $this->wclsi_single_prod );

		// Empty props from the Lightspeed side will not overwrite existing wc props
		$this->assertEquals( $this->wc_product_simple->get_description(), 'foo' );
		$this->assertEquals( $this->wc_product_simple->get_short_description(), 'bar' );
	}

	/**
	 * Matrix items
	 */

	function test_matrix_product_import() {
		$product_id = $this->import_product( $this->wclsi_matrix_prod );

		$this->wc_product_variable = wc_get_product( $product_id );
		$this->assertNotFalse( $this->wc_product_variable );

		$wclsi_item_matrix_id_post_meta =
			get_post_meta( $this->wc_product_variable->get_id(), WCLSI_MATRIX_ID_POST_META, true );

		$this->assertEquals( $wclsi_item_matrix_id_post_meta, $this->wclsi_matrix_prod->item_matrix_id );
		$this->assertEquals( $this->wc_product_variable->get_title(), $this->wclsi_matrix_prod->description );

		$wc_variations = $this->wc_product_variable->get_available_variations();
		$this->assertEquals( count( $wc_variations ), count( $this->wclsi_matrix_variations ) );

		/**
		 * Test matrix variations
		 */
		foreach( $this->wclsi_matrix_variations as $wclsi_variation ) {
			$wclsi_variation->reload();

			$wc_variation = wc_get_product( $wclsi_variation->wc_prod_id );

			$this->assertNotFalse( $wc_variation );

			$wclsi_item_id_post_meta =
				get_post_meta( $wc_variation->get_id(), WCLSI_SINGLE_ITEM_ID_POST_META, true );

			$this->assertEquals( $wclsi_item_id_post_meta, $wclsi_variation->item_id );
			$this->assertEquals( $wc_variation->get_sku(), $wclsi_variation->custom_sku );
			$this->assertEquals( $wc_variation->get_sale_price(), $wclsi_variation->get_sale_price());
			$this->assertEquals( $wc_variation->get_regular_price(), $wclsi_variation->get_regular_price());

			/**
			 * Test attributes
			 * TODO - find a better way to getting wclsi attrs
			 * index 0 - color
			 * index 1 - size
			 */
			$wc_attrs = $wc_variation->get_attributes();
			$wclsi_attrs = $wclsi_variation->item_attributes;
			$this->assertEquals( $wc_attrs['color'], $wclsi_attrs->attribute1 );
			$this->assertEquals( $wc_attrs['size'], $wclsi_attrs->attribute2 );
		}
	}

	/**
	 * Pricing
	 */
	function test_inserted_item_price() {
		$wclsi_regular_price = $this->wclsi_single_prod->get_regular_price();
		$wclsi_sale_price    = $this->wclsi_single_prod->get_sale_price();
		$this->assertEquals( $wclsi_sale_price, '10.99' );
		$this->assertEquals( $wclsi_regular_price, '12.99' );
	}

	/**
	 * Taxonomy loading
	 */
	function test_import_categories(){
		$terms = get_terms( array(
			'taxonomy' => 'product_cat',
			'hide_empty' => false,
			'number' => 0
		) );

		// includes "Uncategorized"
		$this->assertEquals( count($terms), 101);
	}

	function test_product_categories(){
		$post_id = $this->import_product( $this->wclsi_single_prod );
		$terms = wp_get_post_terms($post_id, 'product_cat');

		// includes "Uncategorized"
		$this->assertEquals( count($terms), 3 );
	}

	/**
	 * Selective sync
	 */
	function test_selective_sync_on_import() {
		global $WCLSI_API;
		$WCLSI_API->settings[ 'wclsi_wc_selective_sync' ] = array( 'name' => 'true' );

		$post_id = $this->import_product( $this->wclsi_single_prod );
		$wc_prod = wc_get_product( $post_id );

		$this->assertEquals( $wc_prod->get_name(), $this->wclsi_single_prod->description );

		$item_e_commerce = $this->wclsi_single_prod->item_e_commerce;

		$this->assertEquals( $wc_prod->get_description(), $item_e_commerce->long_description );
		$this->assertEquals( $wc_prod->get_short_description(), $item_e_commerce->short_description );
		$this->assertEquals( $wc_prod->get_weight(), $item_e_commerce->weight );
		$this->assertEquals( $wc_prod->get_height(), $item_e_commerce->height );
		$this->assertEquals( $wc_prod->get_length(), $item_e_commerce->length );
		$this->assertEquals( $wc_prod->get_width(), $item_e_commerce->width );
		$this->assertEquals( $wc_prod->get_regular_price(), $this->wclsi_single_prod->get_regular_price() );
		$this->assertEquals( $wc_prod->get_sale_price(), $this->wclsi_single_prod->get_sale_price() );

		$stocky_quantity = wclsi_get_lightspeed_inventory( $this->wclsi_single_prod );
		$this->assertEquals( $wc_prod->get_stock_quantity(), $stocky_quantity );
	}
}