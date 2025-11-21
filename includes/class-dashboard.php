<?php

class TMGMT_Dashboard {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    private $page_hook;

    public function add_menu_page() {
        $this->page_hook = add_submenu_page(
            'edit.php?post_type=event',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'tmgmt-dashboard',
            array($this, 'render_dashboard')
        );
    }

    public function enqueue_scripts($hook) {
        if ($hook != $this->page_hook) {
            return;
        }
        // We can add specific CSS here or inline for now
    }

    public function render_dashboard() {
        // 1. Fetch Columns
        $columns = get_posts(array(
            'post_type' => 'tmgmt_kanban_col',
            'numberposts' => -1,
            'meta_key' => '_tmgmt_kanban_order',
            'orderby' => 'meta_value_num',
            'order' => 'ASC'
        ));

        // 2. Fetch All Events
        $events = get_posts(array(
            'post_type' => 'event',
            'numberposts' => -1,
            'post_status' => 'any' // Include drafts? Maybe just publish/future
        ));

        // 3. Group Events by Status
        $events_by_status = array();
        foreach ($events as $event) {
            $status = get_post_meta($event->ID, '_tmgmt_status', true);
            if (!$status) $status = 'undefined';
            $events_by_status[$status][] = $event;
        }

        echo '<div class="wrap">';
        echo '<h1>Töns MGMT Dashboard</h1>';
        
        if (empty($columns)) {
            echo '<p>Bitte konfigurieren Sie zuerst <a href="edit.php?post_type=tmgmt_kanban_col">Kanban Spalten</a>.</p>';
            echo '</div>';
            return;
        }

        echo '<div class="tmgmt-kanban-board">';
        
        foreach ($columns as $col) {
            $col_title = $col->post_title;
            $col_color = get_post_meta($col->ID, '_tmgmt_kanban_color', true);
            $col_statuses = get_post_meta($col->ID, '_tmgmt_kanban_statuses', true);
            
            if (!$col_color) $col_color = '#e0e0e0';
            if (!is_array($col_statuses)) $col_statuses = array();

            echo '<div class="tmgmt-kanban-column">';
            echo '<div class="tmgmt-kanban-header" style="border-top: 3px solid ' . esc_attr($col_color) . ';">';
            echo '<h2>' . esc_html($col_title) . '</h2>';
            echo '</div>';
            echo '<div class="tmgmt-kanban-body">';

            // Find events that match any of the statuses in this column
            $col_events = array();
            foreach ($col_statuses as $status_id) {
                // We need the slug of the status definition
                $status_post = get_post($status_id);
                if ($status_post) {
                    $slug = $status_post->post_name;
                    if (isset($events_by_status[$slug])) {
                        $col_events = array_merge($col_events, $events_by_status[$slug]);
                    }
                }
            }

            // Render Cards
            if (empty($col_events)) {
                echo '<p class="tmgmt-empty-col">Keine Events</p>';
            } else {
                foreach ($col_events as $event) {
                    $this->render_event_card($event);
                }
            }

            echo '</div>'; // body
            echo '</div>'; // column
        }

        echo '</div>'; // board
        echo '</div>'; // wrap

        // Inline Styles for now
        ?>
        <style>
            .tmgmt-kanban-board {
                display: flex;
                gap: 15px;
                overflow-x: auto;
                padding-bottom: 20px;
                align-items: flex-start;
            }
            .tmgmt-kanban-column {
                background: #f0f0f1;
                min-width: 280px;
                width: 280px;
                border-radius: 4px;
                flex-shrink: 0;
            }
            .tmgmt-kanban-header {
                padding: 10px 15px;
                background: #fff;
                border-bottom: 1px solid #ddd;
                border-radius: 4px 4px 0 0;
            }
            .tmgmt-kanban-header h2 {
                margin: 0;
                font-size: 16px;
                color: #333;
            }
            .tmgmt-kanban-body {
                padding: 10px;
                min-height: 100px;
            }
            .tmgmt-event-card {
                background: #fff;
                border: 1px solid #ccc;
                border-radius: 3px;
                padding: 10px;
                margin-bottom: 10px;
                box-shadow: 0 1px 2px rgba(0,0,0,0.05);
                cursor: pointer;
                transition: box-shadow 0.2s;
            }
            .tmgmt-event-card:hover {
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            }
            .tmgmt-card-title {
                font-weight: bold;
                margin-bottom: 5px;
                font-size: 14px;
            }
            .tmgmt-card-date {
                font-size: 12px;
                color: #666;
                margin-bottom: 5px;
            }
            .tmgmt-card-status {
                font-size: 11px;
                display: inline-block;
                padding: 2px 6px;
                background: #eee;
                border-radius: 10px;
                color: #555;
            }
            .tmgmt-empty-col {
                color: #999;
                text-align: center;
                font-style: italic;
                margin-top: 20px;
            }
        </style>
        <?php
    }

    private function render_event_card($event) {
        $date = get_post_meta($event->ID, '_tmgmt_event_date', true);
        $status_slug = get_post_meta($event->ID, '_tmgmt_status', true);
        $status_label = TMGMT_Event_Status::get_label($status_slug);
        $edit_link = get_edit_post_link($event->ID);

        echo '<div class="tmgmt-event-card" onclick="location.href=\'' . $edit_link . '\'">';
        echo '<div class="tmgmt-card-title">' . esc_html($event->post_title) . '</div>';
        if ($date) {
            echo '<div class="tmgmt-card-date"><span class="dashicons dashicons-calendar" style="font-size:14px; width:14px; height:14px; vertical-align:middle;"></span> ' . esc_html($date) . '</div>';
        }
        echo '<div class="tmgmt-card-status">' . esc_html($status_label) . '</div>';
        echo '</div>';
    }
}
