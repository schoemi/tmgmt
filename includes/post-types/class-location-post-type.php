<?php
/**
 * Location Post Type
 *
 * Registers the 'tmgmt_location' custom post type.
 */

if (!defined('ABSPATH')) {
    exit;
}

class TMGMT_Location_Post_Type {

    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
        add_action('wp_ajax_tmgmt_search_locations', array($this, 'ajax_search_locations'));
        add_action('wp_ajax_tmgmt_save_location_from_event', array($this, 'ajax_save_location_from_event'));
    }

    public function register_post_type() {
        $labels = array(
            'name'               => __('Orte', 'toens-mgmt'),
            'singular_name'      => __('Ort', 'toens-mgmt'),
            'menu_name'          => __('Orte', 'toens-mgmt'),
            'name_admin_bar'     => __('Ort', 'toens-mgmt'),
            'add_new'            => __('Neuer Ort', 'toens-mgmt'),
            'add_new_item'       => __('Neuen Ort hinzufügen', 'toens-mgmt'),
            'new_item'           => __('Neuer Ort', 'toens-mgmt'),
            'edit_item'          => __('Ort bearbeiten', 'toens-mgmt'),
            'view_item'          => __('Ort ansehen', 'toens-mgmt'),
            'all_items'          => __('Alle Orte', 'toens-mgmt'),
            'search_items'       => __('Orte suchen', 'toens-mgmt'),
            'not_found'          => __('Keine Orte gefunden.', 'toens-mgmt'),
            'not_found_in_trash' => __('Keine Orte im Papierkorb gefunden.', 'toens-mgmt')
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => 'edit.php?post_type=event',
            'query_var'          => true,
            'rewrite'            => array('slug' => 'tmgmt-location'),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 22,
            'supports'           => array('title'),
            'show_in_rest'       => false,
        );

        register_post_type('tmgmt_location', $args);
    }

    public function add_meta_boxes() {
        add_meta_box(
            'tmgmt_location_details',
            'Adressdaten',
            array($this, 'render_details_box'),
            'tmgmt_location',
            'normal',
            'high'
        );
    }

    public function render_details_box($post) {
        wp_nonce_field('tmgmt_save_location_meta', 'tmgmt_location_meta_nonce');

        $street = get_post_meta($post->ID, '_tmgmt_location_street', true);
        $number = get_post_meta($post->ID, '_tmgmt_location_number', true);
        $zip = get_post_meta($post->ID, '_tmgmt_location_zip', true);
        $city = get_post_meta($post->ID, '_tmgmt_location_city', true);
        $country = get_post_meta($post->ID, '_tmgmt_location_country', true);
        $lat = get_post_meta($post->ID, '_tmgmt_location_lat', true);
        $lng = get_post_meta($post->ID, '_tmgmt_location_lng', true);
        $notes = get_post_meta($post->ID, '_tmgmt_location_notes', true);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="tmgmt_location_street">Straße & Hausnummer</label></th>
                <td>
                    <input type="text" name="tmgmt_location_street" id="tmgmt_location_street" value="<?php echo esc_attr($street); ?>" placeholder="Straße" style="width: 70%;">
                    <input type="text" name="tmgmt_location_number" id="tmgmt_location_number" value="<?php echo esc_attr($number); ?>" placeholder="Nr." style="width: 20%;">
                </td>
            </tr>
            <tr>
                <th><label for="tmgmt_location_zip">PLZ & Ort</label></th>
                <td>
                    <input type="text" name="tmgmt_location_zip" id="tmgmt_location_zip" value="<?php echo esc_attr($zip); ?>" placeholder="PLZ" style="width: 20%;">
                    <input type="text" name="tmgmt_location_city" id="tmgmt_location_city" value="<?php echo esc_attr($city); ?>" placeholder="Ort" style="width: 70%;">
                </td>
            </tr>
            <tr>
                <th><label for="tmgmt_location_country">Land</label></th>
                <td>
                    <input type="text" name="tmgmt_location_country" id="tmgmt_location_country" value="<?php echo esc_attr($country); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th><label for="tmgmt_location_lat">Geodaten</label></th>
                <td>
                    <input type="text" name="tmgmt_location_lat" id="tmgmt_location_lat" value="<?php echo esc_attr($lat); ?>" placeholder="Latitude" style="width: 45%;">
                    <input type="text" name="tmgmt_location_lng" id="tmgmt_location_lng" value="<?php echo esc_attr($lng); ?>" placeholder="Longitude" style="width: 45%;">
                </td>
            </tr>
            <tr>
                <th><label for="tmgmt_location_notes">Hinweise</label></th>
                <td>
                    <textarea name="tmgmt_location_notes" id="tmgmt_location_notes" rows="5" class="large-text"><?php echo esc_textarea($notes); ?></textarea>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_meta_boxes($post_id) {
        if (!isset($_POST['tmgmt_location_meta_nonce']) || !wp_verify_nonce($_POST['tmgmt_location_meta_nonce'], 'tmgmt_save_location_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $fields = array(
            'tmgmt_location_street',
            'tmgmt_location_number',
            'tmgmt_location_zip',
            'tmgmt_location_city',
            'tmgmt_location_country',
            'tmgmt_location_lat',
            'tmgmt_location_lng',
            'tmgmt_location_notes'
        );

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST[$field]));
            } else {
                update_post_meta($post_id, '_' . $field, '');
            }
        }
    }

    public function ajax_search_locations() {
        // Security check? Maybe just capability check
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }

        $term = isset($_GET['term']) ? sanitize_text_field($_GET['term']) : '';
        
        $args = array(
            'post_type' => 'tmgmt_location',
            'post_status' => 'publish',
            's' => $term,
            'posts_per_page' => 20
        );

        $query = new WP_Query($args);
        $results = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $id = get_the_ID();
                
                $results[] = array(
                    'id' => $id,
                    'title' => get_the_title(),
                    'street' => get_post_meta($id, '_tmgmt_location_street', true),
                    'number' => get_post_meta($id, '_tmgmt_location_number', true),
                    'zip' => get_post_meta($id, '_tmgmt_location_zip', true),
                    'city' => get_post_meta($id, '_tmgmt_location_city', true),
                    'country' => get_post_meta($id, '_tmgmt_location_country', true),
                    'lat' => get_post_meta($id, '_tmgmt_location_lat', true),
                    'lng' => get_post_meta($id, '_tmgmt_location_lng', true),
                    'notes' => get_post_meta($id, '_tmgmt_location_notes', true),
                );
            }
        }
        
        wp_send_json_success($results);
    }

    public function ajax_save_location_from_event() {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }

        // Verify nonce?
        // check_ajax_referer('tmgmt_save_location_nonce', 'nonce');

        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        if (empty($name)) {
            wp_send_json_error('Name is required');
        }

        $post_data = array(
            'post_title'    => $name,
            'post_type'     => 'tmgmt_location',
            'post_status'   => 'publish',
            'post_author'   => get_current_user_id()
        );

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            wp_send_json_error($post_id->get_error_message());
        }

        $fields = array(
            'street' => '_tmgmt_location_street',
            'number' => '_tmgmt_location_number',
            'zip' => '_tmgmt_location_zip',
            'city' => '_tmgmt_location_city',
            'country' => '_tmgmt_location_country',
            'lat' => '_tmgmt_location_lat',
            'lng' => '_tmgmt_location_lng',
            'notes' => '_tmgmt_location_notes'
        );

        foreach ($fields as $key => $meta_key) {
            if (isset($_POST[$key])) {
                update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$key]));
            }
        }

        wp_send_json_success(array('id' => $post_id, 'message' => 'Ort gespeichert'));
    }
}
