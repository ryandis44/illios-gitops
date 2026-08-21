<?php
/**
 * Illios Digital LLC Cache
 *
 * A WordPress plugin that combines Cloudflare and Varnish cache purging capabilities.
 *
 * @package   Illios_Cache
 * @category  Performance
 * @author    James Mach
 * @copyright 2025 Illios Digital LLC
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 *
 * @wordpress-plugin
 * Plugin Name:       Illios Digital LLC Cache
 * Plugin URI:        https://github.com/your-repo/cache-illios
 * Description:       Combined Cloudflare and Varnish cache management for optimal performance
 * Version:           0.1.0
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Author:            Illios Digital LLC
 * Author URI:        https://illiosdigital.com
 * Text Domain:       cache-illios
 * Domain Path:       /languages
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ILLIOS_CACHE_VERSION', '0.1.0');
define('ILLIOS_CACHE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ILLIOS_CACHE_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Default plugin settings.
 *
 * Applied everywhere the settings are read, not just on the admin screen, so
 * the settings page and the runtime can never disagree about what is enabled.
 */
function illios_cache_default_settings() {
    return array(
        'cloudflare_enabled' => true,
        'varnish_enabled'    => true,
        'purge_on_post_save' => true,
        'purge_on_comment'   => false,
        // Varnish purges are blocking and run one request per URL per server,
        // inside the save request. Keep the per-request wait short so an
        // unreachable server cannot stall publishing.
        'varnish_timeout'    => 5,
    );
}

/**
 * Should saving this post trigger a purge?
 *
 * save_post fires for every post type, including internal ones like
 * customize_changeset, oembed_cache and revisions. Purging for those means a
 * pointless round trip to Cloudflare and Varnish on routine admin activity.
 */
function illios_cache_should_purge_post($post_id) {
    $should = true;

    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        $should = false;
    } elseif ('auto-draft' === get_post_status($post_id)) {
        // Never been public, so nothing can be cached for it yet.
        $should = false;
    } else {
        $post_type_object = get_post_type_object(get_post_type($post_id));

        // Only skip post types we can positively identify as non-public.
        if ($post_type_object
            && empty($post_type_object->public)
            && empty($post_type_object->publicly_queryable)) {
            $should = false;
        }
    }

    return apply_filters('illios_cache_should_purge_post', $should, $post_id);
}

/**
 * Read the plugin settings with defaults applied.
 */
function illios_cache_get_settings() {
    $options = get_option('illios_cache_settings', array());

    if (!is_array($options)) {
        $options = array();
    }

    return wp_parse_args($options, illios_cache_default_settings());
}

/**
 * Pages that list a post type but that WordPress has no link to.
 *
 * A post type registered with has_archive => false has no archive link, so
 * get_post_type_archive_link() returns false and the hand-built pages that
 * actually list those posts are never purged. Map them explicitly.
 */
function illios_cache_associated_paths() {
    $paths = array(
        'episode'  => array('/', '/episodes/', '/all-episodes/'),
        'research' => array('/', '/research-hub/', '/all-research/'),
    );

    return apply_filters('illios_cache_associated_paths', $paths);
}

/**
 * Extra URLs that should be purged alongside a given post.
 */
function illios_cache_get_associated_urls($post_id) {
    $post_type = get_post_type($post_id);
    $urls      = array();

    if (!$post_type) {
        return $urls;
    }

    $paths = illios_cache_associated_paths();

    if (!empty($paths[$post_type])) {
        foreach ($paths[$post_type] as $path) {
            $url = home_url($path);
            // Cloudflare purge-by-URL is an exact match, so cover both forms.
            $urls[] = trailingslashit($url);
            $urls[] = untrailingslashit($url);
        }
    }

    $urls = apply_filters('illios_cache_associated_urls', $urls, $post_id, $post_type);

    return array_values(array_unique(array_filter($urls)));
}

/**
 * Flatten a purge result into a list of readable error strings.
 *
 * The handlers return nested arrays (one entry per URL, per server, or per
 * API request chunk) whose *elements* may be WP_Error objects. is_wp_error()
 * on the outer array is always false, which is why failed purges used to be
 * reported as successes.
 */
function illios_cache_collect_errors($result, $context = '') {
    $prefix = ('' !== $context) ? $context . ': ' : '';

    if (is_wp_error($result)) {
        return array($prefix . $result->get_error_message());
    }

    if (!is_array($result)) {
        return array();
    }

    // Cloudflare's API envelope, and the Varnish handler's per-server result.
    if (array_key_exists('success', $result)) {
        if ($result['success']) {
            return array();
        }

        $errors = array();

        if (!empty($result['errors']) && is_array($result['errors'])) {
            foreach ($result['errors'] as $error) {
                $errors[] = $prefix . (isset($error['message']) ? $error['message'] : 'unknown error');
            }
        }

        if (empty($errors)) {
            $errors[] = $prefix . 'the request was not successful';
        }

        return $errors;
    }

    $errors = array();

    foreach ($result as $key => $item) {
        $child  = $context . (is_string($key) ? ' [' . $key . ']' : '');
        $errors = array_merge($errors, illios_cache_collect_errors($item, $child));
    }

    return $errors;
}

// Main plugin class
class Illios_Cache_Plugin {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init();
    }
    
    private function init() {
        // Load dependencies
        $this->load_dependencies();
        
        // Initialize hooks
        add_action('init', array($this, 'init_plugin'));
        
        // Cache purging hooks
        add_action('save_post', array($this, 'purge_cache_on_save'));
        add_action('wp_trash_post', array($this, 'purge_cache_on_delete'));
        add_action('wp_insert_comment', array($this, 'purge_cache_on_comment'));
        add_action('wp_set_comment_status', array($this, 'purge_cache_on_comment_status'));
        
        // Theme and plugin change hooks
        add_action('switch_theme', array($this, 'purge_all_caches'));
        
        // Initialize admin settings
        if (is_admin()) {
            new Illios_Cache_Admin_Settings();
            add_action('wp_ajax_illios_cache_purge', array($this, 'handle_manual_purge'));
        }

        // Never let an edge cache store a logged-in user's response
        add_action('init', array($this, 'add_logged_in_bypass_headers'));
            }
    
    private function load_dependencies() {
        require_once ILLIOS_CACHE_PLUGIN_DIR . 'includes/cloudflare/class-cloudflare-handler.php';
        require_once ILLIOS_CACHE_PLUGIN_DIR . 'includes/varnish/class-varnish-handler.php';
        require_once ILLIOS_CACHE_PLUGIN_DIR . 'includes/admin/class-admin-settings.php';
    }
    
    public function init_plugin() {
        // Plugin initialization code
        load_plugin_textdomain('cache-illios', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function purge_cache_on_save($post_id) {
        // Skips autosaves, revisions, auto-drafts and non-public post types.
        if (!illios_cache_should_purge_post($post_id)) {
            return;
        }

        $options = illios_cache_get_settings();

        // Global dev mode always purges; otherwise honour the individual setting.
        if (empty($options['global_dev_mode_enabled']) && empty($options['purge_on_post_save'])) {
            return;
        }

        $this->purge_post_related_caches($post_id);
    }

    public function purge_cache_on_delete($post_id) {
        $options = illios_cache_get_settings();

        // Always purge on delete if global dev mode or if configured
        if (!empty($options['global_dev_mode_enabled']) || !empty($options['purge_on_post_save'])) {
            $this->purge_post_related_caches($post_id);
        }
    }

    public function purge_cache_on_comment($comment_id) {
        $options = illios_cache_get_settings();
        
        // Check if global dev mode is enabled - if so, always purge
        if (isset($options['global_dev_mode_enabled']) && $options['global_dev_mode_enabled']) {
            $comment = get_comment($comment_id);
            if ($comment) {
                $this->purge_post_related_caches($comment->comment_post_ID);
            }
            return;
        }
        
        // Otherwise check individual setting
        if (!isset($options['purge_on_comment']) || !$options['purge_on_comment']) {
            return;
        }

        $comment = get_comment($comment_id);
        if ($comment) {
            $this->purge_post_related_caches($comment->comment_post_ID);
        }
    }

    public function purge_cache_on_comment_status($comment_id, $status = null) {
        $options = illios_cache_get_settings();
        
        // Check if global dev mode is enabled - if so, always purge
        if (isset($options['global_dev_mode_enabled']) && $options['global_dev_mode_enabled']) {
            $comment = get_comment($comment_id);
            if ($comment) {
                $this->purge_post_related_caches($comment->comment_post_ID);
            }
            return;
        }
        
        // Otherwise check individual setting
        if (!isset($options['purge_on_comment']) || !$options['purge_on_comment']) {
            return;
        }

        $comment = get_comment($comment_id);
        if ($comment) {
            $this->purge_post_related_caches($comment->comment_post_ID);
        }
    }

    /**
     * Purge caches related to a specific post
     */
    private function purge_post_related_caches($post_id) {
        // save_post can fire more than once for the same post in a single
        // request. Purging is a blocking network round trip, so do it once.
        static $already_purged = array();

        if (isset($already_purged[$post_id])) {
            return array();
        }

        $already_purged[$post_id] = true;

        $cf_handler = new Illios_Cache_Cloudflare_Handler();
        $varnish_handler = new Illios_Cache_Varnish_Handler();
        $errors = array();

        // Purge Cloudflare
        if ($cf_handler->is_enabled()) {
            $errors = array_merge(
                $errors,
                illios_cache_collect_errors($cf_handler->purge_post($post_id), 'Cloudflare')
            );
        }

        // Purge Varnish
        if ($varnish_handler->is_enabled()) {
            $errors = array_merge(
                $errors,
                illios_cache_collect_errors($varnish_handler->purge_post($post_id), 'Varnish')
            );
        }

        foreach ($errors as $error) {
            error_log('Illios Cache: purge for post ' . $post_id . ' failed - ' . $error);
        }

        return $errors;
    }

    /**
     * Purge every configured cache.
     *
     * Returns a list of error strings, empty on full success. Public because
     * it is also used directly as a switch_theme callback.
     */
    public function purge_all_caches() {
        $errors = array();
        $attempted = 0;

        // Purge Cloudflare
        $cf_handler = new Illios_Cache_Cloudflare_Handler();
        if ($cf_handler->is_enabled()) {
            $attempted++;
            $errors = array_merge(
                $errors,
                illios_cache_collect_errors($cf_handler->purge_all(), 'Cloudflare')
            );
        }

        // Purge Varnish
        $varnish_handler = new Illios_Cache_Varnish_Handler();
        if ($varnish_handler->is_enabled()) {
            $attempted++;
            $errors = array_merge(
                $errors,
                illios_cache_collect_errors($varnish_handler->purge_all(), 'Varnish')
            );
        }

        // Nothing ran at all, so reporting success would be a lie.
        if (0 === $attempted) {
            $errors[] = 'No cache backend is enabled or configured, so nothing was purged';
        }

        foreach ($errors as $error) {
            error_log('Illios Cache: purge all failed - ' . $error);
        }

        return $errors;
    }

    /**
     * Add headers to bypass edge caching for logged-in users
     */
    public function add_logged_in_bypass_headers() {
        if (is_user_logged_in()) {
            if (!headers_sent()) {
                header('Cache-Control: no-cache, must-revalidate, max-age=0');
                header('Pragma: no-cache');
            }
        }
    }

    public function handle_manual_purge() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'illios_cache_purge')) {
            wp_send_json_error('Security check failed');
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $type = isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : '';

        try {
            switch ($type) {
                case 'cloudflare':
                    $cf_handler = new Illios_Cache_Cloudflare_Handler();
                    if (!$cf_handler->is_enabled()) {
                        wp_send_json_error('Cloudflare is not enabled or configured');
                    }

                    $errors = illios_cache_collect_errors($cf_handler->purge_all(), 'Cloudflare');
                    if (!empty($errors)) {
                        wp_send_json_error(implode(' | ', $errors));
                    }
                    break;

                case 'varnish':
                    $varnish_handler = new Illios_Cache_Varnish_Handler();
                    if (!$varnish_handler->is_enabled()) {
                        wp_send_json_error('Varnish is not enabled or configured');
                    }

                    $errors = illios_cache_collect_errors($varnish_handler->purge_all(), 'Varnish');
                    if (!empty($errors)) {
                        wp_send_json_error(implode(' | ', $errors));
                    }
                    break;

                case 'all':
                    $errors = $this->purge_all_caches();
                    if (!empty($errors)) {
                        wp_send_json_error(implode(' | ', $errors));
                    }
                    break;

                default:
                    wp_send_json_error('Invalid purge type');
            }
            
            wp_send_json_success('Cache purged successfully');
            
        } catch (Exception $e) {
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }

/**
     * Add admin bar menu for quick actions
     */
    public function add_admin_bar_menu($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }

        $wp_admin_bar->add_menu(array(
            'id' => 'illios-cache',
            'title' => 'Manage Cache',
            'href' => admin_url('options-general.php?page=illios-cache-admin')
        ));

        $wp_admin_bar->add_menu(array(
            'id' => 'illios-cache-purge-all',
            'parent' => 'illios-cache',
            'title' => 'Purge All Caches',
            'href' => wp_nonce_url(admin_url('admin-post.php?action=illios_cache_purge_all'), 'illios_cache_purge_all')
        ));

        $wp_admin_bar->add_menu(array(
            'id' => 'illios-cache-purge-cf',
            'parent' => 'illios-cache',
            'title' => 'Purge Cloudflare Cache',
            'href' => wp_nonce_url(admin_url('admin-post.php?action=illios_cache_purge_cf'), 'illios_cache_purge_cf')
        ));

        $wp_admin_bar->add_menu(array(
            'id' => 'illios-cache-purge-varnish',
            'parent' => 'illios-cache',
            'title' => 'Purge Varnish Cache',
            'href' => wp_nonce_url(admin_url('admin-post.php?action=illios_cache_purge_varnish'), 'illios_cache_purge_varnish')
        ));
    }

    /**
     * Handle admin bar purge actions
     */
    public function handle_admin_bar_purge() {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : '';
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

        $allowed_actions = array(
            'illios_cache_purge_all',
            'illios_cache_purge_cf',
            'illios_cache_purge_varnish',
        );

        // The nonce action matches the request action for all three buttons.
        if (!in_array($action, $allowed_actions, true) || !wp_verify_nonce($nonce, $action)) {
            wp_die('Security check failed');
        }

        $redirect_url = wp_get_referer() ? wp_get_referer() : home_url();
        $errors = array();

        try {
            switch ($action) {
                case 'illios_cache_purge_all':
                    $errors = $this->purge_all_caches();
                    $redirect_url = empty($errors)
                        ? add_query_arg('cache_purged', 'all', $redirect_url)
                        : add_query_arg('cache_error', 'all', $redirect_url);
                    break;

                case 'illios_cache_purge_cf':
                    $cf_handler = new Illios_Cache_Cloudflare_Handler();
                    $errors = illios_cache_collect_errors($cf_handler->purge_all(), 'Cloudflare');
                    $redirect_url = empty($errors)
                        ? add_query_arg('cache_purged', 'cloudflare', $redirect_url)
                        : add_query_arg('cache_error', 'cf', $redirect_url);
                    break;

                case 'illios_cache_purge_varnish':
                    $varnish_handler = new Illios_Cache_Varnish_Handler();
                    $errors = illios_cache_collect_errors($varnish_handler->purge_all(), 'Varnish');
                    $redirect_url = empty($errors)
                        ? add_query_arg('cache_purged', 'varnish', $redirect_url)
                        : add_query_arg('cache_error', 'varnish', $redirect_url);
                    break;
            }

            foreach ($errors as $error) {
                error_log('Illios Cache: admin bar purge failed - ' . $error);
            }
        } catch (Exception $e) {
            $redirect_url = add_query_arg('cache_error', 'general', $redirect_url);
        }

        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Show admin notices for cache purge results
     */
    public function show_cache_notices() {
        if (isset($_GET['cache_purged'])) {
            $type = sanitize_text_field($_GET['cache_purged']);
            $message = '';
            
            switch ($type) {
                case 'all':
                    $message = 'All caches purged successfully!';
                    break;
                case 'cloudflare':
                    $message = 'Cloudflare cache purged successfully!';
                    break;
                case 'varnish':
                    $message = 'Varnish cache purged successfully!';
                    break;
            }
            
            if ($message) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
            }
        }

        if (isset($_GET['cache_error'])) {
            $type = sanitize_text_field($_GET['cache_error']);
            $message = '';
            
            switch ($type) {
                case 'all':
                    $message = 'One or more caches could not be purged. Check your settings and the error log.';
                    break;
                case 'cf':
                    $message = 'Error purging Cloudflare cache. Check your settings.';
                    break;
                case 'varnish':
                    $message = 'Error purging Varnish cache. Check your server configuration.';
                    break;
                case 'general':
                    $message = 'An error occurred while purging cache.';
                    break;
            }
            
            if ($message) {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($message) . '</p></div>';
            }
        }
    }
}

// Initialize the plugin
function illios_cache_plugin() {
    return Illios_Cache_Plugin::instance();
}

// Start the plugin
add_action('plugins_loaded', 'illios_cache_plugin');

// Add admin bar menu
add_action('admin_bar_menu', array(illios_cache_plugin(), 'add_admin_bar_menu'), 100);

// Handle admin bar actions
add_action('admin_post_illios_cache_purge_all', array(illios_cache_plugin(), 'handle_admin_bar_purge'));
add_action('admin_post_illios_cache_purge_cf', array(illios_cache_plugin(), 'handle_admin_bar_purge'));
add_action('admin_post_illios_cache_purge_varnish', array(illios_cache_plugin(), 'handle_admin_bar_purge'));

// Show admin notices
add_action('admin_notices', array(illios_cache_plugin(), 'show_cache_notices'));