<?php
if (!defined('ABSPATH')) exit;

class AIP_Frontend {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_shortcode('ai_itinerary', [$this, 'render_shortcode']);
        add_action('wp_footer', [$this, 'render_widget']);
    }

    public function enqueue_assets() {
        // React bundle
        $dist_path = AIP_PLUGIN_DIR . 'assets/dist/aip-widget.js';
        if (file_exists($dist_path)) {
            wp_enqueue_script('aip-widget', AIP_PLUGIN_URL . 'assets/dist/aip-widget.js', [], AIP_VERSION, true);
            wp_enqueue_style('aip-widget', AIP_PLUGIN_URL . 'assets/dist/aip-widget.css', [], AIP_VERSION);
        }

        // Pass config to React
        wp_localize_script('aip-widget', 'aipConfig', [
            'api_url'          => rest_url('aip/v1'),
            'nonce'            => wp_create_nonce('wp_rest'),
            'is_logged_in'     => is_user_logged_in(),
            'user_id'          => get_current_user_id(),
            'bot_name'         => get_option('aip_bot_name', 'Travel Buddy'),
            'primary_color'    => get_option('aip_primary_color', '#2271b1'),
            'secondary_color'  => get_option('aip_secondary_color', '#135e96'),
            'free_limit'       => (int) get_option('aip_free_itinerary_limit', 3),
            'google_client_id' => get_option('aip_google_client_id', ''),
            'default_language' => get_option('aip_default_language', 'en'),
        ]);
    }

    public function render_shortcode($atts) {
        return '<div id="aip-fullpage" data-mode="fullpage"></div>';
    }

    public function render_widget() {
        echo '<div id="aip-widget" data-mode="widget"></div>';
    }
}
