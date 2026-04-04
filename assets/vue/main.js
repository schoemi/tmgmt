import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import Aura from '@primeuix/themes/aura'
import AppShell from './components/AppShell.vue'
import KanbanWidget from './components/KanbanWidget.vue'
import widgetRegistry from './registry/widgetRegistry.js'

// Requirement 3.6: Prüfe Verfügbarkeit von window.tmgmtData
if (!window.tmgmtData) {
    const mountPoint = document.querySelector('#tmgmt-dashboard-app')
    if (mountPoint) {
        mountPoint.innerHTML = '<p class="tmgmt-error">Dashboard konnte nicht geladen werden: tmgmtData nicht verfügbar.</p>'
    }
} else {
    // Requirement 3.5: Pinia-Instanz erstellen
    const pinia = createPinia()

    // Requirement 3.1: Vue-App auf #tmgmt-dashboard-app mounten
    const app = createApp(AppShell)
    app.use(pinia)
    app.use(PrimeVue, {
        theme: {
            preset: Aura
        }
    })

    // Standard-Widget registrieren (KanbanWidget)
    widgetRegistry.register({
        id: 'kanban',
        label: 'Kanban',
        icon: 'fa-columns',
        component: KanbanWidget,
        order: 1,
    })

    app.mount('#tmgmt-dashboard-app')

    // Requirement 9.1: Öffentliche API nach Mount exponieren
    window.tmgmtDashboard = {
        registerWidget: (config) => widgetRegistry.register(config),
    }
}
