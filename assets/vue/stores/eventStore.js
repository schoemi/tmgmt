/**
 * Pinia Store für globale Event- und Board-Daten.
 *
 * Exportiert zusätzlich `createTestableStore(apiServiceMock)` für isolierte
 * Unit- und Property-Tests mit injiziertem Mock-API-Service.
 */

import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiService from '../services/apiService.js'

/**
 * Interne Factory, die den Store mit einem konfigurierbaren API-Service erstellt.
 * @param {object} api – apiService-kompatibles Objekt mit get() und post()
 * @param {string} [storeId] – eindeutige Store-ID (für Testinstanzen)
 */
function createStore(api, storeId = 'eventStore') {
    return defineStore(storeId, () => {
        // --- State ---
        const board = ref({ columns: [], events: [] })
        const loading = ref(false)
        const error = ref(null)

        // --- Helpers ---
        function findEvent(id) {
            return board.value.events.find(e => e.id === id) ?? null
        }

        // --- Actions ---

        /**
         * Lädt das Board via GET /kanban und befüllt board.columns + board.events.
         */
        async function loadBoard() {
            loading.value = true
            error.value = null
            try {
                const data = await api.get('/kanban')
                board.value = {
                    columns: data.columns ?? [],
                    events: data.events ?? [],
                }
            } catch (err) {
                error.value = err?.message ?? String(err)
            } finally {
                loading.value = false
            }
        }

        /**
         * Setzt den Status eines Events optimistisch, ruft dann die API auf.
         * Bei Erfolg: dispatcht `tmgmt:event-updated` auf window.
         * Bei Fehler: ruft revertEventStatus() auf und setzt error.
         *
         * @param {number} id
         * @param {string} status
         */
        async function updateEventStatus(id, status) {
            const event = findEvent(id)
            if (!event) return

            const oldStatus = event.status

            // Optimistisches Update
            event.status = status

            try {
                await api.post('/events/' + id, { status })
                window.dispatchEvent(new CustomEvent('tmgmt:event-updated', { detail: { id } }))
            } catch (err) {
                revertEventStatus(id, oldStatus)
                error.value = err?.message ?? String(err)
            }
        }

        /**
         * Setzt den Status eines Events auf einen früheren Wert zurück (Rollback).
         *
         * @param {number} id
         * @param {string} oldStatus
         */
        function revertEventStatus(id, oldStatus) {
            const event = findEvent(id)
            if (event) {
                event.status = oldStatus
            }
        }

        /**
         * Aktualisiert ein einzelnes Feld eines Events via POST (Auto-Save).
         *
         * @param {number} id
         * @param {string} field
         * @param {*} value
         */
        async function updateEventField(id, field, value) {
            const event = findEvent(id)
            if (!event) return

            error.value = null
            try {
                await api.post('/events/' + id, { [field]: value })
                if (Object.prototype.hasOwnProperty.call(event, field)) {
                    event[field] = value
                }
            } catch (err) {
                error.value = err?.message ?? String(err)
            }
        }

        return {
            // State
            board,
            loading,
            error,
            // Actions
            loadBoard,
            updateEventStatus,
            revertEventStatus,
            updateEventField,
        }
    })
}

// Standard-Store mit dem echten apiService-Singleton
export const useEventStore = createStore(apiService)

/**
 * Factory für isolierte Testinstanzen mit injiziertem Mock-API-Service.
 * Jeder Aufruf erzeugt eine neue Store-Definition mit eindeutiger ID,
 * sodass Pinia-Instanzen nicht kollidieren.
 *
 * @param {object} apiServiceMock – Objekt mit get() und post() als jest.fn() / vi.fn()
 * @returns {ReturnType<typeof defineStore>}
 */
export function createTestableStore(apiServiceMock) {
    const uniqueId = 'eventStore_test_' + Math.random().toString(36).slice(2)
    return createStore(apiServiceMock, uniqueId)
}
