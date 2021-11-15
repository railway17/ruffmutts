<?php
/**
 * Class LSI_Import_Products_Test
 *
 * @package Woocommerce_Lightspeed_Integration
 */
class LSI_Init_Settings_Test extends WP_UnitTestCase {

	/**
	 * @var LSI_Init_Settings
	 */
	public $wclsi_settings;

	function setUp() {
		$this->wclsi_settings = new LSI_Init_Settings();
	}

	function tearDown() {
		$this->clear_wclsi_data();
	}

	function account_api_response() {
		$result = simplexml_load_file( dirname( __FILE__ ) . '/fixtures/account.xml' );
		return json_decode( json_encode( $result ) );
	}

	function multi_shop_api_response() {
		$result = simplexml_load_file( dirname( __FILE__ ) . '/fixtures/multi_shop.xml' );
		return json_decode( json_encode( $result ) );
	}

	function single_shop_api_response() {
		$result = simplexml_load_file( dirname( __FILE__ ) . '/fixtures/single_shop.xml' );
		return json_decode( json_encode( $result ) );
	}

	function clear_wclsi_data() {
		delete_option( 'wclsi_oauth_token' );
		delete_option( 'wclsi_refresh_token' );
		delete_option( 'wclsi_expires_in' );
		delete_option( 'woocommerce_lightspeed-integration_settings' );
	}

	function setup_oath($multi_shop = true) {
		$ls_api_double = $this->createMock(WP_MOSAPICall::class);

		if ( $multi_shop ) {
			$shop_response = $this->multi_shop_api_response();
		} else {
			$shop_response = $this->single_shop_api_response();
		}

		$account_response = $this->account_api_response();

		$value_map = [
			['Account', 'Read', null, [], '', null, 'json', $account_response],
			["Account/119658/Shop/", 'Read', null, [], '', null, 'json', $shop_response]
		];

		$ls_api_double->method('makeAPICall')
					  ->will($this->returnValueMap($value_map));

		$this->assertEquals( $account_response, $ls_api_double->makeAPICall('Account', 'Read') );
		$this->assertEquals( $shop_response, $ls_api_double->makeAPICall("Account/119658/Shop/", 'Read') );

		$this->wclsi_settings->init_wclsi_settings_with_token( $ls_api_double, 'token', 'refresh_token', 600 );
	}

	function test_uninitialized_state() {
		$this->assertEmpty( $this->wclsi_settings->token );
		$this->assertEmpty( $this->wclsi_settings->ls_account_id );
		$this->assertObjectNotHasAttribute( 'MOSAPI', $this->wclsi_settings );
		$this->assertObjectNotHasAttribute( 'store_timezone', $this->wclsi_settings );
		$this->assertObjectNotHasAttribute( 'store_name', $this->wclsi_settings );
		$this->assertObjectNotHasAttribute( 'ls_enabled_stores', $this->wclsi_settings );
	}

	function test_oauth() {
		$this->setup_oath();

		$token = get_option( 'wclsi_oauth_token' );
		$refresh_token = get_option( 'wclsi_refresh_token' );
		$expires_in = get_option( 'wclsi_expires_in' );

		$this->assertEquals( $token, 'token' );
		$this->assertEquals( $refresh_token, 'refresh_token' );
		$this->assertNotEmpty( $expires_in );

		$account_id = get_option( 'wclsi_account_id' );

		$this->assertEquals( '119658', $account_id );
		$this->assertEquals( $account_id, $this->wclsi_settings->ls_account_id );
	}

	function test_multi_shop() {
		$this->setup_oath();

		$settings = get_option( 'woocommerce_lightspeed-integration_settings' );
		$shop_data = get_option( 'wclsi_shop_data' );
		$store_data = $shop_data['ls_store_data'];

		$this->assertEquals( 'US/Pacific', $shop_data['store_timezone'] );
		$this->assertEquals( 'Multiple', $shop_data['store_name'] );
		$this->assertEquals( 3, $store_data->{'@attributes'}->count );
		$this->assertEmpty( $settings['ls_enabled_stores'] );
	}

	function test_single_shop() {
		$this->setup_oath( false );

		$settings = get_option( 'woocommerce_lightspeed-integration_settings' );
		$shop_data = get_option( 'wclsi_shop_data' );
		$store_data = $shop_data['ls_store_data'];

		$this->assertEquals( 'Europe/Paris', $shop_data['store_timezone'] );
		$this->assertEquals( 'Woot', $shop_data['store_name'] );
		$this->assertEquals( 1, $store_data->{'@attributes'}->count );
		$this->assertEquals( 1, $settings[WCLSI_INVENTORY_SHOP_ID] );
		$this->assertEquals( array('Woot' => 1), $settings['ls_enabled_stores'] );
	}

	function test_seed_data() {
		$settings = $this->wclsi_settings->settings;

		global $WC_PROD_SELECTIVE_SYNC_PROPERTIES;

		$expected = array();
		foreach ( $WC_PROD_SELECTIVE_SYNC_PROPERTIES as $k => $v ) {
			$expected[$k] = 'true';
		}

		$this->assertEquals( $expected, $settings[ WCLSI_WC_SELECTIVE_SYNC ] );
		$this->assertEquals( 'true', $settings[ WCLSI_LS_TO_WC_AUTOLOAD ] );

		$ls_expected = array( 'stock_quantity' => 'true' );
		$this->assertEquals( $ls_expected, $settings[ WCLSI_LS_SELECTIVE_SYNC ] );
	}
}
