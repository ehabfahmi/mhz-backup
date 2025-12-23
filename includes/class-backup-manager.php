<?php
namespace MHZ;

if (!defined('ABSPATH')) {
    exit;
}

class Backup_Manager
{

    private $upload_dir;
    private $backup_dir;

    public function __construct()
    {
        $upload_info = wp_upload_dir();
        $this->upload_dir = $upload_info['basedir'];
        $this->backup_dir = $this->upload_dir . '/mhz-backups';

        if (!file_exists($this->backup_dir)) {
            wp_mkdir_p($this->backup_dir);
            // Secure directory
            file_put_contents($this->backup_dir . '/index.php', '<?php // Silence is golden.');
            file_put_contents($this->backup_dir . '/.htaccess', 'deny from all');
        }
    }

    /**
     * Create a full backup
     *
     * @return array|bool Result info or false on failure
     */
    public function create_backup()
    {
        mhz_log("Starting full backup process...");

        // 1. Prepare Paths
        $timestamp = date('Y-m-d-H-i-s');
        $identifier = uniqid();
        $temp_db_file = $this->backup_dir . "/db-{$identifier}.sql";
        $final_zip_name = "backup-{$timestamp}-{$identifier}.wpress"; // Using .wpress as requested to mimic behavior, though intl it's zip
        $final_zip_path = $this->backup_dir . '/' . $final_zip_name;

        // 2. Export Database
        $db_exporter = new DB_Exporter();
        if (!$db_exporter->export($temp_db_file)) {
            mhz_log("Database export failed.");
            return false;
        }

        // 3. Zip Files
        $file_zipper = new File_Zipper();
        // We want to zip ABSPATH, but we need to include the DB file which is currently in backup_dir
        // Strategy: Zip ABSPATH (excluding backup_dir), then add DB file to zip.

        if (!$file_zipper->zip(ABSPATH, $final_zip_path, ['mhz-backups'])) {
            mhz_log("File zipping failed.");
            @unlink($temp_db_file);
            return false;
        }

        // 4. Add DB file to the existing Zip
        $zip = new \ZipArchive();
        if ($zip->open($final_zip_path) === true) {
            $zip->addFile($temp_db_file, 'database.sql');
            $zip->close();
        } else {
            mhz_log("Failed to add database.sql to zip.");
        }

        // 5. Cleanup
        @unlink($temp_db_file);

        mhz_log("Backup created successfully: $final_zip_path");

        return [
            'file' => $final_zip_name,
            'path' => $final_zip_path,
            'url' => content_url('uploads/mhz-backups/' . $final_zip_name),
            'size' => mhz_format_size(filesize($final_zip_path)),
            'time' => $timestamp
        ];
    }

    public function get_backups()
    {
        $files = glob($this->backup_dir . '/*.wpress');
        $backups = [];
        if ($files) {
            foreach ($files as $file) {
                $backups[] = [
                    'name' => basename($file),
                    'path' => $file,
                    'size' => mhz_format_size(filesize($file)),
                    'time' => date('Y-m-d H:i:s', filemtime($file))
                ];
            }
        }
        return $backups;
    }
}
