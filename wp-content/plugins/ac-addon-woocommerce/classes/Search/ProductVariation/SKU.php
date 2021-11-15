<?php

namespace ACA\WC\Search\ProductVariation;

use ACP\Search\Comparison;
use ACP\Search\Helper\Sql\ComparisonFactory;
use ACP\Search\Operators;
use ACP\Search\Query\Bindings;
use ACP\Search\Value;

class SKU extends Comparison {

	public function __construct() {
		$operators = new Operators( [
			Operators::EQ,
			Operators::CONTAINS,
			Operators::BEGINS_WITH,
			Operators::ENDS_WITH,
		] );

		parent::__construct( $operators );
	}

	public function set_temporary_group_by( $clauses ) {
		global $wpdb;

		if ( empty( $clauses['groupby'] ) ) {
			$clauses['groupby'] = "{$wpdb->posts}.ID";
		}

		return $clauses;
	}

	protected function create_query_bindings( $operator, Value $value ) {
		global $wpdb;

		$bindings = new Bindings();

		$alias_products = $bindings->get_unique_alias( 'sku' );
		$alias_product_meta = $bindings->get_unique_alias( 'sku' );
		$alias_variation_meta = $bindings->get_unique_alias( 'sku' );

		$join = "
			INNER JOIN {$wpdb->postmeta} AS {$alias_variation_meta} ON {$alias_variation_meta}.post_id = {$wpdb->posts}.ID
			INNER JOIN {$wpdb->posts} AS {$alias_products} ON {$alias_products}.ID = {$wpdb->posts}.post_parent
			INNER JOIN {$wpdb->postmeta} AS {$alias_product_meta} ON {$alias_product_meta}.post_id = {$alias_products}.ID
		";

		$variation_meta_value = $this->get_comparison_meta_value( $alias_variation_meta, $operator, $value );
		$product_meta_value = $this->get_comparison_meta_value( $alias_product_meta, $operator, $value );

		$where = "
			{$alias_products}.post_type = 'product'
			AND (
		        (
		            {$alias_variation_meta}.meta_key = '_sku' AND
		            {$variation_meta_value}
		        )
		      OR
		        (
		            {$alias_variation_meta}.meta_key = '_sku' AND
		            {$alias_variation_meta}.meta_value = '' AND
		            {$alias_product_meta}.meta_key = '_sku' AND
		            {$product_meta_value}
		        )
			)
		";

		// TODO: Remove and make add-on dependent on ACP 5.5 once released
		add_filter( 'posts_clauses', [ $this, 'set_temporary_group_by' ] );

		$bindings->join( $join )
		         ->where( $where )
		         ->group_by( "{$wpdb->posts}.ID" );

		return $bindings;
	}

	/**
	 * @param string $alias
	 * @param string $operator
	 * @param Value  $value
	 *
	 * @return string
	 */
	private function get_comparison_meta_value( $alias, $operator, $value ) {
		$comparison = ComparisonFactory::create( $alias . '.meta_value', $operator, $value );

		return $comparison->prepare();
	}

}