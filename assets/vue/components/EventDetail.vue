<template>
  <div class="tmgmt-event-detail">
    <!-- Fehler -->
    <Message v-if="globalError" severity="error" :closable="false">{{ globalError }}</Message>

    <!-- Laden -->
    <div v-if="loading" class="tmgmt-event-detail__loading">
      <ProgressSpinner style="width: 36px; height: 36px" />
      <span>Lade Event-Daten...</span>
    </div>

    <template v-if="data && !loading">
      <!-- Header-Zeile -->
      <div class="tmgmt-event-detail__header">
        <Tag v-if="data.meta?.event_id" :value="data.meta.event_id" severity="secondary" />
        <InputText
          v-model="fields.title"
          placeholder="Titel eingeben..."
          class="tmgmt-event-detail__title"
          @focus="onFocus('title', fields.title)"
          @blur="onBlur('title', fields.title)"
        />
        <div class="tmgmt-event-detail__header-right">
          <Tag v-if="saveStatus === 'saving'" value="Speichere..." severity="secondary" />
          <Tag v-else-if="saveStatus === 'saved'" value="Gespeichert" severity="success" />
          <Tag v-else-if="saveStatus === 'error'" value="Fehler!" severity="danger" />
          <Select
            v-model="fields.status"
            :options="statusOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Status"
            class="tmgmt-event-detail__status-select"
            @change="onStatusChange"
          />
        </div>
      </div>

      <!-- Hauptbereich: Tabs links, Sidebar rechts -->
      <div class="tmgmt-event-detail__layout">
        <!-- Linke Spalte: Tabs -->
        <div class="tmgmt-event-detail__main">
          <Tabs :value="activeTab" @update:value="activeTab = $event">
            <TabList>
              <Tab value="details"><i class="pi pi-calendar" /> Details</Tab>
              <Tab value="kontakt"><i class="pi pi-user" /> Kontakt</Tab>
              <Tab value="vertrag"><i class="pi pi-file" /> Vertrag</Tab>
              <Tab value="logbuch">
                <i class="pi pi-history" /> Logbuch
                <Tag v-if="sortedLogs.length" :value="String(sortedLogs.length)" severity="secondary" rounded class="tmgmt-tab-badge" />
              </Tab>
              <Tab value="kommunikation">
                <i class="pi pi-envelope" /> Kommunikation
                <Tag v-if="sortedComm.length" :value="String(sortedComm.length)" severity="secondary" rounded class="tmgmt-tab-badge" />
              </Tab>
              <Tab value="anhaenge">
                <i class="pi pi-paperclip" /> Anhänge
                <Tag v-if="data.attachments?.length" :value="String(data.attachments.length)" severity="secondary" rounded class="tmgmt-tab-badge" />
              </Tab>
            </TabList>

            <TabPanels>
              <!-- Tab: Details -->
              <TabPanel value="details">
                <!-- Anfragedaten -->
                <div class="tmgmt-section">
                  <h4 class="tmgmt-section__title">Anfragedaten</h4>
                  <div class="tmgmt-form-row">
                    <div class="tmgmt-form-group tmgmt-form-group--grow">
                      <label class="tmgmt-form-label">Datum</label>
                      <DatePicker v-model="fields.date" dateFormat="yy-mm-dd" fluid
                        @focus="onFocus('date', fields.date)" @blur="onBlur('date', fields.date)" />
                    </div>
                    <div class="tmgmt-form-group tmgmt-form-group--grow">
                      <label class="tmgmt-form-label">Startzeit</label>
                      <InputText v-model="fields.start_time" type="time" fluid
                        @focus="onFocus('start_time', fields.start_time)" @blur="onBlur('start_time', fields.start_time)" />
                    </div>
                    <div class="tmgmt-form-group tmgmt-form-group--grow">
                      <label class="tmgmt-form-label">Anfrage vom</label>
                      <DatePicker v-model="fields.inquiry_date" dateFormat="yy-mm-dd" fluid
                        @focus="onFocus('inquiry_date', fields.inquiry_date)" @blur="onBlur('inquiry_date', fields.inquiry_date)" />
                    </div>
                  </div>
                </div>

                <!-- Veranstaltungsort -->
                <div class="tmgmt-section">
                  <h4 class="tmgmt-section__title">Veranstaltungsort</h4>
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
                    <div class="tmgmt-form-group tmgmt-form-group--medium">
                      <label class="tmgmt-form-label">Land</label>
                      <InputText v-model="fields.venue_country" fluid
                        @focus="onFocus('venue_country', fields.venue_country)" @blur="onBlur('venue_country', fields.venue_country)" />
                    </div>
                  </div>
                </div>

                <!-- Planung -->
                <div class="tmgmt-section">
                  <h4 class="tmgmt-section__title">Planung</h4>
                  <div class="tmgmt-form-row">
                    <div class="tmgmt-form-group tmgmt-form-group--grow">
                      <label class="tmgmt-form-label">Ankunftszeit</label>
                      <InputText v-model="fields.arrival_time" type="time" fluid
                        @focus="onFocus('arrival_time', fields.arrival_time)" @blur="onBlur('arrival_time', fields.arrival_time)" />
                    </div>
                    <div class="tmgmt-form-group tmgmt-form-group--grow">
                      <label class="tmgmt-form-label">Abfahrtszeit</label>
                      <InputText v-model="fields.departure_time" type="time" fluid
                        @focus="onFocus('departure_time', fields.departure_time)" @blur="onBlur('departure_time', fields.departure_time)" />
                    </div>
                  </div>
                  <div class="tmgmt-form-group">
                    <label class="tmgmt-form-label">Anreise Notizen</label>
                    <Textarea v-model="fields.arrival_notes" rows="2" fluid autoResize
                      @focus="onFocus('arrival_notes', fields.arrival_notes)" @blur="onBlur('arrival_notes', fields.arrival_notes)" />
                  </div>
                </div>

                <!-- Notizen -->
                <div class="tmgmt-section">
                  <h4 class="tmgmt-section__title">Notizen</h4>
                  <Textarea v-model="fields.content" rows="4" fluid autoResize placeholder="Interne Notizen..."
                    @focus="onFocus('content', fields.content)" @blur="onBlur('content', fields.content)" />
                </div>
              </TabPanel>

              <!-- Tab: Kontakt -->
              <TabPanel value="kontakt">
                <div class="tmgmt-section">
                  <h4 class="tmgmt-section__title">Vertragskontakt</h4>
                  <div class="tmgmt-form-row">
                    <div class="tmgmt-form-group tmgmt-form-group--small">
                      <label class="tmgmt-form-label">Anrede</label>
                      <InputText v-model="fields.contact_salutation" fluid
                        @focus="onFocus('contact_salutation', fields.contact_salutation)" @blur="onBlur('contact_salutation', fields.contact_salutation)" />
                    </div>
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
                    <div class="tmgmt-form-group tmgmt-form-group--medium">
                      <label class="tmgmt-form-label">Land</label>
                      <InputText v-model="fields.contact_country" fluid
                        @focus="onFocus('contact_country', fields.contact_country)" @blur="onBlur('contact_country', fields.contact_country)" />
                    </div>
                  </div>
                  <div class="tmgmt-form-row">
                    <div class="tmgmt-form-group tmgmt-form-group--grow">
                      <label class="tmgmt-form-label">E-Mail (Vertrag)</label>
                      <InputText v-model="fields.contact_email_contract" type="email" fluid
                        @focus="onFocus('contact_email_contract', fields.contact_email_contract)" @blur="onBlur('contact_email_contract', fields.contact_email_contract)" />
                    </div>
                    <div class="tmgmt-form-group tmgmt-form-group--grow">
                      <label class="tmgmt-form-label">Telefon (Vertrag)</label>
                      <InputText v-model="fields.contact_phone_contract" fluid
                        @focus="onFocus('contact_phone_contract', fields.contact_phone_contract)" @blur="onBlur('contact_phone_contract', fields.contact_phone_contract)" />
                    </div>
                  </div>
                </div>

                <!-- Weitere Kontakte (read-only, aus Veranstalter aufgelöst) -->
                <div class="tmgmt-section">
                  <h4 class="tmgmt-section__title">Weitere Kontakte</h4>
                  <div class="tmgmt-contact-cards">
                    <div class="tmgmt-contact-card">
                      <Tag value="Technik" severity="secondary" />
                      <div class="tmgmt-contact-card__name">{{ data.meta?.contact_name_tech || '—' }}</div>
                      <div v-if="data.meta?.contact_email_tech" class="tmgmt-contact-card__line">
                        <i class="pi pi-envelope" /> {{ data.meta.contact_email_tech }}
                      </div>
                      <div v-if="data.meta?.contact_phone_tech" class="tmgmt-contact-card__line">
                        <i class="pi pi-phone" /> {{ data.meta.contact_phone_tech }}
                      </div>
                    </div>
                    <div class="tmgmt-contact-card">
                      <Tag value="Programm" severity="secondary" />
                      <div class="tmgmt-contact-card__name">{{ data.meta?.contact_name_program || '—' }}</div>
                      <div v-if="data.meta?.contact_email_program" class="tmgmt-contact-card__line">
                        <i class="pi pi-envelope" /> {{ data.meta.contact_email_program }}
                      </div>
                      <div v-if="data.meta?.contact_phone_program" class="tmgmt-contact-card__line">
                        <i class="pi pi-phone" /> {{ data.meta.contact_phone_program }}
                      </div>
                    </div>
                  </div>
                </div>
              </TabPanel>

              <!-- Tab: Vertrag -->
              <TabPanel value="vertrag">
                <div class="tmgmt-section">
                  <h4 class="tmgmt-section__title">Vertragsdaten</h4>
                  <div class="tmgmt-form-row">
                    <div class="tmgmt-form-group tmgmt-form-group--grow">
                      <label class="tmgmt-form-label">Gage (€)</label>
                      <InputNumber v-model="fields.fee" mode="currency" currency="EUR" locale="de-DE" fluid
                        @focus="onFocus('fee', fields.fee)" @blur="onBlur('fee', fields.fee)" />
                    </div>
                    <div class="tmgmt-form-group tmgmt-form-group--grow">
                      <label class="tmgmt-form-label">Anzahlung (€)</label>
                      <InputNumber v-model="fields.deposit" mode="currency" currency="EUR" locale="de-DE" fluid
                        @focus="onFocus('deposit', fields.deposit)" @blur="onBlur('deposit', fields.deposit)" />
                    </div>
                  </div>
                </div>
              </TabPanel>

              <!-- Tab: Logbuch -->
              <TabPanel value="logbuch">
                <div v-if="sortedLogs.length === 0" class="tmgmt-empty">Keine Einträge.</div>
                <div v-for="log in sortedLogs" :key="log.id ?? log.date" class="tmgmt-log-entry">
                  <div class="tmgmt-log-entry__meta">
                    <Tag :value="log.date" severity="secondary" />
                    <span v-if="log.user" class="tmgmt-log-entry__user">{{ log.user }}</span>
                  </div>
                  <div class="tmgmt-log-entry__message">{{ log.text ?? log.message ?? log.content }}</div>
                </div>
              </TabPanel>

              <!-- Tab: Kommunikation -->
              <TabPanel value="kommunikation">
                <div v-if="sortedComm.length === 0" class="tmgmt-empty">Keine Kommunikation vorhanden.</div>
                <Accordion v-else :multiple="true">
                  <AccordionPanel v-for="comm in sortedComm" :key="comm.id" :value="'comm-' + comm.id">
                    <AccordionHeader>
                      <div class="tmgmt-comm-header">
                        <i :class="comm.type === 'email' ? 'pi pi-envelope' : 'pi pi-comment'" />
                        <span class="tmgmt-comm-header__subject">{{ comm.subject || '(Kein Betreff)' }}</span>
                        <Tag :value="comm.date" severity="secondary" />
                        <span class="tmgmt-comm-header__recipient">→ {{ comm.recipient }}</span>
                      </div>
                    </AccordionHeader>
                    <AccordionContent>
                      <div v-html="comm.content" />
                    </AccordionContent>
                  </AccordionPanel>
                </Accordion>
              </TabPanel>

              <!-- Tab: Anhänge -->
              <TabPanel value="anhaenge">
                <div v-if="!data.attachments || data.attachments.length === 0" class="tmgmt-empty">
                  Keine Anhänge vorhanden.
                </div>
                <div v-for="file in (data.attachments ?? [])" :key="file.id ?? file.url" class="tmgmt-attachment">
                  <i class="pi pi-file" />
                  <a :href="file.url" target="_blank" rel="noopener">{{ file.title ?? file.filename }}</a>
                  <Tag v-if="file.category" :value="file.category" severity="secondary" />
                </div>
              </TabPanel>
            </TabPanels>
          </Tabs>
        </div>

        <!-- Rechte Spalte: Sidebar -->
        <div class="tmgmt-event-detail__sidebar">
          <!-- Aktionen -->
          <div v-if="data.actions && data.actions.length" class="tmgmt-sidebar-panel">
            <h4 class="tmgmt-sidebar-panel__title"><i class="pi pi-bolt" /> Aktionen</h4>
            <Message v-if="actionError" severity="error" :closable="false">{{ actionError }}</Message>
            <Button
              v-for="action in data.actions"
              :key="action.id"
              :label="action.label"
              severity="secondary"
              :loading="actionLoading === action.id"
              class="tmgmt-sidebar-panel__btn"
              @click="executeAction(action)"
            />
          </div>

          <!-- Touren -->
          <div v-if="data.tours && data.tours.length" class="tmgmt-sidebar-panel">
            <h4 class="tmgmt-sidebar-panel__title"><i class="pi pi-map" /> Touren</h4>
            <div v-for="tour in data.tours" :key="tour.id" class="tmgmt-tour-item">
              <i :class="tour.status === 'error' ? 'pi pi-times-circle tmgmt-tour-item--error' : tour.status === 'warning' ? 'pi pi-exclamation-triangle tmgmt-tour-item--warning' : 'pi pi-check-circle tmgmt-tour-item--ok'" />
              <a :href="tour.link" target="_blank" rel="noopener">{{ tour.title }}</a>
              <Tag :value="tour.mode" severity="secondary" />
            </div>
          </div>

          <!-- Karte -->
          <div v-if="hasGeo" class="tmgmt-sidebar-panel">
            <h4 class="tmgmt-sidebar-panel__title"><i class="pi pi-map-marker" /> Karte</h4>
            <div ref="mapContainer" class="tmgmt-event-detail__map"></div>
          </div>
        </div>
      </div>
    </template>

    <!-- MissingFieldsModal -->
    <MissingFieldsModal
      v-if="missingFieldsModal.visible"
      :missing-fields="missingFieldsModal.fields"
      :target-status="missingFieldsModal.targetStatus"
      @confirmed="onMissingFieldsConfirmed"
      @cancelled="onMissingFieldsCancelled"
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, nextTick } from 'vue'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import DatePicker from 'primevue/datepicker'
import Select from 'primevue/select'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Message from 'primevue/message'
import Tabs from 'primevue/tabs'
import TabList from 'primevue/tablist'
import Tab from 'primevue/tab'
import TabPanels from 'primevue/tabpanels'
import TabPanel from 'primevue/tabpanel'
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

const data = ref(null)
const loading = ref(false)
const globalError = ref('')
const activeTab = ref('details')
const mapContainer = ref(null)

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
  (window.tmgmtData?.statuses) ? window.tmgmtData.statuses : {}
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
const sortedComm = computed(() => {
  if (!data.value?.communication) return []
  return [...data.value.communication].sort((a, b) => new Date(b.date) - new Date(a.date))
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
  if (!window.L || !mapContainer.value) return
  const map = window.L.map(mapContainer.value).setView([lat, lng], 13)
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
    missingFieldsModal.previousStatus = data.value?.meta?.status ?? ''
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
      if (data.value?.meta) data.value.meta.status = status
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
    if (data.value?.meta) data.value.meta.status = missingFieldsModal.targetStatus
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
.tmgmt-event-detail {
  width: 100%;
  max-width: 1200px;
}

.tmgmt-event-detail__loading {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 40px;
  justify-content: center;
}

/* Header */
.tmgmt-event-detail__header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}

.tmgmt-event-detail__title {
  flex: 1;
  min-width: 200px;
  font-size: 18px;
  font-weight: 600;
}

.tmgmt-event-detail__header-right {
  display: flex;
  align-items: center;
  gap: 10px;
}

.tmgmt-event-detail__status-select {
  min-width: 180px;
}

/* Layout: Main + Sidebar */
.tmgmt-event-detail__layout {
  display: flex;
  gap: 24px;
  align-items: flex-start;
}

.tmgmt-event-detail__main {
  flex: 1;
  min-width: 0;
}

.tmgmt-event-detail__sidebar {
  width: 280px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* Sections inside tabs */
.tmgmt-section {
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 1px solid var(--p-surface-200);
}

.tmgmt-section:last-child {
  border-bottom: none;
  margin-bottom: 0;
  padding-bottom: 0;
}

.tmgmt-section__title {
  margin: 0 0 12px 0;
  font-size: 13px;
  font-weight: 600;
  color: var(--p-text-muted-color);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* Form layout */
.tmgmt-form-row {
  display: flex;
  gap: 10px;
}

.tmgmt-form-group {
  margin-bottom: 10px;
}

.tmgmt-form-group--grow { flex: 1; }
.tmgmt-form-group--small { flex: 0 0 80px; }
.tmgmt-form-group--medium { flex: 0 0 140px; }

.tmgmt-form-label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  margin-bottom: 4px;
  color: var(--p-text-color);
}

/* Tab badge */
.tmgmt-tab-badge {
  margin-left: 6px;
  font-size: 11px;
}

/* Sidebar panels */
.tmgmt-sidebar-panel {
  border: 1px solid var(--p-surface-200);
  border-radius: var(--p-border-radius);
  padding: 14px;
}

.tmgmt-sidebar-panel__title {
  margin: 0 0 12px 0;
  font-size: 13px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 6px;
}

.tmgmt-sidebar-panel__btn {
  width: 100%;
  justify-content: flex-start;
  margin-bottom: 6px;
}

.tmgmt-sidebar-panel__btn:last-child {
  margin-bottom: 0;
}

/* Tour items */
.tmgmt-tour-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 4px 0;
  font-size: 13px;
}

.tmgmt-tour-item a {
  color: var(--p-primary-color);
  text-decoration: none;
}

.tmgmt-tour-item--ok { color: var(--p-green-500); }
.tmgmt-tour-item--warning { color: var(--p-yellow-500); }
.tmgmt-tour-item--error { color: var(--p-red-500); }

/* Contact cards */
.tmgmt-contact-cards {
  display: flex;
  gap: 12px;
}

.tmgmt-contact-card {
  flex: 1;
  border: 1px solid var(--p-surface-200);
  border-radius: var(--p-border-radius);
  padding: 12px;
}

.tmgmt-contact-card__name {
  font-weight: 600;
  font-size: 14px;
  margin: 8px 0 4px;
}

.tmgmt-contact-card__line {
  font-size: 13px;
  color: var(--p-text-muted-color);
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 2px;
}

/* Log entries */
.tmgmt-log-entry {
  padding: 8px 0;
  border-bottom: 1px solid var(--p-surface-100);
  font-size: 13px;
}

.tmgmt-log-entry:last-child {
  border-bottom: none;
}

.tmgmt-log-entry__meta {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 2px;
}

.tmgmt-log-entry__user {
  color: var(--p-text-muted-color);
  font-size: 12px;
}

/* Communication header */
.tmgmt-comm-header {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  font-size: 13px;
}

.tmgmt-comm-header__subject {
  font-weight: 600;
}

.tmgmt-comm-header__recipient {
  color: var(--p-text-muted-color);
  font-size: 12px;
}

/* Attachments */
.tmgmt-attachment {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 0;
  font-size: 13px;
}

.tmgmt-attachment a {
  color: var(--p-primary-color);
  text-decoration: none;
}

/* Map */
.tmgmt-event-detail__map {
  height: 220px;
  border-radius: var(--p-border-radius);
}

/* Empty state */
.tmgmt-empty {
  color: var(--p-text-muted-color);
  font-size: 13px;
  font-style: italic;
  padding: 8px 0;
}

/* Responsive */
@media (max-width: 768px) {
  .tmgmt-event-detail__layout {
    flex-direction: column;
  }

  .tmgmt-event-detail__sidebar {
    width: 100%;
  }

  .tmgmt-form-row {
    flex-direction: column;
  }

  .tmgmt-contact-cards {
    flex-direction: column;
  }
}
</style>
