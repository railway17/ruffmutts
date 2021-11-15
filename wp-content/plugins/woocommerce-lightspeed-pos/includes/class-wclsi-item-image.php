<?php
if ( !class_exists( 'WCLSI_Item_Image' ) ) :

	class WCLSI_Item_Image {

		function __construct() {
			add_action( 'delete_attachment', array( $this, 'sync_deleted_images') );
		}

		private static function get_item_image_mysql_args( $item_image, $wclsi_item_id ) {

			$args = array(
				'wclsi_item_id' => $wclsi_item_id,
				'item_id' => isset( $item_image->itemID ) ? $item_image->itemID : null,
				'item_matrix_id' => isset( $item_image->itemMatrixID ) ? $item_image->itemMatrixID : null,
				'image_id' => isset( $item_image->imageID ) ? $item_image->imageID : null,
				'description' => isset( $item_image->description ) ? $item_image->description : null,
				'filename' => isset( $item_image->filename ) ? $item_image->filename : null,
				'ordering' => isset( $item_image->ordering ) ? $item_image->ordering : null,
				'public_id' => isset( $item_image->publicID ) ? $item_image->publicID : null,
				'base_image_url' => isset( $item_image->baseImageURL ) ? $item_image->baseImageURL : null,
				'size' => isset( $item_image->size ) ? $item_image->size : null,
				'create_time' => isset( $item_image->createTime ) ? $item_image->createTime : null,
				'time_stamp' => isset( $item_image->timeStamp ) ? $item_image->timeStamp : null,
				'created_at' => current_time('mysql')
			);

			wclsi_format_empty_vals( $args );

			return $args;
		}

		public static function insert_item_images( $item ) {
			if ( !isset( $item->Images ) ) { return; }

			if ( is_array( $item->Images->Image ) ) {
				foreach( $item->Images->Image as $image ) {
					self::insert_item_image( $image, $item->wclsi_item_id );
				}
			} else if ( is_object( $item->Images->Image ) ) {
				self::insert_item_image( $item->Images->Image, $item->wclsi_item_id );
			}
		}

		public static function update_item_images( $new_item, $old_item ) {

			if ( empty( $new_item->Images->Image ) ) {
				return;
			}

			if ( is_object( $new_item->Images->Image ) ) {
				$array_format = array( $new_item->Images->Image );
				$new_item->Images->Image = $array_format;
			}

			if ( is_array( $new_item->Images->Image ) ) {
				foreach( $new_item->Images->Image as $image ) {
					if( self::item_image_exists( $image->imageID, $old_item ) ) {
						// @todo not sure if this is necessary since not sure what kind of image updates can be performed in LS
						self::update_item_image( $image, $old_item->id );
					} else {
						self::insert_item_image( $image, $old_item->id );
					}
				}
			}

			self::prune_old_item_images( $new_item, $old_item );
		}

		public static function insert_item_image( $item_image, $wclsi_item_id ) {
			global $wpdb, $WCLSI_ITEM_IMAGES_TABLE;

			if( is_null( $item_image ) ) {
				return;
			}

			$args = self::get_item_image_mysql_args( $item_image, $wclsi_item_id );

			$formatting = array(
				'%d', '%d', '%d', '%d', '%s', '%s',
				'%d', '%s', '%s', '%d', '%s', '%s',
				'%s'
			);

			$wpdb->insert( $WCLSI_ITEM_IMAGES_TABLE, $args, $formatting );
		}

		public static function update_item_image( $new_item_image, $wclsi_item_id ) {
			global $wpdb, $WCLSI_ITEM_IMAGES_TABLE;

			$update_args = self::get_item_image_mysql_args( $new_item_image, $wclsi_item_id );
			$where_args['image_id'] = $new_item_image->imageID;

			if( isset( $update_args[ 'created_at' ] ) ) {
				unset( $update_args[ 'created_at' ] );
			}
			
			$where_format  = array( '%d' );
			$update_format = array(
				'%d', '%d', '%d', '%d', '%s', '%s',
				'%d', '%s', '%s', '%d', '%s', '%s',
				'%s'
			);

			$wpdb->update( $WCLSI_ITEM_IMAGES_TABLE, $update_args, $where_args, $update_format, $where_format );
		}

		private static function item_image_exists( $image_id, WCLSI_Lightspeed_Prod $item ) {
			foreach( $item->images as $image ) {
				if( $image->image_id == $image_id ) {
					return true;
				}
			}
			return false;
		}

		private static function missing_from_new_item_images( $old_image_id, $new_item ) {
			foreach( $new_item->Images->Image as $new_item_image ) {
				if( $new_item_image->imageID == $old_image_id ) {
					return false;
				}
			}
			return true;
		}

		/**
		 * During a sync operation, check if we need to delete any item images.
		 * @param $new_item
		 * @param $old_item
		 */
		private static function prune_old_item_images( $new_item, $old_item ) {
			foreach ( $old_item->images as $old_image ) {
				if ( self::missing_from_new_item_images( $old_image->image_id, $new_item ) ) {
					self::delete_item_image( $old_image );
				}
			}
		}

		public static function delete_item_image( $old_image ) {
			global $wpdb, $WCLSI_ITEM_IMAGES_TABLE;

			if( $old_image->wp_attachment_id > 0 ) {
				wp_delete_attachment( $old_image->wp_attachment_id, true );
			}

			$wpdb->delete( $WCLSI_ITEM_IMAGES_TABLE, array( 'image_id' => $old_image->image_id ), array( '%d' ) );
		}

		public static function download_ls_image( $image_url ){

			$image = $image_url;

			$get = wp_remote_get( $image );

			if ( is_wp_error( $get ) )
				return $get;

			$type = wp_remote_retrieve_header( $get, 'content-type' );

			if ( is_wp_error( $type ) )
				return $get;

			if ( !$type )
				return false;

			return wp_upload_bits( basename( $image ), '', wp_remote_retrieve_body( $get ) );
		}
	}

endif;
