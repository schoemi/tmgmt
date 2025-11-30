<?php
/**
 * Contact Post Type
 *
 * Registers the 'tmgmt_contact' custom post type.
 */

if (!defined('ABSPATH')) {
    exit;
}

class TMGMT_Contact_Post_Type {
    public function add_easyverein_id_meta_box() {
        add_meta_box(
            'tmgmt_contact_easyverein_id',
            'easyVerein ID',
            array($this, 'render_easyverein_id_meta_box'),
            'tmgmt_contact',
            'side',
            'default'
        );
    }

    public function render_easyverein_id_meta_box($post) {
        $easyverein_id = get_post_meta($post->ID, '_tmgmt_contact_easyverein_id', true);
        echo '<label for="tmgmt_contact_easyverein_id">easyVerein Kontakt-ID:</label>';
        echo '<input type="text" name="tmgmt_contact_easyverein_id" id="tmgmt_contact_easyverein_id" value="' . esc_attr($easyverein_id) . '" class="regular-text">';
    }

    public function save_easyverein_id_meta_box($post_id) {
        if (isset($_POST['tmgmt_contact_easyverein_id'])) {
            update_post_meta($post_id, '_tmgmt_contact_easyverein_id', sanitize_text_field($_POST['tmgmt_contact_easyverein_id']));
        }
    }

    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('add_meta_boxes', array($this, 'add_easyverein_id_meta_box'));
        add_action('save_post', array($this, 'save_meta_boxes'));
        add_action('save_post', array($this, 'save_easyverein_id_meta_box'));
        add_action('wp_ajax_tmgmt_search_contacts', array($this, 'ajax_search_contacts'));
        add_action('wp_ajax_tmgmt_save_contact_from_event', array($this, 'ajax_save_contact_from_event'));
    }

    public function register_post_type() {
        $labels = array(
            'name'               => __('Kontakte', 'toens-mgmt'),
            'singular_name'      => __('Kontakt', 'toens-mgmt'),
            'menu_name'          => __('Kontakte', 'toens-mgmt'),
            'add_new'            => __('Neuer Kontakt', 'toens-mgmt'),
            'add_new_item'       => __('Neuen Kontakt hinzufügen', 'toens-mgmt'),
            'edit_item'          => __('Kontakt bearbeiten', 'toens-mgmt'),
            'view_item'          => __('Kontakt ansehen', 'toens-mgmt'),
            'all_items'          => __('Alle Kontakte', 'toens-mgmt'),
            'search_items'       => __('Kontakte suchen', 'toens-mgmt'),
            'not_found'          => __('Keine Kontakte gefunden.', 'toens-mgmt'),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => 'edit.php?post_type=event',
            'query_var'          => true,
            'rewrite'            => array('slug' => 'tmgmt-contact'),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 23,
            'supports'           => array('title'),
            'show_in_rest'       => false,
        );

        register_post_type('tmgmt_contact', $args);
    }

    public function add_meta_boxes() {
        add_meta_box(
            'tmgmt_contact_details',
            'Kontaktdaten',
            array($this, 'render_details_box'),
            'tmgmt_contact',
            'normal',
            'high'
        );
    }

    public function render_details_box($post) {
        wp_nonce_field('tmgmt_save_contact_meta', 'tmgmt_contact_meta_nonce');

        $salutation = get_post_meta($post->ID, '_tmgmt_contact_salutation', true);
        $firstname = get_post_meta($post->ID, '_tmgmt_contact_firstname', true);
        $lastname = get_post_meta($post->ID, '_tmgmt_contact_lastname', true);
        $company = get_post_meta($post->ID, '_tmgmt_contact_company', true);
        
        $street = get_post_meta($post->ID, '_tmgmt_contact_street', true);
        $number = get_post_meta($post->ID, '_tmgmt_contact_number', true);
        $zip = get_post_meta($post->ID, '_tmgmt_contact_zip', true);
        $city = get_post_meta($post->ID, '_tmgmt_contact_city', true);
        $country = get_post_meta($post->ID, '_tmgmt_contact_country', true);

        $email = get_post_meta($post->ID, '_tmgmt_contact_email', true);
        $phone = get_post_meta($post->ID, '_tmgmt_contact_phone', true);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="tmgmt_contact_salutation">Anrede</label></th>
                <td><input type="text" name="tmgmt_contact_salutation" id="tmgmt_contact_salutation" value="<?php echo esc_attr($salutation); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="tmgmt_contact_firstname">Vorname</label></th>
                <td><input type="text" name="tmgmt_contact_firstname" id="tmgmt_contact_firstname" value="<?php echo esc_attr($firstname); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="tmgmt_contact_lastname">Nachname</label></th>
                <td><input type="text" name="tmgmt_contact_lastname" id="tmgmt_contact_lastname" value="<?php echo esc_attr($lastname); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="tmgmt_contact_company">Firma</label></th>
                <td><input type="text" name="tmgmt_contact_company" id="tmgmt_contact_company" value="<?php echo esc_attr($company); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="tmgmt_contact_street">Straße & Nr.</label></th>
                <td>
                    <input type="text" name="tmgmt_contact_street" id="tmgmt_contact_street" value="<?php echo esc_attr($street); ?>" placeholder="Straße" style="width: 70%;">
                    <input type="text" name="tmgmt_contact_number" id="tmgmt_contact_number" value="<?php echo esc_attr($number); ?>" placeholder="Nr." style="width: 20%;">
                </td>
            </tr>
            <tr>
                <th><label for="tmgmt_contact_zip">PLZ & Ort</label></th>
                <td>
                    <input type="text" name="tmgmt_contact_zip" id="tmgmt_contact_zip" value="<?php echo esc_attr($zip); ?>" placeholder="PLZ" style="width: 20%;">
                    <input type="text" name="tmgmt_contact_city" id="tmgmt_contact_city" value="<?php echo esc_attr($city); ?>" placeholder="Ort" style="width: 70%;">
                </td>
            </tr>
            <tr>
                <th><label for="tmgmt_contact_country">Land</label></th>
                <td><input type="text" name="tmgmt_contact_country" id="tmgmt_contact_country" value="<?php echo esc_attr($country); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="tmgmt_contact_email">E-Mail</label></th>
                <td><input type="email" name="tmgmt_contact_email" id="tmgmt_contact_email" value="<?php echo esc_attr($email); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="tmgmt_contact_phone">Telefon</label></th>
                <td><input type="text" name="tmgmt_contact_phone" id="tmgmt_contact_phone" value="<?php echo esc_attr($phone); ?>" class="regular-text"></td>
            </tr>
        </table>
        <?php
    }

    public function save_meta_boxes($post_id) {
        if (!isset($_POST['tmgmt_contact_meta_nonce']) || !wp_verify_nonce($_POST['tmgmt_contact_meta_nonce'], 'tmgmt_save_contact_meta')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $fields = array(
            '_tmgmt_contact_salutation',
            '_tmgmt_contact_firstname',
            '_tmgmt_contact_lastname',
            '_tmgmt_contact_company',
            '_tmgmt_contact_street',
            '_tmgmt_contact_number',
            '_tmgmt_contact_zip',
            '_tmgmt_contact_city',
            '_tmgmt_contact_country',
            '_tmgmt_contact_email',
            '_tmgmt_contact_phone'
        );

        foreach ($fields as $field) {
            $input_name = substr($field, 1); // remove leading underscore
            if (isset($_POST[$input_name])) {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$input_name]));
            }
        }
    }

    public function ajax_search_contacts() {
        $term = sanitize_text_field($_GET['term']);
        
        $args = array(
            'post_type' => 'tmgmt_contact',
            's' => $term,
            'posts_per_page' => 10
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
                    'salutation' => get_post_meta($id, '_tmgmt_contact_salutation', true),
                    'firstname' => get_post_meta($id, '_tmgmt_contact_firstname', true),
                    'lastname' => get_post_meta($id, '_tmgmt_contact_lastname', true),
                    'company' => get_post_meta($id, '_tmgmt_contact_company', true),
                    'street' => get_post_meta($id, '_tmgmt_contact_street', true),
                    'number' => get_post_meta($id, '_tmgmt_contact_number', true),
                    'zip' => get_post_meta($id, '_tmgmt_contact_zip', true),
                    'city' => get_post_meta($id, '_tmgmt_contact_city', true),
                    'country' => get_post_meta($id, '_tmgmt_contact_country', true),
                    'email' => get_post_meta($id, '_tmgmt_contact_email', true),
                    'phone' => get_post_meta($id, '_tmgmt_contact_phone', true),
                );
            }
        }
        
        wp_send_json_success($results);
    }

    public function ajax_save_contact_from_event() {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Keine Berechtigung');
        }

        $title = sanitize_text_field($_POST['title']);
        
        $post_data = array(
            'post_title'    => $title,
            'post_type'     => 'tmgmt_contact',
            'post_status'   => 'publish'
        );
        
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            wp_send_json_error($post_id->get_error_message());
        }
        
        $fields = array(
            'salutation' => '_tmgmt_contact_salutation',
            'firstname' => '_tmgmt_contact_firstname',
            'lastname' => '_tmgmt_contact_lastname',
            'company' => '_tmgmt_contact_company',
            'street' => '_tmgmt_contact_street',
            'number' => '_tmgmt_contact_number',
            'zip' => '_tmgmt_contact_zip',
            'city' => '_tmgmt_contact_city',
            'country' => '_tmgmt_contact_country',
            'email' => '_tmgmt_contact_email',
            'phone' => '_tmgmt_contact_phone'
        );
        
        foreach ($fields as $post_key => $meta_key) {
            if (isset($_POST[$post_key])) {
                update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$post_key]));
            }
        }
        
        wp_send_json_success();
    }
}
