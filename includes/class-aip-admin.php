<?php
if (!defined('ABSPATH')) exit;

class AIP_Admin {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function add_menu() {
        add_menu_page(
            __('AI Itinerary', 'ai-itinerary'),
            __('AI Itinerary', 'ai-itinerary'),
            'manage_options',
            'aip-dashboard',
            [$this, 'render_dashboard'],
            'dashicons-airplane',
            30
        );

        add_submenu_page('aip-dashboard', __('Dashboard', 'ai-itinerary'), __('Dashboard', 'ai-itinerary'), 'manage_options', 'aip-dashboard', [$this, 'render_dashboard']);
        add_submenu_page('aip-dashboard', __('Settings', 'ai-itinerary'), __('Settings', 'ai-itinerary'), 'manage_options', 'aip-settings', [$this, 'render_settings']);
        add_submenu_page('aip-dashboard', __('Itineraries', 'ai-itinerary'), __('Itineraries', 'ai-itinerary'), 'manage_options', 'aip-itineraries', [$this, 'render_itineraries']);
    }

    public function register_settings() {
        // General
        register_setting('aip_settings', 'aip_claude_api_key');
        register_setting('aip_settings', 'aip_claude_model');
        register_setting('aip_settings', 'aip_bot_name');
        register_setting('aip_settings', 'aip_ai_tone');
        register_setting('aip_settings', 'aip_free_itinerary_limit');
        register_setting('aip_settings', 'aip_default_language');

        // Payment
        register_setting('aip_settings', 'aip_premium_price');
        register_setting('aip_settings', 'aip_pms_plan_id');

        // Affiliates
        register_setting('aip_settings', 'aip_travelpayouts_token');
        register_setting('aip_settings', 'aip_skyscanner_sid');
        register_setting('aip_settings', 'aip_skyscanner_auth_token');

        // Auth
        register_setting('aip_settings', 'aip_google_client_id');
        register_setting('aip_settings', 'aip_google_client_secret');

        // Branding
        register_setting('aip_settings', 'aip_primary_color');
        register_setting('aip_settings', 'aip_secondary_color');
        register_setting('aip_settings', 'aip_logo_url');
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'aip-') === false) return;

        wp_enqueue_style('aip-admin', AIP_PLUGIN_URL . 'assets/admin.css', [], AIP_VERSION);
        wp_enqueue_script('aip-chart', 'https://cdn.jsdelivr.net/npm/chart.js', [], '4.4.0', true);
    }

    // ============================================================
    // DASHBOARD PAGE
    // ============================================================

    public function render_dashboard() {
        $analytics = AIP_Database::get_analytics(30);
        ?>
        <div class="wrap">
            <h1><?php _e('AI Itinerary Dashboard', 'ai-itinerary'); ?></h1>

            <div class="aip-stats-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin: 20px 0;">
                <div class="aip-stat-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0; color: #666; font-size: 14px;"><?php _e('Total Itineraries (30d)', 'ai-itinerary'); ?></h3>
                    <p style="margin: 5px 0 0; font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo esc_html($analytics['total_itineraries']); ?></p>
                </div>
                <?php
                $free_count = 0;
                $premium_count = 0;
                foreach ($analytics['itinerary_types'] as $t) {
                    if ($t->type === 'free') $free_count = $t->count;
                    if ($t->type === 'premium') $premium_count = $t->count;
                }
                ?>
                <div class="aip-stat-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0; color: #666; font-size: 14px;"><?php _e('Free', 'ai-itinerary'); ?></h3>
                    <p style="margin: 5px 0 0; font-size: 32px; font-weight: bold; color: #50c878;"><?php echo esc_html($free_count); ?></p>
                </div>
                <div class="aip-stat-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0; color: #666; font-size: 14px;"><?php _e('Premium', 'ai-itinerary'); ?></h3>
                    <p style="margin: 5px 0 0; font-size: 32px; font-weight: bold; color: #ff9800;"><?php echo esc_html($premium_count); ?></p>
                </div>
                <div class="aip-stat-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h3 style="margin: 0; color: #666; font-size: 14px;"><?php _e('Affiliate Clicks', 'ai-itinerary'); ?></h3>
                    <p style="margin: 5px 0 0; font-size: 32px; font-weight: bold; color: #e91e63;">
                        <?php
                        $total_clicks = 0;
                        foreach ($analytics['affiliate_clicks'] as $c) $total_clicks += $c->count;
                        echo esc_html($total_clicks);
                        ?>
                    </p>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h3><?php _e('Itineraries per Day', 'ai-itinerary'); ?></h3>
                    <canvas id="aip-daily-chart" height="200"></canvas>
                </div>
                <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h3><?php _e('Popular Destinations', 'ai-itinerary'); ?></h3>
                    <canvas id="aip-destinations-chart" height="200"></canvas>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dailyData = <?php echo wp_json_encode($analytics['daily_stats']); ?>;
            const destData = <?php echo wp_json_encode($analytics['popular_destinations']); ?>;

            if (dailyData.length && document.getElementById('aip-daily-chart')) {
                new Chart(document.getElementById('aip-daily-chart'), {
                    type: 'line',
                    data: {
                        labels: dailyData.map(d => d.date),
                        datasets: [{
                            label: 'Itineraries',
                            data: dailyData.map(d => d.count),
                            borderColor: '#2271b1',
                            tension: 0.3,
                            fill: true,
                            backgroundColor: 'rgba(34,113,177,0.1)',
                        }]
                    },
                    options: { responsive: true, plugins: { legend: { display: false } } }
                });
            }

            if (destData.length && document.getElementById('aip-destinations-chart')) {
                new Chart(document.getElementById('aip-destinations-chart'), {
                    type: 'bar',
                    data: {
                        labels: destData.map(d => d.destination),
                        datasets: [{
                            label: 'Count',
                            data: destData.map(d => d.count),
                            backgroundColor: '#ff9800',
                        }]
                    },
                    options: { responsive: true, indexAxis: 'y', plugins: { legend: { display: false } } }
                });
            }
        });
        </script>
        <?php
    }

    // ============================================================
    // SETTINGS PAGE
    // ============================================================

    public function render_settings() {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        ?>
        <div class="wrap">
            <h1><?php _e('AI Itinerary Settings', 'ai-itinerary'); ?></h1>

            <h2 class="nav-tab-wrapper">
                <a href="?page=aip-settings&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>"><?php _e('General', 'ai-itinerary'); ?></a>
                <a href="?page=aip-settings&tab=payment" class="nav-tab <?php echo $active_tab === 'payment' ? 'nav-tab-active' : ''; ?>"><?php _e('Payment', 'ai-itinerary'); ?></a>
                <a href="?page=aip-settings&tab=affiliates" class="nav-tab <?php echo $active_tab === 'affiliates' ? 'nav-tab-active' : ''; ?>"><?php _e('Affiliates', 'ai-itinerary'); ?></a>
                <a href="?page=aip-settings&tab=auth" class="nav-tab <?php echo $active_tab === 'auth' ? 'nav-tab-active' : ''; ?>"><?php _e('Auth', 'ai-itinerary'); ?></a>
                <a href="?page=aip-settings&tab=branding" class="nav-tab <?php echo $active_tab === 'branding' ? 'nav-tab-active' : ''; ?>"><?php _e('Branding', 'ai-itinerary'); ?></a>
            </h2>

            <form method="post" action="options.php">
                <?php settings_fields('aip_settings'); ?>

                <?php if ($active_tab === 'general'): ?>
                <table class="form-table">
                    <tr>
                        <th><?php _e('Claude API Key', 'ai-itinerary'); ?></th>
                        <td><input type="password" name="aip_claude_api_key" value="<?php echo esc_attr(get_option('aip_claude_api_key')); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><?php _e('Claude Model', 'ai-itinerary'); ?></th>
                        <td>
                            <select name="aip_claude_model">
                                <option value="claude-haiku-4-5-20251001" <?php selected(get_option('aip_claude_model', 'claude-haiku-4-5-20251001'), 'claude-haiku-4-5-20251001'); ?>>Claude Haiku 4.5 (Cheapest)</option>
                                <option value="claude-sonnet-4-6" <?php selected(get_option('aip_claude_model'), 'claude-sonnet-4-6'); ?>>Claude Sonnet 4.6</option>
                                <option value="claude-opus-4-6" <?php selected(get_option('aip_claude_model'), 'claude-opus-4-6'); ?>>Claude Opus 4.6</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Bot Name', 'ai-itinerary'); ?></th>
                        <td><input type="text" name="aip_bot_name" value="<?php echo esc_attr(get_option('aip_bot_name', 'Travel Buddy')); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><?php _e('AI Tone', 'ai-itinerary'); ?></th>
                        <td>
                            <select name="aip_ai_tone">
                                <option value="friendly" <?php selected(get_option('aip_ai_tone', 'friendly'), 'friendly'); ?>><?php _e('Friendly', 'ai-itinerary'); ?></option>
                                <option value="professional" <?php selected(get_option('aip_ai_tone'), 'professional'); ?>><?php _e('Professional', 'ai-itinerary'); ?></option>
                                <option value="casual" <?php selected(get_option('aip_ai_tone'), 'casual'); ?>><?php _e('Casual', 'ai-itinerary'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Free Itinerary Limit (per month)', 'ai-itinerary'); ?></th>
                        <td><input type="number" name="aip_free_itinerary_limit" value="<?php echo esc_attr(get_option('aip_free_itinerary_limit', 3)); ?>" min="0" max="100" /></td>
                    </tr>
                    <tr>
                        <th><?php _e('Default Language', 'ai-itinerary'); ?></th>
                        <td>
                            <select name="aip_default_language">
                                <option value="en" <?php selected(get_option('aip_default_language', 'en'), 'en'); ?>>English</option>
                                <option value="es" <?php selected(get_option('aip_default_language'), 'es'); ?>>Spanish</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <?php elseif ($active_tab === 'payment'): ?>
                <table class="form-table">
                    <tr>
                        <th><?php _e('Premium Price ($)', 'ai-itinerary'); ?></th>
                        <td><input type="text" name="aip_premium_price" value="<?php echo esc_attr(get_option('aip_premium_price', '5.00')); ?>" class="small-text" /></td>
                    </tr>
                    <tr>
                        <th><?php _e('PMS Plan ID', 'ai-itinerary'); ?></th>
                        <td>
                            <input type="text" name="aip_pms_plan_id" value="<?php echo esc_attr(get_option('aip_pms_plan_id')); ?>" class="small-text" />
                            <p class="description"><?php _e('Paid Member Subscriptions plan ID for premium access.', 'ai-itinerary'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('WooCommerce Product', 'ai-itinerary'); ?></th>
                        <td>
                            <?php
                            $wc_id = get_option('aip_wc_product_id');
                            if ($wc_id) {
                                printf(__('Product ID: %d', 'ai-itinerary'), $wc_id);
                            } else {
                                _e('Not created yet. Deactivate and reactivate plugin.', 'ai-itinerary');
                            }
                            ?>
                        </td>
                    </tr>
                </table>

                <?php elseif ($active_tab === 'affiliates'): ?>
                <table class="form-table">
                    <tr>
                        <th><?php _e('Travelpayouts Token', 'ai-itinerary'); ?></th>
                        <td><input type="text" name="aip_travelpayouts_token" value="<?php echo esc_attr(get_option('aip_travelpayouts_token')); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><?php _e('Skyscanner Account SID', 'ai-itinerary'); ?></th>
                        <td><input type="text" name="aip_skyscanner_sid" value="<?php echo esc_attr(get_option('aip_skyscanner_sid')); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><?php _e('Skyscanner Auth Token', 'ai-itinerary'); ?></th>
                        <td><input type="password" name="aip_skyscanner_auth_token" value="<?php echo esc_attr(get_option('aip_skyscanner_auth_token')); ?>" class="regular-text" /></td>
                    </tr>
                </table>

                <?php elseif ($active_tab === 'auth'): ?>
                <table class="form-table">
                    <tr>
                        <th><?php _e('Google Client ID', 'ai-itinerary'); ?></th>
                        <td><input type="text" name="aip_google_client_id" value="<?php echo esc_attr(get_option('aip_google_client_id')); ?>" class="large-text" /></td>
                    </tr>
                    <tr>
                        <th><?php _e('Google Client Secret', 'ai-itinerary'); ?></th>
                        <td><input type="password" name="aip_google_client_secret" value="<?php echo esc_attr(get_option('aip_google_client_secret')); ?>" class="regular-text" /></td>
                    </tr>
                </table>

                <?php elseif ($active_tab === 'branding'): ?>
                <table class="form-table">
                    <tr>
                        <th><?php _e('Primary Color', 'ai-itinerary'); ?></th>
                        <td><input type="color" name="aip_primary_color" value="<?php echo esc_attr(get_option('aip_primary_color', '#2271b1')); ?>" /></td>
                    </tr>
                    <tr>
                        <th><?php _e('Secondary Color', 'ai-itinerary'); ?></th>
                        <td><input type="color" name="aip_secondary_color" value="<?php echo esc_attr(get_option('aip_secondary_color', '#135e96')); ?>" /></td>
                    </tr>
                    <tr>
                        <th><?php _e('Logo URL', 'ai-itinerary'); ?></th>
                        <td><input type="url" name="aip_logo_url" value="<?php echo esc_attr(get_option('aip_logo_url')); ?>" class="large-text" /></td>
                    </tr>
                </table>
                <?php endif; ?>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    // ============================================================
    // ITINERARIES LIST PAGE
    // ============================================================

    public function render_itineraries() {
        global $wpdb;
        $itineraries = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}aip_itineraries ORDER BY created_at DESC LIMIT 100");
        ?>
        <div class="wrap">
            <h1><?php _e('All Itineraries', 'ai-itinerary'); ?></h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('ID', 'ai-itinerary'); ?></th>
                        <th><?php _e('Title', 'ai-itinerary'); ?></th>
                        <th><?php _e('Destination', 'ai-itinerary'); ?></th>
                        <th><?php _e('Days', 'ai-itinerary'); ?></th>
                        <th><?php _e('Type', 'ai-itinerary'); ?></th>
                        <th><?php _e('Status', 'ai-itinerary'); ?></th>
                        <th><?php _e('User', 'ai-itinerary'); ?></th>
                        <th><?php _e('Created', 'ai-itinerary'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($itineraries)): ?>
                        <tr><td colspan="8"><?php _e('No itineraries yet.', 'ai-itinerary'); ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($itineraries as $item): ?>
                        <tr>
                            <td><?php echo esc_html($item->id); ?></td>
                            <td><?php echo esc_html($item->title); ?></td>
                            <td><?php echo esc_html($item->destination); ?></td>
                            <td><?php echo esc_html($item->days); ?></td>
                            <td><span class="aip-badge aip-badge-<?php echo esc_attr($item->type); ?>"><?php echo esc_html(ucfirst($item->type)); ?></span></td>
                            <td><?php echo esc_html(ucfirst($item->status)); ?></td>
                            <td>
                                <?php
                                if ($item->user_id) {
                                    $user = get_user_by('id', $item->user_id);
                                    echo $user ? esc_html($user->display_name) : __('Deleted User', 'ai-itinerary');
                                } else {
                                    _e('Guest', 'ai-itinerary');
                                }
                                ?>
                            </td>
                            <td><?php echo esc_html($item->created_at); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
