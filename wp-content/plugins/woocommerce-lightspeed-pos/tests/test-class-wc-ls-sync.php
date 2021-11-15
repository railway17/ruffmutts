<?php
/**
* Class LSI_Import_Products_Test
*
* @package Woocommerce_Lightspeed_Integration
*/

class LSI_Synchronizer_Test extends WP_UnitTestCase {

	/**
	 * @var WCLSI_Synchronizer
	 */
	public $synchronizer;

	/**
	 * @var WC_Product
	 */
	protected $wc_prod = null;

	/**
	 * @var WCLSI_Lightspeed_Prod
	 */
	protected $wclsi_matrix_prod = null;

	/**
	 * @var WCLSI_Lightspeed_Prod
	 */
	protected $wclsi_single_prod = null;

	function setUp(){
		global $WCLSI_API, $LS_PROD_SELECTIVE_SYNC_PROPERTIES;

		$WCLSI_API->settings[ WCLSI_LS_SELECTIVE_SYNC ] =
			array_fill_keys(array_keys($LS_PROD_SELECTIVE_SYNC_PROPERTIES), true);

		$this->synchronizer = new WCLSI_Synchronizer();

		$single_item_api_response = $this->load_single_item_xml();
		$matrix_item_api_response = $this->load_matrix_item_xml();
		$wclsi_single_prod_id     = WCLSI_Lightspeed_Prod::insert_ls_api_item( $single_item_api_response );
		$wclsi_matrix_prod_id     = WCLSI_Lightspeed_Prod::insert_ls_api_item( $matrix_item_api_response );

		$this->wclsi_single_prod = new WCLSI_Lightspeed_Prod( $wclsi_single_prod_id );
		$this->wclsi_matrix_prod = new WCLSI_Lightspeed_Prod( $wclsi_matrix_prod_id );

		$post_id = $this->import_product( $this->wclsi_single_prod );
		$this->wc_prod = wc_get_product( $post_id );
	}

	function tearDown(){
		if ( !is_null( $this->wclsi_single_prod ) ) {
			$this->wclsi_single_prod->delete();
			$this->wclsi_single_prod = null;
		}

		if ( !is_null( $this->wclsi_matrix_prod ) ) {
			$this->wclsi_matrix_prod->delete();
			$this->wclsi_matrix_prod = null;
		}

		if ( !is_null( $this->wc_prod ) ) {
			$this->wc_prod->delete(true);
			$this->wc_prod = null;
		}
	}

	function import_product($prod, $sync = false, $img_flag = true) {
		$import_utility = new LSI_Import_Products;
		return $import_utility->import_item( $prod, $sync, $img_flag );
	}

	private function load_single_item_xml() {
		$result = simplexml_load_file( dirname( __FILE__ ) . '/fixtures/item.xml' );
		return json_decode( json_encode( $result ) );
	}

	private function load_matrix_item_xml() {
		$result = simplexml_load_file( dirname( __FILE__ ) . '/fixtures/item_matrix.xml' );
		return json_decode( json_encode( $result ) );
	}

	/**
	 * Tests
	 */
	function test_build_ls_update_payload(){
		$wc_prod = $this->wc_prod;

		$result = $this->synchronizer->build_ls_update_payload( $wc_prod, $this->wclsi_single_prod->item_id );

		$this->assertEquals( $wc_prod->get_sku(), $result->customSku );
		$this->assertEquals( $wc_prod->get_name(), $result->description );

		$item_e_ecommerce = $result->ItemECommerce;
		$this->assertEquals( $wc_prod->get_description(), $item_e_ecommerce->longDescription );
		$this->assertEquals( $wc_prod->get_short_description(), $item_e_ecommerce->shortDescription );
		$this->assertEquals( $wc_prod->get_weight(), $item_e_ecommerce->weight );
		$this->assertEquals( $wc_prod->get_width(), $item_e_ecommerce->width );
		$this->assertEquals( $wc_prod->get_height(), $item_e_ecommerce->height );
		$this->assertEquals( $wc_prod->get_length(), $item_e_ecommerce->length );

		$prices = $result->Prices;
		$regular_price = $prices[0]->ItemPrice->amount;
		$sale_price = $prices[2]->ItemPrice->amount;
		$this->assertEquals( $wc_prod->get_regular_price(), $regular_price );
		$this->assertEquals( $wc_prod->get_sale_price(), $sale_price );

		$item_shops = $result->ItemShops['ItemShop'];
		foreach($item_shops as $shop) {
			$this->assertEquals( $wc_prod->get_stock_quantity(), $shop['qoh'] );
		}
	}

	function test_build_ls_update_payload_with_selective_sync(){
		global $WCLSI_API;
		$WCLSI_API->settings[ WCLSI_LS_SELECTIVE_SYNC ] =
			array(
				'customSku' => true,
				'description' => true,
				'sale_price' => true
			);

		$wc_prod = $this->wc_prod;
		$result = $this->synchronizer->build_ls_update_payload( $wc_prod, $this->wclsi_single_prod->item_id );

		$this->assertEquals( $wc_prod->get_sku(), $result->customSku );
		$this->assertEquals( $wc_prod->get_name(), $result->description );

		$prices = $result->Prices;
		$sale_price = $prices[2]->ItemPrice->amount;
		$this->assertEquals( isset($prices[0]), false );
		$this->assertEquals( $wc_prod->get_sale_price(), $sale_price );
		$this->assertEquals( isset( $result->ItemShops ), false );
		$this->assertEquals( isset( $result->ItemECommerce ), false );
	}

	function test_insert_new_ls_item(){
		$prod_id = $this->import_product( $this->wclsi_matrix_prod );
		$wc_variable_prod = wc_get_product( $prod_id );
		$wclsi_variation = WCLSI_Test_Setup::load_item_variations_xml()->Item[0];
		WCLSI_Synchronizer::insert_new_ls_item( $wclsi_variation );

		$wc_variation_count    = count( $wc_variable_prod->get_available_variations() );
		$wclsi_variation_count = count( $this->wclsi_matrix_prod->variations );

		$this->assertEquals( $wc_variation_count, 1 );
		$this->assertEquals( $wclsi_variation_count, 1 );
	}
}

