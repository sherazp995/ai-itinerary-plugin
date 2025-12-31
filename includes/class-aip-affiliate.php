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
     * Add affiliate links to itinerary data (flexible, platform-agnostic)
     */
    public static function add_affiliate_links($itinerary_data, $destination, $dates = array()) {
        $button_style = get_option('aip_affiliate_button_style', 'hidden');
        
        // Get active affiliate providers from database
        $providers = AIP_Database::get_active_affiliate_providers();
        
        $links = array();
        
        foreach ($providers as $provider) {
            $link = self::generate_affiliate_link($provider, $destination, $dates);
            if ($link) {
                $links[$provider->slug] = $link;
            }
        }
        
        // Add links to itinerary data
        $itinerary_data['affiliate_links'] = $links;
        $itinerary_data['affiliate_style'] = $button_style;
        
        return $itinerary_data;
    }
    
    /**
     * Generate affiliate link from provider template
     * This is the core method that makes the system flexible and extensible
     */
    private static function generate_affiliate_link($provider, $destination, $dates = array()) {
        // Skip if no affiliate ID configured
        if (empty($provider->affiliate_id)) {
            return null;
        }
        
        // Prepare replacement variables
        $replacements = array(
            '{affiliate_id}' => urlencode($provider->affiliate_id),
            '{destination}' => urlencode($destination),
            '{destination_slug}' => sanitize_title($destination),
        );
        
        // Add date parameters if available
        if (!empty($dates['check_in'])) {
            $replacements['{check_in}'] = urlencode($dates['check_in']);
        }
        if (!empty($dates['check_out'])) {
            $replacements['{check_out}'] = urlencode($dates['check_out']);
        }
        
        // Handle IATA codes (for flights)
        if (!empty($dates['destination_iata'])) {
            $replacements['{destination_iata}'] = urlencode($dates['destination_iata']);
        }
        if (!empty($dates['origin'])) {
            $replacements['{origin}'] = urlencode($dates['origin']);
        }
        
        // Generate URL from template
        $url = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $provider->link_template
        );
        
        return array(
            'url' => $url,
            'label' => __($provider->label, 'ai-itinerary-plugin'),
            'provider' => $provider->name,
            'icon' => $provider->icon,
            'category' => $provider->category,
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

