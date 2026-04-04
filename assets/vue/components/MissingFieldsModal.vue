<template>
  <Dialog
    :visible="true"
    modal
    header="Fehlende Angaben"
    :style="{ width: '500px' }"
    @update:visible="$emit('cancelled')"
  >
    <div class="tmgmt-missing-fields">
      <div v-for="field in missingFields" :key="field" class="tmgmt-missing-fields__group">
        <label :for="'mf-' + field" class="tmgmt-missing-fields__label">{{ getLabel(field) }}</label>
        <InputText
          v-if="getInputType(field) === 'text' || getInputType(field) === 'email'"
          :id="'mf-' + field"
          v-model="fieldValues[field]"
          :type="getInputType(field)"
          fluid
        />
        <DatePicker
          v-else-if="getInputType(field) === 'date'"
          :id="'mf-' + field"
          v-model="fieldValues[field]"
          dateFormat="yy-mm-dd"
          fluid
        />
        <InputNumber
          v-else-if="getInputType(field) === 'number'"
          :id="'mf-' + field"
          v-model="fieldValues[field]"
          fluid
        />
        <InputText
          v-else
          :id="'mf-' + field"
          v-model="fieldValues[field]"
          fluid
        />
      </div>

      <Message v-if="showWarning" severity="warn" :closable="false">
        Bitte alle Felder ausfüllen.
      </Message>
    </div>

    <template #footer>
      <Button label="Abbrechen" severity="secondary" text @click="$emit('cancelled')" />
      <Button label="Speichern & Fortfahren" @click="handleConfirm" />
    </template>
  </Dialog>
</template>

<script setup>
import { ref, reactive, watch } from 'vue'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import DatePicker from 'primevue/datepicker'
import Button from 'primevue/button'
import Message from 'primevue/message'

const props = defineProps({
  missingFields: { type: Array, required: true },
  targetStatus: { type: String, required: true },
})

const emit = defineEmits(['confirmed', 'cancelled'])

const fieldLabels = {
  title: 'Titel',
  date: 'Datum',
  start_time: 'Startzeit',
  arrival_time: 'Ankunftszeit',
  departure_time: 'Abfahrtszeit',
  arrival_notes: 'Anreise Notizen',
  venue_name: 'Location / Venue',
  venue_street: 'Straße (Location)',
  venue_number: 'Hausnummer (Location)',
  venue_zip: 'PLZ (Location)',
  venue_city: 'Stadt (Location)',
  venue_country: 'Land (Location)',
  contact_salutation: 'Anrede',
  contact_firstname: 'Vorname',
  contact_lastname: 'Nachname',
  contact_company: 'Firma / Veranstalter',
  contact_street: 'Straße (Kontakt)',
  contact_number: 'Hausnummer (Kontakt)',
  contact_zip: 'PLZ (Kontakt)',
  contact_city: 'Stadt (Kontakt)',
  contact_country: 'Land (Kontakt)',
  contact_email: 'E-Mail',
  contact_phone: 'Telefon',
  contact_email_contract: 'E-Mail (Vertrag)',
  contact_phone_contract: 'Telefon (Vertrag)',
  contact_name_tech: 'Name (Technik)',
  contact_email_tech: 'E-Mail (Technik)',
  contact_phone_tech: 'Telefon (Technik)',
  contact_name_program: 'Name (Programm)',
  contact_email_program: 'E-Mail (Programm)',
  contact_phone_program: 'Telefon (Programm)',
  fee: 'Gage',
  deposit: 'Anzahlung',
  inquiry_date: 'Anfrage vom',
}

const fieldValues = reactive({})
const showWarning = ref(false)

watch(
  () => props.missingFields,
  (fields) => {
    fields.forEach((field) => {
      if (!(field in fieldValues)) fieldValues[field] = ''
    })
    showWarning.value = false
  },
  { immediate: true }
)

function getLabel(field) {
  return fieldLabels[field] || field
}

function getInputType(field) {
  if (field.includes('date')) return 'date'
  if (field.includes('time')) return 'time'
  if (field.includes('email')) return 'email'
  if (field === 'fee' || field === 'deposit') return 'number'
  return 'text'
}

function handleConfirm() {
  const allFilled = props.missingFields.every(
    (field) => fieldValues[field] !== undefined && fieldValues[field] !== null && String(fieldValues[field]).trim() !== ''
  )
  if (!allFilled) {
    showWarning.value = true
    return
  }
  showWarning.value = false
  const payload = {}
  props.missingFields.forEach((field) => {
    payload[field] = fieldValues[field]
  })
  emit('confirmed', payload)
}
</script>

<style scoped>
.tmgmt-missing-fields__group {
  margin-bottom: 12px;
}

.tmgmt-missing-fields__label {
  display: block;
  margin-bottom: 4px;
  font-weight: 600;
  font-size: 13px;
}
</style>
