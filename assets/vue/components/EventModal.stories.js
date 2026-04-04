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
      window.tmgmtData = {
        nonce: 'fake',
        apiUrl: '/wp-json/tmgmt/v1',
        capabilities: {},
        statuses: { inquiry: 'Anfrage', confirmed: 'Bestätigt', done: 'Abgeschlossen' },
        status_requirements: {},
      }
      return {}
    },
    template: '<EventModal :event-id="1" @close="() => {}" />',
  }),
}
