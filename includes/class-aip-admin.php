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
        
        // Payment Settings
        register_setting('aip_payment', 'aip_payment_method');
        register_setting('aip_payment', 'aip_stripe_public_key');
        register_setting('aip_payment', 'aip_stripe_secret_key');
        register_setting('aip_payment', 'aip_paypal_client_id');
        register_setting('aip_payment', 'aip_paypal_client_secret');
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
        </table>
        <?php submit_button(); ?>
        <?php
    }
    
    /**
     * Render affiliate settings
     */
    private function render_affiliate_settings() {
        settings_fields('aip_affiliate');
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="aip_booking_affiliate_id"><?php _e('Booking.com Affiliate ID', 'ai-itinerary-plugin'); ?></label></th>
                <td><input type="text" name="aip_booking_affiliate_id" id="aip_booking_affiliate_id" value="<?php echo esc_attr(get_option('aip_booking_affiliate_id')); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="aip_skyscanner_affiliate_id"><?php _e('Skyscanner Affiliate ID', 'ai-itinerary-plugin'); ?></label></th>
                <td><input type="text" name="aip_skyscanner_affiliate_id" id="aip_skyscanner_affiliate_id" value="<?php echo esc_attr(get_option('aip_skyscanner_affiliate_id')); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="aip_getyourguide_affiliate_id"><?php _e('GetYourGuide Affiliate ID', 'ai-itinerary-plugin'); ?></label></th>
                <td><input type="text" name="aip_getyourguide_affiliate_id" id="aip_getyourguide_affiliate_id" value="<?php echo esc_attr(get_option('aip_getyourguide_affiliate_id')); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th scope="row"><label for="aip_affiliate_button_style"><?php _e('Button Style', 'ai-itinerary-plugin'); ?></label></th>
                <td>
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
}

