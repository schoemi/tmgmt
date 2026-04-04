<?php
// Feature: contract-send-dialog, Property 3: Template-Selector-Sichtbarkeit hängt von der Template-Anzahl ab
// For any Anzahl Templates: templates-Array hat korrekte Länge

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
 * Property-Based Test: Template-Selector-Sichtbarkeit hängt von der Template-Anzahl ab
 *
 * For any number of published tmgmt_contract_tpl posts (1–20), the preview response's
 * templates array must have exactly that many entries. The selected_template_id must
 * match the action's configured contract template.
 *
 * **Validates: Requirements 4.1, 4.2, 4.3**
 */
class ContractTemplateSelectorVisibilityPropertyTest extends \PHPUnit\Framework\TestCase
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
     * Create the minimal event + action + email template + contact chain
     * needed for a successful preview call.
     *
     * @return int The contract template ID configured on the action.
     */
    private function setUpBaseScenario(int $eventId, int $actionId, int $contractTemplateId): int
    {
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

        // Email template
        $emailTemplateId = $actionId + 50000;
        $emailTpl               = new \stdClass();
        $emailTpl->ID           = $emailTemplateId;
        $emailTpl->post_title   = 'Email Template';
        $emailTpl->post_content = '';
        $emailTpl->post_type    = 'tmgmt_email_template';
        $emailTpl->post_status  = 'publish';
        $test_post_store[$emailTemplateId] = $emailTpl;

        update_post_meta($emailTemplateId, '_tmgmt_email_subject', 'Betreff');
        update_post_meta($emailTemplateId, '_tmgmt_email_body', '<p>Body</p>');

        update_post_meta($actionId, '_tmgmt_action_type', 'contract_generation');
        update_post_meta($actionId, '_tmgmt_action_email_template_id', $emailTemplateId);
        update_post_meta($actionId, '_tmgmt_action_contract_template_id', $contractTemplateId);

        // Contact chain
        $contactId = $eventId + 90000;
        $contact               = new \stdClass();
        $contact->ID           = $contactId;
        $contact->post_title   = 'Kontakt';
        $contact->post_content = '';
        $contact->post_type    = 'tmgmt_contact';
        $contact->post_status  = 'publish';
        $test_post_store[$contactId] = $contact;

        update_post_meta($contactId, '_tmgmt_contact_salutation', 'Herr');
        update_post_meta($contactId, '_tmgmt_contact_firstname', 'Max');
        update_post_meta($contactId, '_tmgmt_contact_lastname', 'Mustermann');
        update_post_meta($contactId, '_tmgmt_contact_email', 'max@example.com');

        $veranstalterId = $eventId + 80000;
        $veranstalter               = new \stdClass();
        $veranstalter->ID           = $veranstalterId;
        $veranstalter->post_title   = 'Veranstalter';
        $veranstalter->post_content = '';
        $veranstalter->post_type    = 'tmgmt_veranstalter';
        $veranstalter->post_status  = 'publish';
        $test_post_store[$veranstalterId] = $veranstalter;

        update_post_meta($veranstalterId, '_tmgmt_veranstalter_contacts', array(
            array('role' => 'vertrag', 'contact_id' => $contactId),
        ));
        update_post_meta($eventId, '_tmgmt_event_veranstalter_id', $veranstalterId);

        // Event meta for placeholder parser
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

        return $contractTemplateId;
    }

    /**
     * Create N published contract template posts in the test store.
     *
     * @param int   $count    Number of templates to create.
     * @param int   $startId  Starting post ID for the templates.
     * @return int[] Array of created template IDs.
     */
    private function createContractTemplates(int $count, int $startId): array
    {
        global $test_post_store;
        $ids = array();

        for ($i = 0; $i < $count; $i++) {
            $id = $startId + $i;
            $tpl               = new \stdClass();
            $tpl->ID           = $id;
            $tpl->post_title   = 'Vertrag ' . $id;
            $tpl->post_content = '<p>Vertrag Content ' . $id . '</p>';
            $tpl->post_type    = 'tmgmt_contract_tpl';
            $tpl->post_status  = 'publish';
            $test_post_store[$id] = $tpl;
            $ids[] = $id;
        }

        return $ids;
    }

    /**
     * Property 3: Template-Selector-Sichtbarkeit hängt von der Template-Anzahl ab.
     *
     * For any random number of published tmgmt_contract_tpl posts (1–20):
     * - count(response['templates']) must equal the number of published templates
     * - selected_template_id must match the action's configured template
     *
     * **Validates: Requirements 4.1, 4.2, 4.3**
     */
    public function testTemplateCountMatchesPublishedTemplates(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                // Random number of templates (1–20)
                Generator\choose(1, 20),
                // Random event ID
                Generator\choose(1, 500),
                // Random action ID offset
                Generator\choose(1000, 2000)
            )
            ->then(function (int $templateCount, int $eventId, int $actionId): void {
                // Reset stores for each iteration
                global $test_post_meta_store, $test_post_store;
                $test_post_meta_store = array();
                $test_post_store      = array();

                // Create the contract templates (IDs start at 30000 to avoid collisions)
                $templateIds = $this->createContractTemplates($templateCount, 30000);

                // Use the first template as the action's configured template
                $configuredTemplateId = $templateIds[0];

                // Set up the base scenario (event, action, email template, contact chain)
                $this->setUpBaseScenario($eventId, $actionId, $configuredTemplateId);

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

                // templates array must be present
                $this->assertArrayHasKey('templates', $result, 'Response must contain "templates" key');

                // count(templates) must match the number of published contract templates
                $this->assertCount(
                    $templateCount,
                    $result['templates'],
                    sprintf(
                        'Expected %d templates in response, got %d',
                        $templateCount,
                        count($result['templates'])
                    )
                );

                // Each template entry must have id and title
                foreach ($result['templates'] as $idx => $tpl) {
                    $this->assertArrayHasKey('id', $tpl, "Template entry $idx must have 'id'");
                    $this->assertArrayHasKey('title', $tpl, "Template entry $idx must have 'title'");
                }

                // selected_template_id must match the action's configured template
                $this->assertArrayHasKey('selected_template_id', $result, 'Response must contain "selected_template_id"');
                $this->assertSame(
                    $configuredTemplateId,
                    $result['selected_template_id'],
                    'selected_template_id must match the action\'s configured contract template'
                );

                // The configured template must appear in the templates list
                $templateIdsInResponse = array_column($result['templates'], 'id');
                $this->assertContains(
                    $configuredTemplateId,
                    $templateIdsInResponse,
                    'The configured template must appear in the templates list'
                );
            });
    }
}
