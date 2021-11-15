<?php

namespace ACA\WC\Column\ShopOrder;

use AC;
use ACA\WC\Editing;
use ACA\WC\Settings;
use ACA\WC\Settings\ShopOrder\NoteType;
use ACP;
use DateTime;

/**
 * @since 3.3
 */
class Notes extends AC\Column implements ACP\Editing\Editable, ACP\Export\Exportable {

	public function __construct() {
		$this->set_type( 'column-wc_order_notes' )
		     ->set_group( 'woocommerce' )
		     ->set_label( __( 'Order Notes', 'woocommerce' ) );
	}

	public function get_value( $id ) {
		$value = null;

		switch ( $this->get_display_property() ) {
			case Settings\ShopOrder\Notes::LATEST_VALUE:
				$value = $this->get_latest_value( $id );
				break;
			case Settings\ShopOrder\Notes::COUNT_VALUE :
			default:
				$value = $this->get_count_value( $id );
		}

		return $value ? $value : $this->get_empty_char();
	}

	/**
	 * @param int $id
	 *
	 * @return object|null
	 */
	public function get_last_order_note( $id ) {
		$notes = $this->get_order_notes( $id );

		return count( $notes ) > 0 ? reset( $notes ) : null;
	}

	/**
	 * @param int $order_id
	 *
	 * @return object[]
	 */
	private function get_order_notes( $order_id ) {
		global $wpdb;

		$sql = $wpdb->prepare( "
			SELECT cc.comment_content AS content, cc.comment_ID AS id, cc.comment_date AS date, cc.comment_author AS author, cm.meta_value AS is_customer_note 
			FROM {$wpdb->comments} AS cc
			LEFT JOIN {$wpdb->commentmeta} AS cm ON cc.comment_ID = cm.comment_id AND cm.meta_key = 'is_customer_note'
			WHERE cc.comment_post_ID = %d
			ORDER BY cc.comment_date DESC
		", $order_id );

		$notes = $wpdb->get_results( $sql );

		switch ( $this->get_note_type() ) {
			case NoteType::CUSTOMER_NOTE :
				return array_filter( $notes, [ $this, 'is_customer_note' ] );
			case NoteType::PRIVATE_NOTE :
				return array_filter( $notes, [ $this, 'is_private_note' ] );
			case NoteType::SYSTEM_NOTE :
				return array_filter( $notes, [ $this, 'is_system_note' ] );
			default :
				return $notes;
		}
	}

	/**
	 * @param object $note
	 *
	 * @return bool
	 */
	private function is_private_note( $note ) {
		return ! $this->is_customer_note( $note ) && ! $this->is_system_note( $note );
	}

	/**
	 * @param object $note
	 *
	 * @return bool
	 */
	private function is_system_note( $note ) {
		return __( 'WooCommerce', 'woocommerce' ) === $note->author;
	}

	/**
	 * @param object $note
	 *
	 * @return bool
	 */
	private function is_customer_note( $note ) {
		return '1' === $note->is_customer_note;
	}

	/**
	 * @return string
	 */
	private function get_note_type() {
		return $this->get_setting( NoteType::NAME )->get_value();
	}

	/**
	 * @return string
	 */
	private function get_display_property() {
		return $this->get_setting( Settings\ShopOrder\Notes::NAME )->get_value();
	}

	/**
	 * @param $id
	 *
	 * @return string
	 */
	private function get_count_value( $id ) {
		$notes = $this->get_order_notes( $id );

		if ( ! $notes ) {
			return '';
		}

		$content = [];

		foreach ( $notes as $note ) {
			$content[] = sprintf( '<small>%s</small><br>%s', DateTime::createFromFormat( 'Y-m-d H:i:s', $note->date )->format( 'F j, Y - H:i' ), $note->content );
		}

		return ac_helper()->html->tooltip( ac_helper()->html->rounded( count( $content ) ), implode( '<br><br>', $content ) );
	}

	/**
	 * @param $id
	 *
	 * @return string
	 */
	private function get_latest_value( $id ) {
		$note = $this->get_last_order_note( $id );

		return $note
			? sprintf( '<small>%s</small><br>%s', DateTime::createFromFormat( 'Y-m-d H:i:s', $note->date )->format( 'F j, Y - H:i' ), $note->content )
			: $this->get_empty_char();
	}

	public function register_settings() {
		$this->add_setting( new NoteType( $this ) );
		$this->add_setting( new Settings\ShopOrder\Notes( $this ) );
	}

	public function editing() {
		switch ( $this->get_display_property() ) {
			case Settings\ShopOrder\Notes::LATEST_VALUE:
				return new Editing\ShopOrder\LastNote( $this );
			default:
				return new ACP\Editing\Model\Disabled( $this );
		}
	}

	public function export() {
		return new ACP\Export\Model\StrippedValue( $this );
	}

}