<?php
/**
 * PHPUnit bootstrap - loads WordPress test environment
 */

// Find WordPress
$wp_root = '/var/www/html/wordpress';

if (!file_exists($wp_root . '/wp-load.php')) {
    die("WordPress not found at {$wp_root}\n");
}

// Define ABSPATH before loading
define('ABSPATH', $wp_root . '/');

// Suppress headers-already-sent warnings in CLI
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';

// Load WordPress
require_once $wp_root . '/wp-load.php';

// Ensure our plugin is loaded
require_once dirname(__DIR__) . '/ai-itinerary-plugin.php';

// Load test helpers
require_once __DIR__ . '/helpers/MockClaude.php';
