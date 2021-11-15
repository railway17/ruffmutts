<?php

namespace ACA\WC\Column\ProductVariation;

use AC;
use AC\MetaType;
use ACA\WC\Editing;
use ACP;
use ACP\Search\Comparison;

/**
 * @since 3.0
 */
class Description extends AC\Column\Meta
	implements ACP\Editing\Editable, ACP\Sorting\Sortable, ACP\Search\Searchable {

	public function __construct() {
		$this->set_type( 'column-wc-product_description' );
		$this->set_label( __( 'Description', 'woocommerce' ) );
		$this->set_group( 'woocommerce' );
	}

	public function get_meta_key() {
		return '_variation_description';
	}

	public function register_settings() {
		$this->add_setting( new AC\Settings\Column\StringLimit( $this ) );
	}

	public function editing() {
		return new Editing\ProductVariation\Description( $this );
	}

	public function sorting() {
		return new ACP\Sorting\Model\Post\Meta( $this->get_meta_key() );
	}

	public function search() {
		return new Comparison\Meta\Text( $this->get_meta_key(), MetaType::POST );
	}

}