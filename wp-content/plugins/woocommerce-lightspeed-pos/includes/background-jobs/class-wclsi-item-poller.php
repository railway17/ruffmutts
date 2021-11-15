<?php
if ( ! class_exists( 'WCLSI_Item_Poller' ) ) :
	class WCLSI_Item_Poller {

		// Poll every 5 seconds
		const POLLING_INTERVAL = 5;
		const LS_ITEM_TYPES = ['ItemMatrix', 'Item'];
		const UPDATE_TYPES = array(
			'timeStamp' => 'product updates',
			'ItemShops.timeStamp' => 'inventory updates',
			'Images.timeStamp' => 'image updates'
		);

		public static function init() {
			add_action( 'init', array( get_called_class(), 'schedule_wclsi_poller' ), 10, null, null );
			add_action( 'wclsi_poll', array( get_called_class(), 'poll'), 10 );
		}

		static function schedule_wclsi_poller() {
			global $WCLSI_API;

			if( 'true' !== $WCLSI_API->settings[ WCLSI_POLLER_SETTING ] ) {
				if (false !== as_next_scheduled_action( 'wclsi_poll' )) {
					as_unschedule_all_actions( 'wclsi_poll' );
				}
				return;
			}
			
			if ( false === as_next_scheduled_action( 'wclsi_poll' ) ) {
				as_schedule_recurring_action( strtotime( '+5 second' ), 5, 'wclsi_poll', array(), 'wclsi' );
			}
		}
		
		static function poll( LSI_Init_Settings $ls_api = null, LSI_Import_Products $updater = null ) {
			$now = strtotime('now');
			
			if ( wclsi_skip_job() ) { return; }

			global $WCLSI_API, $WCLSI_PRODS;
			if ( is_null( $updater ) ) { $updater = $WCLSI_PRODS; }
			if ( is_null( $ls_api  ) ) { $ls_api  = $WCLSI_API;   }

			$last_check = strtotime( get_option( WCLSI_LAST_SYNC_TIMESTAMP ) );

			if ( false == $last_check || self::exceeds_max_window( $last_check ) ) {
				$since = strtotime( '-' . self::POLLING_INTERVAL . ' seconds' );
			} else {
				$since = $last_check;
			}

			$skip_run = $now < ($since + 5);

			// Don't poll if we've run it in the last 5 seconds
			if ( !$skip_run ) {

				// Limit poller to once per request
				if( !wp_cache_get(WCLSI_ITEM_POLL) ) {
					wp_cache_add(WCLSI_ITEM_POLL, uniqid());
				} else {
					return;
				}

				if ( empty( get_transient('pull_item_attribute_sets') ) ) {
					$updater->pull_item_attribute_sets();
					set_transient('pull_item_attribute_sets', true, 30);
				}

				$items_to_update = array();

				foreach( self::LS_ITEM_TYPES as $LS_ITEM_TYPE ) {
					foreach( self::UPDATE_TYPES as $TIMESTAMP_FIELD => $UPDATE_TYPE) {

						// Skip inventory updates for matrix products in LS
						if ( $LS_ITEM_TYPE == 'ItemMatrix' && $TIMESTAMP_FIELD == 'ItemShops.timeStamp') { continue; }

						$items_to_update =
							array_merge(
								$items_to_update,
								self::poll_api( $since, $LS_ITEM_TYPE, $TIMESTAMP_FIELD, $ls_api)
							);
					}
				}

				if ( !empty( $items_to_update ) ) {
					foreach( $items_to_update as $ls_api_item ) {
						self::update_item( $ls_api_item, $updater );
					}
				}

				update_option( WCLSI_LAST_SYNC_TIMESTAMP, date( DATE_ATOM, strtotime( 'now' ) ) );
			}
		}

		static function poll_api( $since, $ls_item_type, $timestamp_field, LSI_Init_Settings $ls_api = null ) {
			global $WCLSI_SINGLE_LOAD_RELATIONS, $WCLSI_MATRIX_LOAD_RELATIONS;

			$poll_matrix_items = $ls_item_type == 'ItemMatrix';

			$relations = $poll_matrix_items ? $WCLSI_MATRIX_LOAD_RELATIONS : $WCLSI_SINGLE_LOAD_RELATIONS;

			// Convert to string, go back 30 seconds in case we missed some updates
			$since = date( DATE_ATOM, $since - 30 );

			$search_params = array(
				'load_relations' => json_encode( $relations ),
				$timestamp_field => ">,$since"
			);

			$poll_result = $ls_api->make_api_call(
				"Account/$ls_api->ls_account_id/$ls_item_type",
				'Read',
				$search_params
			);

			if ( is_wp_error( $poll_result ) ) {
				self::log_failed_poll( $poll_result, $since );

				// Force poll result to be an empty array in the case of an error
				$poll_result = array();
			} else {
				if ( isset( $poll_result->{$ls_item_type} ) ) {
					if ( is_object( $poll_result->{$ls_item_type} ) ) {
						$poll_result = array( $poll_result->{$ls_item_type} );
					} else if ( is_array( $poll_result->{$ls_item_type} ) ) {
						$poll_result = $poll_result->{$ls_item_type};
					}
				} else {
					$poll_result = array();
				}

				self::log_successful_poll( $poll_result, $since, $ls_item_type, $timestamp_field );
			}

			return $poll_result;
		}

		private static function update_item( $api_item, LSI_Import_Products $updater = null ) {
			$item_id = property_exists( $api_item, 'itemID' ) ? $api_item->itemID : null;
			$item_mysql_id = WCLSI_Lightspeed_Prod::get_mysql_id( $item_id, $api_item->itemMatrixID );

			if ( !empty( $item_mysql_id ) ) {
				$wclsi_prod = new WCLSI_Lightspeed_Prod( $item_mysql_id );
				WCLSI_Lightspeed_Prod::update_via_api_item( $api_item, $wclsi_prod );

				if ( $wclsi_prod->wc_prod_id > 0 ) {
					$wclsi_sync = (bool) get_post_meta( $wclsi_prod->wc_prod_id, WCLSI_SYNC_POST_META, true );

					if ( $wclsi_sync ) {
						$wclsi_prod->reload();

						/**
						 * No need to update matrix variations as it should be covered
						 * by the single item poll function
						 */
						wp_cache_add( 'wclsi_poller_update', true );
						$updater->update_wc_prod( $wclsi_prod, false );
					}
				}
			}
		}

		private static function log_successful_poll( $poll_result, $since, $ls_item_type, $timestamp_field ) {
			global $WCLSI_WC_Logger;

			$poll_matrix_items = $ls_item_type == 'ItemMatrix';

			$update_type = self::UPDATE_TYPES[ $timestamp_field ];

			$update_description = "Syncing $ls_item_type $update_type for: " . PHP_EOL;

			if ( count( $poll_result ) > 0 ) {
				foreach( $poll_result as $item ) {
					if ( $poll_matrix_items ) {
						$update_description .=
							'Matrix item id: ' . $item->itemMatrixID .
							', description: ' . $item->description . PHP_EOL;
					} else {
						$update_description .=
							'Item id: ' . $item->itemID .
							', description: ' . $item->description . PHP_EOL;
					}
				}

				$WCLSI_WC_Logger->add(
					WCLSI_LOG,
					PHP_EOL .
					PHP_EOL .
					'---- Running LS auto-poll ----' . PHP_EOL .
					'---- Looking up changes since: ' . $since . ' ----' . PHP_EOL .
					PHP_EOL .
					$update_description .
					PHP_EOL .
					PHP_EOL
				);
			}
		}

		private static function log_failed_poll( $poll_result, $since ) {
			global $WCLSI_WC_Logger;
			$WCLSI_WC_Logger->add(
				WCLSI_ERROR_LOG,
				'---- Automated sync failed:' . PHP_EOL .
				'---- since timeStamp:' . $since . ' ----' . PHP_EOL .
				print_r( $poll_result, true ) . PHP_EOL .
				wclsi_get_stack_trace() . PHP_EOL
			);
		}

		private static function exceeds_max_window( $last_check ) {
			// max window of 24hrs
			$max_window = date( DATE_ATOM, strtotime('now') - 864000 );
			return $last_check < $max_window;
		}
	}
endif;

WCLSI_Item_Poller::init();
