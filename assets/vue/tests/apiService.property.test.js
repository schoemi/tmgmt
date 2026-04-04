// Feature: reactive-dashboard, Property 12: API-Service setzt Nonce und Basis-URL
// Validates: Requirements 7.1, 7.2

import { describe, it } from 'vitest'
import fc from 'fast-check'
import { createApiService } from '../services/apiService.js'

// Feature: reactive-dashboard, Property 13: HTTP-Fehler als strukturiertes Objekt
// Validates: Requirements 7.3

describe('Property 12: API-Service setzt Nonce und Basis-URL', () => {
    it('GET: X-WP-Nonce-Header gesetzt und URL beginnt mit apiUrl', async () => {
        await fc.assert(
            fc.asyncProperty(
                fc.record({
                    path: fc.string(),
                    nonce: fc.string(),
                    apiUrl: fc.webUrl(),
                }),
                async ({ path, nonce, apiUrl }) => {
                    const captured = []
                    const service = createApiService({ nonce, apiUrl }, (url, opts) => {
                        captured.push({ url, opts })
                        return Promise.resolve({ ok: true, json: () => ({}) })
                    })
                    await service.get(path)
                    return (
                        captured[0].url.startsWith(apiUrl) &&
                        captured[0].opts.headers['X-WP-Nonce'] === nonce
                    )
                }
            ),
            { numRuns: 100 }
        )
    })

    it('POST: X-WP-Nonce-Header gesetzt und URL beginnt mit apiUrl', async () => {
        await fc.assert(
            fc.asyncProperty(
                fc.record({
                    path: fc.string(),
                    nonce: fc.string(),
                    apiUrl: fc.webUrl(),
                    body: fc.option(fc.object(), { nil: undefined }),
                }),
                async ({ path, nonce, apiUrl, body }) => {
                    const captured = []
                    const service = createApiService({ nonce, apiUrl }, (url, opts) => {
                        captured.push({ url, opts })
                        return Promise.resolve({ ok: true, json: () => ({}) })
                    })
                    await service.post(path, body)
                    return (
                        captured[0].url.startsWith(apiUrl) &&
                        captured[0].opts.headers['X-WP-Nonce'] === nonce
                    )
                }
            ),
            { numRuns: 100 }
        )
    })
})

describe('Property 13: HTTP-Fehler als strukturiertes Objekt', () => {
    it('GET: HTTP >= 400 wirft ApiError mit korrektem status-Feld', async () => {
        await fc.assert(
            fc.asyncProperty(
                fc.integer({ min: 400, max: 599 }),
                async (statusCode) => {
                    const service = createApiService(
                        { nonce: 'x', apiUrl: 'http://x/' },
                        () => Promise.resolve({
                            ok: false,
                            status: statusCode,
                            json: () => Promise.resolve({ message: 'err' }),
                        })
                    )
                    try {
                        await service.get('/test')
                        return false
                    } catch (e) {
                        return e.status === statusCode
                    }
                }
            ),
            { numRuns: 100 }
        )
    })

    it('GET: ApiError enthält message- und data-Felder', async () => {
        await fc.assert(
            fc.asyncProperty(
                fc.integer({ min: 400, max: 599 }),
                fc.string(),
                async (statusCode, errorMessage) => {
                    const service = createApiService(
                        { nonce: 'x', apiUrl: 'http://x/' },
                        () => Promise.resolve({
                            ok: false,
                            status: statusCode,
                            json: () => Promise.resolve({ message: errorMessage, data: { detail: 'extra' } }),
                        })
                    )
                    try {
                        await service.get('/test')
                        return false
                    } catch (e) {
                        return (
                            e.status === statusCode &&
                            typeof e.message === 'string' &&
                            'data' in e
                        )
                    }
                }
            ),
            { numRuns: 100 }
        )
    })
})
