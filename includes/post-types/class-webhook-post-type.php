<?php

class TMGMT_Webhook_Post_Type {

    const POST_TYPE = 'tmgmt_webhook';

    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
        
        // AJAX handler for testing webhook
        add_action('wp_ajax_tmgmt_test_webhook', array($this, 'handle_test_webhook'));
    }

    public function register_post_type() {
        $labels = array(
            'name'                  => 'Webhooks',
            'singular_name'         => 'Webhook',
            'menu_name'             => 'Webhooks',
            'add_new'               => 'Neuen Webhook erstellen',
            'add_new_item'          => 'Neuen Webhook erstellen',
            'edit_item'             => 'Webhook bearbeiten',
            'new_item'              => 'Neuer Webhook',
            'view_item'             => 'Webhook ansehen',
            'search_items'          => 'Webhooks suchen',
            'not_found'             => 'Keine Webhooks gefunden',
            'not_found_in_trash'    => 'Keine Webhooks im Papierkorb gefunden',
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'webhook'),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array('title'),
        );

        register_post_type(self::POST_TYPE, $args);
    }

    public function add_meta_boxes() {
        add_meta_box(
            'tmgmt_webhook_settings',
            'Einstellungen',
            array($this, 'render_settings_box'),
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public function render_settings_box($post) {
        wp_nonce_field('tmgmt_save_webhook', 'tmgmt_webhook_nonce');

        $url = get_post_meta($post->ID, '_tmgmt_webhook_url', true);
        $method = get_post_meta($post->ID, '_tmgmt_webhook_method', true);
        if (!$method) $method = 'POST';
        ?>
        <div style="margin-bottom: 15px;">
            <label for="tmgmt_webhook_url" style="display:block; font-weight:bold; margin-bottom:5px;">URL</label>
            <input type="url" id="tmgmt_webhook_url" name="tmgmt_webhook_url" value="<?php echo esc_attr($url); ?>" class="widefat" placeholder="https://n8n.example.com/webhook/..." required>
        </div>

        <div style="margin-bottom: 15px;">
            <label for="tmgmt_webhook_method" style="display:block; font-weight:bold; margin-bottom:5px;">Methode</label>
            <select id="tmgmt_webhook_method" name="tmgmt_webhook_method">
                <option value="POST" <?php selected($method, 'POST'); ?>>POST</option>
                <option value="GET" <?php selected($method, 'GET'); ?>>GET</option>
            </select>
        </div>

        <div style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
            <button type="button" class="button button-secondary" id="tmgmt-test-webhook" data-id="<?php echo esc_attr($post->ID); ?>">Webhook testen</button>
            <span id="tmgmt-test-result" style="margin-left: 10px; font-style: italic;"></span>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#tmgmt-test-webhook').on('click', function() {
                var btn = $(this);
                var resultSpan = $('#tmgmt-test-result');
                var postId = btn.data('id');
                
                // If URL field is changed but not saved, warn user
                // For simplicity, we just use the saved ID. Ideally we should save first.
                
                btn.prop('disabled', true).text('Teste...');
                resultSpan.text('');

                $.post(ajaxurl, {
                    action: 'tmgmt_test_webhook',
                    post_id: postId,
                    nonce: '<?php echo wp_create_nonce('tmgmt_test_webhook_nonce'); ?>'
                }, function(response) {
                    btn.prop('disabled', false).text('Webhook testen');
                    if (response.success) {
                        resultSpan.css('color', 'green').text('Erfolg: ' + response.data.message);
                    } else {
                        resultSpan.css('color', 'red').text('Fehler: ' + response.data.message);
                    }
                });
            });
        });
        </script>
        <?php
    }

    public function save_meta_boxes($post_id) {
        if (!isset($_POST['tmgmt_webhook_nonce']) || !wp_verify_nonce($_POST['tmgmt_webhook_nonce'], 'tmgmt_save_webhook')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['tmgmt_webhook_url'])) {
            update_post_meta($post_id, '_tmgmt_webhook_url', esc_url_raw($_POST['tmgmt_webhook_url']));
        }
        
        if (isset($_POST['tmgmt_webhook_method'])) {
            update_post_meta($post_id, '_tmgmt_webhook_method', sanitize_text_field($_POST['tmgmt_webhook_method']));
        }
    }

    public function handle_test_webhook() {
        check_ajax_referer('tmgmt_test_webhook_nonce', 'nonce');

        $post_id = intval($_POST['post_id']);
        $url = get_post_meta($post_id, '_tmgmt_webhook_url', true);
        $method = get_post_meta($post_id, '_tmgmt_webhook_method', true);

        if (empty($url)) {
            wp_send_json_error(array('message' => 'Keine URL gespeichert. Bitte erst speichern.'));
        }

        $body_data = array('test' => true, 'message' => 'Hello from TMGMT');

        if ($method === 'GET') {
            // For GET, append data to URL as query parameters
            // Sending JSON body with GET is non-standard and often dropped by servers/proxies
            $url = add_query_arg($body_data, $url);
            $args = array(
                'method' => $method,
                'timeout' => 10,
                // No body for GET
            );
        } else {
            // For POST, send JSON body
            $args = array(
                'method' => $method,
                'timeout' => 10,
                'body' => json_encode($body_data),
                'headers' => array('Content-Type' => 'application/json')
            );
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code >= 200 && $code < 300) {
            wp_send_json_success(array('message' => "Status $code"));
        } else {
            wp_send_json_error(array('message' => "Status $code: " . substr($body, 0, 50)));
        }
    }
}
