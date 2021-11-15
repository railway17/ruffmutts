<?php

namespace ACA\WC\Editing\ShopOrder;

use ACA\WC;
use ACP;

class LastNote extends ACP\Editing\Model {

	public function __construct( WC\Column\ShopOrder\Notes $column ) {
		parent::__construct( $column );
	}

	public function get_view_settings() {
		return [
			'type'          => 'textarea',
			'bulk_editable' => false,
		];
	}

	public function get_edit_value( $id ) {
		$note = $this->get_last_note_for_order( $id );

		return $note->content;
	}

	/**
	 * @param $id
	 *
	 * @return \stdClass|null
	 */
	private function get_last_note_for_order( $id ) {
		if ( $this->column instanceof WC\Column\ShopOrder\Notes ) {
			return $this->column->get_last_order_note( $id );
		}

		return null;
	}

	public function save( $id, $value ) {
		$note = $this->get_last_note_for_order( $id );

		return wp_update_comment( [
			'comment_ID'      => $note->id,
			'comment_content' => $value,
		] );
	}

	public function register_settings() {
		parent::register_settings();

		$this->column->remove_setting( 'bulk_edit' );
		// TODO use code below once implemented and released in ACP Pro
		//$this->column->remove_setting( ACP\Editing\Settings\BulkEditing::NAME );
	}
}
