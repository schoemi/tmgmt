<?php
// Feature: contract-generation, Property 13: Unterschrift-Overlay im gerenderten HTML

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

// Require the classes under test
require_once dirname(__DIR__, 2) . '/includes/class-placeholder-parser.php';
require_once dirname(__DIR__, 2) . '/includes/class-contract-generator.php';

/**
 * Property-Based Test: Unterschrift-Overlay im gerenderten HTML
 *
 * For any Event with tmgmt_contract_signature_id set to a positive integer,
 * the HTML returned by render_template() must contain an <img> tag with the
 * URL returned by wp_get_attachment_url() for that attachment ID.
 *
 * **Validates: Requirements 1.9, 4.4**
 */
class ContractSignatureOverlayTest extends \PHPUnit\Framework\TestCase
{
    use TestTrait;

    private TMGMT_Contract_Generator $sut;

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

    /**
     * Property 13: Signature overlay is present in rendered HTML.
     *
     * For any random positive attachment ID, when tmgmt_contract_signature_id
     * is set to that ID, render_template() must return HTML containing an
     * <img> tag with the URL that wp_get_attachment_url() returns for that ID.
     *
     * The bootstrap stub for wp_get_attachment_url() returns a fixed URL
     * ('http://example.com/wp-content/uploads/test-file.pdf') regardless of
     * the attachment ID, so we assert against that fixed URL.
     */
    public function testSignatureOverlayPresentInRenderedHtml(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generator\pos()  // random positive attachment ID
            )
            ->then(function (int $attachmentId): void {
                global $test_post_meta_store, $test_post_store, $test_options_store;
                $test_post_meta_store = [];
                $test_post_store      = [];
                $test_options_store   = [];

                update_option('date_format', 'Y-m-d');
                update_option('time_format', 'H:i');

                // Configure the signature attachment ID
                update_option('tmgmt_contract_signature_id', $attachmentId);

                $eventId    = 1001;
                $templateId = 2001;

                // Create a fake event post
                $fakePost             = new stdClass();
                $fakePost->ID         = $eventId;
                $fakePost->post_title = 'Test Event';
                $fakePost->post_type  = 'tmgmt_event';
                $test_post_store[$eventId] = $fakePost;

                // Create a published contract template post with simple content
                $fakeTemplate               = new stdClass();
                $fakeTemplate->ID           = $templateId;
                $fakeTemplate->post_title   = 'Test Template';
                $fakeTemplate->post_content = '<p>Vertrag</p>';
                $fakeTemplate->post_status  = 'publish';
                $fakeTemplate->post_type    = 'tmgmt_contract_tpl';
                $test_post_store[$templateId] = $fakeTemplate;

                // Populate required event meta
                $metaValues = [
                    '_tmgmt_event_date'             => '2025-06-01',
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
                    '_tmgmt_contact_email_contract' => 'contract@example.com',
                    '_tmgmt_contact_phone_contract' => '+49 30 654321',
                    '_tmgmt_contact_name_tech'      => 'Tech Person',
                    '_tmgmt_contact_email_tech'     => 'tech@example.com',
                    '_tmgmt_contact_phone_tech'     => '+49 30 111222',
                    '_tmgmt_contact_name_program'   => 'Program Person',
                    '_tmgmt_contact_email_program'  => 'prog@example.com',
                    '_tmgmt_contact_phone_program'  => '+49 30 333444',
                    '_tmgmt_fee'                    => '1500',
                    '_tmgmt_deposit'                => '500',
                    '_tmgmt_inquiry_date'           => '2024-01-01',
                ];

                foreach ($metaValues as $key => $value) {
                    update_post_meta($eventId, $key, $value);
                }

                $result = $this->sut->render_template($eventId, $templateId);

                // render_template() must succeed
                $this->assertFalse(
                    is_wp_error($result),
                    'render_template() returned WP_Error: '
                        . (is_wp_error($result) ? $result->get_error_message() : '')
                );

                $this->assertIsString($result);

                // The bootstrap stub returns this fixed URL for any attachment ID
                $expectedUrl = 'http://example.com/wp-content/uploads/test-file.pdf';

                // The rendered HTML must contain an <img> tag
                $this->assertStringContainsString(
                    '<img',
                    $result,
                    'render_template() output does not contain an <img> tag for the signature overlay'
                );

                // The rendered HTML must contain the signature URL
                $this->assertStringContainsString(
                    $expectedUrl,
                    $result,
                    sprintf(
                        'render_template() output does not contain the signature URL "%s" for attachment ID %d',
                        $expectedUrl,
                        $attachmentId
                    )
                );
            });
    }
}
