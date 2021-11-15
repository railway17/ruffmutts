<?php
function import_wp_pro()
{
    global $iwp;

    if (!is_null($iwp)) {
        return $iwp;
    }

    $iwp = new ImportWP\Pro\ImportWPPro();
    $iwp->register();
    return $iwp;
}

function iwp_pro_loaded()
{
    if (function_exists(('import_wp_pro'))) {
        import_wp_pro();
    }
}
add_action('plugins_loaded', 'iwp_pro_loaded');
