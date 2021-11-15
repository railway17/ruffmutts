<?php

namespace ACA\WC\Editing\ShopOrder;

use AC\Column;
use ACA\WC\Editing;
use ACP;

class AddressFactory {

	/**
	 * @param string      $address_property
	 * @param Column\Meta $column
	 *
	 * @return ACP\Editing\Model
	 */
	public function create( $address_property, Column\Meta $column ) {
		switch ( $address_property ) {
			case '' :
				return new ACP\Editing\Model\Disabled( $column );
			case 'country' :
				return new Editing\MetaCountry( $column );
			case 'full_name' :
				return new ACP\Editing\Model\Disabled( $column );
			default :
				return new ACP\Editing\Model\Meta( $column );
		}
	}

}