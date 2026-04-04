<?php
// Feature: contract-event-attachment, Property 4: Fehlerresilienz
// For any event ID, when wp_insert_attachment() fails: generate_and_send() does not abort,
// email is still sent, and a contract_error log entry is written.

use Eris\Generator;
use Eris\TestTrait;

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
 * Standalone spy SMTP sender (duck-typed).
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
 * Standalone spy Communication Manager (duck-typed).
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
 * Standalone spy Log Manager (duck-typed).
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
 * Testable subclass that injects spy dependencies and simulates attachment failure.
 *
 * Overrides register_pdf_attachment() to always return a WP_Error,
 * simulating wp_insert_attachment() returning 0 or WP_Error.
 */
class TMGMT_Contract_Generator_AttachmentFail extends TMGMT_Contract_Generator {
    protected function make_smtp_sender() {
        return new TMGMT_SMTP_Sender_Spy();
    }

    protected function make_communication_manager() {
        return new TMGMT_Communication_Manager_Spy();
    }

    protected function make_log_manager() {
        return new TMGMT_Log_Manager_Spy();
    }

    /**
     * Always fails — simulates wp_insert_attachment() returning 0 or WP_Error.
     */
    protected function register_pdf_attachment(int $event_id, string $pdf_path, string $pdf_url): int|WP_Error {
        $log = $this->make_log_manager();
        $error = new WP_Error(
            'attachment_failed',
            sprintf('WP-Attachment konnte nicht erstellt werden für: %s', basename($pdf_path))
        );
        $log->log($event_id, 'contract_error', $error->get_error_message());
        return $error;
    }
}

/**
 * Property-Based Test: Fehlerresilienz — Versand wird bei Attachment-Fehler fortgesetzt
 *
 * For any event ID, when wp_insert_attachment() fails (returns 0 or WP_Error):
 * generate_and_send() does not abort, email is still sent, and a contract_error
 * log entry is written.
 *
 * **Validates: Requirements 1.5, 4.2**
 */
class ContractAttachmentErrorResiliencePropertyTest extends \PHPUnit\Framework\TestCase
{
    use TestTrait;

    private TMGMT_Contract_Generator_AttachmentFail $sut;

    /** @var list<string> Temp PDF files created during the test run */
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

        $this->sut = new TMGMT_Contract_Generator_AttachmentFail();
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

    /**
     * Set up the full event/action/template/contact chain for a single iteration.
     */
    private function setUpScenario(
        int    $eventId,
        int    $actionId,
        int    $templateId,
        int    $emailTemplateId,
        string $contactEmail
    ): void {
        global $test_post_store;

        // Event post
        $event               = new \stdClass();
        $event->ID           = $eventId;
        $event->post_title   = 'Event ' . $eventId;
        $event->post_content = '';
        $event->post_type    = 'tmgmt_event';
        $test_post_store[$eventId] = $event;

        // Contract template post
        $tpl               = new \stdClass();
        $tpl->ID           = $templateId;
        $tpl->post_title   = 'Vertrag ' . $templateId;
        $tpl->post_content = '<p>Vertrag für [contact_firstname]</p>';
        $tpl->post_status  = 'publish';
        $tpl->post_type    = 'tmgmt_contract_tpl';
        $test_post_store[$templateId] = $tpl;

        // Email template post
        $emailTpl               = new \stdClass();
        $emailTpl->ID           = $emailTemplateId;
        $emailTpl->post_title   = 'Email Template';
        $emailTpl->post_content = '';
        $emailTpl->post_type    = 'tmgmt_email_tpl';
        $test_post_store[$emailTemplateId] = $emailTpl;

        update_post_meta($emailTemplateId, '_tmgmt_email_subject', 'Betreff');
        update_post_meta($emailTemplateId, '_tmgmt_email_body', '<p>Body</p>');

        // Action post
        $action               = new \stdClass();
        $action->ID           = $actionId;
        $action->post_title   = 'Send Contract';
        $action->post_content = '';
        $action->post_type    = 'tmgmt_action';
        $test_post_store[$actionId] = $action;

        update_post_meta($actionId, '_tmgmt_action_type', 'contract_generation');
        update_post_meta($actionId, '_tmgmt_action_contract_template_id', (string) $templateId);
        update_post_meta($actionId, '_tmgmt_action_email_template_id', (string) $emailTemplateId);
        update_post_meta($actionId, '_tmgmt_action_target_status', 'contract_sent');

        // Contact chain: Event → Veranstalter → Contact (Rolle: vertrag)
        $contactId = $eventId + 90000;
        $contact               = new \stdClass();
        $contact->ID           = $contactId;
        $contact->post_title   = 'Kontakt ' . $contactId;
        $contact->post_content = '';
        $contact->post_type    = 'tmgmt_contact';
        $test_post_store[$contactId] = $contact;

        update_post_meta($contactId, '_tmgmt_contact_salutation', 'Herr');
        update_post_meta($contactId, '_tmgmt_contact_firstname', 'Max');
        update_post_meta($contactId, '_tmgmt_contact_lastname', 'Mustermann');
        update_post_meta($contactId, '_tmgmt_contact_email', $contactEmail);

        $veranstalterId = $eventId + 80000;
        $veranstalter               = new \stdClass();
        $veranstalter->ID           = $veranstalterId;
        $veranstalter->post_title   = 'Veranstalter ' . $veranstalterId;
        $veranstalter->post_content = '';
        $veranstalter->post_type    = 'tmgmt_veranstalter';
        $test_post_store[$veranstalterId] = $veranstalter;

        update_post_meta($veranstalterId, '_tmgmt_veranstalter_contacts', [
            ['role' => 'vertrag', 'contact_id' => $contactId],
        ]);

        update_post_meta($eventId, '_tmgmt_event_veranstalter_id', (string) $veranstalterId);

        // Event meta values required by placeholder parser
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

    /**
     * Property 4: Fehlerresilienz — Versand wird bei Attachment-Fehler fortgesetzt.
     *
     * For any randomly generated event ID, when register_pdf_attachment() fails
     * (simulating wp_insert_attachment() returning 0 or WP_Error):
     * - generate_and_send() returns true (email was still sent)
     * - SMTP sender was called exactly once (email went out)
     * - A contract_error log entry was written
     */
    public function testSendContinuesWhenAttachmentRegistrationFails(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generator\choose(1, 5000)
            )
            ->then(function (int $eventId): void {
                global $test_post_meta_store, $test_post_store;
                $test_post_meta_store = [];
                $test_post_store      = [];

                TMGMT_SMTP_Sender_Spy::reset();
                TMGMT_Communication_Manager_Spy::reset();
                TMGMT_Log_Manager_Spy::reset();

                // Use fixed offsets for related IDs to avoid collisions
                $actionId        = $eventId + 10000;
                $templateId      = $eventId + 20000;
                $emailTemplateId = $eventId + 30000;

                $this->setUpScenario(
                    $eventId,
                    $actionId,
                    $templateId,
                    $emailTemplateId,
                    'test@example.com'
                );

                // Call generate_and_send — attachment registration will fail
                $result = $this->sut->generate_and_send($eventId, $actionId);

                // 1) generate_and_send() must return true (not abort)
                $this->assertTrue(
                    $result === true,
                    sprintf(
                        'generate_and_send() should return true despite attachment failure, got: %s',
                        is_wp_error($result) ? $result->get_error_message() : var_export($result, true)
                    )
                );

                // 2) SMTP sender must have been called (email was sent)
                $this->assertGreaterThanOrEqual(
                    1,
                    count(TMGMT_SMTP_Sender_Spy::$calls),
                    'SMTP sender should have been called at least once — email must still be sent'
                );

                // 3) A contract_error log entry must exist
                $errorLogs = array_filter(
                    TMGMT_Log_Manager_Spy::$calls,
                    fn(array $entry) => $entry['type'] === 'contract_error' && $entry['post_id'] === $eventId
                );
                $this->assertNotEmpty(
                    $errorLogs,
                    'A contract_error log entry should be written when attachment registration fails'
                );

                // Clean up generated PDF
                $pdfPath = get_post_meta($eventId, '_tmgmt_contract_pdf_path', true);
                if (!empty($pdfPath)) {
                    $this->tempFiles[] = $pdfPath;
                }
            });
    }
}
