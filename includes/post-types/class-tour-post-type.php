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
            'supports'           => array('title')
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
        
        $warning_count = get_post_meta($post->ID, 'tmgmt_tour_warning_count', true);
        $error_count = get_post_meta($post->ID, 'tmgmt_tour_error_count', true);
        $update_required = get_post_meta($post->ID, 'tmgmt_tour_update_required', true);

        wp_nonce_field('tmgmt_save_tour', 'tmgmt_tour_nonce');
        ?>
        <div class="tmgmt-tour-editor">
            <p>
                <label for="tmgmt_tour_date"><strong>Datum der Tour:</strong></label>
                <input type="date" id="tmgmt_tour_date" name="tmgmt_tour_date" value="<?php echo esc_attr($date); ?>">
                
                <label for="tmgmt_tour_bus_travel" style="margin-left: 20px;">
                    <input type="checkbox" id="tmgmt_tour_bus_travel" name="tmgmt_tour_bus_travel" value="1" <?php checked($bus_travel, '1'); ?>>
                    <strong>Reise mit Bus</strong>
                </label>

                <button type="button" class="button button-primary" id="tmgmt-calc-tour" style="margin-left: 20px;">Tour berechnen / Aktualisieren</button>
                <span id="tmgmt-calc-spinner" class="spinner" style="float:none;"></span>
            </p>
            
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
                } else {
                    echo '<p>Noch keine Tour berechnet.</p>';
                }
                ?>
            </div>
            <input type="hidden" name="tmgmt_tour_data" id="tmgmt_tour_data" value="<?php echo esc_attr($data); ?>">
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#tmgmt-calc-tour').on('click', function() {
                var date = $('#tmgmt_tour_date').val();
                if (!date) {
                    alert('Bitte wählen Sie ein Datum.');
                    return;
                }
                
                $('#tmgmt-calc-spinner').addClass('is-active');
                
                $.post(ajaxurl, {
                    action: 'tmgmt_calculate_tour',
                    date: date,
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
            echo '<tr>';
            
            // Time Column
            echo '<td>';
            if ($item['type'] === 'travel') {
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
            if ($item['type'] === 'start') echo '<strong>Start: ' . esc_html($item['location']) . '</strong>';
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
            echo '<td>' . $item['type'] . '</td>';
            
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

                    // Update Event Meta with Planned Times
                    if (isset($item['type']) && $item['type'] === 'event' && isset($item['id'])) {
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
