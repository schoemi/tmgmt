<?php

class TMGMT_Tour_Post_Type {

    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
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
        
        wp_nonce_field('tmgmt_save_tour', 'tmgmt_tour_nonce');
        ?>
        <div class="tmgmt-tour-editor">
            <p>
                <label for="tmgmt_tour_date"><strong>Datum der Tour:</strong></label>
                <input type="date" id="tmgmt_tour_date" name="tmgmt_tour_date" value="<?php echo esc_attr($date); ?>">
                <button type="button" class="button button-primary" id="tmgmt-calc-tour">Tour berechnen / Aktualisieren</button>
                <span id="tmgmt-calc-spinner" class="spinner" style="float:none;"></span>
            </p>
            
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
        echo '<thead><tr><th>Zeit</th><th>Ort / Event</th><th>Aktion</th><th>Dauer/Distanz</th><th>Puffer</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($schedule as $item) {
            echo '<tr>';
            
            // Time Column
            echo '<td>';
            if (isset($item['arrival_time'])) echo 'An: ' . $item['arrival_time'] . '<br>';
            if (isset($item['show_start'])) echo '<strong>Show: ' . $item['show_start'] . '</strong><br>';
            if (isset($item['departure_time'])) echo 'Ab: ' . $item['departure_time'];
            echo '</td>';
            
            // Location Column
            echo '<td>';
            if ($item['type'] === 'start') echo '<strong>Start: ' . esc_html($item['location']) . '</strong>';
            if ($item['type'] === 'event') echo '<strong>' . esc_html($item['title']) . '</strong><br>' . esc_html($item['location']);
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
            
            // Buffer Column
            echo '<td>';
            if (isset($item['buffer_arrival'])) echo 'An: ' . $item['buffer_arrival'] . ' Min<br>';
            if (isset($item['buffer_departure'])) echo 'Ab: ' . $item['buffer_departure'] . ' Min';
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

        if (isset($_POST['tmgmt_tour_data'])) {
            // We save the raw JSON string. In a real app, we should decode and sanitize.
            update_post_meta($post_id, 'tmgmt_tour_data', wp_unslash($_POST['tmgmt_tour_data']));
        }
    }
}
