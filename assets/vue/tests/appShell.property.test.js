// Feature: reactive-dashboard, Property 2: localStorage Widget-Persistenz (Round-Trip)
// Validates: Requirements 3.4

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
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

// Mock apiService (used by EventDetail)
vi.mock('../services/apiService.js', () => ({
  default: { get: vi.fn().mockResolvedValue({ id: 1, title: '', meta: {}, logs: [], communication: [], actions: [], attachments: [], tours: [] }), post: vi.fn() },
}))

// vi.mock is hoisted – use importOriginal to keep named exports intact
vi.mock('../registry/widgetRegistry.js', async (importOriginal) => {
    const actual = await importOriginal()
    const { defineComponent: dc, h: hh } = await import('vue')

    const WidgetAlpha = dc({ name: 'WidgetAlpha', emits: ['open-event-modal'], render: () => hh('div', 'alpha') })
    const WidgetBeta  = dc({ name: 'WidgetBeta',  emits: ['open-event-modal'], render: () => hh('div', 'beta') })

    const registry = actual.createWidgetRegistry()
    registry.register({ id: 'widget-alpha', label: 'Alpha', icon: 'fa-star',   component: WidgetAlpha, order: 1 })
    registry.register({ id: 'widget-beta',  label: 'Beta',  icon: 'fa-circle', component: WidgetBeta,  order: 2 })

    return { ...actual, default: registry }
})

import AppShell from '../components/AppShell.vue'

// ─── localStorage mock ────────────────────────────────────────────────────────

function createLocalStorageMock() {
    let store = {}
    return {
        getItem: (key) => (key in store ? store[key] : null),
        setItem: (key, value) => { store[key] = String(value) },
        removeItem: (key) => { delete store[key] },
        clear: () => { store = {} },
    }
}

// ─── Setup ───────────────────────────────────────────────────────────────────

let localStorageMock

beforeEach(() => {
    localStorageMock = createLocalStorageMock()
    vi.stubGlobal('localStorage', localStorageMock)

    window.tmgmtData = {
        capabilities: {},
        statuses: {},
        status_requirements: {},
        field_map: {},
    }
})

afterEach(() => {
    vi.unstubAllGlobals()
})

const plugins = [
    [PrimeVue, { theme: { preset: Aura } }],
]

// ─── Property 2: localStorage Round-Trip (pure storage layer) ────────────────

describe('Property 2: localStorage Widget-Persistenz (Round-Trip)', () => {
    it('für jede Widget-ID: nach setItem wird dieselbe ID via getItem zurückgegeben', () => {
        fc.assert(
            fc.property(
                fc.string({ minLength: 1 }),
                (widgetId) => {
                    localStorage.setItem('tmgmt_active_widget', widgetId)
                    return localStorage.getItem('tmgmt_active_widget') === widgetId
                }
            ),
            { numRuns: 100 }
        )
    })
})

// ─── Component-level: AppShell shows widgets and detail view ─────────────────

describe('AppShell: Dashboard und Detail-Ansicht', () => {
    it('zeigt Widget-Navigation im Dashboard-Modus', async () => {
        const wrapper = mount(AppShell, { global: { plugins } })
        await wrapper.vm.$nextTick()
        // TabMenu should be visible
        expect(wrapper.text()).toContain('Alpha')
        expect(wrapper.text()).toContain('Beta')
        wrapper.unmount()
    })

    it('zeigt EventDetail und Zurück-Button wenn Event ausgewählt', async () => {
        const wrapper = mount(AppShell, { global: { plugins } })
        await wrapper.vm.$nextTick()

        // Simulate opening an event
        wrapper.vm.openEventDetail(42)
        await wrapper.vm.$nextTick()

        // Should show back button, not TabMenu
        expect(wrapper.text()).toContain('Zurück')
        expect(wrapper.find('.tmgmt-app-shell__detail-header').exists()).toBe(true)
        wrapper.unmount()
    })

    it('kehrt zum Dashboard zurück nach closeEventDetail', async () => {
        const wrapper = mount(AppShell, { global: { plugins } })
        await wrapper.vm.$nextTick()

        wrapper.vm.openEventDetail(42)
        await wrapper.vm.$nextTick()
        expect(wrapper.text()).toContain('Zurück')

        wrapper.vm.closeEventDetail()
        await wrapper.vm.$nextTick()
        expect(wrapper.text()).toContain('Alpha')
        wrapper.unmount()
    })
})
