<?php

namespace ImportWP\Pro\Cron;

use ImportWP\Common\Filesystem\Filesystem;
use ImportWP\Common\Http\Http;
use ImportWP\Common\Importer\ImporterStatusManager;
use ImportWP\Common\Model\ImporterModel;
use ImportWP\Common\Properties\Properties;
use ImportWP\Common\Util\Logger;
use ImportWP\EventHandler;
use ImportWP\Pro\Importer\ImporterManager;

class CronManager
{
    /**
     * @var Properties
     */
    private $properties;

    /**
     * @var Http
     */
    private $http;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var ImporterManager
     */
    private $importer_manager;

    /**
     * @var ImporterStatusManager
     */
    private $importer_status_manager;

    private $_cron_handle = 'iwp_scheduler';
    private $_cron_runner_handle = 'iwp_schedule_runner';
    private $_chunk_runner_handle = 'iwp_chunk_runner';

    /**
     * @var EventHandler
     */
    protected $event_handler;

    /**
     * Undocumented function
     *
     * @param ImporterManager $importer_manager
     * @param ImporterStatusManager $importer_status_manager
     * @param Properties $properties
     * @param Http $http
     * @param Filesystem $filesystem
     * @param EventHandler $event_handler
     */
    public function __construct(ImporterManager $importer_manager, ImporterStatusManager $importer_status_manager, Properties $properties, Http $http, Filesystem $filesystem, $event_handler)
    {
        $this->importer_manager = $importer_manager;
        $this->importer_status_manager = $importer_status_manager;
        $this->properties = $properties;
        $this->http = $http;
        $this->filesystem = $filesystem;
        $this->event_handler = $event_handler;

        add_action('init', [$this, 'register_cron_runner']);
        add_action($this->_cron_handle, [$this, 'spawner']);
        add_action($this->_cron_runner_handle, [$this, 'cron_runner']);
        add_action($this->_chunk_runner_handle, [$this, 'chunk_runner']);
        add_filter('cron_schedules', [$this, 'register_cron_interval']);

        $this->event_handler->listen('iwp/importer/status/output', [$this, 'update_status_message']);
    }

    public function register_cron_interval($schedules)
    {
        $schedules['iwp_spawner'] = [
            'interval' => MINUTE_IN_SECONDS,
            'display' => __('Every minutes.', 'importwp')
        ];
        return $schedules;
    }

    public function register_cron_runner()
    {
        if (!wp_next_scheduled($this->_cron_handle)) {
            wp_schedule_event(time(), 'iwp_spawner', $this->_cron_handle);
        }
    }

    public function spawner()
    {
        $importers = $this->get_imports();
        if (empty($importers)) {
            return;
        }

        foreach ($importers as $importer_model) {

            if ('schedule' === $importer_model->getSetting('import_method') && false === $this->is_cron_disabled($importer_model)) {
                $importers[] = $importer_model;
            }

            switch ($importer_model->getSetting('import_method')) {
                case 'schedule':
                    if (false === $this->is_cron_disabled($importer_model) && !wp_next_scheduled($this->_cron_runner_handle, [$importer_model->getId()])) {
                        $next_schedule = $this->spawn_importer($importer_model);
                        if ($next_schedule) {
                            Logger::write(__CLASS__ . '::spawner -wp_schedule_event=' . date('Y-m-d H:i:s', $next_schedule), $importer_model->getId());
                            wp_schedule_event($next_schedule, 'iwp_spawner', $this->_cron_runner_handle, [$importer_model->getId()]);
                        }
                    }
                    break;
                case 'run':

                    $importer_status = $this->importer_status_manager->get_importer_status($importer_model);
                    $is_running = $importer_status->has_status('running') || $importer_status->has_status('timeout');

                    $schedule_args = [$importer_model->getId()];
                    $next_schedule = wp_next_scheduled($this->_chunk_runner_handle, $schedule_args);
                    $counter = $importer_status->get_counter();

                    if ($is_running && !$next_schedule && $counter > 0 && $this->properties->chunk_limit > 1) {
                        // TODO: Check to see if importer is running or timeout, if so make sure cron_event is registered
                        wp_schedule_event(time(), 'iwp_spawner', $this->_chunk_runner_handle, $schedule_args);
                    } else if (!$is_running && $next_schedule) {
                        // TODO: Check to see if importer is no running, if so make sure cron_event is unregistered
                        wp_unschedule_event($next_schedule, $this->_chunk_runner_handle, $schedule_args);
                    }
                    break;
            }
        }
    }

    public function chunk_runner($importer_id)
    {
        $importer_model = $this->importer_manager->get_importer($importer_id);
        if (!$importer_model) {

            $next_schedule = wp_next_scheduled($this->_chunk_runner_handle, [$importer_id]);
            Logger::write(__CLASS__ . '::chunk_runner -scheduled=' . $next_schedule, $importer_id);
            if ($next_schedule) {

                $result = wp_unschedule_event($next_schedule, $this->_chunk_runner_handle, [$importer_id]);
                Logger::write(__CLASS__ . '::chunk_runner -unscheduled=' . ($result ? 'yes' : 'no'), $importer_id);
            }

            return;
        }

        $importer_status = $this->importer_status_manager->get_importer_status($importer_model);
        $is_running = $importer_status->has_status('running') || $importer_status->has_status('timeout');
        if (!$is_running) {
            Logger::write(__CLASS__ . '::chunk_runner -not-running', $importer_model->getId());
            return;
        }

        $this->importer_manager->import($importer_model, $importer_status->get_session_id());
    }

    private function is_allowed_to_run($importer_model)
    {
        $importer_model = $this->importer_manager->get_importer($importer_model);
        /**
         * @var \WPDB $wpdb
         */
        global $wpdb;

        $rows = $wpdb->get_col("SELECT meta_value FROM {$wpdb->postmeta} WHERE (meta_key LIKE '\_%\_%\_chunk' OR meta_key LIKE '\_%\_%\_delete') AND meta_value NOT LIKE '%complete%'  AND post_id=" . $importer_model->getId());
        if (!empty($rows)) {

            $time_limit = $this->properties->chunk_timeout;
            $chunk_limit = $this->properties->chunk_limit;

            $count = array_reduce($rows, function ($carry, $item) use ($time_limit) {
                $item = maybe_unserialize($item);
                $carry += ($item['status'] == 'running' && time() - $time_limit <= $item['time'] ? 1 : 0);
                return $carry;
            }, 0);

            if ($count >= $chunk_limit) {
                Logger::write(__CLASS__ . '::is_allowed_to_run -not-allowed -max=' . $count . '/' . $chunk_limit, $importer_model->getId());
                return false;
            }
        }

        return true;
    }

    public function cron_runner($importer_id)
    {
        $importer_model = $this->importer_manager->get_importer($importer_id);
        if (!$importer_model) {

            $next_schedule = wp_next_scheduled($this->_cron_runner_handle, [$importer_id]);
            Logger::write(__CLASS__ . '::cron_runner -unschedule= ' . $next_schedule, $importer_id);
            if ($next_schedule) {
                $result = wp_unschedule_event($next_schedule, $this->_cron_runner_handle, [$importer_id]);
                Logger::write(__CLASS__ . '::cron_runner -unscheduled=' . ($result ? 'yes' : 'no'), $importer_id);
            }

            return;
        }

        if (!$this->is_allowed_to_run($importer_model->getId())) {
            return false;
        }

        $last_ran = get_post_meta($importer_model->getId(), '_iwp_cron_last_ran', true);

        // check to see if more than a second has passed before this was last ran
        if (false !== $last_ran && intval($last_ran) - time() >= 5) {
            $sleep_time = min(intval($last_ran) - time(), 5);
            sleep($sleep_time);
            Logger::write(__CLASS__ . '::spawner -sleep=' . $sleep_time, $importer_model->getId());
        }

        update_post_meta($importer_model->getId(), '_iwp_cron_last_ran', time());

        if (false === $importer_model || 'schedule' !== $importer_model->getSetting('import_method')) {
            if (intval($importer_model->getId()) > 0) {
                $next_schedule = wp_next_scheduled($this->_cron_runner_handle, [$importer_model->getId()]);
                if ($next_schedule) {
                    wp_unschedule_event($next_schedule, $this->_cron_runner_handle, [$importer_model->getId()]);
                }
            }
            return;
        }

        $cron_status = get_post_meta($importer_model->getId(), '_iwp_cron_status', true);
        $cron_session = get_post_meta($importer_model->getId(), '_iwp_cron_session', true);
        $args = false;

        Logger::write(__CLASS__ . '::spawner -status=' . $cron_status, $importer_model->getId());

        if (!$cron_status) {
            update_post_meta($importer_model->getId(), '_iwp_cron_status', 'start');
            $args = [$importer_model, 'start', null];
        } elseif ($cron_status === 'stopped' || $cron_status === 'running') {
            $args = [$importer_model, 'resume', $cron_session];
        } elseif ($cron_status === 'running') {

            $last_ran = intval(get_post_meta($importer_model->getId(), '_iwp_cron_updated', true));
            $importer_status = $this->importer_status_manager->get_importer_status($importer_model);
            $waited_time = time() - $last_ran;

            if (!$importer_status->has_status('timeout')) {

                $waited_1_hour = $waited_time >= (MINUTE_IN_SECONDS * 20);

                Logger::write(__CLASS__ . '::cron_runner -status=' . $importer_status->get_status() . ' -time=' . $waited_time . ' -waiting=' . ($waited_1_hour ? 'yes' : 'no'));
                if (!$waited_1_hour) {
                    return false;
                }

                Logger::write(__CLASS__ . '::cron_runner -stop');

                $importer_status->record_fatal_error("Stopping import due to wrong import status: " . $importer_status->get_status());
                $importer_status->save();
                $importer_status->write_to_file();

                delete_post_meta($importer_model->getId(), '_iwp_cron_updated');
                delete_post_meta($importer_model->getId(), '_iwp_cron_status');
                delete_post_meta($importer_model->getId(), '_iwp_cron_version');
                return false;
            }

            $waited_5_mins = $waited_time >= (MINUTE_IN_SECONDS * 5);
            Logger::write(__CLASS__ . '::cron_runner -time=' . $waited_time . ' -waiting=' . ($waited_5_mins ? 'yes' : 'no'));

            if (!$waited_5_mins) {
                Logger::write(__CLASS__ . '::cron_runner -escape=');
                return false;
            }

            Logger::write(__CLASS__ . '::cron_runner -resume=');
            $args = [$importer_model, 'resume', $cron_session];
        } elseif ($cron_status === 'error') {

            // if we have errored, clear status stop schedule, and let spawner schedule next event.
            delete_post_meta($importer_model->getId(), '_iwp_cron_updated');
            delete_post_meta($importer_model->getId(), '_iwp_cron_status');
            delete_post_meta($importer_model->getId(), '_iwp_cron_version');
            Logger::write(__METHOD__ . ' -stopped');

            $next_schedule = wp_next_scheduled($this->_cron_runner_handle, [$importer_model->getId()]);
            if ($next_schedule) {
                wp_unschedule_event($next_schedule, $this->_cron_runner_handle, [$importer_model->getId()]);
            }
            return false;
        }

        if (!$args) {
            return false;
        }

        return $this->run($args[0], $args[1], $args[2]);
    }

    /**
     * @return ImporterModel[]
     */
    public function get_scheduled_imports()
    {
        $query = new \WP_Query([
            'post_type' => IWP_POST_TYPE,
            'posts_per_page' => -1,
        ]);

        if (!$query->have_posts()) {
            return false;
        }

        $importers = [];

        foreach ($query->posts as $post) {
            $importer_model = $this->importer_manager->get_importer($post);
            if ('schedule' === $importer_model->getSetting('import_method') && false === $this->is_cron_disabled($importer_model)) {
                $importers[] = $importer_model;
            }
        }

        return $importers;
    }

    /**
     * @return ImporterModel[]
     */
    public function get_imports()
    {
        $query = new \WP_Query([
            'post_type' => IWP_POST_TYPE,
            'posts_per_page' => -1,
        ]);

        if (!$query->have_posts()) {
            return false;
        }

        $importers = [];

        foreach ($query->posts as $post) {
            $importer_model = $this->importer_manager->get_importer($post);
            $importers[] = $importer_model;
        }

        return $importers;
    }

    /**
     * Check to see if all schedules are disabled
     *
     * @param ImporterModel $importer_model
     * @return boolean
     */
    private function is_cron_disabled($importer_model)
    {
        $settings = $importer_model->getSetting('cron');
        if (!empty($settings)) {
            foreach ($settings as $setting) {
                if ($setting['setting_cron_disabled'] === false) {
                    return false;
                }
            }
        }

        return true;
    }

    public function calculate_scheduled_time($schedule, $day = 0, $hour = 0, $minute = 0, $current_time = null)
    {
        $minute_padded = str_pad($minute, 2, 0, STR_PAD_LEFT);
        $hour_padded = str_pad($hour, 2, 0, STR_PAD_LEFT);
        $day_padded = str_pad($day, 2, 0, STR_PAD_LEFT);
        $time_offset = time() - current_time('timestamp');
        $current_time = !is_null($current_time) ? $current_time : time();
        $scheduled_time = false;

        switch ($schedule) {
            case 'month':
                // 1-31

                // 31st of feb, should = 28/29
                if (date('t', $current_time) < $day) {
                    $day_padded = str_pad(date('t', $current_time), 2, 0, STR_PAD_LEFT);
                }

                $scheduled_time = $time_offset + strtotime(date('Y-m-' . $day_padded . ' ' . $hour_padded . ':' . $minute_padded . ':00', $current_time));

                if ($scheduled_time < $current_time) {

                    // 31st of feb, should = 28/29
                    $future_time = strtotime('+28 days', $current_time); // 28 days is the shortest month, adding + 1 month can skip feb
                    if (date('t', $future_time) < $day) {
                        $day_padded = str_pad(date('t', $future_time), 2, 0, STR_PAD_LEFT);
                    }

                    $scheduled_time = $time_offset + strtotime(date('Y-m-' . $day_padded . ' ' . $hour_padded . ':' . $minute_padded . ':00', $future_time));
                }
                break;
            case 'week':
                // day 0-6 : 0 = SUNDAY
                $day_str = '';
                switch (intval($day)) {
                    case 0:
                        $day_str =  'sunday';
                        break;
                    case 1:
                        $day_str =  'monday';
                        break;
                    case 2:
                        $day_str =  'tuesday';
                        break;
                    case 3:
                        $day_str =  'wednesday';
                        break;
                    case 4:
                        $day_str =  'thursday';
                        break;
                    case 5:
                        $day_str =  'friday';
                        break;
                    case 6:
                        $day_str =  'saturday';
                        break;
                }
                $scheduled_time = $time_offset + strtotime(date('Y-m-d ' . $hour_padded . ':' . $minute_padded . ':00', strtotime('next ' . $day_str, $current_time)));
                if ($scheduled_time - WEEK_IN_SECONDS > $current_time) {
                    $scheduled_time -= WEEK_IN_SECONDS;
                }
                break;
            case 'day':
                $scheduled_time = $time_offset + strtotime(date('Y-m-d ' . $hour_padded . ':' . $minute_padded . ':00', $current_time));
                if ($scheduled_time <= $current_time) {
                    $scheduled_time += DAY_IN_SECONDS;
                }
                break;
            case 'hour':
                $scheduled_time = strtotime(date('Y-m-d H:' . $minute_padded . ':00', $current_time));
                if ($scheduled_time <= $current_time) {
                    $scheduled_time += HOUR_IN_SECONDS;
                }
                break;
        }

        return $scheduled_time;
    }

    public function run($importer_id, $action = 'start', $session = null)
    {
        // Fetch new file
        $importer_model = $this->importer_manager->get_importer($importer_id);

        if (false === $importer_model || 'schedule' !== $importer_model->getSetting('import_method')) {
            return;
        }

        update_post_meta($importer_model->getId(), '_iwp_cron_updated', time());
        update_post_meta($importer_model->getId(), '_iwp_cron_status', 'running');

        Logger::write(__CLASS__ . '::run -action=' . $action . ' -session' . $session);

        if ($action === 'start') {

            // TODO: Remove duplicate code, found in rest manager

            $datasource = $importer_model->getDatasource();
            switch ($datasource) {
                case 'remote':
                    $raw_source = $importer_model->getDatasourceSetting('remote_url');
                    $source = apply_filters('iwp/importer/datasource', $raw_source, $raw_source, $importer_model);
                    $source = apply_filters('iwp/importer/datasource/remote', $source, $raw_source, $importer_model);
                    $attachment_id = $this->importer_manager->remote_file($importer_model, $source, $importer_model->getParser());
                    break;
                case 'local':
                    $raw_source = $importer_model->getDatasourceSetting('local_url');
                    $source = apply_filters('iwp/importer/datasource', $raw_source, $raw_source, $importer_model);
                    $source = apply_filters('iwp/importer/datasource/local', $source, $raw_source, $importer_model);
                    $attachment_id = $this->importer_manager->local_file($importer_model, $source);
                    break;
                default:
                    // TODO: record error 
                    $attachment_id = new \WP_Error('IWP_CRON_1', 'Unable to get new file using datasource: ' . $datasource);
                    break;
            }

            $importer_model = $this->importer_manager->get_importer($importer_id);
            $status = $this->importer_status_manager->create($importer_model);
            $session = $status->get_session_id();

            // store session to meta
            update_post_meta($importer_model->getId(), '_iwp_cron_session', $session);

            if (is_wp_error($attachment_id)) {
                update_post_meta($importer_model->getId(), '_iwp_cron_status', 'error');
                // TODO: record error
                $status->record_fatal_error($attachment_id->get_error_message());
                $status->save();
                $status->write_to_file();
                return;
            }

            // TODO: rotate files to not fill up server
            $importer_model->limit_importer_files($this->properties->file_rotation);
        } else {
            $importer_status = $this->importer_status_manager->get_importer_status($importer_model);
            if ($importer_status->get_session_id() !== $session) {
                Logger::write(__CLASS__ . '::run -invalid-session -current=' . $session . ' -session' . $importer_status->get_session_id());
                return;
            }

            $meta = get_post_meta($importer_model->getId(), '_iwp_scheduled', true);
            if ($meta && isset($meta['session'])) {
                $meta['session'] = $session;
                update_post_meta($importer_model->getId(), '_iwp_scheduled', $meta);
            }
        }

        $update_timestamp = function ($importer_status) use ($importer_model) {
            update_post_meta($importer_model->getId(), '_iwp_cron_updated', time());
        };

        add_action('iwp/importer/status/save', $update_timestamp);
        $status = $this->importer_manager->import($importer_model, $session);
        remove_action('iwp/importer/status/save', $update_timestamp);

        update_post_meta($importer_model->getId(), '_iwp_cron_updated', time());

        // mark it as stopped if all running chunks do not equal running
        if ($status->get_status() !== 'running') {
            update_post_meta($importer_model->getId(), '_iwp_cron_status', 'stopped');
        }

        if ($status->has_status('error') || $status->has_status('complete') || $status->has_status('cancelled')) {
            delete_post_meta($importer_model->getId(), '_iwp_cron_updated');
            delete_post_meta($importer_model->getId(), '_iwp_cron_status');

            $next_schedule = wp_next_scheduled($this->_cron_runner_handle, [$importer_model->getId()]);
            if ($next_schedule) {
                wp_unschedule_event($next_schedule, $this->_cron_runner_handle, [$importer_model->getId()]);
            }
        }
    }

    /**
     * @param ImporterModel $importer_model
     */
    public function spawn_importer($importer_model)
    {
        $schedule_settings = $importer_model->getSetting('cron');
        if (empty($schedule_settings)) {
            return false;
        }

        $schedule_next = -1;

        foreach ($schedule_settings as $schedule_setting) {

            if (!isset($schedule_setting['setting_cron_schedule'], $schedule_setting['setting_cron_day'], $schedule_setting['setting_cron_minute'], $schedule_setting['setting_cron_schedule'], $schedule_setting['setting_cron_disabled'])) {
                continue;
            }

            $schedule = $schedule_setting['setting_cron_schedule'];
            $day = $schedule_setting['setting_cron_day'];
            $hour = $schedule_setting['setting_cron_hour'];
            $minute = $schedule_setting['setting_cron_minute'];
            $disabled = $schedule_setting['setting_cron_disabled'];

            if ($disabled === true) {
                continue;
            }

            $scheduled_time = $this->calculate_scheduled_time($schedule, $day, $hour, $minute);
            if (false === $scheduled_time) {
                continue;
            }

            if ($schedule_next === -1 || $schedule_next > $scheduled_time) {
                $schedule_next = $scheduled_time;
            }
        }

        if ($schedule_next === -1) {
            return false;
        }

        return $schedule_next;
    }

    public function unschedule($importer_id)
    {
        $importer_model = $this->importer_manager->get_importer($importer_id);

        delete_post_meta($importer_model->getId(), '_iwp_cron_updated');
        delete_post_meta($importer_model->getId(), '_iwp_cron_status');
        delete_post_meta($importer_model->getId(), '_iwp_cron_version');

        $next_schedule = wp_next_scheduled($this->_cron_runner_handle, [$importer_model->getId()]);
        if ($next_schedule) {
            wp_unschedule_event($next_schedule, $this->_cron_runner_handle, [$importer_model->getId()]);
        }

        return true;
    }

    /**
     * @param ImporterModel $importer_model 
     * @return string
     */
    public function get_status($importer_model)
    {
        $importer_model = $this->importer_manager->get_importer($importer_model);

        // start | stopped | running | error
        $cron_status = get_post_meta($importer_model->getId(), '_iwp_cron_status', true);
        $scheduled_time = wp_next_scheduled($this->_cron_runner_handle, [$importer_model->getId()]);
        if (!$cron_status) {

            if ($scheduled_time) {
                // scheduled but not started
                return [
                    'status' => 'start',
                    'time' => $scheduled_time,
                    'delta' => $scheduled_time - time()
                ];
            }

            // not scheduled
            $spawner_time = wp_next_scheduled($this->_cron_handle);
            return [
                'status' => 'spawner',
                'time' => $spawner_time,
                'delta' => $spawner_time - time()
            ];
        }

        if ($cron_status === 'stopped') {
            return [
                'status' => 'resume',
                'time' => $scheduled_time,
                'delta' => $scheduled_time - time()
            ];
        }

        return [
            'status' => $cron_status,
            'time' => $scheduled_time,
            'delta' => $scheduled_time - time()
        ];
    }

    /**
     * Modify the status message
     *
     * @param array $output
     * @param ImporterModel $importer_model
     * @return array
     */
    public function update_status_message($output, $importer_model)
    {

        $status = $this->get_status($importer_model);

        if ('schedule' !== $importer_model->getSetting('import_method') || false !== $this->is_cron_disabled($importer_model)) {
            return $output;
        }

        switch ($status['status']) {
            case 'start':
                $output['msg'] = '(Scheduled at ' . date(get_site_option('date_format') . ' ' . get_site_option('time_format'), $status['time']) . ') ' . $output['msg'];
                break;
            case 'spawner':
                $output['msg'] = '(Scheduling) ' . $output['msg'];
                break;
            case 'resume':
                $output['msg'] = '(Resuming in ' . $status['delta'] . 's) ' . $output['msg'];
                break;
        }

        $output['cron'] = $status;
        return $output;
    }
}
