import EventDetail from './EventDetail.vue'

const mockEvent = {
  id: 42,
  title: 'Sommerfest Stadtpark',
  content: 'Bühne 2, Soundcheck ab 16:00',
  meta: {
    event_id: '25A3F1B2',
    event_date: '2026-07-15',
    event_start_time: '20:00',
    event_arrival_time: '16:00',
    event_departure_time: '23:30',
    inquiry_date: '2026-03-01',
    status: 'confirmed',
    fee: '2500',
    deposit: '500',
    venue_name: 'Stadtpark Open Air',
    venue_street: 'Parkstraße',
    venue_number: '12',
    venue_zip: '20095',
    venue_city: 'Hamburg',
    venue_country: 'Deutschland',
    contact_salutation: 'Herr',
    contact_firstname: 'Max',
    contact_lastname: 'Mustermann',
    contact_company: 'Stadtpark Events GmbH',
    contact_street: 'Eventweg',
    contact_number: '5',
    contact_zip: '20095',
    contact_city: 'Hamburg',
    contact_country: 'Deutschland',
    contact_email_contract: 'vertrag@example.com',
    contact_phone_contract: '+49 40 654321',
    contact_name_tech: 'Lisa Technik',
    contact_email_tech: 'lisa@example.com',
    contact_phone_tech: '+49 40 111222',
    contact_name_program: 'Tom Programm',
    contact_email_program: 'tom@example.com',
    contact_phone_program: '+49 40 333444',
  },
  logs: [
    { id: 1, date: '15.03.2026 10:00', user: 'Admin', message: 'Event erstellt', type: 'api_info' },
    { id: 2, date: '16.03.2026 14:30', user: 'Admin', message: 'Status geändert auf: Bestätigt', type: 'status_change' },
    { id: 3, date: '20.03.2026 09:15', user: 'System', message: 'Vertrag versendet', type: 'api_update' },
  ],
  communication: [
    {
      id: 1, date: '20.03.2026 09:15', user: 'Admin', type: 'email',
      recipient: 'vertrag@example.com', subject: 'Vertrag: Sommerfest Stadtpark',
      content: '<p>Sehr geehrter Herr Mustermann, anbei der Vertrag...</p>',
    },
  ],
  actions: [
    { id: 101, label: 'Vertrag senden', type: 'email', target_status: 'contract_sent', required_fields: [] },
    { id: 102, label: 'Absagen', type: 'status', target_status: 'cancelled', required_fields: [] },
  ],
  attachments: [
    { id: 10, title: 'Vertrag_2026.pdf', filename: 'Vertrag_2026.pdf', url: '#', type: 'application/pdf', category: 'Vertrag' },
    { id: 11, title: 'Rider.pdf', filename: 'Rider.pdf', url: '#', type: 'application/pdf', category: '' },
  ],
  tours: [
    { id: 5, title: 'Norddeutschland Tour 15.07.', link: '#', mode: 'published', status: 'ok' },
  ],
}

function setupMocks() {
  window.tmgmtData = {
    nonce: 'fake-nonce',
    apiUrl: '/wp-json/tmgmt/v1',
    capabilities: {},
    statuses: {
      inquiry: 'Anfrage', confirmed: 'Bestätigt', contract_sent: 'Vertrag versendet',
      done: 'Abgeschlossen', cancelled: 'Abgesagt',
    },
    status_requirements: {},
  }
  window.fetch = async (url, options) => {
    if (typeof url === 'string' && url.includes('/events/')) {
      if (options?.method === 'POST') {
        return new Response(JSON.stringify({ success: true }), { status: 200, headers: { 'Content-Type': 'application/json' } })
      }
      return new Response(JSON.stringify(mockEvent), { status: 200, headers: { 'Content-Type': 'application/json' } })
    }
    return new Response('{}', { status: 200, headers: { 'Content-Type': 'application/json' } })
  }
}

export default {
  title: 'Dashboard/EventDetail',
  component: EventDetail,
}

export const Default = {
  render: () => ({
    components: { EventDetail },
    setup() { setupMocks(); return {} },
    template: '<EventDetail :event-id="42" />',
  }),
}

export const Loading = {
  render: () => ({
    components: { EventDetail },
    setup() {
      window.tmgmtData = { nonce: 'x', apiUrl: '/wp-json/tmgmt/v1', statuses: {} }
      window.fetch = () => new Promise(() => {})
      return {}
    },
    template: '<EventDetail :event-id="99" />',
  }),
}

export const Error = {
  render: () => ({
    components: { EventDetail },
    setup() {
      window.tmgmtData = { nonce: 'x', apiUrl: '/wp-json/tmgmt/v1', statuses: {} }
      window.fetch = async () => new Response(
        JSON.stringify({ message: 'Event nicht gefunden' }),
        { status: 404, headers: { 'Content-Type': 'application/json' } }
      )
      return {}
    },
    template: '<EventDetail :event-id="999" />',
  }),
}

export const Empty = {
  render: () => ({
    components: { EventDetail },
    setup() {
      const emptyEvent = {
        id: 1, title: 'Neues Event', content: '',
        meta: { event_id: '26XYZ789', status: 'inquiry' },
        logs: [], communication: [], actions: [], attachments: [], tours: [],
      }
      window.tmgmtData = {
        nonce: 'x', apiUrl: '/wp-json/tmgmt/v1',
        statuses: { inquiry: 'Anfrage', confirmed: 'Bestätigt' },
        status_requirements: {},
      }
      window.fetch = async () => new Response(
        JSON.stringify(emptyEvent),
        { status: 200, headers: { 'Content-Type': 'application/json' } }
      )
      return {}
    },
    template: '<EventDetail :event-id="1" />',
  }),
}
