<?php
/**
 * Enhanced Admin Settings Class for Illios Cache Plugin
 * Now includes APO and advanced Cloudflare features
 *
 * @package Illios_Cache
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Illios_Cache_Admin_Settings {

    private $options;

    public function __construct() {
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_menu', array($this, 'add_options_page'));
        add_action('wp_ajax_illios_cache_test_connection', array($this, 'handle_test_connection'));
        add_action('wp_ajax_illios_cache_apply_wp_settings', array($this, 'handle_apply_wp_settings'));
        add_action('wp_ajax_illios_cache_toggle_apo', array($this, 'handle_toggle_apo'));
        add_action('wp_ajax_illios_cache_toggle_dev_mode', array($this, 'handle_toggle_dev_mode'));
        add_action('wp_ajax_illios_cache_get_dev_mode_status', array($this, 'handle_get_dev_mode_status'));
        $this->options = get_option('illios_cache_settings', array());
    }

    public function admin_init() {
        register_setting(
            'illios_cache_settings_group',
            'illios_cache_settings',
            array($this, 'sanitize')
        );

        // Global Development Mode Setting
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

        // Cloudflare Settings Section
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

        // Advanced Cloudflare Management (now part of cloudflare_section)
        add_settings_field(
            'cloudflare_advanced_controls',
            '',
            array($this, 'cloudflare_advanced_controls_callback'),
            'illios-cache-admin',
            'cloudflare_section'
        );

        // APO Settings Section
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

        // Varnish Settings Section (existing)
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

        // Auto Purge Settings
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

        // Global dev mode setting
        if (isset($input['global_dev_mode_enabled'])) {
            $new_input['global_dev_mode_enabled'] = (bool) $input['global_dev_mode_enabled'];
        }

        // Cloudflare settings
        if (isset($input['cloudflare_api_token'])) {
            $new_input['cloudflare_api_token'] = sanitize_text_field($input['cloudflare_api_token']);
        }

        if (isset($input['cloudflare_zone_id'])) {
            $new_input['cloudflare_zone_id'] = sanitize_text_field($input['cloudflare_zone_id']);
        }

        if (isset($input['cloudflare_enabled'])) {
            $new_input['cloudflare_enabled'] = (bool) $input['cloudflare_enabled'];
        }

        // APO settings
        if (isset($input['cloudflare_apo_enabled'])) {
            $new_input['cloudflare_apo_enabled'] = (bool) $input['cloudflare_apo_enabled'];
        }

        if (isset($input['cloudflare_apo_cache_by_device_type'])) {
            $new_input['cloudflare_apo_cache_by_device_type'] = (bool) $input['cloudflare_apo_cache_by_device_type'];
        }

        // Varnish settings (existing)
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
        ?>
        <div class="wrap">
            <h1>Illios Cache Settings</h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('illios_cache_settings_group');
                do_settings_sections('illios-cache-admin');
                submit_button();
                ?>
            </form>
            
            <div class="card" style="margin-top: 20px;">
                <h2>Manual Cache Purge</h2>
                <p>Use these buttons to manually purge cache when needed:</p>
                <button type="button" id="purge-cloudflare" class="button button-secondary">Purge Cloudflare</button>
                <button type="button" id="purge-varnish" class="button button-secondary">Purge Varnish</button>
                <button type="button" id="purge-all" class="button button-primary">Purge All Caches</button>
            </div>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Handle enable/disable functionality
            function toggleCloudflareFields() {
                var enabled = $('#cloudflare_enabled').is(':checked');
                $('.cloudflare-field').prop('disabled', !enabled).css({
                    'opacity': enabled ? 1 : 0.5,
                    'cursor': enabled ? 'auto' : 'not-allowed'
                });
                
                $('.cloudflare-field').each(function() {
                    var $th = $(this).closest('tr').find('th');
                    if (!enabled) {
                        $th.css('color', '#999');
                    } else {
                        $th.css('color', '');
                    }
                });

                // Also toggle advanced controls
                $('.cloudflare-advanced-btn').prop('disabled', !enabled).css({
                    'opacity': enabled ? 1 : 0.5,
                    'cursor': enabled ? 'auto' : 'not-allowed'
                });
            }
            
            function toggleVarnishFields() {
                var enabled = $('#varnish_enabled').is(':checked');
                $('.varnish-field').prop('disabled', !enabled).css({
                    'opacity': enabled ? 1 : 0.5,
                    'cursor': enabled ? 'auto' : 'not-allowed'
                });
                
                $('.varnish-field').each(function() {
                    var $th = $(this).closest('tr').find('th');
                    if (!enabled) {
                        $th.css('color', '#999');
                    } else {
                        $th.css('color', '');
                    }
                });
            }

            function toggleAPOFields() {
                var enabled = $('#cloudflare_apo_enabled').is(':checked');
                var cf_enabled = $('#cloudflare_enabled').is(':checked');
                var should_enable = enabled && cf_enabled;
                
                $('.apo-field').prop('disabled', !should_enable).css({
                    'opacity': should_enable ? 1 : 0.5,
                    'cursor': should_enable ? 'auto' : 'not-allowed'
                });
                
                $('.apo-field').each(function() {
                    var $th = $(this).closest('tr').find('th');
                    if (!should_enable) {
                        $th.css('color', '#999');
                    } else {
                        $th.css('color', '');
                    }
                });
            }
            
            // Initialize field states
            toggleCloudflareFields();
            toggleVarnishFields();
            toggleAPOFields();
            
            // Bind change events
            $('#cloudflare_enabled').change(function() {
                toggleCloudflareFields();
                toggleAPOFields();
            });
            $('#varnish_enabled').change(toggleVarnishFields);
            $('#cloudflare_apo_enabled').change(toggleAPOFields);
            
            // Purge cache functionality
            $('#purge-cloudflare').click(function() {
                purgeCache('cloudflare');
            });
            
            $('#purge-varnish').click(function() {
                purgeCache('varnish');
            });
            
            $('#purge-all').click(function() {
                purgeCache('all');
            });

            // Advanced Cloudflare management functionality
            $('#test-cloudflare').click(function() {
                testConnection();
            });

            $('#apply-wp-settings').click(function() {
                applyWordPressSettings();
            });
            
            function purgeCache(type) {
                $.post(ajaxurl, {
                    action: 'illios_cache_purge',
                    type: type,
                    nonce: '<?php echo wp_create_nonce('illios_cache_purge'); ?>'
                }, function(response) {
                    if (response.success) {
                        showNotice('Cache purged successfully!', 'success');
                    } else {
                        showNotice('Error purging cache: ' + response.data, 'error');
                    }
                });
            }

            function testConnection() {
                $.post(ajaxurl, {
                    action: 'illios_cache_test_connection',
                    nonce: '<?php echo wp_create_nonce('illios_cache_test'); ?>'
                }, function(response) {
                    if (response.success) {
                        showNotice('Connection successful!', 'success');
                    } else {
                        showNotice('Connection failed: ' + response.data, 'error');
                    }
                });
            }

            function applyWordPressSettings() {
                $.post(ajaxurl, {
                    action: 'illios_cache_apply_wp_settings',
                    nonce: '<?php echo wp_create_nonce('illios_cache_wp_settings'); ?>'
                }, function(response) {
                    if (response.success) {
                        showNotice('WordPress settings applied successfully!', 'success');
                    } else {
                        showNotice('Error applying settings: ' + response.data, 'error');
                    }
                });
            }

            function globalToggleDevMode() {
                var $button = $('#global-dev-mode-toggle');
                var originalText = $button.text();
                $button.text('Processing...').prop('disabled', true);
                
                $.post(ajaxurl, {
                    action: 'illios_cache_toggle_dev_mode',
                    nonce: '<?php echo wp_create_nonce('illios_cache_dev_mode'); ?>'
                }, function(response) {
                    if (response.success) {
                        showNotice('Development mode toggled successfully!', 'success');
                        updateGlobalDevModeStatus(); // Update the status display
                    } else {
                        showNotice('Error toggling development mode: ' + response.data, 'error');
                        $button.text(originalText).prop('disabled', false);
                    }
                });
            }

            function updateGlobalDevModeStatus() {
                $.post(ajaxurl, {
                    action: 'illios_cache_get_dev_mode_status',
                    nonce: '<?php echo wp_create_nonce('illios_cache_dev_mode_status'); ?>'
                }, function(response) {
                    var $button = $('#global-dev-mode-toggle');
                    var $status = $('#global-dev-mode-status');
                    var $info = $('#global-dev-mode-info');
                    
                    if (response.success) {
                        var data = response.data;
                        
                        if (data.enabled) {
                            var timeRemaining = data.time_remaining;
                            var hours = Math.floor(timeRemaining / 3600);
                            var minutes = Math.floor((timeRemaining % 3600) / 60);
                            
                            $button.text('Disable Development Mode')
                                   .css({
                                       'background': '#d63638',
                                       'border-color': '#d63638',
                                       'color': 'white'
                                   })
                                   .prop('disabled', false);
                            
                            $info.html('Time remaining: <strong>' + hours + 'h ' + minutes + 'm</strong><br>' +
                                      'Enabled at: ' + new Date(data.enabled_at * 1000).toLocaleString());
                            $status.css('border-left-color', '#d63638').show();
                        } else {
                            $button.text('Enable Development Mode')
                                   .css({
                                       'background': '',
                                       'border-color': '',
                                       'color': ''
                                   })
                                   .prop('disabled', false);
                            $status.hide();
                        }
                    } else {
                        $button.text('Enable Development Mode').prop('disabled', false);
                        $status.hide();
                    }
                });
            }

            // Check dev mode status on page load
            updateGlobalDevModeStatus();
            
            // Update dev mode status every minute if enabled
            setInterval(updateGlobalDevModeStatus, 60000);

            function showNotice(message, type) {
                var noticeClass = type === 'error' ? 'notice-error' : 'notice-success';
                var notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
                $('.wrap h1').after(notice);
                
                setTimeout(function() {
                    notice.fadeOut(function() {
                        notice.remove();
                    });
                }, 5000);
            }
        });
        </script>
        <?php
    }

    // AJAX Handlers
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

    // Section Info Callbacks
    public function global_dev_mode_section_info() {
        print '<p>Global development mode automatically purges both Cloudflare and Varnish cache on every content change for immediate testing. <strong>Warning:</strong> This impacts performance and should only be used during active development.</p>';
        
        // Check if any caching is configured
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

    // Global Dev Mode Field Callback
    public function global_dev_mode_enabled_callback() {
        printf(
            '<input type="checkbox" id="global_dev_mode_enabled" name="illios_cache_settings[global_dev_mode_enabled]" value="1" %s />',
            isset($this->options['global_dev_mode_enabled']) && $this->options['global_dev_mode_enabled'] ? 'checked' : ''
        );
        echo '<label for="global_dev_mode_enabled">Enable global development mode (automatically purge all cache on content changes)</label>';
        echo '<p class="description">When enabled, cache is purged immediately on every post save, comment, or other content change for both Cloudflare and Varnish.</p>';
    }

    // Cloudflare Field Callbacks
    public function cloudflare_enabled_callback() {
        printf(
            '<input type="checkbox" id="cloudflare_enabled" name="illios_cache_settings[cloudflare_enabled]" value="1" %s />',
            isset($this->options['cloudflare_enabled']) && $this->options['cloudflare_enabled'] ? 'checked' : ''
        );
        echo '<label for="cloudflare_enabled">Enable Cloudflare integration and cache purging</label>';
    }

    public function cloudflare_api_token_callback() {
        $disabled = (!isset($this->options['cloudflare_enabled']) || !$this->options['cloudflare_enabled']) ? 'disabled' : '';
        printf(
            '<input type="password" id="cloudflare_api_token" name="illios_cache_settings[cloudflare_api_token]" value="%s" class="regular-text cloudflare-field" %s />',
            isset($this->options['cloudflare_api_token']) ? esc_attr($this->options['cloudflare_api_token']) : '',
            $disabled
        );
        echo '<p class="description">Your Cloudflare API token with Zone:Cache Purge and Zone:Zone Settings permissions. <a href="https://dash.cloudflare.com/profile/api-tokens" target="_blank">Create Token</a></p>';
    }

    public function cloudflare_zone_id_callback() {
        $disabled = (!isset($this->options['cloudflare_enabled']) || !$this->options['cloudflare_enabled']) ? 'disabled' : '';
        printf(
            '<input type="text" id="cloudflare_zone_id" name="illios_cache_settings[cloudflare_zone_id]" value="%s" class="regular-text cloudflare-field" %s />',
            isset($this->options['cloudflare_zone_id']) ? esc_attr($this->options['cloudflare_zone_id']) : '',
            $disabled
        );
        echo '<p class="description">Your Cloudflare Zone ID (found in the right sidebar of your domain overview)</p>';
    }

    // Advanced Cloudflare Controls
    public function cloudflare_advanced_controls_callback() {
        $disabled = (!isset($this->options['cloudflare_enabled']) || !$this->options['cloudflare_enabled']) ? 'disabled' : '';
        ?>
        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
            <button type="button" id="test-cloudflare" class="button button-secondary cloudflare-advanced-btn" <?php echo $disabled; ?>>Test Connection</button>
            <button type="button" id="apply-wp-settings" class="button button-secondary cloudflare-advanced-btn" <?php echo $disabled; ?>>Apply WordPress Settings</button>
        </div>
        <?php
    }

    // APO Field Callbacks
    public function cloudflare_apo_enabled_callback() {
        $cf_token_set = isset($this->options['cloudflare_api_token']) && !empty($this->options['cloudflare_api_token']);
        $cf_enabled = isset($this->options['cloudflare_enabled']) && $this->options['cloudflare_enabled'] && $cf_token_set;
        $disabled = !$cf_enabled ? 'disabled' : '';

        if (!$cf_enabled) {
            $account_status = 'Not configured';
        } else {
            $cf_handler = new Illios_Cache_Cloudflare_Handler();
            $plan = $cf_handler->get_account_plan();
            $account_status = $plan ? ucfirst($plan) : 'Unknown';
            if ($plan === 'free') {
                $disabled = 'disabled';
            }
        }

        $checked = isset($this->options['cloudflare_apo_enabled']) && $this->options['cloudflare_apo_enabled'] ? 'checked' : '';
        $label_style = $disabled ? 'style="color: #999; cursor: not-allowed;"' : '';

        printf(
            '<input type="checkbox" id="cloudflare_apo_enabled" name="illios_cache_settings[cloudflare_apo_enabled]" value="1" %s %s class="apo-field" />',
            $checked,
            $disabled
        );

        echo '<label for="cloudflare_apo_enabled" ' . $label_style . '>Enable Automatic Platform Optimization</label>';
        echo '<p class="description" style="margin-top:4px;">Account status: <strong>' . esc_html($account_status) . '</strong></p>';

        if (!$cf_enabled) {
            echo '<p class="description" style="color: #111;">Enable Cloudflare integration and configure first</p>';
        } elseif ($account_status === 'Free') {
            echo '<p class="description" style="color: red;">APO cannot be enabled on your current plan (Free plan only allows $5/month).</p>';
        } else {
            echo '<p class="description">Caches your entire WordPress site at Cloudflare\'s edge for maximum performance</p>';
        }
    }

    public function cloudflare_apo_cache_by_device_type_callback() {
        $cf_enabled = isset($this->options['cloudflare_enabled']) && $this->options['cloudflare_enabled'];
        $apo_enabled = isset($this->options['cloudflare_apo_enabled']) && $this->options['cloudflare_apo_enabled'];
        $disabled = (!$cf_enabled || !$apo_enabled) ? 'disabled' : '';

        $checked = isset($this->options['cloudflare_apo_cache_by_device_type']) && $this->options['cloudflare_apo_cache_by_device_type'] ? 'checked' : '';
        $label_style = $disabled ? 'style="color: #999; cursor: not-allowed;"' : '';

        printf(
            '<input type="checkbox" id="cloudflare_apo_cache_by_device_type" name="illios_cache_settings[cloudflare_apo_cache_by_device_type]" value="1" %s %s class="apo-field" />',
            $checked,
            $disabled
        );

        echo '<label for="cloudflare_apo_cache_by_device_type" ' . $label_style . '>Separate cache for mobile devices</label>';
        echo '<p class="description">Creates separate cache versions for desktop and mobile devices</p>';
    }


    // Varnish Field Callbacks (existing)
    public function varnish_enabled_callback() {
        printf(
            '<input type="checkbox" id="varnish_enabled" name="illios_cache_settings[varnish_enabled]" value="1" %s />',
            isset($this->options['varnish_enabled']) && $this->options['varnish_enabled'] ? 'checked' : ''
        );
        echo '<label for="varnish_enabled">Enable automatic Varnish cache purging</label>';
    }

    public function varnish_servers_callback() {
        $disabled = (!isset($this->options['varnish_enabled']) || !$this->options['varnish_enabled']) ? 'disabled' : '';
        printf(
            '<textarea id="varnish_servers" name="illios_cache_settings[varnish_servers]" rows="5" class="large-text varnish-field" style="max-width: 350px;" %s>%s</textarea>',
            $disabled,
            isset($this->options['varnish_servers']) ? esc_textarea($this->options['varnish_servers']) : ''
        );
        echo '<p class="description">List of Varnish server IPs, one per line (e.g., 127.0.0.1:6081)</p>';
    }

    public function varnish_timeout_callback() {
        $disabled = (!isset($this->options['varnish_enabled']) || !$this->options['varnish_enabled']) ? 'disabled' : '';
        $timeout = isset($this->options['varnish_timeout']) ? $this->options['varnish_timeout'] : 30;
        printf(
            '<input type="number" id="varnish_timeout" name="illios_cache_settings[varnish_timeout]" value="%d" min="5" max="60" class="small-text varnish-field" %s />',
            $timeout,
            $disabled
        );
        echo '<p class="description">Timeout for Varnish purge requests (5-60 seconds, default: 30)</p>';
    }

    // Auto Purge Field Callbacks
    public function purge_on_post_save_callback() {
        printf(
            '<input type="checkbox" id="purge_on_post_save" name="illios_cache_settings[purge_on_post_save]" value="1" %s />',
            isset($this->options['purge_on_post_save']) && $this->options['purge_on_post_save'] ? 'checked' : ''
        );
        echo '<label for="purge_on_post_save">Automatically purge cache when posts are saved/updated</label>';
    }

    public function purge_on_comment_callback() {
        printf(
            '<input type="checkbox" id="purge_on_comment" name="illios_cache_settings[purge_on_comment]" value="1" %s />',
            isset($this->options['purge_on_comment']) && $this->options['purge_on_comment'] ? 'checked' : ''
        );
        echo '<label for="purge_on_comment">Automatically purge cache when comments are posted</label>';
    }
}