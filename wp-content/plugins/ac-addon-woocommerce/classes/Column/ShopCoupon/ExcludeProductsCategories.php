<?php

namespace ACA\WC\Column\ShopCoupon;

use ACA\WC\Column;
use ACA\WC\Editing;
use ACA\WC\Filtering;
use ACA\WC\Search;
use ACP;

/**
 * @since 3.0
 */
class ExcludeProductsCategories extends Column\CouponProductCategories
	implements ACP\Filtering\Filterable, ACP\Editing\Editable, ACP\Search\Searchable {

	public function __construct() {
		parent::__construct();

		$this->set_type( 'column-wc-coupon_exclude_product_categories' );
		$this->set_label( __( 'Exclude Product Categories', 'codepress-admin-columns' ) );
	}

	public function get_meta_key() {
		return 'exclude_product_categories';
	}

	public function filtering() {
		return new Filtering\ShopCoupon\ProductCategories( $this );
	}

	public function editing() {
		return new Editing\ShopCoupon\ExcludeProductCategories( $this );
	}

	public function search() {
		return new Search\ShopCoupon\Categories( $this->get_meta_key() );
	}

}