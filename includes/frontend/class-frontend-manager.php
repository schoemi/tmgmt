<?php

class TMGMT_Frontend_Manager {

    public function __construct() {
        add_shortcode('tmgmt_tour_overview', array($this, 'render_tour_overview'));
        add_filter('the_content', array($this, 'render_single_tour_content'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('wp_ajax_tmgmt_save_tour_settings', array($this, 'ajax_save_tour_settings'));
    }

    public function enqueue_frontend_scripts() {
        global $post;
        if (is_a($post, 'WP_Post') && ($post->post_type === 'tmgmt_tour' || has_shortcode($post->post_content, 'tmgmt_tour_overview'))) {
            wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css');
            wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
            wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true);
            
            wp_enqueue_script(
                'tmgmt-frontend-script',
                TMGMT_PLUGIN_URL . 'assets/js/frontend-script.js',
                array('jquery', 'leaflet-js'),
                TMGMT_VERSION,
                true
            );

            wp_localize_script('tmgmt-frontend-script', 'tmgmt_vars', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('tmgmt_frontend_nonce')
            ));

            // Add some basic styles for the frontend interface
            wp_add_inline_style('font-awesome', '
                .tmgmt-frontend-container { max-width: 1200px; margin: 0 auto; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; }
                .tmgmt-tour-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                .tmgmt-tour-table th, .tmgmt-tour-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                .tmgmt-tour-table th { background-color: #f2f2f2; }
                .tmgmt-controls { background: #fff; padding: 20px; border: 1px solid #dfe1e6; margin-bottom: 20px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
                .tmgmt-controls label { display: inline-block; margin-right: 10px; font-weight: 600; color: #172b4d; font-size: 14px; }
                .tmgmt-controls select, .tmgmt-controls input[type="date"] { padding: 8px; border: 1px solid #dfe1e6; border-radius: 4px; font-size: 14px; color: #172b4d; background-color: #fff; }
                .tmgmt-controls select:focus, .tmgmt-controls input[type="date"]:focus { border-color: #0079bf; outline: none; }
                .tmgmt-button { background-color: #0079bf; color: #fff; padding: 8px 16px; border: none; border-radius: 3px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 14px; font-weight: 500; transition: background 0.1s ease; }
                .tmgmt-button:hover { background-color: #026aa7; color: #fff; }
                .tmgmt-button.secondary { background-color: #f4f5f7; color: #172b4d; border: none; }
                .tmgmt-button.secondary:hover { background-color: #ebecf0; color: #091e42; }
                .tmgmt-status-ok { color: #00a32a; }
                .tmgmt-status-warning { color: #dba617; }
                .tmgmt-status-error { color: #d63638; }
                #tmgmt-tour-map { height: 400px; width: 100%; margin-bottom: 20px; border: 1px solid #ccc; }
                .spinner { display: inline-block; width: 20px; height: 20px; border: 3px solid rgba(0,0,0,0.3); border-radius: 50%; border-top-color: #000; animation: spin 1s ease-in-out infinite; vertical-align: middle; margin-left: 10px; visibility: hidden; }
                .spinner.is-active { visibility: visible; }
                @keyframes spin { to { transform: rotate(360deg); } }
                .tmgmt-row { display: flex; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 15px; }
                .tmgmt-field-group { display: flex; align-items: center; }
            ');
        }
    }

    public function render_tour_overview($atts) {
        if (!current_user_can('edit_posts')) {
            return '<p>Keine Berechtigung.</p>';
        }

        $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
        $args = array(
            'post_type' => 'tmgmt_tour',
            'post_status' => 'any',
            'posts_per_page' => 20,
            'paged' => $paged,
            'orderby' => 'meta_value',
            'meta_key' => 'tmgmt_tour_date',
            'order' => 'DESC'
        );

        $query = new WP_Query($args);
        
        ob_start();
        echo '<div class="tmgmt-frontend-container">';
        echo '<h2>Tourenplanung Übersicht</h2>';
        
        if ($query->have_posts()) {
            echo '<table class="tmgmt-tour-table">';
            echo '<thead><tr><th>Datum</th><th>Titel</th><th>Status</th><th>Aktionen</th></tr></thead>';
            echo '<tbody>';
            while ($query->have_posts()) {
                $query->the_post();
                $tour_id = get_the_ID();
                $date = get_post_meta($tour_id, 'tmgmt_tour_date', true);
                $formatted_date = $date ? date('d.m.Y', strtotime($date)) : '-';
                
                $update_required = get_post_meta($tour_id, 'tmgmt_tour_update_required', true);
                $error_count = (int)get_post_meta($tour_id, 'tmgmt_tour_error_count', true);
                $warning_count = (int)get_post_meta($tour_id, 'tmgmt_tour_warning_count', true);
                
                $status_html = '<span class="tmgmt-status-ok"><i class="fas fa-check-circle"></i> OK</span>';
                if ($update_required) {
                    $status_html = '<span class="tmgmt-status-error"><i class="fas fa-sync"></i> Update erforderlich</span>';
                } elseif ($error_count > 0) {
                    $status_html = '<span class="tmgmt-status-error"><i class="fas fa-exclamation-circle"></i> ' . $error_count . ' Fehler</span>';
                } elseif ($warning_count > 0) {
                    $status_html = '<span class="tmgmt-status-warning"><i class="fas fa-exclamation-triangle"></i> ' . $warning_count . ' Warnungen</span>';
                }

                echo '<tr>';
                echo '<td>' . esc_html($formatted_date) . '</td>';
                echo '<td><strong><a href="' . get_permalink() . '">' . get_the_title() . '</a></strong></td>';
                echo '<td>' . $status_html . '</td>';
                echo '<td><a href="' . get_permalink() . '" class="tmgmt-button secondary">Planen</a></td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
            
            // Pagination
            echo '<div class="tmgmt-pagination">';
            echo paginate_links(array(
                'total' => $query->max_num_pages,
                'current' => $paged
            ));
            echo '</div>';
        } else {
            echo '<p>Keine Touren gefunden.</p>';
        }
        echo '</div>';
        wp_reset_postdata();
        
        return ob_get_clean();
    }

    public function render_single_tour_content($content) {
        if (is_singular('tmgmt_tour') && in_the_loop() && is_main_query()) {
            if (!current_user_can('edit_posts')) {
                return '<p>Keine Berechtigung.</p>';
            }

            global $post;
            $tour_id = $post->ID;
            
            // Get Data
            $date = get_post_meta($tour_id, 'tmgmt_tour_date', true);
            $mode = get_post_meta($tour_id, 'tmgmt_tour_mode', true);
            $bus_travel = get_post_meta($tour_id, 'tmgmt_tour_bus_travel', true);
            $end_at_base = get_post_meta($tour_id, 'tmgmt_tour_end_at_base', true);
            $pickup_shuttle = get_post_meta($tour_id, 'tmgmt_tour_pickup_shuttle', true);
            $dropoff_shuttle = get_post_meta($tour_id, 'tmgmt_tour_dropoff_shuttle', true);
            $data_json = get_post_meta($tour_id, 'tmgmt_tour_data', true);
            
            // Get Shuttles
            $shuttles = get_posts(array('post_type' => 'tmgmt_shuttle', 'numberposts' => -1));
            $pickup_shuttles = array();
            $dropoff_shuttles = array();
            foreach ($shuttles as $s) {
                $type = get_post_meta($s->ID, 'tmgmt_shuttle_type', true);
                if ($type === 'pickup') $pickup_shuttles[] = $s;
                else $dropoff_shuttles[] = $s;
            }

            ob_start();
            ?>
            <div class="tmgmt-frontend-container">
                <div class="tmgmt-controls">
                    <form id="tmgmt-frontend-form">
                        <input type="hidden" id="tmgmt_tour_id" value="<?php echo $tour_id; ?>">
                        
                        <div class="tmgmt-row">
                            <div class="tmgmt-field-group">
                                <label>Datum:</label>
                                <input type="date" id="tmgmt_tour_date" value="<?php echo esc_attr($date); ?>">
                            </div>
                            
                            <div class="tmgmt-field-group">
                                <label>Modus:</label>
                                <select id="tmgmt_tour_mode">
                                    <option value="draft" <?php selected($mode, 'draft'); ?>>Entwurf</option>
                                    <option value="real" <?php selected($mode, 'real'); ?>>Echtplanung</option>
                                </select>
                            </div>
                        </div>

                        <div class="tmgmt-row">
                            <div class="tmgmt-field-group">
                                <input type="checkbox" id="tmgmt_tour_bus_travel" <?php checked($bus_travel, '1'); ?> style="margin-right: 8px;">
                                <label for="tmgmt_tour_bus_travel" style="margin: 0;">Busfahrt (Faktor 1.5)</label>
                            </div>
                            
                            <div class="tmgmt-field-group">
                                <input type="checkbox" id="tmgmt_tour_end_at_base" <?php checked($end_at_base, '1'); ?> style="margin-right: 8px;">
                                <label for="tmgmt_tour_end_at_base" style="margin: 0;">Ende am Proberaum</label>
                            </div>
                        </div>

                        <div class="tmgmt-row">
                            <div class="tmgmt-field-group">
                                <label>Shuttle (Abholung):</label>
                                <select id="tmgmt_tour_pickup_shuttle">
                                    <option value="">- Keine -</option>
                                    <?php foreach ($pickup_shuttles as $s): ?>
                                        <option value="<?php echo $s->ID; ?>" <?php selected($pickup_shuttle, $s->ID); ?>><?php echo esc_html($s->post_title); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="tmgmt-field-group">
                                <label>Shuttle (Rückfahrt):</label>
                                <select id="tmgmt_tour_dropoff_shuttle">
                                    <option value="">- Keine -</option>
                                    <?php foreach ($dropoff_shuttles as $s): ?>
                                        <option value="<?php echo $s->ID; ?>" <?php selected($dropoff_shuttle, $s->ID); ?>><?php echo esc_html($s->post_title); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="tmgmt-row" style="margin-bottom: 0; margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">
                            <button type="button" id="tmgmt-save-settings" class="tmgmt-button secondary">Einstellungen speichern</button>
                            <button type="button" id="tmgmt-calc-tour" class="tmgmt-button">Tour berechnen</button>
                            <a href="<?php echo admin_url('admin-post.php?action=tmgmt_print_tour&tour_id=' . $tour_id); ?>" target="_blank" class="tmgmt-button secondary">Drucken (PDF)</a>
                            <span id="tmgmt-spinner" class="spinner"></span>
                        </div>
                    </form>
                </div>

                <div id="tmgmt-tour-results">
                    <?php if ($data_json): ?>
                        <div id="tmgmt-tour-map"></div>
                        <div id="tmgmt-schedule-container">
                            <?php 
                                // We reuse the backend table renderer if possible, or duplicate logic.
                                // Since render_schedule_table is in TMGMT_Tour_Post_Type, we can instantiate it or make it static.
                                // For now, let's duplicate the table logic for frontend to have full control over styling.
                                $schedule = json_decode($data_json, true);
                                $this->render_frontend_schedule($schedule);
                            ?>
                        </div>
                    <?php else: ?>
                        <p>Noch keine Tour berechnet.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <script>
                // Pass initial data to JS
                var tmgmtTourData = <?php echo $data_json ? $data_json : '[]'; ?>;
            </script>
            <?php
            return ob_get_clean();
        }
        return $content;
    }

    private function render_frontend_schedule($schedule) {
        if (!is_array($schedule)) return;
        
        echo '<table class="tmgmt-tour-table">';
        echo '<thead><tr><th>Zeit</th><th>Aktivität</th><th>Ort / Details</th><th>Kontakte</th><th>Dauer</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($schedule as $item) {
            $row_style = '';
            if ($item['type'] === 'event') $row_style = 'background-color: #e6f7ff;';
            elseif (strpos($item['type'], 'travel') !== false) $row_style = 'color: #666; font-style: italic;';
            elseif (strpos($item['type'], 'shuttle') !== false) $row_style = 'background-color: #fff0f0;';
            elseif ($item['type'] === 'start') $row_style = 'background-color: #e8f5e9;';

            $time_col = '';
            if (isset($item['arrival_time']) && isset($item['departure_time'])) {
                $time_col = $item['arrival_time'] . ' - ' . $item['departure_time'];
            } elseif (isset($item['arrival_time'])) {
                $time_col = 'Ank: ' . $item['arrival_time'];
            } elseif (isset($item['departure_time'])) {
                $time_col = 'Abf: ' . $item['departure_time'];
            }

            echo '<tr style="' . $row_style . '">';
            echo '<td style="white-space: nowrap; font-weight: bold;">' . $time_col . '</td>';
            
            echo '<td>';
            if ($item['type'] === 'event') {
                echo '<strong>Auftritt</strong>';
                if (isset($item['show_start']) && !empty($item['show_start'])) {
                    echo '<br><span style="font-size: 0.9em; color: #555;">' . esc_html($item['show_start']) . ' Uhr</span>';
                }
            }
            elseif ($item['type'] === 'travel') echo 'Fahrt';
            elseif ($item['type'] === 'shuttle_travel') echo 'Shuttle Fahrt';
            elseif ($item['type'] === 'shuttle_stop') echo 'Shuttle Stop';
            elseif ($item['type'] === 'start') echo 'Laden / Start';
            elseif ($item['type'] === 'end') echo 'Ende';
            else echo esc_html($item['type']);
            echo '</td>';

            echo '<td>';
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
            
            // Warnings/Errors
            if (isset($item['error'])) echo '<br><span class="tmgmt-status-error"><i class="fas fa-exclamation-circle"></i> ' . esc_html($item['error']) . '</span>';
            if (isset($item['warning'])) echo '<br><span class="tmgmt-status-warning"><i class="fas fa-exclamation-triangle"></i> ' . esc_html($item['warning']) . '</span>';
            echo '</td>';

            echo '<td style="font-size: 0.9em;">';
            if ($item['type'] === 'event') {
                if (!empty($item['contact_name']) || !empty($item['contact_phone'])) {
                    echo '<strong>Vertrag:</strong><br>';
                    if (!empty($item['contact_name'])) echo esc_html($item['contact_name']) . '<br>';
                    if (!empty($item['contact_phone'])) echo esc_html($item['contact_phone']);
                }
                if (!empty($item['program_name']) || !empty($item['program_phone'])) {
                    echo '<br><strong>Programm:</strong><br>';
                    if (!empty($item['program_name'])) echo esc_html($item['program_name']) . '<br>';
                    if (!empty($item['program_phone'])) echo esc_html($item['program_phone']);
                }
            }
            echo '</td>';

            echo '<td>';
            if (isset($item['duration'])) echo $item['duration'] . ' min';
            if (isset($item['loading_time'])) echo '<br>Laden: ' . $item['loading_time'] . ' min';
            echo '</td>';

            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    public function ajax_save_tour_settings() {
        check_ajax_referer('tmgmt_frontend_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Keine Berechtigung.');
        }

        $tour_id = isset($_POST['tour_id']) ? intval($_POST['tour_id']) : 0;
        if (!$tour_id) wp_send_json_error('Invalid ID');

        if (isset($_POST['date'])) update_post_meta($tour_id, 'tmgmt_tour_date', sanitize_text_field($_POST['date']));
        if (isset($_POST['mode'])) update_post_meta($tour_id, 'tmgmt_tour_mode', sanitize_text_field($_POST['mode']));
        
        $bus_travel = isset($_POST['bus_travel']) && $_POST['bus_travel'] === 'true' ? '1' : '0';
        update_post_meta($tour_id, 'tmgmt_tour_bus_travel', $bus_travel);
        
        $end_at_base = isset($_POST['end_at_base']) && $_POST['end_at_base'] === 'true' ? '1' : '0';
        update_post_meta($tour_id, 'tmgmt_tour_end_at_base', $end_at_base);
        
        if (isset($_POST['pickup_shuttle'])) update_post_meta($tour_id, 'tmgmt_tour_pickup_shuttle', sanitize_text_field($_POST['pickup_shuttle']));
        if (isset($_POST['dropoff_shuttle'])) update_post_meta($tour_id, 'tmgmt_tour_dropoff_shuttle', sanitize_text_field($_POST['dropoff_shuttle']));

        // Also update title if date changed
        if (isset($_POST['date'])) {
            $formatted_date = date_i18n(get_option('date_format'), strtotime($_POST['date']));
            wp_update_post(array(
                'ID' => $tour_id,
                'post_title' => 'Tour am ' . $formatted_date
            ));
        }

        wp_send_json_success('Gespeichert.');
    }
}
