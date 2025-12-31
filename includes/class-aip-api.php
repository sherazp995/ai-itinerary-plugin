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
        add_action('wp_ajax_aip_chat_message', array($this, 'handle_chat_message'));
        add_action('wp_ajax_aip_reset_conversation', array($this, 'reset_conversation'));
        
        // AJAX endpoints for non-logged-in users
        add_action('wp_ajax_nopriv_aip_generate_itinerary', array($this, 'generate_itinerary'));
        add_action('wp_ajax_nopriv_aip_check_limit', array($this, 'check_limit'));
        add_action('wp_ajax_nopriv_aip_chat_message', array($this, 'handle_chat_message'));
        add_action('wp_ajax_nopriv_aip_reset_conversation', array($this, 'reset_conversation'));
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
            // Check if preferences is a structured array from conversation
            if (is_array($preferences)) {
                $prompt .= "\n\nUser preferences:";
                if (isset($preferences['budget'])) {
                    $prompt .= "\n- Budget: " . $preferences['budget'];
                }
                if (isset($preferences['interests'])) {
                    $prompt .= "\n- Interests: " . $preferences['interests'];
                }
                if (isset($preferences['pace'])) {
                    $prompt .= "\n- Trip pace: " . $preferences['pace'];
                }
                if (isset($preferences['travel_style'])) {
                    $prompt .= "\n- Travel style: " . $preferences['travel_style'];
                }
            } else {
                $prompt .= "\n\nUser preferences: " . $preferences;
            }
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
        $bot_name = get_option('aip_bot_name', 'Travel Buddy');
        
        $prompts = array(
            'friendly' => sprintf('You are %s, a friendly and respectful travel assistant who loves helping people plan amazing trips. Be enthusiastic but professional, and always provide accurate, helpful information. When introducing yourself, use your name naturally.', $bot_name),
            'professional' => sprintf('You are %s, a professional travel consultant providing expert advice and detailed travel planning services. Maintain a courteous and professional demeanor at all times.', $bot_name),
            'casual' => sprintf('You are %s, a fun and casual travel buddy helping friends plan their next adventure. Be friendly, use conversational language, but always be respectful.', $bot_name),
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
    
    /**
     * Handle chat message and manage multi-step conversation
     */
    public function handle_chat_message() {
        check_ajax_referer('aip_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $message = sanitize_text_field($_POST['message'] ?? '');
        
        if (empty($message)) {
            wp_send_json_error(array('message' => __('Message cannot be empty', 'ai-itinerary-plugin')));
        }
        
        // Handle initialization request
        if ($message === '__init__') {
            $state = $this->get_conversation_state($user_id);
            wp_send_json_success(array('state' => $state));
            return;
        }
        
        // Get or initialize conversation state
        $state = $this->get_conversation_state($user_id);
        
        // Process message based on current step
        $response = $this->process_conversation_step($message, $state, $user_id);
        
        // Save updated state
        $this->save_conversation_state($user_id, $response['state']);
        
        wp_send_json_success($response);
    }
    
    /**
     * Get conversation state from database or session
     */
    private function get_conversation_state($user_id) {
        if ($user_id > 0) {
            $state = AIP_Database::get_conversation_state($user_id);
            if ($state) {
                return $state;
            }
        } else {
            // For guests, use session
            if (!session_id()) {
                session_start();
            }
            if (isset($_SESSION['aip_conversation_state'])) {
                return $_SESSION['aip_conversation_state'];
            }
        }
        
        // Return initial state
        return $this->get_initial_conversation_state();
    }
    
    /**
     * Save conversation state to database or session
     */
    private function save_conversation_state($user_id, $state) {
        if ($user_id > 0) {
            AIP_Database::save_conversation_state($user_id, $state);
        } else {
            if (!session_id()) {
                session_start();
            }
            $_SESSION['aip_conversation_state'] = $state;
        }
    }
    
    /**
     * Get initial conversation state
     */
    private function get_initial_conversation_state() {
        return array(
            'messages' => array(), // Store conversation history
            'data' => array(), // Store extracted travel details
            'ready_to_generate' => false,
        );
    }
    
    /**
     * Process conversation step and return response  
     */
    private function process_conversation_step($message, $state, $user_id) {
        $bot_name = get_option('aip_bot_name', 'Travel Buddy');
        
        // Add user message to history
        $state['messages'][] = array(
            'role' => 'user',
            'content' => $message
        );
        
        // Get AI response
        $ai_response = $this->get_ai_conversation_response($state, $bot_name);
        
        // Add AI response to history
        $state['messages'][] = array(
            'role' => 'assistant',
            'content' => $ai_response['message']
        );
        
        // Update extracted data
        if (!empty($ai_response['extracted_data'])) {
            $state['data'] = array_merge($state['data'], $ai_response['extracted_data']);
        }
        
        // Check if we have enough info to generate
        $state['ready_to_generate'] = $this->has_required_info($state['data']);
        
        return array(
            'bot_message' => $ai_response['message'], // Frontend expects 'bot_message'
            'state' => $state,
            'ready_to_generate' => $state['ready_to_generate'],
            'collected_data' => $state['data'], // Frontend expects 'collected_data'
        );
    }
    
    /**
     * Get AI conversation response
     */
    private function get_ai_conversation_response($state, $bot_name) {
        $api_key = get_option('aip_openai_api_key');
        $model = get_option('aip_openai_model', 'gpt-3.5-turbo');
        
        // Build system prompt for conversation
        $system_prompt = $this->get_conversation_system_prompt($bot_name, $state['data']);
        
        // Prepare messages for API
        $messages = array(
            array('role' => 'system', 'content' => $system_prompt)
        );
        
        // Add conversation history
        foreach ($state['messages'] as $msg) {
            $messages[] = $msg;
        }
        
        // Call OpenAI API
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'timeout' => 60,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 500,
            )),
        ));
        
        if (is_wp_error($response)) {
            return array(
                'message' => __('Sorry, I\'m having trouble connecting. Please try again.', 'ai-itinerary-plugin'),
                'extracted_data' => array(),
            );
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $ai_message = $body['choices'][0]['message']['content'] ?? '';
        
        // Extract structured data from the conversation
        $extracted_data = $this->extract_travel_info_from_conversation($state['messages'], $ai_message);
        
        return array(
            'message' => $ai_message,
            'extracted_data' => $extracted_data,
        );
    }
    
    /**
     * Get system prompt for natural conversation
     */
    private function get_conversation_system_prompt($bot_name, $current_data) {
        $missing_info = $this->get_missing_info($current_data);
        
        $prompt = sprintf(
            "You are %s, a friendly and enthusiastic AI travel assistant. Your goal is to have a natural, engaging conversation with the user to plan their trip.\n\n",
            $bot_name
        );
        
        $prompt .= "CONVERSATION GUIDELINES:\n";
        $prompt .= "1. Be warm, enthusiastic, and conversational - chat naturally like a knowledgeable friend\n";
        $prompt .= "2. Ask ONE question at a time to keep the conversation flowing\n";
        $prompt .= "3. Acknowledge what the user just told you before asking the next question\n";
        $prompt .= "4. Use emojis sparingly to add personality (🌍 ✈️ 🗺️ 🏖️ 🎒)\n";
        $prompt .= "5. Keep responses concise (2-3 sentences max)\n";
        $prompt .= "6. Be flexible - if user provides multiple details at once, acknowledge all of them\n\n";
        
        $prompt .= "REQUIRED INFORMATION TO COLLECT:\n";
        $prompt .= "- Destination (country and city/region)\n";
        $prompt .= "- Trip length (number of days)\n";
        $prompt .= "- Budget level (low/medium/high)\n";
        $prompt .= "- Interests (e.g., culture, food, adventure, relaxation)\n";
        $prompt .= "- Trip pace (relaxed/balanced/fast-paced)\n";
        $prompt .= "- Travel style (solo/couple/family/group)\n\n";
        
        if (empty($current_data)) {
            $prompt .= "This is the START of the conversation. Introduce yourself warmly and ask about their destination.\n";
        } else {
            $prompt .= "INFORMATION COLLECTED SO FAR:\n";
            foreach ($current_data as $key => $value) {
                $prompt .= "- " . ucfirst($key) . ": " . $value . "\n";
            }
            $prompt .= "\n";
            
            if (!empty($missing_info)) {
                $prompt .= "STILL NEED TO ASK ABOUT: " . implode(', ', $missing_info) . "\n";
                $prompt .= "Ask about the NEXT missing item naturally, based on the conversation flow.\n";
            } else {
                $prompt .= "You have ALL the information needed! Tell the user you're ready to create their itinerary and ask if they want a FREE or PREMIUM version:\n";
                $prompt .= "- FREE: Basic itinerary with daily activities and tips\n";
                $prompt .= "- PREMIUM: Detailed itinerary with specific hotels, restaurants, bookings, prices, and insider tips\n";
            }
        }
        
        return $prompt;
    }
    
    /**
     * Extract travel info from conversation
     */
    private function extract_travel_info_from_conversation($messages, $latest_response) {
        $extracted = array();
        
        // Get the last user message
        $last_user_message = '';
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if ($messages[$i]['role'] === 'user') {
                $last_user_message = strtolower($messages[$i]['content']);
                break;
            }
        }
        
        if (empty($last_user_message)) {
            return $extracted;
        }
        
        // Extract days if mentioned
        if (preg_match('/(\d+)\s*(days?|nights?)/', $last_user_message, $matches)) {
            $extracted['days'] = absint($matches[1]);
        }
        
        // Extract budget mentions
        if (preg_match('/\b(low|budget|cheap|affordable)\b/i', $last_user_message)) {
            $extracted['budget'] = 'low';
        } elseif (preg_match('/\b(high|luxury|expensive|premium)\b/i', $last_user_message)) {
            $extracted['budget'] = 'high';
        } elseif (preg_match('/\b(medium|moderate|average|mid-range)\b/i', $last_user_message)) {
            $extracted['budget'] = 'medium';
        }
        
        // Extract pace
        if (preg_match('/\b(relaxed|slow|easy|leisurely)\b/i', $last_user_message)) {
            $extracted['pace'] = 'relaxed';
        } elseif (preg_match('/\b(fast|quick|active|intense|packed)\b/i', $last_user_message)) {
            $extracted['pace'] = 'fast-paced';
        } elseif (preg_match('/\b(balanced|moderate|normal)\b/i', $last_user_message)) {
            $extracted['pace'] = 'balanced';
        }
        
        // Extract travel style
        if (preg_match('/\b(solo|alone|myself)\b/i', $last_user_message)) {
            $extracted['travel_style'] = 'solo';
        } elseif (preg_match('/\b(couple|partner|spouse|girlfriend|boyfriend)\b/i', $last_user_message)) {
            $extracted['travel_style'] = 'couple';
        } elseif (preg_match('/\b(family|kids|children)\b/i', $last_user_message)) {
            $extracted['travel_style'] = 'family';
        } elseif (preg_match('/\b(group|friends)\b/i', $last_user_message)) {
            $extracted['travel_style'] = 'group';
        }
        
        // Extract destination info (simple approach - store raw text)
        // AI will naturally extract this in conversation
        if (count($messages) <= 2 && !isset($extracted['destination'])) {
            // First user message is likely the destination
            $extracted['destination'] = trim($messages[0]['content']);
        }
        
        return $extracted;
    }
    
    /**
     * Check if we have required info
     */
    private function has_required_info($data) {
        $required = array('destination', 'days', 'budget', 'interests', 'pace', 'travel_style');
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get list of missing information
     */
    private function get_missing_info($data) {
        $required = array(
            'destination' => 'destination',
            'days' => 'trip length',
            'budget' => 'budget level',
            'interests' => 'interests',
            'pace' => 'trip pace',
            'travel_style' => 'travel style',
        );
        
        $missing = array();
        foreach ($required as $key => $label) {
            if (empty($data[$key])) {
                $missing[] = $label;
            }
        }
        
        return $missing;
    }
    
    /**
     * Reset conversation state
     */
    public function reset_conversation() {
        check_ajax_referer('aip_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        
        if ($user_id > 0) {
            AIP_Database::clear_conversation_state($user_id);
        } else {
            if (!session_id()) {
                session_start();
            }
            unset($_SESSION['aip_conversation_state']);
        }
        
        // Get initial AI greeting
        $initial_state = $this->get_initial_conversation_state();
        $bot_name = get_option('aip_bot_name', 'Travel Buddy');
        
        // Use AI to generate natural greeting
        $greeting_response = $this->get_ai_conversation_response($initial_state, $bot_name);
        
        // Add greeting to conversation history
        $initial_state['messages'][] = array(
            'role' => 'assistant',
            'content' => $greeting_response['message']
        );
        
        $this->save_conversation_state($user_id, $initial_state);
        
        wp_send_json_success(array(
            'bot_message' => $greeting_response['message'],
            'state' => $initial_state,
        ));
    }
}
