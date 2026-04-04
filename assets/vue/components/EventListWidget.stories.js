import EventListWidget from './EventListWidget.vue'

const mockEvents = [
  { id: 1, event_id: '25A1B2C3', title: 'Sommerfest Stadtpark', status: 'confirmed', date: '2026-07-15', time: '20:00', city: 'Hamburg', venue: 'Stadtpark Open Air', veranstalter: 'Stadtpark Events GmbH', fee: '2500' },
  { id: 2, event_id: '25D4E5F6', title: 'Clubshow Knust', status: 'inquiry', date: '2026-08-02', time: '21:00', city: 'Hamburg', venue: 'Knust', veranstalter: 'Knust Booking', fee: '800' },
  { id: 3, event_id: '25G7H8I9', title: 'Stadtfest Lübeck', status: 'done', date: '2026-06-10', time: '19:00', city: 'Lübeck', venue: 'Marktplatz', veranstalter: 'Stadt Lübeck', fee: '3000' },
  { id: 4, event_id: '25J0K1L2', title: 'Firmenfeier Airbus', status: 'contract_sent', date: '2026-09-20', time: '18:00', city: 'Hamburg', venue: 'Airbus Kantine', veranstalter: 'Airbus SE', fee: '5000' },
  { id: 5, event_id: '25M3N4O5', title: 'Hochzeit Meyer', status: 'cancelled', date: '2026-05-01', time: '15:00', city: 'Bremen', venue: 'Schloss Schönebeck', veranstalter: 'Familie Meyer', fee: '1500' },
]

const mockStatuses = {
  inquiry: 'Anfrage',
  confirmed: 'Bestätigt',
  contract_sent: 'Vertrag versendet',
  done: 'Abgeschlossen',
  cancelled: 'Abgesagt',
}

function setupMocks(events = mockEvents) {
  window.tmgmtData = {
    nonce: 'fake', apiUrl: '/wp-json/tmgmt/v1', capabilities: {},
    statuses: mockStatuses, status_requirements: {},
  }
  window.fetch = async (url, options) => {
    if (typeof url === 'string' && url.includes('/events') && (!options || options.method !== 'POST')) {
      return new Response(JSON.stringify({ events, statuses: mockStatuses }), {
        status: 200, headers: { 'Content-Type': 'application/json' },
      })
    }
    return new Response('{}', { status: 200, headers: { 'Content-Type': 'application/json' } })
  }
}

export default {
  title: 'Dashboard/EventListWidget',
  component: EventListWidget,
  argTypes: {
    'open-event-modal': { action: 'open-event-modal' },
  },
}

export const Default = {
  render: () => ({
    components: { EventListWidget },
    setup() { setupMocks(); return {} },
    template: '<EventListWidget @open-event-modal="() => {}" />',
  }),
}

export const Empty = {
  render: () => ({
    components: { EventListWidget },
    setup() { setupMocks([]); return {} },
    template: '<EventListWidget @open-event-modal="() => {}" />',
  }),
}

export const Loading = {
  render: () => ({
    components: { EventListWidget },
    setup() {
      window.tmgmtData = { nonce: 'x', apiUrl: '/wp-json/tmgmt/v1', statuses: {} }
      window.fetch = () => new Promise(() => {})
      return {}
    },
    template: '<EventListWidget @open-event-modal="() => {}" />',
  }),
}

export const Error = {
  render: () => ({
    components: { EventListWidget },
    setup() {
      window.tmgmtData = { nonce: 'x', apiUrl: '/wp-json/tmgmt/v1', statuses: {} }
      window.fetch = async () => new Response(
        JSON.stringify({ message: 'Serverfehler' }),
        { status: 500, headers: { 'Content-Type': 'application/json' } }
      )
      return {}
    },
    template: '<EventListWidget @open-event-modal="() => {}" />',
  }),
}
