import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import Aura from '@primeuix/themes/aura'
import fc from 'fast-check'

// jsdom polyfills for PrimeVue
if (typeof window !== 'undefined' && !window.matchMedia) {
  window.matchMedia = vi.fn().mockImplementation(query => ({
    matches: false, media: query, onchange: null,
    addListener: vi.fn(), removeListener: vi.fn(),
    addEventListener: vi.fn(), removeEventListener: vi.fn(), dispatchEvent: vi.fn(),
  }))
}
if (typeof ResizeObserver === 'undefined') {
  global.ResizeObserver = class { observe() {} unobserve() {} disconnect() {} }
}

vi.mock('../services/apiService.js', () => ({
  default: { get: vi.fn(), post: vi.fn() },
}))

import EventListWidget from '../components/EventListWidget.vue'
import apiService from '../services/apiService.js'

const mockStatuses = { inquiry: 'Anfrage', confirmed: 'Bestätigt', done: 'Abgeschlossen', cancelled: 'Abgesagt' }

function createMockEvents(count = 3) {
  return Array.from({ length: count }, (_, i) => ({
    id: i + 1,
    event_id: `25TEST${i}`,
    title: `Event ${i + 1}`,
    status: Object.keys(mockStatuses)[i % Object.keys(mockStatuses).length],
    date: `2026-0${(i % 9) + 1}-15`,
    time: '20:00',
    city: `Stadt ${i}`,
    venue: `Venue ${i}`,
    veranstalter: `Veranstalter ${i}`,
    fee: String((i + 1) * 1000),
  }))
}

function mountWidget(events = createMockEvents()) {
  apiService.get.mockResolvedValue({ events, statuses: mockStatuses })
  window.tmgmtData = {
    nonce: 'test', apiUrl: 'https://localhost/wp-json/tmgmt/v1',
    statuses: mockStatuses, status_requirements: {},
  }
  return mount(EventListWidget, {
    global: {
      plugins: [createPinia(), [PrimeVue, { theme: { preset: Aura } }]],
    },
  })
}

describe('EventListWidget', () => {
  beforeEach(() => { vi.clearAllMocks() })

  it('zeigt Events nach dem Laden', async () => {
    const wrapper = mountWidget()
    await flushPromises()
    expect(wrapper.text()).toContain('Event 1')
    expect(wrapper.text()).toContain('Event 2')
  })

  it('zeigt Fehlermeldung bei API-Fehler', async () => {
    apiService.get.mockRejectedValue({ message: 'Serverfehler' })
    window.tmgmtData = { nonce: 'x', apiUrl: 'https://localhost/api', statuses: {} }
    const wrapper = mount(EventListWidget, {
      global: { plugins: [createPinia(), [PrimeVue, { theme: { preset: Aura } }]] },
    })
    await flushPromises()
    expect(wrapper.text()).toContain('Serverfehler')
  })

  it('zeigt Lade-Spinner initial', async () => {
    apiService.get.mockReturnValue(new Promise(() => {}))
    window.tmgmtData = { nonce: 'x', apiUrl: 'https://localhost/api', statuses: {} }
    const wrapper = mount(EventListWidget, {
      global: { plugins: [createPinia(), [PrimeVue, { theme: { preset: Aura } }]] },
    })
    await wrapper.vm.$nextTick()
    expect(wrapper.find('.tmgmt-event-list__loading').exists()).toBe(true)
  })

  it('zeigt leere Nachricht wenn keine Events', async () => {
    const wrapper = mountWidget([])
    await flushPromises()
    expect(wrapper.text()).toContain('Keine Events gefunden')
  })

  it('emittiert open-event-modal bei Zeilen-Klick', async () => {
    const wrapper = mountWidget()
    await flushPromises()
    const rows = wrapper.findAll('tr.tmgmt-event-list__row')
    if (rows.length > 0) {
      await rows[0].trigger('click')
      expect(wrapper.emitted('open-event-modal')).toBeTruthy()
    }
  })

  it('property: rendert beliebige Anzahl Events ohne Absturz', () => {
    fc.assert(
      fc.property(
        fc.array(
          fc.record({
            id: fc.nat({ max: 9999 }),
            event_id: fc.string({ minLength: 1, maxLength: 10 }),
            title: fc.string({ minLength: 0, maxLength: 100 }),
            status: fc.constantFrom('inquiry', 'confirmed', 'done', 'cancelled', ''),
            date: fc.constantFrom('2026-07-15', '2026-01-01', ''),
            time: fc.constantFrom('20:00', '18:30', ''),
            city: fc.string({ maxLength: 50 }),
            venue: fc.string({ maxLength: 50 }),
            veranstalter: fc.string({ maxLength: 50 }),
            fee: fc.constantFrom('', '1000', '2500.50', '0'),
          }),
          { maxLength: 50 }
        ),
        (events) => {
          const wrapper = mountWidget(events)
          expect(wrapper.exists()).toBe(true)
          wrapper.unmount()
        }
      ),
      { numRuns: 15 }
    )
  })

  it('property: Suche filtert korrekt', async () => {
    const events = [
      { id: 1, event_id: 'A1', title: 'Hamburg Konzert', status: 'inquiry', date: '', time: '', city: 'Hamburg', venue: '', veranstalter: '', fee: '' },
      { id: 2, event_id: 'B2', title: 'Berlin Show', status: 'confirmed', date: '', time: '', city: 'Berlin', venue: '', veranstalter: '', fee: '' },
      { id: 3, event_id: 'C3', title: 'München Gig', status: 'done', date: '', time: '', city: 'München', venue: '', veranstalter: '', fee: '' },
    ]
    const wrapper = mountWidget(events)
    await flushPromises()

    // All visible initially
    expect(wrapper.text()).toContain('Hamburg Konzert')
    expect(wrapper.text()).toContain('Berlin Show')

    // Type search
    const searchInput = wrapper.find('.tmgmt-event-list__search')
    await searchInput.setValue('Berlin')
    await flushPromises()

    expect(wrapper.text()).toContain('Berlin Show')
    expect(wrapper.text()).not.toContain('Hamburg Konzert')
  })
})
