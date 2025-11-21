<?php

class TMGMT_Kanban_Column_Post_Type {

    const POST_TYPE = 'tmgmt_kanban_col';

    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', array($this, 'add_custom_columns'));
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', array($this, 'render_custom_columns'), 10, 2);
    }

    public function register_post_type() {
        $labels = array(
            'name'               => 'Kanban Spalten',
            'singular_name'      => 'Kanban Spalte',
            'menu_name'          => 'Kanban Spalten',
            'add_new'            => 'Neue Spalte',
            'add_new_item'       => 'Neue Kanban Spalte hinzufügen',
            'edit_item'          => 'Kanban Spalte bearbeiten',
            'new_item'           => 'Neue Kanban Spalte',
            'view_item'          => 'Kanban Spalte ansehen',
            'search_items'       => 'Kanban Spalten suchen',
            'not_found'          => 'Keine Kanban Spalten gefunden',
            'not_found_in_trash' => 'Keine Kanban Spalten im Papierkorb gefunden',
        );

        $args = array(
            'labels'              => $labels,
            'public'              => false, // Not public on frontend
            'show_ui'             => true,
            'show_in_menu'        => 'edit.php?post_type=event', // Submenu of Event? Or Settings? Let's put it under Settings or top level for now.
            // User asked for configuration. Maybe under "Einstellungen" (Settings) if we had one.
            // For now, let's put it as a separate CPT in the menu or under Events.
            // Given the structure, maybe 'show_in_menu' => true is safest, or under a parent.
            // Let's put it under the "Töns MGMT" concept. 
            // Since we don't have a main menu page yet, let's make it a top level menu item or put it under Events?
            // Actually, let's just make it show_ui=true and it will appear.
            'capability_type'     => 'post',
            'hierarchical'        => false,
            'supports'            => array('title'),
            'menu_icon'           => 'dashicons-columns',
        );

        register_post_type(self::POST_TYPE, $args);
    }

    public function add_meta_boxes() {
        add_meta_box(
            'tmgmt_kanban_col_settings',
            'Einstellungen',
            array($this, 'render_settings_box'),
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public function render_settings_box($post) {
        wp_nonce_field('tmgmt_save_kanban_col', 'tmgmt_kanban_col_nonce');

        $order = get_post_meta($post->ID, '_tmgmt_kanban_order', true);
        $color = get_post_meta($post->ID, '_tmgmt_kanban_color', true);
        $selected_statuses = get_post_meta($post->ID, '_tmgmt_kanban_statuses', true);
        if (!is_array($selected_statuses)) {
            $selected_statuses = array();
        }

        // Fetch all Status Definitions
        $statuses = get_posts(array(
            'post_type' => 'tmgmt_status_def',
            'numberposts' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC',
            'post_status' => 'publish'
        ));

        ?>
        <div style="margin-bottom: 15px;">
            <label for="tmgmt_kanban_order" style="display:block; font-weight:bold; margin-bottom:5px;">Reihenfolge (Numerisch)</label>
            <input type="number" id="tmgmt_kanban_order" name="tmgmt_kanban_order" value="<?php echo esc_attr($order); ?>" class="small-text">
            <p class="description">Kleinere Zahlen erscheinen links.</p>
        </div>

        <div style="margin-bottom: 15px;">
            <label for="tmgmt_kanban_color" style="display:block; font-weight:bold; margin-bottom:5px;">Farbe</label>
            <input type="color" id="tmgmt_kanban_color" name="tmgmt_kanban_color" value="<?php echo esc_attr($color ? $color : '#cccccc'); ?>">
        </div>

        <div style="margin-bottom: 15px;">
            <label style="display:block; font-weight:bold; margin-bottom:5px;">Zugeordnete Status</label>
            <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">
                <?php if (empty($statuses)) : ?>
                    <p>Keine Status Definitionen gefunden.</p>
                <?php else : ?>
                    <?php foreach ($statuses as $status) : ?>
                        <label style="display:block; margin-bottom: 5px;">
                            <input type="checkbox" name="tmgmt_kanban_statuses[]" value="<?php echo $status->ID; ?>" <?php checked(in_array($status->ID, $selected_statuses)); ?>>
                            <?php echo esc_html($status->post_title); ?>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <p class="description">Wählen Sie die Status aus, die in dieser Spalte angezeigt werden sollen.</p>
        </div>
        <?php
    }

    public function save_meta_boxes($post_id) {
        if (!isset($_POST['tmgmt_kanban_col_nonce']) || !wp_verify_nonce($_POST['tmgmt_kanban_col_nonce'], 'tmgmt_save_kanban_col')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['tmgmt_kanban_order'])) {
            update_post_meta($post_id, '_tmgmt_kanban_order', intval($_POST['tmgmt_kanban_order']));
        }

        if (isset($_POST['tmgmt_kanban_color'])) {
            update_post_meta($post_id, '_tmgmt_kanban_color', sanitize_hex_color($_POST['tmgmt_kanban_color']));
        }

        if (isset($_POST['tmgmt_kanban_statuses'])) {
            $statuses = array_map('intval', $_POST['tmgmt_kanban_statuses']);
            update_post_meta($post_id, '_tmgmt_kanban_statuses', $statuses);
        } else {
            update_post_meta($post_id, '_tmgmt_kanban_statuses', array());
        }
    }

    public function add_custom_columns($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['order'] = 'Reihenfolge';
        $new_columns['color'] = 'Farbe';
        $new_columns['statuses'] = 'Status';
        $new_columns['date'] = $columns['date'];
        return $new_columns;
    }

    public function render_custom_columns($column, $post_id) {
        switch ($column) {
            case 'order':
                echo get_post_meta($post_id, '_tmgmt_kanban_order', true);
                break;
            case 'color':
                $color = get_post_meta($post_id, '_tmgmt_kanban_color', true);
                echo '<div style="width: 20px; height: 20px; background-color: ' . esc_attr($color) . '; border: 1px solid #ccc;"></div>';
                break;
            case 'statuses':
                $status_ids = get_post_meta($post_id, '_tmgmt_kanban_statuses', true);
                if (!empty($status_ids) && is_array($status_ids)) {
                    $names = array();
                    foreach ($status_ids as $id) {
                        $names[] = get_the_title($id);
                    }
                    echo implode(', ', $names);
                } else {
                    echo '—';
                }
                break;
        }
    }
}
