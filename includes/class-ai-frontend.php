<?php

class AI_Frontend {

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_shortcode('ai_itinerary_widget', [$this, 'shortcode']);
    }

    public function enqueue_assets() {
        // Get the plugin URL correctly by referencing a file in the plugin root
        $plugin_file = dirname(__FILE__, 2) . '/ai-itinerary-plugin.php';
        $plugin_url = plugin_dir_url($plugin_file);
        
        // CSS
        wp_enqueue_style('ai-itinerary-frontend', $plugin_url . 'assets/css/frontend.css', [], '1.0');

        // JS
        wp_register_script('ai-itinerary-frontend', $plugin_url . 'assets/js/frontend.js', ['jquery'], '1.0', true);

        $options = [
            'free_prompts' => (int) get_option('ai_free_prompts', 3),
            'pdf_style' => get_option('ai_pdf_style', 'minimal'),
            'output_language' => get_option('ai_output_language', 'en'),
            'widget_style' => get_option('ai_widget_style', 'floating'),
            'interface_type' => get_option('ai_interface_type', 'chat'),
            'allow_guest_save' => get_option('ai_allow_guest_save', 'yes'),
            'woo_integration' => get_option('ai_woo_integration', 'yes'),
            'warn_on_close' => get_option('ai_warn_on_close', 'yes'),
        ];

        wp_localize_script('ai-itinerary-frontend', 'aiItinerary', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_itinerary_nonce'),
            'options' => $options,
        ]);

        wp_enqueue_script('ai-itinerary-frontend');
    }

    public function shortcode($atts = []) {
        if (get_option('ai_enable_shortcode', 'yes') !== 'yes') return '';
        return $this->render_widget();
    }

    public function render_widget() {
        ob_start();
        $options = [
            'pdf_style' => get_option('ai_pdf_style', 'minimal'),
            'output_language' => get_option('ai_output_language', 'en'),
            'widget_style' => get_option('ai_widget_style', 'floating'),
            'interface_type' => get_option('ai_interface_type', 'chat'),
            'allow_guest_save' => get_option('ai_allow_guest_save', 'yes'),
            'warn_on_close' => get_option('ai_warn_on_close', 'yes'),
        ];
        ?>
        <div id="ai-itinerary-widget" class="ai-itinerary-widget <?php echo esc_attr($options['widget_style']); ?>">
            <button class="ai-open-widget">Plan trip</button>
            <div class="ai-widget-panel" aria-hidden="true">
                <div class="ai-widget-header">AI Itinerary</div>
                <div class="ai-widget-body">
                    <?php if ($options['interface_type'] === 'chat') : ?>
                        <div class="ai-chat-area"></div>
                        <div class="ai-input-wrapper">
                            <textarea class="ai-input" placeholder="Describe your trip..."></textarea>
                            <button class="ai-send-btn">Send</button>
                        </div>
                    <?php else: ?>
                        <form class="ai-form">
                            <input type="text" name="destination" placeholder="Destination">
                            <input type="date" name="start_date">
                            <input type="date" name="end_date">
                            <button type="submit">Generate</button>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="ai-widget-footer">
                    <button class="ai-save-itinerary" disabled>Save</button>
                    <button class="ai-download-pdf" disabled>Download PDF</button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}