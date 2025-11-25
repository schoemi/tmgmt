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
                        <p>Konfigurieren Sie den Startpunkt (Proberaum) und Pufferzeiten für die Routenberechnung.</p>
                        <a href="admin.php?page=tmgmt-route-settings" class="button button-primary">Verwalten</a>
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
}
