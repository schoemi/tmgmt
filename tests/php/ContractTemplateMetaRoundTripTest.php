<?php
// Feature: contract-generation, Property 12: Template-ID-Meta-Round-Trip an tmgmt_action

use Eris\Generator;
use Eris\TestTrait;

// Stub absint() if not already defined (WordPress function)
if (!function_exists('absint')) {
    function absint($maybeint): int {
        return abs((int) $maybeint);
    }
}

// Require the class under test
require_once dirname(__DIR__, 2) . '/includes/post-types/class-action-post-type.php';

/**
 * Property-Based Test: Template-ID-Meta-Round-Trip an tmgmt_action
 *
 * For any random positive integer ID, when saved via save_meta_boxes() with
 * $_POST['tmgmt_action_contract_template_id'] = $id, get_post_meta($action_id,
 * '_tmgmt_action_contract_template_id', true) must return the same value (as
 * integer, since absint() is used).
 *
 * **Validates: Requirements 3.6**
 */
class ContractTemplateMetaRoundTripTest extends \PHPUnit\Framework\TestCase
{
    use TestTrait;

    private TMGMT_Action_Post_Type $sut;

    protected function setUp(): void
    {
        global $test_post_meta_store;
        $test_post_meta_store = [];

        $this->sut = new TMGMT_Action_Post_Type();
    }

    protected function tearDown(): void
    {
        $_POST = [];
    }

    /**
     * Property 12: Random positive integer IDs round-trip through save_meta_boxes() /
     * get_post_meta() for the _tmgmt_action_contract_template_id meta key.
     */
    public function testTemplateIdMetaRoundTrip(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generator\pos()
            )
            ->then(function (int $templateId): void {
                global $test_post_meta_store;
                $test_post_meta_store = [];

                $actionId = 42;

                $_POST = [
                    'tmgmt_action_nonce'                  => 'test_nonce',
                    'tmgmt_action_type'                   => 'contract_generation',
                    'tmgmt_action_contract_template_id'   => $templateId,
                ];

                $this->sut->save_meta_boxes($actionId);

                $stored = get_post_meta($actionId, '_tmgmt_action_contract_template_id', true);

                $this->assertSame(
                    absint($templateId),
                    $stored,
                    "Template ID {$templateId} did not round-trip correctly through save_meta_boxes()"
                );

                $_POST = [];
            });
    }
}
