<?php
/**
 * Plugin Name: Töns Management
 * Description: Skeleton plugin rebuilt from scratch.
 * Version: 0.0.2
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define Constants
define('TMGMT_VERSION', '0.0.2');
define('TMGMT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TMGMT_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include Post Types
require_once TMGMT_PLUGIN_DIR . 'includes/post-types/class-event-post-type.php';
require_once TMGMT_PLUGIN_DIR . 'includes/post-types/class-event-meta-boxes.php';
require_once TMGMT_PLUGIN_DIR . 'includes/post-types/class-status-definition-post-type.php';
require_once TMGMT_PLUGIN_DIR . 'includes/post-types/class-event-status.php';
require_once TMGMT_PLUGIN_DIR . 'includes/post-types/class-webhook-post-type.php';
require_once TMGMT_PLUGIN_DIR . 'includes/post-types/class-kanban-column-post-type.php';
require_once TMGMT_PLUGIN_DIR . 'includes/class-action-handler.php';
require_once TMGMT_PLUGIN_DIR . 'includes/class-dashboard.php';
require_once TMGMT_PLUGIN_DIR . 'includes/class-appointment-list.php';
require_once TMGMT_PLUGIN_DIR . 'includes/class-rest-api.php';
require_once TMGMT_PLUGIN_DIR . 'includes/class-assets.php';
require_once TMGMT_PLUGIN_DIR . 'includes/class-log-manager.php';

// Initialize Plugin
function tmgmt_init() {
    new TMGMT_Event_Post_Type();
    new TMGMT_Event_Meta_Boxes();
    new TMGMT_Status_Definition_Post_Type();
    new TMGMT_Webhook_Post_Type();
    new TMGMT_Kanban_Column_Post_Type();
    new TMGMT_Action_Handler();
    new TMGMT_Dashboard();
    new TMGMT_Appointment_List();
    new TMGMT_REST_API();
    new TMGMT_Assets();
}
add_action('plugins_loaded', 'tmgmt_init');

// Activation Hook for DB Table
register_activation_hook(__FILE__, 'tmgmt_activate');
function tmgmt_activate() {
    $log_manager = new TMGMT_Log_Manager();
    $log_manager->create_table();
}

