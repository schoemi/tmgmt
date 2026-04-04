# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased] – 2026-04-04

### Added

- **Vertrag-PDF als Event-Anhang** (`contract-event-attachment`): Nach dem Versand eines Vertrags wird das generierte PDF automatisch als WordPress-Attachment registriert und dem Event zugeordnet
  - Neue Methode `TMGMT_Contract_Generator::register_pdf_attachment()`: erstellt WP-Attachment via `wp_insert_attachment` mit `post_parent = event_id`, speichert Attachment-ID als `_tmgmt_contract_attachment_id` Post-Meta, trägt Eintrag mit Kategorie `Vertrag` in `_tmgmt_event_attachments` ein
  - Aufruf als Step 5b in `generate_and_send()` zwischen PDF-Meta-Speicherung und E-Mail-Versand; bei Fehler wird geloggt und der Versand fortgesetzt (kein Abbruch)
  - Normalisierung bestehender `_tmgmt_event_attachments`-Einträge (numerische Werte → `{id, category}`), Duplikat-Vermeidung bei gleicher Attachment-ID
  - Logging: `attachment_added` bei Erfolg, `contract_error` bei Fehler
  - Property-Based Test (Eris, 100 Iterationen): Fehlerresilienz — Versand wird bei Attachment-Fehler fortgesetzt (Property 4)
  - `docs/data_structure-kommentiert.md`: neues Meta-Feld `_tmgmt_contract_attachment_id` dokumentiert
- **Spec: Vertrag-Senden-Dialog** (`contract-send-dialog`): Vollständige Implementierung aller 8 Tasks
  - `TMGMT_Contract_Generator`: neue Methode `render_preview()` für temporäre PDF-Vorschau ohne WP-Attachment; `generate_and_send()` um `$overrides`-Parameter erweitert (to, cc, bcc, subject, body, template_id); Factory-Methoden für testbare Dependency Injection
  - Zwei neue REST-Endpunkte: `GET /tmgmt/v1/events/{id}/contract-preview` (E-Mail-Felder + PDF-URL + Templates) und `POST /tmgmt/v1/events/{id}/contract-send` (finaler Versand mit Override-Feldern); beide mit `permission_callback` abgesichert
  - Dialog-HTML-Markup in `render_actions_box()`: zweispaltige Ansicht (E-Mail-Formular links, PDF-iframe rechts), Template-Selector, deutsche Labels
  - `assets/js/contract-send-dialog.js`: Button-Interception für `contract_generation`-Aktionen, Preview-Laden, Template-Wechsel, Send-Flow mit SweetAlert2-Feedback, Cancel- und Error-Handling (404 → kein Dialog, 500 → Fehler in PDF-Spalte)
  - 6 Property-Based Tests (Eris, je 100 Iterationen): Preview-Vollständigkeit, Overrides-Round-Trip, Template-Sichtbarkeit, render_template()-Determinismus, Status-Update, Auth-Schutz
  - Unit-Tests für REST-Endpunkt-Fehlerbehandlung (404, 500, 400), Dialog-Markup, Overrides-Backward-Compatibility
  - `docs/api_documentation.md`: Contract API-Sektion mit beiden neuen Endpunkten ergänzt
  - `tests/bootstrap.php`: kanonischer `WP_Error`-Stub mit `get_error_data()`, `get_posts()`-Stub filtert aus `$test_post_store`
- PrimeVue 4 mit Aura-Theme-Preset in das Vue-Frontend integriert (`primevue`, `@primeuix/themes` als devDependencies)
- `assets/vue/main.js`: PrimeVue-Plugin mit Aura-Preset registriert (`app.use(PrimeVue, { theme: { preset: Aura } })`)
- `.storybook/preview.js`: PrimeVue + Aura global für alle Stories konfiguriert, damit PrimeVue-Komponenten in Storybook korrekt rendern
- Neue Storybook Stories: `AppShell.stories.js`, `EventModal.stories.js`

### Changed

- **Dashboard-Komponenten auf PrimeVue umgebaut** – alle 4 Vue-Komponenten nutzen jetzt PrimeVue statt Custom-HTML/CSS:
  - `AppShell.vue`: Navigation von Custom-Buttons auf PrimeVue `TabMenu` umgestellt; Font-Awesome-Icons auf PrimeIcons gemappt
  - `KanbanWidget.vue`: `Button`, `Tag`, `Message`, `ProgressSpinner` statt Custom-HTML; Karten-Meta mit `Tag`-Badges; Spalten-Count als `Tag rounded`
  - `EventModal.vue`: Custom-Modal durch PrimeVue `Dialog` ersetzt; alle Formularfelder auf `InputText`, `InputNumber`, `DatePicker`, `Select`, `Textarea` umgestellt; Collapsible-Sections durch `Accordion`/`AccordionPanel` ersetzt; Gage/Anzahlung als `InputNumber` mit `mode="currency"` und `locale="de-DE"`; Status-Dropdown als PrimeVue `Select`; Logbuch-Einträge mit `Tag` für Datum; Save-Status als `Tag` mit Severity
  - `MissingFieldsModal.vue`: Custom-Modal durch PrimeVue `Dialog` ersetzt; Inputs auf `InputText`, `InputNumber`, `DatePicker` umgestellt; Warnung als PrimeVue `Message`; Footer mit PrimeVue `Button`
- CSS-Variablen nutzen jetzt PrimeVue Design-Tokens (`--p-surface-*`, `--p-border-radius`, `--p-text-*`) statt Hardcoded-Werte
- Spec `contract-send-dialog`: Implementierungsplan (`tasks.md`) erstellt mit 8 Tasks – Backend (Contract Generator Preview/Overrides, REST-Endpunkte), Frontend (Dialog-HTML, `contract-send-dialog.js` mit Template-Wechsel und Send-Flow), Status-Update/Kommunikationslogging; Property-Tests (Eris) als optionale Sub-Tasks für alle 6 Correctness Properties; zwei Checkpoints nach Backend- und Frontend-Phase
- Storybook 8 (`@storybook/vue3-vite`) zum Projekt hinzugefügt für isolierte Vue-Komponentenentwicklung
- `.storybook/main.js`: Konfiguration mit Story-Discovery in `assets/vue/**/*.stories.js`
- `.storybook/preview.js`: globales Pinia-Setup für alle Stories
- `assets/vue/components/KanbanWidget.stories.js`: Stories für Default, Loading, Error und Empty-Zustände
- `assets/vue/components/MissingFieldsModal.stories.js`: Stories für Default, SingleField und ManyFields-Varianten
- npm-Scripts `storybook` und `build-storybook` in `package.json`

### Fixed

- `.storybook/main.js`: `@vitejs/plugin-vue` via `viteFinal` explizit registriert — Storybook startet einen eigenen Vite-Dev-Server und liest die bestehende `assets/vue/vite.config.js` nicht automatisch ein; ohne das Plugin schlug das Parsen von `.vue`-Dateien mit „Failed to parse source for import analysis" fehl
- `ContractMetaRoundTripTest`, `ContractPdfValidityTest`, `ContractStatusTransitionTest`: auf testbare Subklassen mit Spy-SMTP-Sender umgestellt und `to`-Override übergeben — Tests schlugen fehl, weil `generate_and_send()` jetzt die Veranstalter→Kontakt-Kette für die E-Mail-Adresse benötigt und der echte `TMGMT_SMTP_Sender` PHPMailer voraussetzt

---

## [Unreleased] – 2026-04-03 (Reaktives Dashboard – Implementierung)

### Added

- **Vue 3 + Vite SPA** (`assets/vue/`): vollständige Migration des Vanilla-JS-Dashboards auf eine erweiterbare Single-Page-Anwendung mit Widget-Registry
  - `assets/vue/main.js`: App-Einstiegspunkt mit `tmgmtData`-Guard, Pinia-Mount und `window.tmgmtDashboard.registerWidget`-API
  - `assets/vue/vite.config.js`: IIFE-Output nach `assets/dist/dashboard.iife.js` + `dashboard.css`, Leaflet/SweetAlert2 als Externals, Vitest-Konfiguration
  - `assets/vue/services/apiService.js`: zentraler API-Service mit automatischem `X-WP-Nonce`-Header, strukturierter Fehlerbehandlung (`{ status, message, data }`) und Deduplizierung gleichzeitiger Aufrufe
  - `assets/vue/stores/eventStore.js`: Pinia-Store mit optimistischem Status-Update, Rollback bei API-Fehler und `tmgmt:event-updated` CustomEvent-Dispatch
  - `assets/vue/registry/widgetRegistry.js`: Widget-Registry mit Duplikat-Schutz (inkl. Prototype-Pollution-Fix via `hasOwnProperty`), aufsteigender `order`-Sortierung und Permissions-Filterung
  - `assets/vue/components/AppShell.vue`: App-Container mit `<nav class="tmgmt-dashboard-nav">`, `localStorage`-Persistenz unter `tmgmt_active_widget` und `<component :is>` Widget-Wechsel
  - `assets/vue/components/KanbanWidget.vue`: Kanban-Board mit HTML5 Drag & Drop, optimistischem State-Update, Pflichtfeld-Prüfung, Mobile-Akkordeon bei ≤ 768 px und „Neues Event"-Button
  - `assets/vue/components/EventModal.vue`: Event-Detailmodal mit Auto-Save bei `blur`, Status-Pflichtfeld-Prüfung, absteigendem Logbuch, Leaflet-Karte und Inline-Fehlermeldungen
  - `assets/vue/components/MissingFieldsModal.vue`: Pflichtfelder-Modal als eigenständige Vue-Komponente (Props: `missingFields`, `targetStatus`; Emits: `confirmed`, `cancelled`)
- **Property-Tests** (`assets/vue/tests/`, fast-check, je 100 Iterationen):
  - Properties 1–4, 6–13, 16–17 abgedeckt: tmgmtData-Vollständigkeit, localStorage-Round-Trip, Widget-ID-Eindeutigkeit, Sortierreihenfolge, optimistisches Update, State-Revert, Kanban-Rendering, Pflichtfeld-Modal, Event-Modal-Round-Trip, Auto-Save, Logbuch-Sortierung, API-Nonce/URL, HTTP-Fehlerstruktur, Permissions-Sichtbarkeit, Window-Event
- **Playwright-Tests** (`tests/iteration-8-reactive-dashboard.spec.js`): App-Shell-Sichtbarkeit, Drag & Drop, Event-Modal, Mobile-Akkordeon (375 px), Widget-Navigation ohne Seitenneuladen

### Changed

- `includes/class-frontend-dashboard.php`: Script-Handle auf `tmgmt-dashboard-vue`, Bundle-Pfad auf `assets/dist/dashboard.iife.js`, Style auf `assets/dist/dashboard.css`; SweetAlert2 als WordPress-Script registriert und enqueued; Mount-Point von `#tmgmt-kanban-app` auf `#tmgmt-dashboard-app` geändert; altes Modal-HTML und Bottom-Sheet-HTML entfernt; Version via `filemtime` bei `WP_DEBUG=true`
- `package.json`: Vite, `@vitejs/plugin-vue`, Vue 3, Pinia, fast-check, Vitest, `@vue/test-utils`, jsdom als devDependencies ergänzt; `build`-, `dev`- und `test:unit`-Scripts hinzugefügt
- `assets/vue/vite.config.js`: `test.include` auf `assets/vue/tests/**/*.test.js` eingeschränkt (verhindert Konflikt mit Playwright-Tests)

---

## [Unreleased] – 2026-04-03 (Spec: Reaktives Dashboard – Tasks)

### Added

- Spec `reactive-dashboard`: Implementierungsplan (`tasks.md`) erstellt mit 13 Tasks von der Vite Build-Pipeline über API-Service, Pinia-Store, Widget-Registry und alle Vue-Komponenten bis zur PHP-Anpassung und Playwright-Tests; Property-Tests (fast-check) als optionale Sub-Tasks direkt neben den jeweiligen Implementierungs-Tasks

---

## [Unreleased] – 2026-04-03 (Specs: Reaktives Dashboard Design & Contract-Send-Dialog Updates)

### Added

- Spec `reactive-dashboard`: Design-Dokument erstellt mit Architektur-Diagrammen (Mermaid), Komponentenstruktur (`AppShell.vue`, `KanbanWidget.vue`, `EventModal.vue`, `MissingFieldsModal.vue`, `widgetRegistry.js`, `apiService.js`, Pinia `eventStore`), Dateistruktur unter `assets/vue/`, 17 Correctness Properties und dualer Testing-Strategie (Vitest + fast-check PBT + Playwright E2E)

### Changed

- Spec `contract-send-dialog` Requirements aktualisiert und mit Design-Dokument synchronisiert:
  - Permission-Check auf `edit_tmgmt_events || edit_posts` korrigiert (konsistent mit Plugin-Standard)
  - `render_preview()`-Methode explizit gefordert (temporäres PDF ohne WordPress-Attachment-Eintrag)
  - `no_template`-Flag in Preview-Response ergänzt
  - `contract-send-dialog.js` als eigenständige Datei explizit gefordert
  - HTTP-400 bei fehlenden Pflichtfeldern im Send-Endpoint ergänzt
  - `Communication_Manager`-Eintrag und Log-Eintrag nach erfolgreichem Versand als Requirements aufgenommen
  - `$overrides`-Parameter für `generate_and_send()` als Round-Trip-Anforderung 7.4 ergänzt

---

## [Unreleased] – 2026-04-03 (Spec: Reaktives Dashboard)

### Added

- Spec `reactive-dashboard`: Design-Dokument erstellt
  - Architektur: Vite IIFE-Output nach `assets/dist/`, Pinia State-Store, zentraler `apiService.js` mit automatischem Nonce-Header
  - Neue Dateistruktur `assets/vue/` für Vue 3 Quellcode (main.js, AppShell.vue, KanbanWidget.vue, EventModal.vue, MissingFieldsModal.vue, widgetRegistry.js, apiService.js, eventStore.js)
  - Widget-Registry mit `registerWidget(config)` API und `window.tmgmtDashboard`-Exposition
  - 2 Mermaid-Diagramme: Komponentenarchitektur und Drag-&-Drop-Sequenzdiagramm
  - Datenmodelle für `tmgmtData`, `WidgetConfig`, `BoardData` und `ApiError`
  - 17 Correctness Properties (fast-check PBT) für Widget-Registry, API-Service, Kanban-State, Auto-Save, Logbuch-Sortierung und Permissions
  - Fehlerbehandlungstabelle und Testing-Strategie (Vitest + fast-check + Playwright)

---

## [Unreleased] – 2026-04-03 (Spec: Reaktives Dashboard)

### Added

- Spec `reactive-dashboard`: Requirements-Dokument erstellt für die Migration des Frontend-Dashboards von Vanilla JS auf Vue 3 + Vite
  - 9 EARS-konforme Requirements: Vite Build-Pipeline, WordPress Asset-Integration, Dashboard App-Shell mit Widget-Navigation, Widget-Registrierungssystem (`registerWidget` API), Vue 3-Migration von Kanban-Board und Event-Modal, zentraler API-Service, CSS-Kompatibilität (`tmgmt-*` Klassen), Entwickler-Erweiterbarkeit via `window.tmgmtDashboard`
  - Glossar mit zentralen Konzepten: Dashboard_App, Widget, Widget_Registry, WP_Bridge, Build_Pipeline

---

## [Unreleased] – 2026-04-03 (Mail-Bugfixes & Vertrag-Senden-Dialog)

### Added

- `assets/js/contract-template-editor.js`: Gutenberg Sidebar Panel mit klickbaren Platzhalter-Buttons für Vertragsvorlagen
- `includes/post-types/class-contract-template-post-type.php`: neuer CPT `tmgmt_contract_tpl` (Slug auf 18 Zeichen gekürzt, `show_in_rest: true`, Block-Editor selektiv aktiviert)
- `docs/placeholders.md`: vollständige Platzhalter-Referenz mit allen verfügbaren Platzhaltern
- Neue PHPUnit-Testklassen: `ContractActionHandlerTest`, `ContractBlockEditorFilterTest`, `ContractCptRegistrationTest`, `ContractSidebarPlaceholderTest`, `ContractSignatureOverlayTest`, `ContractTemplateMetaRoundTripTest`
- Spec `contract-send-dialog`: Requirements- und Design-Dokument mit Sequenzdiagramm, zwei neuen REST-Endpunkten (`contract-preview`, `contract-send`) und 6 Correctness Properties

### Fixed

- `TMGMT_Contract_Generator::send_contract_email`: E-Mail-Body wurde aus `post_content` statt aus `_tmgmt_email_body` (Post-Meta) gelesen — PHPMailer brach mit stiller Exception ab
- `TMGMT_SMTP_Sender::send()`: `catch`-Block gibt jetzt `error` mit konkretem Fehlertext zurück statt Exception zu verwerfen
- `TMGMT_SMTP_Sender::test_connection()`: Fehlermeldung enthält jetzt den PHPMailer-Fehlertext
- `TMGMT_Action_Handler`: Template-Check blockierte Versand auch wenn recipient/subject/body vollständig aus Preview-Dialog übergeben wurden
- `TMGMT_REST_API::preview_action`: HTTP 400 bei fehlender E-Mail-Vorlage entfernt — fehlende Werte werden als leere Strings behandelt und durch Fallbacks aufgefüllt
- `TMGMT_Customer_Access_Manager::send_email_template`: `TMGMT_Placeholder_Parser` wurde fälschlicherweise als Objekt instanziiert (Fatal Error im Admin-Backend) — umgestellt auf statischen Aufruf `TMGMT_Placeholder_Parser::parse()`
- `TMGMT_REST_API::execute_action`: `$email_template_id` war bei vollständig vom Frontend übergebenen Feldern undefined (PHP 8 Warning korrumpierte JSON-Response); `$request` bei `email_confirmation`-Aktionen durch `$conf_result` ersetzt
- `TMGMT_Contract_Template_Post_Type`: Post-Type-Slug von `tmgmt_contract_template` (23 Zeichen) auf `tmgmt_contract_tpl` (18 Zeichen) gekürzt — WordPress erlaubt maximal 20 Zeichen
- `TMGMT_Placeholder_Parser`: `[confirmation_link]`-Platzhalter wurde in `get_placeholders()` registriert aber nicht ersetzt — Ersetzung in `parse()` ergänzt

### Changed

- `TMGMT_Contract_Generator`: `generate_and_send()` und `send_contract_email()` ermitteln Vertrags-E-Mail-Adresse jetzt über `get_contact_data_for_event()` statt direktem Meta-Key-Zugriff
- `TMGMT_Integration_Manager`: Kontaktfelder werden über `get_contact_data_for_event()` aufgelöst statt über veralteten `_tmgmt_contact_cpt_id`-Fallback
- `TMGMT_Settings_Menu`: Unterseite „Vertrag" und Karte „Vertragsvorlagen" ergänzt; `tmgmt_contract_tpl` in `highlight_submenu_item()` aufgenommen
- `TMGMT_Admin_Menu`: `edit.php?post_type=tmgmt_contract_tpl` aus `desired_order`-Liste entfernt
- `TMGMT_SMTP_Sender::send()`: unterstützt jetzt zusätzliche Parameter `cc`, `bcc`, `reply_to` und `attachments`

---

## [Unreleased] – 2026-04-03 (Vertrag-Senden-Dialog – Design)

### Added

- **Spec: Vertrag-Senden-Dialog** (`contract-send-dialog`) – Design-Dokument erstellt:
  - Sequenzdiagramm des vollständigen Dialog-Flows: Klick → Preview-Endpunkt → Dialog öffnen → (optional) Template wechseln → Versand
  - Zwei neue REST-Endpunkte spezifiziert: `GET /tmgmt/v1/events/{id}/contract-preview` (liefert E-Mail-Felder + PDF-URL + verfügbare Templates) und `POST /tmgmt/v1/events/{id}/contract-send` (finaler Versand mit Override-Feldern)
  - `TMGMT_Contract_Generator`: neue Methode `render_preview()` und `$overrides`-Parameter für `generate_and_send()`
  - Neue JS-Datei `assets/js/contract-send-dialog.js` für Dialog-Steuerung (jQuery-UI-Dialog)
  - Dialog-HTML-Struktur: zweispaltig (E-Mail-Formular links, PDF-iframe rechts), Template-Selector-Dropdown
  - 6 Correctness Properties definiert (Preview-Vollständigkeit, Round-Trip, Template-Sichtbarkeit, Determinismus, Status-Update, Auth-Schutz)
  - Fehlerbehandlungstabelle für alle Fehlerfälle (404, 500, 400, 401/403)

---

## [Unreleased] – 2026-04-03 (Vertrag-Senden-Dialog)

### Added

- **Spec: Vertrag-Senden-Dialog** (`contract-send-dialog`) – Requirements erstellt:
  - Beim Auslösen einer `contract_generation`-Aktion wird ein modaler Dialog angezeigt statt sofortigem Versand
  - Zweispaltige Ansicht: links editierbare E-Mail-Vorschau (To, CC, BCC, Betreff, Body, Anhänge), rechts PDF-Vorschau via `<iframe>`
  - Template-Auswahl-Dropdown (nur sichtbar wenn mehr als eine Vertragsvorlage existiert) mit automatischer PDF-Neu-Generierung bei Vorlagenwechsel
  - Zwei neue REST-Endpunkte: `GET /tmgmt/v1/events/{id}/contract-preview` und `POST /tmgmt/v1/events/{id}/contract-send`, beide mit `permission_callback` abgesichert
  - Finaler Versand übernimmt alle im Dialog vorgenommenen Änderungen; Round-Trip-Integrität zwischen Vorschau und Versand spezifiziert

---

## [Unreleased] – 2026-04-03

### Fixed

- `TMGMT_Contract_Generator::send_contract_email`: E-Mail-Body wurde aus `$template->post_content` gelesen, das bei E-Mail-Vorlagen immer leer ist — Body wird jetzt korrekt aus `_tmgmt_email_body` (Post-Meta) gelesen, wo `TMGMT_Email_Template_Post_Type` ihn speichert; leerer Body ließ PHPMailer mit einer Exception abbrechen, die bisher still verworfen wurde
- `TMGMT_SMTP_Sender::send()`: `catch`-Block gibt jetzt `error` mit `$mail->ErrorInfo` bzw. `$e->getMessage()` zurück statt die Exception zu verwerfen — SMTP-Fehler sind damit im Frontend sichtbar
- `TMGMT_Contract_Generator::send_contract_email`: Fehlermeldung enthält jetzt den konkreten SMTP-Fehlertext aus `$smtp_result['error']`
- `TMGMT_SMTP_Sender::test_connection()`: Fehlermeldung bei fehlgeschlagenem Test enthält jetzt ebenfalls den PHPMailer-Fehlertext

---

## [Unreleased] – 2026-04-03 (früher)

### Fixed

- `TMGMT_Contract_Generator::send_contract_email`: `wp_mail()` durch `TMGMT_SMTP_Sender::send()` ersetzt — Vertragsversand nutzt jetzt konsistent die konfigurierte SMTP-Verbindung statt PHP-Mail-Fallback
- `TMGMT_Contract_Generator::send_contract_email`: `$template->post_content` wird jetzt via `apply_filters('the_content', ...)` gerendert — Gutenberg-Blöcke werden korrekt zu HTML aufgelöst statt als roher Block-Markup versendet
- `TMGMT_Contract_Generator::send_contract_email`: manueller `$wpdb->insert` für Token-Erstellung entfernt — `[customer_dashboard_link]` wird vollständig durch `TMGMT_Placeholder_Parser::parse()` aufgelöst, das intern `TMGMT_Customer_Access_Manager` nutzt

---

## [Unreleased] – 2026-04-02

### Fixed

- **E-Mail-Versand schlug fehl** (`TMGMT_Action_Handler::handle_execute_action`): `wp_mail()` (PHP-Mail) durch `TMGMT_SMTP_Sender::send()` ersetzt — E-Mails werden jetzt über die im Plugin konfigurierte SMTP-Verbindung versendet statt über den PHP-Mail-Fallback; CC/BCC/Reply-To werden korrekt aufgelöst auch wenn keine Vorlage konfiguriert ist

### Changed

- `TMGMT_SMTP_Sender::send()`: unterstützt jetzt zusätzliche Parameter `cc`, `bcc`, `reply_to` und `attachments` (Dateipfade)

---

### Fixed

- **"Keine E-Mail Vorlage ausgewählt" beim Senden nach Preview** (`TMGMT_Action_Handler::handle_execute_action`): Der Template-Check blockierte den Versand auch dann, wenn recipient/subject/body bereits vollständig aus dem Preview-Dialog übergeben wurden — Check wird jetzt nur noch ausgeführt wenn keine Dialog-Daten vorhanden sind

---

### Fixed

- **HTTP 400 beim Öffnen des E-Mail-Dialogs** (`TMGMT_REST_API::preview_action`): Der Endpoint gab einen 400-Fehler zurück wenn an der Aktion keine E-Mail-Vorlage konfiguriert war (`_tmgmt_action_email_template_id` leer) — der Dialog konnte nicht geöffnet werden; der harte Fehler wurde entfernt, fehlende Vorlagenwerte werden jetzt als leere Strings behandelt und durch die bestehenden Fallbacks (`[contact_email_contract]` als Empfänger, `Info: [event_title]` als Betreff) aufgefüllt

---

### Fixed

- **PHP Fatal: Backend lädt nicht** (`TMGMT_Customer_Access_Manager::send_email_template`): `TMGMT_Placeholder_Parser` wurde als Objekt instanziiert (`new TMGMT_Placeholder_Parser($event_id)`) und `parse()` als Instanzmethode mit nur einem Parameter aufgerufen — da die Klasse ausschließlich statische Methoden hat und keinen Konstruktor, warf PHP beim Aufruf einen Fatal Error der das gesamte Admin-Backend unbrauchbar machte; umgestellt auf `TMGMT_Placeholder_Parser::parse($text, $event_id)`

---

### Fixed

- **HTTP 500 beim E-Mail-Versand via REST-Endpoint** (`TMGMT_REST_API::execute_action`): Zwei Bugs behoben, die zusammen einen 500-Fehler verursachten:
  - `$email_template_id` wurde nur im inneren Fallback-Block definiert, aber danach außerhalb für CC/BCC/Reply-To/Attachments verwendet — wenn subject, body und recipient vollständig vom Frontend übergeben wurden, war die Variable undefined und löste in PHP 8 einen Warning aus, der die JSON-Response korrumpierte
  - `$request` (das `WP_REST_Request`-Objekt) wurde bei `email_confirmation`-Aktionen durch `$conf_manager->create_request()` überschrieben — umbenannt zu `$conf_result`

---

### Fixed

- **E-Mail-Vorschau im Admin-Backend schlug fehl**:
  - `TMGMT_Placeholder_Parser`: neue statische Methode `get_contact_data_for_event(int $event_id)` löst Kontaktdaten rollenbasiert (`vertrag`, `technik`, `programm`) über `_tmgmt_event_veranstalter_id` → `_tmgmt_veranstalter_contacts` → `tmgmt_contact`-Post-Meta auf; `parse()` nutzt diese Methode statt direkter Event-Meta-Keys für alle `[contact_*]`-Platzhalter
  - `TMGMT_Contract_Generator`: `generate_and_send()` und `send_contract_email()` ermitteln die Vertrags-E-Mail-Adresse jetzt über `get_contact_data_for_event()['vertrag']['email']` statt `get_post_meta($event_id, '_tmgmt_contact_email_contract')`
  - `TMGMT_REST_API` (`get_event`): Kontaktfelder werden vor der Response-Rückgabe aus dem neuen Resolver in `$clean_meta` injiziert, damit das Frontend-Dashboard korrekte Werte anzeigt; veraltete Kontakt-Keys aus dem `update_event`-Meta-Map entfernt
  - `TMGMT_Integration_Manager`: Kontaktfelder werden über `get_contact_data_for_event()` aufgelöst statt über den veralteten `_tmgmt_contact_cpt_id`-Fallback

---

### Fixed

- `TMGMT_Contract_Template_Post_Type`: Post-Type-Slug von `tmgmt_contract_template` (23 Zeichen) auf `tmgmt_contract_tpl` (18 Zeichen) gekürzt — WordPress erlaubt maximal 20 Zeichen für Post-Type-Namen; der zu lange Name war die Ursache für den Fehler „Der Inhaltstyp ist ungültig" beim Aufruf von `/wp-admin/edit.php?post_type=tmgmt_contract_template`
- Alle Referenzen auf den alten Slug in `class-action-post-type.php`, `class-admin-menu.php`, `class-settings-menu.php` sowie allen PHPUnit-Tests aktualisiert
- Unnötige Custom-Capabilities (`edit_contract_templates` etc.) und zugehörige Änderungen an `TMGMT_Roles` wieder entfernt — `capability_type => 'post'` ist korrekt und ausreichend

### Added

- `docs/placeholders.md`: vollständige Platzhalter-Referenz mit allen 38 verfügbaren Platzhaltern, gruppiert nach Kategorie (Event, Location, Kontakt, Kommunikation, Vertrag & Finanzen, Links) mit jeweiliger Meta-Key-Quelle

### Changed

- `TMGMT_Contract_Template_Post_Type`: `show_in_menu` von `true` auf `admin.php?page=tmgmt-settings` geändert — Vertragsvorlagen erscheinen nicht mehr als eigenständiger Top-Level-Menüpunkt, sondern sind über die Einstellungsübersicht erreichbar
- `TMGMT_Settings_Menu`: neue Karte „Vertragsvorlagen" auf der Einstellungsübersicht ergänzt; `tmgmt_contract_tpl` in `highlight_submenu_item()` aufgenommen, damit der Menüpunkt „Einstellungen" beim Bearbeiten einer Vorlage aktiv hervorgehoben wird
- `TMGMT_Admin_Menu`: `edit.php?post_type=tmgmt_contract_tpl` aus der `desired_order`-Liste entfernt

---

### Added

- **Automatische Vertragsgenerierung – vollständige Implementierung aller Spec-Tasks** (`contract-generation`):
  - Neuer CPT `tmgmt_contract_template` (`TMGMT_Contract_Template_Post_Type`) mit `show_in_rest: true`, Gutenberg-Block-Editor via `use_block_editor_for_post_type`-Filter (exklusiv für diesen CPT), Classic Editor bleibt für alle anderen CPTs aktiv
  - Gutenberg Sidebar Panel (`assets/js/contract-template-editor.js`, Vanilla JS) mit klickbaren Platzhalter-Buttons; Platzhalter werden via `wp_localize_script` aus `TMGMT_Placeholder_Parser::get_placeholders()` befüllt
  - `TMGMT_Contract_Generator` vollständig überarbeitet: `render_template(int $event_id, int $template_post_id)` lädt `post_content` des Template-Posts, rendert Blöcke via `apply_filters('the_content', ...)`, ersetzt Platzhalter und fügt Unterschrift-Overlay programmatisch ein (bei `tmgmt-signature-marker`-Div oder am Ende des HTML)
  - `save_pdf()` erstellt Upload-Verzeichnis `tmgmt-contracts/{event_id}/` und ruft `TMGMT_PDF_Generator::generate_contract_pdf()` auf
  - `generate_and_send()` orchestriert den vollständigen Workflow: E-Mail-Prüfung → Template laden → PDF erzeugen → Post-Meta speichern → E-Mail senden → Status setzen; bei jedem Fehler `WP_Error` zurückgeben ohne Status-Änderung
  - `send_contract_email()` befüllt E-Mail-Template via `TMGMT_Placeholder_Parser`, ersetzt `[customer_dashboard_link]` via `TMGMT_Customer_Access_Manager` und sendet PDF als Anhang
  - Aktionstyp `contract_generation` in `TMGMT_Action_Post_Type`: Dropdown für veröffentlichte `tmgmt_contract_template`-Posts (`_tmgmt_action_contract_template_id`), E-Mail-Template-Auswahl, Ziel-Status-Konfiguration; `toggleFields()`-JS erweitert
  - Dispatch für `contract_generation` in `TMGMT_Action_Handler::handle_execute_action()` via `make_contract_generator()`-Factory (testbar); Status-Wechsel-Block überspringt `contract_generation` (Status wird intern in `generate_and_send()` gesetzt)
  - Einstellungsseite `tmgmt-contract-settings` in `TMGMT_Settings_Menu` mit Media-Uploader für `tmgmt_contract_signature_id` und `wp_dropdown_users` für `tmgmt_contract_notification_user_id`; Optionsgruppe `tmgmt_contract_options`
  - Upload-Bereich `render_contract_upload_section()` im Kunden-Dashboard, sichtbar nur bei Status `contract_sent`; AJAX-Handler `tmgmt_upload_signed_contract` (auch `nopriv`) mit Token-Validierung, MIME-Whitelist (PDF, JPEG, PNG), Attachment-Speicherung, Status → `contract_signed` und Benachrichtigungs-E-Mail mit Fallback auf `admin_email`
  - `[confirmation_link]`-Platzhalter in `TMGMT_Placeholder_Parser::parse()` ergänzt (war in `get_placeholders()` registriert, aber nicht ersetzt)
  - 4 neue PHPUnit-Testklassen und 9 erweiterte/neue Testklassen:
    - `ContractBlockEditorFilterTest` – Property 10: Block-Editor-Filter exklusiv für `tmgmt_contract_template`
    - `ContractCptRegistrationTest` – Unit: CPT hat `show_in_rest`, `show_ui`, `public: false`, korrekte `supports`
    - `ContractSidebarPlaceholderTest` – Property 11: alle Platzhalter in `wp_localize_script`-Daten enthalten
    - `ContractTemplateMetaRoundTripTest` – Property 12: `_tmgmt_action_contract_template_id` Round-Trip via `save_meta_boxes()`
    - `ContractSignatureOverlayTest` – Property 13: `<img>`-Tag mit Unterschrift-URL im gerenderten HTML
    - `ContractActionHandlerTest` – Unit: Dispatch, JSON-Fehler bei `WP_Error`, kein Status-Wechsel durch Handler
    - `ContractErrorCasesTest` – Unit: `template_missing`, `missing_contract_email`, `empty_template_content`, PDF-Fehler ohne Status-Änderung, Benachrichtigungs-Fallback auf `admin_email`
    - `ContractActionTypeTest` – Unit: `contract_generation`-Option und Template-Dropdown in Metabox vorhanden
    - `tests/bootstrap.php`: Stubs für `apply_filters`, `wp_enqueue_script`, `wp_localize_script`, `absint`, `wp_dropdown_pages`, `checked` ergänzt; `get_post_type_object`-Stub erweitert

- **Spec: Automatische Vertragsgenerierung** (`contract-generation`) – Requirements, Design und Tasks erstellt:
  - Vertragsvorlagen als Custom Post Type `tmgmt_contract_template` mit Gutenberg Block Editor (selektiv aktiviert via `use_block_editor_for_post_type`-Filter; Classic Editor bleibt für alle anderen CPTs aktiv)
  - Gutenberg Sidebar Panel (`assets/js/contract-template-editor.js`) mit klickbaren Platzhalter-Buttons aus `TMGMT_Placeholder_Parser::get_placeholders()`
  - `TMGMT_Contract_Generator` liest `post_content` des Template-Posts, rendert Blöcke via `apply_filters('the_content', ...)`, ersetzt Platzhalter und fügt Unterschrift-Overlay programmatisch ein
  - Aktionstyp `contract_generation` mit Dropdown für veröffentlichte Vertragsvorlagen, E-Mail-Template-Auswahl und Ziel-Status-Konfiguration
  - Kunden-Upload des unterschriebenen Vertrags im Dashboard (Status `contract_sent` → Upload → `contract_signed` + Benachrichtigung)
  - 13 Correctness Properties definiert (Property-Based Tests mit eris + Unit-Tests)

- **Automatische Vertragsgenerierung** – vollständiger digitaler Vertragsworkflow:
  - Neue Klasse `TMGMT_Contract_Generator` (`includes/class-contract-generator.php`) mit den Methoden `generate_and_send()`, `render_template()`, `save_pdf()` und `send_contract_email()`
  - Contract-Template `templates/contract/default.php` mit Platzhalter-Unterstützung und positioniertem Unterschrift-Bild
  - Neuer Aktionstyp `contract_generation` im `TMGMT_Action_Post_Type` inkl. JavaScript-Toggle für das E-Mail-Template-Auswahlfeld
  - Dispatch für `contract_generation` in `TMGMT_Action_Handler::handle_execute_action()`
  - Einstellungsseite „Vertrag" (`tmgmt-contract-settings`) in `TMGMT_Settings_Menu` mit Media-Uploader für die Organisations-Unterschrift (`tmgmt_contract_signature_id`) und User-Dropdown für den Benachrichtigungs-Empfänger (`tmgmt_contract_notification_user_id`)
  - Upload-Bereich für den unterschriebenen Vertrag im Kunden-Dashboard (`TMGMT_Customer_Access_Manager::render_contract_upload_section()`), sichtbar nur bei Status `contract_sent`
  - AJAX-Handler `tmgmt_upload_signed_contract` (auch `nopriv`) mit Token-Validierung, MIME-Typ-Whitelist (PDF, JPEG, PNG), Attachment-Speicherung und Benachrichtigungs-E-Mail mit Fallback auf `admin_email`

### Changed

- `TMGMT_Customer_Access_Manager`: AJAX-Hooks für `tmgmt_upload_signed_contract` registriert; MIME-Erkennung in geschützte Methode `detect_mime_type()` ausgelagert (testbar)
- `TMGMT_Action_Handler`: neuer Branch für `contract_generation` in `handle_execute_action()`
- `TMGMT_Settings_Menu`: Unterseite „Vertrag" und zugehörige Optionsgruppe `tmgmt_contract_options` ergänzt
- `tmgmt.php`: `require_once` für `class-contract-generator.php` hinzugefügt
- `docs/data_structure-kommentiert.md`: Post-Meta-Felder und WordPress-Optionen der Vertragsgenerierung dokumentiert

### Tests

- 11 neue PHPUnit-Testklassen (Property-Based Tests mit eris + Unit-Tests):
  - `ContractPlaceholderPropertyTest` – Property 1: keine unersetzten Platzhalter nach Rendering
  - `ContractRenderIdempotencyTest` – Property 2: Idempotenz des Template-Renderings
  - `ContractPdfValidityTest` – Property 3: PDF-Datei beginnt mit `%PDF` nach Generierung
  - `ContractMetaRoundTripTest` – Property 4: Post-Meta `_tmgmt_contract_pdf_path` / `_tmgmt_contract_pdf_url` korrekt gesetzt
  - `ContractStatusTransitionTest` – Property 5: Event-Status nach erfolgreichem Versand
  - `ContractUploadVisibilityTest` – Property 6: Upload-Bereich nur bei Status `contract_sent` sichtbar
  - `ContractUploadRoundTripTest` – Property 7: Upload speichert Attachment-ID als Post-Meta
  - `ContractMimeValidationTest` – Property 8: ungültige MIME-Typen werden abgelehnt
  - `ContractSettingsRoundTripTest` – Property 9: Einstellungen-Round-Trip
  - `ContractActionTypeTest` – Unit: Aktionstyp `contract_generation` in der Metabox vorhanden
  - `ContractErrorCasesTest` – Unit: Fehlerfälle (fehlendes Template, fehlende E-Mail, PDF-Fehler ohne Status-Änderung, Unterschrift-`<img>`-Tag, Benachrichtigungs-Fallback auf `admin_email`)

- **Veranstalter CPT** (`includes/post-types/class-veranstalter-post-type.php`):
  - Neuer Custom Post Type `tmgmt_veranstalter` mit Adressfeldern, Kontaktzuordnung und verknüpften Locations
  - Kontakt-CPT: Post Title entfernt, wird automatisch aus Vor- und Nachname generiert
  - Kontaktsuche nutzt `meta_query` über Vorname, Nachname, Firma und E-Mail
  - PHPUnit-Tests: `AddressRoundTripTest`, `ContactAssignmentRoundTripTest`, `LocationsRoundTripTest`, `LocationRemovalInvariantTest`, `ForcePublishInvariantTest`, `MetaKeyPrefixInvariantTest`, `SanitizationInvariantTest`, `SearchResultStructureTest`, `ContractRoleValidationTest`
  - `phpunit.xml` und `tests/bootstrap.php` eingerichtet
  - `.gitignore`: `composer.phar` und `composer-setup.php` ausgeschlossen

- **Kiro Steering-Dokumentation** (`.kiro/steering/`):
  - `product.md` – Produktbeschreibung, Kernfunktionen und Domainkonzepte
  - `tech.md` – Tech-Stack, Bibliotheken, Datenbankstruktur und häufige Befehle
  - `structure.md` – Projektstruktur, Namenskonventionen und Architekturregeln
