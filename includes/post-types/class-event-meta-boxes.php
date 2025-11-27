<?php

class TMGMT_Event_Meta_Boxes {

    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
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
            'tmgmt_venue_name' => 'Veranstaltungsort: Name',
            'tmgmt_venue_street' => 'Veranstaltungsort: Straße',
            'tmgmt_venue_number' => 'Veranstaltungsort: Nr.',
            'tmgmt_venue_zip' => 'Veranstaltungsort: PLZ',
            'tmgmt_venue_city' => 'Veranstaltungsort: Ort',
            'tmgmt_venue_country' => 'Veranstaltungsort: Land',
            'tmgmt_geo_lat' => 'Geodaten: Latitude',
            'tmgmt_geo_lng' => 'Geodaten: Longitude',
            'tmgmt_arrival_notes' => 'Hinweise Anreise / Bus',
            'tmgmt_contact_salutation' => 'Kontakt: Anrede',
            'tmgmt_contact_firstname' => 'Kontakt: Vorname',
            'tmgmt_contact_lastname' => 'Kontakt: Nachname',
            'tmgmt_contact_company' => 'Kontakt: Firma / Verein',
            'tmgmt_contact_street' => 'Kontakt: Straße',
            'tmgmt_contact_number' => 'Kontakt: Nr.',
            'tmgmt_contact_zip' => 'Kontakt: PLZ',
            'tmgmt_contact_city' => 'Kontakt: Ort',
            'tmgmt_contact_country' => 'Kontakt: Land',
            'tmgmt_contact_email_contract' => 'Kontakt: E-Mail (Vertrag)',
            'tmgmt_contact_phone_contract' => 'Kontakt: Telefon (Vertrag)',
            'tmgmt_contact_email_tech' => 'Kontakt: E-Mail (Technik)',
            'tmgmt_contact_phone_tech' => 'Kontakt: Telefon (Technik)',
            'tmgmt_contact_email_program' => 'Kontakt: E-Mail (Programm)',
            'tmgmt_contact_phone_program' => 'Kontakt: Telefon (Programm)',
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
            'tmgmt_contact_details',
            'Kontaktdaten',
            array($this, 'render_contact_details_box'),
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
        
        // Retrieve existing values
        $date = get_post_meta($post->ID, '_tmgmt_event_date', true);
        $start_time = get_post_meta($post->ID, '_tmgmt_event_start_time', true);
        $arrival_time = get_post_meta($post->ID, '_tmgmt_event_arrival_time', true);
        $departure_time = get_post_meta($post->ID, '_tmgmt_event_departure_time', true);
        
        $venue_name = get_post_meta($post->ID, '_tmgmt_venue_name', true);
        $venue_street = get_post_meta($post->ID, '_tmgmt_venue_street', true);
        $venue_number = get_post_meta($post->ID, '_tmgmt_venue_number', true);
        $venue_zip = get_post_meta($post->ID, '_tmgmt_venue_zip', true);
        $venue_city = get_post_meta($post->ID, '_tmgmt_venue_city', true);
        $venue_country = get_post_meta($post->ID, '_tmgmt_venue_country', true);
        
        $geo_lat = get_post_meta($post->ID, '_tmgmt_geo_lat', true);
        $geo_lng = get_post_meta($post->ID, '_tmgmt_geo_lng', true);
        
        $arrival_notes = get_post_meta($post->ID, '_tmgmt_arrival_notes', true);

        ?>
        <style>
            .tmgmt-row { display: flex; gap: 15px; margin-bottom: 10px; flex-wrap: wrap; }
            .tmgmt-field { flex: 1; min-width: 200px; }
            .tmgmt-field label { display: block; font-weight: 600; margin-bottom: 5px; }
            .tmgmt-field input, .tmgmt-field textarea { width: 100%; }
            .tmgmt-section-title { font-weight: bold; border-bottom: 1px solid #ccc; margin: 15px 0 10px; padding-bottom: 5px; }
            #tmgmt-map { height: 300px; width: 100%; margin-top: 10px; border: 1px solid #ccc; display: none; }
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
        <div class="tmgmt-row">
            <div class="tmgmt-field">
                <label for="tmgmt_event_departure_time">Geplante Abreisezeit</label>
                <input type="time" id="tmgmt_event_departure_time" name="tmgmt_event_departure_time" value="<?php echo esc_attr($departure_time); ?>">
            </div>
        </div>

        <div class="tmgmt-section-title">Ort aus Datenbank laden</div>
        <div class="tmgmt-row" style="position: relative;">
            <div class="tmgmt-field">
                <input type="text" id="tmgmt_location_search" placeholder="Ort suchen..." autocomplete="off" style="width: 100%;">
                <div id="tmgmt_location_search_results" style="position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #ccc; z-index: 100; max-height: 200px; overflow-y: auto; display: none; box-shadow: 0 2px 5px rgba(0,0,0,0.1);"></div>
            </div>
        </div>

        <div class="tmgmt-section-title">Adresse Veranstaltungsort</div>
        <div class="tmgmt-row">
            <div class="tmgmt-field">
                <label for="tmgmt_venue_name">Name des Veranstaltungsorts</label>
                <input type="text" id="tmgmt_venue_name" name="tmgmt_venue_name" value="<?php echo esc_attr($venue_name); ?>" placeholder="z.B. Stadthalle oder Grundschule">
            </div>
        </div>
        <div class="tmgmt-row">
            <div class="tmgmt-field" style="flex: 3;">
                <label for="tmgmt_venue_street">Straße</label>
                <input type="text" id="tmgmt_venue_street" name="tmgmt_venue_street" value="<?php echo esc_attr($venue_street); ?>">
            </div>
            <div class="tmgmt-field" style="flex: 1;">
                <label for="tmgmt_venue_number">Nr.</label>
                <input type="text" id="tmgmt_venue_number" name="tmgmt_venue_number" value="<?php echo esc_attr($venue_number); ?>">
            </div>
        </div>
        <div class="tmgmt-row">
            <div class="tmgmt-field" style="flex: 1;">
                <label for="tmgmt_venue_zip">PLZ</label>
                <input type="text" id="tmgmt_venue_zip" name="tmgmt_venue_zip" value="<?php echo esc_attr($venue_zip); ?>">
            </div>
            <div class="tmgmt-field" style="flex: 2;">
                <label for="tmgmt_venue_city">Ort</label>
                <input type="text" id="tmgmt_venue_city" name="tmgmt_venue_city" value="<?php echo esc_attr($venue_city); ?>">
            </div>
            <div class="tmgmt-field" style="flex: 2;">
                <label for="tmgmt_venue_country">Land</label>
                <input type="text" id="tmgmt_venue_country" name="tmgmt_venue_country" value="<?php echo esc_attr($venue_country); ?>">
            </div>
        </div>

        <div class="tmgmt-section-title">Geodaten</div>
        <div class="tmgmt-row">
            <div class="tmgmt-field">
                <label for="tmgmt_geo_lat">Latitude</label>
                <input type="text" id="tmgmt_geo_lat" name="tmgmt_geo_lat" value="<?php echo esc_attr($geo_lat); ?>" readonly>
            </div>
            <div class="tmgmt-field">
                <label for="tmgmt_geo_lng">Longitude</label>
                <input type="text" id="tmgmt_geo_lng" name="tmgmt_geo_lng" value="<?php echo esc_attr($geo_lng); ?>" readonly>
            </div>
            <div class="tmgmt-field" style="display: flex; align-items: flex-end; gap: 10px;">
                <button type="button" id="tmgmt-geocode-btn" class="button button-secondary">Adresse auflösen</button>
                <button type="button" id="tmgmt-save-location-btn" class="button button-secondary">Als neuen Ort speichern</button>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Search
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
                                        data-street="${item.street}"
                                        data-number="${item.number}"
                                        data-zip="${item.zip}"
                                        data-city="${item.city}"
                                        data-country="${item.country}"
                                        data-lat="${item.lat}"
                                        data-lng="${item.lng}"
                                        data-notes="${item.notes}"
                                        data-name="${item.title}"
                                    ><strong>${item.title}</strong><br><small>${item.street} ${item.number}, ${item.zip} ${item.city}</small></div>`;
                                });
                                $('#tmgmt_location_search_results').html(html).show();
                            } else {
                                $('#tmgmt_location_search_results').hide();
                            }
                        }
                    });
                }, 300);
            });

            // Select
            $(document).on('click', '.tmgmt-location-result', function() {
                const data = $(this).data();
                $('#tmgmt_venue_name').val(data.name);
                $('#tmgmt_venue_street').val(data.street);
                $('#tmgmt_venue_number').val(data.number);
                $('#tmgmt_venue_zip').val(data.zip);
                $('#tmgmt_venue_city').val(data.city);
                $('#tmgmt_venue_country').val(data.country);
                $('#tmgmt_geo_lat').val(data.lat);
                $('#tmgmt_geo_lng').val(data.lng);
                $('#tmgmt_arrival_notes').val(data.notes);
                
                $('#tmgmt_location_search_results').hide();
                $('#tmgmt_location_search').val('');
            });

            // Save
            $('#tmgmt-save-location-btn').on('click', function() {
                const name = $('#tmgmt_venue_name').val();
                if (!name) {
                    Swal.fire('Fehlende Angabe', 'Bitte geben Sie einen Namen für den Veranstaltungsort ein.', 'warning');
                    return;
                }
                
                const btn = $(this);
                btn.prop('disabled', true).text('Speichere...');
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'tmgmt_save_location_from_event',
                        name: name,
                        street: $('#tmgmt_venue_street').val(),
                        number: $('#tmgmt_venue_number').val(),
                        zip: $('#tmgmt_venue_zip').val(),
                        city: $('#tmgmt_venue_city').val(),
                        country: $('#tmgmt_venue_country').val(),
                        lat: $('#tmgmt_geo_lat').val(),
                        lng: $('#tmgmt_geo_lng').val(),
                        notes: $('#tmgmt_arrival_notes').val()
                    },
                    success: function(res) {
                        btn.prop('disabled', false).text('Als neuen Ort speichern');
                        if (res.success) {
                            Swal.fire('Gespeichert', 'Ort erfolgreich gespeichert!', 'success');
                        } else {
                            Swal.fire('Fehler', 'Fehler: ' + res.data, 'error');
                        }
                    },
                    error: function() {
                        btn.prop('disabled', false).text('Als neuen Ort speichern');
                        Swal.fire('Fehler', 'Ein Fehler ist aufgetreten.', 'error');
                    }
                });
            });
            
            // Close search on click outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#tmgmt_location_search, #tmgmt_location_search_results').length) {
                    $('#tmgmt_location_search_results').hide();
                }
            });
        });
        </script>
        
        <div id="tmgmt-map-container">
            <div id="tmgmt-map"></div>
        </div>

        <div class="tmgmt-section-title">Hinweise</div>
        <div class="tmgmt-row">
            <div class="tmgmt-field">
                <label for="tmgmt_arrival_notes">Hinweise Anreise / Bus</label>
                <textarea id="tmgmt_arrival_notes" name="tmgmt_arrival_notes" rows="4"><?php echo esc_textarea($arrival_notes); ?></textarea>
            </div>
        </div>
        <?php
    }

    public function render_contact_details_box($post) {
        $salutation = get_post_meta($post->ID, '_tmgmt_contact_salutation', true);
        $firstname = get_post_meta($post->ID, '_tmgmt_contact_firstname', true);
        $lastname = get_post_meta($post->ID, '_tmgmt_contact_lastname', true);
        $company = get_post_meta($post->ID, '_tmgmt_contact_company', true);
        
        $contact_street = get_post_meta($post->ID, '_tmgmt_contact_street', true);
        $contact_number = get_post_meta($post->ID, '_tmgmt_contact_number', true);
        $contact_zip = get_post_meta($post->ID, '_tmgmt_contact_zip', true);
        $contact_city = get_post_meta($post->ID, '_tmgmt_contact_city', true);
        $contact_country = get_post_meta($post->ID, '_tmgmt_contact_country', true);

        $email_contract = get_post_meta($post->ID, '_tmgmt_contact_email_contract', true);
        $phone_contract = get_post_meta($post->ID, '_tmgmt_contact_phone_contract', true);
        
        $name_tech = get_post_meta($post->ID, '_tmgmt_contact_name_tech', true);
        $email_tech = get_post_meta($post->ID, '_tmgmt_contact_email_tech', true);
        $phone_tech = get_post_meta($post->ID, '_tmgmt_contact_phone_tech', true);
        
        $name_program = get_post_meta($post->ID, '_tmgmt_contact_name_program', true);
        $email_program = get_post_meta($post->ID, '_tmgmt_contact_email_program', true);
        $phone_program = get_post_meta($post->ID, '_tmgmt_contact_phone_program', true);
        ?>
        
        <div class="tmgmt-row">
            <div class="tmgmt-field">
                <label for="tmgmt_contact_salutation">Anrede</label>
                <input type="text" id="tmgmt_contact_salutation" name="tmgmt_contact_salutation" value="<?php echo esc_attr($salutation); ?>">
            </div>
            <div class="tmgmt-field">
                <label for="tmgmt_contact_firstname">Vorname</label>
                <input type="text" id="tmgmt_contact_firstname" name="tmgmt_contact_firstname" value="<?php echo esc_attr($firstname); ?>">
            </div>
            <div class="tmgmt-field">
                <label for="tmgmt_contact_lastname">Nachname</label>
                <input type="text" id="tmgmt_contact_lastname" name="tmgmt_contact_lastname" value="<?php echo esc_attr($lastname); ?>">
            </div>
        </div>
        <div class="tmgmt-row">
            <div class="tmgmt-field">
                <label for="tmgmt_contact_company">Firma / Verein</label>
                <input type="text" id="tmgmt_contact_company" name="tmgmt_contact_company" value="<?php echo esc_attr($company); ?>">
            </div>
        </div>

        <div class="tmgmt-section-title">Postadresse (Kontaktperson)</div>
        <div class="tmgmt-row">
            <div class="tmgmt-field" style="flex: 3;">
                <label for="tmgmt_contact_street">Straße</label>
                <input type="text" id="tmgmt_contact_street" name="tmgmt_contact_street" value="<?php echo esc_attr($contact_street); ?>">
            </div>
            <div class="tmgmt-field" style="flex: 1;">
                <label for="tmgmt_contact_number">Nr.</label>
                <input type="text" id="tmgmt_contact_number" name="tmgmt_contact_number" value="<?php echo esc_attr($contact_number); ?>">
            </div>
        </div>
        <div class="tmgmt-row">
            <div class="tmgmt-field" style="flex: 1;">
                <label for="tmgmt_contact_zip">PLZ</label>
                <input type="text" id="tmgmt_contact_zip" name="tmgmt_contact_zip" value="<?php echo esc_attr($contact_zip); ?>">
            </div>
            <div class="tmgmt-field" style="flex: 2;">
                <label for="tmgmt_contact_city">Ort</label>
                <input type="text" id="tmgmt_contact_city" name="tmgmt_contact_city" value="<?php echo esc_attr($contact_city); ?>">
            </div>
            <div class="tmgmt-field" style="flex: 2;">
                <label for="tmgmt_contact_country">Land</label>
                <input type="text" id="tmgmt_contact_country" name="tmgmt_contact_country" value="<?php echo esc_attr($contact_country); ?>">
            </div>
        </div>

        <div class="tmgmt-section-title">Kommunikation</div>
        <div class="tmgmt-row">
            <div class="tmgmt-field">
                <label for="tmgmt_contact_email_contract">E-Mail (Vertrag)</label>
                <input type="email" id="tmgmt_contact_email_contract" name="tmgmt_contact_email_contract" value="<?php echo esc_attr($email_contract); ?>">
            </div>
            <div class="tmgmt-field">
                <label for="tmgmt_contact_phone_contract">Telefon (Vertrag)</label>
                <input type="tel" id="tmgmt_contact_phone_contract" name="tmgmt_contact_phone_contract" value="<?php echo esc_attr($phone_contract); ?>">
            </div>
        </div>
        <div class="tmgmt-row">
            <div class="tmgmt-field">
                <label for="tmgmt_contact_name_tech">Name (Technik)</label>
                <input type="text" id="tmgmt_contact_name_tech" name="tmgmt_contact_name_tech" value="<?php echo esc_attr($name_tech); ?>">
            </div>
            <div class="tmgmt-field">
                <label for="tmgmt_contact_email_tech">E-Mail (Technik)</label>
                <input type="email" id="tmgmt_contact_email_tech" name="tmgmt_contact_email_tech" value="<?php echo esc_attr($email_tech); ?>">
            </div>
            <div class="tmgmt-field">
                <label for="tmgmt_contact_phone_tech">Telefon (Technik)</label>
                <input type="tel" id="tmgmt_contact_phone_tech" name="tmgmt_contact_phone_tech" value="<?php echo esc_attr($phone_tech); ?>">
            </div>
        </div>
        <div class="tmgmt-row">
            <div class="tmgmt-field">
                <label for="tmgmt_contact_name_program">Name (Programm)</label>
                <input type="text" id="tmgmt_contact_name_program" name="tmgmt_contact_name_program" value="<?php echo esc_attr($name_program); ?>">
            </div>
            <div class="tmgmt-field">
                <label for="tmgmt_contact_email_program">E-Mail (Programm)</label>
                <input type="email" id="tmgmt_contact_email_program" name="tmgmt_contact_email_program" value="<?php echo esc_attr($email_program); ?>">
            </div>
            <div class="tmgmt-field">
                <label for="tmgmt_contact_phone_program">Telefon (Programm)</label>
                <input type="tel" id="tmgmt_contact_phone_program" name="tmgmt_contact_phone_program" value="<?php echo esc_attr($phone_program); ?>">
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

        // Check for changes relevant to Tour Planning (Time, Date, Status, Geo)
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

        // 4. Geo
        $old_lat = get_post_meta($post_id, '_tmgmt_geo_lat', true);
        $new_lat = isset($_POST['tmgmt_geo_lat']) ? sanitize_text_field($_POST['tmgmt_geo_lat']) : '';
        if ($old_lat !== $new_lat) $tour_relevant_changes = true;

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
            'tmgmt_venue_name', 'tmgmt_venue_street', 'tmgmt_venue_number', 'tmgmt_venue_zip', 'tmgmt_venue_city', 'tmgmt_venue_country',
            'tmgmt_geo_lat', 'tmgmt_geo_lng', 'tmgmt_arrival_notes',
            // Contact Details
            'tmgmt_contact_salutation', 'tmgmt_contact_firstname', 'tmgmt_contact_lastname', 'tmgmt_contact_company',
            'tmgmt_contact_street', 'tmgmt_contact_number', 'tmgmt_contact_zip', 'tmgmt_contact_city', 'tmgmt_contact_country',
            'tmgmt_contact_email_contract', 'tmgmt_contact_phone_contract',
            'tmgmt_contact_name_tech', 'tmgmt_contact_email_tech', 'tmgmt_contact_phone_tech',
            'tmgmt_contact_name_program', 'tmgmt_contact_email_program', 'tmgmt_contact_phone_program',
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
                // Handle unchecked checkboxes or empty fields if necessary, 
                // but for text inputs, if they are missing from POST it usually means they weren't on the form.
                // However, if the user clears the input, it comes as empty string.
                // If we want to delete meta when empty:
                // delete_post_meta($post_id, '_' . $field);
                // For now, updating with empty string is fine.
                update_post_meta($post_id, '_' . $field, '');
            }
        }
    }
}
