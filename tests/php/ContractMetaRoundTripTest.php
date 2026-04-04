<?php
// Feature: contract-generation, Property 4: Post-Meta wird nach PDF-Speicherung korrekt gesetzt

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

// Stub wp_mail so no real email is sent during tests
if (!function_exists('wp_mail')) {
    function wp_mail($to, $subject, $message, $headers = '', $attachments = array()): bool {
        return true;
    }
}

// Stub TMGMT_Log_Manager if not already defined
if (!class_exists('TMGMT_Log_Manager')) {
    class TMGMT_Log_Manager {
        public static function log($post_id, $type, $message): void {}
    }
}

// Stub TMGMT_Customer_Access_Manager if not already defined
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

// Stub TMGMT_Communication_Manager if not already defined
if (!class_exists('TMGMT_Communication_Manager')) {
    class TMGMT_Communication_Manager {
        public function add_entry($event_id, $type, $recipient = '', $subject = '', $content = '', $user_id = 0) { return 1; }
    }
}

// Require the classes under test
require_once dirname(__DIR__, 2) . '/includes/class-placeholder-parser.php';
require_once dirname(__DIR__, 2) . '/includes/class-pdf-generator.php';
require_once dirname(__DIR__, 2) . '/includes/class-contract-generator.php';

// Testable subclass with spy SMTP sender to avoid PHPMailer dependency
if (!class_exists('MetaRoundTrip_SMTP_Spy')) {
    class MetaRoundTrip_SMTP_Spy {
        public function send(array $params): array {
            return ['success' => true, 'raw_email' => '', 'message_id' => ''];
        }
    }
}
if (!class_exists('MetaRoundTrip_ContractGenerator')) {
    class MetaRoundTrip_ContractGenerator extends TMGMT_Contract_Generator {
        protected function make_smtp_sender() { return new MetaRoundTrip_SMTP_Spy(); }
        protected function make_communication_manager() { return new TMGMT_Communication_Manager(); }
        protected function make_log_manager() { return new TMGMT_Log_Manager(); }
    }
}

/**
 * Property-Based Test: Post-Meta wird nach PDF-Speicherung korrekt gesetzt
 *
 * For any Event with complete data, after a successful call to generate_and_send(),
 * both _tmgmt_contract_pdf_path and _tmgmt_contract_pdf_url must be set and non-empty
 * as post-meta on the event.
 *
 * **Validates: Requirements 4.4**
 */
class ContractMetaRoundTripTest extends \PHPUnit\Framework\TestCase
{
    use TestTrait;

    private TMGMT_Contract_Generator $sut;

    /** @var list<string> Temp PDF files created during the test run, cleaned up in tearDown */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        global $test_post_meta_store, $test_post_store, $test_options_store;
        $test_post_meta_store = [];
        $test_post_store      = [];
        $test_options_store   = [];

        update_option('date_format', 'Y-m-d');
        update_option('time_format', 'H:i');

        $this->sut = new MetaRoundTrip_ContractGenerator();
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
     * Property 4: After generate_and_send() succeeds, _tmgmt_contract_pdf_path and
     * _tmgmt_contract_pdf_url are set as post-meta on the event and are non-empty.
     */
    public function testPostMetaIsSetAfterPdfSave(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generator\string(),           // event title
                Generator\string(),           // event_date
                Generator\string(),           // fee
                Generator\elements(           // valid email
                    'alice@example.com',
                    'bob@example.org',
                    'carol@test.de',
                    'dave@sample.net',
                    'eve@domain.com'
                ),
                Generator\string(),           // contact_firstname
                Generator\string()            // contact_lastname
            )
            ->then(function (
                string $title,
                string $eventDate,
                string $fee,
                string $email,
                string $firstName,
                string $lastName
            ): void {
                global $test_post_meta_store, $test_post_store;
                $test_post_meta_store = [];
                $test_post_store      = [];

                $eventId  = 4001;
                $actionId = 5001;

                // Create a fake WP post for the event
                $fakeEvent               = new stdClass();
                $fakeEvent->ID           = $eventId;
                $fakeEvent->post_title   = $title;
                $fakeEvent->post_content = '';
                $fakeEvent->post_type    = 'tmgmt_event';
                $test_post_store[$eventId] = $fakeEvent;

                // Create a fake WP post for the action
                $fakeAction               = new stdClass();
                $fakeAction->ID           = $actionId;
                $fakeAction->post_title   = 'Test Contract Action';
                $fakeAction->post_content = '';
                $fakeAction->post_type    = 'tmgmt_action';
                $test_post_store[$actionId] = $fakeAction;

                // Create a published template post
                $templateId                   = 6001;
                $fakeTemplate                 = new stdClass();
                $fakeTemplate->ID             = $templateId;
                $fakeTemplate->post_title     = 'Test Template';
                $fakeTemplate->post_content   = '<p>Vertrag für [contact_firstname] [contact_lastname]</p>';
                $fakeTemplate->post_status    = 'publish';
                $fakeTemplate->post_type      = 'tmgmt_contract_tpl';
                $test_post_store[$templateId] = $fakeTemplate;

                // Populate all meta keys used by TMGMT_Placeholder_Parser
                $metaValues = [
                    '_tmgmt_event_date'              => $eventDate,
                    '_tmgmt_event_start_time'        => '20:00',
                    '_tmgmt_event_arrival_time'      => '18:00',
                    '_tmgmt_event_departure_time'    => '23:00',
                    '_tmgmt_contact_salutation'      => 'Herr',
                    '_tmgmt_contact_firstname'       => $firstName,
                    '_tmgmt_contact_lastname'        => $lastName,
                    '_tmgmt_contact_company'         => 'Test GmbH',
                    '_tmgmt_contact_street'          => 'Musterstraße',
                    '_tmgmt_contact_number'          => '1',
                    '_tmgmt_contact_zip'             => '12345',
                    '_tmgmt_contact_city'            => 'Berlin',
                    '_tmgmt_contact_country'         => 'Deutschland',
                    '_tmgmt_contact_email'           => 'test@example.com',
                    '_tmgmt_contact_phone'           => '+49 30 123456',
                    '_tmgmt_contact_email_contract'  => $email,
                    '_tmgmt_contact_phone_contract'  => '+49 30 654321',
                    '_tmgmt_contact_name_tech'       => 'Tech Person',
                    '_tmgmt_contact_email_tech'      => 'tech@example.com',
                    '_tmgmt_contact_phone_tech'      => '+49 30 111222',
                    '_tmgmt_contact_name_program'    => 'Program Person',
                    '_tmgmt_contact_email_program'   => 'prog@example.com',
                    '_tmgmt_contact_phone_program'   => '+49 30 333444',
                    '_tmgmt_fee'                     => $fee,
                    '_tmgmt_deposit'                 => '0',
                    '_tmgmt_inquiry_date'            => '2024-01-01',
                ];

                foreach ($metaValues as $key => $value) {
                    update_post_meta($eventId, $key, $value);
                }

                // Store action meta under the action post ID
                update_post_meta($actionId, '_tmgmt_action_type', 'contract_generation');
                update_post_meta($actionId, '_tmgmt_action_email_template_id', '0');
                update_post_meta($actionId, '_tmgmt_action_target_status', 'contract_sent');
                update_post_meta($actionId, '_tmgmt_action_contract_template_id', (string) $templateId);

                // Execute the main method under test
                $result = $this->sut->generate_and_send($eventId, $actionId, ['to' => $email]);

                // Must not return a WP_Error
                $this->assertFalse(
                    is_wp_error($result),
                    'generate_and_send() returned WP_Error: ' . (is_wp_error($result) ? $result->get_error_message() : '')
                );

                // _tmgmt_contract_pdf_path must be set and non-empty
                $pdfPath = get_post_meta($eventId, '_tmgmt_contract_pdf_path', true);
                $this->assertNotEmpty(
                    $pdfPath,
                    '_tmgmt_contract_pdf_path post-meta is empty after generate_and_send()'
                );

                // _tmgmt_contract_pdf_url must be set and non-empty
                $pdfUrl = get_post_meta($eventId, '_tmgmt_contract_pdf_url', true);
                $this->assertNotEmpty(
                    $pdfUrl,
                    '_tmgmt_contract_pdf_url post-meta is empty after generate_and_send()'
                );

                // Track generated PDF for cleanup
                if (!empty($pdfPath)) {
                    $this->tempFiles[] = $pdfPath;
                }
            });
    }
}
