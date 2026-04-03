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

if (!function_exists('home_url')) {
    function home_url(string $path = ''): string {
        return 'http://example.com' . $path;
    }
}

// WordPress constant used by $wpdb->get_row()
if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

// Stub TMGMT_Customer_Access_Manager if not already defined
if (!class_exists('TMGMT_Customer_Access_Manager')) {
    class TMGMT_Customer_Access_Manager {
        public function get_valid_token(int $event_id): ?object {
            // Return a fake token row so [customer_dashboard_link] resolves cleanly
            return (object) ['token' => 'test-token-' . $event_id];
        }
    }
}

// Require the classes under test
require_once dirname(__DIR__, 2) . '/includes/class-placeholder-parser.php';
require_once dirname(__DIR__, 2) . '/includes/class-contract-generator.php';

/**
 * Property-Based Test: Keine unersetzten Platzhalter nach dem Rendering
 *
 * For any Event with complete contact data and any random subset of known
 * placeholders embedded in the template post_content, the HTML returned by
 * render_template() must not contain any of the known placeholder keys from
 * TMGMT_Placeholder_Parser::get_placeholders().
 *
 * **Validates: Requirements 1.5, 4.3, 5.3, 7.2**
 */
class ContractPlaceholderPropertyTest extends \PHPUnit\Framework\TestCase
{
    use TestTrait;

    private TMGMT_Contract_Generator $sut;

    /** @var list<string> All known placeholder keys, e.g. '[event_date]' */
    private array $allPlaceholderKeys;

    protected function setUp(): void
    {
        global $test_post_meta_store, $test_post_store, $test_options_store;
        $test_post_meta_store = [];
        $test_post_store      = [];
        $test_options_store   = [];

        update_option('date_format', 'Y-m-d');
        update_option('time_format', 'H:i');

        $this->sut = new TMGMT_Contract_Generator();

        // Exclude [customer_dashboard_link] from the random subset because it
        // resolves to a full <a> tag (not a plain string) and is handled
        // separately via TMGMT_Customer_Access_Manager. It is still covered by
        // the meta-values setup below.
        $allKeys = array_keys(TMGMT_Placeholder_Parser::get_placeholders());
        $this->allPlaceholderKeys = array_values(
            array_filter($allKeys, fn($k) => $k !== '[customer_dashboard_link]')
        );
    }

    /**
     * Property 1: No unreplaced placeholders remain after rendering.
     *
     * Uses Generator\elements() to pick a random subset of known placeholder
     * keys and embeds them into the template post_content. After calling
     * render_template(), none of the known placeholder keys from
     * get_placeholders() may appear in the output HTML.
     */
    public function testNoUnreplacedPlaceholdersAfterRendering(): void
    {
        $placeholderKeys = $this->allPlaceholderKeys;

        $this
            ->limitTo(100)
            ->forAll(
                // Random subset of placeholder keys to embed in the template
                Generator\seq(Generator\elements(...$placeholderKeys)),
                Generator\string(),  // event title
                Generator\string(),  // event_date (raw – may not be a real date)
                Generator\string(),  // fee
                Generator\string(),  // contact_firstname
                Generator\string()   // contact_lastname
            )
            ->then(function (
                array  $selectedPlaceholders,
                string $title,
                string $eventDate,
                string $fee,
                string $firstName,
                string $lastName
            ) use ($placeholderKeys): void {
                global $test_post_meta_store, $test_post_store;
                $test_post_meta_store = [];
                $test_post_store      = [];

                $eventId    = 1001;
                $templateId = 2001;

                // Build template content from the randomly selected placeholder subset
                $templateContent = '<p>' . implode(' ', $selectedPlaceholders) . '</p>';

                // Create a fake event post
                $fakePost               = new stdClass();
                $fakePost->ID           = $eventId;
                $fakePost->post_title   = $title;
                $fakePost->post_content = '';
                $fakePost->post_type    = 'tmgmt_event';
                $test_post_store[$eventId] = $fakePost;

                // Create a published tmgmt_contract_tpl post with random placeholder content
                $fakeTemplate               = new stdClass();
                $fakeTemplate->ID           = $templateId;
                $fakeTemplate->post_title   = 'Test Template';
                $fakeTemplate->post_content = $templateContent;
                $fakeTemplate->post_status  = 'publish';
                $fakeTemplate->post_type    = 'tmgmt_contract_tpl';
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

                $result = $this->sut->render_template($eventId, $templateId);

                // render_template() must succeed (not return WP_Error)
                $this->assertFalse(
                    is_wp_error($result),
                    'render_template() returned WP_Error: '
                        . (is_wp_error($result) ? $result->get_error_message() : '')
                );

                $this->assertIsString($result);

                // Assert that none of the known TMGMT placeholder tokens remain in the output.
                // Checking each registered placeholder individually avoids false positives from
                // random input strings that happen to contain [word] patterns.
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
