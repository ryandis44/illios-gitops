<?php
/**
 * Varnish Handler Class for Illios Cache Plugin
 * Based on the proven Varnish HTTP Purge plugin logic
 *
 * @package Illios_Cache
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Illios_Cache_Varnish_Handler {

    private $servers;
    private $enabled;
    private $timeout = 30;
    private $purge_urls = array();

    public function __construct() {
        $options = get_option('illios_cache_settings', array());
        $this->enabled = isset($options['varnish_enabled']) ? $options['varnish_enabled'] : false;
        $this->timeout = isset($options['varnish_timeout']) ? $options['varnish_timeout'] : 30;
        
        // Parse server list
        $server_string = isset($options['varnish_servers']) ? $options['varnish_servers'] : '';
        $this->servers = $this->parse_servers($server_string);
    }

    /**
     * Parse server configuration string into array
     */
    private function parse_servers($server_string) {
        $servers = array();
        
        if (empty($server_string)) {
            // Default to localhost if no servers specified
            return array('127.0.0.1');
        }

        $lines = explode("\n", $server_string);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Clean up the server entry
            $line = str_replace(array('http://', 'https://'), '', $line);
            
            $servers[] = $line;
        }

        return $servers;
    }

    /**
     * Get the list of configured servers
     */
    public function get_servers() {
        return $this->servers;
    }

    /**
     * Check if Varnish is properly configured and enabled
     */
    public function is_enabled() {
        return $this->enabled && !empty($this->servers);
    }

    /**
     * Purge all cache from Varnish servers (regex purge)
     */
    public function purge_all() {
        if (!$this->is_enabled()) {
            return new WP_Error('varnish_disabled', 'Varnish purging is not enabled or configured');
        }
        
        // Use regex purge for full cache clear
        $home_url = home_url('/');
        $regex_url = $home_url . '?vhp-regex';
        
        return $this->purge_url($regex_url);
    }

    /**
     * Purge specific URL from Varnish servers
     * Based on the proven Varnish HTTP Purge plugin logic
     */
    public function purge_url($url) {
        if (!$this->is_enabled()) {
            return new WP_Error('varnish_disabled', 'Varnish purging is not enabled or configured');
        }

        // Bail early if someone sent a non-URL
        if (false === filter_var($url, FILTER_VALIDATE_URL)) {
            return new WP_Error('invalid_url', 'Invalid URL provided for purging');
        }

        $p = wp_parse_url($url);

        // Bail early if there's no host
        if (!isset($p['host'])) {
            return new WP_Error('invalid_url', 'URL must include a valid host');
        }

        // Determine if we're using regex to flush all pages or not
        $pregex = '';
        $x_purge_method = 'default';

        if (isset($p['query']) && ('vhp-regex' === $p['query'])) {
            $pregex = '.*';
            $x_purge_method = 'regex';
        }

        // Determine the path
        $path = (isset($p['path'])) ? $p['path'] : '';

        // Use HTTP schema (Varnish typically runs on HTTP)
        $schema = 'http://';

        $results = array();

        // Loop through all Varnish servers
        foreach ($this->servers as $server) {            
            // Allow setting of ports in server name
            $host_headers = $p['host'];
            if (isset($p['port'])) {
                $host_headers .= ':' . $p['port'];
            }

            // Create path to purge
            $purgeme = $schema . $server . $path . $pregex;

            // Check the queries
            if (!empty($p['query']) && 'vhp-regex' !== $p['query']) {
                $purgeme .= '?' . $p['query'];
            }

            // Prepare headers
            $headers = array(
                'Host' => $host_headers,
                'X-Purge-Method' => $x_purge_method,
                'User-Agent' => 'Illios-Cache-Plugin/' . ILLIOS_CACHE_VERSION,
            );

            // Send PURGE request
            $response = wp_remote_request($purgeme, array(
                'method' => 'PURGE',
                'headers' => $headers,
                'timeout' => $this->timeout,
                'sslverify' => false,
                'blocking' => true,
            ));

            if (is_wp_error($response)) {
                $results[$server] = $response;
                continue;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            
            // Varnish typically returns 200 for successful purges
            if (!in_array($response_code, array(200, 404))) {
                error_log("Varnish server {$server} returned HTTP {$response_code}");
                $results[$server] = new WP_Error(
                    'varnish_error',
                    "Varnish server returned HTTP {$response_code}",
                    $response_code
                );
            } else {
                $results[$server] = array(
                    'success' => true,
                    'response_code' => $response_code,
                    'server' => $server,
                    'url' => $purgeme,
                    'method' => 'PURGE'
                );
            }
        }

        return $results;
    }

    /**
     * Purge multiple URLs from Varnish servers
     */
    public function purge_urls($urls) {
        if (!$this->is_enabled()) {
            return new WP_Error('varnish_disabled', 'Varnish purging is not enabled or configured');
        }

        if (empty($urls) || !is_array($urls)) {
            return new WP_Error('invalid_urls', 'URLs must be provided as an array');
        }

        $results = array();

        foreach ($urls as $url) {
            $url_results = $this->purge_url($url);
            $results[$url] = $url_results;
        }

        return $results;
    }

    /**
     * Generate URLs to purge based on post ID
     * This mirrors the comprehensive logic from Varnish HTTP Purge plugin
     */
    public function get_post_related_urls($post_id) {
        $listofurls = array();
        
        // Valid post statuses that should trigger purging
        $valid_post_status = array('publish', 'private', 'trash', 'pending', 'draft');
        $this_post_status = get_post_status($post_id);
        
        // Invalid post types we should ignore
        $invalid_post_type = array('nav_menu_item', 'revision');
        $noarchive_post_type = array('post', 'page');
        $this_post_type = get_post_type($post_id);

        // Verify we have a valid post to work with
        if (false === get_permalink($post_id) || 
            !in_array($this_post_status, $valid_post_status) || 
            in_array($this_post_type, $invalid_post_type)) {
            return $listofurls;
        }

        // Post URL
        $listofurls[] = get_permalink($post_id);

        // REST API endpoints (if available)
        if (version_compare(get_bloginfo('version'), '4.7', '>=')) {
            $rest_api_route = 'wp/v2';
            
            $post_type_object = get_post_type_object($this_post_type);
            if (isset($post_type_object->rest_base)) {
                $listofurls[] = get_rest_url() . $rest_api_route . '/' . $post_type_object->rest_base . '/' . $post_id . '/';
            } elseif ('post' === $this_post_type) {
                $listofurls[] = get_rest_url() . $rest_api_route . '/posts/' . $post_id . '/';
            } elseif ('page' === $this_post_type) {
                $listofurls[] = get_rest_url() . $rest_api_route . '/pages/' . $post_id . '/';
            }

            // Categories
            $categories = get_the_category($post_id);
            if ($categories) {
                foreach ($categories as $cat) {
                    $listofurls[] = get_category_link($cat->term_id);
                    $listofurls[] = get_rest_url() . $rest_api_route . '/categories/' . $cat->term_id . '/';
                }
            }

            // Tags
            $tags = get_the_tags($post_id);
            if ($tags) {
                foreach ($tags as $tag) {
                    $listofurls[] = get_tag_link($tag->term_id);
                    $listofurls[] = get_rest_url() . $rest_api_route . '/tags/' . $tag->term_id . '/';
                }
            }

            // Custom taxonomies
            $taxonomies = get_post_taxonomies($post_id);
            if ($taxonomies) {
                foreach ($taxonomies as $taxonomy) {
                    $features = (array) get_taxonomy($taxonomy);
                    if ($features['public']) {
                        $terms = wp_get_post_terms($post_id, $taxonomy);
                        foreach ($terms as $term) {
                            $listofurls[] = get_term_link($term);
                            $rest_base = isset($features['rest_base']) && !empty($features['rest_base']) 
                                ? $features['rest_base'] 
                                : $term->taxonomy;
                            $listofurls[] = get_rest_url() . $rest_api_route . '/' . $rest_base . '/' . $term->term_id . '/';
                        }
                    }
                }
            }

            // Additional URLs for posts (not pages)
            if ($this_post_type && 'post' === $this_post_type) {
                // Author URLs
                $author_id = get_post_field('post_author', $post_id);
                $listofurls[] = get_author_posts_url($author_id);
                $listofurls[] = get_author_feed_link($author_id);
                $listofurls[] = get_rest_url() . $rest_api_route . '/users/' . $author_id . '/';

                // Feeds
                $listofurls[] = get_bloginfo_rss('rdf_url');
                $listofurls[] = get_bloginfo_rss('rss_url');
                $listofurls[] = get_bloginfo_rss('rss2_url');
                $listofurls[] = get_bloginfo_rss('atom_url');
                $listofurls[] = get_bloginfo_rss('comments_rss2_url');
                $listofurls[] = get_post_comments_feed_link($post_id);
            }
        }

        // AMP URLs if AMP plugin is active
        if (function_exists('amp_get_permalink')) {
            $listofurls[] = amp_get_permalink($post_id);
        }

        // Handle trashed posts
        if ('trash' === $this_post_status) {
            $trashpost = get_permalink($post_id);
            $trashpost = str_replace('__trashed', '', $trashpost);
            $listofurls[] = $trashpost;
            $listofurls[] = $trashpost . 'feed/';
        }

        // Archive pages for custom post types
        if ($this_post_type && !in_array($this_post_type, $noarchive_post_type)) {
            $listofurls[] = get_post_type_archive_link(get_post_type($post_id));
            $listofurls[] = get_post_type_archive_feed_link(get_post_type($post_id));
        }

        // Home page and posts page
        $listofurls[] = get_rest_url();
        $listofurls[] = user_trailingslashit(home_url());
        
        if ('page' === get_option('show_on_front')) {
            if (get_option('page_for_posts')) {
                $listofurls[] = get_permalink(get_option('page_for_posts'));
            }
        }

        // Remove query parameters and duplicates
        $listofurls = array_map(function($url) { 
            return strtok($url, '?'); 
        }, $listofurls);
        
        return array_unique(array_filter($listofurls));
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
     * Test connection to Varnish servers
     */
    public function test_connection() {
        
        if (!$this->is_enabled()) {
            return new WP_Error('varnish_disabled', 'Varnish is not enabled or configured');
        }
        
        $results = array();
        
        foreach ($this->servers as $server) {
            // Parse server to get host and port
            $server_parts = parse_url('http://' . $server);
            $host = $server_parts['host'];
            $port = isset($server_parts['port']) ? $server_parts['port'] : 80;
            
            $results[$server] = $this->test_varnish_purge($host, $port);
        }
        
        return $results;
    }

    private function test_varnish_purge($host, $port) {
        $url = "http://{$host}:{$port}/";
        
        // Test PURGE method - most reliable way to detect Varnish
        $response = wp_remote_request($url, array(
            'method' => 'PURGE',
            'timeout' => $this->timeout,
            'headers' => array(
                'Host' => parse_url(home_url(), PHP_URL_HOST)
            )
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'PURGE test failed: ' . $response->get_error_message()
            );
        }
        
        $status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        
        // Varnish typically returns 200 (success) or 405 (not allowed in ACL) for PURGE
        if (in_array($status, array(200, 405))) {
            // Look for Varnish-specific PURGE responses
            if (stripos($body, 'PURGE') !== false || stripos($body, 'varnish') !== false) {
                return array(
                    'success' => true,
                    'message' => 'Varnish detected via PURGE method response'
                );
            }
            
            // Even without specific text, proper PURGE handling suggests Varnish
            if ($status === 200 || ($status === 405 && stripos($body, 'not allowed') !== false)) {
                return array(
                    'success' => true,
                    'message' => "PURGE method supported (status: {$status}) - likely Varnish"
                );
            }
        }
        
        return array(
            'success' => false,
            'message' => "PURGE not supported (status: {$status}) - no Varnish detected"
        );
    }
}