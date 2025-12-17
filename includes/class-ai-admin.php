<?php

class AI_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'settings']);
    }

    public function menu() {
        add_menu_page(
            'AI Itinerary',
            'AI Itinerary',
            'manage_options',
            'ai-itinerary-settings',
            [$this, 'settings_page'],
            'dashicons-location-alt'
        );
    }

    public function settings() {
        register_setting('ai_itinerary_group', 'ai_api_key');
        register_setting('ai_itinerary_group', 'ai_free_prompts');
        register_setting('ai_itinerary_group', 'ai_premium_price');

        register_setting('ai_itinerary_group', 'ai_pdf_style');
        register_setting('ai_itinerary_group', 'ai_output_language');
        register_setting('ai_itinerary_group', 'ai_widget_style');
        register_setting('ai_itinerary_group', 'ai_interface_type');
        register_setting('ai_itinerary_group', 'ai_allow_guest_save');
        register_setting('ai_itinerary_group', 'ai_woo_integration');
        register_setting('ai_itinerary_group', 'ai_warn_on_close');
        register_setting('ai_itinerary_group', 'ai_enable_shortcode');
    }

    public function settings_page() {
        $pdf_style = esc_attr(get_option('ai_pdf_style', 'minimal'));
        $output_language = esc_attr(get_option('ai_output_language', 'en'));
        $widget_style = esc_attr(get_option('ai_widget_style', 'floating'));
        $interface_type = esc_attr(get_option('ai_interface_type', 'chat'));
        $allow_guest_save = get_option('ai_allow_guest_save', 'yes');
        $woo_integration = get_option('ai_woo_integration', 'yes');
        $warn_on_close = get_option('ai_warn_on_close', 'yes');
        $enable_shortcode = get_option('ai_enable_shortcode', 'yes');
        ?>
        <div class="wrap">
            <h1>AI Itinerary Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields('ai_itinerary_group'); ?>
                <?php do_settings_sections('ai_itinerary_group'); ?>

                <table class="form-table">
                    <tr>
                        <th>OpenAI API Key</th>
                        <td><input type="text" name="ai_api_key" value="<?php echo esc_attr(get_option('ai_api_key')); ?>" class="regular-text"></td>
                    </tr>

                    <tr>
                        <th>Free User Prompts</th>
                        <td><input type="number" name="ai_free_prompts" value="<?php echo esc_attr(get_option('ai_free_prompts', 3)); ?>"></td>
                    </tr>

                    <tr>
                        <th>Premium Price</th>
                        <td><input type="text" name="ai_premium_price" value="<?php echo esc_attr(get_option('ai_premium_price', '5')); ?>"></td>
                    </tr>

                    <tr>
                        <th>PDF Style</th>
                        <td>
                            <select name="ai_pdf_style">
                                <option value="minimal" <?php selected($pdf_style, 'minimal'); ?>>Minimal</option>
                                <option value="modern" <?php selected($pdf_style, 'modern'); ?>>Modern</option>
                                <option value="image-heavy" <?php selected($pdf_style, 'image-heavy'); ?>>Image-heavy</option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th>Output Language</th>
                        <td><input type="text" name="ai_output_language" value="<?php echo $output_language; ?>" class="regular-text"><p class="description">Use ISO language codes, e.g. <code>en</code></p></td>
                    </tr>

                    <tr>
                        <th>Widget Style</th>
                        <td>
                            <select name="ai_widget_style">
                                <option value="floating" <?php selected($widget_style, 'floating'); ?>>Floating</option>
                                <option value="embedded" <?php selected($widget_style, 'embedded'); ?>>Embedded</option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th>Interface Type</th>
                        <td>
                            <select name="ai_interface_type">
                                <option value="chat" <?php selected($interface_type, 'chat'); ?>>Chat</option>
                                <option value="form" <?php selected($interface_type, 'form'); ?>>Form</option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th>Allow Guest Save</th>
                        <td>
                            <label><input type="checkbox" name="ai_allow_guest_save" value="yes" <?php checked($allow_guest_save, 'yes'); ?>> Allow guests to save itineraries (will prompt to download)</label>
                        </td>
                    </tr>

                    <tr>
                        <th>WooCommerce Integration</th>
                        <td>
                            <label><input type="checkbox" name="ai_woo_integration" value="yes" <?php checked($woo_integration, 'yes'); ?>> Enable WooCommerce integration for premium purchases</label>
                        </td>
                    </tr>

                    <tr>
                        <th>Warn on Close</th>
                        <td>
                            <label><input type="checkbox" name="ai_warn_on_close" value="yes" <?php checked($warn_on_close, 'yes'); ?>> Warn users before closing the chat if unsaved</label>
                        </td>
                    </tr>

                    <tr>
                        <th>Enable Shortcode</th>
                        <td>
                            <label><input type="checkbox" name="ai_enable_shortcode" value="yes" <?php checked($enable_shortcode, 'yes'); ?>> Enable [ai_itinerary_widget] shortcode to place widget on any page</label>
                        </td>
                    </tr>

                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
