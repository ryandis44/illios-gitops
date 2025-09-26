<?php
// In your page-admin-settings.php, replace the do_settings_sections call with custom sections

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap illios-cache-admin-wrap">
    <h1><?php esc_html_e('Illios Cache Settings', 'illios-cache'); ?></h1>

    <form method="post" action="options.php" id="illios-cache-settings-form">
        <?php settings_fields('illios_cache_settings_group'); ?>
        
        <!-- Global Dev Mode Section -->
        <div class="illios-section">
            <h2>Development Mode</h2>
            <table class="form-table">
                <?php do_settings_fields('illios-cache-admin', 'global_dev_mode_section'); ?>
            </table>
        </div>

        <!-- Cloudflare Section with branded styling -->
        <div class="illios-section illios-cloudflare-section">
            <h2><span class="section-title">Cloudflare Settings</span></h2>
            <div class="section-description">
                <p>Configure your Cloudflare API settings below.</p>
            </div>
            <table class="form-table">
                <?php do_settings_fields('illios-cache-admin', 'cloudflare_section'); ?>
            </table>

            <br/>
            <br/>
            <br/>
        <!-- APO Section (part of Cloudflare) -->

            <h2>Cloudflare Automatic Platform Optimization (APO)</h2>
            <div class="section-description">
                <p>Automatic Platform Optimization caches your entire WordPress site on Cloudflare's edge network for maximum performance. <strong>Note:</strong> APO costs $5/month on Free plans or is included with Pro+ plans.</p>
            </div>
            <table class="form-table">
                <?php do_settings_fields('illios-cache-admin', 'apo_section'); ?>
            </table>
        </div>

        <!-- Varnish Section with branded styling -->
        <div class="illios-section illios-varnish-section">
            <h2><span class="section-title">Varnish Settings</span></h2>
            <div class="section-description">
                <p>Configure your Varnish server settings below:</p>
            </div>
            <table class="form-table">
                <?php do_settings_fields('illios-cache-admin', 'varnish_section'); ?>
            </table>
        </div>

        <!-- Auto Purge Section -->
        <div class="illios-section">
            <h2>Auto Purge Settings</h2>
            <div class="section-description">
                <p>Configure automatic cache purging triggers:</p>
            </div>
            <table class="form-table">
                <?php do_settings_fields('illios-cache-admin', 'auto_purge_section'); ?>
            </table>
        </div>

        <?php submit_button(__('Save Settings', 'illios-cache')); ?>
    </form>

    <div class="card" style="margin-top: 20px;">
        <h2><?php esc_html_e('Manual Cache Purge', 'illios-cache'); ?></h2>
        <p><?php esc_html_e('Use these buttons to manually purge cache when needed:', 'illios-cache'); ?></p>
        <button type="button" id="purge-cloudflare" class="button button-secondary"><?php esc_html_e('Purge Cloudflare', 'illios-cache'); ?></button>
        <button type="button" id="purge-varnish" class="button button-secondary"><?php esc_html_e('Purge Varnish', 'illios-cache'); ?></button>
        <button type="button" id="purge-all" class="button button-primary"><?php esc_html_e('Purge All Caches', 'illios-cache'); ?></button>
    </div>
</div>