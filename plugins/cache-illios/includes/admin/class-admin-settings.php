<?php
/**
 * Admin Settings Class for Illios Cache Plugin
 *
 * @package Illios_Cache
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Illios_Cache_Admin_Settings {

    private $options;
    private $template_path;

    public function __construct() {
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_menu', array($this, 'add_options_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('update_option_illios_cache_settings', array($this, 'sync_dev_mode_on_settings_update'), 10, 2);

        $this->options = $this->get_options_with_defaults();
        $this->template_path = plugin_dir_path(__FILE__) . 'templates/';
    }

    private function get_options_with_defaults() {
        // Shared with the runtime so the settings screen and the purge code
        // can never disagree about what is enabled.
        return illios_cache_get_settings();
    }

    public function enqueue_admin_assets($hook) {
        if ('settings_page_illios-cache-admin' !== $hook) {
            return;
        }

        wp_enqueue_script(
            'illios-cache-admin',
            plugin_dir_url(__FILE__) . 'js/admin-scripts.js',
            array('jquery'),
            ILLIOS_CACHE_VERSION,
            true
        );

        wp_enqueue_style(
            'illios-cache-admin',
            plugin_dir_url(__FILE__) . 'css/admin-styles.css',
            array(),
            ILLIOS_CACHE_VERSION
        );

        wp_localize_script('illios-cache-admin', 'illios_cache_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce_purge' => wp_create_nonce('illios_cache_purge'),
        ));
    }

    public function admin_init() {
        register_setting(
            'illios_cache_settings_group',
            'illios_cache_settings',
            array($this, 'sanitize')
        );

        $this->register_global_dev_mode_section();
        $this->register_cloudflare_section();
        $this->register_varnish_section();
        $this->register_auto_purge_section();
    }

    /**
     * Push development mode to Cloudflare when the setting changes.
     *
     * global_dev_mode_enabled is the single source of truth: it both forces a
     * purge on every content change and puts the Cloudflare zone into
     * development mode. Note that Cloudflare expires development mode on its
     * own after three hours, so re-saving is what re-asserts it.
     */
    public function sync_dev_mode_on_settings_update($old_value, $new_value) {
        $old_enabled = !empty($old_value['global_dev_mode_enabled']);
        $new_enabled = !empty($new_value['global_dev_mode_enabled']);

        if ($old_enabled === $new_enabled) {
            return;
        }

        $cf_handler = new Illios_Cache_Cloudflare_Handler();

        if (!$cf_handler->is_enabled()) {
            return;
        }

        $result = $cf_handler->set_development_mode($new_enabled);

        if (is_wp_error($result)) {
            add_settings_error(
                'illios_cache_settings',
                'dev_mode_sync_error',
                'Development mode was saved, but Cloudflare could not be updated: ' . $result->get_error_message(),
                'error'
            );
            return;
        }

        add_settings_error(
            'illios_cache_settings',
            'dev_mode_sync_success',
            $new_enabled
                ? 'Development mode enabled and pushed to Cloudflare.'
                : 'Development mode disabled and pushed to Cloudflare.',
            'updated'
        );
    }

    private function register_global_dev_mode_section() {
        add_settings_section(
            'global_dev_mode_section',
            '', // Empty title since we handle it in template
            null, // No callback needed
            'illios-cache-admin'
        );

        add_settings_field(
            'global_dev_mode_enabled',
            'Enable Development Mode',
            array($this, 'global_dev_mode_enabled_callback'),
            'illios-cache-admin',
            'global_dev_mode_section'
        );
    }

    private function register_cloudflare_section() {
        add_settings_section(
            'cloudflare_section',
            '', // Empty title since we handle it in template
            null, // No callback needed
            'illios-cache-admin'
        );

        add_settings_field(
            'cloudflare_enabled',
            'Enable Cloudflare Purging',
            array($this, 'cloudflare_enabled_callback'),
            'illios-cache-admin',
            'cloudflare_section'
        );
    }

    private function register_varnish_section() {
        add_settings_section(
            'varnish_section',
            '', // Empty title since we handle it in template
            null, // No callback needed
            'illios-cache-admin'
        );

        add_settings_field(
            'varnish_enabled',
            'Enable Varnish Purging',
            array($this, 'varnish_enabled_callback'),
            'illios-cache-admin',
            'varnish_section'
        );
    }

    private function register_auto_purge_section() {
        add_settings_section(
            'auto_purge_section',
            '', // Empty title since we handle it in template
            null, // No callback needed
            'illios-cache-admin'
        );

        add_settings_field(
            'purge_on_post_save',
            'Purge on Post Save',
            array($this, 'purge_on_post_save_callback'),
            'illios-cache-admin',
            'auto_purge_section'
        );

        add_settings_field(
            'purge_on_comment',
            'Purge on Comment',
            array($this, 'purge_on_comment_callback'),
            'illios-cache-admin',
            'auto_purge_section'
        );
    }

    public function sanitize($input) {
        // Start from what is already stored. Settings without a field on this
        // screen (Cloudflare credentials, Varnish servers and timeout) are set
        // via wp-config/WP-CLI, so building the array from scratch would wipe
        // them on every save.
        $new_input = get_option('illios_cache_settings', array());

        if (!is_array($new_input)) {
            $new_input = array();
        }

        // Checkboxes that this screen renders. An unchecked box is absent from
        // $input, so each one must be written as an explicit false or it could
        // never be turned off.
        $checkboxes = array(
            'global_dev_mode_enabled',
            'cloudflare_enabled',
            'varnish_enabled',
            'purge_on_post_save',
            'purge_on_comment',
        );

        foreach ($checkboxes as $checkbox) {
            $new_input[$checkbox] = !empty($input[$checkbox]);
        }

        return $new_input;
    }

    public function add_options_page() {
        add_options_page(
            'Manage Cache Settings',
            'Manage Cache',
            'manage_options',
            'illios-cache-admin',
            array($this, 'render_page')
        );
    }

    public function render_page() {
        include $this->template_path . 'page-admin-settings.php';
    }

    private function render_field($template, $args) {
        include $this->template_path . $template;
    }

    // Field Callbacks
    public function global_dev_mode_enabled_callback() {
        $this->render_field('checkbox-field.php', array(
            'id' => 'global_dev_mode_enabled',
            'name' => 'illios_cache_settings[global_dev_mode_enabled]',
            'value' => !empty($this->options['global_dev_mode_enabled']),
            'label' => 'Enable global development mode (automatically purge all cache on content changes)',
            'class' => 'global-dev-mode-field',
            'description' => 'When enabled, cache is purged immediately on every post save, comment, or other content change for both Cloudflare and Varnish, and the Cloudflare zone is put into development mode.'
        ));
    }

    public function cloudflare_enabled_callback() {
        $this->render_field('checkbox-field.php', array(
            'id' => 'cloudflare_enabled',
            'name' => 'illios_cache_settings[cloudflare_enabled]',
            'value' => !empty($this->options['cloudflare_enabled']),
            'label' => 'Enable automatic Cloudflare cache purging',
        ));
    }

    public function varnish_enabled_callback() {
        $this->render_field('checkbox-field.php', array(
            'id' => 'varnish_enabled',
            'name' => 'illios_cache_settings[varnish_enabled]',
            'value' => !empty($this->options['varnish_enabled']),
            'label' => 'Enable automatic Varnish cache purging',
        ));
    }

    public function purge_on_post_save_callback() {
        $this->render_field('checkbox-field.php', array(
            'id' => 'purge_on_post_save',
            'name' => 'illios_cache_settings[purge_on_post_save]',
            'value' => !empty($this->options['purge_on_post_save']),
            'label' => 'Automatically purge cache when posts are saved/updated'
        ));
    }

    public function purge_on_comment_callback() {
        $this->render_field('checkbox-field.php', array(
            'id' => 'purge_on_comment',
            'name' => 'illios_cache_settings[purge_on_comment]',
            'value' => !empty($this->options['purge_on_comment']),
            'label' => 'Automatically purge cache when comments are posted'
        ));
    }
}
