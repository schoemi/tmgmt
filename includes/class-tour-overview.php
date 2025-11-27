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
            'tmgmt_view_tour_overview',
            'tmgmt-tour-overview',
            array($this, 'render_page')
        );
    }

    public function render_page() {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'events';
        $base_url = admin_url('edit.php?post_type=event&page=tmgmt-tour-overview');
        ?>
        <div class="wrap">
            <h1>Touren-Übersicht</h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_url(add_query_arg('tab', 'events', $base_url)); ?>" class="nav-tab <?php echo $active_tab == 'events' ? 'nav-tab-active' : ''; ?>">Event-Übersicht</a>
                <a href="<?php echo esc_url(add_query_arg('tab', 'tours', $base_url)); ?>" class="nav-tab <?php echo $active_tab == 'tours' ? 'nav-tab-active' : ''; ?>">Touren-Übersicht</a>
            </h2>

            <?php
            if ($active_tab == 'tours') {
                $this->render_tours_tab();
            } else {
                $this->render_events_tab();
            }
            ?>
        </div>
        <?php
    }

    private function render_tours_tab() {
        $year = isset($_GET['filter_year']) ? intval($_GET['filter_year']) : date('Y');
        $filter_mode = isset($_GET['filter_mode']) ? sanitize_text_field($_GET['filter_mode']) : '';
        
        // Fetch Tours
        $args = array(
            'post_type' => 'tmgmt_tour',
            'numberposts' => -1,
            'meta_query' => array(
                array(
                    'key' => 'tmgmt_tour_date',
                    'value' => array($year . '-01-01', $year . '-12-31'),
                    'compare' => 'BETWEEN'
                )
            ),
            'orderby' => 'meta_value',
            'meta_key' => 'tmgmt_tour_date',
            'order' => 'ASC'
        );
        
        $tours = get_posts($args);

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

        // Filter UI
        ?>
        <form method="get" action="<?php echo esc_url(admin_url('edit.php')); ?>" style="margin: 20px 0; background: #fff; padding: 15px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <input type="hidden" name="post_type" value="event">
            <input type="hidden" name="page" value="tmgmt-tour-overview">
            <input type="hidden" name="tab" value="tours">
            
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
                    <label for="filter_mode" style="display:block; font-weight:bold; margin-bottom:5px;">Planungsstatus</label>
                    <select name="filter_mode" id="filter_mode">
                        <option value="">Alle</option>
                        <option value="real" <?php selected($filter_mode, 'real'); ?>>Echtplanung</option>
                        <option value="draft" <?php selected($filter_mode, 'draft'); ?>>Entwurfsplanung</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="button button-primary">Filtern</button>
                </div>
            </div>
        </form>

        <!-- Bulk Export -->
        <div style="margin: 20px 0; background: #fff; padding: 15px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <h3 style="margin-top:0;">Bulk Export (Bus-Briefing)</h3>
            <p>Exportiert alle Touren mit Status "Echtplanung" im gewählten Zeitraum.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" target="_blank">
                <input type="hidden" name="action" value="tmgmt_export_tours_pdf">
                <div style="display: flex; gap: 15px; align-items: flex-end;">
                    <div>
                        <label for="export_start" style="display:block; font-weight:bold; margin-bottom:5px;">Von</label>
                        <input type="date" name="export_start" id="export_start" value="<?php echo $year; ?>-01-01" required>
                    </div>
                    <div>
                        <label for="export_end" style="display:block; font-weight:bold; margin-bottom:5px;">Bis</label>
                        <input type="date" name="export_end" id="export_end" value="<?php echo $year; ?>-12-31" required>
                    </div>
                    <div>
                        <button type="submit" class="button button-secondary"><span class="dashicons dashicons-pdf" style="margin-top:3px;"></span> PDF Exportieren</button>
                    </div>
                </div>
            </form>
        </div>

        <?php
        // Calculate Statistics
        $count_real_tours = 0;
        $count_tours_with_errors = 0;
        $count_events_in_real_tours = 0;
        $total_bus_km = 0;
        $real_event_ids = array();

        foreach ($tours as $tour) {
            $mode = get_post_meta($tour->ID, 'tmgmt_tour_mode', true);
            $error_count = (int)get_post_meta($tour->ID, 'tmgmt_tour_error_count', true);
            $bus_travel = get_post_meta($tour->ID, 'tmgmt_tour_bus_travel', true);
            $data_json = get_post_meta($tour->ID, 'tmgmt_tour_data', true);
            $schedule = json_decode($data_json, true);

            if ($mode === 'real') {
                $count_real_tours++;
                if ($error_count > 0) $count_tours_with_errors++;
                
                if (is_array($schedule)) {
                    foreach ($schedule as $item) {
                        if ($item['type'] === 'event') {
                            $count_events_in_real_tours++;
                            $real_event_ids[] = $item['id'];
                        }
                    }
                }
            }
            
            // Bus KM (only real planning)
            if ($mode === 'real' && $bus_travel == '1' && is_array($schedule)) {
                foreach ($schedule as $item) {
                    if (isset($item['distance'])) {
                        $total_bus_km += floatval($item['distance']);
                    }
                }
            }
        }

        // Calculate Events without Real Planning
        $all_events_args = array(
            'post_type' => 'event',
            'numberposts' => -1,
            'meta_query' => array(
                array(
                    'key' => '_tmgmt_event_date',
                    'value' => array($year . '-01-01', $year . '-12-31'),
                    'compare' => 'BETWEEN'
                )
            )
        );
        $all_events = get_posts($all_events_args);
        $count_events_without_real_planning = 0;
        foreach ($all_events as $evt) {
            if (!in_array($evt->ID, $real_event_ids)) {
                $count_events_without_real_planning++;
            }
        }

        // Filter Tours for Display
        if ($filter_mode) {
            $tours = array_filter($tours, function($t) use ($filter_mode) {
                $m = get_post_meta($t->ID, 'tmgmt_tour_mode', true);
                // Handle empty/default
                if (!$m) $m = 'draft';
                return $m === $filter_mode;
            });
        }
        ?>

        <!-- Statistics Dashboard -->
        <div style="display: flex; gap: 20px; margin: 20px 0; flex-wrap: wrap;">
            <div class="card" style="padding: 15px; flex: 1; min-width: 150px; text-align: center; border-left: 4px solid #00a32a;">
                <h2 style="margin: 0; font-size: 2.5em; color: #00a32a;"><?php echo $count_real_tours; ?></h2>
                <p style="margin: 5px 0 0; font-weight: bold;">Touren (Echtplanung)</p>
            </div>
            
            <div class="card" style="padding: 15px; flex: 1; min-width: 150px; text-align: center; border-left: 4px solid <?php echo ($count_tours_with_errors > 0) ? '#d63638' : '#ccd0d4'; ?>;">
                <h2 style="margin: 0; font-size: 2em; color: <?php echo ($count_tours_with_errors > 0) ? '#d63638' : '#333'; ?>;"><?php echo $count_tours_with_errors; ?></h2>
                <p style="margin: 5px 0 0;">Touren mit Fehlern</p>
            </div>

            <div class="card" style="padding: 15px; flex: 1; min-width: 150px; text-align: center; border-left: 4px solid #2271b1;">
                <h2 style="margin: 0; font-size: 2em; color: #2271b1;"><?php echo $count_events_in_real_tours; ?></h2>
                <p style="margin: 5px 0 0;">Events (Echtplanung)</p>
            </div>

            <div class="card" style="padding: 15px; flex: 1; min-width: 150px; text-align: center; border-left: 4px solid #dba617;">
                <h2 style="margin: 0; font-size: 2em; color: #dba617;"><?php echo $count_events_without_real_planning; ?></h2>
                <p style="margin: 5px 0 0;">Events ohne Echtplanung</p>
            </div>

            <div class="card" style="padding: 15px; flex: 1; min-width: 150px; text-align: center; border-left: 4px solid #666;">
                <h2 style="margin: 0; font-size: 2em; color: #666;"><?php echo number_format($total_bus_km, 1, ',', '.'); ?> km</h2>
                <p style="margin: 5px 0 0;">Bus-Reisekilometer</p>
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 50px;"></th>
                    <th>Datum</th>
                    <th>Zeitraum</th>
                    <th>Titel</th>
                    <th>Events</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tours)): ?>
                    <tr><td colspan="6">Keine Touren gefunden.</td></tr>
                <?php else: ?>
                    <?php foreach ($tours as $tour): 
                        $date = get_post_meta($tour->ID, 'tmgmt_tour_date', true);
                        $data_json = get_post_meta($tour->ID, 'tmgmt_tour_data', true);
                        $schedule = json_decode($data_json, true);
                        $mode = get_post_meta($tour->ID, 'tmgmt_tour_mode', true);
                        $error_count = (int)get_post_meta($tour->ID, 'tmgmt_tour_error_count', true);
                        $warning_count = (int)get_post_meta($tour->ID, 'tmgmt_tour_warning_count', true);
                        
                        // Analyze Schedule
                        $start_time = '-';
                        $end_time = '-';
                        $event_count = 0;
                        $events_list = array();

                        if (is_array($schedule) && !empty($schedule)) {
                            // Find Start (Departure from Start)
                            foreach ($schedule as $item) {
                                if ($item['type'] === 'start' && isset($item['departure_time'])) {
                                    $start_time = $item['departure_time'];
                                }
                                if ($item['type'] === 'end' && isset($item['arrival_time'])) {
                                    $end_time = $item['arrival_time'];
                                }
                                if ($item['type'] === 'event') {
                                    $event_count++;
                                    // Get Event Status
                                    $event_status = get_post_meta($item['id'], '_tmgmt_status', true);
                                    if (!$event_status) $event_status = get_post_meta($item['id'], 'tmgmt_status', true);
                                    
                                    $status_label = isset($all_statuses[$event_status]) ? $all_statuses[$event_status] : $event_status;

                                    $events_list[] = array(
                                        'time' => isset($item['show_start']) ? $item['show_start'] : '-',
                                        'title' => $item['title'],
                                        'location' => $item['location'],
                                        'status' => $status_label,
                                        'buffer' => isset($item['actual_buffer']) ? $item['actual_buffer'] : '-',
                                        'error' => isset($item['error']) ? $item['error'] : '',
                                        'warning' => isset($item['warning']) ? $item['warning'] : ''
                                    );
                                }
                            }
                        }

                        $formatted_date = date_i18n(get_option('date_format'), strtotime($date));
                    ?>
                    <tr class="tour-row" data-tour-id="<?php echo $tour->ID; ?>">
                        <td>
                            <button type="button" class="button-link tmgmt-toggle-row" style="font-size: 20px; cursor: pointer;">
                                <span class="dashicons dashicons-arrow-right-alt2"></span>
                            </button>
                        </td>
                        <td><strong><?php echo esc_html($formatted_date); ?></strong></td>
                        <td><?php echo esc_html($start_time . ' - ' . $end_time); ?></td>
                        <td>
                            <strong><a href="<?php echo get_edit_post_link($tour->ID); ?>"><?php echo esc_html($tour->post_title); ?></a></strong>
                        </td>
                        <td><?php echo $event_count; ?></td>
                        <td>
                            <?php 
                            if ($mode === 'real') {
                                echo '<span style="background: #00a32a; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 10px; text-transform: uppercase;">Echtplanung</span> ';
                            } else {
                                echo '<span style="background: #666; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 10px; text-transform: uppercase;">Entwurf</span> ';
                            }

                            if ($error_count > 0) {
                                echo '<span style="color: #d63638;"><span class="dashicons dashicons-warning"></span> ' . $error_count . ' Fehler</span>';
                            } elseif ($warning_count > 0) {
                                echo '<span style="color: #dba617;"><span class="dashicons dashicons-warning"></span> ' . $warning_count . ' Warnungen</span>';
                            } else {
                                echo '<span style="color: #00a32a;"><span class="dashicons dashicons-yes"></span> OK</span>';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr class="tour-details-row" id="tour-details-<?php echo $tour->ID; ?>" style="display:none;">
                        <td colspan="6" style="padding: 0 0 20px 50px; background: #f9f9f9; box-shadow: inset 0 5px 10px -10px rgba(0,0,0,0.1);">
                            <?php if (empty($events_list)): ?>
                                <p style="padding: 10px; margin: 0; color: #666;">Keine Events in dieser Tour.</p>
                            <?php else: ?>
                            <table class="widefat" style="border:none; background: transparent; margin-top: 10px;">
                                <thead>
                                    <tr>
                                        <th>Uhrzeit</th>
                                        <th>Event</th>
                                        <th>Ort</th>
                                        <th>Status</th>
                                        <th>Puffer</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($events_list as $evt): ?>
                                    <tr>
                                        <td><?php echo esc_html($evt['time']); ?></td>
                                        <td><?php echo esc_html($evt['title']); ?></td>
                                        <td><?php echo esc_html($evt['location']); ?></td>
                                        <td><?php echo esc_html($evt['status']); ?></td>
                                        <td>
                                            <?php 
                                            echo esc_html($evt['buffer']) . ' Min'; 
                                            if ($evt['error']) echo ' <span class="dashicons dashicons-warning" style="color:#d63638" title="' . esc_attr($evt['error']) . '"></span>';
                                            elseif ($evt['warning']) echo ' <span class="dashicons dashicons-warning" style="color:#dba617" title="' . esc_attr($evt['warning']) . '"></span>';
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <script>
        jQuery(document).ready(function($) {
            $('.tmgmt-toggle-row').on('click', function() {
                var row = $(this).closest('tr');
                var tourId = row.data('tour-id');
                var detailsRow = $('#tour-details-' + tourId);
                var icon = $(this).find('.dashicons');
                
                if (detailsRow.is(':visible')) {
                    detailsRow.hide();
                    icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-right-alt2');
                } else {
                    detailsRow.show();
                    icon.removeClass('dashicons-arrow-right-alt2').addClass('dashicons-arrow-down-alt2');
                }
            });
        });
        </script>
        <?php
    }

    private function render_events_tab() {
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
                <input type="hidden" name="tab" value="events">
                
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
        <?php
    }
}
