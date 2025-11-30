<?php
/**
 * Event Post Type
 *
 * Registers the 'event' custom post type.
 */

if (!defined('ABSPATH')) {
    exit;
}

class TMGMT_Event_Post_Type {

    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_filter('wp_insert_post_data', array($this, 'force_publish_status'), 10, 2);
        add_action('admin_head', array($this, 'simplify_publish_metabox'));
        add_filter('gettext', array($this, 'change_publish_button_text'), 10, 3);
        add_action('add_meta_boxes', array($this, 'remove_custom_fields_metabox'), 99);
        add_action('save_post', array($this, 'generate_event_id'), 10, 3);
        add_action('admin_notices', array($this, 'show_event_id_notice'));
    }

    public function generate_event_id($post_id, $post, $update) {
        if ($post->post_type !== 'event') {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check if ID already exists
        $existing_id = get_post_meta($post_id, '_tmgmt_event_id', true);
        if (!empty($existing_id)) {
            return;
        }

        // Generate ID: YY + 6 random chars (A-Z, 0-9)
        $year = date('y');
        $random = strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
        $event_id = $year . $random;

        // Ensure uniqueness (simple check, collision unlikely but possible)
        // In a high volume system, we would loop here.
        
        update_post_meta($post_id, '_tmgmt_event_id', $event_id);
        
        // Set transient to show notice
        set_transient('tmgmt_new_event_id_' . $post_id, $event_id, 30);
    }

    public function show_event_id_notice() {
        global $post;
        if (!$post) return;
        
        $new_id = get_transient('tmgmt_new_event_id_' . $post->ID);
        if ($new_id) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong>Event ID generiert:</strong> <?php echo esc_html($new_id); ?></p>
            </div>
            <?php
            delete_transient('tmgmt_new_event_id_' . $post->ID);
        }
    }

    public function remove_custom_fields_metabox() {
        remove_meta_box('postcustom', 'event', 'normal');
    }

    public function register_post_type() {
        $labels = array(
            'name'               => __('Gigs', 'toens-mgmt'),
            'singular_name'      => __('Gig', 'toens-mgmt'),
            'menu_name'          => __('TMGMT', 'toens-mgmt'),
            'name_admin_bar'     => __('Gig', 'toens-mgmt'),
            'add_new'            => __('Neuer Gig', 'toens-mgmt'),
            'add_new_item'       => __('Neuen Gig hinzufügen', 'toens-mgmt'),
            'new_item'           => __('Neuer Gig', 'toens-mgmt'),
            'edit_item'          => __('Gig bearbeiten', 'toens-mgmt'),
            'view_item'          => __('Gig ansehen', 'toens-mgmt'),
            'all_items'          => __('Alle Gigs', 'toens-mgmt'),
            'search_items'       => __('Gigs suchen', 'toens-mgmt'),
            'parent_item_colon'  => __('Eltern-Gig:', 'toens-mgmt'),
            'not_found'          => __('Keine Gigs gefunden.', 'toens-mgmt'),
            'not_found_in_trash' => __('Keine Gigs im Papierkorb gefunden.', 'toens-mgmt')
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => true, // Hide default menu
            'query_var'          => true,
            'rewrite'            => array('slug' => 'event'),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 5,
            'menu_icon'          => 'dashicons-admin-settings',
            'supports'           => array('title', 'editor', 'custom-fields'),
            'show_in_rest'       => false,
            'capability_type'    => 'event',
            'map_meta_cap'       => true,
        );

        register_post_type('event', $args);
    }

    /**
     * Force post status to 'publish' for events, bypassing drafts.
     */
    public function force_publish_status($data, $postarr) {
        if ($data['post_type'] === 'event' && $data['post_status'] !== 'trash' && $data['post_status'] !== 'auto-draft') {
            $data['post_status'] = 'publish';
        }
        return $data;
    }

    /**
     * Simplify the Publish meta box in Classic Editor via CSS.
     */
    public function simplify_publish_metabox() {
        global $post;
        if (isset($post) && $post->post_type === 'event') {
            echo '<style>
                #minor-publishing { display: none; } /* Hide Save Draft / Preview */
                .misc-pub-section { display: none; } /* Hide Status, Visibility, Date */
                #major-publishing-actions { background: transparent; border-top: none; }
                #delete-action { float: right; }
                #publishing-action { float: left; text-align: left; }
            </style>';
        }
    }

    /**
     * Rename "Publish" and "Update" buttons to "Save".
     */
    public function change_publish_button_text($translation, $text, $domain) {
        global $post;
        if (isset($post) && $post->post_type === 'event') {
            if ($text === 'Veröffentlichen' || $text === 'Publish' || $text === 'Aktualisieren' || $text === 'Update') {
                return __('Speichern', 'toens-mgmt');
            }
        }
        return $translation;
    }
}
