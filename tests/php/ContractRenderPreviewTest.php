<?php
// Feature: contract-send-dialog, Task 1.1: render_preview() unit tests

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
 * Unit tests for TMGMT_Contract_Generator::render_preview()
 *
 * **Validates: Requirements 3.3, 7.1**
 */
class ContractRenderPreviewTest extends \PHPUnit\Framework\TestCase
{
    private TMGMT_Contract_Generator $sut;
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
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        $this->tempFiles = [];
    }

    private function createEventAndTemplate(int $eventId, int $templateId, string $templateContent = '<p>Vertrag für [contact_firstname]</p>'): void
    {
        global $test_post_store;

        $fakeEvent               = new stdClass();
        $fakeEvent->ID           = $eventId;
        $fakeEvent->post_title   = 'Test Event';
        $fakeEvent->post_content = '';
        $fakeEvent->post_type    = 'tmgmt_event';
        $test_post_store[$eventId] = $fakeEvent;

        $fakeTemplate               = new stdClass();
        $fakeTemplate->ID           = $templateId;
        $fakeTemplate->post_title   = 'Test Template';
        $fakeTemplate->post_content = $templateContent;
        $fakeTemplate->post_status  = 'publish';
        $fakeTemplate->post_type    = 'tmgmt_contract_tpl';
        $test_post_store[$templateId] = $fakeTemplate;

        $metaValues = [
            '_tmgmt_event_date'              => '2024-06-15',
            '_tmgmt_event_start_time'        => '20:00',
            '_tmgmt_event_arrival_time'      => '18:00',
            '_tmgmt_event_departure_time'    => '23:00',
            '_tmgmt_contact_salutation'      => 'Herr',
            '_tmgmt_contact_firstname'       => 'Max',
            '_tmgmt_contact_lastname'        => 'Mustermann',
            '_tmgmt_contact_company'         => 'Test GmbH',
            '_tmgmt_contact_street'          => 'Musterstraße',
            '_tmgmt_contact_number'          => '1',
            '_tmgmt_contact_zip'             => '12345',
            '_tmgmt_contact_city'            => 'Berlin',
            '_tmgmt_contact_country'         => 'Deutschland',
            '_tmgmt_contact_email'           => 'test@example.com',
            '_tmgmt_contact_phone'           => '+49 30 123456',
            '_tmgmt_contact_email_contract'  => 'contract@example.com',
            '_tmgmt_contact_phone_contract'  => '+49 30 654321',
            '_tmgmt_contact_name_tech'       => 'Tech Person',
            '_tmgmt_contact_email_tech'      => 'tech@example.com',
            '_tmgmt_contact_phone_tech'      => '+49 30 111222',
            '_tmgmt_contact_name_program'    => 'Program Person',
            '_tmgmt_contact_email_program'   => 'prog@example.com',
            '_tmgmt_contact_phone_program'   => '+49 30 333444',
            '_tmgmt_fee'                     => '1000',
            '_tmgmt_deposit'                 => '500',
            '_tmgmt_inquiry_date'            => '2024-01-01',
        ];

        foreach ($metaValues as $key => $value) {
            update_post_meta($eventId, $key, $value);
        }
    }

    /**
     * Test: render_preview() returns array with pdf_url and html keys on success.
     */
    public function testRenderPreviewReturnsArrayWithExpectedKeys(): void
    {
        $eventId    = 5001;
        $templateId = 6001;
        $this->createEventAndTemplate($eventId, $templateId);

        $result = $this->sut->render_preview($eventId, $templateId);

        $this->assertFalse(
            is_wp_error($result),
            'render_preview() returned WP_Error: ' . (is_wp_error($result) ? $result->get_error_message() : '')
        );
        $this->assertIsArray($result);
        $this->assertArrayHasKey('pdf_url', $result);
        $this->assertArrayHasKey('html', $result);
    }

    /**
     * Test: render_preview() returns a valid PDF URL containing the event ID and 'preview'.
     */
    public function testRenderPreviewPdfUrlContainsPreviewAndEventId(): void
    {
        $eventId    = 5002;
        $templateId = 6002;
        $this->createEventAndTemplate($eventId, $templateId);

        $result = $this->sut->render_preview($eventId, $templateId);

        $this->assertFalse(is_wp_error($result));
        $this->assertStringContainsString('tmgmt-contracts/' . $eventId . '/', $result['pdf_url']);
        $this->assertStringContainsString('-preview-', $result['pdf_url']);
        $this->assertStringEndsWith('.pdf', $result['pdf_url']);

        // Track the actual file for cleanup
        $upload   = wp_upload_dir();
        $dir_path = trailingslashit($upload['basedir']) . 'tmgmt-contracts/' . $eventId . '/';
        $filename = basename($result['pdf_url']);
        $this->tempFiles[] = $dir_path . $filename;
    }

    /**
     * Test: render_preview() html output has placeholders replaced (not raw tokens).
     */
    public function testRenderPreviewHtmlHasPlaceholdersReplaced(): void
    {
        $eventId    = 5003;
        $templateId = 6003;
        $this->createEventAndTemplate($eventId, $templateId, '<p>Vertrag für [contact_firstname] [contact_lastname]</p>');

        $result = $this->sut->render_preview($eventId, $templateId);

        $this->assertFalse(is_wp_error($result));
        // Placeholders should be replaced (even if to empty when no Veranstalter chain is set up)
        $this->assertStringNotContainsString('[contact_firstname]', $result['html']);
        $this->assertStringNotContainsString('[contact_lastname]', $result['html']);
        $this->assertStringContainsString('Vertrag für', $result['html']);

        // Cleanup
        $upload   = wp_upload_dir();
        $dir_path = trailingslashit($upload['basedir']) . 'tmgmt-contracts/' . $eventId . '/';
        $filename = basename($result['pdf_url']);
        $this->tempFiles[] = $dir_path . $filename;
    }

    /**
     * Test: render_preview() does NOT persist any post-meta (no WP attachment).
     */
    public function testRenderPreviewDoesNotPersistPostMeta(): void
    {
        $eventId    = 5004;
        $templateId = 6004;
        $this->createEventAndTemplate($eventId, $templateId);

        $result = $this->sut->render_preview($eventId, $templateId);

        $this->assertFalse(is_wp_error($result));

        // Ensure no contract PDF meta was saved
        $pdfPath = get_post_meta($eventId, '_tmgmt_contract_pdf_path', true);
        $pdfUrl  = get_post_meta($eventId, '_tmgmt_contract_pdf_url', true);
        $this->assertEmpty($pdfPath, 'render_preview() should not persist _tmgmt_contract_pdf_path');
        $this->assertEmpty($pdfUrl, 'render_preview() should not persist _tmgmt_contract_pdf_url');

        // Cleanup
        $upload   = wp_upload_dir();
        $dir_path = trailingslashit($upload['basedir']) . 'tmgmt-contracts/' . $eventId . '/';
        $filename = basename($result['pdf_url']);
        $this->tempFiles[] = $dir_path . $filename;
    }

    /**
     * Test: render_preview() returns WP_Error when template is missing.
     */
    public function testRenderPreviewReturnsErrorForMissingTemplate(): void
    {
        global $test_post_store;

        $eventId    = 5005;
        $templateId = 9999; // non-existent

        $fakeEvent               = new stdClass();
        $fakeEvent->ID           = $eventId;
        $fakeEvent->post_title   = 'Test Event';
        $fakeEvent->post_content = '';
        $fakeEvent->post_type    = 'tmgmt_event';
        $test_post_store[$eventId] = $fakeEvent;

        $result = $this->sut->render_preview($eventId, $templateId);

        $this->assertTrue(is_wp_error($result));
        $this->assertSame('template_missing', $result->get_error_code());
    }

    /**
     * Test: render_preview() returns WP_Error when template content is empty.
     */
    public function testRenderPreviewReturnsErrorForEmptyTemplate(): void
    {
        $eventId    = 5006;
        $templateId = 6006;
        $this->createEventAndTemplate($eventId, $templateId, '');

        $result = $this->sut->render_preview($eventId, $templateId);

        $this->assertTrue(is_wp_error($result));
        $this->assertSame('empty_template_content', $result->get_error_code());
    }

    /**
     * Test: The generated PDF file actually exists on disk and starts with %PDF.
     */
    public function testRenderPreviewCreatesPdfFileOnDisk(): void
    {
        $eventId    = 5007;
        $templateId = 6007;
        $this->createEventAndTemplate($eventId, $templateId);

        $result = $this->sut->render_preview($eventId, $templateId);

        $this->assertFalse(is_wp_error($result));

        $upload   = wp_upload_dir();
        $dir_path = trailingslashit($upload['basedir']) . 'tmgmt-contracts/' . $eventId . '/';
        $filename = basename($result['pdf_url']);
        $filePath = $dir_path . $filename;

        $this->tempFiles[] = $filePath;

        $this->assertFileExists($filePath, 'Preview PDF file should exist on disk');

        $header = file_get_contents($filePath, false, null, 0, 4);
        $this->assertSame('%PDF', $header, 'Preview PDF should start with %PDF magic bytes');
    }
}
