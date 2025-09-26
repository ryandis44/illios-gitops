<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap illios-cache-admin-wrap">
    <h1><?php esc_html_e('Illios Cache Settings', 'illios-cache'); ?></h1>

    <form method="post" action="options.php" id="illios-cache-settings-form">
        <?php
        settings_fields('illios_cache_settings_group');
        do_settings_sections('illios-cache-admin');
        submit_button(__('Save Settings', 'illios-cache'));
        ?>
    </form>

    <div class="card" style="margin-top: 20px;">
        <h2><?php esc_html_e('Manual Cache Purge', 'illios-cache'); ?></h2>
        <p><?php esc_html_e('Use these buttons to manually purge cache when needed:', 'illios-cache'); ?></p>
        <button type="button" id="purge-cloudflare" class="button button-secondary"><?php esc_html_e('Purge Cloudflare', 'illios-cache'); ?></button>
        <button type="button" id="purge-varnish" class="button button-secondary"><?php esc_html_e('Purge Varnish', 'illios-cache'); ?></button>
        <button type="button" id="purge-all" class="button button-primary"><?php esc_html_e('Purge All Caches', 'illios-cache'); ?></button>
    </div>
</div>
