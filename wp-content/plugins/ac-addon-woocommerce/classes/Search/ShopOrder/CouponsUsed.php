<?php

namespace ACA\WC\Search\ShopOrder;

use ACP\Search\Comparison;
use ACP\Search\Operators;
use ACP\Search\Query\Bindings;
use ACP\Search\Value;

class CouponsUsed extends Comparison {

	public function __construct() {
		$operators = new Operators(
			[
				Operators::IS_EMPTY,
				Operators::NOT_IS_EMPTY,
			]
		);

		parent::__construct( $operators, Value::STRING );
	}

	protected function create_query_bindings( $operator, Value $value ) {
		global $wpdb;

		$order_ids = $this->get_orders_with_coupon_used();
		if ( empty( $order_ids ) ) {
			$order_ids = [ 0 ];
		}

		$operator = $operator === Operators::IS_EMPTY ? 'NOT IN' : 'IN';

		$bindings = new Bindings();
		$bindings->where( sprintf( "{$wpdb->posts}.ID {$operator} ( %s )", implode( ',', $order_ids ) ) );

		return $bindings;
	}

	protected function get_orders_with_coupon_used() {
		global $wpdb;

		$sql = "SELECT distinct(P.ID) as ID
					FROM {$wpdb->prefix}woocommerce_order_items as oi
					JOIN {$wpdb->posts} as P on p.ID = oi.order_id
					WHERE oi.order_item_type = 'coupon'
			";

		return $wpdb->get_col( $sql );
	}

}