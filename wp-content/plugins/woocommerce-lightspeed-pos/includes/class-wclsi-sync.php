<?php
/**
 * @class WCLSI_Synchronizer
 */

if ( ! class_exists('WCLSI_Synchronizer') ) :
	class WCLSI_Synchronizer {

		const DEFAULT_LS_ATTR_SET_ID = 4;
		const MAX_SYNC_RETRY = 20;
		const LS_BENIGN_ERRORS = [
			"You must set at least one ItemAttributes attribute on an ItemMatrix Item."
		];

		function __construct() {
			add_filter( 'woocommerce_updated_product_stock', array($this, 'queue_sync_after_updated_stock'), 1, 1 );
			add_action( 'woocommerce_update_product', array( $this, 'queue_sync_after_wc_prod_update'), 1, 2 );
			add_action( 'woocommerce_update_product_variation', array( $this, 'queue_sync_after_wc_prod_update'), 1, 2 );
			add_action( 'wclsi_sync_changes_to_lightspeed', array( $this, 'sync_changes_to_lightspeed'), 1, 2 );
			add_filter( 'woocommerce_add_to_cart_product_id', array( $this, 'pull_lightspeed_inventory_on_add_to_cart') );
			add_action( 'woocommerce_before_checkout_process', array( $this, 'update_cart_inventory_before_checkout') );
			add_action( 'http_api_curl', array( $this, 'fix_curl_opts_for_img_upload' ), 10, 3 );
			add_action( 'wp_ajax_sync_prod_to_ls', array( $this, 'sync_prod_to_ls_ajax') );
			add_action( 'admin_notices', array( $this, 'display_sync_notifications') );
		}

		/**
		 * @return bool
		 */
		public static function update_in_progress() {
			return wp_cache_get( 'wclsi_importing_item' ) ||
				wp_cache_get( 'manual_prod_update' ) ||
				wp_cache_get( 'checkout_prod_update' ) ||
				wp_cache_get( 'wclsi_poller_update' ) ||
				wp_cache_get( 'wclsi_adding_new_products' );
		}
		
		/**
		 * Displays notifications relating to sync operations
		 */
		function display_sync_notifications () {
			if( is_admin() ) {
				$wclsi_notifications = get_option( WCLSI_NOTIFICATIONS, array() );

				$notification = null;
				if ( array_key_exists( 'wclsi_sync_notifications', $wclsi_notifications ) ) {
					$notification = $wclsi_notifications[ 'wclsi_sync_notifications' ];
				}

				/**
				 * Look up pending sync events
				 */
				$pending_sync_events = as_get_scheduled_actions(
					array(
						'group' => 'wclsi', 
						'hook' => 'wclsi_sync_changes_to_lightspeed',
						'status' => ActionScheduler_Store::STATUS_PENDING,
						'per_page' => -1
					) 
				);

				if ( count( $pending_sync_events ) > 0 ) {
					$class = "notice notice-success is-dismissible";
					$sync_prods_pending = '';
					foreach( $pending_sync_events as $event ) {
						$args = $event->get_args();
						$wc_prod = wc_get_product( $args['changes']['id'] );
						if ( !empty( $wc_prod ) ) {
							$permalink = admin_url("tools.php?page=action-scheduler&s={$wc_prod->get_id()}&orderby=schedule&order=desc&status=pending");
							$sync_prods_pending .= "<li><a href='{$permalink}'>{$wc_prod->get_formatted_name()}</a></li>";
						}
					}

					printf(
						'<div class="%1$s">%5$s<p>%2$s</p>%3$s%4$s</div>', 
						$class,
						'Syncing changes to Lightspeed! The events are being processed in the background.',
						'<p>Refresh or click on a product to see if the action scheduler completed syncing the changes.</p>',
						"<ul style='list-style: circle; margin-left: 55px'>$sync_prods_pending</ul>",
						"<span class='spinner wclsi-spinner' style='float: left; margin-top: 5px'>&nbsp;</span>"
					);
				}

				if ( !empty( $notification ) ) {
					$class = "notice notice-{$notification[ 'type' ]} is-dismissible";
					printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $notification[ 'msg' ] );

					unset( $wclsi_notifications[ 'wclsi_sync_notifications' ] );
					update_option( WCLSI_NOTIFICATIONS, $wclsi_notifications );
				}
			}
		}

		/**
		 * Queue sync after "woocommerce_updated_product_stock" action gets triggered. It's run queries directly
		 * on the DB and so is not part of the save() chain.
		 * @param $wc_prod_id
		 */
		function queue_sync_after_updated_stock( $wc_prod_id ) {
			$wc_prod = wc_get_product( $wc_prod_id );
			wp_cache_add("wclsi_prod_changes_for_{$wc_prod_id}", array( 'updated_stock' => true ));
			$this->queue_sync_after_wc_prod_update( $wc_prod_id, $wc_prod );
		}
		
		/**
		 * @param $wc_prod_id
		 * @param WC_Product $wc_prod
		 */
		function queue_sync_after_wc_prod_update( $wc_prod_id, WC_Product $wc_prod ) {
			/**
			 * Need to find all the places that should not trigger an additional push to lightspeed
			 */
			if (self::update_in_progress()) {
				return;
			}

			if ( !$wc_prod->get_meta( WCLSI_SYNC_POST_META, true ) ) {
				return;
			}

			$update_snapshot_set = wp_cache_get( "wclsi_update_snapshot_queued_for_{$wc_prod->get_id()}" );
			if ( $wc_prod->get_id() > 0 && !$update_snapshot_set ) {
				$update_snapshot = array(
					'id' => $wc_prod->get_id(),
					'name' => $wc_prod->get_title(),
					'sku' => $wc_prod->get_sku(),
					'regular_price' => $wc_prod->get_regular_price(),
					'sale_price' => $wc_prod->get_sale_price(),
					'stock_quantity' => $wc_prod->get_stock_quantity(),
					'short_description' => $wc_prod->get_short_description(),
					'long_description' => $wc_prod->get_description(),
					'weight' => $wc_prod->get_weight(),
					'length' => $wc_prod->get_length(),
					'width' => $wc_prod->get_width(),
					'height' => $wc_prod->get_height()
				);

				if ( $wc_prod->is_type( 'variable' ) || !$wc_prod->managing_stock() ) {
					unset( $update_snapshot['stock_quantity'] );
				}

				as_schedule_single_action(
					time() + (int) ( wclsi_get_api_wait_time_ms() / 1000 ),
					'wclsi_sync_changes_to_lightspeed',
					array( 'changes' => $update_snapshot, 'retry_count' => 0 ),
					'wclsi'
				);

				wp_cache_add( "wclsi_update_snapshot_queued_for_{$wc_prod->get_id()}", true );
			}
		}

		/**
		 * Updates inventory before checkout.
		 * 
		 * WooCommerce *should* display an "out of stock" or "not enough stock" error on the checkout page in case products in Lightspeed
		 * are out of order - this is because we are updating the associated Woo product here before check_cart_item_stock()
		 * get called.
		 */
		function update_cart_inventory_before_checkout() {
			if ( empty( WC()->cart->get_cart_contents() ) )
				return;

			$wclsi_cart_items = array();
			foreach ( WC()->cart->get_cart_contents() as $cart_item_key => $cart_item ) {
				$wc_prod = $cart_item['data'];

				if ( WCLSI_Lightspeed_Prod::is_linked( $wc_prod->get_id() ) ) {
					$syncable = get_post_meta( $wc_prod->get_id(), WCLSI_SYNC_POST_META, true );

					if ( $syncable ) {
						$ls_item = new WCLSI_Lightspeed_Prod();
						$ls_item->init_via_wc_prod_id( $wc_prod->get_id(), true );
						$wclsi_cart_items[ $ls_item->item_id ] = $cart_item;
					}
				}
			}

			if( empty( $wclsi_cart_items ) )
				return;

			global $WCLSI_API;			
			$api_data = wclsi_get_prod_api_path( array_keys( $wclsi_cart_items ) );
			$response = $WCLSI_API->make_api_call(
				"Account/{$WCLSI_API->ls_account_id}{$api_data['path']}",
				"Read",
				$api_data['params']
			);
			
			if ( !is_wp_error( $response ) ) {
				if ( is_object( $response->Item ) )
					$response->Item = array( $response->Item );
				
				$updated_ls_items = $response->Item;
				
				if( !empty( $updated_ls_items ) ) {
					$this->update_wclsi_cart_items( $updated_ls_items );
				}
			} else {
				$error_msg = __(
					'Sorry, there was an error with syncing inventory for one or more of your products in your cart. ' .
					'Please wait and try again in a few seconds. We apologise for any inconvenience caused.' .
					'woocommerce-lightspeed-pos'
				);

				wc_add_notice( apply_filters( 'wclsi_inventory_sync_error', $error_msg ), 'error' );
			}
		}
		
		/**
		 * Pulls in lightspeed inventory on adding to cart action.
		 */
		function pull_lightspeed_inventory_on_add_to_cart( $prod_id ) {
			$lookup_prod_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : $prod_id;

			$this->pull_lightspeed_inventory( $lookup_prod_id );

			return $prod_id;
		}

		/**
		 * Pulls a single product's Lightspeed inventory
		 * @param int $wc_prod_id
		 * @return bool|int
		 */
		function pull_lightspeed_inventory( int $wc_prod_id ) {
			global $WCLSI_API;
			$selective_sync = $WCLSI_API->settings[ WCLSI_WC_SELECTIVE_SYNC ];
			if ( empty( $selective_sync[ 'stock_quantity' ] ) ) {
				return false;
			}

			if ( !get_post_meta( $wc_prod_id, WCLSI_SYNC_POST_META, true ) ) {
				return false;
			}

			$ls_item = new WCLSI_Lightspeed_Prod();
			$ls_item->init_via_wc_prod_id( $wc_prod_id, true );

			if ( $ls_item->id > 0 ) {
				$result = $ls_item->update_via_api();

				if ( is_wp_error( $result ) ) {
					$error_msg = __(
						'Warning: there was an error with syncing the inventory of this product: ',
						'woocommerce-lightspeed-pos'
					);
					$error_msg .= $result->get_error_message();

					wc_add_notice(
						apply_filters( 'wclsi_inventory_sync_error', $error_msg ),
						'error'
					);

					wclsi_log_error($error_msg, $result);
					return false;
				} else {
					$ls_item->reload();
					return $this->set_wc_prod_stock( $ls_item );
				}
			} else {
				return false;
			}
		}

		/**
		 * @param $update_snapshot
		 * @param int $retry_count
		 * @return bool|void
		 * @throws Exception
		 */
		function sync_changes_to_lightspeed( $update_snapshot, $retry_count = 0 ) {
			wp_cache_add( WCLSI_SYNCING_PROD_TO_LS, true );

			// Check if the Woo product still exists before attempting to queue changes
			$wc_prod = wc_get_product( $update_snapshot['id'] );
			if( empty( $wc_prod ) ) {
				$msg = "Could not sync changes for '{$update_snapshot['name']}'. The corresponding Woo product with id '{$update_snapshot['id']}' was not found! " . 
					   "This can happen if the product was deleted before the queue could sync changes to Lightspeed.";
				$this->log_sync_failure( null, new WP_Error('wclsi_failed_sync', $msg, $update_snapshot), $update_snapshot );
				throw new Exception($msg);
			}

			// If the bucket level is too high, return early and re-schedule the update to a future time
			if ( wclsi_get_bucket_status() > 0.8 ) {
				$retry_reason = new WP_Error( 'bucket_level_exceeded_0.8', 'Lightspeed bucket level exceeded 0.8' );
				$this->retry_sync( $update_snapshot, $retry_count, $retry_reason );
				return false;
			}

			// Protects against simultaneous inventory syncs
			if ( wp_cache_get( "wclsi_ls_sync_triggered_via_{$wc_prod->get_id()}" ) ) {
				return false;
			} else {
				wp_cache_add( "wclsi_ls_sync_triggered_via_{$wc_prod->get_id()}", true );
			}

			if ( $wc_prod->is_type( 'variable' ) ) {
				$ls_item_id = $wc_prod->get_meta( WCLSI_MATRIX_ID_POST_META, true );
			} else {
				$ls_item_id = $wc_prod->get_meta( WCLSI_SINGLE_ITEM_ID_POST_META, true );
			}

			// This can sometimes happen when we are creating a new matrix product -- we do not have a handle
			// on the item_id yet
			if ( empty( $ls_item_id ) ) { return false; }

			global $WCLSI_API, $WCLSI_MATRIX_LOAD_RELATIONS, $WCLSI_SINGLE_LOAD_RELATIONS;
			$ls_payload = $this->build_ls_update_payload( $wc_prod, $ls_item_id, $update_snapshot );
			$ls_payload = apply_filters( 'wclsi_update_ls_prod', $ls_payload );
			$resource = $wc_prod->is_type( 'variable' ) ? 'Account.ItemMatrix' : 'Account.Item';
			$relations = $wc_prod->is_type( 'variable' ) ? $WCLSI_MATRIX_LOAD_RELATIONS : $WCLSI_SINGLE_LOAD_RELATIONS;
			$result = $WCLSI_API->make_api_call(
				$resource, 'Update',  array( 'load_relations' => json_encode( $relations ) ), json_encode( $ls_payload ), $ls_item_id
			);

			// retry sync if the result failed
			if ( is_wp_error( $result ) ) {
				if ( !$this->ignore_ls_error_response( $result ) ) {
					$this->retry_sync( $update_snapshot, $retry_count, $result );
					return;
				}
			} else {
				global $WCLSI_WC_Logger;
				$WCLSI_WC_Logger->add(
					WCLSI_LOG,
					PHP_EOL .
					"---- Successful Lightspeed Sync ----" . PHP_EOL .
					"---- Product '{$wc_prod->get_title()}' successfully synced with changes: ----" . PHP_EOL .
					print_r( $update_snapshot, true ) .
					PHP_EOL
				);
			}

			wp_cache_delete( WCLSI_SYNCING_PROD_TO_LS, true );
			
			return $result;
		}

		/**
		 * AJAX function to push a WC prod to LS.
		 */
		function sync_prod_to_ls_ajax() {
			wclsi_verify_nonce();

			if ( isset( $_POST['wc_prod_id'] ) ) {
				$wc_prod_id = (int) $_POST['wc_prod_id'];
			} else {
				header( "HTTP/1.0 409 " . __( 'Could not find a product ID  to sync with.' ) );
				exit;
			}

			wp_cache_add( WCLSI_SYNCING_PROD_TO_LS, true );

			$wc_prod = wc_get_product( $wc_prod_id );
			if ( $wc_prod->is_type( 'variable' ) ) {
				$this->push_matrix_prod( $wc_prod );
			} else if ( $wc_prod->is_type( 'simple' ) || $wc_prod->is_type( 'variation' ) ) {
				$this->push_simple_prod( $wc_prod );
			}

			$errors = get_settings_errors( 'wclsi_settings' );
			$response = array(
				'errors'  => $errors,
				'WAIT_TIME' => wclsi_get_api_wait_time_ms()
			);

			if ( empty( $errors ) ) {
				if ( $wc_prod->is_type( 'variable' ) ) {
					$response['variation_ids'] = $wc_prod->get_children();
				}

				if( $wc_prod->is_type( 'variation') ) {
					$response['is_variation'] = 'true';
				}
				
				if ( $wc_prod->is_type( 'simple') ) {
					$response['show_success'] = 'true';
				}
			}

			echo wp_json_encode( $response );

			wp_cache_delete( WCLSI_SYNCING_PROD_TO_LS );

			exit;
		}

		/***************************
		 *   Private Methods       *
		 ***************************/

		/**
		 * @param $result
		 * @return bool
		 */
		private function ignore_ls_error_response( $result ) {
			return $result->httpCode === '400' && in_array( $result->message, self::LS_BENIGN_ERRORS );
		}

		/**
		 * Sets a products status and updates the existing lightspeed objects in the post meta
		 * with the new inventory.
		 * @param $ls_item
		 * @return int|bool
		 */
		private function set_wc_prod_stock( WCLSI_Lightspeed_Prod $ls_item ) {
			$quantity = wclsi_get_lightspeed_inventory( $ls_item, true );
			$wc_product = wc_get_product( $ls_item->wc_prod_id );

			if ( false !== $wc_product && !is_null( $wc_product ) ) {

				$wc_inventory = $wc_product->get_stock_quantity();
				do_action( 'wclsi_update_wc_stock', $ls_item->wc_prod_id, $quantity );

				//if inventory has changed ... if not, don't do anything!
				if ( absint( $quantity ) === absint( $wc_inventory ) ) {
					return $wc_inventory;
				}

				$wc_product->set_stock_quantity( $quantity );

				set_wc_product_stock_status( $wc_product, $quantity );

				$wc_product->save();

				return $quantity;
			} else {
				global $WCLSI_WC_Logger;
				$WCLSI_WC_Logger->add(
					WCLSI_ERROR_LOG,
					'Error: could not set product inventory for product id: ' . $wc_product->get_id() . PHP_EOL .
					wclsi_get_stack_trace() . PHP_EOL
				);

				return false;
			}
		}

		/**
		 * @param WC_Product $wc_prod
		 * @param $ls_item_id
		 * @param $update_snapshot
		 * @return stdClass|void|WP_Error
		 */
		private function build_ls_update_payload( WC_Product $wc_prod, $ls_item_id, $update_snapshot ) {
			$wclsi_item = new WCLSI_Lightspeed_Prod;

			if ( false == $ls_item_id ) { return; }

			if ( $wc_prod->is_type( 'variable' ) ) {
				$wclsi_item->init_via_item_matrix_id( $ls_item_id );
			} else {
				$wclsi_item->init_via_item_id( $ls_item_id );
			}

			if ( $wclsi_item->id > 0 ) {
				$ls_payload              = new stdClass();
				$ls_payload->customSku   = $update_snapshot['sku'];
				$ls_payload->description = $update_snapshot['name'];

				if ( !$wclsi_item->is_matrix_product() && is_int( $update_snapshot['stock_quantity'] ) ) {
					$ls_payload->ItemShops = $this->build_item_shops( $wclsi_item, $update_snapshot[ 'stock_quantity' ] );
				}

				$this->build_item_e_commerce( $ls_payload, $update_snapshot );
				$this->build_pricing( $ls_payload, $update_snapshot, true );
				$this->handle_ls_selective_sync( $ls_payload );
				return $ls_payload;
			} else {
				return new WP_Error(
					'no_ls_product',
					"Could not find Lightspeed Product with item id {$ls_item_id}",
					$ls_item_id
				);
			}
		}

		/**
		 * @param $ls_payload
		 */
		private function handle_ls_selective_sync( &$ls_payload ) {
			global $WCLSI_API;
			$selective_sync = $WCLSI_API->settings[ WCLSI_LS_SELECTIVE_SYNC ];

			$unset_prop =
				function( $prop_name, &$prop_parent, $prop_ls_key = null ) use ( $selective_sync ) {
					$prop_ls_key = is_null( $prop_ls_key ) ? $prop_name : $prop_ls_key;

					if ( is_array( $prop_parent ) ) {
						if ( !is_null( $prop_parent[$prop_ls_key] ) && empty( $selective_sync[ $prop_name ] ) ) {
							unset( $prop_parent[$prop_ls_key] );
						}
					} elseif ( is_object( $prop_parent ) ) {
						if ( !isset( $prop_parent->{$prop_ls_key} ) ) { return; }
						if ( !is_null( $prop_parent->{$prop_ls_key} ) && empty( $selective_sync[ $prop_name ] ) ) {
							unset( $prop_parent->{$prop_ls_key} );
						}
					}
				};

			$unset_prop( 'description',  $ls_payload );
			$unset_prop( 'customSku',  $ls_payload );
			$unset_prop( 'longDescription',  $ls_payload->ItemECommerce );
			$unset_prop( 'shortDescription',  $ls_payload->ItemECommerce );
			$unset_prop( 'weight',  $ls_payload->ItemECommerce );
			$unset_prop( 'length',  $ls_payload->ItemECommerce );
			$unset_prop( 'width',  $ls_payload->ItemECommerce );
			$unset_prop( 'height',  $ls_payload->ItemECommerce );
			$unset_prop( 'stock_quantity',  $ls_payload, 'ItemShops' );

			// Price index mapping: 0: 'default', 1: 'MSRP', 2: 'Sale'
			$unset_prop( 'regular_price',  $ls_payload->Prices, 0 );
			$unset_prop( 'regular_price',  $ls_payload->Prices, 1 );
			$unset_prop( 'sale_price',  $ls_payload->Prices, 2 );

			foreach($ls_payload as $key => $value) {
				if ( empty( (array) $ls_payload->{$key} ) ) {
					unset( $ls_payload->{$key} );
				}
			}
		}

		/**
		 * @param $updated_ls_items
		 * @return array
		 */
		private function update_wclsi_cart_items( $updated_ls_items ) {
			$updated_wclsi_cart_items = array();

			// Add flag to skip_update_to_ls_prod() so we don't unnecessarily push updates to LS                     
			wp_cache_add( 'checkout_prod_update', true );

			foreach( $updated_ls_items as $ls_api_item ) {
				$key = $ls_api_item->itemID;
				$old_wclsi_item = new WCLSI_Lightspeed_Prod();
				$old_wclsi_item->init_via_item_id( $key );

				if( !empty( $old_wclsi_item ) ) {
					WCLSI_Lightspeed_Prod::update_via_api_item( $ls_api_item, $old_wclsi_item );
					$updated_wclsi_item = $old_wclsi_item->reload();
					$updated_wclsi_cart_items[ $key ] = $updated_wclsi_item;

					if( $updated_wclsi_item->wc_prod_id > 0 ) {
						global $WCLSI_PRODS;
						$WCLSI_PRODS->update_wc_prod( $updated_wclsi_item,  false );
					}
				}
			}

			// Remove skip flag			
			wp_cache_delete( 'checkout_prod_update' );

			return $updated_wclsi_cart_items;
		}

		/**
		 * @param $update_snapshot
		 * @param $retry_count
		 * @param WP_Error $retry_reason
		 * @throws Exception
		 */
		private function retry_sync ( $update_snapshot, $retry_count, WP_Error $retry_reason ) {
			if ( $retry_count > self::MAX_SYNC_RETRY ) {
				$wc_prod = wc_get_product($update_snapshot['id']);
				$this->log_sync_failure( $wc_prod, $retry_reason );

				// Action scheduler should mark this as a failed sync
				throw new Exception($retry_reason->get_error_message());
			}

			$retry_count++;

			as_schedule_single_action(
				time() + (int) ( wclsi_get_api_wait_time_ms() / 1000 ),
				'wclsi_sync_changes_to_lightspeed',
				array( 'changes' => $update_snapshot, 'retry_count' => $retry_count ),
				'wclsi'
			);

			if ( WP_DEBUG ) {
				global $WCLSI_WC_Logger;
				$level = wclsi_get_bucket_status();
				$WCLSI_WC_Logger->add(WCLSI_DEBUGGER_LOG, "Retrying - retry count: {$retry_count} - bucket level: {$level}");
			}
		}

		/**
		 * @param false|null|WC_Product $wc_prod
		 * @param WP_Error $err
		 * @param null|array $update_snapshot
		 */
		private function log_sync_failure( $wc_prod, WP_Error $err, $update_snapshot = null ) {
			$wc_prod_id = empty( $wc_prod ) ? $update_snapshot['id'] : $wc_prod->get_id(); 
			
			$ls_prod = new WCLSI_Lightspeed_Prod();
			$ls_prod->init_via_wc_prod_id( $wc_prod_id );

			$edit_link = get_edit_post_link( $wc_prod_id );
			$woo_title = empty( $wc_prod ) ? $update_snapshot['name'] : "<a href='{$edit_link}'>{$wc_prod->get_formatted_name()}</a>";

			$msg = "Warning: a sync update for Lightspeed Product has failed! " .
				"The linked WooCommerce product is '{$woo_title}'. " .
				"It is recommend to manually verify inventory levels and product properties in Lightspeed are up to " .
				"date with what is set in WooCommerce.";

			$ls_item_id = empty( $ls_prod->item_id ) ? 'unknown' : $ls_prod->item_id;
			$ls_item_name = empty( $ls_prod->description ) ? 'unknown' : $ls_prod->description;
			$data = array(
				'Woo Product' => $woo_title,
				'Lightspeed Item ID' => $ls_item_id,
				'Lightspeed Item Name' => $ls_item_name,
				'Error(s)' => join( PHP_EOL, $err->get_error_messages() )
			);

			if ( !empty( $err->get_error_data() ) ) {
				$data['Data'] = $err->get_error_data();
			}

			$wclsi_notifications = get_option( WCLSI_NOTIFICATIONS, array() );
			$formatted_data = print_r( $data, true );
			$wclsi_notifications[ 'wclsi_sync_notifications' ] = array(
				'msg' => "{$msg} <pre>{$formatted_data}</pre>",
				'type' => 'error'
			);
			update_option( WCLSI_NOTIFICATIONS, $wclsi_notifications );
			wclsi_log_error( $msg, $data );
		}
		
		/**
		 * Pushes a variable WC product to LightSpeed (and transforms it to a matrix product).
		 * @param WC_Product_Variable $wc_prod
		 * @return bool
		 */
		private function push_matrix_prod( WC_Product_Variable $wc_prod ) {
			global $WCLSI_API;

			$wc_attributes = $wc_prod->get_attributes();
			if ( count( $wc_attributes ) > 3 ) {
				add_settings_error(
					'wclsi_settings',
					'wclsi_too_many_attributes',
					__( 'Lightspeed allows a maximum of 3 attributes for matrix products, your product has more than 3 attributes.',
						'woocommerce-lightspeed-pos' ),
					'error'
				);

				return false;
			}

			$matrix_prod_json = $this->ls_prod_json_builder( $wc_prod );
			$matrix_prod_json->itemAttributeSetID = self::DEFAULT_LS_ATTR_SET_ID;
			$result = $WCLSI_API->make_api_call( 'Account.ItemMatrix', 'Create', '', json_encode( $matrix_prod_json ) );

			if ( ! is_wp_error( $result ) && isset( $result->ItemMatrix->itemMatrixID ) ) {
				$this->handle_img_uploads( $result->ItemMatrix, $wc_prod );
				$this->persist_ls_data( $result->ItemMatrix, $wc_prod->get_id() );
			} else {
				return false;
			}
			
			return true;
		}

		/**
		 * Pushes a simple WC product to LightSpeed.
		 *
		 * @param WC_Product $wc_prod
		 *
		 * @return bool
		 */
		private function push_simple_prod( WC_Product $wc_prod ) {
			global $WCLSI_API;

			$matrix_id = 0;
			if( $wc_prod->is_type( 'variation' ) ) {
				$matrix_id = (int) get_post_meta( $wc_prod->get_parent_id(), WCLSI_MATRIX_ID_POST_META, true );
			}

			$ls_prod_json = $this->ls_prod_json_builder( $wc_prod, $matrix_id );

			if( $wc_prod->is_type( 'variation' ) ) {
				$this->build_item_attrs( $ls_prod_json, $wc_prod->get_variation_attributes() );
			}

			$result = $WCLSI_API->make_api_call( 'Account.Item', 'Create', '', json_encode( $ls_prod_json ) );

			if ( ! is_wp_error( $result ) && isset( $result->Item->itemID ) ) {
				$this->handle_img_uploads( $result->Item, $wc_prod );
			} else {
				add_settings_error(
					'wclsi_settings',
					'bad_wc_to_ls_sync',
					$result->get_error_message(),
					'error'
				);

				return false;
			}

			$this->persist_ls_data( $result->Item, $wc_prod->get_id() );

			return true;
		}

		/**
		 * Build ItemAttributes object to a LS matrix product.
		 *
		 * @param &ls_prod
		 * @param $wc_prod_attrs
		 */
		private function build_item_attrs( &$ls_prod, $wc_prod_attrs ) {

			$ItemAttributes                     = new stdClass();
			$ItemAttributes->itemAttributeSetID = self::DEFAULT_LS_ATTR_SET_ID;

			// create an array with mappings - i.e. attribute1 => "color", attribute2 => "size"
			$id = 1;
			foreach ( $wc_prod_attrs as $key => $attr_val ) {
				$ItemAttributes->{'attribute' . $id++} = $attr_val;
			}

			for ( $i = 1; $i < 4; $i ++ ) {
				if ( ! isset( $ItemAttributes->{'attribute' . $i} ) ) {
					$ItemAttributes->{'attribute' . $i} = "";
				}
			}

			$ls_prod->ItemAttributes = $ItemAttributes;
		}

		/**
		 * @param WCLSI_Lightspeed_Prod $ls_prod
		 * @param $inventory
		 *
		 * @return array
		 */
		private function build_item_shops( WCLSI_Lightspeed_Prod $ls_prod, $inventory ) {
			$item_shops_payload = array();

			$item_shops =  $ls_prod->item_shops;
			
			if ( ! empty( $item_shops ) ) {
				global $WCLSI_API;

				if ( isset( $WCLSI_API->settings[ WCLSI_INVENTORY_SHOP_ID ] ) ) {
					$primary_shop_id = $WCLSI_API->settings[ WCLSI_INVENTORY_SHOP_ID ];
					$item_shop = $item_shops[ $primary_shop_id ];
					
					$new_item_shop = $this->setup_new_item_shop(
						$item_shop->item_shop_id,
						$item_shop->shop_id,
						$inventory
					);

					$item_shops_payload['ItemShop'][] = $new_item_shop;
					
				} else {
					// The case where it's a single store, but for whatever reason it's still an array of shopIDs
					foreach ( $item_shops as $item_shop_key => $item_shop ) {
						if ( 0 != $item_shop->shop_id ) {
							$new_item_shop = $this->setup_new_item_shop(
								$item_shop->item_shop_id,
								$item_shop->shop_id,
								$inventory
							);

							$item_shops_payload['ItemShop'][] = $new_item_shop;
						}
					}
				}
			}

			// Place the item shops under the proper structure...
			return $item_shops_payload;
		}

		/**
		 * Creates a new item shop array given an id and stock
		 *
		 * @param $item_shop_id
		 * @param $stock
		 *
		 * @return array
		 */
		private function setup_new_item_shop( $item_shop_id, $shop_id, $stock ) {
			$new_item_shop               = array();
			$new_item_shop['itemShopID'] = $item_shop_id;
			$new_item_shop['qoh']        = $stock;
			$new_item_shop['shopID']     = $shop_id;

			return $new_item_shop;
		}

		/**
		 * Helper function for handling image uploads.
		 *
		 * @param $result
		 * @param WC_Product $wc_prod
		 */
		private function handle_img_uploads( &$result, WC_Product $wc_prod ) {

			$wc_imgs     = array();
			$img_results = array();

			if ( ! $wc_prod->is_type( 'variation' ) ) {
				$wc_imgs = $wc_prod->get_gallery_image_ids();
			} else {
				$variation_id = $wc_prod->variation_id;
			}
			
			$featured_image = $wc_prod->get_image_id();
			
			if ( empty( $wc_imgs ) && empty( $featured_image ) ) {
				return;
			}
			
			// Add the featured image
			array_unshift( $wc_imgs, $featured_image );
			
			$matrix_id = 0;
			if ( !isset( $result->itemID ) && isset( $result->itemMatrixID ) && $result->itemMatrixID > 0 ) {
				$id_ref    = "itemMatrixID";
				$matrix_id = $result->itemMatrixID;
			} elseif ( $result->itemID > 0 ) {
				$id_ref = "itemID";
			}

			// upload gallery images
			$img_errors = array();
			if ( ! empty( $wc_imgs ) && is_array( $wc_imgs ) && ! empty( $id_ref ) ) {
				foreach ( $wc_imgs as $img_id ) {
					$img_result = $this->upload_img( $result->{$id_ref}, $img_id, $matrix_id );

					if ( is_wp_error( $img_result ) ) {
						$img_errors[] = $img_result;
					} else {
						$img_results[] = $img_result;
					}
				}
			} else {
				/**
				 * We have to use get_post_thumbnail() for variation prods since
				 * they extend WC_Product which will default to the parent thumb id.
				 */
				$single_img_id = isset( $variation_id ) ? get_post_thumbnail_id( $variation_id ) : $wc_prod->get_image_id();

				if ( $single_img_id > 0 && ! empty( $id_ref ) ) {
					$img_result = $this->upload_img( $result->{$id_ref}, $single_img_id, $matrix_id );
					if ( is_wp_error( $img_result ) ) {
						$img_errors[] = $img_result;
					} else {
						$img_results[] = $img_result;
					}
					$img_results[] = $img_result;
				}
			}

			if ( ! empty( $img_results ) ) {
				$result->Images = new stdClass();
				if ( count( $img_results ) > 1 ) {
					$result->Images->Image = $img_results;
				} else if ( count( $img_results ) == 1 ) {
					$result->Images->Image = $img_results[0];
				}
			}

			if ( ! empty( $img_errors ) ) {
				foreach ( $img_errors as $img_error ) {
					add_settings_error(
						'wclsi_settings',
						'bad_wc_to_ls_sync_img_upload',
						$img_error->get_error_message(),
						'error'
					);
				}
			}
		}

		/**
		 * Adds timestamps to LS objects, as well as appends them to
		 * option caches.
		 *
		 * @param $ls_prod
		 * @param $wc_prod_id
		 */
		private function persist_ls_data( $ls_prod, $wc_prod_id ) {

			$ls_prod->wc_prod_id           = $wc_prod_id;
			$ls_prod->wclsi_is_synced      = true;
			$ls_prod->wclsi_import_date    = current_time( 'mysql' );
			$ls_prod->wclsi_last_sync_date = current_time( 'mysql' );

			update_post_meta( $wc_prod_id, WCLSI_SYNC_POST_META, true );

			$wclsi_id = WCLSI_Lightspeed_Prod::insert_ls_api_item( $ls_prod );

			$item = new WCLSI_Lightspeed_Prod( $wclsi_id );

			if( $item->is_matrix_product() ) {
				update_post_meta( $wc_prod_id, WCLSI_MATRIX_ID_POST_META, $item->item_matrix_id );
			} elseif ( $item->is_simple_product() ) {
				update_post_meta( $wc_prod_id, WCLSI_SINGLE_ITEM_ID_POST_META, $item->item_id );
			} elseif ( $item->is_variation_product() ) {
				update_post_meta( $wc_prod_id, WCLSI_SINGLE_ITEM_ID_POST_META, $item->item_id );
			}
		}

		/**
		 * Uploads the associated image, if one exists
		 *
		 * @param $ls_prod_id
		 * @param $wc_img_prod_id
		 * @param $matrix_id
		 *
		 * @return boolean
		 */
		private function upload_img( $ls_prod_id, $wc_img_prod_id, $matrix_id = 0 ) {
			global $WCLSI_API;

			if ( ! function_exists( 'curl_init' ) || ! function_exists( 'curl_exec' ) || ! class_exists( 'CURLFile' ) ) {
				return false;
			}

			$add_headers = function ( WP_MOScURL &$curl, &$body ) use ( $wc_img_prod_id, $matrix_id ) {
				$headers = array(
					'accept'    => 'application/json',
					'wc-img-id' => $wc_img_prod_id
				);

				if ( $matrix_id > 0 ) {
					$headers['matrix-id'] = $matrix_id;
				}

				$curl->setHTTPHeader( $headers );
			};

			$item_type = $matrix_id > 0 ? "ItemMatrix" : "Item";
			$img_item_path = "Account/{$WCLSI_API->ls_account_id}/{$item_type}/{$ls_prod_id}/Image";
			
			return $WCLSI_API->make_api_call($img_item_path, 'Create', null, null, null, $add_headers);
		}

		/**
		 * Hack that circumvents body and Content-Length manipulation by WordPress and
		 * allows cURL to build a multi-part using a CURLFile instead of breaking down the body
		 * using http_build_query().
		 *
		 * This is specific to PUT/POST requests.
		 *
		 * @see https://github.com/WordPress/WordPress/blob/master/wp-includes/class-http.php#L327-L340
		 * @see wp-includes/class-http.php
		 *
		 * @param $curl_handle
		 * @param $r
		 * @param $url
		 */
		function fix_curl_opts_for_img_upload( $curl_handle, $r, $url ) {

			if ( isset( $r['headers']['wc-img-id'] ) ) {

				$wc_img_prod_id = (int) $r['headers']['wc-img-id'];

				$file_path = get_attached_file( $wc_img_prod_id );

				// Default to thumbnail file path to optimize large image uploads
				$img_meta_data = wp_get_attachment_metadata( $wc_img_prod_id );
				$thumb         = false;
				if ( isset( $img_meta_data['sizes']['shop_single'] ) ) {
					$thumb = $img_meta_data['sizes']['shop_single']['file'];
				} else if ( isset( $img_meta_data['sizes']['shop_catalog'] ) ) {
					$thumb = $img_meta_data['sizes']['shop_catalog']['file'];
				} else if ( isset( $img_meta_data['sizes']['thumbnail'] ) ) {
					$thumb = $img_meta_data['sizes']['thumbnail']['file'];
				}

				$file_path = $thumb ? str_replace( basename( $file_path ), $thumb, $file_path ) : $file_path;

				$img_file = apply_filters( 'wclsi_sync_to_ls_img_path', $file_path );

				if ( false !== $img_file ) {

					$matrix_id = isset( $r['headers']['matrix-id'] ) ? (int) $r['headers']['matrix-id'] : false;
					$data = array('description' => basename( $img_file ));
					if ($matrix_id > 0) {
						$data['itemMatrixID'] = $matrix_id;
					} 

					$body = array(
						"data"  => json_encode($data),
						"image" => new CURLFile( $img_file, mime_content_type( $img_file ), basename( $img_file ) )
					);

					unset( $r['headers']['Content-Length'] );
					unset( $r['headers']['wc-img-id'] );
					unset( $r['headers']['matrix-id'] );

					$headers = array();
					foreach ( $r['headers'] as $name => $value ) {
						$headers[] = "{$name}: $value";
					}

					curl_setopt( $curl_handle, CURLOPT_HTTPHEADER, $headers );
					curl_setopt( $curl_handle, CURLOPT_POSTFIELDS, $body );
				}
			}
		}

		/**
		 * Given a WC prod, returns an object representation of LS prod.
		 *
		 * @param WC_Product $wc_prod
		 * @param int $matrix_id
		 *
		 * @return mixed|void
		 */
		private function ls_prod_json_builder( WC_Product $wc_prod, $matrix_id = 0 ) {

			/** Setup basic fields **/
			$ls_prod              = new stdClass();
			$ls_prod->customSku   = $wc_prod->get_sku();
			$ls_prod->description = $wc_prod->get_title();
			$ls_prod->defaultCost = empty( $wc_prod->get_regular_price() ) ? '0.00' :  $wc_prod->get_regular_price();

			if ( $matrix_id > 0 ) {
				$attributes           = $wc_prod->get_variation_attributes();
				$ls_prod->description = $ls_prod->description . ' ' . implode( ' ', $attributes );
			}

			if ( $matrix_id > 0 ) {
				$ls_prod->itemMatrixID = $matrix_id;
			}

			$wc_cats = wp_get_post_terms( $wc_prod->get_id(), 'product_cat' );
			if ( !empty( $wc_cats ) && !is_wp_error( $wc_cats ) ) {
				global $wpdb, $WCLSI_ITEM_CATEGORIES_TABLE;

				// Start from the end and work our way to the front to find the lowest leaf
				// of the cat hierarchy that is linked to Lightspeed
				$index = count( $wc_cats );
				while( $index ) {
					$wc_cat = $wc_cats[ --$index ];
					$ls_cat_id = $wpdb->get_var( "SELECT category_id FROM $WCLSI_ITEM_CATEGORIES_TABLE WHERE wc_cat_id = {$wc_cat->term_id}" );
					if ( !is_null( $ls_cat_id ) ) {
						$ls_prod->categoryID = $ls_cat_id;
						break;
					}
				}
			}

			$this->build_item_e_commerce( 
				$ls_prod, 
				array(
					'long_description' => $wc_prod->get_title(),
					'short_description' => $wc_prod->get_short_description(),
					'weight' => $wc_prod->get_weight(),
					'width' => $wc_prod->get_width(),
					'height' => $wc_prod->get_height(),
					'length' => $wc_prod->get_length()
				) 
			);

			$this->build_pricing( 
				$ls_prod, 
				array( 
					'regular_price' => $wc_prod->get_regular_price(), 
					'sale_price' => $wc_prod->get_sale_price() 
				)
			);

			if ( $wc_prod->is_type( 'simple' ) ) {
				$this->build_shop_data( $ls_prod, $wc_prod );

				return apply_filters( 'wclsi_sync_to_ls_simple_prod', $ls_prod, $wc_prod );
			} else if ( $wc_prod->is_type( 'variable' ) ) {
				return apply_filters( 'wclsi_sync_to_ls_matrix_prod', $ls_prod, $wc_prod );
			} else if ( $wc_prod->is_type( 'variation' ) ) {
				$this->build_shop_data( $ls_prod, $wc_prod );

				return apply_filters( 'wclsi_sync_to_ls_variation_prod', $ls_prod, $wc_prod );
			} else {
				return false;
			}
		}

		/**
		 * Helper function to build shop data for inventory
		 *
		 * @param $ls_prod
		 * @param WC_Product $wc_prod
		 *
		 * @return bool
		 */
		private function build_shop_data( &$ls_prod, WC_Product &$wc_prod ) {
			$shop_data = get_option( 'wclsi_shop_data' );
			if ( isset( $shop_data['ls_store_data'] ) ) {
				$shop_data = $shop_data['ls_store_data'];
			} else {
				$shop_data = false;
			}

			$inventory = $wc_prod->get_stock_quantity();

			$ItemShops = array();
			if ( false !== $shop_data && isset( $shop_data->Shop ) && is_array( $shop_data->Shop ) ) {
				foreach ( $shop_data->Shop as $shop ) {
					$ItemShop                   = new stdClass();
					$ItemShop->ItemShop         = new stdClass();
					$ItemShop->ItemShop->shopID = $shop->shopID;
					$ItemShop->ItemShop->qoh    = empty( $inventory ) ? 0 : $inventory;
					$ItemShops[]                = $ItemShop;
				}
			} else if ( false !== $shop_data && isset( $shop_data->Shop ) && is_object( $shop_data->Shop ) ) {
				$ItemShop                   = new stdClass();
				$ItemShop->ItemShop         = new stdClass();
				$ItemShop->ItemShop->shopID = $shop_data->Shop->shopID;
				$ItemShop->ItemShop->qoh    = empty( $inventory ) ? 0 : $inventory;
				$ItemShops[]                = $ItemShop;
			} else {
				return false;
			}
			$ls_prod->ItemShops = $ItemShops;

			return true;
		}

		/**
		 * Helper function to build meta data to push a LS product
		 *
		 * @param $ls_prod
		 * @param $update_snapshot
		 */
		private function build_item_e_commerce( &$ls_prod, $update_snapshot ) {
			$itemECommerce = new stdClass();
			$itemECommerce->longDescription = $update_snapshot['long_description'] ?: '';
			$itemECommerce->shortDescription = $update_snapshot['short_description'] ?: '';
			$itemECommerce->weight = $update_snapshot['weight'] ?: 0;
			$itemECommerce->width  = $update_snapshot['width']  ?: 0;
			$itemECommerce->height = $update_snapshot['height'] ?: 0;
			$itemECommerce->length = $update_snapshot['length'] ?: 0;
			$ls_prod->ItemECommerce = $itemECommerce;
		}

		/**
		 * Helper function to build pricing modules to push a LS product
		 *
		 * @param $ls_prod
		 * @param $update_snapshot
		 * @param $set_sale_price
		 */
		private function build_pricing( &$ls_prod, $update_snapshot, $set_sale_price = true ) {
			$ls_prod->Prices = array();

			$regular_price = empty( $update_snapshot['regular_price'] ) ? '0.00' : $update_snapshot['regular_price'];
			$ItemPriceDefault = new stdClass();
			$ItemPriceDefault->ItemPrice = new stdClass();
			$ItemPriceDefault->ItemPrice->useType = 'default';
			$ItemPriceDefault->ItemPrice->amount = $regular_price;

			$ItemPriceMSRP = new stdClass();
			$ItemPriceMSRP->ItemPrice = new stdClass();
			$ItemPriceMSRP->ItemPrice->useType = 'MSRP';
			$ItemPriceMSRP->ItemPrice->amount = $regular_price;

			$ls_prod->Prices[] = $ItemPriceDefault;
			$ls_prod->Prices[] = $ItemPriceMSRP;

			// Not all stores may have a "Sale" price level set
			if ( $set_sale_price ) {
				$ItemPriceSale = new stdClass();
				$ItemPriceSale->ItemPrice = new stdClass();

				/**
				 * Let's set the Sale Price in Lightspeed back to the "default" price (which in Woo is "regular")
				 * if and only if the sale price in Woo is a falsey value
				 */
				if ( $update_snapshot['sale_price'] === '' ) {
					$ItemPriceSale->ItemPrice->amount = $regular_price;
				} else {
					$ItemPriceSale->ItemPrice->amount = $update_snapshot['sale_price'];
				}

				$ItemPriceSale->ItemPrice->useType = 'Sale';
				$ls_prod->Prices[] = $ItemPriceSale;
			}
		}
	}

	global $WCLSI_SYNCER;
	$WCLSI_SYNCER = new WCLSI_Synchronizer();
endif;
