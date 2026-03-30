<?php
// Feature: veranstalter-cpt, Property 10: Meta-Key Präfix Invariante

/**
 * Property-Based Test: Meta-Key Präfix Invariante
 *
 * All meta keys defined by the Veranstalter CPT must start with `_tmgmt_veranstalter_`.
 *
 * **Validates: Requirements 7.4**
 */
class MetaKeyPrefixInvariantTest extends \PHPUnit\Framework\TestCase
{
    private const META_KEY_PREFIX = '_tmgmt_veranstalter_';

    /**
     * All meta keys defined by the Veranstalter CPT as specified in the design document.
     */
    private const VERANSTALTER_META_KEYS = [
        '_tmgmt_veranstalter_street',
        '_tmgmt_veranstalter_number',
        '_tmgmt_veranstalter_zip',
        '_tmgmt_veranstalter_city',
        '_tmgmt_veranstalter_country',
        '_tmgmt_veranstalter_contacts',
        '_tmgmt_veranstalter_locations',
    ];

    /**
     * Property 10: Every meta key defined by the Veranstalter CPT must start
     * with the prefix `_tmgmt_veranstalter_`.
     *
     * **Validates: Requirements 7.4**
     */
    public function testAllMetaKeysStartWithPrefix(): void
    {
        foreach (self::VERANSTALTER_META_KEYS as $metaKey) {
            $this->assertStringStartsWith(
                self::META_KEY_PREFIX,
                $metaKey,
                "Meta key '{$metaKey}' does not start with required prefix '" . self::META_KEY_PREFIX . "'"
            );
        }
    }

    /**
     * Property 10 (completeness): The set of known meta keys is not empty.
     *
     * **Validates: Requirements 7.4**
     */
    public function testMetaKeySetIsNotEmpty(): void
    {
        $this->assertNotEmpty(
            self::VERANSTALTER_META_KEYS,
            'The set of Veranstalter meta keys must not be empty'
        );
    }

    /**
     * Property 10 (uniqueness): All meta keys are unique — no duplicates.
     *
     * **Validates: Requirements 7.4**
     */
    public function testMetaKeysAreUnique(): void
    {
        $this->assertSame(
            count(self::VERANSTALTER_META_KEYS),
            count(array_unique(self::VERANSTALTER_META_KEYS)),
            'Meta keys must be unique — duplicates detected'
        );
    }
}
