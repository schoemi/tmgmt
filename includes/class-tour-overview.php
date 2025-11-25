<?php

class TMGMT_Tour_Overview {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_page'));
    }

    public function add_menu_page() {
        add_submenu_page(
            'edit.php?post_type=event',
            'Touren-Übersicht',
            'Touren-Übersicht',
            'manage_options',
            'tmgmt-tour-overview',
            array($this, 'render_page')
        );
    }

    public function render_page() {
        // Filters
        $year = isset($_GET['filter_year']) ? intval($_GET['filter_year']) : date('Y');
        $status_filter = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '';

        // Build Query
        $args = array(
            'post_type' => 'event',
            'numberposts' => -1,
            'meta_query' => array(
                'relation' => 'AND'
            )
        );

        // Date Filter
        if ($year) {
            $start_date = $year . '-01-01';
            $end_date = $year . '-12-31';
            
            $args['meta_query']['date_clause'] = array(
                'key' => '_tmgmt_event_date',
                'value' => array($start_date, $end_date),
                'compare' => 'BETWEEN'
            );
            
            $args['orderby'] = 'date_clause';
            $args['order'] = 'ASC';
        } else {
            $args['meta_key'] = '_tmgmt_event_date';
            $args['orderby'] = 'meta_value';
            $args['order'] = 'ASC';
        }

        // Status Filter
        if ($status_filter) {
            $args['meta_query'][] = array(
                'key' => '_tmgmt_status',
                'value' => $status_filter,
                'compare' => '='
            );
        }

        $events = get_posts($args);

        // Pre-fetch statuses
        $all_statuses = array();
        $status_posts = get_posts(array(
            'post_type' => 'tmgmt_status_def',
            'numberposts' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ));
        foreach ($status_posts as $sp) {
            $all_statuses[$sp->post_name] = $sp->post_title;
        }

        // KPIs
        $total_events = count($events);
        $unplanned_events = 0;
        $tours_with_issues = 0;
        $checked_tour_dates = array();

        foreach ($events as $event) {
            $arrival = get_post_meta($event->ID, '_tmgmt_event_arrival_time', true);
            if (empty($arrival)) {
                $unplanned_events++;
            }

            // Check Tour Issues
            $date = get_post_meta($event->ID, '_tmgmt_event_date', true);
            if ($date && !in_array($date, $checked_tour_dates)) {
                $checked_tour_dates[] = $date;
                // Find Tour Post for this date
                $tour_posts = get_posts(array(
                    'post_type' => 'tmgmt_tour',
                    'numberposts' => 1,
                    'meta_query' => array(
                        array(
                            'key' => 'tmgmt_tour_date',
                            'value' => $date,
                            'compare' => '='
                        )
                    )
                ));
                
                if ($tour_posts) {
                    $tour_id = $tour_posts[0]->ID;
                    $errors = (int)get_post_meta($tour_id, 'tmgmt_tour_error_count', true);
                    $warnings = (int)get_post_meta($tour_id, 'tmgmt_tour_warning_count', true);
                    if ($errors > 0 || $warnings > 0) {
                        $tours_with_issues++;
                    }
                }
            }
        }

        ?>
        <div class="wrap">
            <h1>Touren-Übersicht</h1>
            
            <!-- KPIs -->
            <div style="display: flex; gap: 20px; margin: 20px 0;">
                <div class="card" style="padding: 15px; flex: 1; text-align: center;">
                    <h2 style="margin: 0; font-size: 2em;"><?php echo $unplanned_events; ?></h2>
                    <p>Termine ohne Planung</p>
                </div>
                <div class="card" style="padding: 15px; flex: 1; text-align: center;">
                    <h2 style="margin: 0; font-size: 2em; color: #d63638;"><?php echo $tours_with_issues; ?></h2>
                    <p>Touren mit Fehlern/Warnungen</p>
                </div>
                <div class="card" style="padding: 15px; flex: 1; text-align: center;">
                    <h2 style="margin: 0; font-size: 2em;"><?php echo $total_events; ?></h2>
                    <p>Termine Gesamt (<?php echo $year; ?>)</p>
                </div>
            </div>

            <!-- Filters -->
            <form method="get" action="<?php echo esc_url(admin_url('edit.php')); ?>" style="margin-bottom: 20px; background: #fff; padding: 15px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <input type="hidden" name="post_type" value="event">
                <input type="hidden" name="page" value="tmgmt-tour-overview">
                
                <div style="display: flex; gap: 15px; align-items: flex-end;">
                    <div>
                        <label for="filter_year" style="display:block; font-weight:bold; margin-bottom:5px;">Jahr</label>
                        <select name="filter_year" id="filter_year">
                            <?php 
                            $current_year = date('Y');
                            for ($y = $current_year - 1; $y <= $current_year + 2; $y++) {
                                echo '<option value="' . $y . '" ' . selected($year, $y, false) . '>' . $y . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="filter_status" style="display:block; font-weight:bold; margin-bottom:5px;">Status</label>
                        <select name="filter_status" id="filter_status">
                            <option value="">Alle Status</option>
                            <?php
                            foreach ($all_statuses as $slug => $label) {
                                echo '<option value="' . esc_attr($slug) . '" ' . selected($status_filter, $slug, false) . '>' . esc_html($label) . '</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <div>
                        <button type="submit" class="button button-primary">Filtern</button>
                    </div>
                </div>
            </form>

            <!-- Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column column-date sortable desc">
                            <a href="#">Datum / Zeit</a>
                        </th>
                        <th scope="col" class="manage-column column-title">Event</th>
                        <th scope="col" class="manage-column column-location">Ort</th>
                        <th scope="col" class="manage-column column-status">Status</th>
                        <th scope="col" class="manage-column column-planning">Planung</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($events)): ?>
                        <tr><td colspan="5">Keine Termine gefunden.</td></tr>
                    <?php else: ?>
                        <?php foreach ($events as $event): 
                            $date = get_post_meta($event->ID, '_tmgmt_event_date', true);
                            $time = get_post_meta($event->ID, '_tmgmt_event_start_time', true);
                            $city = get_post_meta($event->ID, '_tmgmt_venue_city', true);
                            $status_slug = get_post_meta($event->ID, '_tmgmt_status', true);
                            $arrival = get_post_meta($event->ID, '_tmgmt_event_arrival_time', true);
                            
                            // Get Status Label
                            $status_label = isset($all_statuses[$status_slug]) ? $all_statuses[$status_slug] : $status_slug;

                            // Check Tour Status
                            $tour_status_html = '';
                            if (empty($arrival)) {
                                $tour_status_html = '<span class="dashicons dashicons-minus" style="color:#ccc"></span> Offen';
                            } else {
                                // Check for errors in the tour
                                $tour_posts = get_posts(array(
                                    'post_type' => 'tmgmt_tour',
                                    'numberposts' => 1,
                                    'meta_query' => array(
                                        array(
                                            'key' => 'tmgmt_tour_date',
                                            'value' => $date,
                                            'compare' => '='
                                        )
                                    )
                                ));
                                
                                if ($tour_posts) {
                                    $tour_id = $tour_posts[0]->ID;
                                    $errors = (int)get_post_meta($tour_id, 'tmgmt_tour_error_count', true);
                                    $warnings = (int)get_post_meta($tour_id, 'tmgmt_tour_warning_count', true);
                                    $update_required = get_post_meta($tour_id, 'tmgmt_tour_update_required', true);
                                    
                                    if ($update_required) {
                                        $tour_status_html = '<span class="dashicons dashicons-update" style="color:#d63638"></span> <span style="color:#d63638">Update erforderlich</span>';
                                    } elseif ($errors > 0) {
                                        $tour_status_html = '<span class="dashicons dashicons-warning" style="color:#d63638"></span> <span style="color:#d63638">Fehler (' . $errors . ')</span>';
                                    } elseif ($warnings > 0) {
                                        $tour_status_html = '<span class="dashicons dashicons-warning" style="color:#dba617"></span> <span style="color:#dba617">Warnung (' . $warnings . ')</span>';
                                    } else {
                                        $tour_status_html = '<span class="dashicons dashicons-yes" style="color:#00a32a"></span> OK';
                                    }
                                    
                                    // Link to Tour
                                    $edit_link = get_edit_post_link($tour_id);
                                    $tour_status_html .= ' <a href="' . $edit_link . '" target="_blank" style="text-decoration:none;"><span class="dashicons dashicons-edit"></span></a>';
                                } else {
                                    // Should not happen if arrival is set, but fallback
                                    $tour_status_html = '<span class="dashicons dashicons-yes" style="color:#00a32a"></span> OK';
                                }
                            }
                            
                            $formatted_date = date_i18n(get_option('date_format'), strtotime($date));
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($formatted_date); ?></strong><br>
                                <?php echo esc_html($time); ?> Uhr
                            </td>
                            <td>
                                <strong><a href="<?php echo get_edit_post_link($event->ID); ?>" target="_blank"><?php echo esc_html($event->post_title); ?></a></strong>
                            </td>
                            <td><?php echo esc_html($city); ?></td>
                            <td><?php echo esc_html($status_label); ?></td>
                            <td><?php echo $tour_status_html; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
