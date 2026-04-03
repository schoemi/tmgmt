<?php
/**
 * Unit Test: Aktionstyp `contract_generation` in TMGMT_Action_Post_Type
 *
 * test_contract_generation_action_type_exists() — prüft, dass der Typ
 * `contract_generation` in der gerenderten Metabox vorkommt.
 *
 * Requirements: 3.1
 */

// Stub TMGMT_Event_Status if not already defined
if (!class_exists('TMGMT_Event_Status')) {
    class TMGMT_Event_Status {
        const CONTRACT_SENT = 'contract_sent';
        public static function get_all_statuses() {
            return array(
                'contract_sent'   => 'Vertrag versendet',
                'contract_signed' => 'Vertrag unterschrieben',
            );
        }
    }
}

if (!function_exists('wp_dropdown_pages')) {
    function wp_dropdown_pages($args = '') {
        echo '<select name="' . esc_attr($args['name'] ?? '') . '"></select>';
        return '';
    }
}

if (!function_exists('checked')) {
    function checked($checked, $current = true, $echo = true) {
        $result = ($checked == $current) ? ' checked="checked"' : '';
        if ($echo) echo $result;
        return $result;
    }
}

// Define plugin dir constant if not already set
if (!defined('TMGMT_PLUGIN_DIR')) {
    define('TMGMT_PLUGIN_DIR', dirname(__DIR__, 2) . '/');
}

require_once dirname(__DIR__, 2) . '/includes/post-types/class-action-post-type.php';
require_once dirname(__DIR__, 2) . '/includes/post-types/class-contract-template-post-type.php';

class ContractActionTypeTest extends \PHPUnit\Framework\TestCase
{
    private TMGMT_Action_Post_Type $sut;

    protected function setUp(): void
    {
        global $test_post_meta_store;
        $test_post_meta_store = array();

        $this->sut = new TMGMT_Action_Post_Type();
    }

    /**
     * Verifies that the rendered settings metabox contains the
     * `contract_generation` option value (Requirement 3.1).
     */
    public function test_contract_generation_action_type_exists(): void
    {
        $post = (object) array('ID' => 1);

        ob_start();
        $this->sut->render_settings_box($post);
        $html = ob_get_clean();

        $this->assertStringContainsString(
            'value="contract_generation"',
            $html,
            'The select should contain an option with value "contract_generation"'
        );

        $this->assertStringContainsString(
            'Vertragsgenerierung',
            $html,
            'The option label "Vertragsgenerierung" should be present'
        );
    }

    /**
     * Verifies that the contract template dropdown with name="tmgmt_action_contract_template_id"
     * is present in the rendered metabox HTML (Requirement 3.2).
     */
    public function test_contract_template_dropdown_present_in_metabox(): void
    {
        $post = (object) array('ID' => 1);

        ob_start();
        $this->sut->render_settings_box($post);
        $html = ob_get_clean();

        $this->assertStringContainsString(
            'name="tmgmt_action_contract_template_id"',
            $html,
            'The metabox should contain a select with name="tmgmt_action_contract_template_id"'
        );
    }

    /**
     * Verifies that the `.tmgmt-contract-row` is present in the rendered HTML
     * (Requirement 3.2 — email template row for contract_generation).
     */
    public function test_contract_row_is_present_in_metabox(): void
    {
        $post = (object) array('ID' => 1);

        ob_start();
        $this->sut->render_settings_box($post);
        $html = ob_get_clean();

        $this->assertStringContainsString(
            'tmgmt-contract-row',
            $html,
            'The metabox should contain a .tmgmt-contract-row table row'
        );
    }

    /**
     * Verifies that save_meta_boxes() persists _tmgmt_action_type = 'contract_generation'
     * (Requirement 3.4).
     */
    public function test_save_meta_boxes_persists_contract_generation_type(): void
    {
        global $test_post_meta_store;
        $test_post_meta_store = array();

        $_POST['tmgmt_action_nonce']          = 'test_nonce';
        $_POST['tmgmt_action_type']           = 'contract_generation';
        $_POST['tmgmt_action_email_template_id'] = '42';

        $this->sut->save_meta_boxes(99);

        $this->assertSame(
            'contract_generation',
            $test_post_meta_store[99]['_tmgmt_action_type'],
            '_tmgmt_action_type should be saved as "contract_generation"'
        );

        $this->assertSame(
            '42',
            $test_post_meta_store[99]['_tmgmt_action_email_template_id'],
            '_tmgmt_action_email_template_id should be saved correctly'
        );

        // Clean up superglobal
        unset($_POST['tmgmt_action_nonce'], $_POST['tmgmt_action_type'], $_POST['tmgmt_action_email_template_id']);
    }
}
