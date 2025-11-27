<?php

class TMGMT_Email_Template_Post_Type {

    const POST_TYPE = 'tmgmt_email_template';

    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function enqueue_scripts($hook) {
        global $post_type;
        if ($hook == 'post-new.php' || $hook == 'post.php') {
            if ($post_type === self::POST_TYPE) {
                wp_enqueue_media();
                add_action('admin_footer', array($this, 'print_admin_scripts'));
            }
        }
    }

    public function print_admin_scripts() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            var file_frame;
            
            $('#tmgmt-add-attachments').on('click', function(event){
                event.preventDefault();
                
                if ( file_frame ) {
                    file_frame.open();
                    return;
                }
                
                file_frame = wp.media.frames.file_frame = wp.media({
                    title: 'Anhänge auswählen',
                    button: {
                        text: 'Auswahl hinzufügen'
                    },
                    multiple: true
                });
                
                file_frame.on( 'select', function() {
                    var selection = file_frame.state().get('selection');
                    selection.map( function( attachment ) {
                        attachment = attachment.toJSON();
                        
                        // Check if already added
                        if ($('#attachment-row-' + attachment.id).length === 0) {
                            var html = '<div id="attachment-row-' + attachment.id + '" style="margin-bottom: 5px; padding: 5px; background: #fff; border: 1px solid #ddd; display: flex; align-items: center;">';
                            html += '<input type="hidden" name="tmgmt_email_attachments[]" value="' + attachment.id + '">';
                            html += '<span style="flex-grow: 1;">' + attachment.filename + '</span>';
                            html += '<a href="#" class="tmgmt-remove-attachment" style="color: #a00; text-decoration: none;">Entfernen</a>';
                            html += '</div>';
                            
                            $('#tmgmt-attachments-list').append(html);
                        }
                    });
                });
                
                file_frame.open();
            });

            $(document).on('click', '.tmgmt-remove-attachment', function(e) {
                e.preventDefault();
                $(this).parent().remove();
            });
        });
        </script>
        <?php
    }

    public function register_post_type() {
        $labels = array(
            'name'                  => 'E-Mail Vorlagen',
            'singular_name'         => 'E-Mail Vorlage',
            'menu_name'             => 'E-Mail Vorlagen',
            'add_new'               => 'Neue Vorlage erstellen',
            'add_new_item'          => 'Neue Vorlage erstellen',
            'edit_item'             => 'Vorlage bearbeiten',
            'new_item'              => 'Neue Vorlage',
            'view_item'             => 'Vorlage ansehen',
            'search_items'          => 'Vorlagen suchen',
            'not_found'             => 'Keine Vorlagen gefunden',
            'not_found_in_trash'    => 'Keine Vorlagen im Papierkorb gefunden',
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'email-template'),
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
            'tmgmt_email_settings',
            'E-Mail Konfiguration',
            array($this, 'render_settings_box'),
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public function render_settings_box($post) {
        wp_nonce_field('tmgmt_save_email_template', 'tmgmt_email_template_nonce');

        $recipient = get_post_meta($post->ID, '_tmgmt_email_recipient', true);
        $subject = get_post_meta($post->ID, '_tmgmt_email_subject', true);
        $body = get_post_meta($post->ID, '_tmgmt_email_body', true);
        $cc = get_post_meta($post->ID, '_tmgmt_email_cc', true);
        $bcc = get_post_meta($post->ID, '_tmgmt_email_bcc', true);
        $reply_to = get_post_meta($post->ID, '_tmgmt_email_reply_to', true);
        ?>
        <div style="display: flex; gap: 10px; margin-bottom: 15px;">
            <div style="flex: 1;">
                <label for="tmgmt_email_recipient" style="display:block; font-weight:bold; margin-bottom:5px;">Empfänger (To)</label>
                <input type="text" id="tmgmt_email_recipient" name="tmgmt_email_recipient" value="<?php echo esc_attr($recipient); ?>" class="widefat">
                <p class="description" style="font-size:11px; color:#888;">Leer lassen für Standard: [contact_email_contract]</p>
            </div>
            <div style="flex: 1;">
                <label for="tmgmt_email_subject" style="display:block; font-weight:bold; margin-bottom:5px;">Betreff</label>
                <input type="text" id="tmgmt_email_subject" name="tmgmt_email_subject" value="<?php echo esc_attr($subject); ?>" class="widefat">
                <p class="description" style="font-size:11px; color:#888;">Leer lassen für Standard: Info: [event_title]</p>
            </div>
        </div>

        <div style="display: flex; gap: 10px; margin-bottom: 15px;">
            <div style="flex: 1;">
                <label for="tmgmt_email_cc" style="display:block; font-weight:bold; margin-bottom:5px;">CC</label>
                <input type="text" id="tmgmt_email_cc" name="tmgmt_email_cc" value="<?php echo esc_attr($cc); ?>" class="widefat">
            </div>
            <div style="flex: 1;">
                <label for="tmgmt_email_bcc" style="display:block; font-weight:bold; margin-bottom:5px;">BCC</label>
                <input type="text" id="tmgmt_email_bcc" name="tmgmt_email_bcc" value="<?php echo esc_attr($bcc); ?>" class="widefat">
            </div>
            <div style="flex: 1;">
                <label for="tmgmt_email_reply_to" style="display:block; font-weight:bold; margin-bottom:5px;">Reply-To</label>
                <input type="text" id="tmgmt_email_reply_to" name="tmgmt_email_reply_to" value="<?php echo esc_attr($reply_to); ?>" class="widefat">
            </div>
        </div>

        <div style="margin-bottom: 15px;">
            <label for="tmgmt_email_body" style="display:block; font-weight:bold; margin-bottom:5px;">Nachricht</label>
            <textarea id="tmgmt_email_body" name="tmgmt_email_body" rows="10" class="widefat"><?php echo esc_textarea($body); ?></textarea>
        </div>

        <div style="margin-bottom: 15px;">
            <label style="display:block; font-weight:bold; margin-bottom:5px;">Dateianhänge</label>
            <div id="tmgmt-attachments-list" style="margin-bottom: 10px; background: #f1f1f1; padding: 10px; border: 1px solid #ddd;">
                <?php 
                $attachments = get_post_meta($post->ID, '_tmgmt_email_attachments', true);
                if (is_array($attachments)) {
                    foreach ($attachments as $att_id) {
                        $att_path = get_attached_file($att_id);
                        if ($att_path) {
                            $filename = basename($att_path);
                            echo '<div id="attachment-row-' . $att_id . '" style="margin-bottom: 5px; padding: 5px; background: #fff; border: 1px solid #ddd; display: flex; align-items: center;">';
                            echo '<input type="hidden" name="tmgmt_email_attachments[]" value="' . esc_attr($att_id) . '">';
                            echo '<span style="flex-grow: 1;">' . esc_html($filename) . '</span>';
                            echo '<a href="#" class="tmgmt-remove-attachment" style="color: #a00; text-decoration: none;">Entfernen</a>';
                            echo '</div>';
                        }
                    }
                }
                ?>
            </div>
            <button type="button" class="button" id="tmgmt-add-attachments">Dateien hinzufügen</button>
        </div>

        <div style="font-size: 12px; color: #666; background: #f9f9f9; padding: 10px; border: 1px solid #ddd;">
            <strong>Verfügbare Platzhalter:</strong> [event_title], [event_date], [event_start_time], [venue_name], [contact_firstname], [contact_lastname], [contact_email_contract], [fee], [deposit] ...
        </div>
        <?php
    }

    public function save_meta_boxes($post_id) {
        if (!isset($_POST['tmgmt_email_template_nonce']) || !wp_verify_nonce($_POST['tmgmt_email_template_nonce'], 'tmgmt_save_email_template')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $fields = array(
            'tmgmt_email_recipient',
            'tmgmt_email_subject',
            'tmgmt_email_cc',
            'tmgmt_email_bcc',
            'tmgmt_email_reply_to'
        );

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST[$field]));
            }
        }

        if (isset($_POST['tmgmt_email_body'])) {
            update_post_meta($post_id, '_tmgmt_email_body', wp_kses_post($_POST['tmgmt_email_body']));
        }

        // Save Attachments
        if (isset($_POST['tmgmt_email_attachments']) && is_array($_POST['tmgmt_email_attachments'])) {
            $attachments = array_map('intval', $_POST['tmgmt_email_attachments']);
            update_post_meta($post_id, '_tmgmt_email_attachments', $attachments);
        } else {
            delete_post_meta($post_id, '_tmgmt_email_attachments');
        }
    }
}
