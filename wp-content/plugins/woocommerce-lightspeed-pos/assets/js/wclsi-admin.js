/**
 * Created by ryagudin on 12/31/14.
 */

if(!wclsi_admin) {
    const wclsi_admin = { errors: [] }
} else {
    wclsi_admin.errors = []
}

( function( $ ) {
    const wclsiJS = {

        /**
         * Handles displaying notices
         * @param notice
         * @param type updated or error
         * @param attachTo DOM element to attach notices to
         */
        displayNotice: function(notice, type, attachTo = null) {
            type = type || 'error'; // make it error by default

            // Try to insert after h1 if we're in a post page (for meta box actions)
            const attachToElem = attachTo || $('.wp-header-end');
            const msgDiv =
                $(
                    `<div class="${type} settings-error notice is-dismissible" data-wclsi="wclsi-error">
                        <p>${notice}</p>
                        <button type="button" class="notice-dismiss">
                            <span class="screen-reader-text">Dismiss this notice.</span>
                        </button>
                    </div>`
                );

            msgDiv.find(":button.notice-dismiss").on('click', () => msgDiv.hide());
            
            if( attachToElem.length === 0 ) {
                // Try to get a handle of an already existing notice
                const firstNotice = $('.notice').first();
                if ( firstNotice.length > 0 ) {
                    msgDiv.insertAfter(firstNotice);
                } else {
                    const wrap = $('.wrap') || $('.wpwrap');
                    msgDiv.prependTo(wrap);
                }
            } else {
                msgDiv.insertAfter(attachToElem)
            }

            $('#wp__notice-list').show();
            
            $('html, body').animate({ scrollTop: 0 }, 100);
        },
        setWaitTime: function(waitTime) {
            if ( waitTime ) {
                wclsi_admin.WAIT_TIME = parseInt( waitTime );
            } else {
                wclsi_admin.WAIT_TIME = 1200;
            }

            if ( wclsi_admin.SCRIPT_DEBUG ){ console.log(`WAIT_TIME: ${wclsi_admin.WAIT_TIME}`) }
        },
        /**
         * Initializes click listener for the 'Load LightSpeed Products' button.
         * @param loadProdButton
         */
        initLSLoadProds: function( loadProdButton ) {
            loadProdButton.click(function() {

                // Ask the user if they're sure they want to re-load
                if ( $(this).data('reload') ) {
                    if ( confirm( objectL10n.reload_confirm ) ) {
                        $('#wclsi-load-progress').show();
                        wclsiJS.loadProds(0, 1, true);
                    }
                } else {
                    $('#wclsi-load-progress').show();
                    wclsiJS.loadProds(0, 1, true);
                }
            } );
        },
        /**
         * Makes an AJAX request to get the count of total products,
         * after which makes a request every second as to not exceed API throttling limits
         */
        loadProds: function( offset, limit, getCount, getMatrix ) {
            let pct_complete = 0;
            let progress_div = $( '#wclsi-progress-count' );

            if( getMatrix === undefined || getMatrix === '' ) { getMatrix = false; }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    wclsi_nonce:  wclsi_admin.WCLSI_NONCE,
                    action: 'wclsi_load_prods_ajax',
                    offset   : offset,
                    limit    : limit,
                    getCount : getCount,
                    getMatrix: getMatrix
                },
                dataType: 'json',
                success: function( data ) {

                    if ( wclsi_admin.SCRIPT_DEBUG ) { console.log(data); }

                    wclsiJS.checkForErrors( data );
                    wclsiJS.setWaitTime( data.WAIT_TIME );

                    if ( getCount ) {
                        wclsiJS.prodCount = data.count;
                        setTimeout( () => wclsiJS.loadProds( offset, 50, false, getMatrix ), wclsi_admin.WAIT_TIME );
                    } else {
                        offset = offset + limit;

                        pct_complete = ( offset / wclsiJS.prodCount ) * 100;
                        progress_div.html( `${Math.floor( pct_complete )}%` ); // shows the progress

                        if ( wclsi_admin.SCRIPT_DEBUG ) { console.log(`${offset}/${wclsiJS.prodCount}`); }

                        if ( offset < wclsiJS.prodCount ) {
                            setTimeout( () => wclsiJS.loadProds( offset, limit, false, getMatrix ), wclsi_admin.WAIT_TIME );
                        } else {
                            if( !getMatrix ) {
                                progress_div.html( '0%' ); // shows the progress
                                $('#wclsi-progress-msg').html(objectL10n.loading_matrix_products);
                                setTimeout( () => wclsiJS.loadProds(0, 1, true, true), wclsi_admin.WAIT_TIME );
                            } else {
                                if ( wclsi_admin.errors.length > 0 ) {
                                    $('#wclsi-load-progress').html(`<div class="error">${objectL10n.incomplete_load}</div>`);
                                } else {
                                    location.replace(data.redirect); // We're done! Reload to view the results ...	
                                }
                            }
                        }
                    }
                },
                error: function(jqXHR, statusText, errorThrown) {
                    wclsiJS.handleErrors(jqXHR, statusText, errorThrown);
                    $('#wclsi-load-progress').hide();
                }
            } );
        },
        /**
         * Initializes click listener for the 'Import LightSpeed Categories' button.
         * @param importCatsButton
         */
        initImportCats: function( importCatsButton ) {
            importCatsButton.click(function() {
                $('#wclsi-import-cats-progress').html( '<p>Importing Lightspeed categories - 0% completed ... </p>' );
                wclsiJS.getCatCount();
            } );
        },
        getCatCount: function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    wclsi_nonce:  wclsi_admin.WCLSI_NONCE,
                    action: 'get_category_count'
                },
                dataType: 'json',
                success: function (data) {
                    if ( wclsi_admin.SCRIPT_DEBUG ) { console.log(data); }
                    
                    wclsiJS.checkForErrors( data );
                    wclsiJS.setWaitTime( data.WAIT_TIME );
                    wclsiJS.catCount = data.ciel_count;
                    if (wclsiJS.catCount > 0) {
                        wclsiJS.loadCats(0, 100);
                    } else {
                        $('#wclsi-import-cats-progress').html('There are 0 categories to import!')
                    }
                },
                error: function (jqXHR, statusText, errorThrown) {
                    if ( statusText === 'parsererror' ) {
                        wclsiJS.displayNotice(jqXHR.responseText, 'error');
                    }
                    wclsiJS.displayNotice(errorThrown, 'error');
                }
            });
        },
        /**
         * @param offset
         * @param limit
         */
        loadCats: function( offset, limit ) {
            let pct_complete = 0;
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    wclsi_nonce:  wclsi_admin.WCLSI_NONCE,
                    action: 'import_ls_categories',
                    offset: offset,
                    limit: limit
                },
                dataType: 'json',
                success: function( data ) {
                    if ( wclsi_admin.SCRIPT_DEBUG ) { console.log(data); }

                    wclsiJS.checkForErrors( data );
                    wclsiJS.setWaitTime( data.WAIT_TIME );

                    offset = offset + limit;

                    if ( wclsi_admin.SCRIPT_DEBUG ) { console.log(`${offset}/${wclsiJS.catCount}`); }

                    if ( offset < wclsiJS.catCount ) {
                        pct_complete = ( (offset + 100) / wclsiJS.catCount ) * 100;
                        $('#wclsi-import-cats-progress').html(
                            `<p>Importing Lightspeed categories - ${Math.floor( pct_complete )}% completed ... </p>`
                        );

                        // Try and avoid 429 Lightspeed API throttling - delay each call by 1 second
                        setTimeout( () => wclsiJS.loadCats( offset, limit ), wclsi_admin.WAIT_TIME );
                    } else {
                        if ( data.load_complete && data.errors.length === 0 ) {
                            $('#wclsi-import-cats-progress').html(
                                `<p>Category import complete! Click <a href="${data.prod_cat_link}">here</a> to view the imported categories.</p>`
                            );
                        } else if ( data.load_complete && data.errors.length > 0 ) {
                            $('#wclsi-import-cats-progress').html(
                                `<div class="error">
                                    Category import completed but not all categories were succesfully imported. 
                                    See errors above for more details.
                                    Click <a href="${data.prod_cat_link}">here</a> to view imported categories.
                                </div>`
                            );
                        } else if ( data.errors.length > 0 ) {
                            $('#wclsi-import-cats-progress').html(`<div class="error">${objectL10n.incomplete_load}</div>`);
                        }
                    }
                },
                error: function(jqXHR, statusText, errorThrown) {
                    wclsiJS.handleErrors(jqXHR, statusText, errorThrown);
                }
            } );
        },
        /**
         * Handles click operation for the import all products button
         * @param importAllButton
         */
        importAllProdsButton: function( importAllButton ) {
            importAllButton.click(function() {
                const numberOfItems = $('.displaying-num').first().text();
                const result = confirm( `Are you sure you want to import all ${numberOfItems}?` );
                if ( result ) {
                    wclsiJS.importAllButton.prop('disabled', true);
                    wclsiJS.importAllProds( true ); // pass true for initial call
                }
            } );
        },
        initProgressBar: function(action, totalProds, customText) {
            wclsiJS.progressDivID       = 'wclsi_sync_progress';
            wclsiJS.progressCountSpanID = 'wclsi_progress_count';

            let text, progressTxt = '';
            if ( customText ) {
                text = customText;
            } else {
                text = action === 'update' ? objectL10n.updating_prods : objectL10n.importing_prods;	
            }
            totalProds = totalProds ? totalProds : wclsiJS.totalProds;
            progressTxt = totalProds ? `0/${totalProds}` : '';

            $('.wrap').children('h1:first').after(
                `<div id="${wclsiJS.progressDivID}" class="updated">
                    <p>
                        ${text}
                        <strong><span id="${wclsiJS.progressCountSpanID}">${progressTxt}</span></strong>
                        <span class="spinner wclsi-spinner" style="float: none; vertical-align: text-bottom;" />
                    </p>
                    <p><strong>${objectL10n.dont_close}</strong></p>
                </div>`
            );
            
            $('html, body').animate({ scrollTop: 0 }, 100);
        },
        /**
         * AJAX function that calls methods to import all the loaded products
         * from LightSpeed into WooCommerce.
         */
        importAllProds: function( initImport ) {
            if (initImport === undefined || initImport === '') {
                initImport = false;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    wclsi_nonce:  wclsi_admin.WCLSI_NONCE,
                    action: 'get_prod_ids_ajax',
                    init_import: initImport
                },
                dataType: 'json',
                success: function( data ) {

                    wclsiJS.checkForErrors( data );
                    wclsiJS.setWaitTime( data.WAIT_TIME );

                    if ( data.prod_ids.length > 0 ) {
                        wclsiJS.totalProds       = data.prod_ids.length + data.matrix_ids.length;
                        wclsiJS.totalSingleProds = data.prod_ids.length;
                        wclsiJS.totalMatrixProds = data.matrix_ids.length;
                        wclsiJS.prodIds          = data.prod_ids;
                        wclsiJS.matrixIds        = data.matrix_ids;
                        wclsiJS.importProgress   = 0;

                        wclsiJS.initProgressBar();

                        wclsiJS.runSingleProdAction(
                            'import_all_lightspeed_products_ajax',
                            'false',
                            wclsiJS.totalSingleProds,
                            0,
                            function(){ location.reload() }
                        );

                    } else {
                        wclsiJS.displayNotice( objectL10n.no_prods_error, 'error' );
                    }
                },
                error: function(jqXHR, statusText, errorThrown) {
                    wclsiJS.handleErrors(jqXHR, statusText, errorThrown);
                }
            } );
        },
        /**
         * Used to import a single product
         * @param action          - the action to perform on the product
         * @param isMatrix        - whether this is a matrix product
         * @param totalLocalProds - optional, if given, will make a recursive call "totalProds" number of times
         * @param prodCount       - the current prod count so we don't exceed totalProds
         * @param done            - callback when action is complete
         */
        runSingleProdAction: function( action, isMatrix, totalLocalProds, prodCount, done ) {
            // Stop condition for recursive calls
            if ( totalLocalProds === undefined || totalLocalProds === '') { return; }

            let prodID;
            if ( isMatrix === 'true' ) {
                prodID = wclsiJS.matrixIds[ prodCount ];
            } else {
                prodID = wclsiJS.prodIds[ prodCount ];
            }

            $.ajax({
                url: wclsi_admin.AJAX_URL,
                type: 'POST',
                data: {
                    wclsi_nonce: wclsi_admin.WCLSI_NONCE,
                    action: action,
                    prod_id: prodID,
                    import_progress: wclsiJS.importProgress, // How many products have been called
                    is_matrix: isMatrix,
                    sync_flag: $('#wclsi-enable-sync-on-import-all').is(':checked')
                },
                dataType: 'json',
                success: function( data ) {
                    if ( wclsi_admin.SCRIPT_DEBUG ) { 
                        console.log(data); 
                    }

                    wclsiJS.checkForErrors( data );
                    wclsiJS.setWaitTime( data.WAIT_TIME );

                    if ( wclsi_admin.SCRIPT_DEBUG ) {
                        console.log(`progress: ${wclsiJS.importProgress}`);
                        console.log(`totalProds: ${wclsiJS.totalProds}`);
                    }

                    $(`#${wclsiJS.progressCountSpanID}`).html( `${wclsiJS.importProgress}/${wclsiJS.totalProds}` );

                    if ( wclsiJS.importProgress === wclsiJS.totalProds-1 ) {
                        wclsiJS.importAllButton.prop( 'disabled', false );
                        if( wclsi_admin.errors.length > 0 ) {
                            $(`#${wclsiJS.progressDivID}`).html(`<p>${objectL10n.prod_processing_error}</p>`)
                        } else {
                            if( typeof done == 'function' ) { done(); }
                        }
                    }

                    prodCount++;

                    /**
                     * Use setTimeout of 1.5s to avoid 429 errors from Lightspeed due to API throttling
                     */
                    if ( (prodCount < totalLocalProds) && ( isMatrix === 'false' ) ) {
                        // Recursive call for single prods
                        setTimeout(() => wclsiJS.runSingleProdAction( action, isMatrix, totalLocalProds, prodCount, done ), wclsi_admin.WAIT_TIME );
                    } else if ( (prodCount < totalLocalProds) && ( isMatrix === 'true' ) ) {
                        // Recursive call for matrix prods
                        setTimeout(() => wclsiJS.runSingleProdAction( action, isMatrix, totalLocalProds, prodCount, done ), wclsi_admin.WAIT_TIME );
                    } else if ( (prodCount >= totalLocalProds) && (isMatrix === 'false') ) {
                        // Initial recursive call for matrix prods after single prods are done
                        if( wclsiJS.totalMatrixProds > 0 ) {
                            setTimeout(() => wclsiJS.runSingleProdAction( action, 'true', wclsiJS.totalMatrixProds, 0, done), wclsi_admin.WAIT_TIME )
                        }
                    }

                    wclsiJS.importProgress++;
                },
                error: function(jqXHR, statusText, errorThrown) {
                    wclsiJS.importProgress++; // increment progress even if there's an error...
                    wclsiJS.handleErrors(jqXHR, statusText, errorThrown);
                }
            } );
        },
        /**
         * Handler for checking/unchecking sync checkbox(es)
         * @param checkBoxElem
         */
        syncCheckBoxHandler: function( checkBoxElem ) {
            checkBoxElem.click( function(e) {
                let prodID = $(this).data( 'prodid' );
                if ( prodID === "" || prodID === undefined ) {
                    alert( objectL10n.sync_error );
                    e.preventDefault();
                } else {
                    if ($(this).is(':checked')) {
                        // Enable sync flag
                        wclsiJS.setProdSync(prodID, true);
                    } else {
                        // Disable sync flag
                        wclsiJS.setProdSync(prodID, false);
                    }
                }
            } )
        },
        /**
         * Sets a product's sync flag to true or false
         * @param prodID
         * @param syncFlag Boolean
         */
        setProdSync: function( prodID, syncFlag ) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    wclsi_nonce: wclsi_admin.WCLSI_NONCE,
                    action: 'set_prod_sync_ajax',
                    prod_id: prodID,
                    sync_flag: syncFlag
                },
                dataType: 'json',
                success: function( data ) {
                    wclsiJS.checkForErrors( data );

                    if ( syncFlag ) {
                        alert(objectL10n.sync_success);
                    } else {
                        alert(objectL10n.sync_remove);
                    }
                },
                error: function(jqXHR, statusText, errorThrown) {
                    wclsiJS.handleErrors(jqXHR, statusText, errorThrown);
                }
            } );
        },
        /**
         * Handler for updating a product via the "Manual Update" button in the product page via the metabox
         * @param buttonElem
         */
        manualProdUpdateHander: function( buttonElem ) {
            buttonElem.click( function() {
                let prodID = $(this).data( 'prodid' );
                let noticeID = 'wclsi-sync-notice';
                $(this).attr( 'disabled', 'disabled' );
                $(`<p id="${noticeID}">
                    <span class="spinner wclsi-spinner" id="wclsi-sync-spinner" style="visibility: visible; top: 0;"/>
                    ${objectL10n.syncing}
                   </p>`
                ).insertAfter( $(this) );

                setTimeout(
                    function() {
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                wclsi_nonce:  wclsi_admin.WCLSI_NONCE,
                                action: 'manual_prod_update_ajax',
                                prod_id: prodID
                            },
                            dataType: 'json',
                            success: function( data ) {
                                wclsiJS.checkForErrors( data );
                                wclsiJS.setWaitTime( data.WAIT_TIME );

                                if ( data.success ) {
                                    if( data.errors.length === 0 ) {
                                        $(`#${noticeID}`).html( objectL10n.man_sync_success );
                                        location.reload();
                                    } else {
                                        $(`#${noticeID}`).html( objectL10n.generic_error );
                                    }
                                } else {
                                    wclsiJS.displayNotice( objectL10n.generic_error, 'error' );
                                }
                            },
                            error: function(jqXHR, statusText, errorThrown) {
                                wclsiJS.handleErrors(jqXHR, statusText, errorThrown);
                            }
                        });
                    },
                    wclsi_admin.WAIT_TIME
                )
            } );
        },
        /**
         * Checks errors returns for AJAX results
         * @param data
         * @param attachTo DOM element to attach errors notices to
         */
        checkForErrors: function( data, attachTo = null ) {
            if ( data.errors ) {
                if ( Array.isArray( data.errors ) ) {
                    data.errors.forEach( function( e ) {
                        wclsiJS.displayNotice( e.message, 'error', attachTo );
                        wclsi_admin.errors.push(e);
                    } );
                } else if ( "string" === typeof data.errors ) {
                    wclsiJS.displayNotice( data.errors, 'error', attachTo );
                    wclsi_admin.errors.push(data.errors);
                } else {
                    wclsiJS.displayNotice( objectL10n.generic_error, 'error', attachTo );
                    wclsi_admin.errors.push(objectL10n.generic_error);
                }
            }
        },
        /**
         * Binds click event to sync WC prod to LightSpeed.
         * @param button
         */
        bindClickToSyncToLSButton: function( button ) {
            button.on('click', function() {
                const prodID = $( this ).data( 'prodid' );
                wclsiJS.syncProdToLS( prodID );
            });
        },
        /**
         * AJAX call to sync product with LightSpeed.
         * @param prodID
         * @param showSuccess
         */
        syncProdToLS: function( prodID, showSuccess ) {
            const statusDivID = 'wclsi-sync-status';
            const noticeID    = 'wclsi-sync-status-div';
            const statusDiv   = $( `#${statusDivID}` );
            let progressTxt = '';
            
            if (wclsiJS.syncProdToLSprogress && wclsiJS.variationIds) {
                progressTxt = `${wclsiJS.syncProdToLSprogress}/${wclsiJS.variationIds.length}`; 
            }

            let statusHTML = 
                `<div id="${statusDivID}" style="display: block; padding: 5px;"> 
                    <div id="${noticeID}">
                        ${objectL10n.syncing} ${progressTxt}
                        <span style="float: inherit; margin: 0; vertical-align: inherit;" class="spinner wclsi-spinner" />				  
                    </div>
                </div>`;
            
            if ( statusDiv.length > 0 ) {
                statusDiv.replaceWith( statusHTML );
            } else {
                $( statusHTML ).insertAfter( wclsiJS.syncProdToLSButton );
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    wclsi_nonce:  wclsi_admin.WCLSI_NONCE,
                    action: 'sync_prod_to_ls',
                    wc_prod_id: prodID
                },
                dataType: 'json',
                success: function( data ) {
                    wclsiJS.checkForErrors( data );
                    wclsiJS.setWaitTime( data.WAIT_TIME );

                    if ( data.show_success || showSuccess ) {
                        $(`#${noticeID}`).html( objectL10n.man_sync_success );
                        location.reload();
                        return;
                    }

                    if ( data.variation_ids || data.is_variation ) {
                        if (!wclsiJS.variationIds) {
                            wclsiJS.variationIds = data.variation_ids;
                            wclsiJS.syncProdToLSprogress = 0;
                        }

                        setTimeout(
                            function() {
                                wclsiJS.syncProdToLS(
                                    wclsiJS.variationIds[wclsiJS.syncProdToLSprogress++],
                                    wclsiJS.syncProdToLSprogress === wclsiJS.variationIds.length
                                )
                            },
                            (wclsi_admin.WAIT_TIME * 1.50) // This seems like a fairly expensive action, let's wait longer
                        );
                    }
                },
                error: function(jqXHR, statusText, errorThrown) {
                    wclsiJS.handleErrors(jqXHR, statusText, errorThrown);
                }
            } );
        },
        overrideBulkSubmit: function( form ) {
            form.submit( function(e) {
                let action = wclsiJS.topBulkActionSelector.val();

                if( action === 'import_and_sync' || action === 'import' || action === 'update' ){
                    e.preventDefault();
                } else {
                    return true;
                }

                let inputs = form.find('input:checked[name^=wc_ls_imported_prod]');

                let prod_data = {};
                prod_data.prod_ids = [];
                prod_data.matrix_ids = [];

                for (let i = 0, len = inputs.length; i < len; i++) {
                    let prod = $(inputs[i]);
                    if( prod.data('prod-type') === 'matrix' ){
                        let formatted_id = prod.val().split( '-' )[0];
                        prod_data.matrix_ids.push( formatted_id );
                    }
                    if( prod.data('prod-type') === 'single' ){
                        prod_data.prod_ids.push( prod.val() )
                    }
                }
                let callback = function(){ location.reload() };
                wclsiJS.initProgressBar(action);
                wclsiJS.executeBulkAction( `${action}_product_ajax`, prod_data, callback );
                return false;
            })
        },
        /**
         * @param action - the wp_ajax method
         * @param prod_data - take the form of: {prods_ids: [ .. ], matrix_ids: [ .. ]}
         * @param callback
         */
        executeBulkAction: function( action, prod_data, callback ) {
            if ( wclsi_admin.SCRIPT_DEBUG ) {
                console.log(action);
                console.log(prod_data);
            }

            wclsiJS.totalProds       = prod_data.prod_ids.length + prod_data.matrix_ids.length;
            wclsiJS.totalSingleProds = prod_data.prod_ids.length;
            wclsiJS.totalMatrixProds = prod_data.matrix_ids.length;
            wclsiJS.prodIds          = prod_data.prod_ids;
            wclsiJS.matrixIds        = prod_data.matrix_ids;
            wclsiJS.importProgress   = 0;

            // Actions: import_and_sync, import, sync, update, delete (append "_ajax" to refer to back-end wp functions)
            if(wclsiJS.totalSingleProds > 0) {
                wclsiJS.runSingleProdAction(action, 'false', wclsiJS.totalSingleProds, 0, callback);
            } else {
                wclsiJS.runSingleProdAction(action, 'true', wclsiJS.totalMatrixProds, 0, callback);
            }
        },
        bindRelinkElem: function( relinkElem ) {
            relinkElem.click(function(){
                let prod_id = $(this).data('prod-id');
                let spinner = $('<p>Relinking ...<span class="spinner wclsi-spinner" id="wclsi-sync-spinner" style="float: none; vertical-align: initial;" /></p>');

                if ( $('#wclsi-sync-spinner').length === 0 ) {
                    spinner.insertAfter( $('#wclsi-relink-wrapper') );
                }

                setTimeout(() => wclsiJS.relinkProd( prod_id, function(){ spinner.remove() } ), wclsi_admin.WAIT_TIME );
                
                return false;
            });
        },
        relinkProd: function( prod_id, callback ) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    wclsi_nonce:  wclsi_admin.WCLSI_NONCE,
                    action: 'relink_wc_prod_ajax',
                    wc_prod_id: prod_id
                },
                dataType: 'json',
                success: function( data ) {
                    wclsiJS.checkForErrors( data );

                    if ( data.success ) {
                        wclsiJS.displayNotice( objectL10n.relink_success, 'updated' );
                    } else if ( !data.success && ( undefined === data.errors || 0 === data.errors.length ) ) {
                        wclsiJS.displayNotice( objectL10n.generic_error, 'error' );
                    }

                    callback();
                },
                error: function(jqXHR, statusText, errorThrown) {
                    wclsiJS.handleErrors(jqXHR, statusText, errorThrown);
                    callback();
                }
            } );
        },
        handleErrors: function(jqXHR, statusText, errorThrown, attachTo = null) {
            if ( statusText === 'parsererror' ) {
                wclsiJS.displayNotice( jqXHR.responseText, 'error', attachTo );
            }
            wclsiJS.displayNotice( errorThrown, 'error', attachTo );
        },
        selectAllCheckboxesButton: function(selectAllElem, checkboxes) {
            selectAllElem.click(function() {
                checkboxes.prop("checked", !checkboxes.prop("checked"));
            });
        },
        handleAutoImportVisibility: function( autoLoadCheckbox ) {
            autoLoadCheckbox.click(function() {
                if( $(this).is(':checked') ) {
                    wclsiJS.autoLoadImportTableHeader.show()
                } else {
                    wclsiJS.autoLoadImportTableHeader.hide()
                }
            });
        },
        handleDeleteCatCache: function ( deleteCatCacheButton ) {
            deleteCatCacheButton.click(function() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        wclsi_nonce:  wclsi_admin.WCLSI_NONCE,
                        action: 'clear_category_cache'
                    },
                    dataType: 'json',
                    success: function( data ) {
                        wclsiJS.checkForErrors( data );

                        if ( data.success ) {
                            wclsiJS.displayNotice( objectL10n.cat_cache_clear_success, 'updated' );
                        } else if ( !data.success && ( undefined === data.errors || 0 === data.errors.length ) ) {
                            wclsiJS.displayNotice( objectL10n.generic_error, 'error' );
                        }
                    },
                    error: function(jqXHR, statusText, errorThrown) {
                        wclsiJS.handleErrors(jqXHR, statusText, errorThrown);
                    }
                });

                return false;
            });
        },
        /**
         * Initializes JS for the admin page
         */
        init: function() {
            wclsi_admin.WAIT_TIME = 1000;
            wclsiJS.initAPIButton  = $( `#${wclsi_admin.PLUGIN_PREFIX_ID}init_api_settings_button` );
            wclsiJS.importAllButton  = $( '#wc-import-all-prods' );
            wclsiJS.loadLSProdButton = $( '#wc-ls-load-prods' );
            wclsiJS.submitAPIButton = $( '.submit input:submit' );
            wclsiJS.syncCheckBoxes  = $( '.wclsi-sync-cb' );
            wclsiJS.prodManualSyncButton = $( '#wclsi-manual-sync' );
            wclsiJS.syncProdToLSButton = $( '#wclsi-sync-to-ls' );
            wclsiJS.importAndSyncLinks = $('.wc_ls_imported_prods .import_and_sync a');
            wclsiJS.importLinks = $('.wc_ls_imported_prods .import a');
            wclsiJS.prodTableForm = $('#wc-imported-prods-filter');
            wclsiJS.topBulkActionSelector = $('#bulk-action-selector-top');
            wclsiJS.relinkElem = $('#wclsi-relink');
            wclsiJS.importLSCats = $('#wclsi-import-cats');
            wclsiJS.selectiveSyncCheckboxWrapper = $('.wclsi-selective-sync-checkboxes');
            wclsiJS.autoLoadCheckbox = $('#woocommerce_lightspeed-integration_ls_to_wc_auto_load');
            wclsiJS.autoLoadImportTableHeader = $('#wclsi_import_on_auto_load_wrapper');
            wclsiJS.deleteCatCacheButton = $('#wclsi-delete-cat-cache');

            // Initialize Methods
            wclsiJS.initLSLoadProds( wclsiJS.loadLSProdButton );
            wclsiJS.importAllProdsButton( wclsiJS.importAllButton );
            wclsiJS.syncCheckBoxHandler( wclsiJS.syncCheckBoxes );
            wclsiJS.manualProdUpdateHander( wclsiJS.prodManualSyncButton );
            wclsiJS.bindClickToSyncToLSButton( wclsiJS.syncProdToLSButton );
            wclsiJS.overrideBulkSubmit( wclsiJS.prodTableForm );
            wclsiJS.bindRelinkElem( wclsiJS.relinkElem );
            wclsiJS.initImportCats( wclsiJS.importLSCats );
            wclsiJS.handleAutoImportVisibility( wclsiJS.autoLoadCheckbox );
            wclsiJS.handleDeleteCatCache( wclsiJS.deleteCatCacheButton );            
            wclsiJS.selectAllCheckboxesButton(
                $( '#wclsi_select_all_prod_sync_properties' ),
                $( '#wc_prod_selective_sync_properties input[type=checkbox]' )
            );
            wclsiJS.selectAllCheckboxesButton(
                $( '#wclsi_select_all_ls_prod_sync_properties' ),
                $( '#ls_prod_selective_sync_properties input[type=checkbox]' )
            );

            // Tooltips!
            if( wclsi_admin.IS_ADMIN ) {
                $('#tiptip_holder').removeAttr('style');
                $('#tiptip_arrow').removeAttr('style');
                $('.tips').tipTip({
                    'attribute': 'data-tip',
                    'fadeIn': 50,
                    'fadeOut': 50,
                    'delay': 200
                });
            }

            // Export methods to wclsiNav
            wclsi_admin.displayNotice = wclsiJS.displayNotice;
            wclsi_admin.checkForErrors = wclsiJS.checkForErrors;
            wclsi_admin.setWaitTime = wclsiJS.setWaitTime;
            wclsi_admin.handleErrors = wclsiJS.handleErrors;
            if ( wclsi_admin.IS_ADMIN ) {
                wclsi_admin.executeBulkAction = wclsiJS.executeBulkAction
                wclsi_admin.initProgressBar = wclsiJS.initProgressBar;
            }
        }
    };

    $(function() { wclsiJS.init(); } );
} )( jQuery );
