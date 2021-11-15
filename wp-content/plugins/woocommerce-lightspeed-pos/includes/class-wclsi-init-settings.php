<?php
/**
 * Integrations Page. Sets up various options to initialize communication with LightSpeed.
 * @class LSI_Init_Settings
 */
if ( !class_exists('LSI_Init_Settings') ) :

	class LSI_Init_Settings extends WC_Integration {

		const WOO_CONNECT_URL = 'https://connect.woocommerce.com/login/lightspeed/';

		/**
		 * @var mixed|void|null 
		 */
		public $ls_account_id = null;

		/**
		 * @var array 
		 */
		public $settings_tabs = array();

		/**
		 * @var mixed|string 
		 */
		public $current_tab = 'wclsi_store_settings';

		/**
		 * @var array 
		 */
		private $init_errors = array();

		/**
		 * @var mixed|string|void 
		 */
		private $token = '';

		function __construct() {
			$this->set_header_info();
			$this->init_settings();

			// Setup OAuth
			$this->check_for_lightspeed_token();
			$this->token = get_option( 'wclsi_oauth_token' );

			$account_id = get_option( 'wclsi_account_id' );
			$this->ls_account_id = $account_id;

			$this->settings_tabs = array(
				array('label' => 'Store Settings', 'slug' => 'wclsi_store_settings'),
				array('label' => 'Product Sync Settings', 'slug' => 'wclsi_product_sync_settings'),
				array('label' => 'Category Sync Settings', 'slug' => 'wclsi_category_sync_settings'),
				array('label' => 'Background Jobs', 'slug' => 'wclsi_background_jobs'),
			);

			$this->current_tab = isset($_GET['wclsi-settings-tab']) ? $_GET['wclsi-settings-tab'] : $this->current_tab;
			
			$this->init_store_data();
			$this->init_form_fields();

			// Default to OAuth if we can
			if ( !empty( $this->ls_account_id ) ) {
				if( !empty( $this->token ) ){
					$this->MOSAPI = new WP_MOSAPICall(null, $this->ls_account_id, $this->token);
				}
			}

			add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'admin_notices', array( $this, 'display_init_errors') );
			add_filter( 'woocommerce_settings_api_sanitized_fields_' . $this->id, array( $this, 'sanitize_settings' ) );
			
			add_action( 'admin_enqueue_scripts', array( $this, 'add_wclsi_script' ), 9 );
			add_action( 'admin_enqueue_scripts', array( $this, 'add_wclsi_script_vars'), 10 );
			add_action( 'wp_enqueue_scripts', array( $this, 'add_wclsi_script' ), 9 );
			add_action( 'wp_enqueue_scripts', array( $this, 'add_wclsi_script_vars'), 10 );
		}
		
		function get_settings_option_key () {
			return "{$this->plugin_id}{$this->id}_settings";
		}
		
		/**
		 * Override this method and just return settings
		 * @see process_admin_options()
		 * @param $settings
		 * @return mixed
		 */
		function sanitize_settings( $settings ) {
			return $settings;
		}

		function admin_options() {
			$this->display_errors();
			$this->check_for_reqd_fields();
			?>
			<h3><?php echo isset( $this->method_title ) ? $this->method_title : __( 'Settings', 'woocommerce' ) ; ?></h3>
			<?php echo isset( $this->method_description ) ? wpautop( $this->method_description ) : ''; ?>
			<?php $this->get_setting_tabs() ?>
			<table class="form-table" id="wclsi-settings-table">
				<?php echo $this->current_tab === 'wclsi_store_settings' ? $this->render_api_success_message() : '' ?>
				<?php $this->generate_settings_html(); ?>
			</table>
			<div><input type="hidden" name="section" value="<?php echo $this->id; ?>" /></div>
		<?php
		}

		function get_setting_tabs() {
			global $wp;
			?>
			<nav class="nav-tab-wrapper woo-nav-tab-wrapper">
			<?php foreach($this->settings_tabs as $tab) {?>
				<a href="<?php echo add_query_arg( $wp->query_vars, array('wclsi-settings-tab' => $tab['slug']) ) ?>" class="nav-tab <?php echo ($this->current_tab === $tab['slug'] ? 'nav-tab-active' : '') ?>"><?php echo $tab['label'] ?></a>
			<?php } ?>
			</nav>
			<?php
		}

		function add_wclsi_script () {
			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
			$deps = is_admin() ? array( 'jquery', 'thickbox',  'jquery-tiptip') : array( 'jquery' );
			wp_enqueue_script(
				'wclsi-admin-js',
				plugins_url( "assets/js/wclsi-admin{$suffix}.js", dirname( __FILE__ ) ),
				$deps,
				WCLSI_VERSION,
				true
			);
		}

		function add_wclsi_script_vars() {
			global $WCLSI_objectL10n;
			
			$script_params = array(
				'PLUGIN_PREFIX_ID'  => "{$this->plugin_id}{$this->id}_",
				'SCRIPT_DEBUG'      => defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG,
				'IS_ADMIN'          => is_admin(),
				'AJAX_URL'          => admin_url( 'admin-ajax.php' ),
				'WCLSI_NONCE'       => wp_create_nonce( 'wclsi_ajax' )
			);

			$wclsi_admin_script_vars = apply_filters('wclsi_admin_script_vars', $script_params);
			wp_localize_script( 'wclsi-admin-js', 'wclsi_admin', $wclsi_admin_script_vars);
			wp_localize_script( 'wclsi-admin-js', 'objectL10n', $WCLSI_objectL10n );
		}

		/**
		 * Will only display errors if the plugin has initialized
		 */
		function check_for_reqd_fields() {
			$wclsi_initialized = get_option( 'wclsi_initialized' );
			if ( !$wclsi_initialized ) {
				return;
			}

			if ( empty( $this->store_timezone ) ) {
				?>
				<div class="error">
					<p><?php echo __('Could not find store timezone. This is required for syncing products. Please click on "Connect to Lightspeed" before starting the syncing process with Lightspeed.', 'woocommerce-lightspeed-pos'); ?></p>
				</div>
				<?php
			}
		}
		
		static function get_store_settings() {
			return array(
				'ls_account_id' => array(
					'title'             => __( 'Account ID', 'woocommerce-lightspeed-pos' ),
					'type'              => 'hidden_custom',
					'desc_tip'          => true,
					'default'           => '',
					'description'       => ''
				),
				'connect_to_ls' => array(
					'title'             => __( 'Enable Lightspeed API', 'woocommerce-lightspeed-pos' ),
					'type'              => 'wclsi_ls_button_custom',
					'desc_tip'          => true,
					'default'           => '',
					'description'       => __(
						'Click the button to connect your Lightspeed Retail account to WooCommerce.',
						'woocommerce-lightspeed-pos'
					)
				),
				'ls_enabled_stores' => array(
					'title'             => __( 'Enable Stores', 'woocommerce-lightspeed-pos' ),
					'type'              => 'checkbox_custom',
					'custom_attributes' => '',
					'desc_tip'          => true,
					'description'       => __(
						'Enable the stores you would like to import from.',
						'woocommerce-lightspeed-pos'
					)
				),
				'ls_inventory_store' => array(
					'title'             => __( 'Primary Lightspeed Inventory Store', 'woocommerce-lightspeed-pos' ),
					'type'              => 'inventory_radio_custom',
					'custom_attributes' => '',
					'desc_tip'          => true,
					'description'       => __(
						'This plugin is capable of looking up and pushing inventory to only one of your Lightspeed ' .
						'stores, please select which store you\'d like to sync inventory with.',
						'woocommerce-lightspeed-pos'
					)
				),
				'wclsi_autoload_ls_attrs' => array(
					'title'             => __( 'Auto-import Lightspeed Attributes', 'woocommerce-lightspeed-pos' ),
					'type'              => 'wclsi_autoload_ls_attrs',
					'custom_attributes' => '',
					'desc_tip'          => true,
					'description'       => __(
						'Automatically import Lightspeed Attributes in WooCommerce. This will create new ' .
						'attributes under Products -> Attributes in WooCommerce.',
						'woocommerce-lightspeed-pos'
					)
				)
			);
		}
		
		static function get_product_settings() {
			return array(
				'wclsi_import_status' => array(
					'title'             => __( 'Product status on import', 'woocommerce-lightspeed-pos' ),
					'type'              => 'wclsi_import_status',
					'custom_attributes' => '',
					'desc_tip'          => true,
					'description'       => __(
						'The product status on import. Publish will make the product immediately visible on your ' .
						'store catalog upon import. Draft will keep the product hidden until it is published.' .
						'This setting will apply to all products on import.',
						'woocommerce-lightspeed-pos'
					)
				),
				'wclsi_wc_selective_sync' => array(
					'title'             => __( 'Selective sync for WooCommerce products', 'woocommerce-lightspeed-pos' ),
					'type'              => 'wclsi_wc_selective_sync',
					'custom_attributes' => '',
					'desc_tip'          => true,
					'description'       => __(
						'Select which product fields should get updated for a WooCommerce product when it syncs ' .
						'data from Lightspeed.',
						'woocommerce-lightspeed-pos'
					)
				),
				'wclsi_ls_selective_sync' => array(
					'title'             => __( 'Selective sync for LightSpeed products', 'woocommerce-lightspeed-pos' ),
					'type'              => 'wclsi_ls_selective_sync',
					'custom_attributes' => '',
					'desc_tip'          => true,
					'description'       => __(
						'Select which product fields should get updated for a Lightspeed product when it syncs ' .
						'data from WooCommerce.',
						'woocommerce-lightspeed-pos'
					)
				),
				'wclsi_ignore_archived_ls_prods' => array(
					'title'             => __( 'Ignore Archived Lightspeed Products', 'woocommerce-lightspeed-pos' ),
					'type'              => 'wclsi_ignore_archived_ls_prods',
					'custom_attributes' => '',
					'desc_tip'          => true,
					'default'           => true,
					'description'       => __(
						'When checked, will ignore all archived Lightspeed products upon loading products into ' .
						'WooCommerce.',
						'woocommerce-lightspeed-pos' )
				),
				'wclsi_prune_deleted_variations' => array(
					'title'             => __( 'Prune deleted Lightspeed variations', 'woocommerce-lightspeed-pos' ),
					'type'              => 'wclsi_prune_deleted_variations',
					'custom_attributes' => '',
					'desc_tip'          => true,
					'default'           => true,
					'description'       => __(
						'Only works with manual updates: When checked, deletes WooCommerce variations that correspond to ones deleted in Lightspeed ' .
						'matrix products.',
						'woocommerce-lightspeed-pos' )
				)
			);
		}
		
		static function get_category_settings() {
			return array(
				'import_categories' => array(
					'title'             => __( 'Lightspeed Categories', 'woocommerce-lightspeed-pos' ),
					'type'              => 'wclsi_import_cats_button',
					'custom_attributes' => '',
					'desc_tip'          => true,
					'description'       => __(
						'This is a one time action that will import existing Lightspeed categories. ' .
						'Newly added Lightspeed categories will not be automatically synced! ',
						'woocommerce-lightspeed-pos'
					)
				),
				'delete_category_cache' => array(
					'title'             => __( 'Delete Cached Lightspeed Categories', 'woocommerce-lightspeed-pos' ),
					'type'              => 'wclsi_delete_cats_button',
					'custom_attributes' => '',
					'desc_tip'          => true,
					'description'       => __(
						'This is a one time action that will delete cached Lightspeed categories stored in the WordPress database.',
						'woocommerce-lightspeed-pos'
					)
				),                
				'wclsi_remove_uncategorized_category' => array(
					'title'             => __( 'Remove "Uncategorized" category', 'woocommerce-lightspeed-pos' ),
					'type'              => 'wclsi_remove_uncategorized_category',
					'custom_attributes' => '',
					'desc_tip'          => true,
					'description'       => __(
						'The plugin will attempt to remove the "Uncategorized" Woo category when importing a product from Lightspeed.',
						'woocommerce-lightspeed-pos'
					)
				)
			);
		}
		
		static function get_background_job_settings() {
			return array(
				'wclsi_poller_setting' => array(
					'title'             => __(
						'Auto Polling Lightspeed product changes',
						'woocommerce-lightspeed-pos'
					),
					'type'              => 'wclsi_poller_setting',
					'custom_attributes' => '',
					'desc_tip'          => true,
					'description'       => __(
						"Checks for Lightspeed product changes every 5 seconds and updates the corresponding WooCommerce Product with those changes. " .
						"Checks for changes in stock quantity, images, descriptions, etc.",
						'woocommerce-lightspeed-pos'
					)
				),
				'ls_to_wc_auto_load' => array(
					'title'             => __(
						'Check for new Lightspeed products',
						'woocommerce-lightspeed-pos'
					),
					'type'              => 'wclsi_ls_to_wc_auto_load',
					'custom_attributes' => '',
					'desc_tip'          => true,
					'description'       => __(
						"Checks for new Lightspeed products and loads them into WooCommerce every 30 seconds. " .
						"This will not create any new products. Products will only be loaded into the import table.",
						'woocommerce-lightspeed-pos'
					)
				),
				'wclsi_import_on_auto_load' => array(
					'title'             => __(
						'Action after auto-loading products',
						'woocommerce-lightspeed-pos'
					),
					'type'              => 'wclsi_import_on_auto_load',
					'default'           => 'do_nothing',
					'custom_attributes' => '',
					'desc_tip'          => true,
					'description'       => __(
						'Pick the action for new Lightspeed products when auto-loading is enabled. ' .
						'You can do nothing (default), import, or import & sync after Lightspeed products are auto-loaded. ' .
						'Import status is determined by the "Product status on import" setting.',
						'woocommerce-lightspeed-pos'
					)
				)
			);
		}

		function init_form_fields() {
			switch($this->current_tab) {
				case 'wclsi_store_settings':
					$this->form_fields = self::get_store_settings();
					break;
				case 'wclsi_product_sync_settings':
					$this->form_fields = self::get_product_settings();
					break;
				case 'wclsi_category_sync_settings':
					$this->form_fields = self::get_category_settings();
					break;
				case 'wclsi_background_jobs':
					$this->form_fields = self::get_background_job_settings();
					break;
				default:
					$this->form_fields = array();
			}
		}

		/*****************
		 * Form Elements *
		 *****************/

		function generate_inventory_radio_custom_html( $key, $data ) {
			$shop_data = get_option( 'wclsi_shop_data' );

			if ( false !== $shop_data && is_object( $shop_data[ 'ls_store_data' ] ) ) {
				$shop_data = $shop_data[ 'ls_store_data' ];
				$field = "{$this->plugin_id}{$this->id}_{$key}";

				if ( is_array( $shop_data->Shop ) ) {
					$defaults = array(
						'class'             => 'button-secondary',
						'css'               => '',
						'custom_attributes' => array(),
						'desc_tip'          => false,
						'description'       => '',
						'title'             => '',
					);

					$data = wp_parse_args( $data, $defaults );

					ob_start();
					?>
					<tr>
						<?php echo $this->tooltip_th($field, $data) ?>
						<td class="forminp">
							<fieldset>
								<?php echo $this->get_description_html( $data ); ?>
								<?php foreach ( $shop_data->Shop as $store ) : ?>
									<label>
										<input
											type="radio"
											name="<?php echo $field ?>"
											id="<?php echo $field ?>"
											value="<?php echo $store->shopID ?>"
											<?php
											if( isset( $this->settings[ WCLSI_INVENTORY_SHOP_ID ] ) ) {
												if( $this->settings[ WCLSI_INVENTORY_SHOP_ID ] == $store->shopID ){
													echo 'checked';
												}
											}
											?>
										/>
										<?php echo $store->name ?>
									</label>
									<br/>
								<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>
					<?php
					return ob_get_clean();
				} else {
					?>
					<input
						type="hidden"
						name="<?php echo $field ?>"
						id="name="<?php echo $field ?>"
						value="<?php echo $this->settings[ WCLSI_INVENTORY_SHOP_ID ] ?>"
					/>
					<?php
				}
			} else {
				return '';
			}
		}

		function generate_checkbox_custom_html( $key, $data ) {
			$shop_data = get_option( 'wclsi_shop_data' );

			if ( false !== $shop_data && is_object( $shop_data['ls_store_data'] ) ) {
				$shop_data = $shop_data['ls_store_data'];
				if ( is_array( $shop_data->Shop ) ) {
					$field    = "{$this->plugin_id}{$this->id}_{$key}";
					$defaults = array(
						'class'             => 'button-secondary',
						'css'               => '',
						'custom_attributes' => array(),
						'desc_tip'          => false,
						'description'       => '',
						'title'             => '',
					);

					$data = wp_parse_args( $data, $defaults );
					$enabled_stores = empty( $this->settings['ls_enabled_stores'] ) ? array() : $this->settings['ls_enabled_stores'];
					ob_start();
					?>
					<tr>
						<?php echo $this->tooltip_th($field, $data) ?>
						<td class="forminp">
							<fieldset>
								<?php echo $this->get_description_html( $data ); ?>
								<?php foreach ( $shop_data->Shop as $store ) : ?>
									<label>
										<input
											type="checkbox"
											name="<?php echo $field ?>[<?php echo $store->name ?>]"
											id="<?php echo $field ?>[<?php echo $store->name ?>]"
											value="<?php echo $store->shopID ?>"
											<?php echo isset( $enabled_stores[ $store->name ] ) ? 'checked' : '' ?>
										/>
										<?php echo $store->name ?>
									</label>
									<br/>
								<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>
					<?php
					return ob_get_clean();
				}
			} else {
				$GLOBALS['hide_save_button'] = true;
				return '';
			}
		}

		function generate_hidden_custom_html( $key, $data ) {
			$wclsi_account_id = get_option( 'wclsi_account_id' );
			ob_start();
			if ( false !== $wclsi_account_id ) {
				?>
				<input 
					type="hidden" 
					id="<?php echo "{$this->plugin_id}{$this->id}_{$key}"; ?>"
					name="<?php echo "{$this->plugin_id}{$this->id}_{$key}"; ?>"
					value="<? echo $wclsi_account_id ?>"
				/>
			<?php
			}
			return ob_get_clean();
		}

		function generate_wclsi_autoload_ls_attrs_html( $key, $data ){
			return $this->single_checkbox($key, $data);
		}

		function generate_wclsi_remove_uncategorized_category_html( $key, $data ) {
			return $this->single_checkbox($key, $data);
		}
		
		function generate_wclsi_delete_cats_button_html( $key, $data ){
			if ( !self::wclsi_initialized() ) { return ''; }

			$field = "{$this->plugin_id}{$this->id}_{$key}";
			$data = wp_parse_args( $data );
			ob_start();

			?>
			<tr>
				<?php echo $this->tooltip_th($field, $data) ?>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text">
							<span><?php echo wp_kses_post( $data['title'] ); ?></span>
						</legend>
						<?php echo $this->get_description_html( $data ); ?>
						<button id="wclsi-delete-cat-cache" type="button" class="button-secondary">Delete Cache</button>
						<div id="wclsi-import-cats-progress" style="display: inline-block;"></div>
						<?php wp_nonce_field( 'wclsi_ajax', 'wclsi_nonce', false ); ?>
					</fieldset>
				</td>
			</tr>
			<?php
			return ob_get_clean();
		}
		
		function generate_wclsi_import_cats_button_html( $key, $data ){
			if ( !self::wclsi_initialized() ) { return ''; }

			$field = "{$this->plugin_id}{$this->id}_{$key}";
			$data = wp_parse_args( $data );
			ob_start();

			?>
			<tr>
				<?php echo $this->tooltip_th($field, $data) ?>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text">
							<span><?php echo wp_kses_post( $data['title'] ); ?></span>
						</legend>
						<?php echo $this->get_description_html( $data ); ?>
						<button id="wclsi-import-cats" type="button" class="button-secondary">Import Categories</button>
						<div id="wclsi-import-cats-progress" style="display: inline-block;"></div>
						<?php wp_nonce_field( 'wclsi_ajax', 'wclsi_nonce', false ); ?>
					</fieldset>
				</td>
			</tr>
			<?php
			return ob_get_clean();
		}

		function generate_wclsi_poller_setting_html( $key, $data ){
			return $this->single_checkbox($key, $data);
		}

		function generate_wclsi_ls_to_wc_auto_load_html( $key, $data ){
			return $this->single_checkbox($key, $data);
		}

		function generate_wclsi_import_on_auto_load_html( $key, $data ){
			if ( !self::wclsi_initialized() ) { return ''; }

			$field = "{$this->plugin_id}{$this->id}_{$key}";
			$data = wp_parse_args( $data );
			$display_style = $this->settings['ls_to_wc_auto_load'] ? '' : 'display: none;';

			$options = array(
				'do_nothing' => 'Do nothing (default)',
				'import' => 'Import',
				'import_and_sync' => 'Import & Sync'
			);

			ob_start();
			?>
			<tr id="wclsi_import_on_auto_load_wrapper" style="<?php echo $display_style ?>">
				<?php echo $this->tooltip_th($field, $data) ?>
				<td class="forminp" style="vertical-align: top; padding: 19px 10px;">
					<div style="max-width: 400px;">
					<fieldset>
						<legend class="screen-reader-text">
							<span><?php echo wp_kses_post( $data['title'] ); ?></span>
						</legend>
						<?php echo $this->get_description_html( $data ); ?>
						<select name="<?php echo $field ?>" id="<?php echo $field ?>" style="width: inherit;">
							<?php
								foreach ($options as $opt_key => $description) {
									if ( $this->settings[$key] == $opt_key ) {
										echo "<option value=\"$opt_key\" selected=\"true\">$description</option>";
									} else {
										echo "<option value=\"$opt_key\">$description</option>";
									}
								}
							?>
						</select>
					</fieldset>
					</div>
				</td>
			</tr>
			<?php
			return ob_get_clean();
		}

		function generate_wclsi_ls_button_custom_html( $key, $data ){
			$field = "{$this->plugin_id}{$this->id}_{$key}";
			$data = wp_parse_args( $data );
			$woo_connect_full_url = add_query_arg(
				array(
					'scopes' => 'all',
					'redirect' => urlencode( urlencode( add_query_arg(
						array(
							'page' => 'wc-settings',
							'tab'  => 'integration',
							'section' => 'lightspeed-integration'
						),
						admin_url('admin.php')
					) ) )
				),
				self::WOO_CONNECT_URL
			);

			$connect_text = !empty( $this->token ) ? __('Reconnect to Lightspeed', 'woocommerce-lightspeed-pos') : __('Connect to Lightspeed', 'woocommerce-lightspeedpos');
			ob_start();
			?>
			<tr>
				<?php echo $this->tooltip_th($field, $data) ?>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text">
							<span><?php echo wp_kses_post( $data['title'] ); ?></span>
						</legend>
						<?php echo $this->get_description_html( $data ); ?>
						<a href="<?php echo $woo_connect_full_url ?>" class="wclsi-connect-ls"><?php echo $connect_text ?></a>
					</fieldset>
				</td>
			</tr>
			<?php
			return ob_get_clean();
		}

		function generate_wclsi_import_status_html( $key, $data ){
			if ( !self::wclsi_initialized() ) { return ''; }
			$field = "{$this->plugin_id}{$this->id}_{$key}";
			$data = wp_parse_args( $data );
			$selected = isset( $this->settings[$key] ) ? $this->settings[$key] : 'draft';
			ob_start();
			?>
			<tr>
				<?php echo $this->tooltip_th($field, $data) ?>
				<td class="forminp">
					<div style="max-width: 400px;">
					<fieldset>
						<legend class="screen-reader-text">
							<span><?php echo wp_kses_post( $data['title'] ); ?></span>
						</legend>
						<?php echo $this->get_description_html( $data ); ?>
					</fieldset>
					<select	name="<?php echo $field ?>" id="<?php echo $field ?>" style="width: inherit;">
						<option value="draft"
							<?php
								if ( $selected == 'draft' || empty( $selected ) ) {
									echo 'selected';
								}
							?>>
							Draft (default)
						</option>
						<option value="publish" <?php if ( $selected == 'publish' ) { echo 'selected'; } ?>>
							Publish
						</option>
					</select>
					<?php if ( $selected == 'publish') { ?>
					<div>
						<p class="notice notice-warning">
						<?php
						echo __(
							'Warning: this will make your products live and visible on your store catalog upon import.',
							'woocommerce-lightspeed-pos'
						)
						?>
						</p>
					</div>
					<?php } ?>
					</div>
				</td>
			</tr>
			<?php
			return ob_get_clean();
		}

		function generate_wclsi_wc_selective_sync_html( $key, $data ) {
			if ( !self::wclsi_initialized() ) { return ''; }
			global $WC_PROD_SELECTIVE_SYNC_PROPERTIES;
			$field = "{$this->plugin_id}{$this->id}_{$key}";
			$data = wp_parse_args( $data );
			$sync_properties = $this->settings['wclsi_wc_selective_sync'];
			ob_start();
			?>
			<tr>
				<?php echo $this->tooltip_th($field, $data) ?>
				<td class="forminp">
					<div style="max-width: 400px;" class="wclsi-selective-sync-checkboxes">
						<fieldset>
							<legend class="screen-reader-text">
								<span><?php echo wp_kses_post( $data['title'] ); ?></span>
							</legend>
							<?php echo $this->get_description_html( $data ); ?>
							<p id="wclsi_select_all_prod_sync_properties">Select all</p>
							<div id="wc_prod_selective_sync_properties">
							<?php foreach ( $WC_PROD_SELECTIVE_SYNC_PROPERTIES as $key => $property ) : ?>
								<?php if ( $property == 'Short Description' ) : ?>
									<p id="wclsi-legacy-webstore-header">Legacy Web Store fields: </p>
								<?php endif; ?>
								<input
									type="checkbox"
									name="<?php echo "{$field}[$key]" ?>"
									id="<?php echo "{$field}[$key]" ?>"
									value="true"
									<?php
									if  ( isset($sync_properties[ $key ]) && $sync_properties[ $key ] == 'true' ) {
										echo 'checked';
									}
									?>
								/>
								<label for="<?php echo "{$field}[$key]" ?>"><?php echo $property ?></label>
								<br/>
							<?php endforeach; ?>
							</div>
						</fieldset>
						<div id="wclsi-selective-sync-gradient"></div>
					</div>
				</td>
			</tr>
			<?php
			return ob_get_clean();
		}

		function generate_wclsi_ls_selective_sync_html( $key, $data ) {
			if ( !self::wclsi_initialized() ) { return ''; }
			global $LS_PROD_SELECTIVE_SYNC_PROPERTIES;
			$field = "{$this->plugin_id}{$this->id}_{$key}";
			$data = wp_parse_args( $data );
			if ( isset( $this->settings[ WCLSI_LS_SELECTIVE_SYNC ] ) ) {
				$sync_properties = $this->settings[ WCLSI_LS_SELECTIVE_SYNC ];
			} else {
				$sync_properties = [];
			}

			ob_start();
			?>
			<tr>
				<?php echo $this->tooltip_th($field, $data) ?>
				<td class="forminp">
					<div style="max-width: 400px;" class="wclsi-selective-sync-checkboxes">
						<fieldset>
							<legend class="screen-reader-text">
								<span><?php echo wp_kses_post( $data['title'] ); ?></span>
							</legend>
							<?php echo $this->get_description_html( $data ); ?>
							<p id="wclsi_select_all_ls_prod_sync_properties">Select all</p>
							<div id="ls_prod_selective_sync_properties">
								<?php foreach ( $LS_PROD_SELECTIVE_SYNC_PROPERTIES as $key => $property ) : ?>
									<?php if ( $property == 'Short Description' ) : ?>
										<p id="wclsi-legacy-webstore-header"> Legacy Web Store fields: </p>
									<?php endif; ?>
									<input
										type="checkbox"
										name="<?php echo "{$field}[$key]" ?>"
										id="<?php echo "{$field}[$key]" ?>"
										value="true"
										<?php echo isset($sync_properties[ $key ]) && $sync_properties[ $key ] == 'true' ? 'checked' : '' ?>
									/>
									<label for="<?php echo "{$field}[$key]" ?>"><?php echo $property ?></label>
									<br/>
								<?php endforeach; ?>
							</div>
						</fieldset>
					</div>
				</td>
			</tr>
			<?php
			return ob_get_clean();
		}

		function generate_wclsi_ignore_archived_ls_prods_html( $key, $data ) {
			return $this->single_checkbox($key, $data);
		}

		function generate_wclsi_prune_deleted_variations_html( $key, $data ) {
			return $this->single_checkbox($key, $data);
		}
		
		function tooltip_th ($field, $data) {
			ob_start();
			?>
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
				<?php echo $this->get_tooltip_html( $data ); ?>
			</th>
			<?php
			return ob_get_clean();
		}
		
		function single_checkbox ($key, $data) {
			if ( !self::wclsi_initialized() ) { return ''; }

			$field = "{$this->plugin_id}{$this->id}_{$key}";
			$data = wp_parse_args( $data );

			$setting = isset( $this->settings[$key] ) ? $this->settings[$key] : false;
			$checked = !empty( $setting ) ? 'checked' : '';

			ob_start();
			?>
			<tr style="vertical-align: top;">
				<?php echo $this->tooltip_th($field, $data) ?>
				<td class="forminp" style="vertical-align: top; padding: 15px 10px;">
					<fieldset>
						<?php echo $this->get_description_html( $data ); ?>
						<label>
							<input
								type="checkbox"
								name="<?php echo $field ?>"
								id="<?php echo $field ?>"
								value="true"
								<?php echo $checked ?>
							/>
						</label>
						<br/>
					</fieldset>
				</td>
			</tr>
			<?php
			return ob_get_clean();		    
		}


		/**
		 * Override validate_settings_fields so we don't default to validate_text_field()
		 * @see validate_settings_fields()
		 */
		function validate_ls_enabled_stores_field( $key ) {
			$value = isset( $_POST[ "{$this->plugin_id}{$this->id}_{$key}" ] ) ? $_POST[ "{$this->plugin_id}{$this->id}_{$key}" ] : null;
			return $value;
		}

		function validate_ls_to_wc_auto_load_field( $key ) {
			$value = isset( $_POST[ "{$this->plugin_id}{$this->id}_{$key}" ] ) ? $_POST[ "{$this->plugin_id}{$this->id}_{$key}" ] : null;
			return $value;
		}

		function validate_wclsi_import_on_auto_load_field( $key ) {
			$value = isset( $_POST[ "{$this->plugin_id}{$this->id}_{$key}" ] ) ? $_POST[ "{$this->plugin_id}{$this->id}_{$key}" ] : null;
			return $value;
		}

		function validate_wclsi_wc_selective_sync_field( $key ) {
			$value = isset( $_POST[ "{$this->plugin_id}{$this->id}_{$key}" ] ) ? $_POST[ "{$this->plugin_id}{$this->id}_{$key}" ] : null;
			return $value;
		}

		function validate_wclsi_ls_selective_sync_field( $key ) {
			if ( !isset( $_POST[ "{$this->plugin_id}{$this->id}_{$key}" ] ) ) {
				return null;
			}

			if ( 'wclsi_ls_selective_sync' === $key && key_exists( 'sale_price', $_POST[ "{$this->plugin_id}{$this->id}_{$key}" ] ) ) {
				if ( !$this->sale_price_level_enabled() ) {
					unset( $_POST[ "{$this->plugin_id}{$this->id}_{$key}" ][ 'sale_price' ] );
				}
			}

			return $_POST[ "{$this->plugin_id}{$this->id}_{$key}" ];
		}

		function sale_price_level_enabled () {
			global $WCLSI_API;
			$price_level_endpt = "Account/$WCLSI_API->ls_account_id/PriceLevel/";
			$results = $WCLSI_API->make_api_call( $price_level_endpt, "Read", array( 'name' => 'Sale' ) );
			if ( !is_wp_error( $results ) ) {
				if ( $results->{'@attributes'}->count > 0 ) {
					return true;
				} else {
					$this->init_errors[] = 
						'Could not save "Sale Price" for Lightspeed Selective Sync. ' .
						'Please set up a "Sale" PriceLevel in your Lightspeed store first! ' .
						'Please refer to the <a href="https://docs.woocommerce.com/document/woocommerce-lightspeed-pos/">documentation</a> for more info.';
				}
			} else {
				$this->init_errors[] =
					'Could not save "Sale Price" for Lightspeed Selective Sync. ' .
					'It could not be determined if your Lightspeed store has a "Sale" price level set up. ' .
					'Please refer to the <a href="https://docs.woocommerce.com/document/woocommerce-lightspeed-pos/">documentation</a> for more info.';
			}

			return false;
		}

		function display_init_errors(){
			$displayed = wp_cache_get( 'wclsi_init_errors_displayed' );
			if ( is_array( $this->init_errors ) && !$displayed ) {
				foreach ($this->init_errors as $key => $error) {
					?>
					<div class="error is-dismissible'">
						<p><?php echo $error ?></p>
					</div>
					<?php
				};
				wp_cache_set( 'wclsi_init_errors_displayed', true );
			}
		}

		function check_for_lightspeed_token(){
			if( isset( $_GET['lightspeed_access_token'] ) || isset( $_GET['amp;lightspeed_access_token'] ) ){

				$token = isset( $_GET['lightspeed_access_token'] ) ? esc_attr( $_GET['lightspeed_access_token'] ) : '';
				$refresh_token = isset(  $_GET['lightspeed_refresh_token'] ) ? esc_attr( $_GET['lightspeed_refresh_token'] ) : '';
				$expires_in = isset(  $_GET['expires_in'] ) ? (int) esc_attr( $_GET['expires_in'] ) : null;

				/**
				 * TODO: why does the url parser convert the ampersands to "amp;"?
				 */
				if( empty( $token ) ) {
					$token = esc_attr($_GET['amp;lightspeed_access_token']);
				}

				if( empty( $refresh_token ) ) {
					$refresh_token = esc_attr( $_GET['amp;lightspeed_refresh_token'] );
				}

				if( empty( $expires_in ) ) {
					$expires_in = (int) esc_attr( $_GET['amp;expires_in'] );
				}

				// Setup a separate MerchantOS object since we may not have the account ID yet..
				$ls_api = new WP_MOSAPICall( '', '', $token );

				$this->init_wclsi_settings_with_token( $ls_api, $token, $refresh_token, $expires_in );
			}
		}

		function init_wclsi_settings_with_token( $ls_api, $token, $refresh_token = null, $expires_in = null ){

			// Save account settings
			try {
				$account = $ls_api->makeAPICall('Account', 'Read');
			} catch( Exception $e ) {
				$this->init_errors[] = $e->getMessage();
				return;
			}

			if ( isset( $account->httpCode ) && $account->httpCode != '200' ) {
				$this->init_errors[] =
					sprintf(
						'%s %d %s %s %s %s',
						'Lightspeed API Error - ',
						$account->httpCode,
						$account->message,
						'Read',
						' - Payload: ',
						'/Account'
					);
				return;
			} else {
				$this->check_for_ls_account( $account, $token );
			}

			// Get Shop info
			try {
				$shop = $ls_api->makeAPICall( "Account/{$this->ls_account_id}/Shop/", 'Read' );
			} catch( Exception $e ) {
				$this->init_errors[] = $e->getMessage();
				return;
			}

			if ( isset( $shop->httpCode ) && $shop->httpCode != '200' ) {
				$this->init_errors[] =
					sprintf(
						'%s %d %s %s %s %s',
						'Lightspeed API Error - ',
						$shop->httpCode,
						$shop->message,
						'Read',
						' - Payload: ',
						'/Shop'
					);
				return;
			} else {
				$this->check_for_ls_shop_data( $shop );
			}


			// Plugin is no longer clean, display errors if certain options are not available
			update_option( 'wclsi_initialized', true );
			update_option( 'wclsi_oauth_token', $token );
			update_option( 'wclsi_refresh_token', $refresh_token );

			if ( $expires_in > 0) {
				$wclsi_expires_in = date( "Y-m-d H:i:s", time() + $expires_in );
				update_option( 'wclsi_expires_in', $wclsi_expires_in );
			}
		}

		public static function wclsi_initialized() {
			$wclsi_account_id = get_option( 'wclsi_account_id' );
			return false !== $wclsi_account_id;
		}

		/**
		 * @param $controlname
		 * @param $action
		 * @param string $query_str
		 * @param array $data
		 * @param null $unique_id
		 * @param Closure|null $callback
		 *
		 * @return array|mixed|object|WP_Error
		 */

		function make_api_call( $controlname, $action, $query_str = '', $data = array(), $unique_id = null, Closure $callback = null ) {

			// Stub out API calls if we are testing
			if ( defined( "WCLSI_TEST" ) ) { return false; }

			try {
				$controlname = apply_filters( 'wclsi_api_call_controlname', $controlname);
				$action      = apply_filters( 'wclsi_api_call_action', $action);
				$query_str   = apply_filters( 'wclsi_api_call_query_str', $query_str );
				$data        = apply_filters( 'wclsi_api_call_data', $data );
				$unique_id   = apply_filters( 'wclsi_api_call_unique_id', $unique_id );

				$result = $this->MOSAPI->makeAPICall( $controlname, $action, $unique_id, $data, $query_str, $callback );

				if ( isset( $result->httpCode ) ) {

					// Handle refresh tokens
					if ( $result->httpCode == '401' ) {
						$token_result = $this->refresh_access_token();

						if ( !is_wp_error( $token_result ) ) {
							$this->MOSAPI = new WP_MOSAPICall( '', $this->ls_account_id, $token_result);

							// Try the request again once the token has been refreshed
							$result = $this->MOSAPI->makeAPICall(
								$controlname, $action, $unique_id, $data, $query_str, $callback
							);
						} else {
							if( is_admin() ) {
								add_settings_error(
									'wclsi_settings',
									'wclsi_bad_refresh_token_attempt',
									'Error: ' . $token_result->get_error_message(),
									'error'
								);
							}

							return new WP_Error(
								'wclsi_bad_api_call',
								$token_result->get_error_message()
							);
						}
					}

					// Handle other types of status codes other than 200
					// We need another isset() check here in case there was a token refresh
					if ( isset( $result->httpCode ) && $result->httpCode != '200' ) {

						$error_msg = sprintf(
							'%s %d %s %s %s %s %s %s %s',
							__( 'Lightspeed API Error - ', 'woocommerce-lightspeed-pos' ),
							$result->httpCode,
							$result->message,
							$action,
							__( ' - Payload: ', 'woocommerce-lightspeed-pos' ),
							$controlname,
							$unique_id,
							print_r( $query_str, true ),
							print_r( $data, true )
						);

						if( is_admin() && function_exists( 'add_settings_error' ) ) {
							add_settings_error(
								'wclsi_settings',
								'wclsi_bad_api_call',
								$error_msg,
								'error'
							);
						}

						global $WCLSI_WC_Logger;
						$WCLSI_WC_Logger->add(
							WCLSI_ERROR_LOG,
							"ERROR: BAD LIGHTSPEED REQUEST: " . print_r( $result, true ) . PHP_EOL .
							wclsi_get_stack_trace() . PHP_EOL
						);

						return new WP_Error( 'wclsi_bad_api_call', $error_msg );
					}
				}

				return $result;
			} catch( Exception $e ) {
				if( is_admin() && function_exists( 'add_settings_error' ) ) {
					add_settings_error(
						'wclsi_settings',
						'wclsi_bad_api_call',
						__( $e->getMessage(), 'woocommerce-lightspeed-pos' ),
						'error'
					);
				}

				global $WCLSI_WC_Logger;
				$WCLSI_WC_Logger->add(
					WCLSI_ERROR_LOG,
					"ERROR: BAD LIGHTSPEED REQUEST: {$e->getMessage()}" . PHP_EOL .
					wclsi_get_stack_trace() . PHP_EOL
				);

				return new WP_Error( 'wclsi_bad_api_call', __( $e->getMessage(), 'woocommerce-lightspeed-pos' ) );
			}
		}

		public function init_settings() {
			parent::init_settings();
			$this->seed_selective_sync();
			$this->seed_autoload();
			$this->seed_ignore_archived_ls_prods();
			$this->seed_prune_deleted_variations();
			$this->seed_wclsi_poller();
			$this->seed_wclsi_autoload_ls_attrs();
		}

		/*******************
		 * Private Methods *
		 *******************/
		private function seed_wclsi_autoload_ls_attrs() {
			if ( !array_key_exists( WCLSI_AUTOLOAD_LS_ATTRS, $this->settings ) ) {
				$this->settings[ WCLSI_AUTOLOAD_LS_ATTRS ] = 'true';
			}
		}

		private function seed_wclsi_poller() {
			if ( !array_key_exists( WCLSI_POLLER_SETTING, $this->settings ) ) {
				$this->settings[ WCLSI_POLLER_SETTING ] = 'true';
			}
		}

		private function seed_prune_deleted_variations() {
			if ( !array_key_exists( WCLSI_PRUNE_DELETED_VARIATIONS, $this->settings ) ) {
				$this->settings[ WCLSI_PRUNE_DELETED_VARIATIONS ] = 'true';
			}
		}

		private function seed_ignore_archived_ls_prods() {
			if ( !array_key_exists( WCLSI_IGNORE_ARCHIVED_LS_PRODS, $this->settings ) ) {
				$this->settings[ WCLSI_IGNORE_ARCHIVED_LS_PRODS ] = 'true';
			}
		}

		private function seed_autoload() {
			if ( !array_key_exists( WCLSI_LS_TO_WC_AUTOLOAD, $this->settings ) ) {
				$this->settings[ WCLSI_LS_TO_WC_AUTOLOAD ] = 'true';
			}

			if ( !array_key_exists( WCLSI_IMPORT_ON_AUTOLOAD, $this->settings ) ) {
				$this->settings[ WCLSI_IMPORT_ON_AUTOLOAD ] = 'do_nothing';
			}
		}

		private function seed_selective_sync() {

			if ( !array_key_exists( WCLSI_WC_SELECTIVE_SYNC, $this->settings ) ) {
				global $WC_PROD_SELECTIVE_SYNC_PROPERTIES;

				// default all selective sync properties to true
				$selective_sync_default = array();
				$selective_ls_sync_default = array();

				foreach ( $WC_PROD_SELECTIVE_SYNC_PROPERTIES as $key => $property ) {

					// We're not sure if the Sale Price Level has been setup, leave it out
					if ( $key === 'sale_price') { continue; }

					$selective_sync_default[ $key ] = 'true';
				}

				$this->settings[ 'wclsi_wc_selective_sync' ] = $selective_sync_default;
			}

			if ( !array_key_exists( WCLSI_LS_SELECTIVE_SYNC, $this->settings ) ) {

				// default ony the stock_quantity & stock_quantity_checkout sync property to true
				$selective_ls_sync_default[ 'stock_quantity' ] = 'true';
				$this->settings[ WCLSI_LS_SELECTIVE_SYNC ] = $selective_ls_sync_default;
			}
		}

		private function set_header_info() {
			$this->id                 = 'lightspeed-integration';
			$this->method_title       = __( 'WooCommerce Lightspeed POS Integration', 'woocommerce-lightspeed-pos' );
			$this->method_description =
				'<p>' .
				__( 'Import Lightspeed Cloud data to your WooCommerce instance', 'woocommerce-lightspeed-pos' ) . ' | ' .
				'<a href="' . WCLSI_ADMIN_URL . '">Import Page</a>' . ' | ' .
				'<a href="' . WCLSI_DOCS_URL . '" target="_blank">Documentation</a>' . ' | ' .
				'<i>v' . WCLSI_VERSION . '</i>' .
				'</p>';
		}

		private function init_store_data(){
			$shop_data = get_option( 'wclsi_shop_data' );
			if ( !empty( $shop_data ) ) {
				$this->store_timezone = $shop_data[ 'store_timezone' ];
				$this->store_name     = $shop_data[ 'store_name' ];
				if ( isset( $this->settings['ls_enabled_stores'] ) ) {
					$this->ls_enabled_stores = $this->settings['ls_enabled_stores'];
				}
			}
		}

		private function render_api_success_message(){
			if ( wclsi_oauth_enabled() && !empty( $this->store_name ) && !empty( $this->store_timezone ) ) {
				echo sprintf(
					'<p id="wclsi-api-status" style="%s">%s<strong>%s</strong>, %s<strong>%s</strong></p>',
					'color: green;',
					__( ' API Settings successfully initialized! Store name(s): ', 'woocommerce-lightspeed-pos' ),
					$this->store_name,
					__( 'Time Zone: ', 'woocommerce-lightspeed-pos' ),
					$this->store_timezone
				);
			}
		}

		private function check_for_ls_account( $account_response, $token ){
			if ( isset( $account_response->Account->accountID ) ) {

				// Save the token
				$this->token = $token;
				$wclsi_settings  = get_option( $this->get_settings_option_key() );

				// Delete the deprecated API Key if it exists
				if( isset( $wclsi_settings[ 'api_key' ] ) ){
					unset($wclsi_settings[ 'api_key' ]);
				}

				// Reset multi-store setting if we're changing accounts
				if ( isset( $this->ls_account_id ) && $this->ls_account_id != $account_response->Account->accountID ) {
					unset( $wclsi_settings[ 'ls_enabled_stores' ] );
				}

				update_option( $this->get_settings_option_key(), $wclsi_settings );

				$this->ls_account_id = $account_response->Account->accountID;

				// Save the account ID
				update_option( 'wclsi_account_id', $account_response->Account->accountID );
			} else {
				$this->init_errors[] = __( 'Could not find an account associated with this API key.', 'woocommerce-lightspeed-pos' );
				return;
			}
		}

		private function check_for_ls_shop_data( $shop_response ){

			// Note: in the case of multiple shops, the time-zone will default to the first store
			if ( !empty( $shop_response ) ) {

				// Add it to the settings
				$shop_data = array();
				$shop_data['store_timezone'] = is_array( $shop_response->Shop ) ? $shop_response->Shop[0]->timeZone : $shop_response->Shop->timeZone;
				$shop_data['store_name'] = is_array( $shop_response->Shop ) ? 'Multiple' : $shop_response->Shop->name;
				$shop_data['ls_store_data'] = $shop_response;

				if ( is_object( $shop_response->Shop ) ) {
					$wc_options = get_option( $this->get_settings_option_key() );
					$wc_options['ls_enabled_stores'] = array($shop_response->Shop->name => $shop_response->Shop->shopID);
					$wc_options[ WCLSI_INVENTORY_SHOP_ID ] = $shop_response->Shop->shopID;
					update_option( $this->get_settings_option_key(), $wc_options );
				}

				update_option( 'wclsi_shop_data', $shop_data );
			}
		}

		private function refresh_access_token() {
			$woo_connector_url = add_query_arg(
				array( 'refresh_token' => get_option('wclsi_refresh_token') ),
				WCLSI_REFRESH_CONNECTOR_URL
			);

			$result = wp_remote_post( $woo_connector_url );

			if ( is_wp_error( $result) ) { return $result; }

			if ( $result['response']['code'] == 200 ) {
				$response = json_decode( $result['body'] );

				if ( isset( $response->access_token ) ) {
					update_option('wclsi_oauth_token', $response->access_token );
					return $response->access_token;
				} elseif ( isset( $response->error ) && isset( $response->reason ) ) {
					return new WP_Error('could_not_retrieve_access_token', $response->reason, $result);
				}
			}

			return new WP_Error(
				'could_not_retrieve_access_token',
				'Something went wrong with refreshing you access token. Please contact support!',
				$result
			);
		}
	}

	global $WCLSI_API;
	$WCLSI_API = new LSI_Init_Settings();
endif;
