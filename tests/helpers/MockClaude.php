<?php
/**
 * Intercepts wp_remote_post calls to the Claude API and returns mock responses.
 * Add this filter before tests that need mocked Claude responses.
 */

class MockClaude {

    private static $next_response = null;
    private static $call_log = [];

    public static function enable() {
        add_filter('pre_http_request', [self::class, 'intercept'], 10, 3);
    }

    public static function disable() {
        remove_filter('pre_http_request', [self::class, 'intercept'], 10);
        self::$next_response = null;
        self::$call_log = [];
    }

    /**
     * Set a specific mock response for the next Claude API call.
     */
    public static function setNextResponse($text) {
        self::$next_response = $text;
    }

    /**
     * Get all intercepted Claude API calls.
     */
    public static function getCallLog() {
        return self::$call_log;
    }

    /**
     * WordPress HTTP filter: intercept outgoing requests to Claude API.
     */
    public static function intercept($preempt, $parsed_args, $url) {
        // Only intercept Anthropic API calls
        if (strpos($url, 'api.anthropic.com') === false) {
            return $preempt;
        }

        $body = json_decode($parsed_args['body'] ?? '{}', true);
        self::$call_log[] = [
            'url' => $url,
            'body' => $body,
            'time' => time(),
        ];

        // Use custom response if set
        $text = self::$next_response ?? self::generateDefaultResponse($body);
        self::$next_response = null;

        return [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body' => wp_json_encode([
                'id' => 'msg_mock_' . wp_rand(1000, 9999),
                'type' => 'message',
                'role' => 'assistant',
                'content' => [['type' => 'text', 'text' => $text]],
                'model' => $body['model'] ?? 'claude-sonnet-4-6',
                'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
            ]),
        ];
    }

    /**
     * Generate a context-appropriate mock response based on the request.
     */
    private static function generateDefaultResponse($body) {
        $system = $body['system'] ?? '';
        $messages = $body['messages'] ?? [];
        $last_msg = end($messages)['content'] ?? '';

        // If system prompt asks for JSON extraction
        if (strpos($system, 'Extract travel planning') !== false) {
            return '{"destination": "USA", "days": null, "trip_type": null, "budget": null, "interests": null, "pace": null}';
        }

        // If system prompt asks for itinerary generation
        if (strpos($system, 'travel itinerary generator') !== false) {
            return wp_json_encode([
                'destination' => 'New York, USA',
                'days' => 3,
                'summary' => 'A wonderful 3-day trip to New York City.',
                'itinerary' => [
                    [
                        'day' => 1,
                        'title' => 'Arrival & Manhattan',
                        'activities' => [
                            ['time' => '09:00', 'period' => 'morning', 'title' => 'Central Park', 'description' => 'Walk through the park', 'location' => 'Central Park', 'coordinates' => ['lat' => 40.785, 'lng' => -73.968], 'duration' => '2h', 'cost_estimate' => 'Free'],
                            ['time' => '14:00', 'period' => 'afternoon', 'title' => 'Times Square', 'description' => 'Explore the lights', 'location' => 'Times Square', 'coordinates' => ['lat' => 40.758, 'lng' => -73.985], 'duration' => '2h', 'cost_estimate' => 'Free'],
                        ],
                        'meals' => [
                            'breakfast' => ['name' => 'Sarabeth\'s', 'cuisine' => 'American', 'price_range' => '$$'],
                            'lunch' => ['name' => 'Shake Shack', 'cuisine' => 'Burgers', 'price_range' => '$'],
                            'dinner' => ['name' => 'Joe\'s Pizza', 'cuisine' => 'Pizza', 'price_range' => '$'],
                        ],
                        'accommodation' => ['name' => 'The Pod Hotel', 'price_range' => '$$', 'area' => 'Midtown'],
                    ],
                ],
                'tips' => ['Get a MetroCard for public transit', 'Walk across Brooklyn Bridge at sunset'],
                'budget_summary' => ['total_estimate' => '$500-800', 'breakdown' => ['accommodation' => '$300', 'food' => '$150', 'activities' => '$50', 'transport' => '$30']],
                'best_time_to_visit' => 'April-June',
                'packing_suggestions' => ['Comfortable walking shoes', 'Light jacket'],
            ]);
        }

        // Default: conversation response
        return "Great choice! How many days are you planning for your trip?";
    }
}
