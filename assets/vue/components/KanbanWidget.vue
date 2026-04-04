<template>
  <div class="tmgmt-kanban-widget">
    <!-- Fehler -->
    <Message v-if="store.error" severity="error" :closable="false">{{ store.error }}</Message>

    <!-- Laden -->
    <div v-else-if="store.loading" class="tmgmt-board--loading">
      <ProgressSpinner style="width: 40px; height: 40px" />
      <span>Lade Board...</span>
    </div>

    <!-- Board -->
    <div v-else class="tmgmt-board">
      <div class="tmgmt-board__toolbar">
        <Button label="Neues Event" icon="pi pi-plus" @click="createNewEvent" />
      </div>

      <div class="tmgmt-board__columns">
        <div
          v-for="column in store.board.columns"
          :key="column.id"
          class="tmgmt-column"
          :class="{ 'tmgmt-column--expanded': expandedColumns.has(column.id) }"
          :style="{ '--column-color': column.color }"
          @dragover.prevent
          @drop="onDrop($event, column)"
        >
          <div
            class="tmgmt-column__header"
            :style="{ borderTopColor: column.color }"
            @click="toggleColumn(column.id)"
          >
            <span class="tmgmt-column__title">{{ column.title }}</span>
            <Tag :value="String(getEventsForColumn(column).length)" rounded />
          </div>

          <div class="tmgmt-column__cards">
            <div
              v-for="event in getEventsForColumn(column)"
              :key="event.id"
              class="tmgmt-card"
              draggable="true"
              @dragstart="onDragStart($event, event)"
              @click="openEventModal(event.id)"
            >
              <div class="tmgmt-card__title">{{ event.title }}</div>
              <div class="tmgmt-card__meta">
                <Tag v-if="event.date" :value="event.date" severity="secondary" />
                <Tag v-if="event.city" :value="event.city" severity="info" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Missing Fields Modal -->
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
import { ref, reactive, onMounted, onUnmounted } from 'vue'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import { useEventStore } from '../stores/eventStore.js'
import apiService from '../services/apiService.js'
import MissingFieldsModal from './MissingFieldsModal.vue'

const emit = defineEmits(['open-event-modal'])
const store = useEventStore()

// --- Mobile Akkordeon ---
const isMobile = ref(window.innerWidth <= 768)
const expandedColumns = ref(new Set())

function onResize() {
  isMobile.value = window.innerWidth <= 768
  if (!isMobile.value) {
    expandedColumns.value = new Set(store.board.columns.map(c => c.id))
  }
}

function toggleColumn(columnId) {
  if (!isMobile.value) return
  const next = new Set(expandedColumns.value)
  if (next.has(columnId)) {
    next.delete(columnId)
  } else {
    next.add(columnId)
  }
  expandedColumns.value = next
}

function getEventsForColumn(column) {
  return store.board.events.filter(e => column.statuses.includes(e.status))
}

// --- Drag & Drop ---
const draggedEvent = ref(null)

function onDragStart(nativeEvent, event) {
  draggedEvent.value = event
  nativeEvent.dataTransfer.effectAllowed = 'move'
}

const missingFieldsModal = reactive({
  visible: false,
  fields: [],
  targetStatus: '',
  pendingEventId: null,
  pendingOldStatus: null,
})

function getMissingFields(event, targetStatus) {
  const requirements = window.tmgmtData?.status_requirements ?? {}
  const fieldMap = window.tmgmtData?.field_map ?? {}
  const required = requirements[targetStatus] ?? []
  return required.filter(metaKey => {
    const fieldKey = fieldMap[metaKey] ?? metaKey
    const value = event[fieldKey]
    return value === undefined || value === null || String(value).trim() === ''
  })
}

async function onDrop(nativeEvent, targetColumn) {
  if (!draggedEvent.value) return
  const event = draggedEvent.value
  draggedEvent.value = null
  const targetStatus = targetColumn.statuses[0]
  if (!targetStatus || event.status === targetStatus) return

  const missing = getMissingFields(event, targetStatus)
  if (missing.length > 0) {
    missingFieldsModal.fields = missing
    missingFieldsModal.targetStatus = targetStatus
    missingFieldsModal.pendingEventId = event.id
    missingFieldsModal.pendingOldStatus = event.status
    missingFieldsModal.visible = true
    return
  }
  await store.updateEventStatus(event.id, targetStatus)
}

async function onMissingFieldsConfirmed(fieldValues) {
  missingFieldsModal.visible = false
  const { pendingEventId, targetStatus } = missingFieldsModal
  try {
    await apiService.post('/events/' + pendingEventId, fieldValues)
  } catch (_) { /* ignore */ }
  await store.updateEventStatus(pendingEventId, targetStatus)
}

function onMissingFieldsCancelled() {
  missingFieldsModal.visible = false
  missingFieldsModal.pendingEventId = null
  missingFieldsModal.pendingOldStatus = null
}

function openEventModal(eventId) {
  emit('open-event-modal', eventId)
}

async function createNewEvent() {
  try {
    const result = await apiService.post('/events', { title: 'Neues Event' })
    const newId = result?.id ?? result?.data?.id
    if (newId) {
      await store.loadBoard()
      emit('open-event-modal', newId)
    }
  } catch (err) {
    store.error = err?.message ?? 'Fehler beim Erstellen des Events'
  }
}

onMounted(async () => {
  await store.loadBoard()
  if (!isMobile.value) {
    expandedColumns.value = new Set(store.board.columns.map(c => c.id))
  }
  window.addEventListener('resize', onResize)
})

onUnmounted(() => {
  window.removeEventListener('resize', onResize)
})
</script>

<style scoped>
.tmgmt-kanban-widget {
  width: 100%;
}

.tmgmt-board {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.tmgmt-board--loading {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 20px;
}

.tmgmt-board__toolbar {
  display: flex;
  justify-content: flex-end;
  padding: 8px 0;
}

.tmgmt-board__columns {
  display: flex;
  gap: 12px;
  align-items: flex-start;
}

.tmgmt-column {
  flex: 1;
  min-width: 200px;
  background: var(--p-surface-100);
  border-radius: var(--p-border-radius);
  border-top: 3px solid var(--column-color, #ccc);
}

.tmgmt-column__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 12px;
  font-weight: 600;
  cursor: default;
}

.tmgmt-column__cards {
  padding: 8px;
  min-height: 60px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.tmgmt-card {
  background: var(--p-surface-0);
  border: 1px solid var(--p-surface-200);
  border-radius: var(--p-border-radius);
  padding: 10px 12px;
  cursor: pointer;
  transition: box-shadow 0.15s;
}

.tmgmt-card:hover {
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
}

.tmgmt-card[draggable="true"] {
  cursor: grab;
}

.tmgmt-card__title {
  font-weight: 500;
  margin-bottom: 4px;
}

.tmgmt-card__meta {
  display: flex;
  gap: 6px;
}

@media (max-width: 768px) {
  .tmgmt-board__columns {
    flex-direction: column;
  }

  .tmgmt-column {
    min-width: unset;
    width: 100%;
  }

  .tmgmt-column__header {
    cursor: pointer;
  }

  .tmgmt-column__cards {
    display: none;
  }

  .tmgmt-column--expanded .tmgmt-column__cards {
    display: flex;
  }
}
</style>
