<?php
if ( ! class_exists( 'Create_WCLSI_Tables' ) ) :

	class Create_WCLSI_Tables {

		private $migration_version = '0.1';

		function __construct() {}

		function run_migration(){
			$wclsi_db_version = get_option(WCLSI_DB_VERSION_OPTION, '0.0');
			if ( version_compare( $wclsi_db_version, $this->migration_version ) < 0 ) {
				$this->create_wclsi_tables();
				update_option( WCLSI_DB_VERSION_OPTION, $this->migration_version );
			}
		}

		function create_wclsi_tables(){

			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			$this->create_wclsi_items_table();
			$this->create_wclsi_item_shops();
			$this->create_wclsi_item_prices();
			$this->create_wclsi_item_images();
			$this->create_wclsi_item_categories();
			$this->create_wclsi_item_attribute_sets();
			$this->create_wclsi_item_e_commerce();
		}

		private function table_exists( $table_name ){
			global $wpdb;
			$sql = $wpdb->prepare( "SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s;", DB_NAME, $table_name );
			$row = $wpdb->get_row($sql);
			return !is_null($row);
		}

		/**
		 * item_id - if product is simple, use this id
		 * item_matrix_id - if product is matrix, use item_matrix_id
		 * matrix_parent_id - if the product is a variation, fill in the matrix_parent_id
		 * custom_id - a custom ID 3rd party devs can use to query by
		 * custom_value - a custom value 3rd party can use to store serialized data in
		 */
		function create_wclsi_items_table(){
			global $wpdb;

			$table_name = $wpdb->prefix . "wclsi_items";

			$this->wclsi_items_table = $table_name;

			if ( $this->table_exists( $table_name ) ) { return; }

			$charset_collate = $wpdb->get_charset_collate();

			$sql =
				"CREATE TABLE $table_name (
					id                    BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
					item_id               BIGINT(20) UNSIGNED UNIQUE,
					item_matrix_id        BIGINT(20) UNSIGNED,
					wc_prod_id            BIGINT(20) UNSIGNED UNIQUE,
					wclsi_import_date     DATETIME,
					wclsi_last_sync_date  DATETIME,
					wclsi_is_synced		  TINYINT(1),
					system_sku            BIGINT(20) UNSIGNED,
					custom_sku            VARCHAR(255),
					manufacturer_sku      VARCHAR(255),
					default_cost          DECIMAL(20,2),
					avg_cost              DECIMAL(20,2),
					discountable          TINYINT(1) DEFAULT 0,
					tax                   TINYINT(1) DEFAULT 0,
					archived              TINYINT(1) DEFAULT 0,
					item_type             VARCHAR(255),
					serialized            TINYINT(1) DEFAULT 0,
					description           TEXT,
					model_year            VARCHAR(255),
					upc                   INT(18),
					ean                   INT(18),
					create_time           DATETIME,
					time_stamp            DATETIME,
					category_id           BIGINT(20) UNSIGNED,
					tax_class_id          BIGINT(20) UNSIGNED,
					department_id         BIGINT(20) UNSIGNED,
					manufacturer_id       BIGINT(20) UNSIGNED,
					season_id             BIGINT(20) UNSIGNED,
					default_vendor_id     BIGINT(20) UNSIGNED,
					item_e_commerce_id    BIGINT(20) UNSIGNED,
					item_attribute_set_id BIGINT(20) UNSIGNED,
					item_attributes       LONGTEXT,
					tags                  LONGTEXT,
					custom_field_values   LONGTEXT,
					custom_id             BIGINT(20) UNSIGNED,
					custom_value          LONGTEXT,
					updated_at            TIMESTAMP,
					created_at            TIMESTAMP,
					PRIMARY KEY(id)
				 ) $charset_collate;			
				";
			dbDelta($sql);

			$this->add_indexes(
				$table_name,
				array(
					'category_id',
					'custom_id',
					'item_matrix_id',
					'default_cost',
					'custom_sku',
					'wclsi_last_sync_date',
					'wclsi_import_date',
					'wclsi_is_synced',
					'item_attribute_set_id'
				)
			);
		}

		function create_wclsi_item_images(){
			global $wpdb;
			$table_name = $wpdb->prefix . "wclsi_item_images";

			if ( $this->table_exists( $table_name ) ) { return; }

			$charset_collate = $wpdb->get_charset_collate();

			$sql =
				"CREATE TABLE $table_name (
					id BIGINT(20) NOT NULL AUTO_INCREMENT,
					wclsi_item_id BIGINT(20) UNSIGNED NOT NULL,
					wp_attachment_id BIGINT(20) UNSIGNED UNIQUE,
					item_id BIGINT(20) UNSIGNED,
					item_matrix_id BIGINT(20) UNSIGNED,
					image_id BIGINT(20) UNSIGNED,
					description TEXT,
					filename VARCHAR(255),
					ordering INT(11) UNSIGNED,
					public_id VARCHAR(255),
					base_image_url VARCHAR(255),
					size INT(11),
					create_time DATETIME,
					time_stamp DATETIME,
					updated_at TIMESTAMP,
					created_at TIMESTAMP,
					PRIMARY KEY(id),
					UNIQUE KEY(image_id)					
				) $charset_collate;
				";
			dbDelta($sql);

			$this->add_indexes(
				$table_name,
				array(
					'item_id',
					'item_matrix_id',
					'image_id',
					'wclsi_item_id'
				)
			);
		}

		function create_wclsi_item_shops(){
			global $wpdb;
			$table_name = $wpdb->prefix . "wclsi_item_shops";

			if ( $this->table_exists( $table_name ) ) { return; }

			$charset_collate = $wpdb->get_charset_collate();

			$sql =
				"CREATE TABLE $table_name (
					id BIGINT(20) NOT NULL AUTO_INCREMENT,
					wclsi_item_id BIGINT(20) UNSIGNED NOT NULL,
					item_shop_id BIGINT(20) UNSIGNED,
					qoh INT(11) UNSIGNED,
					backorder INT(11) UNSIGNED,
					component_qoh INT(11) UNSIGNED,
					component_backorder INT(11) UNSIGNED,
					reorder_point INT(11) UNSIGNED,
					reorder_level INT(11) UNSIGNED,
					time_stamp DATETIME,
					item_id BIGINT(20) UNSIGNED,
					shop_id BIGINT(20) UNSIGNED,
					metadata LONGTEXT,
					updated_at TIMESTAMP,
					created_at TIMESTAMP,	
					PRIMARY KEY(id),
					UNIQUE KEY(item_shop_id)					
				) $charset_collate;
				";

			dbDelta($sql);

			$this->add_indexes(
				$table_name,
				array(
					'item_id',
					'shop_id',
					'wclsi_item_id',
					'qoh'
				)
			);
		}

		function create_wclsi_item_prices(){
			global $wpdb;
			$table_name = $wpdb->prefix . "wclsi_item_prices";

			if ( $this->table_exists( $table_name ) ) { return; }

			$charset_collate = $wpdb->get_charset_collate();

			$sql =
				"CREATE TABLE $table_name (
					id BIGINT(20) NOT NULL AUTO_INCREMENT,
					wclsi_item_id BIGINT(20) UNSIGNED NOT NULL,
					amount DECIMAL(20,2),
					use_type_id INT(11),
					use_type VARCHAR(255),
					updated_at TIMESTAMP,
					created_at TIMESTAMP,
					PRIMARY KEY(id)
				) $charset_collate;
				";

			dbDelta($sql);

			$this->add_indexes( $table_name, array( 'wclsi_item_id') );
		}

		function create_wclsi_item_categories(){
			global $wpdb;
			$table_name = $wpdb->prefix . "wclsi_item_categories";

			if ( $this->table_exists( $table_name ) ) { return; }

			$charset_collate = $wpdb->get_charset_collate();

			$sql =
				"CREATE TABLE $table_name (
					id BIGINT(20) NOT NULL AUTO_INCREMENT,
					wc_cat_id BIGINT(20),
					category_id BIGINT(20) NOT NULL UNIQUE,
					name VARCHAR(255),
					node_depth INT(11),
					full_path_name VARCHAR(255),
					left_node BIGINT(20),
					right_node BIGINT(20),
					create_time DATETIME,
					time_stamp DATETIME,
					parent_id BIGINT(20),
					updated_at TIMESTAMP,
					created_at TIMESTAMP,
					PRIMARY KEY(id)
				) $charset_collate;
				";

			dbDelta($sql);

			$this->add_indexes(
				$table_name,
				array(
					'parent_id',
					'name',
					'wc_cat_id'
				)
			);
		}

		function create_wclsi_item_attribute_sets(){
			global $wpdb;
			$table_name = $wpdb->prefix . "wclsi_item_attribute_sets";

			if ( $this->table_exists( $table_name ) ) { return; }

			$charset_collate = $wpdb->get_charset_collate();

			$sql =
				"CREATE TABLE $table_name (
					id BIGINT(20) NOT NULL AUTO_INCREMENT,
					item_attribute_set_id BIGINT(20) NOT NULL UNIQUE,
					name VARCHAR(255),
					attribute_name_1 VARCHAR(255),
					attribute_name_2 VARCHAR(255),
					attribute_name_3 VARCHAR(255),
					system TINYINT(1) DEFAULT 0,
					archived TINYINT(1) DEFAULT 0,
					updated_at TIMESTAMP,
					created_at TIMESTAMP,
					PRIMARY KEY(id),
					UNIQUE KEY(item_attribute_set_id)
				) $charset_collate;
				";

			dbDelta($sql);

			$this->add_indexes( $table_name, array( 'name' ) );
		}

		function create_wclsi_item_e_commerce(){
			global $wpdb;
			$table_name = $wpdb->prefix . "wclsi_item_e_commerce";

			if ( $this->table_exists( $table_name ) ) { return; }

			$charset_collate = $wpdb->get_charset_collate();

			$sql =
				"CREATE TABLE $table_name (
					id BIGINT(20) NOT NULL AUTO_INCREMENT,
					wclsi_item_id BIGINT(20) UNSIGNED NOT NULL,
					item_e_commerce_id BIGINT(20) UNSIGNED,
					long_description LONGTEXT,
					short_description LONGTEXT,
					weight DECIMAL(10,7),
					width DECIMAL(10,7),
					height DECIMAL(10,7),
					length DECIMAL(10,7),
					list_on_store TINYINT(1),
					updated_at TIMESTAMP,
					created_at TIMESTAMP,				
					PRIMARY KEY(id),
					UNIQUE KEY(item_e_commerce_id)
				) $charset_collate;
				";

			dbDelta($sql);

			$this->add_indexes( $table_name, array( 'wclsi_item_id' ) );
		}

		private function add_indexes($table_name, $indexes) {
			global $wpdb;
			foreach( $indexes as $index_name ) {
				$wpdb->query( "ALTER TABLE $table_name ADD INDEX ($index_name);" );
			}
		}
	}

	new Create_WCLSI_Tables();

endif;
