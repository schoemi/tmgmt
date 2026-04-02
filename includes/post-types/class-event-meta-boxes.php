<?php

class TMGMT_Event_Meta_Boxes {

    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
        add_action('wp_ajax_tmgmt_get_veranstalter_details', array($this, 'ajax_get_veranstalter_details'));
    }

    /**
     * AJAX handler: Returns Veranstalter details (address, contacts with roles, locations with addresses).
     */
    public function ajax_get_veranstalter_details() {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Keine Berechtigung');
            return;
        }

        $veranstalter_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        if (!$veranstalter_id) {
            wp_send_json_error('Veranstalter nicht gefunden');
            return;
        }

        $veranstalter = get_post($veranstalter_id);

        if (!$veranstalter || get_post_type($veranstalter_id) !== 'tmgmt_veranstalter') {
            wp_send_json_error('Veranstalter nicht gefunden');
            return;
        }

        // Load address
        $address = array(
            'street'  => get_post_meta($veranstalter_id, '_tmgmt_veranstalter_street', true),
            'number'  => get_post_meta($veranstalter_id, '_tmgmt_veranstalter_number', true),
            'zip'     => get_post_meta($veranstalter_id, '_tmgmt_veranstalter_zip', true),
            'city'    => get_post_meta($veranstalter_id, '_tmgmt_veranstalter_city', true),
            'country' => get_post_meta($veranstalter_id, '_tmgmt_veranstalter_country', true),
        );

        // Load contacts with roles
        $contact_assignments = get_post_meta($veranstalter_id, '_tmgmt_veranstalter_contacts', true);
        $contacts = array();

        $role_labels = array(
            'vertrag'  => 'Vertrag',
            'technik'  => 'Technik',
            'programm' => 'Programm',
        );

        if (is_array($contact_assignments)) {
            foreach ($contact_assignments as $assignment) {
                $contact_id = isset($assignment['contact_id']) ? intval($assignment['contact_id']) : 0;
                $role       = isset($assignment['role']) ? $assignment['role'] : '';

                $contact_post = get_post($contact_id);

                if (!$contact_post || get_post_type($contact_id) !== 'tmgmt_contact') {
                    $contacts[] = array(
                        'role'       => $role,
                        'role_label' => isset($role_labels[$role]) ? $role_labels[$role] : $role,
                        'contact_id' => $contact_id,
                        'name'       => 'Kontakt nicht gefunden (ID: ' . $contact_id . ')',
                        'email'      => '',
                        'phone'      => '',
                    );
                    continue;
                }

                $firstname = get_post_meta($contact_id, '_tmgmt_contact_firstname', true);
                $lastname  = get_post_meta($contact_id, '_tmgmt_contact_lastname', true);
                $name      = trim($firstname . ' ' . $lastname);

                $contacts[] = array(
                    'role'       => $role,
                    'role_label' => isset($role_labels[$role]) ? $role_labels[$role] : $role,
                    'contact_id' => $contact_id,
                    'name'       => $name,
                    'email'      => get_post_meta($contact_id, '_tmgmt_contact_email', true),
                    'phone'      => get_post_meta($contact_id, '_tmgmt_contact_phone', true),
                );
            }
        }

        // Load locations
        $location_ids = get_post_meta($veranstalter_id, '_tmgmt_veranstalter_locations', true);
        $locations = array();

        if (is_array($location_ids)) {
            foreach ($location_ids as $location_id) {
                $location_id   = intval($location_id);
                $location_post = get_post($location_id);

                if (!$location_post) {
                    continue;
                }

                $locations[] = array(
                    'id'      => $location_id,
                    'title'   => $location_post->post_title,
                    'street'  => get_post_meta($location_id, '_tmgmt_location_street', true),
                    'number'  => get_post_meta($location_id, '_tmgmt_location_number', true),
                    'zip'     => get_post_meta($location_id, '_tmgmt_location_zip', true),
                    'city'    => get_post_meta($location_id, '_tmgmt_location_city', true),
                    'country' => get_post_meta($location_id, '_tmgmt_location_country', true),
                    'lat'     => get_post_meta($location_id, '_tmgmt_location_lat', true),
                    'lng'     => get_post_meta($location_id, '_tmgmt_location_lng', true),
                );
            }
        }

        wp_send_json_success(array(
            'id'        => $veranstalter_id,
            'title'     => $veranstalter->post_title,
            'address'   => $address,
            'contacts'  => $contacts,
            'locations' => $locations,
            'edit_url'  => get_edit_post_link($veranstalter_id, 'raw'),
        ));
    }

    /**
     * Returns all registered fields for the Event CPT.
     * Used for validation and admin UI.
     * 
     * @return array
     */
    public static function get_registered_fields() {
        return array(
            'tmgmt_event_date' => 'Datum der Veranstaltung',
            'tmgmt_event_start_time' => 'Geplante Auftrittszeit',
            'tmgmt_event_arrival_time' => 'Geplante Anreisezeit',
            'tmgmt_event_departure_time' => 'Geplante Abreisezeit',
            'tmgmt_event_location_id' => 'Veranstaltungsort',
            'tmgmt_event_veranstalter_id' => 'Veranstalter',
            'tmgmt_inquiry_date' => 'Anfrage vom',
            'tmgmt_fee' => 'Vereinbarte Gage',
            'tmgmt_deposit' => 'Anzahlung',
        );
    }

    public function add_meta_boxes() {
        add_meta_box(
            'tmgmt_event_details',
            'Veranstaltungsdaten',
            array($this, 'render_event_details_box'),
            'event',
            'normal',
            'high'
        );

        add_meta_box(
            'tmgmt_veranstalter_details',
            'Veranstalter',
            array($this, 'render_veranstalter_box'),
            'event',
            'normal',
            'high'
        );

        add_meta_box(
            'tmgmt_inquiry_details',
            'Anfragedaten',
            array($this, 'render_inquiry_details_box'),
            'event',
            'side',
            'default'
        );

        add_meta_box(
            'tmgmt_contract_details',
            'Vertragsdaten',
            array($this, 'render_contract_details_box'),
            'event',
            'side',
            'default'
        );

        add_meta_box(
            'tmgmt_tour_info',
            'Tourenplanung',
            array($this, 'render_tour_info_box'),
            'event',
            'side',
            'default'
        );

        add_meta_box(
            'tmgmt_event_files',
            'Dateien',
            array($this, 'render_event_files_box'),
            'event',
            'normal',
            'default'
        );

        add_meta_box(
            'tmgmt_pdf_export',
            'PDF Export',
            array($this, 'render_pdf_export_box'),
            'event',
            'side',
            'default'
        );

        add_meta_box(
            'tmgmt_event_log',
            'Verlauf / Logbuch',
            array($this, 'render_log_box'),
            'event',
            'normal',
            'low'
        );
    }

    public function render_tour_info_box($post) {
        $date = get_post_meta($post->ID, '_tmgmt_event_date', true);
        if (!$date) $date = get_post_meta($post->ID, 'tmgmt_event_date', true);

        if (!$date) {
            echo '<p><em>Kein Datum gesetzt.</em></p>';
            return;
        }

        $tours = get_posts(array(
            'post_type' => 'tmgmt_tour',
            'numberposts' => -1,
            'meta_query' => array(
                array(
                    'key' => 'tmgmt_tour_date',
                    'value' => $date,
                    'compare' => '='
                )
            )
        ));

        if (empty($tours)) {
            echo '<p>Keine Tourenplanung für diesen Tag gefunden.</p>';
            return;
        }

        echo '<ul style="margin: 0;">';
        $found_in_any = false;

        foreach ($tours as $tour) {
            $data_json = get_post_meta($tour->ID, 'tmgmt_tour_data', true);
            $schedule = json_decode($data_json, true);
            $mode = get_post_meta($tour->ID, 'tmgmt_tour_mode', true);
            if (!$mode) $mode = 'draft';

            $is_in_tour = false;
            $event_status_in_tour = ''; // OK, Warning, Error

            if (is_array($schedule)) {
                foreach ($schedule as $item) {
                    if (isset($item['type']) && $item['type'] === 'event' && isset($item['id']) && $item['id'] == $post->ID) {
                        $is_in_tour = true;
                        if (isset($item['error'])) {
                            $event_status_in_tour = 'error';
                        } elseif (isset($item['warning'])) {
                            $event_status_in_tour = 'warning';
                        } else {
                            $event_status_in_tour = 'ok';
                        }
                        break;
                    }
                }
            }

            if ($is_in_tour) {
                $found_in_any = true;
                $edit_link = get_edit_post_link($tour->ID);
                $view_link = get_permalink($tour->ID);
                
                echo '<li style="margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #eee;">';
                echo '<strong><a href="' . esc_url($edit_link) . '">' . esc_html($tour->post_title) . '</a></strong>';
                echo ' <a href="' . esc_url($view_link) . '" target="_blank" style="text-decoration:none; font-size: 12px;"><span class="dashicons dashicons-external"></span> Frontend</a><br>';
                
                // Mode Badge
                if ($mode === 'real') {
                    echo '<span style="background: #00a32a; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 10px; text-transform: uppercase;">Echtplanung</span> ';
                } else {
                    echo '<span style="background: #666; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 10px; text-transform: uppercase;">Entwurf</span> ';
                }

                // Status Icon
                if ($event_status_in_tour === 'error') {
                    echo '<span class="dashicons dashicons-warning" style="color: #d63638; font-size: 16px; vertical-align: text-bottom;" title="Fehler im Plan"></span>';
                } elseif ($event_status_in_tour === 'warning') {
                    echo '<span class="dashicons dashicons-warning" style="color: #dba617; font-size: 16px; vertical-align: text-bottom;" title="Warnung im Plan"></span>';
                } else {
                    echo '<span class="dashicons dashicons-yes" style="color: #00a32a; font-size: 16px; vertical-align: text-bottom;" title="OK"></span>';
                }

                echo '</li>';
            }
        }
        echo '</ul>';

        if (!$found_in_any) {
            echo '<p>Event ist in keiner der ' . count($tours) . ' Touren für diesen Tag enthalten (evtl. Status-Filter?).</p>';
        }
    }

    public function render_log_box($post) {
        $log_manager = new TMGMT_Log_Manager();
        $log_manager->render_log_table($post->ID);
    }

    public function render_event_details_box($post) {
        wp_nonce_field('tmgmt_save_event_meta', 'tmgmt_event_meta_nonce');
        
        // Get Event ID
        $event_id = get_post_meta($post->ID, '_tmgmt_event_id', true);
        if ($event_id) {
            echo '<div style="background: #f0f0f1; padding: 10px; margin-bottom: 15px; border-left: 4px solid #2271b1;">';
            echo '<strong>Event ID:</strong> <span style="font-family: monospace; font-size: 1.2em;">' . esc_html($event_id) . '</span>';
            echo '</div>';
        }
        
        // Retrieve existing values
        $date = get_post_meta($post->ID, '_tmgmt_event_date', true);
        $start_time = get_post_meta($post->ID, '_tmgmt_event_start_time', true);
        $arrival_time = get_post_meta($post->ID, '_tmgmt_event_arrival_time', true);
        $departure_time = get_post_meta($post->ID, '_tmgmt_event_departure_time', true);
        
        // Location reference (new approach - link instead of duplicate)
        $location_id = get_post_meta($post->ID, '_tmgmt_event_location_id', true);
        $location = null;
        $location_data = array();
        
        if (!empty($location_id)) {
            $location = get_post($location_id);
            if ($location && get_post_type($location_id) === 'tmgmt_location') {
                $location_data = array(
                    'id'      => $location_id,
                    'title'   => $location->post_title,
                    'street'  => get_post_meta($location_id, '_tmgmt_location_street', true),
                    'number'  => get_post_meta($location_id, '_tmgmt_location_number', true),
                    'zip'     => get_post_meta($location_id, '_tmgmt_location_zip', true),
                    'city'    => get_post_meta($location_id, '_tmgmt_location_city', true),
                    'country' => get_post_meta($location_id, '_tmgmt_location_country', true),
                    'lat'     => get_post_meta($location_id, '_tmgmt_location_lat', true),
                    'lng'     => get_post_meta($location_id, '_tmgmt_location_lng', true),
                    'notes'   => get_post_meta($location_id, '_tmgmt_location_notes', true),
                    'edit_url' => get_edit_post_link($location_id, 'raw'),
                );
            } else {
                // Invalid location reference - reset
                $location = null;
                $location_id = '';
            }
        }
        
        $has_location = ($location !== null);

        ?>
        <style>
            .tmgmt-row { display: flex; gap: 15px; margin-bottom: 10px; flex-wrap: wrap; }
            .tmgmt-field { flex: 1; min-width: 200px; }
            .tmgmt-field label { display: block; font-weight: 600; margin-bottom: 5px; }
            .tmgmt-field input, .tmgmt-field textarea { width: 100%; }
            .tmgmt-section-title { font-weight: bold; border-bottom: 1px solid #ccc; margin: 15px 0 10px; padding-bottom: 5px; }
            .tmgmt-location-info { background: #f9f9f9; padding: 15px; border-left: 4px solid #2271b1; margin-bottom: 15px; }
            .tmgmt-location-info .location-name { font-size: 1.1em; font-weight: 600; margin-bottom: 10px; }
            .tmgmt-location-info .location-address { color: #555; margin-bottom: 8px; }
            .tmgmt-location-info .location-geo { font-size: 0.9em; color: #777; }
            .tmgmt-location-info .location-notes { margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd; font-style: italic; color: #666; }
        </style>

        <div class="tmgmt-row">
            <div class="tmgmt-field">
                <label for="tmgmt_event_date">Datum der Veranstaltung</label>
                <input type="date" id="tmgmt_event_date" name="tmgmt_event_date" value="<?php echo esc_attr($date); ?>">
            </div>
            <div class="tmgmt-field">
                <label for="tmgmt_event_start_time">Geplante Auftrittszeit</label>
                <input type="time" id="tmgmt_event_start_time" name="tmgmt_event_start_time" value="<?php echo esc_attr($start_time); ?>">
            </div>
        </div>
        <div class="tmgmt-row">
            <div class="tmgmt-field">
                <label for="tmgmt_event_arrival_time">Geplante Anreisezeit</label>
                <input type="time" id="tmgmt_event_arrival_time" name="tmgmt_event_arrival_time" value="<?php echo esc_attr($arrival_time); ?>">
            </div>
            <div class="tmgmt-field">
                <label for="tmgmt_event_departure_time">Geplante Abreisezeit</label>
                <input type="time" id="tmgmt_event_departure_time" name="tmgmt_event_departure_time" value="<?php echo esc_attr($departure_time); ?>">
            </div>
        </div>

        <div class="tmgmt-section-title">Veranstaltungsort</div>
        
        <!-- Hidden field for Location ID -->
        <input type="hidden" id="tmgmt_event_location_id" name="tmgmt_event_location_id" value="<?php echo esc_attr($location_id); ?>">
        
        <!-- Search field (shown when no location linked) -->
        <div id="tmgmt-location-search-wrap" style="margin-bottom: 15px;<?php echo $has_location ? ' display:none;' : ''; ?>">
            <div style="position: relative;">
                <input type="text" id="tmgmt_location_search" placeholder="Ort suchen (min. 2 Zeichen)..." autocomplete="off" style="width: 100%;">
                <div id="tmgmt_location_search_results" style="position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #ccc; z-index: 100; max-height: 200px; overflow-y: auto; display: none; box-shadow: 0 2px 5px rgba(0,0,0,0.1);"></div>
            </div>
            <div style="margin-top: 10px;">
                <button type="button" id="tmgmt-create-location-btn" class="button button-secondary">
                    <span class="dashicons dashicons-plus-alt" style="vertical-align: text-bottom;"></span> Neuer Ort
                </button>
            </div>
        </div>
        
        <!-- Location info (shown when linked) -->
        <div id="tmgmt-location-info" style="<?php echo $has_location ? '' : 'display:none;'; ?>">
            <div class="tmgmt-location-info">
                <div class="location-name">
                    <span id="tmgmt-location-name"><?php
                        if ($has_location) {
                            if (!empty($location_data['edit_url'])) {
                                echo '<a href="' . esc_url($location_data['edit_url']) . '" target="_blank">' . esc_html($location_data['title']) . '</a>';
                            } else {
                                echo esc_html($location_data['title']);
                            }
                        }
                    ?></span>
                    <button type="button" id="tmgmt-location-remove" class="button button-link-delete" style="margin-left: 10px;">Verknüpfung entfernen</button>
                </div>
                <div class="location-address" id="tmgmt-location-address"><?php
                    if ($has_location) {
                        $addr_parts = array();
                        $street_line = trim(($location_data['street'] ?? '') . ' ' . ($location_data['number'] ?? ''));
                        if ($street_line) $addr_parts[] = esc_html($street_line);
                        $city_line = trim(($location_data['zip'] ?? '') . ' ' . ($location_data['city'] ?? ''));
                        if ($city_line) $addr_parts[] = esc_html($city_line);
                        if (!empty($location_data['country'])) $addr_parts[] = esc_html($location_data['country']);
                        echo implode('<br>', $addr_parts);
                        if (empty($addr_parts)) echo '<em>Keine Adresse hinterlegt</em>';
                    }
                ?></div>
                <div class="location-geo" id="tmgmt-location-geo"><?php
                    if ($has_location && (!empty($location_data['lat']) || !empty($location_data['lng']))) {
                        echo '<span class="dashicons dashicons-location" style="font-size: 14px; width: 14px; height: 14px; vertical-align: text-bottom;"></span> ';
                        echo esc_html($location_data['lat']) . ', ' . esc_html($location_data['lng']);
                    }
                ?></div>
                <?php if ($has_location && !empty($location_data['notes'])): ?>
                <div class="location-notes" id="tmgmt-location-notes">
                    <strong>Hinweise Anreise / Bus:</strong><br>
                    <?php echo nl2br(esc_html($location_data['notes'])); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Search locations
            let searchTimeout;
            $('#tmgmt_location_search').on('input', function() {
                clearTimeout(searchTimeout);
                const term = $(this).val();
                if (term.length < 2) {
                    $('#tmgmt_location_search_results').hide();
                    return;
                }
                
                searchTimeout = setTimeout(function() {
                    $.ajax({
                        url: ajaxurl,
                        data: {
                            action: 'tmgmt_search_locations',
                            term: term
                        },
                        success: function(res) {
                            if (res.success && res.data.length > 0) {
                                let html = '';
                                res.data.forEach(item => {
                                    html += `<div class="tmgmt-location-result" style="padding: 8px; cursor: pointer; border-bottom: 1px solid #eee;"
                                        data-id="${item.id}"
                                        data-title="${item.title}"
                                        data-street="${item.street || ''}"
                                        data-number="${item.number || ''}"
                                        data-zip="${item.zip || ''}"
                                        data-city="${item.city || ''}"
                                        data-country="${item.country || ''}"
                                        data-lat="${item.lat || ''}"
                                        data-lng="${item.lng || ''}"
                                        data-notes="${item.notes || ''}"
                                    ><strong>${item.title}</strong><br><small>${item.street || ''} ${item.number || ''}, ${item.zip || ''} ${item.city || ''}</small></div>`;
                                });
                                $('#tmgmt_location_search_results').html(html).show();
                            } else {
                                $('#tmgmt_location_search_results').hide();
                            }
                        }
                    });
                }, 300);
            });

            // Select location from search results
            $(document).on('click', '.tmgmt-location-result', function() {
                const data = $(this).data();
                selectLocation(data);
                $('#tmgmt_location_search_results').hide();
                $('#tmgmt_location_search').val('');
            });
            
            function selectLocation(data) {
                // Set hidden field
                $('#tmgmt_event_location_id').val(data.id);
                
                // Build address display
                let addrParts = [];
                let streetLine = ((data.street || '') + ' ' + (data.number || '')).trim();
                if (streetLine) addrParts.push(streetLine);
                let cityLine = ((data.zip || '') + ' ' + (data.city || '')).trim();
                if (cityLine) addrParts.push(cityLine);
                if (data.country) addrParts.push(data.country);
                
                // Update display
                $('#tmgmt-location-name').html('<a href="/wp-admin/post.php?post=' + data.id + '&action=edit" target="_blank">' + data.title + '</a>');
                $('#tmgmt-location-address').html(addrParts.length > 0 ? addrParts.join('<br>') : '<em>Keine Adresse hinterlegt</em>');
                
                if (data.lat || data.lng) {
                    $('#tmgmt-location-geo').html('<span class="dashicons dashicons-location" style="font-size: 14px; width: 14px; height: 14px; vertical-align: text-bottom;"></span> ' + (data.lat || '') + ', ' + (data.lng || ''));
                } else {
                    $('#tmgmt-location-geo').html('');
                }
                
                if (data.notes) {
                    if ($('#tmgmt-location-notes').length === 0) {
                        $('.tmgmt-location-info').append('<div class="location-notes" id="tmgmt-location-notes"><strong>Hinweise Anreise / Bus:</strong><br>' + data.notes.replace(/\n/g, '<br>') + '</div>');
                    } else {
                        $('#tmgmt-location-notes').html('<strong>Hinweise Anreise / Bus:</strong><br>' + data.notes.replace(/\n/g, '<br>')).show();
                    }
                } else {
                    $('#tmgmt-location-notes').hide();
                }
                
                // Show info, hide search
                $('#tmgmt-location-search-wrap').hide();
                $('#tmgmt-location-info').show();
            }
            
            // Remove location link
            $('#tmgmt-location-remove').on('click', function() {
                $('#tmgmt_event_location_id').val('');
                $('#tmgmt-location-info').hide();
                $('#tmgmt-location-search-wrap').show();
            });
            
            // Close search on click outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#tmgmt_location_search, #tmgmt_location_search_results').length) {
                    $('#tmgmt_location_search_results').hide();
                }
            });

            // Create new location dialog
            $('#tmgmt-create-location-btn').on('click', function() {
                Swal.fire({
                    title: 'Neuen Ort anlegen',
                    html: `
                        <div style="text-align: left;">
                            <div style="margin-bottom: 10px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 5px;">Name *</label>
                                <input type="text" id="swal-loc-name" class="swal2-input" style="width: 100%; margin: 0;" placeholder="z.B. Stadthalle Musterstadt">
                            </div>
                            <div style="margin-bottom: 10px; display: flex; gap: 10px;">
                                <div style="flex: 3;">
                                    <label style="display: block; font-weight: 600; margin-bottom: 5px;">Straße</label>
                                    <input type="text" id="swal-loc-street" class="swal2-input" style="width: 100%; margin: 0;">
                                </div>
                                <div style="flex: 1;">
                                    <label style="display: block; font-weight: 600; margin-bottom: 5px;">Nr.</label>
                                    <input type="text" id="swal-loc-number" class="swal2-input" style="width: 100%; margin: 0;">
                                </div>
                            </div>
                            <div style="margin-bottom: 10px; display: flex; gap: 10px;">
                                <div style="flex: 1;">
                                    <label style="display: block; font-weight: 600; margin-bottom: 5px;">PLZ</label>
                                    <input type="text" id="swal-loc-zip" class="swal2-input" style="width: 100%; margin: 0;">
                                </div>
                                <div style="flex: 2;">
                                    <label style="display: block; font-weight: 600; margin-bottom: 5px;">Ort</label>
                                    <input type="text" id="swal-loc-city" class="swal2-input" style="width: 100%; margin: 0;">
                                </div>
                            </div>
                            <div style="margin-bottom: 10px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 5px;">Land</label>
                                <input type="text" id="swal-loc-country" class="swal2-input" style="width: 100%; margin: 0;" value="Deutschland">
                            </div>
                            <div style="margin-bottom: 10px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 5px;">Hinweise Anreise / Bus</label>
                                <textarea id="swal-loc-notes" class="swal2-textarea" style="width: 100%; margin: 0;" rows="3"></textarea>
                            </div>
                        </div>
                    `,
                    width: 500,
                    showCancelButton: true,
                    confirmButtonText: 'Anlegen & Übernehmen',
                    cancelButtonText: 'Abbrechen',
                    preConfirm: function() {
                        var name = $('#swal-loc-name').val();
                        if (!name || name.trim() === '') {
                            Swal.showValidationMessage('Bitte geben Sie einen Namen ein.');
                            return false;
                        }
                        return {
                            name: name.trim(),
                            street: $('#swal-loc-street').val(),
                            number: $('#swal-loc-number').val(),
                            zip: $('#swal-loc-zip').val(),
                            city: $('#swal-loc-city').val(),
                            country: $('#swal-loc-country').val(),
                            notes: $('#swal-loc-notes').val()
                        };
                    }
                }).then(function(result) {
                    if (result.isConfirmed && result.value) {
                        createLocationAndApply(result.value);
                    }
                });
            });

            function createLocationAndApply(data) {
                Swal.fire({
                    title: 'Erstelle Ort...',
                    allowOutsideClick: false,
                    didOpen: function() {
                        Swal.showLoading();
                    }
                });

                $.ajax({
                    url: '/wp-json/tmgmt/v1/locations',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify(data),
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', (typeof tmgmt_vars !== 'undefined' && tmgmt_vars.nonce) ? tmgmt_vars.nonce : wpApiSettings.nonce);
                    },
                    success: function(response) {
                        if (response.success && response.id) {
                            // Select the newly created location
                            selectLocation({
                                id: response.id,
                                title: response.title,
                                street: response.street || '',
                                number: response.number || '',
                                zip: response.zip || '',
                                city: response.city || '',
                                country: response.country || '',
                                lat: response.lat || '',
                                lng: response.lng || '',
                                notes: data.notes || ''
                            });
                            
                            Swal.fire({
                                icon: 'success',
                                title: 'Ort erstellt',
                                text: 'Der Ort "' + response.title + '" wurde erstellt und verknüpft.',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire('Fehler', 'Ort konnte nicht erstellt werden.', 'error');
                        }
                    },
                    error: function(xhr) {
                        var msg = 'Unbekannter Fehler';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }
                        Swal.fire('Fehler', msg, 'error');
                    }
                });
            }
        });
        </script>
        <?php
    }

    public function render_veranstalter_box($post) {
        $veranstalter_id = get_post_meta($post->ID, '_tmgmt_event_veranstalter_id', true);
        $location_id     = get_post_meta($post->ID, '_tmgmt_event_location_id', true);

        // Load Veranstalter data if linked
        $veranstalter      = null;
        $veranstalter_addr = array();
        $contacts          = array();
        $locations         = array();
        $edit_url          = '';

        if (!empty($veranstalter_id)) {
            $veranstalter = get_post($veranstalter_id);
            if ($veranstalter && get_post_type($veranstalter_id) === 'tmgmt_veranstalter') {
                $veranstalter_addr = array(
                    'street'  => get_post_meta($veranstalter_id, '_tmgmt_veranstalter_street', true),
                    'number'  => get_post_meta($veranstalter_id, '_tmgmt_veranstalter_number', true),
                    'zip'     => get_post_meta($veranstalter_id, '_tmgmt_veranstalter_zip', true),
                    'city'    => get_post_meta($veranstalter_id, '_tmgmt_veranstalter_city', true),
                    'country' => get_post_meta($veranstalter_id, '_tmgmt_veranstalter_country', true),
                );
                $edit_url = get_edit_post_link($veranstalter_id, 'raw');

                // Load contacts
                $role_labels = array('vertrag' => 'Vertrag', 'technik' => 'Technik', 'programm' => 'Programm');
                $contact_assignments = get_post_meta($veranstalter_id, '_tmgmt_veranstalter_contacts', true);
                if (is_array($contact_assignments)) {
                    foreach ($contact_assignments as $assignment) {
                        $cid  = isset($assignment['contact_id']) ? intval($assignment['contact_id']) : 0;
                        $role = isset($assignment['role']) ? $assignment['role'] : '';
                        $contact_post = get_post($cid);
                        if ($contact_post && get_post_type($cid) === 'tmgmt_contact') {
                            $fn = get_post_meta($cid, '_tmgmt_contact_firstname', true);
                            $ln = get_post_meta($cid, '_tmgmt_contact_lastname', true);
                            $contacts[] = array(
                                'role_label' => isset($role_labels[$role]) ? $role_labels[$role] : $role,
                                'name'       => trim($fn . ' ' . $ln),
                                'email'      => get_post_meta($cid, '_tmgmt_contact_email', true),
                                'phone'      => get_post_meta($cid, '_tmgmt_contact_phone', true),
                            );
                        } else {
                            $contacts[] = array(
                                'role_label' => isset($role_labels[$role]) ? $role_labels[$role] : $role,
                                'name'       => 'Kontakt nicht gefunden (ID: ' . $cid . ')',
                                'email'      => '',
                                'phone'      => '',
                            );
                        }
                    }
                }

                // Load locations
                $location_ids = get_post_meta($veranstalter_id, '_tmgmt_veranstalter_locations', true);
                if (is_array($location_ids)) {
                    foreach ($location_ids as $lid) {
                        $lid = intval($lid);
                        $loc_post = get_post($lid);
                        if ($loc_post) {
                            $locations[] = array(
                                'id'    => $lid,
                                'title' => $loc_post->post_title,
                                'street'  => get_post_meta($lid, '_tmgmt_location_street', true),
                                'number'  => get_post_meta($lid, '_tmgmt_location_number', true),
                                'zip'     => get_post_meta($lid, '_tmgmt_location_zip', true),
                                'city'    => get_post_meta($lid, '_tmgmt_location_city', true),
                                'country' => get_post_meta($lid, '_tmgmt_location_country', true),
                            );
                        }
                    }
                }
            } else {
                // Invalid Veranstalter reference — reset
                $veranstalter    = null;
                $veranstalter_id = '';
            }
        }

        $has_veranstalter = ($veranstalter !== null);
        
        ?>
        <!-- Hidden field for Veranstalter ID -->
        <input type="hidden" id="tmgmt_event_veranstalter_id" name="tmgmt_event_veranstalter_id" value="<?php echo esc_attr($veranstalter_id); ?>">

        <!-- Search field -->
        <div id="tmgmt-veranstalter-search-wrap" style="margin-bottom: 15px;<?php echo $has_veranstalter ? ' display:none;' : ''; ?>">
            <div style="position: relative;">
                <label for="tmgmt_veranstalter_search">Veranstalter suchen</label>
                <input type="text" id="tmgmt_veranstalter_search" placeholder="Name eingeben (min. 2 Zeichen)..." autocomplete="off" style="width: 100%;">
            </div>
            <div style="margin-top: 10px;">
                <button type="button" id="tmgmt-create-veranstalter-btn" class="button button-secondary">
                    <span class="dashicons dashicons-plus-alt" style="vertical-align: text-bottom;"></span> Neuen Veranstalter anlegen
                </button>
            </div>
        </div>

        <!-- Veranstalter info (shown when linked) -->
        <div id="tmgmt-veranstalter-info" style="<?php echo $has_veranstalter ? '' : 'display:none;'; ?>">
            <!-- Name and edit link -->
            <div style="margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #eee;">
                <strong id="tmgmt-veranstalter-name"><?php
                    if ($has_veranstalter) {
                        if ($edit_url) {
                            echo '<a href="' . esc_url($edit_url) . '" target="_blank">' . esc_html($veranstalter->post_title) . '</a>';
                        } else {
                            echo esc_html($veranstalter->post_title);
                        }
                    }
                ?></strong>
                <button type="button" id="tmgmt-veranstalter-remove" class="button button-link-delete" style="margin-left: 10px;">Verknüpfung entfernen</button>
            </div>

            <!-- Postal address -->
            <div id="tmgmt-veranstalter-address" style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee;">
                <div class="tmgmt-section-title" style="font-weight: bold; margin-bottom: 5px;">Postadresse</div>
                <div id="tmgmt-veranstalter-address-content"><?php
                    if ($has_veranstalter) {
                        $addr_parts = array();
                        $street_line = trim(($veranstalter_addr['street'] ?? '') . ' ' . ($veranstalter_addr['number'] ?? ''));
                        if ($street_line) $addr_parts[] = esc_html($street_line);
                        $city_line = trim(($veranstalter_addr['zip'] ?? '') . ' ' . ($veranstalter_addr['city'] ?? ''));
                        if ($city_line) $addr_parts[] = esc_html($city_line);
                        if (!empty($veranstalter_addr['country'])) $addr_parts[] = esc_html($veranstalter_addr['country']);
                        echo implode('<br>', $addr_parts);
                        if (empty($addr_parts)) echo '<em>Keine Adresse hinterlegt</em>';
                    }
                ?></div>
            </div>

            <!-- Contacts (read-only, per role) -->
            <div id="tmgmt-veranstalter-contacts" style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee;">
                <div class="tmgmt-section-title" style="font-weight: bold; margin-bottom: 5px;">Kontakte</div>
                <div id="tmgmt-veranstalter-contacts-content"><?php
                    if ($has_veranstalter) {
                        if (empty($contacts)) {
                            echo '<em>Keine Kontakte zugeordnet</em>';
                        } else {
                            foreach ($contacts as $contact) {
                                echo '<div style="margin-bottom: 8px; padding: 6px; background: #f9f9f9; border-left: 3px solid #2271b1;">';
                                echo '<strong>' . esc_html($contact['role_label']) . ':</strong> ' . esc_html($contact['name']);
                                if (!empty($contact['email'])) {
                                    echo '<br><span class="dashicons dashicons-email" style="font-size: 14px; width: 14px; height: 14px; vertical-align: text-bottom;"></span> ' . esc_html($contact['email']);
                                }
                                if (!empty($contact['phone'])) {
                                    echo '<br><span class="dashicons dashicons-phone" style="font-size: 14px; width: 14px; height: 14px; vertical-align: text-bottom;"></span> ' . esc_html($contact['phone']);
                                }
                                echo '</div>';
                            }
                        }
                    }
                ?></div>
            </div>

            <!-- Locations (selection list) -->
            <div id="tmgmt-veranstalter-locations">
                <div class="tmgmt-section-title" style="font-weight: bold; margin-bottom: 5px;">Veranstaltungsorte</div>
                <div id="tmgmt-veranstalter-locations-content"><?php
                    if ($has_veranstalter) {
                        if (empty($locations)) {
                            echo '<em>Keine Veranstaltungsorte zugeordnet</em>';
                        } else {
                            echo '<select id="tmgmt-veranstalter-location-select" style="width: 100%; margin-bottom: 8px;">';
                            echo '<option value="">-- Ort auswählen --</option>';
                            foreach ($locations as $loc) {
                                $selected = ($location_id == $loc['id']) ? ' selected' : '';
                                echo '<option value="' . esc_attr($loc['id']) . '"'
                                    . ' data-street="' . esc_attr($loc['street']) . '"'
                                    . ' data-number="' . esc_attr($loc['number']) . '"'
                                    . ' data-zip="' . esc_attr($loc['zip']) . '"'
                                    . ' data-city="' . esc_attr($loc['city']) . '"'
                                    . ' data-country="' . esc_attr($loc['country']) . '"'
                                    . $selected . '>'
                                    . esc_html($loc['title']) . '</option>';
                            }
                            echo '</select>';

                            // Show address of selected location
                            $selected_loc = null;
                            foreach ($locations as $loc) {
                                if ($location_id == $loc['id']) {
                                    $selected_loc = $loc;
                                    break;
                                }
                            }
                            echo '<div id="tmgmt-veranstalter-location-address">';
                            if ($selected_loc) {
                                $loc_parts = array();
                                $loc_street = trim(($selected_loc['street'] ?? '') . ' ' . ($selected_loc['number'] ?? ''));
                                if ($loc_street) $loc_parts[] = esc_html($loc_street);
                                $loc_city = trim(($selected_loc['zip'] ?? '') . ' ' . ($selected_loc['city'] ?? ''));
                                if ($loc_city) $loc_parts[] = esc_html($loc_city);
                                if (!empty($selected_loc['country'])) $loc_parts[] = esc_html($selected_loc['country']);
                                echo implode('<br>', $loc_parts);
                            }
                            echo '</div>';
                        }
                    }
                ?></div>
            </div>
        </div>
        <?php
    }

    public function render_inquiry_details_box($post) {
        $inquiry_date = get_post_meta($post->ID, '_tmgmt_inquiry_date', true);
        
        // Set default date for new posts or empty values
        if (empty($inquiry_date)) {
            $inquiry_date = current_time('Y-m-d\TH:i');
        }

        $status = get_post_meta($post->ID, '_tmgmt_status', true);
        $statuses = TMGMT_Event_Status::get_all_statuses();

        // If no status is set (new post), select the first one (lowest order ID)
        if (empty($status) && !empty($statuses)) {
            // get_all_statuses returns array ordered by menu_order ASC
            $status = array_key_first($statuses);
        }

        $can_change_status = current_user_can('tmgmt_set_event_status_directly');
        ?>
        <div class="tmgmt-field" style="margin-bottom: 10px;">
            <label for="tmgmt_inquiry_date">Anfrage vom</label>
            <input type="datetime-local" id="tmgmt_inquiry_date" name="tmgmt_inquiry_date" value="<?php echo esc_attr($inquiry_date); ?>" style="width:100%">
        </div>
        <div class="tmgmt-field">
            <label for="tmgmt_status">Status</label>
            <select id="tmgmt_status" name="tmgmt_status" style="width:100%" <?php echo $can_change_status ? '' : 'disabled'; ?>>
                <option value="">-- Bitte wählen --</option>
                <?php foreach ($statuses as $key => $label) : ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($status, $key); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (!$can_change_status): ?>
                <input type="hidden" name="tmgmt_status" value="<?php echo esc_attr($status); ?>">
                <p class="description" style="margin-top: 5px; color: #666;">
                    <span class="dashicons dashicons-lock" style="font-size: 14px; vertical-align: text-top;"></span>
                    Statusänderung nur über Workflow-Aktionen möglich.
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    public function render_contract_details_box($post) {
        $fee = get_post_meta($post->ID, '_tmgmt_fee', true);
        $deposit = get_post_meta($post->ID, '_tmgmt_deposit', true);
        ?>
        <div class="tmgmt-field" style="margin-bottom: 10px;">
            <label for="tmgmt_fee">Vereinbarte Gage (€)</label>
            <input type="number" step="0.01" id="tmgmt_fee" name="tmgmt_fee" value="<?php echo esc_attr($fee); ?>" style="width:100%">
        </div>
        <div class="tmgmt-field">
            <label for="tmgmt_deposit">Anzahlung (€)</label>
            <input type="number" step="0.01" id="tmgmt_deposit" name="tmgmt_deposit" value="<?php echo esc_attr($deposit); ?>" style="width:100%">
        </div>
        <?php
    }

    public function render_pdf_export_box($post) {
        $selected_setlist_id = get_post_meta($post->ID, '_tmgmt_selected_setlist', true);
        
        // Fallback check
        if (!$selected_setlist_id) {
             $linked_setlists = get_posts(array(
                'post_type' => 'tmgmt_setlist',
                'meta_key' => '_tmgmt_setlist_event',
                'meta_value' => $post->ID,
                'numberposts' => 1
            ));
            if ($linked_setlists) {
                $selected_setlist_id = $linked_setlists[0]->ID;
            }
        }

        // Get all available setlists (Standard + Custom)
        $all_setlists = get_posts(array(
            'post_type' => 'tmgmt_setlist',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        ?>
        
        <div class="tmgmt-setlist-selector" style="margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
            <label for="tmgmt_selected_setlist"><strong>Setlist zuweisen:</strong></label>
            <select name="tmgmt_selected_setlist" id="tmgmt_selected_setlist" style="width:100%; margin-top:5px;">
                <option value="">-- Keine Setlist --</option>
                <?php foreach ($all_setlists as $sl): 
                    $type = get_post_meta($sl->ID, '_tmgmt_setlist_type', true);
                    if ($type === 'template') continue; // Skip templates
                    
                    $assigned_event = get_post_meta($sl->ID, '_tmgmt_setlist_event', true);
                    $is_assigned_to_other = ($assigned_event && $assigned_event != $post->ID);
                    
                    // Filter out custom setlists assigned to other events
                    if ($type === 'custom' && $is_assigned_to_other) {
                        continue;
                    }

                    // Label formatting
                    $label = $sl->post_title . ' (' . ucfirst($type) . ')';
                    if ($is_assigned_to_other) {
                        $other_event = get_post($assigned_event);
                        $label .= ' [Verwendet in: ' . ($other_event ? $other_event->post_title : 'Unbekannt') . ']';
                    }
                ?>
                    <option value="<?php echo $sl->ID; ?>" <?php selected($selected_setlist_id, $sl->ID); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="description">Wähle eine Setlist für diesen Gig.</p>
            
            <div style="margin-top: 10px;">
                <button type="button" class="button" id="tmgmt-create-custom-setlist-btn">Spezielle Setlist erstellen</button>
            </div>
        </div>

        <!-- Custom Setlist Modal -->
        <div id="tmgmt-custom-setlist-modal" title="Spezielle Setlist erstellen" style="display:none;">
            <p>Wähle eine Vorlage als Basis für die neue Setlist:</p>
            <select id="tmgmt-setlist-template-select" style="width:100%;">
                <option value="">-- Vorlage wählen --</option>
                <?php 
                $templates = get_posts(array(
                    'post_type' => 'tmgmt_setlist',
                    'numberposts' => -1,
                    'meta_key' => '_tmgmt_setlist_type',
                    'meta_value' => 'template'
                ));
                foreach ($templates as $tpl) {
                    echo '<option value="' . $tpl->ID . '">' . esc_html($tpl->post_title) . '</option>';
                }
                ?>
            </select>
            <p class="description">Eine Kopie dieser Vorlage wird erstellt und diesem Event zugewiesen.</p>
        </div>

        <?php if ($selected_setlist_id): ?>
            <p>
                <a href="<?php echo esc_url(admin_url('admin-post.php?action=tmgmt_generate_setlist_pdf&event_id=' . $post->ID)); ?>" class="button button-secondary" target="_blank">Setlist PDF herunterladen</a>
            </p>
            <p>
                <button type="button" class="button button-primary" id="tmgmt-email-pdf-btn">Per E-Mail senden</button>
            </p>
            <p class="description">
                Generiert die Setlist als PDF basierend auf dem gewählten Template.
            </p>
        <?php else: ?>
            <p><em>Bitte weisen Sie eine Setlist zu, um die Export-Funktionen zu nutzen.</em></p>
        <?php endif; ?>

        <!-- Email Modal -->
        <div id="tmgmt-email-modal" title="Setlist per E-Mail senden" style="display:none;">
            <p>
                <label for="tmgmt-email-template">Vorlage wählen:</label><br>
                <select id="tmgmt-email-template" style="width:100%;">
                    <option value="">-- Bitte wählen --</option>
                </select>
            </p>
            <div id="tmgmt-email-preview" style="display:none;">
                <p>
                    <label for="tmgmt-email-recipient">Empfänger:</label><br>
                    <input type="text" id="tmgmt-email-recipient" style="width:100%;">
                </p>
                <p>
                    <label for="tmgmt-email-subject">Betreff:</label><br>
                    <input type="text" id="tmgmt-email-subject" style="width:100%;">
                </p>
                <p>
                    <label for="tmgmt-email-body">Nachricht:</label><br>
                    <textarea id="tmgmt-email-body" rows="8" style="width:100%;"></textarea>
                </p>
                <p>
                    <label>
                        <input type="checkbox" id="tmgmt-email-attach-pdf" checked> 
                        Setlist PDF anhängen
                    </label>
                </p>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var eventId = <?php echo $post->ID; ?>;
            var modal = $('#tmgmt-email-modal');
            var templateSelect = $('#tmgmt-email-template');
            var previewDiv = $('#tmgmt-email-preview');
            
            // Custom Setlist Logic
            var customSetlistModal = $('#tmgmt-custom-setlist-modal');
            var customSetlistBtn = $('#tmgmt-create-custom-setlist-btn');
            var customSetlistTemplateSelect = $('#tmgmt-setlist-template-select');

            customSetlistBtn.click(function() {
                customSetlistModal.dialog({
                    modal: true,
                    width: 400,
                    buttons: {
                        "Erstellen": function() {
                            var templateId = customSetlistTemplateSelect.val();
                            if (!templateId) {
                                Swal.fire('Fehlende Angabe', 'Bitte wählen Sie eine Vorlage.', 'warning');
                                return;
                            }
                            
                            var dialog = $(this);
                            var btn = dialog.parent().find('.ui-dialog-buttonpane button:first');
                            btn.prop('disabled', true).text('Erstelle...');

                            $.post(ajaxurl, {
                                action: 'tmgmt_create_custom_setlist',
                                nonce: '<?php echo wp_create_nonce('tmgmt_create_custom_setlist_nonce'); ?>',
                                event_id: eventId,
                                template_id: templateId
                            }, function(response) {
                                if (response.success) {
                                    // Open new setlist in new tab
                                    window.open(response.data.edit_url, '_blank');
                                    // Reload current page to update selection
                                    location.reload();
                                } else {
                                    Swal.fire('Fehler', 'Fehler: ' + (response.data || 'Unbekannter Fehler'), 'error');
                                    btn.prop('disabled', false).text('Erstellen');
                                }
                            });
                        },
                        "Abbrechen": function() {
                            $(this).dialog("close");
                        }
                    }
                });
            });

            // Load Templates
            $('#tmgmt-email-pdf-btn').click(function() {
                // Reset
                templateSelect.empty().append('<option value="">-- Bitte wählen --</option>');
                previewDiv.hide();
                
                // Fetch Templates
                $.get('/wp-json/tmgmt/v1/email-templates', function(data) {
                    $.each(data, function(i, item) {
                        templateSelect.append($('<option>', { 
                            value: item.id,
                            text : item.title 
                        }));
                    });
                    
                    modal.dialog({
                        modal: true,
                        width: 500,
                        buttons: {
                            "Senden": function() {
                                sendEmail($(this));
                            },
                            "Abbrechen": function() {
                                $(this).dialog("close");
                            }
                        }
                    });
                });
            });

            // Load Preview on Template Change
            templateSelect.change(function() {
                var templateId = $(this).val();
                if (!templateId) {
                    previewDiv.hide();
                    return;
                }

                $.post('/wp-json/tmgmt/v1/events/' + eventId + '/email-preview', {
                    template_id: templateId,
                    _wpnonce: tmgmt_vars.nonce
                }, function(data) {
                    $('#tmgmt-email-recipient').val(data.recipient);
                    $('#tmgmt-email-subject').val(data.subject);
                    $('#tmgmt-email-body').val(data.body);
                    previewDiv.show();
                });
            });

            function sendEmail(dialogRef) {
                var btn = dialogRef.parent().find('.ui-dialog-buttonpane button:contains("Senden")');
                btn.prop('disabled', true).text('Sende...');

                $.post('/wp-json/tmgmt/v1/events/' + eventId + '/email-send', {
                    recipient: $('#tmgmt-email-recipient').val(),
                    subject: $('#tmgmt-email-subject').val(),
                    body: $('#tmgmt-email-body').val(),
                    attach_pdf: $('#tmgmt-email-attach-pdf').is(':checked') ? 1 : 0,
                    _wpnonce: tmgmt_vars.nonce
                }, function(response) {
                    if (response.success) {
                        Swal.fire('Gesendet', response.message, 'success');
                        dialogRef.dialog("close");
                    } else {
                        Swal.fire('Fehler', 'Fehler: ' + response.message, 'error');
                    }
                    btn.prop('disabled', false).text('Senden');
                }).fail(function(xhr) {
                    var msg = 'Unbekannter Fehler';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    Swal.fire('Fehler', 'Fehler: ' + msg, 'error');
                    btn.prop('disabled', false).text('Senden');
                });
            }
        });
        </script>
        <?php
    }

    public function render_event_files_box($post) {
        $attachments = get_posts(array(
            'post_type' => 'attachment',
            'posts_per_page' => -1,
            'post_parent' => $post->ID,
        ));

        if (empty($attachments)) {
            echo '<p>Keine Dateien vorhanden.</p>';
            return;
        }

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr><th>Datei</th><th>Datum</th><th>Typ</th><th>Aktionen</th></tr></thead>';
        echo '<tbody>';
        foreach ($attachments as $attachment) {
            $url = wp_get_attachment_url($attachment->ID);
            $filename = basename($url);
            $date = get_the_date('d.m.Y H:i', $attachment->ID);
            $type = get_post_mime_type($attachment->ID);
            
            echo '<tr>';
            echo '<td><a href="' . esc_url($url) . '" target="_blank">' . esc_html($filename) . '</a></td>';
            echo '<td>' . esc_html($date) . '</td>';
            echo '<td>' . esc_html($type) . '</td>';
            echo '<td>';
            if (current_user_can('delete_post', $attachment->ID)) {
                echo '<button type="button" class="button tmgmt-delete-file" data-id="' . esc_attr($attachment->ID) . '">Löschen</button>';
            }
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    }

    public function save_meta_boxes($post_id) {
        if (!isset($_POST['tmgmt_event_meta_nonce']) || !wp_verify_nonce($_POST['tmgmt_event_meta_nonce'], 'tmgmt_save_event_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Check for Status Change and Log it
        $old_status = get_post_meta($post_id, '_tmgmt_status', true);
        $new_status = isset($_POST['tmgmt_status']) ? sanitize_text_field($_POST['tmgmt_status']) : '';

        // Check for changes relevant to Tour Planning (Time, Date, Status, Location)
        $tour_relevant_changes = false;
        
        // 1. Time
        $old_start_time = get_post_meta($post_id, '_tmgmt_event_start_time', true);
        $new_start_time = isset($_POST['tmgmt_event_start_time']) ? sanitize_text_field($_POST['tmgmt_event_start_time']) : '';
        if ($old_start_time !== $new_start_time) $tour_relevant_changes = true;

        // 2. Date
        $old_date = get_post_meta($post_id, '_tmgmt_event_date', true);
        $new_date = isset($_POST['tmgmt_event_date']) ? sanitize_text_field($_POST['tmgmt_event_date']) : '';
        if ($old_date !== $new_date) $tour_relevant_changes = true;

        // 3. Status
        if ($old_status !== $new_status) $tour_relevant_changes = true;

        // 4. Location (changed location means potentially different geo coordinates)
        $old_location_id = get_post_meta($post_id, '_tmgmt_event_location_id', true);
        $new_location_id = isset($_POST['tmgmt_event_location_id']) ? sanitize_text_field($_POST['tmgmt_event_location_id']) : '';
        if ($old_location_id !== $new_location_id) $tour_relevant_changes = true;

        if ($tour_relevant_changes) {
            // Flag Tour for NEW date
            if ($new_date) {
                $tour_posts = get_posts(array(
                    'post_type' => 'tmgmt_tour',
                    'numberposts' => 1,
                    'meta_query' => array(
                        array(
                            'key' => 'tmgmt_tour_date',
                            'value' => $new_date,
                            'compare' => '='
                        )
                    )
                ));
                if ($tour_posts) {
                    update_post_meta($tour_posts[0]->ID, 'tmgmt_tour_update_required', true);
                }
            }
            
            // Flag Tour for OLD date (if date changed and old date existed)
            if ($old_date && $old_date !== $new_date) {
                $tour_posts_old = get_posts(array(
                    'post_type' => 'tmgmt_tour',
                    'numberposts' => 1,
                    'meta_query' => array(
                        array(
                            'key' => 'tmgmt_tour_date',
                            'value' => $old_date,
                            'compare' => '='
                        )
                    )
                ));
                if ($tour_posts_old) {
                    update_post_meta($tour_posts_old[0]->ID, 'tmgmt_tour_update_required', true);
                }
            }
        }

        if ($old_status !== $new_status && !empty($new_status)) {
            $log_manager = new TMGMT_Log_Manager();
            
            // Get Log Template from Status Definition
            $args = array(
                'name'        => $new_status,
                'post_type'   => 'tmgmt_status_def',
                'post_status' => 'publish',
                'numberposts' => 1
            );
            $status_posts = get_posts($args);
            
            $message = 'Status geändert auf: ' . TMGMT_Event_Status::get_label($new_status);
            
            if (!empty($status_posts)) {
                $template = get_post_meta($status_posts[0]->ID, '_tmgmt_log_template', true);
                if (!empty($template)) {
                    $message = $template;
                }
            }

            $log_manager->log($post_id, 'status_change', $message);
        }

        // Handle Setlist Back-Link Logic
        $old_setlist_id = get_post_meta($post_id, '_tmgmt_selected_setlist', true);
        $new_setlist_id = isset($_POST['tmgmt_selected_setlist']) ? intval($_POST['tmgmt_selected_setlist']) : 0;

        if ($old_setlist_id && $old_setlist_id != $new_setlist_id) {
             $old_linked_event = get_post_meta($old_setlist_id, '_tmgmt_setlist_event', true);
             if ($old_linked_event == $post_id) {
                 delete_post_meta($old_setlist_id, '_tmgmt_setlist_event');
             }
        }

        if ($new_setlist_id && $new_setlist_id != $old_setlist_id) {
             $type = get_post_meta($new_setlist_id, '_tmgmt_setlist_type', true);
             if ($type === 'custom') {
                 update_post_meta($new_setlist_id, '_tmgmt_setlist_event', $post_id);
             }
        }

        $fields = array(
            // Event Details
            'tmgmt_event_date', 'tmgmt_event_start_time', 'tmgmt_event_arrival_time', 'tmgmt_event_departure_time',
            // Location (reference only, no duplicated address fields)
            'tmgmt_event_location_id',
            // Veranstalter
            'tmgmt_event_veranstalter_id',
            // Inquiry
            'tmgmt_inquiry_date', 'tmgmt_status',
            // Contract
            'tmgmt_fee', 'tmgmt_deposit',
            // Setlist
            'tmgmt_selected_setlist'
        );

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST[$field]));
            } else {
                update_post_meta($post_id, '_' . $field, '');
            }
        }
    }
}
