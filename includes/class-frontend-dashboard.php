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
            'statuses' => TMGMT_Event_Status::get_all_statuses()
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
                <span class="tmgmt-close">&times;</span>
                <h2 id="tmgmt-modal-title">Event Details</h2>
                <div id="tmgmt-modal-body">
                    <!-- Form will be injected here -->
                </div>
                <div class="tmgmt-modal-footer">
                    <button id="tmgmt-save-btn" class="button button-primary">Speichern</button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
