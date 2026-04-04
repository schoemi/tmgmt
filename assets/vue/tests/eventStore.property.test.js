// Feature: reactive-dashboard, Property 6: Optimistisches Update bei Drag & Drop
// Validates: Requirements 5.2

import { describe, it, beforeEach } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import fc from 'fast-check'
import { createTestableStore } from '../stores/eventStore.js'

describe('Property 6: Optimistisches Update bei Drag & Drop', () => {
    beforeEach(() => {
        setActivePinia(createPinia())
    })

    it('board.events spiegelt neuen Status wider, bevor die API antwortet', async () => {
        await fc.assert(
            fc.asyncProperty(
                fc.record({
                    id: fc.integer({ min: 1, max: 10000 }),
                    oldStatus: fc.string({ minLength: 1, maxLength: 20 }),
                    newStatus: fc.string({ minLength: 1, maxLength: 20 }),
                }),
                async ({ id, oldStatus, newStatus }) => {
                    // Fresh Pinia for each iteration
                    setActivePinia(createPinia())

                    // Mock API: post() never resolves (simulates in-flight request)
                    const mockApi = {
                        get: () => Promise.resolve({ columns: [], events: [] }),
                        post: () => new Promise(() => {}), // never resolves
                    }

                    const useStore = createTestableStore(mockApi)
                    const store = useStore()

                    // Set up board with an event having oldStatus
                    store.board.events = [{ id, title: 'Test Event', status: oldStatus }]

                    // Call updateEventStatus WITHOUT awaiting
                    store.updateEventStatus(id, newStatus)

                    // Immediately check that the status is already updated (optimistic)
                    const event = store.board.events.find(e => e.id === id)
                    return event !== undefined && event.status === newStatus
                }
            ),
            { numRuns: 100 }
        )
    })
})

// Feature: reactive-dashboard, Property 7: State-Revert bei API-Fehler
// Validates: Requirements 5.3

describe('Property 7: State-Revert bei API-Fehler', () => {
    it('event.status wird auf oldStatus zurückgesetzt wenn API-Aufruf fehlschlägt', async () => {
        await fc.assert(
            fc.asyncProperty(
                fc.record({
                    id: fc.integer({ min: 1, max: 10000 }),
                    oldStatus: fc.string({ minLength: 1, maxLength: 20 }),
                    newStatus: fc.string({ minLength: 1, maxLength: 20 }),
                }),
                async ({ id, oldStatus, newStatus }) => {
                    // Fresh Pinia for each iteration
                    setActivePinia(createPinia())

                    // Mock API: post() rejects (simulates HTTP >= 400 error)
                    const mockApi = {
                        get: () => Promise.resolve({ columns: [], events: [] }),
                        post: () => Promise.reject({ status: 422, message: 'Unprocessable Entity', data: null }),
                    }

                    const useStore = createTestableStore(mockApi)
                    const store = useStore()

                    // Set up board with an event having oldStatus
                    store.board.events = [{ id, title: 'Test Event', status: oldStatus }]

                    // Call updateEventStatus and await it (API will reject)
                    await store.updateEventStatus(id, newStatus)

                    // After the await, status must be reverted to oldStatus
                    const event = store.board.events.find(e => e.id === id)
                    return event !== undefined && event.status === oldStatus
                }
            ),
            { numRuns: 100 }
        )
    })
})

// Feature: reactive-dashboard, Property 17: Window-Event bei API-Update
// Validates: Requirements 9.4

describe('Property 17: Window-Event bei API-Update', () => {
    it('tmgmt:event-updated wird auf window dispatcht nach erfolgreichem API-Update', async () => {
        await fc.assert(
            fc.asyncProperty(
                fc.integer({ min: 1 }),
                async (eventId) => {
                    // Fresh Pinia for each iteration
                    setActivePinia(createPinia())

                    const dispatched = []
                    const handler = (e) => dispatched.push(e.detail)
                    window.addEventListener('tmgmt:event-updated', handler)

                    try {
                        // Mock API: post() resolves successfully
                        const mockApi = {
                            get: () => Promise.resolve({ columns: [], events: [] }),
                            post: () => Promise.resolve({ success: true }),
                        }

                        const useStore = createTestableStore(mockApi)
                        const store = useStore()

                        // Set up board with the event
                        store.board.events = [{ id: eventId, title: 'Test Event', status: 'old-status' }]

                        await store.updateEventStatus(eventId, 'new-status')

                        return dispatched.some(d => d.id === eventId)
                    } finally {
                        // Clean up event listener to avoid cross-contamination
                        window.removeEventListener('tmgmt:event-updated', handler)
                    }
                }
            ),
            { numRuns: 100 }
        )
    })
})
