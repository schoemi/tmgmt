<?php
/**
 * Admin Dashboard – Vue-basiert.
 *
 * Ersetzt das alte PHP-Kanban-Board durch die gleiche Vue-App (AppShell),
 * die auch im Frontend-Dashboard genutzt wird.
 */
class TMGMT_Admin_Dashboard {

    private $page_hook;

    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function add_menu_page() {
        $this->page_hook = add_submenu_page(
            'edit.php?post_type=event',
            'Dashboard',
            'Dashboard',
            'tmgmt_view_dashboard',
            'tmgmt-dashboard',
            array($this, 'render_dashboard')
        );
    }

    public function enqueue_scripts($hook) {
        if ($hook !== $this->page_hook) {
            return;
        }

        // Leaflet
        wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
        wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true);

        // SweetAlert2
        wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js', array(), '11', true);
        wp_enqueue_style('sweetalert2-css', 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css');

        // Vue Dashboard Bundle
        $bundle_path = TMGMT_PLUGIN_DIR . 'assets/dist/dashboard.iife.js';
        $bundle_version = TMGMT_Assets::get_version('assets/dist/dashboard.iife.js');

        wp_enqueue_script(
            'tmgmt-dashboard-vue',
            TMGMT_PLUGIN_URL . 'assets/dist/dashboard.iife.js',
            array('leaflet-js', 'sweetalert2'),
            $bundle_version,
            true
        );

        wp_enqueue_style(
            'tmgmt-dashboard-vue-css',
            TMGMT_PLUGIN_URL . 'assets/dist/dashboard.css',
            array('leaflet-css', 'sweetalert2-css'),
            $bundle_version
        );

        // Admin-spezifisches CSS für WP-Admin-Kompatibilität
        wp_add_inline_style('tmgmt-dashboard-vue-css', $this->get_admin_overrides_css());

        // tmgmtData – gleiche Struktur wie im Frontend
        wp_localize_script('tmgmt-dashboard-vue', 'tmgmtData', $this->get_localized_data());
    }

    /**
     * Liefert die tmgmtData-Konfiguration für wp_localize_script.
     */
    private function get_localized_data() {
        return array(
            'apiUrl'              => rest_url('tmgmt/v1/'),
            'nonce'               => wp_create_nonce('wp_rest'),
            'statuses'            => TMGMT_Event_Status::get_all_statuses(),
            'status_requirements' => TMGMT_Event_Status::get_status_requirements(),
            'can_delete_files'    => current_user_can('administrator'),
            'capabilities'        => array(
                'edit_tmgmt_events' => current_user_can('edit_tmgmt_events'),
                'administrator'     => current_user_can('administrator'),
            ),
            'context'             => 'admin',
            'field_map'           => array(
                'tmgmt_event_date'             => 'date',
                'tmgmt_event_start_time'       => 'start_time',
                'tmgmt_event_arrival_time'     => 'arrival_time',
                'tmgmt_event_departure_time'   => 'departure_time',
                'tmgmt_venue_name'             => 'venue_name',
                'tmgmt_venue_street'           => 'venue_street',
                'tmgmt_venue_number'           => 'venue_number',
                'tmgmt_venue_zip'              => 'venue_zip',
                'tmgmt_venue_city'             => 'venue_city',
                'tmgmt_venue_country'          => 'venue_country',
                'tmgmt_geo_lat'                => 'geo_lat',
                'tmgmt_geo_lng'                => 'geo_lng',
                'tmgmt_arrival_notes'          => 'arrival_notes',
                'tmgmt_contact_salutation'     => 'contact_salutation',
                'tmgmt_contact_firstname'      => 'contact_firstname',
                'tmgmt_contact_lastname'       => 'contact_lastname',
                'tmgmt_contact_company'        => 'contact_company',
                'tmgmt_contact_street'         => 'contact_street',
                'tmgmt_contact_number'         => 'contact_number',
                'tmgmt_contact_zip'            => 'contact_zip',
                'tmgmt_contact_city'           => 'contact_city',
                'tmgmt_contact_country'        => 'contact_country',
                'tmgmt_contact_email_contract' => 'contact_email_contract',
                'tmgmt_contact_phone_contract' => 'contact_phone_contract',
                'tmgmt_contact_email_tech'     => 'contact_email_tech',
                'tmgmt_contact_phone_tech'     => 'contact_phone_tech',
                'tmgmt_contact_email_program'  => 'contact_email_program',
                'tmgmt_contact_phone_program'  => 'contact_phone_program',
                'tmgmt_inquiry_date'           => 'inquiry_date',
                'tmgmt_fee'                    => 'fee',
                'tmgmt_deposit'                => 'deposit',
            ),
        );
    }

    /**
     * Inline-CSS für WP-Admin-Kompatibilität.
     * Verhindert Konflikte mit WordPress-Admin-Styles.
     */
    private function get_admin_overrides_css() {
        return '
            #tmgmt-dashboard-app {
                margin: 20px 0 0 0;
                max-width: 100%;
            }
            #tmgmt-dashboard-app * {
                box-sizing: border-box;
            }
            /* WP-Admin setzt eigene Styles auf Inputs – hier neutralisieren */
            #tmgmt-dashboard-app input[type="text"],
            #tmgmt-dashboard-app input[type="email"],
            #tmgmt-dashboard-app input[type="number"],
            #tmgmt-dashboard-app input[type="time"],
            #tmgmt-dashboard-app select,
            #tmgmt-dashboard-app textarea {
                box-shadow: none;
                border-radius: var(--p-border-radius, 6px);
            }
            #tmgmt-dashboard-app .p-datatable .p-datatable-tbody > tr > td {
                padding: 8px 12px;
            }
        ';
    }

    public function render_dashboard() {
        echo '<div class="wrap">';
        echo '<h1>Töns MGMT Dashboard</h1>';
        echo '<div id="tmgmt-dashboard-app">';
        echo '<div class="tmgmt-loading">Lade Dashboard...</div>';
        echo '</div>';
        echo '</div>';
    }
}
