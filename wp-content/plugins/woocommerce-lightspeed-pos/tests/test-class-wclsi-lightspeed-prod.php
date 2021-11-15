<?php
/**
 * Class LSI_Import_Products_Test
 *
 * @package Woocommerce_Lightspeed_Integration
 */

class WCLSI_Lightspeed_Prod_Test extends WP_UnitTestCase {

	protected $ls_api_result = null;
	protected $wclsi_prod = null;
	protected $ls_api_singulars_result = null;
	protected $wclsi_prod_with_singulars = null;

	function setUp(){
		$this->ls_api_result           = $this->load_item_xml();
		$this->ls_api_singulars_result = $this->load_item_singulars_xml();
		$this->ls_matrix_api_result    = $this->load_item_matrix_xml();

		$this->wclsi_prod    = new WCLSI_Lightspeed_Prod(
			WCLSI_Lightspeed_Prod::insert_ls_api_item( $this->ls_api_result )
		);

		$this->wclsi_prod_with_singulars = new WCLSI_Lightspeed_Prod(
			WCLSI_Lightspeed_Prod::insert_ls_api_item( $this->ls_api_singulars_result )
		);

		$this->wclsi_matrix_prod = new WCLSI_Lightspeed_Prod(
			WCLSI_Lightspeed_Prod::insert_ls_api_item( $this->ls_matrix_api_result )
		);
	}

	function tearDown(){
		$this->wclsi_prod = null;
		$this->wclsi_prod_with_singulars = null;
	}

	public static function tearDownAfterClass(){
		WCLSI_Test_Setup::teardown_wclsi_data();
	}

	private function load_item_xml() {
		$result = simplexml_load_file( dirname( __FILE__ ) . '/fixtures/item.xml' );
		return json_decode( json_encode( $result ) );
	}

	private function load_item_singulars_xml() {
		$result = simplexml_load_file( dirname( __FILE__ ) . '/fixtures/item.single_properties.xml' );
		return json_decode( json_encode( $result ) );
	}

	private function load_item_matrix_xml() {
		$result = simplexml_load_file( dirname( __FILE__ ) . '/fixtures/item_matrix.xml' );
		return json_decode( json_encode( $result ) );
	}

	function assertLightspeedObjectsEqual( $mappings, $ls_api_obj, $wclsi_prod ) {
		foreach( $mappings as $wclsi_key => $ls_key ) {

			if ( $ls_api_obj->{$ls_key} == 'true' ) {
				$ls_api_obj->{$ls_key} = true;
			} elseif( $ls_api_obj->{$ls_key} == 'false' ) {
				$ls_api_obj->{$ls_key} = false;
			} elseif ( is_object( $ls_api_obj->{$ls_key} ) && count( get_object_vars( $ls_api_obj->{$ls_key} ) ) == 0 ) {
				$ls_api_obj->{$ls_key} = null;
			}

			$this->assertEquals( $ls_api_obj->{$ls_key}, $wclsi_prod->{$wclsi_key} );
		}

		$this->assertEquals( date( 'Y-m-d H:i:s', strtotime( $ls_api_obj->createTime ) ), $wclsi_prod->create_time );
		$this->assertEquals( date( 'Y-m-d H:i:s', strtotime( $ls_api_obj->timeStamp ) ), $wclsi_prod->time_stamp );
	}

	function test_single_item_properties(){
		$mappings = array(
			'item_id' => 'itemID',
			'item_matrix_id' => 'itemMatrixID',
			'system_sku' => 'systemSku',
			'custom_sku' => 'customSku',
			'manufacturer_sku' => 'manufacturerSku',
			'default_cost' => 'defaultCost',
			'avg_cost' => 'avgCost',
			'discountable' => 'discountable',
			'tax' => 'tax',
			'archived' => 'archived',
			'item_type' => 'itemType',
			'serialized' => 'serialized',
			'description' => 'description',
			'model_year' => 'modelYear',
			'upc' => 'upc',
			'ean' => 'ean',
			'category_id' => 'categoryID',
			'tax_class_id' => 'taxClassID',
			'department_id' => 'departmentID',
			'manufacturer_id' => 'manufacturerID',
			'season_id' => 'seasonID',
			'default_vendor_id' => 'defaultVendorID',
			'item_e_commerce_id' => 'itemECommerceID',
		);

		$ls_api_obj = $this->ls_api_result;
		$wclsi_prod = $this->wclsi_prod;

		$this->assertLightspeedObjectsEqual( $mappings, $ls_api_obj, $wclsi_prod );
	}

	function test_item_shops(){
		$this->assertObjectHasAttribute( 'ItemShops', $this->ls_api_result );
		$this->assertObjectHasAttribute( 'item_shops', $this->wclsi_prod );

		$ls_api_item_shops = $this->ls_api_result->ItemShops->ItemShop;
		$wclsi_prod_item_shops = $this->wclsi_prod->item_shops;

		$this->assertEquals( count($ls_api_item_shops), count($wclsi_prod_item_shops) );

		for( $i = 0; $i < count($ls_api_item_shops); $i++ ) {
			$ls_api_item_shop = $ls_api_item_shops[$i];
			$wclsi_prod_item_shop = $wclsi_prod_item_shops[$i];

			$this->assertEquals( $ls_api_item_shop->itemShopID, $wclsi_prod_item_shop->item_shop_id );
			$this->assertEquals( $ls_api_item_shop->qoh, $wclsi_prod_item_shop->qoh );
			$this->assertEquals( $ls_api_item_shop->shopID, $wclsi_prod_item_shop->shop_id );
		}
	}

	function test_item_e_commerce(){
		$this->assertObjectHasAttribute( 'ItemECommerce', $this->ls_api_result );

		$ls_item_e_commerce = $this->ls_api_result->ItemECommerce;
		$wclsi_item_e_commerce = $this->wclsi_prod->item_e_commerce;

		$this->assertEquals( $ls_item_e_commerce->longDescription, $wclsi_item_e_commerce->long_description );
		$this->assertEquals( $ls_item_e_commerce->shortDescription, $wclsi_item_e_commerce->short_description );
		$this->assertEquals( $ls_item_e_commerce->weight, $wclsi_item_e_commerce->weight );
		$this->assertEquals( $ls_item_e_commerce->height, $wclsi_item_e_commerce->height );
		$this->assertEquals( $ls_item_e_commerce->length, $wclsi_item_e_commerce->length );
	}

	function test_item_multi_tags(){
		$this->assertObjectHasAttribute( 'tags', $this->wclsi_prod );
		$this->assertTrue( is_array( $this->wclsi_prod->tags ) );
		$this->assertEquals( 2, count( $this->wclsi_prod->tags ) );
	}

	function test_item_single_tags(){
		$this->assertObjectHasAttribute( 'tags', $this->wclsi_prod_with_singulars );
		$this->assertTrue( is_array( $this->wclsi_prod_with_singulars->tags ) );
		$this->assertEquals( 1, count( $this->wclsi_prod_with_singulars->tags ) );
	}
}