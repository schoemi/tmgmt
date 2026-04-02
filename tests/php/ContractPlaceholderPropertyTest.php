<?php
// Feature: contract-generation, Property 1: Keine unersetzten Platzhalter nach dem Rendering

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

if (!function_exists('time_format')) {
    // get_option('time_format') is handled by the existing get_option stub
}

// Require the classes under test
require_once dirname(__DIR__, 2) . '/includes/class-placeholder-parser.php';
require_once dirname(__DIR__, 2) . '/includes/class-contract-generator.php';

/**
 * Property-Based Test: Keine unersetzten Platzhalter nach dem Rendering
 *
 * For any Event with complete contact data, the HTML returned by render_template()
 * must not contain any unreplaced [placeholder] pattern.
 *
 * **Validates: Requirements 1.2, 7.2, 4.1**
 */
class ContractPlaceholderPropertyTest extends \PHPUnit\Framework\TestCase
{
    use TestTrait;

    private TMGMT_Contract_Generator $sut;

    protected function setUp(): void
    {
        global $test_post_meta_store, $test_post_store, $test_options_store;
        $test_post_meta_store = [];
        $test_post_store      = [];
        $test_options_store   = [];

        // Provide date_format / time_format options so the template footer renders cleanly
        update_option('date_format', 'Y-m-d');
        update_option('time_format', 'H:i');

        $this->sut = new TMGMT_Contract_Generator();
    }

    /**
     * Property 1: No unreplaced placeholders remain after rendering.
     *
     * Generates random strings for all relevant event meta fields, sets them up
     * in the in-memory post/meta stores, calls render_template(), and asserts
     * that the resulting HTML contains no [word] pattern.
     */
    public function testNoUnreplacedPlaceholdersAfterRendering(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generator\string(),  // event title / name
                Generator\string(),  // event_date (raw string – may not be a real date, that's fine)
                Generator\string(),  // fee
                Generator\string(),  // contact_firstname
                Generator\string(),  // contact_lastname
                Generator\string(),  // contact_email_contract
                Generator\string()   // deposit
            )
            ->then(function (
                string $title,
                string $eventDate,
                string $fee,
                string $firstName,
                string $lastName,
                string $emailContract,
                string $deposit
            ): void {
                global $test_post_meta_store, $test_post_store;
                $test_post_meta_store = [];
                $test_post_store      = [];

                $eventId = 1001;

                // Create a fake WP post object in the store
                $fakePost              = new stdClass();
                $fakePost->ID          = $eventId;
                $fakePost->post_title  = $title;
                $fakePost->post_content = '';
                $fakePost->post_type   = 'tmgmt_event';
                $test_post_store[$eventId] = $fakePost;

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
                    '_tmgmt_contact_email_contract'  => $emailContract,
                    '_tmgmt_contact_phone_contract'  => '+49 30 654321',
                    '_tmgmt_contact_name_tech'       => 'Tech Person',
                    '_tmgmt_contact_email_tech'      => 'tech@example.com',
                    '_tmgmt_contact_phone_tech'      => '+49 30 111222',
                    '_tmgmt_contact_name_program'    => 'Program Person',
                    '_tmgmt_contact_email_program'   => 'prog@example.com',
                    '_tmgmt_contact_phone_program'   => '+49 30 333444',
                    '_tmgmt_fee'                     => $fee,
                    '_tmgmt_deposit'                 => $deposit,
                    '_tmgmt_inquiry_date'            => '2024-01-01',
                ];

                foreach ($metaValues as $key => $value) {
                    update_post_meta($eventId, $key, $value);
                }

                $result = $this->sut->render_template($eventId);

                // render_template must succeed (not return WP_Error)
                $this->assertFalse(
                    is_wp_error($result),
                    'render_template() returned WP_Error: ' . (is_wp_error($result) ? $result->get_error_message() : '')
                );

                $this->assertIsString($result);

                // Assert that none of the known TMGMT placeholder tokens remain in the output.
                // We check each registered placeholder individually so that random input strings
                // that happen to contain [word] patterns do not cause false positives.
                $knownPlaceholders = array_keys(TMGMT_Placeholder_Parser::get_placeholders());
                foreach ($knownPlaceholders as $placeholder) {
                    $this->assertStringNotContainsString(
                        $placeholder,
                        $result,
                        "render_template() left unreplaced placeholder '{$placeholder}' in the output HTML"
                    );
                }
            });
    }
}
