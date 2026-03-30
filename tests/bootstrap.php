<?php
/**
 * PHPUnit Bootstrap for Veranstalter CPT Tests
 *
 * Provides minimal WordPress function stubs so the class under test
 * can be loaded and tested without a full WordPress environment.
 */

// Stub WordPress functions used by the class
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('register_post_type')) {
    function register_post_type($post_type, $args = array()) {
        return (object) $args;
    }
}

if (!function_exists('add_action')) {
    function add_action($tag, $callback, $priority = 10, $accepted_args = 1) {}
}

if (!function_exists('add_filter')) {
    function add_filter($tag, $callback, $priority = 10, $accepted_args = 1) {}
}

// In-memory meta data store for testing
global $test_post_meta_store;
$test_post_meta_store = array();

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1) {
        return true;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability, ...$args) {
        return true;
    }
}

if (!function_exists('get_post_type')) {
    function get_post_type($post = null) {
        return 'tmgmt_veranstalter';
    }
}

if (!function_exists('update_post_meta')) {
    function update_post_meta($post_id, $meta_key, $meta_value) {
        global $test_post_meta_store;
        $test_post_meta_store[$post_id][$meta_key] = $meta_value;
        return true;
    }
}

if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $meta_key = '', $single = false) {
        global $test_post_meta_store;
        if ($meta_key === '') {
            return isset($test_post_meta_store[$post_id]) ? $test_post_meta_store[$post_id] : array();
        }
        if (isset($test_post_meta_store[$post_id][$meta_key])) {
            return $single ? $test_post_meta_store[$post_id][$meta_key] : array($test_post_meta_store[$post_id][$meta_key]);
        }
        return $single ? '' : array();
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        $str = (string) $str;
        // Strip tags
        $str = strip_tags($str);
        // Remove percent-encoded characters
        // Trim whitespace and convert internal whitespace to single spaces
        $str = trim(preg_replace('/[\r\n\t ]+/', ' ', $str));
        return $str;
    }
}

if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field($action = -1, $name = '_wpnonce', $referer = true, $echo = true) {
        return '';
    }
}

if (!function_exists('add_meta_box')) {
    function add_meta_box($id, $title, $callback, $screen = null, $context = 'advanced', $priority = 'default', $callback_args = null) {}
}

if (!defined('DOING_AUTOSAVE')) {
    define('DOING_AUTOSAVE', false);
}

if (!function_exists('get_post')) {
    function get_post($post_id = null) {
        return null;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default') {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('get_current_screen')) {
    function get_current_screen() {
        return null;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0) {
        global $test_transient_store;
        if (!isset($test_transient_store)) {
            $test_transient_store = array();
        }
        $test_transient_store[$transient] = $value;
        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($transient) {
        global $test_transient_store;
        if (isset($test_transient_store[$transient])) {
            return $test_transient_store[$transient];
        }
        return false;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($transient) {
        global $test_transient_store;
        unset($test_transient_store[$transient]);
        return true;
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null) {
        // No-op in tests
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null) {
        // No-op in tests
    }
}

require_once dirname(__DIR__) . '/includes/post-types/class-veranstalter-post-type.php';
