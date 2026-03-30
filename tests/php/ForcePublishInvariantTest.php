<?php
// Feature: veranstalter-cpt, Property 7: Force-Publish Invariante

use Eris\Generator;
use Eris\TestTrait;

/**
 * Property-Based Test: Force-Publish Invariante
 *
 * For arbitrary post status values (except `trash` and `auto-draft`),
 * the resulting status must be `publish`.
 *
 * **Validates: Requirements 5.5**
 */
class ForcePublishInvariantTest extends \PHPUnit\Framework\TestCase
{
    use TestTrait;

    private TMGMT_Veranstalter_Post_Type $sut;

    protected function setUp(): void
    {
        $this->sut = new TMGMT_Veranstalter_Post_Type();
    }

    /**
     * Property 7: For any post status that is NOT 'trash' or 'auto-draft',
     * force_publish_status must set post_status to 'publish'.
     *
     * **Validates: Requirements 5.5**
     */
    public function testForcePublishForArbitraryStatus(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generator\string()
            )
            ->when(function (string $status): bool {
                return $status !== 'trash' && $status !== 'auto-draft';
            })
            ->then(function (string $status): void {
                $data = [
                    'post_type'   => 'tmgmt_veranstalter',
                    'post_status' => $status,
                ];
                $postarr = [];

                $result = $this->sut->force_publish_status($data, $postarr);

                $this->assertSame('publish', $result['post_status'],
                    "Expected 'publish' for input status '{$status}', got '{$result['post_status']}'");
            });
    }

    /**
     * Property 7 (complement): 'trash' status must be preserved.
     *
     * **Validates: Requirements 5.5**
     */
    public function testTrashStatusIsPreserved(): void
    {
        $data = [
            'post_type'   => 'tmgmt_veranstalter',
            'post_status' => 'trash',
        ];
        $postarr = [];

        $result = $this->sut->force_publish_status($data, $postarr);

        $this->assertSame('trash', $result['post_status'],
            "Expected 'trash' to be preserved, got '{$result['post_status']}'");
    }

    /**
     * Property 7 (complement): 'auto-draft' status must be preserved.
     *
     * **Validates: Requirements 5.5**
     */
    public function testAutoDraftStatusIsPreserved(): void
    {
        $data = [
            'post_type'   => 'tmgmt_veranstalter',
            'post_status' => 'auto-draft',
        ];
        $postarr = [];

        $result = $this->sut->force_publish_status($data, $postarr);

        $this->assertSame('auto-draft', $result['post_status'],
            "Expected 'auto-draft' to be preserved, got '{$result['post_status']}'");
    }

    /**
     * Property 7 (complement): Known WordPress statuses are all forced to 'publish'.
     *
     * **Validates: Requirements 5.5**
     */
    public function testKnownWordPressStatusesForcedToPublish(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generator\elements('draft', 'pending', 'private', 'future', 'inherit', 'publish')
            )
            ->then(function (string $status): void {
                $data = [
                    'post_type'   => 'tmgmt_veranstalter',
                    'post_status' => $status,
                ];
                $postarr = [];

                $result = $this->sut->force_publish_status($data, $postarr);

                $this->assertSame('publish', $result['post_status'],
                    "Expected 'publish' for known WP status '{$status}', got '{$result['post_status']}'");
            });
    }

    /**
     * Property 7 (invariant): For non-veranstalter post types, status must NOT be changed.
     *
     * **Validates: Requirements 5.5**
     */
    public function testNonVeranstalterPostTypeUnchanged(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generator\string(),
                Generator\elements('post', 'page', 'tmgmt_contact', 'tmgmt_location', 'event')
            )
            ->then(function (string $status, string $postType): void {
                $data = [
                    'post_type'   => $postType,
                    'post_status' => $status,
                ];
                $postarr = [];

                $result = $this->sut->force_publish_status($data, $postarr);

                $this->assertSame($status, $result['post_status'],
                    "Status should be unchanged for post type '{$postType}', but was modified from '{$status}' to '{$result['post_status']}'");
            });
    }
}
