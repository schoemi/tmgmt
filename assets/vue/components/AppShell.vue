<template>
  <div class="tmgmt-app-shell">
    <!-- Event Detail View -->
    <template v-if="selectedEventId !== null">
      <div class="tmgmt-app-shell__detail-header">
        <Button
          icon="pi pi-arrow-left"
          :label="'Zurück zur ' + (activeWidget?.label ?? 'Übersicht')"
          severity="secondary"
          text
          @click="closeEventDetail"
        />
      </div>
      <EventDetail :event-id="selectedEventId" />
    </template>

    <!-- Dashboard (Widgets) -->
    <template v-else>
      <!-- Navigation -->
      <TabMenu :model="tabItems" :activeIndex="activeIndex" @tab-change="onTabChange" />

      <!-- Active Widget -->
      <div class="tmgmt-dashboard-content">
        <component
          v-if="activeWidget"
          :is="activeWidget.component"
          @open-event-modal="openEventDetail"
        />
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import TabMenu from 'primevue/tabmenu'
import Button from 'primevue/button'
import registry from '../registry/widgetRegistry.js'
import EventDetail from './EventDetail.vue'

const STORAGE_KEY = 'tmgmt_active_widget'

const userCapabilities = window.tmgmtData?.capabilities ?? {}
const visibleWidgets = computed(() => registry.getVisible(userCapabilities))

const tabItems = computed(() =>
  visibleWidgets.value.map(w => ({
    label: w.label,
    icon: w.icon ? `pi pi-${mapIcon(w.icon)}` : undefined,
    key: w.id,
  }))
)

const activeIndex = ref(0)

const activeWidget = computed(() =>
  visibleWidgets.value[activeIndex.value] ?? null
)

const selectedEventId = ref(null)

function mapIcon(faIcon) {
  const iconMap = {
    'fa-columns': 'objects-column',
    'fa-calendar': 'calendar',
    'fa-map': 'map',
    'fa-list': 'list',
    'fa-chart-bar': 'chart-bar',
    'fa-cog': 'cog',
  }
  return iconMap[faIcon] ?? 'th-large'
}

function onTabChange(event) {
  activeIndex.value = event.index
  const widget = visibleWidgets.value[event.index]
  if (widget) {
    localStorage.setItem(STORAGE_KEY, widget.id)
  }
}

function openEventDetail(id) {
  selectedEventId.value = id
}

function closeEventDetail() {
  selectedEventId.value = null
}

defineExpose({ openEventDetail, closeEventDetail, selectedEventId, activeIndex })

onMounted(() => {
  const stored = localStorage.getItem(STORAGE_KEY)
  if (stored) {
    const idx = visibleWidgets.value.findIndex(w => w.id === stored)
    if (idx >= 0) activeIndex.value = idx
  }
})
</script>

<style scoped>
.tmgmt-app-shell {
  display: flex;
  flex-direction: column;
  width: 100%;
}

.tmgmt-dashboard-content {
  width: 100%;
  margin-top: 16px;
}

.tmgmt-app-shell__detail-header {
  margin-bottom: 12px;
}
</style>
