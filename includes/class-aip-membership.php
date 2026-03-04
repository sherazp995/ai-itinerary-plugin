<?php
if (!defined('ABSPATH')) exit;

class AIP_Membership {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {}

    public static function user_has_premium($user_id) {
        if (!$user_id) return false;

        // Check Paid Member Subscriptions
        if (function_exists('pms_is_member_of_plan')) {
            $plan_id = get_option('aip_pms_plan_id', '');
            if ($plan_id && pms_is_member_of_plan($user_id, (int) $plan_id)) {
                return true;
            }
            if (pms_is_member_of_plan($user_id, 'premium') || pms_is_member_of_plan($user_id, 'Premium')) {
                return true;
            }
        }

        // Manual premium grant fallback
        if (get_user_meta($user_id, 'aip_premium_access', true) === 'yes') {
            return true;
        }

        return false;
    }
}
