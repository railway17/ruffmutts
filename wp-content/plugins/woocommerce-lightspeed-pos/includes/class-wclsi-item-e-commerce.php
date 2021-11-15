<?php
if ( !class_exists( 'WCLSI_Item_E_Commerce' ) ) :

	class WCLSI_Item_E_Commerce {

		function __construct(){}

		private static function get_item_e_commerce_mysql_args( $item_e_commerce, $wclsi_item_id ) {
			$args = array(
				'wclsi_item_id' => $wclsi_item_id,
				'item_e_commerce_id' => isset( $item_e_commerce->itemECommerceID ) ? $item_e_commerce->itemECommerceID : null,
				'long_description' => isset( $item_e_commerce->longDescription ) ? $item_e_commerce->longDescription : null,
				'short_description' => isset( $item_e_commerce->shortDescription ) ? $item_e_commerce->shortDescription : null,
				'weight' => isset( $item_e_commerce->weight ) ? $item_e_commerce->weight : null,
				'width' => isset( $item_e_commerce->width ) ? $item_e_commerce->width : null,
				'height' => isset( $item_e_commerce->height ) ? $item_e_commerce->height : null,
				'length' => isset( $item_e_commerce->length ) ? $item_e_commerce->length : null,
				'list_on_store' => isset( $item_e_commerce->listOnStore ) ? $item_e_commerce->listOnStore : null,
				'created_at' => current_time('mysql')
			);

			wclsi_format_empty_vals( $args );

			return $args;
		}

		private static function insert_args_valid( $args ) {
			if ( empty( $args['item_e_commerce_id'] ) ) {
				return new WP_Error('wclsi_empty_item_e_commerce_id', 'Could not insert item_e_commerce! item_e_commerce_id is null.');
			}

			return true;
		}

		public static function insert_item_e_commerce( $item_e_commerce, $wclsi_item_id ) {
			global $wpdb, $WCLSI_ITEM_E_COMMERCE_TABLE;

			$args = self::get_item_e_commerce_mysql_args( $item_e_commerce, $wclsi_item_id );
			
			$formatting = array( '%d', '%d', '%s', '%s', '%f', '%f', '%f', '%f', '%d', '%s' );

			$is_valid_insert_args = self::insert_args_valid( $args );
			if ( !is_wp_error( $is_valid_insert_args ) ) {
				$wpdb->insert( $WCLSI_ITEM_E_COMMERCE_TABLE, $args, $formatting );
			}
		}

		public static function update_item_e_commerce( $new_item_e_commerce, $wclsi_item_id ) {
			if ( empty( $new_item_e_commerce ) ) { return; }

			global $wpdb, $WCLSI_ITEM_E_COMMERCE_TABLE;

			$update_args = self::get_item_e_commerce_mysql_args( $new_item_e_commerce, $wclsi_item_id );
			$where_args['item_e_commerce_id'] = $new_item_e_commerce->itemECommerceID;

			if( isset( $update_args[ 'created_at' ] ) ) {
				unset( $update_args['created_at'] );
			}

			$update_format = array( '%d', '%d', '%s', '%s', '%f', '%f', '%f', '%f', '%d' );
			$where_format  = array( '%d' );

			$wpdb->update( $WCLSI_ITEM_E_COMMERCE_TABLE, $update_args, $where_args, $update_format, $where_format );
		}
	}

endif;