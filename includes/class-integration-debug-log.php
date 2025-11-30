// Tabelle bei Plugin-Aktivierung anlegen
register_activation_hook(__FILE__, function() {
    $logger = new TMGMT_Integration_Debug_Log();
    $logger->install_table();
});
<?php
/**
 * TMGMT_Integration_Debug_Log
 *
 * Verwaltung der Debug-Logs für Integrationen
 */
if (!defined('ABSPATH')) exit;

class TMGMT_Integration_Debug_Log {
    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'tmgmt_integration_debug_log';
    }

    // Tabelle anlegen
    public function install_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$this->table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            integration_name VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL,
            request LONGTEXT NOT NULL,
            response LONGTEXT NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    // Log-Eintrag speichern
    public function log($integration_name, $request, $response) {
        global $wpdb;
        $wpdb->insert(
            $this->table,
            array(
                'integration_name' => $integration_name,
                'created_at' => current_time('mysql'),
                'request' => maybe_serialize($request),
                'response' => maybe_serialize($response)
            ),
            array('%s', '%s', '%s', '%s')
        );
    }
}
