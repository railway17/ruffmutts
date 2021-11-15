<?php
/**
 * Used to make API calls to LightSpeed.
 * @uses WP_MOScURL class
 *
 * This class is based off of LightSpeed's Merchant OS github repo:
 * https://github.com/merchantos/api_samples/tree/master/php/MOSAPI
 *
 * As well as solepixel's implementation:
 * https://github.com/solepixel/woocommerce-lightspeed-cloud/tree/master/woocommerce-lightspeed-cloud/lib/MOSAPI
 *
 */
if ( class_exists( 'WP_MOSAPICall' ) ) return;

if ( ! class_exists( 'WP_MOScURL' ) ) return;

class WP_MOSAPICall {

	protected $_mos_api_url_https = 'https://api.merchantos.com/API/';
	protected $_mos_api_url = 'http://api.merchantos.com/API/';

	/**
	 * @var
	 * @deprecated
	 */
	protected $_api_key;

	/**
	 * Lightspeed account id
	 * @var
	 */
	protected $_account_num;

	/**
	 * oAuth token
	 * @var null
	 */
	protected $_token;

	/**
	 * MerchantOS API Call
	 * @var string
	 */
	var $api_call;

	/**
	 * MerchantOS API Action
	 * @var string
	 */
	var $api_action;

	public function __construct( $api_key, $account_num, $token = null )
	{
		$this->_api_key = $api_key;
		$this->_account_num = $account_num;
		if( isset( $token ) ) {
			$this->_token = $token;
		}
	}

	public function makeAPICall( $controlname, $action = 'Read', $unique_id = null, $data = array(), $query_str = '', Closure $callback = null, $emitter = 'json' )
	{

		$this->api_call = $controlname;
		$this->api_action = $action;

		$custom_request = 'GET';

		switch ( $action )
		{
			case 'Create':
				$custom_request = 'POST';
				break;
			case 'Read':
				$custom_request = 'GET';
				break;
			case 'Update':
				$custom_request = 'PUT';
				break;
			case 'Delete':
				$custom_request = 'DELETE';
				break;
		}

		$curl = new WP_MOScURL();

		if ( isset( $this->_token ) ) {
			$curl->setOAuth( $this->_token );
		} else {
			$curl->setBasicAuth( $this->_api_key, 'apikey' );
		}

		$curl->setCustomRequest( $custom_request );

		$control_url = $this->_mos_api_url_https . str_replace( '.', '/', str_replace('Account.', 'Account.' . $this->_account_num . '.', $controlname ) );

		if ( $unique_id ) {
			$control_url .= '/' . $unique_id;
		}

		if ( $query_str ) {
			if ( is_array( $query_str ) )
				$query_str = $this->build_query_string( $query_str );

			$control_url .= '.' . $emitter . '?' . $query_str;
		} else {
			$control_url .= '.' . $emitter;
		}

		if ( is_array( $data ) && count( $data ) > 0 ) {
			$body = json_encode( $data );
		} elseif ( is_string( $data ) ) {
			$body = $data;
		} else {
			$body = '';
		}

		if( !is_null( $callback ) ) {
			$callback( $curl, $body );
		}

		return self::_makeCall( $curl, $control_url, $body );
	}

	protected static function _makeCall( $curl, $url, $body )
	{
		$result = $curl->call( $url, $body );

		try {
			$return = json_decode( $result );
		} catch( Exception $e ) {
			$message = $e->getMessage;
			$result = htmlspecialchars( $result );
			throw new Exception( "MerchantOS API Call Error: $message, URL: $url Response: $result" );
		}

		if ( !is_object( $return ) ) {
			try {
				$xml_obj = new SimpleXMLElement( $result );
				$return = json_decode( json_encode( $xml_obj ) );
			} catch ( Exception $e ) {
				$message = $e->getMessage;
				$result = htmlspecialchars( $result );
				throw new Exception( "MerchantOS API Call Error: $message, URL: $url Response: $result" );
			}

			if ( ! is_object( $return ) ) {
				$result = htmlspecialchars( $result );
				throw new Exception( "MerchantOS API Call Error: Could not parse XML, URL: $url Response: $result" );
			}
		}

		return $return;
	}

	private function build_query_string( $data ) {
		if ( function_exists( 'http_build_query' ) ) {
			return http_build_query( $data );
		} else {
			$qs = '';
			foreach( $data as $key => $value ) {
				$append = urlencode( $key ) . '=' . urlencode( $value );
				$qs .= $qs ? '&' . $append : $append;
			}
			return $qs;
		}
	}
}
