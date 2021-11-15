<?php
/**
 * @uses WP_HTTP to make requests to MerchantOS API.
 *
 * Despite the use of 'cURL' in the name of the class, it does not necessarily rely on cURL as
 * the WP HTTP API has fallbacks if cURL is not enabled on the local server.
 *
 * This class is based off of LightSpeed's Merchant OS github repo:
 * https://github.com/merchantos/api_samples/tree/master/php/MOSAPI
 *
 * As well as solepixel's implementation:
 * https://github.com/solepixel/woocommerce-lightspeed-cloud/tree/master/woocommerce-lightspeed-cloud/lib/MOSAPI
 *
 * Notes:
 * WordPress will set CURLOPT_RETURNTRANSFER to true.
 * WordPress will set CURLOPT_CONNECTTIMEOUT and CURLOPT_TIMEOUT to the same value.
 * WordPress will not return headers by default. setDebug() and setReturnHeaders() will do nothing!
 * WordPress does not look at CURLOPT_SSL_VERIFYPEER and CURLOPT_SSL_VERIFYHOST separately. It uses an 'sslverify' arg to
 * check for both or not. Use sslVerifyHost() to set this on or off. sslVerifyPeer() will do nothing!
 */
if ( class_exists( 'WP_MOScURL' ) ) return;

class WP_MOScURL
{
	/**
	 * @var
	 */
	protected $user_agent;

	/**
	 * @deprecated
	 */
	protected $returntransfer;

	/**
	 * @var
	 */
	protected $timeout;

	/**
	 * @deprecated
	 */
	protected $total_timeout;

	/**
	 * @deprecated
	 */
	protected $verifypeer;

	/**
	 * Use this to check for both verifyhost & verifypeer
	 * @var
	 */
	protected $verifyhost;

	/**
	 * @var
	 */
	protected $cainfo;

	/**
	 * @var
	 */
	protected $httpheader;

	/**
	 * A custom request method to use in making the request.
	 * @var string
	 */
	protected $customrequest;

	/**
	 * The authentication type to use.
	 * @var string
	 */
	protected $authtype;

	/**
	 * The oauth token
	 * @var string
	 */
	protected $oauth_token;

	/**
	 * The username to use with authentication
	 * @var string
	 */
	protected $username;

	/**
	 * The password to use with authentication
	 * @var string
	 */
	protected $password;

	/**
	 * @deprecated
	 */
	protected $return_headers;

	/**
	 * Set a cookie(s) to send with the request
	 * @var string
	 */
	protected $cookie;

	/**
	 * @deprecated
	 */
	protected $debug = false;

	/**
	 * @deprecated
	 */
	protected $connection;


	/**
	 * Sets the defaults
	 * user agent = "MerchantOS"
	 * return transfer = true
	 * timeout = 60
	 * verify peer = true
	 * verify host = 2 (yes)
	 * http header = nothing
	 *
	 */
	public function __construct()
	{
		$this->setUserAgent( 'WooCommerce/Lightspeed' );
		$this->setTimeout( 60 );
		$this->setVerifyPeer( true );
		$this->setVerifyHost( 2 );
		$this->setHTTPHeader( false );
		$this->connection = false;
	}

	/**
	 * Set the user agent used
	 * @param string $agent
	 */
	public function setUserAgent( $agent ) {
		$this->user_agent = $agent;
	}

	/**
	 * @deprecated
	 */
	public function setReturnTransfer( $returntransfer ) {}

	/**
	 * Sets the connection timeout
	 * @param integer $timeout
	 */
	public function setTimeout( $timeout ) {
		$this->timeout = $timeout;
	}

	/**
	 * @deprecated
	 */
	public function setTotalTimeout( $timeout ) {}

	/**
	 * @param boolean $verifypeer
	 */
	public function setVerifyPeer( $verifypeer ) {}

	/**
	 * @param integer $verifyhost 0,1,2
	 */
	public function setVerifyHost( $verifyhost ) {
		$this->verifyhost = $verifyhost;
	}

	/**
	 * @param string $cainfo False or path to cacert.pem type file
	 */
	public function setCaInfo( $cainfo ) {
		$this->cainfo = $cainfo;
	}

	/**
	 * @param array|bool $httpheader
	 */
	public function setHTTPHeader( $httpheader ) {
		$this->httpheader = $httpheader;
	}

	/**
	 * @param string $customrequest The custom request method to use.
	 */
	public function setCustomRequest( $customrequest ) {
		$this->customrequest = $customrequest;
	}

	/**
	 * Sets CURLOPT_HTTPAUTH and CURLOPT_USERPWD
	 *
	 * @param string $username The username to send for authentication.
	 * @param string $password The password to send for authentication.
	 */
	public function setBasicAuth( $username, $password ) {
		$this->authtype = 'basic';
		$this->username = $username;
		$this->password = $password;
	}

	public function setOAuth( $token ) {
		$this->authtype = 'oauth';
		$this->oauth_token = $token;
	}

	/**
	 * @deprecated
	 */
	public function setReturnHeaders( $return_headers ) {}

	/**
	 * @deprecated
	 */
	public function setCookie( $cookie ) {}

	/**
	 * @deprecated
	 */
	public function setDebug( $debug ) {}

	/**
	 * @deprecated
	 */
	public function getInfo( $option = null ) {}

	/**
	 * @deprecated
	 */
	public function init() {}

	/**
	 * Make a cURL call, sets the options and then makes the call
	 *
	 * @param $url The URL you want to call with cURL
	 * @param $postfields
	 * @return string The response returned from the URL called
	 * @throws Exception If cURL hits an error it will be thrown as an Exception the exception message will be curl error message, exception code will be curl error number.
	 */
	public function call( $url, $postfields = false ) {

		$args = array(
			'timeout'     => $this->timeout,
			'redirection' => 5,
			'httpversion' => '1.0',
			'user-agent'  => $this->user_agent
		);

		// If a custom request method is set use it
		if ( !is_null( $this->customrequest ) ) {
			$args['method'] = $this->customrequest;
		}

		if ( $this->httpheader ) {
			$args['headers'] = $this->httpheader;
		}

		// If we have an authtype set use it
		if ( isset( $this->authtype ) ) {
			switch ( $this->authtype )
			{
				case 'basic':
					$args['headers']['Authorization'] = 'Basic ' . base64_encode( $this->username . ":" . $this->password );
					break;
				case 'oauth':
					$args['headers']['Authorization'] = 'OAuth ' . $this->oauth_token;
					break;

			}
		}

		// Try and set sslverify to true, see:
		// https://wordpress.stackexchange.com/questions/167898/is-it-safe-to-use-sslverify-true-for-with-wp-remote-get-wp-remote-post
		if ( is_null( $this->verifyhost ) ) {
			$args['sslverify'] = true;
		} else {
			$args['sslverify'] = $this->verifyhost;
		}


		if ( $this->cainfo && file_exists( $this->cainfo ) ) {
			$args['sslcertificates'] = $this->cainfo;
		}

		if ( $this->cookie ) {
			$args['cookies'] = $this->cookie;
		}

		if ( $postfields && ( $this->customrequest == 'POST' || $this->customrequest == 'PUT' ) ) {
			$args['body'] = $postfields;
		}

		$raw_response = wp_remote_request( $url, $args );

		if ( is_wp_error( $raw_response ) ) {
			$code = is_int( $raw_response->get_error_code() ) ? $raw_response->get_error_code() : 0;
			throw new Exception( $raw_response->get_error_message(), $code );
		}
		
		if ( isset( $raw_response['headers'] ) ) {
			$bucket = $raw_response['headers']['x-ls-api-bucket-level'];
			$drip_rate = $raw_response['headers']['x-ls-api-drip-rate'];
			
			$bucket_info = explode('/', $bucket);
			$current_level = $bucket_info[0];
			$max_level = (int) $bucket_info[1];
			$rounded_bucket_level = ceil((float) $current_level);

			// Expires after 30 seconds			
			set_transient(WCLSI_API_BUCKET_LEVEL, $rounded_bucket_level, 30);
            set_transient(WCLSI_API_BUCKET_LEVEL_MAX, $max_level, 30);
			set_transient(WCLSI_API_DRIP_RATE, $drip_rate, 30);

			if( WP_DEBUG ) {
				global $WCLSI_WC_Logger;
				
				// @todo: For whatever reason the logger does not initialize correctly on a fresh install
				if ( empty( $WCLSI_WC_Logger ) ) {
					$WCLSI_WC_Logger = new WC_Logger();
				}

				$WCLSI_WC_Logger->add(
					WCLSI_DEBUGGER_LOG,
					PHP_EOL .
					"LS Bucket Level: {$current_level}/{$max_level}" . PHP_EOL .
					"LS Rounded Bucket Level: {$rounded_bucket_level}" . PHP_EOL .
					"LS Drip Rate: {$drip_rate}" . PHP_EOL
				);
			}
		}

		return wp_remote_retrieve_body( $raw_response );
	}

	/**
	 * @deprecated
	 */
	public function close() {}

	/**
	 * @deprecated
	 */
	protected function wrapper_curl_setopt( $opt, $value ) {}

	/**
	 * @deprecated
	 */
	protected function wrapper_curl_exec() {}

	/**
	 * @deprecated
	 */
	protected function wrapper_curl_close() {}

	/**
	 * @deprecated
	 */
	protected function wrapper_curl_init() {}

	/**
	 * @deprecated
	 */
	protected function wrapper_curl_error() {}

	/**
	 * @deprecated
	 */
	protected function wrapper_curl_errno() {}

	/**
	 * @deprecated
	 */
	protected function wrapper_curl_getinfo($opt) {}
}
