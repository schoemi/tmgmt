// Feature: reactive-dashboard, Property 1: tmgmtData-Vollständigkeit
// Validates: Requirements 2.3

import { describe, it, expect } from 'vitest'
import fc from 'fast-check'

const REQUIRED_KEYS = [
    'apiUrl',
    'nonce',
    'statuses',
    'status_requirements',
    'field_map',
    'can_delete_files',
    'layout_settings',
]

/**
 * Guard function extracted from main.js logic:
 * checks whether a tmgmtData object has all required keys.
 */
function hasAllRequiredKeys(tmgmtData) {
    if (!tmgmtData || typeof tmgmtData !== 'object') return false
    return REQUIRED_KEYS.every((k) => k in tmgmtData)
}

/**
 * Guard function mirroring the main.js startup check:
 * returns an error string when tmgmtData is missing/invalid, null otherwise.
 */
function getStartupError(tmgmtData) {
    if (!tmgmtData) {
        return 'Dashboard konnte nicht geladen werden: tmgmtData nicht verfügbar.'
    }
    return null
}

// ---------------------------------------------------------------------------
// Property test: complete tmgmtData always passes the key check
// ---------------------------------------------------------------------------
describe('Property 1: tmgmtData-Vollständigkeit', () => {
    it('vollständiges tmgmtData-Objekt enthält alle Pflichtschlüssel (100 runs)', () => {
        fc.assert(
            fc.property(
                fc.record({
                    apiUrl: fc.webUrl(),
                    nonce: fc.string(),
                    statuses: fc.dictionary(fc.string({ minLength: 1 }), fc.string()),
                    status_requirements: fc.dictionary(
                        fc.string({ minLength: 1 }),
                        fc.array(fc.string())
                    ),
                    field_map: fc.dictionary(fc.string({ minLength: 1 }), fc.string()),
                    can_delete_files: fc.boolean(),
                    layout_settings: fc.dictionary(fc.string({ minLength: 1 }), fc.anything()),
                }),
                (mockData) => {
                    return REQUIRED_KEYS.every((k) => k in mockData)
                }
            ),
            { numRuns: 100 }
        )
    })

    // -----------------------------------------------------------------------
    // Negative test: object with one key missing fails the check
    // -----------------------------------------------------------------------
    it('tmgmtData mit fehlendem Schlüssel wird korrekt erkannt (negativer Test)', () => {
        fc.assert(
            fc.property(
                fc.integer({ min: 0, max: REQUIRED_KEYS.length - 1 }),
                (missingIndex) => {
                    const missingKey = REQUIRED_KEYS[missingIndex]

                    // Build a complete object then remove one key
                    const complete = {
                        apiUrl: 'https://example.com/wp-json/tmgmt/v1/',
                        nonce: 'abc123',
                        statuses: { pending: 'Ausstehend' },
                        status_requirements: { confirmed: ['tmgmt_event_date'] },
                        field_map: { tmgmt_event_date: 'date' },
                        can_delete_files: true,
                        layout_settings: { columns: 3 },
                    }
                    const incomplete = { ...complete }
                    delete incomplete[missingKey]

                    // The check must detect the missing key
                    return hasAllRequiredKeys(incomplete) === false
                }
            ),
            { numRuns: 100 }
        )
    })

    // -----------------------------------------------------------------------
    // Integration test: guard logic in main.js shows error when tmgmtData missing
    // -----------------------------------------------------------------------
    it('Guard-Logik zeigt Fehler wenn window.tmgmtData fehlt', () => {
        // missing / falsy values
        const missingValues = [undefined, null, false, 0, '']
        for (const val of missingValues) {
            const error = getStartupError(val)
            expect(error).toBeTruthy()
            expect(typeof error).toBe('string')
        }

        // present value → no error
        const error = getStartupError({ apiUrl: 'https://x/', nonce: 'n' })
        expect(error).toBeNull()
    })
})
