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

        // Signed Contract Upload
        add_action('wp_ajax_tmgmt_upload_signed_contract', array($this, 'handle_signed_contract_upload'));
        add_action('wp_ajax_nopriv_tmgmt_upload_signed_contract', array($this, 'handle_signed_contract_upload'));
    }

    /**
     * Returns the default fields that should be readable without explicit configuration.
     *
     * @return array<string, array{read: bool, write: bool}>
     */
    private static function get_default_readable_fields(): array {
        return array(
            'core_content'                => array('read' => true, 'write' => false),
            '_tmgmt_event_date'           => array('read' => true, 'write' => false),
            '_tmgmt_event_start_time'     => array('read' => true, 'write' => false),
            '_tmgmt_event_arrival_time'   => array('read' => true, 'write' => false),
            '_tmgmt_event_departure_time' => array('read' => true, 'write' => false),
            '_tmgmt_event_location_id'    => array('read' => true, 'write' => false),
        );
    }

    /**
     * Computes the effective field configuration by merging stored config with defaults.
     *
     * @return array<string, array{read: bool, write: bool}>
     */
    private function get_effective_config(): array {
        $config = get_option('tmgmt_customer_dashboard_config', array());
        if (empty($config)) {
            return self::get_default_readable_fields();
        }
        $defaults = self::get_default_readable_fields();
        foreach ($defaults as $key => $settings) {
            if (!isset($config[$key])) {
                $config[$key] = $settings;
            }
        }
        return $config;
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
        $config = $this->get_effective_config();

        // Collect writable fields
        $writable_fields = array();
        foreach ($config as $key => $settings) {
            if (!empty($settings['write'])) {
                $writable_fields[$key] = $settings;
            }
        }

        // No-op if nothing is writable
        if (empty($writable_fields)) {
            return;
        }

        // Save only writable fields
        foreach ($writable_fields as $key => $settings) {
            if (!isset($_POST[$key])) {
                continue;
            }

            if ($key === 'core_content') {
                // Update post content via wp_update_post
                wp_update_post(array(
                    'ID'           => $event_id,
                    'post_content' => wp_kses_post($_POST[$key]),
                ));
            } else {
                $value = sanitize_text_field($_POST[$key]);
                update_post_meta($event_id, $key, $value);
            }
        }

        echo '<div class="cd-success-message">Änderungen erfolgreich gespeichert.</div>';
    }

    private function render_dashboard($event_id) {
        $event = get_post($event_id);
        if (!$event) wp_die('Event nicht gefunden.');

        $config     = $this->get_effective_config();
        $status_key = get_post_meta($event_id, '_tmgmt_status', true);
        $css_url    = TMGMT_PLUGIN_URL . 'assets/css/customer-dashboard.css';

        // Determine if any field is writable (controls form + save button)
        $has_writable = false;
        foreach ($config as $settings) {
            if (!empty($settings['write'])) {
                $has_writable = true;
                break;
            }
        }

        ?>
        <!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Event Dashboard: <?php echo esc_html($event->post_title); ?></title>
            <link rel="stylesheet" href="<?php echo esc_url($css_url); ?>">
        </head>
        <body class="cd-body">
            <div class="cd-container">

                <header>
                    <?php $this->render_section_header($event->post_title, $status_key); ?>
                </header>

                <main>
                    <?php if ($has_writable): ?>
                    <form method="post">
                        <input type="hidden" name="tmgmt_save_dashboard" value="1">
                    <?php endif; ?>

                    <?php
                    $this->render_event_details_section($event_id, $config);
                    $this->render_location_section($event_id);
                    $this->render_contact_section($event_id);
                    $this->render_finance_section($event_id, $config);
                    $this->render_confirmations_section($event_id);
                    $this->render_attachments_section($event_id);

                    if ($status_key === TMGMT_Event_Status::CONTRACT_SENT) {
                        $this->render_contract_upload_section($event_id);
                    }

                    if ($has_writable) {
                        echo '<button type="submit" class="cd-button">Änderungen speichern</button>';
                    }
                    ?>

                    <?php if ($has_writable): ?>
                    </form>
                    <?php endif; ?>
                </main>

                <footer class="cd-footer">
                    Powered by Töns Management
                </footer>

            </div>
        </body>
        </html>
        <?php
    }

    /**
     * Renders the header section with event title and status badge.
     * @param string $event_title The event title.
     * @param string $status_key  The event status slug.
     */
    private function render_section_header(string $event_title, string $status_key): void {
        ?>
        <div class="cd-header">
            <h1><?php echo esc_html($event_title); ?></h1>
            <?php if (!empty($status_key)) :
                $status_label = TMGMT_Event_Status::get_label($status_key);
                ?>
                <span class="cd-status-badge"><?php echo esc_html($status_label); ?></span>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Renders the event details section (description, date, times).
     * @param int   $event_id The event post ID.
     * @param array $config   The effective field configuration.
     */
    private function render_event_details_section(int $event_id, array $config): void {
        $fields = array(
            'core_content'                => 'Beschreibung',
            '_tmgmt_event_date'           => 'Datum',
            '_tmgmt_event_start_time'     => 'Startzeit',
            '_tmgmt_event_arrival_time'   => 'Ankunftszeit',
            '_tmgmt_event_departure_time' => 'Abfahrtszeit',
        );

        // Check if any field is visible
        $has_visible = false;
        foreach ($fields as $key => $label) {
            if (isset($config[$key]) && !empty($config[$key]['read'])) {
                $has_visible = true;
                break;
            }
        }

        if (!$has_visible) {
            return;
        }

        ?>
        <section class="cd-section">
            <h2 class="cd-section-title">Veranstaltungsdetails</h2>
            <?php foreach ($fields as $key => $label) :
                if (!isset($config[$key]) || empty($config[$key]['read'])) {
                    continue;
                }

                $is_writable = !empty($config[$key]['write']);

                if ($key === 'core_content') {
                    $value = get_post($event_id)->post_content;
                    ?>
                    <div class="cd-field-group">
                        <?php if ($is_writable) : ?>
                            <label class="cd-field-label" for="core_content"><?php echo esc_html($label); ?></label>
                            <textarea class="cd-field-input" id="core_content" name="core_content" rows="5"><?php echo esc_textarea($value); ?></textarea>
                        <?php else : ?>
                            <span class="cd-field-label"><?php echo esc_html($label); ?></span>
                            <div class="cd-field-value"><?php echo wp_kses_post(wpautop($value)); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php
                } elseif ($key === '_tmgmt_event_date') {
                    $value = get_post_meta($event_id, $key, true);
                    ?>
                    <div class="cd-field-group">
                        <?php if ($is_writable) : ?>
                            <label class="cd-field-label" for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label>
                            <input class="cd-field-input" type="date" id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>">
                        <?php else : ?>
                            <span class="cd-field-label"><?php echo esc_html($label); ?></span>
                            <span class="cd-field-value"><?php echo esc_html($value); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php
                } else {
                    // Time fields
                    $value = get_post_meta($event_id, $key, true);
                    ?>
                    <div class="cd-field-group">
                        <?php if ($is_writable) : ?>
                            <label class="cd-field-label" for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label>
                            <input class="cd-field-input" type="time" id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>">
                        <?php else : ?>
                            <span class="cd-field-label"><?php echo esc_html($label); ?></span>
                            <span class="cd-field-value"><?php echo esc_html($value); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php
                }
            endforeach; ?>
        </section>
        <?php
    }

    /**
     * Renders the location section with address and notes.
     * @param int $event_id The event post ID.
     */
    private function render_location_section(int $event_id): void {
        $location_id = get_post_meta($event_id, '_tmgmt_event_location_id', true);
        if (empty($location_id)) {
            return;
        }

        $location = get_post($location_id);
        if (!$location) {
            return;
        }

        $street  = get_post_meta($location_id, '_tmgmt_location_street', true);
        $number  = get_post_meta($location_id, '_tmgmt_location_number', true);
        $zip     = get_post_meta($location_id, '_tmgmt_location_zip', true);
        $city    = get_post_meta($location_id, '_tmgmt_location_city', true);
        $country = get_post_meta($location_id, '_tmgmt_location_country', true);
        $notes   = get_post_meta($location_id, '_tmgmt_location_notes', true);

        ?>
        <section class="cd-section">
            <h2 class="cd-section-title">Veranstaltungsort</h2>

            <div class="cd-field-group">
                <span class="cd-field-label">Name</span>
                <span class="cd-field-value"><?php echo esc_html($location->post_title); ?></span>
            </div>

            <div class="cd-field-group">
                <span class="cd-field-label">Adresse</span>
                <span class="cd-field-value">
                    <?php
                    $address_lines = array();

                    $street_line = trim($street . ' ' . $number);
                    if (!empty($street_line)) {
                        $address_lines[] = esc_html($street_line);
                    }

                    $city_line = trim($zip . ' ' . $city);
                    if (!empty($city_line)) {
                        $address_lines[] = esc_html($city_line);
                    }

                    if (!empty($country)) {
                        $address_lines[] = esc_html($country);
                    }

                    echo implode('<br>', $address_lines);
                    ?>
                </span>
            </div>

            <?php if (!empty($notes)) : ?>
            <div class="cd-field-group">
                <span class="cd-field-label">Hinweise</span>
                <div class="cd-field-value"><?php echo wp_kses_post(wpautop($notes)); ?></div>
            </div>
            <?php endif; ?>
        </section>
        <?php
    }

    /**
     * Renders the contact section grouped by role.
     * @param int $event_id The event post ID.
     */
    private function render_contact_section(int $event_id): void {
        $contacts = TMGMT_Placeholder_Parser::get_contact_data_for_event($event_id);

        $roles = array(
            'vertrag'  => 'Vertrag',
            'programm' => 'Programm',
            'technik'  => 'Technik',
        );

        // Check if any role has data (firstname or lastname non-empty)
        $has_any = false;
        foreach ($roles as $key => $label) {
            $role_data = $contacts[$key] ?? array();
            if (!empty($role_data['firstname']) || !empty($role_data['lastname'])) {
                $has_any = true;
                break;
            }
        }

        if (!$has_any) {
            return;
        }

        ?>
        <section class="cd-section">
            <h2 class="cd-section-title">Kontaktdaten</h2>
            <?php foreach ($roles as $key => $label) :
                $role_data = $contacts[$key] ?? array();
                $firstname = $role_data['firstname'] ?? '';
                $lastname  = $role_data['lastname'] ?? '';

                if (empty($firstname) && empty($lastname)) {
                    continue;
                }

                $name  = trim($firstname . ' ' . $lastname);
                $email = $role_data['email'] ?? '';
                $phone = $role_data['phone'] ?? '';
                ?>
                <div class="cd-contact-role">
                    <h3 class="cd-contact-role-title"><?php echo esc_html($label); ?></h3>

                    <div class="cd-field-group">
                        <span class="cd-field-label">Name</span>
                        <span class="cd-field-value"><?php echo esc_html($name); ?></span>
                    </div>

                    <?php if (!empty($email)) : ?>
                    <div class="cd-field-group">
                        <span class="cd-field-label">E-Mail</span>
                        <span class="cd-field-value"><?php echo esc_html($email); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($phone)) : ?>
                    <div class="cd-field-group">
                        <span class="cd-field-label">Telefon</span>
                        <span class="cd-field-value"><?php echo esc_html($phone); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </section>
        <?php
    }

    /**
     * Renders the finance section (fee, deposit).
     * @param int   $event_id The event post ID.
     * @param array $config   The effective field configuration.
     */
    private function render_finance_section(int $event_id, array $config): void {
        $fields = array(
            '_tmgmt_fee'     => 'Gage',
            '_tmgmt_deposit' => 'Anzahlung',
        );

        // Check if any finance field is visible
        $has_visible = false;
        foreach ($fields as $key => $label) {
            if (isset($config[$key]) && !empty($config[$key]['read'])) {
                $has_visible = true;
                break;
            }
        }

        if (!$has_visible) {
            return;
        }

        ?>
        <section class="cd-section">
            <h2 class="cd-section-title">Finanzen</h2>
            <?php foreach ($fields as $key => $label) :
                if (!isset($config[$key]) || empty($config[$key]['read'])) {
                    continue;
                }

                $is_writable = !empty($config[$key]['write']);
                $value = get_post_meta($event_id, $key, true);
                ?>
                <div class="cd-field-group">
                    <?php if ($is_writable) : ?>
                        <label class="cd-field-label" for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label>
                        <input class="cd-field-input" type="text" id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>">
                    <?php else : ?>
                        <span class="cd-field-label"><?php echo esc_html($label); ?></span>
                        <span class="cd-field-value"><?php echo esc_html($value); ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </section>
        <?php
    }

    /**
     * Renders the confirmations section.
     * @param int $event_id The event post ID.
     */
    private function render_confirmations_section(int $event_id): void {
        global $wpdb;
        $table = $wpdb->prefix . 'tmgmt_confirmations';

        $confirmations = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE event_id = %d ORDER BY requested_at DESC",
                $event_id
            )
        );

        if (empty($confirmations)) {
            return;
        }

        ?>
        <section class="cd-section">
            <h2 class="cd-section-title">Bestätigungen</h2>
            <ul class="cd-confirmation-list">
                <?php foreach ($confirmations as $row) :
                    $action_title = get_the_title($row->action_id);
                    $is_confirmed = $row->status === 'confirmed';
                    $status_label = $is_confirmed ? 'Bestätigt' : 'Ausstehend';
                    $date         = $is_confirmed ? $row->confirmed_at : $row->requested_at;
                    ?>
                    <li>
                        <span class="cd-field-label"><?php echo esc_html($action_title); ?></span>
                        <span class="cd-field-value"><?php echo esc_html($status_label); ?> — <?php echo esc_html($date); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php
    }

    /**
     * Renders the attachments/files section.
     * @param int $event_id The event post ID.
     */
    private function render_attachments_section(int $event_id): void {
        $attachments = get_post_meta($event_id, '_tmgmt_event_attachments', true);

        if (empty($attachments) || !is_array($attachments)) {
            return;
        }

        // Filter to only valid attachments
        $valid = array();
        foreach ($attachments as $entry) {
            if (empty($entry['id'])) {
                continue;
            }
            $post = get_post($entry['id']);
            if (!$post) {
                continue;
            }
            $valid[] = $entry;
        }

        if (empty($valid)) {
            return;
        }

        ?>
        <section class="cd-section">
            <h2 class="cd-section-title">Dateien</h2>
            <ul class="cd-attachment-list">
                <?php foreach ($valid as $entry) :
                    $filename     = basename(get_attached_file($entry['id']));
                    $category     = $entry['category'] ?? '';
                    $download_url = wp_get_attachment_url($entry['id']);
                    ?>
                    <li>
                        <span class="cd-field-label"><?php echo esc_html($filename); ?></span>
                        <span class="cd-field-value"><?php echo esc_html($category); ?></span>
                        <a href="<?php echo esc_url($download_url); ?>" class="cd-button" download>Download</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php
    }

    private function render_contract_upload_section($event_id) {
        $token = isset($_GET['tmgmt_token']) ? sanitize_text_field($_GET['tmgmt_token']) : '';
        ?>
        <div id="tmgmt-contract-upload-section" class="cd-section cd-upload-area">
            <h2 class="cd-section-title">Unterschriebenen Vertrag hochladen</h2>
            <p>Bitte laden Sie den unterschriebenen Vertrag als PDF, JPG oder PNG hoch.</p>
            <div id="tmgmt-upload-message" class="cd-success-message" style="display:none;"></div>
            <form id="tmgmt-contract-upload-form" enctype="multipart/form-data">
                <input type="hidden" name="action" value="tmgmt_upload_signed_contract">
                <input type="hidden" name="tmgmt_token" value="<?php echo esc_attr($token); ?>">
                <input type="hidden" name="event_id" value="<?php echo esc_attr($event_id); ?>">
                <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('tmgmt_upload_signed_contract_nonce'); ?>">
                <div class="cd-field-group">
                    <label class="cd-field-label" for="tmgmt-signed-contract-file">Datei auswählen</label>
                    <input type="file" id="tmgmt-signed-contract-file" name="signed_contract" accept=".pdf,.jpg,.jpeg,.png" required>
                </div>
                <button type="submit" class="cd-button">Vertrag hochladen</button>
            </form>
            <script>
            (function() {
                var form = document.getElementById('tmgmt-contract-upload-form');
                if (!form) return;
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    var msg = document.getElementById('tmgmt-upload-message');
                    var btn = form.querySelector('button[type="submit"]');
                    btn.disabled = true;
                    btn.textContent = 'Wird hochgeladen...';
                    msg.style.display = 'none';

                    var formData = new FormData(form);
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', '<?php echo esc_js(admin_url('admin-ajax.php')); ?>');
                    xhr.onload = function() {
                        btn.disabled = false;
                        btn.textContent = 'Vertrag hochladen';
                        try {
                            var resp = JSON.parse(xhr.responseText);
                            if (resp.success) {
                                msg.style.background = '#d4edda';
                                msg.style.color = '#155724';
                                msg.style.border = '1px solid #c3e6cb';
                                msg.textContent = resp.data && resp.data.message ? resp.data.message : 'Vertrag erfolgreich hochgeladen.';
                                form.style.display = 'none';
                            } else {
                                msg.style.background = '#f8d7da';
                                msg.style.color = '#721c24';
                                msg.style.border = '1px solid #f5c6cb';
                                msg.textContent = resp.data && resp.data.message ? resp.data.message : 'Fehler beim Hochladen.';
                            }
                        } catch (err) {
                            msg.style.background = '#f8d7da';
                            msg.style.color = '#721c24';
                            msg.textContent = 'Unbekannter Fehler.';
                        }
                        msg.style.display = 'block';
                    };
                    xhr.onerror = function() {
                        btn.disabled = false;
                        btn.textContent = 'Vertrag hochladen';
                        msg.style.background = '#f8d7da';
                        msg.style.color = '#721c24';
                        msg.textContent = 'Netzwerkfehler beim Hochladen.';
                        msg.style.display = 'block';
                    };
                    xhr.send(formData);
                });
            })();
            </script>
        </div>
        <?php
    }

    public function handle_signed_contract_upload() {
        check_ajax_referer('tmgmt_upload_signed_contract_nonce', 'nonce');

        // Validate token
        $token = isset($_POST['tmgmt_token']) ? sanitize_text_field($_POST['tmgmt_token']) : '';
        if (empty($token)) {
            wp_send_json_error(array('message' => 'Ungültiger Token.'));
        }

        global $wpdb;
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $this->table_name WHERE token = %s AND status = 'active'",
            $token
        ));

        if (!$record) {
            wp_send_json_error(array('message' => 'Ungültiger Token.'));
        }

        $event_id = intval($record->event_id);

        // Check file was uploaded
        if (empty($_FILES['signed_contract']) || $_FILES['signed_contract']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(array('message' => 'Keine Datei hochgeladen oder Fehler beim Upload.'));
        }

        // Validate MIME type
        $allowed_mime_types = array('application/pdf', 'image/jpeg', 'image/png');
        $file_tmp = $_FILES['signed_contract']['tmp_name'];
        $detected_mime = $this->detect_mime_type($file_tmp, $_FILES['signed_contract']['type']);

        if (!in_array($detected_mime, $allowed_mime_types, true)) {
            wp_send_json_error(array('message' => 'Ungültiger Dateityp. Erlaubt: PDF, JPG, PNG.'));
            return;
        }

        // Load WP media handling
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        // Save as WP Attachment
        $attachment_id = media_handle_upload('signed_contract', $event_id);

        if (is_wp_error($attachment_id)) {
            wp_send_json_error(array('message' => $attachment_id->get_error_message()));
        }

        // Save attachment ID as post meta (Req. 6.3)
        update_post_meta($event_id, '_tmgmt_signed_contract_attachment_id', $attachment_id);

        // Set event status to contract_signed (Req. 6.4)
        update_post_meta($event_id, '_tmgmt_status', 'contract_signed');

        // Send notification email (Req. 6.5, 6.6)
        $notification_user_id = get_option('tmgmt_contract_notification_user_id');
        $notify_email = '';

        if ($notification_user_id) {
            $user_data = get_userdata(intval($notification_user_id));
            if ($user_data && !empty($user_data->user_email)) {
                $notify_email = $user_data->user_email;
            }
        }

        if (empty($notify_email)) {
            $notify_email = get_option('admin_email');
        }

        $event = get_post($event_id);
        $event_title = $event ? $event->post_title : 'Event #' . $event_id;

        $subject = sprintf('Unterschriebener Vertrag hochgeladen: %s', $event_title);
        $body    = sprintf(
            "Ein Kunde hat den unterschriebenen Vertrag für das Event \"%s\" (ID: %d) hochgeladen.\n\nAttachment-ID: %d",
            $event_title,
            $event_id,
            $attachment_id
        );

        wp_mail($notify_email, $subject, $body);

        wp_send_json_success(array('message' => 'Vertrag erfolgreich hochgeladen.'));
    }

    /**
     * Detect the MIME type of an uploaded file.
     * Extracted as a protected method to allow test subclasses to override it.
     *
     * @param string $tmp_path  Path to the temporary uploaded file.
     * @param string $fallback  Browser-reported MIME type as fallback.
     * @return string
     */
    protected function detect_mime_type(string $tmp_path, string $fallback): string {
        return function_exists('mime_content_type') ? mime_content_type($tmp_path) : $fallback;
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
            $subject = TMGMT_Placeholder_Parser::parse($subject, $event_id);
            $content = TMGMT_Placeholder_Parser::parse($content, $event_id);
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
