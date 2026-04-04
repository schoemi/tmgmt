import EventModal from './EventModal.vue'

export default {
  title: 'Dashboard/EventModal',
  component: EventModal,
  argTypes: {
    close: { action: 'close' },
  },
}

export const Default = {
  render: () => ({
    components: { EventModal },
    setup() {
      window.tmgmtData = window.tmgmtData ?? {
        capabilities: {},
        statuses: { inquiry: 'Anfrage', confirmed: 'Bestätigt', done: 'Abgeschlossen' },
      }
      return {}
    },
    template: '<EventModal :event-id="1" @close="() => {}" />',
  }),
}
