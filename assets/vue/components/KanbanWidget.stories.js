import KanbanWidget from './KanbanWidget.vue'
import { useEventStore } from '../stores/eventStore.js'

export default {
  title: 'Dashboard/KanbanWidget',
  component: KanbanWidget,
}

const mockBoard = {
  columns: [
    { id: 1, title: 'Anfrage', color: '#4c9aff', statuses: ['inquiry'] },
    { id: 2, title: 'Bestätigt', color: '#36b37e', statuses: ['confirmed'] },
    { id: 3, title: 'Abgeschlossen', color: '#6b778c', statuses: ['done'] },
  ],
  events: [
    { id: 1, title: 'Stadtfest Köln', status: 'inquiry', date: '2026-06-15', city: 'Köln' },
    { id: 2, title: 'Open Air Berlin', status: 'inquiry', date: '2026-07-20', city: 'Berlin' },
    { id: 3, title: 'Clubshow Hamburg', status: 'confirmed', date: '2026-08-10', city: 'Hamburg' },
    { id: 4, title: 'Festival München', status: 'done', date: '2026-05-01', city: 'München' },
  ],
}

const setupStore = (overrides = {}) => {
  const store = useEventStore()
  store.board = overrides.board ?? mockBoard
  store.loading = overrides.loading ?? false
  store.error = overrides.error ?? null
  store.loadBoard = async () => {}
}

export const Default = {
  render: () => ({
    components: { KanbanWidget },
    setup() {
      setupStore()
      return {}
    },
    template: '<KanbanWidget />',
  }),
}

export const Loading = {
  render: () => ({
    components: { KanbanWidget },
    setup() {
      setupStore({ loading: true })
      return {}
    },
    template: '<KanbanWidget />',
  }),
}

export const Error = {
  render: () => ({
    components: { KanbanWidget },
    setup() {
      setupStore({ error: 'Board konnte nicht geladen werden.' })
      return {}
    },
    template: '<KanbanWidget />',
  }),
}

export const Empty = {
  render: () => ({
    components: { KanbanWidget },
    setup() {
      setupStore({
        board: { columns: mockBoard.columns, events: [] },
      })
      return {}
    },
    template: '<KanbanWidget />',
  }),
}
