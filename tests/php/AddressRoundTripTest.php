<?php
// Feature: veranstalter-cpt, Property 1: Adressdaten Round-Trip

use Eris\Generator;
use Eris\TestTrait;

/**
 * Property-Based Test: Adressdaten Round-Trip
 *
 * For arbitrary valid address data (street, number, zip, city, country),
 * when saved via save_meta_boxes, get_post_meta must return the same (sanitized) value.
 *
 * **Validates: Requirements 2.3, 2.4**
 */
class AddressRoundTripTest extends \PHPUnit\Framework\TestCase
{
    use TestTrait;

    private TMGMT_Veranstalter_Post_Type $sut;

    protected function setUp(): void
    {
        global $test_post_meta_store;
        $test_post_meta_store = array();
        $_POST = array();
        $this->sut = new TMGMT_Veranstalter_Post_Type();
    }

    protected function tearDown(): void
    {
        $_POST = array();
    }

    /**
     * Property 1: Random address data round-trips through save and load.
     */
    public function testAddressFieldsRoundTrip(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generator\string(),
                Generator\string(),
                Generator\string(),
                Generator\string(),
                Generator\string()
            )
            ->then(function (string $street, string $number, string $zip, string $city, string $country): void {
                global $test_post_meta_store;
                $test_post_meta_store = array();

                $postId = 42;

                $_POST = array(
                    'tmgmt_veranstalter_meta_nonce' => 'valid',
                    'tmgmt_veranstalter_street'     => $street,
                    'tmgmt_veranstalter_number'     => $number,
                    'tmgmt_veranstalter_zip'        => $zip,
                    'tmgmt_veranstalter_city'       => $city,
                    'tmgmt_veranstalter_country'    => $country,
                );

                $this->sut->save_meta_boxes($postId);

                $fields = array(
                    '_tmgmt_veranstalter_street'  => $street,
                    '_tmgmt_veranstalter_number'  => $number,
                    '_tmgmt_veranstalter_zip'     => $zip,
                    '_tmgmt_veranstalter_city'    => $city,
                    '_tmgmt_veranstalter_country' => $country,
                );

                foreach ($fields as $metaKey => $input) {
                    $expected = sanitize_text_field($input);
                    $actual = get_post_meta($postId, $metaKey, true);
                    $this->assertSame($expected, $actual,
                        "Round-trip failed for {$metaKey}: expected sanitized value");
                }
            });
    }
}
