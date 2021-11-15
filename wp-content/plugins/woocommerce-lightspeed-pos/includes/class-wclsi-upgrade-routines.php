<?php
if ( !class_exists( 'WC_LS_Upgrade_Routines' ) ):

	class WC_LS_Upgrade_Routines{

		public static function perform_upgrades (){
			global $wpdb;
			$wclsi_version = $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name='wclsi_version'" );
			if ( WCLSI_VERSION != $wclsi_version ) {
				self::run_1_1_3_routine();

				// Need to use add_action here since we were getting 'invalid_taxonomy' errors
				add_action( 'init', array( 'WC_LS_Upgrade_Routines', 'run_1_3_1_routine' ) );

				self::init_1_4_3_routine();
				self::run_1_6_1_routine( $wclsi_version );
				self::run_1_7_7_routine( $wclsi_version );
				self::run_1_8_1_routine( $wclsi_version );
				self::run_1_8_2_routine( $wclsi_version );
				self::run_1_8_3_routine( $wclsi_version );
				self::run_1_9_0_routine( $wclsi_version );
				self::run_1_9_1_routine( $wclsi_version );

				update_option( 'wclsi_version', WCLSI_VERSION, false );
			}
		}

		public static function run_1_9_1_routine( $current_version ) {
			if ( version_compare( $current_version, '1.9.1' ) >= 0 ) {
				return;
			}

			global $WCLSI_API; 
			$selective_sync = $WCLSI_API->settings[ WCLSI_LS_SELECTIVE_SYNC ];
			if ( !is_null( $selective_sync ) ) {
				if ( 'true' === $selective_sync[ 'stock_quantity_checkout' ] && !key_exists( 'stock_quantity', $selective_sync) ) {
					$WCLSI_API->settings[ WCLSI_LS_SELECTIVE_SYNC ][ 'stock_quantity' ] = 'true';
					unset( $WCLSI_API->settings[ WCLSI_LS_SELECTIVE_SYNC ][ 'stock_quantity_checkout' ] );
					update_option( $WCLSI_API->get_settings_option_key(), $WCLSI_API->settings ); 
				}
			}
		}
		
		public static function run_1_9_0_routine( $current_version ) {
			if ( version_compare( $current_version, '1.9.0' ) >= 0 ) {
				return;
			}

			/**
			 * In version 1.8.1 - we used the wrong option name, let's run this again with the right option name
			 */
			delete_option( 'wclsi_update_cancelled_order' );
		}

		public static function run_1_8_3_routine( $current_version ) {
			if ( version_compare( $current_version, '1.8.3' ) >= 0 ) {
				return;
			}

			/**
			 * Clear out bad item_e_commerce (webstore) data that does not have an item_e_commerce_id
			 * This will effectively "reset" the webstore data for products and allow them to properly 
			 * be inserted into the DB
			 */
			global $wpdb, $WCLSI_ITEM_E_COMMERCE_TABLE;
			$wpdb->query( "DELETE FROM $WCLSI_ITEM_E_COMMERCE_TABLE WHERE item_e_commerce_id IS NULL" );
		}

		public static function run_1_8_2_routine( $current_version ) {
			if ( version_compare( $current_version, '1.8.2' ) >= 0 ) {
				return;
			}

			update_option( WCLSI_LAST_LOAD_TIMESTAMP, date( DATE_ATOM, strtotime( 'now' ) ) );
		}
		
		public static function run_1_8_1_routine( $current_version ) {
			if ( version_compare( $current_version, '1.8.1' ) >= 0 ) {
				return;
			}

			/**
			 * In version 1.7.9 - this option was misplaced and could result in a complete
			 * halt in calls to update_ls_prod() - let's delete this to be safe
			 */
			delete_option( 'wclsi_order_cancelled' );
		}
		
		public static function run_1_7_7_routine( $current_version ) {
			if ( version_compare( $current_version, '1.7.7' ) >= 0 ) { 
				return; 
			}

			global $WCLSI_API;
			$WCLSI_API->settings[ WCLSI_WC_SELECTIVE_SYNC ][ 'attributes' ] = true;
			update_option( $WCLSI_API->get_settings_option_key(), $WCLSI_API->settings );
		}

		public static function run_1_6_1_routine( $current_version ){
			if ( version_compare( $current_version, '1.6.1' ) >= 0 ) { 
				return; 
			}

			// Reset the lock so it follows the 30s max rule
			delete_transient('wclsi_api_call_lock');
		}

		public static function init_1_4_3_routine(){
			add_action( 'admin_notices', array( 'WC_LS_Upgrade_Routines', 'init_1_4_3_notices' ) );
		}

		public static function init_1_4_3_notices(){
			$current_version = get_option( 'wclsi_version' );
			$current_version = empty( $current_version ) ? WCLSI_VERSION : $current_version;
			if ( ( version_compare( $current_version, '1.4.3' ) >= 0 ) || get_option( 'wclsi_upgraded_1_4_3' ) ) {
				return;
			}

			$screen = get_current_screen();
			if ( $screen->id == WCLSI_SCREEN_ID ) {
				$class = 'notice notice-warning';
				$welcome_msg = __( 'Your WooCommerce Lightspeed POS plugin requires an upgrade!', 'woocommerce-lightspeed-pos' );
				$info_msg = __( 'This version requires a database change, to get started click the button below.', 'woocommerce-lightspeed-pos' );
				$warning = __( 'WARNING!', 'woocommerce-lightspeed-pos' );
				$breaking_changes = __( 'This new version may introduce breaking changes to custom code and integrations. Please make sure to read the documentation and make a backup of your site before attempting this upgrade.', 'woocommerce-lightspeed-pos' );
				$upgrade_msg = __( 'Click here to upgrade', 'woocommerce-lightspeed-pos' );

				printf(
					'<div class="%1$s">' .
						'<p><b>%2$s</b></p>' .
						'<p>%3$s</p>' .
						'<p style="color: red;"><b>%4$s</b></p>' .
						'<p style="color: red;">%5$s</p>' .
						'<p><button class="button-secondary" type="button" id="wclsi-1-4-3-upgrade">%6$s</button></p>' .
					'</div>',
					$class,
					$welcome_msg,
					$info_msg,
					$warning,
					$breaking_changes,
					$upgrade_msg
				);
			} else {
				$class = 'notice notice-warning';
				$message = __( 'WooCommerce Lightspeed POS requires an upgrade!', 'woocommerce-lightspeed-pos' );
				$get_started_msg = __( 'Click here to get started.', 'woocommerce-lightspeed-pos');

				printf( '<div class="%1$s"><p>%2$s <a href="%3$s">%4$s</a></p></div>', $class, $message, WCLSI_ADMIN_URL, $get_started_msg );
			}

			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
			wp_enqueue_script( 'wclsi_upgrade_to_1_4_3', plugins_url( 'db/scripts/2017_01_01_upgrade_to_1_4_3' . $suffix . '.js', __FILE__ ) );

			global $WCLSI_objectL10n;
			wp_localize_script( 'wclsi_upgrade_to_1_4_3', 'objectL10n', $WCLSI_objectL10n );
			wp_localize_script( 'wclsi_upgrade_to_1_4_3', 'wclsi_options', array(
				'wclsi_import_page' => WCLSI_ADMIN_URL
			) );
		}

		public static function run_1_3_1_routine(){

			if ( get_option( 'wclsi_upgraded_1_3_1' ) || version_compare( '1.3.1', WCLSI_VERSION ) <= 0 ) {
				return;
			}

			$old_cat_cache = get_option( 'wclsi_cat_cache' );

			if( false !== $old_cat_cache ) {

				$new_cat_cache = new stdClass();
				$new_cat_cache->categories = array();

				if( is_array( $old_cat_cache->cats ) && !empty( $old_cat_cache->cats ) ) {
					foreach ($old_cat_cache->cats as $cat_item ) {
						$new_cat_cache->categories[ $cat_item->categoryID ] = $cat_item;
						if( isset( $cat_item->wc_cat_id ) && isset( $cat_item->wc_cat_id['term_id'] ) ){
							$term_id = (int) $cat_item->wc_cat_id['term_id'];

							// add new meta to keep track of Lightspeed category ids
							add_term_meta( $term_id, '_wclsi_ls_cat_id', $cat_item->categoryID, true);
						}
					}
				}

				update_option( WCLSI_CAT_CHUNK_PREFIX . '0', $new_cat_cache );
				update_option( WCLSI_TOTAL_CAT_CHUNKS, 1 );
				delete_option( 'wclsi_cat_cache' );
			}
			update_option( 'wclsi_upgraded_1_3_1', true );
		}

		/**
		 * Upgrade routine for v1.1.3+: Index all the existing prod chunks
		 * Upgrade flag is "wclsi_upgraded_1_1_3"
		 */
		public static function run_1_1_3_routine(){
			if ( get_option( 'wclsi_upgraded_1_1_3' ) || version_compare( '1.3.1', WCLSI_VERSION ) <= 0 ) {
				return;
			}

			$total_prod_chunks = get_option( 'wclsi_total_chunks' );
			if ( false !== $total_prod_chunks ) {
				for ( $chunk_id = 0; $chunk_id < $total_prod_chunks; $chunk_id++ ) {
					$wclsi_prod_chunk = get_option( 'wclsi_prod_chunk_' . $chunk_id );
					if ( false !== $wclsi_prod_chunk ) {
						$indexed_prod_chunk = array();
						if ( isset( $wclsi_prod_chunk->Item ) && is_array( $wclsi_prod_chunk->Item ) ) {
							foreach ( $wclsi_prod_chunk->Item as $prod ) {
								// index wclsi_prod_chunk by itemID for faster lookup
								$indexed_prod_chunk[ $prod->itemID ] = $prod;
							}
						} else if ( isset( $wclsi_prod_chunk->Item ) && is_object( $wclsi_prod_chunk->Item ) ) {
							$indexed_prod_chunk[ $wclsi_prod_chunk->Item->itemID ] = $wclsi_prod_chunk->Item;
						}

						$wclsi_prod_chunk->Item = $indexed_prod_chunk;
						update_option( 'wclsi_prod_chunk_' . $chunk_id, $wclsi_prod_chunk );
					}
				}
			}
			update_option( 'wclsi_upgraded_1_1_3', true );
		}
	}

endif;
