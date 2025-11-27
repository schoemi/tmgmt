<?php

class TMGMT_Shuttle_Post_Type {

    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
    }

    public function register_post_type() {
        $labels = array(
            'name'                  => 'Sammelfahrten',
            'singular_name'         => 'Sammelfahrt',
            'menu_name'             => 'Sammelfahrten',
            'name_admin_bar'        => 'Sammelfahrt',
            'add_new'               => 'Neue Sammelfahrt',
            'add_new_item'          => 'Neue Sammelfahrt erstellen',
            'new_item'              => 'Neue Sammelfahrt',
            'edit_item'             => 'Sammelfahrt bearbeiten',
            'view_item'             => 'Sammelfahrt ansehen',
            'all_items'             => 'Alle Sammelfahrten',
            'search_items'          => 'Sammelfahrten durchsuchen',
            'not_found'             => 'Keine Sammelfahrten gefunden.',
            'not_found_in_trash'    => 'Keine Sammelfahrten im Papierkorb gefunden.'
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => 'edit.php?post_type=event',
            'query_var'          => true,
            'rewrite'            => array('slug' => 'shuttle'),
            'capability_type'    => 'post', // We can refine this later with 'shuttle' cap
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array('title')
        );

        register_post_type('tmgmt_shuttle', $args);
    }

    public function add_meta_boxes() {
        add_meta_box(
            'tmgmt_shuttle_details',
            'Details zur Sammelfahrt',
            array($this, 'render_details_box'),
            'tmgmt_shuttle',
            'normal',
            'high'
        );
    }

    public function render_details_box($post) {
        wp_nonce_field('tmgmt_save_shuttle', 'tmgmt_shuttle_nonce');

        $type = get_post_meta($post->ID, 'tmgmt_shuttle_type', true);
        $stops_json = get_post_meta($post->ID, 'tmgmt_shuttle_stops', true);
        $stops = json_decode($stops_json, true);
        if (!is_array($stops)) $stops = array();
        ?>
        <div class="tmgmt-shuttle-editor">
            <p>
                <label for="tmgmt_shuttle_type"><strong>Art der Fahrt:</strong></label>
                <select name="tmgmt_shuttle_type" id="tmgmt_shuttle_type">
                    <option value="pickup" <?php selected($type, 'pickup'); ?>>Einsammeln (Vor der Tour)</option>
                    <option value="dropoff" <?php selected($type, 'dropoff'); ?>>Wegbringen (Nach der Tour)</option>
                </select>
            </p>

            <h3>Haltestellen</h3>
            <p class="description">Definiere die Haltestellen in der Reihenfolge der Anfahrt.</p>
            
            <table class="widefat" id="tmgmt-stops-table">
                <thead>
                    <tr>
                        <th style="width: 20px;">#</th>
                        <th>Name / Bezeichnung</th>
                        <th>Adresse (Straße, Nr, PLZ, Ort)</th>
                        <th style="width: 150px;">Geodaten (Lat / Lng)</th>
                        <th style="width: 50px;"></th>
                    </tr>
                </thead>
                <tbody id="tmgmt-stops-list">
                    <?php 
                    if (!empty($stops)) {
                        foreach ($stops as $index => $stop) {
                            $this->render_stop_row($index, $stop);
                        }
                    }
                    ?>
                </tbody>
            </table>
            
            <p>
                <button type="button" class="button" id="tmgmt-add-stop">Haltestelle hinzufügen</button>
            </p>
        </div>

                <script type="text/template" id="tmpl-tmgmt-stop-row">
            <?php $this->render_stop_row('{{INDEX}}', array('name' => '', 'address' => '', 'lat' => '', 'lng' => '')); ?>
        </script>

        <script>
        jQuery(document).ready(function($) {
            var list = $('#tmgmt-stops-list');
            var template = $('#tmpl-tmgmt-stop-row').html();
            
            // Re-index rows helper
            function reindex() {
                list.find('tr').each(function(i) {
                    $(this).find('.stop-index').text(i + 1);
                });
            }

            $('#tmgmt-add-stop').on('click', function() {
                var index = list.find('tr').length;
                var rowHtml = template.replace(/{{INDEX}}/g, index);
                list.append(rowHtml);
                reindex();
            });

            list.on('click', '.remove-stop', function() {
                $(this).closest('tr').remove();
                reindex();
            });

            list.on('click', '.resolve-geo', function() {
                var row = $(this).closest('tr');
                var address = row.find('.stop-address').val();
                var latInput = row.find('input[name="tmgmt_stops_lat[]"]');
                var lngInput = row.find('input[name="tmgmt_stops_lng[]"]');
                
                if (!address) {
                    Swal.fire('Fehlende Angabe', 'Bitte erst eine Adresse eingeben.', 'warning');
                    return;
                }
                
                var btn = $(this);
                btn.prop('disabled', true);
                
                $.getJSON('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(address), function(data) {
                    btn.prop('disabled', false);
                    if (data && data.length > 0) {
                        latInput.val(data[0].lat);
                        lngInput.val(data[0].lon);
                    } else {
                        Swal.fire('Nicht gefunden', 'Adresse nicht gefunden.', 'error');
                    }
                }).fail(function() {
                    btn.prop('disabled', false);
                    Swal.fire('Fehler', 'Fehler beim Abrufen der Geodaten.', 'error');
                });
            });
        });
        </script>
        <?php
    }

    private function render_stop_row($index, $stop) {
        // Using array syntax for inputs so they are posted as arrays
        $lat = isset($stop['lat']) ? $stop['lat'] : '';
        $lng = isset($stop['lng']) ? $stop['lng'] : '';
        ?>
        <tr>
            <td class="stop-index" style="vertical-align: middle;"><?php echo is_numeric($index) ? $index + 1 : '#'; ?></td>
            <td>
                <input type="text" name="tmgmt_stops_name[]" value="<?php echo esc_attr($stop['name']); ?>" style="width: 100%;" placeholder="z.B. Bahnhof oder Name">
            </td>
            <td>
                <div style="display: flex; gap: 5px; align-items: center;">
                    <input type="text" name="tmgmt_stops_address[]" class="stop-address" value="<?php echo esc_attr($stop['address']); ?>" style="width: 100%;" placeholder="Musterstraße 1, 12345 Musterstadt">
                    <button type="button" class="button button-small resolve-geo" title="Geodaten abrufen"><span class="dashicons dashicons-location"></span></button>
                </div>
            </td>
            <td>
                <div style="display: flex; gap: 5px;">
                    <input type="text" name="tmgmt_stops_lat[]" value="<?php echo esc_attr($lat); ?>" style="width: 50%;" placeholder="Lat">
                    <input type="text" name="tmgmt_stops_lng[]" value="<?php echo esc_attr($lng); ?>" style="width: 50%;" placeholder="Lng">
                </div>
            </td>
            <td>
                <button type="button" class="button-link remove-stop" style="color: #d63638;"><span class="dashicons dashicons-trash"></span></button>
            </td>
        </tr>
        <?php
    }

    public function save_meta_boxes($post_id) {
        if (!isset($_POST['tmgmt_shuttle_nonce']) || !wp_verify_nonce($_POST['tmgmt_shuttle_nonce'], 'tmgmt_save_shuttle')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (isset($_POST['tmgmt_shuttle_type'])) {
            update_post_meta($post_id, 'tmgmt_shuttle_type', sanitize_text_field($_POST['tmgmt_shuttle_type']));
        }

        if (isset($_POST['tmgmt_stops_name'])) {
            $names = $_POST['tmgmt_stops_name'];
            $addresses = $_POST['tmgmt_stops_address'];
            $lats = isset($_POST['tmgmt_stops_lat']) ? $_POST['tmgmt_stops_lat'] : array();
            $lngs = isset($_POST['tmgmt_stops_lng']) ? $_POST['tmgmt_stops_lng'] : array();
            
            $stops = array();
            for ($i = 0; $i < count($names); $i++) {
                if (!empty($names[$i])) {
                    $stops[] = array(
                        'name' => sanitize_text_field($names[$i]),
                        'address' => sanitize_text_field($addresses[$i]),
                        'lat' => isset($lats[$i]) ? sanitize_text_field($lats[$i]) : '',
                        'lng' => isset($lngs[$i]) ? sanitize_text_field($lngs[$i]) : ''
                    );
                }
            }
            update_post_meta($post_id, 'tmgmt_shuttle_stops', json_encode($stops));
        } else {
            update_post_meta($post_id, 'tmgmt_shuttle_stops', json_encode(array()));
        }
    }
}
