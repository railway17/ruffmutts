<?php

namespace ACA\WC\Editing\ShopCoupon;

use ACP;
use WC_Coupon;

class ExpiryDate extends ACP\Editing\Model {

	public function get_view_settings() {
		return [
			'type' => 'date',
		];
	}

	public function get_edit_value( $id ) {
		$coupon = new WC_Coupon( $id );
		$date = $coupon->get_date_expires();

		if ( ! $date ) {
			return false;
		}

		// Uses GMT offset
		return $date->date( 'Ymd' );
	}

	public function save( $id, $value ) {
		$coupon = new WC_Coupon( $id );
		$coupon->set_date_expires( strtotime( $value ) );

		return $coupon->save() > 0;
	}

}