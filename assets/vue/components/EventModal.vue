<template>
  <Dialog
    :visible="true"
    modal
    :style="{ width: '1100px' }"
    :closable="true"
    @update:visible="$emit('close')"
  >
    <template #header>
      <div class="tmgmt-event-modal__header">
        <InputText
          v-if="data"
          v-model="fields.title"
          placeholder="Titel"
          class="tmgmt-event-modal__title-input"
          @focus="onFocus('title', fields.title)"
          @blur="onBlur('title', fields.title)"
        />
        <span v-else class="tmgmt-event-modal__title-placeholder">Lade...</span>
        <Tag v-if="saveStatus === 'saving'" value="Speichere..." severity="secondary" />
        <Tag v-else-if="saveStatus === 'saved'" value="Gespeichert" severity="success" />
        <Tag v-else-if="saveStatus === 'error'" value="Fehler!" severity="danger" />
      </div>
    </template>

    <!-- Inline-Fehler -->
    <Message v-if="globalError" severity="error" :closable="false">{{ globalError }}</Message>

    <!-- Laden -->
    <div v-if="loading" class="tmgmt-event-modal__loading">
      <ProgressSpinner style="width: 30px; height: 30px" />
      <span>Lade Event-Daten...</span>
    </div>

    <!-- Body -->
    <div v-if="data" class="tmgmt-event-modal__body">
      <div class="tmgmt-event-modal__columns">

        <!-- Linke Spalte -->
        <div class="tmgmt-event-modal__col tmgmt-event-modal__col--left">
          <Accordion :multiple="true" :value="openPanels">
            <AccordionPanel value="anfrage">
              <AccordionHeader>Anfragedaten</AccordionHeader>
              <AccordionContent>
                <div class="tmgmt-form-group">
                  <label class="tmgmt-form-label">Datum</label>
                  <DatePicker v-model="fields.date" dateFormat="yy-mm-dd" fluid
                    @focus="onFocus('date', fields.date)" @blur="onBlur('date', fields.date)" />
                </div>
                <div class="tmgmt-form-group">
                  <label class="tmgmt-form-label">Startzeit</label>
                  <InputText v-model="fields.start_time" type="time" fluid
                    @focus="onFocus('start_time', fields.start_time)" @blur="onBlur('start_time', fields.start_time)" />
                </div>
              </AccordionContent>
            </AccordionPanel>

            <AccordionPanel value="venue">
              <AccordionHeader>Veranstaltungsdaten</AccordionHeader>
              <AccordionContent>
                <div class="tmgmt-form-group">
                  <label class="tmgmt-form-label">Location / Venue</label>
                  <InputText v-model="fields.venue_name" fluid
                    @focus="onFocus('venue_name', fields.venue_name)" @blur="onBlur('venue_name', fields.venue_name)" />
                </div>
                <div class="tmgmt-form-row">
                  <div class="tmgmt-form-group tmgmt-form-group--grow">
                    <label class="tmgmt-form-label">Straße</label>
                    <InputText v-model="fields.venue_street" fluid
                      @focus="onFocus('venue_street', fields.venue_street)" @blur="onBlur('venue_street', fields.venue_street)" />
                  </div>
                  <div class="tmgmt-form-group tmgmt-form-group--small">
                    <label class="tmgmt-form-label">Nr.</label>
                    <InputText v-model="fields.venue_number" fluid
                      @focus="onFocus('venue_number', fields.venue_number)" @blur="onBlur('venue_number', fields.venue_number)" />
                  </div>
                </div>
                <div class="tmgmt-form-row">
                  <div class="tmgmt-form-group tmgmt-form-group--small">
                    <label class="tmgmt-form-label">PLZ</label>
                    <InputText v-model="fields.venue_zip" fluid
                      @focus="onFocus('venue_zip', fields.venue_zip)" @blur="onBlur('venue_zip', fields.venue_zip)" />
                  </div>
                  <div class="tmgmt-form-group tmgmt-form-group--grow">
                    <label class="tmgmt-form-label">Stadt</label>
                    <InputText v-model="fields.venue_city" fluid
                      @focus="onFocus('venue_city', fields.venue_city)" @blur="onBlur('venue_city', fields.venue_city)" />
                  </div>
                </div>
                <div class="tmgmt-form-group">
                  <label class="tmgmt-form-label">Land</label>
                  <InputText v-model="fields.venue_country" fluid
                    @focus="onFocus('venue_country', fields.venue_country)" @blur="onBlur('venue_country', fields.venue_country)" />
                </div>
              </AccordionContent>
            </AccordionPanel>

            <AccordionPanel value="planung">
              <AccordionHeader>Planung</AccordionHeader>
              <AccordionContent>
                <div class="tmgmt-form-group">
                  <label class="tmgmt-form-label">Ankunftszeit</label>
                  <InputText v-model="fields.arrival_time" type="time" fluid
                    @focus="onFocus('arrival_time', fields.arrival_time)" @blur="onBlur('arrival_time', fields.arrival_time)" />
                </div>
                <div class="tmgmt-form-group">
                  <label class="tmgmt-form-label">Abfahrtszeit</label>
                  <InputText v-model="fields.departure_time" type="time" fluid
                    @focus="onFocus('departure_time', fields.departure_time)" @blur="onBlur('departure_time', fields.departure_time)" />
                </div>
                <div class="tmgmt-form-group">
                  <label class="tmgmt-form-label">Anreise Notizen</label>
                  <Textarea v-model="fields.arrival_notes" rows="3" fluid
                    @focus="onFocus('arrival_notes', fields.arrival_notes)" @blur="onBlur('arrival_notes', fields.arrival_notes)" />
                </div>
              </AccordionContent>
            </AccordionPanel>

            <AccordionPanel value="kontakt">
              <AccordionHeader>Kontaktdaten</AccordionHeader>
              <AccordionContent>
                <div class="tmgmt-form-group">
                  <label class="tmgmt-form-label">Anrede</label>
                  <InputText v-model="fields.contact_salutation" fluid
                    @focus="onFocus('contact_salutation', fields.contact_salutation)" @blur="onBlur('contact_salutation', fields.contact_salutation)" />
                </div>
                <div class="tmgmt-form-row">
                  <div class="tmgmt-form-group tmgmt-form-group--grow">
                    <label class="tmgmt-form-label">Vorname</label>
                    <InputText v-model="fields.contact_firstname" fluid
                      @focus="onFocus('contact_firstname', fields.contact_firstname)" @blur="onBlur('contact_firstname', fields.contact_firstname)" />
                  </div>
                  <div class="tmgmt-form-group tmgmt-form-group--grow">
                    <label class="tmgmt-form-label">Nachname</label>
                    <InputText v-model="fields.contact_lastname" fluid
                      @focus="onFocus('contact_lastname', fields.contact_lastname)" @blur="onBlur('contact_lastname', fields.contact_lastname)" />
                  </div>
                </div>
                <div class="tmgmt-form-group">
                  <label class="tmgmt-form-label">Firma / Veranstalter</label>
                  <InputText v-model="fields.contact_company" fluid
                    @focus="onFocus('contact_company', fields.contact_company)" @blur="onBlur('contact_company', fields.contact_company)" />
                </div>
                <div class="tmgmt-form-row">
                  <div class="tmgmt-form-group tmgmt-form-group--grow">
                    <label class="tmgmt-form-label">Straße</label>
                    <InputText v-model="fields.contact_street" fluid
                      @focus="onFocus('contact_street', fields.contact_street)" @blur="onBlur('contact_street', fields.contact_street)" />
                  </div>
                  <div class="tmgmt-form-group tmgmt-form-group--small">
                    <label class="tmgmt-form-label">Nr.</label>
                    <InputText v-model="fields.contact_number" fluid
                      @focus="onFocus('contact_number', fields.contact_number)" @blur="onBlur('contact_number', fields.contact_number)" />
                  </div>
                </div>
                <div class="tmgmt-form-row">
                  <div class="tmgmt-form-group tmgmt-form-group--small">
                    <label class="tmgmt-form-label">PLZ</label>
                    <InputText v-model="fields.contact_zip" fluid
                      @focus="onFocus('contact_zip', fields.contact_zip)" @blur="onBlur('contact_zip', fields.contact_zip)" />
                  </div>
                  <div class="tmgmt-form-group tmgmt-form-group--grow">
                    <label class="tmgmt-form-label">Stadt</label>
                    <InputText v-model="fields.contact_city" fluid
                      @focus="onFocus('contact_city', fields.contact_city)" @blur="onBlur('contact_city', fields.contact_city)" />
                  </div>
                </div>
                <div class="tmgmt-form-group">
                  <label class="tmgmt-form-label">Land</label>
                  <InputText v-model="fields.contact_country" fluid
                    @focus="onFocus('contact_country', fields.contact_country)" @blur="onBlur('contact_country', fields.contact_country)" />
                </div>
                <div class="tmgmt-form-group">
                  <label class="tmgmt-form-label">E-Mail (Vertrag)</label>
                  <InputText v-model="fields.contact_email_contract" type="email" fluid
                    @focus="onFocus('contact_email_contract', fields.contact_email_contract)" @blur="onBlur('contact_email_contract', fields.contact_email_contract)" />
                </div>
                <div class="tmgmt-form-group">
                  <label class="tmgmt-form-label">Telefon (Vertrag)</label>
                  <InputText v-model="fields.contact_phone_contract" fluid
                    @focus="onFocus('contact_phone_contract', fields.contact_phone_contract)" @blur="onBlur('contact_phone_contract', fields.contact_phone_contract)" />
                </div>
              </AccordionContent>
            </AccordionPanel>

            <AccordionPanel value="vertrag">
              <AccordionHeader>Vertragsdaten</AccordionHeader>
              <AccordionContent>
                <div class="tmgmt-form-group">
                  <label class="tmgmt-form-label">Gage (€)</label>
                  <InputNumber v-model="fields.fee" mode="currency" currency="EUR" locale="de-DE" fluid
                    @focus="onFocus('fee', fields.fee)" @blur="onBlur('fee', fields.fee)" />
                </div>
                <div class="tmgmt-form-group">
                  <label class="tmgmt-form-label">Anzahlung (€)</label>
                  <InputNumber v-model="fields.deposit" mode="currency" currency="EUR" locale="de-DE" fluid
                    @focus="onFocus('deposit', fields.deposit)" @blur="onBlur('deposit', fields.deposit)" />
                </div>
                <div class="tmgmt-form-group">
                  <label class="tmgmt-form-label">Anfrage vom</label>
                  <DatePicker v-model="fields.inquiry_date" dateFormat="yy-mm-dd" fluid
                    @focus="onFocus('inquiry_date', fields.inquiry_date)" @blur="onBlur('inquiry_date', fields.inquiry_date)" />
                </div>
              </AccordionContent>
            </AccordionPanel>
          </Accordion>
        </div>

        <!-- Rechte Spalte -->
        <div class="tmgmt-event-modal__col tmgmt-event-modal__col--right">

          <!-- Status & Aktionen -->
          <div class="tmgmt-event-modal__panel">
            <h4>Status &amp; Aktionen</h4>
            <div class="tmgmt-form-group">
              <label class="tmgmt-form-label">Status</label>
              <Select v-model="fields.status" :options="statusOptions" optionLabel="label" optionValue="value" fluid @change="onStatusChange" />
            </div>
            <div v-if="data.actions && data.actions.length" class="tmgmt-event-modal__actions">
              <Message v-if="actionError" severity="error" :closable="false">{{ actionError }}</Message>
              <Button
                v-for="action in data.actions"
                :key="action.id"
                :label="action.label"
                :icon="action.icon ? 'pi pi-' + action.icon : undefined"
                severity="secondary"
                :loading="actionLoading === action.id"
                class="tmgmt-event-modal__action-btn"
                @click="executeAction(action)"
              />
            </div>
          </div>

          <!-- Notizen -->
          <div class="tmgmt-event-modal__panel">
            <h4>Notizen</h4>
            <Textarea v-model="fields.content" rows="6" fluid
              @focus="onFocus('content', fields.content)" @blur="onBlur('content', fields.content)" />
          </div>

          <!-- Karte -->
          <div v-if="hasGeo" class="tmgmt-event-modal__panel">
            <h4>Karte</h4>
            <div id="tmgmt-event-map" class="tmgmt-event-modal__map"></div>
          </div>
        </div>
      </div>

      <!-- Logbuch -->
      <Accordion :multiple="true" :value="['logbuch']" class="tmgmt-event-modal__full-section">
        <AccordionPanel value="logbuch">
          <AccordionHeader>Logbuch</AccordionHeader>
          <AccordionContent>
            <div v-if="sortedLogs.length === 0" class="tmgmt-event-modal__empty">Keine Einträge.</div>
            <div v-for="log in sortedLogs" :key="log.id ?? log.date" class="tmgmt-logbuch-entry">
              <Tag :value="log.date" severity="secondary" />
              <span>{{ log.text ?? log.message ?? log.content }}</span>
            </div>
          </AccordionContent>
        </AccordionPanel>

        <AccordionPanel value="anhaenge">
          <AccordionHeader>Anhänge</AccordionHeader>
          <AccordionContent>
            <div v-if="!data.attachments || data.attachments.length === 0" class="tmgmt-event-modal__empty">
              Keine Anhänge vorhanden.
            </div>
            <div v-for="file in (data.attachments ?? [])" :key="file.id ?? file.url" class="tmgmt-attachment-item">
              <a :href="file.url" target="_blank" rel="noopener">{{ file.name ?? file.filename }}</a>
            </div>
          </AccordionContent>
        </AccordionPanel>
      </Accordion>
    </div>

    <!-- MissingFieldsModal -->
    <MissingFieldsModal
      v-if="missingFieldsModal.visible"
      :missing-fields="missingFieldsModal.fields"
      :target-status="missingFieldsModal.targetStatus"
      @confirmed="onMissingFieldsConfirmed"
      @cancelled="onMissingFieldsCancelled"
    />
  </Dialog>
</template>

<script setup>
import { ref, reactive, computed, onMounted, nextTick } from 'vue'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import DatePicker from 'primevue/datepicker'
import Select from 'primevue/select'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Message from 'primevue/message'
import Accordion from 'primevue/accordion'
import AccordionPanel from 'primevue/accordionpanel'
import AccordionHeader from 'primevue/accordionheader'
import AccordionContent from 'primevue/accordioncontent'
import ProgressSpinner from 'primevue/progressspinner'
import apiService from '../services/apiService.js'
import MissingFieldsModal from './MissingFieldsModal.vue'

const props = defineProps({
  eventId: { type: Number, required: true }
})
const emit = defineEmits(['close'])

const data = ref(null)
const loading = ref(false)
const globalError = ref('')
const openPanels = ref(['anfrage', 'venue', 'planung', 'kontakt', 'vertrag'])

const fields = reactive({
  title: '', date: '', start_time: '',
  venue_name: '', venue_street: '', venue_number: '', venue_zip: '', venue_city: '', venue_country: '',
  arrival_time: '', departure_time: '', arrival_notes: '',
  contact_salutation: '', contact_firstname: '', contact_lastname: '', contact_company: '',
  contact_street: '', contact_number: '', contact_zip: '', contact_city: '', contact_country: '',
  contact_email_contract: '', contact_phone_contract: '',
  fee: '', deposit: '', inquiry_date: '',
  status: '', content: ''
})

const focusValues = {}
const saveStatus = ref('')
let saveStatusTimer = null

const actionLoading = ref(null)
const actionError = ref('')

const missingFieldsModal = reactive({
  visible: false, fields: [], targetStatus: '', previousStatus: ''
})

const statuses = computed(() =>
  (window.tmgmtData && window.tmgmtData.statuses) ? window.tmgmtData.statuses : {}
)

const statusOptions = computed(() =>
  Object.entries(statuses.value).map(([value, label]) => ({ value, label }))
)

const hasGeo = computed(() =>
  data.value?.meta?.geo_lat && data.value?.meta?.geo_lng
)

const sortedLogs = computed(() => {
  if (!data.value?.logs) return []
  return [...data.value.logs].sort((a, b) => new Date(b.date) - new Date(a.date))
})

onMounted(async () => { await loadEvent() })

async function loadEvent() {
  loading.value = true
  globalError.value = ''
  try {
    const response = await apiService.get('/events/' + props.eventId)
    data.value = response
    const meta = response.meta ?? response
    Object.keys(fields).forEach(key => {
      if (meta[key] !== undefined && meta[key] !== null) fields[key] = meta[key]
      else if (response[key] !== undefined && response[key] !== null) fields[key] = response[key]
    })
    if (meta.geo_lat && meta.geo_lng) {
      await nextTick()
      initMap(meta.geo_lat, meta.geo_lng)
    }
  } catch (err) {
    globalError.value = err?.message ?? 'Fehler beim Laden der Event-Daten.'
  } finally {
    loading.value = false
  }
}

function initMap(lat, lng) {
  if (!window.L) return
  const mapEl = document.getElementById('tmgmt-event-map')
  if (!mapEl) return
  const map = window.L.map('tmgmt-event-map').setView([lat, lng], 13)
  window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
  }).addTo(map)
  window.L.marker([lat, lng]).addTo(map)
}

function onFocus(fieldName, value) {
  focusValues[fieldName] = value
}

async function onBlur(fieldName, value) {
  const original = focusValues[fieldName]
  if (original === undefined || original === value) return
  saveStatus.value = 'saving'
  clearTimeout(saveStatusTimer)
  try {
    await apiService.post('/events/' + props.eventId, { [fieldName]: value })
    saveStatus.value = 'saved'
  } catch (err) {
    saveStatus.value = 'error'
    globalError.value = err?.message ?? 'Fehler beim Speichern.'
  } finally {
    saveStatusTimer = setTimeout(() => { saveStatus.value = '' }, 3000)
  }
}

function onStatusChange() {
  const newStatus = fields.status
  const requirements = window.tmgmtData?.status_requirements?.[newStatus]
  if (!requirements || requirements.length === 0) {
    persistStatus(newStatus)
    return
  }
  const missing = requirements.filter(f => {
    const val = fields[f]
    return val === undefined || val === null || String(val).trim() === ''
  })
  if (missing.length > 0) {
    missingFieldsModal.previousStatus = data.value?.status ?? ''
    missingFieldsModal.targetStatus = newStatus
    missingFieldsModal.fields = missing
    missingFieldsModal.visible = true
  } else {
    persistStatus(newStatus)
  }
}

function persistStatus(status) {
  saveStatus.value = 'saving'
  clearTimeout(saveStatusTimer)
  apiService.post('/events/' + props.eventId, { status })
    .then(() => {
      saveStatus.value = 'saved'
      if (data.value) data.value.status = status
    })
    .catch(err => {
      saveStatus.value = 'error'
      globalError.value = err?.message ?? 'Fehler beim Speichern des Status.'
    })
    .finally(() => {
      saveStatusTimer = setTimeout(() => { saveStatus.value = '' }, 3000)
    })
}

async function onMissingFieldsConfirmed(payload) {
  missingFieldsModal.visible = false
  Object.entries(payload).forEach(([key, val]) => {
    if (key in fields) fields[key] = val
  })
  saveStatus.value = 'saving'
  clearTimeout(saveStatusTimer)
  try {
    await apiService.post('/events/' + props.eventId, {
      ...payload, status: missingFieldsModal.targetStatus
    })
    saveStatus.value = 'saved'
    if (data.value) data.value.status = missingFieldsModal.targetStatus
  } catch (err) {
    saveStatus.value = 'error'
    globalError.value = err?.message ?? 'Fehler beim Speichern.'
    fields.status = missingFieldsModal.previousStatus
  } finally {
    saveStatusTimer = setTimeout(() => { saveStatus.value = '' }, 3000)
  }
}

function onMissingFieldsCancelled() {
  missingFieldsModal.visible = false
  fields.status = missingFieldsModal.previousStatus
}

async function executeAction(action) {
  actionError.value = ''
  actionLoading.value = action.id
  try {
    await apiService.post('/events/' + props.eventId + '/actions/' + action.id + '/execute', action.params ?? {})
    await loadEvent()
  } catch (err) {
    actionError.value = err?.message ?? 'Fehler beim Ausführen der Aktion.'
  } finally {
    actionLoading.value = null
  }
}
</script>

<style scoped>
.tmgmt-event-modal__header {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
}

.tmgmt-event-modal__title-input {
  flex: 1;
  font-size: 18px;
  font-weight: 600;
}

.tmgmt-event-modal__title-placeholder {
  flex: 1;
  color: var(--p-text-muted-color);
}

.tmgmt-event-modal__loading {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 20px;
  justify-content: center;
}

.tmgmt-event-modal__body {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.tmgmt-event-modal__columns {
  display: flex;
  gap: 20px;
}

.tmgmt-event-modal__col--left {
  flex: 2;
  min-width: 0;
}

.tmgmt-event-modal__col--right {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.tmgmt-event-modal__panel {
  border: 1px solid var(--p-surface-200);
  border-radius: var(--p-border-radius);
  padding: 14px;
}

.tmgmt-event-modal__panel h4 {
  margin: 0 0 12px 0;
  font-size: 13px;
  font-weight: 600;
}

.tmgmt-event-modal__actions {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 12px;
}

.tmgmt-event-modal__action-btn {
  width: 100%;
  justify-content: flex-start;
}

.tmgmt-event-modal__map {
  height: 250px;
  border-radius: var(--p-border-radius);
}

.tmgmt-event-modal__full-section {
  margin-top: 16px;
}

.tmgmt-event-modal__empty {
  color: var(--p-text-muted-color);
  font-size: 13px;
  font-style: italic;
}

.tmgmt-logbuch-entry {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 6px 0;
  border-bottom: 1px solid var(--p-surface-100);
  font-size: 13px;
}

.tmgmt-logbuch-entry:last-child {
  border-bottom: none;
}

.tmgmt-attachment-item {
  padding: 4px 0;
  font-size: 13px;
}

.tmgmt-form-row {
  display: flex;
  gap: 10px;
}

.tmgmt-form-group {
  margin-bottom: 10px;
}

.tmgmt-form-group--grow {
  flex: 1;
}

.tmgmt-form-group--small {
  flex: 0 0 80px;
}

.tmgmt-form-label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  margin-bottom: 4px;
  color: var(--p-text-color);
}

@media (max-width: 768px) {
  .tmgmt-event-modal__columns {
    flex-direction: column;
  }
}
</style>
