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
        // Handle Filter
        $filter_val = isset($_GET['tmgmt_period']) ? sanitize_text_field($_GET['tmgmt_period']) : '';

        $args = array(
            'post_type'      => 'event',
            'posts_per_page' => -1,
            'meta_key'       => '_tmgmt_event_date',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'post_status'    => array('publish', 'future', 'draft', 'pending', 'private')
        );

        // Apply Date Filter
        if (!empty($filter_val)) {
            if (strpos($filter_val, 'year_') === 0) {
                $year = substr($filter_val, 5);
                $args['meta_query'] = array(
                    array(
                        'key'     => '_tmgmt_event_date',
                        'value'   => array($year . '-01-01', $year . '-12-31'),
                        'compare' => 'BETWEEN',
                        'type'    => 'DATE'
                    )
                );
            } elseif (strpos($filter_val, 'session_') === 0) {
                $start_year = substr($filter_val, 8);
                $end_year = $start_year + 1;
                $args['meta_query'] = array(
                    array(
                        'key'     => '_tmgmt_event_date',
                        'value'   => array($start_year . '-07-01', $end_year . '-06-30'),
                        'compare' => 'BETWEEN',
                        'type'    => 'DATE'
                    )
                );
            }
        }

        $events = get_posts($args);

        // Calculate available filters based on ALL events (not just filtered ones)
        $all_dates = $this->get_all_event_dates();
        $filters = $this->build_filters($all_dates);

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
        
        // Render Filter Form
        echo '<form method="get" style="margin: 20px 0; display: flex; align-items: center; gap: 10px;">';
        echo '<input type="hidden" name="post_type" value="event">';
        echo '<input type="hidden" name="page" value="tmgmt-appointment-list">';
        echo '<select name="tmgmt_period">';
        echo '<option value="">Alle Zeiträume</option>';
        
        if (!empty($filters['years'])) {
            echo '<optgroup label="Jahre">';
            foreach ($filters['years'] as $year) {
                $val = 'year_' . $year;
                $sel = ($filter_val === $val) ? 'selected' : '';
                echo '<option value="' . esc_attr($val) . '" ' . $sel . '>' . esc_html($year) . '</option>';
            }
            echo '</optgroup>';
        }

        if (!empty($filters['sessions'])) {
            echo '<optgroup label="Saisons (01.07. - 30.06.)">';
            foreach ($filters['sessions'] as $year => $label) {
                $val = 'session_' . $year;
                $sel = ($filter_val === $val) ? 'selected' : '';
                echo '<option value="' . esc_attr($val) . '" ' . $sel . '>' . esc_html($label) . '</option>';
            }
            echo '</optgroup>';
        }

        echo '</select>';
        echo '<button type="submit" class="button">Filtern</button>';
        if (!empty($filter_val)) {
            echo '<a href="' . admin_url('edit.php?post_type=event&page=tmgmt-appointment-list') . '" class="button">Reset</a>';
        }
        echo '</form>';

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

    private function get_all_event_dates() {
        global $wpdb;
        $dates = $wpdb->get_col("
            SELECT meta_value 
            FROM $wpdb->postmeta 
            WHERE meta_key = '_tmgmt_event_date' 
            AND meta_value != ''
            ORDER BY meta_value ASC
        ");
        return array_unique($dates);
    }

    private function build_filters($dates) {
        $years = array();
        $sessions = array();

        foreach ($dates as $date) {
            $timestamp = strtotime($date);
            $year = date('Y', $timestamp);
            $month = date('n', $timestamp);

            // Add to years
            if (!in_array($year, $years)) {
                $years[] = $year;
            }

            // Determine Session
            // If month >= 7 (July), session starts this year.
            // If month < 7 (Jan-Jun), session started previous year.
            if ($month >= 7) {
                $session_start = $year;
            } else {
                $session_start = $year - 1;
            }
            
            $session_label = $session_start . '/' . ($session_start + 1);
            $sessions[$session_start] = $session_label;
        }

        sort($years);
        ksort($sessions);

        return array('years' => $years, 'sessions' => $sessions);
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
