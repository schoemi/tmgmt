<?php

class TMGMT_Appointment_List {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_page'));
    }

    public function add_menu_page() {
        add_submenu_page(
            'edit.php?post_type=event',
            'Terminliste',
            'Terminliste',
            'edit_posts',
            'tmgmt-appointment-list',
            array($this, 'render_list')
        );
    }

    public function render_list() {
        $args = array(
            'post_type'      => 'event',
            'posts_per_page' => -1,
            'meta_key'       => '_tmgmt_event_date',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'post_status'    => array('publish', 'future', 'draft', 'pending', 'private')
        );

        $events = get_posts($args);

        // Group by Date
        $grouped = array();
        foreach ($events as $event) {
            $date = get_post_meta($event->ID, '_tmgmt_event_date', true);
            if (empty($date)) {
                $date = '0000-00-00'; // For "No Date"
            }
            $grouped[$date][] = $event;
        }

        // Sort groups (dates)
        ksort($grouped);

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">Terminliste</h1>';
        
        if (empty($events)) {
            echo '<p>Keine Termine gefunden.</p>';
            echo '</div>';
            return;
        }

        echo '<div class="tmgmt-appointment-list" style="max-width: 800px; margin-top: 20px;">';

        foreach ($grouped as $date => $day_events) {
            $is_no_date = ($date === '0000-00-00');
            $display_date = $is_no_date ? 'Ohne Datum' : date_i18n(get_option('date_format'), strtotime($date));
            $day_name = $is_no_date ? '' : date_i18n('l', strtotime($date));

            echo '<div class="tmgmt-day-group" style="margin-bottom: 30px;">';
            echo '<h2 style="border-bottom: 2px solid #ddd; padding-bottom: 10px; margin-bottom: 15px; font-size: 1.2em;">';
            if (!$is_no_date) {
                echo '<span style="font-weight:normal; color:#666; margin-right:10px;">' . esc_html($day_name) . '</span>';
            }
            echo esc_html($display_date);
            echo '</h2>';

            echo '<ul style="list-style: none; padding: 0; margin: 0;">';
            
            foreach ($day_events as $event) {
                $this->render_event_item($event);
            }

            echo '</ul>';
            echo '</div>';
        }

        echo '</div>'; // .tmgmt-appointment-list
        echo '</div>'; // .wrap
    }

    private function render_event_item($event) {
        $start_time = get_post_meta($event->ID, '_tmgmt_event_start_time', true);
        $city = get_post_meta($event->ID, '_tmgmt_venue_city', true);
        $status_slug = get_post_meta($event->ID, '_tmgmt_status', true);
        $status_label = TMGMT_Event_Status::get_label($status_slug);
        $edit_link = get_edit_post_link($event->ID);

        // Determine status color (simple logic or fetch from definition if we had color there)
        // For now, just a badge
        
        echo '<li style="background: #fff; border: 1px solid #ccd0d4; padding: 15px; margin-bottom: 10px; border-left: 4px solid #2271b1; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 1px 1px rgba(0,0,0,.04);">';
        
        echo '<div style="flex-grow: 1;">';
        echo '<div style="font-size: 1.1em; font-weight: 600; margin-bottom: 5px;">';
        echo '<a href="' . esc_url($edit_link) . '" style="text-decoration: none; color: #1d2327;">' . esc_html($event->post_title) . '</a>';
        echo '</div>';
        
        echo '<div style="color: #646970; font-size: 0.9em;">';
        if ($start_time) {
            echo '<span class="dashicons dashicons-clock" style="font-size: 16px; vertical-align: text-bottom; margin-right: 3px;"></span> ' . esc_html($start_time) . ' Uhr';
            echo '<span style="margin: 0 10px; color: #ddd;">|</span>';
        }
        if ($city) {
            echo '<span class="dashicons dashicons-location" style="font-size: 16px; vertical-align: text-bottom; margin-right: 3px;"></span> ' . esc_html($city);
        }
        echo '</div>';
        echo '</div>'; // Content

        echo '<div style="text-align: right; min-width: 150px;">';
        echo '<span style="background: #f0f0f1; padding: 4px 8px; border-radius: 4px; font-size: 0.85em; color: #50575e; border: 1px solid #dcdcde;">';
        echo esc_html($status_label);
        echo '</span>';
        echo '</div>';

        echo '</li>';
    }
}
