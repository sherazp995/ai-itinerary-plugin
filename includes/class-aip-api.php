<?php
/**
 * API Handler - OpenAI Integration & AJAX Endpoints
 *
 * @package AI_Itinerary_Generator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AIP_API {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // AJAX endpoints for logged-in users
        add_action('wp_ajax_aip_generate_itinerary', array($this, 'generate_itinerary'));
        add_action('wp_ajax_aip_save_itinerary', array($this, 'save_itinerary'));
        add_action('wp_ajax_aip_get_itineraries', array($this, 'get_itineraries'));
        add_action('wp_ajax_aip_check_limit', array($this, 'check_limit'));
        
        // AJAX endpoints for non-logged-in users
        add_action('wp_ajax_nopriv_aip_generate_itinerary', array($this, 'generate_itinerary'));
        add_action('wp_ajax_nopriv_aip_check_limit', array($this, 'check_limit'));
    }
    
    /**
     * Generate itinerary using OpenAI
     */
    public function generate_itinerary() {
        check_ajax_referer('aip_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $destination = sanitize_text_field($_POST['destination'] ?? '');
        $days = absint($_POST['days'] ?? 1);
        $language = sanitize_text_field($_POST['language'] ?? get_option('aip_default_language', 'en'));
        $type = sanitize_text_field($_POST['type'] ?? 'free'); // free or premium
        $start_date = sanitize_text_field($_POST['start_date'] ?? '');
        $end_date = sanitize_text_field($_POST['end_date'] ?? '');
        $preferences = sanitize_textarea_field($_POST['preferences'] ?? '');
        
        // Validation
        if (empty($destination)) {
            wp_send_json_error(array('message' => __('Destination is required', 'ai-itinerary-plugin')));
        }
        
        // Check if user has reached limit
        if (!$this->can_generate($user_id, $type)) {
            wp_send_json_error(array(
                'message' => __('You have reached your free itinerary limit. Please purchase a premium itinerary.', 'ai-itinerary-plugin'),
                'upgrade_required' => true
            ));
        }
        
        // Get OpenAI API key
        $api_key = get_option('aip_openai_api_key');
        if (empty($api_key)) {
            wp_send_json_error(array('message' => __('OpenAI API key not configured', 'ai-itinerary-plugin')));
        }
        
        // Build prompt based on type and tone
        $prompt = $this->build_prompt($destination, $days, $language, $type, $preferences);
        
        // Call OpenAI API
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => $type === 'premium' ? 'gpt-4' : 'gpt-3.5-turbo',
                'messages' => array(
                    array('role' => 'system', 'content' => $this->get_system_prompt()),
                    array('role' => 'user', 'content' => $prompt)
                ),
                'temperature' => 0.7,
                'max_tokens' => $type === 'premium' ? 4000 : 1500,
            )),
            'timeout' => 60,
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => __('API request failed', 'ai-itinerary-plugin') . ': ' . $response->get_error_message()));
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['choices'][0]['message']['content'])) {
            error_log('OpenAI API Error: ' . print_r($body, true));
            wp_send_json_error(array('message' => __('Failed to generate itinerary', 'ai-itinerary-plugin')));
        }
        
        $itinerary_content = $body['choices'][0]['message']['content'];
        
        // Parse the itinerary (assuming JSON format)
        $itinerary_data = json_decode($itinerary_content, true);
        if (!$itinerary_data) {
            // If not JSON, wrap in simple structure
            $itinerary_data = array(
                'destination' => $destination,
                'days' => $days,
                'content' => $itinerary_content
            );
        }
        
        // Add affiliate links
        $itinerary_data = AIP_Affiliate::add_affiliate_links($itinerary_data, $destination);
        
        // Save itinerary to database
        $itinerary_id = AIP_Database::save_itinerary(array(
            'user_id' => $user_id ? $user_id : 0,
            'title' => sprintf(__('%s - %d Days', 'ai-itinerary-plugin'), $destination, $days),
            'destination' => $destination,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'days' => $days,
            'type' => $type,
            'language' => $language,
            'data' => $itinerary_data,
            'status' => $type === 'premium' ? 'draft' : 'completed',
        ));
        
        // Update user meta
        if ($user_id) {
            $user_meta = AIP_Database::get_user_meta($user_id);
            $count_field = $type === 'free' ? 'free_itinerary_count' : 'premium_itinerary_count';
            AIP_Database::update_user_meta($user_id, array(
                $count_field => $user_meta->{$count_field} + 1,
                'last_itinerary_date' => current_time('mysql'),
            ));
        }
        
        // Log analytics
        AIP_Database::log_analytics('itinerary_generated', array(
            'type' => $type,
            'destination' => $destination,
            'days' => $days,
        ), $user_id, $itinerary_id);
        
        wp_send_json_success(array(
            'itinerary_id' => $itinerary_id,
            'itinerary_data' => $itinerary_data,
            'type' => $type,
            'requires_payment' => $type === 'premium',
        ));
    }
    
    /**
     * Build AI prompt
     */
    private function build_prompt($destination, $days, $language, $type, $preferences = '') {
        $detail_level = $type === 'premium' ? 'very detailed and comprehensive' : 'concise and informative';
        
        $prompt = sprintf(
            "Create a %s %d-day travel itinerary for %s in %s language.",
            $detail_level,
            $days,
            $destination,
            $this->get_language_name($language)
        );
        
        if ($type === 'premium') {
            $prompt .= " Include detailed recommendations for:
- Specific hotels and accommodations with price ranges
- Restaurants and local food experiences
- Activities and attractions with timing suggestions
- Transportation options between locations
- Local tips and cultural insights
- Budget breakdown
- Best times to visit each place
- Booking recommendations";
        } else {
            $prompt .= " Include:
- Daily activity suggestions
- Popular attractions
- General dining recommendations
- Basic travel tips";
        }
        
        if (!empty($preferences)) {
            $prompt .= "\n\nUser preferences: " . $preferences;
        }
        
        $prompt .= "\n\nFormat the response as a well-structured JSON with the following structure:
{
  \"destination\": \"destination name\",
  \"days\": number_of_days,
  \"itinerary\": [
    {
      \"day\": 1,
      \"title\": \"Day title\",
      \"activities\": [
        {\"time\": \"morning/afternoon/evening\", \"title\": \"Activity name\", \"description\": \"Details\", \"location\": \"Location name\"}
      ],
      \"meals\": {
        \"breakfast\": \"Recommendation\",
        \"lunch\": \"Recommendation\",
        \"dinner\": \"Recommendation\"
      },
      \"accommodation\": \"Hotel recommendation\" (premium only)
    }
  ],
  \"tips\": [\"tip1\", \"tip2\"],
  \"budget\": {\"total\": \"estimated budget\", \"breakdown\": {}} (premium only)
}";
        
        return $prompt;
    }
    
    /**
     * Get system prompt based on tone
     */
    private function get_system_prompt() {
        $tone = get_option('aip_ai_tone', 'friendly');
        
        $prompts = array(
            'friendly' => 'You are a friendly and respectful travel assistant who loves helping people plan amazing trips. Be enthusiastic but professional, and always provide accurate, helpful information.',
            'professional' => 'You are a professional travel consultant providing expert advice and detailed travel planning services.',
            'casual' => 'You are a fun and casual travel buddy helping friends plan their next adventure. Be friendly, use conversational language, but always be respectful.',
        );
        
        return $prompts[$tone] ?? $prompts['friendly'];
    }
    
    /**
     * Get language name
     */
    private function get_language_name($code) {
        $languages = array(
            'en' => 'English',
            'es' => 'Spanish',
            'fr' => 'French',
            'de' => 'German',
            'it' => 'Italian',
            'pt' => 'Portuguese',
        );
        
        return $languages[$code] ?? 'English';
    }
    
    /**
     * Check if user can generate itinerary
     */
    private function can_generate($user_id, $type) {
        // Premium always requires payment, so return true for generation
        if ($type === 'premium') {
            return true;
        }
        
        // Free itinerary limit check
        $limit = absint(get_option('aip_free_itinerary_limit', 3));
        
        if ($user_id) {
            $user_meta = AIP_Database::get_user_meta($user_id);
            return $user_meta->free_itinerary_count < $limit;
        } else {
            // For non-logged-in users, use session
            if (!session_id()) {
                session_start();
            }
            $count = isset($_SESSION['aip_free_count']) ? $_SESSION['aip_free_count'] : 0;
            return $count < $limit;
        }
    }
    
    /**
     * Check user's remaining limit
     */
    public function check_limit() {
        check_ajax_referer('aip_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $limit = absint(get_option('aip_free_itinerary_limit', 3));
        
        if ($user_id) {
            $user_meta = AIP_Database::get_user_meta($user_id);
            $used = $user_meta->free_itinerary_count;
        } else {
            if (!session_id()) {
                session_start();
            }
            $used = isset($_SESSION['aip_free_count']) ? $_SESSION['aip_free_count'] : 0;
        }
        
        wp_send_json_success(array(
            'limit' => $limit,
            'used' => $used,
            'remaining' => max(0, $limit - $used),
        ));
    }
    
    /**
     * Save itinerary (manual save by user)
     */
    public function save_itinerary() {
        check_ajax_referer('aip_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array('message' => __('Please log in to save itineraries', 'ai-itinerary-plugin')));
        }
        
        $itinerary_id = absint($_POST['itinerary_id'] ?? 0);
        $title = sanitize_text_field($_POST['title'] ?? '');
        
        if ($itinerary_id) {
            $result = AIP_Database::update_itinerary($itinerary_id, array('title' => $title), $user_id);
            if ($result) {
                wp_send_json_success(array('message' => __('Itinerary saved', 'ai-itinerary-plugin')));
            }
        }
        
        wp_send_json_error(array('message' => __('Failed to save itinerary', 'ai-itinerary-plugin')));
    }
    
    /**
     * Get user's saved itineraries
     */
    public function get_itineraries() {
        check_ajax_referer('aip_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array('message' => __('Please log in', 'ai-itinerary-plugin')));
        }
        
        $itineraries = AIP_Database::get_user_itineraries($user_id);
        wp_send_json_success(array('itineraries' => $itineraries));
    }
}

