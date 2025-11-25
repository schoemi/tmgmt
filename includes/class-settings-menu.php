<?php

class TMGMT_Settings_Menu {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_filter('parent_file', array($this, 'highlight_settings_menu'));
        add_filter('submenu_file', array($this, 'highlight_submenu_item'));
    }

    public function add_settings_menu() {
        add_submenu_page(
            'edit.php?post_type=event',
            'Einstellungen',
            'Einstellungen',
            'manage_options',
            'tmgmt-settings',
            array($this, 'render_settings_page')
        );
        
        add_submenu_page(
            'tmgmt-settings-hidden', // Hidden parent to not show in menu but allow access
            'Routenplanung',
            'Routenplanung',
            'manage_options',
            'tmgmt-route-settings',
            array($this, 'render_route_settings_page')
        );

        add_submenu_page(
            'tmgmt-settings-hidden',
            'Frontend Layout',
            'Frontend Layout',
            'manage_options',
            'tmgmt-frontend-layout',
            array($this, 'render_frontend_layout_page')
        );
    }

    public function register_settings() {
        // Route Planning Settings
        register_setting('tmgmt_route_options', 'tmgmt_route_start_name');
        register_setting('tmgmt_route_options', 'tmgmt_route_start_address');
        register_setting('tmgmt_route_options', 'tmgmt_route_start_zip');
        register_setting('tmgmt_route_options', 'tmgmt_route_start_city');
        register_setting('tmgmt_route_options', 'tmgmt_route_start_lat');
        register_setting('tmgmt_route_options', 'tmgmt_route_start_lng');
        
        register_setting('tmgmt_route_options', 'tmgmt_route_buffer_arrival', array('type' => 'integer', 'default' => 30));
        register_setting('tmgmt_route_options', 'tmgmt_route_min_buffer_arrival', array('type' => 'integer', 'default' => 15));
        register_setting('tmgmt_route_options', 'tmgmt_route_max_idle_time', array('type' => 'integer', 'default' => 120));
        register_setting('tmgmt_route_options', 'tmgmt_route_show_duration', array('type' => 'integer', 'default' => 60));
        register_setting('tmgmt_route_options', 'tmgmt_route_buffer_departure', array('type' => 'integer', 'default' => 30));
        register_setting('tmgmt_route_options', 'tmgmt_route_loading_time', array('type' => 'integer', 'default' => 60));
        register_setting('tmgmt_route_options', 'tmgmt_route_bus_factor', array('type' => 'number', 'default' => 1.0));
        register_setting('tmgmt_route_options', 'tmgmt_route_status_filter', array('type' => 'array', 'default' => array()));
        
        register_setting('tmgmt_route_options', 'tmgmt_ors_api_key');
        register_setting('tmgmt_route_options', 'tmgmt_here_api_key');

        // Frontend Layout Settings
        register_setting('tmgmt_frontend_layout', 'tmgmt_frontend_layout_settings', array(
            'type' => 'string', // Stored as JSON string
            'default' => '{}'
        ));
    }

    public function highlight_settings_menu($parent_file) {
        global $current_screen;
        if (!is_object($current_screen)) return $parent_file;
        
        $cpts = array('tmgmt_status_def', 'tmgmt_action', 'tmgmt_email_template', 'tmgmt_webhook', 'tmgmt_kanban_col');
        if (in_array($current_screen->post_type, $cpts)) {
            return 'edit.php?post_type=event';
        }
        return $parent_file;
    }

    public function highlight_submenu_item($submenu_file) {
        global $current_screen;
        if (!is_object($current_screen)) return $submenu_file;

        $cpts = array('tmgmt_status_def', 'tmgmt_action', 'tmgmt_email_template', 'tmgmt_webhook', 'tmgmt_kanban_col');
        if (in_array($current_screen->post_type, $cpts)) {
            return 'tmgmt-settings';
        }
        return $submenu_file;
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>TMGMT Einstellungen</h1>
            <p>Verwalten Sie hier die Konfiguration für das Event Management.</p>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
                
                <div class="card" style="padding: 0; overflow: hidden;">
                    <div style="padding: 15px; background: #f0f0f1; border-bottom: 1px solid #c3c4c7;">
                        <h2 style="margin:0; font-size: 16px;">Status Definitionen</h2>
                    </div>
                    <div style="padding: 15px;">
                        <p>Definieren Sie die Status für den Workflow (z.B. Option, Fest, Abgesagt) und deren Regeln.</p>
                        <a href="edit.php?post_type=tmgmt_status_def" class="button button-primary">Verwalten</a>
                    </div>
                </div>

                <div class="card" style="padding: 0; overflow: hidden;">
                    <div style="padding: 15px; background: #f0f0f1; border-bottom: 1px solid #c3c4c7;">
                        <h2 style="margin:0; font-size: 16px;">Aktionen</h2>
                    </div>
                    <div style="padding: 15px;">
                        <p>Erstellen Sie wiederverwendbare Aktionen (E-Mails, Webhooks), die bei Statuswechseln ausgelöst werden.</p>
                        <a href="edit.php?post_type=tmgmt_action" class="button button-primary">Verwalten</a>
                    </div>
                </div>

                <div class="card" style="padding: 0; overflow: hidden;">
                    <div style="padding: 15px; background: #f0f0f1; border-bottom: 1px solid #c3c4c7;">
                        <h2 style="margin:0; font-size: 16px;">E-Mail Vorlagen</h2>
                    </div>
                    <div style="padding: 15px;">
                        <p>Verwalten Sie Textvorlagen für E-Mails mit Platzhaltern.</p>
                        <a href="edit.php?post_type=tmgmt_email_template" class="button button-primary">Verwalten</a>
                    </div>
                </div>

                <div class="card" style="padding: 0; overflow: hidden;">
                    <div style="padding: 15px; background: #f0f0f1; border-bottom: 1px solid #c3c4c7;">
                        <h2 style="margin:0; font-size: 16px;">Webhooks</h2>
                    </div>
                    <div style="padding: 15px;">
                        <p>Konfigurieren Sie externe Endpunkte für Automatisierungen.</p>
                        <a href="edit.php?post_type=tmgmt_webhook" class="button button-primary">Verwalten</a>
                    </div>
                </div>

                <div class="card" style="padding: 0; overflow: hidden;">
                    <div style="padding: 15px; background: #f0f0f1; border-bottom: 1px solid #c3c4c7;">
                        <h2 style="margin:0; font-size: 16px;">Kanban Spalten</h2>
                    </div>
                    <div style="padding: 15px;">
                        <p>Konfigurieren Sie die Spalten des Dashboards und ordnen Sie Status zu.</p>
                        <a href="edit.php?post_type=tmgmt_kanban_col" class="button button-primary">Verwalten</a>
                    </div>
                </div>

                <div class="card" style="padding: 0; overflow: hidden;">
                    <div style="padding: 15px; background: #f0f0f1; border-bottom: 1px solid #c3c4c7;">
                        <h2 style="margin:0; font-size: 16px;">Routenplanung</h2>
                    </div>
                    <div style="padding: 15px;">
                        <p>Konfigurieren Sie Startpunkt, Pufferzeiten und API-Keys für die Tourenberechnung.</p>
                        <a href="admin.php?page=tmgmt-route-settings" class="button button-primary">Konfigurieren</a>
                    </div>
                </div>

                <div class="card" style="padding: 0; overflow: hidden;">
                    <div style="padding: 15px; background: #f0f0f1; border-bottom: 1px solid #c3c4c7;">
                        <h2 style="margin:0; font-size: 16px;">Frontend Layout</h2>
                    </div>
                    <div style="padding: 15px;">
                        <p>Passen Sie die Reihenfolge und das Verhalten der Sektionen im Frontend-Modal an.</p>
                        <a href="admin.php?page=tmgmt-frontend-layout" class="button button-primary">Layout anpassen</a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_route_settings_page() {
        ?>
        <div class="wrap">
            <h1>Routenplanung Einstellungen</h1>
            <form method="post" action="options.php">
                <?php settings_fields('tmgmt_route_options'); ?>
                <?php do_settings_sections('tmgmt_route_options'); ?>
                
                <h2 class="title">API Konfiguration</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="tmgmt_ors_api_key">OpenRouteService API Key</label></th>
                        <td>
                            <input name="tmgmt_ors_api_key" type="text" id="tmgmt_ors_api_key" value="<?php echo esc_attr(get_option('tmgmt_ors_api_key')); ?>" class="regular-text">
                            <p class="description">Benötigt für Routenberechnungen. <a href="https://openrouteservice.org/" target="_blank">Hier kostenlos erstellen</a>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmgmt_here_api_key">HERE Maps API Key</label></th>
                        <td>
                            <input name="tmgmt_here_api_key" type="text" id="tmgmt_here_api_key" value="<?php echo esc_attr(get_option('tmgmt_here_api_key')); ?>" class="regular-text">
                            <p class="description">Alternative für Routenberechnungen. <a href="https://developer.here.com/" target="_blank">Hier kostenlos erstellen</a>.</p>
                        </td>
                    </tr>
                </table>
                
                <h2 class="title">Standard-Startpunkt (Proberaum)</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="tmgmt_route_start_name">Bezeichnung</label></th>
                        <td><input name="tmgmt_route_start_name" type="text" id="tmgmt_route_start_name" value="<?php echo esc_attr(get_option('tmgmt_route_start_name')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmgmt_route_start_address">Straße und Hausnummer</label></th>
                        <td><input name="tmgmt_route_start_address" type="text" id="tmgmt_route_start_address" value="<?php echo esc_attr(get_option('tmgmt_route_start_address')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmgmt_route_start_zip">PLZ</label></th>
                        <td><input name="tmgmt_route_start_zip" type="text" id="tmgmt_route_start_zip" value="<?php echo esc_attr(get_option('tmgmt_route_start_zip')); ?>" class="small-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmgmt_route_start_city">Ort</label></th>
                        <td><input name="tmgmt_route_start_city" type="text" id="tmgmt_route_start_city" value="<?php echo esc_attr(get_option('tmgmt_route_start_city')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmgmt_route_start_lat">Geodaten (Lat / Lon)</label></th>
                        <td>
                            <input name="tmgmt_route_start_lat" type="text" id="tmgmt_route_start_lat" value="<?php echo esc_attr(get_option('tmgmt_route_start_lat')); ?>" placeholder="Latitude" class="regular-text" style="width: 150px;">
                            <input name="tmgmt_route_start_lng" type="text" id="tmgmt_route_start_lng" value="<?php echo esc_attr(get_option('tmgmt_route_start_lng')); ?>" placeholder="Longitude" class="regular-text" style="width: 150px;">
                            <button type="button" class="button button-secondary" id="tmgmt-geocode-btn">Geodaten aus Adresse ermitteln</button>
                            <p class="description">Koordinaten für die Routenberechnung.</p>
                            <p id="tmgmt-geocode-msg" style="margin-top:5px; color:#666;"></p>
                        </td>
                    </tr>
                </table>

                <h2 class="title">Routenplanungsoptionen</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="tmgmt_route_buffer_arrival">Pufferzeit Anreise (Minuten)</label></th>
                        <td>
                            <input name="tmgmt_route_buffer_arrival" type="number" id="tmgmt_route_buffer_arrival" value="<?php echo esc_attr(get_option('tmgmt_route_buffer_arrival', 30)); ?>" class="small-text">
                            <p class="description">Zusätzliche Zeit, die zur reinen Fahrzeit addiert wird (Standard).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmgmt_route_min_buffer_arrival">Minimale Pufferzeit vor Auftritt (Minuten)</label></th>
                        <td>
                            <input name="tmgmt_route_min_buffer_arrival" type="number" id="tmgmt_route_min_buffer_arrival" value="<?php echo esc_attr(get_option('tmgmt_route_min_buffer_arrival', 15)); ?>" class="small-text">
                            <p class="description">Absolute Untergrenze vor Showbeginn. Wird diese unterschritten, gilt die Tour als fehlerhaft.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmgmt_route_max_idle_time">Zeit für Leerlauf-Warnung (Minuten)</label></th>
                        <td>
                            <input name="tmgmt_route_max_idle_time" type="number" id="tmgmt_route_max_idle_time" value="<?php echo esc_attr(get_option('tmgmt_route_max_idle_time', 120)); ?>" class="small-text">
                            <p class="description">Warnung anzeigen, wenn die Wartezeit vor Ort diesen Wert überschreitet.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmgmt_route_show_duration">Dauer der Show (Minuten)</label></th>
                        <td>
                            <input name="tmgmt_route_show_duration" type="number" id="tmgmt_route_show_duration" value="<?php echo esc_attr(get_option('tmgmt_route_show_duration', 60)); ?>" class="small-text">
                            <p class="description">Standard-Dauer des Auftritts für die Berechnung der Abreisezeit.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmgmt_route_buffer_departure">Pufferzeit Abreise (Minuten)</label></th>
                        <td>
                            <input name="tmgmt_route_buffer_departure" type="number" id="tmgmt_route_buffer_departure" value="<?php echo esc_attr(get_option('tmgmt_route_buffer_departure', 30)); ?>" class="small-text">
                            <p class="description">Zeit für Abbau und Verabschiedung nach dem Auftritt.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmgmt_route_loading_time">Verladen / Vorlaufzeit (Minuten)</label></th>
                        <td>
                            <input name="tmgmt_route_loading_time" type="number" id="tmgmt_route_loading_time" value="<?php echo esc_attr(get_option('tmgmt_route_loading_time', 60)); ?>" class="small-text">
                            <p class="description">Zeit, die vor der Abfahrt für das Beladen benötigt wird.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmgmt_route_bus_factor">Busfaktor</label></th>
                        <td>
                            <input name="tmgmt_route_bus_factor" type="number" step="0.1" id="tmgmt_route_bus_factor" value="<?php echo esc_attr(get_option('tmgmt_route_bus_factor', 1.0)); ?>" class="small-text">
                            <p class="description">Faktor zur Berechnung der Fahrzeit (z.B. 1.2 = 20% langsamer als PKW).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Berücksichtigte Status</th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><span>Berücksichtigte Status</span></legend>
                                <?php
                                $statuses = get_posts(array(
                                    'post_type' => 'tmgmt_status_def',
                                    'numberposts' => -1,
                                    'orderby' => 'menu_order',
                                    'order' => 'ASC'
                                ));
                                $selected_statuses = get_option('tmgmt_route_status_filter', array());
                                if (!is_array($selected_statuses)) $selected_statuses = array();
                                
                                if ($statuses) {
                                    foreach ($statuses as $status) {
                                        $checked = in_array($status->ID, $selected_statuses) ? 'checked' : '';
                                        echo '<label><input type="checkbox" name="tmgmt_route_status_filter[]" value="' . esc_attr($status->ID) . '" ' . $checked . '> ' . esc_html($status->post_title) . '</label><br>';
                                    }
                                } else {
                                    echo '<p class="description">Keine Status-Definitionen gefunden.</p>';
                                }
                                ?>
                                <p class="description">Wählen Sie die Status aus, die bei der Routenplanung berücksichtigt werden sollen (z.B. nur "Fest gebucht").</p>
                            </fieldset>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
                <a href="admin.php?page=tmgmt-settings" class="button button-secondary">Zurück zur Übersicht</a>
            </form>
            
            <script>
            document.getElementById('tmgmt-geocode-btn').addEventListener('click', function() {
                var street = document.getElementById('tmgmt_route_start_address').value;
                var zip = document.getElementById('tmgmt_route_start_zip').value;
                var city = document.getElementById('tmgmt_route_start_city').value;
                var msg = document.getElementById('tmgmt-geocode-msg');
                
                if (!street || !city) {
                    msg.style.color = '#d63638';
                    msg.textContent = 'Bitte Straße und Ort ausfüllen.';
                    return;
                }
                
                var query = street + ', ' + zip + ' ' + city;
                msg.style.color = '#666';
                msg.textContent = 'Suche Geodaten...';
                
                fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(query))
                    .then(response => response.json())
                    .then(data => {
                        if (data && data.length > 0) {
                            document.getElementById('tmgmt_route_start_lat').value = data[0].lat;
                            document.getElementById('tmgmt_route_start_lng').value = data[0].lon;
                            msg.style.color = '#00a32a';
                            msg.textContent = 'Gefunden!';
                        } else {
                            msg.style.color = '#d63638';
                            msg.textContent = 'Keine Geodaten gefunden.';
                        }
                    })
                    .catch(err => {
                        msg.style.color = '#d63638';
                        msg.textContent = 'Fehler bei der Abfrage.';
                        console.error(err);
                    });
            });
            </script>
        </div>
        <?php
    }

    public function render_frontend_layout_page() {
        if (isset($_POST['submit_layout'])) {
            check_admin_referer('tmgmt_save_layout');
            $layout_data = array();
            $sections = array(
                'inquiry_details', 
                'event_details', 
                'planning',
                'contact_details', 
                'other_contacts',
                'contract_details', 
                'status_box',
                'notes', 
                'files', 
                'map',
                'logs'
            );
            
            foreach ($sections as $sec) {
                $layout_data[$sec] = array(
                    'desktop' => array(
                        'order' => intval($_POST[$sec . '_desktop_order']),
                        'collapsed' => isset($_POST[$sec . '_desktop_collapsed'])
                    ),
                    'mobile' => array(
                        'order' => intval($_POST[$sec . '_mobile_order']),
                        'collapsed' => isset($_POST[$sec . '_mobile_collapsed'])
                    )
                );
            }
            
            update_option('tmgmt_frontend_layout_settings', json_encode($layout_data));
            echo '<div class="notice notice-success is-dismissible"><p>Layout gespeichert.</p></div>';
        }

        $saved_layout = json_decode(get_option('tmgmt_frontend_layout_settings', '{}'), true);
        
        // Defaults
        $sections = array(
            'inquiry_details' => 'Anfragedaten',
            'event_details' => 'Veranstaltungsdaten',
            'planning' => 'Planung',
            'contact_details' => 'Kontaktdaten',
            'other_contacts' => 'Weitere Ansprechpartner',
            'contract_details' => 'Vertragsdaten',
            'status_box' => 'Status & Aktionen',
            'notes' => 'Notizen',
            'files' => 'Dateien / Anhänge',
            'map' => 'Karte',
            'logs' => 'Logbuch / Kommunikation'
        );

        // Prepare data for sorting
        $rows = array();
        $i = 1;
        foreach ($sections as $key => $label) {
            $d_order = isset($saved_layout[$key]['desktop']['order']) ? $saved_layout[$key]['desktop']['order'] : $i;
            $d_collapsed = isset($saved_layout[$key]['desktop']['collapsed']) ? $saved_layout[$key]['desktop']['collapsed'] : false;
            $m_order = isset($saved_layout[$key]['mobile']['order']) ? $saved_layout[$key]['mobile']['order'] : $i;
            $m_collapsed = isset($saved_layout[$key]['mobile']['collapsed']) ? $saved_layout[$key]['mobile']['collapsed'] : false;
            
            $rows[] = array(
                'key' => $key,
                'label' => $label,
                'desktop_order' => $d_order,
                'desktop_collapsed' => $d_collapsed,
                'mobile_order' => $m_order,
                'mobile_collapsed' => $m_collapsed
            );
            $i++;
        }

        // Sorting logic
        $orderby = isset($_GET['orderby']) ? $_GET['orderby'] : 'label';
        $order = isset($_GET['order']) ? strtoupper($_GET['order']) : 'ASC';
        
        // Validate orderby
        $allowed_sort_columns = array('label', 'desktop_order', 'mobile_order');
        if (!in_array($orderby, $allowed_sort_columns)) {
            $orderby = 'label';
        }

        usort($rows, function($a, $b) use ($orderby, $order) {
            $valA = $a[$orderby];
            $valB = $b[$orderby];
            
            if ($valA == $valB) return 0;
            
            if ($order === 'ASC') {
                return $valA < $valB ? -1 : 1;
            } else {
                return $valA > $valB ? -1 : 1;
            }
        });

        // Helper for sort links
        $get_sort_link = function($col_name, $label_text) use ($orderby, $order) {
            $new_order = ($orderby === $col_name && $order === 'ASC') ? 'DESC' : 'ASC';
            $url = add_query_arg(array('orderby' => $col_name, 'order' => $new_order));
            $indicator = '';
            if ($orderby === $col_name) {
                $indicator = ($order === 'ASC') ? ' &uarr;' : ' &darr;';
            }
            return '<a href="' . esc_url($url) . '">' . esc_html($label_text) . $indicator . '</a>';
        };
        
        ?>
        <div class="wrap">
            <h1>Frontend Layout Konfiguration</h1>
            <form method="post">
                <?php wp_nonce_field('tmgmt_save_layout'); ?>
                
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php echo $get_sort_link('label', 'Sektion'); ?></th>
                            <th colspan="2">Desktop</th>
                            <th colspan="2">Mobile</th>
                        </tr>
                        <tr>
                            <th></th>
                            <th><?php echo $get_sort_link('desktop_order', 'Reihenfolge'); ?></th>
                            <th>Initial eingeklappt</th>
                            <th><?php echo $get_sort_link('mobile_order', 'Reihenfolge'); ?></th>
                            <th>Initial eingeklappt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        foreach ($rows as $row): 
                            $key = $row['key'];
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($row['label']); ?></strong></td>
                            <td>
                                <input type="number" name="<?php echo $key; ?>_desktop_order" value="<?php echo $row['desktop_order']; ?>" min="1" max="20" style="width:60px;">
                            </td>
                            <td>
                                <input type="checkbox" name="<?php echo $key; ?>_desktop_collapsed" <?php checked($row['desktop_collapsed']); ?>>
                            </td>
                            <td>
                                <input type="number" name="<?php echo $key; ?>_mobile_order" value="<?php echo $row['mobile_order']; ?>" min="1" max="20" style="width:60px;">
                            </td>
                            <td>
                                <input type="checkbox" name="<?php echo $key; ?>_mobile_collapsed" <?php checked($row['mobile_collapsed']); ?>>
                            </td>
                        </tr>
                        <?php 
                        endforeach; 
                        ?>
                    </tbody>
                </table>
                
                <p class="submit">
                    <button type="submit" name="submit_layout" class="button button-primary">Einstellungen speichern</button>
                    <a href="admin.php?page=tmgmt-settings" class="button">Zurück</a>
                </p>
            </form>
        </div>
        <?php
    }
}
