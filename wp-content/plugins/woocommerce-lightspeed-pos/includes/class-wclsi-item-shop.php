<?php
if ( !class_exists( 'WCLSI_Item_Shop' ) ) :

	class WCLSI_Item_Shop {

		function __construct() {}

		private static function get_item_shop_mysql_args( $item_shop, $wclsi_item_id ) {
			$args = array(
				'wclsi_item_id' => $wclsi_item_id,
				'item_shop_id' => isset( $item_shop->itemShopID ) ? $item_shop->itemShopID : null,
				'qoh' => isset( $item_shop->qoh ) ? $item_shop->qoh : null,
				'backorder' => isset( $item_shop->backorder ) ? $item_shop->backorder : null,
				'component_qoh' => isset( $item_shop->componentQoh ) ? $item_shop->componentQoh : null,
				'component_backorder' => isset( $item_shop->componentBackorder ) ? $item_shop->componentBackorder : null,
				'reorder_point' => isset( $item_shop->reorderPoint ) ? $item_shop->reorderPoint : null,
				'reorder_level' => isset( $item_shop->reorderLevel ) ? $item_shop->reorderLevel : null,
				'time_stamp' => isset( $item_shop->timeStamp ) ? $item_shop->timeStamp : null,
				'item_id' => isset( $item_shop->itemID ) ? $item_shop->itemID : null,
				'shop_id' => isset( $item_shop->shopID ) ? $item_shop->shopID : null,
				'metadata' => isset( $item_shop->metadata ) ? maybe_serialize( $item_shop->metadata ) : null,
				'created_at' => current_time( 'mysql' )
			);

			wclsi_format_empty_vals( $args );

			return $args;
		}

		public static function insert_item_shops( $item ) {
			if ( !isset( $item->ItemShops ) ) { return; }

			if ( is_array( $item->ItemShops->ItemShop ) ) {
				foreach( $item->ItemShops->ItemShop as $item_shop ) {
					self::insert_item_shop( $item_shop, $item->wclsi_item_id );
				}
			} else if ( is_object( $item->ItemShops->ItemShop ) ) {
				self::insert_item_shop( $item->ItemShops->ItemShop, $item->wclsi_item_id );
			}
		}

		public static function update_item_shops( $new_item, $old_item ) {

			if ( empty( $new_item->ItemShops->ItemShop ) ) {
				return;
			}

			if ( is_object( $new_item->ItemShops->ItemShop ) ) {
				$array_format = array( $new_item->ItemShops->ItemShop );
				$new_item->ItemShops->ItemShop = $array_format;
			}

			foreach( $new_item->ItemShops->ItemShop as $item_shop ) {
				if ( self::item_shop_exists( $item_shop->itemShopID, $old_item ) ) {
					self::update_item_shop( $item_shop, $old_item->id );
				} else {
					self::insert_item_shop( $item_shop, $old_item->id );
				}
			}

			self::prune_old_item_shops( $new_item, $old_item );
		}

		private static function item_shop_exists( $item_shop_id, WCLSI_Lightspeed_Prod $item ) {
			foreach( $item->item_shops as $item_shop ) {
				if( $item_shop->item_shop_id == $item_shop_id ) {
					return true;
					break;
				}
			}
			return false;
		}

		private static function missing_from_new_item_shops( $old_item_shop_id, $new_item ) {
			foreach( $new_item->ItemShops->ItemShop as $new_item_shop ) {
				if( $new_item_shop->itemShopID == $old_item_shop_id ) {
					return false;
				}
			}
			return true;
		}

		/**
		 * During a sync operation, check if we need to delete any item shops.
		 * This may occur if a store was deleted in Lightspeed.
		 * @todo use array_filter()
		 * @param $new_item
		 * @param $old_item
		 */
		private static function prune_old_item_shops( $new_item, $old_item ) {
			foreach ( $old_item->item_shops as $old_item_shop ) {
				if ( self::missing_from_new_item_shops( $old_item_shop->item_shop_id, $new_item ) ) {
					self::delete_item_shop( $old_item_shop->item_shop_id );
				}
			}
		}

		public static function insert_item_shop( $item_shop, $wclsi_item_id ) {
			global $wpdb, $WCLSI_ITEM_SHOP_TABLE;

			$args = self::get_item_shop_mysql_args( $item_shop, $wclsi_item_id );

			$formatting = array(
				'%d', '%d', '%d', '%d', '%d', '%d',
				'%d', '%d', '%s', '%d', '%d', '%s',
				'%s', '%s'
			);

			$wpdb->insert( $WCLSI_ITEM_SHOP_TABLE, $args, $formatting );
		}

		public static function update_item_shop( $new_item_shop, $wclsi_item_id ) {
			global $wpdb, $WCLSI_ITEM_SHOP_TABLE;

			$update_args = self::get_item_shop_mysql_args( $new_item_shop, $wclsi_item_id );
			$where_args['item_shop_id'] = $new_item_shop->itemShopID;

			if( isset( $update_args[ 'created_at' ] ) ) {
				unset( $update_args['created_at'] );
			}
			
			$where_format  = array( '%d' );
			$update_format = array(
				'%d', '%d', '%d', '%d', '%d', '%d',
				'%d', '%d', '%s', '%d', '%d', '%s',
				'%s', '%s'
			);

			$wpdb->update( $WCLSI_ITEM_SHOP_TABLE, $update_args, $where_args, $update_format, $where_format );
		}

		public static function delete_item_shop( $item_shop_id ) {
			global $wpdb, $WCLSI_ITEM_SHOP_TABLE;
			$wpdb->delete( $WCLSI_ITEM_SHOP_TABLE, array( 'item_shop_id' => $item_shop_id ), array( '%d' ) );
		}
	}

endif;
