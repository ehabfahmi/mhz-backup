<?php
namespace MHZ;

if (!defined('ABSPATH')) {
    exit;
}

class File_Zipper
{

    private $excludes = [];

    public function __construct()
    {
        // Default excludes
        $this->excludes = [
            '.git',
            'node_modules',
            basename(MHZ_PLUGIN_DIR), // Exclude self if needed, or just specific cache dirs
            // We usually exclude the backup storage directory if it's inside ABSPATH
            'mhz-backups'
        ];
    }

    /**
     * Zip a directory
     *
     * @param string $source_path Directory to zip (usually ABSPATH)
     * @param string $destination_zip Zip file path
     * @param array $additional_excludes Custom excludes
     * @return bool
     */
    public function zip(string $source_path, string $destination_zip, array $additional_excludes = []): bool
    {

        if (!extension_loaded('zip') || !file_exists($source_path)) {
            mhz_log("Zip extension missing or source path invalid.");
            return false;
        }

        $valid_excludes = array_merge($this->excludes, $additional_excludes);

        mhz_log("Starting Zip process for $source_path to $destination_zip");

        $zip = new \ZipArchive();
        if ($zip->open($destination_zip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            mhz_log("Failed to open zip file: $destination_zip");
            return false;
        }

        $source_path = realpath($source_path);

        // Use Recursive Directory Iterator
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source_path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $file_path = $file->getRealPath();

            // Relative path for zip
            $relative_path = substr($file_path, strlen($source_path) + 1);

            // Check excludes
            // Check against each exclude pattern
            $excluded = false;
            foreach ($valid_excludes as $exclude) {
                if (strpos($file_path, $exclude) !== false) {
                    $excluded = true;
                    break;
                }
            }
            if ($excluded) {
                continue;
            }

            if ($file->isDir()) {
                $zip->addEmptyDir($relative_path);
            } else {
                $zip->addFile($file_path, $relative_path);
            }
        }

        $result = $zip->close();
        mhz_log("Zip process finished. Result: " . ($result ? 'Success' : 'Failure'));
        return $result;
    }
}
