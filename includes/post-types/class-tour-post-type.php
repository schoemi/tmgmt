<?php

class TMGMT_Tour_Post_Type {

    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
        add_action('admin_post_tmgmt_print_tour', array($this, 'handle_print_tour'));
        add_filter('manage_tmgmt_tour_posts_columns', array($this, 'add_custom_columns'));
        add_action('manage_tmgmt_tour_posts_custom_column', array($this, 'render_custom_columns'), 10, 2);
    }

    public function add_custom_columns($columns) {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['tmgmt_tour_status'] = 'Status';
            }
        }
        return $new_columns;
    }

    public function render_custom_columns($column, $post_id) {
        if ($column === 'tmgmt_tour_status') {
            $update_required = get_post_meta($post_id, 'tmgmt_tour_update_required', true);
            $error_count = (int)get_post_meta($post_id, 'tmgmt_tour_error_count', true);
            $warning_count = (int)get_post_meta($post_id, 'tmgmt_tour_warning_count', true);

            if ($update_required) {
                echo '<span class="dashicons dashicons-update" style="color:#d63638"></span> <span style="color:#d63638; font-weight:bold;">Update erforderlich</span>';
            } elseif ($error_count > 0) {
                echo '<span class="dashicons dashicons-warning" style="color:#d63638"></span> <span style="color:#d63638">' . $error_count . ' Fehler</span>';
            } elseif ($warning_count > 0) {
                echo '<span class="dashicons dashicons-warning" style="color:#dba617"></span> <span style="color:#dba617">' . $warning_count . ' Warnungen</span>';
            } else {
                echo '<span class="dashicons dashicons-yes" style="color:#00a32a"></span> OK';
            }
        }
    }

    public function register_post_type() {
        $labels = array(
            'name'                  => 'Tourenpläne',
            'singular_name'         => 'Tourenplan',
            'menu_name'             => 'Tourenpläne',
            'name_admin_bar'        => 'Tourenplan',
            'add_new'               => 'Neuen Plan erstellen',
            'add_new_item'          => 'Neuen Tourenplan erstellen',
            'new_item'              => 'Neuer Tourenplan',
            'edit_item'             => 'Tourenplan bearbeiten',
            'view_item'             => 'Tourenplan ansehen',
            'all_items'             => 'Alle Tourenpläne',
            'search_items'          => 'Tourenpläne durchsuchen',
            'not_found'             => 'Keine Tourenpläne gefunden.',
            'not_found_in_trash'    => 'Keine Tourenpläne im Papierkorb gefunden.'
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true, // Enable Frontend
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => 'edit.php?post_type=event',
            'query_var'          => true,
            'rewrite'            => array('slug' => 'tour'),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array('title'),
            'capability_type'    => 'tour',
            'map_meta_cap'       => true,
        );

        register_post_type('tmgmt_tour', $args);
    }

    public function add_meta_boxes() {
        add_meta_box(
            'tmgmt_tour_details',
            'Tourenplan Details',
            array($this, 'render_details_box'),
            'tmgmt_tour',
            'normal',
            'high'
        );
    }

    public function render_details_box($post) {
        $date = get_post_meta($post->ID, 'tmgmt_tour_date', true);
        $data = get_post_meta($post->ID, 'tmgmt_tour_data', true);
        $bus_travel = get_post_meta($post->ID, 'tmgmt_tour_bus_travel', true);
        $mode = get_post_meta($post->ID, 'tmgmt_tour_mode', true);
        if (!$mode) $mode = 'draft'; // Default to draft
        
        $warning_count = get_post_meta($post->ID, 'tmgmt_tour_warning_count', true);
        $error_count = get_post_meta($post->ID, 'tmgmt_tour_error_count', true);
        $update_required = get_post_meta($post->ID, 'tmgmt_tour_update_required', true);

        // Fetch Shuttles
        $shuttles = get_posts(array(
            'post_type' => 'tmgmt_shuttle',
            'numberposts' => -1,
            'post_status' => 'any'
        ));
        $pickup_shuttles = array();
        $dropoff_shuttles = array();
        foreach ($shuttles as $shuttle) {
            $type = get_post_meta($shuttle->ID, 'tmgmt_shuttle_type', true);
            if ($type === 'pickup') $pickup_shuttles[] = $shuttle;
            else if ($type === 'dropoff') $dropoff_shuttles[] = $shuttle;
        }
        
        $selected_pickup = get_post_meta($post->ID, 'tmgmt_tour_pickup_shuttle', true);
        $selected_dropoff = get_post_meta($post->ID, 'tmgmt_tour_dropoff_shuttle', true);
        $end_at_base = get_post_meta($post->ID, 'tmgmt_tour_end_at_base', true);

        wp_nonce_field('tmgmt_save_tour', 'tmgmt_tour_nonce');
        ?>
        <div class="tmgmt-tour-editor">
            <p>
                <label for="tmgmt_tour_date"><strong>Datum der Tour:</strong></label>
                <input type="date" id="tmgmt_tour_date" name="tmgmt_tour_date" value="<?php echo esc_attr($date); ?>">
                
                <label for="tmgmt_tour_mode" style="margin-left: 20px;"><strong>Modus:</strong></label>
                <select name="tmgmt_tour_mode" id="tmgmt_tour_mode">
                    <option value="draft" <?php selected($mode, 'draft'); ?>>Entwurfsplanung</option>
                    <option value="real" <?php selected($mode, 'real'); ?>>Echtplanung</option>
                </select>

                <label for="tmgmt_tour_bus_travel" style="margin-left: 20px;">
                    <input type="checkbox" id="tmgmt_tour_bus_travel" name="tmgmt_tour_bus_travel" value="1" <?php checked($bus_travel, '1'); ?>>
                    <strong>Reise mit Bus</strong>
                </label>

                <button type="button" class="button button-primary" id="tmgmt-calc-tour" style="margin-left: 20px;">Tour berechnen / Aktualisieren</button>
                <a href="<?php echo admin_url('admin-post.php?action=tmgmt_print_tour&tour_id=' . $post->ID); ?>" target="_blank" class="button button-secondary" style="margin-left: 10px;">Drucken (PDF)</a>
                <span id="tmgmt-calc-spinner" class="spinner" style="float:none;"></span>
            </p>

            <div style="margin-bottom: 20px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd;">
                <label for="tmgmt_tour_pickup_shuttle"><strong>Sammelfahrt (Abholung):</strong></label>
                <select name="tmgmt_tour_pickup_shuttle" id="tmgmt_tour_pickup_shuttle">
                    <option value="">- Keine -</option>
                    <?php foreach ($pickup_shuttles as $s): ?>
                        <option value="<?php echo $s->ID; ?>" <?php selected($selected_pickup, $s->ID); ?>><?php echo esc_html($s->post_title); ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="tmgmt_tour_dropoff_shuttle" style="margin-left: 20px;"><strong>Sammelfahrt (Rückfahrt):</strong></label>
                <select name="tmgmt_tour_dropoff_shuttle" id="tmgmt_tour_dropoff_shuttle">
                    <option value="">- Keine -</option>
                    <?php foreach ($dropoff_shuttles as $s): ?>
                        <option value="<?php echo $s->ID; ?>" <?php selected($selected_dropoff, $s->ID); ?>><?php echo esc_html($s->post_title); ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="tmgmt_tour_end_at_base" style="margin-left: 20px;">
                    <input type="checkbox" id="tmgmt_tour_end_at_base" name="tmgmt_tour_end_at_base" value="1" <?php checked($end_at_base, '1'); ?>>
                    <strong>Ende am Proberaum</strong>
                </label>
            </div>
            
            <?php if ($update_required): ?>
            <div class="notice notice-error inline" style="margin: 10px 0; padding: 10px; border-left-color: #d63638;">
                <p>
                    <span class="dashicons dashicons-update" style="color:#d63638; vertical-align: text-bottom;"></span>
                    <strong>Update erforderlich:</strong> Die Auftrittszeit eines Termins wurde geändert. Bitte Tour neu berechnen.
                </p>
            </div>
            <?php elseif ($warning_count > 0 || $error_count > 0): ?>
            <div class="notice notice-warning inline" style="margin: 10px 0; padding: 10px; border-left-color: <?php echo ($error_count > 0) ? '#d63638' : '#dba617'; ?>;">
                <p>
                    <strong>Status:</strong> 
                    <?php if ($error_count > 0) echo '<span style="color:#d63638">' . $error_count . ' Fehler</span> '; ?>
                    <?php if ($warning_count > 0) echo '<span style="color:#dba617">' . $warning_count . ' Warnungen</span>'; ?>
                </p>
            </div>
            <?php endif; ?>

            <div id="tmgmt-tour-results" style="margin-top: 20px;">
                <?php
                if ($data) {
                    $schedule = json_decode($data, true);
                    
                    // Map Container
                    echo '<div id="tmgmt-tour-map" style="height: 400px; width: 100%; margin-bottom: 20px; border: 1px solid #ccc;"></div>';
                    
                    $this->render_schedule_table($schedule);
                    
                    // Render Free Slot Summary
                    $free_slots = array();
                    foreach ($schedule as $item) {
                        if (isset($item['free_slot_before'])) {
                            $free_slots[] = $item;
                        }
                    }
                    
                    if (!empty($free_slots)) {
                        echo '<div class="postbox" style="margin-top: 20px;">';
                        echo '<h3 class="hndle"><span>Freie Zeiträume (Gap Analysis)</span></h3>';
                        echo '<div class="inside">';
                        echo '<ul>';
                        foreach ($free_slots as $slot) {
                            $fs = $slot['free_slot_before'];
                            echo '<li><strong>' . $fs['start'] . ' - ' . $fs['end'] . ' (' . $fs['duration'] . ' Min)</strong> vor ' . esc_html($slot['title']) . ' (' . esc_html($slot['location']) . ')</li>';
                        }
                        echo '</ul>';
                        echo '</div></div>';
                    }
                } else {
                    echo '<p>Noch keine Tour berechnet.</p>';
                }
                ?>
            </div>
            <input type="hidden" name="tmgmt_tour_data" id="tmgmt_tour_data" value="<?php echo esc_attr($data); ?>">
            <input type="hidden" id="tmgmt_tour_id" value="<?php echo $post->ID; ?>">
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Initialize Map if data exists
            var tourData = <?php echo $data ? $data : '[]'; ?>;
            if (tourData.length > 0 && typeof L !== 'undefined') {
                var map = L.map('tmgmt-tour-map');
                var bounds = [];
                
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(map);

                var counter = 1;
                tourData.forEach(function(item) {
                    var lat = null;
                    var lng = null;
                    var title = '';
                    var iconColor = 'blue';

                    if (item.type === 'start') {
                        lat = item.lat;
                        lng = item.lng;
                        title = 'Start: ' + item.location;
                        iconColor = 'green';
                    } else if (item.type === 'event') {
                        lat = item.lat;
                        lng = item.lng;
                        title = counter + '. ' + item.title + ' (' + item.location + ')';
                        counter++;
                        iconColor = 'blue';
                    } else if (item.type === 'shuttle_stop') {
                        // Shuttle stops might not have lat/lng in the schedule directly if not calculated properly?
                        // Actually they should have it if we want to map them.
                        // Let's check if we have lat/lng in the item.
                        // The current logic in Tour Manager puts lat/lng in 'start' and 'event'.
                        // For shuttle stops, we need to ensure they are there.
                        // Looking at Tour Manager, shuttle stops are added with 'location' and 'address'.
                        // We might need to fetch lat/lng for them or ensure they are passed.
                        // Wait, the shuttle stops in the schedule array come from the shuttle definition which HAS lat/lng.
                        // But let's check if they are copied to the schedule item.
                        // In Tour Manager:
                        // $stop_loc = array('lat' => ..., 'lng' => ...);
                        // $schedule[] = array('type' => 'shuttle_stop', ...);
                        // It seems lat/lng are NOT explicitly added to the 'shuttle_stop' item in the final schedule array in Tour Manager.
                        // I should fix Tour Manager to include lat/lng in shuttle_stop items first.
                    }
                    
                    if (lat && lng) {
                        var marker = L.marker([lat, lng], {
                            title: title
                        }).addTo(map);
                        
                        marker.bindPopup('<strong>' + title + '</strong>');
                        bounds.push([lat, lng]);
                    }
                });

                if (bounds.length > 0) {
                    map.fitBounds(bounds, {padding: [50, 50]});
                } else {
                    map.setView([51.1657, 10.4515], 6); // Default Germany
                }
            }

            $('#tmgmt-calc-tour').on('click', function() {
                var date = $('#tmgmt_tour_date').val();
                var mode = $('#tmgmt_tour_mode').val();
                var tour_id = $('#tmgmt_tour_id').val();
                if (!date) {
                    alert('Bitte wählen Sie ein Datum.');
                    return;
                }
                
                $('#tmgmt-calc-spinner').addClass('is-active');
                
                $.post(ajaxurl, {
                    action: 'tmgmt_calculate_tour',
                    date: date,
                    mode: mode,
                    tour_id: tour_id,
                    nonce: '<?php echo wp_create_nonce('tmgmt_backend_nonce'); ?>'
                }, function(response) {
                    $('#tmgmt-calc-spinner').removeClass('is-active');
                    if (response.success) {
                        // Reload page to show results (simplest way for now, or render via JS)
                        // For now, let's put the JSON in the hidden field and submit the form to save & render
                        $('#tmgmt_tour_data').val(JSON.stringify(response.data));
                        $('#publish').click(); // Trigger save
                    } else {
                        alert('Fehler: ' + response.data);
                    }
                });
            });
        });
        </script>
        <?php
    }

    private function render_schedule_table($schedule) {
        if (empty($schedule)) return;
        
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr><th>Zeit</th><th>Ort / Event</th><th>Aktion</th><th>Dauer/Distanz</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($schedule as $item) {
            // Render Free Slot Row if exists
            if (isset($item['free_slot_before'])) {
                $fs = $item['free_slot_before'];
                echo '<tr style="background-color: #e6fffa;">';
                echo '<td>' . esc_html($fs['start']) . ' - ' . esc_html($fs['end']) . '</td>';
                echo '<td colspan="2"><strong>Freier Zeitraum</strong></td>';
                echo '<td>' . esc_html($fs['duration']) . ' Min</td>';
                echo '</tr>';
            }

            echo '<tr>';
            
            // Time Column
            echo '<td>';
            if ($item['type'] === 'travel' || $item['type'] === 'shuttle_travel') {
                // For travel: Departure (Start of trip) -> Arrival (End of trip)
                if (isset($item['departure_time'])) echo 'Ab: ' . $item['departure_time'] . '<br>';
                if (isset($item['arrival_time'])) echo 'An: ' . $item['arrival_time'];
            } else {
                // For events/start/end: Arrival -> Show -> Departure
                if (isset($item['arrival_time'])) echo 'An: ' . $item['arrival_time'] . '<br>';
                if (isset($item['show_start'])) echo '<strong>Show: ' . $item['show_start'] . '</strong><br>';
                if (isset($item['departure_time'])) echo 'Ab: ' . $item['departure_time'];
            }
            echo '</td>';
            
            // Location Column
            echo '<td>';
            if ($item['type'] === 'start') {
                echo '<strong>Start: ' . esc_html($item['location']) . '</strong>';
                if (isset($item['meeting_time'])) {
                    echo '<br><span style="color: #2271b1; font-weight: bold;">Treffen: ' . $item['meeting_time'] . '</span>';
                }
            }
            if ($item['type'] === 'shuttle_stop') echo '<strong>Sammelfahrt: ' . esc_html($item['location']) . '</strong><br>' . esc_html($item['address']);
            if ($item['type'] === 'shuttle_travel') echo '<em>Fahrt nach ' . esc_html($item['to']) . '</em>';
            if ($item['type'] === 'event') {
                $edit_link = get_edit_post_link($item['id']);
                echo '<strong><a href="' . esc_url($edit_link) . '" target="_blank">' . esc_html($item['title']) . '</a></strong><br>' . esc_html($item['location']);
                
                if (isset($item['error'])) {
                    if ($item['error'] === 'Auftrittszeit vor Ankunft') {
                        echo '<br><span style="color: #d63638; font-weight: bold;">⚠️ ' . esc_html($item['error']) . ' (' . $item['time_diff'] . ' Min zu spät)</span>';
                    } else {
                        echo '<br><span style="color: #d63638; font-weight: bold;">⚠️ ' . esc_html($item['error']) . ' (Nur ' . $item['time_diff'] . ' Min Puffer)</span>';
                    }
                } elseif (isset($item['warning'])) {
                    echo '<br><span style="color: #d69e2e; font-weight: bold;">⚠️ ' . esc_html($item['warning']) . ' (Nur ' . $item['time_diff'] . ' Min Puffer)</span>';
                } elseif (isset($item['idle_warning'])) {
                    echo '<br><span style="color: #00a32a; font-weight: bold;">ℹ️ ' . esc_html($item['idle_warning']) . ' (' . $item['time_diff'] . ' Min Puffer)</span>';
                } else {
                    // No warning/error -> Show actual buffer
                    if (isset($item['actual_buffer'])) {
                        echo '<br><span style="color: #666;">Puffer: ' . $item['actual_buffer'] . ' Min</span>';
                    }
                }
            }
            if ($item['type'] === 'travel') echo '<em>Fahrt nach ' . esc_html($item['to']) . '</em>';
            if ($item['type'] === 'end') echo '<strong>Ende: ' . esc_html($item['location']) . '</strong>';
            echo '</td>';
            
            // Action Column
            $icon = '';
            switch ($item['type']) {
                case 'start':
                    // Traffic Lights Go (Green)
                    $icon = '<i class="fa-solid fa-traffic-light" title="Start" style="color: #00a32a; font-size: 24px;"></i>';
                    break;
                case 'shuttle_stop':
                    $icon = '<i class="fa-solid fa-people-group" title="Sammelpunkt" style="color: #2271b1; font-size: 24px;"></i>';
                    break;
                case 'shuttle_travel':
                    $icon = '<i class="fa-solid fa-van-shuttle" title="Sammelfahrt" style="color: #666; font-size: 24px;"></i>';
                    break;
                case 'event':
                    // Trumpet (Music)
                    $icon = '<i class="fa-solid fa-music" title="Event" style="color: #2271b1; font-size: 24px;"></i>';
                    break;
                case 'travel':
                    // Bus
                    $icon = '<i class="fa-solid fa-bus" title="Fahrt" style="color: #666; font-size: 24px;"></i>';
                    break;
                case 'end':
                    // Finish Flag
                    $icon = '<i class="fa-solid fa-flag-checkered" title="Ende" style="color: #d63638; font-size: 24px;"></i>';
                    break;
                default:
                    $icon = $item['type'];
            }
            echo '<td style="text-align:center; vertical-align: middle;">' . $icon . '</td>';
            
            // Duration Column
            echo '<td>';
            if (isset($item['duration'])) echo $item['duration'] . ' Min';
            if (isset($item['distance'])) echo ' (' . $item['distance'] . ' km)';
            echo '</td>';
            
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }

    public function save_meta_boxes($post_id) {
        if (!isset($_POST['tmgmt_tour_nonce']) || !wp_verify_nonce($_POST['tmgmt_tour_nonce'], 'tmgmt_save_tour')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (isset($_POST['tmgmt_tour_date'])) {
            update_post_meta($post_id, 'tmgmt_tour_date', sanitize_text_field($_POST['tmgmt_tour_date']));
            
            $date = sanitize_text_field($_POST['tmgmt_tour_date']);
            if ($date) {
                $formatted_date = date_i18n(get_option('date_format'), strtotime($date));
                remove_action('save_post', array($this, 'save_meta_boxes'));
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_title' => 'Tour am ' . $formatted_date
                ));
                add_action('save_post', array($this, 'save_meta_boxes'));
            }
        }

        // Save Bus Travel Checkbox
        $bus_travel = isset($_POST['tmgmt_tour_bus_travel']) ? '1' : '0';
        update_post_meta($post_id, 'tmgmt_tour_bus_travel', $bus_travel);

        // Save End At Base Checkbox
        $end_at_base = isset($_POST['tmgmt_tour_end_at_base']) ? '1' : '0';
        update_post_meta($post_id, 'tmgmt_tour_end_at_base', $end_at_base);

        // Save Shuttles
        if (isset($_POST['tmgmt_tour_pickup_shuttle'])) {
            update_post_meta($post_id, 'tmgmt_tour_pickup_shuttle', sanitize_text_field($_POST['tmgmt_tour_pickup_shuttle']));
        }
        if (isset($_POST['tmgmt_tour_dropoff_shuttle'])) {
            update_post_meta($post_id, 'tmgmt_tour_dropoff_shuttle', sanitize_text_field($_POST['tmgmt_tour_dropoff_shuttle']));
        }

        // Save Mode with Validation
        $mode = isset($_POST['tmgmt_tour_mode']) ? sanitize_text_field($_POST['tmgmt_tour_mode']) : 'draft';
        
        if ($mode === 'real') {
            // 1. Check for existing Real Tour on this date
            $date = sanitize_text_field($_POST['tmgmt_tour_date']);
            $existing = get_posts(array(
                'post_type' => 'tmgmt_tour',
                'post_status' => 'any',
                'meta_query' => array(
                    'relation' => 'AND',
                    array('key' => 'tmgmt_tour_date', 'value' => $date),
                    array('key' => 'tmgmt_tour_mode', 'value' => 'real')
                ),
                'exclude' => array($post_id)
            ));
            
            if (!empty($existing)) {
                $mode = 'draft';
                // TODO: Add user feedback that mode was reverted
            }
            
            // 2. Check for Errors in the Data
            if (isset($_POST['tmgmt_tour_data'])) {
                $json = wp_unslash($_POST['tmgmt_tour_data']);
                $schedule = json_decode($json, true);
                if (is_array($schedule)) {
                    foreach ($schedule as $item) {
                        if (isset($item['error'])) {
                            $mode = 'draft';
                            break;
                        }
                    }
                }
            }
        }
        update_post_meta($post_id, 'tmgmt_tour_mode', $mode);

        if (isset($_POST['tmgmt_tour_data'])) {
            // We save the raw JSON string. In a real app, we should decode and sanitize.
            $json = wp_unslash($_POST['tmgmt_tour_data']);
            update_post_meta($post_id, 'tmgmt_tour_data', $json);

            // Reset Update Required Flag
            update_post_meta($post_id, 'tmgmt_tour_update_required', false);

            // Calculate and save counts
            $schedule = json_decode($json, true);
            $warnings = 0;
            $errors = 0;
            if (is_array($schedule)) {
                foreach ($schedule as $item) {
                    if (isset($item['warning']) || isset($item['idle_warning'])) $warnings++;
                    if (isset($item['error'])) $errors++;

                    // Update Event Meta with Planned Times (ONLY IF REAL MODE)
                    if ($mode === 'real' && isset($item['type']) && $item['type'] === 'event' && isset($item['id'])) {
                        if (isset($item['arrival_time'])) {
                            update_post_meta($item['id'], '_tmgmt_event_arrival_time', $item['arrival_time']);
                        }
                        if (isset($item['departure_time'])) {
                            update_post_meta($item['id'], '_tmgmt_event_departure_time', $item['departure_time']);
                        }
                    }
                }
            }
            update_post_meta($post_id, 'tmgmt_tour_warning_count', $warnings);
            update_post_meta($post_id, 'tmgmt_tour_error_count', $errors);
        }
    }
    
    public function handle_print_tour() {
        if (!current_user_can('edit_posts')) {
            wp_die('Keine Berechtigung.');
        }

        $tour_id = isset($_GET['tour_id']) ? intval($_GET['tour_id']) : 0;
        if (!$tour_id) {
            wp_die('Ungültige Tour ID.');
        }

        $tour = get_post($tour_id);
        if (!$tour || $tour->post_type !== 'tmgmt_tour') {
            wp_die('Tour nicht gefunden.');
        }

        $json_data = get_post_meta($tour_id, 'tmgmt_tour_data', true);
        $schedule = json_decode($json_data, true);

        if (!$schedule) {
            wp_die('Keine Fahrplandaten vorhanden. Bitte erst berechnen.');
        }

        // Extract Summary Data
        $shuttle_stops = array();
        $meeting_time = '-';
        $first_departure = '-';
        $gig_count = 0;
        $start_location = '';
        $is_pickup = true;

        foreach ($schedule as $item) {
            if ($item['type'] === 'start') {
                $is_pickup = false;
                $meeting_time = isset($item['meeting_time']) ? $item['meeting_time'] : '-';
                $first_departure = isset($item['departure_time']) ? $item['departure_time'] : '-';
                $start_location = isset($item['location']) ? $item['location'] : 'Start';
            } elseif ($item['type'] === 'shuttle_stop' && $is_pickup) {
                $shuttle_stops[] = $item;
            } elseif ($item['type'] === 'event') {
                $gig_count++;
            }
        }

        // HTML Output
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Tourplan: <?php echo esc_html($tour->post_title); ?></title>
            <style>
                body { font-family: sans-serif; font-size: 12px; }
                h1 { font-size: 18px; margin-bottom: 10px; }
                .summary-box { border: 1px solid #000; padding: 10px; margin-bottom: 20px; background-color: #f9f9f9; }
                .summary-row { margin-bottom: 5px; }
                .summary-label { font-weight: bold; display: inline-block; width: 200px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }
                th { background-color: #eee; }
                .type-event { background-color: #e6f7ff; }
                .type-travel { color: #666; font-style: italic; }
                .type-shuttle { background-color: #fff0f0; }
                .type-start { background-color: #e8f5e9; }
                @media print {
                    .no-print { display: none; }
                    body { font-size: 11px; }
                    .type-event { background-color: #f0f8ff !important; -webkit-print-color-adjust: exact; }
                    .type-shuttle { background-color: #fff5f5 !important; -webkit-print-color-adjust: exact; }
                    .type-start { background-color: #f1f8e9 !important; -webkit-print-color-adjust: exact; }
                }
            </style>
        </head>
        <body>
            <div class="no-print" style="margin-bottom: 20px; padding: 10px; background: #eee; border-bottom: 1px solid #ccc;">
                <button onclick="window.print();" style="padding: 8px 16px; font-size: 14px; cursor: pointer; background: #0073aa; color: #fff; border: none; border-radius: 4px;">Drucken / PDF speichern</button>
                <button onclick="window.close();" style="padding: 8px 16px; font-size: 14px; cursor: pointer; margin-left: 10px;">Schließen</button>
            </div>

            <h1><?php echo esc_html($tour->post_title); ?></h1>
            <p><strong>Datum:</strong> <?php echo date('d.m.Y', strtotime(get_post_meta($tour_id, 'tmgmt_tour_date', true))); ?></p>

            <div class="summary-box">
                <h3>Übersicht</h3>
                <?php if (!empty($shuttle_stops)): ?>
                    <div style="margin-bottom: 15px;">
                        <strong>Abfahrtszeiten Shuttle:</strong>
                        <ul style="margin: 5px 0 0 20px; padding: 0;">
                        <?php foreach ($shuttle_stops as $stop): ?>
                            <li><?php echo esc_html($stop['departure_time']); ?> Uhr - <?php echo esc_html($stop['location']); ?></li>
                        <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="summary-row">
                    <span class="summary-label">Treffzeitpunkt (<?php echo esc_html($start_location); ?>):</span>
                    <span><?php echo esc_html($meeting_time); ?> Uhr</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Abfahrt zum ersten Event:</span>
                    <span><?php echo esc_html($first_departure); ?> Uhr</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Anzahl Auftritte:</span>
                    <span><?php echo intval($gig_count); ?></span>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th style="width: 120px;">Zeit</th>
                        <th style="width: 150px;">Aktivität</th>
                        <th>Ort / Details</th>
                        <th style="width: 200px;">Kontakte</th>
                        <th style="width: 100px;">Dauer</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($schedule as $item): ?>
                        <?php 
                            $row_class = '';
                            if ($item['type'] === 'event') $row_class = 'type-event';
                            elseif (strpos($item['type'], 'travel') !== false) $row_class = 'type-travel';
                            elseif (strpos($item['type'], 'shuttle') !== false) $row_class = 'type-shuttle';
                            elseif ($item['type'] === 'start') $row_class = 'type-start';
                            
                            $time_col = '';
                            if (isset($item['arrival_time']) && isset($item['departure_time'])) {
                                $time_col = $item['arrival_time'] . ' - ' . $item['departure_time'];
                            } elseif (isset($item['arrival_time'])) {
                                $time_col = 'Ank: ' . $item['arrival_time'];
                            } elseif (isset($item['departure_time'])) {
                                $time_col = 'Abf: ' . $item['departure_time'];
                            }
                        ?>
                        <tr class="<?php echo $row_class; ?>">
                            <td style="white-space: nowrap; font-weight: bold;"><?php echo $time_col; ?></td>
                            <td>
                                <?php 
                                    if ($item['type'] === 'event') echo '<strong>Auftritt</strong>';
                                    elseif ($item['type'] === 'travel') echo 'Fahrt';
                                    elseif ($item['type'] === 'shuttle_travel') echo 'Shuttle Fahrt';
                                    elseif ($item['type'] === 'shuttle_stop') echo 'Shuttle Stop';
                                    elseif ($item['type'] === 'start') echo 'Laden / Start';
                                    elseif ($item['type'] === 'end') echo 'Ende';
                                    else echo esc_html($item['type']);
                                ?>
                            </td>
                            <td>
                                <?php 
                                    if ($item['type'] === 'event') {
                                        if (isset($item['title'])) echo '<strong>' . esc_html($item['title']) . '</strong>';
                                        if (isset($item['organizer']) && $item['organizer']) echo ' (' . esc_html($item['organizer']) . ')';
                                        echo '<br>';
                                        
                                        if (isset($item['address'])) echo '<small>' . esc_html($item['address']) . '</small>';
                                        elseif (isset($item['location'])) echo '<small>' . esc_html($item['location']) . '</small>';
                                    } else {
                                        if (isset($item['location'])) echo '<strong>' . esc_html($item['location']) . '</strong><br>';
                                        if (isset($item['address'])) echo '<small>' . esc_html($item['address']) . '</small>';
                                    }
                                    
                                    if (isset($item['from']) && isset($item['to'])) echo esc_html($item['from']) . ' &rarr; ' . esc_html($item['to']);
                                    if (isset($item['distance'])) echo ' (' . round($item['distance'], 1) . ' km)';
                                ?>
                            </td>
                            <td style="font-size: 11px;">
                                <?php if ($item['type'] === 'event'): ?>
                                    <?php if (!empty($item['contact_name']) || !empty($item['contact_phone'])): ?>
                                        <div style="margin-bottom: 5px;">
                                            <strong>Vertrag:</strong><br>
                                            <?php if (!empty($item['contact_name'])) echo esc_html($item['contact_name']) . '<br>'; ?>
                                            <?php if (!empty($item['contact_phone'])) echo esc_html($item['contact_phone']); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($item['program_name']) || !empty($item['program_phone'])): ?>
                                        <div>
                                            <strong>Programm:</strong><br>
                                            <?php if (!empty($item['program_name'])) echo esc_html($item['program_name']) . '<br>'; ?>
                                            <?php if (!empty($item['program_phone'])) echo esc_html($item['program_phone']); ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                    if (isset($item['duration'])) echo $item['duration'] . ' min';
                                    if (isset($item['loading_time'])) echo '<br>Laden: ' . $item['loading_time'] . ' min';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </body>
        </html>
        <?php
        exit;
    }
}
