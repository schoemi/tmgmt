<?php
/**
 * Unit Tests: TMGMT_Action_Handler – contract_generation dispatch
 *
 * Tests:
 * - test_handler_dispatches_contract_generation()         — Req. 3.1, 4.1
 * - test_handler_returns_json_error_on_wp_error()         — Req. 4.8
 * - test_handler_does_not_change_status_on_error()        — Req. 4.8
 * - test_handler_skips_generic_status_change()            — Req. 4.8 (status managed inside generate_and_send)
 */

if (!defined('TMGMT_PLUGIN_DIR')) {
    define('TMGMT_PLUGIN_DIR', dirname(__DIR__, 2) . '/');
}

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

if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer($action = -1, $query_arg = false, $die = true): bool { return true; }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field(string $str): string { return trim($str); }
}

if (!function_exists('wp_enqueue_editor')) {
    function wp_enqueue_editor(): void {}
}

if (!function_exists('intval')) {
    // already a PHP built-in, but guard just in case
}

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

// Load the real contract generator so the spy can extend it
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

/**
 * Spy/stub for TMGMT_Contract_Generator.
 * Records calls and returns a configurable result.
 */
class TMGMT_Contract_Generator_Spy extends TMGMT_Contract_Generator {
    public static bool $called        = false;
    public static int  $lastEventId   = 0;
    public static int  $lastActionId  = 0;
    /** @var true|WP_Error */
    public static mixed $returnValue  = true;

    public function generate_and_send(int $event_id, int $action_id, array $overrides = []): bool|WP_Error {
        self::$called       = true;
        self::$lastEventId  = $event_id;
        self::$lastActionId = $action_id;
        return self::$returnValue;
    }
}

require_once dirname(__DIR__, 2) . '/includes/class-action-handler.php';

/**
 * Subclass of TMGMT_Action_Handler that injects the spy generator.
 */
class TMGMT_Action_Handler_Testable extends TMGMT_Action_Handler {
    protected function make_contract_generator(): TMGMT_Contract_Generator_Spy {
        return new TMGMT_Contract_Generator_Spy();
    }
}

class ContractActionHandlerTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        global $test_post_meta_store, $test_post_store, $test_json_response;
        $test_post_meta_store = [];
        $test_post_store      = [];
        $test_json_response   = null;

        TMGMT_Contract_Generator_Spy::$called       = false;
        TMGMT_Contract_Generator_Spy::$lastEventId  = 0;
        TMGMT_Contract_Generator_Spy::$lastActionId = 0;
        TMGMT_Contract_Generator_Spy::$returnValue  = true;

        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_POST = [];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function seedActionPost(int $actionId, string $type = 'contract_generation', string $targetStatus = ''): void
    {
        global $test_post_store;
        $post               = new stdClass();
        $post->ID           = $actionId;
        $post->post_title   = 'Test Action';
        $post->post_type    = 'tmgmt_action';
        $post->post_status  = 'publish';
        $test_post_store[$actionId] = $post;

        update_post_meta($actionId, '_tmgmt_action_type', $type);
        update_post_meta($actionId, '_tmgmt_action_target_status', $targetStatus);
    }

    private function seedEventMeta(int $eventId, string $status = 'initial'): void
    {
        update_post_meta($eventId, '_tmgmt_status', $status);
    }

    private function postRequest(int $eventId, int $actionId): void
    {
        $_POST['nonce']     = 'test_nonce';
        $_POST['event_id']  = (string) $eventId;
        $_POST['action_id'] = (string) $actionId;
        $_POST['note']      = '';
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    /**
     * When action_type is 'contract_generation', handle_execute_action() must
     * call TMGMT_Contract_Generator::generate_and_send() with the correct IDs
     * and return a JSON success response.
     *
     * Requirements: 3.1, 4.1
     */
    public function test_handler_dispatches_contract_generation(): void
    {
        global $test_json_response;

        $eventId  = 10;
        $actionId = 20;

        $this->seedActionPost($actionId, 'contract_generation');
        $this->seedEventMeta($eventId);
        $this->postRequest($eventId, $actionId);

        TMGMT_Contract_Generator_Spy::$returnValue = true;

        $handler = new TMGMT_Action_Handler_Testable();
        $handler->handle_execute_action();

        $this->assertTrue(
            TMGMT_Contract_Generator_Spy::$called,
            'generate_and_send() must be called for contract_generation action type'
        );
        $this->assertSame($eventId,  TMGMT_Contract_Generator_Spy::$lastEventId);
        $this->assertSame($actionId, TMGMT_Contract_Generator_Spy::$lastActionId);

        $this->assertNotNull($test_json_response);
        $this->assertTrue(
            $test_json_response['success'],
            'Handler must return JSON success when generate_and_send() returns true'
        );
    }

    /**
     * When generate_and_send() returns a WP_Error, handle_execute_action() must
     * return a JSON error response with the error message.
     *
     * Requirements: 4.8
     */
    public function test_handler_returns_json_error_on_wp_error(): void
    {
        global $test_json_response;

        $eventId  = 11;
        $actionId = 21;

        $this->seedActionPost($actionId, 'contract_generation');
        $this->seedEventMeta($eventId);
        $this->postRequest($eventId, $actionId);

        TMGMT_Contract_Generator_Spy::$returnValue = new WP_Error('template_missing', 'Vorlage nicht gefunden.');

        $handler = new TMGMT_Action_Handler_Testable();
        $handler->handle_execute_action();

        $this->assertNotNull($test_json_response);
        $this->assertFalse(
            $test_json_response['success'],
            'Handler must return JSON error when generate_and_send() returns WP_Error'
        );
        $this->assertStringContainsString(
            'Vorlage nicht gefunden.',
            $test_json_response['data']['message']
        );
    }

    /**
     * When generate_and_send() returns a WP_Error, the handler must NOT apply
     * the generic status-change block (status is managed inside generate_and_send).
     *
     * Requirements: 4.8
     */
    public function test_handler_does_not_change_status_on_error(): void
    {
        $eventId  = 12;
        $actionId = 22;

        $this->seedActionPost($actionId, 'contract_generation', 'contract_sent');
        $this->seedEventMeta($eventId, 'initial_status');
        $this->postRequest($eventId, $actionId);

        TMGMT_Contract_Generator_Spy::$returnValue = new WP_Error('mpdf_error', 'PDF-Fehler.');

        $handler = new TMGMT_Action_Handler_Testable();
        $handler->handle_execute_action();

        $status = get_post_meta($eventId, '_tmgmt_status', true);
        $this->assertSame(
            'initial_status',
            $status,
            'Status must not be changed by the handler when generate_and_send() returns WP_Error'
        );
    }

    /**
     * Even when generate_and_send() succeeds, the handler must NOT apply the
     * generic status-change block for contract_generation — status is managed
     * exclusively inside generate_and_send().
     *
     * Requirements: 4.8
     */
    public function test_handler_skips_generic_status_change_on_success(): void
    {
        $eventId  = 13;
        $actionId = 23;

        // target_status is set on the action, but the handler must NOT apply it
        $this->seedActionPost($actionId, 'contract_generation', 'contract_sent');
        $this->seedEventMeta($eventId, 'initial_status');
        $this->postRequest($eventId, $actionId);

        // generate_and_send() succeeds but does NOT update the meta in this spy
        TMGMT_Contract_Generator_Spy::$returnValue = true;

        $handler = new TMGMT_Action_Handler_Testable();
        $handler->handle_execute_action();

        // The handler must not have changed the status itself
        $status = get_post_meta($eventId, '_tmgmt_status', true);
        $this->assertSame(
            'initial_status',
            $status,
            'Handler must not apply generic status change for contract_generation — that is generate_and_send()\'s responsibility'
        );
    }
}
