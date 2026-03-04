<?php
/**
 * Plugin Name: AI Travel Itinerary Generator
 * Plugin URI: https://yoiner.gamercity.io
 * Description: AI-powered travel itinerary generator with chat interface, PDF export, and affiliate integration.
 * Version: 2.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Sheraz
 * Text Domain: ai-itinerary
 * Domain Path: /languages
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AIP_VERSION', '2.0.0');
define('AIP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AIP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AIP_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Composer autoloader (DOMPDF)
if (file_exists(AIP_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once AIP_PLUGIN_DIR . 'vendor/autoload.php';
}

// Include classes
require_once AIP_PLUGIN_DIR . 'includes/class-aip-database.php';
require_once AIP_PLUGIN_DIR . 'includes/class-aip-rest-api.php';
require_once AIP_PLUGIN_DIR . 'includes/class-aip-claude.php';
require_once AIP_PLUGIN_DIR . 'includes/class-aip-frontend.php';
require_once AIP_PLUGIN_DIR . 'includes/class-aip-admin.php';
require_once AIP_PLUGIN_DIR . 'includes/class-aip-pdf.php';
require_once AIP_PLUGIN_DIR . 'includes/class-aip-woocommerce.php';
require_once AIP_PLUGIN_DIR . 'includes/class-aip-membership.php';
require_once AIP_PLUGIN_DIR . 'includes/class-aip-travelpayouts.php';
require_once AIP_PLUGIN_DIR . 'includes/class-aip-skyscanner.php';
require_once AIP_PLUGIN_DIR . 'includes/class-aip-updater.php';

class AI_Itinerary_Plugin {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }

    public function init() {
        AIP_Database::get_instance();
        AIP_REST_API::get_instance();
        AIP_Claude::get_instance();
        AIP_Frontend::get_instance();
        AIP_Admin::get_instance();
        AIP_PDF::get_instance();
        AIP_WooCommerce::get_instance();
        AIP_Membership::get_instance();
        AIP_Travelpayouts::get_instance();
        AIP_Skyscanner::get_instance();
        AIP_Updater::init();
    }

    public function activate() {
        AIP_Database::create_tables();
        AIP_WooCommerce::create_products();
        flush_rewrite_rules();
    }

    public function deactivate() {
        flush_rewrite_rules();
    }
}

AI_Itinerary_Plugin::get_instance();
