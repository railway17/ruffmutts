<?php
/**
 * @class LSI_Import_Categories
 *
 * Handles importing LightSpeed categories into WordPress/WooCommerce
 *
 */
if ( !class_exists( 'LSI_Import_Categories' ) ) :

	class LSI_Import_Categories
	{

		function __construct() {
			add_action( 'pre_delete_term', array( $this, 'sync_deleted_cats'), 10, 2 );
			add_action( 'wp_ajax_import_ls_categories', array( $this, 'import_ls_categories') );
			add_action( 'wp_ajax_get_category_count', array( $this, 'get_category_count') );
			add_action( 'wp_ajax_clear_category_cache', array( $this, 'clear_category_cache') );
		}

		function clear_category_cache() {
			wclsi_verify_nonce();

			global $wpdb, $WCLSI_ITEM_CATEGORIES_TABLE;
			$result = $wpdb->query("TRUNCATE TABLE $WCLSI_ITEM_CATEGORIES_TABLE");
			if( true === $result ) {
				echo json_encode(
					array(
						'success' => true
					)
				);
			} else {
				echo json_encode(
					array(
						'errors' => __(
							'Could not clear the category cache! Please try again',
							'woocommerce-lightspeed-pos'
						)
					)
				);
			}

			exit;
		}

		/**
		 * Looks up LightSpeed categories, keeps record of them.
		 * @todo if a user decides to re-import, does the cache get completely overwritten?
		 */
		function import_ls_categories() {
			wclsi_verify_nonce();

			$offset = 0;
			if ( isset( $_POST['offset'] ) ) {
				$offset = (int) $_POST['offset'];
			}

			$limit = 0;
			if ( isset( $_POST['limit'] ) ) {
				$limit = (int) $_POST['limit'];
			}

			$result = $this->import_ls_categories_by_offset( $offset, $limit );

			if( is_wp_error( $result ) ) {
				echo json_encode(
					array(
						'errors' => get_settings_errors( 'wclsi_settings' )
					)
				);
				exit;
			}

			if( isset( $result->Category ) ) {
				$ls_cats = $result->Category;

				if ( is_object( $ls_cats ) ) {
					$single_ls_category = $ls_cats;
					$ls_cats = array( $single_ls_category );
				}

				foreach( $ls_cats as $ls_cat ) {
					self::insert_ls_api_cat( $ls_cat );
				}

				if ( ($offset + $limit) >= $result->{'@attributes'}->count ) { // Last chunk condition

					// Generate the categories on the last call
					$this->generate_ls_categories();

					echo json_encode(
						array(
							'loaded_chunk_' . $offset => true,
							'load_complete' => true,
							'prod_cat_link' => admin_url('edit-tags.php?taxonomy=product_cat&post_type=product'),
							'errors' => get_settings_errors( 'wclsi_settings' ),
							'WAIT_TIME' => wclsi_get_api_wait_time_ms()
						)
					);
				} else {
					echo json_encode(
						array(
							'loaded_chunk_' . $offset => true,
							'errors' => get_settings_errors( 'wclsi_settings' ),
							'WAIT_TIME' => wclsi_get_api_wait_time_ms()
						)
					);
				}
			}

			exit;
		}

		function get_category_count(){
			wclsi_verify_nonce();

			$result = $this->import_ls_categories_by_offset( 0, 1 );
			$ciel_count = 0;
			$real_count = 0;

			if ( is_wp_error( $result ) ) {
				if ( is_admin() ) {
					add_settings_error(
						'wclsi_settings',
						'wclsi-import-cat-error',
						$result->get_error_message()
					);
				}
			} else {
				$ciel_count = ceil( $result->{'@attributes'}->count / 100 ) * 100;
				$real_count = $result->{'@attributes'}->count;
			}

			$args = array(
				'ciel_count' => $ciel_count,
				'real_count' => $real_count
			);

			if ( is_admin() ) {
				$args['errors'] = get_settings_errors( 'wclsi_settings' );
			}

			echo json_encode( $args );

			exit;
		}

		function import_ls_categories_by_offset( $offset = 0, $limit = 100 ) {
			global $WCLSI_API;

			$resource = "Account/$WCLSI_API->ls_account_id/Category";
			$search_params = array(
				'offset' => $offset,
				'limit' => $limit
			);

			return $WCLSI_API->make_api_call( $resource, 'Read', $search_params );
		}

		private static function get_mysql_args( $ls_cat ) {
			$args = array(
				'category_id' => isset( $ls_cat->categoryID ) ? $ls_cat->categoryID : null,
				'name' => isset( $ls_cat->name ) ? $ls_cat->name : null,
				'wc_cat_id' => isset( $ls_cat->wc_cat_id ) ? $ls_cat->wc_cat_id : null,
				'node_depth' => isset( $ls_cat->nodeDepth ) ? $ls_cat->nodeDepth : null,
				'full_path_name' => isset( $ls_cat->fullPathName ) ? $ls_cat->fullPathName : null,
				'left_node' => isset( $ls_cat->leftNode ) ? $ls_cat->leftNode : null,
				'right_node' => isset( $ls_cat->rightNode ) ? $ls_cat->rightNode : null,
				'time_stamp' => isset( $ls_cat->timeStamp ) ? $ls_cat->timeStamp : null,
				'parent_id' => isset( $ls_cat->parentID ) ? $ls_cat->parentID : null,
				'create_time' => isset( $ls_cat->createTime ) ? $ls_cat->createTime : null,
				'created_at' => current_time('mysql'),
			);

			wclsi_format_empty_vals( $args );

			return $args;
		}

		public static function insert_ls_api_cat( $ls_cat ) {
			global $wpdb, $WCLSI_ITEM_CATEGORIES_TABLE;

			$cat_msysql_id = self::get_mysql_id( $ls_cat );

			if ( $cat_msysql_id > 0 ) {
				return $cat_msysql_id;
			}

			$args = self::get_mysql_args( $ls_cat );

			$formatting = array( '%d', '%s', '%d', '%d', '%s', '%d', '%d', '%s', '%d', '%s', '%s' );

			$wpdb->insert( $WCLSI_ITEM_CATEGORIES_TABLE, $args, $formatting );

			return $wpdb->insert_id;
		}

		/**
		 * Helper function to load existing cats instead of looking them up.
		 */
		private static function get_mysql_id( $ls_cat ) {
			global $wpdb, $WCLSI_ITEM_CATEGORIES_TABLE;
			$category_id = $ls_cat->categoryID;
			return $wpdb->get_var( "SELECT id FROM $WCLSI_ITEM_CATEGORIES_TABLE WHERE category_id=$category_id" );
		}

		/**
		 * Creates the product category hierarchy that was imported via LightSpeed.
		 */
		function generate_ls_categories() {
			global $wpdb, $WCLSI_ITEM_CATEGORIES_TABLE;

			$result = $wpdb->get_results("SELECT * FROM $WCLSI_ITEM_CATEGORIES_TABLE ORDER BY parent_id DESC");

			$indexed_ls_cats = [];
			if( !is_null( $result ) ) {
				foreach( $result as $ls_cat ) {
					$indexed_ls_cats[ $ls_cat->category_id ] = $ls_cat;
				}
			}

			foreach( $indexed_ls_cats as $ls_cat ) {
				$this->walk_cat_hierarchy( $ls_cat, $indexed_ls_cats );
			}
		}

		private function walk_cat_hierarchy( $ls_cat, &$indexed_ls_cats ) {
			if ( $ls_cat->parent_id > 0 ) {
				$parent = $indexed_ls_cats[$ls_cat->parent_id];
				$wc_cat_parent_id = $this->walk_cat_hierarchy( $parent, $indexed_ls_cats );
			} else {
				$wc_cat_parent_id = 0;
			}

			return $this->import_ls_cat( $ls_cat, $indexed_ls_cats, $wc_cat_parent_id );
		}

		private function import_ls_cat( $ls_cat, &$indexed_ls_cats, $wc_cat_parent_id = 0 ) {
			global $wpdb, $WCLSI_ITEM_CATEGORIES_TABLE;
			$slug = sanitize_title_with_dashes( str_replace( '/', '-', $ls_cat->name ) );
			$term_exists = get_term_by( 'name', $ls_cat->name, 'product_cat' );
			if ( $term_exists ) {
				if ($ls_cat->wc_cat_id == $term_exists->term_id) {
					return $ls_cat->wc_cat_id;
				} else {
					$wpdb->update(
						$WCLSI_ITEM_CATEGORIES_TABLE,
						array( 'wc_cat_id' => $term_exists->term_id ),
						array( 'id' => $ls_cat->id ),
						array( '%d' ),
						array( '%d' )
					);

					$term_id = $term_exists->term_id;
				}
			} else {
				$new_term = wp_insert_term(
					$ls_cat->name,
					'product_cat',
					array(
						'slug' => $slug,
						'parent' => $wc_cat_parent_id
					)
				);

				if ( !is_wp_error( $new_term ) ) {
					$wpdb->update(
						$WCLSI_ITEM_CATEGORIES_TABLE,
						array( 'wc_cat_id' => $new_term['term_id'] ),
						array( 'id' => $ls_cat->id ),
						array( '%d' ),
						array( '%d' )
					);

					$term_id = $new_term['term_id'];
				} else {
					if ( isset( $term->error_data['term_exists'] ) ) {
						$msg =
							"Could not import the Lightspeed category: '$ls_cat->name' - 
							a sibling category with the same name already exists under the same parent!";
					} else {
						$msg = $new_term->get_error_messages();
					}

					add_settings_error(
						'wclsi_settings',
						'wclsi_cat_import_error',
						$msg
					);

					$term_id = 0;
				}
			}

			$indexed_ls_cats[ $ls_cat->category_id ]->wc_cat_id = $term_id;

			return $term_id;
		}

		/**
		 * Keep Lightspeed cache up to date if a Woo product cat gets deleted.
		 * @param $term_id
		 * @param $taxonomy
		 */
		public function sync_deleted_cats( $term_id, $taxonomy ) {

			if ( $taxonomy != 'product_cat' ) {
				return;
			}

			global $wpdb, $WCLSI_ITEM_CATEGORIES_TABLE;

			$wpdb->query("DELETE FROM $WCLSI_ITEM_CATEGORIES_TABLE where wc_cat_id=$term_id");
		}
	}
	$LSI_Import_Categories = new LSI_Import_Categories();
endif;

