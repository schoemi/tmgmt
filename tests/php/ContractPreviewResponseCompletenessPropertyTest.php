<?php
// Feature: contract-send-dialog, Property 1: Preview-Endpunkt gibt vollständige E-Mail-Felder zurück
// For any Event mit konfigurierter Aktion: Response enthält alle Pflichtfelder

use Eris\Generator;
use Eris\TestTrait;

if (!defined('TMGMT_PLUGIN_DIR')) {
    define('TMGMT_PLUGIN_DIR', dirname(__DIR__, 2) . '/');
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        private string $code;
        private string $message;
        private array $data;
        public function __construct(string $code = '', string $message = '', $data = array()) {
            $this->code    = $code;
            $this->message = $message;
            $this->data    = is_array($data) ? $data : array();
        }
        public function get_error_code(): string    { return $this->code; }
        public function get_error_message(): string { return $this->message; }
        public function get_error_data($code = '')  { return $this->data; }
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

if (!function_exists('wp_kses_post')) {
    function wp_kses_post($data) {
        return $data;
    }
}

if (!function_exists('rest_ensure_response')) {
    function rest_ensure_response($data) {
        return $data;
    }
}

if (!function_exists('register_rest_route')) {
    function register_rest_route($namespace, $route, $args = array()) {}
}

require_once dirname(__DIR__, 2) . '/includes/class-placeholder-parser.php';
require_once dirname(__DIR__, 2) . '/includes/class-pdf-generator.php';
require_once dirname(__DIR__, 2) . '/includes/class-contract-generator.php';
require_once dirname(__DIR__, 2) . '/includes/class-rest-api.php';


/**
 * Minimal WP_REST_Request stub for unit testing.
 */
if (!class_exists('FakeRestRequest')) {
    class FakeRestRequest implements ArrayAccess {
        private array $params = array();
        private array $url_params = array();

        public function __construct(array $url_params = array(), array $query_params = array()) {
            $this->url_params = $url_params;
            $this->params     = $query_params;
        }

        public function get_param(string $key) {
            return $this->params[$key] ?? $this->url_params[$key] ?? null;
        }

        public function get_json_params(): array { return $this->params; }
        public function get_body_params(): array { return $this->params; }

        public function offsetExists($offset): bool  { return isset($this->url_params[$offset]); }
        public function offsetGet($offset): mixed     { return $this->url_params[$offset] ?? null; }
        public function offsetSet($offset, $value): void { $this->url_params[$offset] = $value; }
        public function offsetUnset($offset): void    { unset($this->url_params[$offset]); }
    }
}

/**
 * Testable subclass that stubs out the contract generator dependency.
 */
if (!class_exists('Testable_REST_API')) {
    class Testable_REST_API extends TMGMT_REST_API {
        public ?TMGMT_Contract_Generator $stubGenerator = null;

        protected function make_contract_generator() {
            return $this->stubGenerator ?? new TMGMT_Contract_Generator();
        }
    }
}

/**
 * Contract generator stub that returns a fixed preview result.
 */
if (!class_exists('StubContractGenerator')) {
    class StubContractGenerator extends TMGMT_Contract_Generator {
        public function render_preview(int $event_id, int $template_id): array|WP_Error {
            return array(
                'pdf_url' => 'http://example.com/wp-content/uploads/tmgmt-contracts/' . $event_id . '/contract-' . $event_id . '-preview-' . time() . '.pdf',
                'html'    => '<p>Preview HTML</p>',
            );
        }
    }
}

/**
 * Property-Based Test: Preview-Endpunkt gibt vollständige E-Mail-Felder zurück
 *
 * For any event with a configured contract_generation action and email template,
 * the preview response must contain all required fields: to, subject, body,
 * attachments, pdf_url, and templates.
 *
 * **Validates: Requirements 2.1, 2.3, 3.2, 3.3, 5.1, 5.3**
 */
class ContractPreviewResponseCompletenessPropertyTest extends \PHPUnit\Framework\TestCase
{
    use TestTrait;

    private Testable_REST_API $api;
    private StubContractGenerator $stubGen;

    protected function setUp(): void
    {
        global $test_post_meta_store, $test_post_store, $test_options_store;
        $test_post_meta_store = array();
        $test_post_store      = array();
        $test_options_store   = array();

        update_option('date_format', 'Y-m-d');
        update_option('time_format', 'H:i');

        $this->stubGen = new StubContractGenerator();
        $this->api     = new Testable_REST_API();
        $this->api->stubGenerator = $this->stubGen;
    }

    /**
     * Set up the full event/action/email-template/contact chain for a single iteration.
     */
    private function setUpScenario(
        int    $eventId,
        int    $actionId,
        int    $emailTemplateId,
        int    $contractTemplateId,
        string $contactEmail,
        string $emailSubject,
        string $emailBody
    ): void {
        global $test_post_store;

        // Event post
        $event               = new \stdClass();
        $event->ID           = $eventId;
        $event->post_title   = 'Event ' . $eventId;
        $event->post_content = '';
        $event->post_type    = 'event';
        $event->post_status  = 'publish';
        $test_post_store[$eventId] = $event;

        // Action post
        $action               = new \stdClass();
        $action->ID           = $actionId;
        $action->post_title   = 'Action ' . $actionId;
        $action->post_content = '';
        $action->post_type    = 'tmgmt_action';
        $action->post_status  = 'publish';
        $test_post_store[$actionId] = $action;

        update_post_meta($actionId, '_tmgmt_action_type', 'contract_generation');
        update_post_meta($actionId, '_tmgmt_action_email_template_id', $emailTemplateId);
        update_post_meta($actionId, '_tmgmt_action_contract_template_id', $contractTemplateId);

        // Email template post
        $emailTpl               = new \stdClass();
        $emailTpl->ID           = $emailTemplateId;
        $emailTpl->post_title   = 'Email Template ' . $emailTemplateId;
        $emailTpl->post_content = '';
        $emailTpl->post_type    = 'tmgmt_email_template';
        $emailTpl->post_status  = 'publish';
        $test_post_store[$emailTemplateId] = $emailTpl;

        update_post_meta($emailTemplateId, '_tmgmt_email_subject', $emailSubject);
        update_post_meta($emailTemplateId, '_tmgmt_email_body', $emailBody);

        // Contract template post (also used by get_posts for templates list)
        $contractTpl               = new \stdClass();
        $contractTpl->ID           = $contractTemplateId;
        $contractTpl->post_title   = 'Vertrag ' . $contractTemplateId;
        $contractTpl->post_content = '<p>Vertrag Content</p>';
        $contractTpl->post_type    = 'tmgmt_contract_tpl';
        $contractTpl->post_status  = 'publish';
        $test_post_store[$contractTemplateId] = $contractTpl;

        // Contact chain: Event → Veranstalter → Contact (role: vertrag)
        $contactId = $eventId + 90000;
        $contact               = new \stdClass();
        $contact->ID           = $contactId;
        $contact->post_title   = 'Kontakt ' . $contactId;
        $contact->post_content = '';
        $contact->post_type    = 'tmgmt_contact';
        $contact->post_status  = 'publish';
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
        $veranstalter->post_status  = 'publish';
        $test_post_store[$veranstalterId] = $veranstalter;

        update_post_meta($veranstalterId, '_tmgmt_veranstalter_contacts', array(
            array('role' => 'vertrag', 'contact_id' => $contactId),
        ));

        update_post_meta($eventId, '_tmgmt_event_veranstalter_id', $veranstalterId);

        // Event meta values required by placeholder parser
        $metaValues = array(
            '_tmgmt_event_date'           => '2024-06-15',
            '_tmgmt_event_start_time'     => '20:00',
            '_tmgmt_event_arrival_time'   => '18:00',
            '_tmgmt_event_departure_time' => '23:00',
            '_tmgmt_fee'                  => '1000',
            '_tmgmt_deposit'              => '500',
            '_tmgmt_inquiry_date'         => '2024-01-01',
        );
        foreach ($metaValues as $key => $value) {
            update_post_meta($eventId, $key, $value);
        }
    }

    /**
     * Property 1: Preview-Endpunkt gibt vollständige E-Mail-Felder zurück.
     *
     * For any randomly generated event/action/email-template combination,
     * the preview response must contain all required keys: to, subject, body,
     * attachments, pdf_url, and templates — with non-empty to and subject.
     */
    public function testPreviewResponseContainsAllRequiredFields(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                // Random event IDs (avoid collisions with derived IDs)
                Generator\choose(1, 500),
                // Random action ID offset
                Generator\choose(1000, 2000),
                // Random email template ID offset
                Generator\choose(3000, 4000),
                // Random contract template ID offset
                Generator\choose(5000, 6000),
                // Random contact email
                Generator\elements(
                    'alice@example.com',
                    'bob@example.org',
                    'carol@test.de',
                    'dave@sample.net',
                    'eve@domain.com',
                    'frank@musik.de',
                    'greta@konzert.at',
                    'hans@booking.ch'
                ),
                // Random email subject
                Generator\map(
                    function (string $s): string {
                        return 'Betreff: ' . $s;
                    },
                    Generator\string()
                ),
                // Random email body
                Generator\map(
                    function (string $s): string {
                        return '<p>' . htmlspecialchars($s, ENT_QUOTES, 'UTF-8') . '</p>';
                    },
                    Generator\string()
                )
            )
            ->then(function (
                int    $eventId,
                int    $actionId,
                int    $emailTemplateId,
                int    $contractTemplateId,
                string $contactEmail,
                string $emailSubject,
                string $emailBody
            ): void {
                // Reset stores for each iteration
                global $test_post_meta_store, $test_post_store;
                $test_post_meta_store = array();
                $test_post_store      = array();

                $this->setUpScenario(
                    $eventId,
                    $actionId,
                    $emailTemplateId,
                    $contractTemplateId,
                    $contactEmail,
                    $emailSubject,
                    $emailBody
                );

                $request = new FakeRestRequest(
                    array('event_id' => (string) $eventId),
                    array('action_id' => (string) $actionId)
                );

                $result = $this->api->get_contract_preview($request);

                // Must not be a WP_Error
                $this->assertIsArray(
                    $result,
                    'Preview response must be an array, got WP_Error: '
                        . (is_wp_error($result) ? $result->get_error_message() : '')
                );

                // All required keys must be present
                $requiredKeys = array('to', 'subject', 'body', 'attachments', 'pdf_url', 'templates');
                foreach ($requiredKeys as $key) {
                    $this->assertArrayHasKey(
                        $key,
                        $result,
                        sprintf('Preview response must contain key "%s"', $key)
                    );
                }

                // 'to' must match the contact email from the chain
                $this->assertSame(
                    $contactEmail,
                    $result['to'],
                    'Preview "to" must be the resolved contact email'
                );

                // 'subject' must be non-empty (placeholder-parsed from the email template)
                $this->assertNotEmpty(
                    $result['subject'],
                    'Preview "subject" must not be empty when email template is configured'
                );

                // 'body' must be non-empty
                $this->assertNotEmpty(
                    $result['body'],
                    'Preview "body" must not be empty when email template is configured'
                );

                // 'attachments' must be an array
                $this->assertIsArray(
                    $result['attachments'],
                    'Preview "attachments" must be an array'
                );

                // 'pdf_url' must be a non-empty string
                $this->assertNotEmpty(
                    $result['pdf_url'],
                    'Preview "pdf_url" must not be empty'
                );

                // 'templates' must be an array with at least one entry (the contract template we created)
                $this->assertIsArray(
                    $result['templates'],
                    'Preview "templates" must be an array'
                );
                $this->assertNotEmpty(
                    $result['templates'],
                    'Preview "templates" must contain at least one template'
                );

                // 'selected_template_id' must be present and match the configured template
                $this->assertArrayHasKey(
                    'selected_template_id',
                    $result,
                    'Preview response must contain "selected_template_id"'
                );
                $this->assertSame(
                    $contractTemplateId,
                    $result['selected_template_id'],
                    'selected_template_id must match the configured contract template'
                );

                // no_template flag must NOT be present when email template is configured
                $this->assertArrayNotHasKey(
                    'no_template',
                    $result,
                    'no_template flag must not be present when email template is configured'
                );
            });
    }
}
