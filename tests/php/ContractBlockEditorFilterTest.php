<?php
// Feature: contract-generation, Property 10: Block-Editor-Filter ist exklusiv für tmgmt_contract_tpl

use Eris\Generator;
use Eris\TestTrait;

// Define plugin dir constant if not already set
if (!defined('TMGMT_PLUGIN_DIR')) {
    define('TMGMT_PLUGIN_DIR', dirname(__DIR__, 2) . '/');
}

require_once dirname(__DIR__, 2) . '/includes/post-types/class-contract-template-post-type.php';

/**
 * Property-Based Test: Block-Editor-Filter ist exklusiv für tmgmt_contract_tpl
 *
 * For any post type string, enable_block_editor_for_cpt() must:
 *   - return true  when $post_type === 'tmgmt_contract_tpl' (regardless of $use_block_editor)
 *   - return the original $use_block_editor value unchanged for every other post type string
 *
 * **Validates: Requirements 1.2, 1.3**
 */
class ContractBlockEditorFilterTest extends \PHPUnit\Framework\TestCase
{
    use TestTrait;

    private TMGMT_Contract_Template_Post_Type $sut;

    protected function setUp(): void
    {
        $this->sut = new TMGMT_Contract_Template_Post_Type();
    }

    /**
     * Property 10a: For any random post type that is NOT 'tmgmt_contract_tpl',
     * the filter returns the original $use_block_editor value unchanged.
     */
    public function testNonContractTemplatePostTypesPassThroughUseBlockEditor(): void
    {
        // Generate strings that are guaranteed to differ from the target post type
        $otherPostTypes = [
            'post', 'page', 'attachment', 'tmgmt_event', 'tmgmt_veranstalter',
            'tmgmt_action', 'tmgmt_email_template', 'tmgmt_invoice', 'custom_type',
            'tmgmt_contract_templat',   // one char short
            'tmgmt_contract_tpl_', // one char extra
            '',
        ];

        $this
            ->limitTo(100)
            ->forAll(
                Generator\elements(...$otherPostTypes),
                Generator\bool()
            )
            ->then(function (string $postType, bool $useBlockEditor): void {
                $result = $this->sut->enable_block_editor_for_cpt($useBlockEditor, $postType);

                $this->assertSame(
                    $useBlockEditor,
                    $result,
                    "For post type '$postType', expected enable_block_editor_for_cpt to return "
                    . ($useBlockEditor ? 'true' : 'false')
                    . " (the original value), but got "
                    . ($result ? 'true' : 'false')
                );
            });
    }

    /**
     * Property 10b: For 'tmgmt_contract_tpl', the filter always returns true,
     * regardless of the incoming $use_block_editor value.
     */
    public function testContractTemplatePostTypeAlwaysEnablesBlockEditor(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generator\bool()
            )
            ->then(function (bool $useBlockEditor): void {
                $result = $this->sut->enable_block_editor_for_cpt(
                    $useBlockEditor,
                    'tmgmt_contract_tpl'
                );

                $this->assertTrue(
                    $result,
                    "For post type 'tmgmt_contract_tpl', enable_block_editor_for_cpt must always return true, "
                    . "but returned false when \$use_block_editor was " . ($useBlockEditor ? 'true' : 'false')
                );
            });
    }
}
