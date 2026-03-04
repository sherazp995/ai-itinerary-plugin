<?php
if (!defined('ABSPATH')) exit;

class AIP_Skyscanner {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {}

    public static function search_flights($destination, $date = '') {
        $sid = get_option('aip_skyscanner_sid', '');
        $auth = get_option('aip_skyscanner_auth_token', '');

        if (empty($sid) || empty($auth)) return [];

        $dest_encoded = urlencode($destination);
        $date_param = $date ? "&odate={$date}" : '';

        return [
            'provider' => 'skyscanner',
            'category' => 'flights',
            'label'    => __('Compare Flights on Skyscanner', 'ai-itinerary'),
            'icon'     => 'flight_compare',
            'url'      => "https://www.skyscanner.com/transport/flights/?q={$dest_encoded}{$date_param}",
        ];
    }
}
