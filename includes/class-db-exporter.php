<?php
namespace MHZ;

if (!defined('ABSPATH')) {
    exit;
}

class DB_Exporter
{

    /**
     * Export the database to a file
     *
     * @param string $file_path Absolute path to the destination SQL file
     * @param array $tables Optional. List of tables to export. Default empty (all).
     * @return bool Success
     */
    public function export(string $file_path, array $tables = []): bool
    {
        global $wpdb;

        mhz_log("Starting DB Export to $file_path");

        $handle = fopen($file_path, 'w');
        if (!$handle) {
            mhz_log("Failed to open file for writing: $file_path");
            return false;
        }

        // 1. Get Tables
        if (empty($tables)) {
            $tables = $wpdb->get_results("SHOW FULL TABLES", ARRAY_N);
            // Filter views if necessary, but "SHOW FULL TABLES" gives us type to check
            // For simplicity, we stick to base tables usually, or dump views as views.
            // Let's just get table names for now.
            $tables = $wpdb->get_col("SHOW TABLES");
        }

        // Write Header
        fwrite($handle, "-- WordPress Comprehensive Backup\n");
        fwrite($handle, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
        fwrite($handle, "-- Host: " . DB_HOST . "\n");
        fwrite($handle, "-- Database: " . DB_NAME . "\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS = 0;\n\n");

        foreach ($tables as $table) {
            // Structure
            $row = $wpdb->get_row("SHOW CREATE TABLE `$table`", ARRAY_N);
            if ($row) {
                fwrite($handle, "DROP TABLE IF EXISTS `$table`;\n");
                fwrite($handle, $row[1] . ";\n\n");
            }

            // Data
            // We chunk data to avoid memory issues
            $count = $wpdb->get_var("SELECT COUNT(*) FROM `$table`");

            if ($count > 0) {
                // Iterate in chunks of 1000
                $limit = 1000;
                for ($offset = 0; $offset < $count; $offset += $limit) {
                    $rows = $wpdb->get_results("SELECT * FROM `$table` LIMIT $limit OFFSET $offset", ARRAY_A);

                    if ($rows) {
                        foreach ($rows as $row_data) {
                            $vals = [];
                            foreach ($row_data as $key => $val) {
                                if (is_null($val)) {
                                    $vals[] = "NULL";
                                } else {
                                    // Escape string
                                    $vals[] = "'" . esc_sql($val) . "'";
                                }
                            }
                            fwrite($handle, "INSERT INTO `$table` VALUES (" . implode(',', $vals) . ");\n");
                        }
                    }
                    // Force garbage collection if needed, though PHP usu handles logic scope well
                }
                fwrite($handle, "\n");
            }
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($handle);

        mhz_log("DB Export completed.");
        return true;
    }
}
