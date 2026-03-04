<?php
use PHPUnit\Framework\TestCase;

class SkyscannerTest extends TestCase {

    public function test_search_flights_empty_without_credentials(): void {
        update_option('aip_skyscanner_sid', '');
        update_option('aip_skyscanner_auth_token', '');

        $result = AIP_Skyscanner::search_flights('Tokyo');
        $this->assertEmpty($result);
    }

    public function test_search_flights_returns_link(): void {
        update_option('aip_skyscanner_sid', 'test_sid');
        update_option('aip_skyscanner_auth_token', 'test_auth');

        $result = AIP_Skyscanner::search_flights('Tokyo');

        $this->assertEquals('skyscanner', $result['provider']);
        $this->assertEquals('flights', $result['category']);
        $this->assertStringContainsString('skyscanner.com', $result['url']);
        $this->assertStringContainsString('Tokyo', $result['url']);
    }

    public function test_search_flights_with_date(): void {
        update_option('aip_skyscanner_sid', 'sid');
        update_option('aip_skyscanner_auth_token', 'auth');

        $result = AIP_Skyscanner::search_flights('London', '2026-06-15');

        $this->assertStringContainsString('odate=2026-06-15', $result['url']);
    }

    protected function tearDown(): void {
        delete_option('aip_skyscanner_sid');
        delete_option('aip_skyscanner_auth_token');
    }
}
