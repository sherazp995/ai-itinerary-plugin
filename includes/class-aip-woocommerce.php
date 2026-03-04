<?php
if (!defined('ABSPATH')) exit;

class AIP_WooCommerce {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('woocommerce_order_status_completed', [$this, 'handle_order_complete']);
        add_action('woocommerce_order_status_processing', [$this, 'handle_order_complete']);
    }

    public static function create_products() {
        if (!class_exists('WooCommerce')) return;

        $product_id = get_option('aip_wc_product_id');
        if (!$product_id || !wc_get_product($product_id)) {
            $product = new WC_Product_Simple();
            $product->set_name(__('Premium Travel Itinerary', 'ai-itinerary'));
            $product->set_description(__('AI-generated detailed travel itinerary with hotels, restaurants, activities, and PDF export.', 'ai-itinerary'));
            $product->set_regular_price(get_option('aip_premium_price', '5.00'));
            $product->set_virtual(true);
            $product->set_catalog_visibility('hidden');
            $product->set_status('publish');
            $product->save();

            update_option('aip_wc_product_id', $product->get_id());
        }
    }

    public static function get_checkout_url($trip_data) {
        if (!class_exists('WooCommerce')) return home_url();

        $product_id = get_option('aip_wc_product_id');
        if (!$product_id) return home_url();

        WC()->cart->empty_cart();
        WC()->cart->add_to_cart($product_id, 1, 0, [], [
            'aip_trip_data' => $trip_data,
        ]);

        return wc_get_checkout_url();
    }

    public function handle_order_complete($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        $product_id = get_option('aip_wc_product_id');

        foreach ($order->get_items() as $item) {
            if ($item->get_product_id() == $product_id) {
                $trip_data = $item->get_meta('aip_trip_data');
                if (empty($trip_data)) continue;

                $user_id = $order->get_user_id();

                $prompt = AIP_Claude::build_generation_prompt($trip_data, 'premium');
                $messages = [['role' => 'user', 'content' => $prompt]];
                $system = "You are a travel itinerary generator. Return ONLY valid JSON. No markdown.";

                $result = AIP_Claude::send_message($messages, $system, 8192);

                $itinerary_data = [];
                if (!is_wp_error($result)) {
                    $content = $result['content'][0]['text'] ?? '';
                    $itinerary_data = json_decode($content, true) ?: [];
                }

                $itinerary_id = AIP_Database::save_itinerary([
                    'user_id'     => $user_id,
                    'title'       => sprintf('%s - %d Days (Premium)', $trip_data['destination'], $trip_data['days']),
                    'destination' => $trip_data['destination'],
                    'days'        => (int) $trip_data['days'],
                    'type'        => 'premium',
                    'data'        => $itinerary_data,
                    'wc_order_id' => $order_id,
                    'status'      => !empty($itinerary_data) ? 'completed' : 'failed',
                ]);

                AIP_Database::log_event('premium_purchase', [
                    'order_id' => $order_id,
                    'amount'   => $order->get_total(),
                ], $user_id, $itinerary_id);

                $order->update_meta_data('aip_itinerary_id', $itinerary_id);
                $order->save();
            }
        }
    }
}
