<?php
use PHPUnit\Framework\TestCase;

class MembershipTest extends TestCase {

    protected function tearDown(): void {
        // Clean up test user meta
        delete_user_meta(99993, 'aip_premium_access');
    }

    public function test_no_user_returns_false(): void {
        $this->assertFalse(AIP_Membership::user_has_premium(0));
    }

    public function test_user_meta_fallback_grants_premium(): void {
        $user_id = 99993;
        update_user_meta($user_id, 'aip_premium_access', 'yes');

        $this->assertTrue(AIP_Membership::user_has_premium($user_id));
    }

    public function test_user_without_meta_no_premium(): void {
        $user_id = 99994;
        delete_user_meta($user_id, 'aip_premium_access');

        $this->assertFalse(AIP_Membership::user_has_premium($user_id));
    }
}
