<?php
class WCLSI_Unit_Tests_Bootstrap {

	/** @var \WCLSI_Unit_Tests_Bootstrap instance */
	protected static $instance = null;

	/** @var string directory where wordpress-tests-lib is installed */
	public $wp_tests_dir;

	// directory storing dependency plugins
	public $modules_dir;

	/** @var string plugin directory */
	public $plugin_dir;

	/**
	 * PHPUnit bootstrap file
	 *
	 * @package Woocommerce_Lightspeed_Integration
	 */
	function __construct() {
		$this->tests_dir    = dirname( __FILE__ );
		$this->plugin_dir   = dirname( $this->tests_dir );
		$this->modules_dir  = dirname( dirname( $this->tests_dir ) );
		$this->wp_tests_dir = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : '/tmp/wordpress-tests-lib';

		// load test function so tests_add_filter() is available
		require_once( $this->wp_tests_dir  . '/includes/functions.php' );

		// load WC
		tests_add_filter( 'muplugins_loaded', array( $this, 'load_wc' ) );

		// load Woo-Lightspeed
		tests_add_filter( 'woocommerce_init', array( $this, 'load_wclsi' ) );;

		// install WC
		tests_add_filter( 'setup_theme', array( $this, 'install_wc' ) );

		// install Woo-Lighspeed
		tests_add_filter( 'setup_theme', array( $this, 'install_wclsi' ) );

		// load the WP testing environment
		require_once( $this->wp_tests_dir . '/includes/bootstrap.php' );

		// load test setup
		require_once( $this->plugin_dir . '/tests/wclsi-test-setup.php' );
	}

	/**
	 * Load WooCommerce
	 */
	public function load_wc() {
		require_once( $this->modules_dir . '/woocommerce/woocommerce.php' );
	}

	/**
	 * Load Woo-Lightspeed
	 */
	public function load_wclsi() {
		require_once( $this->plugin_dir . '/woocommerce-lightspeed-integration.php' );
	}

	/**
	 * Load WooCommerce for testing
	 *
	 * @since 2.0
	 */
	public function install_wc() {
		echo "Installing WooCommerce..." . PHP_EOL;

		define( 'WP_UNINSTALL_PLUGIN', true );

		update_option( 'woocommerce_status_options', array('uninstall_data' => true) );

		include( $this->modules_dir . '/woocommerce/uninstall.php' );

		WC_Install::install();

		WC()->init();

		echo "WooCommerce Finished Installing..." . PHP_EOL;
	}

	public function install_wclsi() {
		echo "Installing WooCommerce Lightspeed Integration..." . PHP_EOL;

		define( 'WCLSI_TEST', true );

		$WC_LS_Integration = new WC_LS_Integration( __FILE__ );
		$WC_LS_Integration->init();

		echo "WooCommerce Lightspeed Integration Finished Installing..." . PHP_EOL;
	}

	/**
	 * Get the single class instance
	 * @return WCLSI_Unit_Tests_Bootstrap
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

}

WCLSI_Unit_Tests_Bootstrap::instance();

/**
 * Override woothemes_queue_update() and is_active_woocommerce()
 *
 * @since 2.0
 */
function is_woocommerce_active() {
	return true;
}
function woothemes_queue_update($file, $file_id, $product_id) {
	return true;
}
