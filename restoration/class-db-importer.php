<?php
namespace MHZ;

if (!defined('ABSPATH')) {
    exit;
}

class DB_Importer
{

    /**
     * Import a SQL file into the database
     *
     * @param string $sql_file Absolute path to the SQL file
     * @return bool
     */
    public function import(string $sql_file): bool
    {
        global $wpdb;

        if (!file_exists($sql_file)) {
            return false;
        }

        mhz_log("Starting DB Import from $sql_file");

        // Disable foreign key checks for safety during import
        $wpdb->query("SET FOREIGN_KEY_CHECKS = 0");

        $handle = fopen($sql_file, 'r');
        if (!$handle) {
            return false;
        }

        $query = '';
        while (($line = fgets($handle)) !== false) {
            $line = trim($line);

            // Skip comments
            if (empty($line) || strpos($line, '--') === 0 || strpos($line, '/*') === 0) {
                continue;
            }

            $query .= $line;

            // If line ends with semicolon, it's a complete query
            if (substr($line, -1) === ';') {
                $result = $wpdb->query($query);
                if ($result === false) {
                    mhz_log("DB Error: " . $wpdb->last_error . " | Query: " . substr($query, 0, 100) . "...");
                    // Check if critical error or just minor? Usually we want to stop or log.
                }
                $query = '';
            }
        }

        fclose($handle);

        $wpdb->query("SET FOREIGN_KEY_CHECKS = 1");

        mhz_log("DB Import finished.");
        return true;
    }
}
