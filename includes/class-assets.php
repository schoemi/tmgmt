<?php

class TMGMT_Assets {

    public function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    public function enqueue_admin_scripts($hook) {
        global $post;

        if ($hook == 'post-new.php' || $hook == 'post.php') {
            if ('event' === $post->post_type) {
                wp_enqueue_script('jquery-ui-dialog');
                wp_enqueue_style('wp-jquery-ui-dialog');

                // Leaflet
                wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
                wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true);

                wp_enqueue_script(
                    'tmgmt-admin-script',
                    TMGMT_PLUGIN_URL . 'assets/js/admin-script.js',
                    array('jquery', 'jquery-ui-dialog', 'leaflet-js'),
                    TMGMT_VERSION,
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
                    'validation_rules' => $rules
                ));
            }
        }
    }
}
