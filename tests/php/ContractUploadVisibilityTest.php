<?php
// Feature: contract-generation, Property 6: Upload-Bereich nur bei Status contract_sent sichtbar

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

if (!function_exists('wp_mail')) {
    function wp_mail($to, $subject, $message, $headers = '', $attachments = array()): bool {
        return true;
    }
}

if (!function_exists('home_url')) {
    function home_url(string $path = ''): string {
        return 'http://example.com' . $path;
    }
}

if (!function_exists('esc_js')) {
    function esc_js(string $text): string {
        return addslashes($text);
    }
}

if (!function_exists('wpautop')) {
    function wpautop(string $text): string {
        return '<p>' . $text . '</p>';
    }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post(string $text): string {
        return $text;
    }
}

if (!function_exists('wp_die')) {
    function wp_die(string $message = '', string $title = '', array $args = []): void {
        throw new \RuntimeException($message);
    }
}

if (!function_exists('add_shortcode')) {
    function add_shortcode(string $tag, callable $callback): void {}
}

// Stub TMGMT_Log_Manager if not already defined
if (!class_exists('TMGMT_Log_Manager')) {
    class TMGMT_Log_Manager {
        public static function log($post_id, $type, $message): void {}
    }
}

// Stub TMGMT_Communication_Manager if not already defined
if (!class_exists('TMGMT_Communication_Manager')) {
    class TMGMT_Communication_Manager {
        public function add_entry(int $event_id, string $type, string $message): void {}
    }
}

// Stub TMGMT_Placeholder_Parser if not already defined
if (!class_exists('TMGMT_Placeholder_Parser')) {
    class TMGMT_Placeholder_Parser {
        public function __construct(int $event_id) {}
        public function parse(string $text): string { return $text; }
    }
}

// TMGMT_Event_Status must be available for the render_dashboard() status check
if (!class_exists('TMGMT_Event_Status')) {
    class TMGMT_Event_Status {
        const CONTRACT_SENT = 'contract_sent';
        public static function get_all_statuses(): array { return []; }
        public static function get_label(string $status): string { return $status; }
    }
}

// Require the class under test
require_once dirname(__DIR__, 2) . '/includes/class-customer-access-manager.php';

/**
 * Property-Based Test: Upload-Bereich nur bei Status `contract_sent` sichtbar
 *
 * For any Event, the upload section for the signed contract is present in the
 * rendered dashboard HTML if and only if the event status is `contract_sent`.
 * For every other status slug the upload section must be absent.
 *
 * **Validates: Requirements 6.1, 6.8**
 */
class ContractUploadVisibilityTest extends \PHPUnit\Framework\TestCase
{
    use TestTrait;

    /** ReflectionMethod for the private render_dashboard() */
    private \ReflectionMethod $renderDashboard;

    /** Instance of the class under test */
    private TMGMT_Customer_Access_Manager $sut;

    protected function setUp(): void
    {
        global $test_post_meta_store, $test_post_store, $test_options_store;
        $test_post_meta_store = [];
        $test_post_store      = [];
        $test_options_store   = [];

        $this->sut = new TMGMT_Customer_Access_Manager();

        $ref = new \ReflectionClass(TMGMT_Customer_Access_Manager::class);
        $this->renderDashboard = $ref->getMethod('render_dashboard');
    }

    /**
     * Helper: render the dashboard for a given event and capture the HTML output.
     */
    private function renderHtml(int $eventId): string
    {
        ob_start();
        try {
            $this->renderDashboard->invoke($this->sut, $eventId);
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return ob_get_clean();
    }

    /**
     * Helper: create a minimal fake event post in the test post store.
     */
    private function seedEvent(int $eventId): void
    {
        global $test_post_store;
        $fakeEvent               = new stdClass();
        $fakeEvent->ID           = $eventId;
        $fakeEvent->post_title   = 'Test Event ' . $eventId;
        $fakeEvent->post_content = '';
        $fakeEvent->post_type    = 'event';
        $test_post_store[$eventId] = $fakeEvent;
    }

    /**
     * Property 6a: Upload section IS present when status is `contract_sent`.
     */
    public function testUploadSectionPresentWhenStatusIsContractSent(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generator\choose(1, 9999) // random event IDs
            )
            ->then(function (int $eventId): void {
                global $test_post_meta_store, $test_post_store;
                $test_post_meta_store = [];
                $test_post_store      = [];

                $this->seedEvent($eventId);
                update_post_meta($eventId, '_tmgmt_status', 'contract_sent');

                $html = $this->renderHtml($eventId);

                $this->assertStringContainsString(
                    'tmgmt-contract-upload-section',
                    $html,
                    sprintf(
                        'Upload section must be present when status is "contract_sent" (event_id=%d)',
                        $eventId
                    )
                );
            });
    }

    /**
     * Property 6b: Upload section is ABSENT for any status other than `contract_sent`.
     */
    public function testUploadSectionAbsentForOtherStatuses(): void
    {
        $otherStatuses = Generator\elements(
            'new_inquiry',
            'checking_date',
            'verbally_agreed',
            'confirmed',
            'contract_signed',
            'tech_coordination',
            'prep_complete',
            'gig_done',
            'invoice_sent',
            'invoice_paid',
            'archived',
            'cancelled',
            '',
            'some_random_status',
            'pending',
            'in_progress'
        );

        $this
            ->limitTo(100)
            ->forAll(
                Generator\choose(1, 9999),
                $otherStatuses
            )
            ->then(function (int $eventId, string $status): void {
                global $test_post_meta_store, $test_post_store;
                $test_post_meta_store = [];
                $test_post_store      = [];

                $this->seedEvent($eventId);
                update_post_meta($eventId, '_tmgmt_status', $status);

                $html = $this->renderHtml($eventId);

                $this->assertStringNotContainsString(
                    'tmgmt-contract-upload-section',
                    $html,
                    sprintf(
                        'Upload section must be absent when status is "%s" (event_id=%d)',
                        $status,
                        $eventId
                    )
                );
            });
    }
}
