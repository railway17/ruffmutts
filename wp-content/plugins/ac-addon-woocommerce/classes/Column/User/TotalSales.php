<?php

namespace ACA\WC\Column\User;

use AC;
use ACA\WC\Export;
use ACA\WC\Sorting;
use ACA\WC\Search;
use ACP;

/**
 * @since 1.3
 */
class TotalSales extends AC\Column
	implements ACP\Sorting\Sortable, ACP\Export\Exportable, ACP\Search\Searchable {

	public function __construct() {
		$this->set_type( 'column-wc-user-total-sales' );
		$this->set_label( __( 'Total Sales', 'codepress-admin-columns' ) );
		$this->set_group( 'woocommerce' );
	}

	/**
	 * @return string[]
	 */
	private function get_order_statuses() {
		return [ 'wc-completed', 'wc-processing' ];
	}

	public function get_value( $user_id ) {
		$values = [];

		foreach ( ac_addon_wc_helper()->get_totals_for_user( $user_id, $this->get_order_statuses() ) as $currency => $total ) {
			if ( $total ) {
				$values[] = wc_price( $total );
			}
		}

		if ( ! $values ) {
			return $this->get_empty_char();
		}

		return implode( ' | ', $values );
	}

	public function get_raw_value( $user_id ) {
		return ac_addon_wc_helper()->get_totals_for_user( $user_id, $this->get_order_statuses() );
	}

	public function sorting() {
		return new Sorting\User\TotalSales( $this->get_order_statuses() );
	}

	public function search() {
		return new Search\User\TotalSales( $this->get_order_statuses() );
	}

	public function export() {
		return new Export\User\TotalSales( $this );
	}

}