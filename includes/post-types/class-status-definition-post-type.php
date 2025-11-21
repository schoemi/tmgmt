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
            'show_in_menu'       => 'edit.php?post_type=event', // Submenu of Events
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
            array($this, 'render_actions_box'),
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public function render_actions_box($post) {
        $actions = get_post_meta($post->ID, '_tmgmt_status_actions', true);
        if (!is_array($actions)) {
            $actions = array();
        }

        // Get Webhooks
        $webhooks = get_posts(array(
            'post_type' => 'tmgmt_webhook',
            'posts_per_page' => -1,
            'post_status' => 'publish' // or 'any' if you want to allow drafts
        ));

        // Get Statuses (excluding current one to avoid loops? No, loops might be valid)
        $statuses = TMGMT_Event_Status::get_all_statuses();

        ?>
        <div id="tmgmt-actions-container">
            <?php foreach ($actions as $index => $action) : ?>
                <?php $this->render_action_row($index, $action, $webhooks, $statuses); ?>
            <?php endforeach; ?>
        </div>

        <button type="button" class="button" id="tmgmt-add-action" style="margin-top: 10px;">+ Aktion hinzufügen</button>

        <!-- Template for new row -->
        <script type="text/template" id="tmgmt-action-template">
            <?php $this->render_action_row('__INDEX__', array(), $webhooks, $statuses); ?>
        </script>

        <script>
        jQuery(document).ready(function($) {
            var container = $('#tmgmt-actions-container');
            var template = $('#tmgmt-action-template').html();
            var count = <?php echo count($actions); ?>;

            $('#tmgmt-add-action').on('click', function() {
                var newRow = template.replace(/__INDEX__/g, count);
                container.append(newRow);
                count++;
            });

            container.on('click', '.tmgmt-remove-action', function() {
                $(this).closest('.tmgmt-action-row').remove();
            });

            container.on('change', '.tmgmt-action-type', function() {
                var type = $(this).val();
                var row = $(this).closest('.tmgmt-action-row');
                if (type === 'webhook') {
                    row.find('.tmgmt-webhook-select').show();
                } else {
                    row.find('.tmgmt-webhook-select').hide();
                }
            });
            
            // Trigger change on load to set initial state
            $('.tmgmt-action-type').trigger('change');
        });
        </script>
        <?php
    }

    private function render_action_row($index, $action, $webhooks, $statuses) {
        $label = isset($action['label']) ? $action['label'] : '';
        $type = isset($action['type']) ? $action['type'] : 'note';
        $webhook_id = isset($action['webhook_id']) ? $action['webhook_id'] : '';
        $target_status = isset($action['target_status']) ? $action['target_status'] : '';
        ?>
        <div class="tmgmt-action-row" style="border: 1px solid #ccc; padding: 10px; margin-bottom: 10px; background: #f9f9f9;">
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 200px;">
                    <label style="display:block; font-size: 12px;">Bezeichnung</label>
                    <input type="text" name="tmgmt_actions[<?php echo $index; ?>][label]" value="<?php echo esc_attr($label); ?>" style="width: 100%;" placeholder="z.B. Vertrag erstellen">
                </div>
                <div style="width: 150px;">
                    <label style="display:block; font-size: 12px;">Typ</label>
                    <select name="tmgmt_actions[<?php echo $index; ?>][type]" class="tmgmt-action-type" style="width: 100%;">
                        <option value="note" <?php selected($type, 'note'); ?>>Notiz / Doku</option>
                        <option value="webhook" <?php selected($type, 'webhook'); ?>>Webhook</option>
                    </select>
                </div>
                <div class="tmgmt-webhook-select" style="flex: 1; min-width: 200px; display: none;">
                    <label style="display:block; font-size: 12px;">Webhook</label>
                    <select name="tmgmt_actions[<?php echo $index; ?>][webhook_id]" style="width: 100%;">
                        <option value="">-- Wählen --</option>
                        <?php foreach ($webhooks as $wh) : ?>
                            <option value="<?php echo esc_attr($wh->ID); ?>" <?php selected($webhook_id, $wh->ID); ?>>
                                <?php echo esc_html($wh->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="flex: 1; min-width: 200px;">
                    <label style="display:block; font-size: 12px;">Ziel-Status (Optional)</label>
                    <select name="tmgmt_actions[<?php echo $index; ?>][target_status]" style="width: 100%;">
                        <option value="">-- Kein Wechsel --</option>
                        <?php foreach ($statuses as $slug => $status_label) : ?>
                            <option value="<?php echo esc_attr($slug); ?>" <?php selected($target_status, $slug); ?>>
                                <?php echo esc_html($status_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size: 12px;">&nbsp;</label>
                    <button type="button" class="button tmgmt-remove-action"><span class="dashicons dashicons-trash" style="margin-top: 4px;"></span></button>
                </div>
            </div>
        </div>
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

        // Save Actions
        if (isset($_POST['tmgmt_actions']) && is_array($_POST['tmgmt_actions'])) {
            $actions = array();
            foreach ($_POST['tmgmt_actions'] as $action) {
                if (!empty($action['label'])) {
                    $actions[] = array(
                        'label' => sanitize_text_field($action['label']),
                        'type' => sanitize_text_field($action['type']),
                        'webhook_id' => sanitize_text_field($action['webhook_id']),
                        'target_status' => sanitize_text_field($action['target_status']),
                    );
                }
            }
            update_post_meta($post_id, '_tmgmt_status_actions', $actions);
        } else {
            update_post_meta($post_id, '_tmgmt_status_actions', array());
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
