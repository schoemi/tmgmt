// Feature: reactive-dashboard, Property 3: Widget-ID-Eindeutigkeit
// Validates: Requirements 4.2

import { describe, it } from 'vitest'
import fc from 'fast-check'
import { createWidgetRegistry } from '../registry/widgetRegistry.js'

describe('Property 3: Widget-ID-Eindeutigkeit', () => {
    it('zweite Registrierung mit gleicher ID wird abgelehnt', () => {
        fc.assert(
            fc.property(
                fc.string({ minLength: 1 }),
                (id) => {
                    const registry = createWidgetRegistry()
                    registry.register({ id, label: 'A', icon: 'fa-star', component: {} })
                    registry.register({ id, label: 'B', icon: 'fa-star', component: {} })
                    return registry.getAll().filter(w => w.id === id).length === 1
                }
            ),
            { numRuns: 100 }
        )
    })
})

// Feature: reactive-dashboard, Property 4: Widget-Sortierreihenfolge
// Validates: Requirements 4.4

describe('Property 4: Widget-Sortierreihenfolge', () => {
    it('getAll() gibt aufsteigend nach order sortierte Liste zurück', () => {
        fc.assert(
            fc.property(
                fc.uniqueArray(
                    fc.record({ id: fc.uuid(), order: fc.integer() }),
                    { minLength: 1, selector: (w) => w.id }
                ),
                (widgets) => {
                    const registry = createWidgetRegistry()
                    widgets.forEach(w => registry.register({ ...w, label: 'label', icon: 'fa-star', component: {} }))
                    const sorted = registry.getAll()
                    return sorted.every((w, i) => i === 0 || sorted[i - 1].order <= w.order)
                }
            ),
            { numRuns: 100 }
        )
    })
})

// Feature: reactive-dashboard, Property 16: Permissions-basierte Widget-Sichtbarkeit
// Validates: Requirements 9.2

describe('Property 16: Permissions-basierte Widget-Sichtbarkeit', () => {
    it('Widget nur sichtbar wenn Capability vorhanden', () => {
        fc.assert(
            fc.property(
                fc.record({
                    capability: fc.string({ minLength: 1 }),
                    hasCapability: fc.boolean()
                }),
                ({ capability, hasCapability }) => {
                    const registry = createWidgetRegistry()
                    registry.register({ id: 'test', label: 'T', icon: 'fa-star', component: {}, permissions: [capability] })
                    const userCaps = hasCapability ? { [capability]: true } : {}
                    const visible = registry.getVisible(userCaps)
                    return visible.some(w => w.id === 'test') === hasCapability
                }
            ),
            { numRuns: 100 }
        )
    })
})
