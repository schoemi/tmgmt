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
        $available_actions = get_post_meta($status_def_id, '_tmgmt_available_actions', true);

        if (empty($available_actions) || !is_array($available_actions)) {
            echo '<p>Keine Aktionen für diesen Status definiert.</p>';
            return;
        }

        echo '<div class="tmgmt-actions-list">';
        foreach ($available_actions as $action_id) {
            $action_post = get_post($action_id);
            if (!$action_post || $action_post->post_status !== 'publish') continue;

            $label = $action_post->post_title;
            $type = get_post_meta($action_id, '_tmgmt_action_type', true);
            $btn_class = $type === 'webhook' ? 'button-primary' : 'button-secondary';
            
            echo '<button type="button" class="button ' . $btn_class . ' tmgmt-trigger-action" ';
            echo 'data-id="' . $action_id . '" ';
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
                var actionId = btn.data('id');
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
                                executeAction(actionId, note, $(this));
                            },
                            "Abbrechen": function() {
                                $(this).dialog("close");
                            }
                        }
                    });
                } else {
                    // Webhook - Confirm?
                    if (confirm('Möchten Sie die Aktion "' + label + '" wirklich ausführen?')) {
                        executeAction(actionId, '', null);
                    }
                }

                function executeAction(actionId, note, dialog) {
                    // Show loading state?
                    btn.prop('disabled', true).text('Verarbeite...');

                    $.post(ajaxurl, {
                        action: 'tmgmt_execute_action',
                        event_id: eventId,
                        action_id: actionId,
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
        $action_id = intval($_POST['action_id']);
        $note = isset($_POST['note']) ? sanitize_textarea_field($_POST['note']) : '';

        // 1. Get Action Post
        $action_post = get_post($action_id);
        if (!$action_post || $action_post->post_type !== 'tmgmt_action') {
            wp_send_json_error(array('message' => 'Aktion nicht gefunden.'));
        }

        // 2. Get Action Meta
        $action_type = get_post_meta($action_id, '_tmgmt_action_type', true);
        $webhook_id = get_post_meta($action_id, '_tmgmt_action_webhook_id', true);
        $email_template_id = get_post_meta($action_id, '_tmgmt_action_email_template_id', true);
        $target_status = get_post_meta($action_id, '_tmgmt_action_target_status', true);

        $log_manager = new TMGMT_Log_Manager();
        $log_message = "Aktion ausgeführt: " . $action_post->post_title;

        // 3. Get Current Status (for validation if needed, or logging)
        $status_slug = get_post_meta($event_id, '_tmgmt_status', true);

        // 4. Execute Logic based on Type
        if ($action_type === 'webhook') {
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

        } elseif ($action_type === 'email') {
            // Email Logic
            if (empty($email_template_id)) {
                wp_send_json_error(array('message' => 'Keine E-Mail Vorlage ausgewählt.'));
            }

            $recipient_raw = get_post_meta($email_template_id, '_tmgmt_email_recipient', true);
            $subject_raw = get_post_meta($email_template_id, '_tmgmt_email_subject', true);
            $body_raw = get_post_meta($email_template_id, '_tmgmt_email_body', true);
            $cc_raw = get_post_meta($email_template_id, '_tmgmt_email_cc', true);
            $bcc_raw = get_post_meta($email_template_id, '_tmgmt_email_bcc', true);
            $reply_to_raw = get_post_meta($email_template_id, '_tmgmt_email_reply_to', true);

            $recipient = TMGMT_Placeholder_Parser::parse($recipient_raw, $event_id);
            $subject = TMGMT_Placeholder_Parser::parse($subject_raw, $event_id);
            $body = TMGMT_Placeholder_Parser::parse($body_raw, $event_id);
            
            // Optional Headers
            $headers = array('Content-Type: text/html; charset=UTF-8');
            
            if (!empty($cc_raw)) {
                $cc = TMGMT_Placeholder_Parser::parse($cc_raw, $event_id);
                if (!empty($cc)) $headers[] = 'Cc: ' . $cc;
            }
            
            if (!empty($bcc_raw)) {
                $bcc = TMGMT_Placeholder_Parser::parse($bcc_raw, $event_id);
                if (!empty($bcc)) $headers[] = 'Bcc: ' . $bcc;
            }
            
            if (!empty($reply_to_raw)) {
                $reply_to = TMGMT_Placeholder_Parser::parse($reply_to_raw, $event_id);
                if (!empty($reply_to)) $headers[] = 'Reply-To: ' . $reply_to;
            }

            // Send
            $sent = wp_mail($recipient, $subject, nl2br($body), $headers);

            if ($sent) {
                $log_message .= " - E-Mail gesendet an: $recipient";
                $log_manager->log($event_id, 'email_sent', "E-Mail '$subject' an $recipient gesendet.");
            } else {
                $log_manager->log($event_id, 'email_error', "Fehler beim Senden der E-Mail an $recipient.");
                wp_send_json_error(array('message' => 'E-Mail konnte nicht gesendet werden.'));
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
        if (!empty($target_status)) {
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
