<?php
/**
 * Unit Tests: Contract Send Dialog HTML markup in render_actions_box()
 *
 * Verifies that the hidden #tmgmt-contract-send-dialog div contains all
 * required field IDs, layout elements, and German labels.
 *
 * Requirements: 1.2, 1.4, 2.2, 3.1, 4.1
 */

if (!defined('TMGMT_PLUGIN_DIR')) {
    define('TMGMT_PLUGIN_DIR', dirname(__DIR__, 2) . '/');
}

// ── WordPress stubs ──────────────────────────────────────────────────────────

if (!class_exists('WP_Error')) {
    class WP_Error {
        private string $code;
        private string $message;
        public function __construct(string $code = '', string $message = '') {
            $this->code    = $code;
            $this->message = $message;
        }
        public function get_error_code(): string    { return $this->code; }
        public function get_error_message(): string { return $this->message; }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing): bool { return $thing instanceof WP_Error; }
}

if (!function_exists('wp_enqueue_editor')) {
    function wp_enqueue_editor(): void {}
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce(string $action = ''): string { return 'test_nonce'; }
}

if (!function_exists('esc_attr')) {
    function esc_attr(string $text): string { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('esc_html')) {
    function esc_html(string $text): string { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('maybe_unserialize')) {
    function maybe_unserialize($data) { return is_string($data) ? @unserialize($data) ?: $data : $data; }
}

// ── Plugin class stubs ───────────────────────────────────────────────────────

if (!class_exists('TMGMT_Communication_Manager')) {
    class TMGMT_Communication_Manager {
        public function add_entry(int $event_id, string $type, string $recipient, string $subject, string $body, int $user_id = 0): int { return 0; }
        public function render_backend_table(int $post_id): void {}
    }
}

if (!class_exists('TMGMT_Confirmation_Manager')) {
    class TMGMT_Confirmation_Manager {
        public function render_backend_table(int $post_id): void {}
    }
}

if (!class_exists('TMGMT_Log_Manager')) {
    class TMGMT_Log_Manager {
        public function log($post_id, $type, $message, $extra = null, $comm_id = null): void {}
    }
}

if (!class_exists('TMGMT_Event_Status')) {
    class TMGMT_Event_Status {
        public static function get_label(string $status): string { return $status; }
        public static function get_all_statuses(): array { return []; }
    }
}

if (!class_exists('TMGMT_Placeholder_Parser')) {
    class TMGMT_Placeholder_Parser {
        public static function parse(string $text, int $event_id): string { return $text; }
        public static function get_placeholders(): array { return []; }
    }
}

if (!class_exists('TMGMT_PDF_Generator')) {
    class TMGMT_PDF_Generator {
        public function generate_contract_pdf(string $html, string $output_path): bool|WP_Error { return true; }
    }
}

if (!class_exists('TMGMT_Customer_Access_Manager')) {
    class TMGMT_Customer_Access_Manager {
        public function get_valid_token(int $event_id): ?object { return null; }
    }
}

require_once dirname(__DIR__, 2) . '/includes/class-contract-generator.php';
require_once dirname(__DIR__, 2) . '/includes/class-action-handler.php';

class ContractDialogMarkupTest extends \PHPUnit\Framework\TestCase
{
    private string $html;

    protected function setUp(): void
    {
        global $test_post_meta_store, $test_post_store;
        $test_post_meta_store = [];
        $test_post_store      = [];

        // Seed a status definition so render_actions_box() produces output
        $eventId = 100;
        $statusDefId = 200;
        $actionId = 300;

        // Event meta
        update_post_meta($eventId, '_tmgmt_status', 'test-status');
        update_post_meta($eventId, '_tmgmt_event_attachments', []);

        // Status definition post
        $statusDef = new stdClass();
        $statusDef->ID = $statusDefId;
        $statusDef->post_title = 'Test Status';
        $statusDef->post_type = 'tmgmt_status_def';
        $statusDef->post_status = 'publish';
        $statusDef->post_name = 'test-status';
        $test_post_store[$statusDefId] = $statusDef;

        // Action post
        $actionPost = new stdClass();
        $actionPost->ID = $actionId;
        $actionPost->post_title = 'Vertrag senden';
        $actionPost->post_type = 'tmgmt_action';
        $actionPost->post_status = 'publish';
        $test_post_store[$actionId] = $actionPost;

        update_post_meta($actionId, '_tmgmt_action_type', 'contract_generation');
        update_post_meta($statusDefId, '_tmgmt_available_actions', [$actionId]);

        // Event post object
        $post = new stdClass();
        $post->ID = $eventId;
        $post->post_title = 'Test Event';
        $post->post_type = 'event';
        $post->post_status = 'publish';
        $test_post_store[$eventId] = $post;

        // Capture output
        $handler = new TMGMT_Action_Handler();
        ob_start();
        $handler->render_actions_box($post);
        $this->html = ob_get_clean();
    }

    // ── Structure tests ──────────────────────────────────────────────────────

    public function test_dialog_div_exists_and_is_hidden(): void
    {
        $this->assertStringContainsString('id="tmgmt-contract-send-dialog"', $this->html);
        $this->assertStringContainsString('style="display:none;"', $this->html);
    }

    public function test_dialog_has_data_event_id(): void
    {
        $this->assertStringContainsString('data-event-id="100"', $this->html);
    }

    public function test_two_column_layout(): void
    {
        $this->assertStringContainsString('tmgmt-contract-dialog-left', $this->html);
        $this->assertStringContainsString('tmgmt-contract-dialog-right', $this->html);
    }

    // ── Left column field IDs ────────────────────────────────────────────────

    public function test_to_field(): void
    {
        $this->assertStringContainsString('id="tmgmt-contract-to"', $this->html);
        $this->assertMatchesRegularExpression('/<input[^>]+id="tmgmt-contract-to"/', $this->html);
    }

    public function test_cc_field(): void
    {
        $this->assertStringContainsString('id="tmgmt-contract-cc"', $this->html);
        $this->assertMatchesRegularExpression('/<input[^>]+id="tmgmt-contract-cc"/', $this->html);
    }

    public function test_bcc_field(): void
    {
        $this->assertStringContainsString('id="tmgmt-contract-bcc"', $this->html);
        $this->assertMatchesRegularExpression('/<input[^>]+id="tmgmt-contract-bcc"/', $this->html);
    }

    public function test_subject_field(): void
    {
        $this->assertStringContainsString('id="tmgmt-contract-subject"', $this->html);
        $this->assertMatchesRegularExpression('/<input[^>]+id="tmgmt-contract-subject"/', $this->html);
    }

    public function test_body_textarea(): void
    {
        $this->assertStringContainsString('id="tmgmt-contract-body"', $this->html);
        $this->assertMatchesRegularExpression('/<textarea[^>]+id="tmgmt-contract-body"/', $this->html);
    }

    public function test_attachments_div(): void
    {
        $this->assertStringContainsString('id="tmgmt-contract-attachments"', $this->html);
    }

    // ── Right column ─────────────────────────────────────────────────────────

    public function test_pdf_preview_iframe(): void
    {
        $this->assertStringContainsString('id="tmgmt-contract-pdf-preview"', $this->html);
        $this->assertMatchesRegularExpression('/<iframe[^>]+id="tmgmt-contract-pdf-preview"/', $this->html);
    }

    public function test_loading_spinner(): void
    {
        $this->assertStringContainsString('id="tmgmt-contract-loading"', $this->html);
        $this->assertStringContainsString('spinner is-active', $this->html);
    }

    // ── Template selector ────────────────────────────────────────────────────

    public function test_template_selector_exists_and_hidden(): void
    {
        $this->assertStringContainsString('id="tmgmt-contract-template-selector"', $this->html);
        $this->assertMatchesRegularExpression('/<select[^>]+id="tmgmt-contract-template-selector"/', $this->html);
        // The wrapper row is hidden by default
        $this->assertStringContainsString('id="tmgmt-contract-template-row"', $this->html);
        $this->assertMatchesRegularExpression('/id="tmgmt-contract-template-row"[^>]*style="display:none/', $this->html);
    }

    // ── Buttons ──────────────────────────────────────────────────────────────

    public function test_send_button(): void
    {
        $this->assertStringContainsString('id="tmgmt-contract-send-btn"', $this->html);
        $this->assertStringContainsString('Vertrag senden', $this->html);
    }

    public function test_cancel_button(): void
    {
        $this->assertStringContainsString('id="tmgmt-contract-cancel-btn"', $this->html);
        $this->assertStringContainsString('Abbrechen', $this->html);
    }

    // ── German labels ────────────────────────────────────────────────────────

    public function test_german_labels(): void
    {
        $this->assertStringContainsString('Empfänger:', $this->html);
        $this->assertStringContainsString('CC:', $this->html);
        $this->assertStringContainsString('BCC:', $this->html);
        $this->assertStringContainsString('Betreff:', $this->html);
        $this->assertStringContainsString('Nachricht:', $this->html);
        $this->assertStringContainsString('Anhänge:', $this->html);
        $this->assertStringContainsString('Vorlage:', $this->html);
    }
}
