<?php
/**
 * Loads everything. Every entry point (index.php, admin, CLI, tests) starts here.
 */

if (PHP_VERSION_ID < 80000) {
    http_response_code(500);
    exit('This website needs PHP 8.0 or newer. Please switch the PHP version in your hosting panel.');
}

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__));
    define('APP_DIR', __DIR__);
    define('TEMPLATE_DIR', ROOT_DIR . '/templates');
}

require_once APP_DIR . '/config.php';
require_once APP_DIR . '/helpers.php';
require_once APP_DIR . '/content.php';
require_once APP_DIR . '/cache.php';
require_once APP_DIR . '/render.php';
require_once APP_DIR . '/front.php';

mb_internal_encoding('UTF-8');
date_default_timezone_set((string) (config('timezone') ?: 'UTC'));

error_reporting(E_ALL);
ini_set('display_errors', config('debug') || PHP_SAPI === 'cli' ? '1' : '0');
ini_set('log_errors', '1');
