<?php
/**
 * Enhanced Cloudflare Handler Class for Illios Cache Plugin
 * Now includes APO support and advanced features
 *
 * @package Illios_Cache
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Illios_Cache_Cloudflare_Handler {

    private $api_token;
    private $zone_id;
    private $enabled;
    private $apo_enabled = false;
    private $apo_cache_by_device_type = false;
    private $api_base_url = 'https://api.cloudflare.com/client/v4/';

    public function __construct() {
        $options = get_option('illios_cache_settings', array());
        $this->api_token = isset($options['cloudflare_api_token']) ? $options['cloudflare_api_token'] : '';
        $this->zone_id = isset($options['cloudflare_zone_id']) ? $options['cloudflare_zone_id'] : '';
        $this->enabled = isset($options['cloudflare_enabled']) ? $options['cloudflare_enabled'] : false;
        $this->apo_enabled = isset($options['cloudflare_apo_enabled']) ? $options['cloudflare_apo_enabled'] : false;
        $this->apo_cache_by_device_type = isset($options['cloudflare_apo_cache_by_device_type']) ? $options['cloudflare_apo_cache_by_device_type'] : false;
    }

    /**
     * Check if Cloudflare is properly configured and enabled
     */
    public function is_enabled() {
        return $this->enabled && !empty($this->api_token) && !empty($this->zone_id);
    }

    /**
     * Check if APO is enabled
     */
    public function is_apo_enabled() {
        return $this->apo_enabled && $this->is_enabled();
    }

    /**
     * Get APO status from Cloudflare
     */
    public function get_apo_status() {
        if (!$this->is_enabled()) {
            return new WP_Error('cloudflare_disabled', 'Cloudflare is not enabled or configured');
        }

        $endpoint = "zones/{$this->zone_id}/settings/automatic_platform_optimization";
        $response = $this->make_api_request($endpoint, 'GET');

        if (is_wp_error($response)) {
            return $response;
        }

        return $response;
    }

    /**
     * Enable/Disable APO
     */
    public function set_apo_status($enabled = true, $cache_by_device_type = false) {
        if (!$this->is_enabled()) {
            return new WP_Error('cloudflare_disabled', 'Cloudflare is not enabled or configured');
        }

        $endpoint = "zones/{$this->zone_id}/settings/automatic_platform_optimization";
        $hostname = parse_url(home_url(), PHP_URL_HOST);

        $body = json_encode(array(
            'value' => array(
                'enabled' => $enabled,
                'cf' => false,
                'wordpress' => $enabled,
                'wp_plugin' => $enabled,
                'hostnames' => array($hostname),
                'cache_by_device_type' => $cache_by_device_type
            )
        ));

        $response = $this->make_api_request($endpoint, 'PATCH', $body);

        if (is_wp_error($response)) {
            error_log('Cloudflare APO toggle failed: ' . $response->get_error_message());
            return $response;
        }

        // Check Cloudflare API result
        if (!isset($response['success']) || !$response['success']) {
            $message = 'Failed to update APO.';
            if (!empty($response['errors'][0]['message'])) {
                $message = $response['errors'][0]['message'];
            }
            return new WP_Error('cloudflare_apo_failed', $message);
        }

        // Update local settings only if API call succeeded
        $options = get_option('illios_cache_settings', array());
        $options['cloudflare_apo_enabled'] = $enabled;
        $options['cloudflare_apo_cache_by_device_type'] = $cache_by_device_type;
        update_option('illios_cache_settings', $options);

        $this->apo_enabled = $enabled;
        $this->apo_cache_by_device_type = $cache_by_device_type;

        return $response;
    }

    /**
     * Get development mode status
     */
    public function get_development_mode() {
        if (!$this->is_enabled()) {
            return new WP_Error('cloudflare_disabled', 'Cloudflare is not enabled or configured');
        }

        $endpoint = "zones/{$this->zone_id}/settings/development_mode";
        
        $response = $this->make_api_request($endpoint, 'GET');

        if (is_wp_error($response)) {
            return $response;
        }

        return $response;
    }

    /**
     * Toggle development mode
     */
    public function set_development_mode($enabled = true) {
        if (!$this->is_enabled()) {
            return new WP_Error('cloudflare_disabled', 'Cloudflare is not enabled or configured');
        }

        $endpoint = "zones/{$this->zone_id}/settings/development_mode";
        $body = json_encode(array('value' => $enabled ? 'on' : 'off'));

        $response = $this->make_api_request($endpoint, 'PATCH', $body);

        if (is_wp_error($response)) {
            return $response;
        }

        return $response;
    }

    /**
     * Apply recommended WordPress settings
     */
    public function apply_wordpress_settings() {
        if (!$this->is_enabled()) {
            return new WP_Error('cloudflare_disabled', 'Cloudflare is not enabled or configured');
        }

        $results = array();
        
        // Recommended WordPress settings based on official plugin
        $settings = array(
            // Always Online
            'always_online' => array(
                'endpoint' => "zones/{$this->zone_id}/settings/always_online",
                'value' => 'on'
            ),
            // Security Level
            'security_level' => array(
                'endpoint' => "zones/{$this->zone_id}/settings/security_level", 
                'value' => 'medium'
            ),
            // SSL Mode - Full (strict) for better security
            'ssl' => array(
                'endpoint' => "zones/{$this->zone_id}/settings/ssl",
                'value' => 'full'
            ),
            // Browser Cache TTL
            'browser_cache_ttl' => array(
                'endpoint' => "zones/{$this->zone_id}/settings/browser_cache_ttl",
                'value' => 14400 // 4 hours
            ),
            // Minification
            'minify' => array(
                'endpoint' => "zones/{$this->zone_id}/settings/minify",
                'value' => array('css' => 'on', 'html' => 'on', 'js' => 'on')
            ),
            // Rocket Loader - off by default for WordPress compatibility
            'rocket_loader' => array(
                'endpoint' => "zones/{$this->zone_id}/settings/rocket_loader", 
                'value' => 'off'
            ),
            // Auto-HTTPS Rewrites
            'automatic_https_rewrites' => array(
                'endpoint' => "zones/{$this->zone_id}/settings/automatic_https_rewrites",
                'value' => 'on'
            ),
            // Opportunistic Encryption
            'opportunistic_encryption' => array(
                'endpoint' => "zones/{$this->zone_id}/settings/opportunistic_encryption",
                'value' => 'on'
            ),
            // IP Geolocation
            'ip_geolocation' => array(
                'endpoint' => "zones/{$this->zone_id}/settings/ip_geolocation",
                'value' => 'on'
            )
        );

        foreach ($settings as $setting_name => $config) {
            $body = json_encode(array('value' => $config['value']));
            $response = $this->make_api_request($config['endpoint'], 'PATCH', $body);
            
            if (is_wp_error($response)) {
                $results[$setting_name] = array(
                    'success' => false,
                    'error' => $response->get_error_message()
                );
            } else {
                $results[$setting_name] = array(
                    'success' => true,
                    'data' => $response
                );
            }
        }

        return $results;
    }

    /**
     * Purge all cache from Cloudflare
     */
    public function purge_all() {
        if (!$this->is_enabled()) {
            return new WP_Error('cloudflare_disabled', 'Cloudflare purging is not enabled or configured');
        }

        $endpoint = "zones/{$this->zone_id}/purge_cache";
        $body = json_encode(array('purge_everything' => true));

        $response = $this->make_api_request($endpoint, 'POST', $body);

        if (is_wp_error($response)) {
            error_log('Cloudflare purge all failed: ' . $response->get_error_message());
            return $response;
        }

        return $response;
    }

    /**
     * Purge specific URLs from Cloudflare
     */
    public function purge_urls($urls) {
        if (!$this->is_enabled()) {
            return new WP_Error('cloudflare_disabled', 'Cloudflare purging is not enabled or configured');
        }

        if (empty($urls) || !is_array($urls)) {
            return new WP_Error('invalid_urls', 'URLs must be provided as an array');
        }

        // Cloudflare allows max 30 URLs per request
        $url_chunks = array_chunk($urls, 30);
        $results = array();

        foreach ($url_chunks as $chunk) {
            $endpoint = "zones/{$this->zone_id}/purge_cache";
            $body = json_encode(array('files' => $chunk));

            $response = $this->make_api_request($endpoint, 'POST', $body);
            
            if (is_wp_error($response)) {
                error_log('Cloudflare purge URLs failed: ' . $response->get_error_message());
                $results[] = $response;
            } else {
                $results[] = $response;
            }
        }

        return $results;
    }

    /**
     * Purge cache by tags (if using Enterprise features)
     */
    // public function purge_by_tags($tags) {
    //     if (!$this->is_enabled()) {
    //         return new WP_Error('cloudflare_disabled', 'Cloudflare purging is not enabled or configured');
    //     }

    //     if (empty($tags) || !is_array($tags)) {
    //         return new WP_Error('invalid_tags', 'Tags must be provided as an array');
    //     }

    //     $endpoint = "zones/{$this->zone_id}/purge_cache";
    //     $body = json_encode(array('tags' => $tags));

    //     $response = $this->make_api_request($endpoint, 'POST', $body);

    //     if (is_wp_error($response)) {
    //         error_log('Cloudflare purge by tags failed: ' . $response->get_error_message());
    //         return $response;
    //     }

    //     return $response;
    // }

    /**
     * Verify API credentials and zone access
     */
    public function verify_credentials() {
        if (empty($this->api_token) || empty($this->zone_id)) {
            return new WP_Error('missing_credentials', 'API token and Zone ID are required');
        }

        // First verify the token itself
        $endpoint = "user/tokens/verify";
        $response = $this->make_api_request($endpoint, 'GET');

        if (is_wp_error($response)) {
            return $response;
        }

        if (!isset($response['success']) || $response['success'] !== true) {
            return new WP_Error('invalid_token', 'Invalid API token');
        }

        // Then verify zone access
        $endpoint = "zones/{$this->zone_id}";
        $response = $this->make_api_request($endpoint, 'GET');

        if (is_wp_error($response)) {
            return $response;
        }

        if (isset($response['success']) && $response['success'] === true) {
            return true;
        }

        return new WP_Error('invalid_zone', 'Invalid Zone ID or insufficient permissions');
    }

    /**
     * Get comprehensive URLs for purging based on post
     */
    public function get_post_related_urls($post_id) {
        $urls = array();
        
        // Home page
        $urls[] = home_url('/');
        
        // Post URL with variations
        $post_url = get_permalink($post_id);
        if ($post_url) {
            $urls[] = $post_url;
            $urls[] = trailingslashit($post_url); // With trailing slash
            $urls[] = untrailingslashit($post_url); // Without trailing slash
        }

        // Post type archive
        $post_type = get_post_type($post_id);
        if ($post_type && $post_type !== 'page') {
            $archive_url = get_post_type_archive_link($post_type);
            if ($archive_url) {
                $urls[] = $archive_url;
            }
        }

        // Categories and tags
        if ($post_type === 'post') {
            // Blog page (posts page)
            if (get_option('page_for_posts')) {
                $urls[] = get_permalink(get_option('page_for_posts'));
            }

            $categories = get_the_category($post_id);
            foreach ($categories as $category) {
                $urls[] = get_category_link($category->term_id);
            }

            $tags = get_the_tags($post_id);
            if ($tags) {
                foreach ($tags as $tag) {
                    $urls[] = get_tag_link($tag->term_id);
                }
            }

            // Author page
            $post = get_post($post_id);
            if ($post) {
                $urls[] = get_author_posts_url($post->post_author);
            }

            // Feeds
            $urls[] = get_bloginfo('rss2_url');
            $urls[] = get_bloginfo('atom_url');
        }

        // Custom taxonomies
        $taxonomies = get_post_taxonomies($post_id);
        if ($taxonomies) {
            foreach ($taxonomies as $taxonomy) {
                if (!in_array($taxonomy, array('category', 'post_tag'))) {
                    $terms = wp_get_post_terms($post_id, $taxonomy);
                    foreach ($terms as $term) {
                        $term_link = get_term_link($term);
                        if (!is_wp_error($term_link)) {
                            $urls[] = $term_link;
                        }
                    }
                }
            }
        }

        // AMP URLs if AMP plugin is active
        if (function_exists('amp_get_permalink')) {
            $urls[] = amp_get_permalink($post_id);
        }

        return array_unique(array_filter($urls));
    }

    /**
     * Purge cache for a specific post and all related URLs
     */
    public function purge_post($post_id) {
        $urls_to_purge = $this->get_post_related_urls($post_id);
        
        if (!empty($urls_to_purge)) {
            return $this->purge_urls($urls_to_purge);
        }
        
        return array();
    }

    // public function can_enable_apo() {
    //     $plan = $this->get_account_plan(); // fetch plan info via API
    //     // Free plan = 0, Pro+ = 1+
    //     return $plan !== 'free';
    // }

    public function get_account_plan() {
        if (empty($this->api_token)) {
            return null; // API not configured
        }

        $endpoint = 'user/tokens/verify'; // Or appropriate endpoint returning plan info
        $response = $this->make_api_request($endpoint);

        if (is_wp_error($response)) {
            return null;
        }

        return strtolower($response['result']['plan'] ?? 'free');
    }

    /**
     * Make API request to Cloudflare
     */
    private function make_api_request($endpoint, $method = 'GET', $body = null) {
        $url = $this->api_base_url . $endpoint;

        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_token,
                'Content-Type' => 'application/json',
                'User-Agent' => 'Illios-Cache-Plugin/' . ILLIOS_CACHE_VERSION
            ),
            'timeout' => 30,
        );

        if ($body && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = $body;
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code >= 400) {
            $error_data = json_decode($response_body, true);
            $error_message = 'HTTP ' . $response_code . ' error';
            
            if (isset($error_data['errors']) && is_array($error_data['errors']) && count($error_data['errors']) > 0) {
                $error_message = $error_data['errors'][0]['message'];
            }
            
            return new WP_Error('cloudflare_api_error', $error_message, $response_code);
        }

        $decoded_response = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_decode_error', 'Failed to decode API response');
        }

        return $decoded_response;
    }
}