<?php
if (!defined('ABSPATH')) exit;

use Dompdf\Dompdf;
use Dompdf\Options;

class AIP_PDF {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {}

    public static function generate($itinerary_data, $type = 'free') {
        $primary_color = get_option('aip_primary_color', '#2271b1');
        $bot_name = get_option('aip_bot_name', 'Travel Buddy');
        $logo_url = get_option('aip_logo_url', '');

        $html = self::build_html($itinerary_data, $type, [
            'primary_color' => $primary_color,
            'bot_name'      => $bot_name,
            'logo_url'      => $logo_url,
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private static function build_html($data, $type, $config) {
        $destination = esc_html($data['destination'] ?? 'Travel Itinerary');
        $days = (int) ($data['days'] ?? 0);
        $summary = esc_html($data['summary'] ?? '');
        $primary = esc_attr($config['primary_color']);
        $bot_name = esc_html($config['bot_name']);

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8">';
        $html .= '<style>';
        $html .= "body { font-family: Helvetica, Arial, sans-serif; color: #333; margin: 0; padding: 30px; }";
        $html .= ".header { background: {$primary}; color: white; padding: 30px; text-align: center; margin: -30px -30px 30px; }";
        $html .= ".header h1 { margin: 0; font-size: 28px; }";
        $html .= ".header p { margin: 5px 0 0; opacity: 0.9; }";
        $html .= ".summary { background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 20px; }";
        $html .= ".day { margin-bottom: 25px; page-break-inside: avoid; }";
        $html .= ".day-header { background: {$primary}; color: white; padding: 10px 15px; font-size: 16px; font-weight: bold; }";
        $html .= ".activity { padding: 8px 15px; border-bottom: 1px solid #eee; }";
        $html .= ".activity .time { color: {$primary}; font-weight: bold; display: inline-block; width: 70px; }";
        $html .= ".meal { padding: 8px 15px; background: #fff8e1; }";
        $html .= ".hotel { padding: 10px 15px; background: #e3f2fd; }";
        $html .= ".tips { background: #f1f8e9; padding: 15px; margin-top: 20px; }";
        $html .= ".tips h3 { margin-top: 0; }";
        $html .= ".budget { background: #fce4ec; padding: 15px; margin-top: 15px; }";
        $html .= ".footer { text-align: center; margin-top: 30px; color: #999; font-size: 12px; }";
        $html .= '</style></head><body>';

        // Header
        $html .= "<div class='header'>";
        $html .= "<h1>{$destination}</h1>";
        $html .= "<p>{$days}-Day Itinerary by {$bot_name}</p>";
        $html .= "</div>";

        // Summary
        if ($summary) {
            $html .= "<div class='summary'><p>{$summary}</p></div>";
        }

        // Days
        $itinerary = $data['itinerary'] ?? [];
        foreach ($itinerary as $day) {
            $day_num = (int) ($day['day'] ?? 0);
            $day_title = esc_html($day['title'] ?? "Day {$day_num}");

            $html .= "<div class='day'>";
            $html .= "<div class='day-header'>Day {$day_num}: {$day_title}</div>";

            // Activities
            foreach (($day['activities'] ?? []) as $activity) {
                $time = esc_html($activity['time'] ?? '');
                $title = esc_html($activity['title'] ?? '');
                $desc = esc_html($activity['description'] ?? '');
                $cost = esc_html($activity['cost_estimate'] ?? '');

                $html .= "<div class='activity'>";
                $html .= "<span class='time'>{$time}</span> <strong>{$title}</strong>";
                if ($desc) $html .= " &mdash; {$desc}";
                if ($cost) $html .= " <em>({$cost})</em>";
                $html .= "</div>";
            }

            // Meals
            $meals = $day['meals'] ?? [];
            foreach (['breakfast', 'lunch', 'dinner'] as $meal_type) {
                if (!empty($meals[$meal_type])) {
                    $meal = $meals[$meal_type];
                    $name = esc_html(is_array($meal) ? ($meal['name'] ?? '') : $meal);
                    $cuisine = esc_html(is_array($meal) ? ($meal['cuisine'] ?? '') : '');
                    $price = esc_html(is_array($meal) ? ($meal['price_range'] ?? '') : '');

                    $html .= "<div class='meal'>";
                    $html .= ucfirst($meal_type) . ": <strong>{$name}</strong>";
                    if ($cuisine) $html .= " ({$cuisine})";
                    if ($price) $html .= " &mdash; {$price}";
                    $html .= "</div>";
                }
            }

            // Accommodation (premium only)
            $hotel = $day['accommodation'] ?? null;
            if ($hotel && $type === 'premium') {
                $hotel_name = esc_html(is_array($hotel) ? ($hotel['name'] ?? '') : $hotel);
                $hotel_area = esc_html(is_array($hotel) ? ($hotel['area'] ?? '') : '');
                $hotel_price = esc_html(is_array($hotel) ? ($hotel['price_range'] ?? '') : '');

                $html .= "<div class='hotel'>";
                $html .= "Stay: <strong>{$hotel_name}</strong>";
                if ($hotel_area) $html .= " ({$hotel_area})";
                if ($hotel_price) $html .= " &mdash; {$hotel_price}";
                $html .= "</div>";
            }

            $html .= "</div>";
        }

        // Tips
        $tips = $data['tips'] ?? [];
        if (!empty($tips)) {
            $html .= "<div class='tips'><h3>Travel Tips</h3><ul>";
            foreach ($tips as $tip) {
                $html .= "<li>" . esc_html($tip) . "</li>";
            }
            $html .= "</ul></div>";
        }

        // Budget (premium only)
        if ($type === 'premium' && !empty($data['budget_summary'])) {
            $budget = $data['budget_summary'];
            $html .= "<div class='budget'><h3>Budget Summary</h3>";
            $html .= "<p><strong>Estimated Total: " . esc_html($budget['total_estimate'] ?? '') . "</strong></p>";
            if (!empty($budget['breakdown'])) {
                $html .= "<ul>";
                foreach ($budget['breakdown'] as $cat => $amount) {
                    $html .= "<li>" . ucfirst(esc_html($cat)) . ": " . esc_html($amount) . "</li>";
                }
                $html .= "</ul>";
            }
            $html .= "</div>";
        }

        // Footer
        $html .= "<div class='footer'>";
        $html .= "<p>Generated by {$bot_name} | yoiner.gamercity.io</p>";
        $html .= "</div>";

        $html .= '</body></html>';
        return $html;
    }
}
