<?php
// Feature: contract-send-dialog, Property 2: Übermittelte Felder werden für den Versand verwendet
// For any to/subject/body: generate_and_send() with overrides passes those values to SMTP sender

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
 * Property-Based Test: Übermittelte Felder werden für den Versand verwendet (Round-Trip)
 *
 * For any valid combination of overridden to, subject, and body values,
 * generate_and_send() must pass exactly those overridden values to the
 * SMTP sender — not the template defaults.
 *
 * **Validates: Requirements 2.4, 5.4, 6.1, 7.2**
 */
class ContractOverridesRoundTripPropertyTest extends \PHPUnit\Framework\TestCase
{
    use TestTrait;

    private TMGMT_Contract_Generator_Testable $sut;

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

        update_post_meta($emailTemplateId, '_tmgmt_email_subject', 'Template Betreff');
        update_post_meta($emailTemplateId, '_tmgmt_email_body', '<p>Template Body</p>');

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
     * Property 2: Übermittelte Felder werden für den Versand verwendet.
     *
     * For any randomly generated email address, subject, and body,
     * calling generate_and_send() with those overrides must result in
     * the SMTP sender receiving exactly those values — not the template defaults.
     */
    public function testOverriddenFieldsArePassedToSmtpSender(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                // Random email addresses
                Generator\elements(
                    'alice@example.com',
                    'bob@example.org',
                    'carol@test.de',
                    'dave@sample.net',
                    'eve@domain.com',
                    'frank@musik.de',
                    'greta@konzert.at',
                    'hans@booking.ch',
                    'ida@veranstaltung.de',
                    'jan@agentur.com'
                ),
                // Random subjects
                Generator\map(
                    function (string $s): string {
                        // Ensure non-empty subject by prepending a prefix
                        return 'Betreff: ' . $s;
                    },
                    Generator\string()
                ),
                // Random body content
                Generator\map(
                    function (string $s): string {
                        return '<p>' . htmlspecialchars($s, ENT_QUOTES, 'UTF-8') . '</p>';
                    },
                    Generator\string()
                )
            )
            ->then(function (
                string $overrideTo,
                string $overrideSubject,
                string $overrideBody
            ): void {
                global $test_post_meta_store, $test_post_store;
                $test_post_meta_store = [];
                $test_post_store      = [];

                TMGMT_SMTP_Sender_Spy::reset();
                TMGMT_Communication_Manager_Spy::reset();
                TMGMT_Log_Manager_Spy::reset();

                $eventId         = 1001;
                $actionId        = 2001;
                $templateId      = 3001;
                $emailTemplateId = 4001;

                $this->setUpScenario(
                    $eventId,
                    $actionId,
                    $templateId,
                    $emailTemplateId,
                    'original@example.com'
                );

                // Call generate_and_send with overrides
                $result = $this->sut->generate_and_send($eventId, $actionId, [
                    'to'      => $overrideTo,
                    'subject' => $overrideSubject,
                    'body'    => $overrideBody,
                ]);

                // Must succeed
                $this->assertFalse(
                    is_wp_error($result),
                    'generate_and_send() returned WP_Error: '
                        . (is_wp_error($result) ? $result->get_error_message() : '')
                );

                // SMTP sender must have been called exactly once
                $this->assertCount(
                    1,
                    TMGMT_SMTP_Sender_Spy::$calls,
                    'SMTP sender should be called exactly once'
                );

                $smtpCall = TMGMT_SMTP_Sender_Spy::$calls[0];

                // The overridden 'to' must be used, not the template default
                $this->assertSame(
                    $overrideTo,
                    $smtpCall['to'],
                    sprintf(
                        'SMTP "to" should be "%s" (override) but got "%s"',
                        $overrideTo,
                        $smtpCall['to']
                    )
                );

                // The overridden 'subject' must be used, not the template default
                $this->assertSame(
                    $overrideSubject,
                    $smtpCall['subject'],
                    sprintf(
                        'SMTP "subject" should be the override but got "%s"',
                        $smtpCall['subject']
                    )
                );

                // The overridden 'body' must be used, not the template default
                $this->assertSame(
                    $overrideBody,
                    $smtpCall['body'],
                    sprintf(
                        'SMTP "body" should be the override but got "%s"',
                        $smtpCall['body']
                    )
                );

                // Clean up generated PDF
                $pdfPath = get_post_meta($eventId, '_tmgmt_contract_pdf_path', true);
                if (!empty($pdfPath)) {
                    $this->tempFiles[] = $pdfPath;
                }
            });
    }
}
