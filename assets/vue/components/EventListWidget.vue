<template>
  <div class="tmgmt-event-list">
    <!-- Toolbar -->
    <div class="tmgmt-event-list__toolbar">
      <IconField>
        <InputIcon class="pi pi-search" />
        <InputText v-model="searchQuery" placeholder="Suchen..." class="tmgmt-event-list__search" />
      </IconField>
      <Select
        v-model="statusFilter"
        :options="statusFilterOptions"
        optionLabel="label"
        optionValue="value"
        placeholder="Alle Status"
        showClear
        class="tmgmt-event-list__filter"
      />
    </div>

    <!-- Fehler -->
    <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>

    <!-- Laden -->
    <div v-if="loading" class="tmgmt-event-list__loading">
      <ProgressSpinner style="width: 30px; height: 30px" />
      <span>Lade Events...</span>
    </div>

    <!-- Tabelle -->
    <DataTable
      v-if="!loading && !error"
      :value="filteredEvents"
      :sortField="sortField"
      :sortOrder="sortOrder"
      @sort="onSort"
      stripedRows
      :rowHover="true"
      class="tmgmt-event-list__table"
      @row-click="onRowClick"
      :rowClass="() => 'tmgmt-event-list__row'"
    >
      <Column field="event_id" header="ID" sortable style="width: 100px">
        <template #body="{ data }">
          <Tag :value="data.event_id || '—'" severity="secondary" />
        </template>
      </Column>
      <Column field="date" header="Datum" sortable style="width: 120px">
        <template #body="{ data }">
          {{ formatDate(data.date) }}
        </template>
      </Column>
      <Column field="title" header="Titel" sortable />
      <Column field="venue" header="Location" sortable>
        <template #body="{ data }">
          <span v-if="data.venue">{{ data.venue }}</span>
          <span v-else-if="data.city">{{ data.city }}</span>
          <span v-else class="tmgmt-muted">—</span>
        </template>
      </Column>
      <Column field="city" header="Stadt" sortable style="width: 130px" />
      <Column field="veranstalter" header="Veranstalter" sortable />
      <Column field="status" header="Status" sortable style="width: 150px">
        <template #body="{ data }">
          <Tag :value="statusLabel(data.status)" :severity="statusSeverity(data.status)" />
        </template>
      </Column>
      <Column field="fee" header="Gage" sortable style="width: 110px">
        <template #body="{ data }">
          <span v-if="data.fee">{{ formatCurrency(data.fee) }}</span>
          <span v-else class="tmgmt-muted">—</span>
        </template>
      </Column>
      <template #empty>
        <div class="tmgmt-event-list__empty">Keine Events gefunden.</div>
      </template>
    </DataTable>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import apiService from '../services/apiService.js'

const emit = defineEmits(['open-event-modal'])

const events = ref([])
const statuses = ref({})
const loading = ref(false)
const error = ref('')
const searchQuery = ref('')
const statusFilter = ref(null)
const sortField = ref('date')
const sortOrder = ref(-1)

const statusFilterOptions = computed(() => {
  const opts = [{ label: 'Alle Status', value: null }]
  for (const [value, label] of Object.entries(statuses.value)) {
    opts.push({ value, label })
  }
  return opts
})

const filteredEvents = computed(() => {
  let result = events.value

  // Status filter
  if (statusFilter.value) {
    result = result.filter(e => e.status === statusFilter.value)
  }

  // Search
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase().trim()
    result = result.filter(e =>
      (e.title && e.title.toLowerCase().includes(q)) ||
      (e.event_id && e.event_id.toLowerCase().includes(q)) ||
      (e.city && e.city.toLowerCase().includes(q)) ||
      (e.venue && e.venue.toLowerCase().includes(q)) ||
      (e.veranstalter && e.veranstalter.toLowerCase().includes(q))
    )
  }

  return result
})

function statusLabel(slug) {
  return statuses.value[slug] || slug || '—'
}

function statusSeverity(slug) {
  if (!slug) return 'secondary'
  if (slug.includes('cancel') || slug.includes('absag')) return 'danger'
  if (slug.includes('done') || slug.includes('abgeschlossen')) return 'success'
  if (slug.includes('confirm') || slug.includes('bestätigt')) return 'info'
  return 'secondary'
}

function formatDate(dateStr) {
  if (!dateStr) return '—'
  try {
    const d = new Date(dateStr)
    return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' })
  } catch {
    return dateStr
  }
}

function formatCurrency(val) {
  const num = parseFloat(val)
  if (isNaN(num)) return val
  return num.toLocaleString('de-DE', { style: 'currency', currency: 'EUR' })
}

function onSort(event) {
  sortField.value = event.sortField
  sortOrder.value = event.sortOrder
}

function onRowClick(event) {
  if (event.data?.id) {
    emit('open-event-modal', event.data.id)
  }
}

onMounted(async () => {
  loading.value = true
  error.value = ''
  try {
    const data = await apiService.get('/events')
    events.value = data.events ?? []
    statuses.value = data.statuses ?? {}
  } catch (err) {
    error.value = err?.message ?? 'Fehler beim Laden der Events.'
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.tmgmt-event-list__toolbar {
  display: flex;
  gap: 12px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}

.tmgmt-event-list__search {
  min-width: 220px;
}

.tmgmt-event-list__filter {
  min-width: 180px;
}

.tmgmt-event-list__loading {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 30px;
  justify-content: center;
}

.tmgmt-event-list__row {
  cursor: pointer;
}

.tmgmt-event-list__empty {
  text-align: center;
  padding: 30px;
  color: var(--p-text-muted-color);
  font-style: italic;
}

.tmgmt-muted {
  color: var(--p-text-muted-color);
}
</style>
