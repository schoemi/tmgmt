# TMGMT - Event Management Plugin

## Überblick
Das **TMGMT Plugin** ist eine spezialisierte Lösung für WordPress zur Verwaltung von Band-Auftritten, Konzertanfragen und Tour-Planung. Es kombiniert ein intuitives Frontend-Dashboard (Kanban-Board) mit einer tiefen Integration in das WordPress-Backend.

---

## Funktionen für Anwender

### 1. Das Dashboard (Kanban Board)
Das Herzstück der Anwendung ist das visuelle Board, das einen sofortigen Überblick über alle Anfragen und Termine bietet.

*   **Visuelle Übersicht:** Events werden als Karten dargestellt, sortiert nach ihrem aktuellen Status (Spalten).
*   **Drag & Drop Workflow:** Der Status eines Events kann einfach durch Ziehen der Karte in eine andere Spalte geändert werden.
*   **Schnellerfassung:** Über den Button "Neues Event" können neue Anfragen mit minimalem Aufwand (nur Titel) angelegt werden.
*   **Farbkodierung:** Spalten können farblich markiert werden, um Phasen wie "Neu", "In Arbeit" oder "Abgeschlossen" visuell zu trennen.

### 2. Event-Verwaltung (Detail-Ansicht)
Durch Klick auf eine Karte öffnet sich ein detailliertes Modal-Fenster zur Bearbeitung aller Event-Informationen. Die Daten sind logisch gruppiert:

#### a) Anfragedaten
*   Titel des Events
*   Datum und geplante Startzeit (Auftrittsbeginn)

#### b) Veranstaltungsdaten
*   **Location:** Name des Veranstaltungsorts (z.B. "Stadthalle").
*   **Adresse:** Straße, Hausnummer, PLZ, Stadt, Land.
*   **Karte:** Automatische Anzeige des Standorts auf einer Karte (basierend auf der Adresse).

#### c) Planung & Logistik
*   **Zeiten:** Späteste Anreisezeit (Get-in) und Abreisezeit.
*   **Hinweise:** Freitextfeld für wichtige Infos zu Anfahrt, Parken oder Bus-Logistik.

#### d) Kontaktdaten (Veranstalter)
*   **Hauptansprechpartner:** Anrede, Vorname, Nachname, Firma/Verein.
*   **Postadresse:** Adresse für Vertragszusendung.
*   **Kommunikation:** E-Mail und Telefonnummer (spezifisch für Vertragsangelegenheiten).

#### e) Weitere Ansprechpartner
*   **Technik:** Name, E-Mail, Telefon.
*   **Programm/Ablauf:** Name, E-Mail, Telefon.

#### f) Vertragsdaten
*   **Finanzen:** Vereinbarte Gage und Anzahlung.
*   **Historie:** Datum der ursprünglichen Anfrage.

#### g) Notizen & Logbuch
*   **Interne Notizen:** Ein großes Textfeld für alles, was nicht in die Masken passt.
*   **Logbuch:** Eine automatische Historie, die festhält, wer wann den Status geändert hat.

### 3. Intelligente Validierung (Pflichtfelder)
Das System unterstützt den Workflow durch gezielte Abfragen:
*   **Status-Regeln:** Für jeden Statuswechsel können Pflichtfelder definiert werden (z.B. "Für Status 'Vertrag' muss eine Gage eingetragen sein").
*   **Bottom Sheet:** Fehlen beim Verschieben einer Karte notwendige Daten, öffnet sich am unteren Bildschirmrand automatisch ein Eingabebereich ("Bottom Sheet"), in dem nur die fehlenden Infos abgefragt werden. Das Event wird erst verschoben, wenn diese Daten eingetragen sind.

### 4. Automatisierung
*   **Geocoding:** Beim Speichern der Adresse werden im Hintergrund automatisch die Geokoordinaten (Latitude/Longitude) ermittelt, um die Karte darzustellen.
*   **Auto-Save:** Im Frontend-Modal werden Änderungen automatisch gespeichert, sobald ein Feld verlassen wird.

---

## Datenstruktur

Das Plugin basiert auf dem WordPress Custom Post Type `event`.

### Datenmodell
Die Daten werden in logischen Gruppen in den `post_meta` Tabellen gespeichert:

| Gruppe | Felder (Beispiele) |
| :--- | :--- |
| **Core** | `_tmgmt_event_date`, `_tmgmt_event_start_time`, `_tmgmt_status` |
| **Venue** | `_tmgmt_venue_name`, `_tmgmt_venue_street`, `_tmgmt_venue_city`, `_tmgmt_geo_lat` |
| **Contact** | `_tmgmt_contact_firstname`, `_tmgmt_contact_lastname`, `_tmgmt_contact_company` |
| **Planning** | `_tmgmt_event_arrival_time`, `_tmgmt_event_departure_time`, `_tmgmt_arrival_notes` |
| **Finance** | `_tmgmt_fee`, `_tmgmt_deposit` |

---

## Einrichtung (Admin)

1.  **Status definieren:** Unter `TMGMT > Status Definitionen` werden die möglichen Zustände (z.B. "Option", "Fest") angelegt. Hier können auch Pflichtfelder pro Status definiert werden.
2.  **Board konfigurieren:** Unter `TMGMT > Kanban Spalten` werden die Spalten für das Dashboard erstellt und die Status zugeordnet.
3.  **Frontend einbinden:** Der Shortcode `[tmgmt_dashboard]` platziert das Board auf einer beliebigen WordPress-Seite.

---
*Dokumentation erstellt am: 23.11.2025*
