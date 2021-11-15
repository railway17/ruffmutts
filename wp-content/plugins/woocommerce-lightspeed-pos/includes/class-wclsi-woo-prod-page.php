<?php

if ( !class_exists( 'WCLSI_Woo_Prod_Page' ) ) :

	class WCLSI_Woo_Prod_Page {
		function __construct() {
			add_action( 'admin_notices', array( $this, 'show_archived_warning' ), 10 );
		}

		function show_archived_warning () {
			$wc_prod_id = get_the_ID();
			if ( !$wc_prod_id ) {
				return;
			}

			$ls_prod = new WCLSI_Lightspeed_Prod();
			$ls_prod->init_via_wc_prod_id( get_the_ID() );
			if ( $ls_prod->id > 0 && $ls_prod->archived ) {
				?>
				<div class="error is-dismissible'">
					<p>Warning: this is an <strong>Archived Lightspeed Product!</strong></p>
					<p>This product has been archived in Lightspeed.</p>
					<p>It is recommended to <strong>hide this product from your store front</strong> if you have not already done so!</p>
				</div>
				<?php
			}

			if ( $ls_prod->is_matrix_product() ) {
				$wc_prod = wc_get_product( get_the_ID() );
				$variation_ids = $wc_prod->get_children();
				$archived_children = [];
				foreach ( $variation_ids as $variation_id ) {
					$child_ls_prod = new WCLSI_Lightspeed_Prod();
					$child_ls_prod->init_via_wc_prod_id( $variation_id );
					if ( $child_ls_prod->id > 0 && $child_ls_prod->wc_prod_id > 0 && $child_ls_prod->archived ) {
						$wc_variation = wc_get_product( $child_ls_prod->wc_prod_id );
						if ( $wc_variation->get_id() > 0 ) {
							$archived_children[] = $wc_variation->get_formatted_name();
						}
					}
				}

				if ( count( $archived_children ) > 0 ) {
					?>
					<div class="error is-dismissible'">
						<p>Warning: there are one or more variations that are <strong>archived lightspeed products!</strong></p>
						<p>The following variations have been archived in Lightspeed:</p>
						<ul style="list-style: circle; margin-left: 30px;">
							<?php foreach ( $archived_children as $archived_child ) { echo "<li>{$archived_child}</li>"; } ?>
						</ul>
						<p>It is recommended to <strong>hide the variations from your store front</strong> if you have not already done so!</p>
					</div>
					<?php
				}
			}
		}
	}

	global $WCLSI_Woo_Prod_Page;
	$WCLSI_Woo_Prod_Page = new WCLSI_Woo_Prod_Page();
endif;
