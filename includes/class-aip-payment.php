<?php
/**
 * Payment Integration Handler (Stripe & PayPal)
 *
 * @package AI_Itinerary_Generator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AIP_Payment {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_aip_create_payment_intent', array($this, 'create_payment_intent'));
        add_action('wp_ajax_aip_verify_payment', array($this, 'verify_payment'));
        add_action('wp_ajax_aip_create_paypal_order', array($this, 'create_paypal_order'));
        add_action('wp_ajax_aip_verify_paypal_payment', array($this, 'verify_paypal_payment'));
    }
    
    /**
     * Create Stripe Payment Intent
     */
    public function create_payment_intent() {
        check_ajax_referer('aip_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $itinerary_id = absint($_POST['itinerary_id'] ?? 0);
        
        // Check if account required
        if (get_option('aip_require_account', 'yes') === 'yes' && !$user_id) {
            wp_send_json_error(array('message' => __('Please log in or create an account', 'ai-itinerary-plugin')));
        }
        
        if (!$itinerary_id) {
            wp_send_json_error(array('message' => __('Invalid itinerary ID', 'ai-itinerary-plugin')));
        }
        
        $amount = floatval(get_option('aip_premium_price', 5.00));
        $currency = get_option('aip_currency', 'USD');
        
        // Get Stripe secret key
        $stripe_secret = get_option('aip_stripe_secret_key');
        if (empty($stripe_secret)) {
            wp_send_json_error(array('message' => __('Stripe not configured', 'ai-itinerary-plugin')));
        }
        
        // Create payment intent
        $response = wp_remote_post('https://api.stripe.com/v1/payment_intents', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $stripe_secret,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'body' => array(
                'amount' => round($amount * 100), // Convert to cents
                'currency' => strtolower($currency),
                'metadata' => array(
                    'itinerary_id' => $itinerary_id,
                    'user_id' => $user_id,
                ),
            ),
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => __('Payment initialization failed', 'ai-itinerary-plugin')));
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['client_secret'])) {
            wp_send_json_success(array(
                'client_secret' => $body['client_secret'],
                'publishable_key' => get_option('aip_stripe_public_key'),
            ));
        } else {
            error_log('Stripe Error: ' . print_r($body, true));
            wp_send_json_error(array('message' => __('Payment initialization failed', 'ai-itinerary-plugin')));
        }
    }
    
    /**
     * Verify Stripe payment
     */
    public function verify_payment() {
        check_ajax_referer('aip_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $payment_intent_id = sanitize_text_field($_POST['payment_intent_id'] ?? '');
        $itinerary_id = absint($_POST['itinerary_id'] ?? 0);
        
        if (empty($payment_intent_id) || !$itinerary_id) {
            wp_send_json_error(array('message' => __('Invalid payment data', 'ai-itinerary-plugin')));
        }
        
        // Get Stripe secret key
        $stripe_secret = get_option('aip_stripe_secret_key');
        
        // Verify payment with Stripe
        $response = wp_remote_get('https://api.stripe.com/v1/payment_intents/' . $payment_intent_id, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $stripe_secret,
            ),
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => __('Payment verification failed', 'ai-itinerary-plugin')));
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['status']) && $body['status'] === 'succeeded') {
            // Save payment record
            $payment_id = AIP_Database::save_payment(array(
                'user_id' => $user_id ? $user_id : 0,
                'itinerary_id' => $itinerary_id,
                'amount' => $body['amount'] / 100,
                'currency' => strtoupper($body['currency']),
                'payment_method' => 'stripe',
                'transaction_id' => $payment_intent_id,
                'status' => 'completed',
                'payment_data' => $body,
            ));
            
            // Update itinerary status
            AIP_Database::update_itinerary($itinerary_id, array('status' => 'paid'), $user_id);
            
            // Update user meta
            if ($user_id) {
                $user_meta = AIP_Database::get_user_meta($user_id);
                AIP_Database::update_user_meta($user_id, array(
                    'total_spent' => $user_meta->total_spent + ($body['amount'] / 100),
                ));
            }
            
            // Log analytics
            AIP_Database::log_analytics('payment_completed', array(
                'amount' => $body['amount'] / 100,
                'currency' => $body['currency'],
                'method' => 'stripe',
            ), $user_id, $itinerary_id);
            
            wp_send_json_success(array(
                'message' => __('Payment successful!', 'ai-itinerary-plugin'),
                'payment_id' => $payment_id,
            ));
        } else {
            wp_send_json_error(array('message' => __('Payment not completed', 'ai-itinerary-plugin')));
        }
    }
    
    /**
     * Create PayPal order
     */
    public function create_paypal_order() {
        check_ajax_referer('aip_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $itinerary_id = absint($_POST['itinerary_id'] ?? 0);
        
        if (get_option('aip_require_account', 'yes') === 'yes' && !$user_id) {
            wp_send_json_error(array('message' => __('Please log in or create an account', 'ai-itinerary-plugin')));
        }
        
        if (!$itinerary_id) {
            wp_send_json_error(array('message' => __('Invalid itinerary ID', 'ai-itinerary-plugin')));
        }
        
        $amount = floatval(get_option('aip_premium_price', 5.00));
        $currency = get_option('aip_currency', 'USD');
        
        $client_id = get_option('aip_paypal_client_id');
        $client_secret = get_option('aip_paypal_client_secret');
        
        if (empty($client_id) || empty($client_secret)) {
            wp_send_json_error(array('message' => __('PayPal not configured', 'ai-itinerary-plugin')));
        }
        
        // Get access token
        $auth_response = wp_remote_post('https://api-m.paypal.com/v1/oauth2/token', array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'body' => 'grant_type=client_credentials',
        ));
        
        if (is_wp_error($auth_response)) {
            wp_send_json_error(array('message' => __('PayPal authentication failed', 'ai-itinerary-plugin')));
        }
        
        $auth_body = json_decode(wp_remote_retrieve_body($auth_response), true);
        $access_token = $auth_body['access_token'] ?? '';
        
        if (empty($access_token)) {
            wp_send_json_error(array('message' => __('PayPal authentication failed', 'ai-itinerary-plugin')));
        }
        
        // Create order
        $order_response = wp_remote_post('https://api-m.paypal.com/v2/checkout/orders', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'intent' => 'CAPTURE',
                'purchase_units' => array(
                    array(
                        'amount' => array(
                            'currency_code' => $currency,
                            'value' => number_format($amount, 2, '.', ''),
                        ),
                        'description' => 'Premium Travel Itinerary',
                    ),
                ),
            )),
        ));
        
        if (is_wp_error($order_response)) {
            wp_send_json_error(array('message' => __('PayPal order creation failed', 'ai-itinerary-plugin')));
        }
        
        $order_body = json_decode(wp_remote_retrieve_body($order_response), true);
        
        if (isset($order_body['id'])) {
            wp_send_json_success(array(
                'order_id' => $order_body['id'],
                'client_id' => $client_id,
            ));
        } else {
            error_log('PayPal Error: ' . print_r($order_body, true));
            wp_send_json_error(array('message' => __('PayPal order creation failed', 'ai-itinerary-plugin')));
        }
    }
    
    /**
     * Verify PayPal payment
     */
    public function verify_paypal_payment() {
        check_ajax_referer('aip_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $order_id = sanitize_text_field($_POST['order_id'] ?? '');
        $itinerary_id = absint($_POST['itinerary_id'] ?? 0);
        
        if (empty($order_id) || !$itinerary_id) {
            wp_send_json_error(array('message' => __('Invalid payment data', 'ai-itinerary-plugin')));
        }
        
        $client_id = get_option('aip_paypal_client_id');
        $client_secret = get_option('aip_paypal_client_secret');
        
        // Get access token
        $auth_response = wp_remote_post('https://api-m.paypal.com/v1/oauth2/token', array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ),
            'body' => 'grant_type=client_credentials',
        ));
        
        $auth_body = json_decode(wp_remote_retrieve_body($auth_response), true);
        $access_token = $auth_body['access_token'] ?? '';
        
        // Verify order
        $verify_response = wp_remote_get('https://api-m.paypal.com/v2/checkout/orders/' . $order_id, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ),
        ));
        
        if (is_wp_error($verify_response)) {
            wp_send_json_error(array('message' => __('Payment verification failed', 'ai-itinerary-plugin')));
        }
        
        $verify_body = json_decode(wp_remote_retrieve_body($verify_response), true);
        
        if (isset($verify_body['status']) && $verify_body['status'] === 'COMPLETED') {
            $amount = floatval($verify_body['purchase_units'][0]['amount']['value'] ?? 0);
            $currency = $verify_body['purchase_units'][0]['amount']['currency_code'] ?? 'USD';
            
            // Save payment record
            $payment_id = AIP_Database::save_payment(array(
                'user_id' => $user_id ? $user_id : 0,
                'itinerary_id' => $itinerary_id,
                'amount' => $amount,
                'currency' => $currency,
                'payment_method' => 'paypal',
                'transaction_id' => $order_id,
                'status' => 'completed',
                'payment_data' => $verify_body,
            ));
            
            // Update itinerary status
            AIP_Database::update_itinerary($itinerary_id, array('status' => 'paid'), $user_id);
            
            // Update user meta
            if ($user_id) {
                $user_meta = AIP_Database::get_user_meta($user_id);
                AIP_Database::update_user_meta($user_id, array(
                    'total_spent' => $user_meta->total_spent + $amount,
                ));
            }
            
            // Log analytics
            AIP_Database::log_analytics('payment_completed', array(
                'amount' => $amount,
                'currency' => $currency,
                'method' => 'paypal',
            ), $user_id, $itinerary_id);
            
            wp_send_json_success(array(
                'message' => __('Payment successful!', 'ai-itinerary-plugin'),
                'payment_id' => $payment_id,
            ));
        } else {
            wp_send_json_error(array('message' => __('Payment not completed', 'ai-itinerary-plugin')));
        }
    }
}

