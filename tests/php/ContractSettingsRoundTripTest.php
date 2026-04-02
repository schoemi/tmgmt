<?php
// Feature: contract-generation, Property 9: Einstellungen-Round-Trip

use Eris\Generator;
use Eris\TestTrait;

/**
 * Property-Based Test: Einstellungen-Round-Trip
 *
 * For arbitrary valid positive integer IDs, when saved via update_option,
 * get_option must return the same value for both contract settings options.
 *
 * **Validates: Requirements 2.3**
 */
class ContractSettingsRoundTripTest extends \PHPUnit\Framework\TestCase
{
    use TestTrait;

    protected function setUp(): void
    {
        global $test_options_store;
        $test_options_store = array();
    }

    /**
     * Property 9: Random positive integer IDs round-trip through update_option / get_option.
     */
    public function testSettingsRoundTrip(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generator\pos(),
                Generator\pos()
            )
            ->then(function (int $signatureId, int $notificationUserId): void {
                global $test_options_store;
                $test_options_store = array();

                update_option('tmgmt_contract_signature_id', $signatureId);
                update_option('tmgmt_contract_notification_user_id', $notificationUserId);

                $this->assertSame(
                    $signatureId,
                    get_option('tmgmt_contract_signature_id'),
                    'tmgmt_contract_signature_id did not round-trip correctly'
                );

                $this->assertSame(
                    $notificationUserId,
                    get_option('tmgmt_contract_notification_user_id'),
                    'tmgmt_contract_notification_user_id did not round-trip correctly'
                );
            });
    }
}
