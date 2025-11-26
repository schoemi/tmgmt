<?php
/**
 * Title Post Type
 *
 * Registers the 'tmgmt_title' custom post type for Setlist management.
 */

if (!defined('ABSPATH')) {
    exit;
}

class TMGMT_Title_Post_Type {

    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
    }

    public function register_post_type() {
        $labels = array(
            'name'               => __('Titel', 'toens-mgmt'),
            'singular_name'      => __('Titel', 'toens-mgmt'),
            'menu_name'          => __('Titel (GEMA)', 'toens-mgmt'),
            'name_admin_bar'     => __('Titel', 'toens-mgmt'),
            'add_new'            => __('Neuer Titel', 'toens-mgmt'),
            'add_new_item'       => __('Neuen Titel hinzufügen', 'toens-mgmt'),
            'new_item'           => __('Neuer Titel', 'toens-mgmt'),
            'edit_item'          => __('Titel bearbeiten', 'toens-mgmt'),
            'view_item'          => __('Titel ansehen', 'toens-mgmt'),
            'all_items'          => __('Alle Titel', 'toens-mgmt'),
            'search_items'       => __('Titel suchen', 'toens-mgmt'),
            'not_found'          => __('Keine Titel gefunden.', 'toens-mgmt'),
            'not_found_in_trash' => __('Keine Titel im Papierkorb gefunden.', 'toens-mgmt')
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => 'edit.php?post_type=event', // Submenu of TMGMT
            'query_var'          => true,
            'rewrite'            => array('slug' => 'tmgmt-title'),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 20,
            'supports'           => array('title'), // Only title, other fields are meta
            'show_in_rest'       => false,
        );

        register_post_type('tmgmt_title', $args);
    }

    public function add_meta_boxes() {
        add_meta_box(
            'tmgmt_title_details',
            'Titel Details',
            array($this, 'render_details_box'),
            'tmgmt_title',
            'normal',
            'high'
        );
    }

    public function render_details_box($post) {
        wp_nonce_field('tmgmt_save_title_meta', 'tmgmt_title_meta_nonce');

        $artist = get_post_meta($post->ID, '_tmgmt_title_artist', true);
        $composer = get_post_meta($post->ID, '_tmgmt_title_composer', true);
        $lyricist = get_post_meta($post->ID, '_tmgmt_title_lyricist', true);
        $arranger = get_post_meta($post->ID, '_tmgmt_title_arranger', true);
        $duration = get_post_meta($post->ID, '_tmgmt_title_duration', true);
        $gema_nr = get_post_meta($post->ID, '_tmgmt_title_gema_nr', true);
        ?>
        <style>
            .tmgmt-row { display: flex; gap: 15px; margin-bottom: 15px; }
            .tmgmt-field { flex: 1; }
            .tmgmt-field label { display: block; font-weight: bold; margin-bottom: 5px; }
            .tmgmt-field input { width: 100%; }
        </style>

        <div class="tmgmt-row">
            <div class="tmgmt-field">
                <label for="tmgmt_title_artist">Interpret</label>
                <input type="text" id="tmgmt_title_artist" name="tmgmt_title_artist" value="<?php echo esc_attr($artist); ?>">
            </div>
            <div class="tmgmt-field">
                <label for="tmgmt_title_duration">Spieldauer (MM:SS)</label>
                <input type="text" id="tmgmt_title_duration" name="tmgmt_title_duration" value="<?php echo esc_attr($duration); ?>" placeholder="03:45">
            </div>
        </div>

        <div class="tmgmt-row">
            <div class="tmgmt-field">
                <label for="tmgmt_title_composer">Komponist</label>
                <input type="text" id="tmgmt_title_composer" name="tmgmt_title_composer" value="<?php echo esc_attr($composer); ?>">
            </div>
            <div class="tmgmt-field">
                <label for="tmgmt_title_lyricist">Textdichter</label>
                <input type="text" id="tmgmt_title_lyricist" name="tmgmt_title_lyricist" value="<?php echo esc_attr($lyricist); ?>">
            </div>
        </div>

        <div class="tmgmt-row">
            <div class="tmgmt-field">
                <label for="tmgmt_title_arranger">Bearbeiter</label>
                <input type="text" id="tmgmt_title_arranger" name="tmgmt_title_arranger" value="<?php echo esc_attr($arranger); ?>">
            </div>
            <div class="tmgmt-field">
                <label for="tmgmt_title_gema_nr">GEMA-Werknummer</label>
                <input type="text" id="tmgmt_title_gema_nr" name="tmgmt_title_gema_nr" value="<?php echo esc_attr($gema_nr); ?>">
            </div>
        </div>
        <?php
    }

    public function save_meta_boxes($post_id) {
        if (!isset($_POST['tmgmt_title_meta_nonce']) || !wp_verify_nonce($_POST['tmgmt_title_meta_nonce'], 'tmgmt_save_title_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $fields = array(
            'tmgmt_title_artist',
            'tmgmt_title_composer',
            'tmgmt_title_lyricist',
            'tmgmt_title_arranger',
            'tmgmt_title_duration',
            'tmgmt_title_gema_nr'
        );

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST[$field]));
            }
        }
    }
}
