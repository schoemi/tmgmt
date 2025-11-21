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
    }

    public function remove_custom_fields_metabox() {
        remove_meta_box('postcustom', 'event', 'normal');
    }

    public function register_post_type() {
        $labels = array(
            'name'               => __('Events', 'toens-mgmt'),
            'singular_name'      => __('Event', 'toens-mgmt'),
            'menu_name'          => __('Events', 'toens-mgmt'),
            'name_admin_bar'     => __('Event', 'toens-mgmt'),
            'add_new'            => __('Neues Event', 'toens-mgmt'),
            'add_new_item'       => __('Neues Event hinzufügen', 'toens-mgmt'),
            'new_item'           => __('Neues Event', 'toens-mgmt'),
            'edit_item'          => __('Event bearbeiten', 'toens-mgmt'),
            'view_item'          => __('Event ansehen', 'toens-mgmt'),
            'all_items'          => __('Alle Events', 'toens-mgmt'),
            'search_items'       => __('Events suchen', 'toens-mgmt'),
            'parent_item_colon'  => __('Eltern-Event:', 'toens-mgmt'),
            'not_found'          => __('Keine Events gefunden.', 'toens-mgmt'),
            'not_found_in_trash' => __('Keine Events im Papierkorb gefunden.', 'toens-mgmt')
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false, // Not public on frontend for now
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'event'),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 5,
            'menu_icon'          => 'dashicons-calendar-alt',
            'supports'           => array('title', 'editor', 'custom-fields'),
            'show_in_rest'       => false, // Disables Gutenberg Editor
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
