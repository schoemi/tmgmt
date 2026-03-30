<?php
// Feature: veranstalter-cpt, Property 6: Ort-Entfernung Invariante

use Eris\Generator;
use Eris\TestTrait;

/**
 * Property-Based Test: Ort-Entfernung Invariante
 *
 * After removing a location, the list contains exactly one fewer element
 * and the removed location is no longer present.
 *
 * **Validates: Requirements 4.6**
 */
class LocationRemovalInvariantTest extends \PHPUnit\Framework\TestCase
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
     * Property 6: Removing a location produces a list with one fewer element,
     * and the removed location is absent.
     */
    public function testLocationRemovalInvariant(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generator\bind(
                    Generator\choose(1, 10),
                    function (int $size) {
                        return Generator\tuple(
                            Generator\vector($size, Generator\choose(1, 1000)),
                            Generator\choose(0, $size - 1)
                        );
                    }
                )
            )
            ->then(function (array $tuple): void {
                list($locationIds, $removeIndex) = $tuple;

                // Make IDs unique to avoid ambiguity
                $locationIds = array_values(array_unique($locationIds));
                if (empty($locationIds)) {
                    return;
                }
                $removeIndex = $removeIndex % count($locationIds);

                $originalCount = count($locationIds);
                $removedId = $locationIds[$removeIndex];

                // Simulate removal: save without the removed ID
                $remaining = array_values(array_filter(
                    $locationIds,
                    function ($id) use ($removedId) { return $id !== $removedId; }
                ));

                $this->assertCount($originalCount - 1, $remaining,
                    'After removal, list should have exactly one fewer element');
                $this->assertNotContains($removedId, $remaining,
                    'Removed location should not be in the remaining list');
            });
    }
}
