<?php
// Feature: contract-send-dialog, Property 4: render_template() ist deterministisch
// Two calls with identical event_id + template_id produce equivalent HTML

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
require_once dirname(__DIR__, 2) . '/includes/class-contract-generator.php';

/**
 * Property-Based Test: render_template() Determinismus
 *
 * For any randomly generated event_id and template_id pair, calling
 * render_template() twice with identical inputs must produce identical HTML.
 *
 * **Validates: Requirements 7.1, 7.3**
 */
class ContractRenderTemplateDeterminismPropertyTest extends \PHPUnit\Framework\TestCase
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
     * Helper: set up event post, template post, and all meta values for a given pair.
     */
    private function createEventAndTemplate(
        int $eventId,
        int $templateId,
        string $templateContent,
        string $firstName,
        string $lastName,
        string $fee
    ): void {
        global $test_post_store;

        $event               = new stdClass();
        $event->ID           = $eventId;
        $event->post_title   = 'Event ' . $eventId;
        $event->post_content = '';
        $event->post_type    = 'tmgmt_event';
        $test_post_store[$eventId] = $event;

        $tpl               = new stdClass();
        $tpl->ID           = $templateId;
        $tpl->post_title   = 'Template ' . $templateId;
        $tpl->post_content = $templateContent;
        $tpl->post_status  = 'publish';
        $tpl->post_type    = 'tmgmt_contract_tpl';
        $test_post_store[$templateId] = $tpl;

        $metaValues = [
            '_tmgmt_event_date'              => '2024-06-15',
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
            '_tmgmt_contact_email_contract'  => 'contract@example.com',
            '_tmgmt_contact_phone_contract'  => '+49 30 654321',
            '_tmgmt_contact_name_tech'       => 'Tech Person',
            '_tmgmt_contact_email_tech'      => 'tech@example.com',
            '_tmgmt_contact_phone_tech'      => '+49 30 111222',
            '_tmgmt_contact_name_program'    => 'Program Person',
            '_tmgmt_contact_email_program'   => 'prog@example.com',
            '_tmgmt_contact_phone_program'   => '+49 30 333444',
            '_tmgmt_fee'                     => $fee,
            '_tmgmt_deposit'                 => '500',
            '_tmgmt_inquiry_date'            => '2024-01-01',
        ];

        foreach ($metaValues as $key => $value) {
            update_post_meta($eventId, $key, $value);
        }
    }

    /**
     * Property 4: render_template() ist deterministisch.
     *
     * For any random event_id + template_id pair with random contact data,
     * two consecutive calls to render_template() produce identical HTML output.
     */
    public function testRenderTemplateIsDeterministic(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generator\choose(1, 99999),   // event_id
                Generator\choose(100000, 199999), // template_id (non-overlapping range)
                Generator\elements(
                    '<p>Vertrag für [contact_firstname] [contact_lastname]</p>',
                    '<h1>[contact_company]</h1><p>Datum: [event_date]</p>',
                    '<p>Honorar: [fee] EUR, Anzahlung: [deposit] EUR</p>',
                    '<div>[contact_salutation] [contact_firstname] [contact_lastname], [contact_city]</div>',
                    '<p>Ankunft: [arrival_time], Start: [start_time], Abreise: [departure_time]</p>'
                ),
                Generator\string(),  // contact_firstname
                Generator\string(),  // contact_lastname
                Generator\string()   // fee
            )
            ->then(function (
                int    $eventId,
                int    $templateId,
                string $templateContent,
                string $firstName,
                string $lastName,
                string $fee
            ): void {
                global $test_post_meta_store, $test_post_store;
                $test_post_meta_store = [];
                $test_post_store      = [];

                $this->createEventAndTemplate(
                    $eventId,
                    $templateId,
                    $templateContent,
                    $firstName,
                    $lastName,
                    $fee
                );

                // Call render_template() twice with identical inputs
                $result1 = $this->sut->render_template($eventId, $templateId);
                $result2 = $this->sut->render_template($eventId, $templateId);

                // Both calls must succeed
                $this->assertFalse(
                    is_wp_error($result1),
                    'First render_template() returned WP_Error: '
                        . (is_wp_error($result1) ? $result1->get_error_message() : '')
                );
                $this->assertFalse(
                    is_wp_error($result2),
                    'Second render_template() returned WP_Error: '
                        . (is_wp_error($result2) ? $result2->get_error_message() : '')
                );

                $this->assertIsString($result1);
                $this->assertIsString($result2);

                // Determinism: identical inputs must produce identical output
                $this->assertSame(
                    $result1,
                    $result2,
                    sprintf(
                        'render_template(%d, %d) is not deterministic: two calls with identical inputs returned different HTML',
                        $eventId,
                        $templateId
                    )
                );
            });
    }
}
