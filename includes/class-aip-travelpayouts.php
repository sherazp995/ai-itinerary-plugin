<?php
if (!defined('ABSPATH')) exit;

class AIP_Travelpayouts {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {}

    public static function get_links($destination) {
        $token = get_option('aip_travelpayouts_token', '');
        if (empty($token)) return [];

        $dest_encoded = urlencode($destination);

        $links = [];

        // Hotels via Hotellook
        $links[] = [
            'provider' => 'travelpayouts',
            'category' => 'hotels',
            'label'    => __('Book Hotels', 'ai-itinerary'),
            'icon'     => 'hotel',
            'url'      => "https://search.hotellook.com/?marker={$token}&destination={$dest_encoded}",
        ];

        // Flights via Aviasales
        $links[] = [
            'provider' => 'travelpayouts',
            'category' => 'flights',
            'label'    => __('Find Flights', 'ai-itinerary'),
            'icon'     => 'flight',
            'url'      => "https://www.aviasales.com/?marker={$token}&destination={$dest_encoded}",
        ];

        // Activities
        $links[] = [
            'provider' => 'travelpayouts',
            'category' => 'activities',
            'label'    => __('Book Activities', 'ai-itinerary'),
            'icon'     => 'activity',
            'url'      => "https://www.getyourguide.com/s/?partner_id={$token}&q={$dest_encoded}",
        ];

        return $links;
    }
}
