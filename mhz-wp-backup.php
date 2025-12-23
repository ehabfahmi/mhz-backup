<?php
/**
 * Plugin Name: MHZ WP Backup
 * Plugin URI: https://myhostzone.com/
 * Description: A complete backup and migration solution for WordPress.
 * Version: 1.5.0
 * Author: MHZ Team
 * Author URI: https://myhostzone.com/
 * License: GPL v2 or later
 * Text Domain: mhz-wp-backup
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MHZ_VERSION', '1.5.0');
define('MHZ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MHZ_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include helper functions
require_once MHZ_PLUGIN_DIR . 'includes/helpers.php';

// Autoload classes
spl_autoload_register(function ($class) {
    $prefix = 'MHZ\\';
    $base_dir = MHZ_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);

    // Map class name to file name (Standard WP naming convention or PSR-4 adaption)
    // Here we use a map for critical classes or simple lowercase mapping
    // Changing to simple mapping: ClassName -> class-classname.php inside includes/ or subfolders

    // For this simple implementation, let's explicit require handling in a main init function 
    // or map explicitly if we want strict control. 
    // Let's stick to a simple require strategy for the main components for now to ensure load order.
});

// Manually require core classes for now to avoid autoload complexity issues in this environment
require_once MHZ_PLUGIN_DIR . 'includes/class-backup-manager.php';
require_once MHZ_PLUGIN_DIR . 'includes/class-db-exporter.php';
require_once MHZ_PLUGIN_DIR . 'includes/class-file-zipper.php';
require_once MHZ_PLUGIN_DIR . 'includes/class-cloud-storage.php';
require_once MHZ_PLUGIN_DIR . 'includes/class-scheduler.php';
require_once MHZ_PLUGIN_DIR . 'includes/class-search-replace.php';
require_once MHZ_PLUGIN_DIR . 'restoration/class-db-importer.php';
require_once MHZ_PLUGIN_DIR . 'restoration/class-restore-manager.php';
require_once MHZ_PLUGIN_DIR . 'admin/class-admin-ui.php';

// Initialize the plugin
function mhz_init()
{
    $backup_manager = new \MHZ\Backup_Manager();
    $admin_ui = new \MHZ\Admin_UI();
    $scheduler = new \MHZ\Scheduler();

    // Hooks
    add_action('admin_menu', [$admin_ui, 'register_menus']);
    add_action('mhz_scheduled_backup', [$backup_manager, 'create_backup']);
}
add_action('plugins_loaded', 'mhz_init');

// Activation Hook
register_activation_hook(__FILE__, function () {
    if (!wp_next_scheduled('mhz_scheduled_backup')) {
        wp_schedule_event(time(), 'daily', 'mhz_scheduled_backup');
    }
});

// Deactivation Hook
register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('mhz_scheduled_backup');
});
