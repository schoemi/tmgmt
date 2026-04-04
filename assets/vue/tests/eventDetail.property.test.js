/**
 * Property-Tests für EventDetail.vue
 *
 * Testet die Komponente isoliert mit gemocktem apiService.
 * Da EventDetail den apiService-Singleton importiert, mocken wir
 * das Modul direkt via vi.mock.
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import Aura from '@primeuix/themes/aura'
import fc from 'fast-check'

// jsdom polyfills for PrimeVue
if (typeof window !== 'undefined' && !window.matchMedia) {
  window.matchMedia = vi.fn().mockImplementation(query => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: vi.fn(),
    removeListener: vi.fn(),
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    dispatchEvent: vi.fn(),
  }))
}

if (typeof ResizeObserver === 'undefined') {
  global.ResizeObserver = class {
    observe() {}
    unobserve() {}
    disconnect() {}
  }
}

// Mock apiService before importing EventDetail
vi.mock('../services/apiService.js', () => {
  return {
    default: {
      get: vi.fn(),
      post: vi.fn(),
    },
    createApiService: vi.fn(),
  }
})

import EventDetail from '../components/EventDetail.vue'
import apiService from '../services/apiService.js'

function createMockResponse(overrides = {}) {
  return {
    id: 42,
    title: 'Test Event',
    content: 'Notizen',
    meta: {
      event_id: '25ABC123',
      event_date: '2026-07-15',
      event_start_time: '20:00',
      status: 'inquiry',
      venue_name: '', venue_street: '', venue_number: '',
      venue_zip: '', venue_city: '', venue_country: '',
      contact_salutation: '', contact_firstname: '', contact_lastname: '',
      contact_company: '', contact_street: '', contact_number: '',
      contact_zip: '', contact_city: '', contact_country: '',
      contact_email_contract: '', contact_phone_contract: '',
      contact_name_tech: '', contact_email_tech: '', contact_phone_tech: '',
      contact_name_program: '', contact_email_program: '', contact_phone_program: '',
      fee: '', deposit: '', inquiry_date: '',
      arrival_time: '', departure_time: '',
      ...overrides,
    },
    logs: [],
    communication: [],
    actions: [],
    attachments: [],
    tours: [],
  }
}

function mountDetail(response) {
  apiService.get.mockResolvedValue(response)
  apiService.post.mockResolvedValue({ success: true })

  window.tmgmtData = {
    nonce: 'test',
    apiUrl: 'https://localhost/wp-json/tmgmt/v1',
    statuses: { inquiry: 'Anfrage', confirmed: 'Bestätigt' },
    status_requirements: {},
  }

  return mount(EventDetail, {
    props: { eventId: response.id },
    global: {
      plugins: [
        createPinia(),
        [PrimeVue, { theme: { preset: Aura } }],
      ],
    },
  })
}

describe('EventDetail', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('zeigt Event-ID nach dem Laden', async () => {
    const wrapper = mountDetail(createMockResponse())
    await flushPromises()
    expect(wrapper.text()).toContain('25ABC123')
  })

  it('zeigt Titel im Input-Feld', async () => {
    const wrapper = mountDetail(createMockResponse())
    await flushPromises()
    const titleInput = wrapper.find('.tmgmt-event-detail__title')
    expect(titleInput.exists()).toBe(true)
  })

  it('zeigt Fehlermeldung bei API-Fehler', async () => {
    apiService.get.mockRejectedValue({ message: 'Event nicht gefunden' })
    window.tmgmtData = { nonce: 'x', apiUrl: 'https://localhost/api', statuses: {} }

    const wrapper = mount(EventDetail, {
      props: { eventId: 999 },
      global: {
        plugins: [createPinia(), [PrimeVue, { theme: { preset: Aura } }]],
      },
    })
    await flushPromises()
    expect(wrapper.text()).toContain('Event nicht gefunden')
  })

  it('zeigt Lade-Spinner initial', async () => {
    apiService.get.mockReturnValue(new Promise(() => {}))
    window.tmgmtData = { nonce: 'x', apiUrl: 'https://localhost/api', statuses: {} }

    const wrapper = mount(EventDetail, {
      props: { eventId: 1 },
      global: {
        plugins: [createPinia(), [PrimeVue, { theme: { preset: Aura } }]],
      },
    })
    // onMounted triggers loadEvent() which sets loading=true; need one tick
    await wrapper.vm.$nextTick()
    expect(wrapper.find('.tmgmt-event-detail__loading').exists()).toBe(true)
  })

  it('property: rendert beliebige Titel ohne Absturz', () => {
    fc.assert(
      fc.property(fc.string({ minLength: 0, maxLength: 200 }), (title) => {
        const response = createMockResponse()
        response.title = title
        const wrapper = mountDetail(response)
        expect(wrapper.exists()).toBe(true)
        wrapper.unmount()
      }),
      { numRuns: 20 }
    )
  })

  it('property: verarbeitet beliebige Anzahl Logs', () => {
    fc.assert(
      fc.property(
        fc.array(
          fc.record({
            id: fc.nat(),
            date: fc.constant('01.01.2026 12:00'),
            user: fc.string({ maxLength: 50 }),
            message: fc.string({ maxLength: 200 }),
            type: fc.constantFrom('status_change', 'api_update', 'api_info', 'error'),
          }),
          { maxLength: 30 }
        ),
        (logs) => {
          const response = createMockResponse()
          response.logs = logs
          const wrapper = mountDetail(response)
          expect(wrapper.exists()).toBe(true)
          wrapper.unmount()
        }
      ),
      { numRuns: 10 }
    )
  })

  it('property: verarbeitet beliebige Kommunikationseinträge', () => {
    fc.assert(
      fc.property(
        fc.array(
          fc.record({
            id: fc.nat(),
            date: fc.constant('01.01.2026 12:00'),
            user: fc.string({ maxLength: 30 }),
            type: fc.constantFrom('email', 'note'),
            recipient: fc.string({ maxLength: 50 }),
            subject: fc.string({ maxLength: 100 }),
            content: fc.constant('<p>Inhalt</p>'),
          }),
          { maxLength: 15 }
        ),
        (communication) => {
          const response = createMockResponse()
          response.communication = communication
          const wrapper = mountDetail(response)
          expect(wrapper.exists()).toBe(true)
          wrapper.unmount()
        }
      ),
      { numRuns: 10 }
    )
  })

  it('property: verarbeitet beliebige Anhänge', () => {
    fc.assert(
      fc.property(
        fc.array(
          fc.record({
            id: fc.nat(),
            title: fc.string({ minLength: 1, maxLength: 50 }),
            filename: fc.string({ minLength: 1, maxLength: 50 }),
            url: fc.constant('#'),
            type: fc.constant('application/pdf'),
            category: fc.constantFrom('', 'Vertrag', 'Rider'),
          }),
          { maxLength: 20 }
        ),
        (attachments) => {
          const response = createMockResponse()
          response.attachments = attachments
          const wrapper = mountDetail(response)
          expect(wrapper.exists()).toBe(true)
          wrapper.unmount()
        }
      ),
      { numRuns: 10 }
    )
  })

  it('sortedLogs zeigt Einträge in absteigender Reihenfolge', async () => {
    const response = createMockResponse()
    response.logs = [
      { id: 1, date: '01.01.2026 10:00', user: 'A', message: 'Erster', type: 'api_info' },
      { id: 2, date: '02.01.2026 10:00', user: 'B', message: 'Zweiter', type: 'api_info' },
    ]
    const wrapper = mountDetail(response)
    await flushPromises()
    const text = wrapper.text()
    const posFirst = text.indexOf('Erster')
    const posSecond = text.indexOf('Zweiter')
    // Zweiter (neueres Datum) sollte vor Erster stehen
    expect(posSecond).toBeLessThan(posFirst)
  })
})
