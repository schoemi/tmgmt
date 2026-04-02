<?php
// Feature: contract-generation, Property 2: Idempotenz des Template-Renderings

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
 * Property-Based Test: Idempotenz des Template-Renderings
 *
 * For any Event, calling render_template() twice with the same event data
 * must return identical strings both times.
 *
 * **Validates: Requirements 7.1**
 */
class ContractRenderIdempotencyTest extends \PHPUnit\Framework\TestCase
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
     * Property 2: Idempotency of template rendering.
     *
     * Generates random strings for all relevant event meta fields, sets them up
     * in the in-memory post/meta stores, calls render_template() twice with the
     * same event data, and asserts both return values are identical strings.
     */
    public function testRenderTemplateIsIdempotent(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generator\string(),  // event title / name
                Generator\string(),  // event_date
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
                $fakePost               = new stdClass();
                $fakePost->ID           = $eventId;
                $fakePost->post_title   = $title;
                $fakePost->post_content = '';
                $fakePost->post_type    = 'tmgmt_event';
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

                // Call render_template() twice with the same event data
                $result1 = $this->sut->render_template($eventId);
                $result2 = $this->sut->render_template($eventId);

                // Both calls must succeed
                $this->assertFalse(
                    is_wp_error($result1),
                    'First render_template() call returned WP_Error: ' . (is_wp_error($result1) ? $result1->get_error_message() : '')
                );
                $this->assertFalse(
                    is_wp_error($result2),
                    'Second render_template() call returned WP_Error: ' . (is_wp_error($result2) ? $result2->get_error_message() : '')
                );

                $this->assertIsString($result1);
                $this->assertIsString($result2);

                // Both calls must return identical output (idempotency)
                $this->assertSame(
                    $result1,
                    $result2,
                    'render_template() is not idempotent: two calls with identical event data returned different HTML'
                );
            });
    }
}
