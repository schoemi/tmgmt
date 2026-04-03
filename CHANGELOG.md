# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

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
