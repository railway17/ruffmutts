<?php
/**
 * @class LSI_Import_Product
 * Handles imports/conversions/syncing of LightSpeed items into WooCommerce.
 */
if ( !class_exists( 'LSI_Import_Products' ) ) :

	class LSI_Import_Products {

		const MAX_NUM_OF_ATTRIBUTES = 3;

		function __construct() {
			add_action( 'before_delete_post', array( $this, 'sync_deleted_prods') );
			add_action( 'wp_ajax_get_prod_ids_ajax', array( $this, 'get_prod_ids_ajax') );
			add_action( 'wp_ajax_wclsi_load_prods_ajax', array( $this, 'wclsi_load_prods_ajax' ) );

			add_action( 'wp_ajax_import_all_lightspeed_products_ajax', array( $this, 'import_all_lightspeed_products_ajax') );
			add_action( 'wp_ajax_import_and_sync_product_ajax', array( $this, 'import_and_sync_product_ajax' ) );
			add_action( 'wp_ajax_import_product_ajax', array( $this, 'import_product_ajax' ) );
			add_action( 'wp_ajax_update_product_ajax', array( $this, 'update_product_ajax' ) );

			add_action( 'wp_ajax_get_import_progress_ajax', array( $this, 'get_import_progress_ajax' ) );
			add_action( 'wp_ajax_manual_prod_update_ajax', array( $this, 'manual_prod_update_ajax' ) );

			add_action( 'wp_ajax_set_prod_sync_ajax', array( $this, 'set_prod_sync_ajax' ) );
			add_action( 'updated_post_meta', array( $this, 'set_sync_value'), 10, 4 );
		}

		function import_item( $item, $sync = false, $img_flag = false ) {

			// Used for telling background processes to halt or not run while import is happening
			wp_cache_add( 'wclsi_importing_item', true );

			$item = apply_filters( 'wclsi_import_product', $item );
			$post_id = false;

			if ( wclsi_is_matrix_product( $item ) ) {
				set_time_limit( 180 ); // extend to 3 mins for matrix products
				$post_id = $this->import_matrix_item( $item, $sync, $img_flag );
			} elseif ( wclsi_is_simple_product( $item ) ) {
				$post_id = $this->import_single_item( $item, $sync, $img_flag );
			}

			wp_cache_delete( 'wclsi_importing_item' );

			return $post_id;
		}
		
		function import_matrix_item( $wclsi_matrix_item, $sync = false, $img_flag = false ) {
			if ( false !== wc_get_product( $wclsi_matrix_item->wc_prod_id ) ) {
				return $wclsi_matrix_item->wc_prod_id;
			}

			$wclsi_matrix_item = apply_filters( 'wclsi_import_ls_data_matrix_prod', $wclsi_matrix_item );
			$wc_variable_prod  = $this->init_wc_product( $wclsi_matrix_item );
			$this->set_wc_prod_values( $wc_variable_prod, $wclsi_matrix_item );
			$post_id = $wc_variable_prod->save();

			if ( !is_wp_error( $post_id ) ) {
				// Get & set the category
				$this->handle_product_taxonomy( $wclsi_matrix_item, $post_id );

				$variations = wclsi_get_matrix_prods( $wclsi_matrix_item->item_matrix_id );

				$ls_attr_options = WCLSI_Item_Attributes::get_lightspeed_attribute_options( $variations );
				if( !empty( $ls_attr_options ) ) {
					WCLSI_Item_Attributes::set_product_attributes_for_variable_prod(
						$wclsi_matrix_item->item_attribute_set_id, 
						$wc_variable_prod,
						apply_filters( 'wclsi_import_attributes_matrix_item', $ls_attr_options, $post_id )
					);
				}

				$this->create_variations(
					apply_filters( 'wclsi_import_variations_matrix_item', $variations, $post_id),
					$sync,
					$post_id
				);

				// set wc_prod_id so product images can get saved correctly
				$wclsi_matrix_item->wc_prod_id = $post_id;
				if( !$img_flag ) {
					$prod_imgs = $this->save_ls_prod_images( $wclsi_matrix_item );
					if ( !is_wp_error( $prod_imgs ) && !empty( $prod_imgs ) ) {
						$this->set_wc_images( $post_id, $prod_imgs );
					}
				}

				if ( $sync ) {
					update_post_meta( $post_id, WCLSI_SYNC_POST_META, true );
				}

				$this->persist_import_data( $wclsi_matrix_item, $wc_variable_prod, $sync );
			}

			return $post_id;
		}

		function import_single_item( $item, $sync, $img_flag = false ) {
			if ( false !== wc_get_product( $item->wc_prod_id ) ) {
				return $item->wc_prod_id;
			}

			$item = apply_filters( 'wclsi_import_ls_result_single_prod', $item );
			$wc_simple_prod = $this->init_wc_product( $item );
			$this->set_wc_prod_values( $wc_simple_prod, $item );
			$post_id = $wc_simple_prod->save();

			if ( !is_wp_error( $post_id ) ) {

				$this->handle_product_taxonomy( $item, $post_id );

				// Add a sync flag so our cron-job will know to sync this product
				if ( $sync ) {
					update_post_meta( $post_id, WCLSI_SYNC_POST_META, true );
				}

				// Get the product images
				if( !$img_flag ) {
					$prod_imgs = $this->save_ls_prod_images( $item );
					$prod_imgs = apply_filters( 'wclsi_import_prod_imgs_single_prod', $prod_imgs, $post_id );
					if ( !is_wp_error( $prod_imgs ) && count( $prod_imgs ) > 0 ) {
						$this->set_wc_images( $post_id, $prod_imgs );
					}
				}

				$item->wc_prod_id  = $post_id;
				$item->last_import = current_time('mysql');

				$this->persist_import_data( $item, $wc_simple_prod, $sync );
			}

			return $post_id;
		}

		function update_wc_prod( WCLSI_Lightspeed_Prod $ls_prod, $update_variations = true ) {

			$ls_prod = apply_filters( 'wclsi_update_product', $ls_prod );

			if ( $ls_prod->is_simple_product() || $ls_prod->is_variation_product() ) {
				return $this->update_single_item(  $ls_prod );
			} elseif ( $ls_prod->is_matrix_product() ) {
				return $this->update_matrix_item( $ls_prod, $update_variations );
			}

			return new WP_Error( 'wclsi_bad_update', __( 'Could not process update, invalid product!', 'woocomerce-lightspeed-pos' ) );
		}

		function update_matrix_variations( $variations, WC_Product_Variable $wc_parent_product ) {
			$new_variations_to_import = array();

			// Get the itemAttributeSetID from a random variation
			$attr_set_id = $variations[0]->item_attributes->itemAttributeSetID;
			if ( $attr_set_id > 0 ) {
				// Re-set attributes in case new variation introduces new attribute(s)
				$wclsi_parent_prod = new WCLSI_Lightspeed_Prod();
				$wclsi_parent_prod->init_via_wc_prod_id( $wc_parent_product->get_id() );
				$all_variations = array_merge( $wclsi_parent_prod->variations, $variations );

				global $WCLSI_API;
				$selective_sync_settings = $WCLSI_API->settings[ WCLSI_WC_SELECTIVE_SYNC ];
				if ( isset(  $selective_sync_settings[ 'attributes' ] ) && 'true' === $selective_sync_settings[ 'attributes' ] ) {
					$ls_attr_options = WCLSI_Item_Attributes::get_lightspeed_attribute_options( $all_variations );
					if ( !empty( $ls_attr_options ) ) {
						WCLSI_Item_Attributes::set_product_attributes_for_variable_prod(
							$attr_set_id, 
							$wc_parent_product,
							apply_filters( 'wclsi_update_attributes_matrix_item', $ls_attr_options, $wc_parent_product->get_id() )
						);
					}
				}

				/**
				 * Update attributes for all variations just in case we are adding a new product or
				 * the call to "set_item_attributes()" has caused a conversion of a custom attribute to a 
				 * taxonomy one.
				 */
				foreach( $all_variations as $wclsi_variation ) {
					if ( $wclsi_variation->wc_prod_id > 0 ) {
						$wc_variation_prod = wc_get_product($wclsi_variation->wc_prod_id);
						WCLSI_Item_Attributes::set_attributes_for_wc_variation( $wc_variation_prod, $wclsi_variation );
					}
				}
			}

			foreach ( $variations as $variation ) {
				if ( $variation->wc_prod_id > 0 ) {
					$this->update_single_item( $variation );
				} elseif ( is_null( $variation->wc_prod_id ) ) {
					$new_variations_to_import[] = $variation;
				}
			}

			if ( !empty( $new_variations_to_import ) ) {
				$sync = get_post_meta( $wc_parent_product->get_id(), WCLSI_SYNC_POST_META, true );
				$this->create_variations( $new_variations_to_import, $sync, $wc_parent_product->get_id() );
			}
		}

		function save_ls_prod_images( $item ) {
			global $WCLSI_API;
			$selective_sync = $WCLSI_API->settings[ 'wclsi_wc_selective_sync' ];
			if ( empty( $selective_sync[ 'images'] ) ) {
				return array();
			}

			$prod_imgs = !is_null( $item->images ) ? $item->images : null;
			$img_attach_ids = array();

			if ( !is_null( $prod_imgs ) ) {
				foreach( $prod_imgs as $prod_img ) {

					if( $prod_img->wp_attachment_id > 0 ) {
						// Important: if an image got deleted at some point, then we will skip this
						if( wp_attachment_is_image( $prod_img->wp_attachment_id ) ) {
							$img_attach_ids[ $prod_img->ordering ] = $prod_img->wp_attachment_id;
							continue;
						}
					}

					$attach_id = $this->get_wc_attach_id( $prod_img, $item->wc_prod_id );
					if ( is_wp_error( $attach_id ) ) {
						return $attach_id;
					} elseif ( $attach_id > 0 ) {

						// If an order is set on the image, try to add it in order, otherwise just push it
						if ( $prod_img->ordering >= 0 && !isset( $img_attach_ids[ $prod_img->ordering ] ) ) {
							$img_attach_ids[ $prod_img->ordering ] = $attach_id;
						} else {
							$img_attach_ids[] = $attach_id;
						}
					}
				}
				ksort( $img_attach_ids );
			}

			return $img_attach_ids;
		}

		function get_wc_attach_id( $prod_img, $post_id ) {

			if ( isset( $prod_img->filename ) && isset( $prod_img->base_image_url) && isset( $prod_img->public_id ) ) {
				global $wpdb, $WCLSI_ITEM_IMAGES_TABLE;

				$img_ext   = pathinfo( $prod_img->filename, PATHINFO_EXTENSION);
				$img_url   = $prod_img->base_image_url . 'q_auto:eco/' . $prod_img->public_id . '.' . $img_ext;
				$result = WCLSI_Item_Image::download_ls_image( $img_url );

				if( false === $result || is_wp_error( $result ) || !empty( $result['error'] ) ) {

					$error_msg =  "There was an issue with downloading images from Lightspeed! ";
					if( is_array( $result ) && !empty( $result[ 'error' ] ) ) {
						$error_msg .= $result[ 'error' ];
					} elseif ( is_wp_error( $result ) ) {
						$error_msg .= $result->get_error_message();
					} elseif ( false === $result ) {
						$error_msg .= 'The header value could not be determined via wp_remote_retrieve_header().';
					}

					if( is_admin() ) {
						add_settings_error(
							'wclsi_settings',
							'file_get_contents_failed',
							$error_msg
						);
					}

					return new WP_Error( 'file_get_contents_failed', $error_msg );
				}

				if ( file_exists( $result[ 'file' ] ) ) {
					$attach_id = wc_ls_create_attachment( $result[ 'file' ], $post_id, $prod_img->description );

					$wpdb->update(
						$WCLSI_ITEM_IMAGES_TABLE,
						array( 'wp_attachment_id' => $attach_id ),
						array( 'image_id' => $prod_img->image_id ),
						array( '%d' ),
						array( '%d' )
					);

					return $attach_id;
				}
			}
			return 0;
		}

		/**
		 * Uses the 'delete_post' action hook when a product gets deleted and removes the "last_import" and
		 * "last_sync" fields of the product in the wclsi cache.
		 * @param $wc_prod_id
		 */
		function sync_deleted_prods( $wc_prod_id ) {
			$ls_prod = new WCLSI_Lightspeed_Prod();
			$ls_prod->init_via_wc_prod_id( $wc_prod_id );
			$ls_prod->clear_wc_prod_association();
		}

		function get_prod_ids_ajax() {
			wclsi_verify_nonce();

			if ( isset( $_POST[ 'init_import' ] ) && true == $_POST[ 'init_import' ] ) {
				$prod_ids = wclsi_get_ls_prod_ids();
				echo json_encode( $prod_ids );
				exit;
			}
		}

		function import_all_lightspeed_products_ajax (){
			$sync = ( isset( $_POST['sync_flag'] ) && $_POST['sync_flag'] == 'true' ) ? true : false;
			$this->process_product_ajax_request( 'import', $sync );
		}

		function import_product_ajax(){
			$this->process_product_ajax_request( 'import', false );
		}

		function import_and_sync_product_ajax(){
			$this->process_product_ajax_request( 'import_and_sync' );
		}

		function update_product_ajax(){
			$this->process_product_ajax_request( 'update' );
		}

		/**
		 * Imports and adds a sync flag to all the loaded products from LightSpeed
		 * @param - $_POST['init_import'] - Will return all existing prods
		 * @param bool $sync
		 */
		function process_product_ajax_request( $action, $sync = true ) {
			wclsi_verify_nonce();

			$prod_id = isset( $_POST['prod_id'] ) ? (int) $_POST['prod_id'] : false;

			if ( false !== $prod_id ) {
				$ls_item = new WCLSI_Lightspeed_Prod( $prod_id );

				if ( $ls_item->id > 0 ) {
					if( $action == 'import' || $action == 'import_and_sync' ) {
						$result = $this->import_item( $ls_item, $sync );
					} else if( $action == 'update' ) {
						wp_cache_add( 'manual_prod_update', true );
						$result = $ls_item->update_via_api();
					}

					if( !is_wp_error( $result ) && $action === 'update' && $ls_item->wc_prod_id > 0 ) {
						$ls_item->reload();
						$result = $this->update_wc_prod($ls_item, true);
					}

					if ( !is_wp_error( $result ) ) {
						echo json_encode(
							array(
								'success' => $result,
								'prod_id' => $prod_id,
								'WAIT_TIME' => wclsi_get_api_wait_time_ms()
							)
						);
					} else {
						echo json_encode(
							array(
								'errors' => array(
									array( 'message' => $result->get_error_message() )
								)
							)
						);
						exit;
					}
				} else {
					echo json_encode(
						array(
							'errors' => array(
								array( 'message' => sprintf( __( 'Product with ID %d does not exist!', 'woocommerce-lightspeed-pos' ), $prod_id ) )
							)
						)
					);
					exit;
				}
			} else {
				echo json_encode(
					array(
						'errors' => array(
							array( 'message' => __( 'Could not find a product ID!', 'woocommerce-lightspeed-pos' ) )
						)
					)
				);
				exit;
			}
			exit;
		}

		/**
		 * Starts the load process via an AJAX call. Returns the total number of products to the JS script.
		 * JS script then makes (at most) 40 calls a minute as to not exceed API throttling.
		 */
		function wclsi_load_prods_ajax() {
			wclsi_verify_nonce();

			$offset = 0;
			if ( isset( $_POST['offset'] ) ) {
				$offset = (int) $_POST['offset'];
			}

			$limit = 1;
			if ( isset( $_POST['limit'] ) ) {
				$limit = (int) $_POST['limit'];
			}

			// Flag to get total count of products to load
			$get_count = $_POST['getCount'] === 'true' ? true : false;

			$load_matrix_prods = $_POST['getMatrix'] == 'true' ? true : false;

			if ( $load_matrix_prods ) {
				$result = $this->pull_matrix_items_by_offset( $offset, $limit );
				$prod_identifier = 'ItemMatrix';
			} else {
				$result = $this->pull_simple_items_by_offset( $offset, $limit );
				$prod_identifier = 'Item';
			}

			if ( isset( $result ) && !is_wp_error( $result ) ) {
				if ( $get_count ) {
					$count = ceil( $result->{'@attributes'}->count / 100 ) * 100;

					echo json_encode(
						array(
							'count' => $count,
							'real_count' => $result->{'@attributes'}->count,
							'errors' => get_settings_errors( 'wclsi_settings' ),
							'WAIT_TIME' => wclsi_get_api_wait_time_ms()
						)
					);
				} else {
					if( isset( $result->{$prod_identifier} ) ) {
						$ls_api_items = $result->{$prod_identifier};
					} else {
						$ls_api_items = null;
					}

					if ( isset( $ls_api_items ) ) {
						if( is_object( $ls_api_items ) ) {
							$single_item = $ls_api_items;
							$ls_api_items = array( $single_item );
						}

						foreach( $ls_api_items as $item ) {
							WCLSI_Lightspeed_Prod::insert_ls_api_item( $item );
						}
					}

					if ( ($offset + $limit) >= $result->{'@attributes'}->count ) { // Last chunk condition
						global $WCLSI_API;
						date_default_timezone_set( $WCLSI_API->store_timezone );
						update_option( WCLSI_LAST_LOAD_TIMESTAMP, date( DATE_ATOM ) );

						// We load matrix prods after simple prods, before we finish we should pull the item_attr_sets
						if( $load_matrix_prods ) {
							self::pull_item_attribute_sets();
						}

						echo json_encode(
							array(
								'loaded_chunk_' . $offset => true,
								'redirect' => admin_url('admin.php?page=lightspeed-import-page'),
								'errors' => get_settings_errors( 'wclsi_settings' ),
								'matrix' => $load_matrix_prods,
								'WAIT_TIME' => wclsi_get_api_wait_time_ms()
							)
						);

					} else {
						echo json_encode(
							array(
								'loaded_chunk_' . $offset => true,
								'errors' => get_settings_errors( 'wclsi_settings' ),
								'matrix' => $load_matrix_prods,
								'WAIT_TIME' => wclsi_get_api_wait_time_ms()
							)
						);
					}
				}

			} elseif ( is_wp_error( $result ) ) {
				echo json_encode(
					array(
						'loaded_chunk_' . $offset => true,
						'errors' => $result->get_error_message(),
						'matrix' => $load_matrix_prods,
						'WAIT_TIME' => wclsi_get_api_wait_time_ms()
					)
				);
			}
			exit;
		}

		function pull_simple_items_by_offset( $offset = 0, $limit = 100 ) {
			global $WCLSI_API, $WCLSI_SINGLE_LOAD_RELATIONS;

			$search_params = array(
				'load_relations' => json_encode( $WCLSI_SINGLE_LOAD_RELATIONS ),
				'offset' => $offset,
				'limit'  => $limit,
				'archived' => (bool) $WCLSI_API->settings[ WCLSI_IGNORE_ARCHIVED_LS_PRODS ] ? 'false' : 'true'
			);

			if ( !empty( $WCLSI_API->ls_enabled_stores ) ) {
				$search_params = array_merge( $search_params, array( 'ItemShops.shopID' => 'IN,[' . implode( ',', $WCLSI_API->ls_enabled_stores ) . ']') );
			}

			$search_params = apply_filters( 'wclsi_ls_import_prod_params', $search_params );

			return $WCLSI_API->make_api_call( 'Account/' . $WCLSI_API->ls_account_id . '/Item/', 'Read', $search_params );
		}

		function pull_matrix_items_by_offset( $offset = 0, $limit = 100 ) {
			global $WCLSI_API, $WCLSI_MATRIX_LOAD_RELATIONS;

			$resource = 'Account/' . $WCLSI_API->ls_account_id . '/ItemMatrix';
			$search_params = array(
				'offset' => $offset,
				'load_relations' => json_encode( $WCLSI_MATRIX_LOAD_RELATIONS ),
				'limit' => $limit,
				'archived' => (bool) $WCLSI_API->settings[WCLSI_IGNORE_ARCHIVED_LS_PRODS] ? 'false' : 'true'
			);

			if ( !empty( $WCLSI_API->ls_enabled_stores ) ) {
				$search_params = array_merge( $search_params, array( 'ItemShops.shopID' => 'IN,[' . implode( ',', $WCLSI_API->ls_enabled_stores ) . ']') );
			}

			$search_params = apply_filters( 'wclsi_ls_import_matrix_params', $search_params );

			return $WCLSI_API->make_api_call( $resource, 'Read', $search_params );
		}

		function pull_item_attribute_sets() {
			global $WCLSI_API;
			$item_attr_sets = $WCLSI_API->make_api_call(
				"Account/{$WCLSI_API->ls_account_id}/ItemAttributeSet",
				"Read"
			);

			if ( is_object( $item_attr_sets ) && property_exists( $item_attr_sets, 'ItemAttributeSet' ) ) {
				if ( is_object( $item_attr_sets->ItemAttributeSet ) )
					$item_attr_sets->ItemAttributeSet = array( $item_attr_sets->ItemAttributeSet );

				foreach( $item_attr_sets->ItemAttributeSet as $item_attr_set ) {
					WCLSI_Item_Attributes::insert_or_update_item_attribute_set( $item_attr_set );
				}
			}
		}

		/**
		 * Manually sync a product via the "Manual Sync" button in the LightSpeed metabox
		 * @see self::render_meta_box()
		 */
		function manual_prod_update_ajax() {
			wclsi_verify_nonce();

			if ( !isset( $_POST['prod_id'] ) ) {
				header( "HTTP/1.0 409 " . __('Could not find product ID.', 'woocommerce-lightspeed-pos' ) );
				exit;
			}

			$prod_id = (int) $_POST['prod_id'];
			$ls_prod = new WCLSI_Lightspeed_Prod();

			$ls_prod->init_via_wc_prod_id( $prod_id );

			if ( $ls_prod->id > 0 ) {
				wp_cache_add( 'manual_prod_update', true );
				$msg = WC_LS_Import_Table::process_single_update( $ls_prod->id );
				if ($msg['type'] == 'error') {
					header("HTTP/1.0 409 " . $msg['msg']);
					exit;
				} else {
					wp_send_json(
						array(
							'success' => true,
							'errors' => get_settings_errors( 'wclsi_settings' ),
							'WAIT_TIME' => wclsi_get_api_wait_time_ms()
						)
					);
				}
			} else {
				header("HTTP/1.0 409 " . __( 'Could not find a product ID to update!', 'woocommerce-lightspeed-pos' ) );
				exit;
			}
			exit;
		}

		function set_prod_sync_ajax() {
			wclsi_verify_nonce();

			if ( !isset( $_POST['prod_id'] ) ) {
				header("HTTP/1.0 409 " . __( 'Could not find product ID.', 'woocommerce-lightspeed-pos' ) );
				exit;
			}

			if ( !isset( $_POST['sync_flag'] ) ) {
				header("HTTP/1.0 409 " . __( 'Could not find sync flag parameter.', 'woocommerce-lightspeed-pos' ) );
				exit;
			}

			$sync_flag = $_POST['sync_flag'] == 'true' ? true : false;
			$prod_id   = (int) $_POST['prod_id'];

			$ls_prod = new WCLSI_Lightspeed_Prod();
			$ls_prod->init_via_wc_prod_id( $prod_id );

			if( $ls_prod->id > 0 && $ls_prod->wc_prod_id > 0 ) {
				$variations = $ls_prod->variations;
				if( !empty( $variations ) ) {
					foreach( $variations as $variation ) {
						update_post_meta( $variation->wc_prod_id, WCLSI_SYNC_POST_META, $sync_flag );
					}
				}

				$success = update_post_meta( $prod_id, WCLSI_SYNC_POST_META, $sync_flag );
				wp_send_json( array( 'success' => $success ) );
			}

			exit;
		}

		/**
		 * Sets 'wclsi_is_synced' in the wclsi_items table when $meta_key = WCLSI_SYNC_POST_META
		 * @param $meta_id
		 * @param $object_id
		 * @param $meta_key
		 * @param $_meta_value
		 */
		function set_sync_value( $meta_id, $object_id, $meta_key, $_meta_value ) {
			if ( $meta_key == WCLSI_SYNC_POST_META ) {
				global $wpdb, $WCLSI_ITEM_TABLE;

				$wpdb->update(
					$WCLSI_ITEM_TABLE,
					array( 'wclsi_is_synced' => $_meta_value ),
					array( 'wc_prod_id' => $object_id ),
					array( '%d' ),
					array( '%d' )
				);
			}
		}

		/*******************
		 * Private Methods *
		 *******************/

		/**
		 * @param WCLSI_Lightspeed_Prod $wclsi_item
		 * @param int $parent_id
		 * @return WC_Product_Simple|WC_Product_Variable|WC_Product_Variation
		 */
		private function init_wc_product( WCLSI_Lightspeed_Prod $wclsi_item, $parent_id = 0 ) {
			global $WCLSI_API;

			if ( $wclsi_item->is_matrix_product() ) {
				$wc_product = new WC_Product_Variable();
			} elseif ( $wclsi_item->is_variation_product() ) {
				$wc_product = new WC_Product_Variation();
			} elseif ( $wclsi_item->is_simple_product() ) {
				$wc_product = new WC_Product_Simple();
			}

			if( empty( $wc_product ) ) {
				global $WCLSI_WC_Logger;
				$WCLSI_WC_Logger->add(
					WCLSI_ERROR_LOG,
					'Could not initialize Woo product:' . PHP_EOL . print_r( $wclsi_item, true ) . PHP_EOL .
					wclsi_get_stack_trace() . PHP_EOL
				);
			}

			if ( $wclsi_item->is_variation_product() ) {
				$wc_product->set_parent_id( $parent_id );
			}

			// Variation products should inherit status from parent?
			if ( !$wclsi_item->is_variation_product() ) {
				if ( isset( $WCLSI_API->settings[ 'wclsi_import_status' ] ) &&
					$WCLSI_API->settings[ 'wclsi_import_status' ] == 'publish' ) {
					$wc_product->set_status( 'publish' );
				} else {
					$wc_product->set_status( 'draft' );
				}
			}

			return $wc_product;
		}

		private function set_wc_prod_values( WC_Product &$wc_product, WCLSI_Lightspeed_Prod $wclsi_item ) {
			global $WCLSI_API;
			$selective_sync = $WCLSI_API->settings[ WCLSI_WC_SELECTIVE_SYNC ];
			$is_update = $wc_product->get_id() > 0;
			$errors = [];
			$set_wc_prop =
				function( $prop, $arg ) use(&$wc_product, &$selective_sync, &$is_update, &$errors) {
					if ( is_null( $arg ) ) { return false; }

					try {
						if( !$is_update ) {
							return $wc_product->{"set_$prop"}( $arg );
						} elseif( $is_update && !empty( $selective_sync[ $prop ] ) ) {
							return $wc_product->{"set_$prop"}( $arg );
						}
					} catch ( Exception $e ) {
						$errors[] = "Could not update {$prop} for \"{$wc_product->get_formatted_name()}\": <b>{$e->getMessage()}</b>";
					}

					return true;
				};

			$set_wc_prop( 'name', $wclsi_item->description );

			$sku = wclsi_get_ls_sku( $wclsi_item );
			if ( isset( $selective_sync[ 'sku' ] ) && $this->is_valid_sku( $wclsi_item, $sku, $is_update ) ) {
				$set_wc_prop( 'sku', $sku );
			}

			$set_wc_prop( 'regular_price', $wclsi_item->get_regular_price() );
			$set_wc_prop( 'price', $wclsi_item->get_regular_price() );
			$set_wc_prop( 'sale_price', $wclsi_item->get_sale_price() );

			if ( !empty( (array) $wclsi_item->item_e_commerce ) ) {
				$set_wc_prop( 'description', $wclsi_item->item_e_commerce->long_description );
				$set_wc_prop( 'short_description', $wclsi_item->item_e_commerce->short_description );
				$set_wc_prop( 'weight', $wclsi_item->item_e_commerce->weight );
				$set_wc_prop( 'length', $wclsi_item->item_e_commerce->length );
				$set_wc_prop( 'width', $wclsi_item->item_e_commerce->width );
				$set_wc_prop( 'height', $wclsi_item->item_e_commerce->height );
			}

			$stock_quantity = wclsi_get_lightspeed_inventory($wclsi_item, true);
			
			if ( !$wclsi_item->is_matrix_product() ) {
				$set_wc_prop('stock_quantity', $stock_quantity);
			}

			if ( !$wc_product->is_type( 'variable' ) ) {
				// Don't update stock status unless selective sync stock_quantity is enabled
				if ( !empty( $selective_sync[ 'stock_quantity' ] ) ) {
					set_wc_product_stock_status( $wc_product, $stock_quantity );
				}
			}

			if ( !$is_update ) {
				$wc_product->set_catalog_visibility( 'visible' );
				$manage_stock = $wclsi_item->is_matrix_product() ? 'no' : 'yes';
				$wc_product->set_manage_stock( $manage_stock );
			}
			
			if ( !empty($errors) ) {
				if ( is_admin() ) {
					add_settings_error(
						'wclsi_settings',
						'wclsi_set_wc_prod_value_error',
						join(', ', $errors)
					);
				}

				wclsi_log_error(
					strip_tags( join(', ', $errors) ),
					array(
						'wc_prod' => strip_tags( $wc_product->get_formatted_name() ),
						'wc_prod_id' => $wc_product->get_id(),
						'item_id' => $wclsi_item->item_id,
						'item_matrix_id' => $wclsi_item->item_matrix_id
					)
				);
			}
		}

		private function is_valid_sku( $ls_prod, $sku, $is_update = false ) {
			if( wclsi_is_matrix_product( $ls_prod ) || $is_update ){
				return true;
			}

			$post_id = wc_get_product_id_by_sku( $sku );
			if( $post_id > 0 ) {
				if ( function_exists( 'add_settings_error' ) ){
					add_settings_error(
						'wclsi_settings',
						'existing_sku',
						__(
							sprintf
							(
								'Could not set SKU on product "%s": SKU value "%s" already exists.',
								$ls_prod->description,
								$sku
							),
							'woocommerce-lightspeed-pos'
						),
						'error'
					);
				}
				return false;
			} else {
				return true;
			}
		}

		// @todo move this into WCLSI_Lightspeed_Product
		private function persist_import_data( WCLSI_Lightspeed_Prod $item, WC_Product $wc_prod, $sync = false ) {
			global $wpdb, $WCLSI_ITEM_TABLE;

			$data = array(
				'wclsi_import_date' => current_time('mysql'),
				'wc_prod_id' => $wc_prod->get_id()
			);

			if( $sync ) {
				$data[ 'wclsi_is_synced' ] = true;
			}

			$wpdb->update(
				$WCLSI_ITEM_TABLE,
				$data,
				array( 'id' => $item->id ),
				array( '%s', '%d' ),
				array( '%d' )
			);

			if( $item->is_matrix_product() ) {
				update_post_meta( $wc_prod->get_id(), WCLSI_MATRIX_ID_POST_META, $item->item_matrix_id );
			} elseif ( $item->is_simple_product() ) {
				update_post_meta( $wc_prod->get_id(), WCLSI_SINGLE_ITEM_ID_POST_META, $item->item_id );
			} elseif ( $item->is_variation_product() ) {
				update_post_meta( $wc_prod->get_id(), WCLSI_SINGLE_ITEM_ID_POST_META, $item->item_id );
			}

			$wc_prod->save();
		}

		private function set_wc_images( $post_id, $prod_imgs ) {
			if ( !empty( $prod_imgs ) ) {
				set_post_thumbnail( $post_id, $prod_imgs[0] );
				unset( $prod_imgs[0] ); // Remove the featured image from the set of gallery images
				update_post_meta( $post_id, '_product_image_gallery', implode( ',', $prod_imgs ) );
			} else {
				delete_post_thumbnail( $post_id );
				update_post_meta( $post_id, '_product_image_gallery', '' );
			}
		}

		private function create_variations( $variations, $sync, $parent_id ) {
			foreach( $variations as $ls_variation ) {

				$ls_variation = apply_filters( 'wclsi_create_ls_data_variation', $ls_variation );

				$wc_variation_prod = $this->init_wc_product( $ls_variation, $parent_id );
				$this->set_wc_prod_values( $wc_variation_prod, $ls_variation );
				$post_id = $wc_variation_prod->save();

				if ( !is_wp_error( $post_id ) ) {

					// Get & set the category
					$this->handle_product_taxonomy( $ls_variation, $post_id );
					WCLSI_Item_Attributes::set_attributes_for_wc_variation( $wc_variation_prod, $ls_variation );

					// Add a sync flag so our cron-job will know to sync this product
					if ( $sync ) {
						update_post_meta( $post_id, WCLSI_SYNC_POST_META, true );
					}

					// we need to set wc_prod_id for saving images
					$ls_variation->wc_prod_id = $post_id;
					$prod_imgs = apply_filters(
						'wclsi_create_prod_imgs_variation', $this->save_ls_prod_images( $ls_variation ), $post_id
					);

					if ( !is_wp_error( $prod_imgs ) && count( $prod_imgs ) > 0 ) {
						set_post_thumbnail( $post_id, $prod_imgs[0] );
					}

					$this->persist_import_data( $ls_variation, $wc_variation_prod, $sync );
				}
			}
		}

		private function handle_product_taxonomy( $prod, $post_id ) {

			if( $prod->category_id > 0 ) {
				global $wpdb, $WCLSI_API, $WCLSI_ITEM_CATEGORIES_TABLE;

				$ls_cat = $wpdb->get_row("SELECT * FROM $WCLSI_ITEM_CATEGORIES_TABLE WHERE category_id=$prod->category_id");

				if ( !is_null( $ls_cat ) && $ls_cat->wc_cat_id > 0 ) {
					$cat_id = intval( $ls_cat->wc_cat_id );
					$cat_ids = get_ancestors( $cat_id, 'product_cat' );
					$cat_ids[] = $cat_id;
					wp_set_object_terms( $post_id, $cat_ids, 'product_cat', true );

					if( 'true' === $WCLSI_API->settings[ WCLSI_REMOVE_UNCATEGORIZED_CAT ] ){
						$default_cat_id = intval( get_option( 'default_product_cat', 0) );
						$default_cat = $wpdb->get_row("SELECT * FROM $wpdb->terms WHERE term_id=$default_cat_id");
						
						// Note: if the product does not have any categories associated to it (meaning $ls_cat is null)
						// It will still default the product to "Uncategorized"
						if ( strcmp($default_cat->name, "Uncategorized") == 0 ) {
							wp_remove_object_terms( $post_id, $default_cat_id, 'product_cat');
						}
					}
				}
			}

			// Set the tags
			if ( !empty( $prod->tags ) ) {
				wp_set_object_terms( $post_id, $prod->tags, 'product_tag', true );
			}
		}

		//@todo How to update attributes if they've been changed in Lightspeed?
		private function update_matrix_item( WCLSI_Lightspeed_Prod $ls_matrix_item, $update_variations = true ) {
			$post_id = isset( $ls_matrix_item->wc_prod_id ) ? $ls_matrix_item->wc_prod_id : 0;

			$wc_product = wc_get_product( $post_id );
			if ( !empty( $wc_product ) ) {
				$wc_product = apply_filters( 'wclsi_update_matrix_item', $wc_product, $ls_matrix_item);

				$this->set_wc_prod_values( $wc_product, $ls_matrix_item );

				// Get & set the category
				$this->handle_product_taxonomy( $ls_matrix_item, $post_id );

				$prod_imgs = apply_filters(
					'wclsi_update_post_imgs_matrix_item',
					$this->save_ls_prod_images( $ls_matrix_item ),
					$post_id
				);

				if ( !is_wp_error( $prod_imgs) && !empty( $prod_imgs ) && is_array( $prod_imgs ) ) {
					$this->set_wc_images( $post_id, $prod_imgs );
				}

				if ( $update_variations ) {
					$variations = apply_filters(
						'wclsi_update_variations_matrix_item',
						$ls_matrix_item->variations,
						$post_id
					);

					if ( !empty( $variations ) ) {
						$this->update_matrix_variations( $variations, $wc_product );
					}
				}

				delete_transient( 'wc_product_children_' . $post_id );

				$wc_product->save();
			}

			return $post_id;
		}

		// @todo Handle category updates - what happens if $new_ls_prod has a new category?
		private function update_single_item( WCLSI_Lightspeed_Prod $wclsi_item, $update_attributes = false ) {
			$wc_prod_id = isset( $wclsi_item->wc_prod_id ) ? $wclsi_item->wc_prod_id : 0;
			if ( $wc_prod_id > 0 ) {
				$wclsi_item = apply_filters( 'wclsi_update_product', $wclsi_item, $wc_prod_id );

				$wc_product = wc_get_product( $wc_prod_id );

				if( $update_attributes && $wclsi_item->is_variation_product() ) {
					WCLSI_Item_Attributes::set_attributes_for_wc_variation( $wc_product, $wclsi_item );
				}

				$this->set_wc_prod_values( $wc_product, $wclsi_item );
				$wc_product->save();

				// Set the category & tags
				$this->handle_product_taxonomy( $wclsi_item, $wc_prod_id );

				// Process images
				$prod_imgs = $this->save_ls_prod_images( $wclsi_item );
				$prod_imgs = apply_filters( 'wclsi_update_prod_imgs_single_item', $prod_imgs, $wc_prod_id );

				// Process images for single items
				if ( !is_wp_error( $prod_imgs ) && !empty( $prod_imgs ) && is_array( $prod_imgs ) ) {
					$this->set_wc_images( $wc_prod_id, $prod_imgs );
				}
			}

			return $wc_prod_id;
		}
	}

	global $WCLSI_PRODS;
	$WCLSI_PRODS = new LSI_Import_Products();
endif;
