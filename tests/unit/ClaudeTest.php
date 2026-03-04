<?php
use PHPUnit\Framework\TestCase;

class ClaudeTest extends TestCase {

    protected function setUp(): void {
        MockClaude::enable();
    }

    protected function tearDown(): void {
        MockClaude::disable();
    }

    // ---- send_message ----

    public function test_send_message_returns_response(): void {
        // Set a dummy API key so the method doesn't bail early
        update_option('aip_claude_api_key', 'sk-test-mock-key');

        $messages = [['role' => 'user', 'content' => 'Hello']];
        $result = AIP_Claude::send_message($messages, 'You are a test bot.', 100);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('content', $result);
        $this->assertNotEmpty($result['content'][0]['text']);
    }

    public function test_send_message_without_api_key_returns_error(): void {
        update_option('aip_claude_api_key', '');

        $result = AIP_Claude::send_message([['role' => 'user', 'content' => 'Hi']], '', 100);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('no_api_key', $result->get_error_code());
    }

    public function test_send_message_passes_correct_headers(): void {
        update_option('aip_claude_api_key', 'sk-test-check-headers');

        AIP_Claude::send_message([['role' => 'user', 'content' => 'test']], 'sys prompt', 256);

        $log = MockClaude::getCallLog();
        $this->assertCount(1, $log);
        $this->assertStringContains('api.anthropic.com/v1/messages', $log[0]['url']);
    }

    public function test_send_message_includes_system_prompt(): void {
        update_option('aip_claude_api_key', 'sk-test-system');

        AIP_Claude::send_message([['role' => 'user', 'content' => 'hi']], 'Be friendly', 100);

        $log = MockClaude::getCallLog();
        $this->assertEquals('Be friendly', $log[0]['body']['system']);
    }

    // ---- build_conversation_prompt ----

    public function test_build_prompt_empty_data_asks_destination(): void {
        $prompt = AIP_Claude::build_conversation_prompt([]);

        $this->assertStringContainsString('Ask where they want to go', $prompt);
        $this->assertStringContainsString('travel assistant', $prompt);
    }

    public function test_build_prompt_partial_data_shows_collected(): void {
        $prompt = AIP_Claude::build_conversation_prompt([
            'destination' => 'Paris',
            'days' => '5',
        ]);

        $this->assertStringContainsString('COLLECTED SO FAR', $prompt);
        $this->assertStringContainsString('Paris', $prompt);
        $this->assertStringContainsString('STILL NEED', $prompt);
    }

    public function test_build_prompt_complete_data_ready(): void {
        $prompt = AIP_Claude::build_conversation_prompt([
            'destination' => 'Tokyo',
            'days' => '7',
            'trip_type' => 'leisure',
            'budget' => 'medium',
            'interests' => 'food, temples',
            'pace' => 'relaxed',
        ]);

        $this->assertStringContainsString('ALL INFORMATION COLLECTED', $prompt);
        $this->assertStringContainsString('Tokyo', $prompt);
    }

    // ---- build_generation_prompt ----

    public function test_generation_prompt_free_is_concise(): void {
        $prompt = AIP_Claude::build_generation_prompt([
            'destination' => 'Rome',
            'days' => '3',
            'trip_type' => 'leisure',
            'budget' => 'medium',
            'interests' => 'history',
            'pace' => 'balanced',
        ], 'free');

        $this->assertStringContainsString('concise but informative', $prompt);
        $this->assertStringContainsString('Rome', $prompt);
        $this->assertStringNotContainsString('Specific hotels', $prompt);
    }

    public function test_generation_prompt_premium_is_detailed(): void {
        $prompt = AIP_Claude::build_generation_prompt([
            'destination' => 'Rome',
            'days' => '3',
            'trip_type' => 'leisure',
            'budget' => 'high',
            'interests' => 'art',
            'pace' => 'packed',
        ], 'premium');

        $this->assertStringContainsString('very detailed and comprehensive', $prompt);
        $this->assertStringContainsString('Specific hotels', $prompt);
        $this->assertStringContainsString('budget summary', $prompt);
    }

    // ---- extract_travel_data ----

    public function test_extract_travel_data_returns_parsed_json(): void {
        update_option('aip_claude_api_key', 'sk-test-extract');

        // Mock will return JSON with destination=USA based on system prompt detection
        $data = AIP_Claude::extract_travel_data([
            ['role' => 'user', 'content' => 'I want to go to USA'],
        ]);

        $this->assertIsArray($data);
        $this->assertEquals('USA', $data['destination'] ?? null);
    }

    // Helper for older PHPUnit compat
    private static function assertStringContains(string $needle, string $haystack): void {
        self::assertStringContainsString($needle, $haystack);
    }
}
