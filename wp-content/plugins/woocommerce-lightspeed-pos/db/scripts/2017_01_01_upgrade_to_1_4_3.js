(function($) {
    var wclsi_upgrade_to_1_4_3 = {

        linkToUpgradeButton: function( upgradeButton ) {
            var self = this;
            upgradeButton.click( function() {
                self.getChunkCount();
                return false;
            } );
        },
        getChunkCount: function() {
            var self = this;

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    wclsi_admin_nonce: self.nonce,
                    action: 'wclsi_1_4_3_get_total_chunk_count'
                },
                dataType: 'json',
                success: function (data) {
                    self.checkForErrors( data );

                    if ( wclsi_admin.SCRIPT_DEBUG ) {
                        console.log(data);
                    }

                    self.totalProdChunks = parseInt( data.total_prod_chunks );
                    self.totalMatrixChunks = parseInt( data.total_matrix_chunks );
                    self.totalCatChunks = parseInt( data.total_cat_chunks );
                    self.totalItemAttrChunks = 1;

                    $('#wclsi-load-progress').show();

                    self.importChunk( 'single', 0 );
                },
                error: function (jqXHR, statusText, errorThrown) {
                    if (statusText === 'parsererror') {
                        self.displayNotice(jqXHR.responseText, 'error');
                    }
                    self.displayNotice(errorThrown, 'error');
                }
            });
        },
        importChunk: function( chunkType, chunkCount ) {
            var self = this;

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    wclsi_admin_nonce: self.nonce,
                    action: 'wclsi_1_4_3_move_chunk',
                    chunk_id: chunkCount,
                    importType: chunkType
                },
                dataType: 'json',
                success: function (data) {
                    self.checkForErrors( data );

                    if ( wclsi_admin.SCRIPT_DEBUG ) {
                        console.log(data);
                    }

                    chunkCount++;

                    if ( chunkCount < self.getTotalChunks( chunkType ) ) {
                        pct_complete = chunkCount +'/'  + self.getTotalChunks(chunkType);
                        $('#wclsi-progress-count').html( pct_complete ); // shows the progress

                        self.importChunk( chunkType, chunkCount );
                    } else {
                        self[chunkType + '_completed'] = true;

                        var nextChunkType = self.getNextChunkType();
                        if ( false !== nextChunkType ) {
                            self.importChunk( nextChunkType, 0 );
                        } else {
                            // we're done!
                            self.completeUpgrade();
                        }
                    }
                },
                error: function (jqXHR, statusText, errorThrown) {
                    if (statusText === 'parsererror') {
                        self.displayNotice(jqXHR.responseText, 'error');
                    }
                    self.displayNotice(errorThrown, 'error');
                }
            });
        },
        getNextChunkType: function() {
            var self = this;

            if ( !self['matrix_completed'] ) {
                $('#wclsi-progress-msg').html(objectL10n.loading_matrix_products);
                return 'matrix';
            }
            if( !self['single_completed'] ) {
                return 'single';
            }
            if( !self['cat_completed'] ) {
                $('#wclsi-progress-msg').html(objectL10n.loading_categories);
                return 'cat';
            }
            if( !self['item_attrs_completed'] ) {
                $('#wclsi-progress-msg').html(objectL10n.loading_item_attrs);
                return 'item_attrs';
            }
            return false;
        },
        getTotalChunks: function( type ) {
            var self = this;
            if ( type === 'matrix' ) {
                return self.totalMatrixChunks;
            }
            if ( type === 'single' ) {
                return self.totalProdChunks;
            }
            if( type === 'cat' ) {
                return self.totalCatChunks;
            }
            if( type === 'item_attrs' ) {
                return self.totalItemAttrChunks;
            }
        },
        completeUpgrade: function() {
            var self = this;

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    wclsi_admin_nonce: self.nonce,
                    action: 'wclsi_1_4_3_complete_upgrade'
                },
                dataType: 'json',
                success: function (data) {
                    self.checkForErrors( data );

                    if ( wclsi_admin.SCRIPT_DEBUG ) {
                        console.log(data);
                    }

                    if( data.success ) {
                        $('#wclsi-load-progress').html('<p>' + objectL10n.upgrade_complete + '</p>');
                        location.replace(wclsi_options.wclsi_import_page);
                    }
                },
                error: function (jqXHR, statusText, errorThrown) {
                    if (statusText === 'parsererror') {
                        self.displayNotice(jqXHR.responseText, 'error');
                    }
                    self.displayNotice(errorThrown, 'error');
                }
            });
        },
        init: function() {
            var self = this;

            // Import global methods
            self.checkForErrors = wclsi_admin.checkForErrors;
            self.displayNotice = wclsi_admin.displayNotice;

            // Create handles to important elements
            self.nonce = $( '#wclsi_admin_nonce' ).val();
            self.upgrade_1_4_3_button = $('#wclsi-1-4-3-upgrade');

            // Initialize Methods
            self.linkToUpgradeButton( self.upgrade_1_4_3_button );
        }
    };

    $( document ).ready( function() {
        wclsi_upgrade_to_1_4_3.init();
    } );

}) ( jQuery );