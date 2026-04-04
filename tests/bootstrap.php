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

// Canonical WP_Error stub — loaded once here so all test files share the same definition.
if (!class_exists('WP_Error')) {
    class WP_Error {
        private string $code;
        private string $message;
        private array $data;
        public function __construct(string $code = '', string $message = '', $data = array()) {
            $this->code    = $code;
            $this->message = $message;
            $this->data    = is_array($data) ? $data : array();
        }
        public function get_error_code(): string    { return $this->code; }
        public function get_error_message(): string { return $this->message; }
        public function get_error_data($code = '')  { return $this->data; }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing): bool {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

// In-memory post type registry for testing
global $test_registered_post_types;
$test_registered_post_types = array();

if (!function_exists('register_post_type')) {
    function register_post_type($post_type, $args = array()) {
        global $test_registered_post_types;
        $test_registered_post_types[$post_type] = $args;
        return (object) $args;
    }
}

if (!function_exists('get_post_type_object')) {
    function get_post_type_object($post_type) {
        global $test_registered_post_types;
        if (isset($test_registered_post_types[$post_type])) {
            return (object) $test_registered_post_types[$post_type];
        }
        return null;
    }
}

if (!function_exists('add_action')) {
    function add_action($tag, $callback, $priority = 10, $accepted_args = 1) {}
}

if (!function_exists('add_filter')) {
    function add_filter($tag, $callback, $priority = 10, $accepted_args = 1) {}
}

if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value, ...$args) {
        return $value;
    }
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
        global $test_current_user_caps;
        if (isset($test_current_user_caps) && is_array($test_current_user_caps)) {
            return in_array($capability, $test_current_user_caps, true);
        }
        return true;
    }
}

if (!function_exists('get_post_type')) {
    function get_post_type($post = null) {
        global $test_current_post_type, $test_post_store;
        // If a post ID is given, look up its type from the post store
        if ($post !== null && is_numeric($post) && isset($test_post_store) && is_array($test_post_store) && isset($test_post_store[$post])) {
            return $test_post_store[$post]->post_type;
        }
        if (isset($test_current_post_type)) {
            return $test_current_post_type;
        }
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
        global $test_post_store;
        if (isset($test_post_store) && is_array($test_post_store) && isset($test_post_store[$post_id])) {
            return $test_post_store[$post_id];
        }
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
        if (!empty($GLOBALS['test_current_screen_post_type'])) {
            return (object) array('post_type' => $GLOBALS['test_current_screen_post_type']);
        }
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
        global $test_json_response;
        $test_json_response = array('success' => true, 'data' => $data);
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null) {
        global $test_json_response;
        $test_json_response = array('success' => false, 'data' => $data);
    }
}

if (!function_exists('get_edit_post_link')) {
    function get_edit_post_link($post_id = 0, $context = 'display') {
        return 'http://example.com/wp-admin/post.php?post=' . intval($post_id) . '&action=edit';
    }
}

if (!function_exists('delete_post_meta')) {
    function delete_post_meta($post_id, $meta_key, $meta_value = '') {
        global $test_post_meta_store;
        if (isset($test_post_meta_store[$post_id][$meta_key])) {
            unset($test_post_meta_store[$post_id][$meta_key]);
            return true;
        }
        return false;
    }
}

// In-memory options store for testing
global $test_options_store;
$test_options_store = array();

if (!function_exists('update_option')) {
    function update_option($option, $value, $autoload = null) {
        global $test_options_store;
        $test_options_store[$option] = $value;
        return true;
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        global $test_options_store;
        if (array_key_exists($option, $test_options_store)) {
            return $test_options_store[$option];
        }
        return $default;
    }
}

require_once dirname(__DIR__) . '/includes/post-types/class-veranstalter-post-type.php';

// Stubs for TMGMT_Event_Meta_Boxes dependencies

if (!function_exists('get_posts')) {
    function get_posts($args = array()) {
        global $test_post_store;
        if (!isset($test_post_store) || !is_array($test_post_store)) {
            return array();
        }
        $post_type   = $args['post_type'] ?? '';
        $post_status = $args['post_status'] ?? 'publish';
        $results     = array();
        foreach ($test_post_store as $post) {
            if (isset($post->post_type) && $post->post_type === $post_type
                && isset($post->post_status) && $post->post_status === $post_status) {
                $results[] = $post;
            }
        }
        return $results;
    }
}

if (!function_exists('esc_textarea')) {
    function esc_textarea($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url) {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
}

if (!function_exists('current_time')) {
    function current_time($type, $gmt = 0) {
        return date($type);
    }
}

if (!function_exists('selected')) {
    function selected($selected, $current = true, $echo = true) {
        $result = ($selected == $current) ? ' selected="selected"' : '';
        if ($echo) echo $result;
        return $result;
    }
}

if (!function_exists('get_permalink')) {
    function get_permalink($post_id = 0) {
        return 'http://example.com/?p=' . intval($post_id);
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '') {
        return 'http://example.com/wp-admin/' . ltrim($path, '/');
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1) {
        return 'test_nonce';
    }
}

if (!function_exists('wp_get_attachment_url')) {
    function wp_get_attachment_url($attachment_id = 0) {
        return 'http://example.com/wp-content/uploads/test-file.pdf';
    }
}

if (!function_exists('get_the_date')) {
    function get_the_date($format = '', $post_id = null) {
        return date($format ?: 'Y-m-d');
    }
}

if (!function_exists('get_post_mime_type')) {
    function get_post_mime_type($post_id = null) {
        return 'application/pdf';
    }
}

// Stub classes required by TMGMT_Event_Meta_Boxes
if (!class_exists('TMGMT_Log_Manager')) {
    class TMGMT_Log_Manager {
        public function render_log_table($post_id) {}
        public function log($post_id, $type, $message) {
            global $test_log_calls;
            if (isset($test_log_calls) && is_array($test_log_calls)) {
                $test_log_calls[] = [
                    'post_id' => $post_id,
                    'type'    => $type,
                    'message' => $message,
                ];
            }
        }
    }
}

if (!class_exists('TMGMT_Event_Status')) {
    class TMGMT_Event_Status {
        public static function get_all_statuses() {
            return array();
        }
        public static function get_label($status) {
            return $status;
        }
    }
}

require_once dirname(__DIR__) . '/includes/post-types/class-event-meta-boxes.php';

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir() {
        $upload_dir = sys_get_temp_dir() . '/tmgmt-test-uploads';
        return array(
            'basedir' => $upload_dir,
            'baseurl' => 'http://example.com/wp-content/uploads',
            'error'   => false,
        );
    }
}

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($target) {
        if (file_exists($target)) {
            return @is_dir($target);
        }
        return @mkdir($target, 0777, true);
    }
}

if (!function_exists('trailingslashit')) {
    function trailingslashit($string) {
        return rtrim($string, '/\\') . '/';
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id(): int {
        return 0;
    }
}

// In-memory script localization store for testing
global $test_localized_scripts;
$test_localized_scripts = array();

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script($handle, $src = '', $deps = array(), $ver = false, $in_footer = false) {}
}

if (!function_exists('wp_localize_script')) {
    function wp_localize_script($handle, $object_name, $l10n) {
        global $test_localized_scripts;
        $test_localized_scripts[$handle][$object_name] = $l10n;
        return true;
    }
}

// Stub $wpdb global so database-dependent classes don't crash in unit tests
if (!isset($GLOBALS['wpdb'])) {
    $GLOBALS['wpdb'] = new class {
        public string $prefix = 'wp_';
        public function insert($table, $data, $format = null) { return false; }
        public function update($table, $data, $where, $format = null, $where_format = null) { return false; }
        public function get_results($query, $output = OBJECT) { return []; }
        public function get_row($query, $output = OBJECT, $y = 0) { return null; }
        public function prepare($query, ...$args) { return $query; }
        public function get_charset_collate() { return ''; }
        public function get_var($query) { return null; }
    };
}

if (!function_exists('wp_insert_attachment')) {
    function wp_insert_attachment($args, $file = false, $parent = 0, $wp_error = false) {
        global $test_attachment_counter, $test_post_store;
        if (!isset($test_attachment_counter)) {
            $test_attachment_counter = 9000;
        }
        $test_attachment_counter++;
        $attachment_id = $test_attachment_counter;

        // Store the attachment in the post store so get_post() can find it.
        if (!isset($test_post_store) || !is_array($test_post_store)) {
            $test_post_store = array();
        }
        $test_post_store[$attachment_id] = (object) array(
            'ID'             => $attachment_id,
            'post_type'      => 'attachment',
            'post_mime_type' => $args['post_mime_type'] ?? '',
            'post_title'     => $args['post_title'] ?? '',
            'post_parent'    => $args['post_parent'] ?? $parent,
            'post_status'    => 'inherit',
            'guid'           => $args['guid'] ?? '',
        );

        // Store the file path so get_attached_file() can retrieve it.
        if ($file) {
            global $test_post_meta_store;
            $test_post_meta_store[$attachment_id]['_wp_attached_file'] = $file;
        }

        return $attachment_id;
    }
}

if (!function_exists('get_attached_file')) {
    function get_attached_file($attachment_id) {
        global $test_post_meta_store;
        return $test_post_meta_store[$attachment_id]['_wp_attached_file'] ?? '';
    }
}
