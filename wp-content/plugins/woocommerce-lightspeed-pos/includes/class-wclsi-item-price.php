<?php
if ( !class_exists( 'WCLSI_Item_Price' ) ) :

	class WCLSI_Item_Price {

		function __construct() {}

		private static function get_item_price_mysql_args( $item_price, $wclsi_item_id ) {
			$args = array(
				'wclsi_item_id' => $wclsi_item_id,
				'amount' => isset( $item_price->amount ) ? $item_price->amount : null,
				'use_type_id' => isset( $item_price->useTypeID ) ? $item_price->useTypeID : null,
				'use_type' => isset( $item_price->useType ) ? $item_price->useType : null,
				'created_at' => current_time('mysql')
			);

			wclsi_format_empty_vals( $args );

			return $args;
		}

		public static function insert_item_prices( $item ) {

			if ( empty( $item->Prices ) ) {
				return;
			}

			if ( is_array( $item->Prices->ItemPrice ) ) {
				foreach( $item->Prices->ItemPrice as $price ) {
					self::insert_item_price( $price, $item->wclsi_item_id );
				}
			} else if ( is_object( $item->Prices->ItemPrice ) ) {
				self::insert_item_price( $item->Prices->ItemPrice, $item->wclsi_item_id );
			}
		}

		public static function update_item_prices( $new_item, $old_item ) {
			if ( empty( $new_item->Prices ) ) {
				return;
			}

			if ( is_object( $new_item->Prices->ItemPrice ) ) {
				$array_format = array( $new_item->Prices->ItemPrice );
				$new_item->Prices->ItemPrice = $array_format;
			}

			foreach( $new_item->Prices->ItemPrice as $new_item_price ) {
				if ( self::item_price_exists( $new_item_price->useTypeID, $old_item ) ) {
					self::update_item_price( $new_item_price, $old_item->id );
				} else {
					self::insert_item_price( $new_item_price, $old_item->id );
				}
			}
		}

		private static function item_price_exists( $use_type_id, WCLSI_Lightspeed_Prod $item ) {
			foreach( $item->prices as $item_price ) {
				if( $item_price->use_type_id == $use_type_id ) {
					return true;
					break;
				}
			}
			return false;
		}

		public static function insert_item_price( $item_price, $wclsi_item_id ){
			global $wpdb, $WCLSI_ITEM_PRICES_TABLE;

			$args = self::get_item_price_mysql_args( $item_price, $wclsi_item_id );

			$formatting = array(
				'%d', '%f', '%d', '%s', '%s'
			);

			$wpdb->insert( $WCLSI_ITEM_PRICES_TABLE, $args, $formatting );
		}

		public static function update_item_price( $new_item_price, $wclsi_item_id ) {
			global $wpdb, $WCLSI_ITEM_PRICES_TABLE;

			$update_args = self::get_item_price_mysql_args( $new_item_price, $wclsi_item_id );

			$where_args['wclsi_item_id'] = $wclsi_item_id;
			$where_args['use_type_id'] = $new_item_price->useTypeID;

			if( isset( $update_args[ 'created_at' ] ) ) {
				unset( $update_args['created_at'] );
			}

			if( isset( $update_args[ 'wclsi_item_id' ] ) ) {
				unset( $update_args['wclsi_item_id'] );
			}
			
			$where_format  = array( '%d', '%d' );
			$update_format = array( '%f', '%d', '%s' );

			$wpdb->update( $WCLSI_ITEM_PRICES_TABLE, $update_args, $where_args, $update_format, $where_format );
		}
	}

endif;
