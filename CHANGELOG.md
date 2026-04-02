# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased] – 2026-04-02

### Added

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
