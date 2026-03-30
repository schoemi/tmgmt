<?php
// Feature: veranstalter-cpt, Property 5: Veranstaltungsorte Round-Trip

use Eris\Generator;
use Eris\TestTrait;

/**
 * Property-Based Test: Veranstaltungsorte Round-Trip
 *
 * Random arrays of location IDs saved and loaded must produce the same array.
 *
 * **Validates: Requirements 4.4**
 */
class LocationsRoundTripTest extends \PHPUnit\Framework\TestCase
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
     * Property 5: Location ID arrays round-trip through save and load.
     */
    public function testLocationIdsRoundTrip(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generator\seq(Generator\pos())
            )
            ->then(function (array $locationIds): void {
                global $test_post_meta_store, $test_transient_store;
                $test_post_meta_store = array();
                $test_transient_store = array();

                $postId = 42;

                // Convert to string values as they'd come from form
                $stringIds = array_map('strval', $locationIds);

                $_POST = array(
                    'tmgmt_veranstalter_meta_nonce'  => 'valid',
                    'tmgmt_veranstalter_locations'   => $stringIds,
                );

                $this->sut->save_meta_boxes($postId);

                $saved = get_post_meta($postId, '_tmgmt_veranstalter_locations', true);
                $this->assertIsArray($saved);

                // Expected: intval of each positive ID
                $expected = array_values(array_filter(
                    array_map('intval', $locationIds),
                    function ($id) { return $id > 0; }
                ));

                $this->assertEquals($expected, $saved,
                    'Location IDs did not round-trip correctly');
            });
    }
}
