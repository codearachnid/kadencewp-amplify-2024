<?php

// This will replace the 'wp-json' REST API prefix with 'api'.
// Be sure to flush your rewrite rules for this change to work.
// add_filter( 'rest_url_prefix', function () {
// 	return 'api';
// });
function add_api_rewrite_rule() {
    add_rewrite_rule('^api/(.*)?', 'index.php?rest_route=/$matches[1]', 'top');
}
add_action('init', 'add_api_rewrite_rule');

function flush_rewrite_rules_on_activation() {
    add_api_rewrite_rule();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'flush_rewrite_rules_on_activation');

function flush_rewrite_rules_on_deactivation() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'flush_rewrite_rules_on_deactivation');
