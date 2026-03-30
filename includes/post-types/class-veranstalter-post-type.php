<?php
/**
 * Veranstalter Post Type
 *
 * Registers the 'tmgmt_veranstalter' custom post type.
 */

if (!defined('ABSPATH')) {
    exit;
}

class TMGMT_Veranstalter_Post_Type {

    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
        add_filter('wp_insert_post_data', array($this, 'force_publish_status'), 10, 2);
        add_action('admin_notices', array($this, 'show_missing_contract_notice'));
        add_action('wp_ajax_tmgmt_search_veranstalter', array($this, 'ajax_search_veranstalter'));
        add_action('wp_ajax_tmgmt_search_contacts_for_veranstalter', array($this, 'ajax_search_contacts_for_veranstalter'));
        add_action('wp_ajax_tmgmt_search_locations_for_veranstalter', array($this, 'ajax_search_locations_for_veranstalter'));
    }

    public function register_post_type() {
        $labels = array(
            'name'               => __('Veranstalter', 'toens-mgmt'),
            'singular_name'      => __('Veranstalter', 'toens-mgmt'),
            'menu_name'          => __('Veranstalter', 'toens-mgmt'),
            'name_admin_bar'     => __('Veranstalter', 'toens-mgmt'),
            'add_new'            => __('Neuer Veranstalter', 'toens-mgmt'),
            'add_new_item'       => __('Neuen Veranstalter hinzufügen', 'toens-mgmt'),
            'new_item'           => __('Neuer Veranstalter', 'toens-mgmt'),
            'edit_item'          => __('Veranstalter bearbeiten', 'toens-mgmt'),
            'view_item'          => __('Veranstalter ansehen', 'toens-mgmt'),
            'all_items'          => __('Alle Veranstalter', 'toens-mgmt'),
            'search_items'       => __('Veranstalter suchen', 'toens-mgmt'),
            'not_found'          => __('Keine Veranstalter gefunden.', 'toens-mgmt'),
            'not_found_in_trash' => __('Keine Veranstalter im Papierkorb gefunden.', 'toens-mgmt'),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => 'edit.php?post_type=event',
            'query_var'          => true,
            'rewrite'            => array('slug' => 'tmgmt-veranstalter'),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'supports'           => array('title'),
            'show_in_rest'       => false,
        );

        register_post_type('tmgmt_veranstalter', $args);
    }

    public function force_publish_status($data, $postarr) {
        if ($data['post_type'] === 'tmgmt_veranstalter' && $data['post_status'] !== 'trash' && $data['post_status'] !== 'auto-draft') {
            $data['post_status'] = 'publish';
        }
        return $data;
    }

    public function add_meta_boxes() {
        add_meta_box(
            'tmgmt_veranstalter_address',
            'Postadresse',
            array($this, 'render_address_box'),
            'tmgmt_veranstalter',
            'normal',
            'high'
        );

        add_meta_box(
            'tmgmt_veranstalter_contacts',
            'Kontakte',
            array($this, 'render_contacts_box'),
            'tmgmt_veranstalter',
            'normal',
            'high'
        );

        add_meta_box(
            'tmgmt_veranstalter_locations',
            'Veranstaltungsorte',
            array($this, 'render_locations_box'),
            'tmgmt_veranstalter',
            'normal',
            'default'
        );
    }

    public function render_address_box($post) {
        wp_nonce_field('tmgmt_save_veranstalter_meta', 'tmgmt_veranstalter_meta_nonce');

        $street = get_post_meta($post->ID, '_tmgmt_veranstalter_street', true);
        $number = get_post_meta($post->ID, '_tmgmt_veranstalter_number', true);
        $zip = get_post_meta($post->ID, '_tmgmt_veranstalter_zip', true);
        $city = get_post_meta($post->ID, '_tmgmt_veranstalter_city', true);
        $country = get_post_meta($post->ID, '_tmgmt_veranstalter_country', true);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="tmgmt_veranstalter_street">Straße & Hausnummer</label></th>
                <td>
                    <input type="text" name="tmgmt_veranstalter_street" id="tmgmt_veranstalter_street" value="<?php echo esc_attr($street); ?>" placeholder="Straße" style="width: 70%;">
                    <input type="text" name="tmgmt_veranstalter_number" id="tmgmt_veranstalter_number" value="<?php echo esc_attr($number); ?>" placeholder="Nr." style="width: 20%;">
                </td>
            </tr>
            <tr>
                <th><label for="tmgmt_veranstalter_zip">PLZ & Ort</label></th>
                <td>
                    <input type="text" name="tmgmt_veranstalter_zip" id="tmgmt_veranstalter_zip" value="<?php echo esc_attr($zip); ?>" placeholder="PLZ" style="width: 20%;">
                    <input type="text" name="tmgmt_veranstalter_city" id="tmgmt_veranstalter_city" value="<?php echo esc_attr($city); ?>" placeholder="Ort" style="width: 70%;">
                </td>
            </tr>
            <tr>
                <th><label for="tmgmt_veranstalter_country">Land</label></th>
                <td>
                    <input type="text" name="tmgmt_veranstalter_country" id="tmgmt_veranstalter_country" value="<?php echo esc_attr($country); ?>" class="regular-text">
                </td>
            </tr>
        </table>
        <?php
    }

    public function render_contacts_box($post) {
        $contacts = get_post_meta($post->ID, '_tmgmt_veranstalter_contacts', true);
        if (!is_array($contacts)) {
            $contacts = array();
        }

        $roles = array(
            'vertrag'  => array('label' => 'Vertrag', 'required' => true),
            'technik'  => array('label' => 'Technik', 'required' => false),
            'programm' => array('label' => 'Programm', 'required' => false),
        );

        foreach ($roles as $role_slug => $role_info) {
            $assigned = array_filter($contacts, function ($c) use ($role_slug) {
                return isset($c['role']) && $c['role'] === $role_slug;
            });
            $assigned_contact = !empty($assigned) ? reset($assigned) : null;
            $contact_id = $assigned_contact ? intval($assigned_contact['contact_id']) : 0;
            $contact_title = '';

            if ($contact_id > 0) {
                $contact_post = get_post($contact_id);
                if ($contact_post && $contact_post->post_type === 'tmgmt_contact') {
                    $contact_title = $contact_post->post_title;
                } else {
                    $contact_id = 0;
                }
            }

            $required_mark = $role_info['required'] ? ' <span style="color: red;">*</span>' : '';
            ?>
            <div class="tmgmt-veranstalter-contact-role" style="margin-bottom: 15px;">
                <label><strong><?php echo esc_html($role_info['label']); ?><?php echo $required_mark; ?></strong></label>
                <div style="margin-top: 5px;">
                    <input type="text"
                           class="tmgmt-veranstalter-contact-search"
                           data-role="<?php echo esc_attr($role_slug); ?>"
                           placeholder="Kontakt suchen..."
                           value="<?php echo esc_attr($contact_title); ?>"
                           style="width: 70%;">
                    <input type="hidden"
                           name="tmgmt_veranstalter_contact_<?php echo esc_attr($role_slug); ?>"
                           value="<?php echo esc_attr($contact_id); ?>">
                </div>
            </div>
            <?php
        }
    }

    public function render_locations_box($post) {
        $location_ids = get_post_meta($post->ID, '_tmgmt_veranstalter_locations', true);
        if (!is_array($location_ids)) {
            $location_ids = array();
        }
        ?>
        <div class="tmgmt-veranstalter-locations">
            <div style="margin-bottom: 10px;">
                <input type="text" class="tmgmt-veranstalter-location-search" placeholder="Ort suchen..." style="width: 70%;">
            </div>
            <div class="tmgmt-veranstalter-locations-list">
                <?php foreach ($location_ids as $location_id) :
                    $location_id = intval($location_id);
                    $location_post = get_post($location_id);
                    if (!$location_post || $location_post->post_type !== 'tmgmt_location') {
                        continue;
                    }
                    $location_city = get_post_meta($location_id, '_tmgmt_location_city', true);
                ?>
                <div class="tmgmt-veranstalter-location-item" style="margin-bottom: 5px;">
                    <input type="hidden" name="tmgmt_veranstalter_locations[]" value="<?php echo esc_attr($location_id); ?>">
                    <span><?php echo esc_html($location_post->post_title); ?><?php echo $location_city ? ' (' . esc_html($location_city) . ')' : ''; ?></span>
                    <button type="button" class="button tmgmt-veranstalter-remove-location" style="margin-left: 5px;">Entfernen</button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    public function save_meta_boxes($post_id) {
        if (!isset($_POST['tmgmt_veranstalter_meta_nonce']) || !wp_verify_nonce($_POST['tmgmt_veranstalter_meta_nonce'], 'tmgmt_save_veranstalter_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (get_post_type($post_id) !== 'tmgmt_veranstalter') {
            return;
        }

        // Save address fields
        $address_fields = array(
            'tmgmt_veranstalter_street',
            'tmgmt_veranstalter_number',
            'tmgmt_veranstalter_zip',
            'tmgmt_veranstalter_city',
            'tmgmt_veranstalter_country',
        );

        foreach ($address_fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST[$field]));
            } else {
                update_post_meta($post_id, '_' . $field, '');
            }
        }

        // Save contact assignments
        $contacts = array();
        $roles = array('vertrag', 'technik', 'programm');
        foreach ($roles as $role) {
            $field_name = 'tmgmt_veranstalter_contact_' . $role;
            if (isset($_POST[$field_name]) && intval($_POST[$field_name]) > 0) {
                $contacts[] = array(
                    'contact_id' => intval($_POST[$field_name]),
                    'role'       => $role,
                );
            }
        }
        update_post_meta($post_id, '_tmgmt_veranstalter_contacts', $contacts);

        // Check for missing Vertrag role and set transient
        $has_vertrag = false;
        foreach ($contacts as $c) {
            if ($c['role'] === 'vertrag') {
                $has_vertrag = true;
                break;
            }
        }
        if (!$has_vertrag) {
            set_transient('tmgmt_veranstalter_missing_contract_' . $post_id, true, 30);
        } else {
            delete_transient('tmgmt_veranstalter_missing_contract_' . $post_id);
        }

        // Save location assignments
        $locations = array();
        if (isset($_POST['tmgmt_veranstalter_locations']) && is_array($_POST['tmgmt_veranstalter_locations'])) {
            $locations = array_map('intval', $_POST['tmgmt_veranstalter_locations']);
            $locations = array_filter($locations, function ($id) { return $id > 0; });
            $locations = array_values($locations);
        }
        update_post_meta($post_id, '_tmgmt_veranstalter_locations', $locations);
    }

    public function show_missing_contract_notice() {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'tmgmt_veranstalter') {
            return;
        }

        global $post;
        if (!$post) {
            return;
        }

        $transient_key = 'tmgmt_veranstalter_missing_contract_' . $post->ID;
        if (get_transient($transient_key)) {
            echo '<div class="notice notice-warning is-dismissible"><p>';
            echo esc_html__('Achtung: Kein Kontakt mit der Rolle "Vertrag" zugeordnet. Bitte weisen Sie einen Vertragskontakt zu.', 'toens-mgmt');
            echo '</p></div>';
            delete_transient($transient_key);
        }
    }

    public function ajax_search_veranstalter() {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }

        $term = isset($_GET['term']) ? sanitize_text_field($_GET['term']) : '';

        $args = array(
            'post_type'      => 'tmgmt_veranstalter',
            'post_status'    => 'publish',
            's'              => $term,
            'posts_per_page' => 20,
        );

        $query = new WP_Query($args);
        $results = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $id = get_the_ID();
                $results[] = array(
                    'id'    => $id,
                    'title' => get_the_title(),
                    'city'  => get_post_meta($id, '_tmgmt_veranstalter_city', true),
                );
            }
        }

        wp_send_json_success($results);
    }

    public function ajax_search_contacts_for_veranstalter() {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }

        $term = isset($_GET['term']) ? sanitize_text_field($_GET['term']) : '';

        $args = array(
            'post_type'      => 'tmgmt_contact',
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'meta_query'     => array(
                'relation' => 'OR',
                array(
                    'key'     => '_tmgmt_contact_firstname',
                    'value'   => $term,
                    'compare' => 'LIKE',
                ),
                array(
                    'key'     => '_tmgmt_contact_lastname',
                    'value'   => $term,
                    'compare' => 'LIKE',
                ),
                array(
                    'key'     => '_tmgmt_contact_company',
                    'value'   => $term,
                    'compare' => 'LIKE',
                ),
                array(
                    'key'     => '_tmgmt_contact_email',
                    'value'   => $term,
                    'compare' => 'LIKE',
                ),
            ),
        );

        $query = new WP_Query($args);
        $results = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $id = get_the_ID();
                $results[] = array(
                    'id'        => $id,
                    'title'     => get_the_title(),
                    'firstname' => get_post_meta($id, '_tmgmt_contact_firstname', true),
                    'lastname'  => get_post_meta($id, '_tmgmt_contact_lastname', true),
                    'email'     => get_post_meta($id, '_tmgmt_contact_email', true),
                );
            }
        }

        wp_send_json_success($results);
    }

    public function ajax_search_locations_for_veranstalter() {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }

        $term = isset($_GET['term']) ? sanitize_text_field($_GET['term']) : '';

        $args = array(
            'post_type'      => 'tmgmt_location',
            'post_status'    => 'publish',
            's'              => $term,
            'posts_per_page' => 20,
        );

        $query = new WP_Query($args);
        $results = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $id = get_the_ID();
                $results[] = array(
                    'id'    => $id,
                    'title' => get_the_title(),
                    'city'  => get_post_meta($id, '_tmgmt_location_city', true),
                );
            }
        }

        wp_send_json_success($results);
    }
}
