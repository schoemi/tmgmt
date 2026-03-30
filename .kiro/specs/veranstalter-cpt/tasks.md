# Implementierungsplan: Veranstalter CPT

## Übersicht

Implementierung des Custom Post Types `tmgmt_veranstalter` als Klasse `TMGMT_Veranstalter_Post_Type` nach dem bestehenden Architekturmuster von Contact und Location CPT. Die Implementierung erfolgt inkrementell: zuerst die Grundstruktur mit CPT-Registrierung, dann Meta-Boxen und Speicherlogik, anschließend AJAX-Endpunkte, und zuletzt die Integration in `tmgmt.php`.

## Tasks

- [x] 1. CPT-Registrierung und Grundstruktur
  - [x] 1.1 Klasse `TMGMT_Veranstalter_Post_Type` anlegen
    - Datei `includes/post-types/class-veranstalter-post-type.php` erstellen
    - Klasse mit Konstruktor anlegen, der die Hooks `init`, `add_meta_boxes`, `save_post`, `wp_insert_post_data`, `admin_notices` und die drei AJAX-Actions registriert
    - Methode `register_post_type()` implementieren: Post Type `tmgmt_veranstalter` mit Labels (Singular/Plural: "Veranstalter"), `public => false`, `show_ui => true`, `show_in_menu => 'edit.php?post_type=event'`, `supports => ['title']`, `show_in_rest => false`, `capability_type => 'post'`, `has_archive => false`, `hierarchical => false`
    - Methode `force_publish_status($data, $postarr)` implementieren: Erzwingt `post_status = 'publish'` für `tmgmt_veranstalter` Posts (außer `trash` und `auto-draft`), analog zum Event CPT Pattern
    - _Anforderungen: 1.1, 1.2, 1.3, 1.4, 1.5, 5.5_

  - [x] 1.2 Property-Test: Force-Publish Invariante
    - **Property 7: Force-Publish Invariante**
    - Für beliebige Post-Status-Werte (außer `trash` und `auto-draft`) muss der resultierende Status `publish` sein
    - **Validiert: Anforderung 5.5**

  - [x] 1.3 Property-Test: Meta-Key Präfix Invariante
    - **Property 10: Meta-Key Präfix Invariante**
    - Alle vom Veranstalter CPT definierten Meta-Keys müssen mit `_tmgmt_veranstalter_` beginnen
    - **Validiert: Anforderung 7.4**

- [x] 2. Meta-Box Postadresse
  - [x] 2.1 Adress-Meta-Box implementieren
    - Methode `add_meta_boxes()` implementieren: Drei Meta-Boxen registrieren (Postadresse, Kontakte, Veranstaltungsorte)
    - Methode `render_address_box($post)` implementieren: Felder für Straße, Hausnummer, PLZ, Ort, Land als `<table class="form-table">` rendern, Nonce-Feld `tmgmt_save_veranstalter_meta` / `tmgmt_veranstalter_meta_nonce` setzen
    - Gespeicherte Werte via `get_post_meta` laden und in den Feldern anzeigen
    - _Anforderungen: 2.1, 2.2, 2.4_

  - [x] 2.2 Speicherlogik für Adressfelder implementieren
    - Methode `save_meta_boxes($post_id)` implementieren mit: Nonce-Prüfung (`wp_verify_nonce`), Autosave-Check (`DOING_AUTOSAVE`), Berechtigungsprüfung (`current_user_can('edit_post', $post_id)`)
    - Adressfelder (`_tmgmt_veranstalter_street`, `_tmgmt_veranstalter_number`, `_tmgmt_veranstalter_zip`, `_tmgmt_veranstalter_city`, `_tmgmt_veranstalter_country`) mit `sanitize_text_field` bereinigen und via `update_post_meta` speichern
    - _Anforderungen: 2.3, 2.5, 2.6, 5.1, 5.2, 5.3_

  - [x] 2.3 Property-Test: Adressdaten Round-Trip
    - **Property 1: Adressdaten Round-Trip**
    - Zufällige Adressdaten speichern und laden, gespeicherter Wert muss dem Eingabewert entsprechen
    - **Validiert: Anforderungen 2.3, 2.4**

  - [x] 2.4 Property-Test: Sanitization-Invariante
    - **Property 2: Sanitization-Invariante**
    - Für beliebige Eingabestrings (inkl. HTML-Tags, Sonderzeichen) muss der gespeicherte Wert `sanitize_text_field(input)` entsprechen
    - **Validiert: Anforderung 2.5**

- [x] 3. Checkpoint - Grundstruktur und Adresse prüfen
  - Sicherstellen, dass alle Tests bestehen. Bei Fragen den Benutzer konsultieren.

- [x] 4. Meta-Box Kontaktzuordnung mit Rollen
  - [x] 4.1 Kontakte-Meta-Box implementieren
    - Methode `render_contacts_box($post)` implementieren: Pro Rolle (Vertrag, Technik, Programm) ein AJAX-Suchfeld und Anzeige des ausgewählten Kontakts rendern
    - Vertrag-Rolle visuell als Pflichtfeld markieren
    - Technik- und Programm-Rolle als optional darstellen
    - Gespeicherte Kontaktzuordnungen aus `_tmgmt_veranstalter_contacts` laden und anzeigen, ungültige Kontakt-IDs beim Rendern ignorieren
    - _Anforderungen: 3.1, 3.2, 3.3, 3.4, 3.5, 3.7_

  - [x] 4.2 Speicherlogik für Kontaktzuordnungen implementieren
    - In `save_meta_boxes()` die Kontaktzuordnungen als serialisiertes Array `[{contact_id, role}]` im Meta-Key `_tmgmt_veranstalter_contacts` speichern
    - Ein Kontakt kann mehrere Rollen gleichzeitig haben (gleiche contact_id, verschiedene Rollen)
    - _Anforderungen: 3.6, 3.7_

  - [x] 4.3 Validierung der Vertrag-Rolle implementieren
    - Methode `show_missing_contract_notice()` implementieren: Prüft via Transient ob beim letzten Speichern kein Kontakt mit Rolle `vertrag` zugeordnet war
    - In `save_meta_boxes()` Transient setzen wenn Vertrag-Rolle fehlt
    - Admin-Hinweis (notice-warning) anzeigen wenn Pflicht-Rolle fehlt
    - _Anforderungen: 3.4, 3.9_

  - [x] 4.4 AJAX-Endpunkt für Kontaktsuche implementieren
    - Methode `ajax_search_contacts_for_veranstalter()` implementieren: Berechtigungsprüfung, Suche nach `tmgmt_contact` Posts per Titel, Rückgabe von `{id, title, firstname, lastname, email}` als JSON
    - _Anforderungen: 3.8_

  - [x] 4.5 Property-Test: Kontaktzuordnung Round-Trip
    - **Property 3: Kontaktzuordnung Round-Trip**
    - Zufällige Kontaktzuordnungen (Arrays von `{contact_id, role}`) speichern und laden
    - **Validiert: Anforderungen 3.6, 3.7**

  - [x] 4.6 Property-Test: Vertrag-Rolle Validierung
    - **Property 4: Vertrag-Rolle Validierung**
    - Validierung meldet genau dann Fehler wenn kein Kontakt mit Rolle `vertrag` zugeordnet ist
    - **Validiert: Anforderungen 3.4, 3.5, 3.9**

- [x] 5. Meta-Box Veranstaltungsorte
  - [x] 5.1 Veranstaltungsorte-Meta-Box implementieren
    - Methode `render_locations_box($post)` implementieren: AJAX-Suchfeld zum Hinzufügen von Orten, Liste der zugeordneten Orte mit Name und Stadt, Entfernen-Button pro Ort
    - Gespeicherte Ort-IDs aus `_tmgmt_veranstalter_locations` laden und anzeigen, ungültige Ort-IDs beim Rendern ignorieren
    - _Anforderungen: 4.1, 4.2, 4.5, 4.6_

  - [x] 5.2 Speicherlogik für Veranstaltungsorte implementieren
    - In `save_meta_boxes()` die Ort-IDs als Array im Meta-Key `_tmgmt_veranstalter_locations` speichern
    - _Anforderungen: 4.4_

  - [x] 5.3 AJAX-Endpunkt für Ortsuche implementieren
    - Methode `ajax_search_locations_for_veranstalter()` implementieren: Berechtigungsprüfung, Suche nach `tmgmt_location` Posts per Titel, Rückgabe von `{id, title, city}` als JSON
    - _Anforderungen: 4.3_

  - [x] 5.4 Property-Test: Veranstaltungsorte Round-Trip
    - **Property 5: Veranstaltungsorte Round-Trip**
    - Zufällige Arrays von Ort-IDs speichern und laden
    - **Validiert: Anforderung 4.4**

  - [x] 5.5 Property-Test: Ort-Entfernung Invariante
    - **Property 6: Ort-Entfernung Invariante**
    - Nach Entfernen eines Orts enthält die Liste genau ein Element weniger und der entfernte Ort ist nicht mehr vorhanden
    - **Validiert: Anforderung 4.6**

- [x] 6. Checkpoint - Meta-Boxen und Speicherung prüfen
  - Sicherstellen, dass alle Tests bestehen. Bei Fragen den Benutzer konsultieren.

- [x] 7. AJAX-Suchendpunkt für Veranstalter
  - [x] 7.1 Veranstalter-Suche implementieren
    - Methode `ajax_search_veranstalter()` implementieren: Berechtigungsprüfung (`current_user_can('edit_posts')`), Suche nach `tmgmt_veranstalter` Posts per Titel, Rückgabe von `{id, title, city}` als JSON, Limit auf 20 Ergebnisse
    - Suchbegriff via `sanitize_text_field` bereinigen
    - _Anforderungen: 6.1, 6.2, 6.3, 6.4, 6.5_

  - [x] 7.2 Property-Test: Veranstalter-Suche Ergebnisstruktur
    - **Property 8: Veranstalter-Suche Ergebnisstruktur**
    - Jedes Suchergebnis muss `id`, `title` und `city` enthalten
    - **Validiert: Anforderungen 6.2, 6.3**

  - [x] 7.3 Property-Test: Suchergebnis-Limit
    - **Property 9: Suchergebnis-Limit**
    - Anzahl der Suchergebnisse darf niemals 20 überschreiten
    - **Validiert: Anforderung 6.4**

- [x] 8. Plugin-Integration
  - [x] 8.1 Einbindung in tmgmt.php
    - `require_once TMGMT_PLUGIN_DIR . 'includes/post-types/class-veranstalter-post-type.php';` im require-Block hinzufügen (nach `class-contact-post-type.php`)
    - `new TMGMT_Veranstalter_Post_Type();` in `tmgmt_init()` hinzufügen (nach `new TMGMT_Contact_Post_Type()`)
    - _Anforderungen: 7.1, 7.2, 7.3_

- [x] 9. Abschluss-Checkpoint
  - Sicherstellen, dass alle Tests bestehen und der Veranstalter CPT korrekt im Admin-Menü erscheint. Bei Fragen den Benutzer konsultieren.

## Hinweise

- Tasks mit `*` markiert sind optional und können für ein schnelleres MVP übersprungen werden
- Jeder Task referenziert spezifische Anforderungen für Nachverfolgbarkeit
- Checkpoints stellen inkrementelle Validierung sicher
- Property-Tests verwenden die Eris-Bibliothek für PHP Property-Based Testing
- Die Implementierung folgt dem bestehenden Pattern von `TMGMT_Contact_Post_Type` und `TMGMT_Location_Post_Type`
