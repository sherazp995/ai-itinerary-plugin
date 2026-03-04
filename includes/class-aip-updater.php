<?php
if (!defined('ABSPATH')) exit;

class AIP_Updater {

    private static $github_repo = 'sherazp995/ai-itinerary-plugin';
    private static $cache_key   = 'aip_github_update';
    private static $cache_ttl   = 43200; // 12 hours

    public static function init() {
        add_filter('pre_set_site_transient_update_plugins', [self::class, 'check_update']);
        add_filter('plugins_api', [self::class, 'plugin_info'], 10, 3);
        add_filter('upgrader_post_install', [self::class, 'post_install'], 10, 3);
    }

    private static function get_latest_release() {
        $cached = get_transient(self::$cache_key);
        if ($cached !== false) return $cached;

        $url = 'https://api.github.com/repos/' . self::$github_repo . '/releases/latest';
        $response = wp_remote_get($url, [
            'headers' => ['Accept' => 'application/vnd.github.v3+json'],
            'timeout' => 10,
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }

        $release = json_decode(wp_remote_retrieve_body($response));
        if (empty($release->tag_name)) return false;

        set_transient(self::$cache_key, $release, self::$cache_ttl);
        return $release;
    }

    public static function check_update($transient) {
        if (empty($transient->checked)) return $transient;

        $release = self::get_latest_release();
        if (!$release) return $transient;

        $remote_version = ltrim($release->tag_name, 'v');
        $current_version = AIP_VERSION;

        if (version_compare($remote_version, $current_version, '>')) {
            $zip_url = 'https://api.github.com/repos/' . self::$github_repo . '/zipball/' . $release->tag_name;

            $transient->response[AIP_PLUGIN_BASENAME] = (object) [
                'slug'        => 'ai-itinerary-plugin',
                'plugin'      => AIP_PLUGIN_BASENAME,
                'new_version' => $remote_version,
                'url'         => 'https://github.com/' . self::$github_repo,
                'package'     => $zip_url,
            ];
        }

        return $transient;
    }

    public static function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information' || ($args->slug ?? '') !== 'ai-itinerary-plugin') {
            return $result;
        }

        $release = self::get_latest_release();
        if (!$release) return $result;

        return (object) [
            'name'          => 'AI Travel Itinerary Generator',
            'slug'          => 'ai-itinerary-plugin',
            'version'       => ltrim($release->tag_name, 'v'),
            'author'        => 'Sheraz',
            'homepage'      => 'https://github.com/' . self::$github_repo,
            'download_link' => 'https://api.github.com/repos/' . self::$github_repo . '/zipball/' . $release->tag_name,
            'sections'      => [
                'description' => 'AI-powered travel itinerary generator with chat interface, PDF export, and affiliate integration.',
                'changelog'   => nl2br(esc_html($release->body ?? 'See GitHub for details.')),
            ],
        ];
    }

    public static function post_install($response, $hook_extra, $result) {
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== AIP_PLUGIN_BASENAME) {
            return $response;
        }

        // GitHub zip extracts to a folder like "sherazp995-ai-itinerary-plugin-abc1234"
        // Rename it to the correct plugin folder name
        global $wp_filesystem;
        $plugin_dir = WP_PLUGIN_DIR . '/ai-itinerary-plugin/';

        if ($result['destination'] !== $plugin_dir) {
            $wp_filesystem->move($result['destination'], $plugin_dir);
            $result['destination'] = $plugin_dir;
        }

        activate_plugin(AIP_PLUGIN_BASENAME);
        return $response;
    }
}
