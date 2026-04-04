import AppShell from './AppShell.vue'
import { useEventStore } from '../stores/eventStore.js'
import widgetRegistry from '../registry/widgetRegistry.js'
import KanbanWidget from './KanbanWidget.vue'

export default {
  title: 'Dashboard/AppShell',
  component: AppShell,
}

const mockBoard = {
  columns: [
    { id: 1, title: 'Anfrage', color: '#4c9aff', statuses: ['inquiry'] },
    { id: 2, title: 'Bestätigt', color: '#36b37e', statuses: ['confirmed'] },
  ],
  events: [
    { id: 1, title: 'Stadtfest Köln', status: 'inquiry', date: '2026-06-15', city: 'Köln' },
  ],
}

export const Default = {
  render: () => ({
    components: { AppShell },
    setup() {
      window.tmgmtData = window.tmgmtData ?? { capabilities: {}, statuses: {} }
      widgetRegistry.register({
        id: 'kanban',
        label: 'Kanban',
        icon: 'fa-columns',
        component: KanbanWidget,
        order: 1,
      })
      const store = useEventStore()
      store.board = mockBoard
      store.loading = false
      store.error = null
      store.loadBoard = async () => {}
      return {}
    },
    template: '<AppShell />',
  }),
}
