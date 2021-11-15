<?php

namespace ACA\WC\Column\ProductVariation;

use AC;
use ACA\WC\Editing;
use ACA\WC\Filtering;
use ACP;
use WC_Product_Variation;

/**
 * @since 3.0
 */
class Price extends AC\Column\Meta
	implements ACP\Editing\Editable, ACP\Sorting\Sortable, ACP\Filtering\Filterable, ACP\Export\Exportable, ACP\Search\Searchable {

	public function __construct() {
		$this->set_type( 'variation_price' );
		$this->set_label( __( 'Price', 'woocommerce' ) );
		$this->set_original( true );
	}

	public function get_value( $id ) {
		$variation = new WC_Product_Variation( $id );

		$regular = $variation->get_regular_price();
		$price = $variation->get_price();

		if ( ! $price ) {
			return $this->get_empty_char();
		}

		$value = wc_price( $price );
		if ( $price < $regular ) {
			$value = sprintf( '<del>%s</del> <ins>%s</ins>', wc_price( $regular ), $value );
		}

		return $value;
	}

	public function get_raw_value( $id ) {
		$variation = new WC_Product_Variation( $id );

		return wc_price( $variation->get_price() );
	}

	public function get_meta_key() {
		return '_regular_price';
	}

	public function editing() {
		return new Editing\ProductVariation\Price( $this );
	}

	public function sorting() {
		return new ACP\Sorting\Model\Post\Meta( $this->get_meta_key() );
	}

	public function filtering() {
		return new Filtering\Number( $this );
	}

	public function export() {
		return new ACP\Export\Model\StrippedRawValue( $this );
	}

	public function search() {
		return new ACP\Search\Comparison\Meta\Decimal( $this->get_meta_key(), AC\MetaType::POST );
	}

}