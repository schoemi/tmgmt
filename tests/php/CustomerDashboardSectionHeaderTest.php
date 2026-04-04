<?php
/**
 * Unit tests for render_section_header() in TMGMT_Customer_Access_Manager.
 *
 * Validates: Requirements 5.1, 5.2, 5.3, 1.4
 */

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

if (!class_exists('TMGMT_Placeholder_Parser')) {
    class TMGMT_Placeholder_Parser {
        public function __construct(int $event_id) {}
        public function parse(string $text): string { return $text; }
    }
}

require_once dirname(__DIR__, 2) . '/includes/class-customer-access-manager.php';

class CustomerDashboardSectionHeaderTest extends \PHPUnit\Framework\TestCase
{
    private \ReflectionMethod $renderMethod;
    private TMGMT_Customer_Access_Manager $sut;

    protected function setUp(): void
    {
        global $test_post_meta_store, $test_post_store, $test_options_store;
        $test_post_meta_store = [];
        $test_post_store      = [];
        $test_options_store   = [];

        $this->sut = new TMGMT_Customer_Access_Manager();

        $ref = new \ReflectionClass(TMGMT_Customer_Access_Manager::class);
        $this->renderMethod = $ref->getMethod('render_section_header');
    }

    private function renderHeader(string $title, string $statusKey): string
    {
        ob_start();
        $this->renderMethod->invoke($this->sut, $title, $statusKey);
        return ob_get_clean();
    }

    /**
     * Req 5.1 / 1.4: Event title is rendered as <h1> inside .cd-header
     */
    public function testTitleRenderedAsH1InsideCdHeader(): void
    {
        $html = $this->renderHeader('Summer Festival 2025', '');

        $this->assertStringContainsString('<div class="cd-header">', $html);
        $this->assertStringContainsString('<h1>Summer Festival 2025</h1>', $html);
    }

    /**
     * Req 5.1: Title is HTML-escaped to prevent XSS
     */
    public function testTitleIsHtmlEscaped(): void
    {
        $html = $this->renderHeader('<script>alert("xss")</script>', '');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    /**
     * Req 5.2: Status badge is shown when status key is non-empty.
     * The bootstrap stub returns the key itself as the label.
     */
    public function testStatusBadgeShownWhenStatusSet(): void
    {
        $html = $this->renderHeader('Test Event', 'confirmed');

        $this->assertStringContainsString('<span class="cd-status-badge">', $html);
        // Bootstrap stub: get_label('confirmed') returns 'confirmed'
        $this->assertStringContainsString('confirmed', $html);
    }

    /**
     * Req 5.3: No status badge when status key is empty
     */
    public function testNoStatusBadgeWhenStatusEmpty(): void
    {
        $html = $this->renderHeader('Test Event', '');

        $this->assertStringNotContainsString('cd-status-badge', $html);
    }

    /**
     * Structural: The div is properly closed
     */
    public function testDivIsClosed(): void
    {
        $html = $this->renderHeader('Event', 'some_status');

        $openCount  = substr_count($html, '<div');
        $closeCount = substr_count($html, '</div>');
        $this->assertEquals($openCount, $closeCount, 'All opened divs should be closed');
    }
}
