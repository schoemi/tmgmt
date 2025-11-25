<?php

class TMGMT_Roles {

    // General
    const CAP_VIEW_DASHBOARD = 'tmgmt_view_dashboard';
    const CAP_MANAGE_SETTINGS = 'tmgmt_manage_settings';
    const CAP_VIEW_LOGS = 'tmgmt_view_logs';

    // Events (Gigs) - CPT Caps
    const CAP_READ_EVENT = 'read_event';
    const CAP_EDIT_EVENTS = 'edit_events'; // Plural for CPT base
    const CAP_EDIT_OTHERS_EVENTS = 'edit_others_events';
    const CAP_PUBLISH_EVENTS = 'publish_events';
    const CAP_READ_PRIVATE_EVENTS = 'read_private_events';
    const CAP_DELETE_EVENTS = 'delete_events';
    const CAP_DELETE_OTHERS_EVENTS = 'delete_others_events';
    const CAP_DELETE_PRIVATE_EVENTS = 'delete_private_events';
    const CAP_DELETE_PUBLISHED_EVENTS = 'delete_published_events';
    const CAP_EDIT_PUBLISHED_EVENTS = 'edit_published_events';
    const CAP_EDIT_PRIVATE_EVENTS = 'edit_private_events';

    // Event Specifics
    const CAP_SET_EVENT_STATUS_DIRECTLY = 'tmgmt_set_event_status_directly';

    // Tours - CPT Caps
    const CAP_VIEW_TOUR_OVERVIEW = 'tmgmt_view_tour_overview';
    const CAP_CALCULATE_TOUR = 'tmgmt_calculate_tour';
    const CAP_EDIT_TOURS = 'edit_tours';
    const CAP_EDIT_OTHERS_TOURS = 'edit_others_tours';
    const CAP_PUBLISH_TOURS = 'publish_tours';
    const CAP_READ_TOUR = 'read_tour';
    const CAP_READ_PRIVATE_TOURS = 'read_private_tours';
    const CAP_DELETE_TOURS = 'delete_tours';
    const CAP_DELETE_OTHERS_TOURS = 'delete_others_tours';
    const CAP_DELETE_PRIVATE_TOURS = 'delete_private_tours';
    const CAP_DELETE_PUBLISHED_TOURS = 'delete_published_tours';
    const CAP_EDIT_PUBLISHED_TOURS = 'edit_published_tours';
    const CAP_EDIT_PRIVATE_TOURS = 'edit_private_tours';

    // Lists
    const CAP_VIEW_APPOINTMENT_LIST = 'tmgmt_view_appointment_list';

    // Status & Templates
    const CAP_MANAGE_STATUSES = 'tmgmt_manage_statuses';
    const CAP_MANAGE_EMAIL_TEMPLATES = 'tmgmt_manage_email_templates';
    const CAP_SEND_EMAILS = 'tmgmt_send_emails';

    public function __construct() {
        add_action('init', array($this, 'register_roles'));
        add_action('admin_init', array($this, 'add_caps_to_admin'));
    }

    public function register_roles() {
        // Remove roles if they exist to ensure fresh caps (optional, but good for dev)
        remove_role('tmgmt_booker');
        remove_role('tmgmt_manager');

        // 1. Booker Role
        // Can manage events, but NOT tours, settings, or direct status changes.
        add_role('tmgmt_booker', 'Booker', array(
            'read' => true,
            
            // Dashboard & Lists
            self::CAP_VIEW_DASHBOARD => true,
            self::CAP_VIEW_APPOINTMENT_LIST => true,
            
            // Events (CRUD)
            self::CAP_READ_EVENT => true,
            self::CAP_EDIT_EVENTS => true,
            self::CAP_EDIT_OTHERS_EVENTS => true,
            self::CAP_PUBLISH_EVENTS => true,
            self::CAP_READ_PRIVATE_EVENTS => true,
            self::CAP_DELETE_EVENTS => true,
            self::CAP_DELETE_OTHERS_EVENTS => true,
            self::CAP_DELETE_PRIVATE_EVENTS => true,
            self::CAP_DELETE_PUBLISHED_EVENTS => true,
            self::CAP_EDIT_PUBLISHED_EVENTS => true,
            self::CAP_EDIT_PRIVATE_EVENTS => true,
            
            // NO Direct Status Change
            // self::CAP_SET_EVENT_STATUS_DIRECTLY => false,
        ));

        // 2. Tour Manager Role
        // Can manage events AND tours, and change status directly.
        add_role('tmgmt_manager', 'Tour Manager', array(
            'read' => true,
            
            // Dashboard & Lists
            self::CAP_VIEW_DASHBOARD => true,
            self::CAP_VIEW_APPOINTMENT_LIST => true,
            self::CAP_VIEW_TOUR_OVERVIEW => true,
            self::CAP_VIEW_LOGS => true,
            
            // Events (Full Access)
            self::CAP_READ_EVENT => true,
            self::CAP_EDIT_EVENTS => true,
            self::CAP_EDIT_OTHERS_EVENTS => true,
            self::CAP_PUBLISH_EVENTS => true,
            self::CAP_READ_PRIVATE_EVENTS => true,
            self::CAP_DELETE_EVENTS => true,
            self::CAP_DELETE_OTHERS_EVENTS => true,
            self::CAP_DELETE_PRIVATE_EVENTS => true,
            self::CAP_DELETE_PUBLISHED_EVENTS => true,
            self::CAP_EDIT_PUBLISHED_EVENTS => true,
            self::CAP_EDIT_PRIVATE_EVENTS => true,
            self::CAP_SET_EVENT_STATUS_DIRECTLY => true, // Allowed!
            
            // Tours (Full Access)
            self::CAP_READ_TOUR => true,
            self::CAP_EDIT_TOURS => true,
            self::CAP_EDIT_OTHERS_TOURS => true,
            self::CAP_PUBLISH_TOURS => true,
            self::CAP_READ_PRIVATE_TOURS => true,
            self::CAP_DELETE_TOURS => true,
            self::CAP_DELETE_OTHERS_TOURS => true,
            self::CAP_DELETE_PRIVATE_TOURS => true,
            self::CAP_DELETE_PUBLISHED_TOURS => true,
            self::CAP_EDIT_PUBLISHED_TOURS => true,
            self::CAP_EDIT_PRIVATE_TOURS => true,
            self::CAP_CALCULATE_TOUR => true,
            
            // Settings & Templates
            self::CAP_MANAGE_STATUSES => true,
            self::CAP_MANAGE_EMAIL_TEMPLATES => true,
            self::CAP_SEND_EMAILS => true,
            // self::CAP_MANAGE_SETTINGS => false, // Keep settings for Admin only?
        ));
    }

    public function add_caps_to_admin() {
        $admin = get_role('administrator');
        if ($admin) {
            $caps = array(
                self::CAP_VIEW_DASHBOARD,
                self::CAP_MANAGE_SETTINGS,
                self::CAP_VIEW_LOGS,
                
                self::CAP_READ_EVENT,
                self::CAP_EDIT_EVENTS,
                self::CAP_EDIT_OTHERS_EVENTS,
                self::CAP_PUBLISH_EVENTS,
                self::CAP_READ_PRIVATE_EVENTS,
                self::CAP_DELETE_EVENTS,
                self::CAP_DELETE_OTHERS_EVENTS,
                self::CAP_DELETE_PRIVATE_EVENTS,
                self::CAP_DELETE_PUBLISHED_EVENTS,
                self::CAP_EDIT_PUBLISHED_EVENTS,
                self::CAP_EDIT_PRIVATE_EVENTS,
                
                self::CAP_SET_EVENT_STATUS_DIRECTLY,
                
                self::CAP_VIEW_TOUR_OVERVIEW,
                self::CAP_CALCULATE_TOUR,
                self::CAP_EDIT_TOURS,
                self::CAP_EDIT_OTHERS_TOURS,
                self::CAP_PUBLISH_TOURS,
                self::CAP_READ_TOUR,
                self::CAP_READ_PRIVATE_TOURS,
                self::CAP_DELETE_TOURS,
                self::CAP_DELETE_OTHERS_TOURS,
                self::CAP_DELETE_PRIVATE_TOURS,
                self::CAP_DELETE_PUBLISHED_TOURS,
                self::CAP_EDIT_PUBLISHED_TOURS,
                self::CAP_EDIT_PRIVATE_TOURS,
                
                self::CAP_VIEW_APPOINTMENT_LIST,
                
                self::CAP_MANAGE_STATUSES,
                self::CAP_MANAGE_EMAIL_TEMPLATES,
                self::CAP_SEND_EMAILS
            );

            foreach ($caps as $cap) {
                if (!$admin->has_cap($cap)) {
                    $admin->add_cap($cap);
                }
            }
        }
    }
}
