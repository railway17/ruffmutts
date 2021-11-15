<?php

namespace ACA\WC\Editing\ProductVariation;

use ACA\WC\Column;
use ACA\WC\Editing;

/**
 * @property Column\ProductVariation\ShippingClass $column
 */
class ShippingClass extends Editing\Product\ShippingClass {

	public function get_view_settings() {
		$settings = parent::get_view_settings();
		$settings['options'][''] = __( 'Use Product Shipping Class', 'codepress-admin-columns' );

		return $settings;
	}

}