<?php
/**
 * PDF Generation Handler
 *
 * @package AI_Itinerary_Generator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AIP_PDF {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_aip_generate_pdf', array($this, 'generate_pdf'));
        add_action('wp_ajax_nopriv_aip_generate_pdf', array($this, 'generate_pdf_guest'));
    }
    
    /**
     * Generate PDF for itinerary
     */
    public function generate_pdf() {
        check_ajax_referer('aip_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $itinerary_id = absint($_POST['itinerary_id'] ?? 0);
        
        if (!$itinerary_id) {
            wp_send_json_error(array('message' => __('Invalid itinerary ID', 'ai-itinerary-plugin')));
        }
        
        // Get itinerary
        $itinerary = AIP_Database::get_itinerary($itinerary_id, $user_id);
        
        if (!$itinerary) {
            wp_send_json_error(array('message' => __('Itinerary not found', 'ai-itinerary-plugin')));
        }
        
        // Check if payment required for premium
        if ($itinerary->type === 'premium' && $itinerary->status !== 'paid') {
            wp_send_json_error(array(
                'message' => __('Payment required for premium itinerary', 'ai-itinerary-plugin'),
                'requires_payment' => true
            ));
        }
        
        $pdf_url = $this->create_pdf($itinerary);
        
        if ($pdf_url) {
            // Update itinerary with PDF URL
            AIP_Database::update_itinerary($itinerary_id, array('pdf_url' => $pdf_url), $user_id);
            
            wp_send_json_success(array('pdf_url' => $pdf_url));
        } else {
            wp_send_json_error(array('message' => __('Failed to generate PDF', 'ai-itinerary-plugin')));
        }
    }
    
    /**
     * Generate PDF for guest users
     */
    public function generate_pdf_guest() {
        check_ajax_referer('aip_nonce', 'nonce');
        
        $itinerary_data = json_decode(stripslashes($_POST['itinerary_data'] ?? '{}'), true);
        
        if (empty($itinerary_data)) {
            wp_send_json_error(array('message' => __('No itinerary data', 'ai-itinerary-plugin')));
        }
        
        // Create temporary itinerary object
        $itinerary = (object) array(
            'title' => $itinerary_data['destination'] ?? 'My Itinerary',
            'destination' => $itinerary_data['destination'] ?? '',
            'days' => $itinerary_data['days'] ?? 1,
            'data' => $itinerary_data,
            'type' => 'free',
        );
        
        $pdf_url = $this->create_pdf($itinerary);
        
        if ($pdf_url) {
            wp_send_json_success(array('pdf_url' => $pdf_url));
        } else {
            wp_send_json_error(array('message' => __('Failed to generate PDF', 'ai-itinerary-plugin')));
        }
    }
    
    /**
     * Create PDF file
     */
    private function create_pdf($itinerary) {
        $style = get_option('aip_pdf_style', 'modern');
        $html = $this->generate_html($itinerary, $style);
        
        // Use WordPress default method or external library
        // For this example, we'll use basic HTML to PDF conversion
        // In production, you might want to use libraries like TCPDF, DOMPDF, or mPDF
        
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/ai-itineraries';
        
        if (!file_exists($pdf_dir)) {
            wp_mkdir_p($pdf_dir);
        }
        
        $filename = 'itinerary-' . time() . '-' . wp_generate_password(8, false) . '.pdf';
        $filepath = $pdf_dir . '/' . $filename;
        
        // For basic implementation, we'll save as HTML (you should integrate a proper PDF library)
        // This is a placeholder - integrate TCPDF, DOMPDF, or similar
        file_put_contents($filepath . '.html', $html);
        
        // Return URL
        $pdf_url = $upload_dir['baseurl'] . '/ai-itineraries/' . $filename . '.html';
        
        // TODO: Integrate proper PDF generation library
        // For now, returning HTML file as placeholder
        
        return $pdf_url;
    }
    
    /**
     * Generate HTML for PDF
     */
    private function generate_html($itinerary, $style) {
        $data = $itinerary->data;
        $logo_url = get_option('aip_logo_url', '');
        $primary_color = get_option('aip_primary_color', '#2271b1');
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title><?php echo esc_html($itinerary->title); ?></title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    margin: 40px;
                    color: #333;
                }
                .header {
                    text-align: center;
                    margin-bottom: 40px;
                    border-bottom: 3px solid <?php echo esc_attr($primary_color); ?>;
                    padding-bottom: 20px;
                }
                .logo {
                    max-width: 200px;
                    margin-bottom: 20px;
                }
                h1 {
                    color: <?php echo esc_attr($primary_color); ?>;
                    margin: 0;
                }
                .destination {
                    font-size: 24px;
                    color: #666;
                    margin-top: 10px;
                }
                .day {
                    margin: 30px 0;
                    page-break-inside: avoid;
                }
                .day-header {
                    background: <?php echo esc_attr($primary_color); ?>;
                    color: white;
                    padding: 15px;
                    border-radius: 5px;
                    margin-bottom: 15px;
                }
                .activity {
                    margin: 15px 0;
                    padding: 15px;
                    background: #f5f5f5;
                    border-left: 4px solid <?php echo esc_attr($primary_color); ?>;
                }
                .activity-time {
                    font-weight: bold;
                    color: <?php echo esc_attr($primary_color); ?>;
                    text-transform: uppercase;
                    font-size: 12px;
                }
                .meals {
                    margin: 20px 0;
                    padding: 15px;
                    background: #fff9e6;
                    border-radius: 5px;
                }
                .tips {
                    margin-top: 40px;
                    padding: 20px;
                    background: #e8f5e9;
                    border-radius: 5px;
                }
                <?php if ($style === 'luxury'): ?>
                body { font-family: 'Georgia', serif; }
                .day-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
                <?php elseif ($style === 'minimal'): ?>
                .day-header { background: #333; }
                .activity { background: white; border: 1px solid #ddd; }
                <?php endif; ?>
            </style>
        </head>
        <body>
            <div class="header">
                <?php if ($logo_url): ?>
                    <img src="<?php echo esc_url($logo_url); ?>" class="logo" alt="Logo">
                <?php endif; ?>
                <h1><?php echo esc_html($itinerary->title); ?></h1>
                <div class="destination"><?php echo esc_html($itinerary->destination); ?> - <?php echo esc_html($itinerary->days); ?> Days</div>
            </div>
            
            <?php if (isset($data['itinerary']) && is_array($data['itinerary'])): ?>
                <?php foreach ($data['itinerary'] as $day): ?>
                    <div class="day">
                        <div class="day-header">
                            <strong>Day <?php echo esc_html($day['day']); ?></strong>
                            <?php if (isset($day['title'])): ?>
                                - <?php echo esc_html($day['title']); ?>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (isset($day['activities']) && is_array($day['activities'])): ?>
                            <?php foreach ($day['activities'] as $activity): ?>
                                <div class="activity">
                                    <?php if (isset($activity['time'])): ?>
                                        <div class="activity-time"><?php echo esc_html($activity['time']); ?></div>
                                    <?php endif; ?>
                                    <h3><?php echo esc_html($activity['title'] ?? ''); ?></h3>
                                    <?php if (isset($activity['description'])): ?>
                                        <p><?php echo nl2br(esc_html($activity['description'])); ?></p>
                                    <?php endif; ?>
                                    <?php if (isset($activity['location'])): ?>
                                        <p><strong>Location:</strong> <?php echo esc_html($activity['location']); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <?php if (isset($day['meals'])): ?>
                            <div class="meals">
                                <h4>Meals</h4>
                                <?php if (isset($day['meals']['breakfast'])): ?>
                                    <p><strong>Breakfast:</strong> <?php echo esc_html($day['meals']['breakfast']); ?></p>
                                <?php endif; ?>
                                <?php if (isset($day['meals']['lunch'])): ?>
                                    <p><strong>Lunch:</strong> <?php echo esc_html($day['meals']['lunch']); ?></p>
                                <?php endif; ?>
                                <?php if (isset($day['meals']['dinner'])): ?>
                                    <p><strong>Dinner:</strong> <?php echo esc_html($day['meals']['dinner']); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="content">
                    <?php echo nl2br(esc_html($data['content'] ?? 'No content available')); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($data['tips']) && is_array($data['tips'])): ?>
                <div class="tips">
                    <h3>Travel Tips</h3>
                    <ul>
                        <?php foreach ($data['tips'] as $tip): ?>
                            <li><?php echo esc_html($tip); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div style="margin-top: 50px; text-align: center; color: #999; font-size: 12px;">
                <p>Generated by AI Travel Itinerary Generator</p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}

