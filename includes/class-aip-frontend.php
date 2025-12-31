<?php
/**
 * Frontend Handler - Widget & Shortcodes
 *
 * @package AI_Itinerary_Generator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AIP_Frontend {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_shortcode('ai_itinerary', array($this, 'render_widget_shortcode'));
        add_action('wp_footer', array($this, 'render_widget'));
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets() {
        // CSS
        wp_enqueue_style('aip-frontend-css', AIP_PLUGIN_URL . 'assets/css/frontend.css', array(), AIP_VERSION);
        
        // JS
        wp_enqueue_script('aip-frontend-js', AIP_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), AIP_VERSION, true);
        
        // Stripe JS (if Stripe enabled)
        if (in_array(get_option('aip_payment_method', 'stripe'), array('stripe', 'both'))) {
            wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', array(), null, true);
        }
        
        // PayPal JS (if PayPal enabled)
        if (in_array(get_option('aip_payment_method'), array('paypal', 'both'))) {
            $paypal_client_id = get_option('aip_paypal_client_id');
            if (!empty($paypal_client_id)) {
                wp_enqueue_script('paypal-js', 'https://www.paypal.com/sdk/js?client-id=' . $paypal_client_id . '&currency=' . get_option('aip_currency', 'USD'), array(), null, true);
            }
        }
        
        // Localize script
        wp_localize_script('aip-frontend-js', 'aipConfig', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aip_nonce'),
            'current_user_id' => get_current_user_id(),
            'is_logged_in' => is_user_logged_in(),
            'widget_style' => get_option('aip_widget_style', 'chat'),
            'default_language' => get_option('aip_default_language', 'en'),
            'premium_price' => get_option('aip_premium_price', 5.00),
            'currency' => get_option('aip_currency', 'USD'),
            'free_limit' => get_option('aip_free_itinerary_limit', 3),
            'require_account' => get_option('aip_require_account', 'yes'),
            'warn_before_close' => get_option('aip_warn_before_close', 'yes'),
            'payment_method' => get_option('aip_payment_method', 'stripe'),
            'stripe_public_key' => get_option('aip_stripe_public_key'),
            'primary_color' => get_option('aip_primary_color', '#2271b1'),
            'secondary_color' => get_option('aip_secondary_color', '#135e96'),
            'google_client_id' => get_option('aip_google_client_id'),
            'texts' => array(
                'generating' => __('Generating your itinerary...', 'ai-itinerary-plugin'),
                'error' => __('An error occurred. Please try again.', 'ai-itinerary-plugin'),
                'login_required' => __('Please log in or create an account', 'ai-itinerary-plugin'),
                'upgrade_required' => __('Upgrade to premium for more itineraries', 'ai-itinerary-plugin'),
                'payment_success' => __('Payment successful! Generating your PDF...', 'ai-itinerary-plugin'),
                'unsaved_changes' => __('You have unsaved changes. Are you sure you want to close?', 'ai-itinerary-plugin'),
            ),
        ));
    }
    
    /**
     * Render widget shortcode
     */
    public function render_widget_shortcode($atts) {
        $atts = shortcode_atts(array(
            'style' => get_option('aip_widget_style', 'chat'),
        ), $atts);
        
        ob_start();
        $this->render_widget_html($atts['style'], true);
        return ob_get_clean();
    }
    
    /**
     * Render widget in footer (floating widget)
     */
    public function render_widget() {
        $widget_style = get_option('aip_widget_style', 'chat');
        $this->render_widget_html($widget_style, false);
    }
    
    /**
     * Render widget HTML
     */
    private function render_widget_html($style, $embedded = false) {
        $current_user = wp_get_current_user();
        $primary_color = get_option('aip_primary_color', '#2271b1');
        ?>
        <div class="aip-widget-container <?php echo $embedded ? 'embedded' : 'floating'; ?>" 
             data-style="<?php echo esc_attr($style); ?>">
            
            <?php if (!$embedded): ?>
            <!-- Floating trigger button -->
            <button class="aip-trigger-btn" style="background-color: <?php echo esc_attr($primary_color); ?>;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
            </button>
            <?php endif; ?>
            
            <!-- Widget panel -->
            <div class="aip-widget-panel" style="<?php echo $embedded ? 'display: block;' : ''; ?>">
                <div class="aip-widget-header" style="background-color: <?php echo esc_attr($primary_color); ?>;">
                    <h3><?php _e('AI Travel Planner', 'ai-itinerary-plugin'); ?></h3>
                    <?php if (!$embedded): ?>
                    <button class="aip-close-btn">&times;</button>
                    <?php endif; ?>
                </div>
                
                <div class="aip-widget-body">
                    <!-- Loading state -->
                    <div class="aip-loading" style="display: none;">
                        <div class="aip-spinner"></div>
                        <p><?php _e('Generating your itinerary...', 'ai-itinerary-plugin'); ?></p>
                    </div>
                    
                    <!-- Auth section (if not logged in) -->
                    <?php if (!is_user_logged_in()): ?>
                    <div class="aip-auth-section">
                        <div class="aip-auth-tabs">
                            <button class="aip-auth-tab active" data-tab="login"><?php _e('Login', 'ai-itinerary-plugin'); ?></button>
                            <button class="aip-auth-tab" data-tab="register"><?php _e('Register', 'ai-itinerary-plugin'); ?></button>
                        </div>
                        
                        <div class="aip-auth-content login active">
                            <form class="aip-login-form">
                                <input type="email" name="email" placeholder="<?php esc_attr_e('Email', 'ai-itinerary-plugin'); ?>" required>
                                <input type="password" name="password" placeholder="<?php esc_attr_e('Password', 'ai-itinerary-plugin'); ?>" required>
                                <button type="submit" class="aip-btn-primary"><?php _e('Login', 'ai-itinerary-plugin'); ?></button>
                            </form>
                            
                            <?php if (get_option('aip_google_client_id')): ?>
                            <div class="aip-divider"><?php _e('OR', 'ai-itinerary-plugin'); ?></div>
                            <div id="aip-google-signin" class="aip-google-btn"></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="aip-auth-content register">
                            <form class="aip-register-form">
                                <input type="text" name="first_name" placeholder="<?php esc_attr_e('First Name', 'ai-itinerary-plugin'); ?>" required>
                                <input type="text" name="last_name" placeholder="<?php esc_attr_e('Last Name', 'ai-itinerary-plugin'); ?>" required>
                                <input type="email" name="email" placeholder="<?php esc_attr_e('Email', 'ai-itinerary-plugin'); ?>" required>
                                <input type="password" name="password" placeholder="<?php esc_attr_e('Password (min 6 characters)', 'ai-itinerary-plugin'); ?>" required>
                                <button type="submit" class="aip-btn-primary"><?php _e('Register', 'ai-itinerary-plugin'); ?></button>
                            </form>
                            
                            <?php if (get_option('aip_google_client_id')): ?>
                            <div class="aip-divider"><?php _e('OR', 'ai-itinerary-plugin'); ?></div>
                            <div id="aip-google-signin-register" class="aip-google-btn"></div>
                            <?php endif; ?>
                        </div>
                        
                        <p class="aip-guest-option">
                            <a href="#" class="aip-continue-guest"><?php _e('Continue as guest (limited features)', 'ai-itinerary-plugin'); ?></a>
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Main content -->
                    <div class="aip-main-content" <?php echo is_user_logged_in() ? '' : 'style="display: none;"'; ?>>
                        <!-- User info & limits -->
                        <div class="aip-user-info">
                            <?php if (is_user_logged_in()): ?>
                            <p><?php printf(__('Welcome, %s!', 'ai-itinerary-plugin'), esc_html($current_user->display_name)); ?></p>
                            <?php endif; ?>
                            <div class="aip-limit-info">
                                <span class="aip-remaining-count">-</span> <?php _e('free itineraries remaining', 'ai-itinerary-plugin'); ?>
                            </div>
                        </div>
                        
                        <!-- Chat interface -->
                        <?php if (in_array($style, array('chat', 'both'))): ?>
                        <div class="aip-chat-interface">
                            <div class="aip-chat-messages">
                                <div class="aip-message bot">
                                    <?php _e("Hi! I'm your AI travel assistant. Where would you like to go?", 'ai-itinerary-plugin'); ?>
                                </div>
                            </div>
                            <div class="aip-chat-input">
                                <input type="text" class="aip-chat-field" placeholder="<?php esc_attr_e('Tell me about your trip...', 'ai-itinerary-plugin'); ?>">
                                <button class="aip-send-btn" style="background-color: <?php echo esc_attr($primary_color); ?>;">
                                    <?php _e('Send', 'ai-itinerary-plugin'); ?>
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Form interface -->
                        <?php if (in_array($style, array('form', 'both'))): ?>
                        <div class="aip-form-interface" <?php echo $style === 'both' ? 'style="display: none;"' : ''; ?>>
                            <form class="aip-itinerary-form">
                                <div class="aip-form-group">
                                    <label><?php _e('Destination', 'ai-itinerary-plugin'); ?></label>
                                    <input type="text" name="destination" required placeholder="<?php esc_attr_e('e.g., Paris, France', 'ai-itinerary-plugin'); ?>">
                                </div>
                                
                                <div class="aip-form-group">
                                    <label><?php _e('Number of Days', 'ai-itinerary-plugin'); ?></label>
                                    <input type="number" name="days" min="1" max="30" value="3" required>
                                </div>
                                
                                <div class="aip-form-group">
                                    <label><?php _e('Start Date (Optional)', 'ai-itinerary-plugin'); ?></label>
                                    <input type="date" name="start_date">
                                </div>
                                
                                <div class="aip-form-group">
                                    <label><?php _e('Preferences (Optional)', 'ai-itinerary-plugin'); ?></label>
                                    <textarea name="preferences" rows="3" placeholder="<?php esc_attr_e('e.g., budget-friendly, family trip, adventure...', 'ai-itinerary-plugin'); ?>"></textarea>
                                </div>
                                
                                <div class="aip-form-group">
                                    <label><?php _e('Itinerary Type', 'ai-itinerary-plugin'); ?></label>
                                    <select name="type">
                                        <option value="free"><?php _e('Free (Basic)', 'ai-itinerary-plugin'); ?></option>
                                        <option value="premium"><?php printf(__('Premium ($%s) - Detailed', 'ai-itinerary-plugin'), get_option('aip_premium_price', 5.00)); ?></option>
                                    </select>
                                </div>
                                
                                <button type="submit" class="aip-btn-primary" style="background-color: <?php echo esc_attr($primary_color); ?>;">
                                    <?php _e('Generate Itinerary', 'ai-itinerary-plugin'); ?>
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($style === 'both'): ?>
                        <div class="aip-interface-toggle">
                            <button class="aip-toggle-chat active"><?php _e('Chat', 'ai-itinerary-plugin'); ?></button>
                            <button class="aip-toggle-form"><?php _e('Form', 'ai-itinerary-plugin'); ?></button>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Results section -->
                        <div class="aip-results" style="display: none;">
                            <div class="aip-result-content"></div>
                            
                            <div class="aip-result-actions">
                                <button class="aip-btn-download"><?php _e('Download PDF', 'ai-itinerary-plugin'); ?></button>
                                <button class="aip-btn-save"><?php _e('Save Itinerary', 'ai-itinerary-plugin'); ?></button>
                                <button class="aip-btn-new"><?php _e('Create New', 'ai-itinerary-plugin'); ?></button>
                            </div>
                            
                            <!-- Affiliate links -->
                            <div class="aip-affiliate-section"></div>
                        </div>
                    </div>
                    
                    <!-- Payment modal -->
                    <div class="aip-payment-modal" style="display: none;">
                        <div class="aip-payment-content">
                            <h3><?php _e('Premium Itinerary Payment', 'ai-itinerary-plugin'); ?></h3>
                            <p><?php printf(__('Amount: %s %s', 'ai-itinerary-plugin'), get_option('aip_currency', 'USD'), get_option('aip_premium_price', 5.00)); ?></p>
                            
                            <div id="aip-payment-element"></div>
                            
                            <div class="aip-payment-buttons">
                                <button class="aip-btn-pay" style="background-color: <?php echo esc_attr($primary_color); ?>;"><?php _e('Pay Now', 'ai-itinerary-plugin'); ?></button>
                                <button class="aip-btn-cancel"><?php _e('Cancel', 'ai-itinerary-plugin'); ?></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

