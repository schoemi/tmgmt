<?php
// Feature: contract-send-dialog, Property 6: Nicht-autorisierte Anfragen werden abgewiesen
// For any event_id and action_id: check_permission() returns false for unauthenticated users

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
 * Minimal WP_REST_Request stub for the permission check.
 */
class AuthFakeRestRequest implements ArrayAccess {
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

/**
 * Property-Based Test: Nicht-autorisierte Anfragen werden abgewiesen
 *
 * For any randomly generated event_id and action_id, calling check_permission()
 * with no user capabilities (empty $test_current_user_caps) must return false,
 * and calling it with the required capabilities must return true.
 *
 * **Validates: Requirements 5.5**
 */
class ContractAuthenticationEnforcementPropertyTest extends \PHPUnit\Framework\TestCase
{
    use TestTrait;

    private TMGMT_REST_API $api;

    protected function setUp(): void
    {
        $this->api = new TMGMT_REST_API();
    }

    protected function tearDown(): void
    {
        // Reset to default (no cap restriction)
        global $test_current_user_caps;
        $test_current_user_caps = null;
    }

    /**
     * Property 6: Unauthenticated users are denied access.
     *
     * For any random event_id and action_id, check_permission() returns false
     * when the user has no capabilities (simulating an unauthenticated request).
     */
    public function testUnauthenticatedUsersAreDeniedAccess(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generator\choose(1, 999999),
                Generator\choose(1, 999999)
            )
            ->then(function (int $eventId, int $actionId): void {
                global $test_current_user_caps;

                // Simulate unauthenticated user: no capabilities
                $test_current_user_caps = [];

                $request = new AuthFakeRestRequest(
                    array('event_id' => (string) $eventId),
                    array('action_id' => (string) $actionId)
                );

                $result = $this->api->check_permission($request);

                $this->assertFalse(
                    $result,
                    sprintf(
                        'check_permission() should return false for unauthenticated user (event_id=%d, action_id=%d)',
                        $eventId,
                        $actionId
                    )
                );
            });
    }

    /**
     * Property 6 (inverse): Authorized users with edit_posts are granted access.
     *
     * For any random event_id and action_id, check_permission() returns true
     * when the user has the edit_posts capability.
     */
    public function testAuthorizedUsersWithEditPostsAreGrantedAccess(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generator\choose(1, 999999),
                Generator\choose(1, 999999)
            )
            ->then(function (int $eventId, int $actionId): void {
                global $test_current_user_caps;

                // Simulate authorized user with edit_posts
                $test_current_user_caps = ['edit_posts'];

                $request = new AuthFakeRestRequest(
                    array('event_id' => (string) $eventId),
                    array('action_id' => (string) $actionId)
                );

                $result = $this->api->check_permission($request);

                $this->assertTrue(
                    $result,
                    sprintf(
                        'check_permission() should return true for user with edit_posts (event_id=%d, action_id=%d)',
                        $eventId,
                        $actionId
                    )
                );
            });
    }

    /**
     * Property 6 (inverse): Authorized users with edit_tmgmt_events are granted access.
     *
     * For any random event_id and action_id, check_permission() returns true
     * when the user has the edit_tmgmt_events capability.
     */
    public function testAuthorizedUsersWithEditTmgmtEventsAreGrantedAccess(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generator\choose(1, 999999),
                Generator\choose(1, 999999)
            )
            ->then(function (int $eventId, int $actionId): void {
                global $test_current_user_caps;

                // Simulate authorized user with edit_tmgmt_events
                $test_current_user_caps = ['edit_tmgmt_events'];

                $request = new AuthFakeRestRequest(
                    array('event_id' => (string) $eventId),
                    array('action_id' => (string) $actionId)
                );

                $result = $this->api->check_permission($request);

                $this->assertTrue(
                    $result,
                    sprintf(
                        'check_permission() should return true for user with edit_tmgmt_events (event_id=%d, action_id=%d)',
                        $eventId,
                        $actionId
                    )
                );
            });
    }
}
