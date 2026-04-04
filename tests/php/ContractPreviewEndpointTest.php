<?php
// Feature: contract-send-dialog, Task 2.1: Unit tests for GET /events/{event_id}/contract-preview

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
    function register_rest_route($namespace, $route, $args = array()) {
        global $test_registered_rest_routes;
        if (!isset($test_registered_rest_routes)) {
            $test_registered_rest_routes = array();
        }
        $test_registered_rest_routes[$namespace . $route] = $args;
    }
}

require_once dirname(__DIR__, 2) . '/includes/class-placeholder-parser.php';
require_once dirname(__DIR__, 2) . '/includes/class-pdf-generator.php';
require_once dirname(__DIR__, 2) . '/includes/class-contract-generator.php';
require_once dirname(__DIR__, 2) . '/includes/class-rest-api.php';


/**
 * Minimal WP_REST_Request stub for unit testing.
 */
class FakeRestRequest implements ArrayAccess {
    private array $params = array();
    private array $url_params = array();

    public function __construct(array $url_params = array(), array $query_params = array()) {
        $this->url_params = $url_params;
        $this->params     = $query_params;
    }

    public function get_param(string $key) {
        if (isset($this->params[$key])) {
            return $this->params[$key];
        }
        if (isset($this->url_params[$key])) {
            return $this->url_params[$key];
        }
        return null;
    }

    public function get_json_params(): array { return $this->params; }
    public function get_body_params(): array { return $this->params; }

    // ArrayAccess for URL params like $request['event_id']
    public function offsetExists($offset): bool  { return isset($this->url_params[$offset]); }
    public function offsetGet($offset): mixed     { return $this->url_params[$offset] ?? null; }
    public function offsetSet($offset, $value): void { $this->url_params[$offset] = $value; }
    public function offsetUnset($offset): void    { unset($this->url_params[$offset]); }
}

/**
 * Testable subclass that stubs out the contract generator dependency.
 */
class Testable_REST_API extends TMGMT_REST_API {
    public ?TMGMT_Contract_Generator $stubGenerator = null;

    protected function make_contract_generator() {
        return $this->stubGenerator ?? new TMGMT_Contract_Generator();
    }
}

/**
 * Contract generator stub that returns a fixed preview result.
 */
class StubContractGenerator extends TMGMT_Contract_Generator {
    public $previewResult;

    public function render_preview(int $event_id, int $template_id): array|WP_Error {
        if ($this->previewResult !== null) {
            return $this->previewResult;
        }
        return array(
            'pdf_url' => 'http://example.com/wp-content/uploads/tmgmt-contracts/' . $event_id . '/contract-' . $event_id . '-preview-1234567890.pdf',
            'html'    => '<p>Preview HTML</p>',
        );
    }
}

/**
 * Unit tests for the GET /events/{event_id}/contract-preview endpoint.
 *
 * **Validates: Requirements 2.1, 2.3, 2.5, 3.2, 4.1, 4.2, 4.3, 5.1, 5.3, 5.5, 5.6, 5.7**
 */
class ContractPreviewEndpointTest extends \PHPUnit\Framework\TestCase
{
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
     * Helper: create an event post in the test store.
     */
    private function createEvent(int $id, string $title = 'Test Event'): void
    {
        global $test_post_store;
        $post               = new stdClass();
        $post->ID           = $id;
        $post->post_title   = $title;
        $post->post_content = '';
        $post->post_type    = 'event';
        $post->post_status  = 'publish';
        $test_post_store[$id] = $post;
    }

    /**
     * Helper: create an action post in the test store.
     */
    private function createAction(int $id, int $emailTemplateId = 0, int $contractTemplateId = 0): void
    {
        global $test_post_store;
        $post               = new stdClass();
        $post->ID           = $id;
        $post->post_title   = 'Test Action';
        $post->post_content = '';
        $post->post_type    = 'tmgmt_action';
        $post->post_status  = 'publish';
        $test_post_store[$id] = $post;

        update_post_meta($id, '_tmgmt_action_type', 'contract_generation');
        if ($emailTemplateId) {
            update_post_meta($id, '_tmgmt_action_email_template_id', $emailTemplateId);
        }
        if ($contractTemplateId) {
            update_post_meta($id, '_tmgmt_action_contract_template_id', $contractTemplateId);
        }
    }

    /**
     * Helper: create an email template post in the test store.
     */
    private function createEmailTemplate(int $id, string $subject = 'Ihr Vertrag', string $body = '<p>Body</p>'): void
    {
        global $test_post_store;
        $post               = new stdClass();
        $post->ID           = $id;
        $post->post_title   = 'Email Template';
        $post->post_content = '';
        $post->post_type    = 'tmgmt_email_template';
        $post->post_status  = 'publish';
        $test_post_store[$id] = $post;

        update_post_meta($id, '_tmgmt_email_subject', $subject);
        update_post_meta($id, '_tmgmt_email_body', $body);
    }

    /**
     * Helper: set up the veranstalter → contact chain for an event.
     */
    private function setupContactChain(int $eventId, string $email = 'max@example.com'): void
    {
        global $test_post_store;

        // Create contact
        $contactId = $eventId + 9000;
        $contact               = new stdClass();
        $contact->ID           = $contactId;
        $contact->post_title   = 'Max Mustermann';
        $contact->post_content = '';
        $contact->post_type    = 'tmgmt_contact';
        $contact->post_status  = 'publish';
        $test_post_store[$contactId] = $contact;

        update_post_meta($contactId, '_tmgmt_contact_email', $email);
        update_post_meta($contactId, '_tmgmt_contact_firstname', 'Max');
        update_post_meta($contactId, '_tmgmt_contact_lastname', 'Mustermann');
        update_post_meta($contactId, '_tmgmt_contact_salutation', 'Herr');

        // Create veranstalter
        $veranstalterId = $eventId + 8000;
        $veranstalter               = new stdClass();
        $veranstalter->ID           = $veranstalterId;
        $veranstalter->post_title   = 'Test Veranstalter';
        $veranstalter->post_content = '';
        $veranstalter->post_type    = 'tmgmt_veranstalter';
        $veranstalter->post_status  = 'publish';
        $test_post_store[$veranstalterId] = $veranstalter;

        update_post_meta($veranstalterId, '_tmgmt_veranstalter_contacts', array(
            array('contact_id' => $contactId, 'role' => 'vertrag'),
        ));

        // Link event to veranstalter
        update_post_meta($eventId, '_tmgmt_event_veranstalter_id', $veranstalterId);
    }

    // --- Tests ---

    /**
     * Test: returns 404 for invalid event_id.
     */
    public function testReturns404ForInvalidEventId(): void
    {
        $request = new FakeRestRequest(
            array('event_id' => '99999'),
            array('action_id' => '1')
        );

        $result = $this->api->get_contract_preview($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('not_found', $result->get_error_code());
    }

    /**
     * Test: returns 404 for invalid action_id.
     */
    public function testReturns404ForInvalidActionId(): void
    {
        $this->createEvent(100);

        $request = new FakeRestRequest(
            array('event_id' => '100'),
            array('action_id' => '99999')
        );

        $result = $this->api->get_contract_preview($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('not_found', $result->get_error_code());
    }

    /**
     * Test: returns 500 when PDF generation fails.
     */
    public function testReturns500WhenPdfGenerationFails(): void
    {
        $this->createEvent(200);
        $this->createAction(201, 0, 300);

        $this->stubGen->previewResult = new WP_Error('pdf_error', 'PDF generation failed');

        $request = new FakeRestRequest(
            array('event_id' => '200'),
            array('action_id' => '201')
        );

        $result = $this->api->get_contract_preview($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('pdf_error', $result->get_error_code());
    }

    /**
     * Test: returns no_template flag when no email template is configured.
     */
    public function testReturnsNoTemplateFlagWhenNoEmailTemplate(): void
    {
        $this->createEvent(300);
        $this->createAction(301, 0, 400); // no email template

        $request = new FakeRestRequest(
            array('event_id' => '300'),
            array('action_id' => '301')
        );

        $result = $this->api->get_contract_preview($request);

        $this->assertIsArray($result);
        $this->assertTrue($result['no_template']);
        $this->assertSame('', $result['to']);
        $this->assertSame('', $result['subject']);
        $this->assertSame('', $result['body']);
    }

    /**
     * Test: response contains all required fields.
     */
    public function testResponseContainsAllRequiredFields(): void
    {
        $this->createEvent(400);
        $this->createAction(401, 500, 600);
        $this->createEmailTemplate(500, 'Betreff [event_title]', '<p>Body</p>');
        $this->setupContactChain(400, 'max@example.com');

        $request = new FakeRestRequest(
            array('event_id' => '400'),
            array('action_id' => '401')
        );

        $result = $this->api->get_contract_preview($request);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('to', $result);
        $this->assertArrayHasKey('cc', $result);
        $this->assertArrayHasKey('bcc', $result);
        $this->assertArrayHasKey('subject', $result);
        $this->assertArrayHasKey('body', $result);
        $this->assertArrayHasKey('attachments', $result);
        $this->assertArrayHasKey('pdf_url', $result);
        $this->assertArrayHasKey('templates', $result);
        $this->assertArrayHasKey('selected_template_id', $result);
    }

    /**
     * Test: email fields are resolved via placeholder parser.
     */
    public function testEmailFieldsResolvedViaPlaceholderParser(): void
    {
        $this->createEvent(500, 'Konzert Berlin');
        $this->createAction(501, 600, 700);
        $this->createEmailTemplate(600, 'Ihr Vertrag – [event_title]', '<p>Hallo [contact_firstname]</p>');
        $this->setupContactChain(500, 'max@example.com');

        $request = new FakeRestRequest(
            array('event_id' => '500'),
            array('action_id' => '501')
        );

        $result = $this->api->get_contract_preview($request);

        $this->assertIsArray($result);
        $this->assertSame('max@example.com', $result['to']);
        $this->assertStringContainsString('Konzert Berlin', $result['subject']);
        $this->assertStringContainsString('Max', $result['body']);
        $this->assertStringNotContainsString('[event_title]', $result['subject']);
        $this->assertStringNotContainsString('[contact_firstname]', $result['body']);
    }

    /**
     * Test: template_id query param overrides action meta.
     */
    public function testTemplateIdQueryParamOverridesActionMeta(): void
    {
        $this->createEvent(600);
        $this->createAction(601, 700, 800); // action has template 800

        $request = new FakeRestRequest(
            array('event_id' => '600'),
            array('action_id' => '601', 'template_id' => '999')
        );

        $result = $this->api->get_contract_preview($request);

        $this->assertIsArray($result);
        $this->assertSame(999, $result['selected_template_id']);
    }

    /**
     * Test: pdf_url is present in the response.
     */
    public function testPdfUrlIsPresentInResponse(): void
    {
        $this->createEvent(700);
        $this->createAction(701, 0, 800);

        $request = new FakeRestRequest(
            array('event_id' => '700'),
            array('action_id' => '701')
        );

        $result = $this->api->get_contract_preview($request);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result['pdf_url']);
        $this->assertStringContainsString('tmgmt-contracts/', $result['pdf_url']);
    }

    /**
     * Test: no_template key is absent when email template IS configured.
     */
    public function testNoTemplateKeyAbsentWhenEmailTemplateConfigured(): void
    {
        $this->createEvent(800);
        $this->createAction(801, 900, 1000);
        $this->createEmailTemplate(900);
        $this->setupContactChain(800);

        $request = new FakeRestRequest(
            array('event_id' => '800'),
            array('action_id' => '801')
        );

        $result = $this->api->get_contract_preview($request);

        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('no_template', $result);
    }

    /**
     * Test: returns 404 when event exists but is wrong post type.
     */
    public function testReturns404WhenEventIsWrongPostType(): void
    {
        global $test_post_store;
        $post               = new stdClass();
        $post->ID           = 1100;
        $post->post_title   = 'Not an event';
        $post->post_content = '';
        $post->post_type    = 'post'; // wrong type
        $post->post_status  = 'publish';
        $test_post_store[1100] = $post;

        $request = new FakeRestRequest(
            array('event_id' => '1100'),
            array('action_id' => '1')
        );

        $result = $this->api->get_contract_preview($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('not_found', $result->get_error_code());
    }
}
