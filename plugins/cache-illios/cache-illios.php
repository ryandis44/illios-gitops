<?php
/**
 * Illios Digital LLC Cache - Enhanced Version
 *
 * A WordPress plugin that combines Cloudflare (with APO support) and Varnish cache purging capabilities.
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
 * Description:       Combined Cloudflare (with APO support) and Varnish cache management for optimal performance
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
        // add_action('activated_plugin', array($this, 'purge_all_caches'));
        // add_action('deactivated_plugin', array($this, 'purge_all_caches'));
        
        // Initialize admin settings
        if (is_admin()) {
            new Illios_Cache_Admin_Settings();
            add_action('wp_ajax_illios_cache_purge', array($this, 'handle_manual_purge'));
        }

        // Add APO cache bypass for logged-in users
        add_action('init', array($this, 'add_apo_bypass_headers'));
        
        // Add Cloudflare headers for APO detection
        add_action('wp_head', array($this, 'add_apo_detection_meta'), 1);
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
        // Skip for autosaves and revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        $options = get_option('illios_cache_settings', array());
        
        // Check if global dev mode is enabled - if so, always purge
        if (isset($options['global_dev_mode_enabled']) && $options['global_dev_mode_enabled']) {
            $this->purge_post_related_caches($post_id);
            return;
        }

        // Otherwise check individual setting
        if (!isset($options['purge_on_post_save']) || !$options['purge_on_post_save']) {
            return;
        }

        $this->purge_post_related_caches($post_id);
    }
    
    public function purge_cache_on_delete($post_id) {
        $options = get_option('illios_cache_settings', array());
        
        // Always purge on delete if global dev mode or if configured
        if ((isset($options['global_dev_mode_enabled']) && $options['global_dev_mode_enabled']) ||
            (isset($options['purge_on_post_save']) && $options['purge_on_post_save'])) {
            $this->purge_post_related_caches($post_id);
        }
    }

    public function purge_cache_on_comment($comment_id) {
        $options = get_option('illios_cache_settings', array());
        
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
        $options = get_option('illios_cache_settings', array());
        
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
        $cf_handler = new Illios_Cache_Cloudflare_Handler();
        $varnish_handler = new Illios_Cache_Varnish_Handler();

        // Purge Cloudflare
        if ($cf_handler->is_enabled()) {
            $cf_result = $cf_handler->purge_post($post_id);
            if (is_wp_error($cf_result)) {
                error_log('Cloudflare post purge failed: ' . $cf_result->get_error_message());
            }
        }

        // Purge Varnish  
        if ($varnish_handler->is_enabled()) {
            $varnish_result = $varnish_handler->purge_post($post_id);
            if (is_wp_error($varnish_result)) {
                error_log('Varnish post purge failed: ' . $varnish_result->get_error_message());
            }
        }
    }
    
    private function purge_all_caches() {
        // Purge Cloudflare
        $cf_handler = new Illios_Cache_Cloudflare_Handler();
        if ($cf_handler->is_enabled()) {
            $cf_result = $cf_handler->purge_all();
            if (is_wp_error($cf_result)) {
                error_log('Cloudflare purge all failed: ' . $cf_result->get_error_message());
            }
        }
        
        // Purge Varnish
        $varnish_handler = new Illios_Cache_Varnish_Handler();
        if ($varnish_handler->is_enabled()) {
            $varnish_result = $varnish_handler->purge_all();
            if (is_wp_error($varnish_result)) {
                error_log('Varnish purge all failed: ' . $varnish_result->get_error_message());
            }
        }
    }

    /**
     * Add headers to bypass APO cache for logged-in users
     */
    public function add_apo_bypass_headers() {
        if (is_user_logged_in()) {
            // Standard WordPress bypass headers for APO
            if (!headers_sent()) {
                header('Cache-Control: no-cache, must-revalidate, max-age=0');
                header('Pragma: no-cache');
            }
        }
    }

    /**
     * Add meta tag for APO detection
     */
    public function add_apo_detection_meta() {
        $cf_handler = new Illios_Cache_Cloudflare_Handler();
        if ($cf_handler->is_apo_enabled()) {
            echo '<meta name="cf-2fa-verify" content="' . home_url() . '">' . "\n";
        }
    }

    public function handle_manual_purge() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'illios_cache_purge')) {
            wp_send_json_error('Security check failed');
        }
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $type = sanitize_text_field($_POST['type']);
        
        try {
            switch ($type) {
                case 'cloudflare':
                    $cf_handler = new Illios_Cache_Cloudflare_Handler();
                    if (!$cf_handler->is_enabled()) {
                        wp_send_json_error('Cloudflare is not enabled or configured');
                    }
                    
                    $result = $cf_handler->purge_all();
                    if (is_wp_error($result)) {
                        wp_send_json_error($result->get_error_message());
                    }
                    break;
                    
                case 'varnish':
                    $varnish_handler = new Illios_Cache_Varnish_Handler();
                    if (!$varnish_handler->is_enabled()) {
                        wp_send_json_error('Varnish is not enabled or configured');
                    }
                    
                    $result = $varnish_handler->purge_all();
                    if (is_wp_error($result)) {
                        wp_send_json_error($result->get_error_message());
                    }
                    break;
                    
                case 'all':
                    $this->purge_all_caches();
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
     * Get plugin status information
     */
    public function get_status() {
        $cf_handler = new Illios_Cache_Cloudflare_Handler();
        $varnish_handler = new Illios_Cache_Varnish_Handler();
        
        $status = array(
            'cloudflare' => array(
                'enabled' => $cf_handler->is_enabled(),
                'apo_enabled' => $cf_handler->is_apo_enabled(),
                'credentials_valid' => false
            ),
            'varnish' => array(
                'enabled' => $varnish_handler->is_enabled(),
                'servers' => count($varnish_handler->get_servers())
            )
        );

        // Test Cloudflare credentials if enabled
        if ($cf_handler->is_enabled()) {
            $test = $cf_handler->verify_credentials();
            $status['cloudflare']['credentials_valid'] = !is_wp_error($test);
        }

        return $status;
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
            'title' => 'Purge Cloudflare',
            'href' => wp_nonce_url(admin_url('admin-post.php?action=illios_cache_purge_cf'), 'illios_cache_purge_cf')
        ));

        $wp_admin_bar->add_menu(array(
            'id' => 'illios-cache-purge-varnish',
            'parent' => 'illios-cache',
            'title' => 'Purge Varnish',
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

        $action = $_GET['action'];
        $nonce_action = str_replace('illios_cache_', 'illios_cache_', $action);
        
        if (!wp_verify_nonce($_GET['_wpnonce'], $nonce_action)) {
            wp_die('Security check failed');
        }

        $redirect_url = wp_get_referer() ? wp_get_referer() : home_url();

        try {
            switch ($action) {
                case 'illios_cache_purge_all':
                    $this->purge_all_caches();
                    $redirect_url = add_query_arg('cache_purged', 'all', $redirect_url);
                    break;
                    
                case 'illios_cache_purge_cf':
                    $cf_handler = new Illios_Cache_Cloudflare_Handler();
                    $result = $cf_handler->purge_all();
                    if (is_wp_error($result)) {
                        $redirect_url = add_query_arg('cache_error', 'cf', $redirect_url);
                    } else {
                        $redirect_url = add_query_arg('cache_purged', 'cloudflare', $redirect_url);
                    }
                    break;
                    
                case 'illios_cache_purge_varnish':
                    $varnish_handler = new Illios_Cache_Varnish_Handler();
                    $result = $varnish_handler->purge_all();
                    if (is_wp_error($result)) {
                        $redirect_url = add_query_arg('cache_error', 'varnish', $redirect_url);
                    } else {
                        $redirect_url = add_query_arg('cache_purged', 'varnish', $redirect_url);
                    }
                    break;
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