<?php

namespace ACA\WC\Sorting\ShopOrder;

use ACP\Sorting\AbstractModel;

class OrderWeight extends AbstractModel {

	public function get_sorting_vars() {
		return [
			'ids' => $this->get_sorted_order_ids(),
		];
	}

	public function get_sorted_order_ids() {
		global $wpdb;

		$join_type = $this->show_empty
			? 'LEFT'
			: 'INNER';

		$sql = "
		SELECT ID, SUM(total) AS total
		FROM (
			SELECT woi.order_id AS ID, woim2.meta_value*pm.meta_value AS total
			FROM {$wpdb->prefix}woocommerce_order_items AS woi
			{$join_type} JOIN ( 
				SELECT order_item_id, meta_value AS product_id FROM (
					SELECT * FROM {$wpdb->prefix}woocommerce_order_itemmeta
					WHERE meta_key = '_product_id' OR meta_key = '_variation_id'
					ORDER BY meta_value DESC
					LIMIT 1000000000
				) AS sq
				GROUP BY order_item_id
			) AS woim ON woi.order_item_id = woim.order_item_id
			{$join_type} JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS woim2 ON woi.order_item_id = woim2.order_item_id AND woim2.meta_key = '_qty'
			{$join_type} JOIN {$wpdb->postmeta} as pm ON woim.product_id = pm.post_id AND pm.meta_key = '_weight'
			WHERE woi.order_item_type = 'line_item'
		) AS total_order_weight
		GROUP BY total_order_weight.ID
		ORDER BY total
		";

		return $wpdb->get_col( $sql );
	}

}