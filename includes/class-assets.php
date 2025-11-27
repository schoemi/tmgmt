<?php

class TMGMT_Assets {

    public function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
    }

    /**
     * Get version for assets.
     * Returns filemtime if WP_DEBUG is true, otherwise TMGMT_VERSION.
     */
    public static function get_version($file = '') {
        if (defined('WP_DEBUG') && WP_DEBUG && $file) {
            $file_path = TMGMT_PLUGIN_DIR . $file;
            if (file_exists($file_path)) {
                return filemtime($file_path);
            }
        }
        return TMGMT_VERSION;
    }

    public function enqueue_frontend_scripts() {
        if (is_singular('tmgmt_tour')) {
            global $post;
            
            // Leaflet
            wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
            wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true);

            // SweetAlert2
            wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array(), '11.0.0', true);
            wp_enqueue_style('sweetalert2-css', 'https://cdn.jsdelivr.net/npm/@sweetalert2/theme-wordpress-admin/wordpress-admin.css');

            // Live View Assets
            wp_enqueue_style(
                'tmgmt-live-view-css',
                TMGMT_PLUGIN_URL . 'assets/css/live-view.css',
                array(),
                self::get_version('assets/css/live-view.css')
            );

            wp_enqueue_script(
                'tmgmt-live-view-js',
                TMGMT_PLUGIN_URL . 'assets/js/live-view.js',
                array('jquery', 'leaflet-js'),
                self::get_version('assets/js/live-view.js'),
                true
            );

            wp_localize_script('tmgmt-live-view-js', 'tmgmt_live_vars', array(
                'tour_id' => $post->ID,
                'api_url' => rest_url('tmgmt/v1'),
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wp_rest')
            ));
        }
    }

    public function enqueue_admin_scripts($hook) {
        global $post;

        // Always enqueue FontAwesome for TMGMT pages
        if (strpos($hook, 'tmgmt') !== false || ($post && in_array($post->post_type, array('event', 'tmgmt_tour')))) {
            wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css');
        }

        if ($hook == 'post-new.php' || $hook == 'post.php') {
            if ('event' === $post->post_type) {
                wp_enqueue_script('jquery-ui-dialog');
                wp_enqueue_style('wp-jquery-ui-dialog');

                // Leaflet
                wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
                wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true);

                // SweetAlert2
                wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array(), '11.0.0', true);
                wp_enqueue_style('sweetalert2-css', 'https://cdn.jsdelivr.net/npm/@sweetalert2/theme-wordpress-admin/wordpress-admin.css');

                wp_enqueue_script(
                    'tmgmt-admin-script',
                    TMGMT_PLUGIN_URL . 'assets/js/admin-script.js',
                    array('jquery', 'jquery-ui-dialog', 'leaflet-js'),
                    self::get_version('assets/js/admin-script.js'),
                    true
                );

                // Prepare validation rules
                $rules = array();
                $statuses = TMGMT_Event_Status::get_all_statuses();
                foreach ($statuses as $key => $label) {
                    $required = TMGMT_Event_Status::get_required_fields($key);
                    if (!empty($required)) {
                        $rules[$key] = $required;
                    }
                }

                wp_localize_script('tmgmt-admin-script', 'tmgmt_vars', array(
                    'validation_rules' => $rules,
                    'nonce' => wp_create_nonce('wp_rest')
                ));
            }
            
            // Enqueue for Tour Post Type
            if ('tmgmt_tour' === $post->post_type) {
                // Leaflet
                wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
                wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true);
            }
        }
    }
}
