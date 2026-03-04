<?php
if (!defined('ABSPATH')) exit;

class AIP_REST_API {

    private static $instance = null;
    const NAMESPACE = 'aip/v1';

    public static function get_instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {

        // --- Chat ---
        register_rest_route(self::NAMESPACE, '/chat/message', [
            'methods'  => 'POST',
            'callback' => [$this, 'chat_message'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/chat/reset', [
            'methods'  => 'POST',
            'callback' => [$this, 'chat_reset'],
            'permission_callback' => '__return_true',
        ]);

        // --- Itinerary ---
        register_rest_route(self::NAMESPACE, '/itinerary/generate', [
            'methods'  => 'POST',
            'callback' => [$this, 'itinerary_generate'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/itinerary/(?P<id>\d+)', [
            'methods'  => 'GET',
            'callback' => [$this, 'itinerary_get'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/itineraries', [
            'methods'  => 'GET',
            'callback' => [$this, 'itineraries_list'],
            'permission_callback' => function() { return is_user_logged_in(); },
        ]);

        register_rest_route(self::NAMESPACE, '/itinerary/(?P<id>\d+)/save', [
            'methods'  => 'POST',
            'callback' => [$this, 'itinerary_save'],
            'permission_callback' => function() { return is_user_logged_in(); },
        ]);

        // --- PDF ---
        register_rest_route(self::NAMESPACE, '/pdf/generate', [
            'methods'  => 'POST',
            'callback' => [$this, 'pdf_generate'],
            'permission_callback' => '__return_true',
        ]);

        // --- User ---
        register_rest_route(self::NAMESPACE, '/user/status', [
            'methods'  => 'GET',
            'callback' => [$this, 'user_status'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/auth/login', [
            'methods'  => 'POST',
            'callback' => [$this, 'auth_login'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/auth/register', [
            'methods'  => 'POST',
            'callback' => [$this, 'auth_register'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/auth/google', [
            'methods'  => 'POST',
            'callback' => [$this, 'auth_google'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/auth/logout', [
            'methods'  => 'POST',
            'callback' => [$this, 'auth_logout'],
            'permission_callback' => function() { return is_user_logged_in(); },
        ]);

        // --- Affiliate ---
        register_rest_route(self::NAMESPACE, '/affiliate/(?P<destination>[a-zA-Z0-9\-\s,]+)', [
            'methods'  => 'GET',
            'callback' => [$this, 'affiliate_links'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/affiliate/click', [
            'methods'  => 'POST',
            'callback' => [$this, 'affiliate_click'],
            'permission_callback' => '__return_true',
        ]);

        // --- Admin ---
        register_rest_route(self::NAMESPACE, '/admin/analytics', [
            'methods'  => 'GET',
            'callback' => [$this, 'admin_analytics'],
            'permission_callback' => function() { return current_user_can('manage_options'); },
        ]);
    }

    // ============================================================
    // CHAT ENDPOINTS
    // ============================================================

    public function chat_message(WP_REST_Request $request) {
        $message = sanitize_text_field($request->get_param('message'));
        if (empty($message)) {
            return new WP_Error('empty_message', __('Message cannot be empty', 'ai-itinerary'), ['status' => 400]);
        }

        $user_id = get_current_user_id();
        $session_id = $user_id ? null : AIP_Database::get_guest_session_id();

        // Get conversation state
        $convo = AIP_Database::get_conversation($user_id, $session_id);
        $messages = $convo ? json_decode($convo->messages, true) : [];
        $collected = $convo ? json_decode($convo->collected_data, true) : [];

        if (!is_array($messages)) $messages = [];
        if (!is_array($collected)) $collected = [];

        // Add user message
        $messages[] = ['role' => 'user', 'content' => $message];

        // Build system prompt
        $system_prompt = AIP_Claude::build_conversation_prompt($collected);

        // Get Claude response
        $result = AIP_Claude::send_message($messages, $system_prompt, 500);

        if (is_wp_error($result)) {
            return new WP_Error('ai_error', $result->get_error_message(), ['status' => 500]);
        }

        $bot_message = $result['content'][0]['text'] ?? '';

        // Add bot message to history
        $messages[] = ['role' => 'assistant', 'content' => $bot_message];

        // Extract data from conversation using Claude
        $new_data = AIP_Claude::extract_travel_data($messages);
        $collected = array_merge($collected, $new_data);

        // Check if all required fields collected
        $required = ['destination', 'days', 'trip_type', 'budget', 'interests', 'pace'];
        $missing = [];
        foreach ($required as $field) {
            if (empty($collected[$field])) $missing[] = $field;
        }
        $ready = empty($missing);

        // Save conversation state
        AIP_Database::save_conversation($user_id, $session_id, $messages, $collected, $ready);

        return rest_ensure_response([
            'bot_message'    => $bot_message,
            'collected_data' => $collected,
            'ready'          => $ready,
            'missing'        => $missing,
        ]);
    }

    public function chat_reset(WP_REST_Request $request) {
        $user_id = get_current_user_id();
        $session_id = $user_id ? null : AIP_Database::get_guest_session_id();

        AIP_Database::clear_conversation($user_id, $session_id);

        $bot_name = get_option('aip_bot_name', 'Travel Buddy');

        return rest_ensure_response([
            'bot_message' => sprintf(
                __("Hi! I'm %s, your AI travel assistant. Where would you like to go?", 'ai-itinerary'),
                $bot_name
            ),
            'collected_data' => [],
            'ready' => false,
        ]);
    }

    // ============================================================
    // ITINERARY ENDPOINTS
    // ============================================================

    public function itinerary_generate(WP_REST_Request $request) {
        $user_id = get_current_user_id();
        $session_id = $user_id ? null : AIP_Database::get_guest_session_id();
        $type = sanitize_text_field($request->get_param('type') ?? 'free');

        // Get conversation data
        $convo = AIP_Database::get_conversation($user_id, $session_id);
        if (!$convo) {
            return new WP_Error('no_conversation', __('No conversation found. Please start a chat first.', 'ai-itinerary'), ['status' => 400]);
        }

        $collected = json_decode($convo->collected_data, true);
        if (empty($collected['destination'])) {
            return new WP_Error('incomplete', __('Please complete the conversation first.', 'ai-itinerary'), ['status' => 400]);
        }

        // Check free limit
        if ($type === 'free') {
            $limit = (int) get_option('aip_free_itinerary_limit', 3);
            $used = $user_id
                ? AIP_Database::get_user_free_count($user_id)
                : AIP_Database::get_guest_free_count($session_id);

            if ($used >= $limit) {
                return new WP_Error('limit_reached', __('Free itinerary limit reached. Try premium!', 'ai-itinerary'), ['status' => 403]);
            }
        }

        // Check premium access
        if ($type === 'premium') {
            if (!$user_id) {
                return new WP_Error('auth_required', __('Please log in for premium itineraries.', 'ai-itinerary'), ['status' => 401]);
            }
            $has_premium = AIP_Membership::user_has_premium($user_id);
            if (!$has_premium) {
                $checkout_url = AIP_WooCommerce::get_checkout_url($collected);
                return rest_ensure_response([
                    'needs_payment' => true,
                    'checkout_url'  => $checkout_url,
                ]);
            }
        }

        // Create itinerary record
        $itinerary_id = AIP_Database::save_itinerary([
            'user_id'     => $user_id,
            'title'       => sprintf('%s - %d Days', $collected['destination'], $collected['days']),
            'destination' => $collected['destination'],
            'days'        => (int) $collected['days'],
            'type'        => $type,
            'language'    => get_option('aip_default_language', 'en'),
            'data'        => '{}',
            'status'      => 'generating',
        ]);

        // Generate with Claude
        $prompt = AIP_Claude::build_generation_prompt($collected, $type);
        $messages = [['role' => 'user', 'content' => $prompt]];
        $system = "You are a travel itinerary generator. Return ONLY valid JSON. No markdown, no explanation.";

        $result = AIP_Claude::send_message($messages, $system, $type === 'premium' ? 8192 : 4096);

        if (is_wp_error($result)) {
            AIP_Database::update_itinerary($itinerary_id, ['status' => 'failed']);
            return $result;
        }

        $content = $result['content'][0]['text'] ?? '';
        $itinerary_data = json_decode($content, true);

        if (!$itinerary_data) {
            // Try to extract JSON from markdown code block
            if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $matches)) {
                $itinerary_data = json_decode($matches[1], true);
            }
        }

        if (!$itinerary_data) {
            AIP_Database::update_itinerary($itinerary_id, ['status' => 'failed']);
            return new WP_Error('parse_error', __('Failed to parse itinerary. Please try again.', 'ai-itinerary'), ['status' => 500]);
        }

        // Save completed itinerary
        AIP_Database::update_itinerary($itinerary_id, [
            'data'   => wp_json_encode($itinerary_data),
            'status' => 'completed',
        ]);

        // Increment free count
        if ($type === 'free') {
            if ($user_id) {
                AIP_Database::increment_user_free_count($user_id);
            } else {
                AIP_Database::increment_guest_free_count($session_id);
            }
        }

        // Log analytics
        AIP_Database::log_event('itinerary_generated', [
            'type'        => $type,
            'destination' => $collected['destination'],
            'days'        => $collected['days'],
        ], $user_id, $itinerary_id);

        // Clear conversation
        AIP_Database::clear_conversation($user_id, $session_id);

        // Get affiliate links
        $affiliates = AIP_Travelpayouts::get_links($collected['destination']);

        return rest_ensure_response([
            'itinerary_id'    => $itinerary_id,
            'itinerary'       => $itinerary_data,
            'type'            => $type,
            'affiliate_links' => $affiliates,
        ]);
    }

    public function itinerary_get(WP_REST_Request $request) {
        $id = (int) $request->get_param('id');
        $itinerary = AIP_Database::get_itinerary($id);

        if (!$itinerary) {
            return new WP_Error('not_found', __('Itinerary not found', 'ai-itinerary'), ['status' => 404]);
        }

        $itinerary->data = json_decode($itinerary->data, true);
        return rest_ensure_response($itinerary);
    }

    public function itineraries_list(WP_REST_Request $request) {
        $user_id = get_current_user_id();
        $itineraries = AIP_Database::get_user_itineraries($user_id);

        foreach ($itineraries as &$item) {
            $item->data = json_decode($item->data, true);
        }

        return rest_ensure_response($itineraries);
    }

    public function itinerary_save(WP_REST_Request $request) {
        $id = (int) $request->get_param('id');
        $title = sanitize_text_field($request->get_param('title'));

        $itinerary = AIP_Database::get_itinerary($id);
        if (!$itinerary || $itinerary->user_id != get_current_user_id()) {
            return new WP_Error('forbidden', __('Not your itinerary', 'ai-itinerary'), ['status' => 403]);
        }

        if ($title) {
            AIP_Database::update_itinerary($id, ['title' => $title]);
        }

        return rest_ensure_response(['saved' => true]);
    }

    // ============================================================
    // PDF ENDPOINT
    // ============================================================

    public function pdf_generate(WP_REST_Request $request) {
        $itinerary_id = (int) $request->get_param('itinerary_id');
        $itinerary = AIP_Database::get_itinerary($itinerary_id);

        if (!$itinerary) {
            return new WP_Error('not_found', __('Itinerary not found', 'ai-itinerary'), ['status' => 404]);
        }

        $data = json_decode($itinerary->data, true);
        $pdf_content = AIP_PDF::generate($data, $itinerary->type);

        AIP_Database::log_event('pdf_downloaded', [
            'itinerary_id' => $itinerary_id,
            'type'         => $itinerary->type,
        ], get_current_user_id(), $itinerary_id);

        // Return PDF as download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="itinerary-' . $itinerary_id . '.pdf"');
        echo $pdf_content;
        exit;
    }

    // ============================================================
    // AUTH ENDPOINTS
    // ============================================================

    public function user_status(WP_REST_Request $request) {
        $user_id = get_current_user_id();
        $session_id = $user_id ? null : AIP_Database::get_guest_session_id();
        $limit = (int) get_option('aip_free_itinerary_limit', 3);

        if ($user_id) {
            $user = wp_get_current_user();
            $used = AIP_Database::get_user_free_count($user_id);
            $has_premium = AIP_Membership::user_has_premium($user_id);

            return rest_ensure_response([
                'logged_in'      => true,
                'user_id'        => $user_id,
                'email'          => $user->user_email,
                'first_name'     => $user->first_name,
                'last_name'      => $user->last_name,
                'display_name'   => $user->display_name,
                'has_premium'    => $has_premium,
                'free_limit'     => $limit,
                'free_used'      => $used,
                'free_remaining' => max(0, $limit - $used),
            ]);
        }

        $used = AIP_Database::get_guest_free_count($session_id);
        return rest_ensure_response([
            'logged_in'      => false,
            'has_premium'    => false,
            'free_limit'     => $limit,
            'free_used'      => $used,
            'free_remaining' => max(0, $limit - $used),
        ]);
    }

    public function auth_login(WP_REST_Request $request) {
        $email = sanitize_email($request->get_param('email'));
        $password = $request->get_param('password');

        if (empty($email) || empty($password)) {
            return new WP_Error('missing_fields', __('Email and password are required', 'ai-itinerary'), ['status' => 400]);
        }

        $user = wp_authenticate($email, $password);
        if (is_wp_error($user)) {
            return new WP_Error('auth_failed', __('Invalid email or password', 'ai-itinerary'), ['status' => 401]);
        }

        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);

        return rest_ensure_response([
            'success' => true,
            'user_id' => $user->ID,
            'name'    => $user->display_name,
        ]);
    }

    public function auth_register(WP_REST_Request $request) {
        $first_name = sanitize_text_field($request->get_param('first_name'));
        $last_name = sanitize_text_field($request->get_param('last_name'));
        $email = sanitize_email($request->get_param('email'));
        $password = $request->get_param('password');

        if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
            return new WP_Error('missing_fields', __('All fields are required', 'ai-itinerary'), ['status' => 400]);
        }

        if (strlen($password) < 6) {
            return new WP_Error('weak_password', __('Password must be at least 6 characters', 'ai-itinerary'), ['status' => 400]);
        }

        if (email_exists($email)) {
            return new WP_Error('email_exists', __('An account with this email already exists', 'ai-itinerary'), ['status' => 409]);
        }

        $user_id = wp_create_user($email, $password, $email);
        if (is_wp_error($user_id)) {
            return $user_id;
        }

        wp_update_user([
            'ID'           => $user_id,
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'display_name' => $first_name . ' ' . $last_name,
        ]);

        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);

        AIP_Database::log_event('user_registered', ['method' => 'email'], $user_id);

        return rest_ensure_response([
            'success' => true,
            'user_id' => $user_id,
            'name'    => $first_name . ' ' . $last_name,
        ]);
    }

    public function auth_google(WP_REST_Request $request) {
        $token = sanitize_text_field($request->get_param('credential'));
        if (empty($token)) {
            return new WP_Error('no_token', __('Google credential missing', 'ai-itinerary'), ['status' => 400]);
        }

        // Verify Google token
        $google_response = wp_remote_get('https://oauth2.googleapis.com/tokeninfo?id_token=' . $token);
        if (is_wp_error($google_response)) {
            return new WP_Error('google_error', __('Google verification failed', 'ai-itinerary'), ['status' => 500]);
        }

        $google_data = json_decode(wp_remote_retrieve_body($google_response), true);
        $client_id = get_option('aip_google_client_id');

        if (($google_data['aud'] ?? '') !== $client_id) {
            return new WP_Error('invalid_token', __('Invalid Google token', 'ai-itinerary'), ['status' => 401]);
        }

        $email = sanitize_email($google_data['email'] ?? '');
        $first_name = sanitize_text_field($google_data['given_name'] ?? '');
        $last_name = sanitize_text_field($google_data['family_name'] ?? '');

        if (empty($email)) {
            return new WP_Error('no_email', __('Could not get email from Google', 'ai-itinerary'), ['status' => 400]);
        }

        // Find or create user
        $user = get_user_by('email', $email);
        if (!$user) {
            $user_id = wp_create_user($email, wp_generate_password(), $email);
            if (is_wp_error($user_id)) return $user_id;

            wp_update_user([
                'ID'           => $user_id,
                'first_name'   => $first_name,
                'last_name'    => $last_name,
                'display_name' => trim("$first_name $last_name"),
            ]);

            update_user_meta($user_id, 'aip_google_id', $google_data['sub'] ?? '');
            AIP_Database::log_event('user_registered', ['method' => 'google'], $user_id);
        } else {
            $user_id = $user->ID;
        }

        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);

        return rest_ensure_response([
            'success' => true,
            'user_id' => $user_id,
            'name'    => trim("$first_name $last_name"),
        ]);
    }

    public function auth_logout(WP_REST_Request $request) {
        wp_logout();
        return rest_ensure_response(['success' => true]);
    }

    // ============================================================
    // AFFILIATE ENDPOINTS
    // ============================================================

    public function affiliate_links(WP_REST_Request $request) {
        $destination = sanitize_text_field($request->get_param('destination'));
        $links = AIP_Travelpayouts::get_links($destination);
        return rest_ensure_response($links);
    }

    public function affiliate_click(WP_REST_Request $request) {
        AIP_Database::log_affiliate_click([
            'user_id'      => get_current_user_id(),
            'itinerary_id' => (int) $request->get_param('itinerary_id'),
            'provider'     => sanitize_text_field($request->get_param('provider')),
            'category'     => sanitize_text_field($request->get_param('category')),
            'destination'  => sanitize_text_field($request->get_param('destination')),
            'link_url'     => esc_url_raw($request->get_param('url')),
        ]);
        return rest_ensure_response(['tracked' => true]);
    }

    // ============================================================
    // ADMIN ENDPOINTS
    // ============================================================

    public function admin_analytics(WP_REST_Request $request) {
        $days = (int) ($request->get_param('days') ?? 30);
        return rest_ensure_response(AIP_Database::get_analytics($days));
    }
}
