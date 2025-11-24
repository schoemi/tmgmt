<?php

class TMGMT_Communication_Manager {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'tmgmt_communication';
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
            user_id bigint(20) NOT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            type varchar(50) NOT NULL,
            recipient varchar(255) DEFAULT '' NOT NULL,
            subject varchar(255) DEFAULT '' NOT NULL,
            content longtext NOT NULL,
            PRIMARY KEY  (id),
            KEY event_id (event_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Inserts a new communication entry.
     * 
     * @param int $event_id
     * @param string $type 'email' or 'note'
     * @param string $recipient
     * @param string $subject
     * @param string $content
     * @param int $user_id (Optional)
     * @return int|false The row ID or false on error.
     */
    public function add_entry($event_id, $type, $recipient, $subject, $content, $user_id = null) {
        global $wpdb;

        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $result = $wpdb->insert(
            $this->table_name,
            array(
                'event_id' => $event_id,
                'user_id' => $user_id,
                'created_at' => current_time('mysql'),
                'type' => $type,
                'recipient' => $recipient,
                'subject' => $subject,
                'content' => $content
            ),
            array(
                '%d',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s'
            )
        );

        if ($result) {
            return $wpdb->insert_id;
        }
        return false;
    }

    /**
     * Retrieves communication entries for a specific event.
     * 
     * @param int $event_id
     * @return array
     */
    public function get_entries($event_id) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $this->table_name WHERE event_id = %d ORDER BY created_at DESC",
                $event_id
            )
        );
    }

    /**
     * Renders the communication table HTML for Backend.
     * 
     * @param int $event_id
     */
    public function render_backend_table($event_id) {
        $entries = $this->get_entries($event_id);
        
        echo '<div class="tmgmt-communication-list">';
        if (empty($entries)) {
            echo '<p>Keine Kommunikation vorhanden.</p>';
        } else {
            echo '<table class="widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>Datum</th>';
            echo '<th>Typ</th>';
            echo '<th>Von</th>';
            echo '<th>An</th>';
            echo '<th>Betreff / Inhalt</th>';
            echo '</tr></thead>';
            echo '<tbody>';
            
            foreach ($entries as $entry) {
                $user_info = get_userdata($entry->user_id);
                $user_name = $user_info ? $user_info->display_name : 'System';
                $date = date_i18n('d.m.Y H:i', strtotime($entry->created_at));
                $type_label = ($entry->type === 'email') ? 'E-Mail' : 'Notiz';
                $recipient = ($entry->type === 'email') ? esc_html($entry->recipient) : 'Intern';
                
                echo '<tr>';
                echo '<td>' . $date . '</td>';
                echo '<td>' . $type_label . '</td>';
                echo '<td>' . esc_html($user_name) . '</td>';
                echo '<td>' . $recipient . '</td>';
                echo '<td>';
                if ($entry->type === 'email') {
                    echo '<strong>' . esc_html($entry->subject) . '</strong><br>';
                }
                // Truncate content for preview, maybe add a "Show more" toggle
                $preview = wp_trim_words($entry->content, 20, '...');
                echo '<div class="tmgmt-comm-preview">' . esc_html($preview) . '</div>';
                echo '<a href="#" class="tmgmt-comm-toggle" data-id="' . $entry->id . '">Details anzeigen</a>';
                echo '<div id="tmgmt-comm-full-' . $entry->id . '" style="display:none; margin-top:10px; background:#fff; padding:10px; border:1px solid #ddd;">' . nl2br(esc_html($entry->content)) . '</div>';
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
            
            // Simple JS for toggle
            ?>
            <script>
            jQuery(document).ready(function($) {
                $('.tmgmt-comm-toggle').on('click', function(e) {
                    e.preventDefault();
                    var id = $(this).data('id');
                    $('#tmgmt-comm-full-' + id).toggle();
                });
            });
            </script>
            <?php
        }
        echo '</div>';
    }
}
