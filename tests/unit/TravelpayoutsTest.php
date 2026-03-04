<?php
use PHPUnit\Framework\TestCase;

class TravelpayoutsTest extends TestCase {

    public function test_get_links_returns_empty_without_token(): void {
        update_option('aip_travelpayouts_token', '');
        $links = AIP_Travelpayouts::get_links('Paris');
        $this->assertEmpty($links);
    }

    public function test_get_links_returns_three_links(): void {
        update_option('aip_travelpayouts_token', 'test_token_123');

        $links = AIP_Travelpayouts::get_links('Paris');

        $this->assertCount(3, $links);

        // Hotels
        $this->assertEquals('hotels', $links[0]['category']);
        $this->assertStringContainsString('hotellook.com', $links[0]['url']);
        $this->assertStringContainsString('test_token_123', $links[0]['url']);
        $this->assertStringContainsString('Paris', $links[0]['url']);

        // Flights
        $this->assertEquals('flights', $links[1]['category']);
        $this->assertStringContainsString('aviasales.com', $links[1]['url']);

        // Activities
        $this->assertEquals('activities', $links[2]['category']);
        $this->assertStringContainsString('getyourguide.com', $links[2]['url']);
    }

    protected function tearDown(): void {
        delete_option('aip_travelpayouts_token');
    }
}
