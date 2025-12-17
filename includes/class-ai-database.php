<?php

class AI_Database {

    /**
     * Create DB tables for saved itineraries
     */
    public static function create_tables() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_itineraries';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned DEFAULT NULL,
            title varchar(255) NOT NULL,
            data longtext NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Save an itinerary
     */
    public static function save($user_id, $data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_itineraries';

        $insert_data = [
            'user_id' => $user_id ?: null,
            'title' => sanitize_text_field($data['title'] ?? 'Untitled'),
            'data' => maybe_serialize($data['data'] ?? []),
            'created_at' => $data['created_at'] ?? current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];

        $result = $wpdb->insert($table_name, $insert_data);

        if ($result) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Get an itinerary by ID
     */
    public static function get($id, $user_id = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_itineraries';

        $query = $wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $id
        );

        // If user_id provided, verify ownership (for security)
        if ($user_id !== null) {
            $query = $wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d AND (user_id = %d OR user_id IS NULL)",
                $id,
                $user_id
            );
        }

        $row = $wpdb->get_row($query);

        if ($row) {
            $row->data = maybe_unserialize($row->data);
        }

        return $row;
    }

    /**
     * Get all itineraries for a user
     */
    public static function get_user_itineraries($user_id, $limit = 50, $offset = 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_itineraries';

        $query = $wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $user_id,
            $limit,
            $offset
        );

        $rows = $wpdb->get_results($query);

        foreach ($rows as $row) {
            $row->data = maybe_unserialize($row->data);
        }

        return $rows;
    }

    /**
     * Update an itinerary
     */
    public static function update($id, $data, $user_id = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_itineraries';

        $update_data = [
            'updated_at' => current_time('mysql'),
        ];

        if (isset($data['title'])) {
            $update_data['title'] = sanitize_text_field($data['title']);
        }

        if (isset($data['data'])) {
            $update_data['data'] = maybe_serialize($data['data']);
        }

        $where = ['id' => $id];

        // If user_id provided, verify ownership
        if ($user_id !== null) {
            $where['user_id'] = $user_id;
        }

        return $wpdb->update($table_name, $update_data, $where);
    }

    /**
     * Delete an itinerary
     */
    public static function delete($id, $user_id = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_itineraries';

        $where = ['id' => $id];

        // If user_id provided, verify ownership
        if ($user_id !== null) {
            $where['user_id'] = $user_id;
        }

        return $wpdb->delete($table_name, $where);
    }

    /**
     * Get itinerary count for a user
     */
    public static function get_user_count($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_itineraries';

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE user_id = %d",
                $user_id
            )
        );
    }

    /**
     * Constructor (for plugin loader to instantiate)
     */
    public function __construct() {
        // Hooks can go here if needed
    }
}
