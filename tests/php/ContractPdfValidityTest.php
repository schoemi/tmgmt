<?php
// Feature: contract-generation, Property 3: PDF-Datei ist gültig nach Generierung

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

// Stub TMGMT_Log_Manager if not already defined (bootstrap may define it)
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
        public function add_entry(int $event_id, string $type, string $message): void {}
    }
}

// Require the classes under test
require_once dirname(__DIR__, 2) . '/includes/class-placeholder-parser.php';
require_once dirname(__DIR__, 2) . '/includes/class-pdf-generator.php';
require_once dirname(__DIR__, 2) . '/includes/class-contract-generator.php';

/**
 * Property-Based Test: PDF-Datei ist gültig nach Generierung
 *
 * For any Event with complete data, after a successful call to generate_and_send(),
 * the file at the stored _tmgmt_contract_pdf_path must exist and begin with %PDF.
 *
 * **Validates: Requirements 4.2, 4.3**
 */
class ContractPdfValidityTest extends \PHPUnit\Framework\TestCase
{
    use TestTrait;

    private TMGMT_Contract_Generator $sut;

    /** @var list<string> Temp files created during the test run, cleaned up in tearDown */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        global $test_post_meta_store, $test_post_store, $test_options_store;
        $test_post_meta_store = [];
        $test_post_store      = [];
        $test_options_store   = [];

        update_option('date_format', 'Y-m-d');
        update_option('time_format', 'H:i');

        $this->sut = new TMGMT_Contract_Generator();
    }

    protected function tearDown(): void
    {
        // Clean up any PDF files written during the test
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        $this->tempFiles = [];
    }

    /**
     * Property 3: After generate_and_send() succeeds, the PDF file exists and starts with %PDF.
     *
     * Generates random event data with a valid email address, sets up the in-memory
     * WP stores, calls generate_and_send(), then asserts:
     *   1. The call did not return a WP_Error.
     *   2. The post-meta _tmgmt_contract_pdf_path is set and non-empty.
     *   3. The file at that path exists on disk.
     *   4. The file content begins with the PDF magic bytes "%PDF".
     */
    public function testPdfFileIsValidAfterGeneration(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generator\string(),                    // event title / name
                Generator\string(),                    // event_date
                Generator\string(),                    // fee
                Generator\elements(                    // valid email (fixed domain, random local part)
                    'alice@example.com',
                    'bob@example.org',
                    'carol@test.de',
                    'dave@sample.net',
                    'eve@domain.com'
                ),
                Generator\string(),                    // contact_firstname
                Generator\string()                     // contact_lastname
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

                $eventId  = 2001;
                $actionId = 3001;

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
                $templateId                   = 4001;
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
                    // Action meta
                    '_tmgmt_action_type'             => 'contract_generation',
                    '_tmgmt_action_email_template_id' => '0',
                    '_tmgmt_action_target_status'    => 'contract_sent',
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
                $result = $this->sut->generate_and_send($eventId, $actionId);

                // 1. Must not return a WP_Error
                $this->assertFalse(
                    is_wp_error($result),
                    'generate_and_send() returned WP_Error: ' . (is_wp_error($result) ? $result->get_error_message() : '')
                );

                // 2. _tmgmt_contract_pdf_path must be set and non-empty
                $pdfPath = get_post_meta($eventId, '_tmgmt_contract_pdf_path', true);
                $this->assertNotEmpty(
                    $pdfPath,
                    '_tmgmt_contract_pdf_path post-meta is empty after generate_and_send()'
                );

                // Track for cleanup
                if (!empty($pdfPath)) {
                    $this->tempFiles[] = $pdfPath;
                }

                // 3. The file must exist on disk
                $this->assertFileExists(
                    $pdfPath,
                    "PDF file does not exist at stored path: {$pdfPath}"
                );

                // 4. The file must begin with the PDF magic bytes
                $header = file_get_contents($pdfPath, false, null, 0, 4);
                $this->assertSame(
                    '%PDF',
                    $header,
                    "PDF file at {$pdfPath} does not begin with %PDF magic bytes"
                );
            });
    }
}
