<?php
/**
 * Unit Test: CPT-Registrierung hat `show_in_rest`
 *
 * test_cpt_registration_has_show_in_rest() — prüft, dass der registrierte CPT
 * `tmgmt_contract_tpl` die Argumente show_in_rest: true, show_ui: true,
 * public: false und supports: ['title', 'editor', 'custom-fields'] hat.
 *
 * Requirements: 1.1
 */

// Define plugin dir constant if not already set
if (!defined('TMGMT_PLUGIN_DIR')) {
    define('TMGMT_PLUGIN_DIR', dirname(__DIR__, 2) . '/');
}

require_once dirname(__DIR__, 2) . '/includes/post-types/class-contract-template-post-type.php';

class ContractCptRegistrationTest extends \PHPUnit\Framework\TestCase
{
    /** @var object|null The registered CPT object */
    private ?object $cptObject = null;

    protected function setUp(): void
    {
        global $test_registered_post_types;
        $test_registered_post_types = array();

        $sut = new TMGMT_Contract_Template_Post_Type();
        $sut->register_post_type();

        $this->cptObject = get_post_type_object('tmgmt_contract_tpl');
    }

    /**
     * Verifies that the CPT is registered with show_in_rest = true (Req. 1.1).
     * Required for Gutenberg / the Block Editor to work.
     */
    public function test_cpt_registration_has_show_in_rest(): void
    {
        $this->assertNotNull(
            $this->cptObject,
            'get_post_type_object("tmgmt_contract_tpl") must not return null'
        );

        $this->assertTrue(
            (bool) ($this->cptObject->show_in_rest ?? false),
            'CPT tmgmt_contract_tpl must be registered with show_in_rest = true'
        );
    }

    /**
     * Verifies show_ui = true (Req. 1.1).
     */
    public function test_cpt_registration_has_show_ui(): void
    {
        $this->assertNotNull($this->cptObject);

        $this->assertTrue(
            (bool) ($this->cptObject->show_ui ?? false),
            'CPT tmgmt_contract_tpl must be registered with show_ui = true'
        );
    }

    /**
     * Verifies public = false (Req. 1.1).
     */
    public function test_cpt_registration_is_not_public(): void
    {
        $this->assertNotNull($this->cptObject);

        $this->assertFalse(
            (bool) ($this->cptObject->public ?? true),
            'CPT tmgmt_contract_tpl must be registered with public = false'
        );
    }

    /**
     * Verifies supports includes 'title', 'editor', 'custom-fields' (Req. 1.1).
     */
    public function test_cpt_registration_supports_required_features(): void
    {
        $this->assertNotNull($this->cptObject);

        $supports = (array) ($this->cptObject->supports ?? array());

        $this->assertContains('title',         $supports, 'CPT supports must include "title"');
        $this->assertContains('editor',        $supports, 'CPT supports must include "editor"');
        $this->assertContains('custom-fields', $supports, 'CPT supports must include "custom-fields"');
    }
}
