// Feature: reactive-dashboard, Property 5: Kanban rendert alle Spalten und Karten vollständig
// Validates: Requirements 5.1, 5.5

import { describe, it, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import fc from 'fast-check'

// Shared mutable board state used by the mocked store
let _mockBoard = { columns: [], events: [] }
let _mockLoading = false
let _mockError = null
let _mockLoadBoard = vi.fn()

// Mock the eventStore module so KanbanWidget uses our controlled store
vi.mock('../stores/eventStore.js', () => ({
    useEventStore: () => ({
        get board() { return _mockBoard },
        set board(v) { _mockBoard = v },
        get loading() { return _mockLoading },
        get error() { return _mockError },
        loadBoard: _mockLoadBoard,
        updateEventStatus: vi.fn(),
        revertEventStatus: vi.fn(),
        updateEventField: vi.fn(),
    }),
    createTestableStore: vi.fn(),
}))

// Import KanbanWidget AFTER the mock is set up
import KanbanWidget from '../components/KanbanWidget.vue'

beforeEach(() => {
    window.tmgmtData = { status_requirements: {}, field_map: {}, statuses: {} }
    _mockLoadBoard = vi.fn().mockResolvedValue(undefined)
    _mockLoading = false
    _mockError = null
})

describe('Property 5: Kanban rendert alle Spalten und Karten vollständig', () => {
    it('jede Spalte und jede Karte ist im gerenderten Board vorhanden', async () => {
        await fc.assert(
            fc.asyncProperty(
                fc.record({
                    columns: fc.uniqueArray(
                        fc.record({
                            id: fc.integer({ min: 1, max: 9999 }),
                            title: fc.string({ minLength: 1, maxLength: 40 }),
                            color: fc.hexaString({ minLength: 6, maxLength: 6 }).map(h => '#' + h),
                            statuses: fc.uniqueArray(
                                fc.string({ minLength: 1, maxLength: 20 }),
                                { minLength: 1, maxLength: 3 }
                            ),
                        }),
                        { minLength: 1, maxLength: 5, selector: col => col.id }
                    ),
                }),
                fc.context(),
                async ({ columns }, ctx) => {
                    // Build events: one event per column using the first status
                    const events = columns.map((col, idx) => ({
                        id: idx + 1,
                        title: `Event-${col.id}-${idx}`,
                        status: col.statuses[0],
                        date: '2024-06-01',
                        time: '20:00',
                        city: `Stadt-${idx}`,
                    }))

                    // Pre-populate the shared mock board
                    _mockBoard = { columns, events }
                    _mockLoading = false
                    _mockError = null
                    _mockLoadBoard = vi.fn().mockResolvedValue(undefined)

                    const pinia = createPinia()
                    setActivePinia(pinia)

                    const wrapper = mount(KanbanWidget, {
                        global: {
                            plugins: [pinia],
                            stubs: { MissingFieldsModal: true },
                        },
                    })

                    // Wait for onMounted and re-renders
                    await wrapper.vm.$nextTick()
                    await wrapper.vm.$nextTick()

                    // Assert: each column has a .tmgmt-column with the correct title
                    const columnElements = wrapper.findAll('.tmgmt-column')
                    for (const col of columns) {
                        const found = columnElements.some(el => {
                            const titleEl = el.find('.tmgmt-column__title')
                            return titleEl.exists() && titleEl.text().trim() === col.title.trim()
                        })
                        if (!found) {
                            ctx.log(`Column not found: "${col.title}" (found ${columnElements.length} columns)`)
                            wrapper.unmount()
                            return false
                        }
                    }

                    // Assert: each event has a .tmgmt-card with the event title
                    const cardElements = wrapper.findAll('.tmgmt-card')
                    for (const event of events) {
                        const found = cardElements.some(el => {
                            const titleEl = el.find('.tmgmt-card__title')
                            return titleEl.exists() && titleEl.text().trim() === event.title.trim()
                        })
                        if (!found) {
                            ctx.log(`Card not found: "${event.title}" (found ${cardElements.length} cards)`)
                            wrapper.unmount()
                            return false
                        }
                    }

                    wrapper.unmount()
                    return true
                }
            ),
            { numRuns: 100 }
        )
    })
})
