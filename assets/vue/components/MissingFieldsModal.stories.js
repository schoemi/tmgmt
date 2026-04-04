import MissingFieldsModal from './MissingFieldsModal.vue'

export default {
  title: 'Dashboard/MissingFieldsModal',
  component: MissingFieldsModal,
  argTypes: {
    confirmed: { action: 'confirmed' },
    cancelled: { action: 'cancelled' },
  },
}

export const Default = {
  args: {
    missingFields: ['date', 'venue_name', 'contact_email'],
    targetStatus: 'confirmed',
  },
}

export const SingleField = {
  args: {
    missingFields: ['fee'],
    targetStatus: 'confirmed',
  },
}

export const ManyFields = {
  args: {
    missingFields: [
      'date', 'start_time', 'venue_name', 'venue_city',
      'contact_firstname', 'contact_lastname', 'contact_email', 'fee',
    ],
    targetStatus: 'done',
  },
}
