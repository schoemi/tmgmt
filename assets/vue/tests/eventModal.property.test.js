// Feature: reactive-dashboard, Property 9: Event-Modal Daten-Round-Trip
// Validates: Requirements 6.1, 6.2

import { describe, it, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import fc from 'fast-check'

// Mock apiService before importing the component
vi.mock('../services/apiService.js', () => {
    const mockGet = vi.fn()
    const mockPost = vi.fn()
    return {
        default: {
            get: mockGet,
            post: mockPost,
        },
        createApiService: vi.fn(),
    }
})

// Import after mock is set up
import EventModal from '../components/EventModal.vue'
import apiService from '../services/apiService.js'

beforeEach(() => {
    window.tmgmtData = {
        statuses: {},
        status_requirements: {},
        field_map: {},
    }
    vi.clearAllMocks()
})

describe('Property 9: Event-Modal Daten-Round-Trip', () => {
    it('alle Formularfelder werden mit API-Werten befüllt', async () => {
        await fc.assert(
            fc.asyncProperty(
                fc.record({
                    title: fc.string({ minLength: 1, maxLength: 60 }),
                    date: fc.date({ min: new Date('2020-01-01'), max: new Date('2030-12-31') })
                        .map(d => d.toISOString().slice(0, 10)),
                    venue_city: fc.string({ minLength: 1, maxLength: 40 }),
                    status: fc.string({ minLength: 1, maxLength: 20 }),
                    venue_name: fc.string({ minLength: 1, maxLength: 60 }),
                    fee: fc.integer({ min: 100, max: 99999 }).map(String),
                }),
                fc.context(),
                async ({ title, date, venue_city, status, venue_name, fee }, ctx) => {
                    // Build the API response matching the structure EventModal expects:
                    // fields.title  ← response.title
                    // fields.date   ← meta.date
                    // fields.venue_city ← meta.venue_city
                    // fields.status ← meta.status
                    // fields.venue_name ← meta.venue_name
                    // fields.fee    ← meta.fee
                    const mockResponse = {
                        title,
                        meta: {
                            date,
                            venue_city,
                            status,
                            venue_name,
                            fee,
                        },
                        logs: [],
                        actions: [],
                    }

                    apiService.get.mockResolvedValueOnce(mockResponse)

                    // Populate statuses so the select renders the option
                    window.tmgmtData = {
                        statuses: { [status]: status },
                        status_requirements: {},
                        field_map: {},
                    }

                    const pinia = createPinia()
                    setActivePinia(pinia)

                    const wrapper = mount(EventModal, {
                        props: { eventId: 1 },
                        global: {
                            plugins: [pinia],
                            stubs: { MissingFieldsModal: true },
                        },
                    })

                    // Wait for onMounted → loadEvent() to complete (async API call)
                    await flushPromises()

                    // Assert: title input (in header)
                    const titleInput = wrapper.find('.tmgmt-event-modal__title-input')
                    if (!titleInput.exists()) {
                        ctx.log('title input not found')
                        wrapper.unmount()
                        return false
                    }
                    if (titleInput.element.value !== title) {
                        ctx.log(`title mismatch: expected "${title}", got "${titleInput.element.value}"`)
                        wrapper.unmount()
                        return false
                    }

                    // Assert: date input
                    const dateInputs = wrapper.findAll('input[type="date"]')
                    const dateInput = dateInputs.find(el => el.element.value === date)
                    if (!dateInput) {
                        ctx.log(`date input not found with value "${date}"`)
                        wrapper.unmount()
                        return false
                    }

                    // Assert: venue_city input
                    const allTextInputs = wrapper.findAll('input[type="text"]')
                    const cityInput = allTextInputs.find(el => el.element.value === venue_city)
                    if (!cityInput) {
                        ctx.log(`venue_city input not found with value "${venue_city}"`)
                        wrapper.unmount()
                        return false
                    }

                    // Assert: status select
                    const statusSelect = wrapper.find('select.tmgmt-form-select')
                    if (!statusSelect.exists()) {
                        ctx.log('status select not found')
                        wrapper.unmount()
                        return false
                    }
                    if (statusSelect.element.value !== status) {
                        ctx.log(`status mismatch: expected "${status}", got "${statusSelect.element.value}"`)
                        wrapper.unmount()
                        return false
                    }

                    wrapper.unmount()
                    return true
                }
            ),
            { numRuns: 100 }
        )
    })
})

// Feature: reactive-dashboard, Property 10: Auto-Save bei Blur
// Validates: Requirements 6.3

describe('Property 10: Auto-Save bei Blur', () => {
    it('POST /events/{id} wird mit geändertem Feld und neuem Wert aufgerufen', async () => {
        const fieldNames = ['title', 'venue_city', 'fee', 'content', 'arrival_notes']

        await fc.assert(
            fc.asyncProperty(
                fc.constantFrom(...fieldNames),
                fc.string({ minLength: 1, maxLength: 50 }),
                async (fieldName, newValue) => {
                    // Build a minimal mock response so EventModal loads successfully
                    const mockResponse = {
                        title: 'Test Event',
                        meta: {
                            date: '2024-06-01',
                            venue_city: 'Berlin',
                            status: 'anfrage',
                            venue_name: 'Test Venue',
                            fee: '500',
                            content: 'Some notes',
                            arrival_notes: 'Arrive early',
                        },
                        logs: [],
                        actions: [],
                    }

                    apiService.get.mockResolvedValueOnce(mockResponse)
                    apiService.post.mockResolvedValue({ success: true })

                    window.tmgmtData = {
                        statuses: { anfrage: 'Anfrage' },
                        status_requirements: {},
                        field_map: {},
                    }

                    const pinia = createPinia()
                    setActivePinia(pinia)

                    const wrapper = mount(EventModal, {
                        props: { eventId: 1 },
                        global: {
                            plugins: [pinia],
                            stubs: { MissingFieldsModal: true },
                        },
                    })

                    // Wait for onMounted → loadEvent() to complete
                    await flushPromises()

                    // Reset post mock call count after load (loadEvent doesn't call post)
                    apiService.post.mockClear()

                    // Record original value via onFocus
                    wrapper.vm.onFocus(fieldName, 'old-value')

                    // Set the new value on the reactive fields object
                    wrapper.vm.fields[fieldName] = newValue

                    // Trigger blur with the new value
                    await wrapper.vm.onBlur(fieldName, newValue)

                    // Wait for async post call to resolve
                    await flushPromises()

                    // Assert: apiService.post was called with correct path and payload
                    const wasCalled = apiService.post.mock.calls.some(([path, payload]) => {
                        return path === '/events/1' &&
                            payload !== null &&
                            typeof payload === 'object' &&
                            Object.keys(payload).length === 1 &&
                            payload[fieldName] === newValue
                    })

                    wrapper.unmount()
                    return wasCalled
                }
            ),
            { numRuns: 100 }
        )
    })
})

// Feature: reactive-dashboard, Property 8: Pflichtfeld-Modal bei fehlendem Feld
// Validates: Requirements 5.4, 6.4

describe('Property 8: Pflichtfeld-Modal bei fehlendem Feld', () => {
    it('MissingFieldsModal wird geöffnet und API-Aufruf wird zurückgehalten wenn Pflichtfeld leer ist', async () => {
        const requiredFields = ['venue_city', 'fee', 'contact_email_contract']

        await fc.assert(
            fc.asyncProperty(
                fc.string({ minLength: 1, maxLength: 30 }).filter(s => s.trim().length > 0),
                fc.constantFrom(...requiredFields),
                async (targetStatus, requiredField) => {
                    // Build a mock response where the requiredField is empty
                    const mockResponse = {
                        title: 'Test Event',
                        status: 'anfrage',
                        meta: {
                            date: '2024-06-01',
                            venue_city: '',
                            fee: '',
                            contact_email_contract: '',
                            status: 'anfrage',
                            venue_name: 'Test Venue',
                        },
                        logs: [],
                        actions: [],
                    }

                    apiService.get.mockResolvedValueOnce(mockResponse)
                    apiService.post.mockResolvedValue({ success: true })

                    // Set up status_requirements so targetStatus requires requiredField
                    window.tmgmtData = {
                        statuses: {
                            anfrage: 'Anfrage',
                            [targetStatus]: targetStatus,
                        },
                        status_requirements: {
                            [targetStatus]: [requiredField],
                        },
                        field_map: {},
                    }

                    const pinia = createPinia()
                    setActivePinia(pinia)

                    const wrapper = mount(EventModal, {
                        props: { eventId: 1 },
                        global: {
                            plugins: [pinia],
                            stubs: { MissingFieldsModal: true },
                        },
                    })

                    // Wait for loadEvent() to complete
                    await flushPromises()

                    // Clear post mock after initial load
                    apiService.post.mockClear()

                    // Ensure the required field is empty in the reactive fields
                    wrapper.vm.fields[requiredField] = ''

                    // Simulate status change to targetStatus
                    wrapper.vm.fields.status = targetStatus
                    wrapper.vm.onStatusChange()

                    await flushPromises()

                    // Assert: MissingFieldsModal is visible
                    const modalVisible = wrapper.vm.missingFieldsModal.visible === true

                    // Assert: apiService.post was NOT called (status update deferred)
                    const postNotCalled = apiService.post.mock.calls.length === 0

                    wrapper.unmount()
                    return modalVisible && postNotCalled
                }
            ),
            { numRuns: 100 }
        )
    })
})

// Feature: reactive-dashboard, Property 11: Logbuch-Sortierung
// Validates: Requirements 6.5

describe('Property 11: Logbuch-Sortierung', () => {
    it('sortedLogs zeigt Einträge in absteigender chronologischer Reihenfolge', async () => {
        await fc.assert(
            fc.asyncProperty(
                fc.array(
                    fc.record({
                        id: fc.integer(),
                        date: fc.date({ min: new Date('2000-01-01'), max: new Date('2030-12-31') })
                            .map(d => d.toISOString()),
                        message: fc.string(),
                    }),
                    { minLength: 2, maxLength: 10 }
                ),
                async (logs) => {
                    const mockResponse = {
                        title: 'Test Event',
                        meta: {
                            date: '2024-06-01',
                            status: 'anfrage',
                        },
                        logs,
                        actions: [],
                    }

                    apiService.get.mockResolvedValueOnce(mockResponse)

                    window.tmgmtData = {
                        statuses: { anfrage: 'Anfrage' },
                        status_requirements: {},
                        field_map: {},
                    }

                    const pinia = createPinia()
                    setActivePinia(pinia)

                    const wrapper = mount(EventModal, {
                        props: { eventId: 1 },
                        global: {
                            plugins: [pinia],
                            stubs: { MissingFieldsModal: true },
                        },
                    })

                    await flushPromises()

                    const sortedLogs = wrapper.vm.sortedLogs

                    wrapper.unmount()

                    // Assert descending order: each entry's date >= next entry's date
                    for (let i = 1; i < sortedLogs.length; i++) {
                        if (new Date(sortedLogs[i - 1].date) < new Date(sortedLogs[i].date)) {
                            return false
                        }
                    }
                    return true
                }
            ),
            { numRuns: 100 }
        )
    })
})
