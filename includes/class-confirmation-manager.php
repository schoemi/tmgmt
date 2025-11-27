<?php

class TMGMT_Confirmation_Manager {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'tmgmt_confirmations';

        // Register public handler for confirmation link
        add_action('admin_post_tmgmt_confirm_action', array($this, 'handle_confirmation_link'));
        add_action('admin_post_nopriv_tmgmt_confirm_action', array($this, 'handle_confirmation_link'));
    }

    /**
     * Creates the database table on plugin activation.
     */
    public function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $this->table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_id bigint(20) NOT NULL,
            action_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            recipient_email varchar(255) NOT NULL,
            token varchar(64) NOT NULL,
            requested_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            confirmed_at datetime DEFAULT NULL,
            status varchar(20) DEFAULT 'pending' NOT NULL,
            PRIMARY KEY  (id),
            KEY event_id (event_id),
            KEY token (token)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Creates a new confirmation request.
     */
    public function create_request($event_id, $action_id, $recipient_email, $user_id = null) {
        global $wpdb;

        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // Generate a secure token
        $token = bin2hex(random_bytes(32));

        $result = $wpdb->insert(
            $this->table_name,
            array(
                'event_id' => $event_id,
                'action_id' => $action_id,
                'user_id' => $user_id,
                'recipient_email' => $recipient_email,
                'token' => $token,
                'requested_at' => current_time('mysql'),
                'status' => 'pending'
            ),
            array('%d', '%d', '%d', '%s', '%s', '%s', '%s')
        );

        if ($result) {
            return array(
                'id' => $wpdb->insert_id,
                'token' => $token,
                'link' => admin_url('admin-post.php?action=tmgmt_confirm_action&token=' . $token)
            );
        }
        return false;
    }

    /**
     * Handles the confirmation link click.
     */
    public function handle_confirmation_link() {
        global $wpdb;

        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

        if (empty($token)) {
            wp_die('Ungültiger Link.');
        }

        $entry = $wpdb->get_row($wpdb->prepare("SELECT * FROM $this->table_name WHERE token = %s", $token));

        if (!$entry) {
            wp_die('Anfrage nicht gefunden.');
        }

        if ($entry->status === 'confirmed') {
            // Already confirmed, redirect to success page anyway or show message
            $this->redirect_to_success_page($entry->action_id);
            return;
        }

        // Update status
        $wpdb->update(
            $this->table_name,
            array(
                'status' => 'confirmed',
                'confirmed_at' => current_time('mysql')
            ),
            array('id' => $entry->id),
            array('%s', '%s'),
            array('%d')
        );

        // Trigger "Confirmation of Confirmation" if configured
        $this->send_confirmation_receipt($entry);

        // Redirect
        $this->redirect_to_success_page($entry->action_id);
    }

    private function redirect_to_success_page($action_id) {
        $page_id = get_post_meta($action_id, '_tmgmt_action_confirm_page', true);
        
        if ($page_id && get_post_status($page_id) === 'publish') {
            wp_redirect(get_permalink($page_id));
            exit;
        }

        // Fallback
        wp_die('Vielen Dank! Die Bestätigung wurde erfolgreich gespeichert.', 'Bestätigung erfolgreich', array('response' => 200));
    }

    private function send_confirmation_receipt($entry) {
        $send_receipt = get_post_meta($entry->action_id, '_tmgmt_action_send_receipt', true);
        
        if ($send_receipt) {
            $template_id = get_post_meta($entry->action_id, '_tmgmt_action_receipt_template', true);
            
            if ($template_id) {
                $template_post = get_post($template_id);
                if ($template_post) {
                    $subject = $template_post->post_title; // Or meta if subject is separate
                    // Usually subject is in meta for email templates
                    $subject_meta = get_post_meta($template_id, '_tmgmt_email_subject', true);
                    if ($subject_meta) $subject = $subject_meta;

                    $content = $template_post->post_content;

                    // Simple placeholder replacement (could use Placeholder Parser if needed, but context is limited)
                    // We have event_id, so we CAN use the parser!
                    
                    if (class_exists('TMGMT_Placeholder_Parser')) {
                        $parser = new TMGMT_Placeholder_Parser($entry->event_id);
                        $subject = $parser->parse($subject);
                        $content = $parser->parse($content);
                    }

                    $headers = array('Content-Type: text/html; charset=UTF-8');
                    wp_mail($entry->recipient_email, $subject, $content, $headers);
                }
            }
        }
    }

    /**
     * Renders the backend table for an event.
     */
    public function render_backend_table($event_id) {
        global $wpdb;
        $entries = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $this->table_name WHERE event_id = %d ORDER BY requested_at DESC",
                $event_id
            )
        );

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>Vorgang</th>';
        echo '<th>Empfänger</th>';
        echo '<th>Angefordert am</th>';
        echo '<th>Status</th>';
        echo '<th>Bestätigt am</th>';
        echo '<th>Versender</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        if (empty($entries)) {
            echo '<tr><td colspan="6">Keine Bestätigungen angefordert.</td></tr>';
        } else {
            foreach ($entries as $entry) {
                $action_name = get_the_title($entry->action_id);
                $user_info = get_userdata($entry->user_id);
                $user_name = $user_info ? $user_info->display_name : 'Unbekannt';
                
                $status_label = $entry->status === 'confirmed' 
                    ? '<span style="color:green; font-weight:bold;">Bestätigt</span>' 
                    : '<span style="color:orange;">Ausstehend</span>';

                $confirmed_date = $entry->confirmed_at ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($entry->confirmed_at)) : '-';
                $requested_date = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($entry->requested_at));

                echo '<tr>';
                echo '<td>' . esc_html($action_name) . '</td>';
                echo '<td>' . esc_html($entry->recipient_email) . '</td>';
                echo '<td>' . $requested_date . '</td>';
                echo '<td>' . $status_label . '</td>';
                echo '<td>' . $confirmed_date . '</td>';
                echo '<td>' . esc_html($user_name) . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
    }
}
