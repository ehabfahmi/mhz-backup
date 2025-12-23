<?php
namespace MHZ;

if (!defined('ABSPATH')) {
    exit;
}

class Search_Replace
{
    private $old_url;
    private $new_url;

    public function replace($old_url, $new_url)
    {
        $this->old_url = $old_url;
        $this->new_url = $new_url;

        if ($this->old_url === $this->new_url) {
            return;
        }

        mhz_log("Starting Search & Replace: $old_url -> $new_url");

        global $wpdb;

        // Get all tables
        $tables = $wpdb->get_results('SHOW TABLES', ARRAY_N);
        foreach ($tables as $table) {
            $table_name = $table[0];
            $this->process_table($table_name);
        }

        mhz_log("Search & Replace completed.");
    }

    private function process_table($table)
    {
        global $wpdb;

        // Get columns
        $columns = $wpdb->get_results("SHOW COLUMNS FROM `$table`");
        $pk = '';
        foreach ($columns as $column) {
            if ($column->Key == 'PRI') {
                $pk = $column->Field;
                break;
            }
        }

        // If no primary key, we might skip or handle differently. For simplicity, we skip strictly row-processing tables without PKs or handle them in bulk if safe.
        // Most WP tables have PK.
        if (!$pk) {
            return;
        }

        // We process row by row to handle serialization safely.
        // Optimization: Select only rows containing the old URL string.
        // Note: This simple LIKE might miss some serialized strings if they are split, but usually works for URLs.
        $rows = $wpdb->get_results("SELECT * FROM `$table`", ARRAY_A); // Processing all for safety in this version, optimization comes later if needed or usage of LIMIT/Offset.

        foreach ($rows as $row) {
            $update_needed = false;
            $updated_row = [];

            foreach ($row as $col => $val) {
                $new_val = $this->recursive_replace($val);
                if ($new_val !== $val) {
                    $update_needed = true;
                    $updated_row[$col] = $new_val;
                }
            }

            if ($update_needed) {
                $where = [$pk => $row[$pk]];
                $wpdb->update($table, $updated_row, $where);
            }
        }
    }

    private function recursive_replace($data)
    {
        if (is_string($data)) {
            // Check if serialized
            if (is_serialized($data)) {
                $unserialized = @unserialize($data);
                if ($unserialized !== false) {
                    $replaced = $this->recursive_replace($unserialized);
                    return serialize($replaced);
                }
            }
            return str_replace($this->old_url, $this->new_url, $data);
        }

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->recursive_replace($value);
            }
            return $data;
        }

        if (is_object($data)) {
            // We can't easily modify protected/private props without reflection, 
            // but for stdClass (common in WP options), this works.
            $vars = get_object_vars($data);
            foreach ($vars as $key => $value) {
                $data->$key = $this->recursive_replace($value);
            }
            return $data;
        }

        return $data;
    }
}
