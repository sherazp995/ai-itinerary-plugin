<?php
/**
 * Affiliate Integration Handler
 *
 * @package AI_Itinerary_Generator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AIP_Affiliate {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Constructor
    }
    
    /**
     * Add affiliate links to itinerary data
     */
    public static function add_affiliate_links($itinerary_data, $destination) {
        $button_style = get_option('aip_affiliate_button_style', 'hidden');
        
        // Generate affiliate links
        $links = array(
            'booking' => self::get_booking_link($destination),
            'skyscanner' => self::get_skyscanner_link($destination),
            'getyourguide' => self::get_getyourguide_link($destination),
        );
        
        // Add links to itinerary data
        $itinerary_data['affiliate_links'] = $links;
        $itinerary_data['affiliate_style'] = $button_style;
        
        return $itinerary_data;
    }
    
    /**
     * Get Booking.com affiliate link
     */
    private static function get_booking_link($destination) {
        $affiliate_id = get_option('aip_booking_affiliate_id');
        
        if (empty($affiliate_id)) {
            return null;
        }
        
        $destination_encoded = urlencode($destination);
        
        return array(
            'url' => "https://www.booking.com/searchresults.html?ss={$destination_encoded}&aid={$affiliate_id}",
            'label' => __('Find Hotels', 'ai-itinerary-plugin'),
            'provider' => 'Booking.com',
            'icon' => '🏨',
        );
    }
    
    /**
     * Get Skyscanner affiliate link
     */
    private static function get_skyscanner_link($destination) {
        $affiliate_id = get_option('aip_skyscanner_affiliate_id');
        
        if (empty($affiliate_id)) {
            return null;
        }
        
        $destination_encoded = urlencode($destination);
        
        return array(
            'url' => "https://www.skyscanner.com/transport/flights/everywhere/{$destination_encoded}/?associateid={$affiliate_id}",
            'label' => __('Book Flights', 'ai-itinerary-plugin'),
            'provider' => 'Skyscanner',
            'icon' => '✈️',
        );
    }
    
    /**
     * Get GetYourGuide affiliate link
     */
    private static function get_getyourguide_link($destination) {
        $affiliate_id = get_option('aip_getyourguide_affiliate_id');
        
        if (empty($affiliate_id)) {
            return null;
        }
        
        $destination_encoded = urlencode($destination);
        
        return array(
            'url' => "https://www.getyourguide.com/s/?q={$destination_encoded}&partner_id={$affiliate_id}",
            'label' => __('Book Activities', 'ai-itinerary-plugin'),
            'provider' => 'GetYourGuide',
            'icon' => '🎯',
        );
    }
    
    /**
     * Render affiliate links HTML
     */
    public static function render_affiliate_links($links, $style = 'hidden') {
        if (empty($links)) {
            return '';
        }
        
        ob_start();
        
        if ($style === 'visible') {
            ?>
            <div class="aip-affiliate-links visible">
                <h4><?php _e('Plan Your Trip:', 'ai-itinerary-plugin'); ?></h4>
                <div class="aip-affiliate-buttons">
                    <?php foreach ($links as $key => $link): ?>
                        <?php if ($link && isset($link['url'])): ?>
                            <a href="<?php echo esc_url($link['url']); ?>" 
                               class="aip-affiliate-button" 
                               target="_blank" 
                               rel="noopener noreferrer nofollow">
                                <span class="icon"><?php echo esc_html($link['icon']); ?></span>
                                <span class="label"><?php echo esc_html($link['label']); ?></span>
                                <span class="provider"><?php echo esc_html($link['provider']); ?></span>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php
        } else {
            // Hidden affiliate links - integrated into text
            ?>
            <div class="aip-affiliate-links hidden" style="display: none;" data-links='<?php echo esc_attr(json_encode($links)); ?>'></div>
            <?php
        }
        
        return ob_get_clean();
    }
}

