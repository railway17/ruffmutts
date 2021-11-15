<?php
/**
 * Class WCLSI_Item_Poller_Test
 *
 * @package Woocommerce_Lightspeed_Integration
 */

class WCLSI_Item_Poller_Test extends WP_UnitTestCase {
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
	 * Test items
	 */
	static $single_item_id = null;
	static $matrix_item_id = null;
	static $variation_ids = [];

	static function load_single_item_poll_response_xml() {
		$result = simplexml_load_file( dirname( __FILE__ ) . '/fixtures/item_poller_fixtures/item.update.xml' );
		return json_decode( json_encode( $result ) );
	}

	static function load_matrix_item_poll_response_xml() {
		$result = simplexml_load_file( dirname( __FILE__ ) . '/fixtures/item_poller_fixtures/item_matrix.update.xml' );
		return json_decode( json_encode( $result ) );
	}

	static function setUpBeforeClass() {
		global $WCLSI_API, $WC_PROD_SELECTIVE_SYNC_PROPERTIES;

		$WCLSI_API->settings[ WCLSI_WC_SELECTIVE_SYNC ] =
			array_fill_keys(array_keys($WC_PROD_SELECTIVE_SYNC_PROPERTIES), true);
	}

	function setUp() {
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

		// Delete options/transients if they exist from the last test run
		delete_option( WCLSI_LAST_SYNC_TIMESTAMP );
		delete_transient( WCLSI_ITEM_POLL );
	}

	function tearDown() {
		delete_option( WCLSI_LAST_SYNC_TIMESTAMP );
		delete_transient( WCLSI_ITEM_POLL );

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

	function test_poll_timestamps() {
		update_option( 'wclsi_oauth_token', 1234 );

		$last_poll_timestamp = get_option( WCLSI_LAST_SYNC_TIMESTAMP );
		$poll_transient = get_transient( WCLSI_ITEM_POLL );

		$this->assertFalse( $last_poll_timestamp );
		$this->assertFalse( $poll_transient );

		$api_stub = $this->createMock(LSI_Init_Settings::class);
		$api_stub->method('make_api_call')
		         ->willReturn( array() );

		$import_util = new LSI_Import_Products();
		WCLSI_Item_Poller::poll($api_stub, $import_util);

		// Re-fetch timestamp, transient
		$last_poll_timestamp = get_option( WCLSI_LAST_SYNC_TIMESTAMP );
		$poll_transient = get_transient( WCLSI_ITEM_POLL );

		$this->assertNotFalse( $last_poll_timestamp );
		$this->assertNotFalse( $poll_transient );
	}

	function test_poll_update_api_single_item_response() {
		$import_util = new LSI_Import_Products();
		$api_stub = $this->createMock(LSI_Init_Settings::class);
		$api_stub->method('make_api_call')
				 ->will($this->onConsecutiveCalls(
					 self::load_single_item_poll_response_xml(),
					 array()
				 ));

		$wc_prod_id = $import_util->import_item( $this->wclsi_single_prod, true, true );
		$this->wc_product_simple = wc_get_product( $wc_prod_id );

		$this->assertEquals( $this->wc_product_simple->get_title(), 'Red Shirt' );
		$this->assertEquals( $this->wc_product_simple->get_description(), '<p>Great shirt for every occasion!</p>' );
		$this->assertEquals(
			$this->wc_product_simple->get_short_description(),
			'<p>Great shirt for every occasion!<br />I love red shirts! -Kristina</p>'
		);

		WCLSI_Item_Poller::poll( $api_stub, $import_util );

		$this->wc_product_simple = wc_get_product( $wc_prod_id );
		$this->assertEquals( $this->wc_product_simple->get_title(), 'AWESOME Red Shirt' );
		$this->assertEquals( $this->wc_product_simple->get_description(), 'AWESOME shirt for every occasion!' );
		$this->assertEquals( $this->wc_product_simple->get_short_description(), 'Woohoo AWESOME shirt!' );
	}

	function test_poll_update_api_matrix_item_response() {
		$import_util = new LSI_Import_Products();
		$api_stub = $this->createMock(LSI_Init_Settings::class);
		$api_stub->method('make_api_call')
		         ->will($this->onConsecutiveCalls(
			         array(),
			         self::load_matrix_item_poll_response_xml()
		         ));

		$wc_prod_id = $import_util->import_item( $this->wclsi_matrix_prod, true, true );
		$this->wc_product_variable = wc_get_product( $wc_prod_id );

		$this->assertEquals( $this->wc_product_variable->get_title(), 'T-shirt' );
		$this->assertEquals(
			$this->wc_product_variable->get_description(),
			'Testing some cool matrix syncing between WC and LS.'
		);

		WCLSI_Item_Poller::poll( $api_stub, $import_util );

		$this->wc_product_variable = wc_get_product( $wc_prod_id );
		$this->assertEquals( $this->wc_product_variable->get_title(), 'T-shirt - matrix polling update' );
		$this->assertEquals( $this->wc_product_variable->get_description(), 'matrix polling update!' );
	}
}
