<?php
/**
 * Mail Queue Admin Page
 * 
 * Provides an admin interface to view, filter, and manage the mail queue.
 */
if (!defined('ABSPATH')) exit;

class TMGMT_Mail_Queue_Admin {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_page'));
    }

    /**
     * Register the Mail Queue admin page.
     */
    public function add_menu_page() {
        add_submenu_page(
            'edit.php?post_type=event',
            'E-Mail Posteingang',
            'E-Mail Posteingang',
            'edit_posts',
            'tmgmt-mail-queue',
            array($this, 'render_page')
        );
    }

    /**
     * Render the Mail Queue admin page.
     */
    public function render_page() {
        $queue = new TMGMT_Mail_Queue();
        
        // Get current filter
        $current_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        
        // Get counts for tabs
        $count_all = $queue->count_all();
        $count_new = $queue->count_by_status('neu');
        $count_assigned = $queue->count_by_status('zugeordnet');
        $count_replied = $queue->count_by_status('beantwortet');
        
        // Get emails based on filter
        if ($current_status) {
            $emails = $queue->get_by_status($current_status);
        } else {
            $emails = $queue->get_all();
        }
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">E-Mail Posteingang</h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=tmgmt-connection-settings')); ?>" class="page-title-action">Verbindung konfigurieren</a>
            <button type="button" class="page-title-action" id="tmgmt-fetch-emails">Jetzt abrufen</button>
            <span id="tmgmt-fetch-status" style="margin-left: 10px;"></span>
            
            <hr class="wp-header-end">
            
            <!-- Status Filter Tabs -->
            <ul class="subsubsub">
                <li>
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=event&page=tmgmt-mail-queue')); ?>" 
                       class="<?php echo empty($current_status) ? 'current' : ''; ?>">
                        Alle <span class="count">(<?php echo $count_all; ?>)</span>
                    </a> |
                </li>
                <li>
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=event&page=tmgmt-mail-queue&status=neu')); ?>"
                       class="<?php echo $current_status === 'neu' ? 'current' : ''; ?>">
                        Neu <span class="count">(<?php echo $count_new; ?>)</span>
                    </a> |
                </li>
                <li>
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=event&page=tmgmt-mail-queue&status=zugeordnet')); ?>"
                       class="<?php echo $current_status === 'zugeordnet' ? 'current' : ''; ?>">
                        Zugeordnet <span class="count">(<?php echo $count_assigned; ?>)</span>
                    </a> |
                </li>
                <li>
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=event&page=tmgmt-mail-queue&status=beantwortet')); ?>"
                       class="<?php echo $current_status === 'beantwortet' ? 'current' : ''; ?>">
                        Beantwortet <span class="count">(<?php echo $count_replied; ?>)</span>
                    </a>
                </li>
            </ul>
            
            <table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
                <thead>
                    <tr>
                        <th scope="col" style="width: 200px;">Von</th>
                        <th scope="col">Betreff</th>
                        <th scope="col" style="width: 150px;">Datum</th>
                        <th scope="col" style="width: 100px;">Status</th>
                        <th scope="col" style="width: 200px;">Zugeordnet zu</th>
                        <th scope="col" style="width: 150px;">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($emails)) : ?>
                        <tr>
                            <td colspan="6">Keine E-Mails gefunden.</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($emails as $email) : ?>
                            <?php
                            $event_title = '';
                            if ($email->event_id) {
                                $event = get_post($email->event_id);
                                $event_title = $event ? $event->post_title : '(Gelöscht)';
                            }
                            $status_labels = array(
                                'neu' => '<span class="status-neu" style="color: #d63638; font-weight: bold;">Neu</span>',
                                'zugeordnet' => '<span class="status-zugeordnet" style="color: #dba617;">Zugeordnet</span>',
                                'beantwortet' => '<span class="status-beantwortet" style="color: #00a32a;">Beantwortet</span>',
                            );
                            $status_html = isset($status_labels[$email->status]) ? $status_labels[$email->status] : esc_html($email->status);
                            ?>
                            <tr data-email-id="<?php echo esc_attr($email->id); ?>">
                                <td>
                                    <strong><?php echo esc_html($email->from_name ?: $email->from_email); ?></strong>
                                    <?php if ($email->from_name) : ?>
                                        <br><small><?php echo esc_html($email->from_email); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="#" class="tmgmt-view-email" data-id="<?php echo esc_attr($email->id); ?>">
                                        <?php echo esc_html($email->subject ?: '(Kein Betreff)'); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html(date_i18n('d.m.Y H:i', strtotime($email->email_date))); ?></td>
                                <td><?php echo $status_html; ?></td>
                                <td>
                                    <?php if ($email->event_id) : ?>
                                        <a href="<?php echo esc_url(get_edit_post_link($email->event_id)); ?>">
                                            <?php echo esc_html($event_title); ?>
                                        </a>
                                    <?php else : ?>
                                        <em>Nicht zugeordnet</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="button button-small tmgmt-view-email" data-id="<?php echo esc_attr($email->id); ?>">Ansehen</button>
                                    <?php if (!$email->event_id) : ?>
                                        <button type="button" class="button button-small tmgmt-assign-email" data-id="<?php echo esc_attr($email->id); ?>">Zuordnen</button>
                                        <button type="button" class="button button-small button-primary tmgmt-create-event" data-id="<?php echo esc_attr($email->id); ?>" data-subject="<?php echo esc_attr($email->subject); ?>" data-from="<?php echo esc_attr($email->from_email); ?>" data-from-name="<?php echo esc_attr($email->from_name); ?>">+ Event</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Email View Modal -->
        <div id="tmgmt-email-modal" style="display: none;">
            <div class="tmgmt-modal-backdrop"></div>
            <div class="tmgmt-modal-content">
                <div class="tmgmt-modal-header">
                    <h2 id="tmgmt-email-subject">E-Mail</h2>
                    <button type="button" class="tmgmt-modal-close">&times;</button>
                </div>
                <div class="tmgmt-modal-body">
                    <div class="tmgmt-email-meta">
                        <p><strong>Von:</strong> <span id="tmgmt-email-from"></span></p>
                        <p><strong>An:</strong> <span id="tmgmt-email-to"></span></p>
                        <p><strong>Datum:</strong> <span id="tmgmt-email-date"></span></p>
                    </div>
                    <hr>
                    <div id="tmgmt-email-body"></div>
                </div>
                <div class="tmgmt-modal-footer">
                    <button type="button" class="button tmgmt-modal-close">Schließen</button>
                </div>
            </div>
        </div>
        
        <!-- Assign Modal -->
        <div id="tmgmt-assign-modal" style="display: none;">
            <div class="tmgmt-modal-backdrop"></div>
            <div class="tmgmt-modal-content" style="max-width: 500px;">
                <div class="tmgmt-modal-header">
                    <h2>E-Mail zuordnen</h2>
                    <button type="button" class="tmgmt-modal-close">&times;</button>
                </div>
                <div class="tmgmt-modal-body">
                    <p>Wählen Sie ein Event aus:</p>
                    <input type="hidden" id="tmgmt-assign-email-id" value="">
                    <select id="tmgmt-assign-event-select" style="width: 100%;">
                        <option value="">-- Event auswählen --</option>
                        <?php
                        $events = get_posts(array(
                            'post_type' => 'event',
                            'posts_per_page' => -1,
                            'orderby' => 'date',
                            'order' => 'DESC',
                        ));
                        foreach ($events as $event) {
                            $date = get_post_meta($event->ID, '_tmgmt_event_date', true);
                            $date_str = $date ? date_i18n('d.m.Y', strtotime($date)) : '';
                            printf(
                                '<option value="%d">%s%s</option>',
                                $event->ID,
                                esc_html($event->post_title),
                                $date_str ? ' (' . $date_str . ')' : ''
                            );
                        }
                        ?>
                    </select>
                </div>
                <div class="tmgmt-modal-footer">
                    <button type="button" class="button tmgmt-modal-close">Abbrechen</button>
                    <button type="button" class="button button-primary" id="tmgmt-assign-confirm">Zuordnen</button>
                </div>
            </div>
        </div>
        
        <style>
            .tmgmt-modal-backdrop {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.7);
                z-index: 100000;
            }
            .tmgmt-modal-content {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: #fff;
                border-radius: 4px;
                max-width: 800px;
                width: 90%;
                max-height: 80vh;
                display: flex;
                flex-direction: column;
                z-index: 100001;
            }
            .tmgmt-modal-header {
                padding: 15px 20px;
                border-bottom: 1px solid #ddd;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .tmgmt-modal-header h2 {
                margin: 0;
                font-size: 18px;
            }
            .tmgmt-modal-close {
                background: none;
                border: none;
                font-size: 24px;
                cursor: pointer;
                padding: 0;
                line-height: 1;
            }
            .tmgmt-modal-body {
                padding: 20px;
                overflow-y: auto;
                flex: 1;
            }
            .tmgmt-modal-footer {
                padding: 15px 20px;
                border-top: 1px solid #ddd;
                text-align: right;
            }
            .tmgmt-email-meta p {
                margin: 5px 0;
            }
            #tmgmt-email-body {
                white-space: pre-wrap;
                font-family: inherit;
            }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            var restUrl = '<?php echo esc_url_raw(rest_url('tmgmt/v1/')); ?>';
            var nonce = '<?php echo wp_create_nonce('wp_rest'); ?>';
            
            // Fetch emails button
            $('#tmgmt-fetch-emails').on('click', function() {
                var btn = $(this);
                var status = $('#tmgmt-fetch-status');
                btn.prop('disabled', true);
                status.text('Rufe E-Mails ab...').css('color', '#666');
                
                $.ajax({
                    url: restUrl + 'imap/fetch-now',
                    method: 'POST',
                    headers: { 'X-WP-Nonce': nonce },
                    success: function(data) {
                        status.text(data.message || 'Fertig').css('color', '#00a32a');
                        btn.prop('disabled', false);
                        if (data.inserted > 0) {
                            setTimeout(function() { location.reload(); }, 1000);
                        }
                    },
                    error: function() {
                        status.text('Fehler beim Abrufen').css('color', '#d63638');
                        btn.prop('disabled', false);
                    }
                });
            });
            
            // View email
            $('.tmgmt-view-email').on('click', function(e) {
                e.preventDefault();
                var id = $(this).data('id');
                
                $.ajax({
                    url: restUrl + 'mail-queue/' + id,
                    method: 'GET',
                    headers: { 'X-WP-Nonce': nonce },
                    success: function(data) {
                        $('#tmgmt-email-subject').text(data.subject || '(Kein Betreff)');
                        $('#tmgmt-email-from').text((data.from_name ? data.from_name + ' <' + data.from_email + '>' : data.from_email));
                        $('#tmgmt-email-to').text(data.to_email || '-');
                        $('#tmgmt-email-date').text(data.email_date || '-');
                        
                        // Prefer HTML body, fallback to text
                        if (data.body_html) {
                            $('#tmgmt-email-body').html(data.body_html);
                        } else {
                            $('#tmgmt-email-body').text(data.body_text || '(Kein Inhalt)');
                        }
                        
                        $('#tmgmt-email-modal').show();
                    },
                    error: function() {
                        alert('Fehler beim Laden der E-Mail');
                    }
                });
            });
            
            // Assign email
            $('.tmgmt-assign-email').on('click', function(e) {
                e.preventDefault();
                var id = $(this).data('id');
                $('#tmgmt-assign-email-id').val(id);
                $('#tmgmt-assign-event-select').val('');
                $('#tmgmt-assign-modal').show();
            });
            
            // Confirm assignment
            $('#tmgmt-assign-confirm').on('click', function() {
                var emailId = $('#tmgmt-assign-email-id').val();
                var eventId = $('#tmgmt-assign-event-select').val();
                
                if (!eventId) {
                    alert('Bitte wählen Sie ein Event aus.');
                    return;
                }
                
                $.ajax({
                    url: restUrl + 'mail-queue/' + emailId + '/assign',
                    method: 'POST',
                    headers: { 'X-WP-Nonce': nonce },
                    contentType: 'application/json',
                    data: JSON.stringify({ event_id: parseInt(eventId) }),
                    success: function(data) {
                        $('#tmgmt-assign-modal').hide();
                        location.reload();
                    },
                    error: function(xhr) {
                        var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Fehler bei der Zuordnung';
                        alert(msg);
                    }
                });
            });
            
            // Create event from email
            $('.tmgmt-create-event').on('click', function(e) {
                e.preventDefault();
                var btn = $(this);
                var id = btn.data('id');
                var subject = btn.data('subject');
                
                if (!confirm('Neues Event erstellen aus E-Mail:\n\n"' + (subject || '(Kein Betreff)') + '"')) {
                    return;
                }
                
                btn.prop('disabled', true).text('Erstelle...');
                
                $.ajax({
                    url: restUrl + 'mail-queue/' + id + '/create-event',
                    method: 'POST',
                    headers: { 'X-WP-Nonce': nonce },
                    success: function(data) {
                        if (data.success && data.edit_url) {
                            // Redirect to the new event
                            window.location.href = data.edit_url;
                        } else {
                            alert(data.message || 'Event erstellt.');
                            location.reload();
                        }
                    },
                    error: function(xhr) {
                        var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Fehler beim Erstellen';
                        alert(msg);
                        btn.prop('disabled', false).text('+ Event');
                    }
                });
            });
            
            // Close modals
            $('.tmgmt-modal-close, .tmgmt-modal-backdrop').on('click', function() {
                $(this).closest('[id$="-modal"]').hide();
            });
            
            // ESC to close
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    $('[id$="-modal"]').hide();
                }
            });
        });
        </script>
        <?php
    }
}
