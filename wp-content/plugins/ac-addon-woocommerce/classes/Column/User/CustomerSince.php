<?php

namespace ACA\WC\Column\User;

use AC;
use ACA\WC\Sorting;
use ACP\Sorting\Sortable;

/**
 * @since 3.0
 */
class CustomerSince extends AC\Column implements Sortable {

	public function __construct() {
		$this->set_type( 'column-wc-user-customer_since' )
		     ->set_label( __( 'Customer Since', 'codepress-admin-columns' ) )
		     ->set_group( 'woocommerce' );
	}

	public function get_raw_value( $customer_id ) {
		$orders = wc_get_orders( [
			'limit'       => 1,
			'status'      => 'wc-completed',
			'customer_id' => $customer_id,
			'orderby'     => 'date',
			'order'       => 'ASC',
		] );

		if ( ! $orders ) {
			return false;
		}

		$order = $orders[0];

		$date = $order->get_date_created();

		if ( ! $date ) {
			return false;
		}

		return $date->format( 'Y-m-d' );
	}

	public function register_settings() {
		$this->add_setting( new AC\Settings\Column\Date( $this ) );
	}

	public function sorting() {
		return new Sorting\User\FirstOrder();
	}

}