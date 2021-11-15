<?php
class WCLSI_Lightspeed_Prod {

	private $id = 0;
	public $item_id = null;
	public $item_matrix_id = null;
	private $item_e_commerce = array();
	private $item_shops = array();
	private $prices = array();
	private $images = array();
	private $persisted = false;

	/**
	 * @var WCLSI_Lightspeed_Prod[]
	 */
	private $variations = array();

	/**
	 * WCLSI_Lightspeed_Prod constructor.
	 *
	 * You can create a new Lighspeed Product by passing in a Lightspeed API object
	 * or an ID for a Lightspeed Product object that already exists in the `<wp_prefix>_wclsi_items` table.
	 *
	 * @param $id
	 */
	function __construct( $id = 0 ) {
		if ( $id > 0 ) {
			$this->init_via_id( $id );
		}
	}

	/**
	 * Lazy load member properties
	 */
	function __get( $name ) {
		switch ( true ) {
			case ($name == 'id'):
				return $this->id;
			case ($name == 'item_e_commerce' || $name == 'ItemECommerce'):
				return $this->get_item_e_commerce();
			case ($name == 'item_shops' || $name == 'ItemShops'):
				return $this->get_item_shops();
			case ($name == 'prices' || $name == 'Prices'):
				return $this->get_item_prices();
			case ($name == 'images' || $name == 'Images'):
				return $this->get_item_images();
			case ($name == 'variations'):
				return $this->get_variations();
			case ($name == 'persisted'):
				return $this->persisted;
		}

		return null;
	}

	/******* init methods *******/

	function init_via_id( $id, $clear_cache = false ) {
		$this->init_via_col_and_id( 'id', $id, $clear_cache );
	}

	function init_via_item_id( $item_id, $clear_cache = false ) {
		$this->init_via_col_and_id( 'item_id', $item_id, $clear_cache );
	}

	function init_via_item_matrix_id( $item_matrix_id, $clear_cache = false ) {
		$this->init_via_id(self::get_mysql_id(null, $item_matrix_id), $clear_cache);
	}

	function init_via_wc_prod_id( $wc_prod_id, $clear_cache = false ) {
		$this->init_via_col_and_id('wc_prod_id', $wc_prod_id, $clear_cache );
	}

	function reload() {
		if ( $this->id > 0 ) {
			$this->init_via_id( $this->id, true );

			if ( !empty( $this->get_variations() ) ) {
				foreach( $this->variations as $variation ) {
					$variation->reload();
				}
			}
		}

		return $this;
	}

	/******* get methods *******/

	private function get_variations() {
		if ( !empty( $this->variations ) ) { return $this->variations; }

		if ( !$this->persisted ) { return null; }

		if( $this->is_matrix_product() ) {
			global $wpdb, $WCLSI_ITEM_TABLE;
			$variation_ids = $wpdb->get_col(
				"SELECT id FROM $WCLSI_ITEM_TABLE WHERE item_matrix_id=$this->item_matrix_id AND item_id>0"
			);

			foreach ( $variation_ids as $id ) {
				$this->variations[] = new WCLSI_Lightspeed_Prod( $id );
			}
		}

		return $this->variations;
	}

	private function get_item_e_commerce() {
		if ( is_array( $this->item_e_commerce ) && isset( $this->item_e_commerce[0] ) ) {
			return $this->item_e_commerce[0];
		}

		if ( !$this->persisted ) { return null; }

		global $wpdb, $WCLSI_ITEM_E_COMMERCE_TABLE;
		$this->item_e_commerce = $wpdb->get_results( "SELECT * FROM $WCLSI_ITEM_E_COMMERCE_TABLE WHERE wclsi_item_id = $this->id" );

		// Some products may not have an item_e_commerce object associated with them
		if( empty( $this->item_e_commerce ) ) { return array(); }

		return $this->item_e_commerce[0];
	}

	private function get_item_shops() {
		if ( !empty( $this->item_shops ) ) { return $this->item_shops; }

		if ( !$this->persisted ) { return null; }

		global $wpdb, $WCLSI_ITEM_SHOP_TABLE;
		$results = $wpdb->get_results( "SELECT * FROM $WCLSI_ITEM_SHOP_TABLE WHERE wclsi_item_id = $this->id" );
		if( !empty( $results ) ) {
			foreach( $results as $key => $item_shop ) {
				$item_shop->metadata = maybe_unserialize( $item_shop->metadata );
				$results[ $item_shop->shop_id ] = $item_shop;
			}
		}
		$this->item_shops = $results;
		return $this->item_shops;
	}

	private function get_item_prices() {
		if ( !empty( $this->prices ) ) { return $this->prices; }

		if ( !$this->persisted ) { return null; }

		global $wpdb, $WCLSI_ITEM_PRICES_TABLE;
		$this->prices = $wpdb->get_results( "SELECT * FROM $WCLSI_ITEM_PRICES_TABLE WHERE wclsi_item_id = $this->id" );
		return $this->prices;
	}

	private function get_item_images() {
		if ( !empty( $this->images ) ) { return $this->images; }

		if ( !$this->persisted ) { return null; }

		global $wpdb, $WCLSI_ITEM_IMAGES_TABLE;
		$this->images = $wpdb->get_results( "SELECT * FROM $WCLSI_ITEM_IMAGES_TABLE WHERE wclsi_item_id = $this->id" );
		return $this->images;
	}

	public function get_sale_price() {
		if ( $this->get_item_prices() ) {
			$sale_price = null;
			foreach( $this->prices as $price ) {
				if ( $price->use_type == 'Sale' ) {
					$sale_price = $price->amount;
				}
			}
			return apply_filters('wclsi_get_sale_price', $sale_price, $this);
		} else {
			return null;
		}
	}

	public function get_regular_price() {
		if ( $this->get_item_prices() ) {
			$regular_price = null;
			foreach( $this->prices as $price ) {
				if ( $price->use_type == 'Default' ) {
					$regular_price = $price->amount;
				}
			}
			return apply_filters('wclsi_get_regular_price', $regular_price, $this);
		} else {
			return null;
		}
	}

	/******* Update Methods *******/

	/**
	 * @param bool $update_matrix_variations
	 * @return int|WP_Error
	 */
	public function update_via_api($update_matrix_variations = true) {
		global $WCLSI_API;

		$this->ensure_item_id_exists();

		$api_data = wclsi_get_prod_api_path( $this );
		$endpoint = "Account/{$WCLSI_API->ls_account_id}{$api_data['path']}";

		$result = $WCLSI_API->make_api_call( $endpoint, 'Read', $api_data['params'] );

		if( is_wp_error( $result ) )
			return $result;

		if ( $this->is_matrix_product() ) {
			$new_ls_api_item = $result->ItemMatrix;
		} elseif ( $this->is_simple_product() || $this->is_variation_product() ) {
			$new_ls_api_item = $result->Item;
		} else {
			$error_msg = __( 'Error: invalid Lightspeed Product. Could not complete update operation!', 'woocommerce-lightspeed-pos' );
			if( is_admin() ) {
				add_settings_error(
					'wclsi_settings',
					'invalid_ls_object',
					$error_msg
				);
			}
			return new WP_Error( 'invalid_ls_object', $error_msg, $result );
		}

		$this->copy_wclsi_fields( $new_ls_api_item );

		if ( $this->is_matrix_product() && $update_matrix_variations ) {
			$this->persist_matrix_variations();
		}

		self::update_via_api_item( $new_ls_api_item, $this );

		return $this->id;
	}

	function persist_matrix_variations() {
		if ( !$this->is_matrix_product() )
			return false;
		
		global $WCLSI_SINGLE_LOAD_RELATIONS, $WCLSI_API;

		$endpoint = "Account/$WCLSI_API->ls_account_id/Item/";
		$search_params = array(
			'load_relations' => json_encode( $WCLSI_SINGLE_LOAD_RELATIONS ),
			'itemMatrixID' => $this->item_matrix_id
		);

		/**
		 * It seems like adding archived => true the API is inclusive of products that are not archived
		 */
		if ( "true" !== $WCLSI_API->settings[ WCLSI_IGNORE_ARCHIVED_LS_PRODS ] ) {
			$search_params[ 'archived' ] = true;
		}

		// Add a 1 second delay before making a second API call
		sleep(1);

		$updated_variations = $WCLSI_API->make_api_call( $endpoint, 'Read', $search_params );

		if( is_wp_error( $updated_variations ) ) {
			return $updated_variations;
		}

		if ( !empty( $updated_variations->Item ) ) {
			if ( is_object( $updated_variations->Item ) ) {
				$updated_variations->Item = array( $updated_variations->Item );
			}

			$this->update_variations( $updated_variations->Item );
		}
	}

	/**
	 * Fetches a Lightspeed's single product parent matrix product and saves it to the DB
	 */
	function persist_parent_matrix() {
		if ( empty( $this->item_matrix_id ) ) {
			return false;
		}

		$wclsi_matrix_id = WCLSI_Lightspeed_Prod::get_mysql_id( null, $this->item_matrix_id );
		if ( $wclsi_matrix_id > 0 )
			return $wclsi_matrix_id;
			
		global $WCLSI_API, $WCLSI_MATRIX_LOAD_RELATIONS;

		sleep(1);

		$response = $WCLSI_API->make_api_call(
			"Account/$WCLSI_API->ls_account_id/ItemMatrix/$this->item_matrix_id",
			"Read",
			array( 'load_relations' => json_encode( $WCLSI_MATRIX_LOAD_RELATIONS ) )
		);

		if ( isset( $response->ItemMatrix ) ) {
			return WCLSI_Lightspeed_Prod::insert_ls_api_item(  $response->ItemMatrix );
		} else {
			return $response;
		}
	}

	/**
	 * This function will accommodate new variations that have only been added
	 * in Lightspeed after the created_at date of the record
	 *
	 * @param $updated_variations
	 */
	private function update_variations( $updated_variations ) {
		foreach( $updated_variations as $ls_api_item ) {
			
			$item_mysql_id = self::get_mysql_id( $ls_api_item->itemID, $ls_api_item->itemMatrixID );

			/**
			 * If the variation exists, then use existing update() function,
			 * otherwise insert a new record
			 */
			if( $item_mysql_id > 0 ) {
				$old_item = new WCLSI_Lightspeed_Prod($item_mysql_id);
				self::update_via_api_item( $ls_api_item, $old_item );
			} else {
				self::insert_ls_api_item( $ls_api_item );
			}
		}

		global $WCLSI_API;
		if ( 'true' === $WCLSI_API->settings[ WCLSI_PRUNE_DELETED_VARIATIONS ] ) {
			$this->prune_old_variations( $updated_variations );    
		}
	}
	
	private function prune_old_variations( $updated_variations ) {
		$updated_variation_ids = array_column($updated_variations, 'itemID');
		foreach( $this->get_variations() as $old_variation ) {
			if ( !in_array($old_variation->item_id, $updated_variation_ids ) ) {
				$wc_prod = wc_get_product($old_variation->wc_prod_id);
				if (!empty($wc_prod)) {
				   $wc_prod->delete(true); 
				}
				$old_variation->delete();
			}
		}
	}
	
	/**
	 * Hotpatch - there have been reports of NULL item_id for single items
	 */
	private function ensure_item_id_exists() {
		if ( is_null( $this->item_id ) && 0 === (int) $this->item_matrix_id ) {
			global $WCLSI_API;

			$item_shops = $this->get_item_shops();
			$primary_shop_id = (int) $WCLSI_API->settings[ WCLSI_INVENTORY_SHOP_ID ];

			if( isset( $item_shops[ $primary_shop_id ] ) ) {
				$primary_item_shop = $item_shops[ $primary_shop_id ];

				if( !is_null( $primary_item_shop ) && !is_null( $primary_item_shop->item_id ) ) {
					$this->item_id = $primary_item_shop->item_id;
					$this->update_column( 'item_id', $this->item_id );
				} else {
					global $WCLSI_WC_Logger;
					$WCLSI_WC_Logger->add(
						WCLSI_ERROR_LOG,
						'Warning: Found Corrupt Lightspeed record:' . PHP_EOL .
						print_r( $this, true ) . PHP_EOL .
						wclsi_get_stack_trace() . PHP_EOL
					);

					die();
				}
			}
		}
	}
	
	/******* Update Methods *******/

	/**
	 * @param stdClass $new_item
	 * @param WCLSI_Lightspeed_Prod $old_item
	 */
	public static function update_via_api_item( $new_item, $old_item ) {
		global $wpdb, $WCLSI_ITEM_TABLE;

		$new_item->wclsi_last_sync_date = current_time('mysql');

		$update_args = self::get_item_mysql_args( $new_item );
		$where_args['id'] = $old_item->id;

		$unset_fields = array(
			'created_at',
			'wc_prod_id',
			'wclsi_import_date',
			'wclsi_is_synced'
		);

		foreach( $unset_fields as $field ) {
			if( array_key_exists( $field, $update_args ) ) {
				unset( $update_args[ $field ] );
			}
		}

		$where_format  = array( '%d' );
		$update_format = array(
			'%d', '%d', '%s', '%s', '%s', '%f',
			'%f', '%d', '%d', '%d', '%s', '%d',
			'%s', '%d', '%d', '%d', '%s', '%s',
			'%d', '%d', '%d', '%d', '%d', '%d',
			'%d', '%d', '%s', '%s', '%s', '%d',
			'%s', '%s'
		);
		
		$wpdb->update( $WCLSI_ITEM_TABLE, $update_args, $where_args, $update_format, $where_format );

		WCLSI_Item_Price::update_item_prices( $new_item, $old_item );
		WCLSI_Item_Shop::update_item_shops( $new_item, $old_item );
		WCLSI_Item_Image::update_item_images( $new_item, $old_item );

		if( !empty( $old_item->get_item_e_commerce() ) ) {
			WCLSI_Item_E_Commerce::update_item_e_commerce( $new_item->ItemECommerce, $old_item->id );
		} else {
			WCLSI_Item_E_Commerce::insert_item_e_commerce( $new_item->ItemECommerce, $old_item->id );
		}
	}

	/******* Insert Methods *******/

	/**
	 * @param $item
	 * @param null $custom_id
	 * @param null $custom_value
	 *
	 * @return int|null|string
	 */
	public static function insert_ls_api_item( $item, $custom_id = null, $custom_value = null ){
		global $wpdb, $WCLSI_ITEM_TABLE;

		$result = self::item_exists( $item );
		if( $result > 0 ) {
			return $result;
		}

		$args = self::get_item_mysql_args( $item, $custom_id, $custom_value );

		$formatting = array(
			'%d', '%d', '%s', '%s', '%s', '%f',
			'%f', '%d', '%d', '%d', '%s', '%d',
			'%s', '%d', '%d', '%d', '%s', '%s',
			'%d', '%d', '%d', '%d', '%d', '%d',
			'%d', '%d', '%s', '%s', '%s', '%d',
			'%s', '%s', '%d', '%s', '%s', '%d'
		);

		$wpdb->insert( $WCLSI_ITEM_TABLE, $args, $formatting );

		$item->wclsi_item_id = $wpdb->insert_id;

		if( isset( $item->ItemECommerce ) ) {
			WCLSI_Item_E_Commerce::insert_item_e_commerce( $item->ItemECommerce, $item->wclsi_item_id );
		}

		WCLSI_Item_Shop::insert_item_shops( $item );
		WCLSI_Item_Price::insert_item_prices( $item );
		WCLSI_Item_Image::insert_item_images( $item );

		return $item->wclsi_item_id;
	}

	/******* Delete Methods *******/

	function delete( $delete_variations = false ) {
		global $wpdb, $WCLSI_ITEM_TABLE, $WCLSI_RELATED_ITEM_TABLES;

		foreach ( $WCLSI_RELATED_ITEM_TABLES as $table_name ) {
			$wpdb->query( "DELETE FROM $table_name WHERE wclsi_item_id = $this->id" );
		}

		if( $this->is_matrix_product() && $delete_variations ) {
			$this->delete_variations();
		}

		$wpdb->query( "DELETE FROM $WCLSI_ITEM_TABLE WHERE id = $this->id" );
	}

	function delete_variations() {
		if( empty( $this->get_variations() ) ) { return; }

		foreach( $this->variations as $variation ) {
			$variation->delete();
		}
	}

	/******* Utility Methods *******/

	private static function get_item_mysql_args( $item, $custom_id = null, $custom_value = null ) {

		$tags = null;
		if( isset( $item->Tags ) ) {
			if ( is_string( $item->Tags->tag ) ) {
				$tags = array( $item->Tags->tag );
			} else if ( is_array( $item->Tags->tag ) ) {
				$tags = $item->Tags->tag;
			}
		}

		$custom_field_values = null;
		if( isset( $item->CustomFieldValues ) ) {
			if ( is_object( $item->CustomFieldValues ) ) {
				$custom_field_values = array( $item->CustomFieldValues );
			} else if ( is_array( $item->CustomFieldValues ) ) {
				$custom_field_values = $item->CustomFieldValues;
			}
		}

		$args = array(
			'item_id'               => isset( $item->itemID ) ? $item->itemID : null,
			'item_matrix_id'        => isset( $item->itemMatrixID ) ? $item->itemMatrixID : null,
			'system_sku'            => isset( $item->systemSku ) ? $item->systemSku : null,
			'custom_sku'            => isset( $item->customSku ) ? $item->customSku : null,
			'manufacturer_sku'      => isset( $item->manufacturerSku ) ? $item->manufacturerSku : null,
			'default_cost'          => isset( $item->defaultCost ) ? $item->defaultCost : null,
			'avg_cost'              => isset( $item->avgCost ) ? $item->avgCost : null,
			'discountable'          => isset( $item->discountable ) && $item->discountable == 'true' ? true : false,
			'tax'                   => isset( $item->tax ) && $item->tax == 'true' ? true : false,
			'archived'              => isset( $item->archived ) && $item->archived == 'true' ? true : false,
			'item_type'             => isset( $item->itemType ) ? $item->itemType : null,
			'serialized'            => isset( $item->serialized ) ? $item->serialized : null,
			'description'           => isset( $item->description ) ? $item->description : null,
			'model_year'            => isset( $item->modelYear ) ? $item->modelYear : null,
			'upc'                   => isset( $item->upc ) ? $item->upc : null,
			'ean'                   => isset( $item->ean ) ? $item->ean : null,
			'create_time'           => isset( $item->createTime ) ? $item->createTime : null,
			'time_stamp'            => isset( $item->timeStamp ) ? $item->timeStamp : null,
			'category_id'           => isset( $item->categoryID ) ? $item->categoryID : null,
			'tax_class_id'          => isset( $item->taxClassID ) ? $item->taxClassID : null,
			'department_id'         => isset( $item->departmentID ) ? $item->departmentID : null,
			'manufacturer_id'       => isset( $item->manufacturerID ) ? $item->manufacturerID : null,
			'season_id'             => isset( $item->seasonID ) ? $item->seasonID : null,
			'default_vendor_id'     => isset( $item->defaultVendorID ) ? $item->defaultVendorID : null,
			'item_e_commerce_id'    => isset( $item->itemECommerceID ) ? $item->itemECommerceID : null,
			'item_attribute_set_id' => isset( $item->itemAttributeSetID ) ? $item->itemAttributeSetID : null,
			'item_attributes'       => isset( $item->ItemAttributes ) ? maybe_serialize( $item->ItemAttributes ) : null,
			'tags'                  => maybe_serialize( $tags ),
			'custom_field_values'   => maybe_serialize( $custom_field_values ),
			'custom_id'             => $custom_id,
			'custom_value'          => maybe_serialize( $custom_value ),
			'created_at'            => isset( $item->created_at ) ? $item->created_at : current_time('mysql'),
			'wc_prod_id'            => isset( $item->wc_prod_id ) ? $item->wc_prod_id : null,
			'wclsi_import_date'     => isset( $item->wclsi_import_date ) ? $item->wclsi_import_date : null,
			'wclsi_last_sync_date'  => isset( $item->wclsi_last_sync_date ) ? $item->wclsi_last_sync_date : null,
			'wclsi_is_synced'       => isset( $item->wclsi_is_synced ) ? $item->wclsi_is_synced : null
		);

		wclsi_format_empty_vals( $args );

		return $args;
	}

	private function init_via_col_and_id( $column_name, $id, $clear_cache = false ) {
		if ( 'id' === $column_name && !$clear_cache ) {
			$cache_result = wp_cache_get( "wclsi-{$id}", "wclsi-prods" );

			if ( false !== $cache_result && is_object( $cache_result ) ) {
				foreach( $cache_result as $k => $v ) {
					$this->{$k} = $v;
				}

				// Return early since we got a positive cache result
				return;
			}
		}

		global $wpdb, $WCLSI_ITEM_TABLE;

		if( $clear_cache ) {
			$wpdb->flush();
			$this->item_e_commerce = null;
			$this->item_shops = null;
			$this->prices = null;
			$this->images = null;
			$this->variations = null;
		}
		
		$result = $wpdb->get_row( "SELECT * FROM $WCLSI_ITEM_TABLE WHERE $column_name = $id", ARRAY_A );

		if ( !empty( $result ) ) {
			foreach ( $result as $property => $value ) {
				$this->{$property} = maybe_unserialize( $value );
			}

			$booleans = array( 'discountable', 'tax', 'archived', 'serialized' );
			foreach( $booleans as $bool_property ) {
				$this->{$bool_property} = (bool) $this->{$bool_property};
			}

			$this->persisted = true;
		}

		if ( 'id' === $column_name && !empty($result) ) {
			wp_cache_set( "wclsi-{$id}", $this, "wclsi-prods" );
		}
	}

	public static function get_mysql_id( $item_id, $item_matrix_id ) {
		global $wpdb, $WCLSI_ITEM_TABLE;

		$result = 0;
		if ( empty( $item_id ) && $item_matrix_id >= 0 ) {
			$result = $wpdb->get_var( "SELECT id FROM $WCLSI_ITEM_TABLE WHERE item_id IS NULL AND item_matrix_id = $item_matrix_id" );
		} else if ( $item_id > 0 && $item_matrix_id >= 0 ) {
			$result = $wpdb->get_var( "SELECT id FROM $WCLSI_ITEM_TABLE WHERE item_id = $item_id AND item_matrix_id = $item_matrix_id" );
		}

		return $result;
	}

	function is_matrix_product() {
		return is_null( $this->item_id ) && $this->item_matrix_id > 0;
	}

	function is_simple_product() {
		return $this->item_id > 0 && $this->item_matrix_id == 0;
	}

	function is_variation_product() {
		return $this->item_id > 0 && $this->item_matrix_id > 0;
	}
	

	/**
	 * Removes the wc_prod_id association
	 */
	function clear_wc_prod_association(){
		global $wpdb, $WCLSI_ITEM_TABLE;

		if ( $this->wc_prod_id > 0 ) {
			$wpdb->query("UPDATE {$WCLSI_ITEM_TABLE} SET wclsi_is_synced = NULL where id = {$this->id}");
			$wpdb->query("UPDATE {$WCLSI_ITEM_TABLE} SET wclsi_last_sync_date = NULL where id = {$this->id}");
			$wpdb->query("UPDATE {$WCLSI_ITEM_TABLE} SET wclsi_import_date = NULL where id = {$this->id}");
			$wpdb->query("UPDATE {$WCLSI_ITEM_TABLE} SET wc_prod_id = NULL where id = {$this->id}");

			if( $this->is_matrix_product() && !empty( $this->get_variations() ) ) {
				foreach( $this->variations as $variation ) {
					$variation->clear_wc_prod_association();
				}
			}
		}
	}

	function update_column( $column_name, $value ) {
		if ( !$this->persisted ) { return false; }

		global $wpdb, $WCLSI_ITEM_TABLE;

		$wpdb->show_errors();
		return $wpdb->update(
			$WCLSI_ITEM_TABLE,
			array( $column_name => maybe_serialize( $value ) ),
			array( 'id' => $this->id ),
			array( wclsi_get_wpdb_field_type( $value ) ),
			array( '%d' )
		);
	}

	/**
	 * Copies over wclsi-related fields from an exist local db record to a
	 * ls_api_item that was just pulled from Lightspeed's api
	 * @param $ls_api_item
	 */
	function copy_wclsi_fields( &$ls_api_item ) {
		$ls_api_item->wclsi_import_date = $this->wclsi_import_date;
		$ls_api_item->wclsi_last_sync_date = $this->wclsi_last_sync_date;
		$ls_api_item->wclsi_is_synced = $this->wclsi_is_synced;
		$ls_api_item->wc_prod_id = $this->wc_prod_id;
	}

	/**
	 * @param $ls_api_item
	 * @return int|null|string
	 */
	public static function item_exists( $ls_api_item ){
		$item_id = isset( $ls_api_item->itemID ) ? $ls_api_item->itemID : null;
		$item_matrix_id = isset( $ls_api_item->itemMatrixID ) ? $ls_api_item->itemMatrixID : 0;

		return self::get_mysql_id( $item_id, $item_matrix_id );
	}

	/**
	 * @param int $wc_prod_id
	 * @return string|null
	 */
	public static function is_linked($wc_prod_id ) {
		global $wpdb, $WCLSI_ITEM_TABLE;
		return $wpdb->get_var( "SELECT id FROM $WCLSI_ITEM_TABLE WHERE wc_prod_id = $wc_prod_id" );
	}

	/**
	 * Gets a count of all the item
	 * @return int
	 */
	public static function get_item_count() {
		global $wpdb, $WCLSI_ITEM_TABLE;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $WCLSI_ITEM_TABLE" );
	}
}
