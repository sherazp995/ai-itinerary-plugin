<?php
/**
 * AI Travel Itinerary Generator
 *
 * @package AI_Itinerary_Generator
 * @author Your Name
 * @copyright 2025 Your Company
 * @license GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name: AI Travel Itinerary Generator
 * Plugin URI: https://example.com/ai-itinerary-plugin
 * Description: AI-powered travel itinerary generator with free & premium modes, affiliate integration, PDF export, and multilingual support.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-itinerary-plugin
 * Domain Path: /languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AIP_VERSION', '1.0.0');
define('AIP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AIP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AIP_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
class AI_Itinerary_Plugin {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        // Core classes
        require_once AIP_PLUGIN_DIR . 'includes/class-aip-database.php';
        require_once AIP_PLUGIN_DIR . 'includes/class-aip-admin.php';
        require_once AIP_PLUGIN_DIR . 'includes/class-aip-frontend.php';
        require_once AIP_PLUGIN_DIR . 'includes/class-aip-api.php';
        require_once AIP_PLUGIN_DIR . 'includes/class-aip-pdf.php';
        require_once AIP_PLUGIN_DIR . 'includes/class-aip-payment.php';
        require_once AIP_PLUGIN_DIR . 'includes/class-aip-auth.php';
        require_once AIP_PLUGIN_DIR . 'includes/class-aip-affiliate.php';
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    /**
     * Initialize plugin components
     */
    public function init() {
        // Initialize components
        AIP_Database::get_instance();
        AIP_Admin::get_instance();
        AIP_Frontend::get_instance();
        AIP_API::get_instance();
        AIP_PDF::get_instance();
        AIP_Payment::get_instance();
        AIP_Auth::get_instance();
        AIP_Affiliate::get_instance();
    }
    
    /**
     * Activation hook
     */
    public function activate() {
        // Create database tables
        AIP_Database::create_tables();
        
        // Set default options
        $defaults = array(
            'aip_free_itinerary_limit' => 3,
            'aip_premium_price' => 5.00,
            'aip_default_language' => 'en',
            'aip_widget_style' => 'chat',
            'aip_pdf_style' => 'modern',
            'aip_payment_method' => 'stripe',
            'aip_require_account' => 'yes',
            'aip_save_itineraries' => 'yes',
            'aip_warn_before_close' => 'yes',
            'aip_ai_tone' => 'friendly',
        );
        
        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Deactivation hook
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
}

// Initialize the plugin
function ai_itinerary_plugin() {
    return AI_Itinerary_Plugin::get_instance();
}

// Start the plugin
ai_itinerary_plugin();

