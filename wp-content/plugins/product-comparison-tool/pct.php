<?php
/*
Plugin Name: RuffMutts Product Comparison Tool
Description: Establishes relationship between wordpress and database for a pseudo-product-type (also known as a custom-post-type in wordpress). This advanced custom tool is copyright and owned by RuffMutts. Automatic updates will not be availible for this specific plugin and for security it's folder will not be writable.

Version: 1.0.66
Author: Han Ming
Author URI: https://www.choice.marketing/websites
License: GPL3

****************************************************************************************** 
	Copyright (C) 2021-today RuffMutts.ca

	This program is distributed WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

*******************************************************************************************/

function ruffmutts_register_my_cpts() {

	/**
	 * Post Type: Comparisons.
	 */

	$labels = [
		"name" => __( "Comparisons", "hello-elementor" ),
		"singular_name" => __( "Compare", "hello-elementor" ),
		"menu_name" => __( "Competitor Products", "hello-elementor" ),
		"all_items" => __( "All comparisons", "hello-elementor" ),
		"add_new" => __( "Add new", "hello-elementor" ),
		"add_new_item" => __( "Add new compare", "hello-elementor" ),
		"edit_item" => __( "Edit compare", "hello-elementor" ),
		"new_item" => __( "New compare", "hello-elementor" ),
		"view_item" => __( "View compare", "hello-elementor" ),
		"view_items" => __( "View comparisons", "hello-elementor" ),
		"search_items" => __( "Search comparisons", "hello-elementor" ),
		"not_found" => __( "No comparisons found", "hello-elementor" ),
		"not_found_in_trash" => __( "No comparisons found in trash", "hello-elementor" ),
		"parent" => __( "Parent compare:", "hello-elementor" ),
		"featured_image" => __( "Featured image for this compare", "hello-elementor" ),
		"set_featured_image" => __( "Set featured image for this compare", "hello-elementor" ),
		"remove_featured_image" => __( "Remove featured image for this compare", "hello-elementor" ),
		"use_featured_image" => __( "Use as featured image for this compare", "hello-elementor" ),
		"archives" => __( "compare archives", "hello-elementor" ),
		"insert_into_item" => __( "Insert into compare", "hello-elementor" ),
		"uploaded_to_this_item" => __( "Upload to this compare", "hello-elementor" ),
		"filter_items_list" => __( "Filter comparisons list", "hello-elementor" ),
		"items_list_navigation" => __( "comparisons list navigation", "hello-elementor" ),
		"items_list" => __( "comparisons list", "hello-elementor" ),
		"attributes" => __( "comparisons attributes", "hello-elementor" ),
		"name_admin_bar" => __( "compare", "hello-elementor" ),
		"item_published" => __( "compare published", "hello-elementor" ),
		"item_published_privately" => __( "compare published privately.", "hello-elementor" ),
		"item_reverted_to_draft" => __( "compare reverted to draft.", "hello-elementor" ),
		"item_scheduled" => __( "compare scheduled", "hello-elementor" ),
		"item_updated" => __( "compare updated.", "hello-elementor" ),
		"parent_item_colon" => __( "Parent compare:", "hello-elementor" ),
	];

	$args = [
		"label" => __( "Comparisons", "hello-elementor" ),
		"labels" => $labels,
		"description" => "Competitor product information to help you compare the benefits of natural healthy pet foods in Canada.",
		"public" => true,
		"publicly_queryable" => true,
		"show_ui" => true,
		"show_in_rest" => true,
		"rest_base" => "",
		"rest_controller_class" => "WP_REST_Posts_Controller",
		"has_archive" => false,
		"show_in_menu" => true,
		"show_in_nav_menus" => true,
		"delete_with_user" => false,
		"exclude_from_search" => false,
		"capability_type" => "product",
		"map_meta_cap" => true,
		"hierarchical" => false,
		"rewrite" => [ "slug" => "comparison", "with_front" => true ],
		"query_var" => true,
		"menu_icon" => "dashicons-pets",
		"supports" => [ "title", "thumbnail", "custom-fields" ],
		"taxonomies" => [ "product_cat" ],
		"show_in_graphql" => false,
	];

	register_post_type( "comparison", $args );
}

add_action( 'init', 'ruffmutts_register_my_cpts' );





function ruffmutts_register_my_taxes() {

	/**
	 * Taxonomy: Stores.
	 */

	$labels = [
		"name" => __( "Stores", "hello-elementor" ),
		"singular_name" => __( "store", "hello-elementor" ),
		"menu_name" => __( "Competitor Stores", "hello-elementor" ),
		"all_items" => __( "All Stores", "hello-elementor" ),
		"edit_item" => __( "Edit store", "hello-elementor" ),
		"view_item" => __( "View store", "hello-elementor" ),
		"update_item" => __( "Update store name", "hello-elementor" ),
		"add_new_item" => __( "Add new store", "hello-elementor" ),
		"new_item_name" => __( "New store name", "hello-elementor" ),
		"parent_item" => __( "Parent store", "hello-elementor" ),
		"parent_item_colon" => __( "Parent store:", "hello-elementor" ),
		"search_items" => __( "Search Stores", "hello-elementor" ),
		"popular_items" => __( "Popular Stores", "hello-elementor" ),
		"separate_items_with_commas" => __( "Separate Stores with commas", "hello-elementor" ),
		"add_or_remove_items" => __( "Add or remove Stores", "hello-elementor" ),
		"choose_from_most_used" => __( "Choose from the most used Stores", "hello-elementor" ),
		"not_found" => __( "No Stores found", "hello-elementor" ),
		"no_terms" => __( "No Stores", "hello-elementor" ),
		"items_list_navigation" => __( "Stores list navigation", "hello-elementor" ),
		"items_list" => __( "Stores list", "hello-elementor" ),
		"back_to_items" => __( "Back to Stores", "hello-elementor" ),
	];

	
	$args = [
		"label" => __( "Stores", "hello-elementor" ),
		"labels" => $labels,
		"public" => true,
		"publicly_queryable" => true,
		"hierarchical" => false,
		"show_ui" => true,
		"show_in_menu" => true,
		"show_in_nav_menus" => true,
		"query_var" => true,
		"rewrite" => [ 'slug' => 'store', 'with_front' => true, ],
		"show_admin_column" => false,
		"show_in_rest" => true,
		"rest_base" => "store",
		"rest_controller_class" => "WP_REST_Terms_Controller",
		"show_in_quick_edit" => false,
		"show_in_graphql" => false,
	];
	register_taxonomy( "store", [ "comparison" ], $args );

	/**
	 * Taxonomy: Life Stages.
	 */

	$labels = [
		"name" => __( "Life Stages", "hello-elementor" ),
		"singular_name" => __( "Life Stage", "hello-elementor" ),
		"menu_name" => __( "Life Stages", "hello-elementor" ),
		"all_items" => __( "All Life Stages", "hello-elementor" ),
		"edit_item" => __( "Edit Life Stage", "hello-elementor" ),
		"view_item" => __( "View Life Stage", "hello-elementor" ),
		"update_item" => __( "Update Life Stage name", "hello-elementor" ),
		"add_new_item" => __( "Add new Life Stage", "hello-elementor" ),
		"new_item_name" => __( "New Life Stage name", "hello-elementor" ),
		"parent_item" => __( "Parent Life Stage", "hello-elementor" ),
		"parent_item_colon" => __( "Parent Life Stage:", "hello-elementor" ),
		"search_items" => __( "Search Life Stages", "hello-elementor" ),
		"popular_items" => __( "Popular Life Stages", "hello-elementor" ),
		"separate_items_with_commas" => __( "Separate Life Stages with commas", "hello-elementor" ),
		"add_or_remove_items" => __( "Add or remove Life Stages", "hello-elementor" ),
		"choose_from_most_used" => __( "Choose from the most used Life Stages", "hello-elementor" ),
		"not_found" => __( "No Life Stages found", "hello-elementor" ),
		"no_terms" => __( "No Life Stages", "hello-elementor" ),
		"items_list_navigation" => __( "Life Stages list navigation", "hello-elementor" ),
		"items_list" => __( "Life Stages list", "hello-elementor" ),
		"back_to_items" => __( "Back to Life Stages", "hello-elementor" ),
	];

	
	$args = [
		"label" => __( "Life Stages", "hello-elementor" ),
		"labels" => $labels,
		"public" => true,
		"publicly_queryable" => true,
		"hierarchical" => false,
		"show_ui" => true,
		"show_in_menu" => true,
		"show_in_nav_menus" => true,
		"query_var" => true,
		"rewrite" => [ 'slug' => 'life_stage', 'with_front' => true, ],
		"show_admin_column" => false,
		"show_in_rest" => true,
		"rest_base" => "life_stage",
		"rest_controller_class" => "WP_REST_Terms_Controller",
		"show_in_quick_edit" => false,
		"show_in_graphql" => false,
	];
	register_taxonomy( "life_stage", [ "product", "comparison" ], $args );

	/**
	 * Taxonomy: Primary Ingredients.
	 */

	$labels = [
		"name" => __( "Primary Ingredients", "hello-elementor" ),
		"singular_name" => __( "Primary Ingredient", "hello-elementor" ),
		"menu_name" => __( "Primary Ingredients", "hello-elementor" ),
		"all_items" => __( "All Primary Ingredients", "hello-elementor" ),
		"edit_item" => __( "Edit Primary Ingredient", "hello-elementor" ),
		"view_item" => __( "View Primary Ingredient", "hello-elementor" ),
		"update_item" => __( "Update Primary Ingredient name", "hello-elementor" ),
		"add_new_item" => __( "Add new Primary Ingredient", "hello-elementor" ),
		"new_item_name" => __( "New Primary Ingredient name", "hello-elementor" ),
		"parent_item" => __( "Parent Primary Ingredient", "hello-elementor" ),
		"parent_item_colon" => __( "Parent Primary Ingredient:", "hello-elementor" ),
		"search_items" => __( "Search Primary Ingredients", "hello-elementor" ),
		"popular_items" => __( "Popular Primary Ingredients", "hello-elementor" ),
		"separate_items_with_commas" => __( "Separate Primary Ingredients with commas", "hello-elementor" ),
		"add_or_remove_items" => __( "Add or remove Primary Ingredients", "hello-elementor" ),
		"choose_from_most_used" => __( "Choose from the most used Primary Ingredients", "hello-elementor" ),
		"not_found" => __( "No Primary Ingredients found", "hello-elementor" ),
		"no_terms" => __( "No Primary Ingredients", "hello-elementor" ),
		"items_list_navigation" => __( "Primary Ingredients list navigation", "hello-elementor" ),
		"items_list" => __( "Primary Ingredients list", "hello-elementor" ),
		"back_to_items" => __( "Back to Primary Ingredients", "hello-elementor" ),
	];

	
	$args = [
		"label" => __( "Primary Ingredients", "hello-elementor" ),
		"labels" => $labels,
		"public" => true,
		"publicly_queryable" => true,
		"hierarchical" => false,
		"show_ui" => true,
		"show_in_menu" => true,
		"show_in_nav_menus" => true,
		"query_var" => true,
		"rewrite" => [ 'slug' => 'primary_ingredient', 'with_front' => true, ],
		"show_admin_column" => false,
		"show_in_rest" => true,
		"rest_base" => "primary_ingredient",
		"rest_controller_class" => "WP_REST_Terms_Controller",
		"show_in_quick_edit" => false,
		"show_in_graphql" => false,
	];
	register_taxonomy( "primary_ingredient", [ "product", "comparison" ], $args );
}
add_action( 'init', 'ruffmutts_register_my_taxes' );


function bidirectional_acf_update_value( $value, $post_id, $field  ) {
	
	// vars
	$field_name = $field['name'];
	$field_key = $field['key'];
	$global_name = 'is_updating_' . $field_name;
	
	
	// bail early if this filter was triggered from the update_field() function called within the loop below
	// - this prevents an inifinte loop
	if( !empty($GLOBALS[ $global_name ]) ) return $value;
	
	
	// set global variable to avoid inifite loop
	// - could also remove_filter() then add_filter() again, but this is simpler
	$GLOBALS[ $global_name ] = 1;
	
	
	// loop over selected posts and add this $post_id
	if( is_array($value) ) {
	
		foreach( $value as $post_id2 ) {
			
			// load existing related posts
			$value2 = get_field($field_name, $post_id2, false);
			
			
			// allow for selected posts to not contain a value
			if( empty($value2) ) {
				
				$value2 = array();
				
			}
			
			
			// bail early if the current $post_id is already found in selected post's $value2
			if( in_array($post_id, $value2) ) continue;
			
			
			// append the current $post_id to the selected post's 'related_posts' value
			$value2[] = $post_id;
			
			
			// update the selected post's value (use field's key for performance)
			update_field($field_key, $value2, $post_id2);
			
		}
	
	}
	
	
	// find posts which have been removed
	$old_value = get_field($field_name, $post_id, false);
	
	if( is_array($old_value) ) {
		
		foreach( $old_value as $post_id2 ) {
			
			// bail early if this value has not been removed
			if( is_array($value) && in_array($post_id2, $value) ) continue;
			
			
			// load existing related posts
			$value2 = get_field($field_name, $post_id2, false);
			
			
			// bail early if no value
			if( empty($value2) ) continue;
			
			
			// find the position of $post_id within $value2 so we can remove it
			$pos = array_search($post_id, $value2);
			
			
			// remove
			unset( $value2[ $pos] );
			
			
			// update the un-selected post's value (use field's key for performance)
			update_field($field_key, $value2, $post_id2);
			
		}
		
	}
	
	
	// reset global varibale to allow this filter to function as per normal
	$GLOBALS[ $global_name ] = 0;
	
	
	// return
    return $value;
    
}

add_filter('acf/update_value/name=best_alternatives', 'bidirectional_acf_update_value', 10, 3);