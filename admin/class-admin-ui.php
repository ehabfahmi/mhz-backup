<?php
namespace MHZ;

if (!defined('ABSPATH')) {
    exit;
}

class Admin_UI
{

    private $active_tab;

    public function __construct()
    {
        if (is_admin()) {
            add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
            add_action('wp_ajax_mhz_create_backup', [$this, 'ajax_create_backup']);
            add_action('wp_ajax_mhz_restore_backup', [$this, 'ajax_restore_backup']);
            add_action('wp_ajax_mhz_delete_backup', [$this, 'ajax_delete_backup']);
            add_action('wp_ajax_mhz_check_restore_progress', [$this, 'ajax_check_restore_progress']);
            add_action('wp_ajax_mhz_cancel_restore', [$this, 'ajax_cancel_restore']);
        }

        $this->active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'backup';
    }

    public function register_menus()
    {
        add_menu_page(
            __('WP Comprehensive Backup', 'mhz-wp-backup'),
            __('WP Backup', 'mhz-wp-backup'),
            'manage_options',
            'mhz',
            [$this, 'render_page'],
            'dashicons-download',
            90
        );
    }

    public function enqueue_assets($hook)
    {
        if (strpos($hook, 'mhz') === false) {
            return;
        }

        // Enqueue JS/CSS
        // For MVP, inline JS/CSS or simple implementation in render
    }

    public function render_page()
    {
        ?>
        <div class="wrap">
            <h1><?php _e('MHZ WP Backup & Migration', 'mhz-wp-backup'); ?></h1>
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_url(admin_url('admin.php?page=mhz&tab=backup')); ?>"
                    class="nav-tab <?php echo $this->active_tab == 'backup' ? 'nav-tab-active' : ''; ?>"><?php _e('Export / Backup', 'mhz-wp-backup'); ?></a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=mhz&tab=import')); ?>"
                    class="nav-tab <?php echo $this->active_tab == 'import' ? 'nav-tab-active' : ''; ?>"><?php _e('Import', 'mhz-wp-backup'); ?></a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=mhz&tab=settings')); ?>"
                    class="nav-tab <?php echo $this->active_tab == 'settings' ? 'nav-tab-active' : ''; ?>"><?php _e('Settings', 'mhz-wp-backup'); ?></a>
            </h2>

            <div class="mhz-content" style="background:#fff; padding:20px; border:1px solid #ccc; margin-top:20px;">
                <?php
                switch ($this->active_tab) {
                    case 'import':
                        $this->render_import();
                        break;
                    case 'settings':
                        $this->render_settings();
                        break;
                    default:
                        $this->render_backup();
                        break;
                }
                ?>
            </div>
        </div>

        <!-- Quick Inline JS/CSS for Demo -->
        <style>
            .mhz-button-large {
                font-size: 18px;
                padding: 10px 30px;
                height: auto;
            }

            .mhz-button-large {
                font-size: 18px;
                padding: 10px 30px;
                height: auto;
            }

            table.backups-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }

            table.backups-table th,
            table.backups-table td {
                text-align: left;
                padding: 10px;
                border-bottom: 1px solid #eee;
            }
        </style>
        <script>
            jQuery(document).ready(function ($) {
                var restoreInterval;

                // CREATE BACKUP
                $('#mhz-create-backup').on('click', function (e) {
                    e.preventDefault();
                    var btn = $(this);
                    btn.prop('disabled', true).text('<?php _e('Creating Backup...', 'mhz-wp-backup'); ?>');
                    $('.mhz-backup-progress-bar').show();
                    $('.mhz-backup-progress-fill').css('width', '50%'); // Fake progress for now

                    $.post(ajaxurl, {
                        action: 'mhz_create_backup',
                        nonce: '<?php echo wp_create_nonce('mhz-create'); ?>'
                    }, function (response) {
                        $('.mhz-backup-progress-fill').css('width', '100%');
                        if (response.success) {
                            alert('Backup Created Successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + response.data);
                            btn.prop('disabled', false).text('<?php _e('Create Backup', 'mhz-wp-backup'); ?>');
                        }
                    });
                });

                // RESTORE
                $('.mhz-restore-btn').on('click', function (e) {
                    if (!confirm('Are you sure? This will overwrite your database and files.')) return false;

                    var file = $(this).data('file');

                    // Show Progress UI
                    $('.mhz-restore-progress-overlay').show();
                    $('.mhz-restore-status-text').text('Initializing...');
                    $('.mhz-restore-progress-fill').css('width', '5%');

                    // Start Polling
                    restoreInterval = setInterval(checkRestoreProgress, 2000);

                    $.post(ajaxurl, {
                        action: 'mhz_restore_backup',
                        file: file,
                        nonce: '<?php echo wp_create_nonce('mhz-restore'); ?>'
                    }, function (response) {
                        clearInterval(restoreInterval);
                        $('.mhz-restore-progress-fill').css('width', '100%');
                        $('.mhz-restore-status-text').text('Done!');

                        if (response.success) {
                            alert('Restore Completed!');
                            location.reload();
                        } else {
                            // If it was cancelled, we might get a failure here or success depending on how we handle exit
                            if (response.data === 'cancelled') {
                                alert('Restore Cancelled.');
                            } else {
                                alert('Error: ' + response.data);
                            }
                            $('.mhz-restore-progress-overlay').hide();
                        }
                    }).fail(function () {
                        // Likely timed out or server error
                        // Continue polling? or stop?
                    });
                });

                // Cancel Restore
                $('#mhz-cancel-restore-btn').on('click', function () {
                    if (!confirm('Stop the restore process? site might be broken.')) return;

                    $.post(ajaxurl, {
                        action: 'mhz_cancel_restore',
                        nonce: '<?php echo wp_create_nonce('mhz-restore-cancel'); ?>'
                    }, function (response) {
                        $('.mhz-restore-status-text').text('Cancelling...');
                    });
                });

                function checkRestoreProgress() {
                    $.post(ajaxurl, {
                        action: 'mhz_check_restore_progress',
                        nonce: '<?php echo wp_create_nonce('mhz-progress'); ?>'
                    }, function (response) {
                        if (response.success) {
                            var data = response.data;
                            var pct = data.percentage + '%';
                            $('.mhz-restore-progress-fill').css('width', pct);
                            $('.mhz-restore-status-text').text(data.message);

                            if (data.status === 'cancelled') {
                                clearInterval(restoreInterval);
                                alert('Restore was cancelled.');
                                location.reload();
                            }
                        }
                    });
                }

                // DELETE
                $('.mhz-delete-btn').on('click', function (e) {
                    // ... existing delete code ...
                    if (!confirm('Are you sure you want to delete this backup?')) return false;

                    var file = $(this).data('file');
                    $.post(ajaxurl, {
                        action: 'mhz_delete_backup',
                        file: file,
                        nonce: '<?php echo wp_create_nonce('mhz-delete'); ?>'
                    }, function (response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Error: ' + response.data);
                        }
                    });
                });
            });
        </script>
        <?php
    }

    private function render_backup()
    {
        $backup_manager = new \MHZ\Backup_Manager();
        $backups = $backup_manager->get_backups();
        ?>
        <div style="text-align:center; padding: 40px;">
            <h3><?php _e('Export Site', 'mhz-wp-backup'); ?></h3>
            <p><?php _e('Create a full backup of your website (Database + Files)', 'mhz-wp-backup'); ?></p>
            <button id="mhz-create-backup"
                class="button button-primary mhz-button-large"><?php _e('Create Backup', 'mhz-wp-backup'); ?></button>
            <div class="mhz-backup-progress-bar"
                style="display:none; width: 100%; background: #f0f0f0; margin: 20px 0; border-radius: 5px; overflow: hidden;">
                <div class="mhz-backup-progress-fill"
                    style="width: 0%; height: 20px; background: #2271b1; transition: width 0.3s;"></div>
            </div>
        </div>

        <!-- Restore Progress Overlay -->
        <div class="mhz-restore-progress-overlay"
            style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.9); z-index:9999; text-align:center; padding-top:200px;">
            <h2><?php _e('Restoring Website...', 'mhz-wp-backup'); ?></h2>
            <p class="mhz-restore-status-text"><?php _e('Please wait...', 'mhz-wp-backup'); ?></p>
            <div style="width: 50%; margin: 0 auto; background: #ddd; height: 30px; border-radius: 15px; overflow: hidden;">
                <div class="mhz-restore-progress-fill"
                    style="width: 0%; height: 100%; background: #2271b1; transition: width 0.5s;"></div>
            </div>
            <br>
            <button id="mhz-cancel-restore-btn"
                class="button button-secondary"><?php _e('Cancel Restore', 'mhz-wp-backup'); ?></button>
            <p style="color:red; font-size: 12px; margin-top: 10px;">
                <?php _e('Warning: Cancelling mid-restore may break your site.', 'mhz-wp-backup'); ?>
            </p>
        </div>

        <hr>

        <h3><?php _e('Available Backups', 'mhz-wp-backup'); ?></h3>
        <table class="backups-table">
            <thead>
                <tr>
                    <th><?php _e('Filename', 'mhz-wp-backup'); ?></th>
                    <th><?php _e('Size', 'mhz-wp-backup'); ?></th>
                    <th><?php _e('Date', 'mhz-wp-backup'); ?></th>
                    <th><?php _e('Actions', 'mhz-wp-backup'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($backups)): ?>
                    <tr>
                        <td colspan="4"><?php _e('No backups found.', 'mhz-wp-backup'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($backups as $backup): ?>
                        <tr>
                            <td><?php echo esc_html($backup['name']); ?></td>
                            <td><?php echo esc_html($backup['size']); ?></td>
                            <td><?php echo esc_html($backup['time']); ?></td>
                            <td>
                                <a href="<?php echo esc_url(content_url('uploads/mhz-backups/' . $backup['name'])); ?>" class="button"
                                    download><?php _e('Download', 'mhz-wp-backup'); ?></a>
                                <button class="button mhz-restore-btn"
                                    data-file="<?php echo esc_attr($backup['path']); ?>"><?php _e('Restore', 'mhz-wp-backup'); ?></button>
                                <button class="button mhz-delete-btn" style="color:red;"
                                    data-file="<?php echo esc_attr($backup['name']); ?>"><?php _e('Delete', 'mhz-wp-backup'); ?></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    private function render_import()
    {
        ?>
        <div style="text-align:center; padding: 40px; border: 2px dashed #ccc;">
            <h3><?php _e('Import Site', 'mhz-wp-backup'); ?></h3>
            <p><?php _e('Drag & Drop a .wpress file here to restore', 'mhz-wp-backup'); ?></p>
            <p><em>(Not fully implemented in this demo - Use the Restore buttons on the Backup tab for local files)</em></p>
        </div>
        <?php
    }

    private function render_settings()
    {
        ?>
        <h3><?php _e('Settings', 'mhz-wp-backup'); ?></h3>
        <form method="post" action="options.php">
            <p><strong><?php _e('Cloud Storage', 'mhz-wp-backup'); ?></strong></p>
            <label><input type="checkbox" name="mhz_gdrive">
                <?php _e('Google Drive (Premium)', 'mhz-wp-backup'); ?></label><br>
            <label><input type="checkbox" name="mhz_dropbox">
                <?php _e('Dropbox (Premium)', 'mhz-wp-backup'); ?></label>

            <p><strong><?php _e('Schedule', 'mhz-wp-backup'); ?></strong></p>
            <select name="mhz_schedule">
                <option value="manual"><?php _e('Manual Only', 'mhz-wp-backup'); ?></option>
                <option value="weekly"><?php _e('Once Weekly', 'mhz-wp-backup'); ?></option>
                <option value="monthly"><?php _e('Once Monthly', 'mhz-wp-backup'); ?></option>
            </select>
            <p><em>(Settings saving not fully wired in this demo)</em></p>
        </form>
        <?php
    }

    /**
     * AJAX: Create Backup
     */
    public function ajax_create_backup()
    {
        check_ajax_referer('mhz-create', 'nonce');

        // Increase time limit
        set_time_limit(600);

        $manager = new \MHZ\Backup_Manager();
        $result = $manager->create_backup();

        if ($result) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error('Backup failed.');
        }
    }

    /**
     * AJAX: Restore Backup
     */
    public function ajax_restore_backup()
    {
        check_ajax_referer('mhz-restore', 'nonce');

        if (empty($_POST['file'])) {
            wp_send_json_error('No file specified.');
        }

        $file_path = sanitize_text_field($_POST['file']);

        // Security check: ensure file is in our backup directory
        if (strpos($file_path, 'mhz-backups') === false) {
            wp_send_json_error('Invalid file path.');
        }

        $restorer = new \MHZ\Restore_Manager();
        if ($restorer->restore($file_path)) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Restore failed.');
        }
    }

    /**
     * AJAX: Check Restore Progress
     */
    public function ajax_check_restore_progress()
    {
        check_ajax_referer('mhz-progress', 'nonce');

        $progress = get_option('mhz_restore_progress', ['percentage' => 0, 'message' => 'Initializing...', 'status' => 'running']);
        wp_send_json_success($progress);
    }

    /**
     * AJAX: Cancel Restore
     */
    public function ajax_cancel_restore()
    {
        check_ajax_referer('mhz-restore-cancel', 'nonce');
        update_option('mhz_restore_cancel', true);
        wp_send_json_success();
    }

    /**
     * AJAX: Delete Backup
     */
    public function ajax_delete_backup()
    {
        check_ajax_referer('mhz-delete', 'nonce');
        $file_name = sanitize_file_name($_POST['file']);
        $path = WP_CONTENT_DIR . '/uploads/mhz-backups/' . $file_name;

        if (file_exists($path)) {
            unlink($path);
            wp_send_json_success();
        } else {
            wp_send_json_error('File not found.');
        }
    }
}
