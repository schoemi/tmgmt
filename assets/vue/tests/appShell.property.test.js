// Feature: reactive-dashboard, Property 2: localStorage Widget-Persistenz (Round-Trip)
// Validates: Requirements 3.4

import { describe, it, beforeEach, afterEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import fc from 'fast-check'

// vi.mock is hoisted – use importOriginal to keep named exports intact
vi.mock('../registry/widgetRegistry.js', async (importOriginal) => {
    const actual = await importOriginal()
    const { defineComponent: dc, h: hh } = await import('vue')

    const WidgetAlpha = dc({ name: 'WidgetAlpha', render: () => hh('div', 'alpha') })
    const WidgetBeta  = dc({ name: 'WidgetBeta',  render: () => hh('div', 'beta') })

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

// ─── Property 2: localStorage Round-Trip (pure storage layer) ────────────────

describe('Property 2: localStorage Widget-Persistenz (Round-Trip)', () => {
    it('für jede Widget-ID: nach setItem wird dieselbe ID via getItem zurückgegeben', () => {
        // **Validates: Requirements 3.4**
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

// ─── Component-level: AppShell persists and restores active widget ────────────

describe('AppShell: localStorage Widget-Persistenz (Komponenten-Ebene)', () => {
    it('setActiveWidget speichert ID in localStorage und Remount stellt sie wieder her', async () => {
        // 1. Mount AppShell
        const wrapper = mount(AppShell, {
            global: { stubs: { EventModal: true } },
        })
        await wrapper.vm.$nextTick()

        // 2. Call setActiveWidget with the second widget's ID
        const widgetId = 'widget-beta'
        wrapper.vm.setActiveWidget(widgetId)
        await wrapper.vm.$nextTick()

        // 3. localStorage must contain the widget ID
        expect(localStorage.getItem('tmgmt_active_widget')).toBe(widgetId)

        // 4. Unmount
        wrapper.unmount()

        // 5. Remount – onMounted should restore from localStorage
        const wrapper2 = mount(AppShell, {
            global: { stubs: { EventModal: true } },
        })
        await wrapper2.vm.$nextTick()

        // 6. activeWidgetId should be restored
        expect(wrapper2.vm.activeWidgetId).toBe(widgetId)

        wrapper2.unmount()
    })
})
