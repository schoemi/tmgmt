<?php
// Feature: contract-generation, Property 7: Upload-Round-Trip speichert Attachment und Meta

use Eris\Generator;
use Eris\TestTrait;

// Define plugin dir constant if not already set
if (!defined('TMGMT_PLUGIN_DIR')) {
    define('TMGMT_PLUGIN_DIR', dirname(__DIR__, 2) . '/');
}

// Stub WP_Error if not available
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
    function is_wp_error($thing): bool {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('date_i18n')) {
    function date_i18n(string $format, int $timestamp = 0): string {
        return date($format, $timestamp ?: time());
    }
}

if (!function_exists('wp_mail')) {
    function wp_mail($to, $subject, $message, $headers = '', $attachments = array()): bool {
        return true;
    }
}

if (!function_exists('home_url')) {
    function home_url(string $path = ''): string {
        return 'http://example.com' . $path;
    }
}

if (!function_exists('esc_js')) {
    function esc_js(string $text): string {
        return addslashes($text);
    }
}

if (!function_exists('wpautop')) {
    function wpautop(string $text): string {
        return '<p>' . $text . '</p>';
    }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post(string $text): string {
        return $text;
    }
}

if (!function_exists('wp_die')) {
    function wp_die(string $message = '', string $title = '', array $args = []): void {
        throw new \RuntimeException($message);
    }
}

if (!function_exists('add_shortcode')) {
    function add_shortcode(string $tag, callable $callback): void {}
}

if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer($action = -1, $query_arg = false, $die = true): bool {
        return true;
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email(string $email): string {
        return filter_var($email, FILTER_SANITIZE_EMAIL) ?: '';
    }
}

// Stub media_handle_upload — returns a fixed fake attachment ID
if (!function_exists('media_handle_upload')) {
    function media_handle_upload(string $file_id, int $post_id, array $post_data = [], array $overrides = []): int {
        // Return a deterministic fake attachment ID
        return 999;
    }
}

// Stub get_userdata — not needed for the happy path but called in notification code
if (!function_exists('get_userdata')) {
    function get_userdata(int $user_id) {
        return false;
    }
}

// Stub mime_content_type — always return application/pdf so MIME validation passes
if (!function_exists('mime_content_type')) {
    function mime_content_type(string $filename): string {
        return 'application/pdf';
    }
}

// Stub TMGMT_Log_Manager if not already defined
if (!class_exists('TMGMT_Log_Manager')) {
    class TMGMT_Log_Manager {
        public static function log($post_id, $type, $message): void {}
    }
}

// Stub TMGMT_Communication_Manager if not already defined
if (!class_exists('TMGMT_Communication_Manager')) {
    class TMGMT_Communication_Manager {
        public function add_entry(int $event_id, string $type, string $message): void {}
    }
}

// Stub TMGMT_Placeholder_Parser if not already defined
if (!class_exists('TMGMT_Placeholder_Parser')) {
    class TMGMT_Placeholder_Parser {
        public function __construct(int $event_id) {}
        public function parse(string $text): string { return $text; }
    }
}

// TMGMT_Event_Status must be available for the render_dashboard() status check
if (!class_exists('TMGMT_Event_Status')) {
    class TMGMT_Event_Status {
        const CONTRACT_SENT = 'contract_sent';
        public static function get_all_statuses(): array { return []; }
        public static function get_label(string $status): string { return $status; }
    }
}

// WordPress constant used by $wpdb->get_row() and get_results()
if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

// Require the class under test
require_once dirname(__DIR__, 2) . '/includes/class-customer-access-manager.php';

/**
 * Property-Based Test: Upload-Round-Trip speichert Attachment und Meta
 *
 * For any valid file (with PDF header %PDF) uploaded via handle_signed_contract_upload(),
 * the post-meta _tmgmt_signed_contract_attachment_id on the event must be set to the
 * returned attachment ID, and that ID must be non-zero.
 *
 * **Validates: Requirements 6.2, 6.3**
 */
class ContractUploadRoundTripTest extends \PHPUnit\Framework\TestCase
{
    use TestTrait;

    private TMGMT_Customer_Access_Manager $sut;

    /** @var list<string> Temp files created during the test run */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        global $test_post_meta_store, $test_post_store, $test_options_store, $test_json_response;
        $test_post_meta_store = [];
        $test_post_store      = [];
        $test_options_store   = [];
        $test_json_response   = null;

        $this->sut = new TMGMT_Customer_Access_Manager();
    }

    protected function tearDown(): void
    {
        // Clean up any temp files
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        $this->tempFiles = [];

        // Reset superglobals
        $_POST  = [];
        $_FILES = [];
    }

    /**
     * Create a temporary file with the given content and return its path.
     */
    private function createTempFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tmgmt_test_');
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;
        return $path;
    }

    /**
     * Seed a fake event post in the test post store.
     */
    private function seedEvent(int $eventId): void
    {
        global $test_post_store;
        $fakeEvent               = new stdClass();
        $fakeEvent->ID           = $eventId;
        $fakeEvent->post_title   = 'Test Event ' . $eventId;
        $fakeEvent->post_content = '';
        $fakeEvent->post_type    = 'event';
        $test_post_store[$eventId] = $fakeEvent;
    }

    /**
     * Configure the $wpdb stub so that get_row() returns a valid token record
     * pointing to the given event ID.
     */
    private function stubWpdbTokenLookup(int $eventId): void
    {
        $record           = new stdClass();
        $record->id       = 1;
        $record->event_id = $eventId;
        $record->token    = 'valid-test-token';
        $record->status   = 'active';

        $GLOBALS['wpdb'] = new class($record) {
            private object $record;
            public string $prefix = 'wp_';

            public function __construct(object $record) {
                $this->record = $record;
            }

            public function get_row($query, $output = OBJECT, $y = 0): ?object {
                return $this->record;
            }

            public function prepare($query, ...$args): string { return $query; }
            public function insert($table, $data, $format = null) { return false; }
            public function update($table, $data, $where, $format = null, $where_format = null) { return false; }
            public function get_results($query, $output = OBJECT): array { return []; }
            public function get_charset_collate(): string { return ''; }
            public function get_var($query) { return null; }
        };
    }

    /**
     * Property 7: Upload-Round-Trip speichert Attachment und Meta
     *
     * For any valid PDF file content (starting with %PDF), after a successful call to
     * handle_signed_contract_upload(), _tmgmt_signed_contract_attachment_id is set on
     * the event and equals the non-zero attachment ID returned by media_handle_upload().
     */
    public function testUploadRoundTripStoresAttachmentAndMeta(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                // Random suffix bytes appended after the %PDF header
                Generator\string()
            )
            ->then(function (string $extraContent): void {
                global $test_post_meta_store, $test_post_store, $test_json_response;
                $test_post_meta_store = [];
                $test_post_store      = [];
                $test_json_response   = null;

                $eventId = 8001;

                // Seed event post
                $this->seedEvent($eventId);

                // Stub $wpdb to return a valid token record for this event
                $this->stubWpdbTokenLookup($eventId);

                // Create a temp file with a valid PDF header
                $fileContent = '%PDF-1.4' . $extraContent;
                $tmpPath     = $this->createTempFile($fileContent);

                // Set up $_POST and $_FILES as handle_signed_contract_upload() expects
                $_POST['tmgmt_token'] = 'valid-test-token';
                $_POST['nonce']       = 'test_nonce';

                $_FILES['signed_contract'] = [
                    'name'     => 'signed-contract.pdf',
                    'type'     => 'application/pdf',
                    'tmp_name' => $tmpPath,
                    'error'    => UPLOAD_ERR_OK,
                    'size'     => strlen($fileContent),
                ];

                // Call the handler — it calls wp_send_json_success/error which sets $test_json_response
                $this->sut->handle_signed_contract_upload();

                // The response must be a success
                $this->assertNotNull(
                    $test_json_response,
                    'handle_signed_contract_upload() did not produce a JSON response'
                );
                $this->assertTrue(
                    $test_json_response['success'],
                    'handle_signed_contract_upload() returned error: ' .
                    ($test_json_response['data']['message'] ?? '(no message)')
                );

                // _tmgmt_signed_contract_attachment_id must be set and non-zero
                $attachmentId = get_post_meta($eventId, '_tmgmt_signed_contract_attachment_id', true);

                $this->assertNotEmpty(
                    $attachmentId,
                    '_tmgmt_signed_contract_attachment_id post-meta is empty after upload'
                );

                $this->assertGreaterThan(
                    0,
                    (int) $attachmentId,
                    '_tmgmt_signed_contract_attachment_id must be a non-zero integer'
                );

                // The stored ID must match what media_handle_upload() returned (999)
                $this->assertSame(
                    999,
                    (int) $attachmentId,
                    '_tmgmt_signed_contract_attachment_id does not match the attachment ID from media_handle_upload()'
                );
            });
    }
}
