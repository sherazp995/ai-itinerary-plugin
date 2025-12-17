<?php
/**
 * Authentication Handler - Google OAuth & User Management
 *
 * @package AI_Itinerary_Generator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AIP_Auth {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_nopriv_aip_register_user', array($this, 'register_user'));
        add_action('wp_ajax_nopriv_aip_google_auth', array($this, 'google_auth'));
        add_action('login_form', array($this, 'add_google_login_button'));
        add_action('register_form', array($this, 'add_google_login_button'));
    }
    
    /**
     * Register new user
     */
    public function register_user() {
        check_ajax_referer('aip_nonce', 'nonce');
        
        $first_name = sanitize_text_field($_POST['first_name'] ?? '');
        $last_name = sanitize_text_field($_POST['last_name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Validation
        if (empty($first_name) || empty($last_name) || empty($email)) {
            wp_send_json_error(array('message' => __('Please fill in all required fields', 'ai-itinerary-plugin')));
        }
        
        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Invalid email address', 'ai-itinerary-plugin')));
        }
        
        if (email_exists($email)) {
            wp_send_json_error(array('message' => __('Email already registered', 'ai-itinerary-plugin')));
        }
        
        if (strlen($password) < 6) {
            wp_send_json_error(array('message' => __('Password must be at least 6 characters', 'ai-itinerary-plugin')));
        }
        
        // Create user
        $username = sanitize_user($email);
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error(array('message' => $user_id->get_error_message()));
        }
        
        // Update user meta
        wp_update_user(array(
            'ID' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => $first_name . ' ' . $last_name,
        ));
        
        // Initialize user meta in our custom table
        AIP_Database::get_user_meta($user_id);
        
        // Log in the user
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);
        
        // Log analytics
        AIP_Database::log_analytics('user_registered', array(
            'method' => 'email',
        ), $user_id);
        
        wp_send_json_success(array(
            'message' => __('Registration successful!', 'ai-itinerary-plugin'),
            'user_id' => $user_id,
            'user_name' => $first_name . ' ' . $last_name,
        ));
    }
    
    /**
     * Google OAuth authentication
     */
    public function google_auth() {
        check_ajax_referer('aip_nonce', 'nonce');
        
        $google_token = sanitize_text_field($_POST['google_token'] ?? '');
        
        if (empty($google_token)) {
            wp_send_json_error(array('message' => __('Invalid Google token', 'ai-itinerary-plugin')));
        }
        
        $google_client_id = get_option('aip_google_client_id');
        
        if (empty($google_client_id)) {
            wp_send_json_error(array('message' => __('Google OAuth not configured', 'ai-itinerary-plugin')));
        }
        
        // Verify Google token
        $response = wp_remote_get('https://oauth2.googleapis.com/tokeninfo?id_token=' . $google_token);
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => __('Google authentication failed', 'ai-itinerary-plugin')));
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        // Verify the token is for our app
        if (!isset($body['aud']) || $body['aud'] !== $google_client_id) {
            wp_send_json_error(array('message' => __('Invalid Google token', 'ai-itinerary-plugin')));
        }
        
        $email = sanitize_email($body['email'] ?? '');
        $first_name = sanitize_text_field($body['given_name'] ?? '');
        $last_name = sanitize_text_field($body['family_name'] ?? '');
        $google_id = sanitize_text_field($body['sub'] ?? '');
        
        if (empty($email)) {
            wp_send_json_error(array('message' => __('Email not provided by Google', 'ai-itinerary-plugin')));
        }
        
        // Check if user exists
        $user = get_user_by('email', $email);
        
        if (!$user) {
            // Create new user
            $username = sanitize_user($email);
            $random_password = wp_generate_password(12, false);
            $user_id = wp_create_user($username, $random_password, $email);
            
            if (is_wp_error($user_id)) {
                wp_send_json_error(array('message' => __('Failed to create user account', 'ai-itinerary-plugin')));
            }
            
            // Update user meta
            wp_update_user(array(
                'ID' => $user_id,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'display_name' => $first_name . ' ' . $last_name,
            ));
            
            // Store Google ID
            update_user_meta($user_id, 'aip_google_id', $google_id);
            
            // Initialize user meta in our custom table
            AIP_Database::get_user_meta($user_id);
            
            // Log analytics
            AIP_Database::log_analytics('user_registered', array(
                'method' => 'google',
            ), $user_id);
            
            $user = get_user_by('id', $user_id);
        } else {
            $user_id = $user->ID;
            
            // Update Google ID if not set
            if (!get_user_meta($user_id, 'aip_google_id', true)) {
                update_user_meta($user_id, 'aip_google_id', $google_id);
            }
        }
        
        // Log in the user
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);
        
        // Log analytics
        AIP_Database::log_analytics('user_login', array(
            'method' => 'google',
        ), $user_id);
        
        wp_send_json_success(array(
            'message' => __('Login successful!', 'ai-itinerary-plugin'),
            'user_id' => $user_id,
            'user_name' => $user->display_name,
        ));
    }
    
    /**
     * Add Google login button to login/register forms
     */
    public function add_google_login_button() {
        $google_client_id = get_option('aip_google_client_id');
        
        if (empty($google_client_id)) {
            return;
        }
        ?>
        <div class="aip-google-login" style="text-align: center; margin: 20px 0;">
            <p><?php _e('Or sign in with:', 'ai-itinerary-plugin'); ?></p>
            <div id="aip-google-signin-button"></div>
        </div>
        
        <script src="https://accounts.google.com/gsi/client" async defer></script>
        <script>
        window.addEventListener('load', function() {
            if (typeof google !== 'undefined') {
                google.accounts.id.initialize({
                    client_id: '<?php echo esc_js($google_client_id); ?>',
                    callback: handleGoogleSignIn
                });
                google.accounts.id.renderButton(
                    document.getElementById('aip-google-signin-button'),
                    { theme: 'outline', size: 'large', width: 300 }
                );
            }
        });
        
        function handleGoogleSignIn(response) {
            jQuery.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'aip_google_auth',
                    nonce: '<?php echo wp_create_nonce('aip_nonce'); ?>',
                    google_token: response.credential
                },
                success: function(data) {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert(data.data.message);
                    }
                }
            });
        }
        </script>
        <?php
    }
}

