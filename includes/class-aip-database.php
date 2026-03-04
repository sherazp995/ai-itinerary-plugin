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
            share_token VARCHAR(32) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_destination (destination),
            INDEX idx_status (status),
            UNIQUE KEY idx_share_token (share_token)
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

    public static function get_itinerary_by_share_token($token) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aip_itineraries WHERE share_token = %s AND status = 'completed' LIMIT 1",
            $token
        ));
    }

    public static function maybe_upgrade_schema() {
        $stored = get_option('aip_db_version');
        if ($stored === AIP_VERSION) return;
        self::create_tables();
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
