# Anforderungsdokument: Veranstalter CPT

## Einleitung

Dieses Dokument beschreibt die Anforderungen für den neuen Custom Post Type (CPT) "Veranstalter" im TMGMT-Plugin. Veranstalter repräsentieren Organisationen und Vereine, die als Auftraggeber für Gigs fungieren. Einem Veranstalter können Kontakte mit verschiedenen Rollen zugeordnet werden, und er verfügt über eine Postadresse sowie Verknüpfungen zu einem oder mehreren Veranstaltungsorten (CPT Orte).

## Glossar

- **Veranstalter_CPT**: Der WordPress Custom Post Type `tmgmt_veranstalter`, der Organisationen und Vereine als Veranstalter abbildet.
- **Kontakt_CPT**: Der bestehende Custom Post Type `tmgmt_contact`, der einzelne Kontaktpersonen speichert.
- **Ort_CPT**: Der bestehende Custom Post Type `tmgmt_location`, der Veranstaltungsorte speichert.
- **Kontakt_Rolle**: Die Funktion, die ein Kontakt innerhalb eines Veranstalters übernimmt (z.B. Vertrag, Technik, Programm).
- **Vertrag_Rolle**: Die primäre Kontakt-Rolle eines Veranstalters. Genau ein Kontakt mit dieser Rolle ist Pflicht.
- **Technik_Rolle**: Optionale Kontakt-Rolle für den Ansprechpartner für technische Belange.
- **Programm_Rolle**: Optionale Kontakt-Rolle für den Ansprechpartner vor Ort (Programm).
- **Meta_Box**: Ein WordPress-Backend-Element zur Eingabe und Anzeige von Metadaten innerhalb der Post-Bearbeitungsseite.
- **Admin_Menü**: Das WordPress-Administrationsmenü, in dem der Veranstalter_CPT als Untermenüpunkt erscheint.

## Anforderungen

### Anforderung 1: CPT-Registrierung

**User Story:** Als Administrator möchte ich einen eigenen Post Type für Veranstalter haben, damit Organisationen und Vereine zentral verwaltet werden können.

#### Akzeptanzkriterien

1. THE Veranstalter_CPT SHALL den Post Type `tmgmt_veranstalter` mit dem Label "Veranstalter" (Plural: "Veranstalter") registrieren.
2. THE Veranstalter_CPT SHALL als Untermenüpunkt im bestehenden TMGMT-Hauptmenü (`edit.php?post_type=event`) angezeigt werden.
3. THE Veranstalter_CPT SHALL ausschließlich das Feld "title" als WordPress-Standard-Support verwenden.
4. THE Veranstalter_CPT SHALL den Post Type als nicht-öffentlich (`public => false`) mit sichtbarer Admin-Oberfläche (`show_ui => true`) registrieren.
5. THE Veranstalter_CPT SHALL die REST-API-Sichtbarkeit deaktivieren (`show_in_rest => false`).

### Anforderung 2: Postadresse des Veranstalters

**User Story:** Als Benutzer möchte ich eine Postadresse für jeden Veranstalter hinterlegen können, damit Korrespondenz und Verträge an die richtige Adresse gesendet werden.

#### Akzeptanzkriterien

1. THE Veranstalter_CPT SHALL eine Meta_Box "Postadresse" auf der Bearbeitungsseite anzeigen.
2. THE Meta_Box SHALL Eingabefelder für Straße, Hausnummer, PLZ, Ort und Land bereitstellen.
3. WHEN ein Benutzer die Bearbeitungsseite speichert, THE Veranstalter_CPT SHALL alle Adressfelder als Post-Meta mit dem Präfix `_tmgmt_veranstalter_` speichern.
4. WHEN ein Benutzer die Bearbeitungsseite öffnet, THE Veranstalter_CPT SHALL die gespeicherten Adressdaten in den Eingabefeldern anzeigen.
5. THE Veranstalter_CPT SHALL alle Eingaben vor dem Speichern mit `sanitize_text_field` bereinigen.
6. THE Veranstalter_CPT SHALL die Speicherung durch eine Nonce-Prüfung absichern.

### Anforderung 3: Kontaktzuordnung mit Rollen

**User Story:** Als Benutzer möchte ich einem Veranstalter Kontakte mit spezifischen Rollen zuordnen können, damit klar ist, wer für welchen Bereich zuständig ist.

#### Akzeptanzkriterien

1. THE Veranstalter_CPT SHALL eine Meta_Box "Kontakte" auf der Bearbeitungsseite anzeigen.
2. THE Meta_Box SHALL die Zuordnung von Kontakten aus dem bestehenden Kontakt_CPT ermöglichen.
3. THE Veranstalter_CPT SHALL die folgenden Kontakt_Rollen unterstützen: Vertrag_Rolle, Technik_Rolle und Programm_Rolle.
4. THE Veranstalter_CPT SHALL genau einen Kontakt mit der Vertrag_Rolle als Pflichtfeld erfordern.
5. THE Veranstalter_CPT SHALL die Technik_Rolle und Programm_Rolle als optionale Zuordnungen behandeln.
6. WHEN ein Kontakt zugeordnet wird, THE Veranstalter_CPT SHALL die Kontakt-ID und die zugehörige Rolle als serialisiertes Array im Post-Meta `_tmgmt_veranstalter_contacts` speichern.
7. THE Veranstalter_CPT SHALL ermöglichen, dass ein einzelner Kontakt mehrere Rollen gleichzeitig übernimmt.
8. WHEN ein Benutzer einen Kontakt zuordnen möchte, THE Meta_Box SHALL eine Suchfunktion bereitstellen, die bestehende Kontakte aus dem Kontakt_CPT durchsucht.
9. IF beim Speichern kein Kontakt mit der Vertrag_Rolle zugeordnet ist, THEN THE Veranstalter_CPT SHALL eine Admin-Hinweismeldung anzeigen, die auf die fehlende Pflicht-Rolle hinweist.

### Anforderung 4: Verknüpfung mit Veranstaltungsorten

**User Story:** Als Benutzer möchte ich einem Veranstalter einen oder mehrere Veranstaltungsorte zuordnen können, damit die Spielstätten des Veranstalters dokumentiert sind.

#### Akzeptanzkriterien

1. THE Veranstalter_CPT SHALL eine Meta_Box "Veranstaltungsorte" auf der Bearbeitungsseite anzeigen.
2. THE Meta_Box SHALL die Zuordnung von einem oder mehreren Orten aus dem bestehenden Ort_CPT ermöglichen.
3. WHEN ein Benutzer einen Ort zuordnen möchte, THE Meta_Box SHALL eine Suchfunktion bereitstellen, die bestehende Orte aus dem Ort_CPT durchsucht.
4. WHEN ein Ort zugeordnet wird, THE Veranstalter_CPT SHALL die Ort-IDs als Array im Post-Meta `_tmgmt_veranstalter_locations` speichern.
5. THE Meta_Box SHALL die zugeordneten Orte mit Name und Stadt anzeigen.
6. THE Meta_Box SHALL das Entfernen einzelner zugeordneter Orte ermöglichen.

### Anforderung 5: Speicherung und Datenintegrität

**User Story:** Als Benutzer möchte ich sicher sein, dass alle Veranstalter-Daten korrekt gespeichert und geladen werden, damit keine Informationen verloren gehen.

#### Akzeptanzkriterien

1. THE Veranstalter_CPT SHALL beim Speichern eine WordPress-Nonce-Prüfung durchführen.
2. THE Veranstalter_CPT SHALL bei aktivem Autosave die Speicherung der Meta-Daten überspringen.
3. THE Veranstalter_CPT SHALL vor dem Speichern die Berechtigung des Benutzers mit `current_user_can('edit_post', $post_id)` prüfen.
4. WHEN ein Veranstalter-Post gelöscht wird, THE Veranstalter_CPT SHALL die zugehörigen Meta-Daten durch WordPress-Standard-Verhalten bereinigen lassen.
5. THE Veranstalter_CPT SHALL den Post-Status beim Speichern auf "publish" erzwingen und den Entwurf-Modus deaktivieren.

### Anforderung 6: AJAX-Suchfunktionen

**User Story:** Als Benutzer möchte ich Veranstalter durchsuchen können, damit ich sie in anderen Kontexten (z.B. Events) schnell finden und verknüpfen kann.

#### Akzeptanzkriterien

1. THE Veranstalter_CPT SHALL einen AJAX-Endpunkt `tmgmt_search_veranstalter` bereitstellen.
2. WHEN eine Suchanfrage eingeht, THE Veranstalter_CPT SHALL Veranstalter nach Titel durchsuchen und die Ergebnisse als JSON zurückgeben.
3. THE Veranstalter_CPT SHALL in den Suchergebnissen die ID, den Titel und die Stadt des Veranstalters zurückgeben.
4. THE Veranstalter_CPT SHALL die Suchergebnisse auf maximal 20 Einträge begrenzen.
5. THE Veranstalter_CPT SHALL vor der Ausführung der Suche die Berechtigung des Benutzers prüfen.

### Anforderung 7: Plugin-Integration

**User Story:** Als Entwickler möchte ich, dass der Veranstalter_CPT nahtlos in die bestehende Plugin-Architektur integriert wird, damit die Codebasis konsistent bleibt.

#### Akzeptanzkriterien

1. THE Veranstalter_CPT SHALL als eigene Klasse `TMGMT_Veranstalter_Post_Type` in der Datei `includes/post-types/class-veranstalter-post-type.php` implementiert werden.
2. THE Veranstalter_CPT SHALL in der Hauptdatei `tmgmt.php` per `require_once` eingebunden und in der Funktion `tmgmt_init()` instanziiert werden.
3. THE Veranstalter_CPT SHALL dem bestehenden Architekturmuster der anderen Post Types (Kontakt_CPT, Ort_CPT) folgen.
4. THE Veranstalter_CPT SHALL alle Meta-Keys mit dem Präfix `_tmgmt_veranstalter_` versehen.
