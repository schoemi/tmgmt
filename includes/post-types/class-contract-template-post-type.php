<?php
/**
 * Contract Template Post Type
 *
 * Registriert den CPT 'tmgmt_contract_template' für Vertragsvorlagen
 * im Gutenberg Block Editor.
 */

if (!defined('ABSPATH')) {
    exit;
}

class TMGMT_Contract_Template_Post_Type {

    const POST_TYPE = 'tmgmt_contract_tpl';

    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_filter('use_block_editor_for_post_type', array($this, 'enable_block_editor_for_cpt'), 10, 2);
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_editor_assets'));
    }

    public function register_post_type(): void {
        $labels = array(
            'name'               => __('Vertragsvorlagen', 'toens-mgmt'),
            'singular_name'      => __('Vertragsvorlage', 'toens-mgmt'),
            'menu_name'          => __('Vertragsvorlagen', 'toens-mgmt'),
            'add_new'            => __('Neue Vorlage', 'toens-mgmt'),
            'add_new_item'       => __('Neue Vertragsvorlage hinzufügen', 'toens-mgmt'),
            'new_item'           => __('Neue Vertragsvorlage', 'toens-mgmt'),
            'edit_item'          => __('Vertragsvorlage bearbeiten', 'toens-mgmt'),
            'view_item'          => __('Vertragsvorlage ansehen', 'toens-mgmt'),
            'all_items'          => __('Alle Vertragsvorlagen', 'toens-mgmt'),
            'search_items'       => __('Vertragsvorlagen suchen', 'toens-mgmt'),
            'not_found'          => __('Keine Vertragsvorlagen gefunden.', 'toens-mgmt'),
            'not_found_in_trash' => __('Keine Vertragsvorlagen im Papierkorb gefunden.', 'toens-mgmt'),
        );

        register_post_type(self::POST_TYPE, array(
            'labels'          => $labels,
            'public'          => false,
            'show_ui'         => true,
            'show_in_menu'    => 'admin.php?page=tmgmt-settings',
            'show_in_rest'    => true,
            'menu_icon'       => 'dashicons-media-document',
            'menu_position'   => 6,
            'capability_type' => 'post',
            'map_meta_cap'    => true,
            'supports'        => array('title', 'editor', 'custom-fields'),
        ));
    }

    /**
     * Aktiviert Gutenberg ausschließlich für tmgmt_contract_template.
     * Alle anderen Post Types behalten den Classic Editor.
     */
    public function enable_block_editor_for_cpt(bool $use_block_editor, string $post_type): bool {
        if ($post_type === self::POST_TYPE) {
            return true;
        }
        return $use_block_editor;
    }

    public function enqueue_editor_assets(): void {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== self::POST_TYPE) {
            return;
        }

        wp_enqueue_script(
            'tmgmt-contract-template-editor',
            TMGMT_PLUGIN_URL . 'assets/js/contract-template-editor.js',
            array('wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-block-editor', 'wp-rich-text'),
            TMGMT_VERSION,
            true
        );

        wp_localize_script('tmgmt-contract-template-editor', 'tmgmtContractEditor', array(
            'placeholders' => TMGMT_Placeholder_Parser::get_placeholders(),
        ));
    }
}
