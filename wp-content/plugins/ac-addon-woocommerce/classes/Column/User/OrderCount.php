<?php

namespace ACA\WC\Column\User;

use AC;
use ACA\WC\Search;
use ACA\WC\Settings;
use ACA\WC\Sorting;
use ACP;

/**
 * @since 1.3
 */
class OrderCount extends AC\Column
	implements ACP\Sorting\Sortable, ACP\Search\Searchable {

	public function __construct() {
		$this->set_type( 'column-wc-user-order_count' )
		     ->set_label( __( 'Number of Orders', 'woocommerce' ) )
		     ->set_group( 'woocommerce' );
	}

	public function get_value( $user_id ) {
		$count = $this->get_raw_value( $user_id, $this->get_order_status() );

		$link = add_query_arg( [
			'post_type'      => 'shop_order',
			'_customer_user' => $user_id,
		], admin_url( 'edit.php' ) );

		return $count
			? sprintf( '<a href="%s">%s</a>', $link, $count )
			: $this->get_empty_char();
	}

	public function get_raw_value( $user_id, $status = 'any' ) {
		return count( ac_addon_wc_helper()->get_order_ids_by_user( $user_id, $status ) );
	}

	public function sorting() {
		return new Sorting\User\OrderCount( $this->get_order_status() ? [ $this->get_order_status() ] : [] );
	}

	public function search() {
		return new Search\User\OrderCount( $this->get_order_status() ? [ $this->get_order_status() ] : [] );
	}

	/**
	 * @return string
	 */
	private function get_order_status() {
		return $this->get_status_setting()->get_order_status();
	}

	/**
	 * @return Settings\User\OrderStatus
	 */
	private function get_status_setting() {
		$setting = $this->get_setting( Settings\User\OrderStatus::NAME );

		/** @var Settings\User\OrderStatus $setting */
		return $setting;
	}

	public function register_settings() {
		$this->add_setting( new Settings\User\OrderStatus( $this ) );
	}

}