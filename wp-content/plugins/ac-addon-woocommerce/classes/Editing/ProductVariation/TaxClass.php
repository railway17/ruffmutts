<?php

namespace ACA\WC\Editing\ProductVariation;

use ACA\WC\Column;
use ACP;

/**
 * @property Column\ProductVariation\TaxClass $column
 */
class TaxClass extends ACP\Editing\Model {

	public function __construct( Column\Product\TaxClass $column ) {
		parent::__construct( $column );
	}

	public function get_edit_value( $id ) {
		return get_post_meta( $id, $this->column->get_meta_key(), true );
	}

	public function get_view_settings() {
		$options = [ 'parent' => __( 'Use Product Tax Class', 'codepress-admin-columns' ) ];
		$options = array_merge( $options, $this->column->get_tax_classes() );

		return [
			'type'    => 'select',
			'options' => $options,
		];
	}

	public function save( $id, $value ) {
		$product = wc_get_product( $id );
		$product->set_tax_class( $value );

		return $product->save() > 0;
	}

}