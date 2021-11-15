<?php

namespace ACA\WC\Settings\User;

use AC;
use AC\View;

class OrderStatus extends AC\Settings\Column {

	const NAME = 'order_status';

	/**
	 * @var string
	 */
	private $order_status;

	protected function define_options() {
		return [ self::NAME => 'wc-completed' ];
	}

	public function create_view() {
		$select = $this->create_element( 'select' )
		               ->set_attribute( 'data-label', 'update' )
		               ->set_attribute( 'data-refresh', 'column' )
		               ->set_options( $this->get_display_options() );

		return new View( [
			'label'   => __( 'Status', 'codepress-admin-columns' ),
			'setting' => $select,
		] );
	}

	protected function get_display_options() {
		return [ '' => __( 'Any', 'codepress-admin-columns' ) ] + wc_get_order_statuses();
	}

	/**
	 * @return string
	 */
	public function get_order_status() {
		return $this->order_status;
	}

	/**
	 * @param string $order_status
	 *
	 * @return bool
	 */
	public function set_order_status( $order_status ) {
		$this->order_status = $order_status;

		return true;
	}

}