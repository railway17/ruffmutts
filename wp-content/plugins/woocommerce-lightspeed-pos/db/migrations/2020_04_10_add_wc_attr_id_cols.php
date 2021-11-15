<?php
if ( ! class_exists( 'Add_Wc_Attr_Id_Cols' ) ) :

	class Add_Wc_Attr_Id_Cols {

		private $migration_version = '0.2';

		function run_migration() {
			$wclsi_db_version = get_option(WCLSI_DB_VERSION_OPTION, '0.0');
			if (version_compare($wclsi_db_version, $this->migration_version) < 0) {
				$this->add_attr_wc_id_columns();
				update_option( WCLSI_DB_VERSION_OPTION, $this->migration_version );
			}
		}

		private function column_exists( $column_name ){
			global $wpdb, $WCLSI_ITEM_ATTRIBUTE_SETS_TABLE;
			$sql = $wpdb->prepare( "SHOW COLUMNS FROM $WCLSI_ITEM_ATTRIBUTE_SETS_TABLE LIKE '%s';", $column_name );
			$row = $wpdb->get_row($sql);
			return !is_null($row);
		}
		
		private function add_attr_wc_id_columns () {
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			global $wpdb, $WCLSI_ITEM_ATTRIBUTE_SETS_TABLE;

			$results = array();

			$wc_id_columns = array(
				'attr_name_1_wc_id',
				'attr_name_2_wc_id',
				'attr_name_3_wc_id'
			);

			foreach($wc_id_columns as $column) {
				if( $this->column_exists($column) )
					continue;

				$results[] = $wpdb->query( "ALTER TABLE $WCLSI_ITEM_ATTRIBUTE_SETS_TABLE ADD COLUMN $column BIGINT(20) UNSIGNED;" );
			}

			$this->add_indexes(
				$WCLSI_ITEM_ATTRIBUTE_SETS_TABLE,
				$wc_id_columns
			);

			return $results;
		}

		private function add_indexes( $table_name, $indexes ) {
			global $wpdb;
			foreach( $indexes as $index_name ) {
				if ( !$this->index_exists( $table_name, $index_name ) ) {
					$wpdb->query( "ALTER TABLE $table_name ADD INDEX ($index_name);" );    
				}
			}   
		}
		
		private function index_exists( $table_name, $index_name ) {
			global $wpdb;
			$results = $wpdb->get_results( "SHOW INDEX FROM $table_name" );
			$indexes = array_column($results, 'Key_name');
			return in_array( $index_name, $indexes );
		}
	}

endif;
