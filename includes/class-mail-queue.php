<?php

class TMGMT_Mail_Queue {

    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'tmgmt_mail_queue';
        add_action('admin_menu', array($this, 'add_admin_page'));
    }

    /**
     * Register the Mail Queue admin page as a submenu under tmgmt-settings-hidden.
     */
    public function add_admin_page() {
        add_submenu_page(
            'tmgmt-settings-hidden',
            'Mail Queue',
            'Mail Queue',
            'tmgmt_manage_settings',
            'tmgmt-mail-queue',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Creates the database table on plugin activation.
     * Uses dbDelta() for safe schema creation/updates.
     */
    public function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $this->table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            message_id varchar(255) NOT NULL,
            from_email varchar(255) NOT NULL,
            from_name varchar(255) DEFAULT '' NOT NULL,
            to_email varchar(255) DEFAULT '' NOT NULL,
            subject varchar(500) DEFAULT '' NOT NULL,
            body_text longtext NOT NULL,
            body_html longtext NOT NULL,
            email_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            fetched_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            status varchar(20) DEFAULT 'neu' NOT NULL,
            event_id bigint(20) DEFAULT 0 NOT NULL,
            assign_method varchar(20) DEFAULT '' NOT NULL,
            in_reply_to varchar(255) DEFAULT '' NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY message_id (message_id),
            KEY status (status),
            KEY event_id (event_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Inserts a new email into the mail queue.
     * Checks for duplicates via message_id before inserting.
     *
     * @param array $email_data Associative array with email fields.
     * @return int|false The row ID or false on duplicate/error.
     */
    public function insert(array $email_data) {
        global $wpdb;

        $message_id = isset($email_data['message_id']) ? $email_data['message_id'] : '';

        // Skip emails without a message_id
        if (empty($message_id)) {
            return false;
        }

        // Duplicate check
        if ($this->exists_by_message_id($message_id)) {
            return false;
        }

        $result = $wpdb->insert(
            $this->table_name,
            array(
                'message_id'    => sanitize_text_field($message_id),
                'from_email'    => sanitize_email($email_data['from_email'] ?? ''),
                'from_name'     => sanitize_text_field($email_data['from_name'] ?? ''),
                'to_email'      => sanitize_email($email_data['to_email'] ?? ''),
                'subject'       => sanitize_text_field($email_data['subject'] ?? ''),
                'body_text'     => wp_kses_post($email_data['body_text'] ?? ''),
                'body_html'     => wp_kses_post($email_data['body_html'] ?? ''),
                'email_date'    => $email_data['email_date'] ?? current_time('mysql'),
                'fetched_at'    => current_time('mysql'),
                'status'        => 'neu',
                'event_id'      => 0,
                'assign_method' => '',
                'in_reply_to'   => sanitize_text_field($email_data['in_reply_to'] ?? ''),
            ),
            array(
                '%s', '%s', '%s', '%s', '%s',
                '%s', '%s', '%s', '%s', '%s',
                '%d', '%s', '%s'
            )
        );

        if ($result) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Checks if an email with the given message_id already exists.
     *
     * @param string $message_id The Message-ID header value.
     * @return bool
     */
    public function exists_by_message_id(string $message_id): bool {
        global $wpdb;

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $this->table_name WHERE message_id = %s",
                $message_id
            )
        );

        return (int) $count > 0;
    }

    /**
     * Retrieves mail queue entries filtered by status.
     *
     * @param string $status One of: 'neu', 'zugeordnet', 'nicht_zugeordnet'
     * @return array Array of row objects.
     */
    public function get_by_status(string $status): array {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $this->table_name WHERE status = %s ORDER BY email_date DESC",
                $status
            )
        );
    }

    /**
     * Retrieves all mail queue entries.
     *
     * @return array Array of row objects.
     */
    public function get_all(): array {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT * FROM $this->table_name ORDER BY email_date DESC"
        );
    }

    /**
     * Counts all mail queue entries.
     *
     * @return int Total count.
     */
    public function count_all(): int {
        global $wpdb;

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $this->table_name"
        );
    }

    /**
     * Counts mail queue entries by status.
     *
     * @param string $status The status to count.
     * @return int Count for the given status.
     */
    public function count_by_status(string $status): int {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $this->table_name WHERE status = %s",
                $status
            )
        );
    }

    /**
     * Retrieves mail queue entries for a specific event.
     *
     * @param int $event_id The event post ID.
     * @return array Array of row objects.
     */
    public function get_by_event(int $event_id): array {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $this->table_name WHERE event_id = %d ORDER BY email_date DESC",
                $event_id
            )
        );
    }

    /**
     * Retrieves a single mail queue entry by ID.
     *
     * @param int $id The queue entry ID.
     * @return object|null The row object or null if not found.
     */
    public function get_by_id(int $id): ?object {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $this->table_name WHERE id = %d",
                $id
            )
        );

        return $row ?: null;
    }

    /**
     * Updates the status of a mail queue entry.
     * Optionally sets event_id and assign_method.
     *
     * @param int         $id       The queue entry ID.
     * @param string      $status   New status value.
     * @param int|null    $event_id Optional event post ID.
     * @param string|null $method   Optional assignment method.
     * @return bool True on success, false on failure.
     */
    public function update_status(int $id, string $status, ?int $event_id = null, ?string $method = null): bool {
        global $wpdb;

        $data = array('status' => $status);
        $format = array('%s');

        if ($event_id !== null) {
            $data['event_id'] = $event_id;
            $format[] = '%d';
        }

        if ($method !== null) {
            $data['assign_method'] = $method;
            $format[] = '%s';
        }

        $result = $wpdb->update(
            $this->table_name,
            $data,
            array('id' => $id),
            $format,
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Renders the Mail Queue admin overview page.
     * Shows status filter tabs, email table, and manual assignment controls.
     */
    public function render_admin_page() {
        $valid_statuses = array('neu', 'zugeordnet', 'nicht_zugeordnet');
        $current_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'nicht_zugeordnet';
        if (!in_array($current_status, $valid_statuses, true)) {
            $current_status = 'nicht_zugeordnet';
        }

        $emails = $this->get_by_status($current_status);
        $base_url = admin_url('admin.php?page=tmgmt-mail-queue');

        $status_labels = array(
            'neu'               => 'Neu',
            'zugeordnet'        => 'Zugeordnet',
            'nicht_zugeordnet'  => 'Nicht zugeordnet',
        );
        ?>
        <div class="wrap">
            <h1>Mail Queue</h1>

            <ul class="subsubsub">
                <?php $i = 0; foreach ($status_labels as $status_key => $label) : $i++; ?>
                    <li>
                        <a href="<?php echo esc_url(add_query_arg('status', $status_key, $base_url)); ?>"
                           class="<?php echo $current_status === $status_key ? 'current' : ''; ?>">
                            <?php echo esc_html($label); ?>
                            <span class="count">(<?php echo count($this->get_by_status($status_key)); ?>)</span>
                        </a>
                        <?php echo $i < count($status_labels) ? ' | ' : ''; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
                <thead>
                    <tr>
                        <th style="width: 20%;">Absender</th>
                        <th style="width: 30%;">Betreff</th>
                        <th style="width: 15%;">Datum</th>
                        <th style="width: 20%;">Vorschau</th>
                        <?php if ($current_status === 'nicht_zugeordnet') : ?>
                            <th style="width: 15%;">Zuordnung</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($emails)) : ?>
                        <tr>
                            <td colspan="<?php echo $current_status === 'nicht_zugeordnet' ? 5 : 4; ?>">
                                Keine E-Mails mit Status &quot;<?php echo esc_html($status_labels[$current_status]); ?>&quot; vorhanden.
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($emails as $email) : ?>
                            <tr>
                                <td>
                                    <?php echo esc_html($email->from_name ?: $email->from_email); ?>
                                    <?php if ($email->from_name) : ?>
                                        <br><small><?php echo esc_html($email->from_email); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($email->subject); ?></td>
                                <td><?php echo esc_html($email->email_date); ?></td>
                                <td><?php echo esc_html(wp_trim_words(wp_strip_all_tags($email->body_text ?: $email->body_html), 15, '…')); ?></td>
                                <?php if ($current_status === 'nicht_zugeordnet') : ?>
                                    <td>
                                        <div class="tmgmt-assign-row" data-queue-id="<?php echo esc_attr($email->id); ?>">
                                            <input type="text" class="tmgmt-event-search" placeholder="Event suchen…" style="width: 100%; margin-bottom: 4px;">
                                            <button type="button" class="button button-small tmgmt-assign-btn" disabled>Zuordnen</button>
                                            <span class="tmgmt-assign-result" style="display: block; margin-top: 4px;"></span>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($current_status === 'nicht_zugeordnet') : ?>
        <script>
        (function() {
            var restUrl = '<?php echo esc_url_raw(rest_url('tmgmt/v1')); ?>';
            var nonce = '<?php echo wp_create_nonce('wp_rest'); ?>';

            document.querySelectorAll('.tmgmt-assign-row').forEach(function(row) {
                var queueId = row.getAttribute('data-queue-id');
                var input = row.querySelector('.tmgmt-event-search');
                var btn = row.querySelector('.tmgmt-assign-btn');
                var result = row.querySelector('.tmgmt-assign-result');
                var selectedEventId = null;

                input.addEventListener('input', function() {
                    var val = this.value.trim();
                    selectedEventId = null;
                    btn.disabled = true;
                    result.textContent = '';

                    if (val.length < 2) return;

                    fetch(restUrl + '/events?search=' + encodeURIComponent(val) + '&per_page=5', {
                        headers: { 'X-WP-Nonce': nonce }
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(events) {
                        // Remove existing dropdown
                        var existing = row.querySelector('.tmgmt-event-dropdown');
                        if (existing) existing.remove();

                        if (!events.length) return;

                        var dropdown = document.createElement('div');
                        dropdown.className = 'tmgmt-event-dropdown';
                        dropdown.style.cssText = 'border:1px solid #ccc;background:#fff;max-height:120px;overflow-y:auto;position:absolute;z-index:10;width:' + input.offsetWidth + 'px;';

                        events.forEach(function(ev) {
                            var item = document.createElement('div');
                            item.style.cssText = 'padding:4px 8px;cursor:pointer;';
                            item.textContent = (ev.title && ev.title.rendered ? ev.title.rendered : ev.title) + ' (ID: ' + ev.id + ')';
                            item.addEventListener('mousedown', function(e) {
                                e.preventDefault();
                                selectedEventId = ev.id;
                                input.value = item.textContent;
                                btn.disabled = false;
                                dropdown.remove();
                            });
                            item.addEventListener('mouseenter', function() { this.style.background = '#f0f0f1'; });
                            item.addEventListener('mouseleave', function() { this.style.background = '#fff'; });
                            dropdown.appendChild(item);
                        });

                        input.parentNode.style.position = 'relative';
                        input.parentNode.appendChild(dropdown);
                    });
                });

                input.addEventListener('blur', function() {
                    setTimeout(function() {
                        var dd = row.querySelector('.tmgmt-event-dropdown');
                        if (dd) dd.remove();
                    }, 200);
                });

                btn.addEventListener('click', function() {
                    if (!selectedEventId) return;
                    btn.disabled = true;
                    result.textContent = 'Wird zugeordnet…';

                    fetch(restUrl + '/mail-queue/' + queueId + '/assign', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': nonce
                        },
                        body: JSON.stringify({ event_id: selectedEventId })
                    })
                    .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, data: d }; }); })
                    .then(function(res) {
                        if (res.ok) {
                            result.style.color = 'green';
                            result.textContent = 'Zugeordnet!';
                            setTimeout(function() { location.reload(); }, 800);
                        } else {
                            result.style.color = 'red';
                            result.textContent = res.data.message || 'Fehler bei der Zuordnung.';
                            btn.disabled = false;
                        }
                    })
                    .catch(function() {
                        result.style.color = 'red';
                        result.textContent = 'Netzwerkfehler.';
                        btn.disabled = false;
                    });
                });
            });
        })();
        </script>
        <?php endif; ?>
        <?php
    }
}
