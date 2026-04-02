<?php
/**
 * Unit Tests für Fehlerfälle der Vertragsgenerierung
 *
 * Tests:
 * - test_template_missing_returns_wp_error()         — Req. 1.5
 * - test_missing_contract_email_returns_wp_error()   — Req. 5.4
 * - test_pdf_generation_failure_does_not_change_status() — Req. 4.5
 * - test_signature_img_tag_present_when_url_set()    — Req. 1.3
 */

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
        global $test_wp_mail_last_recipient;
        $test_wp_mail_last_recipient = $to;
        return true;
    }
}

if (!class_exists('TMGMT_Log_Manager')) {
    class TMGMT_Log_Manager {
        public function log($post_id, $type, $message): void {}
    }
}

if (!class_exists('TMGMT_Communication_Manager')) {
    class TMGMT_Communication_Manager {
        public function add_entry(int $event_id, string $type, string $recipient, string $subject, string $body, int $user_id): void {}
    }
}

// Stubs needed by TMGMT_Customer_Access_Manager
if (!function_exists('home_url')) {
    function home_url(string $path = ''): string { return 'http://example.com' . $path; }
}
if (!function_exists('esc_js')) {
    function esc_js(string $text): string { return addslashes($text); }
}
if (!function_exists('wpautop')) {
    function wpautop(string $text): string { return '<p>' . $text . '</p>'; }
}
if (!function_exists('wp_kses_post')) {
    function wp_kses_post(string $text): string { return $text; }
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
    function check_ajax_referer($action = -1, $query_arg = false, $die = true): bool { return true; }
}
if (!function_exists('sanitize_email')) {
    function sanitize_email(string $email): string {
        return filter_var($email, FILTER_SANITIZE_EMAIL) ?: '';
    }
}
if (!function_exists('media_handle_upload')) {
    function media_handle_upload(string $file_id, int $post_id, array $post_data = [], array $overrides = []): int {
        return 999;
    }
}
if (!function_exists('get_userdata')) {
    function get_userdata(int $user_id) { return false; }
}
if (!function_exists('mime_content_type')) {
    function mime_content_type(string $filename): string { return 'application/pdf'; }
}
if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}
if (!class_exists('TMGMT_Event_Status')) {
    class TMGMT_Event_Status {
        const CONTRACT_SENT = 'contract_sent';
        public static function get_all_statuses(): array { return []; }
        public static function get_label(string $status): string { return $status; }
    }
}

require_once dirname(__DIR__, 2) . '/includes/class-customer-access-manager.php';
require_once dirname(__DIR__, 2) . '/includes/class-placeholder-parser.php';
require_once dirname(__DIR__, 2) . '/includes/class-pdf-generator.php';
require_once dirname(__DIR__, 2) . '/includes/class-contract-generator.php';

/**
 * A test double for TMGMT_Contract_Generator that overrides save_pdf()
 * to simulate a PDF generation failure, without touching the real PDF library.
 */
class TMGMT_Contract_Generator_PdfFails extends TMGMT_Contract_Generator {
    public function save_pdf( string $html, int $event_id ): array|WP_Error {
        return new WP_Error( 'mpdf_error', 'Simulated PDF generation failure.' );
    }
}

/**
 * Unit Tests: Fehlerfälle der Vertragsgenerierung
 */
class ContractErrorCasesTest extends \PHPUnit\Framework\TestCase
{
    /** @var list<string> Temp files to clean up after each test */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        global $test_post_meta_store, $test_post_store, $test_options_store;
        $test_post_meta_store = [];
        $test_post_store      = [];
        $test_options_store   = [];

        update_option('date_format', 'Y-m-d');
        update_option('time_format', 'H:i');
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        $this->tempFiles = [];
    }

    // -------------------------------------------------------------------------
    // Helper: populate minimal event meta so generate_and_send() can proceed
    // past the email-check and template-check steps.
    // -------------------------------------------------------------------------
    private function seedEventMeta(int $eventId, string $contractEmail = 'test@example.com'): void
    {
        $meta = [
            '_tmgmt_contact_email_contract' => $contractEmail,
            '_tmgmt_event_date'             => '2024-06-15',
            '_tmgmt_event_start_time'       => '20:00',
            '_tmgmt_event_arrival_time'     => '18:00',
            '_tmgmt_event_departure_time'   => '23:00',
            '_tmgmt_contact_salutation'     => 'Herr',
            '_tmgmt_contact_firstname'      => 'Max',
            '_tmgmt_contact_lastname'       => 'Mustermann',
            '_tmgmt_contact_company'        => 'Test GmbH',
            '_tmgmt_contact_street'         => 'Musterstraße',
            '_tmgmt_contact_number'         => '1',
            '_tmgmt_contact_zip'            => '12345',
            '_tmgmt_contact_city'           => 'Berlin',
            '_tmgmt_contact_country'        => 'Deutschland',
            '_tmgmt_contact_email'          => 'test@example.com',
            '_tmgmt_contact_phone'          => '+49 30 123456',
            '_tmgmt_contact_phone_contract' => '+49 30 654321',
            '_tmgmt_contact_name_tech'      => 'Tech Person',
            '_tmgmt_contact_email_tech'     => 'tech@example.com',
            '_tmgmt_contact_phone_tech'     => '+49 30 111222',
            '_tmgmt_contact_name_program'   => 'Program Person',
            '_tmgmt_contact_email_program'  => 'prog@example.com',
            '_tmgmt_contact_phone_program'  => '+49 30 333444',
            '_tmgmt_fee'                    => '1500',
            '_tmgmt_deposit'                => '0',
            '_tmgmt_inquiry_date'           => '2024-01-01',
        ];
        foreach ($meta as $key => $value) {
            update_post_meta($eventId, $key, $value);
        }
    }

    private function seedActionMeta(int $actionId, string $targetStatus = 'contract_sent'): void
    {
        update_post_meta($actionId, '_tmgmt_action_type', 'contract_generation');
        update_post_meta($actionId, '_tmgmt_action_email_template_id', '0');
        update_post_meta($actionId, '_tmgmt_action_target_status', $targetStatus);
    }

    private function seedPostStore(int $eventId, int $actionId): void
    {
        global $test_post_store;

        $fakeEvent               = new stdClass();
        $fakeEvent->ID           = $eventId;
        $fakeEvent->post_title   = 'Test Event';
        $fakeEvent->post_content = '';
        $fakeEvent->post_type    = 'tmgmt_event';
        $test_post_store[$eventId] = $fakeEvent;

        $fakeAction               = new stdClass();
        $fakeAction->ID           = $actionId;
        $fakeAction->post_title   = 'Test Action';
        $fakeAction->post_content = '';
        $fakeAction->post_type    = 'tmgmt_action';
        $test_post_store[$actionId] = $fakeAction;
    }

    // =========================================================================
    // Test 1 — Req. 1.5
    // =========================================================================

    /**
     * When no template file exists, render_template() returns WP_Error
     * with code 'template_missing'.
     *
     * Requirements: 1.5
     */
    public function test_template_missing_returns_wp_error(): void
    {
        $sut = new TMGMT_Contract_Generator();

        $result = $sut->render_template(
            event_id: 1,
            template_file: 'this_template_does_not_exist_xyz.php'
        );

        $this->assertInstanceOf(
            WP_Error::class,
            $result,
            'render_template() must return WP_Error when the template file does not exist'
        );
        $this->assertSame(
            'template_missing',
            $result->get_error_code(),
            'WP_Error code must be "template_missing"'
        );
    }

    // =========================================================================
    // Test 2 — Req. 5.4
    // =========================================================================

    /**
     * When _tmgmt_contact_email_contract is empty, generate_and_send() returns
     * WP_Error with code 'missing_contract_email'.
     *
     * Requirements: 5.4
     */
    public function test_missing_contract_email_returns_wp_error(): void
    {
        $eventId  = 101;
        $actionId = 201;

        $this->seedPostStore($eventId, $actionId);
        // Seed meta WITHOUT a contract email
        $this->seedEventMeta($eventId, contractEmail: '');
        $this->seedActionMeta($actionId);

        $sut    = new TMGMT_Contract_Generator();
        $result = $sut->generate_and_send($eventId, $actionId);

        $this->assertInstanceOf(
            WP_Error::class,
            $result,
            'generate_and_send() must return WP_Error when contract email is empty'
        );
        $this->assertSame(
            'missing_contract_email',
            $result->get_error_code(),
            'WP_Error code must be "missing_contract_email"'
        );
    }

    // =========================================================================
    // Test 3 — Req. 4.5
    // =========================================================================

    /**
     * When PDF generation fails, generate_and_send() returns WP_Error and does
     * NOT change _tmgmt_status on the event.
     *
     * Uses TMGMT_Contract_Generator_PdfFails — a subclass that overrides
     * save_pdf() to return a WP_Error, simulating a PDF library failure.
     *
     * Requirements: 4.5
     */
    public function test_pdf_generation_failure_does_not_change_status(): void
    {
        $eventId  = 102;
        $actionId = 202;

        $this->seedPostStore($eventId, $actionId);
        $this->seedEventMeta($eventId);
        $this->seedActionMeta($actionId, targetStatus: 'contract_sent');

        // Set a known initial status so we can verify it is unchanged
        update_post_meta($eventId, '_tmgmt_status', 'initial_status');

        $sut    = new TMGMT_Contract_Generator_PdfFails();
        $result = $sut->generate_and_send($eventId, $actionId);

        // Must return a WP_Error
        $this->assertInstanceOf(
            WP_Error::class,
            $result,
            'generate_and_send() must return WP_Error when PDF generation fails'
        );

        // _tmgmt_status must remain unchanged
        $status = get_post_meta($eventId, '_tmgmt_status', true);
        $this->assertSame(
            'initial_status',
            $status,
            '_tmgmt_status must not be changed when PDF generation fails'
        );
    }

    // =========================================================================
    // Test 4 — Req. 6.6
    // =========================================================================

    /**
     * When tmgmt_contract_notification_user_id is not configured (0 / empty),
     * handle_signed_contract_upload() sends the notification email to admin_email.
     *
     * Requirements: 6.6
     */
    public function test_notification_fallback_to_admin_email(): void
    {
        global $test_wp_mail_last_recipient, $test_post_meta_store, $test_post_store,
               $test_json_response, $test_options_store;

        $test_post_meta_store       = [];
        $test_post_store            = [];
        $test_json_response         = null;
        $test_wp_mail_last_recipient = null;

        // Do NOT set tmgmt_contract_notification_user_id (or set it to 0)
        update_option('tmgmt_contract_notification_user_id', 0);
        update_option('admin_email', 'admin@example.com');

        $eventId = 501;

        // Seed a fake event post
        $fakeEvent               = new stdClass();
        $fakeEvent->ID           = $eventId;
        $fakeEvent->post_title   = 'Fallback Test Event';
        $fakeEvent->post_content = '';
        $fakeEvent->post_type    = 'event';
        $test_post_store[$eventId] = $fakeEvent;

        // Stub $wpdb to return a valid token record pointing to this event
        $record           = new stdClass();
        $record->id       = 1;
        $record->event_id = $eventId;
        $record->token    = 'fallback-test-token';
        $record->status   = 'active';

        $GLOBALS['wpdb'] = new class($record) {
            private object $record;
            public string $prefix = 'wp_';
            public function __construct(object $record) { $this->record = $record; }
            public function get_row($query, $output = OBJECT, $y = 0): ?object { return $this->record; }
            public function prepare($query, ...$args): string { return $query; }
            public function insert($table, $data, $format = null) { return false; }
            public function update($table, $data, $where, $format = null, $where_format = null) { return false; }
            public function get_results($query, $output = OBJECT): array { return []; }
            public function get_charset_collate(): string { return ''; }
            public function get_var($query) { return null; }
        };

        // Create a temp PDF file
        $tmpPath = tempnam(sys_get_temp_dir(), 'tmgmt_fallback_');
        file_put_contents($tmpPath, '%PDF-1.4 test content');

        $_POST['tmgmt_token'] = 'fallback-test-token';
        $_POST['nonce']       = 'test_nonce';

        $_FILES['signed_contract'] = [
            'name'     => 'signed-contract.pdf',
            'type'     => 'application/pdf',
            'tmp_name' => $tmpPath,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize($tmpPath),
        ];

        $sut = new TMGMT_Customer_Access_Manager();
        $sut->handle_signed_contract_upload();

        // Clean up
        if (file_exists($tmpPath)) {
            @unlink($tmpPath);
        }
        $_POST  = [];
        $_FILES = [];

        // The upload must have succeeded
        $this->assertNotNull($test_json_response, 'handle_signed_contract_upload() produced no JSON response');
        $this->assertTrue(
            $test_json_response['success'],
            'handle_signed_contract_upload() returned error: ' .
            ($test_json_response['data']['message'] ?? '(no message)')
        );

        // wp_mail() must have been called with the admin email as recipient
        $this->assertSame(
            'admin@example.com',
            $test_wp_mail_last_recipient,
            'Notification email must fall back to admin_email when no notification user is configured'
        );
    }

    // =========================================================================
    // Test 5 — Req. 1.3
    // =========================================================================

    /**
     * When tmgmt_contract_signature_id is set, render_template() output contains
     * an <img> tag with the signature URL.
     *
     * The bootstrap stubs wp_get_attachment_url() to return a fixed URL.
     * We set the option to a non-zero ID and verify the rendered HTML contains
     * an <img> tag pointing to that URL.
     *
     * Requirements: 1.3
     */
    public function test_signature_img_tag_present_when_url_set(): void
    {
        // The bootstrap stub returns this URL for any non-zero attachment ID
        $expectedUrl = 'http://example.com/wp-content/uploads/test-file.pdf';

        // Set a non-zero signature attachment ID
        update_option('tmgmt_contract_signature_id', 42);

        // Minimal event meta so the template renders without errors
        $eventId = 103;
        $this->seedEventMeta($eventId);

        $sut    = new TMGMT_Contract_Generator();
        $result = $sut->render_template($eventId);

        $this->assertIsString(
            $result,
            'render_template() must return a string when the template exists'
        );
        $this->assertStringContainsString(
            '<img',
            $result,
            'Rendered HTML must contain an <img> tag when signature_id is set'
        );
        $this->assertStringContainsString(
            $expectedUrl,
            $result,
            'The <img> tag must reference the signature URL returned by wp_get_attachment_url()'
        );
    }
}
