<?php
use PHPUnit\Framework\TestCase;

class RestApiTest extends TestCase {

    protected function setUp(): void {
        MockClaude::enable();
        update_option('aip_claude_api_key', 'sk-test-integration');
        update_option('aip_free_itinerary_limit', 3);

        // Initialize REST API routes
        do_action('rest_api_init');
    }

    protected function tearDown(): void {
        MockClaude::disable();
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}aip_itineraries WHERE title LIKE 'TEST_%' OR title LIKE '%Days'");
        $wpdb->query("DELETE FROM {$wpdb->prefix}aip_conversations WHERE session_id LIKE 'test_%'");
        $wpdb->query("DELETE FROM {$wpdb->prefix}aip_analytics WHERE event_type LIKE 'test_%' OR event_type = 'itinerary_generated'");
    }

    // ---- Chat Endpoints ----

    public function test_chat_message_returns_bot_response(): void {
        $request = new WP_REST_Request('POST', '/aip/v1/chat/message');
        $request->set_param('message', 'I want to go to USA');

        // Set guest session cookie
        $_COOKIE['aip_session'] = 'test_rest_' . wp_generate_uuid4();

        $api = AIP_REST_API::get_instance();
        $response = $api->chat_message($request);
        $data = $response->get_data();

        $this->assertArrayHasKey('bot_message', $data);
        $this->assertNotEmpty($data['bot_message']);
        $this->assertArrayHasKey('collected_data', $data);
        $this->assertArrayHasKey('ready', $data);
        $this->assertArrayHasKey('missing', $data);
    }

    public function test_chat_message_empty_returns_error(): void {
        $request = new WP_REST_Request('POST', '/aip/v1/chat/message');
        $request->set_param('message', '');

        $api = AIP_REST_API::get_instance();
        $response = $api->chat_message($request);

        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertEquals('empty_message', $response->get_error_code());
    }

    public function test_chat_reset_returns_greeting(): void {
        $_COOKIE['aip_session'] = 'test_reset_' . wp_generate_uuid4();

        $request = new WP_REST_Request('POST', '/aip/v1/chat/reset');
        $api = AIP_REST_API::get_instance();
        $response = $api->chat_reset($request);
        $data = $response->get_data();

        $this->assertArrayHasKey('bot_message', $data);
        $this->assertStringContainsString('travel assistant', $data['bot_message']);
        $this->assertEmpty($data['collected_data']);
        $this->assertFalse($data['ready']);
    }

    // ---- User Status ----

    public function test_user_status_guest(): void {
        wp_set_current_user(0);
        $_COOKIE['aip_session'] = 'test_status_' . wp_generate_uuid4();

        $request = new WP_REST_Request('GET', '/aip/v1/user/status');
        $api = AIP_REST_API::get_instance();
        $response = $api->user_status($request);
        $data = $response->get_data();

        $this->assertFalse($data['logged_in']);
        $this->assertFalse($data['has_premium']);
        $this->assertEquals(3, $data['free_limit']);
        $this->assertEquals(0, $data['free_used']);
        $this->assertEquals(3, $data['free_remaining']);
    }

    public function test_user_status_logged_in(): void {
        // Create test user
        $user_id = wp_insert_user([
            'user_login' => 'aip_test_user_' . wp_rand(1000, 9999),
            'user_email' => 'aip_test_' . wp_rand(1000, 9999) . '@example.com',
            'user_pass' => 'test123456',
            'first_name' => 'Test',
            'last_name' => 'User',
        ]);

        wp_set_current_user($user_id);

        $request = new WP_REST_Request('GET', '/aip/v1/user/status');
        $api = AIP_REST_API::get_instance();
        $response = $api->user_status($request);
        $data = $response->get_data();

        $this->assertTrue($data['logged_in']);
        $this->assertEquals($user_id, $data['user_id']);
        $this->assertEquals('Test', $data['first_name']);
        $this->assertArrayHasKey('has_premium', $data);
        $this->assertArrayHasKey('free_remaining', $data);

        // Clean up
        wp_delete_user($user_id);
        wp_set_current_user(0);
    }

    // ---- Itinerary Generation ----

    public function test_itinerary_generate_without_conversation_returns_error(): void {
        $_COOKIE['aip_session'] = 'test_nogen_' . wp_generate_uuid4();

        $request = new WP_REST_Request('POST', '/aip/v1/itinerary/generate');
        $request->set_param('type', 'free');

        $api = AIP_REST_API::get_instance();
        $response = $api->itinerary_generate($request);

        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertEquals('no_conversation', $response->get_error_code());
    }

    public function test_itinerary_generate_with_conversation(): void {
        $session_id = 'test_gen_' . wp_generate_uuid4();
        $_COOKIE['aip_session'] = $session_id;

        // Plant a conversation with all data collected
        AIP_Database::save_conversation(0, $session_id, [
            ['role' => 'user', 'content' => 'Go to New York'],
        ], [
            'destination' => 'New York',
            'days' => '3',
            'trip_type' => 'leisure',
            'budget' => 'medium',
            'interests' => 'sightseeing',
            'pace' => 'balanced',
        ], true);

        $request = new WP_REST_Request('POST', '/aip/v1/itinerary/generate');
        $request->set_param('type', 'free');

        $api = AIP_REST_API::get_instance();
        $response = $api->itinerary_generate($request);
        $data = $response->get_data();

        $this->assertArrayHasKey('itinerary_id', $data);
        $this->assertArrayHasKey('itinerary', $data);
        $this->assertGreaterThan(0, $data['itinerary_id']);
        $this->assertEquals('free', $data['type']);

        // Verify itinerary was saved in DB
        $row = AIP_Database::get_itinerary($data['itinerary_id']);
        $this->assertEquals('completed', $row->status);
        $this->assertEquals('New York', $row->destination);
    }

    public function test_itinerary_generate_premium_requires_auth(): void {
        wp_set_current_user(0);
        $_COOKIE['aip_session'] = 'test_prem_' . wp_generate_uuid4();

        AIP_Database::save_conversation(0, $_COOKIE['aip_session'], [], [
            'destination' => 'Paris', 'days' => '5', 'trip_type' => 'leisure',
            'budget' => 'high', 'interests' => 'art', 'pace' => 'relaxed',
        ], true);

        $request = new WP_REST_Request('POST', '/aip/v1/itinerary/generate');
        $request->set_param('type', 'premium');

        $api = AIP_REST_API::get_instance();
        $response = $api->itinerary_generate($request);

        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertEquals('auth_required', $response->get_error_code());
    }

    // ---- Itinerary Get ----

    public function test_itinerary_get_returns_data(): void {
        $id = AIP_Database::save_itinerary([
            'user_id' => 0,
            'title' => 'TEST_Get Test',
            'destination' => 'Rome',
            'days' => 4,
            'data' => '{"summary":"Test trip"}',
            'status' => 'completed',
        ]);

        $request = new WP_REST_Request('GET', '/aip/v1/itinerary/' . $id);
        $request->set_param('id', $id);

        $api = AIP_REST_API::get_instance();
        $response = $api->itinerary_get($request);
        $data = $response->get_data();

        $this->assertEquals('Rome', $data->destination);
        $this->assertEquals('Test trip', $data->data['summary']);
    }

    public function test_itinerary_get_not_found(): void {
        $request = new WP_REST_Request('GET', '/aip/v1/itinerary/999999');
        $request->set_param('id', 999999);

        $api = AIP_REST_API::get_instance();
        $response = $api->itinerary_get($request);

        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertEquals('not_found', $response->get_error_code());
    }

    // ---- Affiliate ----

    public function test_affiliate_links_returns_data(): void {
        update_option('aip_travelpayouts_token', 'test_tp_token');

        $request = new WP_REST_Request('GET', '/aip/v1/affiliate/Paris');
        $request->set_param('destination', 'Paris');

        $api = AIP_REST_API::get_instance();
        $response = $api->affiliate_links($request);
        $data = $response->get_data();

        $this->assertCount(3, $data);
        $this->assertEquals('hotels', $data[0]['category']);

        delete_option('aip_travelpayouts_token');
    }

    public function test_affiliate_click_tracking(): void {
        $request = new WP_REST_Request('POST', '/aip/v1/affiliate/click');
        $request->set_param('provider', 'travelpayouts');
        $request->set_param('category', 'hotels');
        $request->set_param('destination', 'TEST_Click_Paris');
        $request->set_param('url', 'https://example.com/hotel');

        $api = AIP_REST_API::get_instance();
        $response = $api->affiliate_click($request);
        $data = $response->get_data();

        $this->assertTrue($data['tracked']);

        // Verify in DB
        global $wpdb;
        $row = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}aip_affiliate_clicks WHERE destination = 'TEST_Click_Paris' ORDER BY id DESC LIMIT 1");
        $this->assertNotNull($row);
        $this->assertEquals('travelpayouts', $row->provider);
    }

    // ---- Admin ----

    public function test_admin_analytics_returns_data(): void {
        $request = new WP_REST_Request('GET', '/aip/v1/admin/analytics');
        $request->set_param('days', 30);

        $api = AIP_REST_API::get_instance();
        $response = $api->admin_analytics($request);
        $data = $response->get_data();

        $this->assertArrayHasKey('total_itineraries', $data);
        $this->assertArrayHasKey('daily_stats', $data);
        $this->assertArrayHasKey('popular_destinations', $data);
    }

    // ---- PDF ----

    public function test_pdf_generate_not_found(): void {
        $request = new WP_REST_Request('POST', '/aip/v1/pdf/generate');
        $request->set_param('itinerary_id', 999999);

        $api = AIP_REST_API::get_instance();
        $response = $api->pdf_generate($request);

        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertEquals('not_found', $response->get_error_code());
    }
}
