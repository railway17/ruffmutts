<?php
if ( ! class_exists('WCLSI_Check_For_New_Prods') ) :

	class WCLSI_Check_For_New_Prods {
		function __construct() {
			add_action( 'init', array( $this, 'schedule_check_for_new_ls_prods') );
			add_action( 'wclsi_import_on_auto_load', array($this, 'wclsi_import_on_auto_load'), 10, 1);
			add_action( 'check_for_new_ls_prods', array( $this, 'check_for_new_ls_prods'), 10 );
			add_action( 'admin_notices', array( $this, 'render_new_ls_prods_notification') );
		}

		/**
		 * Adds the "check_for_new_ls_prods" job to Action Scheduler
		 */
		function schedule_check_for_new_ls_prods() {
			global $WCLSI_API;

			if( 'true' !== $WCLSI_API->settings[ WCLSI_LS_TO_WC_AUTOLOAD ] ) {
				if (false !== as_next_scheduled_action( 'check_for_new_ls_prods' )) {
					as_unschedule_all_actions( 'check_for_new_ls_prods' );
				}
				return;
			}

			if ( false === as_next_scheduled_action( 'check_for_new_ls_prods' ) ) {
				as_schedule_recurring_action( 
					strtotime( '+30 second' ), 
					30, 
					'check_for_new_ls_prods',
					array(),
					'wclsi'
				);
			}
		}

		/**
		 * Checks for new LightSpeed products since last import
		 */
		function check_for_new_ls_prods() {
			if ( wclsi_skip_job() )
				return;
	
			global $WCLSI_API, $WCLSI_SINGLE_LOAD_RELATIONS;
	
			$last_load_timestamp = get_option( WCLSI_LAST_LOAD_TIMESTAMP );
	
			if ( !empty( $last_load_timestamp ) ) {
				// Go back 60 second in case we missed something
				$since = date( DATE_ATOM, strtotime( $last_load_timestamp ) - 60 );
				$search_params = array(
					'load_relations' => json_encode( $WCLSI_SINGLE_LOAD_RELATIONS ),
					'createTime' => '>,' . $since
				);
	
				$response = $WCLSI_API->make_api_call(
					"Account/$WCLSI_API->ls_account_id/Item/",
					'Read',
					$search_params
				);
	
				if ( !is_wp_error( $response ) && $response->{'@attributes'}->count > 0 ) {
					if ( is_object( $response->Item ) ) {
						$response->Item = array( $response->Item );
					}
	
					$ls_api_items = $response->Item;
					$prod_list = '';
					$wclsi_ids_for_import = [];
	
					wp_cache_add( 'wclsi_adding_new_products', true );
	
					foreach( $ls_api_items as $ls_api_item ) {
	
						// Skip over already inserted items - this could be because of the overlap
						// of going back 60 seconds with the createTime timestamp
						if ( WCLSI_Lightspeed_Prod::item_exists( $ls_api_item ) )
							continue;
	
						$result = self::add_ls_api_item( $ls_api_item );
						$wclsi_item = new WCLSI_Lightspeed_Prod( $result[ 'wclsi_item_id' ] );
						$sku = wclsi_get_ls_sku( $wclsi_item );
						$prod_list .= "<li>Description: \"$wclsi_item->description\" SKU: \"$sku\"</li>";
	
						// Accumulate IDs for possible import
						$wclsi_ids_for_import[] = $result[ 'wclsi_item_id' ];
						$wclsi_ids_for_import[] = $result[ 'wclsi_matrix_id' ];
					}
	
					// If the import_on_auto_load settings is enabled, schedule the imports
					$import_autoload_option = $WCLSI_API->settings[ WCLSI_IMPORT_ON_AUTOLOAD ];
					if ( 'import_and_sync' == $import_autoload_option || 'import' == $import_autoload_option ) {
						$wclsi_ids_for_import = array_unique( array_filter( $wclsi_ids_for_import ) );
	
						if ( !empty( $wclsi_ids_for_import ) ) {
							foreach( $wclsi_ids_for_import as $wclsi_id ) {
								$wclsi_item = new WCLSI_Lightspeed_Prod( $wclsi_id );
								self::schedule_import_on_autoload( $wclsi_item );
							}
						}
					}
	
					if ( !empty( $prod_list ) ) {
						self::render_add_new_prod_html( $response->{'@attributes'}->count, $prod_list );
					}
	
					wp_cache_delete( 'wclsi_adding_new_products' );
				}
			}

			// Always update the WCLSI_LAST_LOAD_TIMESTAMP regardless of what we are doing
			update_option( WCLSI_LAST_LOAD_TIMESTAMP, date( DATE_ATOM, strtotime( 'now' ) ) );
		}
	
		/**
		 * @param WCLSI_Lightspeed_Prod $wclsi_item
		 */
		private static function schedule_import_on_autoload( WCLSI_Lightspeed_Prod $wclsi_item ) {
			if( !$wclsi_item->is_variation_product() ) {
				as_schedule_single_action(
					time(),
					'wclsi_import_on_auto_load',
					array( $wclsi_item->id ),
					'wclsi'
				);
			}
		}
	
		/**
		 * @param $wclsi_item_id
		 */
		function wclsi_import_on_auto_load( $wclsi_item_id ) {
			global $WCLSI_API, $WCLSI_PRODS;
	
			$valid_options = array( 'do_nothing', 'import', 'import_and_sync' );
			$option = $WCLSI_API->settings[ 'wclsi_import_on_auto_load' ];
	
			if ( !in_array( $option, $valid_options ) ) { return; }
	
			$wclsi_item = new WCLSI_Lightspeed_Prod( $wclsi_item_id );
			if ( $wclsi_item->id > 0 ) {
	
				if ( 'import_and_sync' === $option ) {
					$sync = true;
				} elseif ( 'import' === $option ) {
					$sync = false;
				}
	
				$WCLSI_PRODS->import_item( $wclsi_item, $sync );
			}
		}
	
		/**
		 * @param $prod_count
		 * @param $prod_list
		 */
		private static function render_add_new_prod_html( $prod_count, $prod_list ) {
			$matrix_note =
				__(
					'<p>Note: variation products will be consolidated into their parent matrix/variable product.</p>',
					'woocommerce-lightspeed-pos'
				);
	
			$notification_msg =
				__(
					'Good news! %d new Lightspeed item(s) have been added to the Lightspeed import table:<br/>%s%s',
					'woocommerce-lightspeed-pos'
				);
	
			$wclsi_notifications = get_option( WCLSI_NOTIFICATIONS, array() );
			$wclsi_notifications[ 'wclsi_new_items' ] =
				array(
					'msg' => sprintf(
						$notification_msg,
						$prod_count,
						"<ul style='margin-left:30px;list-style:square'>$prod_list</ul>",
						$matrix_note
					),
					'type' => 'success'
				);
	
			update_option( WCLSI_NOTIFICATIONS, $wclsi_notifications );
		}
	
		function render_new_ls_prods_notification() {
			if( is_admin() ) {
				$wclsi_notifications = get_option( WCLSI_NOTIFICATIONS, array() );
	
				$notification = null;
				if ( array_key_exists( 'wclsi_new_items', $wclsi_notifications ) ) {
					$notification = $wclsi_notifications[ 'wclsi_new_items' ];
				}
	
				if ( !empty( $notification ) ) {
					$class = "notice notice-{$notification[ 'type' ]} is-dismissible";
					printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $notification[ 'msg' ] );
	
					unset( $wclsi_notifications[ 'wclsi_new_items' ] );
					update_option( WCLSI_NOTIFICATIONS, $wclsi_notifications );
				}
			}
		}
	
		/**
		 * Inserts newly-added Lightspeed products
		 */
		static function add_ls_api_item($ls_api_item ) {
			if ( empty( $ls_api_item ) ) { return 0; }
	
			global $WCLSI_API;
			$wclsi_matrix_id = null;
	
			// If the product is a variation and its parent does not exist, insert its parent as well
			if ( (int) $ls_api_item->itemMatrixID > 0 &&
				empty( WCLSI_Lightspeed_Prod::get_mysql_id( null, $ls_api_item->itemMatrixID ) ) ) {
				global $WCLSI_MATRIX_LOAD_RELATIONS;
	
				sleep(1);
	
				$wclsi_matrix_api_item = $WCLSI_API->make_api_call(
					"Account/$WCLSI_API->ls_account_id/ItemMatrix/$ls_api_item->itemMatrixID",
					"Read",
					array( 'load_relations' => json_encode( $WCLSI_MATRIX_LOAD_RELATIONS ) )
				);
	
				if ( isset($wclsi_matrix_api_item->ItemMatrix) ) {
					$wclsi_matrix_id = WCLSI_Lightspeed_Prod::insert_ls_api_item(  $wclsi_matrix_api_item->ItemMatrix );
				}
			}
	
			// Check if item already exists			
			$wclsi_id = WCLSI_Lightspeed_Prod::get_mysql_id( $ls_api_item->itemID, $ls_api_item->itemMatrixID );
			if ( empty( $wclsi_id ) ) {
				$wclsi_id = WCLSI_Lightspeed_Prod::insert_ls_api_item( $ls_api_item );
			}
	
			if (  $ls_api_item->itemMatrixID > 0 ) {
				$wclsi_parent_prod = new WCLSI_Lightspeed_Prod();
				$wclsi_parent_prod->init_via_item_matrix_id( $ls_api_item->itemMatrixID );
	
				// If the parent has been imported, then import the variation as well
				if ( $wclsi_parent_prod->wc_prod_id > 0 ) {
					global $WCLSI_PRODS;
					$parent_wc_prod = wc_get_product( $wclsi_parent_prod->wc_prod_id );
					$wclsi_prod = new WCLSI_Lightspeed_Prod( $wclsi_id );
					if ( !empty( $parent_wc_prod ) ) {
						$WCLSI_PRODS->update_matrix_variations(array( $wclsi_prod ), $parent_wc_prod );
					}
				}
			}
	
			return array( 'wclsi_item_id' => $wclsi_id, 'wclsi_matrix_id' => $wclsi_matrix_id );
		}
	}

	global $WCLSI_Check_For_New_Prods;
	$WCLSI_Check_For_New_Prods = new WCLSI_Check_For_New_Prods();
endif;
