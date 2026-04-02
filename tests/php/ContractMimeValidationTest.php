<?php
// Feature: contract-generation, Property 8: Ungültige MIME-Typen werden abgelehnt

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

// Stub media_handle_upload — should NOT be called for invalid MIME types
if (!function_exists('media_handle_upload')) {
    function media_handle_upload(string $file_id, int $post_id, array $post_data = [], array $overrides = []): int {
        return 999;
    }
}

if (!function_exists('get_userdata')) {
    function get_userdata(int $user_id) {
        return false;
    }
}

if (!class_exists('TMGMT_Log_Manager')) {
    class TMGMT_Log_Manager {
        public static function log($post_id, $type, $message): void {}
    }
}

if (!class_exists('TMGMT_Communication_Manager')) {
    class TMGMT_Communication_Manager {
        public function add_entry(int $event_id, string $type, string $message): void {}
    }
}

if (!class_exists('TMGMT_Placeholder_Parser')) {
    class TMGMT_Placeholder_Parser {
        public function __construct(int $event_id) {}
        public function parse(string $text): string { return $text; }
    }
}

if (!class_exists('TMGMT_Event_Status')) {
    class TMGMT_Event_Status {
        const CONTRACT_SENT = 'contract_sent';
        public static function get_all_statuses(): array { return []; }
        public static function get_label(string $status): string { return $status; }
    }
}

if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

require_once dirname(__DIR__, 2) . '/includes/class-customer-access-manager.php';

/**
 * Exception thrown by the wp_send_json_error stub to halt execution,
 * mirroring WordPress's real behaviour (which calls wp_die/exit).
 */
class JsonErrorHaltException extends \RuntimeException {}

/**
 * Testable subclass that allows overriding the detected MIME type per-test.
 */
class TestableMimeCustomerAccessManager extends TMGMT_Customer_Access_Manager {
    public string $stubbedMimeType = 'application/octet-stream';

    protected function detect_mime_type(string $tmp_path, string $fallback): string {
        return $this->stubbedMimeType;
    }
}

/**
 * Property-Based Test: Ungültige MIME-Typen werden abgelehnt
 *
 * For any file whose MIME type is NOT in {application/pdf, image/jpeg, image/png},
 * handle_signed_contract_upload() must:
 *   - return a JSON error response (not success)
 *   - NOT set _tmgmt_signed_contract_attachment_id on the event
 *   - NOT change _tmgmt_status to 'contract_signed'
 *
 * **Validates: Requirements 6.7**
 */
class ContractMimeValidationTest extends \PHPUnit\Framework\TestCase
{
    use TestTrait;

    private TestableMimeCustomerAccessManager $sut;

    /** @var list<string> Temp files created during the test run */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        global $test_post_meta_store, $test_post_store, $test_options_store, $test_json_response;
        $test_post_meta_store = [];
        $test_post_store      = [];
        $test_options_store   = [];
        $test_json_response   = null;

        $this->sut = new TestableMimeCustomerAccessManager();
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        $this->tempFiles = [];

        $_POST  = [];
        $_FILES = [];
    }

    private function createTempFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tmgmt_mime_test_');
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;
        return $path;
    }

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
     * Property 8: Ungültige MIME-Typen werden abgelehnt
     *
     * For any MIME type outside the whitelist {application/pdf, image/jpeg, image/png},
     * handle_signed_contract_upload() must reject the upload with an error response,
     * must not create an attachment, and must not change the event status.
     */
    public function testInvalidMimeTypesAreRejected(): void
    {
        $invalidMimeTypes = [
            'text/plain',
            'text/html',
            'application/zip',
            'application/octet-stream',
            'video/mp4',
            'audio/mpeg',
            'application/javascript',
            'image/gif',
            'image/webp',
            'application/x-php',
        ];

        $this
            ->limitTo(100)
            ->forAll(
                Generator\elements(...$invalidMimeTypes)
            )
            ->then(function (string $invalidMime): void {
                global $test_post_meta_store, $test_post_store, $test_json_response;
                $test_post_meta_store = [];
                $test_post_store      = [];
                $test_json_response   = null;

                $eventId = 9001;

                $this->seedEvent($eventId);
                $this->stubWpdbTokenLookup($eventId);

                // Stub mime_content_type() to return the invalid MIME type
                $this->sut->stubbedMimeType = $invalidMime;

                // Create a temp file (content doesn't matter — MIME is stubbed)
                $tmpPath = $this->createTempFile('fake file content');

                $_POST['tmgmt_token'] = 'valid-test-token';
                $_POST['nonce']       = 'test_nonce';

                $_FILES['signed_contract'] = [
                    'name'     => 'contract.bin',
                    'type'     => $invalidMime,
                    'tmp_name' => $tmpPath,
                    'error'    => UPLOAD_ERR_OK,
                    'size'     => 17,
                ];

                $this->sut->handle_signed_contract_upload();

                // 1. Response must be an error (not success)
                $this->assertNotNull(
                    $test_json_response,
                    "handle_signed_contract_upload() produced no JSON response for MIME type: $invalidMime"
                );
                $this->assertFalse(
                    $test_json_response['success'],
                    "Upload with invalid MIME type '$invalidMime' was incorrectly accepted as success"
                );

                // 2. No attachment ID must be stored on the event
                $attachmentId = get_post_meta($eventId, '_tmgmt_signed_contract_attachment_id', true);
                $this->assertEmpty(
                    $attachmentId,
                    "_tmgmt_signed_contract_attachment_id was set despite invalid MIME type '$invalidMime'"
                );

                // 3. Status must NOT be changed to 'contract_signed'
                $status = get_post_meta($eventId, '_tmgmt_status', true);
                $this->assertNotEquals(
                    'contract_signed',
                    $status,
                    "_tmgmt_status was changed to 'contract_signed' despite invalid MIME type '$invalidMime'"
                );
            });
    }
}
