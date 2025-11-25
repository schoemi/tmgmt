<?php

class TMGMT_Tour_Post_Type {

    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
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
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => 'edit.php?post_type=event',
            'query_var'          => true,
            'rewrite'            => array('slug' => 'tour'),
            'capability_type'    => 'post',
            'has_archive'        => false,
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
}
