<?php

/**
 * Allow logout without confirmation
 */

add_action('init', 'logout_without_confirm');
function logout_without_confirm() {
    if (isset($_GET['action']) && $_GET['action'] === 'logout') {
        // Check the nonce for security purposes
        if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'log-out')) {
            wp_logout();
            wp_redirect(home_url()); // Redirect to homepage or any URL you prefer
            exit;
        } else {
            // Generate the logout URL with the nonce and redirect to it
            $log_out_url = add_query_arg('_wpnonce', wp_create_nonce('log-out'), wp_logout_url());
            wp_redirect($log_out_url);
            exit;
        }
    }
}
