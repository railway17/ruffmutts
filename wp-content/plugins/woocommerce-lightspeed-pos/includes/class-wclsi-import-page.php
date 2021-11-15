<?php
/**
 * @class LSI_Import_page
 * Displays to the user products and other settings that can be imported from LightSpeed as well as handles
 * a lot of the logic for importing products and other data from LightSpeed.
 *
 * @url http://cloud-docs.merchantos.com/API/APIHelp.help
 *
 *
 */
if ( !class_exists( 'LSI_Import_Page' ) ) :

	class LSI_Import_Page {

		public $wclsi_import_table;

		function __construct() {
			global $WCLSI_API;

			add_action( 'admin_menu', array( $this, 'add_import_page_submenu_item' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_resources') );
			add_filter( 'set-screen-option', array( $this, 'set_table_options' ), 10, 3 );
			add_action( 'add_meta_boxes_product', array( $this, 'add_wclsi_meta_box' ), 10 );
			add_action( 'wp_ajax_relink_wc_prod_ajax', array( $this, 'relink_wc_prod_ajax' ) );

			$this->ls_settings = $WCLSI_API;
			$this->table_disabled =
				!wclsi_oauth_enabled() ||
					( false === $this->ls_settings->ls_account_id ) ||
						!isset( $this->ls_settings->settings[ WCLSI_INVENTORY_SHOP_ID ] ) ||
							!( isset( $this->ls_settings->settings[ WCLSI_INVENTORY_SHOP_ID ] ) &&
								is_numeric( $this->ls_settings->settings[ WCLSI_INVENTORY_SHOP_ID ] ) &&
									( $this->ls_settings->settings[ WCLSI_INVENTORY_SHOP_ID ] >= 0 ) );
		}

		/**
		 * Create a submenu page for the LightSpeed Importer
		 */
		public function add_import_page_submenu_item() {
			$hook = add_submenu_page(
				'woocommerce',
				WCLSI_MENU_NAME, 
				WCLSI_MENU_NAME,
				'edit_products',
				'lightspeed-import-page',
				array( $this, 'lsi_import_page_html' )
			);
			add_action( "load-$hook", array( $this, 'add_screen_options' ) );
			add_action( "load-$hook", array( $this, 'prepare_lightspeed_import_table' ) );
		}

		/**
		 * Prepare the Lightspeed import table, make sure that runs after add_screen_options so screen options
		 * will work correctly!
		 */
		function prepare_lightspeed_import_table() {
			$this->wclsi_import_table = new WC_LS_Import_Table();

			if ( !$this->table_disabled ) {
				$this->wclsi_import_table->prepare_items();
			}

			$this->check_setting_errors();
		}

		/**
		 * Adds a screen options
		 */
		function add_screen_options() {
			$option = 'per_page';
			$args = array(
				'label'   => 'Lightspeed Products',
				'default' => 20,
				'option'  => 'product_per_page'
			);
			add_screen_option( $option, $args );
		}

		/**
		 * Adds meta boxes to products
		 * @param $post
		 */
		public function add_wclsi_meta_box( $post ) {
			$allowed_statuses = array('publish', 'future', 'draft', 'pending', 'private');
			if( isset( $post->post_status ) && in_array( $post->post_status, $allowed_statuses ) ) {
				add_meta_box(
					'wclsi_meta_box',
					__( 'Lightspeed Settings', 'woocommerce-lightspeed-pos' ),
					array($this, 'render_meta_box'),
					$post->post_type,
					'side'
				);
			}
		}

		/**
		 * Renders lightspeed settings in meta box
		 * @param $post
		 */
		public function render_meta_box( $post ) {
			$wc_prod = wc_get_product( $post->ID );
			$ls_obj = new WCLSI_Lightspeed_Prod();
			$ls_obj->init_via_wc_prod_id( $wc_prod->get_id() );
			if ( 0 === $ls_obj->id ) {
				if ( $wc_prod->is_type( 'simple' ) ) {
					$ls_item_id = $wc_prod->get_meta( WCLSI_SINGLE_ITEM_ID_POST_META, true );
					if ( $ls_item_id > 0 ) {
						$ls_obj->init_via_item_id( $ls_item_id );
					}
				} elseif ( $wc_prod->is_type( 'variable' ) ) {
					$ls_item_matrix_id = $wc_prod->get_meta( WCLSI_MATRIX_ID_POST_META, true );
					if ( $ls_item_matrix_id > 0 ) {
						$ls_obj->init_via_item_matrix_id( $ls_item_matrix_id );
					}
				}
			}

			if ( $ls_obj->id > 0 ) {
				$this->render_synced_meta_box( $post, $ls_obj );
			} else {
				$this->render_non_synced_meta_box( $post );
			}

			wp_nonce_field( 'wclsi_ajax', 'wclsi_nonce', false );
		}

		private function render_non_synced_meta_box( $post ){
			$tooltip_img = esc_attr( WC()->plugin_url() . '/assets/images/help.png');
			$tooltip = __( 
				'Create this product in Lightspeed. This does not check whether the product already exists in Lightspeed. ' . 
				'If the product already exists, then this will most likely result in a duplicate.', 
				'woocommerce-lightspeed-pos' 
			);

			echo "<p style='display: inline-block;'>" .
					 "<button class='button-secondary button' type='button' data-prodid='{$post->ID}' id='wclsi-sync-to-ls' style='float: left;'>Create this product in Lightspeed</button>" .
					 "<img class='help_tip tips wclsi-load-prod-tip' data-tip='{$tooltip}' src='{$tooltip_img}' height='16' width='16'>" .
				 "</p>" .
				 "<div id='wclsi-sync-status'></div>";

			$this->render_relink_button( $post );
		}

		private function render_synced_meta_box( $post, $ls_obj ) {
			$is_synced = get_post_meta( $post->ID, WCLSI_SYNC_POST_META, true );
			$checked = $is_synced ? 'checked' : '';

			echo '<p><input id="wclsi_ls_sync" class="wclsi-sync-cb" type="checkbox" data-prodid="' . esc_attr( $post->ID ) . '" ' . $checked . '><label for="wclsi_ls_sync">' . __( 'Sync with Lightspeed?', 'woocommerce-lightspeed-pos' ) . '</label></p>';

			if ( $is_synced ) {
				$last_sync_date = !empty( $ls_obj->wclsi_last_sync_date ) ?  date_i18n( 'Y-m-d H:i', strtotime( $ls_obj->wclsi_last_sync_date ) ) : __( 'Pending...', 'woocommerce-lightspeed-pos' );
				echo sprintf('<p>%s<b>%s</b></p>', __( 'Last Sync Date: ', 'woocommerce-lightspeed-pos' ), $last_sync_date);
			}

			echo '<button class="button-secondary button" type="button" data-prodid="' . esc_attr( $post->ID ) . '" id="wclsi-manual-sync">Manual Update via Lightspeed</button>';
			$this->render_relink_button( $post );
		}

		private function render_relink_button( $post ) {
			$tooltip_msg =  __(
				'Relink this product with Lightspeed. It will attempt to match on the SKU value. ' .
				'Please make sure SKU values are unique to each product!',
				'woocommerce-lightspeedpos'
			);

			$tooltip =
				'<span class="tips" data-tip="' . $tooltip_msg . '" >' .
					'<img class="help_tip" src="' . esc_url(  WC()->plugin_url() . '/assets/images/help.png' ) . '" height="16" width="16">' .
				'</span>';

			echo sprintf(
					'<div id="wclsi-relink-wrapper"><p><a id="wclsi-relink" data-prod-id="%d" href="#">%s</a>%s</p></div>',
					$post->ID, __( 'Relink with Lightspeed', 'woocommerce-lightspeed-pos' ),
					$tooltip
				);
		}

		/**
		 * Sets screen options
		 *
		 * @param $status
		 * @param $option
		 * @param $value
		 *
		 * @return mixed
		 */
		function set_table_options($status, $option, $value) {
			return $value;
		}

		/**
		 * Enqueue necessary css and js files
		 */
		public function enqueue_resources() {
			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
			wp_enqueue_style( 'import-admin-page-css', plugins_url( "/assets/css/import-admin-page$suffix.css", dirname( __FILE__ ) ), array('thickbox'), WCLSI_VERSION );
			wp_enqueue_style( 'woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css', array(), WC_VERSION );
			wp_enqueue_script( 'jquery-tiptip', WC()->plugin_url() . '/assets/js/jquery-tiptip/jquery.tipTip' . $suffix . '.js', array( 'jquery' ), WC_VERSION, true );
		}

		/**
		 * Makes sure that the user has initialized API vars...
		 */
		public function check_setting_errors() {
			// Will only display errors if the plugin has initialized
			$wclsi_initialized = get_option( 'wclsi_initialized' );
			if ( !$wclsi_initialized ) {
				add_settings_error(
					'wclsi_settings',
					'null_api_key',
					sprintf( __( 'Thank you for using WooCommerce Lightspeed POS! Please visit the <a href="%s">integration page</a> and authorize with Lightspeed to start the syncing process :).', 'woocommerce-lightspeed-pos' ), admin_url('admin.php?page=wc-settings&tab=integration&section=lightspeed-integration') ),
					'updated'
				);

				return;
			}

			// Will only display one error at a time
			if ( !wclsi_oauth_enabled() ) {
				$this->wclsi_import_table->items = array(); // Force the table to not display
				add_settings_error(
					'wclsi_settings',
					'wclsi_ouath_not_enabled',
					sprintf( __( 'Please enabled your connection to Lightspeed! Please visit the <a href="%s">integration page</a> and connect to Lightspeed to start the syncing process.', 'woocommerce-lightspeed-pos' ), WCLSI_ADMIN_SETTINGS_URL ),
					'error'
				);
			} elseif ( empty( $this->ls_settings->store_timezone ) ) {
				$this->wclsi_import_table->items = array(); // Force the table to not display
				add_settings_error(
					'wclsi_settings',
					'null_timezone',
					sprintf( __( 'Could not find store timezone. This is required for syncing products. Please visit the <a href="%s">integration page</a> and initialize the API settings.', 'woocommerce-lightspeed-pos' ), WCLSI_ADMIN_SETTINGS_URL ),
					'error'
				);
			} elseif ( !isset( $this->ls_settings->settings[ WCLSI_INVENTORY_SHOP_ID ] ) ||
							!( isset( $this->ls_settings->settings[ WCLSI_INVENTORY_SHOP_ID ] ) &&
								is_numeric( $this->ls_settings->settings[ WCLSI_INVENTORY_SHOP_ID ] ) &&
								   ( $this->ls_settings->settings[ WCLSI_INVENTORY_SHOP_ID ] >= 0 ) ) ){

				$this->wclsi_import_table->items = array(); // Force the table to not display
				add_settings_error(
					'wclsi_settings',
					'null_inventory_shop_id',
					sprintf( __( 'Could not find a primary lightspeed inventory shop id. Please visit the <a href="%s">integration page</a> and select a Primary Lightspeed Inventory Store.', 'woocommerce-lightspeed-pos' ), WCLSI_ADMIN_SETTINGS_URL ),
					'error'
				);
			}
		}

		/**
		 * Render the page HTML
		 */
		public function lsi_import_page_html() {
			if ( isset( $_GET['wc-ls-clear-loaded'] ) ) {
				$this->wclsi_import_table->clear_table_data();
			?>
				<!-- Refresh the page to get rid of "wc-ls-clear-loaded" URL param-->
				<script type="text/javascript">window.location = "<?php echo WCLSI_ADMIN_URL ?>";</script>
			<?php
			}
			?>
			<div class="wrap">
				<h1 id="lightspeed-import-page"><?php echo WCLSI_ADMIN_PAGE_TITLE ?></h1>
				<?php settings_errors( 'wclsi_settings' ); ?>
				<div id="wclsi-load-progress" class="updated">
					<span class="spinner wclsi-spinner" style="float: left; width: inherit; margin: 5px 0 0 0;">&nbsp;</span>
					<p>
						<span id="wclsi-progress-msg"><?php echo __( 'Loading products ... ', 'woocommerce-lightspeed-pos' ) ?></span>
						<b><span id="wclsi-progress-count">0%</span><?php echo __( ' complete', 'woocommerce-lightspeed-pos' ) ?></b>
					</p>
				</div>
				<p>
					<?php echo __( 'Sync Lightspeed and WooCommerce data here!' , 'woocommerce-lightspeed-pos' ); ?> |
					<a href="<?php echo WCLSI_ADMIN_SETTINGS_URL ?>"><?php echo __('Settings', 'woocommerce-lightspeed-pos') ?></a> |
					<a href="https://docs.woothemes.com/document/woocommerce-lightspeed-pos/" target="_blank">Documentation</a> |
					<i>v<?php echo WCLSI_VERSION ?></i>
				</p>
				<?php
					$last_sync = get_option( WCLSI_LAST_SYNC_TIMESTAMP );
					if ( false !== $last_sync ) {
						$last_load_msg = __('Last automated sync run', 'woocommerce-lightspeed-pos');
	
						$timezone = $this->ls_settings->store_timezone;
						$last_load_time = new DateTime( 'now', new DateTimeZone( $timezone ) );
						$last_load_time->setTimestamp( strtotime( $last_sync ) );
						$last_load_formatted = $last_load_time->format( 'F j, H:i' );
	
						$next_sync_time = strtotime( $last_sync ) + (60*5);
						$next_sync_formatted = new DateTime( 'now', new DateTimeZone( $timezone ) );
						$next_sync_formatted->setTimestamp( $next_sync_time );
						$next_sync_formatted = $next_sync_formatted->format( 'F j, H:i' );
	
						echo "<p><i>$last_load_msg: <b>$last_load_formatted - $timezone</b> - " .
							 "next run (approximately): <b>$next_sync_formatted - $timezone</b></i></p>";
					}
	
					if ( $this->wclsi_import_table->has_items() ) {
					   $load_button_txt = __( 'Re-Load products from Lightspeed', 'woocommerce-lightspeed-pos' );
					} else {
					   $load_button_txt = __( 'Load Products from Lightspeed', 'woocommerce-lightspeed-pos' );
					}
					$reload = $this->wclsi_import_table->has_items() ? 'data-reload="true"' : '';
				?>
				<p>
					<input
						alt="#TB_inline?inlineId=wclsi_api_search&height=800&width=1200"
						title="Search Lightspeed API"
						class="thickbox button button-primary help_tip tips"
						data-tip="<?php echo __('Search your Lightspeed Store for specific products.') ?>"
						type="button"
						value="Search Lightspeed API"
						<?php echo $this->table_disabled ? 'disabled' : '' ?>
					/> Or <input 
						id="wc-ls-load-prods" 
						type="button" 
						class="button button-secondary help_tip tips"
						data-tip="<?php echo __('Loads Lightspeed products into a table list. No products are added to your WooCommerce store.') ?>"
						<?php echo $reload ?>
						<?php echo $this->table_disabled ? 'disabled' : '' ?>
						value="<?php echo $load_button_txt; ?>"
					/>
				</p>
				<div id="wclsi_api_search" style="display: none;"><?php WCLSI_Api_Navigator::render_navigator(); ?>></div>
				<form method="post" id="wc-ls-import-form" action="">
					<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>"/>
					<?php wp_nonce_field( 'wclsi_ajax', 'wclsi_nonce', false ); ?>
				</form>
	
				<div id="wc-ls-import-results">
					<?php $this->render_imported_prods() ?>
				</div>
			</div>
		<?php
		}

		/**
		 * Render the imported products from LightSpeed and/or via the cached products
		 */
		function render_imported_prods() {
			if ( !$this->table_disabled ) {
				?>
				<!-- Forms are NOT created automatically, so you need to wrap the table in one to use features like bulk actions -->
				<form id="wc-imported-prods-filter" method="get">
					<!-- For plugins, we also need to ensure that the form posts back to our current page -->
					<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>"/>
					<?php

				if ( $this->wclsi_import_table->has_items() ) {
					?>
					<button type="button" class="button button-secondary" id="wc-import-all-prods"><?php echo  __( 'Import All', 'woocommerce-lightspeed-pos' ) ?></button>
					<?php
						$msg = __( 'Imports all Lightspeed products into WooCommerce. If sync checkbox is checked, 
						products will also be added to the sync schedule after they are imported.',
						'woocommerce-lightspeedpos' );

						echo wclsi_span_tooltip($msg);
					?>
					<label for="wclsi-enable-sync-on-import-all">Sync</label> <input type="checkbox" id="wclsi-enable-sync-on-import-all" />
					<?php
					$this->wclsi_import_table->search_box( 'Search name, price, sku, or category', 'wc_ls_search' );
					$this->wclsi_import_table->display();
					submit_button( __( 'Clear All Loaded Products', 'woocommerce-lightspeed-pos' ), 'secondary', 'wc-ls-clear-loaded', array( 'type' => 'button' ) );
				}
				?>
				</form>
			<?php
			}
		}

		/**
		 * Will attempt to re-link a woocommerce
		 */
		function relink_wc_prod_ajax(){
			wclsi_verify_nonce();

			$prod_id = isset( $_POST['wc_prod_id'] ) ? (int) $_POST['wc_prod_id'] : false;

			if( $prod_id > 0 ) {
				$result = $this->relink_wc_prod( $prod_id );
				if( is_wp_error( $result ) ) {
					echo json_encode(
						array(
							'success' => false,
							'errors' => array(
								array( 'message' => $result->get_error_message() )
							)
						)
					);
					exit;
				} else {
					echo json_encode( array( 'success' => (bool) $result, 'prod_id' => $prod_id ) );
					exit;
				}
			}else {
				header("HTTP/1.0 409 " . sprintf( __( 'Product with ID %d does not exist!', 'woocommerce-lightspeed-pos' ), $prod_id ) );
				exit;
			}
		}

		function relink_wc_prod( $wc_prod_id ){

			$wc_prod = wc_get_product( $wc_prod_id );
			if ( $wc_prod->is_type( 'simple' ) ) {
				return $this->relink_simple_prod( $wc_prod );
			} else if ( $wc_prod->is_type( 'variable' ) ) {
				return $this->relink_matrix_prod( $wc_prod );
			} else {
				return new WP_Error(
					'wclsi_cannot_relink',
					__('Could not find a WooCommerce product ID to relink with.', 'woocommerce-lightspeed-pos'),
					$wc_prod_id
				);
			}
		}

		/**
		 * @param WC_Product_Simple $wc_prod
		 *
		 * @return bool|int|null|string|WP_Error
		 */
		function relink_simple_prod( WC_Product_Simple $wc_prod ) {
			$ls_item_id = get_post_meta( $wc_prod->get_id(), WCLSI_SINGLE_ITEM_ID_POST_META, true );
			$wclsi_id = WCLSI_Lightspeed_Prod::get_mysql_id( $ls_item_id, 0 );

			if ( $wclsi_id > 0 ) {
				return $this->relink_wclsi_item( $wclsi_id, $wc_prod );
			} else {
				global $WCLSI_API, $WCLSI_SINGLE_LOAD_RELATIONS;
				$search_params = array(
					'load_relations' => json_encode( $WCLSI_SINGLE_LOAD_RELATIONS )
				);

				$sku = $wc_prod->get_sku();

				if ( empty( $ls_item_id ) ) {
					$search_params['or'] = "customSku=$sku|systemSku=$sku";
					$ls_item_id = '';
				}

				$result =
					$WCLSI_API->make_api_call(
						"Account/$WCLSI_API->ls_account_id/Item/$ls_item_id",
						"Read",
						$search_params
					);

				if ( is_wp_error( $result ) ) {
					return $result;
				} elseif ( 0 == $result->{'@attributes'}->count ) {
					return new WP_Error(
						'wclsi_could_not_find_ls_product',
						__(
							"Could not relink! Lightspeed product with SKU: '$sku' could not be found.",
							'woocommerce-lightspeed-pos'
						),
						'error');
				} elseif ( $result->{'@attributes'}->count > 1 && !empty( $sku ) ) {
					return new WP_Error(
						'wclsi_could_not_match_ls_product',
						__(
							"Could not relink! There was more than one matching Lightspeed product with sku: '$sku'.",
							'woocommerce-lightspeed-pos'
						),
						'error');
				} else {
					$ls_api_item = $result->Item;
					return $this->relink_ls_api_item( $ls_api_item, $wc_prod );
				}
			}
		}

		/**
		 * @param WC_Product_Variable $wc_prod
		 * @return WP_Error|bool
		 */
		function relink_matrix_prod( WC_Product_Variable $wc_prod ) {
			$ls_matrix_id = get_post_meta( $wc_prod->get_id(), WCLSI_MATRIX_ID_POST_META, true );

			if ( false === $ls_matrix_id) {
				$matrix_id_not_found_error_msg =
					__(
						'Could not find a Lightspeed Matrix ID to relink with.',
						'woocommerce-lightspeed-pos'
					);

				return new WP_Error(
					'wclsi_relink_matrix_id_not_found',
					$matrix_id_not_found_error_msg,
					'error'
				);
			} else {
				$wclsi_id = WCLSI_Lightspeed_Prod::get_mysql_id( null, $ls_matrix_id );
				if ( $wclsi_id > 0 ) {
					$result = $this->relink_wclsi_item( $wclsi_id, $wc_prod );
					if ( is_wp_error( $result ) ) {
						return $result;
					}
				} else {
					global $WCLSI_API, $WCLSI_MATRIX_LOAD_RELATIONS;
					$search_params = array(
						'load_relations' => json_encode( $WCLSI_MATRIX_LOAD_RELATIONS )
					);

					$result =
						$WCLSI_API->make_api_call(
							"Account/$WCLSI_API->ls_account_id/ItemMatrix/$ls_matrix_id",
							"Read",
							$search_params
						);

					if ( is_wp_error( $result ) ) {
						return $result;
					} elseif ( 0 == $result->{'@attributes'}->count ) {
						return new WP_Error(
							'wclsi_could_not_find_ls_product',
							__(
								'Could not relink product! The product was not found in Lightspeed.',
								'woocommerce-lightspeed-pos'
							),
							'error');
					} else {
						$ls_api_matrix_item = $result->ItemMatrix;
						$this->relink_ls_api_item( $ls_api_matrix_item, $wc_prod );
					}
				}

				return $this->relink_variation_prods( $wc_prod, $ls_matrix_id );
			}
		}

		function relink_variation_prods( WC_Product_Variable $wc_parent_prod, $ls_matrix_id ) {
			$variation_ids = $wc_parent_prod->get_children();
			$wclsi_variation_mapping = [];
			$ls_items_to_lookup = [];
			$skus_to_lookup = [];
			$results = [];

			$sync = get_post_meta( $wc_parent_prod->get_id(), WCLSI_SYNC_POST_META, true);

			// Try to get the LS item_id via post_meta first
			foreach( $variation_ids as $wc_variation_id ) {
				$ls_item_id = get_post_meta( $wc_variation_id, WCLSI_SINGLE_ITEM_ID_POST_META, true );

				// If there is no id, try and use the SKU
				if (  empty( $ls_item_id ) ) {
					$wc_variation = wc_get_product( $wc_variation_id );
					$skus_to_lookup[$wc_variation_id] = $wc_variation->get_sku();
				} else {
					$wclsi_variation_mapping[$wc_variation_id] = $ls_item_id;
				}
			}

			if ( !empty( $wclsi_variation_mapping ) ) {
				foreach ( $wclsi_variation_mapping as $wc_variation_id => $ls_item_id ) {
					// See if the product exists in wp_wclsi_items table first and try and relink it
					$wclsi_id = WCLSI_Lightspeed_Prod::get_mysql_id( $ls_item_id, $ls_matrix_id );
					if ( $wclsi_id > 0 ) {
					   $results[] = $this->relink_wclsi_item( $wclsi_id, wc_get_product($wc_variation_id), $sync );
					} else {
					   $ls_items_to_lookup[$wc_variation_id] = $ls_item_id;
					}
				}
			}

			// Lookup the items we couldn't find
			if ( !empty( $skus_to_lookup ) || !empty( $ls_items_to_lookup ) ) {
				global $WCLSI_API, $WCLSI_SINGLE_LOAD_RELATIONS;

				$search_params = array(
					'load_relations' => json_encode( $WCLSI_SINGLE_LOAD_RELATIONS ),
					'itemMatrixID' => $ls_matrix_id,
					'or' =>  $this->get_relink_lookup_params( $skus_to_lookup, $ls_items_to_lookup )
				);

				$result = $WCLSI_API->make_api_call( "Account/$WCLSI_API->ls_account_id/Item", "Read", $search_params );

				if ( is_wp_error( $result ) ) {
					return $result;
				} elseif ( 0 == $result->{'@attributes'}->count ) {
					$skus = join(',', $skus_to_lookup);
					return new WP_Error(
						'wclsi_could_not_find_ls_product',
						__(
							"Could not relink product! The product(s) with SKU $skus were not found in Lightspeed.",
							'woocommerce-lightspeed-pos'
						),
						'error');
				} else {
					$ls_api_items = $result->Item;

					$items_to_lookup = array_flip( $ls_items_to_lookup ) + array_flip( $skus_to_lookup );
					foreach( $ls_api_items as $ls_api_item ) {

						if ( isset( $items_to_lookup[ $ls_api_item->itemID ] ) ) {
							$wc_prod_id = $items_to_lookup[ $ls_api_item->itemID ];
						} elseif ( isset( $items_to_lookup[ $ls_api_item->systemSku ] ) ) {
							$wc_prod_id = $items_to_lookup[ $ls_api_item->systemSku ];
						} elseif ( isset( $items_to_lookup[ $ls_api_item->customSku ] ) ) {
							$wc_prod_id = $items_to_lookup[ $ls_api_item->customSku ];
						}

						$results[] = $this->relink_ls_api_item( $ls_api_item, wc_get_product( $wc_prod_id ), $sync );
					}
				}
			}

			foreach( $results as $result ) {
				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}

			return true;
		}

		/**
		 * "Re-links" a WC product with one that does NOT exist in the wp_wclsi_items table. It achieves this by first
		 * inserting a new item in the table and then filling in the "wc_prod_id" column with the WC prod id.
		 * @param $ls_api_item
		 * @param $wc_prod
		 *
		 * @return bool|false|int|null|string|WP_Error
		 */
		private function relink_ls_api_item( $ls_api_item, WC_Product $wc_prod, $sync = false ) {
			// last check on the wp_wclsi_items table in case post_meta did not exist for the product to begin with
			$wclsi_id = WCLSI_Lightspeed_Prod::item_exists( $ls_api_item );
			if ( $wclsi_id > 0 ) {
				return $this->relink_wclsi_item( $wclsi_id, $wc_prod );
			} else {
				$key   = $wc_prod->is_type( 'variable' ) ? WCLSI_MATRIX_ID_POST_META : WCLSI_SINGLE_ITEM_ID_POST_META;
				$value = $wc_prod->is_type( 'variable' ) ? $ls_api_item->itemMatrixID : $ls_api_item->itemID;
				update_post_meta( $wc_prod->get_id(), $key, $value );

				// For variation items in case the parent matrix product has sync enabled
				if ( $sync ) {
					$ls_api_item->wclsi_is_synced = true;
					update_post_meta( $wc_prod->get_id(), WCLSI_SYNC_POST_META, true);
				}

				$ls_api_item->wc_prod_id = $wc_prod->get_id();
				$import_date = $wc_prod->get_date_created();
				if( !empty( $import_date ) ) {
					$ls_api_item->wclsi_import_date = date_i18n('Y-m-d H:i:s', $import_date->getTimestamp());
				}

				return WCLSI_Lightspeed_Prod::insert_ls_api_item( $ls_api_item );
			}
		}

		/**
		 * "Re-links" a WC product with one that exists in the wp_wclsi_items table.
		 *
		 * @param $wclsi_id
		 * @param $wc_prod
		 * @param bool $sync
		 *
		 * @return bool|false|int|WP_Error
		 */
		private function relink_wclsi_item( $wclsi_id, $wc_prod, $sync = false ) {
			$wclsi_prod = new WCLSI_Lightspeed_Prod( $wclsi_id );

			if ( is_null( $wclsi_prod->wc_prod_id ) ) {
				$key = $wclsi_prod->is_matrix_product() ? WCLSI_MATRIX_ID_POST_META : WCLSI_SINGLE_ITEM_ID_POST_META;
				$value = $wclsi_prod->is_matrix_product() ? $wclsi_prod->item_matrix_id : $wclsi_prod->item_id;
				update_post_meta( $wc_prod->get_id(), $key, $value );

				// For variation items in case the parent matrix product has sync enabled
				if ( $sync ) {
					$wclsi_prod->update_column( 'wclsi_is_synced', true );
					update_post_meta( $wc_prod->get_id(), WCLSI_SYNC_POST_META, true);
				}

				$import_date = $wc_prod->get_date_created();
				if ( !empty( $import_date ) ) {
					$wclsi_prod->update_column( 'wclsi_import_date', date_i18n('Y-m-d H:i:s', $import_date->getTimestamp()) );
				}

				$update_result = $wclsi_prod->update_column('wc_prod_id', $wc_prod->get_id() );
				if ( false === $update_result ) {
					global $wpdb;
					$error_msg =
						__(
							"Could not relink product, there was a problem with updating the wc_prod_id column. {$wpdb->last_error}",
							'woocommerce-lightspeed-pos'
						);
					return new WP_Error( 'wclsi_could_not_relink_mysql', $error_msg, $wclsi_id );
				} else if ( is_int( $update_result ) ) {
					return $update_result;
				}
			} else {
				$error_msg =
					__(
						'Product already linked! This product is already exists in the Lightspeed Import Table.',
						'woocommerce-lightspeed-pos'
					);
				return new WP_Error( 'wclsi_already_linked', $error_msg );
			}
		}

		private function get_relink_lookup_params( $skus_to_lookup, $ls_items_to_lookup ){
			$sku_lookup_params = '';
			foreach( $skus_to_lookup as $sku ) {
				if ( $sku !== end($skus_to_lookup) ) {
					$sku_lookup_params .= "systemSku=$sku|customSku=$sku|";
				} else {
					$sku_lookup_params .= "systemSku=$sku|customSku=$sku";
				}
			}

			$ls_items_lookup_params = '';
			foreach( $ls_items_to_lookup as $item_id ) {
				if ( $item_id !== end($ls_items_to_lookup) ) {
					$ls_items_lookup_params .= "itemID=$item_id|";
				} else {
					$ls_items_lookup_params .= "itemID=$item_id";
				}
			}

			if ( empty( $ls_items_lookup_params ) ) {
				return $sku_lookup_params;
			} else {
				return $sku_lookup_params . '|' . $ls_items_lookup_params;
			}
		}
	}

	$LSI_Import_Page = new LSI_Import_page();
endif;