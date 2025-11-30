<?php

class TMGMT_Integration_Manager {
    /**
     * Load a single integration config and replace API key placeholders
     */
    public function load_integration_config($integration_slug) {
        $config_path = $this->integrations_dir . $integration_slug;
        if (!file_exists($config_path)) return false;
        $config = json_decode(file_get_contents($config_path), true);
        // Load API keys from settings
        $api_keys = get_option('tmgmt_api_keys', array());
        // Replace API key placeholders flexibly
        $integration_key = str_replace('.json', '', $integration_slug);
        // Versuche beide Varianten: easyverein-invoice und easyverein
        $api_key = $api_keys[$integration_key] ?? $api_keys['easyverein'] ?? '';
        $patterns = [
            '{{API_KEY}}',
            '{{api_token}}',
            '{{easyverein_api_key}}',
            '{{' . $integration_key . '_api_key}}'
        ];
        array_walk_recursive($config, function (&$value) use ($patterns, $api_key) {
            if (is_string($value)) {
                foreach ($patterns as $pattern) {
                    if (strpos($value, $pattern) !== false) {
                        $value = str_replace($pattern, $api_key, $value);
                    }
                }
            }
        });
        return $config;
    }

    private $integrations_dir;

    public function __construct() {
        $this->integrations_dir = TMGMT_PLUGIN_DIR . 'includes/integrations/';
        add_action('wp_ajax_tmgmt_execute_integration_action', array($this, 'ajax_execute_action'));
        add_action('add_meta_boxes', array($this, 'add_finances_meta_box'));
    }

    /**
     * Register the Finances Meta Box
     */
    public function add_finances_meta_box() {
        add_meta_box(
            'tmgmt_event_finances',
            'Finanzen & Rechnungen',
            array($this, 'render_finances_box'),
            'event',
            'normal',
            'high'
        );
    }

    /**
     * Render the Finances Meta Box
     */
    public function render_finances_box($post) {
        // 1. List existing Invoices
        $invoices = get_posts(array(
            'post_type' => 'tmgmt_invoice',
            'meta_key' => '_tmgmt_invoice_event_id',
            'meta_value' => $post->ID,
            'posts_per_page' => -1
        ));

        echo '<div class="tmgmt-finances-section">';
        echo '<h3>Rechnungen</h3>';

        if ($invoices) {
            echo '<table class="widefat fixed striped">';
            echo '<thead><tr><th>Nr.</th><th>Datum</th><th>Empfänger</th><th>Status</th><th>Aktionen</th></tr></thead>';
            echo '<tbody>';
            foreach ($invoices as $invoice) {
                $number = get_post_meta($invoice->ID, '_tmgmt_invoice_number', true);
                $date = get_post_meta($invoice->ID, '_tmgmt_invoice_date', true);
                $recipient = get_post_meta($invoice->ID, '_tmgmt_invoice_recipient', true);
                $status = get_post_meta($invoice->ID, '_tmgmt_invoice_status', true);
                $pdf_url = get_post_meta($invoice->ID, '_tmgmt_invoice_pdf_url', true);

                $edit_link = get_edit_post_link($invoice->ID);

                echo '<tr>';
                echo '<td><a href="' . esc_url($edit_link) . '">' . esc_html($number ?: '(Entwurf)') . '</a></td>';
                echo '<td>' . esc_html($date) . '</td>';
                echo '<td>' . esc_html(wp_trim_words($recipient, 5)) . '</td>';
                echo '<td>' . esc_html($status) . '</td>';
                echo '<td>';
                if ($pdf_url) {
                    echo '<a href="' . esc_url($pdf_url) . '" target="_blank" class="button button-small">PDF</a> ';
                }
                echo '<a href="' . esc_url($edit_link) . '" class="button button-small">Bearbeiten</a>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>Keine Rechnungen vorhanden.</p>';
        }
        echo '</div>';

        // 2. Integration Actions
        $integrations = $this->get_integrations();
        
        echo '<div class="tmgmt-finances-actions" style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 20px;">';
        echo '<h3>Aktionen</h3>';
        
        if (empty($integrations)) {
            echo '<p>Keine Integrationen konfiguriert.</p>';
        } else {
            foreach ($integrations as $file => $config) {
                if (empty($config['actions'])) continue;
                
                echo '<div class="tmgmt-integration-group" style="margin-bottom: 15px;">';
                echo '<strong>' . esc_html($config['name']) . '</strong><br>';
                
                foreach ($config['actions'] as $action) {
                    echo '<button type="button" class="button button-secondary tmgmt-run-integration-action" '
                        . 'data-integration="' . esc_attr($file) . '" '
                        . 'data-action="' . esc_attr($action['id']) . '" '
                        . 'data-event="' . esc_attr($post->ID) . '" '
                        . 'style="margin-right: 5px; margin-top: 5px;">';
                    echo esc_html($action['name']);
                    echo '</button> ';
                }
                echo '</div>';
            }
        }
        echo '</div>';

        // JS for Button Click
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('.tmgmt-run-integration-action').on('click', function() {
                const btn = $(this);
                const integration = btn.data('integration');
                const actionId = btn.data('action');
                const eventId = btn.data('event');
                
                if (!confirm('Möchten Sie diese Aktion wirklich ausführen?')) return;
                
                btn.prop('disabled', true).text('Läuft...');
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'tmgmt_execute_integration_action',
                        integration: integration,
                        action_id: actionId,
                        event_id: eventId
                    },
                    success: function(res) {
                        if (res.success) {
                            alert('Aktion erfolgreich ausgeführt!');
                            location.reload();
                        } else {
                            alert('Fehler: ' + (res.data || 'Unbekannter Fehler'));
                            btn.prop('disabled', false).text(btn.data('original-text'));
                        }
                    },
                    error: function() {
                        alert('Server-Fehler');
                        btn.prop('disabled', false).text(btn.data('original-text'));
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Load all JSON integrations
     */
    private function get_integrations() {
        $files = glob($this->integrations_dir . '*.json');
        $integrations = array();
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $json = json_decode($content, true);
            if ($json) {
                $integrations[basename($file)] = $json;
            }
        }
        
        return $integrations;
    }

    /**
     * AJAX Handler to execute the action
     */
    public function ajax_execute_action() {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Keine Berechtigung');
        }

        $integration_file = sanitize_file_name($_POST['integration'] ?? '');
        $action_id = sanitize_text_field($_POST['action_id'] ?? '');
        $event_id = intval($_POST['event_id'] ?? 0);

        $file_path = $this->integrations_dir . $integration_file;
        if (!file_exists($file_path)) {
            wp_send_json_error('Integration nicht gefunden');
        }

        $config = $this->load_integration_config($integration_file);
        if (!$config) wp_send_json_error('Ungültige Integration');

        $action_config = null;
        foreach (($config['actions'] ?? array()) as $act) {
            if (($act['id'] ?? '') === $action_id) {
                $action_config = $act;
                break;
            }
        }
        if (!$action_config) {
            wp_send_json_error('Aktion nicht gefunden');
        }

        // Logging helpers
        if (!class_exists('TMGMT_Log_Manager')) {
            require_once TMGMT_PLUGIN_DIR . 'includes/class-log-manager.php';
        }
        $log_manager = new TMGMT_Log_Manager();

        $debug_mode = !empty($config['debug_mode']);
        if ($debug_mode && !class_exists('TMGMT_Integration_Debug_Log')) {
            require_once TMGMT_PLUGIN_DIR . 'includes/class-integration-debug-log.php';
        }
        $debug_logger = $debug_mode ? new TMGMT_Integration_Debug_Log() : null;

        $step_responses = array();
        $steps = isset($action_config['sequence']) ? $action_config['sequence'] : array($action_config);
        foreach ($steps as $i => $step) {
            $body_json = json_encode($step['body'] ?? new stdClass());
            $parsed_body = preg_replace_callback('/\{\{\s*step\.(\d+)\.([\w_]+)\s*\}\}/', function($matches) use ($step_responses) {
                $step_idx = intval($matches[1]);
                $key = $matches[2];
                return isset($step_responses[$step_idx][$key]) ? $step_responses[$step_idx][$key] : '';
            }, $body_json);
            $parsed_body = $this->parse_placeholders_recursive($parsed_body, $event_id);

            $url = rtrim($config['base_url'] ?? '', '/') . '/' . ltrim($step['endpoint'] ?? '', '/');
            $args = array(
                'method' => $step['method'] ?? 'GET',
                'headers' => $step['headers'] ?? array(),
                'body' => $parsed_body,
                'timeout' => 30
            );
            if (isset($config['authentication'])) {
                if ($config['authentication']['type'] === 'bearer') {
                    $args['headers']['Authorization'] = 'Bearer ' . $config['authentication']['token'];
                }
            }

            $response = wp_remote_request($url, $args);
            if (is_wp_error($response)) {
                $error_msg = $response->get_error_message();
                $log_manager->log($event_id, 'integration', 'API Fehler: ' . $error_msg);
                if ($debug_mode && $debug_logger) {
                    $debug_logger->log($config['name'] ?? 'integration', $args, $error_msg);
                }
                wp_send_json_error($error_msg);
            }
            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            if ($code >= 400) {
                $log_manager->log($event_id, 'integration', 'API Fehler (' . $code . '): ' . $body);
                if ($debug_mode && $debug_logger) {
                    $debug_logger->log($config['name'] ?? 'integration', $args, $body);
                }
                wp_send_json_error('API Fehler (' . $code . '): ' . $body);
            }

            $step_responses[$i] = $data;
            if ($debug_mode && $debug_logger) {
                $debug_logger->log($config['name'] ?? 'integration', $args, $body);
            }
            if (!empty($step['response_mapping']) && $data) {
                $this->handle_response_mapping($step['response_mapping'], $data, $event_id);
            }
        }

        wp_send_json_success('Integration erfolgreich ausgeführt');
    }

    private function parse_placeholders_recursive($json_string, $event_id) {
        $this->current_event_id = $event_id;
        $result = preg_replace_callback('/\{\{\s*([\w\.]+)\s*\}\}/', [$this, 'replace_placeholder_callback'], $json_string);
        unset($this->current_event_id);
        return $result;
    }

    private function get_value_for_key($key, $event_id) {
        // Map keys like 'contact.lastname' to actual data
        $parts = explode('.', $key);
        $object = $parts[0];
        $field = $parts[1] ?? '';

        if ($object === 'contact') {
            // Mapping Event-Meta zu Kontakt-Meta
            $mapping = array(
                'lastname' => '_tmgmt_contact_lastname',
                'firstname' => '_tmgmt_contact_firstname',
                'company' => '_tmgmt_contact_company',
                'street' => '_tmgmt_contact_street',
                'number' => '_tmgmt_contact_number',
                'zip' => '_tmgmt_contact_zip',
                'city' => '_tmgmt_contact_city',
                'email' => '_tmgmt_contact_email_contract'
            );
            if (isset($mapping[$field])) {
                // 1. Kontakt-ID aus Event holen
                $contact_cpt_id = get_post_meta($event_id, '_tmgmt_contact_cpt_id', true);
                if ($contact_cpt_id) {
                    $val = get_post_meta($contact_cpt_id, $mapping[$field], true);
                    if (!empty($val)) return $val;
                }
                // 2. Fallback: Wert aus Event-Meta
                return get_post_meta($event_id, $mapping[$field], true);
            }
        }
        
        if ($object === 'event') {
            if ($field === 'fee') return get_post_meta($event_id, '_tmgmt_event_fee', true);
            if ($field === 'id') return $event_id;
        }

        return ''; // Default empty
    }

    private function handle_response_mapping($mapping, $data, $event_id) {
        // Example: "accounting_id": "id" -> Save $data['id'] to accounting_id
        // We need to know WHERE to save it.
        // The concept says "accounting_id" on Contact or Invoice.
        
        // This part is tricky because we don't know if we created a Contact or an Invoice just by the mapping key.
        // We might need to define the target in the JSON.
        // For now, let's implement a simple logic:
        
        if (isset($mapping['accounting_id'])) {
            $val = $this->get_value_from_data($data, $mapping['accounting_id']);
            // Save to Event's main contact? Or the Event itself?
            // Let's save to the Event meta for now as a reference
            update_post_meta($event_id, '_tmgmt_accounting_customer_id', $val);
        }
        
        if (isset($mapping['invoice_id'])) {
            // Create a new Invoice Post
            $val = $this->get_value_from_data($data, $mapping['invoice_id']);
            $pdf = isset($mapping['pdf_url']) ? $this->get_value_from_data($data, $mapping['pdf_url']) : '';
            $number = isset($mapping['invoice_number']) ? $this->get_value_from_data($data, $mapping['invoice_number']) : '';
            
            $invoice_data = array(
                'post_title' => 'Rechnung ' . $number,
                'post_type' => 'tmgmt_invoice',
                'post_status' => 'publish'
            );
            $invoice_post_id = wp_insert_post($invoice_data);
            
            update_post_meta($invoice_post_id, '_tmgmt_invoice_event_id', $event_id);
            // Speichere die invoice_id als Buchhaltungs-ID
            update_post_meta($invoice_post_id, '_tmgmt_invoice_accounting_id', $val);
            update_post_meta($invoice_post_id, '_tmgmt_invoice_number', $number);
            update_post_meta($invoice_post_id, '_tmgmt_invoice_pdf_url', $pdf);
            update_post_meta($invoice_post_id, '_tmgmt_invoice_date', date('Y-m-d'));
        }
    }

    private function get_value_from_data($data, $path) {
        // Simple dot notation getter for array
        $parts = explode('.', $path);
        $current = $data;
        foreach ($parts as $part) {
            if (isset($current[$part])) {
                $current = $current[$part];
            } else {
                return null;
            }
        }
        return $current;
    }

    // Hilfsfunktion: Hole zugehörige Invoice-ID für ein Event
    private function get_invoice_id_for_event($event_id) {
        $invoices = get_posts(array(
            'post_type' => 'tmgmt_invoice',
            'meta_key' => '_tmgmt_invoice_event_id',
            'meta_value' => $event_id,
            'posts_per_page' => 1,
            'fields' => 'ids'
        ));
        return !empty($invoices) ? $invoices[0] : false;
    }

    // --- Hilfsfunktion für Platzhalterersetzung ---
    private function replace_placeholder_callback($matches) {
        $key = $matches[1];
        $event_id = $this->current_event_id ?? null;
        if (!$event_id) return '';
        // Unterstütze sowohl contact.firstname als auch contact_firstname
        if (strpos($key, '.') !== false) {
            return $this->get_value_for_key($key, $event_id);
        }
        // Einzelne Platzhalter wie contact_firstname, title, description, payment_method
        if (strpos($key, 'contact_') === 0) {
            $meta_key = '_tmgmt_' . $key;
            $contact_cpt_id = get_post_meta($event_id, '_tmgmt_contact_cpt_id', true);
            if ($contact_cpt_id) {
                $val = get_post_meta($contact_cpt_id, $meta_key, true);
                if (!empty($val)) return $val;
            }
            return get_post_meta($event_id, $meta_key, true);
        }
        if ($key === 'title') {
            $post = get_post($event_id);
            return $post ? $post->post_title : '';
        }
        if ($key === 'description') {
            $post = get_post($event_id);
            return $post ? $post->post_content : '';
        }
        if ($key === 'payment_method') {
            return get_post_meta($event_id, '_tmgmt_payment_method', true);
        }
        // Datums-Platzhalter immer als YYYY-MM-DD
        $date_keys = array('event_date','invoice_date','service_date','due_date','inquiry_date');
        if (in_array($key, $date_keys)) {
            // Für invoice_date: aus Invoice CPT, falls vorhanden
            if ($key === 'invoice_date') {
                $invoice_id = $this->get_invoice_id_for_event($event_id);
                if ($invoice_id) {
                    $val = get_post_meta($invoice_id, '_tmgmt_invoice_date', true);
                    if (!empty($val)) return date('Y-m-d', strtotime($val));
                }
            }
            $val = get_post_meta($event_id, '_tmgmt_' . $key, true);
            if (!empty($val)) return date('Y-m-d', strtotime($val));
        }
        // Gesamtpreis aus Invoice CPT, falls vorhanden
        if ($key === 'invoice_total') {
            $invoice_id = $this->get_invoice_id_for_event($event_id);
            if ($invoice_id) {
                $val = get_post_meta($invoice_id, '_tmgmt_invoice_total', true);
                if (!empty($val)) return $val;
            }
            // Fallback: Event-Meta
            $val = get_post_meta($event_id, '_tmgmt_invoice_total', true);
            if (!empty($val)) return $val;
        }
        // Fallback: Event-Meta
        return get_post_meta($event_id, '_tmgmt_' . $key, true);
    }
}
