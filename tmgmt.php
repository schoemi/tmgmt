<?php
/**
 * Plugin Name: Töns Management
 * Description: Gig Management Plugin for WordPress
 * Version: 0.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define Constants
define( 'TMGMT_VERSION', '0.2.0' );
define('TMGMT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TMGMT_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include Post Types
require_once TMGMT_PLUGIN_DIR . 'includes/post-types/class-event-post-type.php';
require_once TMGMT_PLUGIN_DIR . 'includes/post-types/class-event-meta-boxes.php';
require_once TMGMT_PLUGIN_DIR . 'includes/post-types/class-status-definition-post-type.php';
require_once TMGMT_PLUGIN_DIR . 'includes/post-types/class-event-status.php';
require_once TMGMT_PLUGIN_DIR . 'includes/post-types/class-webhook-post-type.php';
require_once TMGMT_PLUGIN_DIR . 'includes/post-types/class-email-template-post-type.php';
require_once TMGMT_PLUGIN_DIR . 'includes/post-types/class-action-post-type.php';
require_once TMGMT_PLUGIN_DIR . 'includes/post-types/class-kanban-column-post-type.php';
require_once TMGMT_PLUGIN_DIR . 'includes/class-placeholder-parser.php';
require_once TMGMT_PLUGIN_DIR . 'includes/class-action-handler.php';
require_once TMGMT_PLUGIN_DIR . 'includes/class-dashboard.php';
require_once TMGMT_PLUGIN_DIR . 'includes/class-appointment-list.php';
require_once TMGMT_PLUGIN_DIR . 'includes/class-rest-api.php';
require_once TMGMT_PLUGIN_DIR . 'includes/class-assets.php';
require_once TMGMT_PLUGIN_DIR . 'includes/class-log-manager.php';
require_once TMGMT_PLUGIN_DIR . 'includes/class-communication-manager.php';
require_once TMGMT_PLUGIN_DIR . 'includes/class-roles.php';
require_once TMGMT_PLUGIN_DIR . 'includes/class-frontend-dashboard.php';
require_once TMGMT_PLUGIN_DIR . 'includes/class-settings-menu.php';

// Initialize Plugin
function tmgmt_init() {
    new TMGMT_Settings_Menu(); // Init first to register menu page
    new TMGMT_Event_Post_Type();
    new TMGMT_Event_Meta_Boxes();
    new TMGMT_Status_Definition_Post_Type();
    new TMGMT_Webhook_Post_Type();
    new TMGMT_Email_Template_Post_Type();
    new TMGMT_Action_Post_Type();
    new TMGMT_Kanban_Column_Post_Type();
    new TMGMT_Action_Handler();
    new TMGMT_Dashboard();
    new TMGMT_Appointment_List();
    new TMGMT_REST_API();
    new TMGMT_Assets();
    new TMGMT_Roles();
    new TMGMT_Frontend_Dashboard();
}
add_action('plugins_loaded', 'tmgmt_init');

// Activation Hook for DB Table
register_activation_hook(__FILE__, 'tmgmt_activate');
function tmgmt_activate() {
    $log_manager = new TMGMT_Log_Manager();
    $log_manager->create_table();
    
    $comm_manager = new TMGMT_Communication_Manager();
    $comm_manager->create_table();
}

