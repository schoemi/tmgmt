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

    public static function get_all_caps() {
        return array(
            'General' => array(
                self::CAP_VIEW_DASHBOARD => 'Dashboard anzeigen',
                self::CAP_MANAGE_SETTINGS => 'Einstellungen verwalten',
                self::CAP_VIEW_LOGS => 'Logs anzeigen',
            ),
            'Events (Gigs)' => array(
                self::CAP_READ_EVENT => 'Events lesen',
                self::CAP_EDIT_EVENTS => 'Events bearbeiten',
                self::CAP_EDIT_OTHERS_EVENTS => 'Fremde Events bearbeiten',
                self::CAP_PUBLISH_EVENTS => 'Events veröffentlichen',
                self::CAP_READ_PRIVATE_EVENTS => 'Private Events lesen',
                self::CAP_DELETE_EVENTS => 'Events löschen',
                self::CAP_DELETE_OTHERS_EVENTS => 'Fremde Events löschen',
                self::CAP_DELETE_PRIVATE_EVENTS => 'Private Events löschen',
                self::CAP_DELETE_PUBLISHED_EVENTS => 'Veröffentlichte Events löschen',
                self::CAP_EDIT_PUBLISHED_EVENTS => 'Veröffentlichte Events bearbeiten',
                self::CAP_EDIT_PRIVATE_EVENTS => 'Private Events bearbeiten',
                self::CAP_SET_EVENT_STATUS_DIRECTLY => 'Status direkt ändern',
            ),
            'Tours' => array(
                self::CAP_VIEW_TOUR_OVERVIEW => 'Tourenübersicht anzeigen',
                self::CAP_CALCULATE_TOUR => 'Touren berechnen',
                self::CAP_EDIT_TOURS => 'Touren bearbeiten',
                self::CAP_EDIT_OTHERS_TOURS => 'Fremde Touren bearbeiten',
                self::CAP_PUBLISH_TOURS => 'Touren veröffentlichen',
                self::CAP_READ_TOUR => 'Touren lesen',
                self::CAP_READ_PRIVATE_TOURS => 'Private Touren lesen',
                self::CAP_DELETE_TOURS => 'Touren löschen',
                self::CAP_DELETE_OTHERS_TOURS => 'Fremde Touren löschen',
                self::CAP_DELETE_PRIVATE_TOURS => 'Private Touren löschen',
                self::CAP_DELETE_PUBLISHED_TOURS => 'Veröffentlichte Touren löschen',
                self::CAP_EDIT_PUBLISHED_TOURS => 'Veröffentlichte Touren bearbeiten',
                self::CAP_EDIT_PRIVATE_TOURS => 'Private Touren bearbeiten',
            ),
            'Lists & Others' => array(
                self::CAP_VIEW_APPOINTMENT_LIST => 'Terminliste anzeigen',
                self::CAP_MANAGE_STATUSES => 'Status verwalten',
                self::CAP_MANAGE_EMAIL_TEMPLATES => 'E-Mail Vorlagen verwalten',
                self::CAP_SEND_EMAILS => 'E-Mails senden',
            )
        );
    }

    public static function get_managed_roles() {
        return array(
            'administrator' => 'Administrator',
            'tmgmt_manager' => 'Tour Manager',
            'tmgmt_booker' => 'Booker',
        );
    }

    public static function get_default_caps() {
        $all_caps = self::get_all_caps();
        $flat_caps = array();
        foreach ($all_caps as $group) {
            foreach ($group as $cap => $label) {
                $flat_caps[] = $cap;
            }
        }

        // Administrator gets everything
        $admin_caps = array();
        foreach ($flat_caps as $cap) {
            $admin_caps[$cap] = true;
        }

        // Manager gets everything except Settings
        $manager_caps = $admin_caps;
        unset($manager_caps[self::CAP_MANAGE_SETTINGS]);

        // Booker gets limited set
        $booker_caps = array(
            'read' => true,
            self::CAP_VIEW_DASHBOARD => true,
            self::CAP_VIEW_APPOINTMENT_LIST => true,
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
        );

        return array(
            'administrator' => $admin_caps,
            'tmgmt_manager' => $manager_caps,
            'tmgmt_booker' => $booker_caps,
        );
    }

    public function register_roles() {
        // Remove roles if they exist to ensure fresh caps (optional, but good for dev)
        remove_role('tmgmt_booker');
        remove_role('tmgmt_manager');

        $saved_caps = get_option('tmgmt_role_caps');
        if (empty($saved_caps) || !is_array($saved_caps)) {
            $saved_caps = self::get_default_caps();
        }

        // 1. Booker Role
        $booker_caps = isset($saved_caps['tmgmt_booker']) ? $saved_caps['tmgmt_booker'] : array();
        $booker_caps['read'] = true; // Always required
        add_role('tmgmt_booker', 'Booker', $booker_caps);

        // 2. Tour Manager Role
        $manager_caps = isset($saved_caps['tmgmt_manager']) ? $saved_caps['tmgmt_manager'] : array();
        $manager_caps['read'] = true; // Always required
        add_role('tmgmt_manager', 'Tour Manager', $manager_caps);
    }

    public function add_caps_to_admin() {
        $admin = get_role('administrator');
        if ($admin) {
            $saved_caps = get_option('tmgmt_role_caps');
            if (empty($saved_caps) || !is_array($saved_caps)) {
                $saved_caps = self::get_default_caps();
            }

            $admin_caps = isset($saved_caps['administrator']) ? $saved_caps['administrator'] : array();
            
            // Get all possible TMGMT caps to ensure we can also remove them if unchecked
            $all_possible_caps = array();
            foreach (self::get_all_caps() as $group) {
                foreach ($group as $cap => $label) {
                    $all_possible_caps[] = $cap;
                }
            }

            foreach ($all_possible_caps as $cap) {
                if (isset($admin_caps[$cap]) && $admin_caps[$cap]) {
                    if (!$admin->has_cap($cap)) {
                        $admin->add_cap($cap);
                    }
                } else {
                    // If cap is not in saved config (or false), remove it
                    if ($admin->has_cap($cap)) {
                        $admin->remove_cap($cap);
                    }
                }
            }
        }
    }
}

