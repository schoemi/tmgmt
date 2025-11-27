# TMGMT - Event & Tour Management Plugin

## Überblick
Das **TMGMT Plugin** ist eine umfassende Lösung für WordPress zur Verwaltung von Band-Auftritten, Konzertanfragen und komplexer Tour-Logistik. Es kombiniert ein intuitives Frontend-Dashboard (Kanban-Board) mit einer leistungsstarken Tourenplanung, die automatisch Fahrzeiten, Puffer und Shuttle-Services berechnet.

---

## Hauptfunktionen

### 1. Event Management (Kanban Board)
Das Herzstück der täglichen Arbeit ist das visuelle Board für den schnellen Überblick.

*   **Visuelles Kanban-Board:** Events werden als Karten dargestellt, sortiert nach Status.
*   **Drag & Drop Workflow:** Statusänderungen durch einfaches Verschieben.
*   **Detail-Modal:**
    *   **Stammdaten:** Datum, Uhrzeit, Location, Kontakte (Veranstalter, Technik, Programm).
    *   **Vertragsdaten:** Gage, Anzahlung, Anfragedatum.
    *   **Dateimanagement:** Upload von Verträgen, Ridern, Setlisten direkt am Event.
    *   **Logbuch & Kommunikation:** Automatische Historie aller Änderungen und Notizen.
    *   **Touren-Info:** Anzeige zugehöriger Tourenpläne direkt im Event.
*   **Validierung:** Definition von Pflichtfeldern für Statuswechsel (z.B. "Kein Vertrag ohne Gage").

### 2. Intelligente Tourenplanung
Das Plugin berechnet automatisch detaillierte Tagespläne für Touren mit mehreren Stopps.

*   **Automatische Routenberechnung:** Integration von **HERE Maps** oder **OpenRouteService** zur Ermittlung von Distanzen und Fahrzeiten.
*   **Caching:** Routendaten werden gecached, um API-Limits zu schonen und die Performance zu maximieren.
*   **Planungs-Modi:**
    *   **Entwurf:** Zeigt Lücken im Zeitplan auf (Gap-Analyse).
    *   **Echtplanung:** Validiert Pufferzeiten und warnt bei Verspätungen.
*   **Logistik-Features:**
    *   **Bus-Faktor:** Automatische Anpassung der Fahrzeiten (z.B. x1.5) für Busfahrten.
    *   **Ladezeiten:** Konfigurierbare Puffer für Loading/Unloading.
    *   **Shuttle-Service:** Integration von Abhol- und Rückfahrt-Routen (z.B. Einsammeln der Bandmitglieder).
    *   **Ende am Proberaum:** Optionale Rückkehr zur Base vor der Shuttle-Rückfahrt.
*   **Frontend-Ansicht:**
    *   Übersicht aller Touren via Shortcode `[tmgmt_tour_overview]`.
    *   Detaillierte Tagesansicht mit Karte und Zeitplan.
    *   **PDF-Export:** Druckoptimierte Ansicht für die Crew.

### 3. Shuttle Management
Verwaltung von Standard-Routen für den Personentransport.

*   **Routen-Definition:** Anlegen von Shuttle-Routen (z.B. "Niederrhein Einsammeln") mit definierten Haltepunkten.
*   **Typen:** Unterscheidung zwischen Abholung (Pickup) und Rückfahrt (Dropoff).
*   **Integration:** Nahtlose Einbindung in die Tourenberechnung.

### 5. Live Tracking & Kommunikation
*   **Live View:** Echtzeit-Ansicht für laufende Touren.
    *   **Interaktive Karte:** Zeigt aktuelle Position, Route und nächsten Halt.
    *   **Timeline:** Dynamische Liste der Wegpunkte mit Status (Geplant, Verspätet, Erledigt).
    *   **Test-Modus:** Simulation von Position und Zeit für Testszenarien.
*   **E-Mail Aktionen:**
    *   Versand von E-Mails direkt aus dem Event-Modal.
    *   Unterstützung für Anhänge (Verträge, Rider).
    *   Vorlagen-System für Standard-Mails.

---

## Technische Struktur

### Custom Post Types
*   `event`: Die eigentliche Veranstaltung.
*   `tmgmt_tour`: Ein Tagesplan, der mehrere Events verknüpft.
*   `tmgmt_shuttle`: Definition von Shuttle-Routen.
*   `tmgmt_kanban_col` / `tmgmt_status_def`: Konfiguration des Boards.

### Shortcodes
*   `[tmgmt_dashboard]`: Das Kanban-Board für das Event-Management.
*   `[tmgmt_tour_overview]`: Liste und Detailansicht der Tourenpläne.

### API & Integration
*   **REST API:** Das Frontend kommuniziert vollständig über eigene REST-Endpoints (`tmgmt/v1/`).
*   **Live API:** Endpoints für Echtzeit-Positionsdaten und Test-Modus Steuerung.
*   **Externe APIs:**
    *   HERE Maps API (Routing)
    *   OpenRouteService (Routing Fallback)
    *   Nominatim / Photon (Geocoding)

---

## Einrichtung

1.  **Grundeinstellungen:** API-Keys für Kartendienste und Home-Base-Koordinaten hinterlegen.
2.  **Status & Board:** Status-Definitionen und Kanban-Spalten konfigurieren.
3.  **Shuttles:** Standard-Shuttle-Routen anlegen.
4.  **Frontend:** Seiten erstellen und Shortcodes einfügen.
5.  **Live Tracking:** Test-Modus in den Einstellungen aktivieren/deaktivieren.

---
*Version: 0.4.0 | Stand: 27.11.2025*
