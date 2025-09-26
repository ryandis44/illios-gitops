<?php
/**
 * Enhanced Admin Settings Class for Illios Cache Plugin
 * Refactored to use templates and external assets
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
        
        // AJAX handlers
        add_action('wp_ajax_illios_cache_test_connection', array($this, 'handle_test_connection'));
        add_action('wp_ajax_illios_cache_apply_wp_settings', array($this, 'handle_apply_wp_settings'));
        add_action('wp_ajax_illios_cache_toggle_apo', array($this, 'handle_toggle_apo'));
        add_action('wp_ajax_illios_cache_toggle_dev_mode', array($this, 'handle_toggle_dev_mode'));
        add_action('wp_ajax_illios_cache_get_dev_mode_status', array($this, 'handle_get_dev_mode_status'));
        
        $this->options = get_option('illios_cache_settings', array());
        $this->template_path = plugin_dir_path(__FILE__) . 'templates/';
    }

    public function enqueue_admin_assets($hook) {
        if ('settings_page_illios-cache-admin' !== $hook) {
            return;
        }

        wp_enqueue_script(
            'illios-cache-admin',
            plugin_dir_url(__FILE__) . 'js/admin-scripts.js',
            array('jquery'),
            '1.0.0',
            true
        );

        wp_enqueue_style(
            'illios-cache-admin',
            plugin_dir_url(__FILE__) . 'css/admin-styles.css',
            array(),
            '1.0.0'
        );

        wp_localize_script('illios-cache-admin', 'illios_cache_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce_purge' => wp_create_nonce('illios_cache_purge'),
            'nonce_test' => wp_create_nonce('illios_cache_test'),
            'nonce_wp' => wp_create_nonce('illios_cache_wp_settings'),
            'nonce_dev_mode' => wp_create_nonce('illios_cache_dev_mode'),
            'nonce_dev_mode_status' => wp_create_nonce('illios_cache_dev_mode_status'),
            'nonce_apo' => wp_create_nonce('illios_cache_apo')
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
        $this->register_apo_section();
        $this->register_varnish_section();
        $this->register_auto_purge_section();
    }

    private function register_global_dev_mode_section() {
        add_settings_section(
            'global_dev_mode_section',
            'Global Development Mode',
            array($this, 'global_dev_mode_section_info'),
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
            '<span style="font-size: 1.2em;">Cloudflare Settings</span>',
            array($this, 'cloudflare_section_info'),
            'illios-cache-admin'
        );

        add_settings_field(
            'cloudflare_enabled',
            'Enable Cloudflare Integration',
            array($this, 'cloudflare_enabled_callback'),
            'illios-cache-admin',
            'cloudflare_section'
        );

        add_settings_field(
            'cloudflare_api_token',
            'API Token',
            array($this, 'cloudflare_api_token_callback'),
            'illios-cache-admin',
            'cloudflare_section'
        );

        add_settings_field(
            'cloudflare_zone_id',
            'Zone ID',
            array($this, 'cloudflare_zone_id_callback'),
            'illios-cache-admin',
            'cloudflare_section'
        );

        add_settings_field(
            'cloudflare_advanced_controls',
            '',
            array($this, 'cloudflare_advanced_controls_callback'),
            'illios-cache-admin',
            'cloudflare_section'
        );
    }

    private function register_apo_section() {
        add_settings_section(
            'apo_section',
            'Automatic Platform Optimization (APO)',
            array($this, 'apo_section_info'),
            'illios-cache-admin'
        );

        add_settings_field(
            'cloudflare_apo_enabled',
            'Enable APO',
            array($this, 'cloudflare_apo_enabled_callback'),
            'illios-cache-admin',
            'apo_section'
        );

        add_settings_field(
            'cloudflare_apo_cache_by_device_type',
            'Cache by Device Type',
            array($this, 'cloudflare_apo_cache_by_device_type_callback'),
            'illios-cache-admin',
            'apo_section'
        );
    }

    private function register_varnish_section() {
        add_settings_section(
            'varnish_section',
            '<span style="font-size: 1.2em;">Varnish Settings</span>',
            array($this, 'varnish_section_info'),
            'illios-cache-admin'
        );

        add_settings_field(
            'varnish_enabled',
            'Enable Varnish Purging',
            array($this, 'varnish_enabled_callback'),
            'illios-cache-admin',
            'varnish_section'
        );

        add_settings_field(
            'varnish_servers',
            'Varnish Servers',
            array($this, 'varnish_servers_callback'),
            'illios-cache-admin',
            'varnish_section'
        );

        add_settings_field(
            'varnish_timeout',
            'Request Timeout (seconds)',
            array($this, 'varnish_timeout_callback'),
            'illios-cache-admin',
            'varnish_section'
        );
    }

    private function register_auto_purge_section() {
        add_settings_section(
            'auto_purge_section',
            'Auto Purge Settings',
            array($this, 'auto_purge_section_info'),
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
        $new_input = array();

        if (isset($input['global_dev_mode_enabled'])) {
            $new_input['global_dev_mode_enabled'] = (bool) $input['global_dev_mode_enabled'];
        }

        if (isset($input['cloudflare_api_token'])) {
            $new_input['cloudflare_api_token'] = sanitize_text_field($input['cloudflare_api_token']);
        }

        if (isset($input['cloudflare_zone_id'])) {
            $new_input['cloudflare_zone_id'] = sanitize_text_field($input['cloudflare_zone_id']);
        }

        if (isset($input['cloudflare_enabled'])) {
            $new_input['cloudflare_enabled'] = (bool) $input['cloudflare_enabled'];
        }

        if (isset($input['cloudflare_apo_enabled'])) {
            $new_input['cloudflare_apo_enabled'] = (bool) $input['cloudflare_apo_enabled'];
        }

        if (isset($input['cloudflare_apo_cache_by_device_type'])) {
            $new_input['cloudflare_apo_cache_by_device_type'] = (bool) $input['cloudflare_apo_cache_by_device_type'];
        }

        if (isset($input['varnish_servers'])) {
            $new_input['varnish_servers'] = sanitize_textarea_field($input['varnish_servers']);
        }

        if (isset($input['varnish_enabled'])) {
            $new_input['varnish_enabled'] = (bool) $input['varnish_enabled'];
        }

        if (isset($input['varnish_timeout'])) {
            $timeout = (int) $input['varnish_timeout'];
            $new_input['varnish_timeout'] = ($timeout > 0 && $timeout <= 60) ? $timeout : 30;
        }

        if (isset($input['purge_on_post_save'])) {
            $new_input['purge_on_post_save'] = (bool) $input['purge_on_post_save'];
        }

        if (isset($input['purge_on_comment'])) {
            $new_input['purge_on_comment'] = (bool) $input['purge_on_comment'];
        }

        return $new_input;
    }

    public function add_options_page() {
        add_options_page(
            'Illios Cache Settings',
            'Illios Cache',
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

    // Section Info Callbacks
    public function global_dev_mode_section_info() {
        print '<p>Global development mode automatically purges both Cloudflare and Varnish cache on every content change for immediate testing. <strong>Warning:</strong> This impacts performance and should only be used during active development.</p>';
        
        $cf_enabled = isset($this->options['cloudflare_enabled']) && $this->options['cloudflare_enabled'];
        $varnish_enabled = isset($this->options['varnish_enabled']) && $this->options['varnish_enabled'];
        $dev_mode_enabled = isset($this->options['global_dev_mode_enabled']) && $this->options['global_dev_mode_enabled'];
        
        if ($dev_mode_enabled && !$cf_enabled && !$varnish_enabled) {
            echo '<div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 10px; margin: 10px 0; color: #856404;">';
            echo '<strong>Warning:</strong> Development mode is enabled but no caching services are configured. This setting will have no effect until you configure Cloudflare or Varnish below.';
            echo '</div>';
        }
    }

    public function cloudflare_section_info() {
        print '<p>Configure your Cloudflare API settings below. You can create an API token at: <a href="https://dash.cloudflare.com/profile/api-tokens" target="_blank">Cloudflare Dashboard</a></p>';
    }

    public function apo_section_info() {
        print '<p>Automatic Platform Optimization caches your entire WordPress site on Cloudflare\'s edge network for maximum performance. <strong>Note:</strong> APO costs $5/month on Free plans or is included with Pro+ plans.</p>';
    }

    public function varnish_section_info() {
        print 'Configure your Varnish server settings below:';
    }

    public function auto_purge_section_info() {
        print 'Configure automatic cache purging triggers:';
    }

    // Field Callbacks
    public function global_dev_mode_enabled_callback() {
        $this->render_field('checkbox-field.php', array(
            'id' => 'global_dev_mode_enabled',
            'name' => 'illios_cache_settings[global_dev_mode_enabled]',
            'value' => isset($this->options['global_dev_mode_enabled']) && $this->options['global_dev_mode_enabled'],
            'label' => 'Enable global development mode (automatically purge all cache on content changes)',
            'description' => 'When enabled, cache is purged immediately on every post save, comment, or other content change for both Cloudflare and Varnish.'
        ));
    }

    public function cloudflare_enabled_callback() {
        $this->render_field('checkbox-field.php', array(
            'id' => 'cloudflare_enabled',
            'name' => 'illios_cache_settings[cloudflare_enabled]',
            'value' => isset($this->options['cloudflare_enabled']) && $this->options['cloudflare_enabled'],
            'label' => 'Enable Cloudflare integration and cache purging'
        ));
    }

    public function cloudflare_api_token_callback() {
        $disabled = (!isset($this->options['cloudflare_enabled']) || !$this->options['cloudflare_enabled']);
        $this->render_field('text-field.php', array(
            'id' => 'cloudflare_api_token',
            'name' => 'illios_cache_settings[cloudflare_api_token]',
            'value' => isset($this->options['cloudflare_api_token']) ? $this->options['cloudflare_api_token'] : '',
            'type' => 'password',
            'class' => 'class="regular-text cloudflare-field"',
            'disabled' => $disabled,
            'description' => 'Your Cloudflare API token with Zone:Cache Purge and Zone:Zone Settings permissions. <a href="https://dash.cloudflare.com/profile/api-tokens" target="_blank">Create Token</a>'
        ));
    }

    public function cloudflare_zone_id_callback() {
        $disabled = (!isset($this->options['cloudflare_enabled']) || !$this->options['cloudflare_enabled']);
        $this->render_field('text-field.php', array(
            'id' => 'cloudflare_zone_id',
            'name' => 'illios_cache_settings[cloudflare_zone_id]',
            'value' => isset($this->options['cloudflare_zone_id']) ? $this->options['cloudflare_zone_id'] : '',
            'class' => 'class="regular-text cloudflare-field"',
            'disabled' => $disabled,
            'description' => 'Your Cloudflare Zone ID (found in the right sidebar of your domain overview)'
        ));
    }

    public function cloudflare_advanced_controls_callback() {
        $disabled = (!isset($this->options['cloudflare_enabled']) || !$this->options['cloudflare_enabled']);
        ?>
        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
            <?php
            $this->render_field('button-field.php', array(
                'id' => 'test-cloudflare',
                'label' => 'Test Connection',
                'class' => 'button button-secondary cloudflare-advanced-btn',
                'disabled' => $disabled
            ));
            
            $this->render_field('button-field.php', array(
                'id' => 'apply-wp-settings',
                'label' => 'Apply WordPress Settings',
                'class' => 'button button-secondary cloudflare-advanced-btn',
                'disabled' => $disabled
            ));
            ?>
        </div>
        <?php
    }

    public function cloudflare_apo_enabled_callback() {
        $cf_token_set = isset($this->options['cloudflare_api_token']) && !empty($this->options['cloudflare_api_token']);
        $cf_enabled = isset($this->options['cloudflare_enabled']) && $this->options['cloudflare_enabled'] && $cf_token_set;
        $disabled = !$cf_enabled;

        if (!$cf_enabled) {
            $account_status = 'Not configured';
        } else {
            $cf_handler = new Illios_Cache_Cloudflare_Handler();
            $plan = $cf_handler->get_account_plan();
            $account_status = $plan ? ucfirst($plan) : 'Unknown';
            if ($plan === 'free') {
                $disabled = true;
            }
        }

        $description = '<p class="description" style="margin-top:4px;">Account status: <strong>' . esc_html($account_status) . '</strong></p>';

        if (!$cf_enabled) {
            $description .= '<p class="description" style="color: #111;">Enable Cloudflare integration and configure first</p>';
        } elseif ($account_status === 'Free') {
            $description .= '<p class="description" style="color: red;">APO cannot be enabled on your current plan (Free plan only allows $5/month).</p>';
        } else {
            $description .= '<p class="description">Caches your entire WordPress site at Cloudflare\'s edge for maximum performance</p>';
        }

        $this->render_field('checkbox-field.php', array(
            'id' => 'cloudflare_apo_enabled',
            'name' => 'illios_cache_settings[cloudflare_apo_enabled]',
            'value' => isset($this->options['cloudflare_apo_enabled']) && $this->options['cloudflare_apo_enabled'],
            'label' => 'Enable Automatic Platform Optimization',
            'class' => 'class="apo-field"',
            'disabled' => $disabled,
            'description' => $description
        ));
    }

    public function cloudflare_apo_cache_by_device_type_callback() {
        $cf_enabled = isset($this->options['cloudflare_enabled']) && $this->options['cloudflare_enabled'];
        $apo_enabled = isset($this->options['cloudflare_apo_enabled']) && $this->options['cloudflare_apo_enabled'];
        $disabled = (!$cf_enabled || !$apo_enabled);

        $this->render_field('checkbox-field.php', array(
            'id' => 'cloudflare_apo_cache_by_device_type',
            'name' => 'illios_cache_settings[cloudflare_apo_cache_by_device_type]',
            'value' => isset($this->options['cloudflare_apo_cache_by_device_type']) && $this->options['cloudflare_apo_cache_by_device_type'],
            'label' => 'Separate cache for mobile devices',
            'class' => 'class="apo-field"',
            'disabled' => $disabled,
            'description' => 'Creates separate cache versions for desktop and mobile devices'
        ));
    }

    public function varnish_enabled_callback() {
        $this->render_field('checkbox-field.php', array(
            'id' => 'varnish_enabled',
            'name' => 'illios_cache_settings[varnish_enabled]',
            'value' => isset($this->options['varnish_enabled']) && $this->options['varnish_enabled'],
            'label' => 'Enable automatic Varnish cache purging'
        ));
    }

    public function varnish_servers_callback() {
        $disabled = (!isset($this->options['varnish_enabled']) || !$this->options['varnish_enabled']);
        $this->render_field('textarea-field.php', array(
            'id' => 'varnish_servers',
            'name' => 'illios_cache_settings[varnish_servers]',
            'value' => isset($this->options['varnish_servers']) ? $this->options['varnish_servers'] : '',
            'rows' => 5,
            'class' => 'class="large-text varnish-field" style="max-width: 350px;"',
            'disabled' => $disabled,
            'description' => 'List of Varnish server IPs, one per line (e.g., 127.0.0.1:6081)'
        ));
    }

    public function varnish_timeout_callback() {
        $disabled = (!isset($this->options['varnish_enabled']) || !$this->options['varnish_enabled']);
        $timeout = isset($this->options['varnish_timeout']) ? $this->options['varnish_timeout'] : 30;
        $this->render_field('text-field.php', array(
            'id' => 'varnish_timeout',
            'name' => 'illios_cache_settings[varnish_timeout]',
            'value' => $timeout,
            'type' => 'number',
            'min' => 5,
            'max' => 60,
            'class' => 'class="small-text varnish-field"',
            'disabled' => $disabled,
            'description' => 'Timeout for Varnish purge requests (5-60 seconds, default: 30)'
        ));
    }

    public function purge_on_post_save_callback() {
        $this->render_field('checkbox-field.php', array(
            'id' => 'purge_on_post_save',
            'name' => 'illios_cache_settings[purge_on_post_save]',
            'value' => isset($this->options['purge_on_post_save']) && $this->options['purge_on_post_save'],
            'label' => 'Automatically purge cache when posts are saved/updated'
        ));
    }

    public function purge_on_comment_callback() {
        $this->render_field('checkbox-field.php', array(
            'id' => 'purge_on_comment',
            'name' => 'illios_cache_settings[purge_on_comment]',
            'value' => isset($this->options['purge_on_comment']) && $this->options['purge_on_comment'],
            'label' => 'Automatically purge cache when comments are posted'
        ));
    }

    // AJAX Handlers (unchanged from original)
    public function handle_test_connection() {
        if (!wp_verify_nonce($_POST['nonce'], 'illios_cache_test')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $cf_handler = new Illios_Cache_Cloudflare_Handler();
        $result = $cf_handler->verify_credentials();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success('Credentials verified successfully');
    }

    public function handle_apply_wp_settings() {
        if (!wp_verify_nonce($_POST['nonce'], 'illios_cache_wp_settings')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $cf_handler = new Illios_Cache_Cloudflare_Handler();
        $result = $cf_handler->apply_wordpress_settings();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }

    public function handle_get_dev_mode_status() {
        if (!wp_verify_nonce($_POST['nonce'], 'illios_cache_dev_mode_status')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $cf_handler = new Illios_Cache_Cloudflare_Handler();
        $result = $cf_handler->get_development_mode_status();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }

    public function handle_toggle_apo() {
        if (!wp_verify_nonce($_POST['nonce'], 'illios_cache_apo')) {
            wp_send_json_error('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $cf_handler = new Illios_Cache_Cloudflare_Handler();

        if (!$cf_handler->is_enabled()) {
            wp_send_json_error('Cloudflare is not configured.');
        }

        $plan = $cf_handler->get_account_plan();
        if (!$plan) {
            wp_send_json_error('Cloudflare API key not configured.');
        }

        if ($plan === 'free') {
            wp_send_json_error('APO cannot be enabled on the Free plan. Requires $5/month or Pro+ plan.');
        }

        $current_status = $cf_handler->get_apo_status();

        if (is_wp_error($current_status)) {
            wp_send_json_error($current_status->get_error_message());
        }

        $is_enabled = isset($current_status['result']['value']['enabled']) && $current_status['result']['value']['enabled'];
        $result = $cf_handler->set_apo_status(!$is_enabled);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    public function handle_toggle_dev_mode() {
        if (!wp_verify_nonce($_POST['nonce'], 'illios_cache_dev_mode')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $cf_handler = new Illios_Cache_Cloudflare_Handler();
        $current_status = $cf_handler->get_development_mode();
        
        if (is_wp_error($current_status)) {
            wp_send_json_error($current_status->get_error_message());
        }

        $is_enabled = isset($current_status['result']['value']) && $current_status['result']['value'] === 'on';
        $result = $cf_handler->set_development_mode(!$is_enabled);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
}