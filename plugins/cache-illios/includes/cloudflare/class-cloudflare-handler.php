<?php
/**
 * Cloudflare Handler Class for Illios Cache Plugin
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
    private $api_base_url = 'https://api.cloudflare.com/client/v4/';

    public function __construct() {
        $options = illios_cache_get_settings();
        $this->api_token = getenv('CLOUDFLARE_API_TOKEN') ?: (isset($options['cloudflare_api_token']) ? $options['cloudflare_api_token'] : '');
        $this->zone_id = getenv('CLOUDFLARE_ZONE_ID') ?: (isset($options['cloudflare_zone_id']) ? $options['cloudflare_zone_id'] : '');
        $this->enabled = isset($options['cloudflare_enabled']) ? $options['cloudflare_enabled'] : false;
    }

    /**
     * Check if Cloudflare is properly configured and enabled
     */
    public function is_enabled() {
        return $this->enabled && !empty($this->api_token) && !empty($this->zone_id);
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

        // Hand-built listing pages. Post types registered with has_archive =>
        // false have no archive link, so the pages that actually list them are
        // invisible to the check above.
        $urls = array_merge($urls, illios_cache_get_associated_urls($post_id));

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