<?php
if ( !class_exists('WCLSI_Item_Attributes') ) :
	class WCLSI_Item_Attributes {
		
		const MAX_NUM_OF_ATTRIBUTES = 3;

		public $id = 0;
		public $item_attribute_set_id;
		public $name;
		public $attribute_name_1;
		public $attribute_name_2;
		public $attribute_name_3;
		public $attr_name_1_wc_id;
		public $attr_name_2_wc_id;
		public $attr_name_3_wc_id;

		function __construct( $id = 0 ){
			if ( $id > 0 ) {
				$this->init_via_item_attribute_set_id( $id );
			}
		}

		private function init_via_item_attribute_set_id( $id ) {
			global $wpdb, $WCLSI_ITEM_ATTRIBUTE_SETS_TABLE;
			
			$result = $wpdb->get_row( "SELECT * FROM $WCLSI_ITEM_ATTRIBUTE_SETS_TABLE WHERE item_attribute_set_id = $id", ARRAY_A );

			if ( !empty( $result ) ) {
				foreach ( $result as $property => $value ) {
					$this->{$property} = maybe_unserialize( $value );
				}
			}
		}

		/******* Public Static Methods *******/

		/**
		 * @param $item_attribute_set
		 */
		public static function insert_or_update_item_attribute_set( $item_attribute_set ) {
			global $wpdb, $WCLSI_ITEM_ATTRIBUTE_SETS_TABLE;

			if ( empty( $item_attribute_set ) || empty( $item_attribute_set->itemAttributeSetID ) )
				return;

			$wclsi_item_attr_id = self::get_wclsi_item_attr_id( $item_attribute_set->itemAttributeSetID );
			$db_action = $wclsi_item_attr_id > 0 ? 'update' : 'insert';
			$args = array(
				'item_attribute_set_id' => isset( $item_attribute_set->itemAttributeSetID ) ? $item_attribute_set->itemAttributeSetID : null,
				'name' => isset( $item_attribute_set->name ) ? $item_attribute_set->name : null,
				'attribute_name_1' => isset( $item_attribute_set->attributeName1 ) ? $item_attribute_set->attributeName1 : null,
				'attribute_name_2' => isset( $item_attribute_set->attributeName2 ) ? $item_attribute_set->attributeName2 : null,
				'attribute_name_3' => isset( $item_attribute_set->attributeName3 ) ? $item_attribute_set->attributeName3 : null,
				'system' => isset( $item_attribute_set->system ) ? (bool) $item_attribute_set->system : 0,
				'archived' => isset( $item_attribute_set->archived ) ? $item_attribute_set->archived : null,
				'created_at' => current_time('mysql')
			);

			wclsi_format_empty_vals( $args );
			
			if ( 'update' === $db_action ) {
				$update_format = array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s' );
				$where_args    = array( 'id' => $wclsi_item_attr_id );
				$where_format  = array( '%d' );
				
				$args['updated_at'] = current_time('mysql');
				unset( $args['created_at'] );
				
				$wpdb->update( $WCLSI_ITEM_ATTRIBUTE_SETS_TABLE, $args, $where_args, $update_format, $where_format );
				self::update_wc_attrs( $item_attribute_set );
			} else if( 'insert' === $db_action ) {
				$insert_format = array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s' );
				self::create_wc_attrs($item_attribute_set, $args);
				$wpdb->insert( $WCLSI_ITEM_ATTRIBUTE_SETS_TABLE, $args, $insert_format );
			}
		}

		private static function update_wc_attrs( $item_attribute_set ) {
			$wclsi_attr_set = new WCLSI_Item_Attributes( $item_attribute_set->itemAttributeSetID );

			if( $wclsi_attr_set->id > 0 ) {
				$wclsi_attrs = array(
					[$wclsi_attr_set->attr_name_1_wc_id, $item_attribute_set->attributeName1],
					[$wclsi_attr_set->attr_name_2_wc_id, $item_attribute_set->attributeName2],
					[$wclsi_attr_set->attr_name_3_wc_id, $item_attribute_set->attributeName3]
				);

				for( $i = 0; $i < 3; $i++ ) {
					$wc_attr_id = $wclsi_attrs[$i][0];
					$attribute_name = $wclsi_attrs[$i][1];

					if ( !empty( $attribute_name ) ) {
						$wc_attr = wc_get_attribute( $wc_attr_id );

						if ( !is_null( $wc_attr ) ) {
							// Inherit from pre-existing attribute
							wc_update_attribute( 
								$wc_attr_id, 
								array( 
									'name' => $attribute_name, 
									'type' => $wc_attr->type,
									'has_archives' => $wc_attr->has_archives,
									'order_by' => $wc_attr->order_by
								) 
							);
						} else {
							global $wpdb, $WCLSI_API, $WCLSI_ITEM_ATTRIBUTE_SETS_TABLE;

							// If setting enabled, create a new attribute, otherwise set it to null
							if( 'true' === $WCLSI_API->settings[ WCLSI_AUTOLOAD_LS_ATTRS ] ) {
								
								// Try and look up the id first before creating it
								$update_value = wc_attribute_taxonomy_id_by_name( wc_clean( $attribute_name ) );
								if ( empty( $update_value ) ) {
									$update_value = wc_create_attribute( array( 'name' =>  $attribute_name ) );
								}
							} else {
								$update_value = null;
							}

							$attr_index = $i + 1;
							$args = array( "attr_name_{$attr_index}_wc_id" => $update_value );
							$where_args = array( 'id' => $wclsi_attr_set->id );
							$where_format = array( '%d' );
							$update_format = empty( $update_value ) ? null : array( '%d' );
							$wpdb->update( $WCLSI_ITEM_ATTRIBUTE_SETS_TABLE, $args, $where_args, $update_format, $where_format );
						}
					}
				}
			}
		}

		private static function create_wc_attrs( $item_attribute_set, &$args ) {
			global $WCLSI_API;
			
			$attr_name_1_wc_id = $attr_name_2_wc_id = $attr_name_3_wc_id = null;
			
			if( 'true' === $WCLSI_API->settings[ WCLSI_AUTOLOAD_LS_ATTRS ] ) {
				if ( !empty( $item_attribute_set->attributeName1 ) ) {
					$attr_name_1_wc_id = wc_attribute_taxonomy_id_by_name( wc_clean( $item_attribute_set->attributeName1 ) );
					if ( 0 === $attr_name_1_wc_id ) {
						$attr_name_1_wc_id = wc_create_attribute( array( 'name' =>  $item_attribute_set->attributeName1 ) );    
					}
				}

				if ( !empty( $item_attribute_set->attributeName2 ) ) {
					$attr_name_2_wc_id = wc_attribute_taxonomy_id_by_name( wc_clean( $item_attribute_set->attributeName2 ) );
					if ( 0 === $attr_name_2_wc_id ) {
						$attr_name_2_wc_id = wc_create_attribute( array( 'name' => $item_attribute_set->attributeName2 ) );
					}
				}

				if ( !empty( $item_attribute_set->attributeName3 ) ) {
					$attr_name_3_wc_id = wc_attribute_taxonomy_id_by_name( wc_clean( $item_attribute_set->attributeName3 ) );
					if ( 0 === $attr_name_3_wc_id ) {
						$attr_name_3_wc_id = wc_create_attribute( array( 'name' => $item_attribute_set->attributeName3 ) );
					}
				}
			}

			$args['attr_name_1_wc_id'] = $attr_name_1_wc_id;
			$args['attr_name_2_wc_id'] = $attr_name_2_wc_id;
			$args['attr_name_3_wc_id'] = $attr_name_3_wc_id;
		}

		public static function get_wclsi_item_attr_id( $item_attribute_set_id ) {
			global $wpdb, $WCLSI_ITEM_ATTRIBUTE_SETS_TABLE;
			return $wpdb->get_var( "SELECT id FROM $WCLSI_ITEM_ATTRIBUTE_SETS_TABLE WHERE item_attribute_set_id = $item_attribute_set_id" );
		}

		/**
		 * Given an array of variation lightspeed products, returns an array where the index is the itemAttributeSetID
		 * and the values are possible attribute values
		 * @param $variations
		 * @return array
		 */
		public static function get_lightspeed_attribute_options($variations ) {
			$possible_attrs = array();

			foreach( $variations as $variation ) {
				if ( !is_null( $variation->item_attributes ) ) {
					$attr_set_id = $variation->item_attributes->itemAttributeSetID;

					// Filter
					$attr_set = new WCLSI_Item_Attributes( $attr_set_id );

					$attr_slug_1 = sanitize_title( $attr_set->attribute_name_1 );
					$attr_slug_2 = sanitize_title( $attr_set->attribute_name_2 );
					$attr_slug_3 = sanitize_title( $attr_set->attribute_name_3 );

					// Grab the possible attribute values
					$item_attrs = array(
						$variation->item_attributes->attribute1,
						$variation->item_attributes->attribute2,
						$variation->item_attributes->attribute3
					);

					if ( !isset( $possible_attrs[ $attr_set_id ] ) ) {
						if ( $attr_slug_1 !== '' ) {
							$possible_attrs[ $attr_set_id ][ $attr_slug_1 ] = array( $item_attrs[0] );
						}
						if ( $attr_slug_2 !== '' ) {
							$possible_attrs[ $attr_set_id ][ $attr_slug_2 ] = array( $item_attrs[1] );
						}
						if ( $attr_slug_3 !== '' ) {
							$possible_attrs[ $attr_set_id ][ $attr_slug_3 ] = array( $item_attrs[2] );
						}
					} else {
						if ( isset( $possible_attrs[ $attr_set_id ][ $attr_slug_1 ] ) ) {
							array_push( $possible_attrs[ $attr_set_id ][ $attr_slug_1 ], $item_attrs[0] );
						}
						if ( isset( $possible_attrs[ $attr_set_id ][ $attr_slug_2 ] ) ) {
							array_push( $possible_attrs[ $attr_set_id ][ $attr_slug_2 ], $item_attrs[1] );
						}
						if ( isset( $possible_attrs[ $attr_set_id ][ $attr_slug_3 ] ) ) {
							array_push( $possible_attrs[ $attr_set_id ][ $attr_slug_3 ], $item_attrs[2] );
						}
					}
				}
			}

			// Make values unique
			foreach( $possible_attrs as $id => $attribute_set ) {
				foreach( $attribute_set as $attr_slug => $attr_val ) {
					$possible_attrs[ $id ][ $attr_slug ] = array_unique( $possible_attrs[ $id ][ $attr_slug ] );
				}
			}

			return $possible_attrs;
		}

		/**
		 * @param WC_Product_Variation $wc_variation_prod
		 * @param $wclsi_variation
		 */
		public static function set_attributes_for_wc_variation(WC_Product_Variation &$wc_variation_prod, $wclsi_variation ) {
			if ( empty( $wclsi_variation->item_attributes ) )
				return;

			if ( isset( $wclsi_variation->item_attributes ) && empty( $wclsi_variation->item_attributes->itemAttributeSetID ) )
				return;

			$attr_set = new WCLSI_Item_Attributes( $wclsi_variation->item_attributes->itemAttributeSetID );

			if( 0 === $attr_set->id )
				return;

			$wc_parent_prod = wc_get_product( $wc_variation_prod->get_parent_id() );
			$wc_parent_attrs = $wc_parent_prod->get_attributes();
			$attributes = array();

			for( $i = 1; $i <= self::MAX_NUM_OF_ATTRIBUTES; $i++ ) {
				
				/**
				 * Try and get the product attribute from the parent product to make sure
				 * that the variation attribute we are setting is valid
				 */
				$attr_key = sanitize_title( $attr_set->{"attribute_name_{$i}"} );
				$attr_option = $wclsi_variation->item_attributes->{"attribute{$i}"};
				$wc_prod_attr = self::get_product_attribute( $attr_key, $wc_parent_attrs );
				
				if ( is_a( $wc_prod_attr,  'WC_Product_Attribute') ) {
					$wc_prod_attr_option_slugs = $wc_prod_attr->get_slugs();
					
					// Verify the option exists in the product attributes
					if( !in_array( $attr_option, $wc_prod_attr_option_slugs ) && 
						!in_array( sanitize_title( $attr_option ), $wc_prod_attr_option_slugs ) ) {
						
						/**
						 * The option does not exist! 
						 * Let's add it to the product attribute and then save it on the parent product.
						 */
						
						$options = $wc_prod_attr_option_slugs;
						
						/**
						 * Append the option to the existing ones - if it's not a taxonomy, we just use the name of 
						 * the attribute, otherwise we need a term ID.
						 * get_term_attribute_options() should generate a new term ID for an option that does not exist yet
						 */
						$options[] = $attr_option;
						if ( $wc_prod_attr->is_taxonomy() ) {
							$attr_key = $wc_prod_attr->get_name();
							$options = self::get_prod_attribute_option_ids( $options, $wc_prod_attr->get_taxonomy(), 'slug' );                     
						}

						$wc_prod_attr->set_options( $options );
						$wc_parent_attrs[ $attr_key ] = clone $wc_prod_attr;
						
						// Update the parent with the new attribute and the new options
						$wc_parent_prod->set_attributes( $wc_parent_attrs );
						$wc_parent_prod->save();
					}

					if ( $wc_prod_attr->is_taxonomy() ) {
						$attr_key = $wc_prod_attr->get_name();
						$attributes[ $attr_key ] = sanitize_title($attr_option);
					} else {
						$attributes[ $attr_key ] = $attr_option;
					}
				} else {
					/**
					 * The wc product attribute does not exist for this Lightspeed attribute...
					 * Try and fill out some values anyway ...
					 */
					if ( is_null( $attr_set->{"attribute_name_{$i}"} ) ) {
						$attr_slug = "attribute_name_{$i}";
					} else {
						$attr_slug = sanitize_title( $attr_set->{"attribute_name_{$i}"} );
					}

					$attributes[ $attr_slug ] = $attr_option;
				}
			}

			$wc_variation_prod->set_attributes( $attributes );
			$wc_variation_prod->save();
		}

		private static function get_product_attribute( $raw_key, $wc_product_attributes ) {
			$prod_attribute = null;
			
			if ( isset( $wc_parent_attrs[ $raw_key ] ) ) {
				$prod_attribute = $wc_product_attributes[ $raw_key ];
			}

			$taxonomy_key = "pa_{$raw_key}";
			if ( isset( $wc_product_attributes[ $taxonomy_key ] ) ) {
				$prod_attribute = $wc_product_attributes[ $taxonomy_key ];
			}

			return $prod_attribute;
		}
		
		/**
		 * Gets a wc product attribute term options, inserts new ones from Lightspeed if it can't find them
		 * @param $wclsi_options
		 * @param $wc_attr_term_slug
		 * @param string $get_term_by defaults to 'name', 'slug' is also an option
		 * @return array
		 */
		private static function get_prod_attribute_option_ids($wclsi_options, $wc_attr_term_slug, $get_term_by = 'name' ) {
			$wc_term_options = array();
			$errors = [];

			foreach( $wclsi_options as $key => $wclsi_option_name ) {
				if ( empty( $wclsi_option_name ) )
					continue;

				$wc_option_term = get_term_by( $get_term_by, $wclsi_option_name, $wc_attr_term_slug );
				if ( false === $wc_option_term ) {
					// If this is a new option, add it to the parent wc attribute term
					$result = wp_insert_term( (string) $wclsi_option_name, $wc_attr_term_slug );
					
					if ( is_wp_error($result) ) {
						$errors[] = $result->get_error_message();
					} else {
						$term_id = $result['term_id'];    
					}
				} else {
					$term_id = $wc_option_term->term_id;
				}

				$wc_term_options[] = $term_id;
			}
			
			if ( !empty( $errors ) ) {
				if ( is_admin() ) {
					add_settings_error(
						'wclsi_settings',
						'wclsi_get_prod_attribute_option_error',
						join(', ', $errors),
						'error'
					);
				}
				
				wclsi_log_error(
					join(', ', $errors),
					array(
						'$wclsi_options' => $wclsi_options,
						'$wc_attr_term_slug' => $wc_attr_term_slug
					)
				);
			}

			return $wc_term_options;
		}

		public static function set_product_attributes_for_variable_prod($wclsi_attr_set_id, WC_Product_Variable $variable_product, $attr_values ) {
			$attr_set = new WCLSI_Item_Attributes( $wclsi_attr_set_id );

			if ( $attr_set->id > 0 ) {
				$attributes = array();
				$pre_existing_wc_attrs = $variable_product->get_attributes();

				for( $i = 1; $i <= self::MAX_NUM_OF_ATTRIBUTES; $i++ ) {
					$name = $attr_set->{"attribute_name_{$i}"};

					if ( !is_null( $name ) ) {
						$wclsi_slug = sanitize_title( $name );

						// Try and get the associated wc_attribute_term_id, otherwise try and look it up by name
						$wc_attr_id = !empty( $attr_set->{"attr_name_{$i}_wc_id"} ) ? (int) $attr_set->{"attr_name_{$i}_wc_id"} : wc_attribute_taxonomy_id_by_name( $wclsi_slug );
						$wclsi_options = $attr_values[ $wclsi_attr_set_id ][ $wclsi_slug ];
						$wc_attr_term = wc_get_attribute( $wc_attr_id );

						// Find the pre-existing wc_attributes (both custom and taxanomy-related)
						if( !is_null( $wc_attr_term ) ) {
							// Term attributes
							$pre_existing_wc_attr = self::get_pre_existing_wc_attr( $pre_existing_wc_attrs, $wc_attr_term->slug );

							// Create new options if we need to
							$wc_term_options = WCLSI_Item_Attributes::get_prod_attribute_option_ids( $wclsi_options, $wc_attr_term->slug );

							if ( empty( $pre_existing_wc_attr ) ) {
								$new_wc_attr = self::new_wc_product_attribute( $wc_attr_term->id, $wc_attr_term->slug, $wc_term_options, $i );

								/**
								 * There could be an edge case where we are converting a custom attribute to a wc_term
								 * so let's try and inherit the position, visibility and variation settings
								 */
								$custom_attr_lookup = sanitize_title( $wc_attr_term->name );
								$pre_existing_custom_attr = self::get_pre_existing_wc_attr( $pre_existing_wc_attrs, $custom_attr_lookup );
								if ( $pre_existing_custom_attr ) {
									$new_wc_attr->set_position( $pre_existing_custom_attr->get_position() );
									$new_wc_attr->set_variation( $pre_existing_custom_attr->get_variation() );
									$new_wc_attr->set_visible( $pre_existing_custom_attr->get_visible() );

									/**
									 * Get rid of the custom attribute around once we've converted it
									 */
									unset( $pre_existing_wc_attrs[ $custom_attr_lookup ] );
								}

								$attributes[] = $new_wc_attr;
							} else {
								$pre_existing_options = $pre_existing_wc_attr->get_options();
								$pre_existing_wc_attr->set_options( array_unique( array_merge( $pre_existing_options, $wc_term_options ) ) );
								$attributes[] = $pre_existing_wc_attr;

								/**
								 * Lingering custom attributes that match the wc_attr_term, no need to keep them around
								 */
								$custom_attr_lookup = sanitize_title( $wc_attr_term->name );
								if ( isset( $pre_existing_wc_attrs[ $custom_attr_lookup ] ) ) {
									unset( $pre_existing_wc_attrs[ $custom_attr_lookup ] );
								}
							}
						} else {
							// Custom attributes
							$pre_existing_wc_attr = self::get_pre_existing_wc_attr( $pre_existing_wc_attrs, $wclsi_slug );
							if ( empty( $pre_existing_wc_attr ) ) {
								$attributes[] = self::new_wc_product_attribute( 0, $wclsi_slug, $wclsi_options, $i );
							} else {
								$pre_existing_options = $pre_existing_wc_attr->get_options();
								$pre_existing_wc_attr->set_options( array_unique( array_merge( $pre_existing_options, $wclsi_options ) ) );
								$attributes[] = $pre_existing_wc_attr;
							}
						}
					}
				}

				$variable_product->set_attributes( array_merge( $pre_existing_wc_attrs, $attributes ) );
				$variable_product->save();
			}
		}

		/**
		 * Given a Woo product's attributes, try and find attributes using either the taxonomy way with a 'pa_` prefix,
		 * or try to sanitize the name for custom attributes.
		 * @param $attributes
		 * @param $wc_attr_term_slug
		 * @return mixed|string
		 */
		private static function get_pre_existing_wc_attr( $attributes, $wc_attr_term_slug ) {
			// Try to use the slug first for taxonomy terms
			if( isset( $attributes[ $wc_attr_term_slug ] ) ) {
				/**
				 * Return a clone here so changes can persist in the WC data store,
				 * if we use set_options() or other setters, the data store will not detect the changes since they
				 * are being made directly on the attribute
				 */
				return clone $attributes[ $wc_attr_term_slug ];
			}

			return '';
		}

		/**
		 * id = 0 means we are creating a custom attribute
		 * @param int $id
		 * @param string $name
		 * @param array $options
		 * @param int $position
		 * @return WC_Product_Attribute
		 */
		private static function new_wc_product_attribute( $id, $name, $options, $position ) {
			$new_custom_attr = new WC_Product_Attribute();
			$new_custom_attr->set_id( $id );
			$new_custom_attr->set_name( sanitize_title( $name ) );
			$new_custom_attr->set_position( $position );
			$new_custom_attr->set_options( $options );
			$new_custom_attr->set_variation( true );
			$new_custom_attr->set_visible( true );
			return $new_custom_attr;
		}
	}
endif;
