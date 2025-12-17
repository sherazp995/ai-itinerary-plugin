<?php

class AI_Api {

    public function __construct() {
        // Generate itinerary from chat/form input
        add_action('wp_ajax_ai_generate_itinerary', [$this, 'generate_itinerary']);
        add_action('wp_ajax_nopriv_ai_generate_itinerary', [$this, 'generate_itinerary']);

        // Save itinerary to database
        add_action('wp_ajax_ai_save_itinerary', [$this, 'save_itinerary']);
        add_action('wp_ajax_nopriv_ai_save_itinerary', [$this, 'save_itinerary']);

        // Check prompt count for free users
        add_action('wp_ajax_ai_check_prompt_count', [$this, 'check_prompt_count']);
        add_action('wp_ajax_nopriv_ai_check_prompt_count', [$this, 'check_prompt_count']);
    }

    /**
     * Generate itinerary via OpenAI API
     */
    public function generate_itinerary() {
        check_ajax_referer('ai_itinerary_nonce', 'nonce');

        $user_id = get_current_user_id();
        $destination = sanitize_text_field($_POST['destination'] ?? '');
        $days = intval($_POST['days'] ?? 1);
        $language = sanitize_text_field($_POST['language'] ?? get_option('ai_output_language', 'en'));
        $style = sanitize_text_field($_POST['style'] ?? '');

        if (empty($destination)) {
            wp_send_json_error(['message' => 'Destination is required']);
        }

        // Check if user exceeded free prompts
        if (!$this->can_use_ai($user_id)) {
            wp_send_json_error(['message' => 'You have reached your free prompt limit. Please upgrade to Premium.']);
        }

        // Check OpenAI API key
        $api_key = get_option('ai_api_key');
        if (empty($api_key)) {
            wp_send_json_error(['message' => 'AI API key not configured']);
        }

        // Build prompt
        $prompt = "Create a detailed $days-day travel itinerary for {$destination}. ";
        $prompt .= "Include activities, food recommendations, accommodation suggestions, and travel tips. ";
        $prompt .= "Format as JSON with structure: {\"day\": 1, \"activities\": [], \"meals\": {}, \"tips\": []}. ";
        $prompt .= "Respond in {$language} language.";

        // Call OpenAI API
        $response = wp_remote_post("https://api.openai.com/v1/chat/completions", [
            'headers' => [
                "Authorization" => "Bearer $api_key",
                "Content-Type"  => "application/json",
            ],
            'body' => json_encode([
                "model" => "gpt-3.5-turbo",
                "messages" => [
                    ["role" => "system", "content" => "You are a helpful travel planning assistant."],
                    ["role" => "user", "content" => $prompt]
                ],
                "temperature" => 0.7,
                "max_tokens" => 1500
            ]),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'API request failed: ' . $response->get_error_message()]);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Debug: log response if error
        if (!isset($data['choices']) || !is_array($data['choices']) || empty($data['choices'])) {
            error_log('OpenAI API Response Error: ' . $body);
            wp_send_json_error(['message' => 'API Error: ' . (isset($data['error']['message']) ? $data['error']['message'] : 'Invalid response structure')]);
        }

        if (!isset($data['choices'][0]['message']['content'])) {
            error_log('OpenAI API Invalid structure: ' . print_r($data, true));
            wp_send_json_error(['message' => 'Invalid API response: missing content']);
        }

        $itinerary_text = $data['choices'][0]['message']['content'];

        // Increment prompt count
        $this->increment_prompt_count($user_id);

        wp_send_json_success([
            'itinerary' => $itinerary_text,
            'destination' => $destination,
            'days' => $days,
            'language' => $language,
        ]);
    }

    /**
     * Save itinerary to database
     */
    public function save_itinerary() {
        check_ajax_referer('ai_itinerary_nonce', 'nonce');

        $user_id = get_current_user_id();
        $itinerary_data = json_decode(stripslashes($_POST['data'] ?? '{}'), true);
        $title = sanitize_text_field($_POST['title'] ?? 'My Itinerary');

        if (empty($itinerary_data)) {
            wp_send_json_error(['message' => 'No itinerary data provided']);
        }

        // For non-logged-in users, check if guest saves are allowed
        if ($user_id === 0) {
            if (get_option('ai_allow_guest_save', 'yes') !== 'yes') {
                wp_send_json_error(['message' => 'Guest saves are not allowed. Please log in.']);
            }
        }

        // Save to database
        $saved_id = AI_Database::save($user_id, [
            'title' => $title,
            'data' => $itinerary_data,
            'created_at' => current_time('mysql')
        ]);

        if ($saved_id) {
            wp_send_json_success([
                'message' => 'Itinerary saved successfully',
                'id' => $saved_id
            ]);
        } else {
            wp_send_json_error(['message' => 'Failed to save itinerary']);
        }
    }

    /**
     * Check if user can use AI (hasn't exceeded free prompts)
     */
    public function check_prompt_count() {
        check_ajax_referer('ai_itinerary_nonce', 'nonce');

        $user_id = get_current_user_id();
        $can_use = $this->can_use_ai($user_id);
        $count = $this->get_prompt_count($user_id);
        $limit = (int) get_option('ai_free_prompts', 3);

        wp_send_json_success([
            'can_use' => $can_use,
            'current_count' => $count,
            'limit' => $limit,
            'remaining' => max(0, $limit - $count)
        ]);
    }

    /**
     * Check if user can generate (has prompts remaining or is premium)
     */
    private function can_use_ai($user_id) {
        // Logged-in users with premium product can generate unlimited
        if ($user_id > 0 && $this->is_premium_user($user_id)) {
            return true;
        }

        // Free users have limited prompts
        $limit = (int) get_option('ai_free_prompts', 3);
        $count = $this->get_prompt_count($user_id);

        return $count < $limit;
    }

    /**
     * Get prompt usage count (per session for guests, per user for logged-in)
     */
    private function get_prompt_count($user_id) {
        if ($user_id > 0) {
            // Logged-in user: store in user meta
            return (int) get_user_meta($user_id, 'ai_prompt_count', true);
        } else {
            // Guest: use session/transient (expires in 24 hours)
            $session_id = $this->get_guest_session_id();
            $count_key = 'ai_guest_prompts_' . $session_id;
            return (int) get_transient($count_key);
        }
    }

    /**
     * Increment prompt count
     */
    private function increment_prompt_count($user_id) {
        if ($user_id > 0) {
            $current = (int) get_user_meta($user_id, 'ai_prompt_count', true);
            update_user_meta($user_id, 'ai_prompt_count', $current + 1);
        } else {
            $session_id = $this->get_guest_session_id();
            $count_key = 'ai_guest_prompts_' . $session_id;
            $current = (int) get_transient($count_key);
            set_transient($count_key, $current + 1, 86400); // 24 hours
        }
    }

    /**
     * Get or create guest session ID
     */
    private function get_guest_session_id() {
        if (!isset($_COOKIE['ai_guest_session'])) {
            $session_id = wp_generate_uuid4();
            setcookie('ai_guest_session', $session_id, time() + 86400, COOKIEPATH, COOKIE_DOMAIN);
            $_COOKIE['ai_guest_session'] = $session_id;
        }
        return $_COOKIE['ai_guest_session'] ?? wp_generate_uuid4();
    }

    /**
     * Check if user is premium (has purchased or is admin)
     */
    private function is_premium_user($user_id) {
        if (!$user_id) return false;

        // Admins are always premium
        if (current_user_can('manage_options')) {
            return true;
        }

        // Check if WooCommerce integration is enabled
        if (get_option('ai_woo_integration', 'yes') === 'yes' && class_exists('WC_Order')) {
            // Check if user has purchased the premium product
            $premium_product_id = get_option('ai_premium_product_id');
            if ($premium_product_id) {
                return $this->has_purchased_product($user_id, $premium_product_id);
            }
        }

        // Check user meta for manual premium override
        return (bool) get_user_meta($user_id, 'ai_is_premium', true);
    }

    /**
     * Check if user has purchased a WooCommerce product
     */
    private function has_purchased_product($user_id, $product_id) {
        if (!class_exists('WC_Order')) return false;

        $args = [
            'customer' => $user_id,
            'status' => ['wc-completed', 'wc-processing'],
        ];

        $orders = wc_get_orders($args);

        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                if ($item->get_product_id() == $product_id || $item->get_variation_id() == $product_id) {
                    return true;
                }
            }
        }

        return false;
    }
}
