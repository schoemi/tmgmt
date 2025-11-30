<?php
/**
 * Service Post Type
 *
 * Registers the 'tmgmt_service' custom post type for invoicing services.
 */

if (!defined('ABSPATH')) {
    exit;
}

class TMGMT_Service_Post_Type {

    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
    }

    public function register_post_type() {
        $labels = array(
            'name'               => __('Leistungen', 'toens-mgmt'),
            'singular_name'      => __('Leistung', 'toens-mgmt'),
            'menu_name'          => __('Leistungen', 'toens-mgmt'),
            'add_new'            => __('Neue Leistung', 'toens-mgmt'),
            'add_new_item'       => __('Neue Leistung hinzufügen', 'toens-mgmt'),
            'edit_item'          => __('Leistung bearbeiten', 'toens-mgmt'),
            'view_item'          => __('Leistung ansehen', 'toens-mgmt'),
            'all_items'          => __('Alle Leistungen', 'toens-mgmt'),
            'search_items'       => __('Leistungen suchen', 'toens-mgmt'),
            'not_found'          => __('Keine Leistungen gefunden.', 'toens-mgmt'),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => 'edit.php?post_type=event', // Submenu of Events
            'query_var'          => true,
            'rewrite'            => array('slug' => 'tmgmt-service'),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 24,
            'supports'           => array('title', 'editor'), // Editor for description
            'show_in_rest'       => false,
        );

        register_post_type('tmgmt_service', $args);
    }

    public function add_meta_boxes() {
        add_meta_box(
            'tmgmt_service_details',
            'Leistungsdetails',
            array($this, 'render_details_box'),
            'tmgmt_service',
            'normal',
            'high'
        );
    }

    public function render_details_box($post) {
        wp_nonce_field('tmgmt_save_service_meta', 'tmgmt_service_meta_nonce');

        $type = get_post_meta($post->ID, '_tmgmt_service_type', true);
        $vat_rate = get_post_meta($post->ID, '_tmgmt_service_vat_rate', true);
        $price = get_post_meta($post->ID, '_tmgmt_service_price', true);
        $price_unit = get_post_meta($post->ID, '_tmgmt_service_price_unit', true);

        // Options definitions
        $types = array(
            'Gage' => 'Gage',
            'Transport & Reisekosten' => 'Transport & Reisekosten',
            'Technik-Pauschale' => 'Technik-Pauschale',
            'Auf- und Abbau-Pauschalen' => 'Auf- und Abbau-Pauschalen',
            'Verlängerungspauschalen' => 'Verlängerungspauschalen',
            'Cateringpauschale' => 'Cateringpauschale',
            'Versicherungskosten' => 'Versicherungskosten',
            'Lizenzkosten' => 'Lizenzkosten',
            'Backline-Miete' => 'Backline-Miete',
            'Storno- und Ausfallhonorare' => 'Storno- und Ausfallhonorare',
            'Sonstiges' => 'Sonstiges'
        );

        $price_units = array(
            'Pauschal' => 'Pauschal',
            'Stunde' => 'Stunde',
            'Kilometer' => 'Kilometer',
            'Fahrt' => 'Fahrt',
            'Show' => 'Show',
            'Tag(e)' => 'Tag(e)',
            'Minute' => 'Minute',
            'Gage' => 'Gage (aus Event)',
            'Anzahlung' => 'Anzahlung (aus Event)'
        );

        $vat_rates = array(
            '0' => '0%',
            '7' => '7%',
            '19' => '19%',
            '21' => '21%'
        );
        ?>
        <table class="form-table">
            <tr>
                <th><label for="tmgmt_service_type">Typ</label></th>
                <td>
                    <select name="tmgmt_service_type" id="tmgmt_service_type" class="regular-text">
                        <option value="">Bitte wählen...</option>
                        <?php foreach ($types as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($type, $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="tmgmt_service_price_unit">Preiseinheit</label></th>
                <td>
                    <select name="tmgmt_service_price_unit" id="tmgmt_service_price_unit" class="regular-text">
                        <option value="">Bitte wählen...</option>
                        <?php foreach ($price_units as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($price_unit, $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Bei "Gage" oder "Anzahlung" wird der Preis automatisch aus dem Event übernommen.</p>
                </td>
            </tr>
            <tr>
                <th><label for="tmgmt_service_price">Preis (Netto)</label></th>
                <td>
                    <input type="number" step="0.01" name="tmgmt_service_price" id="tmgmt_service_price" value="<?php echo esc_attr($price); ?>" class="regular-text">
                    <p class="description">Wird ignoriert, wenn Preiseinheit "Gage" oder "Anzahlung" gewählt ist.</p>
                </td>
            </tr>
            <tr>
                <th><label for="tmgmt_service_vat_rate">MwSt-Satz</label></th>
                <td>
                    <select name="tmgmt_service_vat_rate" id="tmgmt_service_vat_rate" class="regular-text">
                        <?php foreach ($vat_rates as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($vat_rate, $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_meta_boxes($post_id) {
        if (!isset($_POST['tmgmt_service_meta_nonce']) || !wp_verify_nonce($_POST['tmgmt_service_meta_nonce'], 'tmgmt_save_service_meta')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $fields = array(
            '_tmgmt_service_type',
            '_tmgmt_service_price_unit',
            '_tmgmt_service_price',
            '_tmgmt_service_vat_rate'
        );

        foreach ($fields as $field) {
            $input_name = substr($field, 1); // remove leading underscore
            if (isset($_POST[$input_name])) {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$input_name]));
            }
        }
    }
}
