<?php
/**
 * Plugin Name: AI Travel Itinerary Generator
 * Description: AI-powered itinerary generator with free + premium modes, PDF export, multilingual support, and floating chat widget.
 * Version: 1.0.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) exit;

// Autoload classes
require_once plugin_dir_path(__FILE__) . 'includes/class-ai-loader.php';

// Activation: create DB tables
function ai_itinerary_activate() {
    require_once plugin_dir_path(__FILE__) . 'includes/class-ai-database.php';
    if (class_exists('AI_Database') && method_exists('AI_Database', 'create_tables')) {
        AI_Database::create_tables();
    }
}
register_activation_hook(__FILE__, 'ai_itinerary_activate');

// Start plugin
function ai_itinerary_init() {
    $plugin = new AI_Loader();
    $plugin->run();
}
add_action('plugins_loaded', 'ai_itinerary_init');
