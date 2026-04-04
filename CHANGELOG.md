# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

---

## [0.8.0] – 2026-04-04

### Added

- **Vue 3 + Vite SPA** (`assets/vue/`): vollständige Migration des Vanilla-JS-Dashboards auf eine erweiterbare Single-Page-Anwendung mit Widget-Registry
  - `assets/vue/main.js`: App-Einstiegspunkt mit `tmgmtData`-Guard, Pinia-Mount und `window.tmgmtDashboard.registerWidget`-API
  - `assets/vue/vite.config.js`: IIFE-Output nach `assets/dist/dashboard.iife.js` + `dashboard.css`, Leaflet/SweetAlert2 als Externals, Vitest-Konfiguration
  - `assets/vue/services/apiService.js`: zentraler API-Service mit automatischem `X-WP-Nonce`-Header, strukturierter Fehlerbehandlung und Deduplizierung gleichzeitiger Aufrufe
  - `assets/vue/stores/eventStore.js`: Pinia-Store mit optimistischem Status-Update, Rollback bei API-Fehler und `tmgmt:event-updated` CustomEvent-Dispatch
  - `assets/vue/registry/widgetRegistry.js`: Widget-Registry mit Duplikat-Schutz, aufsteigender `order`-Sortierung und Permissions-Filterung
  - `assets/vue/components/AppShell.vue`: App-Container mit Widget-Navigation, `localStorage`-Persistenz und `<component :is>` Widget-Wechsel
  - `assets/vue/components/KanbanWidget.vue`: Kanban-Board mit HTML5 Drag & Drop, optimistischem State-Update, Pflichtfeld-Prüfung und Mobile-Akkordeon (≤ 768 px)
  - `assets/vue/components/EventModal.vue`: Event-Detailmodal mit Auto-Save bei `blur`, Status-Pflichtfeld-Prüfung, Logbuch, Leaflet-Karte und Inline-Fehlermeldungen
  - `assets/vue/components/MissingFieldsModal.vue`: Pflichtfelder-Modal als eigenständige Vue-Komponente
- **Vue-Dashboard im WordPress-Admin** (`includes/class-admin-dashboard.php`): neue Klasse `TMGMT_Admin_Dashboard` ersetzt das alte PHP-Kanban-Board (`TMGMT_Dashboard`) durch die gleiche Vue-App, die auch im Frontend-Dashboard genutzt wird
  - `tmgmtData`-Lokalisierung mit API-URL, Nonce, Statuses, Status-Requirements, Capabilities, Field-Map und `context: 'admin'`
  - Inline-CSS für WP-Admin-Kompatibilität: neutralisiert WordPress-eigene Input-Styles innerhalb des `#tmgmt-dashboard-app`-Containers
