<?php

class TMGMT_Action_Handler {

    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('wp_ajax_tmgmt_execute_action', array($this, 'handle_execute_action'));
    }

    public function add_meta_boxes() {
        add_meta_box(
            'tmgmt_event_actions',
            'Aktionen',
            array($this, 'render_actions_box'),
            'event',
            'side',
            'high'
        );
    }

    public function render_actions_box($post) {
        $status_slug = get_post_meta($post->ID, '_tmgmt_status', true);
        
        if (empty($status_slug)) {
            echo '<p>Bitte erst einen Status speichern.</p>';
            return;
        }

        // Find Status Definition
        $args = array(
            'name'        => $status_slug,
            'post_type'   => 'tmgmt_status_def',
            'post_status' => 'publish',
            'numberposts' => 1
        );
        $status_posts = get_posts($args);

        if (empty($status_posts)) {
            echo '<p>Keine Aktionen verfügbar (Status Definition nicht gefunden).</p>';
            return;
        }

        $status_def_id = $status_posts[0]->ID;
        $actions = get_post_meta($status_def_id, '_tmgmt_status_actions', true);

        if (empty($actions) || !is_array($actions)) {
            echo '<p>Keine Aktionen für diesen Status definiert.</p>';
            return;
        }

        echo '<div class="tmgmt-actions-list">';
        foreach ($actions as $index => $action) {
            $label = isset($action['label']) ? $action['label'] : 'Aktion';
            $type = isset($action['type']) ? $action['type'] : 'note';
            $btn_class = $type === 'webhook' ? 'button-primary' : 'button-secondary';
            
            echo '<button type="button" class="button ' . $btn_class . ' tmgmt-trigger-action" ';
            echo 'data-index="' . $index . '" ';
            echo 'data-label="' . esc_attr($label) . '" ';
            echo 'data-type="' . esc_attr($type) . '" ';
            echo 'style="width:100%; margin-bottom:5px;">';
            echo esc_html($label);
            echo '</button>';
        }
        echo '</div>';

        // Hidden container for dialogs
        ?>
        <div id="tmgmt-action-dialog" style="display:none;">
            <p id="tmgmt-action-description"></p>
            <textarea id="tmgmt-action-note" rows="5" class="widefat" placeholder="Notiz eingeben..."></textarea>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('.tmgmt-trigger-action').on('click', function() {
                var btn = $(this);
                var index = btn.data('index');
                var type = btn.data('type');
                var label = btn.data('label');
                var eventId = <?php echo $post->ID; ?>;

                if (type === 'note') {
                    // Open Dialog
                    $('#tmgmt-action-description').text('Notiz für "' + label + '" erfassen:');
                    $('#tmgmt-action-note').val('');
                    
                    $('#tmgmt-action-dialog').dialog({
                        title: label,
                        modal: true,
                        width: 400,
                        buttons: {
                            "Speichern & Ausführen": function() {
                                var note = $('#tmgmt-action-note').val();
                                executeAction(index, note, $(this));
                            },
                            "Abbrechen": function() {
                                $(this).dialog("close");
                            }
                        }
                    });
                } else {
                    // Webhook - Confirm?
                    if (confirm('Möchten Sie die Aktion "' + label + '" wirklich ausführen?')) {
                        executeAction(index, '', null);
                    }
                }

                function executeAction(index, note, dialog) {
                    // Show loading state?
                    btn.prop('disabled', true).text('Verarbeite...');

                    $.post(ajaxurl, {
                        action: 'tmgmt_execute_action',
                        event_id: eventId,
                        action_index: index,
                        note: note,
                        nonce: '<?php echo wp_create_nonce('tmgmt_execute_action_nonce'); ?>'
                    }, function(response) {
                        if (dialog) {
                            dialog.dialog("close");
                        }
                        
                        if (response.success) {
                            alert(response.data.message);
                            // Reload page to show new status / logs
                            location.reload();
                        } else {
                            alert('Fehler: ' + response.data.message);
                            btn.prop('disabled', false).text(label);
                        }
                    });
                }
            });
        });
        </script>
        <?php
    }

    public function handle_execute_action() {
        check_ajax_referer('tmgmt_execute_action_nonce', 'nonce');

        $event_id = intval($_POST['event_id']);
        $action_index = intval($_POST['action_index']);
        $note = isset($_POST['note']) ? sanitize_textarea_field($_POST['note']) : '';

        // 1. Get Current Status
        $status_slug = get_post_meta($event_id, '_tmgmt_status', true);
        
        // 2. Get Status Definition
        $args = array(
            'name'        => $status_slug,
            'post_type'   => 'tmgmt_status_def',
            'post_status' => 'publish',
            'numberposts' => 1
        );
        $status_posts = get_posts($args);
        if (empty($status_posts)) {
            wp_send_json_error(array('message' => 'Status Definition nicht gefunden.'));
        }
        $status_def_id = $status_posts[0]->ID;

        // 3. Get Action
        $actions = get_post_meta($status_def_id, '_tmgmt_status_actions', true);
        if (!isset($actions[$action_index])) {
            wp_send_json_error(array('message' => 'Aktion nicht gefunden.'));
        }
        $action = $actions[$action_index];

        $log_manager = new TMGMT_Log_Manager();
        $log_message = "Aktion ausgeführt: " . $action['label'];

        // 4. Execute Logic based on Type
        if ($action['type'] === 'webhook') {
            $webhook_id = $action['webhook_id'];
            $webhook_url = get_post_meta($webhook_id, '_tmgmt_webhook_url', true);
            $webhook_method = get_post_meta($webhook_id, '_tmgmt_webhook_method', true);

            if (empty($webhook_url)) {
                wp_send_json_error(array('message' => 'Webhook URL fehlt.'));
            }

            // Prepare Payload
            $payload = $this->get_event_payload($event_id);
            
            if ($webhook_method === 'GET') {
                $url = add_query_arg($payload, $webhook_url);
                $args = array('method' => 'GET', 'timeout' => 20);
            } else {
                $args = array(
                    'method' => 'POST',
                    'timeout' => 20,
                    'body' => json_encode($payload),
                    'headers' => array('Content-Type' => 'application/json')
                );
            }

            $response = wp_remote_request($webhook_url, $args); // Use original URL for POST, modified for GET? Wait.
            // Fix: For GET use $url, for POST use $webhook_url
            $request_url = ($webhook_method === 'GET') ? $url : $webhook_url;
            $response = wp_remote_request($request_url, $args);

            if (is_wp_error($response)) {
                $log_manager->log($event_id, 'webhook_error', "Webhook Fehler: " . $response->get_error_message());
                wp_send_json_error(array('message' => 'Webhook fehlgeschlagen: ' . $response->get_error_message()));
            }

            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            $log_message .= " (Status: $code)";
            $log_manager->log($event_id, 'webhook_response', "Webhook Response ($code): " . substr($body, 0, 200));

            if ($code < 200 || $code >= 300) {
                wp_send_json_error(array('message' => "Webhook Server Fehler ($code)"));
            }

        } else {
            // Note Type
            if (!empty($note)) {
                $log_message .= " - Notiz: " . $note;
            }
        }

        // Log the main action
        $log_manager->log($event_id, 'action', $log_message);

        // 5. Handle Status Change
        if (!empty($action['target_status'])) {
            $target_status = $action['target_status'];
            if ($target_status !== $status_slug) {
                update_post_meta($event_id, '_tmgmt_status', $target_status);
                
                // Log Status Change (Standard Template)
                // We can reuse the logic from save_post or just log it simply here
                $log_manager->log($event_id, 'status_change', "Status durch Aktion geändert auf: " . TMGMT_Event_Status::get_label($target_status));
            }
        }

        wp_send_json_success(array('message' => 'Aktion erfolgreich ausgeführt.'));
    }

    private function get_event_payload($event_id) {
        $post = get_post($event_id);
        $meta = get_post_meta($event_id);
        
        // Clean up meta (remove internal keys if needed, or just send all)
        // Flatten meta (get_post_meta returns arrays)
        $flat_meta = array();
        foreach ($meta as $key => $val) {
            $flat_meta[$key] = $val[0];
        }

        return array(
            'id' => $event_id,
            'title' => $post->post_title,
            'status' => $flat_meta['_tmgmt_status'],
            'meta' => $flat_meta,
            'permalink' => get_permalink($event_id)
        );
    }
}
