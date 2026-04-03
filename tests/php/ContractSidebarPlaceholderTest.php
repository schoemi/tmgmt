<?php
// Feature: contract-generation, Property 11: Sidebar-Panel enthält alle registrierten Platzhalter

use Eris\Generator;
use Eris\TestTrait;

if (!defined('TMGMT_PLUGIN_DIR')) {
    define('TMGMT_PLUGIN_DIR', dirname(__DIR__, 2) . '/');
}
if (!defined('TMGMT_PLUGIN_URL')) {
    define('TMGMT_PLUGIN_URL', 'http://example.com/wp-content/plugins/tmgmt/');
}
if (!defined('TMGMT_VERSION')) {
    define('TMGMT_VERSION', '0.0.0-test');
}

require_once dirname(__DIR__, 2) . '/includes/class-placeholder-parser.php';
require_once dirname(__DIR__, 2) . '/includes/post-types/class-contract-template-post-type.php';

/**
 * Property-Based Test: Sidebar-Panel enthält alle registrierten Platzhalter
 *
 * For any call to TMGMT_Placeholder_Parser::get_placeholders(), all returned
 * placeholder keys must be present in the data passed via wp_localize_script
 * to tmgmtContractEditor.placeholders.
 *
 * **Validates: Requirements 1.6**
 */
class ContractSidebarPlaceholderTest extends \PHPUnit\Framework\TestCase
{
    use TestTrait;

    private TMGMT_Contract_Template_Post_Type $sut;

    protected function setUp(): void
    {
        global $test_localized_scripts;
        $test_localized_scripts = array();

        // Simulate get_current_screen() returning the contract template screen
        // so enqueue_editor_assets() does not bail out early.
        // We override get_current_screen in this test via a global flag.
        $GLOBALS['test_current_screen_post_type'] = 'tmgmt_contract_tpl';

        $this->sut = new TMGMT_Contract_Template_Post_Type();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['test_current_screen_post_type']);
    }

    /**
     * Property 11: Every key from get_placeholders() must appear in the
     * placeholders array passed to wp_localize_script.
     *
     * Since the placeholder list is static, we iterate over all keys and
     * verify each one is present — with 100 iterations to satisfy the eris
     * requirement.
     */
    public function testAllPlaceholderKeysArePresentInLocalizedScriptData(): void
    {
        $allPlaceholders = TMGMT_Placeholder_Parser::get_placeholders();
        $allKeys         = array_keys($allPlaceholders);

        $this
            ->limitTo(100)
            ->forAll(
                Generator\elements(...$allKeys)
            )
            ->then(function (string $placeholderKey) use ($allPlaceholders): void {
                global $test_localized_scripts;

                // Reset and re-invoke so each iteration is independent
                $test_localized_scripts = array();
                $this->sut->enqueue_editor_assets();

                $this->assertArrayHasKey(
                    'tmgmt-contract-template-editor',
                    $test_localized_scripts,
                    'wp_localize_script was not called for handle "tmgmt-contract-template-editor"'
                );

                $this->assertArrayHasKey(
                    'tmgmtContractEditor',
                    $test_localized_scripts['tmgmt-contract-template-editor'],
                    'Object name "tmgmtContractEditor" was not passed to wp_localize_script'
                );

                $localized = $test_localized_scripts['tmgmt-contract-template-editor']['tmgmtContractEditor'];

                $this->assertArrayHasKey(
                    'placeholders',
                    $localized,
                    'Key "placeholders" is missing from the localized tmgmtContractEditor data'
                );

                $this->assertArrayHasKey(
                    $placeholderKey,
                    $localized['placeholders'],
                    "Placeholder key '$placeholderKey' is missing from the localized placeholders data"
                );

                $this->assertSame(
                    $allPlaceholders[$placeholderKey],
                    $localized['placeholders'][$placeholderKey],
                    "Label for placeholder '$placeholderKey' does not match get_placeholders()"
                );
            });
    }
}
