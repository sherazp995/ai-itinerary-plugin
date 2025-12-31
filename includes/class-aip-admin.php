<?php
/**
 * Admin Panel Handler
 *
 * @package AI_Itinerary_Generator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AIP_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX handlers for affiliate provider management
        add_action('wp_ajax_aip_save_affiliate_provider', array($this, 'ajax_save_affiliate_provider'));
        add_action('wp_ajax_aip_delete_affiliate_provider', array($this, 'ajax_delete_affiliate_provider'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('AI Itinerary', 'ai-itinerary-plugin'),
            __('AI Itinerary', 'ai-itinerary-plugin'),
            'manage_options',
            'aip-dashboard',
            array($this, 'render_dashboard'),
            'dashicons-palmtree',
            30
        );
        
        add_submenu_page(
            'aip-dashboard',
            __('Dashboard', 'ai-itinerary-plugin'),
            __('Dashboard', 'ai-itinerary-plugin'),
            'manage_options',
            'aip-dashboard',
            array($this, 'render_dashboard')
        );
        
        add_submenu_page(
            'aip-dashboard',
            __('Settings', 'ai-itinerary-plugin'),
            __('Settings', 'ai-itinerary-plugin'),
            'manage_options',
            'aip-settings',
            array($this, 'render_settings')
        );
        
        add_submenu_page(
            'aip-dashboard',
            __('Analytics', 'ai-itinerary-plugin'),
            __('Analytics', 'ai-itinerary-plugin'),
            'manage_options',
            'aip-analytics',
            array($this, 'render_analytics')
        );
        
        add_submenu_page(
            'aip-dashboard',
            __('Affiliate Providers', 'ai-itinerary-plugin'),
            __('Affiliate Providers', 'ai-itinerary-plugin'),
            'manage_options',
            'aip-affiliate-providers',
            array($this, 'render_affiliate_providers')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // General Settings
        register_setting('aip_general', 'aip_openai_api_key');
        register_setting('aip_general', 'aip_free_itinerary_limit');
        register_setting('aip_general', 'aip_premium_price');
        register_setting('aip_general', 'aip_default_language');
        register_setting('aip_general', 'aip_widget_style');
        register_setting('aip_general', 'aip_pdf_style');
        register_setting('aip_general', 'aip_ai_tone');
        register_setting('aip_general', 'aip_require_account');
        register_setting('aip_general', 'aip_save_itineraries');
        register_setting('aip_general', 'aip_warn_before_close');
        register_setting('aip_general', 'aip_bot_name'); // Bot identity
        
        // Payment Settings
        register_setting('aip_payment', 'aip_payment_method');
        register_setting('aip_payment', 'aip_stripe_public_key');
        register_setting('aip_payment', 'aip_stripe_secret_key');
        register_setting('aip_payment', 'aip_paypal_client_id');
        register_setting('aip_payment', 'aip_paypal_client_secret');
        register_setting('aip_payment', 'aip_paypal_mode'); // Sandbox or Production
        register_setting('aip_payment', 'aip_currency');
        
        // Affiliate Settings
        register_setting('aip_affiliate', 'aip_booking_affiliate_id');
        register_setting('aip_affiliate', 'aip_skyscanner_affiliate_id');
        register_setting('aip_affiliate', 'aip_getyourguide_affiliate_id');
        register_setting('aip_affiliate', 'aip_affiliate_button_style');
        
        // Google OAuth Settings
        register_setting('aip_auth', 'aip_google_client_id');
        register_setting('aip_auth', 'aip_google_client_secret');
        
        // Branding Settings
        register_setting('aip_branding', 'aip_primary_color');
        register_setting('aip_branding', 'aip_secondary_color');
        register_setting('aip_branding', 'aip_logo_url');
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'aip-') === false) {
            return;
        }
        
        wp_enqueue_style('aip-admin-css', AIP_PLUGIN_URL . 'assets/css/admin.css', array(), AIP_VERSION);
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', array(), '4.4.0', true);
        wp_enqueue_script('aip-admin-js', AIP_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'chart-js'), AIP_VERSION, true);
    }
    
    /**
     * Render dashboard page
     */
    public function render_dashboard() {
        $analytics = AIP_Database::get_analytics(30);
        ?>
        <div class="wrap">
            <h1><?php _e('AI Itinerary Generator - Dashboard', 'ai-itinerary-plugin'); ?></h1>
            
            <div class="aip-dashboard-stats">
                <div class="aip-stat-box">
                    <h3><?php _e('Total Itineraries (30 days)', 'ai-itinerary-plugin'); ?></h3>
                    <p class="aip-stat-number"><?php echo esc_html($analytics['total_itineraries']); ?></p>
                </div>
                
                <div class="aip-stat-box">
                    <h3><?php _e('Total Revenue (30 days)', 'ai-itinerary-plugin'); ?></h3>
                    <p class="aip-stat-number">$<?php echo number_format($analytics['total_revenue'], 2); ?></p>
                </div>
                
                <div class="aip-stat-box">
                    <h3><?php _e('Free vs Premium', 'ai-itinerary-plugin'); ?></h3>
                    <?php foreach ($analytics['itinerary_types'] as $type): ?>
                        <p><?php echo ucfirst($type->type); ?>: <?php echo esc_html($type->count); ?></p>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="aip-charts">
                <div class="aip-chart-container">
                    <h3><?php _e('Itineraries Created (Daily)', 'ai-itinerary-plugin'); ?></h3>
                    <canvas id="aip-itineraries-chart"></canvas>
                </div>
                
                <div class="aip-chart-container">
                    <h3><?php _e('Revenue (Daily)', 'ai-itinerary-plugin'); ?></h3>
                    <canvas id="aip-revenue-chart"></canvas>
                </div>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                // Itineraries Chart
                var itineraryData = <?php echo json_encode($analytics['daily_stats']); ?>;
                var itineraryLabels = itineraryData.map(item => item.date);
                var itineraryCounts = itineraryData.map(item => item.count);
                
                new Chart(document.getElementById('aip-itineraries-chart'), {
                    type: 'line',
                    data: {
                        labels: itineraryLabels,
                        datasets: [{
                            label: 'Itineraries',
                            data: itineraryCounts,
                            borderColor: '#2271b1',
                            backgroundColor: 'rgba(34, 113, 177, 0.1)',
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
                
                // Revenue Chart
                var revenueData = <?php echo json_encode($analytics['daily_revenue']); ?>;
                var revenueLabels = revenueData.map(item => item.date);
                var revenueAmounts = revenueData.map(item => parseFloat(item.revenue));
                
                new Chart(document.getElementById('aip-revenue-chart'), {
                    type: 'bar',
                    data: {
                        labels: revenueLabels,
                        datasets: [{
                            label: 'Revenue ($)',
                            data: revenueAmounts,
                            backgroundColor: '#00a32a',
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
            });
            </script>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render_settings() {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        ?>
        <div class="wrap">
            <h1><?php _e('AI Itinerary Settings', 'ai-itinerary-plugin'); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=aip-settings&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('General', 'ai-itinerary-plugin'); ?>
                </a>
                <a href="?page=aip-settings&tab=payment" class="nav-tab <?php echo $active_tab === 'payment' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Payment', 'ai-itinerary-plugin'); ?>
                </a>
                <a href="?page=aip-settings&tab=affiliate" class="nav-tab <?php echo $active_tab === 'affiliate' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Affiliate', 'ai-itinerary-plugin'); ?>
                </a>
                <a href="?page=aip-settings&tab=auth" class="nav-tab <?php echo $active_tab === 'auth' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Authentication', 'ai-itinerary-plugin'); ?>
                </a>
                <a href="?page=aip-settings&tab=branding" class="nav-tab <?php echo $active_tab === 'branding' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Branding', 'ai-itinerary-plugin'); ?>
                </a>
            </h2>
            
            <form method="post" action="options.php">
                <?php
                switch ($active_tab) {
                    case 'general':
                        $this->render_general_settings();
                        break;
                    case 'payment':
                        $this->render_payment_settings();
                        break;
                    case 'affiliate':
                        $this->render_affiliate_settings();
                        break;
                    case 'auth':
                        $this->render_auth_settings();
                        break;
                    case 'branding':
                        $this->render_branding_settings();
                        break;
                }
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render general settings
     */
    private function render_general_settings() {
        settings_fields('aip_general');
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="aip_openai_api_key"><?php _e('OpenAI API Key', 'ai-itinerary-plugin'); ?></label></th>
                <td><input type="text" name="aip_openai_api_key" id="aip_openai_api_key" value="<?php echo esc_attr(get_option('aip_openai_api_key')); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="aip_free_itinerary_limit"><?php _e('Free Itinerary Limit', 'ai-itinerary-plugin'); ?></label></th>
                <td><input type="number" name="aip_free_itinerary_limit" id="aip_free_itinerary_limit" value="<?php echo esc_attr(get_option('aip_free_itinerary_limit', 3)); ?>" min="0"></td>
            </tr>
            <tr>
                <th scope="row"><label for="aip_premium_price"><?php _e('Premium Price ($)', 'ai-itinerary-plugin'); ?></label></th>
                <td><input type="number" name="aip_premium_price" id="aip_premium_price" value="<?php echo esc_attr(get_option('aip_premium_price', 5.00)); ?>" step="0.01" min="0"></td>
            </tr>
            <tr>
                <th scope="row"><label for="aip_default_language"><?php _e('Default Language', 'ai-itinerary-plugin'); ?></label></th>
                <td>
                    <select name="aip_default_language" id="aip_default_language">
                        <option value="en" <?php selected(get_option('aip_default_language', 'en'), 'en'); ?>>English</option>
                        <option value="es" <?php selected(get_option('aip_default_language'), 'es'); ?>>Spanish</option>
                        <option value="fr" <?php selected(get_option('aip_default_language'), 'fr'); ?>>French</option>
                        <option value="de" <?php selected(get_option('aip_default_language'), 'de'); ?>>German</option>
                        <option value="it" <?php selected(get_option('aip_default_language'), 'it'); ?>>Italian</option>
                        <option value="pt" <?php selected(get_option('aip_default_language'), 'pt'); ?>>Portuguese</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="aip_widget_style"><?php _e('Widget Style', 'ai-itinerary-plugin'); ?></label></th>
                <td>
                    <select name="aip_widget_style" id="aip_widget_style">
                        <option value="chat" <?php selected(get_option('aip_widget_style', 'chat'), 'chat'); ?>>Chat Interface</option>
                        <option value="form" <?php selected(get_option('aip_widget_style'), 'form'); ?>>Form Interface</option>
                        <option value="both" <?php selected(get_option('aip_widget_style'), 'both'); ?>>Both (User Choice)</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="aip_pdf_style"><?php _e('PDF Style', 'ai-itinerary-plugin'); ?></label></th>
                <td>
                    <select name="aip_pdf_style" id="aip_pdf_style">
                        <option value="minimal" <?php selected(get_option('aip_pdf_style', 'modern'), 'minimal'); ?>>Minimal</option>
                        <option value="modern" <?php selected(get_option('aip_pdf_style', 'modern'), 'modern'); ?>>Modern</option>
                        <option value="luxury" <?php selected(get_option('aip_pdf_style'), 'luxury'); ?>>Luxury</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="aip_ai_tone"><?php _e('AI Tone', 'ai-itinerary-plugin'); ?></label></th>
                <td>
                    <select name="aip_ai_tone" id="aip_ai_tone">
                        <option value="friendly" <?php selected(get_option('aip_ai_tone', 'friendly'), 'friendly'); ?>>Friendly & Respectful</option>
                        <option value="professional" <?php selected(get_option('aip_ai_tone'), 'professional'); ?>>Professional</option>
                        <option value="casual" <?php selected(get_option('aip_ai_tone'), 'casual'); ?>>Casual & Fun</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="aip_bot_name"><?php _e('Bot Name', 'ai-itinerary-plugin'); ?></label></th>
                <td>
                    <input type="text" name="aip_bot_name" id="aip_bot_name" value="<?php echo esc_attr(get_option('aip_bot_name', 'Travel Buddy')); ?>" class="regular-text">
                    <p class="description"><?php _e('The name of your AI travel assistant (e.g., "Travel Buddy", "Journey Guide", etc.)', 'ai-itinerary-plugin'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Options', 'ai-itinerary-plugin'); ?></th>
                <td>
                    <label><input type="checkbox" name="aip_require_account" value="yes" <?php checked(get_option('aip_require_account', 'yes'), 'yes'); ?>> <?php _e('Require account before purchase', 'ai-itinerary-plugin'); ?></label><br>
                    <label><input type="checkbox" name="aip_save_itineraries" value="yes" <?php checked(get_option('aip_save_itineraries', 'yes'), 'yes'); ?>> <?php _e('Allow saving itineraries', 'ai-itinerary-plugin'); ?></label><br>
                    <label><input type="checkbox" name="aip_warn_before_close" value="yes" <?php checked(get_option('aip_warn_before_close', 'yes'), 'yes'); ?>> <?php _e('Warn before closing chat if unsaved', 'ai-itinerary-plugin'); ?></label>
                </td>
            </tr>
        </table>
        <?php submit_button(); ?>
        <?php
    }
    
    /**
     * Render payment settings
     */
    private function render_payment_settings() {
        settings_fields('aip_payment');
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="aip_payment_method"><?php _e('Payment Method', 'ai-itinerary-plugin'); ?></label></th>
                <td>
                    <select name="aip_payment_method" id="aip_payment_method">
                        <option value="stripe" <?php selected(get_option('aip_payment_method', 'stripe'), 'stripe'); ?>>Stripe</option>
                        <option value="paypal" <?php selected(get_option('aip_payment_method'), 'paypal'); ?>>PayPal</option>
                        <option value="both" <?php selected(get_option('aip_payment_method'), 'both'); ?>>Both</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="aip_currency"><?php _e('Currency', 'ai-itinerary-plugin'); ?></label></th>
                <td>
                    <select name="aip_currency" id="aip_currency">
                        <option value="USD" <?php selected(get_option('aip_currency', 'USD'), 'USD'); ?>>USD</option>
                        <option value="EUR" <?php selected(get_option('aip_currency'), 'EUR'); ?>>EUR</option>
                        <option value="GBP" <?php selected(get_option('aip_currency'), 'GBP'); ?>>GBP</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row" colspan="2"><h3><?php _e('Stripe Settings', 'ai-itinerary-plugin'); ?></h3></th>
            </tr>
            <tr>
                <th scope="row"><label for="aip_stripe_public_key"><?php _e('Stripe Publishable Key', 'ai-itinerary-plugin'); ?></label></th>
                <td><input type="text" name="aip_stripe_public_key" id="aip_stripe_public_key" value="<?php echo esc_attr(get_option('aip_stripe_public_key')); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="aip_stripe_secret_key"><?php _e('Stripe Secret Key', 'ai-itinerary-plugin'); ?></label></th>
                <td><input type="password" name="aip_stripe_secret_key" id="aip_stripe_secret_key" value="<?php echo esc_attr(get_option('aip_stripe_secret_key')); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row" colspan="2"><h3><?php _e('PayPal Settings', 'ai-itinerary-plugin'); ?></h3></th>
            </tr>
            <tr>
                <th scope="row"><label for="aip_paypal_client_id"><?php _e('PayPal Client ID', 'ai-itinerary-plugin'); ?></label></th>
                <td><input type="text" name="aip_paypal_client_id" id="aip_paypal_client_id" value="<?php echo esc_attr(get_option('aip_paypal_client_id')); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="aip_paypal_client_secret"><?php _e('PayPal Client Secret', 'ai-itinerary-plugin'); ?></label></th>
                <td><input type="password" name="aip_paypal_client_secret" id="aip_paypal_client_secret" value="<?php echo esc_attr(get_option('aip_paypal_client_secret')); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="aip_paypal_mode"><?php _e('PayPal Mode', 'ai-itinerary-plugin'); ?></label></th>
                <td>
                    <select name="aip_paypal_mode" id="aip_paypal_mode">
                        <option value="sandbox" <?php selected(get_option('aip_paypal_mode', 'sandbox'), 'sandbox'); ?>><?php _e('Sandbox (Test)', 'ai-itinerary-plugin'); ?></option>
                        <option value="production" <?php selected(get_option('aip_paypal_mode', 'sandbox'), 'production'); ?>><?php _e('Production (Live)', 'ai-itinerary-plugin'); ?></option>
                    </select>
                    <p class="description"><?php _e('Use Sandbox for testing, Production for live transactions', 'ai-itinerary-plugin'); ?></p>
                </td>
            </tr>
        </table>
        <?php submit_button(); ?>
        <?php
    }
    
    /**
     * Render affiliate settings (deprecated - kept for backward compatibility)
     */
    private function render_affiliate_settings() {
        ?>
        <div class="notice notice-info">
            <p><?php _e('Affiliate settings have been moved to a dedicated page for better management.', 'ai-itinerary-plugin'); ?></p>
            <p><a href="<?php echo admin_url('admin.php?page=aip-affiliate-providers'); ?>" class="button button-primary"><?php _e('Manage Affiliate Providers', 'ai-itinerary-plugin'); ?></a></p>
        </div>
        
        <table class="form-table">
            <tr>
                <th scope="row"><label for="aip_affiliate_button_style"><?php _e('Button Style', 'ai-itinerary-plugin'); ?></label></th>
                <td>
                    <?php settings_fields('aip_affiliate'); ?>
                    <select name="aip_affiliate_button_style" id="aip_affiliate_button_style">
                        <option value="hidden" <?php selected(get_option('aip_affiliate_button_style', 'hidden'), 'hidden'); ?>>Hidden Links</option>
                        <option value="visible" <?php selected(get_option('aip_affiliate_button_style'), 'visible'); ?>>Visible Buttons</option>
                    </select>
                    <p class="description"><?php _e('Choose how affiliate links appear in itineraries', 'ai-itinerary-plugin'); ?></p>
                </td>
            </tr>
        </table>
        <?php submit_button(); ?>
        <?php
    }
    
    /**
     * Render auth settings
     */
    private function render_auth_settings() {
        settings_fields('aip_auth');
        ?>
        <table class="form-table">
            <tr>
                <th scope="row" colspan="2">
                    <h3><?php _e('Google OAuth Settings', 'ai-itinerary-plugin'); ?></h3>
                    <p class="description"><?php _e('Enable Google Sign-In for users. Get credentials from Google Cloud Console.', 'ai-itinerary-plugin'); ?></p>
                </th>
            </tr>
            <tr>
                <th scope="row"><label for="aip_google_client_id"><?php _e('Google Client ID', 'ai-itinerary-plugin'); ?></label></th>
                <td><input type="text" name="aip_google_client_id" id="aip_google_client_id" value="<?php echo esc_attr(get_option('aip_google_client_id')); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="aip_google_client_secret"><?php _e('Google Client Secret', 'ai-itinerary-plugin'); ?></label></th>
                <td><input type="password" name="aip_google_client_secret" id="aip_google_client_secret" value="<?php echo esc_attr(get_option('aip_google_client_secret')); ?>" class="regular-text"></td>
            </tr>
        </table>
        <?php submit_button(); ?>
        <?php
    }
    
    /**
     * Render branding settings
     */
    private function render_branding_settings() {
        settings_fields('aip_branding');
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="aip_primary_color"><?php _e('Primary Color', 'ai-itinerary-plugin'); ?></label></th>
                <td><input type="color" name="aip_primary_color" id="aip_primary_color" value="<?php echo esc_attr(get_option('aip_primary_color', '#2271b1')); ?>"></td>
            </tr>
            <tr>
                <th scope="row"><label for="aip_secondary_color"><?php _e('Secondary Color', 'ai-itinerary-plugin'); ?></label></th>
                <td><input type="color" name="aip_secondary_color" id="aip_secondary_color" value="<?php echo esc_attr(get_option('aip_secondary_color', '#135e96')); ?>"></td>
            </tr>
            <tr>
                <th scope="row"><label for="aip_logo_url"><?php _e('Logo URL', 'ai-itinerary-plugin'); ?></label></th>
                <td><input type="url" name="aip_logo_url" id="aip_logo_url" value="<?php echo esc_attr(get_option('aip_logo_url')); ?>" class="regular-text"></td>
            </tr>
        </table>
        <?php submit_button(); ?>
        <?php
    }
    
    /**
     * Render analytics page
     */
    public function render_analytics() {
        $days = isset($_GET['days']) ? absint($_GET['days']) : 30;
        $analytics = AIP_Database::get_analytics($days);
        ?>
        <div class="wrap">
            <h1><?php _e('Analytics', 'ai-itinerary-plugin'); ?></h1>
            
            <form method="get">
                <input type="hidden" name="page" value="aip-analytics">
                <label for="days"><?php _e('Time Period:', 'ai-itinerary-plugin'); ?></label>
                <select name="days" id="days" onchange="this.form.submit()">
                    <option value="7" <?php selected($days, 7); ?>>Last 7 days</option>
                    <option value="30" <?php selected($days, 30); ?>>Last 30 days</option>
                    <option value="90" <?php selected($days, 90); ?>>Last 90 days</option>
                </select>
            </form>
            
            <div class="aip-dashboard-stats">
                <div class="aip-stat-box">
                    <h3><?php _e('Total Itineraries', 'ai-itinerary-plugin'); ?></h3>
                    <p class="aip-stat-number"><?php echo esc_html($analytics['total_itineraries']); ?></p>
                </div>
                
                <div class="aip-stat-box">
                    <h3><?php _e('Total Revenue', 'ai-itinerary-plugin'); ?></h3>
                    <p class="aip-stat-number">$<?php echo number_format($analytics['total_revenue'], 2); ?></p>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render affiliate providers management page
     */
    public function render_affiliate_providers() {
        // Handle form submission
        if (isset($_POST['aip_save_provider']) && check_admin_referer('aip_affiliate_provider_action', 'aip_affiliate_provider_nonce')) {
            $provider_data = array(
                'id' => isset($_POST['provider_id']) ? absint($_POST['provider_id']) : 0,
                'name' => sanitize_text_field($_POST['provider_name']),
                'slug' => sanitize_title($_POST['provider_slug']),
                'api_base_url' => esc_url_raw($_POST['api_base_url']),
                'affiliate_id' => sanitize_text_field($_POST['affiliate_id']),
                'link_template' => sanitize_text_field($_POST['link_template']),
                'category' => sanitize_text_field($_POST['category']),
                'label' => sanitize_text_field($_POST['label']),
                'icon' => sanitize_text_field($_POST['icon']),
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'sort_order' => absint($_POST['sort_order']),
            );
            
            $result = AIP_Database::save_affiliate_provider($provider_data);
            
            if ($result) {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Affiliate provider saved successfully!', 'ai-itinerary-plugin') . '</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . __('Failed to save affiliate provider.', 'ai-itinerary-plugin') . '</p></div>';
            }
        }
        
        // Handle delete
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['provider_id']) && check_admin_referer('delete_provider_' . $_GET['provider_id'])) {
            AIP_Database::delete_affiliate_provider(absint($_GET['provider_id']));
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Affiliate provider deleted.', 'ai-itinerary-plugin') . '</p></div>';
        }
        
        // Get all providers
        $providers = AIP_Database::get_all_affiliate_providers();
        $editing_provider = null;
        
        // Check if editing
        if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['provider_id'])) {
            $provider_id = absint($_GET['provider_id']);
            foreach ($providers as $provider) {
                if ($provider->id == $provider_id) {
                    $editing_provider = $provider;
                    break;
                }
            }
        }
        ?>
        <div class="wrap">
            <h1><?php _e('Affiliate Providers', 'ai-itinerary-plugin'); ?></h1>
            
            <div class="aip-affiliate-info">
                <p><?php _e('Manage your affiliate integrations. The system is platform-agnostic - you can add any affiliate provider by configuring the link template with placeholder variables.', 'ai-itinerary-plugin'); ?></p>
                <p><strong><?php _e('Available placeholders:', 'ai-itinerary-plugin'); ?></strong> <code>{affiliate_id}</code>, <code>{destination}</code>, <code>{destination_slug}</code>, <code>{check_in}</code>, <code>{check_out}</code>, <code>{destination_iata}</code>, <code>{origin}</code></p>
            </div>
            
            <hr>
            
            <h2><?php echo $editing_provider ? __('Edit Provider', 'ai-itinerary-plugin') : __('Add New Provider', 'ai-itinerary-plugin'); ?></h2>
            
            <form method="post" action="">
                <?php wp_nonce_field('aip_affiliate_provider_action', 'aip_affiliate_provider_nonce'); ?>
                
                <?php if ($editing_provider): ?>
                    <input type="hidden" name="provider_id" value="<?php echo esc_attr($editing_provider->id); ?>">
                <?php endif; ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="provider_name"><?php _e('Provider Name', 'ai-itinerary-plugin'); ?> *</label></th>
                        <td><input type="text" name="provider_name" id="provider_name" class="regular-text" value="<?php echo $editing_provider ? esc_attr($editing_provider->name) : ''; ?>" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="provider_slug"><?php _e('Slug', 'ai-itinerary-plugin'); ?> *</label></th>
                        <td>
                            <input type="text" name="provider_slug" id="provider_slug" class="regular-text" value="<?php echo $editing_provider ? esc_attr($editing_provider->slug) : ''; ?>" required>
                            <p class="description"><?php _e('Unique identifier (e.g., travelpayouts-hotels)', 'ai-itinerary-plugin'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="api_base_url"><?php _e('API Base URL', 'ai-itinerary-plugin'); ?></label></th>
                        <td>
                            <input type="url" name="api_base_url" id="api_base_url" class="regular-text" value="<?php echo $editing_provider ? esc_attr($editing_provider->api_base_url) : ''; ?>">
                            <p class="description"><?php _e('Optional: Base URL for API (if applicable)', 'ai-itinerary-plugin'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="affiliate_id"><?php _e('Affiliate/Tracking ID', 'ai-itinerary-plugin'); ?></label></th>
                        <td>
                            <input type="text" name="affiliate_id" id="affiliate_id" class="regular-text" value="<?php echo $editing_provider ? esc_attr($editing_provider->affiliate_id) : ''; ?>">
                            <p class="description"><?php _e('Your affiliate ID or tracking code', 'ai-itinerary-plugin'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="link_template"><?php _e('Link Template', 'ai-itinerary-plugin'); ?> *</label></th>
                        <td>
                            <textarea name="link_template" id="link_template" class="large-text" rows="3" required><?php echo $editing_provider ? esc_textarea($editing_provider->link_template) : ''; ?></textarea>
                            <p class="description"><?php _e('Use placeholders like {affiliate_id}, {destination}, etc.', 'ai-itinerary-plugin'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="category"><?php _e('Category', 'ai-itinerary-plugin'); ?> *</label></th>
                        <td>
                            <select name="category" id="category" required>
                                <option value="hotels" <?php echo $editing_provider && $editing_provider->category === 'hotels' ? 'selected' : ''; ?>><?php _e('Hotels', 'ai-itinerary-plugin'); ?></option>
                                <option value="flights" <?php echo $editing_provider && $editing_provider->category === 'flights' ? 'selected' : ''; ?>><?php _e('Flights', 'ai-itinerary-plugin'); ?></option>
                                <option value="cars" <?php echo $editing_provider && $editing_provider->category === 'cars' ? 'selected' : ''; ?>><?php _e('Car Rentals', 'ai-itinerary-plugin'); ?></option>
                                <option value="activities" <?php echo $editing_provider && $editing_provider->category === 'activities' ? 'selected' : ''; ?>><?php _e('Activities', 'ai-itinerary-plugin'); ?></option>
                                <option value="other" <?php echo $editing_provider && $editing_provider->category === 'other' ? 'selected' : ''; ?>><?php _e('Other', 'ai-itinerary-plugin'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="label"><?php _e('Button Label', 'ai-itinerary-plugin'); ?> *</label></th>
                        <td><input type="text" name="label" id="label" class="regular-text" value="<?php echo $editing_provider ? esc_attr($editing_provider->label) : ''; ?>" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="icon"><?php _e('Icon (emoji or text)', 'ai-itinerary-plugin'); ?></label></th>
                        <td><input type="text" name="icon" id="icon" class="regular-text" value="<?php echo $editing_provider ? esc_attr($editing_provider->icon) : '🔗'; ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sort_order"><?php _e('Sort Order', 'ai-itinerary-plugin'); ?></label></th>
                        <td><input type="number" name="sort_order" id="sort_order" value="<?php echo $editing_provider ? esc_attr($editing_provider->sort_order) : '100'; ?>" min="0"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Active', 'ai-itinerary-plugin'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="is_active" value="1" <?php echo $editing_provider && $editing_provider->is_active ? 'checked' : ''; ?>>
                                <?php _e('Enable this provider', 'ai-itinerary-plugin'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" name="aip_save_provider" class="button button-primary"><?php echo $editing_provider ? __('Update Provider', 'ai-itinerary-plugin') : __('Add Provider', 'ai-itinerary-plugin'); ?></button>
                    <?php if ($editing_provider): ?>
                        <a href="<?php echo admin_url('admin.php?page=aip-affiliate-providers'); ?>" class="button"><?php _e('Cancel', 'ai-itinerary-plugin'); ?></a>
                    <?php endif; ?>
                </p>
            </form>
            
            <hr>
            
            <h2><?php _e('Existing Providers', 'ai-itinerary-plugin'); ?></h2>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Name', 'ai-itinerary-plugin'); ?></th>
                        <th><?php _e('Slug', 'ai-itinerary-plugin'); ?></th>
                        <th><?php _e('Category', 'ai-itinerary-plugin'); ?></th>
                        <th><?php _e('Affiliate ID', 'ai-itinerary-plugin'); ?></th>
                        <th><?php _e('Status', 'ai-itinerary-plugin'); ?></th>
                        <th><?php _e('Sort', 'ai-itinerary-plugin'); ?></th>
                        <th><?php _e('Actions', 'ai-itinerary-plugin'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($providers)): ?>
                        <tr>
                            <td colspan="7"><?php _e('No affiliate providers found.', 'ai-itinerary-plugin'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($providers as $provider): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($provider->name); ?></strong>
                                    <?php if ($provider->icon): ?>
                                        <span><?php echo esc_html($provider->icon); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><code><?php echo esc_html($provider->slug); ?></code></td>
                                <td><?php echo esc_html(ucfirst($provider->category)); ?></td>
                                <td><?php echo $provider->affiliate_id ? '<span class="dashicons dashicons-yes-alt" style="color: green;"></span>' : '<span class="dashicons dashicons-warning" style="color: orange;"></span>'; ?></td>
                                <td><?php echo $provider->is_active ? '<span style="color: green;">' . __('Active', 'ai-itinerary-plugin') . '</span>' : '<span style="color: gray;">' . __('Inactive', 'ai-itinerary-plugin') . '</span>'; ?></td>
                                <td><?php echo esc_html($provider->sort_order); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=aip-affiliate-providers&action=edit&provider_id=' . $provider->id); ?>" class="button button-small"><?php _e('Edit', 'ai-itinerary-plugin'); ?></a>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=aip-affiliate-providers&action=delete&provider_id=' . $provider->id), 'delete_provider_' . $provider->id); ?>" class="button button-small" onclick="return confirm('<?php _e('Are you sure you want to delete this provider?', 'ai-itinerary-plugin'); ?>');"><?php _e('Delete', 'ai-itinerary-plugin'); ?></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * AJAX handler to save affiliate provider
     */
    public function ajax_save_affiliate_provider() {
        check_ajax_referer('aip_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'ai-itinerary-plugin')));
        }
        
        $provider_data = array(
            'id' => isset($_POST['id']) ? absint($_POST['id']) : 0,
            'name' => sanitize_text_field($_POST['name']),
            'slug' => sanitize_title($_POST['slug']),
            'api_base_url' => esc_url_raw($_POST['api_base_url']),
            'affiliate_id' => sanitize_text_field($_POST['affiliate_id']),
            'link_template' => sanitize_text_field($_POST['link_template']),
            'category' => sanitize_text_field($_POST['category']),
            'label' => sanitize_text_field($_POST['label']),
            'icon' => sanitize_text_field($_POST['icon']),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'sort_order' => absint($_POST['sort_order']),
        );
        
        $result = AIP_Database::save_affiliate_provider($provider_data);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Provider saved successfully', 'ai-itinerary-plugin')));
        } else {
            wp_send_json_error(array('message' => __('Failed to save provider', 'ai-itinerary-plugin')));
        }
    }
    
    /**
     * AJAX handler to delete affiliate provider
     */
    public function ajax_delete_affiliate_provider() {
        check_ajax_referer('aip_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'ai-itinerary-plugin')));
        }
        
        $provider_id = absint($_POST['provider_id']);
        $result = AIP_Database::delete_affiliate_provider($provider_id);
        
        if ($result) {
            wp_send_json_success(array('message' => __('Provider deleted successfully', 'ai-itinerary-plugin')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete provider', 'ai-itinerary-plugin')));
        }
    }
}

