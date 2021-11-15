<?php

namespace ACA\WC\Column\ShopCoupon;

use AC;
use ACA\WC\Editing;
use ACA\WC\Export;
use ACA\WC\Search\ShopCoupon\EmailRestriction;
use ACP;
use WC_Coupon;

/**
 * @since 2.2
 */
class EmailRestrictions extends AC\Column
	implements ACP\Editing\Editable, ACP\Filtering\Filterable, ACP\Export\Exportable, ACP\Search\Searchable {

	public function __construct() {
		$this->set_type( 'column-wc-email-restrictions' )
		     ->set_label( __( 'Email Restrictions', 'codepress-admin-columns' ) )
		     ->set_group( 'woocommerce' );
	}

	public function get_value( $id ) {
		$emails = $this->get_raw_value( $id );

		if ( empty( $emails ) ) {
			return $this->get_empty_char();
		}

		return implode( ', ', $emails );
	}

	public function filtering() {
		return new ACP\Filtering\Model\Disabled( $this );
	}

	public function editing() {
		return new Editing\ShopCoupon\EmailRestrictions( $this );
	}

	public function export() {
		return new Export\ShopCoupon\EmailRestrictions( $this );
	}

	public function search() {
		return new EmailRestriction();
	}

	public function get_raw_value( $id ) {
		$coupon = new WC_Coupon( $id );

		return $coupon->get_email_restrictions();
	}

}