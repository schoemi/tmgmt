<?php

class TMGMT_Admin_Menu {

    public function __construct() {
        add_action('admin_menu', array($this, 'reorder_menu_items'), 100);
    }

    public function reorder_menu_items() {
        global $submenu;

        $parent_slug = 'edit.php?post_type=event';

        if (!isset($submenu[$parent_slug])) {
            return;
        }

        $current_menu = $submenu[$parent_slug];
        $new_menu = array();

        // Define desired order by slug
        $desired_order = array(
            'tmgmt-dashboard',          // Dashboard
            'tmgmt-tour-overview',      // Touren-Übersicht
            'tmgmt-appointment-list',   // Terminliste
            'edit.php?post_type=event', // Alle Gigs
            'post-new.php?post_type=event', // Neuen Gig hinzufügen (keep it near Gigs)
            'edit.php?post_type=tmgmt_tour', // Alle Tourenpläne
            'tmgmt-settings'            // Einstellungen
        );

        // Helper to find item by slug
        $find_item = function($slug) use ($current_menu) {
            foreach ($current_menu as $key => $item) {
                if ($item[2] === $slug) {
                    return $item;
                }
            }
            return null;
        };

        // Build new menu
        foreach ($desired_order as $slug) {
            $item = $find_item($slug);
            if ($item) {
                $new_menu[] = $item;
            }
        }

        // Add any remaining items that were not in our desired list
        foreach ($current_menu as $item) {
            $slug = $item[2];
            if (!in_array($slug, $desired_order)) {
                $new_menu[] = $item;
            }
        }

        $submenu[$parent_slug] = $new_menu;
    }
}
