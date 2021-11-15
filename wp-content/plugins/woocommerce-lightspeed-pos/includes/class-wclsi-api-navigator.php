<?php

if ( !class_exists( 'WCLSI_Api_Navigator' ) ) :

	class WCLSI_Api_Navigator {
		function __construct() {
			add_action( 'admin_enqueue_scripts', array( $this, 'add_wclsi_ls_navigator_js' ), 10 );
			add_action( 'wp_ajax_wclsi_query_ls_api_ajax', array( $this, 'wclsi_query_ls_api_ajax' ));
			add_action( 'wp_ajax_add_items_to_import_table_ajax', array( $this, 'add_items_to_import_table_ajax') );
		}
		
		function add_items_to_import_table_ajax() {
			wclsi_verify_nonce();
			$errors = [];

			if ( array_key_exists( 'prod_id', $_POST ) && array_key_exists( 'is_matrix', $_POST ) ) {
				global $WCLSI_API, $WCLSI_SINGLE_LOAD_RELATIONS, $WCLSI_MATRIX_LOAD_RELATIONS;
				$item_id = $_POST['prod_id'];
				$is_matrix = 'true' === $_POST['is_matrix'];

				// Try and see if the product already exists
				$wclsi_item = new WCLSI_Lightspeed_Prod();
				if ( $is_matrix ) {
					$wclsi_item->init_via_item_matrix_id( $item_id );
				} else {
					$wclsi_item->init_via_item_id( $item_id );
				}
				
				if ( $wclsi_item->id > 0 ) {
					// If the item already exists, just update it
					$result = $wclsi_item->update_via_api( $is_matrix );
					if ( is_wp_error( $result ) ) {
						$errors[] = join(', ', $result->get_error_messages());
					}
				} else {
					// Otherwise look it up, and add it
					$load_relations = $is_matrix ? $WCLSI_MATRIX_LOAD_RELATIONS : $WCLSI_SINGLE_LOAD_RELATIONS;
					$item_resource  = $is_matrix ? 'ItemMatrix' : 'Item';
					$response = $WCLSI_API->make_api_call(
						"Account/{$WCLSI_API->ls_account_id}/$item_resource/$item_id",
						"Read",
						array( 'load_relations' => json_encode( $load_relations ) )
					);

					if ( is_wp_error( $response ) ) {
						$errors[] = join(', ', $response->get_error_messages());
					} else {
						$wclsi_id = WCLSI_Lightspeed_Prod::insert_ls_api_item($response->{$item_resource});
						if ( $wclsi_id > 0 ) {
							$wclsi_item->init_via_id( $wclsi_id );

							// If the product is a matrix product, persist the variations
							if ( $is_matrix ) {
								$wclsi_item->persist_matrix_variations();
							}

							// If the product is a variation product, try and persist the parent matrix
							// If the parent matrix is already imported, import the variation
							if ( $wclsi_item->item_matrix_id > 0 ) {
								$wclsi_parent_id = $wclsi_item->persist_parent_matrix();
								if( is_wp_error( $wclsi_parent_id ) ) {
									$errors[] = join(', ', $wclsi_parent_id->get_error_messages());
								} else {
									$parent_wclsi_prod = new WCLSI_Lightspeed_Prod( $wclsi_parent_id );
									if ( $parent_wclsi_prod->wc_prod_id > 0 ) {
										global $WCLSI_PRODS;
										$parent_wc_prod = wc_get_product( $parent_wclsi_prod->wc_prod_id );
										if ( is_a( $parent_wc_prod, 'WC_Product' ) ) {
											$WCLSI_PRODS->update_matrix_variations( array( $wclsi_item ), $parent_wc_prod );
										}
									}
								}
							}
						} else {
							$errors[] = 'Could not add Lightspeed product! A call to insert_ls_api_item() failed.';
						}
					}
				}

				if ($wclsi_item->wc_prod_id > 0) {
					global $WCLSI_PRODS;
					$WCLSI_PRODS->update_wc_prod( $wclsi_item, $is_matrix );
				}

				if (count($errors) > 0) {
					echo json_encode( array( 'errors' => join(', ', $errors) ) );
				} else {
					echo json_encode( array( 'success' => $wclsi_item->id ) );
				}
			}

			exit;
		}

		function wclsi_query_ls_api_ajax() {
			wclsi_verify_nonce();
			
			if ( array_key_exists( 'search_params', $_POST ) ) {
				$search_value = $_POST['search_params']['search_value'];
				$product_type = $_POST['search_params']['prod_type'];
			} else {
				echo json_encode( array( 'error' => 'Search params not defined!' )  );
				exit;
			}
			
			$result = $this->wclsi_query_ls_api($search_value, $product_type);

			if ( is_wp_error( $result ) ) {
				echo json_encode( 
					array( 
						'errors' => $result->get_error_messages(), 
						'WAIT_TIME' => wclsi_get_api_wait_time_ms() 
					) 
				);
			} else {
				echo json_encode( 
					array( 
						'results' => $this->format_search_results($result, $product_type), 
						'WAIT_TIME' => wclsi_get_api_wait_time_ms() 
					)  
				);
			}

			exit;
		}

		function wclsi_query_ls_api($search_value, $product_type = 'single') {
			global $WCLSI_API, $WCLSI_MATRIX_LOAD_RELATIONS, $WCLSI_SINGLE_LOAD_RELATIONS;
			$search_params = array();
			if ('single' === $product_type) {
				$search_params['or'] = "description=~,$search_value|" .
									   "description=~,$search_value%|" .
									   "description=~,%$search_value%|" .
									   "systemSku=$search_value|" .
									   "customSku=$search_value";
				$search_params['load_relations'] = json_encode( $WCLSI_SINGLE_LOAD_RELATIONS );
				$item_resource = 'Account.Item';
			} else {
				$search_params['or'] = "description=~,%$search_value%";
				$search_params['load_relations'] = json_encode( $WCLSI_MATRIX_LOAD_RELATIONS );
				$item_resource = 'Account.ItemMatrix';
			}
			
			$search_params['orderby'] = 'description';
			$search_params['orderby'] = 'description'; 
			return $WCLSI_API->make_api_call($item_resource, "Read", $search_params);
		}
		
		private function format_search_results( $results, $product_type)  {
			if ($results->{'@attributes'}->count > 0) {
				$ls_item_type = $product_type === 'single' ? 'Item' : 'ItemMatrix';
				$ls_item_id = $product_type === 'single' ? 'itemID' : 'itemMatrixID';
				if( is_object( $results->{$ls_item_type} ) ) {
					$results->{$ls_item_type} = array( $results->{$ls_item_type} );
				}

				ob_start();
				?>
				<p><strong><?php echo $results->{'@attributes'}->count ?></strong> matches found!
				<form id="wclsi_api_navigator_bulk_add">
					<div style="margin-bottom: 10px;">                    
						<label for="wclsi_api_navigator_bulk_options" style="display: none;">Add to Import Table</label>
						<select id="wclsi_api_navigator_bulk_options">
							<option value="add_to_import_table">Add</option>
						</select>
						<button class="button action">Apply</button>
					</div>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th class="manage-column" style="width: 4%;"><input type="checkbox" id="wclsi_nav_add_all_items" /></th>
								<th class="manage-column" style="width: 7%;"><span class="wc-image"><?php echo __('Image', 'wcsli') ?></span></th>
								<th class="manage-column"><strong><?php echo __('Description', 'wcsli') ?></strong></th>
								<?php if ( $product_type === 'single' ) : ?>
								<th class="manage-column"><strong><?php echo __('System SKU', 'wcsli') ?></strong></th>
								<th class="manage-column"><strong><?php echo __('Custom SKU', 'wcsli') ?></strong></th>
								<th class="manage-column"><strong><?php echo __('Variation', 'wcsli') ?></strong></th>
								<?php endif; ?>
							</tr>
						</thead>
						<tbody>
						<?php 
						foreach($results->{$ls_item_type} as $ls_api_item) {
							$is_variation = isset( $ls_api_item->itemID ) && $ls_api_item->itemMatrixID > 0
							?> 
							<tr>
								<td><input type="checkbox" name="wclsi_nav_add_item_<?php echo $ls_api_item->{$ls_item_id} ?>" id="wclsi_nav_add_item_<?php echo $ls_api_item->{$ls_item_id} ?>" value="<?php echo $ls_api_item->{$ls_item_id} ?>" /></td>
								<td style="text-align: center; overflow: hidden;"><?php echo $this->get_image_html($ls_api_item, $product_type) ?></td>
								<td><?php echo $ls_api_item->description ?></td>
								<?php if ( $product_type === 'single' ) : ?>                            
								<td><?php echo $ls_api_item->systemSku ?></td>
								<td><?php echo $ls_api_item->customSku ?></td>
								<td><?php echo $is_variation ? '✓' : '' ?></td>
								<?php endif; ?>
							</tr>
						<?php } ?>
						</tbody>
					</table>
				</form>
				<?php
				$formatted_results = ob_get_clean();
			} else {
				$formatted_results = '<p>No matches found!</p>';
			}
			
			return $formatted_results;
		}
		
		private function get_image_html($ls_api_item, $product_type) {
			$matrix_attrs = $product_type !== 'single' ? 'class="matrix-img" alt="Matrix Item" title="Matrix Item"' : '';
			if ( property_exists( $ls_api_item, 'Images' ) ) {
				if ( is_object( $ls_api_item->Images ) ) {
					$ls_api_item->Images = array( $ls_api_item->Images );
				}

				$feature_img = $ls_api_item->Images[0]->Image;
				if(is_array($feature_img)){
					$feature_img = $feature_img[0];
				}
				
				$file_extension = pathinfo($feature_img->filename, PATHINFO_EXTENSION);
				if (empty($feature_img->baseImageURL) || empty($feature_img->publicID) || empty($file_extension)) {
					$img_src = wc_placeholder_img_src();    
				} else {
					$img_src = "{$feature_img->baseImageURL}w_40,c_fill/{$feature_img->publicID}.{$file_extension}"; 
				}
			} else {
				$img_src = wc_placeholder_img_src();
			}
			
			return "<img alt='{$ls_api_item->description}' src='{$img_src}' height='40' {$matrix_attrs} />";    
		}

		function add_wclsi_ls_navigator_js() {
			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
			wp_enqueue_script(
				'wclsi-ls-navigator-js',
				plugins_url( "assets/js/wclsi-ls-navigator{$suffix}.js", dirname( __FILE__ ) ),
				array( 'jquery', 'thickbox', 'wclsi-admin-js', 'jquery-blockui' ),
				WCLSI_VERSION,
				true
			);
		}

		public static function render_navigator() {
			?>
			<div id="wclsi_api_navigator">
				<div id="wclsi_api_navigator_notices">
					<div class="wclsi-notice wclsi-notice-info" style="margin-left: 0; margin-bottom: 10px;">
						<p>Search is currently limited to a max of <strong>100 items</strong>.</p>
					</div>
					<div id="wclsi-api-navigator-variation-notice" class="wclsi-notice wclsi-notice-info" style="margin-left: 0; margin-bottom: 10px;">
						<div id="wclsi-api-navigator-notice-content">
							<p>For Variation Products:</p>
							<ul>
								<li>If the variation belongs to a parent matrix product that is not listed in import table, then the plugin will automatically add the parent matrix product along with the variation.</li>
								<li>If the variation belongs to a parent matrix product that is already imported into WooCommerce, the plugin will add the variation to the parent variable WooCommerce product.</li>
							</ul>
						</div>
						<p>For more information, please refer to the <a href="https://docs.woocommerce.com/document/woocommerce-lightspeed-pos/" target="_blank">documentation</a>.</p>
					</div>
				</div>
				<div id="wclsi_api_navigator_search_bar" style="padding-top: 20px; padding-left: 10px;">
					<form id="wclsi_api_navigator_search_form">
						<label for="search_value">Search:</label> <input type="search" name="search_value" id="search_value" style="width: 40%;" />
						<input type="radio" id="search_single" name="prod_type" value="single" checked /><label for="search_single">Single</label>
						<input type="radio" id="search_matrix" name="prod_type" value="matrix" /><label for="search_matrix">Matrix</label>
						<button class="button button-secondary">Search</button>
					</form>
				</div>
				<div id="wclsi_api_navigator_search_results">&nbsp;</div>
			</div>
			<?php
		}
	}

	$WCLSI_Api_Navigator = new WCLSI_Api_Navigator();
endif; 