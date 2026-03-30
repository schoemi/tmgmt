<?php
// Feature: veranstalter-cpt, Property 4: Vertrag-Rolle Validierung

use Eris\Generator;
use Eris\TestTrait;

/**
 * Property-Based Test: Vertrag-Rolle Validierung
 *
 * Validation reports an error if and only if no contact with role 'vertrag' is assigned.
 * Missing 'technik' or 'programm' roles must NOT produce an error.
 *
 * **Validates: Requirements 3.4, 3.5, 3.9**
 */
class ContractRoleValidationTest extends \PHPUnit\Framework\TestCase
{
    use TestTrait;

    private TMGMT_Veranstalter_Post_Type $sut;

    protected function setUp(): void
    {
        global $test_post_meta_store, $test_transient_store;
        $test_post_meta_store = array();
        $test_transient_store = array();
        $_POST = array();
        $this->sut = new TMGMT_Veranstalter_Post_Type();
    }

    protected function tearDown(): void
    {
        $_POST = array();
    }

    /**
     * Property 4: When no vertrag contact is assigned, a transient warning is set.
     */
    public function testMissingVertragSetsTransient(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generator\elements(0, 1, 42, 99, 200),
                Generator\elements(0, 2, 55, 100, 300)
            )
            ->then(function (int $technikId, int $programmId): void {
                global $test_post_meta_store, $test_transient_store;
                $test_post_meta_store = array();
                $test_transient_store = array();

                $postId = 42;

                $_POST = array(
                    'tmgmt_veranstalter_meta_nonce' => 'valid',
                    // No vertrag contact
                );
                if ($technikId > 0) {
                    $_POST['tmgmt_veranstalter_contact_technik'] = (string) $technikId;
                }
                if ($programmId > 0) {
                    $_POST['tmgmt_veranstalter_contact_programm'] = (string) $programmId;
                }

                $this->sut->save_meta_boxes($postId);

                $transientKey = 'tmgmt_veranstalter_missing_contract_' . $postId;
                $this->assertTrue(
                    get_transient($transientKey) === true,
                    'Transient should be set when vertrag role is missing'
                );
            });
    }

    /**
     * Property 4: When a vertrag contact IS assigned, no transient warning is set.
     */
    public function testPresentVertragClearsTransient(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generator\elements(1, 10, 42, 99, 200, 500),
                Generator\elements(0, 2, 55, 100, 300),
                Generator\elements(0, 3, 77, 150, 400)
            )
            ->then(function (int $vertragId, int $technikId, int $programmId): void {
                global $test_post_meta_store, $test_transient_store;
                $test_post_meta_store = array();
                $test_transient_store = array();

                $postId = 42;

                $_POST = array(
                    'tmgmt_veranstalter_meta_nonce'          => 'valid',
                    'tmgmt_veranstalter_contact_vertrag'     => (string) $vertragId,
                );
                if ($technikId > 0) {
                    $_POST['tmgmt_veranstalter_contact_technik'] = (string) $technikId;
                }
                if ($programmId > 0) {
                    $_POST['tmgmt_veranstalter_contact_programm'] = (string) $programmId;
                }

                $this->sut->save_meta_boxes($postId);

                $transientKey = 'tmgmt_veranstalter_missing_contract_' . $postId;
                $this->assertFalse(
                    get_transient($transientKey),
                    'Transient should NOT be set when vertrag role is present'
                );
            });
    }
}
