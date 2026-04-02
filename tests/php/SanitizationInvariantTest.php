<?php
// Feature: veranstalter-cpt, Property 2: Sanitization-Invariante

use Eris\Generator;
use Eris\TestTrait;

/**
 * Property-Based Test: Sanitization-Invariante
 *
 * For arbitrary input strings (including HTML tags, special characters),
 * the saved value must always equal sanitize_text_field(input).
 *
 * **Validates: Requirements 2.5**
 */
class SanitizationInvariantTest extends \PHPUnit\Framework\TestCase
{
    use TestTrait;

    private TMGMT_Veranstalter_Post_Type $sut;

    protected function setUp(): void
    {
        global $test_post_meta_store;
        $test_post_meta_store = array();
        $_POST = array();
        $this->sut = new TMGMT_Veranstalter_Post_Type();
    }

    protected function tearDown(): void
    {
        $_POST = array();
    }

    /**
     * Property 2: Saved values must equal sanitize_text_field(input) for arbitrary strings.
     */
    public function testSavedValueEqualsSanitizedInput(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generator\string()
            )
            ->then(function (string $input): void {
                global $test_post_meta_store;
                $test_post_meta_store = array();

                $postId = 42;

                $_POST = array(
                    'tmgmt_veranstalter_meta_nonce' => 'valid',
                    'tmgmt_veranstalter_street'     => $input,
                    'tmgmt_veranstalter_number'     => $input,
                    'tmgmt_veranstalter_zip'        => $input,
                    'tmgmt_veranstalter_city'       => $input,
                    'tmgmt_veranstalter_country'    => $input,
                );

                $this->sut->save_meta_boxes($postId);

                $expected = sanitize_text_field($input);
                $fields = array(
                    '_tmgmt_veranstalter_street',
                    '_tmgmt_veranstalter_number',
                    '_tmgmt_veranstalter_zip',
                    '_tmgmt_veranstalter_city',
                    '_tmgmt_veranstalter_country',
                );

                foreach ($fields as $metaKey) {
                    $actual = get_post_meta($postId, $metaKey, true);
                    $this->assertSame($expected, $actual,
                        "Sanitization invariant violated for {$metaKey}");
                }
            });
    }

    /**
     * Property 2 (HTML): HTML tags must be stripped from saved values.
     */
    public function testHtmlTagsAreStripped(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generator\elements(
                    '<script>alert("xss")</script>',
                    '<b>bold</b>',
                    '<a href="http://example.com">link</a>',
                    '"><img src=x onerror=alert(1)>',
                    '<div class="test">content</div>',
                    'normal text',
                    '   spaces   '
                )
            )
            ->then(function (string $input): void {
                global $test_post_meta_store;
                $test_post_meta_store = array();

                $postId = 42;

                $_POST = array(
                    'tmgmt_veranstalter_meta_nonce' => 'valid',
                    'tmgmt_veranstalter_street'     => $input,
                );

                $this->sut->save_meta_boxes($postId);

                $actual = get_post_meta($postId, '_tmgmt_veranstalter_street', true);
                $this->assertSame(sanitize_text_field($input), $actual);
                // Verify no HTML tags remain (standalone < or > outside tags may persist after strip_tags)
                $this->assertSame(strip_tags($actual), $actual, 'HTML tags must be stripped');
            });
    }
}
