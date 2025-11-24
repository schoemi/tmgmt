<?php

class TMGMT_Log_Manager {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'tmgmt_logs';
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
            message text NOT NULL,
            communication_id bigint(20) DEFAULT 0,
            PRIMARY KEY  (id),
            KEY event_id (event_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Inserts a new log entry.
     * 
     * @param int $event_id
     * @param string $type
     * @param string $message
     * @param int $user_id (Optional, defaults to current user)
     * @param int $communication_id (Optional, link to communication table)
     * @return int|false The row ID or false on error.
     */
    public function log($event_id, $type, $message, $user_id = null, $communication_id = 0) {
        global $wpdb;

        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        return $wpdb->insert(
            $this->table_name,
            array(
                'event_id' => $event_id,
                'user_id' => $user_id,
                'created_at' => current_time('mysql'),
                'type' => $type,
                'message' => $message,
                'communication_id' => $communication_id
            ),
            array(
                '%d',
                '%d',
                '%s',
                '%s',
                '%s',
                '%d'
            )
        );
    }

    /**
     * Retrieves logs for a specific event.
     * 
     * @param int $event_id
     * @return array
     */
    public function get_logs($event_id) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $this->table_name WHERE event_id = %d ORDER BY created_at DESC",
                $event_id
            )
        );
    }

    /**
     * Renders the log table HTML.
     * 
     * @param int $event_id
     */
    public function render_log_table($event_id) {
        $logs = $this->get_logs($event_id);
        
        // Get unique users for filter
        $users = array();
        foreach ($logs as $log) {
            if (!isset($users[$log->user_id])) {
                $user_info = get_userdata($log->user_id);
                $users[$log->user_id] = $user_info ? $user_info->display_name : 'Unbekannt';
            }
        }

        echo '<div class="tmgmt-log-container">';
        
        // Filter Controls
        echo '<div class="tmgmt-log-controls" style="margin-bottom: 10px;">';
        echo '<select id="tmgmt-log-user-filter">';
        echo '<option value="">Alle Benutzer</option>';
        foreach ($users as $id => $name) {
            echo '<option value="' . esc_attr($name) . '">' . esc_html($name) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        echo '<table class="widefat fixed striped" id="tmgmt-log-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th class="sortable" data-sort="date" style="width: 150px; cursor:pointer;">Datum <span class="dashicons dashicons-arrow-down-alt2"></span></th>';
        echo '<th class="sortable" data-sort="user" style="width: 150px; cursor:pointer;">Benutzer <span class="dashicons dashicons-sort"></span></th>';
        echo '<th class="sortable" data-sort="type" style="width: 150px; cursor:pointer;">Typ <span class="dashicons dashicons-sort"></span></th>';
        echo '<th>Nachricht</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        if (empty($logs)) {
            echo '<tr><td colspan="4">Keine Einträge vorhanden.</td></tr>';
        } else {
            foreach ($logs as $log) {
                $user_info = get_userdata($log->user_id);
                $user_name = $user_info ? $user_info->display_name : 'Unbekannt';
                // Use ISO format for sorting attribute, display format for text
                $date_iso = $log->created_at;
                $date_formatted = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->created_at));

                echo '<tr>';
                echo '<td data-value="' . esc_attr($date_iso) . '">' . esc_html($date_formatted) . '</td>';
                echo '<td data-value="' . esc_attr($user_name) . '">' . esc_html($user_name) . '</td>';
                echo '<td data-value="' . esc_attr($log->type) . '">' . esc_html($log->type) . '</td>';
                echo '<td>' . esc_html($log->message) . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }
}
