<?php
/**
 * Plugin Name: OIDC SSO Auto-login
 * Description: Starts OIDC login via OIDCG and bypasses the login screen. Use /?oidc_sso=1 optionally with &redirect_to=<path>
 */

add_action('parse_request', function () {
    if (empty($_GET['oidc_sso'])) {
        return;
    }

    // Where to land after login
    $redirect_to = isset($_GET['redirect_to']) ? wp_unslash($_GET['redirect_to']) : admin_url();
    $redirect_to = wp_validate_redirect($redirect_to, admin_url());

    // Already logged in? Go straight there.
    if (is_user_logged_in()) {
        wp_safe_redirect($redirect_to);
        exit;
    }

    // Require the OIDCG shortcode that generates the proper authorize URL & state
    if (!shortcode_exists('openid_connect_generic_auth_url')) {
        status_header(500);
        wp_die('OpenID Connect Generic plugin not active.');
    }

    // Ask the plugin to build the IdP authorize URL (this mints & stores STATE)
    $auth_url = do_shortcode('[openid_connect_generic_auth_url redirect_to="' . esc_url_raw($redirect_to) . '"]');
    $auth_url = trim($auth_url);

    // If something wrapped it in markup, extract the href just in case
    if (preg_match('#href=["\']([^"\']+)#', $auth_url, $m)) {
        $auth_url = $m[1];
    }

    if (!filter_var($auth_url, FILTER_VALIDATE_URL)) {
        status_header(500);
        wp_die('Could not build OIDC authorization URL.');
    }

    nocache_headers();
    wp_redirect($auth_url); // Redirect to IdP
    exit;
});
