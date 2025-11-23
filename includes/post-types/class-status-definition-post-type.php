<?php

class TMGMT_Status_Definition_Post_Type {

    const POST_TYPE = 'tmgmt_status_def';

    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
        
        // Add columns to list view
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', array($this, 'add_custom_columns'));
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', array($this, 'render_custom_columns'), 10, 2);
    }

    public function register_post_type() {
        $labels = array(
            'name'                  => 'Status Definitionen',
            'singular_name'         => 'Status Definition',
            'menu_name'             => 'Status Verwaltung',
            'name_admin_bar'        => 'Status Definition',
            'add_new'               => 'Neuen Status erstellen',
            'add_new_item'          => 'Neuen Status erstellen',
            'new_item'              => 'Neuer Status',
            'edit_item'             => 'Status bearbeiten',
            'view_item'             => 'Status ansehen',
            'all_items'             => 'Alle Status',
            'search_items'          => 'Status suchen',
            'not_found'             => 'Keine Status gefunden.',
            'not_found_in_trash'    => 'Keine Status im Papierkorb gefunden.'
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'status-def'),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array('title', 'page-attributes'), // Title = Label, Page Attributes = Order
        );

        register_post_type(self::POST_TYPE, $args);
    }

    public function add_meta_boxes() {
        add_meta_box(
            'tmgmt_status_settings',
            'Einstellungen',
            array($this, 'render_settings_box'),
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'tmgmt_status_actions',
            'Verfügbare Aktionen',
            array($this, 'render_available_actions_box'),
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public function render_available_actions_box($post) {
        $available_actions = get_post_meta($post->ID, '_tmgmt_available_actions', true);
        if (!is_array($available_actions)) {
            $available_actions = array();
        }

        $all_actions = get_posts(array(
            'post_type' => 'tmgmt_action',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC'
        ));

        ?>
        <div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff;">
            <?php if (empty($all_actions)) : ?>
                <p>Keine Aktionen gefunden. Bitte erstellen Sie zuerst Aktionen unter "Aktionen".</p>
            <?php else : ?>
                <?php foreach ($all_actions as $action) : ?>
                    <div style="margin-bottom: 5px;">
                        <label>
                            <input type="checkbox" name="tmgmt_available_actions[]" value="<?php echo esc_attr($action->ID); ?>" <?php checked(in_array($action->ID, $available_actions)); ?>>
                            <?php echo esc_html($action->post_title); ?>
                            <span style="color: #888; font-size: 11px;">(<?php echo get_post_meta($action->ID, '_tmgmt_action_type', true); ?>)</span>
                        </label>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <p class="description">Wählen Sie die Aktionen aus, die in diesem Status verfügbar sein sollen.</p>
        <?php
    }

    public function render_settings_box($post) {
        wp_nonce_field('tmgmt_save_status_def', 'tmgmt_status_def_nonce');

        $log_template = get_post_meta($post->ID, '_tmgmt_log_template', true);
        $required_fields = get_post_meta($post->ID, '_tmgmt_required_fields', true);
        if (!is_array($required_fields)) {
            $required_fields = array();
        }

        $all_fields = TMGMT_Event_Meta_Boxes::get_registered_fields();
        ?>
        <div style="margin-bottom: 20px;">
            <label for="tmgmt_log_template" style="display:block; font-weight:bold; margin-bottom:5px;">Logbuch Textbaustein</label>
            <input type="text" id="tmgmt_log_template" name="tmgmt_log_template" value="<?php echo esc_attr($log_template); ?>" class="widefat" placeholder="z.B. Status geändert auf Vertrag versendet">
            <p class="description">Dieser Text wird im Logbuch verwendet, wenn dieser Status gesetzt wird.</p>
        </div>

        <div>
            <label style="display:block; font-weight:bold; margin-bottom:10px;">Pflichtfelder</label>
            <div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff;">
                <?php foreach ($all_fields as $field_id => $field_label) : ?>
                    <div style="margin-bottom: 5px;">
                        <label>
                            <input type="checkbox" name="tmgmt_required_fields[]" value="<?php echo esc_attr($field_id); ?>" <?php checked(in_array($field_id, $required_fields)); ?>>
                            <?php echo esc_html($field_label); ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="description">Wählen Sie die Felder aus, die ausgefüllt sein müssen, bevor dieser Status gesetzt werden kann.</p>
        </div>
        <?php
    }

    public function save_meta_boxes($post_id) {
        if (!isset($_POST['tmgmt_status_def_nonce']) || !wp_verify_nonce($_POST['tmgmt_status_def_nonce'], 'tmgmt_save_status_def')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save Log Template
        if (isset($_POST['tmgmt_log_template'])) {
            update_post_meta($post_id, '_tmgmt_log_template', sanitize_text_field($_POST['tmgmt_log_template']));
        }

        // Save Required Fields
        if (isset($_POST['tmgmt_required_fields']) && is_array($_POST['tmgmt_required_fields'])) {
            $sanitized_fields = array_map('sanitize_text_field', $_POST['tmgmt_required_fields']);
            update_post_meta($post_id, '_tmgmt_required_fields', $sanitized_fields);
        } else {
            update_post_meta($post_id, '_tmgmt_required_fields', array());
        }

        // Save Available Actions
        if (isset($_POST['tmgmt_available_actions']) && is_array($_POST['tmgmt_available_actions'])) {
            $sanitized_actions = array_map('sanitize_text_field', $_POST['tmgmt_available_actions']);
            update_post_meta($post_id, '_tmgmt_available_actions', $sanitized_actions);
        } else {
            update_post_meta($post_id, '_tmgmt_available_actions', array());
        }
    }

    public function add_custom_columns($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = 'Status Bezeichnung';
        $new_columns['slug'] = 'Slug'; // Custom column
        $new_columns['order'] = 'Reihenfolge'; // Custom column
        $new_columns['date'] = $columns['date'];
        return $new_columns;
    }

    public function render_custom_columns($column, $post_id) {
        switch ($column) {
            case 'slug':
                $post = get_post($post_id);
                echo esc_html($post->post_name);
                break;
            case 'order':
                $post = get_post($post_id);
                echo esc_html($post->menu_order);
                break;
        }
    }
}
