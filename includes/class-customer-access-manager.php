<?php

class TMGMT_Customer_Access_Manager {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'tmgmt_access_tokens';

        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('wp_ajax_tmgmt_create_access_token', array($this, 'handle_create_token'));
        add_action('wp_ajax_tmgmt_revoke_access_token', array($this, 'handle_revoke_token'));
        add_action('init', array($this, 'handle_frontend_access'));
        
        // Token Request
        add_shortcode('tmgmt_token_request', array($this, 'render_token_request_form'));
        add_action('wp_ajax_tmgmt_request_token', array($this, 'handle_token_request'));
        add_action('wp_ajax_nopriv_tmgmt_request_token', array($this, 'handle_token_request'));
    }

    public function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $this->table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_id bigint(20) NOT NULL,
            token varchar(64) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            expires_at datetime DEFAULT NULL,
            status varchar(20) DEFAULT 'active' NOT NULL,
            created_by bigint(20) NOT NULL,
            PRIMARY KEY  (id),
            KEY event_id (event_id),
            KEY token (token)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function add_meta_boxes() {
        add_meta_box(
            'tmgmt_customer_access',
            'Veranstalter Zugang',
            array($this, 'render_meta_box'),
            'event',
            'side',
            'low'
        );
    }

    public function render_meta_box($post) {
        $tokens = $this->get_tokens($post->ID);
        ?>
        <div id="tmgmt-access-tokens-list">
            <?php if (empty($tokens)): ?>
                <p class="description">Keine aktiven Zugangs-Tokens vorhanden.</p>
            <?php else: ?>
                <ul style="margin: 0 0 10px 0;">
                    <?php foreach ($tokens as $token): ?>
                        <li style="margin-bottom: 8px; border-bottom: 1px solid #eee; padding-bottom: 8px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <strong style="color: <?php echo $token->status === 'active' ? '#46b450' : '#dc3232'; ?>">
                                        <?php echo $token->status === 'active' ? 'Aktiv' : 'Widerrufen'; ?>
                                    </strong>
                                    <br>
                                    <small><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($token->created_at)); ?></small>
                                </div>
                                <?php if ($token->status === 'active'): ?>
                                    <button type="button" class="button button-small tmgmt-revoke-token" data-id="<?php echo $token->id; ?>" style="color: #a00;">Widerrufen</button>
                                <?php endif; ?>
                            </div>
                            <?php if ($token->status === 'active'): ?>
                                <div style="margin-top: 5px;">
                                    <input type="text" readonly value="<?php echo esc_url(home_url('/?tmgmt_token=' . $token->token)); ?>" class="widefat" onclick="this.select()">
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        
        <button type="button" class="button button-primary" id="tmgmt-create-token" data-event="<?php echo $post->ID; ?>">Neuen Zugang erstellen</button>

        <script>
        jQuery(document).ready(function($) {
            $('#tmgmt-create-token').on('click', function() {
                var btn = $(this);
                btn.prop('disabled', true);
                
                $.post(ajaxurl, {
                    action: 'tmgmt_create_access_token',
                    event_id: btn.data('event'),
                    nonce: '<?php echo wp_create_nonce('tmgmt_access_token_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Fehler: ' + response.data.message);
                        btn.prop('disabled', false);
                    }
                });
            });

            $('.tmgmt-revoke-token').on('click', function() {
                if (!confirm('Wirklich widerrufen? Der Link wird ungültig.')) return;
                
                var btn = $(this);
                $.post(ajaxurl, {
                    action: 'tmgmt_revoke_access_token',
                    token_id: btn.data('id'),
                    nonce: '<?php echo wp_create_nonce('tmgmt_access_token_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Fehler: ' + response.data.message);
                    }
                });
            });
        });
        </script>
        <?php
    }

    public function handle_create_token() {
        check_ajax_referer('tmgmt_access_token_nonce', 'nonce');
        
        if (!current_user_can('edit_events')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }

        $event_id = intval($_POST['event_id']);
        $token = bin2hex(random_bytes(32));
        $user_id = get_current_user_id();

        // Check max tokens setting (optional, as per requirements)
        // $max_tokens = get_option('tmgmt_max_tokens', 5);
        // ... count active tokens ...

        global $wpdb;
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'event_id' => $event_id,
                'token' => $token,
                'created_by' => $user_id,
                'status' => 'active'
            ),
            array('%d', '%s', '%d', '%s')
        );

        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(array('message' => 'Datenbankfehler.'));
        }
    }

    public function handle_revoke_token() {
        check_ajax_referer('tmgmt_access_token_nonce', 'nonce');
        
        if (!current_user_can('edit_events')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung.'));
        }

        $token_id = intval($_POST['token_id']);
        
        global $wpdb;
        $result = $wpdb->update(
            $this->table_name,
            array('status' => 'revoked'),
            array('id' => $token_id),
            array('%s'),
            array('%d')
        );

        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error(array('message' => 'Datenbankfehler.'));
        }
    }

    public function get_tokens($event_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE event_id = %d ORDER BY created_at DESC",
            $event_id
        ));
    }

    public function get_valid_token($event_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE event_id = %d AND status = 'active' ORDER BY created_at DESC LIMIT 1",
            $event_id
        ));
    }

    public function handle_frontend_access() {
        if (isset($_GET['tmgmt_token'])) {
            $token = sanitize_text_field($_GET['tmgmt_token']);
            
            global $wpdb;
            $record = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $this->table_name WHERE token = %s AND status = 'active'",
                $token
            ));

            if ($record) {
                // Handle Form Submission
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tmgmt_save_dashboard'])) {
                    $this->handle_dashboard_save($record->event_id);
                }

                // Render Dashboard
                $this->render_dashboard($record->event_id);
                exit;
            } else {
                wp_die('Ungültiger oder abgelaufener Zugangs-Link.', 'Zugriff verweigert', array('response' => 403));
            }
        }
    }

    private function handle_dashboard_save($event_id) {
        $config = get_option('tmgmt_customer_dashboard_config', array());
        
        // Only save fields that are configured as writable
        foreach ($config as $key => $settings) {
            if (isset($settings['write']) && $settings['write']) {
                if (isset($_POST[$key])) {
                    // Sanitize based on field type (simplified for now)
                    $value = sanitize_text_field($_POST[$key]);
                    update_post_meta($event_id, $key, $value);
                }
            }
        }
        
        // Add success message or redirect
        echo '<div style="background: #d4edda; color: #155724; padding: 15px; margin-bottom: 20px; border: 1px solid #c3e6cb; border-radius: 4px;">Änderungen erfolgreich gespeichert.</div>';
    }

    private function render_dashboard($event_id) {
        $event = get_post($event_id);
        if (!$event) wp_die('Event nicht gefunden.');

        // Get Configuration
        $config = get_option('tmgmt_customer_dashboard_config', array());
        
        // Define Labels (should match settings menu)
        $field_labels = array(
            'core_content' => 'Beschreibung',
            '_tmgmt_event_date' => 'Datum',
            '_tmgmt_event_start_time' => 'Startzeit',
            '_tmgmt_event_arrival_time' => 'Ankunftszeit',
            '_tmgmt_event_departure_time' => 'Abfahrtszeit',
            '_tmgmt_arrival_notes' => 'Anreise Notizen',
            '_tmgmt_venue_name' => 'Location Name',
            '_tmgmt_venue_street' => 'Location Straße',
            '_tmgmt_venue_city' => 'Location Stadt',
            '_tmgmt_contact_firstname' => 'Kontakt Vorname',
            '_tmgmt_contact_lastname' => 'Kontakt Nachname',
            '_tmgmt_contact_email' => 'Kontakt E-Mail',
            '_tmgmt_contact_phone' => 'Kontakt Telefon',
            
            // Contact Address (Contract)
            '_tmgmt_contact_street' => 'Kontakt Straße',
            '_tmgmt_contact_number' => 'Kontakt Hausnummer',
            '_tmgmt_contact_zip' => 'Kontakt PLZ',
            '_tmgmt_contact_city' => 'Kontakt Stadt',
            '_tmgmt_contact_country' => 'Kontakt Land',

            // Contact Program
            '_tmgmt_contact_name_program' => 'Kontakt Name (Programm)',
            '_tmgmt_contact_email_program' => 'Kontakt E-Mail (Programm)',
            '_tmgmt_contact_phone_program' => 'Kontakt Telefon (Programm)',

            // Contact Tech
            '_tmgmt_contact_name_tech' => 'Kontakt Name (Technik)',
            '_tmgmt_contact_email_tech' => 'Kontakt E-Mail (Technik)',
            '_tmgmt_contact_phone_tech' => 'Kontakt Telefon (Technik)',

            '_tmgmt_fee' => 'Gage',
            '_tmgmt_deposit' => 'Anzahlung',
        );

        ?>
        <!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Event Dashboard: <?php echo esc_html($event->post_title); ?></title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; background: #f0f0f1; color: #3c434a; margin: 0; padding: 20px; }
                .container { max-width: 800px; margin: 0 auto; background: #fff; padding: 40px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-radius: 4px; }
                h1 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 20px; }
                .field-group { margin-bottom: 20px; }
                .field-label { font-weight: bold; display: block; margin-bottom: 5px; color: #1d2327; }
                .field-value { padding: 10px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 3px; }
                .field-input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 3px; font-size: 16px; box-sizing: border-box; }
                .footer { margin-top: 40px; font-size: 12px; color: #666; text-align: center; border-top: 1px solid #eee; padding-top: 20px; }
                .button { display: inline-block; text-decoration: none; font-size: 13px; line-height: 2.15384615; min-height: 30px; margin: 0; padding: 0 10px; cursor: pointer; border-width: 1px; border-style: solid; -webkit-appearance: none; border-radius: 3px; white-space: nowrap; box-sizing: border-box; background: #2271b1; border-color: #2271b1; color: #fff; }
                .button:hover { background: #135e96; border-color: #135e96; color: #fff; }
            </style>
        </head>
        <body>
            <div class="container">
                <h1><?php echo esc_html($event->post_title); ?></h1>
                
                <form method="post">
                    <input type="hidden" name="tmgmt_save_dashboard" value="1">
                    
                    <?php 
                    // Core Content
                    if (isset($config['core_content']['read']) && $config['core_content']['read']) {
                        echo '<div class="field-group">';
                        echo '<span class="field-label">Beschreibung</span>';
                        echo '<div class="field-value">' . wp_kses_post(wpautop($event->post_content)) . '</div>';
                        echo '</div>';
                    }

                    // Meta Fields
                    foreach ($field_labels as $key => $label) {
                        if ($key === 'core_content') continue;

                        $is_readable = isset($config[$key]['read']) && $config[$key]['read'];
                        $is_writable = isset($config[$key]['write']) && $config[$key]['write'];
                        
                        if (!$is_readable && !$is_writable) continue;

                        $val = get_post_meta($event_id, $key, true);
                        
                        echo '<div class="field-group">';
                        echo '<label class="field-label" for="' . esc_attr($key) . '">' . esc_html($label) . '</label>';
                        
                        if ($is_writable) {
                            echo '<input type="text" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" value="' . esc_attr($val) . '" class="field-input">';
                        } else {
                            echo '<div class="field-value">' . esc_html($val) . '</div>';
                        }
                        echo '</div>';
                    }
                    
                    // Check if any field is writable to show save button
                    $has_writable = false;
                    foreach ($config as $s) {
                        if (isset($s['write']) && $s['write']) {
                            $has_writable = true;
                            break;
                        }
                    }
                    
                    if ($has_writable) {
                        echo '<button type="submit" class="button">Änderungen speichern</button>';
                    }
                    ?>
                </form>

                <div class="footer">
                    Powered by Töns Management
                </div>
            </div>
        </body>
        </html>
        <?php
    }

    public function render_token_request_form($atts) {
        ob_start();
        $redirect_page_id = get_option('tmgmt_token_request_redirect_page');
        $redirect_url = $redirect_page_id ? get_permalink($redirect_page_id) : '';
        ?>
        <div id="tmgmt-token-request-form-wrapper">
            <form id="tmgmt-token-request-form" class="tmgmt-form">
                <div class="tmgmt-form-row">
                    <label for="tmgmt_req_event_id">Event ID (Vertragsnummer)</label>
                    <input type="text" id="tmgmt_req_event_id" name="event_id" required placeholder="z.B. 25AB12CD">
                </div>
                <div class="tmgmt-form-row">
                    <label for="tmgmt_req_email">E-Mail-Adresse</label>
                    <input type="email" id="tmgmt_req_email" name="email" required placeholder="ihre@email.de">
                </div>
                <div class="tmgmt-form-row">
                    <label for="tmgmt_req_date">Veranstaltungsdatum</label>
                    <input type="date" id="tmgmt_req_date" name="date" required>
                </div>
                <div class="tmgmt-form-row">
                    <button type="submit" class="tmgmt-button">Zugang anfordern</button>
                </div>
                <div id="tmgmt-req-message" style="display:none; margin-top: 15px; padding: 10px; border-radius: 4px;"></div>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var redirectUrl = '<?php echo esc_js($redirect_url); ?>';

            $('#tmgmt-token-request-form').on('submit', function(e) {
                e.preventDefault();
                var form = $(this);
                var btn = form.find('button[type="submit"]');
                var msg = $('#tmgmt-req-message');
                
                btn.prop('disabled', true).text('Bitte warten...');
                msg.hide().removeClass('success error');

                $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                    action: 'tmgmt_request_token',
                    event_id: $('#tmgmt_req_event_id').val(),
                    email: $('#tmgmt_req_email').val(),
                    date: $('#tmgmt_req_date').val(),
                    nonce: '<?php echo wp_create_nonce('tmgmt_token_request_nonce'); ?>'
                }, function(response) {
                    if (redirectUrl) {
                        window.location.href = redirectUrl;
                    } else {
                        // Always show success message to prevent enumeration
                        msg.addClass('success').css('background', '#d4edda').css('color', '#155724').text('Wenn die Daten korrekt sind, erhalten Sie in Kürze eine E-Mail mit dem Zugangslink.').show();
                        form[0].reset();
                        btn.prop('disabled', false).text('Zugang anfordern');
                    }
                });
            });
        });
        </script>
        <style>
            .tmgmt-form-row { margin-bottom: 15px; }
            .tmgmt-form-row label { display: block; margin-bottom: 5px; font-weight: bold; }
            .tmgmt-form-row input { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
            .tmgmt-button { background: #007cba; color: #fff; border: none; padding: 10px 20px; cursor: pointer; border-radius: 4px; }
            .tmgmt-button:hover { background: #005a87; }
            .tmgmt-button:disabled { background: #ccc; cursor: not-allowed; }
        </style>
        <?php
        return ob_get_clean();
    }

    public function handle_token_request() {
        check_ajax_referer('tmgmt_token_request_nonce', 'nonce');
        
        $event_id_str = sanitize_text_field($_POST['event_id']);
        $email = sanitize_email($_POST['email']);
        $date = sanitize_text_field($_POST['date']);

        // 1. Find Event by ID
        $args = array(
            'post_type' => 'event',
            'meta_query' => array(
                array(
                    'key' => '_tmgmt_event_id',
                    'value' => $event_id_str,
                    'compare' => '='
                )
            ),
            'posts_per_page' => 1
        );
        $events = get_posts($args);

        $found = false;
        $event_post_id = 0;

        if (!empty($events)) {
            $event = $events[0];
            $event_post_id = $event->ID;
            
            // 2. Validate Email (check all contact emails)
            $contact_email = get_post_meta($event_post_id, '_tmgmt_contact_email', true);
            $program_email = get_post_meta($event_post_id, '_tmgmt_contact_email_program', true);
            $tech_email = get_post_meta($event_post_id, '_tmgmt_contact_email_tech', true);
            
            $emails = array_map('strtolower', array_filter([$contact_email, $program_email, $tech_email]));
            
            if (in_array(strtolower($email), $emails)) {
                // 3. Validate Date
                $event_date = get_post_meta($event_post_id, '_tmgmt_event_date', true);
                if ($event_date === $date) {
                    $found = true;
                }
            }
        }

        if ($found) {
            // Generate or retrieve token
            $token_row = $this->get_valid_token($event_post_id);
            if (!$token_row) {
                // Create new token
                $token = bin2hex(random_bytes(32));
                global $wpdb;
                $wpdb->insert(
                    $this->table_name,
                    array(
                        'event_id' => $event_post_id,
                        'token' => $token,
                        'created_by' => 0, // System
                        'status' => 'active'
                    ),
                    array('%d', '%s', '%d', '%s')
                );
            }
            
            // Send Success Email
            $template_id = get_option('tmgmt_token_request_email_found');
            if ($template_id) {
                $this->send_email_template($template_id, $event_post_id, $email);
            }
        } else {
            // Send Failure Email (if configured)
            $template_id = get_option('tmgmt_token_request_email_not_found');
            if ($template_id) {
                // We don't have an event ID here, so placeholders won't work fully.
                // But we should still send it.
                $this->send_email_template($template_id, 0, $email);
            }
        }

        wp_send_json_success();
    }

    private function send_email_template($template_id, $event_id, $recipient) {
        $template = get_post($template_id);
        if (!$template) return;

        $subject = get_post_meta($template->ID, '_tmgmt_email_subject', true);
        $content = $template->post_content;

        if ($event_id) {
            $parser = new TMGMT_Placeholder_Parser($event_id);
            $subject = $parser->parse($subject);
            $content = $parser->parse($content);
        }

        // Send Email
        $headers = array('Content-Type: text/html; charset=UTF-8');
        $sent = wp_mail($recipient, $subject, $content, $headers);

        // Log
        if ($sent && $event_id) {
            $comm_manager = new TMGMT_Communication_Manager();
            $comm_manager->add_entry(
                $event_id,
                'email',
                $recipient,
                $subject,
                $content,
                0 // System
            );
        }
    }
}
