<?php
/**
 * Invoice Post Type
 *
 * Registers the 'tmgmt_invoice' custom post type.
 */

if (!defined('ABSPATH')) {
    exit;
}

class TMGMT_Invoice_Post_Type {

    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
    }

    public function register_post_type() {
        $labels = array(
            'name'               => __('Rechnungen', 'toens-mgmt'),
            'singular_name'      => __('Rechnung', 'toens-mgmt'),
            'menu_name'          => __('Rechnungen', 'toens-mgmt'),
            'add_new'            => __('Neue Rechnung', 'toens-mgmt'),
            'add_new_item'       => __('Neue Rechnung hinzufügen', 'toens-mgmt'),
            'edit_item'          => __('Rechnung bearbeiten', 'toens-mgmt'),
            'view_item'          => __('Rechnung ansehen', 'toens-mgmt'),
            'all_items'          => __('Alle Rechnungen', 'toens-mgmt'),
            'search_items'       => __('Rechnungen suchen', 'toens-mgmt'),
            'not_found'          => __('Keine Rechnungen gefunden.', 'toens-mgmt'),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => 'edit.php?post_type=event', // Submenu of Events
            'query_var'          => true,
            'rewrite'            => array('slug' => 'tmgmt-invoice'),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 25,
            'supports'           => array('title'),
            'show_in_rest'       => false,
        );

        register_post_type('tmgmt_invoice', $args);
    }

    public function add_meta_boxes() {
        add_meta_box(
            'tmgmt_invoice_details',
            'Rechnungsdaten',
            array($this, 'render_details_box'),
            'tmgmt_invoice',
            'normal',
            'high'
        );
    }

    public function render_details_box($post) {
        wp_nonce_field('tmgmt_save_invoice_meta', 'tmgmt_invoice_meta_nonce');

        // Fetch events for dropdown
        $events = get_posts(array(
            'post_type' => 'event',
            'numberposts' => 50,
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        // Define possible statuses
        $statuses = array(
            'draft' => 'Entwurf',
            'sent' => 'Gesendet',
            'paid' => 'Bezahlt',
            'cancelled' => 'Storniert'
        );

        // Define possible invoice types
        $types = array(
            'standard' => 'Standard',
            'credit' => 'Gutschrift',
            'cancellation' => 'Stornorechnung'
        );

        $event_id = get_post_meta($post->ID, '_tmgmt_invoice_event_id', true);
        $type = get_post_meta($post->ID, '_tmgmt_invoice_type', true);
        $number = get_post_meta($post->ID, '_tmgmt_invoice_number', true);
        $ref_number = get_post_meta($post->ID, '_tmgmt_invoice_ref_number', true);
        $date = get_post_meta($post->ID, '_tmgmt_invoice_date', true);
        $service_date = get_post_meta($post->ID, '_tmgmt_invoice_service_date', true);
        $recipient = get_post_meta($post->ID, '_tmgmt_invoice_recipient', true);
        $due_date = get_post_meta($post->ID, '_tmgmt_invoice_due_date', true);
        $intro_text = get_post_meta($post->ID, '_tmgmt_invoice_intro_text', true);
        $closing_text = get_post_meta($post->ID, '_tmgmt_invoice_closing_text', true);
        $payment_info = get_post_meta($post->ID, '_tmgmt_invoice_payment_info', true);
        $accounting_id = get_post_meta($post->ID, '_tmgmt_invoice_accounting_id', true);
        $status = get_post_meta($post->ID, '_tmgmt_invoice_status', true);
        $pdf_url = get_post_meta($post->ID, '_tmgmt_invoice_pdf_url', true);
        $invoice_items = get_post_meta($post->ID, '_tmgmt_invoice_items', true);
        $invoice_items = $invoice_items ? json_decode($invoice_items, true) : array();
        $services = get_posts(array('post_type' => 'tmgmt_service', 'numberposts' => 50, 'orderby' => 'date', 'order' => 'DESC'));
        $invoice_total = get_post_meta($post->ID, '_tmgmt_invoice_total', true);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="tmgmt_invoice_event_id">Zugehöriges Event</label></th>
                <td>
                    <select name="tmgmt_invoice_event_id" id="tmgmt_invoice_event_id" class="regular-text">
                        <option value="">Kein Event</option>
                        <?php foreach ($events as $event) : ?>
                            <option value="<?php echo $event->ID; ?>" <?php selected($event_id, $event->ID); ?>>
                                <?php echo esc_html($event->post_title); ?> (<?php echo get_the_date('d.m.Y', $event->ID); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="tmgmt_invoice_status">Status</label></th>
                <td>
                    <select name="tmgmt_invoice_status" id="tmgmt_invoice_status" class="regular-text">
                        <?php foreach ($statuses as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($status, $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="tmgmt_invoice_type">Rechnungstyp</label></th>
                <td>
                    <select name="tmgmt_invoice_type" id="tmgmt_invoice_type" class="regular-text">
                        <?php foreach ($types as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($type, $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="tmgmt_invoice_number">Rechnungsnummer</label></th>
                <td><input type="text" name="tmgmt_invoice_number" id="tmgmt_invoice_number" value="<?php echo esc_attr($number); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="tmgmt_invoice_ref_number">Referenznummer</label></th>
                <td><input type="text" name="tmgmt_invoice_ref_number" id="tmgmt_invoice_ref_number" value="<?php echo esc_attr($ref_number); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="tmgmt_invoice_date">Rechnungsdatum</label></th>
                <td><input type="date" name="tmgmt_invoice_date" id="tmgmt_invoice_date" value="<?php echo esc_attr($date); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="tmgmt_invoice_service_date">Leistungsdatum</label></th>
                <td><input type="date" name="tmgmt_invoice_service_date" id="tmgmt_invoice_service_date" value="<?php echo esc_attr($service_date); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="tmgmt_invoice_due_date">Zahlungsziel</label></th>
                <td><input type="date" name="tmgmt_invoice_due_date" id="tmgmt_invoice_due_date" value="<?php echo esc_attr($due_date); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="tmgmt_invoice_recipient">Rechnungsempfänger</label></th>
                <td><textarea name="tmgmt_invoice_recipient" id="tmgmt_invoice_recipient" rows="4" class="large-text"><?php echo esc_textarea($recipient); ?></textarea></td>
            </tr>
            <tr>
                <th><label for="tmgmt_invoice_intro_text">Anschreibentext</label></th>
                <td><textarea name="tmgmt_invoice_intro_text" id="tmgmt_invoice_intro_text" rows="4" class="large-text"><?php echo esc_textarea($intro_text); ?></textarea></td>
            </tr>
            <tr>
                <th><label for="tmgmt_invoice_closing_text">Schlusstext</label></th>
                <td><textarea name="tmgmt_invoice_closing_text" id="tmgmt_invoice_closing_text" rows="4" class="large-text"><?php echo esc_textarea($closing_text); ?></textarea></td>
            </tr>
            <tr>
                <th><label for="tmgmt_invoice_payment_info">Zahlungsinformation</label></th>
                <td><textarea name="tmgmt_invoice_payment_info" id="tmgmt_invoice_payment_info" rows="4" class="large-text"><?php echo esc_textarea($payment_info); ?></textarea></td>
            </tr>
            <tr>
                <th><label for="tmgmt_invoice_accounting_id">Buchhaltungs-ID</label></th>
                <td><input type="text" name="tmgmt_invoice_accounting_id" id="tmgmt_invoice_accounting_id" value="<?php echo esc_attr($accounting_id); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="tmgmt_invoice_pdf_url">PDF URL</label></th>
                <td>
                    <input type="text" name="tmgmt_invoice_pdf_url" id="tmgmt_invoice_pdf_url" value="<?php echo esc_attr($pdf_url); ?>" class="regular-text">
                    <?php if ($pdf_url): ?>
                        <br><a href="<?php echo esc_url($pdf_url); ?>" target="_blank" class="button button-secondary" style="margin-top:5px;">PDF öffnen</a>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><label for="tmgmt_invoice_total">Gesamtpreis</label></th>
                <td><input type="text" id="tmgmt_invoice_total" value="<?php echo esc_attr(number_format((float)$invoice_total, 2, ',', '.')); ?> €" class="regular-text" readonly></td>
            </tr>
        </table>
        <h3>Rechnungspositionen</h3>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th>Leistung</th>
                    <th>Typ</th>
                    <th>Preis</th>
                    <th>Einheit</th>
                    <th>MwSt</th>
                    <th>Aktion</th>
                </tr>
            </thead>
            <tbody id="tmgmt-invoice-items-list"></tbody>
        </table>
        <h4>Leistung hinzufügen</h4>
        <select id="tmgmt-add-service-select">
            <option value="">Leistung wählen...</option>
            <?php foreach ($services as $service): ?>
                <?php
                    $type = get_post_meta($service->ID, '_tmgmt_service_type', true);
                    $price = get_post_meta($service->ID, '_tmgmt_service_price', true);
                    $unit = get_post_meta($service->ID, '_tmgmt_service_price_unit', true);
                    $vat = get_post_meta($service->ID, '_tmgmt_service_vat_rate', true);
                ?>
                <option value="<?php echo $service->ID; ?>" data-type="<?php echo esc_attr($type); ?>" data-price="<?php echo esc_attr($price); ?>" data-unit="<?php echo esc_attr($unit); ?>" data-vat="<?php echo esc_attr($vat); ?>">
                    <?php echo esc_html($service->post_title); ?> (<?php echo esc_html($type); ?>, <?php echo esc_html($price); ?> <?php echo esc_html($unit); ?>, MwSt: <?php echo esc_html($vat); ?>%)
                </option>
            <?php endforeach; ?>
        </select>
        <button type="button" class="button" id="tmgmt-add-service-btn">Hinzufügen</button>
        <input type="hidden" name="tmgmt_invoice_items_json" id="tmgmt_invoice_items_json" value="<?php echo esc_attr(json_encode($invoice_items)); ?>">
        <script>
        function renderInvoiceItems(items) {
            const tbody = document.getElementById('tmgmt-invoice-items-list');
            tbody.innerHTML = '';
            items.forEach(function(item, idx) {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td><input type='text' class='tmgmt-item-title' data-idx='${idx}' value='${item.title || ''}'></td>
                    <td>${item.type || ''}</td>
                    <td><input type='number' step='0.01' class='tmgmt-item-price' data-idx='${idx}' value='${item.price || ''}'></td>
                    <td>${item.unit || ''}</td>
                    <td>${item.vat_rate || ''}%</td>
                    <td><button type="button" class="button tmgmt-remove-item" data-idx="${idx}">Entfernen</button></td>`;
                tbody.appendChild(tr);
            });
            // Entfernen-Buttons neu binden
            tbody.querySelectorAll('.tmgmt-remove-item').forEach(btn => {
                btn.addEventListener('click', function() {
                    const idx = parseInt(btn.getAttribute('data-idx'));
                    items.splice(idx, 1);
                    document.getElementById('tmgmt_invoice_items_json').value = JSON.stringify(items);
                    renderInvoiceItems(items);
                });
            });
            // Editierbare Felder binden
            tbody.querySelectorAll('.tmgmt-item-title').forEach(input => {
                input.addEventListener('input', function() {
                    const idx = parseInt(input.getAttribute('data-idx'));
                    items[idx].title = input.value;
                    document.getElementById('tmgmt_invoice_items_json').value = JSON.stringify(items);
                });
            });
            tbody.querySelectorAll('.tmgmt-item-price').forEach(input => {
                input.addEventListener('input', function() {
                    const idx = parseInt(input.getAttribute('data-idx'));
                    items[idx].price = input.value;
                    document.getElementById('tmgmt_invoice_items_json').value = JSON.stringify(items);
                });
            });
        }
        document.addEventListener('DOMContentLoaded', function() {
            const addBtn = document.getElementById('tmgmt-add-service-btn');
            const select = document.getElementById('tmgmt-add-service-select');
            const itemsInput = document.getElementById('tmgmt_invoice_items_json');
            let items = itemsInput.value ? JSON.parse(itemsInput.value) : [];
            renderInvoiceItems(items);
            addBtn.addEventListener('click', function() {
                const opt = select.options[select.selectedIndex];
                if (!opt.value) return;
                if (items.some(i => i.service_id == opt.value)) return;
                const item = {
                    service_id: opt.value,
                    title: opt.text.split('(')[0].trim(),
                    type: opt.getAttribute('data-type'),
                    price: opt.getAttribute('data-price'),
                    unit: opt.getAttribute('data-unit'),
                    vat_rate: opt.getAttribute('data-vat')
                };
                items.push(item);
                itemsInput.value = JSON.stringify(items);
                renderInvoiceItems(items);
            });
        });
        </script>
        <?php
    }

    public function save_meta_boxes($post_id) {
        if (!isset($_POST['tmgmt_invoice_meta_nonce']) || !wp_verify_nonce($_POST['tmgmt_invoice_meta_nonce'], 'tmgmt_save_invoice_meta')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $fields = array(
            '_tmgmt_invoice_event_id',
            '_tmgmt_invoice_type',
            '_tmgmt_invoice_number',
            '_tmgmt_invoice_ref_number',
            '_tmgmt_invoice_date',
            '_tmgmt_invoice_service_date',
            '_tmgmt_invoice_recipient',
            '_tmgmt_invoice_due_date',
            '_tmgmt_invoice_intro_text',
            '_tmgmt_invoice_closing_text',
            '_tmgmt_invoice_payment_info',
            '_tmgmt_invoice_accounting_id',
            '_tmgmt_invoice_status',
            '_tmgmt_invoice_pdf_url',
            '_tmgmt_invoice_items'
        );

        foreach ($fields as $field) {
            $input_name = substr($field, 1); // remove leading underscore
            if (isset($_POST[$input_name])) {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$input_name]));
            } else {
                // Handle textarea fields that might be empty but set
                if (in_array($field, ['_tmgmt_invoice_recipient', '_tmgmt_invoice_intro_text', '_tmgmt_invoice_closing_text', '_tmgmt_invoice_payment_info'])) {
                     update_post_meta($post_id, $field, sanitize_textarea_field($_POST[$input_name] ?? ''));
                }
            }
        }

        // Rechnungspositionen speichern und Gesamtpreis berechnen
        if (isset($_POST['tmgmt_invoice_items_json'])) {
            $items_json = wp_unslash($_POST['tmgmt_invoice_items_json']);
            update_post_meta($post_id, '_tmgmt_invoice_items', $items_json);
            $items = json_decode($items_json, true);
            $total = 0;
            if (is_array($items)) {
                foreach ($items as $item) {
                    if (isset($item['price'])) {
                        $total += floatval($item['price']);
                    }
                }
            }
            update_post_meta($post_id, '_tmgmt_invoice_total', $total);
        }
    }
}
