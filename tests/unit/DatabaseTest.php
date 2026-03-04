<?php
use PHPUnit\Framework\TestCase;

class DatabaseTest extends TestCase {

    public static function setUpBeforeClass(): void {
        // Ensure tables exist
        AIP_Database::create_tables();
    }

    protected function tearDown(): void {
        global $wpdb;
        // Clean up test data after each test
        $wpdb->query("DELETE FROM {$wpdb->prefix}aip_itineraries WHERE title LIKE 'TEST_%'");
        $wpdb->query("DELETE FROM {$wpdb->prefix}aip_conversations WHERE session_id LIKE 'test_%'");
        $wpdb->query("DELETE FROM {$wpdb->prefix}aip_analytics WHERE event_type LIKE 'test_%'");
        $wpdb->query("DELETE FROM {$wpdb->prefix}aip_affiliate_clicks WHERE destination LIKE 'TEST_%'");
    }

    // ---- Tables ----

    public function test_tables_exist(): void {
        global $wpdb;
        $tables = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}aip_%'");

        $this->assertContains($wpdb->prefix . 'aip_itineraries', $tables);
        $this->assertContains($wpdb->prefix . 'aip_conversations', $tables);
        $this->assertContains($wpdb->prefix . 'aip_analytics', $tables);
        $this->assertContains($wpdb->prefix . 'aip_affiliate_clicks', $tables);
    }

    // ---- Itinerary CRUD ----

    public function test_save_itinerary_returns_id(): void {
        $id = AIP_Database::save_itinerary([
            'user_id' => 0,
            'title' => 'TEST_Paris Trip',
            'destination' => 'Paris',
            'days' => 5,
            'type' => 'free',
            'language' => 'en',
            'data' => '{"test": true}',
            'status' => 'completed',
        ]);

        $this->assertGreaterThan(0, $id);
    }

    public function test_get_itinerary_returns_row(): void {
        $id = AIP_Database::save_itinerary([
            'user_id' => 0,
            'title' => 'TEST_Tokyo Trip',
            'destination' => 'Tokyo',
            'days' => 7,
            'data' => '{}',
        ]);

        $row = AIP_Database::get_itinerary($id);

        $this->assertNotNull($row);
        $this->assertEquals('Tokyo', $row->destination);
        $this->assertEquals(7, $row->days);
    }

    public function test_get_itinerary_not_found(): void {
        $row = AIP_Database::get_itinerary(999999);
        $this->assertNull($row);
    }

    public function test_update_itinerary(): void {
        $id = AIP_Database::save_itinerary([
            'user_id' => 0,
            'title' => 'TEST_Berlin Trip',
            'destination' => 'Berlin',
            'days' => 3,
            'data' => '{}',
            'status' => 'generating',
        ]);

        AIP_Database::update_itinerary($id, ['status' => 'completed', 'data' => '{"done":true}']);

        $row = AIP_Database::get_itinerary($id);
        $this->assertEquals('completed', $row->status);
    }

    public function test_get_user_itineraries(): void {
        // Create a unique user_id for this test
        $user_id = 99990;

        AIP_Database::save_itinerary(['user_id' => $user_id, 'title' => 'TEST_Trip1', 'destination' => 'A', 'days' => 1, 'data' => '{}']);
        AIP_Database::save_itinerary(['user_id' => $user_id, 'title' => 'TEST_Trip2', 'destination' => 'B', 'days' => 2, 'data' => '{}']);

        $results = AIP_Database::get_user_itineraries($user_id);

        $this->assertCount(2, $results);
        // Just verify both are returned (order depends on insert timing)
        $titles = array_map(fn($r) => $r->title, $results);
        $this->assertContains('TEST_Trip1', $titles);
        $this->assertContains('TEST_Trip2', $titles);
    }

    // ---- Conversations ----

    public function test_save_and_get_conversation(): void {
        $session_id = 'test_' . wp_generate_uuid4();
        $messages = [['role' => 'user', 'content' => 'hello']];
        $collected = ['destination' => 'Rome'];

        AIP_Database::save_conversation(0, $session_id, $messages, $collected, false);

        $convo = AIP_Database::get_conversation(0, $session_id);

        $this->assertNotNull($convo);
        $this->assertEquals($session_id, $convo->session_id);

        $decoded_messages = json_decode($convo->messages, true);
        $this->assertEquals('hello', $decoded_messages[0]['content']);

        $decoded_data = json_decode($convo->collected_data, true);
        $this->assertEquals('Rome', $decoded_data['destination']);
    }

    public function test_save_conversation_upserts(): void {
        $session_id = 'test_' . wp_generate_uuid4();

        AIP_Database::save_conversation(0, $session_id, [['role' => 'user', 'content' => 'v1']], [], false);
        AIP_Database::save_conversation(0, $session_id, [['role' => 'user', 'content' => 'v2']], ['destination' => 'X'], true);

        $convo = AIP_Database::get_conversation(0, $session_id);
        $msgs = json_decode($convo->messages, true);

        $this->assertEquals('v2', $msgs[0]['content']);
        $this->assertEquals(1, $convo->ready_to_generate);
    }

    public function test_clear_conversation(): void {
        $session_id = 'test_' . wp_generate_uuid4();
        AIP_Database::save_conversation(0, $session_id, [['role' => 'user', 'content' => 'hi']], [], false);

        AIP_Database::clear_conversation(0, $session_id);

        $convo = AIP_Database::get_conversation(0, $session_id);
        $this->assertNull($convo);
    }

    // ---- Analytics ----

    public function test_log_event(): void {
        global $wpdb;

        AIP_Database::log_event('test_event', ['key' => 'value'], 0, null);

        $row = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}aip_analytics WHERE event_type = 'test_event' ORDER BY id DESC LIMIT 1");

        $this->assertNotNull($row);
        $data = json_decode($row->event_data, true);
        $this->assertEquals('value', $data['key']);
    }

    public function test_get_analytics_returns_structure(): void {
        $result = AIP_Database::get_analytics(30);

        $this->assertArrayHasKey('total_itineraries', $result);
        $this->assertArrayHasKey('itinerary_types', $result);
        $this->assertArrayHasKey('daily_stats', $result);
        $this->assertArrayHasKey('popular_destinations', $result);
        $this->assertArrayHasKey('affiliate_clicks', $result);
    }

    // ---- Affiliate Clicks ----

    public function test_log_affiliate_click(): void {
        global $wpdb;

        AIP_Database::log_affiliate_click([
            'user_id' => 0,
            'itinerary_id' => null,
            'provider' => 'travelpayouts',
            'category' => 'hotels',
            'destination' => 'TEST_London',
            'link_url' => 'https://example.com/hotel',
        ]);

        $row = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}aip_affiliate_clicks WHERE destination = 'TEST_London' ORDER BY id DESC LIMIT 1");

        $this->assertNotNull($row);
        $this->assertEquals('travelpayouts', $row->provider);
        $this->assertEquals('hotels', $row->category);
    }

    // ---- User Free Count ----

    public function test_user_free_count_starts_at_zero(): void {
        $count = AIP_Database::get_user_free_count(99991);
        $this->assertEquals(0, $count);
    }

    public function test_increment_user_free_count(): void {
        $user_id = 99992;
        // Reset
        delete_user_meta($user_id, 'aip_free_count');
        delete_user_meta($user_id, 'aip_free_count_reset');

        AIP_Database::increment_user_free_count($user_id);
        AIP_Database::increment_user_free_count($user_id);

        $count = AIP_Database::get_user_free_count($user_id);
        $this->assertEquals(2, $count);
    }

    // ---- Guest Free Count ----

    public function test_guest_free_count(): void {
        $session = 'test_guest_' . wp_generate_uuid4();

        $this->assertEquals(0, AIP_Database::get_guest_free_count($session));

        AIP_Database::increment_guest_free_count($session);
        AIP_Database::increment_guest_free_count($session);
        AIP_Database::increment_guest_free_count($session);

        $this->assertEquals(3, AIP_Database::get_guest_free_count($session));

        // Clean up
        delete_transient('aip_guest_count_' . $session);
    }
}
