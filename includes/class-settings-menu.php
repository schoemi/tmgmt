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
            'tmgmt_manage_settings',
            'tmgmt-settings',
            array($this, 'render_settings_page')
        );
        
        add_submenu_page(
            'tmgmt-settings-hidden', // Hidden parent to not show in menu but allow access
            'Routenplanung',
            'Routenplanung',
            'tmgmt_manage_settings',
            'tmgmt-route-settings',
            array($this, 'render_route_settings_page')
        );

        add_submenu_page(
            'tmgmt-settings-hidden',
            'Frontend Layout',
            'Frontend Layout',
            'tmgmt_manage_settings',
            'tmgmt-frontend-layout',
            array($this, 'render_frontend_layout_page')
        );

        add_submenu_page(
            'tmgmt-settings-hidden',
            'Allgemeine Einstellungen',
            'Allgemeine Einstellungen',
            'tmgmt_manage_settings',
            'tmgmt-general-settings',
            array($this, 'render_general_settings_page')
        );

        add_submenu_page(
            'tmgmt-settings-hidden',
            'Organisation',
            'Organisation',
            'tmgmt_manage_settings',
            'tmgmt-organization-settings',
            array($this, 'render_organization_settings_page')
        );

        add_submenu_page(
            'tmgmt-settings-hidden',
            'PDF Export',
            'PDF Export',
            'tmgmt_manage_settings',
            'tmgmt-pdf-settings',
            array($this, 'render_pdf_settings_page')
        );

        add_submenu_page(
            'tmgmt-settings-hidden',
            'Live Tracking',
            'Live Tracking',
            'tmgmt_manage_settings',
            'tmgmt-live-tracking-settings',
            array($this, 'render_live_tracking_settings_page')
        );

        add_submenu_page(
            'tmgmt-settings-hidden',
            'Berechtigungen',
            'Berechtigungen',
            'tmgmt_manage_settings',
            'tmgmt-permissions-settings',
            array($this, 'render_permissions_settings_page')
        );
    }

    public function register_settings() {
        // Permissions Settings
        register_setting('tmgmt_permissions_options', 'tmgmt_role_caps');

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

        // Live Tracking Settings
        register_setting('tmgmt_live_tracking_options', 'tmgmt_live_test_mode_active');
        register_setting('tmgmt_live_tracking_options', 'tmgmt_live_test_lat');
        register_setting('tmgmt_live_tracking_options', 'tmgmt_live_test_lng');
        register_setting('tmgmt_live_tracking_options', 'tmgmt_live_test_time_offset');

        // General Settings
        register_setting('tmgmt_general_options', 'tmgmt_hide_admin_bar_desktop');
        register_setting('tmgmt_general_options', 'tmgmt_hide_admin_bar_mobile');

        // Frontend Layout Settings
        register_setting('tmgmt_frontend_layout', 'tmgmt_frontend_layout_settings', array(
            'type' => 'string', // Stored as JSON string
            'default' => '{}'
        ));

        // Organization Settings
        register_setting('tmgmt_organization_options', 'tmgmt_org_name');
        register_setting('tmgmt_organization_options', 'tmgmt_org_contact');
        register_setting('tmgmt_organization_options', 'tmgmt_org_street');
        register_setting('tmgmt_organization_options', 'tmgmt_org_number');
        register_setting('tmgmt_organization_options', 'tmgmt_org_zip');
        register_setting('tmgmt_organization_options', 'tmgmt_org_city');
        register_setting('tmgmt_organization_options', 'tmgmt_org_country');
        register_setting('tmgmt_organization_options', 'tmgmt_org_email');
        register_setting('tmgmt_organization_options', 'tmgmt_org_phone');
        register_setting('tmgmt_organization_options', 'tmgmt_org_tax_id');
        register_setting('tmgmt_organization_options', 'tmgmt_org_vat_id');
        register_setting('tmgmt_organization_options', 'tmgmt_org_logo');

        // PDF Settings
        register_setting('tmgmt_pdf_options', 'tmgmt_pdf_setlist_template');
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
            <h1>Töns Management Einstellungen</h1>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
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
                        <h2 style="margin:0; font-size: 16px;">Status Definitionen</h2>
                    </div>
                    <div style="padding: 15px;">
                        <p>Definieren Sie Status und deren Eigenschaften.</p>
                        <a href="edit.php?post_type=tmgmt_status_def" class="button button-primary">Verwalten</a>
                    </div>
                </div>

                <div class="card" style="padding: 0; overflow: hidden;">
                    <div style="padding: 15px; background: #f0f0f1; border-bottom: 1px solid #c3c4c7;">
                        <h2 style="margin:0; font-size: 16px;">Aktionen</h2>
                    </div>
                    <div style="padding: 15px;">
                        <p>Definieren Sie Aktionen (E-Mails, Webhooks), die ausgeführt werden können.</p>
                        <a href="edit.php?post_type=tmgmt_action" class="button button-primary">Verwalten</a>
                    </div>
                </div>

                <div class="card" style="padding: 0; overflow: hidden;">
                    <div style="padding: 15px; background: #f0f0f1; border-bottom: 1px solid #c3c4c7;">
                        <h2 style="margin:0; font-size: 16px;">Webhooks</h2>
                    </div>
                    <div style="padding: 15px;">
                        <p>Verwalten Sie Webhook-Endpunkte für externe Integrationen.</p>
                        <a href="edit.php?post_type=tmgmt_webhook" class="button button-primary">Verwalten</a>
                    </div>
                </div>

                <div class="card" style="padding: 0; overflow: hidden;">
                    <div style="padding: 15px; background: #f0f0f1; border-bottom: 1px solid #c3c4c7;">
                        <h2 style="margin:0; font-size: 16px;">Berechtigungen</h2>
                    </div>
                    <div style="padding: 15px;">
                        <p>Verwalten Sie Rollen und deren Zugriffsrechte.</p>
                        <a href="admin.php?page=tmgmt-permissions-settings" class="button button-primary">Verwalten</a>
                    </div>
                </div>

                <div class="card" style="padding: 0; overflow: hidden;">
                    <div style="padding: 15px; background: #f0f0f1; border-bottom: 1px solid #c3c4c7;">
                        <h2 style="margin:0; font-size: 16px;">E-Mail Vorlagen</h2>
                    </div>
                    <div style="padding: 15px;">
                        <p>Erstellen und bearbeiten Sie Vorlagen für E-Mail-Benachrichtigungen.</p>
                        <a href="edit.php?post_type=tmgmt_email_template" class="button button-primary">Verwalten</a>
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
                        <h2 style="margin:0; font-size: 16px;">Organisation</h2>
                    </div>
                    <div style="padding: 15px;">
                        <p>Hinterlegen Sie Ihre Absenderdaten für Dokumente und E-Mails.</p>
                        <a href="admin.php?page=tmgmt-organization-settings" class="button button-primary">Verwalten</a>
                    </div>
                </div>

                <div class="card" style="padding: 0; overflow: hidden;">
                    <div style="padding: 15px; background: #f0f0f1; border-bottom: 1px solid #c3c4c7;">
                        <h2 style="margin:0; font-size: 16px;">PDF Export</h2>
                    </div>
                    <div style="padding: 15px;">
                        <p>Konfigurieren Sie Vorlagen für den PDF-Export (z.B. Setlists).</p>
                        <a href="admin.php?page=tmgmt-pdf-settings" class="button button-primary">Verwalten</a>
                    </div>
                </div>

                <div class="card" style="padding: 0; overflow: hidden;">
                    <div style="padding: 15px; background: #f0f0f1; border-bottom: 1px solid #c3c4c7;">
                        <h2 style="margin:0; font-size: 16px;">Allgemeine Einstellungen</h2>
                    </div>
                    <div style="padding: 15px;">
                        <p>Globale Einstellungen für das Plugin.</p>
                        <a href="admin.php?page=tmgmt-general-settings" class="button button-primary">Verwalten</a>
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

                <div class="card" style="padding: 0; overflow: hidden;">
                    <div style="padding: 15px; background: #f0f0f1; border-bottom: 1px solid #c3c4c7;">
                        <h2 style="margin:0; font-size: 16px;">Live Tracking</h2>
                    </div>
                    <div style="padding: 15px;">
                        <p>Konfigurieren Sie den Testmodus und Simulationseinstellungen für das Live Tracking.</p>
                        <a href="admin.php?page=tmgmt-live-tracking-settings" class="button button-primary">Konfigurieren</a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_organization_settings_page() {
        if (isset($_GET['settings-updated'])) {
            add_settings_error('tmgmt_messages', 'tmgmt_message', __('Einstellungen gespeichert.', 'toens-mgmt'), 'updated');
        }
        settings_errors('tmgmt_messages');
        
        // Enqueue Media Uploader
        wp_enqueue_media();
        ?>
        <div class="wrap">
            <h1>Organisationseinstellungen</h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('tmgmt_organization_options');
                // do_settings_sections('tmgmt_organization_options'); // We render manually
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="tmgmt_org_name">Name der Organisation</label></th>
                        <td><input type="text" name="tmgmt_org_name" id="tmgmt_org_name" value="<?php echo esc_attr(get_option('tmgmt_org_name')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmgmt_org_contact">Ansprechpartner</label></th>
                        <td><input type="text" name="tmgmt_org_contact" id="tmgmt_org_contact" value="<?php echo esc_attr(get_option('tmgmt_org_contact')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmgmt_org_street">Straße & Hausnummer</label></th>
                        <td>
                            <input type="text" name="tmgmt_org_street" id="tmgmt_org_street" value="<?php echo esc_attr(get_option('tmgmt_org_street')); ?>" placeholder="Straße" class="regular-text">
                            <input type="text" name="tmgmt_org_number" id="tmgmt_org_number" value="<?php echo esc_attr(get_option('tmgmt_org_number')); ?>" placeholder="Nr." class="small-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmgmt_org_zip">PLZ & Ort</label></th>
                        <td>
                            <input type="text" name="tmgmt_org_zip" id="tmgmt_org_zip" value="<?php echo esc_attr(get_option('tmgmt_org_zip')); ?>" placeholder="PLZ" class="small-text">
                            <input type="text" name="tmgmt_org_city" id="tmgmt_org_city" value="<?php echo esc_attr(get_option('tmgmt_org_city')); ?>" placeholder="Ort" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmgmt_org_country">Land</label></th>
                        <td><input type="text" name="tmgmt_org_country" id="tmgmt_org_country" value="<?php echo esc_attr(get_option('tmgmt_org_country')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmgmt_org_email">E-Mail-Adresse</label></th>
                        <td><input type="email" name="tmgmt_org_email" id="tmgmt_org_email" value="<?php echo esc_attr(get_option('tmgmt_org_email')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmgmt_org_phone">Telefon</label></th>
                        <td><input type="text" name="tmgmt_org_phone" id="tmgmt_org_phone" value="<?php echo esc_attr(get_option('tmgmt_org_phone')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmgmt_org_tax_id">Steuernummer</label></th>
                        <td><input type="text" name="tmgmt_org_tax_id" id="tmgmt_org_tax_id" value="<?php echo esc_attr(get_option('tmgmt_org_tax_id')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmgmt_org_vat_id">Umsatzsteuer-ID</label></th>
                        <td><input type="text" name="tmgmt_org_vat_id" id="tmgmt_org_vat_id" value="<?php echo esc_attr(get_option('tmgmt_org_vat_id')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmgmt_org_logo">Logo</label></th>
                        <td>
                            <?php
                            $logo_id = get_option('tmgmt_org_logo');
                            $logo_url = $logo_id ? wp_get_attachment_url($logo_id) : '';
                            ?>
                            <div id="tmgmt-logo-preview" style="margin-bottom: 10px;">
                                <?php if ($logo_url) : ?>
                                    <img src="<?php echo esc_url($logo_url); ?>" style="max-width: 200px; height: auto;">
                                <?php endif; ?>
                            </div>
                            <input type="hidden" name="tmgmt_org_logo" id="tmgmt_org_logo" value="<?php echo esc_attr($logo_id); ?>">
                            <button type="button" class="button" id="tmgmt-upload-logo">Logo auswählen</button>
                            <button type="button" class="button" id="tmgmt-remove-logo" <?php echo $logo_id ? '' : 'style="display:none;"'; ?>>Entfernen</button>
                            
                            <script>
                            jQuery(document).ready(function($) {
                                $('#tmgmt-upload-logo').click(function(e) {
                                    e.preventDefault();
                                    var image = wp.media({ 
                                        title: 'Logo hochladen',
                                        multiple: false
                                    }).open()
                                    .on('select', function(e){
                                        var uploaded_image = image.state().get('selection').first();
                                        var image_url = uploaded_image.toJSON().url;
                                        var image_id = uploaded_image.toJSON().id;
                                        $('#tmgmt_org_logo').val(image_id);
                                        $('#tmgmt-logo-preview').html('<img src="' + image_url + '" style="max-width: 200px; height: auto;">');
                                        $('#tmgmt-remove-logo').show();
                                    });
                                });
                                $('#tmgmt-remove-logo').click(function(e) {
                                    e.preventDefault();
                                    $('#tmgmt_org_logo').val('');
                                    $('#tmgmt-logo-preview').empty();
                                    $(this).hide();
                                });
                            });
                            </script>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function render_pdf_settings_page() {
        if (isset($_GET['settings-updated'])) {
            add_settings_error('tmgmt_messages', 'tmgmt_message', __('Einstellungen gespeichert.', 'toens-mgmt'), 'updated');
        }
        settings_errors('tmgmt_messages');
        
        // Scan for templates
        $template_dir = TMGMT_PLUGIN_DIR . 'templates/setlist/';
        $templates = array();
        if (is_dir($template_dir)) {
            $files = scandir($template_dir);
            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'php' || pathinfo($file, PATHINFO_EXTENSION) === 'html') {
                    $templates[$file] = $file;
                }
            }
        }
        
        $current_template = get_option('tmgmt_pdf_setlist_template');
        ?>
        <div class="wrap">
            <h1>PDF Export Einstellungen</h1>
            <form action="options.php" method="post">
                <?php settings_fields('tmgmt_pdf_options'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="tmgmt_pdf_setlist_template">Setlist Template</label></th>
                        <td>
                            <select name="tmgmt_pdf_setlist_template" id="tmgmt_pdf_setlist_template">
                                <option value="">-- Standard Template --</option>
                                <?php foreach ($templates as $file => $name) : ?>
                                    <option value="<?php echo esc_attr($file); ?>" <?php selected($current_template, $file); ?>><?php echo esc_html($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                Wählen Sie eine Vorlage aus dem Ordner <code><?php echo esc_html($template_dir); ?></code>.<br>
                                <a href="<?php echo TMGMT_PLUGIN_URL . 'docs/setlist_templates.md'; ?>" target="_blank">Dokumentation zur Erstellung von Templates</a>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }


    public function render_general_settings_page() {
        ?>
        <div class="wrap">
            <h1>Allgemeine Einstellungen</h1>
            <form method="post" action="options.php">
                <?php settings_fields('tmgmt_general_options'); ?>
                <?php do_settings_sections('tmgmt_general_options'); ?>
                
                <h2 class="title">Admin Leiste</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="tmgmt_hide_admin_bar_desktop">Auf Desktop ausblenden</label></th>
                        <td>
                            <input name="tmgmt_hide_admin_bar_desktop" type="checkbox" id="tmgmt_hide_admin_bar_desktop" value="1" <?php checked(1, get_option('tmgmt_hide_admin_bar_desktop'), true); ?>>
                            <p class="description">Versteckt die WordPress Admin-Leiste für Nicht-Admins auf Desktop-Geräten.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmgmt_hide_admin_bar_mobile">Auf Mobilgeräten ausblenden</label></th>
                        <td>
                            <input name="tmgmt_hide_admin_bar_mobile" type="checkbox" id="tmgmt_hide_admin_bar_mobile" value="1" <?php checked(1, get_option('tmgmt_hide_admin_bar_mobile'), true); ?>>
                            <p class="description">Versteckt die WordPress Admin-Leiste für Nicht-Admins auf Smartphones und Tablets.</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
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
                        <th scope="row"><label for="tmgmt_route_min_free_slot">Minimale Zeit für freien Slot (Minuten)</label></th>
                        <td>
                            <input name="tmgmt_route_min_free_slot" type="number" id="tmgmt_route_min_free_slot" value="<?php echo esc_attr(get_option('tmgmt_route_min_free_slot', 60)); ?>" class="small-text">
                            <p class="description">Ab dieser Dauer wird ein Zeitraum als "Freier Slot" in der Entwurfsplanung angezeigt.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmgmt_route_loading_time">Verladen / Vorlaufzeit (Minuten)</label></th>
                        <td>
                            <input name="tmgmt_route_loading_time" type="number" id="tmgmt_route_loading_time" value="<?php echo esc_attr(get_option('tmgmt_route_loading_time', 60)); ?>" class="small-text">
                            <p class="description">Zeit, die vor der Abfahrt für das Beladen benötigt wird. Der Bus muss diese Zeit VOR der geplanten Abfahrt am Proberaum sein.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tmgmt_route_meeting_buffer">Vorlaufzeit Treffen (Minuten)</label></th>
                        <td>
                            <input name="tmgmt_route_meeting_buffer" type="number" id="tmgmt_route_meeting_buffer" value="<?php echo esc_attr(get_option('tmgmt_route_meeting_buffer', 15)); ?>" class="small-text">
                            <p class="description">Zeit vor dem Eintreffen des Busses (bzw. Ladebeginn), zu der sich die Musiker am Proberaum treffen sollen.</p>
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
                    ),
                    'colors' => array(
                        'bg' => sanitize_hex_color($_POST[$sec . '_bg_color']),
                        'text' => sanitize_hex_color($_POST[$sec . '_text_color'])
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
            $bg_color = isset($saved_layout[$key]['colors']['bg']) ? $saved_layout[$key]['colors']['bg'] : '';
            $text_color = isset($saved_layout[$key]['colors']['text']) ? $saved_layout[$key]['colors']['text'] : '';
            
            $rows[] = array(
                'key' => $key,
                'label' => $label,
                'desktop_order' => $d_order,
                'desktop_collapsed' => $d_collapsed,
                'mobile_order' => $m_order,
                'mobile_collapsed' => $m_collapsed,
                'bg_color' => $bg_color,
                'text_color' => $text_color
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
                            <th colspan="2">Farben (Titel)</th>
                        </tr>
                        <tr>
                            <th></th>
                            <th><?php echo $get_sort_link('desktop_order', 'Reihenfolge'); ?></th>
                            <th>Initial eingeklappt</th>
                            <th><?php echo $get_sort_link('mobile_order', 'Reihenfolge'); ?></th>
                            <th>Initial eingeklappt</th>
                            <th>Hintergrund</th>
                            <th>Text</th>
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
                            <td>
                                <div style="display:flex; align-items:center; gap:3px;">
                                    <input type="text" name="<?php echo $key; ?>_bg_color" value="<?php echo esc_attr($row['bg_color']); ?>" placeholder="Default" style="width:65px;">
                                    <input type="color" value="<?php echo esc_attr($row['bg_color'] ?: '#ffffff'); ?>" oninput="this.previousElementSibling.value = this.value" style="width:25px; height:25px; padding:0; border:0; background:none; cursor:pointer;">
                                </div>
                            </td>
                            <td>
                                <div style="display:flex; align-items:center; gap:3px;">
                                    <input type="text" name="<?php echo $key; ?>_text_color" value="<?php echo esc_attr($row['text_color']); ?>" placeholder="Default" style="width:65px;">
                                    <input type="color" value="<?php echo esc_attr($row['text_color'] ?: '#000000'); ?>" oninput="this.previousElementSibling.value = this.value" style="width:25px; height:25px; padding:0; border:0; background:none; cursor:pointer;">
                                </div>
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

    public function render_live_tracking_settings_page() {
        if (!current_user_can('tmgmt_manage_settings')) {
            return;
        }
        
        // Handle Reset
        if (isset($_POST['reset_test_mode'])) {
            check_admin_referer('tmgmt_reset_test_mode');
            update_option('tmgmt_live_test_mode_active', 0);
            update_option('tmgmt_live_test_lat', '');
            update_option('tmgmt_live_test_lng', '');
            update_option('tmgmt_live_test_time_offset', 0);
            echo '<div class="notice notice-success"><p>Test Modus zurückgesetzt.</p></div>';
        }
        ?>
        <div class="wrap">
            <h1>Live Tracking Konfiguration</h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('tmgmt_live_tracking_options');
                do_settings_sections('tmgmt_live_tracking_options');
                ?>
                
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Test Modus aktivieren</th>
                        <td>
                            <input type="checkbox" name="tmgmt_live_test_mode_active" value="1" <?php checked(get_option('tmgmt_live_test_mode_active'), 1); ?> />
                            <p class="description">Wenn aktiviert, wird die Position nicht vom GPS des Geräts, sondern von den unten stehenden Werten (oder per Klick auf die Karte) genommen.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Simulierte Position (Lat/Lng)</th>
                        <td>
                            <input type="text" name="tmgmt_live_test_lat" value="<?php echo esc_attr(get_option('tmgmt_live_test_lat')); ?>" placeholder="Latitude" />
                            <input type="text" name="tmgmt_live_test_lng" value="<?php echo esc_attr(get_option('tmgmt_live_test_lng')); ?>" placeholder="Longitude" />
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Simulierte Zeit</th>
                        <td>
                            <?php 
                            $offset = (int)get_option('tmgmt_live_test_time_offset', 0);
                            $sim_time = current_time('timestamp') + $offset;
                            $sim_time_iso = date('Y-m-d\TH:i', $sim_time);
                            ?>
                            <input type="datetime-local" id="tmgmt_live_sim_time" value="<?php echo esc_attr($sim_time_iso); ?>" onchange="updateOffset(this.value)" />
                            <input type="hidden" name="tmgmt_live_test_time_offset" id="tmgmt_live_test_time_offset" value="<?php echo esc_attr($offset); ?>" />
                            <p class="description">Setzen Sie die simulierte Zeit. Der Offset wird automatisch berechnet.</p>
                            
                            <script>
                            function updateOffset(val) {
                                if (!val) return;
                                const simDate = new Date(val);
                                const now = new Date();
                                // Adjust for timezone offset if needed, but simpler to just let PHP handle it on save if we posted the date.
                                // But here we are posting the offset.
                                // Let's do a rough calculation or just rely on the hidden field being updated by PHP on reload?
                                // Actually, it's better to calculate it here.
                                const diffSeconds = Math.floor((simDate.getTime() - now.getTime()) / 1000);
                                document.getElementById('tmgmt_live_test_time_offset').value = diffSeconds;
                            }
                            </script>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <hr>
            
            <form method="post">
                <?php wp_nonce_field('tmgmt_reset_test_mode'); ?>
                <button type="submit" name="reset_test_mode" class="button button-secondary" style="color: #d63638; border-color: #d63638;">Test Modus zurücksetzen</button>
            </form>
        </div>
        <?php
    }

    public function render_permissions_settings_page() {
        if (!current_user_can('tmgmt_manage_settings')) {
            return;
        }
        
        $all_caps = TMGMT_Roles::get_all_caps();
        $managed_roles = TMGMT_Roles::get_managed_roles();
        
        // Get saved caps or defaults
        $saved_caps = get_option('tmgmt_role_caps');
        if (empty($saved_caps) || !is_array($saved_caps)) {
            $saved_caps = TMGMT_Roles::get_default_caps();
        }

        ?>
        <div class="wrap">
            <h1>Berechtigungen</h1>
            <p>Hier können Sie festlegen, welche Benutzerrollen Zugriff auf welche Funktionen haben.</p>
            
            <form method="post" action="options.php">
                <?php settings_fields('tmgmt_permissions_options'); ?>
                
                <style>
                    .tmgmt-perms-table th, .tmgmt-perms-table td { padding: 10px; text-align: center; vertical-align: middle; }
                    .tmgmt-perms-table th.cap-name, .tmgmt-perms-table td.cap-name { text-align: left; }
                    .tmgmt-perms-table tr:nth-child(even) { background: #f9f9f9; }
                    .tmgmt-perms-table tr:hover { background: #f0f0f1; }
                    .tmgmt-group-header { background: #e5e5e5 !important; font-weight: bold; text-align: left; padding: 10px; }
                </style>

                <table class="widefat fixed striped tmgmt-perms-table">
                    <thead>
                        <tr>
                            <th class="cap-name" style="width: 40%;">Berechtigung</th>
                            <?php foreach ($managed_roles as $role_key => $role_name): ?>
                                <th><?php echo esc_html($role_name); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_caps as $group_name => $caps): ?>
                            <tr>
                                <td colspan="<?php echo count($managed_roles) + 1; ?>" class="tmgmt-group-header">
                                    <?php echo esc_html($group_name); ?>
                                </td>
                            </tr>
                            <?php foreach ($caps as $cap_key => $cap_label): ?>
                                <tr>
                                    <td class="cap-name">
                                        <strong><?php echo esc_html($cap_label); ?></strong>
                                        <br><small style="color: #888;"><?php echo esc_html($cap_key); ?></small>
                                    </td>
                                    <?php foreach ($managed_roles as $role_key => $role_name): ?>
                                        <td>
                                            <?php 
                                            $checked = isset($saved_caps[$role_key][$cap_key]) && $saved_caps[$role_key][$cap_key];
                                            
                                            // Safety checks
                                            $disabled = false;
                                            
                                            // Admin always has manage settings
                                            if ($role_key === 'administrator' && $cap_key === TMGMT_Roles::CAP_MANAGE_SETTINGS) {
                                                $disabled = true;
                                                $checked = true;
                                            }
                                            
                                            // Admin always has dashboard
                                            if ($role_key === 'administrator' && $cap_key === TMGMT_Roles::CAP_VIEW_DASHBOARD) {
                                                $disabled = true;
                                                $checked = true;
                                            }
                                            ?>
                                            <input type="checkbox" 
                                                   name="tmgmt_role_caps[<?php echo esc_attr($role_key); ?>][<?php echo esc_attr($cap_key); ?>]" 
                                                   value="1" 
                                                   <?php checked($checked); ?> 
                                                   <?php echo $disabled ? 'disabled' : ''; ?>>
                                            <?php if ($disabled): ?>
                                                <input type="hidden" name="tmgmt_role_caps[<?php echo esc_attr($role_key); ?>][<?php echo esc_attr($cap_key); ?>]" value="1">
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
