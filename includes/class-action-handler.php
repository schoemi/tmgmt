<?php

class TMGMT_Action_Handler {

    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('wp_ajax_tmgmt_execute_action', array($this, 'handle_execute_action'));
        add_action('wp_ajax_tmgmt_delete_file', array($this, 'handle_delete_file'));
        add_action('wp_ajax_tmgmt_get_event_details', array($this, 'handle_get_event_details'));
        add_action('wp_ajax_nopriv_tmgmt_get_event_details', array($this, 'handle_get_event_details'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function enqueue_scripts($hook) {
        global $post_type;
        if ($hook == 'post.php' || $hook == 'post-new.php') {
            if ($post_type === 'event') {
                // Enqueue editor scripts so we can use wp.editor in JS
                wp_enqueue_editor();
            }
        }
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
        
        add_meta_box(
            'tmgmt_event_communication',
            'Kommunikation',
            array($this, 'render_communication_box'),
            'event',
            'normal',
            'low'
        );

        add_meta_box(
            'tmgmt_event_confirmations',
            'Angeforderte Bestätigungen',
            array($this, 'render_confirmations_box'),
            'event',
            'normal',
            'low'
        );
    }

    public function render_communication_box($post) {
        $comm_manager = new TMGMT_Communication_Manager();
        $comm_manager->render_backend_table($post->ID);
    }

    public function render_confirmations_box($post) {
        $conf_manager = new TMGMT_Confirmation_Manager();
        $conf_manager->render_backend_table($post->ID);
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
        $attachments_data = get_post_meta($post->ID, '_tmgmt_event_attachments', true);
        $attachments_data = maybe_unserialize($attachments_data);
        if (!is_array($attachments_data)) $attachments_data = array();
        ?>
        <div id="tmgmt-action-dialog" style="display:none;">
            <p id="tmgmt-action-description"></p>
            <textarea id="tmgmt-action-note" rows="5" class="widefat" placeholder="Notiz eingeben..."></textarea>
        </div>

        <div id="tmgmt-action-email-dialog" style="display:none;">
            <p>
                <label>Empfänger:</label><br>
                <input type="text" id="tmgmt-action-email-recipient" class="widefat">
            </p>
            <p>
                <label>Betreff:</label><br>
                <input type="text" id="tmgmt-action-email-subject" class="widefat">
            </p>
            <p>
                <label>Nachricht:</label><br>
                <textarea id="tmgmt-action-email-body" rows="10" class="widefat"></textarea>
            </p>
            
            <hr>
            <p><strong>Anhänge:</strong></p>
            
            <?php if (!empty($attachments_data)): ?>
                <div style="margin-bottom: 10px; max-height: 100px; overflow-y: auto; border: 1px solid #ddd; padding: 5px;">
                    <?php foreach ($attachments_data as $att): 
                        $att_id = isset($att['id']) ? $att['id'] : $att;
                        $att_post = get_post($att_id);
                        if (!$att_post) continue;
                    ?>
                        <label style="display:block;">
                            <input type="checkbox" class="tmgmt-email-existing-file" value="<?php echo esc_attr($att_id); ?>">
                            <?php echo esc_html($att_post->post_title); ?> 
                            <small>(<?php echo esc_html(basename(get_attached_file($att_id))); ?>)</small>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <p>
                <label>Neue Datei hochladen:</label><br>
                <input type="file" id="tmgmt-action-email-file">
            </p>
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
                } else if (type === 'email' || type === 'email_confirmation') {
                    // Fetch Preview
                    btn.prop('disabled', true).text('Lade Vorschau...');
                    
                    // Reset inputs
                    $('#tmgmt-action-email-file').val('');
                    $('.tmgmt-email-existing-file').prop('checked', false);

                    $.ajax({
                        url: '/wp-json/tmgmt/v1/events/' + eventId + '/actions/' + actionId + '/preview',
                        method: 'GET',
                        beforeSend: function ( xhr ) {
                            xhr.setRequestHeader( 'X-WP-Nonce', tmgmt_vars.nonce );
                        },
                        success: function(data) {
                            btn.prop('disabled', false).text(label);
                            
                            // Check for missing token
                            if (data.body && data.body.indexOf('[[MISSING_TOKEN]]') !== -1) {
                                Swal.fire({
                                    title: 'Kein Zugangstoken',
                                    text: 'Für dieses Event existiert noch kein aktiver Veranstalter-Zugang. Soll jetzt einer erstellt werden?',
                                    icon: 'warning',
                                    showCancelButton: true,
                                    confirmButtonText: 'Ja, erstellen',
                                    cancelButtonText: 'Nein, abbrechen'
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        // Create Token
                                        $.post(ajaxurl, {
                                            action: 'tmgmt_create_access_token',
                                            event_id: eventId,
                                            nonce: '<?php echo wp_create_nonce('tmgmt_access_token_nonce'); ?>'
                                        }, function(response) {
                                            if (response.success) {
                                                // Retry Preview
                                                btn.click();
                                            } else {
                                                Swal.fire('Fehler', 'Token konnte nicht erstellt werden.', 'error');
                                            }
                                        });
                                    }
                                });
                                return;
                            }

                            $('#tmgmt-action-email-recipient').val(data.recipient);
                            $('#tmgmt-action-email-subject').val(data.subject);
                            $('#tmgmt-action-email-body').val(data.body);
                            
                            $('#tmgmt-action-email-dialog').dialog({
                                title: 'E-Mail senden: ' + label,
                                modal: true,
                                width: 800,
                                open: function() {
                                    // Initialize Editor
                                    if (typeof wp !== 'undefined' && wp.editor) {
                                        wp.editor.remove('tmgmt-action-email-body');
                                        wp.editor.initialize('tmgmt-action-email-body', {
                                            tinymce: {
                                                wpautop: true,
                                                toolbar1: 'bold italic underline strikethrough | bullist numlist | blockquote hr | alignleft aligncenter alignright | link unlink | wp_more | spellchecker',
                                                height: 300
                                            },
                                            quicktags: true
                                        });
                                    }
                                },
                                close: function() {
                                    if (typeof wp !== 'undefined' && wp.editor) {
                                        wp.editor.remove('tmgmt-action-email-body');
                                    }
                                },
                                buttons: {
                                    "Senden": function() {
                                        var formData = new FormData();
                                        
                                        // File Upload
                                        var fileInput = $('#tmgmt-action-email-file')[0];
                                        if (fileInput.files.length > 0) {
                                            formData.append('email_attachment_upload', fileInput.files[0]);
                                        }

                                        // Existing Files
                                        $('.tmgmt-email-existing-file:checked').each(function() {
                                            formData.append('email_existing_attachments[]', $(this).val());
                                        });

                                        // Get content from editor
                                        var bodyContent = $('#tmgmt-action-email-body').val();
                                        if (typeof wp !== 'undefined' && wp.editor) {
                                            if (tinymce.get('tmgmt-action-email-body') && !tinymce.get('tmgmt-action-email-body').isHidden()) {
                                                bodyContent = tinymce.get('tmgmt-action-email-body').getContent();
                                            }
                                        }

                                        var emailData = {
                                            email_recipient: $('#tmgmt-action-email-recipient').val(),
                                            email_subject: $('#tmgmt-action-email-subject').val(),
                                            email_body: bodyContent
                                        };
                                        
                                        executeAction(actionId, '', $(this), emailData, formData);
                                    },
                                    "Abbrechen": function() {
                                        $(this).dialog("close");
                                    }
                                }
                            });
                        },
                        error: function() {
                            btn.prop('disabled', false).text(label);
                            Swal.fire('Fehler', 'Fehler beim Laden der Vorschau.', 'error');
                        }
                    });
                } else {
                    // Webhook - Confirm?
                    Swal.fire({
                        title: 'Aktion ausführen?',
                        text: 'Möchten Sie die Aktion "' + label + '" wirklich ausführen?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Ja, ausführen',
                        cancelButtonText: 'Abbrechen'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            executeAction(actionId, '', null);
                        }
                    });
                }

                function executeAction(actionId, note, dialog, extraData, formData) {
                    // Show loading state?
                    btn.prop('disabled', true).text('Verarbeite...');

                    var dataToSend;
                    var processData = true;
                    var contentType = 'application/x-www-form-urlencoded; charset=UTF-8';

                    if (formData) {
                        dataToSend = formData;
                        dataToSend.append('action', 'tmgmt_execute_action');
                        dataToSend.append('event_id', eventId);
                        dataToSend.append('action_id', actionId);
                        dataToSend.append('note', note);
                        dataToSend.append('nonce', '<?php echo wp_create_nonce('tmgmt_execute_action_nonce'); ?>');
                        
                        if (extraData) {
                            for (var key in extraData) {
                                dataToSend.append(key, extraData[key]);
                            }
                        }
                        
                        processData = false;
                        contentType = false;
                    } else {
                        dataToSend = {
                            action: 'tmgmt_execute_action',
                            event_id: eventId,
                            action_id: actionId,
                            note: note,
                            nonce: '<?php echo wp_create_nonce('tmgmt_execute_action_nonce'); ?>'
                        };
                        if (extraData) {
                            $.extend(dataToSend, extraData);
                        }
                    }

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: dataToSend,
                        processData: processData,
                        contentType: contentType,
                        success: function(response) {
                            if (dialog) {
                                dialog.dialog("close");
                            }
                            
                            if (response.success) {
                                Swal.fire({
                                    title: 'Erfolg',
                                    text: response.data.message,
                                    icon: 'success'
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire('Fehler', 'Fehler: ' + (response.data ? response.data.message : 'Unbekannt'), 'error');
                                btn.prop('disabled', false).text(label);
                            }
                        },
                        error: function() {
                             Swal.fire('Systemfehler', 'Ein unerwarteter Fehler ist aufgetreten.', 'error');
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

        } elseif ($action_type === 'email' || $action_type === 'email_confirmation') {
            // Email Logic
            if (empty($email_template_id)) {
                wp_send_json_error(array('message' => 'Keine E-Mail Vorlage ausgewählt.'));
            }

            // Check for overrides from dialog
            if (isset($_POST['email_recipient']) && isset($_POST['email_subject']) && isset($_POST['email_body'])) {
                $recipient = sanitize_text_field($_POST['email_recipient']);
                $subject = sanitize_text_field($_POST['email_subject']);
                $body = wp_kses_post($_POST['email_body']);
            } else {
                $recipient_raw = get_post_meta($email_template_id, '_tmgmt_email_recipient', true);
                $subject_raw = get_post_meta($email_template_id, '_tmgmt_email_subject', true);
                $body_raw = get_post_meta($email_template_id, '_tmgmt_email_body', true);

                $recipient = TMGMT_Placeholder_Parser::parse($recipient_raw, $event_id);
                $subject = TMGMT_Placeholder_Parser::parse($subject_raw, $event_id);
                $body = TMGMT_Placeholder_Parser::parse($body_raw, $event_id);
            }

            // Handle Confirmation Link
            if ($action_type === 'email_confirmation') {
                $conf_manager = new TMGMT_Confirmation_Manager();
                $request = $conf_manager->create_request($event_id, $action_id, $recipient);
                
                if ($request) {
                    $body = str_replace('{{confirmation_link}}', $request['link'], $body);
                    // Also support simple link if user didn't use HTML
                    $body = str_replace('{{confirmation_url}}', $request['link'], $body);
                } else {
                    wp_send_json_error(array('message' => 'Fehler beim Erstellen des Bestätigungs-Links.'));
                }
            }

            $cc_raw = get_post_meta($email_template_id, '_tmgmt_email_cc', true);
            $bcc_raw = get_post_meta($email_template_id, '_tmgmt_email_bcc', true);
            $reply_to_raw = get_post_meta($email_template_id, '_tmgmt_email_reply_to', true);

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

            // Handle Attachments
            $attachments = array();

            // 0. Template Attachments
            $tpl_attachments = get_post_meta($email_template_id, '_tmgmt_email_attachments', true);
            if (is_array($tpl_attachments)) {
                foreach ($tpl_attachments as $att_id) {
                    $path = get_attached_file($att_id);
                    if ($path && file_exists($path)) {
                        $attachments[] = $path;
                    }
                }
            }

            // 1. Existing Attachments (from Dialog)
            if (isset($_POST['email_existing_attachments']) && is_array($_POST['email_existing_attachments'])) {
                foreach ($_POST['email_existing_attachments'] as $att_id) {
                    $path = get_attached_file($att_id);
                    if ($path && file_exists($path)) {
                        $attachments[] = $path;
                    }
                }
            }

            // 2. New File Upload
            if (!empty($_FILES['email_attachment_upload'])) {
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                require_once(ABSPATH . 'wp-admin/includes/file.php');
                require_once(ABSPATH . 'wp-admin/includes/media.php');

                $file = $_FILES['email_attachment_upload'];
                $attachment_id = media_handle_upload('email_attachment_upload', $event_id);

                if (!is_wp_error($attachment_id)) {
                    // Add to Event Meta
                    $current_data = get_post_meta($event_id, '_tmgmt_event_attachments', true);
                    $current_data = maybe_unserialize($current_data);
                    if (!is_array($current_data)) $current_data = array();
                    
                    // Normalize current data
                    $normalized_current = array();
                    foreach ($current_data as $item) {
                        if (is_numeric($item)) {
                            $normalized_current[] = array('id' => intval($item), 'category' => '');
                        } elseif (is_array($item) && isset($item['id'])) {
                            $normalized_current[] = $item;
                        }
                    }

                    $normalized_current[] = array('id' => $attachment_id, 'category' => 'E-Mail Upload');
                    update_post_meta($event_id, '_tmgmt_event_attachments', $normalized_current);

                    // Log
                    $log_manager->log($event_id, 'attachment_added', 'Datei ' . basename($file['name']) . ' wurde per E-Mail versendet und hochgeladen.');

                    // Add to email attachments
                    $path = get_attached_file($attachment_id);
                    if ($path) {
                        $attachments[] = $path;
                    }
                } else {
                    $log_manager->log($event_id, 'upload_error', 'Fehler beim Upload: ' . $attachment_id->get_error_message());
                }
            }

            // Send
            $sent = wp_mail($recipient, $subject, nl2br($body), $headers, $attachments);

            if ($sent) {
                $log_message .= " - E-Mail gesendet an: $recipient";
                
                // Save Communication
                $comm_manager = new TMGMT_Communication_Manager();
                $comm_id = $comm_manager->add_entry($event_id, 'email', $recipient, $subject, $body);
                
                $log_manager->log($event_id, 'email_sent', "E-Mail '$subject' an $recipient gesendet.", null, $comm_id);
            } else {
                $log_manager->log($event_id, 'email_error', "Fehler beim Senden der E-Mail an $recipient.");
                wp_send_json_error(array('message' => 'E-Mail konnte nicht gesendet werden.'));
            }

        } else {
            // Note Type
            if (!empty($note)) {
                $log_message .= " - Notiz: " . $note;
                
                // Save Communication
                $comm_manager = new TMGMT_Communication_Manager();
                $comm_id = $comm_manager->add_entry($event_id, 'note', 'Intern', '', $note);
                
                // Log with link
                $log_manager->log($event_id, 'note_added', "Notiz hinzugefügt.", null, $comm_id);
            }
        }

        // Log the main action
        // Only log main action if it's not already logged above (email/note)
        // But 'action_executed' is a summary. Let's keep it but maybe without comm_id or with?
        // Let's keep it simple. The specific logs have the link.
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

    public function handle_delete_file() {
        check_ajax_referer('wp_rest', 'nonce');

        if (!current_user_can('upload_files')) {
             wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }

        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
        if (!$attachment_id) {
            wp_send_json_error(array('message' => 'Keine Datei ID.'));
        }

        // Check if attachment exists and user can delete it
        if (!current_user_can('delete_post', $attachment_id)) {
            wp_send_json_error(array('message' => 'Keine Berechtigung zum Löschen dieser Datei.'));
        }

        $deleted = wp_delete_attachment($attachment_id, true);
        if ($deleted) {
            wp_send_json_success(array('message' => 'Datei gelöscht.'));
        } else {
            wp_send_json_error(array('message' => 'Fehler beim Löschen.'));
        }
    }

    public function handle_get_event_details() {
        // Nonce check is good practice, but if we want public access we might skip it or use a public nonce
        // check_ajax_referer('wp_rest', 'nonce'); 

        $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
        if (!$event_id) {
            wp_send_json_error(array('message' => 'Keine Event ID.'));
        }

        $post = get_post($event_id);
        if (!$post || $post->post_type !== 'event') {
            wp_send_json_error(array('message' => 'Event nicht gefunden.'));
        }

        $meta = get_post_meta($event_id);
        $fields = array();

        // Helper to get meta safely
        $get_meta = function($key) use ($meta) {
            return isset($meta[$key]) ? $meta[$key][0] : '';
        };

        // Basic Info
        $fields['title'] = $post->post_title;
        $fields['date'] = $get_meta('_tmgmt_event_date');
        $fields['start_time'] = $get_meta('_tmgmt_event_start_time');
        $fields['arrival_time'] = $get_meta('_tmgmt_event_arrival_time');
        $fields['departure_time'] = $get_meta('_tmgmt_event_departure_time');
        
        // Venue
        $fields['venue_name'] = $get_meta('_tmgmt_venue_name');
        $fields['venue_street'] = $get_meta('_tmgmt_venue_street');
        $fields['venue_number'] = $get_meta('_tmgmt_venue_number');
        $fields['venue_zip'] = $get_meta('_tmgmt_venue_zip');
        $fields['venue_city'] = $get_meta('_tmgmt_venue_city');
        $fields['venue_country'] = $get_meta('_tmgmt_venue_country');
        $fields['arrival_notes'] = $get_meta('_tmgmt_arrival_notes');

        // Contacts
        $contacts = array();
        $contacts[] = array('role' => 'Vertrag', 'name' => $get_meta('_tmgmt_contact_firstname') . ' ' . $get_meta('_tmgmt_contact_lastname'), 'phone' => $get_meta('_tmgmt_contact_phone_contract'), 'email' => $get_meta('_tmgmt_contact_email_contract'));
        $contacts[] = array('role' => 'Technik', 'name' => $get_meta('_tmgmt_contact_name_tech'), 'phone' => $get_meta('_tmgmt_contact_phone_tech'), 'email' => $get_meta('_tmgmt_contact_email_tech'));
        $contacts[] = array('role' => 'Programm', 'name' => $get_meta('_tmgmt_contact_name_program'), 'phone' => $get_meta('_tmgmt_contact_phone_program'), 'email' => $get_meta('_tmgmt_contact_email_program'));
        
        $fields['contacts'] = array_filter($contacts, function($c) {
            return !empty(trim($c['name'])) || !empty($c['phone']) || !empty($c['email']);
        });

        // Permissions
        $can_edit = current_user_can('edit_post', $event_id);
        $edit_link = $can_edit ? get_edit_post_link($event_id, 'raw') : '';

        wp_send_json_success(array(
            'data' => $fields,
            'can_edit' => $can_edit,
            'edit_link' => $edit_link
        ));
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
