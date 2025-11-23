<?php

class TMGMT_Roles {

    const ROLE_CREW = 'tmgmt_crew';
    const CAP_VIEW_DASHBOARD = 'view_tmgmt_dashboard';
    const CAP_EDIT_EVENTS = 'edit_tmgmt_events';

    public function __construct() {
        add_action('init', array($this, 'register_roles'));
    }

    public function register_roles() {
        // Add Crew Role
        add_role(self::ROLE_CREW, 'Crew / Band', array(
            'read' => true,
            self::CAP_VIEW_DASHBOARD => true,
            self::CAP_EDIT_EVENTS => true,
            'upload_files' => true // Allow media upload if needed
        ));

        // Add caps to Administrator
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap(self::CAP_VIEW_DASHBOARD);
            $admin->add_cap(self::CAP_EDIT_EVENTS);
        }
    }
}
