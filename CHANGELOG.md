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
- **Event-Detail-Ansicht** (`assets/vue/components/EventDetail.vue`): PrimeVue-basierte Full-Page-Komponente mit 6 Tabs (Details, Kontakt, Vertrag, Logbuch, Kommunikation, Anhänge), Sidebar mit Aktionen/Touren/Leaflet-Karte, Auto-Save und Status-Validierung
  - Storybook-Stories (Default, Loading, Error, Empty) und 9 Tests (Unit + Property-Based mit fast-check)
- **Event-Listenansicht** (`assets/vue/components/EventListWidget.vue`): Dashboard-Widget mit PrimeVue `DataTable`, Textsuche, Status-Filter, Sortierung und Zeilen-Klick-Navigation
  - Storybook-Stories und 7 Tests (Unit + Property-Based + Suchfilter)
- Neuer REST-Endpunkt `GET /tmgmt/v1/events` in `TMGMT_REST_API::list_events()`: flache Event-Liste mit Location/Veranstalter-Auflösung und Status-Map; mit `check_permission` abgesichert
- **Kundendashboard-Redesign** (`includes/class-customer-access-manager.php`): Monolithische `render_dashboard()`-Methode in sektionsbasierte Architektur umgebaut
  - `get_default_readable_fields()`: Standardfelder sind ohne Admin-Konfiguration lesbar
  - `get_effective_config()`: Merged gespeicherte Feldkonfiguration mit Defaults als Fallback
  - 8 private Render-Methoden: Header, Event-Details, Location, Kontakte, Finanzen, Bestätigungen, Anhänge, Vertrag-Upload
  - `assets/css/customer-dashboard.css`: externes Stylesheet mit `.cd-*`-Klassen, max-width 900px, responsive Media Queries
  - `tests/php/CustomerDashboardSectionHeaderTest.php`: PHPUnit-Test für Dashboard-Sektionen
- **Automatische Vertragsgenerierung** – vollständiger digitaler Vertragsworkflow:
  - `TMGMT_Contract_Generator` mit `generate_and_send()`, `render_template()`, `render_preview()`, `save_pdf()`, `send_contract_email()` und `register_pdf_attachment()`
  - `generate_and_send()` um `$overrides`-Parameter erweitert (to, cc, bcc, subject, body, template_id); Factory-Methoden für testbare Dependency Injection
  - Neuer CPT `tmgmt_contract_tpl` mit `show_in_rest: true`, Gutenberg-Block-Editor selektiv aktiviert
  - Gutenberg Sidebar Panel (`assets/js/contract-template-editor.js`) mit klickbaren Platzhalter-Buttons
  - Aktionstyp `contract_generation` in `TMGMT_Action_Post_Type` mit Template-Dropdown und E-Mail-Template-Auswahl
  - Einstellungsseite `tmgmt-contract-settings` mit Media-Uploader für Unterschrift und User-Dropdown für Benachrichtigungs-Empfänger
  - Upload-Bereich für unterschriebenen Vertrag im Kunden-Dashboard (sichtbar nur bei Status `contract_sent`), AJAX-Handler mit Token-Validierung und MIME-Whitelist
  - Vertrag-PDF wird automatisch als WordPress-Attachment registriert und dem Event zugeordnet (`register_pdf_attachment()`)
  - `[confirmation_link]`-Platzhalter in `TMGMT_Placeholder_Parser::parse()` ergänzt
- **Vertrag-Senden-Dialog** (`contract-send-dialog`):
  - Zwei neue REST-Endpunkte: `GET /tmgmt/v1/events/{id}/contract-preview` und `POST /tmgmt/v1/events/{id}/contract-send`, beide mit `permission_callback` abgesichert
  - Dialog-HTML-Markup: zweispaltige Ansicht (E-Mail-Formular links, PDF-iframe rechts), Template-Selector
  - `assets/js/contract-send-dialog.js`: Preview-Laden, Template-Wechsel, Send-Flow mit SweetAlert2-Feedback
- **Veranstalter CPT** (`includes/post-types/class-veranstalter-post-type.php`): neuer Custom Post Type `tmgmt_veranstalter` mit Adressfeldern, Kontaktzuordnung und verknüpften Locations
  - Kontakt-CPT: Post Title wird automatisch aus Vor- und Nachname generiert
  - Kontaktsuche nutzt `meta_query` über Vorname, Nachname, Firma und E-Mail
- `TMGMT_Placeholder_Parser::get_contact_data_for_event()`: neue statische Methode löst Kontaktdaten rollenbasiert (Vertrag, Technik, Programm) über Veranstalter→Kontakt-Kette auf
- `docs/placeholders.md`: vollständige Platzhalter-Referenz mit allen 38 verfügbaren Platzhaltern
- `docs/api_documentation.md`: Contract API-Sektion mit beiden neuen Endpunkten
- PrimeVue 4 mit Aura-Theme-Preset in das Vue-Frontend integriert
- Storybook 8 (`@storybook/vue3-vite`) für isolierte Vue-Komponentenentwicklung
- **Kiro Steering-Dokumentation** (`.kiro/steering/`): `product.md`, `tech.md`, `structure.md`
