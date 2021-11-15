<?php

namespace ImportWP\Pro\Rest;

use ImportWP\Common\Filesystem\Filesystem;
use ImportWP\Common\Http\Http;
use ImportWP\Common\Importer\ImporterStatus;
use ImportWP\Common\Importer\ImporterStatusManager;
use ImportWP\Common\Importer\Template\TemplateManager;
use ImportWP\Common\Properties\Properties;
use ImportWP\Pro\Cron\CronManager;
use ImportWP\Pro\Importer\ImporterManager;

class RestManager extends \ImportWP\Common\Rest\RestManager
{
    /**
     * @var CronManager
     */
    private $cron_manager;

    public function __construct(ImporterManager $importer_manager, ImporterStatusManager $importer_status_manager, Properties $properties, Http $http, Filesystem $filesystem, TemplateManager $template_manager, CronManager $cron_manager, $event_handler)
    {
        parent::__construct($importer_manager, $importer_status_manager, $properties, $http, $filesystem, $template_manager, $event_handler);
        $this->cron_manager = $cron_manager;
    }

    public function register_routes()
    {
        parent::register_routes();

        $namespace = $this->properties->rest_namespace . '/' . $this->properties->rest_version;

        register_rest_route($namespace, '/importer/(?P<id>\d+)/cron', array(
            array(
                'methods' => \WP_REST_Server::READABLE,
                'callback' => array($this, 'get_cron_status'),
                'permission_callback' => array($this, 'get_permission')
            )
        ));
    }

    /**
     * Before importer is saved, find existing cron schedule and remove it
     */
    public function save_importer(\WP_REST_Request $request)
    {
        $post_data = $request->get_body_params();
        $id = isset($post_data['id']) ? intval($post_data['id']) : null;

        if (isset($post_data['setting_import_method'])) {
            $this->cron_manager->unschedule($id);
            $importer_model = $this->importer_manager->get_importer($id);
            $this->importer_status_manager->clear($importer_model);
        }

        return parent::save_importer($request);
    }

    public function get_cron_status(\WP_REST_Request $request)
    {
        $id = intval($request->get_param('id'));
        $response = $this->cron_manager->get_status($id);
        return $this->http->end_rest_success($response);
    }
}
