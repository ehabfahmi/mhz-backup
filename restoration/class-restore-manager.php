<?php
namespace MHZ;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-db-importer.php';

class Restore_Manager
{

    /**
     * Restore from a backup file
     *
     * @param string $backup_file_path Absolute path to the .wpress/.zip file
     * @return bool
     */
    public function restore(string $backup_file_path): bool
    {
        mhz_log("Starting Restore from $backup_file_path");

        if (!class_exists('ZipArchive')) {
            mhz_log("ZipArchive missing.");
            return false;
        }

        $temp_dir = mhz_get_temp_dir();
        $extract_path = $temp_dir . uniqid('mhz_restore_');

        if (!wp_mkdir_p($extract_path)) {
            mhz_log("Could not create temp dir: $extract_path");
            return false;
        }

        // 1. Unzip
        $zip = new \ZipArchive();
        if ($zip->open($backup_file_path) === true) {
            $zip->extractTo($extract_path);
            $zip->close();
        } else {
            mhz_log("Failed to open zip.");
            return false;
        }

        // 2. Import DB if exists
        $db_file = $extract_path . '/database.sql';
        if (file_exists($db_file)) {
            // Capture Current/Target URL before import
            $target_url = get_option('siteurl');

            $importer = new DB_Importer();
            if (!$importer->import($db_file)) {
                mhz_log("DB Import failed.");
                return false;
            }
            unlink($db_file); // Cleanup DB file before moving files

            // Post-Import: Search & Replace
            // The DB now contains the OLD URL is siteurl
            // We need to fetch it from DB directly as internal cache might be stale or we just want raw DB value
            global $wpdb;
            $imported_url = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'siteurl'");

            if ($imported_url && $target_url && $imported_url !== $target_url) {
                mhz_log("Detected URL mismatch: Backup=$imported_url, Target=$target_url. Starting Search & Replace.");
                $replacer = new Search_Replace();
                $replacer->replace($imported_url, $target_url);

                // Flush Rewrite Rules
                flush_rewrite_rules();
            }
        }

        // 3. Clean Installation (Delete old files)
        $this->clean_installation();

        // 4. Move Files (Overwrite)
        // We iterate extracted files and move them to ABSPATH
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($extract_path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            // Calculated relative path
            $sub_path = substr($item->getPathname(), strlen($extract_path) + 1);
            $dest_path = ABSPATH . $sub_path;

            if ($item->isDir()) {
                if (!file_exists($dest_path)) {
                    wp_mkdir_p($dest_path);
                }
            } else {
                // Determine if we should overwrite
                // For safety, let's backup wp-config.php if we are about to overwrite it, or skip it
                if (basename($dest_path) === 'wp-config.php') {
                    // Skip wp-config.php overwrite for safety in this demo
                    continue;
                }

                // Copy/Move
                copy($item->getPathname(), $dest_path);
            }
        }

        // 4. Cleanup Temp
        $this->recursive_rmdir($extract_path);

        mhz_log("Restore completed successfully.");
        return true;
    }



    /**
     * Clean the current installation before restore
     * Preserves: wp-config.php, current plugin, backup dir
     */
    private function clean_installation()
    {
        mhz_log("Cleaning existing installation...");

        $protected_paths = [
            wp_normalize_path(ABSPATH . 'wp-config.php'),
            wp_normalize_path(MHZ_PLUGIN_DIR), // Protect this plugin
            wp_normalize_path(WP_CONTENT_DIR . '/uploads/mhz-backups') // Protect backups
        ];

        // Ensure we don't delete the backup source if it's passed differently? 
        // Typically the backup file is inside mhz-backups, so strictly protecting that folder is enough.

        $this->recursive_clean(ABSPATH, $protected_paths);

        mhz_log("Clean finished.");
    }

    private function recursive_clean($dir, $protected_paths)
    {
        $dir = wp_normalize_path($dir);

        if (!is_dir($dir))
            return;

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..')
                continue;

            $path = wp_normalize_path($dir . '/' . $item);

            // 1. Is this path protected?
            if (in_array($path, $protected_paths)) {
                // mhz_log("Skipping protected: $path");
                continue;
            }

            // 2. Is this path a PARENT of a protected path?
            $is_parent = false;
            foreach ($protected_paths as $protected) {
                if (strpos($protected, $path . '/') === 0) {
                    $is_parent = true;
                    break;
                }
            }

            if ($is_parent) {
                // It contains something protected, so we must recurse into it
                // and NOT delete the directory itself at the end.
                if (is_dir($path)) {
                    $this->recursive_clean($path, $protected_paths);
                }
            } else {
                // It is NOT protected and does NOT contain anything protected.
                // WE CAN DELETE IT ALL.
                if (is_dir($path)) {
                    $this->recursive_rmdir($path);
                } else {
                    unlink($path);
                }
            }
        }
    }

    private function recursive_rmdir($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . DIRECTORY_SEPARATOR . $object) && !is_link($dir . "/" . $object))
                        $this->recursive_rmdir($dir . DIRECTORY_SEPARATOR . $object);
                    else
                        unlink($dir . DIRECTORY_SEPARATOR . $object);
                }
            }
            rmdir($dir);
        }
    }
}

/**
 * Helper to get temp dir
 */
function mhz_get_temp_dir()
{
    $temp = get_temp_dir();
    return $temp;
}
