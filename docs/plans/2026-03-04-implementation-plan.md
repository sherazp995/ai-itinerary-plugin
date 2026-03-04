# AI Itinerary Plugin v2 — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Rebuild the AI Travel Itinerary Generator as a React + Claude-powered chat widget integrated with WooCommerce, Paid Member Subscriptions, and Travelpayouts/Skyscanner.

**Architecture:** React 18 frontend (Vite build) communicating with WordPress REST API endpoints. Claude API for conversation + itinerary generation with SSE streaming. DOMPDF for PDF export. WooCommerce for payments. All deployed as a single WordPress plugin.

**Tech Stack:** React 18, Zustand, Vite, PHP 8.4, WordPress REST API, Anthropic Claude API, DOMPDF, WooCommerce, Paid Member Subscriptions, Travelpayouts, Skyscanner.

**Design Doc:** `docs/plans/2026-03-04-plugin-redesign-design.md`

**Dev Environment:**
- Local WordPress: http://localhost
- Plugin path: `/var/www/html/wordpress/wp-content/plugins/ai-itinerary-plugin/`
- Node: v24.8.0, npm: 11.6.0, PHP: 8.4.17, WP-CLI: 2.4.0
- Composer: needs install

---

## Phase 1: Foundation (Tasks 1-3)

### Task 1: Install Composer + DOMPDF dependency

**Files:**
- Create: `composer.json`
- Create: `.gitignore`

**Step 1: Install Composer globally**

```bash
cd /var/www/html/wordpress/wp-content/plugins/ai-itinerary-plugin
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
php -r "unlink('composer-setup.php');"
composer --version
```

Expected: `Composer version 2.x.x`

**Step 2: Create composer.json**

```json
{
    "name": "yoiner/ai-itinerary-plugin",
    "description": "AI Travel Itinerary Generator - WordPress Plugin",
    "type": "wordpress-plugin",
    "require": {
        "php": ">=7.4",
        "dompdf/dompdf": "^2.0"
    },
    "autoload": {
        "classmap": ["includes/"]
    }
}
```

**Step 3: Install dependencies**

```bash
cd /var/www/html/wordpress/wp-content/plugins/ai-itinerary-plugin
composer install --no-dev
```

Expected: `dompdf/dompdf` installed in `vendor/`

**Step 4: Create .gitignore**

```
node_modules/
vendor/
frontend/node_modules/
.env
*.log
```

**Step 5: Verify DOMPDF loads**

```bash
cd /var/www/html/wordpress/wp-content/plugins/ai-itinerary-plugin
php -r "require 'vendor/autoload.php'; echo 'DOMPDF OK: ' . \Dompdf\Dompdf::class . PHP_EOL;"
```

Expected: `DOMPDF OK: Dompdf\Dompdf`

**Step 6: Commit**

```bash
git init
git add composer.json composer.lock .gitignore vendor/
git commit -m "feat: initialize composer with DOMPDF dependency"
```

---

### Task 2: Scaffold main plugin file + constants

**Files:**
- Rewrite: `ai-itinerary-plugin.php`

**Step 1: Rewrite main plugin file**

Replace the entire `ai-itinerary-plugin.php` with the v2 bootstrap. This file:
- Defines constants (version, paths, URLs)
- Loads Composer autoloader
- Loads all `includes/` class files
- Initializes the plugin singleton
- Registers activation/deactivation hooks

```php
<?php
/**
 * Plugin Name: AI Travel Itinerary Generator
 * Plugin URI: https://yoiner.gamercity.io
 * Description: AI-powered travel itinerary generator with chat interface, PDF export, and affiliate integration.
 * Version: 2.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Yoiner
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
```

**Step 2: Create stub class files so PHP doesn't fatal**

Create minimal stub for each class in `includes/`. Each stub:

```php
<?php
// includes/class-aip-{name}.php
if (!defined('ABSPATH')) exit;

class AIP_{Name} {
    private static $instance = null;
    public static function get_instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }
    private function __construct() {}
}
```

Create stubs for: `AIP_Database`, `AIP_REST_API`, `AIP_Claude`, `AIP_Frontend`, `AIP_Admin`, `AIP_PDF`, `AIP_WooCommerce`, `AIP_Membership`, `AIP_Travelpayouts`, `AIP_Skyscanner`.

For `AIP_Database` and `AIP_WooCommerce`, also add static method stubs:

```php
// In AIP_Database
public static function create_tables() {}

// In AIP_WooCommerce
public static function create_products() {}
```

**Step 3: Verify plugin loads without errors**

```bash
wp plugin list --path=/var/www/html/wordpress --status=active | grep ai-itinerary
```

If deactivated, activate:
```bash
wp plugin activate ai-itinerary-plugin --path=/var/www/html/wordpress
```

Then visit http://localhost/wp-admin/plugins.php — plugin should show "AI Travel Itinerary Generator" v2.0.0 with no errors.

**Step 4: Commit**

```bash
git add ai-itinerary-plugin.php includes/
git commit -m "feat: scaffold v2 plugin with stub classes"
```

---

### Task 3: Database class — create tables on activation

**Files:**
- Rewrite: `includes/class-aip-database.php`

**Step 1: Implement the full database class**

```php
<?php
if (!defined('ABSPATH')) exit;

class AIP_Database {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {}

    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = [];

        $sql[] = "CREATE TABLE {$wpdb->prefix}aip_itineraries (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            title VARCHAR(255) NOT NULL,
            destination VARCHAR(255) NOT NULL,
            days INT NOT NULL,
            type ENUM('free','premium') DEFAULT 'free',
            language VARCHAR(10) DEFAULT 'en',
            data LONGTEXT NOT NULL,
            wc_order_id BIGINT UNSIGNED DEFAULT NULL,
            status ENUM('generating','completed','failed') DEFAULT 'generating',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_destination (destination),
            INDEX idx_status (status)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE {$wpdb->prefix}aip_conversations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            session_id VARCHAR(64) DEFAULT NULL,
            messages LONGTEXT NOT NULL,
            collected_data TEXT DEFAULT NULL,
            ready_to_generate TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_session (session_id)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE {$wpdb->prefix}aip_analytics (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_type VARCHAR(50) NOT NULL,
            event_data TEXT DEFAULT NULL,
            user_id BIGINT UNSIGNED DEFAULT 0,
            itinerary_id BIGINT UNSIGNED DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_event_type (event_type),
            INDEX idx_created (created_at),
            INDEX idx_user (user_id)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE {$wpdb->prefix}aip_affiliate_clicks (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED DEFAULT 0,
            itinerary_id BIGINT UNSIGNED DEFAULT NULL,
            provider VARCHAR(50) NOT NULL,
            category VARCHAR(50) NOT NULL,
            destination VARCHAR(255) NOT NULL,
            link_url TEXT NOT NULL,
            clicked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_provider (provider),
            INDEX idx_destination (destination),
            INDEX idx_clicked (clicked_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ($sql as $query) {
            dbDelta($query);
        }

        update_option('aip_db_version', AIP_VERSION);
    }

    // --- Itinerary CRUD ---

    public static function save_itinerary($data) {
        global $wpdb;
        $wpdb->insert("{$wpdb->prefix}aip_itineraries", [
            'user_id'     => $data['user_id'] ?? 0,
            'title'       => $data['title'],
            'destination' => $data['destination'],
            'days'        => $data['days'],
            'type'        => $data['type'] ?? 'free',
            'language'    => $data['language'] ?? 'en',
            'data'        => is_string($data['data']) ? $data['data'] : wp_json_encode($data['data']),
            'wc_order_id' => $data['wc_order_id'] ?? null,
            'status'      => $data['status'] ?? 'generating',
        ]);
        return $wpdb->insert_id;
    }

    public static function update_itinerary($id, $data) {
        global $wpdb;
        return $wpdb->update("{$wpdb->prefix}aip_itineraries", $data, ['id' => $id]);
    }

    public static function get_itinerary($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aip_itineraries WHERE id = %d", $id
        ));
    }

    public static function get_user_itineraries($user_id, $limit = 20) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aip_itineraries WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
            $user_id, $limit
        ));
    }

    // --- Conversation State ---

    public static function get_conversation($user_id, $session_id = null) {
        global $wpdb;
        if ($user_id > 0) {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aip_conversations WHERE user_id = %d ORDER BY updated_at DESC LIMIT 1",
                $user_id
            ));
        }
        if ($session_id) {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aip_conversations WHERE session_id = %s ORDER BY updated_at DESC LIMIT 1",
                $session_id
            ));
        }
        return null;
    }

    public static function save_conversation($user_id, $session_id, $messages, $collected_data, $ready) {
        global $wpdb;
        $table = "{$wpdb->prefix}aip_conversations";
        $existing = self::get_conversation($user_id, $session_id);

        $row = [
            'user_id'           => $user_id,
            'session_id'        => $session_id,
            'messages'          => wp_json_encode($messages),
            'collected_data'    => wp_json_encode($collected_data),
            'ready_to_generate' => $ready ? 1 : 0,
        ];

        if ($existing) {
            return $wpdb->update($table, $row, ['id' => $existing->id]);
        }
        $wpdb->insert($table, $row);
        return $wpdb->insert_id;
    }

    public static function clear_conversation($user_id, $session_id = null) {
        global $wpdb;
        if ($user_id > 0) {
            return $wpdb->delete("{$wpdb->prefix}aip_conversations", ['user_id' => $user_id]);
        }
        if ($session_id) {
            return $wpdb->delete("{$wpdb->prefix}aip_conversations", ['session_id' => $session_id]);
        }
    }

    // --- Analytics ---

    public static function log_event($event_type, $event_data = [], $user_id = 0, $itinerary_id = null) {
        global $wpdb;
        $wpdb->insert("{$wpdb->prefix}aip_analytics", [
            'event_type'   => $event_type,
            'event_data'   => wp_json_encode($event_data),
            'user_id'      => $user_id,
            'itinerary_id' => $itinerary_id,
        ]);
    }

    public static function get_analytics($days = 30) {
        global $wpdb;
        $since = date('Y-m-d', strtotime("-{$days} days"));

        $total_itineraries = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aip_itineraries WHERE created_at >= %s", $since
        ));

        $itinerary_types = $wpdb->get_results($wpdb->prepare(
            "SELECT type, COUNT(*) as count FROM {$wpdb->prefix}aip_itineraries WHERE created_at >= %s GROUP BY type",
            $since
        ));

        $daily_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as count FROM {$wpdb->prefix}aip_itineraries WHERE created_at >= %s GROUP BY DATE(created_at) ORDER BY date",
            $since
        ));

        $popular_destinations = $wpdb->get_results($wpdb->prepare(
            "SELECT destination, COUNT(*) as count FROM {$wpdb->prefix}aip_itineraries WHERE created_at >= %s GROUP BY destination ORDER BY count DESC LIMIT 10",
            $since
        ));

        $affiliate_clicks = $wpdb->get_results($wpdb->prepare(
            "SELECT provider, COUNT(*) as count FROM {$wpdb->prefix}aip_affiliate_clicks WHERE clicked_at >= %s GROUP BY provider",
            $since
        ));

        return [
            'total_itineraries'   => (int) $total_itineraries,
            'itinerary_types'     => $itinerary_types,
            'daily_stats'         => $daily_stats,
            'popular_destinations' => $popular_destinations,
            'affiliate_clicks'    => $affiliate_clicks,
        ];
    }

    // --- Affiliate Clicks ---

    public static function log_affiliate_click($data) {
        global $wpdb;
        $wpdb->insert("{$wpdb->prefix}aip_affiliate_clicks", [
            'user_id'      => $data['user_id'] ?? 0,
            'itinerary_id' => $data['itinerary_id'] ?? null,
            'provider'     => $data['provider'],
            'category'     => $data['category'],
            'destination'  => $data['destination'],
            'link_url'     => $data['link_url'],
        ]);
    }

    // --- User Free Count (uses wp_usermeta) ---

    public static function get_user_free_count($user_id) {
        $reset_date = get_user_meta($user_id, 'aip_free_count_reset', true);
        $first_of_month = date('Y-m-01');

        if ($reset_date !== $first_of_month) {
            update_user_meta($user_id, 'aip_free_count', 0);
            update_user_meta($user_id, 'aip_free_count_reset', $first_of_month);
            return 0;
        }

        return (int) get_user_meta($user_id, 'aip_free_count', true);
    }

    public static function increment_user_free_count($user_id) {
        $count = self::get_user_free_count($user_id);
        update_user_meta($user_id, 'aip_free_count', $count + 1);
    }

    // --- Guest Free Count (uses WP transients) ---

    public static function get_guest_session_id() {
        if (isset($_COOKIE['aip_session'])) {
            return sanitize_text_field($_COOKIE['aip_session']);
        }
        $session_id = wp_generate_uuid4();
        setcookie('aip_session', $session_id, time() + (30 * DAY_IN_SECONDS), '/');
        return $session_id;
    }

    public static function get_guest_free_count($session_id) {
        return (int) get_transient('aip_guest_count_' . $session_id);
    }

    public static function increment_guest_free_count($session_id) {
        $count = self::get_guest_free_count($session_id);
        set_transient('aip_guest_count_' . $session_id, $count + 1, 30 * DAY_IN_SECONDS);
    }
}
```

**Step 2: Deactivate and reactivate plugin to trigger table creation**

```bash
wp plugin deactivate ai-itinerary-plugin --path=/var/www/html/wordpress
wp plugin activate ai-itinerary-plugin --path=/var/www/html/wordpress
```

**Step 3: Verify tables exist**

```bash
mysql -u wordpress -p'wp-test-proj' wordpress -e "SHOW TABLES LIKE 'wp_aip_%';"
```

Expected: 4 tables: `wp_aip_itineraries`, `wp_aip_conversations`, `wp_aip_analytics`, `wp_aip_affiliate_clicks`

**Step 4: Commit**

```bash
git add includes/class-aip-database.php
git commit -m "feat: database class with tables, CRUD, analytics, guest sessions"
```

---

## Phase 2: Backend Core (Tasks 4-8)

### Task 4: Claude API client class

**Files:**
- Rewrite: `includes/class-aip-claude.php`

**Step 1: Implement Claude client with streaming support**

The class handles:
- Non-streaming calls (for itinerary generation fallback)
- SSE streaming relay (PHP reads Claude stream, echos to browser)
- System prompt construction based on conversation state
- Structured data extraction from Claude's tool_use

```php
<?php
if (!defined('ABSPATH')) exit;

class AIP_Claude {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {}

    private static function get_api_key() {
        return get_option('aip_claude_api_key', '');
    }

    private static function get_model() {
        return get_option('aip_claude_model', 'claude-sonnet-4-6');
    }

    /**
     * Send a non-streaming message to Claude
     */
    public static function send_message($messages, $system_prompt = '', $max_tokens = 1024) {
        $api_key = self::get_api_key();
        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('Claude API key not configured', 'ai-itinerary'));
        }

        $body = [
            'model'      => self::get_model(),
            'max_tokens' => $max_tokens,
            'messages'   => $messages,
        ];

        if (!empty($system_prompt)) {
            $body['system'] = $system_prompt;
        }

        $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'timeout' => 120,
            'headers' => [
                'x-api-key'         => $api_key,
                'anthropic-version'  => '2023-06-01',
                'content-type'       => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($status !== 200) {
            $error_msg = $data['error']['message'] ?? 'Unknown API error';
            return new WP_Error('claude_api_error', $error_msg);
        }

        return $data;
    }

    /**
     * Stream a response from Claude via SSE
     * This function outputs directly to the browser and exits.
     */
    public static function stream_message($messages, $system_prompt = '', $max_tokens = 4096) {
        $api_key = self::get_api_key();
        if (empty($api_key)) {
            echo "data: " . wp_json_encode(['error' => 'API key not configured']) . "\n\n";
            return;
        }

        // Set SSE headers
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        // Disable output buffering
        while (ob_get_level()) {
            ob_end_clean();
        }

        $body = wp_json_encode([
            'model'      => self::get_model(),
            'max_tokens' => $max_tokens,
            'stream'     => true,
            'system'     => $system_prompt,
            'messages'   => $messages,
        ]);

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'x-api-key: ' . $api_key,
                'anthropic-version: 2023-06-01',
                'content-type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_WRITEFUNCTION  => function($ch, $data) {
                echo $data;
                if (ob_get_level()) ob_flush();
                flush();
                return strlen($data);
            },
        ]);

        curl_exec($ch);

        if (curl_errno($ch)) {
            echo "data: " . wp_json_encode(['error' => curl_error($ch)]) . "\n\n";
        }

        curl_close($ch);
    }

    /**
     * Build conversation system prompt based on collected data
     */
    public static function build_conversation_prompt($collected_data = []) {
        $bot_name = get_option('aip_bot_name', 'Travel Buddy');
        $tone = get_option('aip_ai_tone', 'friendly');

        $tone_instructions = [
            'friendly'     => 'Fun, friendly, always respectful. Use occasional emojis.',
            'professional' => 'Professional, courteous, and expert-level advice.',
            'casual'       => 'Casual and fun, like chatting with a well-traveled friend.',
        ];

        $tone_text = $tone_instructions[$tone] ?? $tone_instructions['friendly'];

        $required_fields = ['destination', 'days', 'trip_type', 'budget', 'interests', 'pace'];
        $collected = array_filter($collected_data);
        $missing = array_diff($required_fields, array_keys($collected));

        $prompt = "You are {$bot_name}, an AI travel assistant.\n\n";
        $prompt .= "TONE: {$tone_text}\n\n";
        $prompt .= "RULES:\n";
        $prompt .= "- Ask ONE question at a time. Wait for the answer.\n";
        $prompt .= "- Acknowledge each answer briefly before asking the next question.\n";
        $prompt .= "- Keep responses to 2-3 sentences max.\n";
        $prompt .= "- Do NOT mention payment, free, or premium until ALL questions are answered.\n";
        $prompt .= "- Do NOT generate any itinerary content during the conversation.\n\n";

        if (empty($collected)) {
            $prompt .= "CURRENT STEP: Ask where they want to go. Nothing else.\n";
        } elseif (!empty($missing)) {
            $prompt .= "COLLECTED SO FAR:\n";
            foreach ($collected as $key => $value) {
                $prompt .= "- " . ucfirst(str_replace('_', ' ', $key)) . ": {$value}\n";
            }
            $prompt .= "\nSTILL NEED: " . implode(', ', $missing) . "\n";
            $prompt .= "Ask about the NEXT missing item only.\n";
        } else {
            $prompt .= "ALL INFORMATION COLLECTED:\n";
            foreach ($collected as $key => $value) {
                $prompt .= "- " . ucfirst(str_replace('_', ' ', $key)) . ": {$value}\n";
            }
            $prompt .= "\nSummarize their trip details and say you're ready to generate.\n";
            $prompt .= "Do NOT generate the itinerary yet. Just confirm the details.\n";
        }

        $prompt .= "\nREQUIRED QUESTIONS (in order):\n";
        $prompt .= "1. Destination (country/city)\n";
        $prompt .= "2. Number of days\n";
        $prompt .= "3. Trip type (leisure, adventure, family, business, honeymoon)\n";
        $prompt .= "4. Budget range (low, medium, high)\n";
        $prompt .= "5. Interests (specific places, activities, food, culture, etc.)\n";
        $prompt .= "6. Pace (relaxed, balanced, packed)\n";

        return $prompt;
    }

    /**
     * Build itinerary generation prompt
     */
    public static function build_generation_prompt($data, $type = 'free') {
        $detail = $type === 'premium' ? 'very detailed and comprehensive' : 'concise but informative';

        $prompt = "Generate a {$detail} {$data['days']}-day travel itinerary for {$data['destination']}.\n\n";
        $prompt .= "Trip details:\n";
        $prompt .= "- Type: {$data['trip_type']}\n";
        $prompt .= "- Budget: {$data['budget']}\n";
        $prompt .= "- Interests: {$data['interests']}\n";
        $prompt .= "- Pace: {$data['pace']}\n";

        if ($type === 'premium') {
            $prompt .= "\nInclude for EACH day:\n";
            $prompt .= "- Specific hotels with price ranges and area\n";
            $prompt .= "- Restaurant recommendations for breakfast, lunch, dinner\n";
            $prompt .= "- Activities with exact times, durations, and cost estimates\n";
            $prompt .= "- Location coordinates (lat/lng) for each activity\n";
            $prompt .= "- Transportation tips between locations\n";
            $prompt .= "\nAlso include: budget summary, packing suggestions, best time to visit, local tips.\n";
        }

        $prompt .= "\nReturn ONLY valid JSON in this exact format:\n";
        $prompt .= '{"destination":"city, country","days":N,"summary":"2-3 sentence overview",';
        $prompt .= '"itinerary":[{"day":1,"title":"Day title","activities":[{"time":"09:00","period":"morning",';
        $prompt .= '"title":"Activity","description":"Details","location":"Place","coordinates":{"lat":0,"lng":0},';
        $prompt .= '"duration":"2h","cost_estimate":"$20-30"}],"meals":{"breakfast":{"name":"Restaurant",';
        $prompt .= '"cuisine":"Type","price_range":"$"},"lunch":{...},"dinner":{...}},';
        $prompt .= '"accommodation":{"name":"Hotel","price_range":"$$","area":"District"}}],';
        $prompt .= '"tips":["tip1"],"budget_summary":{"total_estimate":"$500-800",';
        $prompt .= '"breakdown":{"accommodation":"$X","food":"$X","activities":"$X","transport":"$X"}},';
        $prompt .= '"best_time_to_visit":"Month-Month","packing_suggestions":["item1"]}';

        return $prompt;
    }

    /**
     * Extract structured travel data from conversation using Claude
     */
    public static function extract_travel_data($messages) {
        $system = "Extract travel planning information from this conversation. Return ONLY a JSON object with these fields (use null for unknown): destination, days (number), trip_type, budget, interests, pace. Nothing else.";

        $result = self::send_message($messages, $system, 256);

        if (is_wp_error($result)) {
            return [];
        }

        $text = $result['content'][0]['text'] ?? '';
        $data = json_decode($text, true);

        if (!is_array($data)) {
            return [];
        }

        // Filter out null values
        return array_filter($data, fn($v) => $v !== null && $v !== '');
    }
}
```

**Step 2: Verify no PHP errors**

```bash
php -l /var/www/html/wordpress/wp-content/plugins/ai-itinerary-plugin/includes/class-aip-claude.php
```

Expected: `No syntax errors detected`

**Step 3: Commit**

```bash
git add includes/class-aip-claude.php
git commit -m "feat: Claude API client with streaming, prompts, data extraction"
```

---

### Task 5: REST API endpoints

**Files:**
- Rewrite: `includes/class-aip-rest-api.php`

**Step 1: Implement all REST routes**

This class registers all `/wp-json/aip/v1/` endpoints and delegates to the appropriate handler classes.

```php
<?php
if (!defined('ABSPATH')) exit;

class AIP_REST_API {

    private static $instance = null;
    const NAMESPACE = 'aip/v1';

    public static function get_instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {

        // --- Chat ---
        register_rest_route(self::NAMESPACE, '/chat/message', [
            'methods'  => 'POST',
            'callback' => [$this, 'chat_message'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/chat/reset', [
            'methods'  => 'POST',
            'callback' => [$this, 'chat_reset'],
            'permission_callback' => '__return_true',
        ]);

        // --- Itinerary ---
        register_rest_route(self::NAMESPACE, '/itinerary/generate', [
            'methods'  => 'POST',
            'callback' => [$this, 'itinerary_generate'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/itinerary/(?P<id>\d+)', [
            'methods'  => 'GET',
            'callback' => [$this, 'itinerary_get'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/itineraries', [
            'methods'  => 'GET',
            'callback' => [$this, 'itineraries_list'],
            'permission_callback' => function() { return is_user_logged_in(); },
        ]);

        register_rest_route(self::NAMESPACE, '/itinerary/(?P<id>\d+)/save', [
            'methods'  => 'POST',
            'callback' => [$this, 'itinerary_save'],
            'permission_callback' => function() { return is_user_logged_in(); },
        ]);

        // --- PDF ---
        register_rest_route(self::NAMESPACE, '/pdf/generate', [
            'methods'  => 'POST',
            'callback' => [$this, 'pdf_generate'],
            'permission_callback' => '__return_true',
        ]);

        // --- User ---
        register_rest_route(self::NAMESPACE, '/user/status', [
            'methods'  => 'GET',
            'callback' => [$this, 'user_status'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/auth/login', [
            'methods'  => 'POST',
            'callback' => [$this, 'auth_login'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/auth/register', [
            'methods'  => 'POST',
            'callback' => [$this, 'auth_register'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/auth/google', [
            'methods'  => 'POST',
            'callback' => [$this, 'auth_google'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/auth/logout', [
            'methods'  => 'POST',
            'callback' => [$this, 'auth_logout'],
            'permission_callback' => function() { return is_user_logged_in(); },
        ]);

        // --- Affiliate ---
        register_rest_route(self::NAMESPACE, '/affiliate/(?P<destination>[a-zA-Z0-9\-\s,]+)', [
            'methods'  => 'GET',
            'callback' => [$this, 'affiliate_links'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/affiliate/click', [
            'methods'  => 'POST',
            'callback' => [$this, 'affiliate_click'],
            'permission_callback' => '__return_true',
        ]);

        // --- Admin ---
        register_rest_route(self::NAMESPACE, '/admin/analytics', [
            'methods'  => 'GET',
            'callback' => [$this, 'admin_analytics'],
            'permission_callback' => function() { return current_user_can('manage_options'); },
        ]);
    }

    // ============================================================
    // CHAT ENDPOINTS
    // ============================================================

    public function chat_message(WP_REST_Request $request) {
        $message = sanitize_text_field($request->get_param('message'));
        if (empty($message)) {
            return new WP_Error('empty_message', __('Message cannot be empty', 'ai-itinerary'), ['status' => 400]);
        }

        $user_id = get_current_user_id();
        $session_id = $user_id ? null : AIP_Database::get_guest_session_id();

        // Get conversation state
        $convo = AIP_Database::get_conversation($user_id, $session_id);
        $messages = $convo ? json_decode($convo->messages, true) : [];
        $collected = $convo ? json_decode($convo->collected_data, true) : [];

        if (!is_array($messages)) $messages = [];
        if (!is_array($collected)) $collected = [];

        // Add user message
        $messages[] = ['role' => 'user', 'content' => $message];

        // Build system prompt
        $system_prompt = AIP_Claude::build_conversation_prompt($collected);

        // Get Claude response (non-streaming for chat — streaming for generation only)
        $result = AIP_Claude::send_message($messages, $system_prompt, 500);

        if (is_wp_error($result)) {
            return new WP_Error('ai_error', $result->get_error_message(), ['status' => 500]);
        }

        $bot_message = $result['content'][0]['text'] ?? '';

        // Add bot message to history
        $messages[] = ['role' => 'assistant', 'content' => $bot_message];

        // Extract data from conversation using Claude
        $new_data = AIP_Claude::extract_travel_data($messages);
        $collected = array_merge($collected, $new_data);

        // Check if all required fields collected
        $required = ['destination', 'days', 'trip_type', 'budget', 'interests', 'pace'];
        $missing = [];
        foreach ($required as $field) {
            if (empty($collected[$field])) $missing[] = $field;
        }
        $ready = empty($missing);

        // Save conversation state
        AIP_Database::save_conversation($user_id, $session_id, $messages, $collected, $ready);

        return rest_ensure_response([
            'bot_message'    => $bot_message,
            'collected_data' => $collected,
            'ready'          => $ready,
            'missing'        => $missing,
        ]);
    }

    public function chat_reset(WP_REST_Request $request) {
        $user_id = get_current_user_id();
        $session_id = $user_id ? null : AIP_Database::get_guest_session_id();

        AIP_Database::clear_conversation($user_id, $session_id);

        $bot_name = get_option('aip_bot_name', 'Travel Buddy');

        return rest_ensure_response([
            'bot_message' => sprintf(
                __("Hi! I'm %s, your AI travel assistant. Where would you like to go?", 'ai-itinerary'),
                $bot_name
            ),
            'collected_data' => [],
            'ready' => false,
        ]);
    }

    // ============================================================
    // ITINERARY ENDPOINTS
    // ============================================================

    public function itinerary_generate(WP_REST_Request $request) {
        $user_id = get_current_user_id();
        $session_id = $user_id ? null : AIP_Database::get_guest_session_id();
        $type = sanitize_text_field($request->get_param('type') ?? 'free');

        // Get conversation data
        $convo = AIP_Database::get_conversation($user_id, $session_id);
        if (!$convo) {
            return new WP_Error('no_conversation', __('No conversation found. Please start a chat first.', 'ai-itinerary'), ['status' => 400]);
        }

        $collected = json_decode($convo->collected_data, true);
        if (empty($collected['destination'])) {
            return new WP_Error('incomplete', __('Please complete the conversation first.', 'ai-itinerary'), ['status' => 400]);
        }

        // Check free limit
        if ($type === 'free') {
            $limit = (int) get_option('aip_free_itinerary_limit', 3);
            $used = $user_id
                ? AIP_Database::get_user_free_count($user_id)
                : AIP_Database::get_guest_free_count($session_id);

            if ($used >= $limit) {
                return new WP_Error('limit_reached', __('Free itinerary limit reached. Try premium!', 'ai-itinerary'), ['status' => 403]);
            }
        }

        // Check premium access
        if ($type === 'premium') {
            if (!$user_id) {
                return new WP_Error('auth_required', __('Please log in for premium itineraries.', 'ai-itinerary'), ['status' => 401]);
            }
            $has_premium = AIP_Membership::user_has_premium($user_id);
            if (!$has_premium) {
                // Return checkout URL instead of generating
                $checkout_url = AIP_WooCommerce::get_checkout_url($collected);
                return rest_ensure_response([
                    'needs_payment' => true,
                    'checkout_url'  => $checkout_url,
                ]);
            }
        }

        // Create itinerary record
        $itinerary_id = AIP_Database::save_itinerary([
            'user_id'     => $user_id,
            'title'       => sprintf('%s - %d Days', $collected['destination'], $collected['days']),
            'destination' => $collected['destination'],
            'days'        => (int) $collected['days'],
            'type'        => $type,
            'language'    => get_option('aip_default_language', 'en'),
            'data'        => '{}',
            'status'      => 'generating',
        ]);

        // Generate with Claude
        $prompt = AIP_Claude::build_generation_prompt($collected, $type);
        $messages = [['role' => 'user', 'content' => $prompt]];
        $system = "You are a travel itinerary generator. Return ONLY valid JSON. No markdown, no explanation.";

        $result = AIP_Claude::send_message($messages, $system, $type === 'premium' ? 8192 : 4096);

        if (is_wp_error($result)) {
            AIP_Database::update_itinerary($itinerary_id, ['status' => 'failed']);
            return $result;
        }

        $content = $result['content'][0]['text'] ?? '';
        $itinerary_data = json_decode($content, true);

        if (!$itinerary_data) {
            // Try to extract JSON from markdown code block
            if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $matches)) {
                $itinerary_data = json_decode($matches[1], true);
            }
        }

        if (!$itinerary_data) {
            AIP_Database::update_itinerary($itinerary_id, ['status' => 'failed']);
            return new WP_Error('parse_error', __('Failed to parse itinerary. Please try again.', 'ai-itinerary'), ['status' => 500]);
        }

        // Save completed itinerary
        AIP_Database::update_itinerary($itinerary_id, [
            'data'   => wp_json_encode($itinerary_data),
            'status' => 'completed',
        ]);

        // Increment free count
        if ($type === 'free') {
            if ($user_id) {
                AIP_Database::increment_user_free_count($user_id);
            } else {
                AIP_Database::increment_guest_free_count($session_id);
            }
        }

        // Log analytics
        AIP_Database::log_event('itinerary_generated', [
            'type'        => $type,
            'destination' => $collected['destination'],
            'days'        => $collected['days'],
        ], $user_id, $itinerary_id);

        // Clear conversation
        AIP_Database::clear_conversation($user_id, $session_id);

        // Get affiliate links
        $affiliates = AIP_Travelpayouts::get_links($collected['destination']);

        return rest_ensure_response([
            'itinerary_id'   => $itinerary_id,
            'itinerary'      => $itinerary_data,
            'type'           => $type,
            'affiliate_links' => $affiliates,
        ]);
    }

    public function itinerary_get(WP_REST_Request $request) {
        $id = (int) $request->get_param('id');
        $itinerary = AIP_Database::get_itinerary($id);

        if (!$itinerary) {
            return new WP_Error('not_found', __('Itinerary not found', 'ai-itinerary'), ['status' => 404]);
        }

        $itinerary->data = json_decode($itinerary->data, true);
        return rest_ensure_response($itinerary);
    }

    public function itineraries_list(WP_REST_Request $request) {
        $user_id = get_current_user_id();
        $itineraries = AIP_Database::get_user_itineraries($user_id);

        foreach ($itineraries as &$item) {
            $item->data = json_decode($item->data, true);
        }

        return rest_ensure_response($itineraries);
    }

    public function itinerary_save(WP_REST_Request $request) {
        $id = (int) $request->get_param('id');
        $title = sanitize_text_field($request->get_param('title'));

        $itinerary = AIP_Database::get_itinerary($id);
        if (!$itinerary || $itinerary->user_id != get_current_user_id()) {
            return new WP_Error('forbidden', __('Not your itinerary', 'ai-itinerary'), ['status' => 403]);
        }

        if ($title) {
            AIP_Database::update_itinerary($id, ['title' => $title]);
        }

        return rest_ensure_response(['saved' => true]);
    }

    // ============================================================
    // PDF ENDPOINT
    // ============================================================

    public function pdf_generate(WP_REST_Request $request) {
        $itinerary_id = (int) $request->get_param('itinerary_id');
        $itinerary = AIP_Database::get_itinerary($itinerary_id);

        if (!$itinerary) {
            return new WP_Error('not_found', __('Itinerary not found', 'ai-itinerary'), ['status' => 404]);
        }

        $data = json_decode($itinerary->data, true);
        $pdf_content = AIP_PDF::generate($data, $itinerary->type);

        AIP_Database::log_event('pdf_downloaded', [
            'itinerary_id' => $itinerary_id,
            'type'         => $itinerary->type,
        ], get_current_user_id(), $itinerary_id);

        // Return PDF as download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="itinerary-' . $itinerary_id . '.pdf"');
        echo $pdf_content;
        exit;
    }

    // ============================================================
    // AUTH ENDPOINTS
    // ============================================================

    public function user_status(WP_REST_Request $request) {
        $user_id = get_current_user_id();
        $session_id = $user_id ? null : AIP_Database::get_guest_session_id();
        $limit = (int) get_option('aip_free_itinerary_limit', 3);

        if ($user_id) {
            $user = wp_get_current_user();
            $used = AIP_Database::get_user_free_count($user_id);
            $has_premium = AIP_Membership::user_has_premium($user_id);

            return rest_ensure_response([
                'logged_in'    => true,
                'user_id'      => $user_id,
                'email'        => $user->user_email,
                'first_name'   => $user->first_name,
                'last_name'    => $user->last_name,
                'display_name' => $user->display_name,
                'has_premium'  => $has_premium,
                'free_limit'   => $limit,
                'free_used'    => $used,
                'free_remaining' => max(0, $limit - $used),
            ]);
        }

        $used = AIP_Database::get_guest_free_count($session_id);
        return rest_ensure_response([
            'logged_in'      => false,
            'has_premium'    => false,
            'free_limit'     => $limit,
            'free_used'      => $used,
            'free_remaining' => max(0, $limit - $used),
        ]);
    }

    public function auth_login(WP_REST_Request $request) {
        $email = sanitize_email($request->get_param('email'));
        $password = $request->get_param('password');

        if (empty($email) || empty($password)) {
            return new WP_Error('missing_fields', __('Email and password are required', 'ai-itinerary'), ['status' => 400]);
        }

        $user = wp_authenticate($email, $password);
        if (is_wp_error($user)) {
            return new WP_Error('auth_failed', __('Invalid email or password', 'ai-itinerary'), ['status' => 401]);
        }

        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);

        return rest_ensure_response([
            'success'  => true,
            'user_id'  => $user->ID,
            'name'     => $user->display_name,
        ]);
    }

    public function auth_register(WP_REST_Request $request) {
        $first_name = sanitize_text_field($request->get_param('first_name'));
        $last_name = sanitize_text_field($request->get_param('last_name'));
        $email = sanitize_email($request->get_param('email'));
        $password = $request->get_param('password');

        if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
            return new WP_Error('missing_fields', __('All fields are required', 'ai-itinerary'), ['status' => 400]);
        }

        if (strlen($password) < 6) {
            return new WP_Error('weak_password', __('Password must be at least 6 characters', 'ai-itinerary'), ['status' => 400]);
        }

        if (email_exists($email)) {
            return new WP_Error('email_exists', __('An account with this email already exists', 'ai-itinerary'), ['status' => 409]);
        }

        $user_id = wp_create_user($email, $password, $email);
        if (is_wp_error($user_id)) {
            return $user_id;
        }

        wp_update_user([
            'ID'           => $user_id,
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'display_name' => $first_name . ' ' . $last_name,
        ]);

        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);

        AIP_Database::log_event('user_registered', ['method' => 'email'], $user_id);

        return rest_ensure_response([
            'success' => true,
            'user_id' => $user_id,
            'name'    => $first_name . ' ' . $last_name,
        ]);
    }

    public function auth_google(WP_REST_Request $request) {
        $token = sanitize_text_field($request->get_param('credential'));
        if (empty($token)) {
            return new WP_Error('no_token', __('Google credential missing', 'ai-itinerary'), ['status' => 400]);
        }

        // Verify Google token
        $google_response = wp_remote_get('https://oauth2.googleapis.com/tokeninfo?id_token=' . $token);
        if (is_wp_error($google_response)) {
            return new WP_Error('google_error', __('Google verification failed', 'ai-itinerary'), ['status' => 500]);
        }

        $google_data = json_decode(wp_remote_retrieve_body($google_response), true);
        $client_id = get_option('aip_google_client_id');

        if (($google_data['aud'] ?? '') !== $client_id) {
            return new WP_Error('invalid_token', __('Invalid Google token', 'ai-itinerary'), ['status' => 401]);
        }

        $email = sanitize_email($google_data['email'] ?? '');
        $first_name = sanitize_text_field($google_data['given_name'] ?? '');
        $last_name = sanitize_text_field($google_data['family_name'] ?? '');

        if (empty($email)) {
            return new WP_Error('no_email', __('Could not get email from Google', 'ai-itinerary'), ['status' => 400]);
        }

        // Find or create user
        $user = get_user_by('email', $email);
        if (!$user) {
            $user_id = wp_create_user($email, wp_generate_password(), $email);
            if (is_wp_error($user_id)) return $user_id;

            wp_update_user([
                'ID'           => $user_id,
                'first_name'   => $first_name,
                'last_name'    => $last_name,
                'display_name' => trim("$first_name $last_name"),
            ]);

            update_user_meta($user_id, 'aip_google_id', $google_data['sub'] ?? '');
            AIP_Database::log_event('user_registered', ['method' => 'google'], $user_id);
        } else {
            $user_id = $user->ID;
        }

        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);

        return rest_ensure_response([
            'success' => true,
            'user_id' => $user_id,
            'name'    => trim("$first_name $last_name"),
        ]);
    }

    public function auth_logout(WP_REST_Request $request) {
        wp_logout();
        return rest_ensure_response(['success' => true]);
    }

    // ============================================================
    // AFFILIATE ENDPOINTS
    // ============================================================

    public function affiliate_links(WP_REST_Request $request) {
        $destination = sanitize_text_field($request->get_param('destination'));
        $links = AIP_Travelpayouts::get_links($destination);
        return rest_ensure_response($links);
    }

    public function affiliate_click(WP_REST_Request $request) {
        AIP_Database::log_affiliate_click([
            'user_id'      => get_current_user_id(),
            'itinerary_id' => (int) $request->get_param('itinerary_id'),
            'provider'     => sanitize_text_field($request->get_param('provider')),
            'category'     => sanitize_text_field($request->get_param('category')),
            'destination'  => sanitize_text_field($request->get_param('destination')),
            'link_url'     => esc_url_raw($request->get_param('url')),
        ]);
        return rest_ensure_response(['tracked' => true]);
    }

    // ============================================================
    // ADMIN ENDPOINTS
    // ============================================================

    public function admin_analytics(WP_REST_Request $request) {
        $days = (int) ($request->get_param('days') ?? 30);
        return rest_ensure_response(AIP_Database::get_analytics($days));
    }
}
```

**Step 2: Verify REST routes register**

```bash
wp rest discover --path=/var/www/html/wordpress 2>/dev/null | grep aip || echo "check manually"
curl -s http://localhost/wp-json/aip/v1/user/status | python3 -m json.tool
```

Expected: JSON response with `logged_in: false`, `free_remaining: 3`

**Step 3: Commit**

```bash
git add includes/class-aip-rest-api.php
git commit -m "feat: full REST API with chat, itinerary, auth, affiliate, admin endpoints"
```

---

### Task 6: WooCommerce integration class

**Files:**
- Rewrite: `includes/class-aip-woocommerce.php`

**Step 1: Implement WooCommerce product creation + checkout flow**

```php
<?php
if (!defined('ABSPATH')) exit;

class AIP_WooCommerce {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('woocommerce_order_status_completed', [$this, 'handle_order_complete']);
        add_action('woocommerce_order_status_processing', [$this, 'handle_order_complete']);
    }

    public static function create_products() {
        if (!class_exists('WooCommerce')) return;

        // Create single itinerary product if not exists
        $product_id = get_option('aip_wc_product_id');
        if (!$product_id || !wc_get_product($product_id)) {
            $product = new WC_Product_Simple();
            $product->set_name(__('Premium Travel Itinerary', 'ai-itinerary'));
            $product->set_description(__('AI-generated detailed travel itinerary with hotels, restaurants, activities, and PDF export.', 'ai-itinerary'));
            $product->set_regular_price(get_option('aip_premium_price', '5.00'));
            $product->set_virtual(true);
            $product->set_catalog_visibility('hidden');
            $product->set_status('publish');
            $product->save();

            update_option('aip_wc_product_id', $product->get_id());
        }
    }

    public static function get_checkout_url($trip_data) {
        if (!class_exists('WooCommerce')) return home_url();

        $product_id = get_option('aip_wc_product_id');
        if (!$product_id) return home_url();

        // Clear cart and add our product
        WC()->cart->empty_cart();
        $cart_item_key = WC()->cart->add_to_cart($product_id, 1, 0, [], [
            'aip_trip_data' => $trip_data,
        ]);

        return wc_get_checkout_url();
    }

    public function handle_order_complete($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        $product_id = get_option('aip_wc_product_id');

        foreach ($order->get_items() as $item) {
            if ($item->get_product_id() == $product_id) {
                $trip_data = $item->get_meta('aip_trip_data');
                if (empty($trip_data)) continue;

                $user_id = $order->get_user_id();

                // Generate premium itinerary
                $prompt = AIP_Claude::build_generation_prompt($trip_data, 'premium');
                $messages = [['role' => 'user', 'content' => $prompt]];
                $system = "You are a travel itinerary generator. Return ONLY valid JSON. No markdown.";

                $result = AIP_Claude::send_message($messages, $system, 8192);

                $itinerary_data = [];
                if (!is_wp_error($result)) {
                    $content = $result['content'][0]['text'] ?? '';
                    $itinerary_data = json_decode($content, true) ?: [];
                }

                $itinerary_id = AIP_Database::save_itinerary([
                    'user_id'     => $user_id,
                    'title'       => sprintf('%s - %d Days (Premium)', $trip_data['destination'], $trip_data['days']),
                    'destination' => $trip_data['destination'],
                    'days'        => (int) $trip_data['days'],
                    'type'        => 'premium',
                    'data'        => $itinerary_data,
                    'wc_order_id' => $order_id,
                    'status'      => !empty($itinerary_data) ? 'completed' : 'failed',
                ]);

                AIP_Database::log_event('premium_purchase', [
                    'order_id' => $order_id,
                    'amount'   => $order->get_total(),
                ], $user_id, $itinerary_id);

                // Store itinerary ID in order meta for redirect
                $order->update_meta_data('aip_itinerary_id', $itinerary_id);
                $order->save();
            }
        }
    }
}
```

**Step 2: Commit**

```bash
git add includes/class-aip-woocommerce.php
git commit -m "feat: WooCommerce integration - product creation, checkout, order hooks"
```

---

### Task 7: Membership + Travelpayouts + Skyscanner + PDF classes

**Files:**
- Rewrite: `includes/class-aip-membership.php`
- Rewrite: `includes/class-aip-travelpayouts.php`
- Rewrite: `includes/class-aip-skyscanner.php`
- Rewrite: `includes/class-aip-pdf.php`

**Step 1: Membership class**

```php
<?php
if (!defined('ABSPATH')) exit;

class AIP_Membership {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {}

    public static function user_has_premium($user_id) {
        if (!$user_id) return false;

        // Check Paid Member Subscriptions
        if (function_exists('pms_is_member_of_plan')) {
            $plan_id = get_option('aip_pms_plan_id', '');
            if ($plan_id && pms_is_member_of_plan($user_id, (int) $plan_id)) {
                return true;
            }
            // Check by plan name fallback
            if (pms_is_member_of_plan($user_id, 'premium') || pms_is_member_of_plan($user_id, 'Premium')) {
                return true;
            }
        }

        // Check user meta fallback (manual premium grant)
        if (get_user_meta($user_id, 'aip_premium_access', true) === 'yes') {
            return true;
        }

        return false;
    }
}
```

**Step 2: Travelpayouts class**

```php
<?php
if (!defined('ABSPATH')) exit;

class AIP_Travelpayouts {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {}

    public static function get_links($destination) {
        $token = get_option('aip_travelpayouts_token', '');
        if (empty($token)) return [];

        $dest_slug = sanitize_title($destination);
        $dest_encoded = urlencode($destination);

        $links = [];

        // Hotels via Hotellook
        $links[] = [
            'provider' => 'travelpayouts',
            'category' => 'hotels',
            'label'    => __('Book Hotels', 'ai-itinerary'),
            'icon'     => 'hotel',
            'url'      => "https://search.hotellook.com/?marker={$token}&destination={$dest_encoded}",
        ];

        // Flights via Aviasales
        $links[] = [
            'provider' => 'travelpayouts',
            'category' => 'flights',
            'label'    => __('Find Flights', 'ai-itinerary'),
            'icon'     => 'flight',
            'url'      => "https://www.aviasales.com/?marker={$token}&destination={$dest_encoded}",
        ];

        // Activities
        $links[] = [
            'provider' => 'travelpayouts',
            'category' => 'activities',
            'label'    => __('Book Activities', 'ai-itinerary'),
            'icon'     => 'activity',
            'url'      => "https://www.getyourguide.com/s/?partner_id={$token}&q={$dest_encoded}",
        ];

        return $links;
    }
}
```

**Step 3: Skyscanner class**

```php
<?php
if (!defined('ABSPATH')) exit;

class AIP_Skyscanner {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {}

    public static function search_flights($destination, $date = '') {
        $sid = get_option('aip_skyscanner_sid', '');
        $auth = get_option('aip_skyscanner_auth_token', '');

        if (empty($sid) || empty($auth)) return [];

        // Skyscanner redirect link (no API call needed for affiliate)
        $dest_encoded = urlencode($destination);
        $date_param = $date ? "&odate={$date}" : '';

        return [
            'provider' => 'skyscanner',
            'category' => 'flights',
            'label'    => __('Compare Flights on Skyscanner', 'ai-itinerary'),
            'icon'     => 'flight_compare',
            'url'      => "https://www.skyscanner.com/transport/flights/?q={$dest_encoded}{$date_param}",
        ];
    }
}
```

**Step 4: PDF class with DOMPDF**

```php
<?php
if (!defined('ABSPATH')) exit;

use Dompdf\Dompdf;
use Dompdf\Options;

class AIP_PDF {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {}

    public static function generate($itinerary_data, $type = 'free') {
        $style = get_option('aip_pdf_style', 'modern');
        $primary_color = get_option('aip_primary_color', '#2271b1');
        $bot_name = get_option('aip_bot_name', 'Travel Buddy');
        $logo_url = get_option('aip_logo_url', '');

        // Build HTML
        $html = self::build_html($itinerary_data, $type, [
            'style'         => $style,
            'primary_color' => $primary_color,
            'bot_name'      => $bot_name,
            'logo_url'      => $logo_url,
        ]);

        // Generate PDF
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private static function build_html($data, $type, $config) {
        $destination = esc_html($data['destination'] ?? 'Travel Itinerary');
        $days = (int) ($data['days'] ?? 0);
        $summary = esc_html($data['summary'] ?? '');
        $primary = esc_attr($config['primary_color']);
        $bot_name = esc_html($config['bot_name']);

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8">';
        $html .= '<style>';
        $html .= "body { font-family: Helvetica, Arial, sans-serif; color: #333; margin: 0; padding: 30px; }";
        $html .= ".header { background: {$primary}; color: white; padding: 30px; text-align: center; margin: -30px -30px 30px; }";
        $html .= ".header h1 { margin: 0; font-size: 28px; }";
        $html .= ".header p { margin: 5px 0 0; opacity: 0.9; }";
        $html .= ".summary { background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 20px; }";
        $html .= ".day { margin-bottom: 25px; page-break-inside: avoid; }";
        $html .= ".day-header { background: {$primary}; color: white; padding: 10px 15px; font-size: 16px; font-weight: bold; }";
        $html .= ".activity { padding: 8px 15px; border-bottom: 1px solid #eee; }";
        $html .= ".activity .time { color: {$primary}; font-weight: bold; display: inline-block; width: 70px; }";
        $html .= ".meal { padding: 8px 15px; background: #fff8e1; }";
        $html .= ".hotel { padding: 10px 15px; background: #e3f2fd; }";
        $html .= ".tips { background: #f1f8e9; padding: 15px; margin-top: 20px; }";
        $html .= ".tips h3 { margin-top: 0; }";
        $html .= ".budget { background: #fce4ec; padding: 15px; margin-top: 15px; }";
        $html .= ".footer { text-align: center; margin-top: 30px; color: #999; font-size: 12px; }";
        $html .= '</style></head><body>';

        // Header
        $html .= "<div class='header'>";
        $html .= "<h1>{$destination}</h1>";
        $html .= "<p>{$days}-Day Itinerary by {$bot_name}</p>";
        $html .= "</div>";

        // Summary
        if ($summary) {
            $html .= "<div class='summary'><p>{$summary}</p></div>";
        }

        // Days
        $itinerary = $data['itinerary'] ?? [];
        foreach ($itinerary as $day) {
            $day_num = (int) ($day['day'] ?? 0);
            $day_title = esc_html($day['title'] ?? "Day {$day_num}");

            $html .= "<div class='day'>";
            $html .= "<div class='day-header'>Day {$day_num}: {$day_title}</div>";

            // Activities
            foreach (($day['activities'] ?? []) as $activity) {
                $time = esc_html($activity['time'] ?? '');
                $title = esc_html($activity['title'] ?? '');
                $desc = esc_html($activity['description'] ?? '');
                $cost = esc_html($activity['cost_estimate'] ?? '');

                $html .= "<div class='activity'>";
                $html .= "<span class='time'>{$time}</span> <strong>{$title}</strong>";
                if ($desc) $html .= " — {$desc}";
                if ($cost) $html .= " <em>({$cost})</em>";
                $html .= "</div>";
            }

            // Meals
            $meals = $day['meals'] ?? [];
            foreach (['breakfast', 'lunch', 'dinner'] as $meal_type) {
                if (!empty($meals[$meal_type])) {
                    $meal = $meals[$meal_type];
                    $name = esc_html(is_array($meal) ? ($meal['name'] ?? '') : $meal);
                    $cuisine = esc_html(is_array($meal) ? ($meal['cuisine'] ?? '') : '');
                    $price = esc_html(is_array($meal) ? ($meal['price_range'] ?? '') : '');

                    $html .= "<div class='meal'>";
                    $html .= ucfirst($meal_type) . ": <strong>{$name}</strong>";
                    if ($cuisine) $html .= " ({$cuisine})";
                    if ($price) $html .= " — {$price}";
                    $html .= "</div>";
                }
            }

            // Accommodation
            $hotel = $day['accommodation'] ?? null;
            if ($hotel && $type === 'premium') {
                $hotel_name = esc_html(is_array($hotel) ? ($hotel['name'] ?? '') : $hotel);
                $hotel_area = esc_html(is_array($hotel) ? ($hotel['area'] ?? '') : '');
                $hotel_price = esc_html(is_array($hotel) ? ($hotel['price_range'] ?? '') : '');

                $html .= "<div class='hotel'>";
                $html .= "Stay: <strong>{$hotel_name}</strong>";
                if ($hotel_area) $html .= " ({$hotel_area})";
                if ($hotel_price) $html .= " — {$hotel_price}";
                $html .= "</div>";
            }

            $html .= "</div>";
        }

        // Tips
        $tips = $data['tips'] ?? [];
        if (!empty($tips)) {
            $html .= "<div class='tips'><h3>Travel Tips</h3><ul>";
            foreach ($tips as $tip) {
                $html .= "<li>" . esc_html($tip) . "</li>";
            }
            $html .= "</ul></div>";
        }

        // Budget (premium only)
        if ($type === 'premium' && !empty($data['budget_summary'])) {
            $budget = $data['budget_summary'];
            $html .= "<div class='budget'><h3>Budget Summary</h3>";
            $html .= "<p><strong>Estimated Total: " . esc_html($budget['total_estimate'] ?? '') . "</strong></p>";
            if (!empty($budget['breakdown'])) {
                $html .= "<ul>";
                foreach ($budget['breakdown'] as $cat => $amount) {
                    $html .= "<li>" . ucfirst(esc_html($cat)) . ": " . esc_html($amount) . "</li>";
                }
                $html .= "</ul>";
            }
            $html .= "</div>";
        }

        // Footer
        $html .= "<div class='footer'>";
        $html .= "<p>Generated by {$bot_name} | yoiner.gamercity.io</p>";
        $html .= "</div>";

        $html .= '</body></html>';
        return $html;
    }
}
```

**Step 5: Verify all files parse**

```bash
for f in includes/class-aip-membership.php includes/class-aip-travelpayouts.php includes/class-aip-skyscanner.php includes/class-aip-pdf.php; do
    php -l /var/www/html/wordpress/wp-content/plugins/ai-itinerary-plugin/$f
done
```

Expected: `No syntax errors detected` for all 4 files

**Step 6: Commit**

```bash
git add includes/class-aip-membership.php includes/class-aip-travelpayouts.php includes/class-aip-skyscanner.php includes/class-aip-pdf.php
git commit -m "feat: membership check, travelpayouts links, skyscanner, PDF generation"
```

---

### Task 8: Admin panel + Frontend enqueue

**Files:**
- Rewrite: `includes/class-aip-admin.php`
- Rewrite: `includes/class-aip-frontend.php`

**Step 1: Implement admin settings page**

This is the largest file. It renders the admin menu (Dashboard, Settings with tabs, Analytics, Itineraries). Refer to design doc Section 11 for all settings fields.

The admin class should register all settings fields listed in the design doc:
- General: Claude API key, model, bot name, tone, free limit, PDF style
- Payment: WC product ID (auto), premium price, PMS plan ID
- Affiliates: Travelpayouts token, Skyscanner SID + auth, link style
- Auth: Google client ID + secret
- Branding: Primary/secondary color, logo URL

Include the dashboard with Chart.js charts (itineraries/day, revenue/day).

**Step 2: Implement frontend enqueue class**

```php
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
            'api_url'        => rest_url('aip/v1'),
            'nonce'          => wp_create_nonce('wp_rest'),
            'is_logged_in'   => is_user_logged_in(),
            'user_id'        => get_current_user_id(),
            'bot_name'       => get_option('aip_bot_name', 'Travel Buddy'),
            'primary_color'  => get_option('aip_primary_color', '#2271b1'),
            'secondary_color' => get_option('aip_secondary_color', '#135e96'),
            'free_limit'     => (int) get_option('aip_free_itinerary_limit', 3),
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
```

**Step 3: Commit**

```bash
git add includes/class-aip-admin.php includes/class-aip-frontend.php
git commit -m "feat: admin panel with settings/dashboard, frontend enqueue + shortcode"
```

---

## Phase 3: React Frontend (Tasks 9-16)

### Task 9: Scaffold React + Vite project

**Files:**
- Create: `frontend/package.json`
- Create: `frontend/vite.config.js`
- Create: `frontend/index.html`
- Create: `frontend/src/main.jsx`
- Create: `frontend/src/App.jsx`

**Step 1: Initialize npm project**

```bash
mkdir -p /var/www/html/wordpress/wp-content/plugins/ai-itinerary-plugin/frontend
cd /var/www/html/wordpress/wp-content/plugins/ai-itinerary-plugin/frontend
npm init -y
npm install react react-dom zustand
npm install -D @vitejs/plugin-react vite
```

**Step 2: Create vite.config.js**

```js
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'path';

export default defineConfig({
  plugins: [react()],
  build: {
    outDir: resolve(__dirname, '../assets/dist'),
    emptyOutDir: true,
    rollupOptions: {
      input: resolve(__dirname, 'src/main.jsx'),
      output: {
        entryFileNames: 'aip-widget.js',
        assetFileNames: 'aip-widget.[ext]',
      },
    },
  },
  server: {
    port: 3100,
    cors: true,
  },
});
```

**Step 3: Create entry files**

`frontend/src/main.jsx`:
```jsx
import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import './styles/global.css';

// Mount on widget container
const widgetEl = document.getElementById('aip-widget');
if (widgetEl) {
  createRoot(widgetEl).render(<App mode="widget" />);
}

// Mount on fullpage container
const fullpageEl = document.getElementById('aip-fullpage');
if (fullpageEl) {
  createRoot(fullpageEl).render(<App mode="fullpage" />);
}
```

`frontend/src/App.jsx`:
```jsx
import React from 'react';
import { WidgetTrigger } from './components/Widget/WidgetTrigger';
import { WidgetPanel } from './components/Widget/WidgetPanel';
import { useAppStore } from './stores/appStore';

export default function App({ mode }) {
  const { isOpen, setIsOpen } = useAppStore();

  if (mode === 'fullpage') {
    return <WidgetPanel mode="fullpage" />;
  }

  return (
    <>
      <WidgetTrigger onClick={() => setIsOpen(!isOpen)} isOpen={isOpen} />
      {isOpen && <WidgetPanel mode="widget" onClose={() => setIsOpen(false)} />}
    </>
  );
}
```

**Step 4: Create placeholder component stubs + store + styles**

Create stub files for all components, hooks, stores, and API client so the app compiles. Each stub exports a minimal component or function.

**Step 5: Build and verify**

```bash
cd /var/www/html/wordpress/wp-content/plugins/ai-itinerary-plugin/frontend
npm run build
ls -la ../assets/dist/
```

Expected: `aip-widget.js` and `aip-widget.css` in `assets/dist/`

**Step 6: Commit**

```bash
git add frontend/ assets/dist/
git commit -m "feat: scaffold React app with Vite, stub components, first build"
```

---

### Task 10: Zustand store + API client

**Files:**
- Create: `frontend/src/stores/appStore.js`
- Create: `frontend/src/api/client.js`

Zustand store manages: isOpen, messages, collectedData, ready, itinerary, isGenerating, user, isAuthenticated.

API client wraps fetch with nonce header from `window.aipConfig`.

---

### Task 11: Chat components

**Files:**
- Create: `frontend/src/components/Chat/ChatView.jsx`
- Create: `frontend/src/components/Chat/MessageList.jsx`
- Create: `frontend/src/components/Chat/BotMessage.jsx`
- Create: `frontend/src/components/Chat/UserMessage.jsx`
- Create: `frontend/src/components/Chat/TypingIndicator.jsx`
- Create: `frontend/src/components/Chat/ChatInput.jsx`
- Create: `frontend/src/components/Chat/GenerateButtons.jsx`
- Create: `frontend/src/hooks/useChat.js`

Build the full chat interface per design doc Section 4.

---

### Task 12: Widget + Header + Layout components

**Files:**
- Create: `frontend/src/components/Widget/WidgetTrigger.jsx`
- Create: `frontend/src/components/Widget/WidgetPanel.jsx`
- Create: `frontend/src/components/Common/Header.jsx`
- Create: `frontend/src/components/Common/LimitCounter.jsx`
- Create: `frontend/src/components/Common/CloseWarning.jsx`

---

### Task 13: Auth modal components

**Files:**
- Create: `frontend/src/components/Auth/AuthModal.jsx`
- Create: `frontend/src/components/Auth/LoginForm.jsx`
- Create: `frontend/src/components/Auth/RegisterForm.jsx`
- Create: `frontend/src/components/Auth/GoogleSignIn.jsx`
- Create: `frontend/src/hooks/useAuth.js`

---

### Task 14: Itinerary display components

**Files:**
- Create: `frontend/src/components/Itinerary/ItineraryPanel.jsx`
- Create: `frontend/src/components/Itinerary/DayCard.jsx`
- Create: `frontend/src/components/Itinerary/ActivityItem.jsx`
- Create: `frontend/src/components/Itinerary/MealItem.jsx`
- Create: `frontend/src/components/Itinerary/HotelItem.jsx`

---

### Task 15: Payment + PDF + Affiliate components

**Files:**
- Create: `frontend/src/components/Payment/CheckoutButton.jsx`
- Create: `frontend/src/components/PDF/DownloadButton.jsx`
- Create: `frontend/src/components/Affiliate/AffiliateSection.jsx`
- Create: `frontend/src/components/Affiliate/AffiliateButton.jsx`

---

### Task 16: Styling + i18n + production build

**Files:**
- Create: `frontend/src/styles/global.css`
- Create: `frontend/src/i18n/en.json`
- Create: `frontend/src/i18n/es.json`

Style all components per the design doc wireframes. Full CSS with:
- Floating widget (400px panel, bottom-right)
- Full-page two-column layout
- Chat bubbles (left/right aligned)
- Typing indicator animation
- Mobile responsive (full-screen on mobile)
- Admin-configurable primary/secondary colors via CSS custom properties

Build production bundle:
```bash
cd frontend && npm run build
```

Commit everything:
```bash
git add .
git commit -m "feat: complete React frontend with all components, styling, i18n"
```

---

## Phase 4: Integration + Polish (Tasks 17-19)

### Task 17: Admin panel implementation

Fully implement the admin class with:
- Dashboard with Chart.js charts
- All settings tabs (General, Payment, Affiliates, Auth, Branding)
- Analytics page with time period selector
- Itineraries browser table

---

### Task 18: End-to-end testing

Manual test checklist:
1. Visit http://localhost — floating widget appears
2. Click widget — panel opens with greeting
3. Chat through all 6 questions
4. Click "Free" — itinerary generates and displays
5. Click "Download PDF" — PDF downloads
6. Click "Save" — prompts login
7. Register with email — works
8. Google sign-in — works
9. Close widget with itinerary — warning appears
10. Visit /travel-planner — full-page two-column layout works
11. Premium flow → redirects to WooCommerce checkout
12. Admin panel → all settings save correctly
13. Admin dashboard → charts render
14. Affiliate links → open in new tab, clicks tracked
15. Mobile → full-screen widget

---

### Task 19: Final build + deploy prep

```bash
cd /var/www/html/wordpress/wp-content/plugins/ai-itinerary-plugin/frontend
npm run build

cd /var/www/html/wordpress/wp-content/plugins/ai-itinerary-plugin
git add .
git commit -m "feat: AI Itinerary Plugin v2.0.0 - complete rebuild"
```

Verify plugin works:
```bash
wp plugin deactivate ai-itinerary-plugin --path=/var/www/html/wordpress
wp plugin activate ai-itinerary-plugin --path=/var/www/html/wordpress
```

Visit http://localhost — everything should work.

---

## Execution Order Summary

| Phase | Tasks | Description | Est. Complexity |
|-------|-------|-------------|-----------------|
| 1 | 1-3 | Foundation: Composer, plugin scaffold, database | Medium |
| 2 | 4-8 | Backend: Claude, REST API, WooCommerce, PDF, affiliates, admin | Heavy |
| 3 | 9-16 | Frontend: React scaffold, store, all components, styling | Heavy |
| 4 | 17-19 | Integration: admin panel, E2E testing, final build | Medium |

**Total files to create/rewrite:** ~45 files
**Dependencies:** composer (DOMPDF), npm (react, zustand, vite)
