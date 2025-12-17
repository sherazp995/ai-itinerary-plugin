<?php
/**
 * Database Handler
 *
 * @package AI_Itinerary_Generator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AIP_Database {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Constructor
    }
    
    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Itineraries table
        $table_itineraries = $wpdb->prefix . 'aip_itineraries';
        $sql_itineraries = "CREATE TABLE $table_itineraries (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            title varchar(255) NOT NULL,
            destination varchar(255) NOT NULL,
            start_date date DEFAULT NULL,
            end_date date DEFAULT NULL,
            days int(11) NOT NULL DEFAULT 1,
            type enum('free','premium') NOT NULL DEFAULT 'free',
            language varchar(10) NOT NULL DEFAULT 'en',
            data longtext NOT NULL,
            pdf_url varchar(500) DEFAULT NULL,
            status enum('draft','completed','paid') NOT NULL DEFAULT 'draft',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY type (type),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql_itineraries);
        
        // User metadata table (for extended user info)
        $table_user_meta = $wpdb->prefix . 'aip_user_meta';
        $sql_user_meta = "CREATE TABLE $table_user_meta (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            free_itinerary_count int(11) NOT NULL DEFAULT 0,
            premium_itinerary_count int(11) NOT NULL DEFAULT 0,
            total_spent decimal(10,2) NOT NULL DEFAULT 0.00,
            last_itinerary_date datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";
        dbDelta($sql_user_meta);
        
        // Payments table
        $table_payments = $wpdb->prefix . 'aip_payments';
        $sql_payments = "CREATE TABLE $table_payments (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            itinerary_id bigint(20) unsigned DEFAULT NULL,
            amount decimal(10,2) NOT NULL,
            currency varchar(10) NOT NULL DEFAULT 'USD',
            payment_method varchar(50) NOT NULL,
            transaction_id varchar(255) NOT NULL,
            status enum('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
            payment_data text DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY itinerary_id (itinerary_id),
            KEY transaction_id (transaction_id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql_payments);
        
        // Analytics table
        $table_analytics = $wpdb->prefix . 'aip_analytics';
        $sql_analytics = "CREATE TABLE $table_analytics (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            event_data text DEFAULT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            itinerary_id bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql_analytics);
    }
    
    /**
     * Save itinerary
     */
    public static function save_itinerary($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'aip_itineraries';
        
        $insert_data = array(
            'user_id' => absint($data['user_id']),
            'title' => sanitize_text_field($data['title']),
            'destination' => sanitize_text_field($data['destination']),
            'start_date' => isset($data['start_date']) ? sanitize_text_field($data['start_date']) : null,
            'end_date' => isset($data['end_date']) ? sanitize_text_field($data['end_date']) : null,
            'days' => absint($data['days']),
            'type' => in_array($data['type'], array('free', 'premium')) ? $data['type'] : 'free',
            'language' => sanitize_text_field($data['language']),
            'data' => wp_json_encode($data['data']),
            'status' => isset($data['status']) ? $data['status'] : 'draft',
        );
        
        $result = $wpdb->insert($table, $insert_data);
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Get itinerary
     */
    public static function get_itinerary($id, $user_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'aip_itineraries';
        
        $query = $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id);
        
        if ($user_id !== null) {
            $query = $wpdb->prepare("SELECT * FROM $table WHERE id = %d AND user_id = %d", $id, $user_id);
        }
        
        $result = $wpdb->get_row($query);
        
        if ($result && !empty($result->data)) {
            $result->data = json_decode($result->data, true);
        }
        
        return $result;
    }
    
    /**
     * Update itinerary
     */
    public static function update_itinerary($id, $data, $user_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'aip_itineraries';
        
        $update_data = array();
        
        if (isset($data['title'])) {
            $update_data['title'] = sanitize_text_field($data['title']);
        }
        if (isset($data['data'])) {
            $update_data['data'] = wp_json_encode($data['data']);
        }
        if (isset($data['pdf_url'])) {
            $update_data['pdf_url'] = esc_url_raw($data['pdf_url']);
        }
        if (isset($data['status'])) {
            $update_data['status'] = $data['status'];
        }
        
        $where = array('id' => $id);
        if ($user_id !== null) {
            $where['user_id'] = $user_id;
        }
        
        return $wpdb->update($table, $update_data, $where);
    }
    
    /**
     * Get user itineraries
     */
    public static function get_user_itineraries($user_id, $limit = 50, $offset = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'aip_itineraries';
        
        $query = $wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $user_id,
            $limit,
            $offset
        );
        
        $results = $wpdb->get_results($query);
        
        foreach ($results as $result) {
            if (!empty($result->data)) {
                $result->data = json_decode($result->data, true);
            }
        }
        
        return $results;
    }
    
    /**
     * Get or create user meta
     */
    public static function get_user_meta($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'aip_user_meta';
        
        $meta = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE user_id = %d", $user_id));
        
        if (!$meta) {
            $wpdb->insert($table, array('user_id' => $user_id));
            $meta = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE user_id = %d", $user_id));
        }
        
        return $meta;
    }
    
    /**
     * Update user meta
     */
    public static function update_user_meta($user_id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'aip_user_meta';
        
        return $wpdb->update($table, $data, array('user_id' => $user_id));
    }
    
    /**
     * Save payment record
     */
    public static function save_payment($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'aip_payments';
        
        $insert_data = array(
            'user_id' => absint($data['user_id']),
            'itinerary_id' => isset($data['itinerary_id']) ? absint($data['itinerary_id']) : null,
            'amount' => floatval($data['amount']),
            'currency' => sanitize_text_field($data['currency']),
            'payment_method' => sanitize_text_field($data['payment_method']),
            'transaction_id' => sanitize_text_field($data['transaction_id']),
            'status' => $data['status'],
            'payment_data' => isset($data['payment_data']) ? wp_json_encode($data['payment_data']) : null,
        );
        
        $result = $wpdb->insert($table, $insert_data);
        
        if ($result) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Log analytics event
     */
    public static function log_analytics($event_type, $event_data = null, $user_id = null, $itinerary_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'aip_analytics';
        
        $insert_data = array(
            'event_type' => sanitize_text_field($event_type),
            'event_data' => $event_data ? wp_json_encode($event_data) : null,
            'user_id' => $user_id,
            'itinerary_id' => $itinerary_id,
        );
        
        return $wpdb->insert($table, $insert_data);
    }
    
    /**
     * Get analytics data for admin dashboard
     */
    public static function get_analytics($days = 30) {
        global $wpdb;
        $table_analytics = $wpdb->prefix . 'aip_analytics';
        $table_payments = $wpdb->prefix . 'aip_payments';
        $table_itineraries = $wpdb->prefix . 'aip_itineraries';
        
        $date_from = date('Y-m-d H:i:s', strtotime("-$days days"));
        
        // Total itineraries
        $total_itineraries = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_itineraries WHERE created_at >= %s",
            $date_from
        ));
        
        // Free vs Premium
        $itinerary_types = $wpdb->get_results($wpdb->prepare(
            "SELECT type, COUNT(*) as count FROM $table_itineraries WHERE created_at >= %s GROUP BY type",
            $date_from
        ));
        
        // Revenue
        $revenue = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM $table_payments WHERE status = 'completed' AND created_at >= %s",
            $date_from
        ));
        
        // Daily stats
        $daily_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as count 
            FROM $table_itineraries 
            WHERE created_at >= %s 
            GROUP BY DATE(created_at) 
            ORDER BY date ASC",
            $date_from
        ));
        
        // Daily revenue
        $daily_revenue = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as date, SUM(amount) as revenue 
            FROM $table_payments 
            WHERE status = 'completed' AND created_at >= %s 
            GROUP BY DATE(created_at) 
            ORDER BY date ASC",
            $date_from
        ));
        
        return array(
            'total_itineraries' => $total_itineraries,
            'itinerary_types' => $itinerary_types,
            'total_revenue' => $revenue ? floatval($revenue) : 0,
            'daily_stats' => $daily_stats,
            'daily_revenue' => $daily_revenue,
        );
    }
}

