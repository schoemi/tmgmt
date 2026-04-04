<?php
// Feature: contract-send-dialog, Task 1.2: generate_and_send() overrides unit tests

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
    function is_wp_error($thing): bool {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('date_i18n')) {
    function date_i18n(string $format, int $timestamp = 0): string {
        return date($format, $timestamp ?: time());
    }
}

require_once dirname(__DIR__, 2) . '/includes/class-placeholder-parser.php';
require_once dirname(__DIR__, 2) . '/includes/class-pdf-generator.php';
require_once dirname(__DIR__, 2) . '/includes/class-contract-generator.php';

/**
 * Standalone spy SMTP sender (duck-typed, no inheritance from real class).
 */
if (!class_exists('TMGMT_SMTP_Sender_Spy')) {
    class TMGMT_SMTP_Sender_Spy {
        /** @var array[] */
        public static array $calls = [];

        public function send(array $params): array {
            self::$calls[] = $params;
            return ['success' => true, 'raw_email' => '', 'message_id' => ''];
        }

        public static function reset(): void {
            self::$calls = [];
        }
    }
}

/**
 * Standalone spy Communication Manager (duck-typed, no inheritance from real class).
 */
if (!class_exists('TMGMT_Communication_Manager_Spy')) {
    class TMGMT_Communication_Manager_Spy {
        /** @var array[] */
        public static array $calls = [];

        public function add_entry($event_id, $type, $recipient, $subject, $content, $user_id = null) {
            self::$calls[] = [
                'event_id'  => $event_id,
                'type'      => $type,
                'recipient' => $recipient,
                'subject'   => $subject,
                'content'   => $content,
                'user_id'   => $user_id,
            ];
            return 1;
        }

        public static function reset(): void {
            self::$calls = [];
        }
    }
}

/**
 * Standalone spy Log Manager (duck-typed, no inheritance from real class).
 */
if (!class_exists('TMGMT_Log_Manager_Spy')) {
    class TMGMT_Log_Manager_Spy {
        /** @var array[] */
        public static array $calls = [];

        public function log($post_id, $type, $message) {
            self::$calls[] = [
                'post_id' => $post_id,
                'type'    => $type,
                'message' => $message,
            ];
        }

        public function render_log_table($post_id) {}

        public static function reset(): void {
            self::$calls = [];
        }
    }
}

/**
 * Testable subclass that injects spy dependencies via factory methods.
 */
if (!class_exists('TMGMT_Contract_Generator_Testable')) {
    class TMGMT_Contract_Generator_Testable extends TMGMT_Contract_Generator {
        protected function make_smtp_sender() {
            return new TMGMT_SMTP_Sender_Spy();
        }

        protected function make_communication_manager() {
            return new TMGMT_Communication_Manager_Spy();
        }

        protected function make_log_manager() {
            return new TMGMT_Log_Manager_Spy();
        }
    }
}

if (!class_exists('TMGMT_Customer_Access_Manager')) {
    class TMGMT_Customer_Access_Manager {
        public function get_valid_token(int $event_id): string {
            return 'test-token-' . $event_id;
        }
        public function get_dashboard_url(int $event_id, string $token): string {
            return 'http://example.com/dashboard/?token=' . $token;
        }
    }
}

/**
 * Unit tests for TMGMT_Contract_Generator::generate_and_send() with $overrides parameter.
 *
 * **Validates: Requirements 6.2, 6.4, 6.7, 6.8, 7.4**
 */
class ContractGenerateAndSendOverridesTest extends \PHPUnit\Framework\TestCase
{
    private TMGMT_Contract_Generator_Testable $sut;
    private array $tempFiles = [];

    protected function setUp(): void
    {
        global $test_post_meta_store, $test_post_store, $test_options_store;

        $test_post_meta_store = [];
        $test_post_store      = [];
        $test_options_store   = [];

        TMGMT_SMTP_Sender_Spy::reset();
        TMGMT_Communication_Manager_Spy::reset();
        TMGMT_Log_Manager_Spy::reset();

        update_option('date_format', 'Y-m-d');
        update_option('time_format', 'H:i');

        $this->sut = new TMGMT_Contract_Generator_Testable();
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

    private function setUpFullScenario(
        int $eventId = 1001,
        int $actionId = 2001,
        int $templateId = 3001,
        int $emailTemplateId = 4001,
        string $contactEmail = 'original@example.com',
        string $targetStatus = 'contract_sent'
    ): void {
        global $test_post_store;

        $event               = new stdClass();
        $event->ID           = $eventId;
        $event->post_title   = 'Test Event';
        $event->post_content = '';
        $event->post_type    = 'tmgmt_event';
        $test_post_store[$eventId] = $event;

        $tpl               = new stdClass();
        $tpl->ID           = $templateId;
        $tpl->post_title   = 'Standard-Vertrag';
        $tpl->post_content = '<p>Vertrag für [contact_firstname]</p>';
        $tpl->post_status  = 'publish';
        $tpl->post_type    = 'tmgmt_contract_tpl';
        $test_post_store[$templateId] = $tpl;

        $emailTpl               = new stdClass();
        $emailTpl->ID           = $emailTemplateId;
        $emailTpl->post_title   = 'Email Template';
        $emailTpl->post_content = '';
        $emailTpl->post_type    = 'tmgmt_email_tpl';
        $test_post_store[$emailTemplateId] = $emailTpl;

        update_post_meta($emailTemplateId, '_tmgmt_email_subject', 'Original Betreff');
        update_post_meta($emailTemplateId, '_tmgmt_email_body', '<p>Original Body</p>');

        $action               = new stdClass();
        $action->ID           = $actionId;
        $action->post_title   = 'Send Contract';
        $action->post_content = '';
        $action->post_type    = 'tmgmt_action';
        $test_post_store[$actionId] = $action;

        update_post_meta($actionId, '_tmgmt_action_type', 'contract_generation');
        update_post_meta($actionId, '_tmgmt_action_contract_template_id', (string) $templateId);
        update_post_meta($actionId, '_tmgmt_action_email_template_id', (string) $emailTemplateId);
        update_post_meta($actionId, '_tmgmt_action_target_status', $targetStatus);

        // Contact chain: Event → Veranstalter → Contact (Rolle: vertrag)
        $contactId = 5001;
        $contact               = new stdClass();
        $contact->ID           = $contactId;
        $contact->post_title   = 'Max Mustermann';
        $contact->post_content = '';
        $contact->post_type    = 'tmgmt_contact';
        $test_post_store[$contactId] = $contact;

        update_post_meta($contactId, '_tmgmt_contact_salutation', 'Herr');
        update_post_meta($contactId, '_tmgmt_contact_firstname', 'Max');
        update_post_meta($contactId, '_tmgmt_contact_lastname', 'Mustermann');
        update_post_meta($contactId, '_tmgmt_contact_email', $contactEmail);

        $veranstalterId = 6001;
        $veranstalter               = new stdClass();
        $veranstalter->ID           = $veranstalterId;
        $veranstalter->post_title   = 'Test Veranstalter';
        $veranstalter->post_content = '';
        $veranstalter->post_type    = 'tmgmt_veranstalter';
        $test_post_store[$veranstalterId] = $veranstalter;

        update_post_meta($veranstalterId, '_tmgmt_veranstalter_contacts', [
            ['role' => 'vertrag', 'contact_id' => $contactId],
        ]);

        update_post_meta($eventId, '_tmgmt_event_veranstalter_id', (string) $veranstalterId);

        $metaValues = [
            '_tmgmt_event_date'           => '2024-06-15',
            '_tmgmt_event_start_time'     => '20:00',
            '_tmgmt_event_arrival_time'   => '18:00',
            '_tmgmt_event_departure_time' => '23:00',
            '_tmgmt_fee'                  => '1000',
            '_tmgmt_deposit'              => '500',
            '_tmgmt_inquiry_date'         => '2024-01-01',
        ];
        foreach ($metaValues as $key => $value) {
            update_post_meta($eventId, $key, $value);
        }
    }

    private function trackPdfCleanup(int $eventId): void
    {
        $pdfPath = get_post_meta($eventId, '_tmgmt_contract_pdf_path', true);
        if (!empty($pdfPath)) {
            $this->tempFiles[] = $pdfPath;
        }
    }

    /** Test: backward compatibility — no overrides works as before. */
    public function testGenerateAndSendWithoutOverridesStillWorks(): void
    {
        $this->setUpFullScenario();
        $result = $this->sut->generate_and_send(1001, 2001);

        $this->assertFalse(
            is_wp_error($result),
            'generate_and_send() without overrides returned WP_Error: ' . (is_wp_error($result) ? $result->get_error_message() : '')
        );
        $this->assertTrue($result);
        $this->assertCount(1, TMGMT_SMTP_Sender_Spy::$calls);
        $this->assertSame('original@example.com', TMGMT_SMTP_Sender_Spy::$calls[0]['to']);
        $this->trackPdfCleanup(1001);
    }

    /** Test: 'to' override replaces the template-resolved recipient. Validates: Req 7.4 */
    public function testToOverrideReplacesRecipient(): void
    {
        $this->setUpFullScenario();
        $result = $this->sut->generate_and_send(1001, 2001, ['to' => 'override@example.com']);

        $this->assertFalse(is_wp_error($result));
        $this->assertSame('override@example.com', TMGMT_SMTP_Sender_Spy::$calls[0]['to']);
        $this->trackPdfCleanup(1001);
    }

    /** Test: 'subject' override replaces the template-resolved subject. Validates: Req 7.4 */
    public function testSubjectOverrideReplacesSubject(): void
    {
        $this->setUpFullScenario();
        $result = $this->sut->generate_and_send(1001, 2001, ['subject' => 'Overridden Subject']);

        $this->assertFalse(is_wp_error($result));
        $this->assertSame('Overridden Subject', TMGMT_SMTP_Sender_Spy::$calls[0]['subject']);
        $this->trackPdfCleanup(1001);
    }

    /** Test: 'body' override replaces the template-resolved body. Validates: Req 7.4 */
    public function testBodyOverrideReplacesBody(): void
    {
        $this->setUpFullScenario();
        $result = $this->sut->generate_and_send(1001, 2001, ['body' => '<p>Custom body</p>']);

        $this->assertFalse(is_wp_error($result));
        $this->assertSame('<p>Custom body</p>', TMGMT_SMTP_Sender_Spy::$calls[0]['body']);
        $this->trackPdfCleanup(1001);
    }

    /** Test: 'cc' and 'bcc' overrides are passed to SMTP sender. Validates: Req 7.4 */
    public function testCcAndBccOverridesArePassedToSmtp(): void
    {
        $this->setUpFullScenario();
        $result = $this->sut->generate_and_send(1001, 2001, [
            'cc'  => 'cc@example.com',
            'bcc' => 'bcc@example.com',
        ]);

        $this->assertFalse(is_wp_error($result));
        $this->assertSame('cc@example.com', TMGMT_SMTP_Sender_Spy::$calls[0]['cc']);
        $this->assertSame('bcc@example.com', TMGMT_SMTP_Sender_Spy::$calls[0]['bcc']);
        $this->trackPdfCleanup(1001);
    }

    /** Test: 'template_id' override uses a different contract template. Validates: Req 7.4 */
    public function testTemplateIdOverrideUsesAlternateTemplate(): void
    {
        global $test_post_store;
        $this->setUpFullScenario();

        $altTemplateId = 3099;
        $altTpl               = new stdClass();
        $altTpl->ID           = $altTemplateId;
        $altTpl->post_title   = 'Festival-Vertrag';
        $altTpl->post_content = '<p>Festival Vertrag Content</p>';
        $altTpl->post_status  = 'publish';
        $altTpl->post_type    = 'tmgmt_contract_tpl';
        $test_post_store[$altTemplateId] = $altTpl;

        $result = $this->sut->generate_and_send(1001, 2001, ['template_id' => $altTemplateId]);

        $this->assertFalse(
            is_wp_error($result),
            'template_id override failed: ' . (is_wp_error($result) ? $result->get_error_message() : '')
        );
        $this->assertTrue($result);
        $this->trackPdfCleanup(1001);
    }

    /** Test: Communication entry uses actually-sent (overridden) values. Validates: Req 6.7 */
    public function testCommunicationEntryUsesActuallySentValues(): void
    {
        $this->setUpFullScenario();
        $result = $this->sut->generate_and_send(1001, 2001, [
            'to'      => 'override@example.com',
            'subject' => 'Custom Subject',
            'body'    => '<p>Custom Body</p>',
        ]);

        $this->assertFalse(is_wp_error($result));
        $this->assertCount(1, TMGMT_Communication_Manager_Spy::$calls);

        $entry = TMGMT_Communication_Manager_Spy::$calls[0];
        $this->assertSame(1001, $entry['event_id']);
        $this->assertSame('email', $entry['type']);
        $this->assertSame('override@example.com', $entry['recipient']);
        $this->assertSame('Custom Subject', $entry['subject']);
        $this->assertSame('<p>Custom Body</p>', $entry['content']);
        $this->trackPdfCleanup(1001);
    }

    /** Test: contract_sent log entry contains actual recipient and target status. Validates: Req 6.8 */
    public function testContractSentLogEntryContainsActualRecipientAndStatus(): void
    {
        $this->setUpFullScenario(targetStatus: 'confirmed');
        $result = $this->sut->generate_and_send(1001, 2001, ['to' => 'override@example.com']);

        $this->assertFalse(is_wp_error($result));

        $sentLogs = array_filter(TMGMT_Log_Manager_Spy::$calls, fn($l) => $l['type'] === 'contract_sent');
        $this->assertCount(1, $sentLogs);

        $sentLog = array_values($sentLogs)[0];
        $this->assertSame(1001, $sentLog['post_id']);
        $this->assertStringContainsString('override@example.com', $sentLog['message']);
        $this->assertStringContainsString('confirmed', $sentLog['message']);
        $this->trackPdfCleanup(1001);
    }

    /** Test: Status is updated correctly with overrides. Validates: Req 6.2, 6.4 */
    public function testStatusUpdateWorksWithOverrides(): void
    {
        $this->setUpFullScenario(targetStatus: 'confirmed');
        $result = $this->sut->generate_and_send(1001, 2001, ['to' => 'override@example.com']);

        $this->assertFalse(is_wp_error($result));
        $this->assertSame('confirmed', get_post_meta(1001, '_tmgmt_status', true));
        $this->trackPdfCleanup(1001);
    }

    /** Test: 'to' override bypasses missing contact email. Validates: Req 7.4 */
    public function testToOverrideBypassesMissingContactEmail(): void
    {
        $this->setUpFullScenario();
        delete_post_meta(1001, '_tmgmt_event_veranstalter_id');

        $result = $this->sut->generate_and_send(1001, 2001, ['to' => 'fallback@example.com']);

        $this->assertFalse(
            is_wp_error($result),
            'to override should bypass missing contact: ' . (is_wp_error($result) ? $result->get_error_message() : '')
        );
        $this->assertSame('fallback@example.com', TMGMT_SMTP_Sender_Spy::$calls[0]['to']);
        $this->trackPdfCleanup(1001);
    }

    /** Test: Without overrides and without contact email, returns error. */
    public function testWithoutOverridesAndNoContactEmailReturnsError(): void
    {
        $this->setUpFullScenario();
        delete_post_meta(1001, '_tmgmt_event_veranstalter_id');

        $result = $this->sut->generate_and_send(1001, 2001);

        $this->assertTrue(is_wp_error($result));
        $this->assertSame('missing_contract_email', $result->get_error_code());
    }
}
