<?php
class WCLSI_Upgrade_To_1_4_3 {

	function __construct() {
		add_action( 'wp_ajax_wclsi_1_4_3_move_chunk', array( $this, 'wclsi_1_4_3_move_chunk' ) );
		add_action( 'wp_ajax_wclsi_1_4_3_get_total_chunk_count', array( $this, 'wclsi_1_4_3_get_total_chunk_count' ) );
		add_action( 'wp_ajax_wclsi_1_4_3_complete_upgrade', array( $this, 'wclsi_1_4_3_complete_upgrade') );
	}

	function wclsi_1_4_3_complete_upgrade(){
		wclsi_verify_nonce();

		// Unschedule WP Cron so new one can kick in
		$timestamp = wp_next_scheduled( 'wclsi_daily_sync' );
		if( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, 'wclsi_daily_sync' );
		}

		$success = update_option( 'wclsi_upgraded_1_4_3', true );
		$success = $success && update_option( 'wclsi_version', '1.4.3' );

		echo json_encode( array( 'success' => $success ) );
		exit;
	}

	function wclsi_1_4_3_get_total_chunk_count(){
		wclsi_verify_nonce();

		$total_matrix_chunks = get_option( WCLSI_TOTAL_MATRIX_CHUNKS );
		$total_prod_chunks = get_option( WCLSI_TOTAL_PROD_CHUNKS );
		$total_cat_chunks = get_option( WCLSI_TOTAL_CAT_CHUNKS );
		echo json_encode(
			array(
				'total_prod_chunks' => empty( $total_prod_chunks ) ? 0 : $total_prod_chunks,
				'total_matrix_chunks' => empty( $total_matrix_chunks ) ? 0 : $total_matrix_chunks,
				'total_cat_chunks' => empty( $total_cat_chunks ) ? 0 : $total_cat_chunks
			)
		);
		exit;
	}

	function wclsi_1_4_3_move_chunk(){
		wclsi_verify_nonce();

		global $wpdb, $EZSQL_ERROR;

		if( WP_DEBUG ) {
			$wpdb->show_errors();
		}

		$chunk_id = (int) $_POST['chunk_id'];

		if( $_POST['importType'] == 'matrix' ) {
			$this->move_matrix_chunk( $chunk_id );
		} elseif ( $_POST['importType'] == 'single' ) {
			$this->move_single_prod_chunk( $chunk_id );
		} elseif( $_POST['importType'] == 'cat' ) {
			$this->move_cat_chunk( $chunk_id );
		} elseif( $_POST['importType'] == 'item_attrs' ) {
			$this->move_item_attrs_cache();
		}

		if( !empty( $EZSQL_ERROR ) ) {
			echo json_encode( array( 'errors' => $EZSQL_ERROR ) );
		} else {
			echo json_encode( array( 'success' => true ) );
		}

		exit;
	}

	function move_item_attrs_cache() {
		$wclsi_attrs_cache = get_option( WCLSI_ATTRS_CACHE );
		if ( false !== $wclsi_attrs_cache ) {
			foreach( $wclsi_attrs_cache->item_attr_sets as $item_attr_set ) {
				WCLSI_Item_Attributes::insert_or_update_item_attribute_set( $item_attr_set );
			}
		}
	}

	function move_cat_chunk( $chunk_id ) {
		$wclsi_cat_chunk = get_option( WCLSI_CAT_CHUNK_PREFIX . $chunk_id );
		if ( false !== $wclsi_cat_chunk ) {
			foreach ( $wclsi_cat_chunk->categories as $ls_category ) {
				LSI_Import_Categories::insert_ls_api_cat($ls_category);
			}
		}
	}

	function move_matrix_chunk( $chunk_id ) {
		$wclsi_matrix_chunk = get_option( WCLSI_MATRIX_CHUNK_PREFIX . $chunk_id );
		if ( false !== $wclsi_matrix_chunk ) {
			foreach ( $wclsi_matrix_chunk->matrix_items as $matrix_item ) {
				$this->move_wclsi_item_fields( $matrix_item );
				WCLSI_Lightspeed_Prod::insert_ls_api_item( $matrix_item );
			}
		}
	}

	function move_single_prod_chunk( $chunk_id ) {
		$wclsi_prod_chunk = get_option( WCLSI_PROD_CHUNK_PREFIX . $chunk_id );
		if ( false !== $wclsi_prod_chunk ) {
			foreach ( $wclsi_prod_chunk->Item as $item ) {
				$this->move_wclsi_item_fields( $item );
				WCLSI_Lightspeed_Prod::insert_ls_api_item( $item );
			}
		}
	}

	function move_all_items(){
		$total_matrix_chunks = get_option( WCLSI_TOTAL_MATRIX_CHUNKS );
		$total_prod_chunks = get_option( WCLSI_TOTAL_PROD_CHUNKS );

		for ( $i = 0; $i < $total_matrix_chunks; $i ++ ) {
			$wclsi_matrix_chunk = get_option( WCLSI_MATRIX_CHUNK_PREFIX . $i );
			if ( false !== $wclsi_matrix_chunk ) {
				foreach ( $wclsi_matrix_chunk->matrix_items as $matrix_item ) {
					$this->move_wclsi_item_fields( $item );
					WCLSI_Lightspeed_Prod::insert_ls_api_item( $matrix_item );
				}
			}
		}

		for ( $i = 0; $i < $total_prod_chunks; $i ++ ) {
			$wclsi_prod_chunk = get_option( WCLSI_PROD_CHUNK_PREFIX . $i );
			if ( false !== $wclsi_prod_chunk ) {
				foreach ( $wclsi_prod_chunk->Item as $item ) {
					$this->move_wclsi_item_fields( $item );
					WCLSI_Lightspeed_Prod::insert_ls_api_item( $item );
				}
			}
		}
	}

	private function move_wclsi_item_fields( &$item ) {
		if( isset( $item->wc_prod_id ) ) {
			$wc_prod_id = $item->wc_prod_id;
			$is_synced = get_post_meta( $wc_prod_id, WCLSI_SYNC_POST_META, true );
			$item->wclsi_is_synced = $is_synced;
		}

		if( isset( $item->last_import ) ) {
			$item->wclsi_import_date = $item->last_import;
		}

		if( isset( $item->last_sync_date ) ) {
			$item->wclsi_last_sync_date = $item->last_sync_date;
		}
	}
}

new WCLSI_Upgrade_To_1_4_3();
