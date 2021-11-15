<?php

namespace ACA\WC\Column\ProductVariation;

use AC;
use ACA\WC\Editing;
use ACA\WC\Export;
use ACA\WC\Sorting;
use ACP;
use WC_Product_Variation;

/**
 * @since 1.1
 */
class Stock extends AC\Column
	implements ACP\Editing\Editable, ACP\Search\Searchable, ACP\Export\Exportable, ACP\Sorting\Sortable {

	public function __construct() {
		$this->set_type( 'variation_stock' );
		$this->set_label( __( 'Stock', 'woocommerce' ) );
		$this->set_original( true );
	}

	public function get_value( $id ) {
		$variation = new WC_Product_Variation( $id );

		$label = __( 'Out of stock', 'woocommerce' );
		if ( 'instock' === $variation->get_stock_status() ) {
			$label = __( 'In stock', 'woocommerce' );
		}

		$quantity = false;
		if ( $variation->get_stock_quantity() ) {
			$quantity = sprintf( '(%s)', $variation->get_stock_quantity() );
		}

		$icon = false;
		if ( 'parent' === $variation->get_manage_stock() ) {
			$icon = ac_helper()->html->tooltip( '<span class="woocommerce-help-tip"></span>', __( 'Stock managed by product', 'codepress-admin-columns' ) );
		}

		return sprintf( '<mark class="%s">%s</mark> %s %s', $variation->get_stock_status(), $label, $quantity, $icon );
	}

	public function editing() {
		return new Editing\ProductVariation\Stock( $this );
	}

	public function search() {
		return new ACP\Search\Comparison\Meta\Number( '_stock', AC\MetaType::POST );
	}

	public function export() {
		return new Export\Product\Stock( $this );
	}

	public function sorting() {
		return new Sorting\ProductVariation\Stock();
	}

}