( function( $ ) {
    const wclsiNav = {
        initSearchSubmit: () => {
            const { searchForm, searchResultsDiv } = wclsiNav
            searchForm.on('submit', (e) => {
                e.preventDefault()
                const searchParams = {}
                searchForm.serializeArray().map(input => searchParams[input.name] = input.value)
                searchResultsDiv.block({ message: null, overlayCSS: { opacity: 0.6, backgroundColor: '#d0d0d0' } })
                setTimeout(() => wclsiNav.submitSearch(searchParams), wclsi_admin.WAIT_TIME)
            })
        },
        submitSearch: (searchParams) => {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    wclsi_nonce: wclsi_admin.WCLSI_NONCE,
                    action: 'wclsi_query_ls_api_ajax',
                    search_params: searchParams
                },
                dataType: 'json',
                success: function( data ) {
                    wclsiNav.checkForErrors(data)

                    if(data.results) {
                        wclsiNav.processSearchResults(data.results)
                    }

                    wclsi_admin.setWaitTime(data.WAIT_TIME)
                },
                error: function(jqXHR, statusText, errorThrown) {
                    wclsi_admin.handleErrors(jqXHR, statusText, errorThrown, wclsiNav.noticesDiv);
                }
            });                
        },
        processSearchResults: (searchResults) => {
            const { searchResultsDiv } = wclsiNav
            searchResultsDiv.unblock();
            if (searchResults) {
                searchResultsDiv.html(searchResults)
                wclsiNav.initAddAllCheckbox()
                wclsiNav.initAddItemsForm()
            }
        },
        initAddAllCheckbox: () => {
            wclsiNav.addAllCheckbox().on('click', (e) => {
                wclsiNav.itemCheckboxes().prop('checked', e.target.checked)
            })
        },
        initAddItemsForm: () => {
            wclsiNav.addItemsForm().on('submit', (e) => {
                e.preventDefault()
                const itemIds = []
                wclsiNav.addItemsForm().serializeArray().map(input => itemIds.push(input.value))
                setTimeout(() => wclsiNav.addItemsToImportTable(itemIds), wclsi_admin.WAIT_TIME)
            })
        },
        addItemsToImportTable: (itemIds) => {
            if (itemIds.length > 0) {
                const is_matrix_search = wclsiNav.searchForm.find('#search_matrix').is(':checked')
                const prod_data = {}
                if (is_matrix_search) {
                    prod_data.prod_ids = [];
                    prod_data.matrix_ids = itemIds;
                } else {
                    prod_data.prod_ids = itemIds;
                    prod_data.matrix_ids = [];                    
                }

                wclsi_admin.initProgressBar('', null, 'Adding products ...');
                wclsi_admin.executeBulkAction('add_items_to_import_table_ajax', prod_data, () => location.reload())
                tb_remove()
            }
        },
        setDOMElements: () => {
            wclsiNav.searchForm = $('#wclsi_api_navigator_search_form')
            wclsiNav.matrixOption = wclsiNav.searchForm.find('#search_matrix')
            wclsiNav.searchResultsDiv = $('#wclsi_api_navigator_search_results')
            wclsiNav.noticesDiv = $('#wclsi_api_navigator_notices')
            wclsiNav.addAllCheckbox = () => $('#wclsi_nav_add_all_items')
            wclsiNav.itemCheckboxes = () => wclsiNav.searchResultsDiv.find('input[type=checkbox]')
            wclsiNav.addItemsForm = () => $('#wclsi_api_navigator_bulk_add')
            wclsiNav.variationNotice = () => $('#wclsi-api-navigator-variation-notice')
        },
        checkForErrors: (data) => {
            wclsi_admin.checkForErrors( data, wclsiNav.noticesDiv );
            // Reset errors since we don't want to interfere with Import Table actions
            wclsi_admin.errors = []
        },
        showVariationNotice: () => {
            const { searchForm, matrixOption, variationNotice } = wclsiNav
            searchForm.on('change', () => {
                if( matrixOption.is(':checked') ) {
                    variationNotice().show()
                } else {
                    variationNotice().hide()
                }
            })
        },
        initMethods: () => {
            wclsiNav.initSearchSubmit()
            wclsiNav.showVariationNotice()
        },
        init: () => {
            wclsiNav.setDOMElements()
            wclsiNav.initMethods()
            $.blockUI.defaults.css = {};
        }
    }
    $(function() { wclsiNav.init(); } );
} )( jQuery );
