<?php

class TMGMT_Action_Post_Type {

    const POST_TYPE = 'tmgmt_action';

    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function register_post_type() {
        $labels = array(
            'name'                  => 'Aktionen',
            'singular_name'         => 'Aktion',
            'menu_name'             => 'Aktionen',
            'add_new'               => 'Neue Aktion erstellen',
            'add_new_item'          => 'Neue Aktion erstellen',
            'edit_item'             => 'Aktion bearbeiten',
            'new_item'              => 'Neue Aktion',
            'view_item'             => 'Aktion ansehen',
            'search_items'          => 'Aktionen suchen',
            'not_found'             => 'Keine Aktionen gefunden',
            'not_found_in_trash'    => 'Keine Aktionen im Papierkorb gefunden',
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'action'),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array('title'),
        );

        register_post_type(self::POST_TYPE, $args);
    }

    public function add_meta_boxes() {
        add_meta_box(
            'tmgmt_action_settings',
            'Aktions-Einstellungen',
            array($this, 'render_settings_box'),
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public function render_settings_box($post) {
        wp_nonce_field('tmgmt_save_action', 'tmgmt_action_nonce');

        $type = get_post_meta($post->ID, '_tmgmt_action_type', true);
        if (!$type) $type = 'note';
        
        $webhook_id = get_post_meta($post->ID, '_tmgmt_action_webhook_id', true);
        $email_template_id = get_post_meta($post->ID, '_tmgmt_action_email_template_id', true);
        $target_status = get_post_meta($post->ID, '_tmgmt_action_target_status', true);
        
        // New Fields
        $attachment_id = get_post_meta($post->ID, '_tmgmt_action_attachment_id', true);
        $confirm_page_id = get_post_meta($post->ID, '_tmgmt_action_confirm_page', true);
        $send_receipt = get_post_meta($post->ID, '_tmgmt_action_send_receipt', true);
        $receipt_template_id = get_post_meta($post->ID, '_tmgmt_action_receipt_template', true);

        // Get Webhooks
        $webhooks = get_posts(array('post_type' => 'tmgmt_webhook', 'numberposts' => -1));
        
        // Get Email Templates
        $email_templates = get_posts(array('post_type' => 'tmgmt_email_template', 'numberposts' => -1));

        // Get Statuses
        $statuses = TMGMT_Event_Status::get_all_statuses();

        ?>
        <table class="form-table">
            <tr>
                <th><label for="tmgmt_action_type">Typ</label></th>
                <td>
                    <select name="tmgmt_action_type" id="tmgmt_action_type">
                        <option value="note" <?php selected($type, 'note'); ?>>Notiz / Doku</option>
                        <option value="webhook" <?php selected($type, 'webhook'); ?>>Webhook</option>
                        <option value="email" <?php selected($type, 'email'); ?>>E-Mail</option>
                        <option value="email_confirmation" <?php selected($type, 'email_confirmation'); ?>>E-Mail mit Bestätigung</option>
                    </select>
                </td>
            </tr>
            <tr class="tmgmt-webhook-row" style="display:none;">
                <th><label for="tmgmt_action_webhook_id">Webhook</label></th>
                <td>
                    <select name="tmgmt_action_webhook_id" id="tmgmt_action_webhook_id">
                        <option value="">-- Wählen --</option>
                        <?php foreach ($webhooks as $wh) : ?>
                            <option value="<?php echo esc_attr($wh->ID); ?>" <?php selected($webhook_id, $wh->ID); ?>>
                                <?php echo esc_html($wh->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr class="tmgmt-email-row" style="display:none;">
                <th><label for="tmgmt_action_email_template_id">E-Mail Vorlage</label></th>
                <td>
                    <select name="tmgmt_action_email_template_id" id="tmgmt_action_email_template_id">
                        <option value="">-- Wählen --</option>
                        <?php foreach ($email_templates as $et) : ?>
                            <option value="<?php echo esc_attr($et->ID); ?>" <?php selected($email_template_id, $et->ID); ?>>
                                <?php echo esc_html($et->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            
            <!-- Attachment Field (For Email & Confirmation) -->
            <tr class="tmgmt-attachment-row" style="display:none;">
                <th><label>Dateianhang (z.B. Rider)</label></th>
                <td>
                    <input type="hidden" name="tmgmt_action_attachment_id" id="tmgmt_action_attachment_id" value="<?php echo esc_attr($attachment_id); ?>">
                    <div id="tmgmt-attachment-preview">
                        <?php if ($attachment_id): 
                            $file_url = wp_get_attachment_url($attachment_id);
                            $file_name = basename($file_url);
                        ?>
                            <p>Aktuelle Datei: <a href="<?php echo esc_url($file_url); ?>" target="_blank"><?php echo esc_html($file_name); ?></a></p>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="button" id="tmgmt-upload-attachment">Datei wählen</button>
                    <button type="button" class="button" id="tmgmt-remove-attachment" <?php echo $attachment_id ? '' : 'style="display:none;"'; ?>>Entfernen</button>
                </td>
            </tr>

            <!-- Confirmation Specific Fields -->
            <tr class="tmgmt-confirmation-row" style="display:none;">
                <th><label for="tmgmt_action_confirm_page">Bestätigungs-Seite (Danke-Seite)</label></th>
                <td>
                    <?php 
                    wp_dropdown_pages(array(
                        'name' => 'tmgmt_action_confirm_page',
                        'id' => 'tmgmt_action_confirm_page',
                        'selected' => $confirm_page_id,
                        'show_option_none' => '-- Standard --'
                    )); 
                    ?>
                    <p class="description">Seite, auf die der Nutzer nach Klick auf den Bestätigungslink geleitet wird.</p>
                </td>
            </tr>
            <tr class="tmgmt-confirmation-row" style="display:none;">
                <th><label for="tmgmt_action_send_receipt">Bestätigung der Bestätigung senden?</label></th>
                <td>
                    <input type="checkbox" name="tmgmt_action_send_receipt" id="tmgmt_action_send_receipt" value="1" <?php checked($send_receipt, 1); ?>>
                    Ja, eine E-Mail senden, wenn der Link geklickt wurde.
                </td>
            </tr>
            <tr class="tmgmt-receipt-row" style="display:none;">
                <th><label for="tmgmt_action_receipt_template">Vorlage für Bestätigungs-Bestätigung</label></th>
                <td>
                    <select name="tmgmt_action_receipt_template" id="tmgmt_action_receipt_template">
                        <option value="">-- Wählen --</option>
                        <?php foreach ($email_templates as $et) : ?>
                            <option value="<?php echo esc_attr($et->ID); ?>" <?php selected($receipt_template_id, $et->ID); ?>>
                                <?php echo esc_html($et->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <tr>
                <th><label for="tmgmt_action_target_status">Ziel-Status (Optional)</label></th>
                <td>
                    <select name="tmgmt_action_target_status" id="tmgmt_action_target_status">
                        <option value="">-- Kein Wechsel --</option>
                        <?php foreach ($statuses as $slug => $label) : ?>
                            <option value="<?php echo esc_attr($slug); ?>" <?php selected($target_status, $slug); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Wenn gesetzt, wird der Status des Events nach Ausführung der Aktion geändert.</p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_meta_boxes($post_id) {
        if (!isset($_POST['tmgmt_action_nonce']) || !wp_verify_nonce($_POST['tmgmt_action_nonce'], 'tmgmt_save_action')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        if (isset($_POST['tmgmt_action_type'])) {
            update_post_meta($post_id, '_tmgmt_action_type', sanitize_text_field($_POST['tmgmt_action_type']));
        }
        if (isset($_POST['tmgmt_action_webhook_id'])) {
            update_post_meta($post_id, '_tmgmt_action_webhook_id', sanitize_text_field($_POST['tmgmt_action_webhook_id']));
        }
        if (isset($_POST['tmgmt_action_email_template_id'])) {
            update_post_meta($post_id, '_tmgmt_action_email_template_id', sanitize_text_field($_POST['tmgmt_action_email_template_id']));
        }
        if (isset($_POST['tmgmt_action_target_status'])) {
            update_post_meta($post_id, '_tmgmt_action_target_status', sanitize_text_field($_POST['tmgmt_action_target_status']));
        }
        
        // Save New Fields
        if (isset($_POST['tmgmt_action_attachment_id'])) {
            update_post_meta($post_id, '_tmgmt_action_attachment_id', sanitize_text_field($_POST['tmgmt_action_attachment_id']));
        }
        if (isset($_POST['tmgmt_action_confirm_page'])) {
            update_post_meta($post_id, '_tmgmt_action_confirm_page', sanitize_text_field($_POST['tmgmt_action_confirm_page']));
        }
        
        $send_receipt = isset($_POST['tmgmt_action_send_receipt']) ? 1 : 0;
        update_post_meta($post_id, '_tmgmt_action_send_receipt', $send_receipt);

        if (isset($_POST['tmgmt_action_receipt_template'])) {
            update_post_meta($post_id, '_tmgmt_action_receipt_template', sanitize_text_field($_POST['tmgmt_action_receipt_template']));
        }
    }

    public function enqueue_scripts($hook) {
        global $post_type;
        if ($hook == 'post-new.php' || $hook == 'post.php') {
            if ($post_type === self::POST_TYPE) {
                wp_enqueue_media(); // Enqueue Media Uploader
                add_action('admin_footer', array($this, 'print_admin_scripts'));
            }
        }
    }

    public function print_admin_scripts() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            function toggleFields() {
                var type = $('#tmgmt_action_type').val();
                $('.tmgmt-webhook-row').hide();
                $('.tmgmt-email-row').hide();
                $('.tmgmt-attachment-row').hide();
                $('.tmgmt-confirmation-row').hide();
                $('.tmgmt-receipt-row').hide();
                
                if (type === 'webhook') {
                    $('.tmgmt-webhook-row').show();
                } else if (type === 'email') {
                    $('.tmgmt-email-row').show();
                    $('.tmgmt-attachment-row').show();
                } else if (type === 'email_confirmation') {
                    $('.tmgmt-email-row').show();
                    $('.tmgmt-attachment-row').show();
                    $('.tmgmt-confirmation-row').show();
                    if ($('#tmgmt_action_send_receipt').is(':checked')) {
                        $('.tmgmt-receipt-row').show();
                    }
                }
            }
            
            $('#tmgmt_action_type').change(toggleFields);
            $('#tmgmt_action_send_receipt').change(toggleFields);
            toggleFields();

            // Media Uploader
            var file_frame;
            $('#tmgmt-upload-attachment').on('click', function(event){
                event.preventDefault();
                if ( file_frame ) {
                    file_frame.open();
                    return;
                }
                file_frame = wp.media.frames.file_frame = wp.media({
                    title: 'Datei auswählen',
                    button: {
                        text: 'Datei verwenden'
                    },
                    multiple: false
                });
                file_frame.on( 'select', function() {
                    var attachment = file_frame.state().get('selection').first().toJSON();
                    $('#tmgmt_action_attachment_id').val(attachment.id);
                    $('#tmgmt-attachment-preview').html('<p>Aktuelle Datei: <a href="'+attachment.url+'" target="_blank">'+attachment.filename+'</a></p>');
                    $('#tmgmt-remove-attachment').show();
                });
                file_frame.open();
            });

            $('#tmgmt-remove-attachment').on('click', function(event){
                event.preventDefault();
                $('#tmgmt_action_attachment_id').val('');
                $('#tmgmt-attachment-preview').html('');
                $(this).hide();
            });
        });
        </script>
        <?php
    }
}
