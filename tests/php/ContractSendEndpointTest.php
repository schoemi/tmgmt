<?php
// Feature: contract-send-dialog, Task 2.2: Unit tests for POST /events/{event_id}/contract-send

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
class SendFakeRestRequest implements ArrayAccess {
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

    public function offsetExists($offset): bool  { return isset($this->url_params[$offset]); }
    public function offsetGet($offset): mixed     { return $this->url_params[$offset] ?? null; }
    public function offsetSet($offset, $value): void { $this->url_params[$offset] = $value; }
    public function offsetUnset($offset): void    { unset($this->url_params[$offset]); }
}

/**
 * Testable subclass that stubs out the contract generator dependency.
 */
class SendTestable_REST_API extends TMGMT_REST_API {
    public ?TMGMT_Contract_Generator $stubGenerator = null;

    protected function make_contract_generator() {
        return $this->stubGenerator ?? new TMGMT_Contract_Generator();
    }
}

/**
 * Contract generator stub that captures generate_and_send() calls.
 */
class SendStubContractGenerator extends TMGMT_Contract_Generator {
    public $sendResult = true;
    public array $lastSendCall = array();

    public function generate_and_send(int $event_id, int $action_id, array $overrides = []): bool|WP_Error {
        $this->lastSendCall = array(
            'event_id'  => $event_id,
            'action_id' => $action_id,
            'overrides' => $overrides,
        );
        return $this->sendResult;
    }
}

/**
 * Unit tests for the POST /events/{event_id}/contract-send endpoint.
 *
 * **Validates: Requirements 5.2, 5.4, 5.5, 5.6, 5.8, 6.1, 6.2, 6.4**
 */
class ContractSendEndpointTest extends \PHPUnit\Framework\TestCase
{
    private SendTestable_REST_API $api;
    private SendStubContractGenerator $stubGen;

    protected function setUp(): void
    {
        global $test_post_meta_store, $test_post_store, $test_options_store;
        $test_post_meta_store = array();
        $test_post_store      = array();
        $test_options_store   = array();

        $this->stubGen = new SendStubContractGenerator();
        $this->api     = new SendTestable_REST_API();
        $this->api->stubGenerator = $this->stubGen;
    }

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

    // --- Tests ---

    /**
     * Test: returns 400 when required fields are missing.
     */
    public function testReturns400WhenRequiredFieldsMissing(): void
    {
        $request = new SendFakeRestRequest(
            array('event_id' => '100'),
            array() // no body params
        );

        $result = $this->api->send_contract($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('missing_fields', $result->get_error_code());
        $this->assertSame(400, $result->get_error_data()['status']);
        $this->assertStringContainsString('action_id', $result->get_error_message());
        $this->assertStringContainsString('to', $result->get_error_message());
        $this->assertStringContainsString('subject', $result->get_error_message());
    }

    /**
     * Test: returns 400 when only some required fields are missing.
     */
    public function testReturns400WhenPartialFieldsMissing(): void
    {
        $request = new SendFakeRestRequest(
            array('event_id' => '100'),
            array('action_id' => '5', 'to' => 'test@example.com') // missing subject
        );

        $result = $this->api->send_contract($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('missing_fields', $result->get_error_code());
        $this->assertStringContainsString('subject', $result->get_error_message());
        $this->assertStringNotContainsString('action_id', $result->get_error_message());
        $this->assertStringNotContainsString('to', $result->get_error_message());
    }

    /**
     * Test: returns 404 for invalid event_id.
     */
    public function testReturns404ForInvalidEventId(): void
    {
        $request = new SendFakeRestRequest(
            array('event_id' => '99999'),
            array('action_id' => '1', 'to' => 'test@example.com', 'subject' => 'Test')
        );

        $result = $this->api->send_contract($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('not_found', $result->get_error_code());
        $this->assertSame(404, $result->get_error_data()['status']);
    }

    /**
     * Test: returns 404 when event exists but is wrong post type.
     */
    public function testReturns404WhenEventIsWrongPostType(): void
    {
        global $test_post_store;
        $post               = new stdClass();
        $post->ID           = 100;
        $post->post_title   = 'Not an event';
        $post->post_content = '';
        $post->post_type    = 'post';
        $post->post_status  = 'publish';
        $test_post_store[100] = $post;

        $request = new SendFakeRestRequest(
            array('event_id' => '100'),
            array('action_id' => '1', 'to' => 'test@example.com', 'subject' => 'Test')
        );

        $result = $this->api->send_contract($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('not_found', $result->get_error_code());
    }

    /**
     * Test: returns 404 for invalid action_id.
     */
    public function testReturns404ForInvalidActionId(): void
    {
        $this->createEvent(100);

        $request = new SendFakeRestRequest(
            array('event_id' => '100'),
            array('action_id' => '99999', 'to' => 'test@example.com', 'subject' => 'Test')
        );

        $result = $this->api->send_contract($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('not_found', $result->get_error_code());
        $this->assertSame(404, $result->get_error_data()['status']);
    }

    /**
     * Test: returns 404 when action exists but is wrong post type.
     */
    public function testReturns404WhenActionIsWrongPostType(): void
    {
        $this->createEvent(100);

        global $test_post_store;
        $post               = new stdClass();
        $post->ID           = 200;
        $post->post_title   = 'Not an action';
        $post->post_content = '';
        $post->post_type    = 'post';
        $post->post_status  = 'publish';
        $test_post_store[200] = $post;

        $request = new SendFakeRestRequest(
            array('event_id' => '100'),
            array('action_id' => '200', 'to' => 'test@example.com', 'subject' => 'Test')
        );

        $result = $this->api->send_contract($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('not_found', $result->get_error_code());
    }

    /**
     * Test: returns 500 when generate_and_send fails.
     */
    public function testReturns500WhenSendFails(): void
    {
        $this->createEvent(100);
        $this->createAction(200);

        $this->stubGen->sendResult = new WP_Error('email_send_failed', 'E-Mail-Versand fehlgeschlagen');

        $request = new SendFakeRestRequest(
            array('event_id' => '100'),
            array('action_id' => '200', 'to' => 'test@example.com', 'subject' => 'Test')
        );

        $result = $this->api->send_contract($request);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('email_send_failed', $result->get_error_code());
        $this->assertSame(500, $result->get_error_data()['status']);
    }

    /**
     * Test: successful send returns success response.
     */
    public function testSuccessfulSendReturnsSuccessResponse(): void
    {
        $this->createEvent(100);
        $this->createAction(200);

        $request = new SendFakeRestRequest(
            array('event_id' => '100'),
            array(
                'action_id'   => '200',
                'to'          => 'max@example.com',
                'subject'     => 'Ihr Vertrag',
                'body'        => '<p>Hallo</p>',
                'cc'          => 'cc@example.com',
                'bcc'         => 'bcc@example.com',
                'template_id' => '10',
            )
        );

        $result = $this->api->send_contract($request);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertSame('Vertrag gesendet.', $result['message']);
    }

    /**
     * Test: overrides are passed to generate_and_send.
     */
    public function testOverridesPassedToGenerateAndSend(): void
    {
        $this->createEvent(100);
        $this->createAction(200);

        $request = new SendFakeRestRequest(
            array('event_id' => '100'),
            array(
                'action_id'   => '200',
                'to'          => 'max@example.com',
                'subject'     => 'Custom Subject',
                'body'        => '<p>Custom Body</p>',
                'cc'          => 'cc@example.com',
                'bcc'         => 'bcc@example.com',
                'template_id' => '42',
            )
        );

        $this->api->send_contract($request);

        $call = $this->stubGen->lastSendCall;
        $this->assertSame(100, $call['event_id']);
        $this->assertSame(200, $call['action_id']);
        $this->assertSame('max@example.com', $call['overrides']['to']);
        $this->assertSame('Custom Subject', $call['overrides']['subject']);
        $this->assertSame('<p>Custom Body</p>', $call['overrides']['body']);
        $this->assertSame('cc@example.com', $call['overrides']['cc']);
        $this->assertSame('bcc@example.com', $call['overrides']['bcc']);
        $this->assertSame('42', $call['overrides']['template_id']);
    }

    /**
     * Test: optional fields (cc, bcc, body, template_id) are not required.
     */
    public function testOptionalFieldsNotRequired(): void
    {
        $this->createEvent(100);
        $this->createAction(200);

        $request = new SendFakeRestRequest(
            array('event_id' => '100'),
            array(
                'action_id' => '200',
                'to'        => 'max@example.com',
                'subject'   => 'Test Subject',
            )
        );

        $result = $this->api->send_contract($request);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);

        $call = $this->stubGen->lastSendCall;
        $this->assertSame('max@example.com', $call['overrides']['to']);
        $this->assertSame('Test Subject', $call['overrides']['subject']);
        $this->assertArrayNotHasKey('cc', $call['overrides']);
        $this->assertArrayNotHasKey('bcc', $call['overrides']);
        $this->assertArrayNotHasKey('body', $call['overrides']);
        $this->assertArrayNotHasKey('template_id', $call['overrides']);
    }

    /**
     * Test: route is registered with correct method and permission callback.
     */
    public function testRouteIsRegistered(): void
    {
        global $test_registered_rest_routes;
        $test_registered_rest_routes = array();

        $api = new TMGMT_REST_API();
        $api->register_routes();

        $route_key = 'tmgmt/v1/events/(?P<event_id>\d+)/contract-send';
        $this->assertArrayHasKey($route_key, $test_registered_rest_routes);

        $route = $test_registered_rest_routes[$route_key];
        $this->assertSame('POST', $route['methods']);
        $this->assertSame(array($api, 'send_contract'), $route['callback']);
        $this->assertSame(array($api, 'check_permission'), $route['permission_callback']);
    }
}
