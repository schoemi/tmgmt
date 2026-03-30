<?php
// Feature: veranstalter-cpt, Property 3: Kontaktzuordnung Round-Trip

use Eris\Generator;
use Eris\TestTrait;

/**
 * Property-Based Test: Kontaktzuordnung Round-Trip
 *
 * Random contact assignments (arrays of {contact_id, role}) saved and loaded
 * must produce an equivalent array.
 *
 * **Validates: Requirements 3.6, 3.7**
 */
class ContactAssignmentRoundTripTest extends \PHPUnit\Framework\TestCase
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
     * Property 3: Contact assignments round-trip through save and load.
     */
    public function testContactAssignmentRoundTrip(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generator\elements(0, 1, 10, 42, 99, 123, 456, 789),
                Generator\elements(0, 2, 11, 55, 100, 200, 300, 500),
                Generator\elements(0, 3, 12, 77, 150, 250, 350, 600)
            )
            ->then(function (int $vertragId, int $technikId, int $programmId): void {
                global $test_post_meta_store, $test_transient_store;
                $test_post_meta_store = array();
                $test_transient_store = array();

                $postId = 42;

                $_POST = array(
                    'tmgmt_veranstalter_meta_nonce' => 'valid',
                );

                if ($vertragId > 0) {
                    $_POST['tmgmt_veranstalter_contact_vertrag'] = (string) $vertragId;
                }
                if ($technikId > 0) {
                    $_POST['tmgmt_veranstalter_contact_technik'] = (string) $technikId;
                }
                if ($programmId > 0) {
                    $_POST['tmgmt_veranstalter_contact_programm'] = (string) $programmId;
                }

                $this->sut->save_meta_boxes($postId);

                $saved = get_post_meta($postId, '_tmgmt_veranstalter_contacts', true);
                $this->assertIsArray($saved);

                // Build expected
                $expected = array();
                if ($vertragId > 0) {
                    $expected[] = array('contact_id' => $vertragId, 'role' => 'vertrag');
                }
                if ($technikId > 0) {
                    $expected[] = array('contact_id' => $technikId, 'role' => 'technik');
                }
                if ($programmId > 0) {
                    $expected[] = array('contact_id' => $programmId, 'role' => 'programm');
                }

                $this->assertEquals($expected, $saved,
                    'Contact assignments did not round-trip correctly');
            });
    }
}
