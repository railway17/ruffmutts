<?php

namespace ImportWP\Pro;

use ImportWP\Common\Plugin\Menu;
use ImportWP\Pro\Cron\CronManager;
use ImportWP\Pro\Importer\ImporterManager;
use ImportWP\Pro\Rest\RestManager;

class ServiceProvider extends \ImportWP\ServiceProvider
{
    /**
     * @var CronManager
     */
    public $cron_manager;

    /**
     * @var RestManager
     */
    public $rest_manager;

    /**
     * @var Menu
     */
    public $menu;

    /**
     * @var ImporterManager
     */
    public $importer_manager;

    public function __construct($event_handler)
    {
        parent::__construct($event_handler);

        $this->properties->is_pro = true;
        $this->properties->plugin_file_path = realpath(dirname(__DIR__) . '/../importwp-pro.php');
        $this->properties->plugin_dir_path = plugin_dir_path($this->properties->plugin_file_path);
        $this->properties->plugin_folder_name = basename($this->properties->plugin_dir_path);
        $this->properties->plugin_basename = plugin_basename($this->properties->plugin_file_path);
        $this->properties->view_dir = $this->properties->plugin_dir_path . trailingslashit('views');

        $this->importer_manager = new ImporterManager($this->importer_status_manager, $this->filesystem, $this->template_manager, $event_handler);
        $this->menu = new Menu($this->properties, $this->view_manager, $this->importer_manager, $this->template_manager);

        $this->cron_manager = new CronManager($this->importer_manager, $this->importer_status_manager, $this->properties, $this->http, $this->filesystem, $event_handler);
        $this->rest_manager = new RestManager($this->importer_manager, $this->importer_status_manager, $this->properties, $this->http, $this->filesystem, $this->template_manager, $this->cron_manager, $event_handler);

        do_action('iwp/register_events', $event_handler, $this);
    }
}
