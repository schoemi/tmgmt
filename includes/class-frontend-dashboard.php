<?php

class TMGMT_Frontend_Dashboard {

    public function __construct() {
        add_shortcode('tmgmt_dashboard', array($this, 'render_dashboard'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    public function enqueue_assets() {
        // Only enqueue if shortcode is present (optimization)
        // But checking for shortcode before enqueue in wp_enqueue_scripts is tricky without parsing content.
        // We'll just register and enqueue if we are on a singular post/page for now, or always.
        // Better: Enqueue inside the shortcode callback? No, that's too late for header, but okay for footer.
        // Let's register here and enqueue in shortcode.
        
        // Leaflet
        wp_register_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
        wp_register_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true);

        wp_register_script(
            'tmgmt-frontend-js',
            TMGMT_PLUGIN_URL . 'assets/js/frontend-dashboard.js',
            array('jquery', 'leaflet-js'), // jQuery is useful for AJAX but we can use fetch. Let's keep it simple.
            TMGMT_VERSION,
            true
        );

        wp_register_style(
            'tmgmt-frontend-css',
            TMGMT_PLUGIN_URL . 'assets/css/frontend-dashboard.css',
            array('leaflet-css'),
            TMGMT_VERSION
        );

        wp_localize_script('tmgmt-frontend-js', 'tmgmtData', array(
            'apiUrl' => rest_url('tmgmt/v1/'),
            'nonce'  => wp_create_nonce('wp_rest'),
            'statuses' => TMGMT_Event_Status::get_all_statuses(),
            'status_requirements' => TMGMT_Event_Status::get_status_requirements(),
            'can_delete_files' => current_user_can('administrator'), // Only admins can delete files
            'layout_settings' => json_decode(get_option('tmgmt_frontend_layout_settings', '{}'), true),
            'field_map' => array(
                'tmgmt_event_date' => 'date',
                'tmgmt_event_start_time' => 'start_time',
                'tmgmt_event_arrival_time' => 'arrival_time',
                'tmgmt_event_departure_time' => 'departure_time',
                'tmgmt_venue_name' => 'venue_name',
                'tmgmt_venue_street' => 'venue_street',
                'tmgmt_venue_number' => 'venue_number',
                'tmgmt_venue_zip' => 'venue_zip',
                'tmgmt_venue_city' => 'venue_city',
                'tmgmt_venue_country' => 'venue_country',
                'tmgmt_geo_lat' => 'geo_lat',
                'tmgmt_geo_lng' => 'geo_lng',
                'tmgmt_arrival_notes' => 'arrival_notes',
                'tmgmt_contact_salutation' => 'contact_salutation',
                'tmgmt_contact_firstname' => 'contact_firstname',
                'tmgmt_contact_lastname' => 'contact_lastname',
                'tmgmt_contact_company' => 'contact_company',
                'tmgmt_contact_street' => 'contact_street',
                'tmgmt_contact_number' => 'contact_number',
                'tmgmt_contact_zip' => 'contact_zip',
                'tmgmt_contact_city' => 'contact_city',
                'tmgmt_contact_country' => 'contact_country',
                'tmgmt_contact_email_contract' => 'contact_email_contract',
                'tmgmt_contact_phone_contract' => 'contact_phone_contract',
                'tmgmt_contact_email_tech' => 'contact_email_tech',
                'tmgmt_contact_phone_tech' => 'contact_phone_tech',
                'tmgmt_contact_email_program' => 'contact_email_program',
                'tmgmt_contact_phone_program' => 'contact_phone_program',
                'tmgmt_inquiry_date' => 'inquiry_date',
                'tmgmt_fee' => 'fee',
                'tmgmt_deposit' => 'deposit',
            )
        ));
    }

    public function render_dashboard($atts) {
        // Check permission
        if (!current_user_can('view_tmgmt_dashboard') && !current_user_can('edit_posts')) {
            return '<p>Zugriff verweigert. Bitte einloggen.</p>';
        }

        wp_enqueue_style('leaflet-css');
        wp_enqueue_script('leaflet-js');
        wp_enqueue_script('tmgmt-frontend-js');
        wp_enqueue_style('tmgmt-frontend-css');

        ob_start();
        ?>
        <div id="tmgmt-kanban-app">
            <div class="tmgmt-loading">Lade Dashboard...</div>
        </div>

        <!-- Detail Modal Template -->
        <div id="tmgmt-modal" class="tmgmt-modal" style="display:none;">
            <div class="tmgmt-modal-content">
                <!-- Content injected by JS -->
            </div>
        </div>

        <!-- Action Bottom Sheet -->
        <div id="tmgmt-action-sheet" class="tmgmt-bottom-sheet" style="display:none;">
            <div class="tmgmt-sheet-overlay"></div>
            <div class="tmgmt-sheet-content">
                <div class="tmgmt-sheet-header">
                    <h3 id="tmgmt-sheet-title">Aktion ausführen</h3>
                    <span class="tmgmt-close-sheet">&times;</span>
                </div>
                <div id="tmgmt-sheet-body">
                    <!-- Dynamic Content -->
                </div>
                <div class="tmgmt-sheet-footer">
                    <button id="tmgmt-sheet-cancel" class="tmgmt-btn">Abbrechen</button>
                    <button id="tmgmt-sheet-confirm" class="tmgmt-btn tmgmt-btn-primary">Ausführen</button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
