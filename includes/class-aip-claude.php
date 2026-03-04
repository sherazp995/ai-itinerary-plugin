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
