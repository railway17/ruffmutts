<?php
/**
 * Created by PhpStorm.
 * User: ryagudin
 * Date: 1/16/15
 * Time: 11:58 AM
 */

if ( !class_exists( 'WP_List_Table' ) ) {
	require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

if ( !class_exists( 'WC_LS_Import_Table') ) :
	class WC_LS_Import_Table extends WP_List_Table {
		/**
		 * Constructor, we override the parent to pass our own arguments
		 * We usually focus on three parameters: singular and plural labels, as well as whether the class supports AJAX.
		 */
		function __construct() {
			parent::__construct( array(
				'singular' => 'wc_ls_imported_prod', //Singular label
				'plural'   => 'wc_ls_imported_prods', //plural label, also this well be one of the table css class
				'ajax'     => false //We won't support Ajax for this table
			) );
		}

		/**
		 * Delete - removes the product form being imported
		 * Update - Updates the product via LightSpeed
		 * Import - Imports the product into WooCommerce
		 * @return array
		 */
		function get_bulk_actions() {
			$actions = array(
				'import_and_sync' => 'Import & Sync',
				'import' => 'Import',
				'sync'   => 'Sync',
				'update' => 'Update',
				'delete' => 'Delete'
			);
			return $actions;
		}

		/**
		 * @return array
		 */
		function get_row_actions() {
			$actions = array(
				'import_and_sync',
				'sync',
				'import',
				'update',
				'delete'
			);
			return $actions;
		}

		/**
		 * Processes the bulk actions...
		 */
		function process_bulk_action() {

			if ( 'delete' === $this->current_action() ) {
				$this->process_bulk_delete();
			}

			if ( 'import' === $this->current_action() ) {
				$this->process_bulk_import();
			}

			if ( 'import_and_sync' === $this->current_action() ) {
				$this->process_bulk_import( true );
			}

			if ( 'sync' === $this->current_action() ) {
				$this->process_bulk_sync();
			}

			if ( 'update' === $this->current_action() ) {
				$this->process_bulk_update();
			}
		}

		/**
		 * Processes bulk syncing action
		 * @see self::process_single_sync()
		 */
		function process_bulk_sync() {
			if ( isset( $_GET['wc_ls_imported_prod'] ) && is_array( $_GET['wc_ls_imported_prod'] ) ) {

				$update_msgs = array();

				foreach( $_GET['wc_ls_imported_prod'] as $key => $prod_id ) {
					$update_msg = $this->process_single_sync( $prod_id, true );
					array_push( $update_msgs, $update_msg );
				}

				$errors = '';
				foreach( $update_msgs as $key => $msg ) {
					if ( $msg['type'] == 'error' ) {
						$errors .= '<p>' . $msg['msg'] . '</p>';
						unset( $update_msgs[ $key ] ); //get rid of it since we want to count the ones that weren't errors
					}
				}

				// Display errors if we can across any errors
				if ( !empty( $errors ) ) {
					add_settings_error(
						'wclsi_settings',
						'wclsi_prods_imported',
						$errors,
						'error'
					);
				}

				// Display a notice to the user that we've synced products
				if ( count( $update_msgs ) > 0 ) {
					add_settings_error(
						'wclsi_settings',
						'wclsi_prods_imported',
						sprintf( __('%d product(s) successfully synced!', 'woocommerce-lightspeed-pos' ), count( $update_msgs ) ),
						'updated'
					);
				}
			}
		}

		/**
		 * Process the bulk delete action ...
		 * @todo add_settings_error() gets called regardless if anything goes wrong ...
		 */
		function process_bulk_delete() {

			if ( isset( $_GET['wc_ls_imported_prod'] ) && is_array( $_GET['wc_ls_imported_prod'] ) ) {

				foreach( $_GET['wc_ls_imported_prod'] as $key => $prod_id ) {
					$this->process_single_delete( $prod_id );
				}

				// Display a notice to the user that we've imported products
				add_settings_error(
					'wclsi_settings',
					'wclsi_prods_imported',
					sprintf( __( '%d products successfully deleted.', 'woocommerce-lightspeed-pos' ), count( $_GET['wc_ls_imported_prod'] ) ),
					'updated'
				);
			}
		}

		/**
		 * Processes bulk update action
		 */
		function process_bulk_update() {
			if ( isset( $_GET['wc_ls_imported_prod'] ) && is_array( $_GET['wc_ls_imported_prod'] ) ) {

				$success_counter = 0;
				$errors = array();

				foreach ( $_GET['wc_ls_imported_prod'] as $key => $prod_id ) {
					$result = $this->process_single_update( $prod_id );
					if ( $result['type'] == 'error' ) {
						$errors[] = $result['msg'];
					} else {
						$success_counter++;
					}
				}

				if ( !empty( $errors ) ) {
					$error_msg = implode( '<br />', $errors );
					add_settings_error(
						'wclsi_settings',
						'wclsi_update_error',
						sprintf( __( 'Could not update %d products. Error message(s): %s' ), count( $errors ), __( $error_msg ) )
					);
				}

				if ( $success_counter > 0 ) {
					add_settings_error(
						'wclsi_settings',
						'wclsi_update_success',
						sprintf( __( 'Successfully updated %d products!'), $success_counter ),
						'updated'
					);
				}
			}
		}

		/**
		 * Processes a single product's manual sync
		 *
		 * @param $item_mysql_id
		 *
		 * @return array
		 */
		public static function process_single_update( $item_mysql_id ) {
			global $WCLSI_WC_Logger, $WCLSI_PRODS;

			$ls_prod = new WCLSI_Lightspeed_Prod( $item_mysql_id );

			if( WP_DEBUG ) {
				$WCLSI_WC_Logger->add(
					WCLSI_LOG,
					'Processing single update (LS->WC) - existing Lightspeed object:' . PHP_EOL . print_r( $ls_prod, true )
				);
			}

			$result = $ls_prod->update_via_api();

			if ( $result > 0 ) {

				if ( $ls_prod->wc_prod_id > 0 ) {
					$ls_prod->reload();
					
					// Skip subsequent queueing of sync events
					wp_cache_add( 'manual_prod_update', true );
					
					$WCLSI_PRODS->update_wc_prod( $ls_prod );
				}

				return array(
					'msg'  => __( '1 product successfully updated.', 'woocommerce-lightspeed-pos' ),
					'type' => 'updated'
				);
			} else if ( is_wp_error( $result ) ) {
				return array(
					'msg'  => __( 'Error - could not complete update action.', 'woocommerce-lightspeed-pos' ),
					'type' => 'error'
				);
			} else {
				return array(
					'msg'  => __( 'Something went wrong! Could not complete update action', 'woocommerce-lightspeed-pos' ),
					'type' => 'error'
				);
			}
		}

		/**
		 * Processes the bulk import action ...
		 * @param $sync
		 */
		function process_bulk_import( $sync = false ) {
			if ( isset( $_GET['wc_ls_imported_prod'] ) && is_array( $_GET['wc_ls_imported_prod'] ) ) {

				$errors = array();

				foreach( $_GET['wc_ls_imported_prod'] as $key => $prod_id ) {
					$result = $this->process_single_import( $prod_id, $sync );

					if( $result['type'] == 'error' ){
						$errors[] = $result;
					}
				}

				if( !empty( $errors ) ) {
					foreach( $errors as $error ) {
						add_settings_error(
							'wclsi_settings',
							'wclsi_bulk_import_errors',
							$error['msg'],
							'error'
						);
					}
				}

				$success_count = count( $_GET['wc_ls_imported_prod'] ) - count( $errors );
				if( $success_count > 0 ) {
					add_settings_error(
						'wclsi_settings',
						'wclsi_prods_imported',
						sprintf(
							__( '%d products successfully imported! You can view the imported product(s) %shere%s.', 'woocommerce-lightspeed-pos' ),
							$success_count,
							'<a href="' . admin_url( 'edit.php?post_type=product' ) . '"">',
							'</a>'
						),
						'updated'
					);
				}
			}
		}

		/**
		 * Sets the sync flag for a single product
		 * @param $item_id
		 * @param $is_synced
		 * @return array
		 */
		function process_single_sync( $item_id, $is_synced ) {

			$item = new WCLSI_Lightspeed_Prod( $item_id );

			if ( isset( $item->wc_prod_id ) && $item->wc_prod_id > 0 ) {
				update_post_meta( $item->wc_prod_id, WCLSI_SYNC_POST_META, $is_synced );
				$update_msg = __( 'Successfully added "' . $item->description . '" to the sync schedule!',  'woocommerce-lightspeed-pos' );
				$update_type = 'updated';
			} else {
				$update_msg = sprintf( '%s "%s" %s',
					__( 'Please import ', 'woocommerce-lightspeed-pos' ),
					$item->description,
					__(' before attempting to sync it!', 'woocommerce-lightspeed-pos' )
				);
				$update_type = 'error';
			}
			return array( 'msg' => $update_msg, 'type' => $update_type );
		}

		/**
		 * Handles a single delete action
		 * @param $prod_id
		 * @return array
		 */
		function process_single_delete( $prod_id ) {
			if ( $prod_id > 0 ) {

				$prod = new WCLSI_Lightspeed_Prod( $prod_id );
				$prod->delete( true );

				return array(
					'msg' => __( '1 product successfully deleted.', 'woocommerce-lightspeed-pos' ),
					'type' => 'updated'
				);
			} else {
				return array(
					'msg' => __( 'Could not find the product to delete!', 'woocommerce-lightspeed-pos' ),
					'type' => 'error'
				);
			}
		}

		/**
		 * Processes a single product import
		 *
		 * @param $item_id
		 * @param $sync
		 *
		 * @return array
		 */
		function process_single_import( $item_id, $sync = false ) {
			global $WCLSI_PRODS;

			$item = new WCLSI_Lightspeed_Prod( $item_id );

			// Convert the LightSpeed product to a WC product
			$result = $WCLSI_PRODS->import_item( $item, $sync );

			if( is_wp_error( $result ) ) {
				return array(
					'msg'  => $result->get_error_message(),
					'type' => 'error'
				);
			} else if ( false == $result ) {
				return array(
					'msg'  => __( 'Something went wrong! The import action could not be completed.', 'woocommerce-lightspeed-pos' ),
					'type' => 'error'
				);
			} else {

				$edit_url = add_query_arg( 'post_type', 'product', admin_url( 'edit.php' ) );
				if( $sync ) {
					$msg = sprintf
					(
						__(
							'1 product successfully imported & synced! You can view the imported product <a href="%s">here</a>.',
							'woocommerce-lightspeed-pos'
						),
						$edit_url
					);
				} else {
					$msg = sprintf
					(
						__(
							'1 product successfully imported! You can view the imported product <a href="%s">here</a>.',
							'woocommerce-lightspeed-pos'
						),
						$edit_url
					);
				}

				return array( 'msg'  => $msg, 'type' => 'updated' );
			}
		}

		/**
		 * Handles row actions defined in @see column_prod_name()
		 */
		function process_row_actions() {
			if ( isset( $_GET['action'] ) && ( isset( $_GET['prod_id'] ) || isset( $_GET['matrix_id'] ) ) ) {

				$row_action = (string) $_GET['action'];

				if ( !in_array( $row_action, $this->get_row_actions() ) ) {
					return;
				}

				$update_info = $this->perform_row_action( $row_action );
				if ( !empty( $update_info ) ) {
					add_settings_error(
						'wclsi_settings',
						'wclsi_prod_imported',
						$update_info['msg'],
						$update_info['type']
					);
				}
			}
		}

		/**
		 * @param $action
		 * @return array
		 */
		private function perform_row_action( $action ) {

			$item_id = 0;

			if ( isset( $_GET[ 'prod_id' ] ) ) {
				$item_id = (int) $_GET[ 'prod_id' ];
			}

			if ( 0 == $item_id ) {
				add_settings_error(
					'wclsi_settings',
					'wclsi_prod_imported',
					__( 'Action failed. Could not find a proper Lightspeed product ID.', 'woocommerce-lightspeed-pos' ),
					'error'
				);
			}

			switch( $action ) {
				case 'import_and_sync':
					return $this->process_single_import( $item_id, true );
				case 'sync':
					return $this->process_single_sync( $item_id, true );
				case 'import':
					return $this->process_single_import( $item_id );
				case 'update':
					return $this->process_single_update( $item_id );
				case 'delete':
					return $this->process_single_delete( $item_id );
				default:
					return array( 'msg' => __( 'Could not complete action!', 'woocommerce-lightspeed-pos' ), 'type' => 'error' );
			}
		}

		/**
		 * Renders the category name instead of the ID
		 * @param $item
		 * @return mixed
		 */
		function column_prod_category( $item ) {

			$cat_html = '–'; // default value

			if( $item['wc_prod_id'] > 0 ){
				$wc_prod_id = (int) $item['wc_prod_id'];
				$wc_prod = wc_get_product( $wc_prod_id );

				if( false !== $wc_prod ){
					$cat_html = wc_get_product_category_list($item['wc_prod_id']);
					if ( empty ( $cat_html ) ) {
						$cat_html = '–';
					}
				}
			} else {
				$cat_html = $item['prod_category'];
			}

			return $cat_html ;
		}

		/**
		 * Renders the image for the product if one exists
		 * @param $item
		 * @return string
		 * @todo return image from wc_prod if wc_prod exists
		 */
		function column_prod_image( $item ) {
			$matrix_attrs = $item['matrix_item_id'] > 0 ? 'class="matrix-img" alt="Matrix Item" title="Matrix Item"' : '';

			$image = !empty( $item[ 'prod_image'] ) ? $item[ 'prod_image' ] : wc_placeholder_img_src();

			return '<img src="' . $image .'" height="40" ' . $matrix_attrs . ' />';
		}

		/**
		 * Displays the last sync date for an imported products
		 * @param $item
		 * @return string
		 */
		function column_prod_last_sync( $item ) {
			$is_synced = get_post_meta( $item['wc_prod_id'], WCLSI_SYNC_POST_META, true );

			if( $is_synced ) {
				if ( is_null( $item['prod_last_sync'] ) ) {
					return 'Pending...';
				} else {
					return date_i18n( 'Y-m-d H:i:s', strtotime( $item['prod_last_sync'] ) );
				}
			}

			return '';
		}

		/**
		 * @param $item
		 *
		 * @return string
		 */
		function column_prod_is_synced( $item ) {
			return $this->render_sync_checkbox( $item );
		}

		/**
		 * @param $item
		 *
		 * @return int|string
		 */
		function column_prod_inventory( $item ) {
			$ls_item = new WCLSI_Lightspeed_Prod( $item[ 'prod_id' ] );

			if( $ls_item->id > 0 ) {
				if ( $ls_item->is_matrix_product() ) {
					return '–';
				} else {
					return wclsi_get_lightspeed_inventory( $ls_item );
				}
			} else {
				return '–';
			}
		}

		function column_prod_price( $item ) {
			if ( $item[ 'wc_prod_id' ] > 0 ) {
				$wc_prod = wc_get_product( $item[ 'wc_prod_id'] );
				if ( false !== $wc_prod ) {
					return $wc_prod->get_price_html();
				} else {
					return '–';
				}
			} else {
				if ( $item['matrix_item_id'] > 0 ) {
					$wclsi_prod = new WCLSI_Lightspeed_Prod( $item['prod_id'] );
					$variations = $wclsi_prod->variations;
					if ( !empty( $variations ) ) {
						$min = $max = null;

						foreach( $variations as $variation ) {
							$price = $variation->get_regular_price();

							if ( is_null( $max ) && is_null( $min ) ) {
								$min = $max = $price;
							}

							if ( $price > $max ) {
								$max = $price;
							} else if ( $price < $min ) {
								$min = $price;
							}
						}

						if ( $min == $max ) {
							return wc_price( $min );
						} else {
							return wc_format_price_range( $min, $max );
						}
					}
				}

				if ( $item[ 'prod_sale_price'] > 0 ) {
					return wc_format_sale_price( $item[ 'prod_price' ], $item[ 'prod_sale_price'] );
				} else {
					return wc_price( $item[ 'prod_price' ] );
				}
			}
		}

		function column_prod_sku( $item ) {
			if( !empty( $item[ 'prod_custom_sku' ] ) ) {
				$ls_sku = $item[ 'prod_custom_sku' ];
			} elseif ( !empty( $item[ 'prod_manufacturer_sku' ] ) ) {
				$ls_sku = $item[ 'prod_manufacturer_sku' ];
			} else {
				$ls_sku = $item[ 'prod_system_sku' ];
			}

			if ( $item['matrix_item_id'] > 0 ) {
				return '–';
			} else {
				if ( $item[ 'wc_prod_id'] > 0 ) {
					$wc_prod = wc_get_product( $item[ 'wc_prod_id'] );
					if ( false !== $wc_prod ) {
						return $wc_prod->get_sku();
					} else {
						return $ls_sku;
					}
				} else {
					return $ls_sku;
				}
			}
		}

		/**
		 * Display the search box.
		 *
		 * @since 3.1.0
		 * @access public
		 *
		 * @param string $text The search button text
		 * @param string $input_id The search input id
		 */
		function search_box( $text, $input_id ) {
			$input_id = $input_id . '-search-input';

			if ( ! empty( $_REQUEST['orderby'] ) )
				echo '<input type="hidden" name="orderby" value="' . esc_attr( $_REQUEST['orderby'] ) . '" />';
			if ( ! empty( $_REQUEST['order'] ) )
				echo '<input type="hidden" name="order" value="' . esc_attr( $_REQUEST['order'] ) . '" />';
			if ( ! empty( $_REQUEST['post_mime_type'] ) )
				echo '<input type="hidden" name="post_mime_type" value="' . esc_attr( $_REQUEST['post_mime_type'] ) . '" />';
			if ( ! empty( $_REQUEST['detached'] ) )
				echo '<input type="hidden" name="detached" value="' . esc_attr( $_REQUEST['detached'] ) . '" />';
			?>
			<p class="search-box">
				<label class="screen-reader-text" for="<?php echo $input_id ?>"><?php echo $text; ?>:</label>
				<input type="search" id="<?php echo $input_id ?>" name="s" value="<?php _admin_search_query(); ?>" />
				<?php submit_button( $text, 'button', false, false, array('id' => 'search-submit') ); ?>
			</p>
		<?php
		}
		
		/**
		 * Preps what to display for each column
		 * @param $item
		 * @param $column_name
		 * @return mixed
		 */
		function column_default( $item, $column_name ) {
			switch( $column_name ) {
				case 'prod_image':
				case 'prod_name':
				case 'prod_price':
				case 'prod_sku':
				case 'prod_last_import':
				case 'prod_is_synced':
				default:
					return $item[ $column_name ];
			}
		}

		/**
		 * @param $item
		 * @return string
		 */
		function column_prod_name( $item ) {

			$current_page = $this->get_pagenum();
			$prod_id = (int) $item[ 'prod_id' ];

			$query_args = array(
				'page' => 'lightspeed-import-page',
				'prod_id' => $prod_id,
				'paged' => $current_page
			);

			if( $item[ 'matrix_item_id' ] > 0 ){
				$query_args[ 'matrix_id' ] = $item[ 'matrix_item_id' ];
			}

			$query_args[ 'action' ] = '';

			$base_url = add_query_arg( $query_args, admin_url( 'admin.php' ) );

			$actions = array(
				'import_and_sync' => sprintf( '<a href="%s=%s" class="wclsi-import-sync-prod">Import & Sync</a>', $base_url, 'import_and_sync' ),
				'import' => sprintf( '<a href="%s=%s" class="wclsi-import-prod">Import</a>' , $base_url, 'import' ),
				'sync'   => sprintf( '<a href="%s=%s" class="wclsi-sync-prod">Sync</a>' , $base_url, 'sync' ),
				'update' => sprintf( '<a href="%s=%s" class="wclsi-update-prod">Update</a>', $base_url, 'update' ),
				'delete' => sprintf( '<a href="%s=%s" class="wclsi-delete-prod">Delete</a>', $base_url, 'delete' )
			);

			if ( $item['wc_prod_id'] ) {
				if ( false !== wc_get_product( $item['wc_prod_id'] ) ) {
					$edit = array(
						'Edit' => sprintf( '<a href="%s">Edit</a>', admin_url('post.php?post=' . $item['wc_prod_id'] . '&action=edit') )
					);
					unset( $actions['import_and_sync'] );
					unset( $actions['import'] );
					$actions = $edit + $actions;
				}
			}

			//Return the title contents
			return sprintf('%1$s %2$s',
				/*$1%s*/ '<b>' . $this->format_item_name_html( $item ) . '</b>',
				/*$2%s*/ $this->row_actions($actions)
			);
		}

		/**
		 * Checkboxes
		 * @param $item
		 * @return string
		 */
		function column_cb($item) {

			$is_matrix =  $item['matrix_item_id'] > 0 ? 'matrix' : 'single';

			return sprintf(
				'<input type="checkbox" name="%1$s[]" value="%2$s" data-prod-type="%3$s" />',
				esc_attr( $this->_args['singular'] ),
				esc_attr( $item['prod_id'] ),
				$is_matrix
			);
		}

		/**
		 * Define the columns that are going to be used in the table
		 * @return array $columns, the array of columns to use with the table
		 */
		function get_columns() {
			return $columns = array(
				'cb'               => '<input type="checkbox" />', //Render a checkbox instead of text
				'prod_image'       => '<span class="wc-image tips" data-tip="' . __('Image', 'wclsli') . '">' . __('Image', 'wclsli') . '</span>',
				'prod_name'        => __( 'Name', 'woocommerce-lightspeed-pos' ),
				'prod_price'       => __( 'Price', 'woocommerce-lightspeed-pos' ),
				'prod_sku'         => __( 'SKU', 'woocommerce-lightspeed-pos' ),
				'prod_inventory'   => __( 'Inventory', 'woocommerce-lightspeed-pos' ),
				'prod_category'    => __( 'Category', 'woocommerce-lightspeed-pos' ),
				'prod_last_import' => __( 'Import Date', 'woocommerce-lightspeed-pos' ) . '<span class="tips" data-tip="' . __('The date when a product was added to WooCommerce.') . '" ><img class="help_tip" src="' . WC()->plugin_url() . '/assets/images/help.png" height="16" width="16"></span>',
				'prod_last_sync'   => __( 'Last Sync', 'woocommerce-lightspeed-pos' ) . '<span class="tips" data-tip="' . __('The date when a product was last synced with LightSpeed.') . '" ><img class="help_tip" src="' . WC()->plugin_url() . '/assets/images/help.png" height="16" width="16"></span>',
				'prod_is_synced'   => '<span class="wclsi-sync tips" data-tip="' . __('Synced Products. Uncheck to remove a product from the sync schedule.', 'wclsli') . '">' . __('Synced', 'wclsli') . '</span>'
			);
		}

		/**
		 * Decide which columns to activate the sorting functionality on
		 * @return array $sortable, the array of columns that can be sorted by the user
		 */
		function get_sortable_columns() {
			$sortable_columns = array(
				'prod_name'        => array( 'prod_name',false), //true means it's already sorted
				'prod_price'       => array( 'prod_price', false),
				'prod_inventory'   => array( 'prod_inventory', false),
				'prod_category'    => array( 'prod_category', false),
				'prod_last_import' => array( 'prod_last_import', false),
				'prod_last_sync'   => array( 'prod_last_sync', false),
				'prod_is_synced'   => array( 'prod_is_synced', false)
			);
			return $sortable_columns;
		}

		/**
		 * Formats prod name
		 */
		private function format_item_name_html( $item ) {
			if ( $item['wc_prod_id'] > 0 && false !== wc_get_product( $item['wc_prod_id'] ) ) {
				$href = add_query_arg(
						array(
							'post' =>  $item['wc_prod_id'],
							'action' => 'edit'
						),
						admin_url( 'post.php' )
					);
				return sprintf('<a href="%s">%s</a>', esc_attr( $href ), esc_attr( get_the_title( $item['wc_prod_id'] ) ) );
			} else if ( !empty( $item['prod_name'] ) ) {
				return $item['prod_name'];
			} else {
				return '--';
			}
		}

		private function render_sync_checkbox( $item ) {
			if ( $item['wc_prod_id'] > 0 ) {
				$is_synced = get_post_meta( $item['wc_prod_id'], WCLSI_SYNC_POST_META, true ) ? 'checked' : '';
				return sprintf( '<input type="checkbox" class="wclsi-sync-cb" data-prodid="%s" id="synced-%s" %s>',
					esc_attr( $item['wc_prod_id'] ),
					esc_attr( $item['wc_prod_id'] ),
					esc_attr( $is_synced )
				);
			} else {
				return sprintf( '<input type="checkbox" class="wclsi-sync-cb" data-error="%s">', __( 'Could not find product ID', 'woocommerce-lightspeed-pos' ) );
			}
		}

		/**
		 * Sorts data based off orderby REQUEST variable
		 */
		private function generate_data_via_search_request() {
			$order_by = isset( $_REQUEST['orderby'] ) ? (string) $_REQUEST['orderby'] : '';
			$order = isset( $_REQUEST['order'] ) ? (string) $_REQUEST['order'] : '';

			// whitelist
			$allowed_orderby = array(
				'prod_name',
				'prod_price',
				'prod_inventory',
				'prod_category',
				'prod_last_import',
				'prod_is_synced',
				'prod_last_sync'
			);

			$allowed_order = array( 'asc', 'desc' );

			$order_by = in_array( $order_by, $allowed_orderby ) ? $order_by : 'item.description';
			$order    = in_array( $order, $allowed_order ) ? $order : 'asc';

			$where = empty( $_REQUEST['s'] ) ? '' : trim($_REQUEST['s']);

			$data = $this->query_lightspeed_prods( $order_by, $order, $where );

			if ( empty( $data ) && !empty( $_REQUEST['s'] ) ) {
				add_settings_error(
					'wclsi_settings',
					'wclsi_no_search_results',
					sprintf(
						'No results found! Click <a href="%s">here</a> to go back.',
						WCLSI_ADMIN_URL
					)
				);
			}

			return $data;
		}

		private function query_lightspeed_prods( $order_by, $order, $where = '' ) {
			global $wpdb, $WCLSI_ITEM_TABLE, $WCLSI_ITEM_CATEGORIES_TABLE, $WCLSI_ITEM_SHOP_TABLE,
				   $WCLSI_ITEM_IMAGES_TABLE, $WCLSI_ITEM_PRICES_TABLE, $WCLSI_API;

			$default_price_use_type_id = 1;

			$shop_id = isset( $WCLSI_API->settings[WCLSI_INVENTORY_SHOP_ID] ) ? $WCLSI_API->settings[WCLSI_INVENTORY_SHOP_ID] : 0;
			$per_page = $this->get_items_per_page( 'product_per_page' );
			$offset = ($this->get_pagenum()-1)*$per_page;

			if ( !wclsi_table_exists( $WCLSI_ITEM_TABLE ) || wclsi_table_empty( $WCLSI_ITEM_TABLE ) ) {
				return array();
			}

			$sql =
				"SELECT 
					item.id                AS prod_id,
					item.item_id           AS prod_ls_id,
					item.description       AS prod_name, 
					item_price.amount      AS prod_price,
					item_sale_price.amount AS prod_sale_price,
					category.name          AS prod_category,
					item_shop.qoh          AS prod_inventory, 
					wclsi_import_date      AS prod_last_import, 
					wclsi_last_sync_date   AS prod_last_sync,
					wclsi_is_synced 	   AS prod_is_synced,
					item.item_matrix_id    AS matrix_item_id,
					item.wc_prod_id        AS wc_prod_id,
					item.custom_sku        AS prod_custom_sku,
					item.system_sku        AS prod_system_sku,
					item.manufacturer_sku  AS prod_manufacturer_sku,
					CONCAT(
					  item_image.base_image_url, 
					  'w_40,c_fill/', item_image.public_id, '.', 
					  SUBSTRING_INDEX(item_image.filename, '.', -1)
					) AS prod_image
				FROM $WCLSI_ITEM_TABLE as item
				LEFT JOIN $WCLSI_ITEM_CATEGORIES_TABLE as category 
				  ON item.category_id = category.category_id
				LEFT JOIN $WCLSI_ITEM_SHOP_TABLE as item_shop 
				  ON item.id = item_shop.wclsi_item_id AND item_shop.shop_id = $shop_id
				LEFT JOIN $WCLSI_ITEM_IMAGES_TABLE as item_image 
				  ON item.id = item_image.wclsi_item_id AND item_image.ordering = 0
				LEFT JOIN $WCLSI_ITEM_PRICES_TABLE as item_price 
				  ON item.id = item_price.wclsi_item_id AND item_price.use_type_id = $default_price_use_type_id
				LEFT JOIN $WCLSI_ITEM_PRICES_TABLE as item_sale_price 
				  ON item.id = item_sale_price.wclsi_item_id AND item_sale_price.use_type = 'Sale'				  
				WHERE
					item.id NOT IN(
					  SELECT id FROM $WCLSI_ITEM_TABLE
						WHERE item_id > 0
						AND item_matrix_id > 0
					)
					AND 
					(
						item.description like '$where%'
						OR item.description like '%$where'
						OR item.description like '%$where%'
						OR item.custom_sku like '$where%'
						OR item.custom_sku like '%$where'
						OR item.custom_sku like '%$where%'
						OR item.system_sku like '$where%'
						OR item.system_sku like '%$where'
						OR item.system_sku like '%$where%'
						OR item.manufacturer_sku like '$where%'
						OR item.manufacturer_sku like '%$where'
						OR item.manufacturer_sku like '%$where%'
						OR category.name like '$where%'
						OR category.name like '%$where'
						OR category.name like '%$where%'
						OR item_price.amount like '$where%'
						OR item_sale_price.amount like '$where%'
					)
				GROUP BY item.id
				ORDER BY $order_by $order
				LIMIT $per_page OFFSET $offset";

			return $wpdb->get_results( $sql, ARRAY_A );
		}

		/**
		 * Prepare the table with different parameters, pagination, columns and table elements
		 */
		function prepare_items() {
			global $wpdb, $WCLSI_ITEM_TABLE;

			$this->_column_headers = $this->get_column_info();

			// update/import/delete products
			$this->process_bulk_action();
			$this->process_row_actions();

			$this->items = $this->generate_data_via_search_request();
			$where = empty( $_REQUEST['s'] ) ? '' : $_REQUEST['s'];

			if ( !wclsi_table_exists( $WCLSI_ITEM_TABLE ) ) {
				$total_items = 0;
			} else {
				$total_items = $wpdb->get_var(
					"SELECT count(*) 
					  FROM $WCLSI_ITEM_TABLE
					  WHERE id NOT IN(
						SELECT id
						  FROM $WCLSI_ITEM_TABLE
						  WHERE item_id > 0
						  AND item_matrix_id > 0
						)
					  AND description like '%$where%';
					"
				);
			}

			$per_page = $this->get_items_per_page( 'product_per_page' );
			$this->set_pagination_args(
				array(
					'total_items' => $total_items,
					'per_page'    => $per_page,
					'total_pages' => ceil( $total_items/$per_page )
				)
			);
		}

		function clear_table_data(){
			global $wpdb, $WCLSI_ITEM_TABLE, $WCLSI_ITEM_E_COMMERCE_TABLE, $WCLSI_ITEM_IMAGES_TABLE,
				   $WCLSI_ITEM_PRICES_TABLE, $WCLSI_ITEM_SHOP_TABLE, $WCLSI_ITEM_ATTRIBUTE_SETS_TABLE;

			$tables = array(
				$WCLSI_ITEM_TABLE,
				$WCLSI_ITEM_SHOP_TABLE,
				$WCLSI_ITEM_PRICES_TABLE,
				$WCLSI_ITEM_IMAGES_TABLE,
				$WCLSI_ITEM_E_COMMERCE_TABLE,
				$WCLSI_ITEM_ATTRIBUTE_SETS_TABLE
			);

			foreach( $tables as $table ) {
				$wpdb->query("DELETE FROM $table;");
			}
		}

		/**
		 * $_GET parameters "action" and "prod_id" are persisting in the links.
		 * Removes those $_GET params from pagination links to they don't cause row actions to get
		 * inadvertently called.
		 */
		protected function pagination( $which ) {
			ob_start();

			parent::pagination( $which );
			$result = ob_get_contents();

			ob_end_clean();

			$dom = new DOMDocument;
			$dom->loadHTML($result);

			foreach ($dom->getElementsByTagName('a') as $node) {
				$bad_url = $node->getAttribute( 'href' );
				$good_url = remove_query_arg( array( 'action', 'prod_id' ), $bad_url );

				// remove_query_arg() doesn't catch url-encoding (i.e. %3D) value
				$good_url = str_replace( array( 'action', 'prod_id' ), '', $good_url );
				$node->setAttribute( 'href', $good_url );
			}

			if ( empty ( $_GET['s'] ) ) {
				foreach( $dom->getElementsByTagName('span') as $span_node ) {
					if ( $span_node->getAttribute('class') == 'displaying-num' ) {

						$single_item_count = wp_cache_get( "wclsi_single_item_count", "wclsi_table_queries" );
						$matrix_item_count = wp_cache_get( "wclsi_matrix_item_count", "wclsi_table_queries" );

						// If we do not have a cache hit, run the query
						if ( false === $single_item_count || false === $matrix_item_count ) {
							global $wpdb, $WCLSI_ITEM_TABLE;
							$single_item_count = $wpdb->get_var(
								"SELECT count(*) FROM $WCLSI_ITEM_TABLE WHERE item_id > 0 AND item_matrix_id >= 0;"
							);

							$matrix_item_count = $wpdb->get_var(
								"SELECT count(*) FROM $WCLSI_ITEM_TABLE WHERE item_id IS NULL AND item_matrix_id >= 0;"
							);

							wp_cache_add( "wclsi_single_item_count", $single_item_count, "wclsi_table_queries" );
							wp_cache_add( "wclsi_matrix_item_count", $matrix_item_count, "wclsi_table_queries" );
						}

						$span_node->textContent =
							$span_node->textContent . " ($single_item_count simple, $matrix_item_count matrix)";
					}
				}
			}

			echo $dom->saveHTML();
		}
	}
endif;
