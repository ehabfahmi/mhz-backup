<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Log message to debug.log or custom log file
 */
function mhz_log($message)
{
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[MHZ] ' . print_r($message, true));
    }
}

/**
 * Format bytes to human readable
 */
function mhz_format_size($bytes, $precision = 2)
{
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
