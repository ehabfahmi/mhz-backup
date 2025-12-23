<?php
namespace MHZ;

if (!defined('ABSPATH')) {
    exit;
}

class Scheduler
{

    public function __construct()
    {
        // Add custom intervals
        add_filter('cron_schedules', [$this, 'add_schedules']);
    }

    /**
     * Add custom cron schedules
     */
    public function add_schedules($schedules)
    {
        $schedules['weekly'] = [
            'interval' => 604800,
            'display' => __('Once Weekly', 'mhz-wp-backup')
        ];
        $schedules['monthly'] = [
            'interval' => 2592000,
            'display' => __('Once Monthly', 'mhz-wp-backup')
        ];
        return $schedules;
    }

    /**
     * Schedule the backup event
     *
     * @param string $recurrence hourly, daily, weekly, monthly. 'manual' to unschedule.
     */
    public function schedule_backup($recurrence)
    {
        $hook = 'mhz_scheduled_backup';

        // Clear existing
        wp_clear_scheduled_hook($hook);

        if ($recurrence === 'manual' || empty($recurrence)) {
            return;
        }

        if (!wp_next_scheduled($hook)) {
            wp_schedule_event(time(), $recurrence, $hook);
            mhz_log("Backup scheduled: $recurrence");
        }
    }

    /**
     * Get current schedule
     */
    public function get_schedule()
    {
        $hook = 'mhz_scheduled_backup';
        $next = wp_next_scheduled($hook);
        if ($next) {
            // Find recurrence
            $events = _get_cron_array();
            // This is a bit complex to find exact recurrence key simply, 
            // but we can trust the setting stored in options usually.
            // For now, simple return.
            return 'active';
        }
        return 'manual';
    }
}
