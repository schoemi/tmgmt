# TMGMT - Technische Dokumentation

## Architektur
Das Plugin folgt einer modularen Struktur, die Backend-Logik (PHP) und Frontend-Interaktion (JS/REST API) trennt.

### Kern-Komponenten
1.  **Custom Post Types (CPT):**
    *   `event`: Speichert die eigentlichen Veranstaltungsdaten.
    *   `tmgmt_status_def`: Definiert mögliche Status und deren Regeln (z.B. Pflichtfelder).
    *   `tmgmt_kanban_col`: Definiert die Spalten des Kanban-Boards.
2.  **REST API:** Dient als Schnittstelle zwischen dem Frontend-Dashboard und der WordPress-Datenbank.
3.  **Frontend Dashboard:** Eine Single-Page-Application (SPA)-ähnliche Oberfläche, die via Shortcode eingebunden wird.

---

## Dateistruktur

```
tmgmt/
├── tmgmt.php                       # Hauptdatei, Initialisierung
├── README.md                       # Anwender-Dokumentation
├── TECHNICAL.md                    # Technische Dokumentation
├── assets/
│   ├── css/
│   │   ├── admin.css               # Styles für WP-Admin
│   │   └── frontend-dashboard.css  # Styles für das Kanban-Board
│   └── js/
│       ├── admin.js                # Scripts für WP-Admin
│       └── frontend-dashboard.js   # Hauptlogik des Dashboards
└── includes/
    ├── class-rest-api.php          # REST API Endpoints
    ├── class-frontend-dashboard.php# Shortcode & Asset Loading
    ├── class-log-manager.php       # Logging-Logik
    ├── class-action-handler.php    # (Optional) Server-side Actions
    └── post-types/
        ├── class-event-cpt.php     # Registrierung CPT 'event'
        ├── class-event-meta-boxes.php # Backend Meta Boxen
        ├── class-status-cpt.php    # CPT 'tmgmt_status_def'
        └── class-kanban-cpt.php    # CPT 'tmgmt_kanban_col'
```

---

## Klassen-Referenz

### `TMGMT_Event_Meta_Boxes`
Verwaltet die Eingabemasken im WordPress-Backend.
*   `get_registered_fields()`: Gibt alle verfügbaren Meta-Felder zurück.
*   `save_meta_boxes()`: Speichert die Daten und triggert Log-Einträge bei Statusänderungen.

### `TMGMT_REST_API`
Stellt die Endpoints unter dem Namespace `tmgmt/v1` bereit.
*   `GET /kanban`: Liefert Spalten-Konfiguration und alle Events für das Board.
*   `POST /events`: Erstellt ein neues Event (nur Titel).
*   `GET /events/(?P<id>\d+)`: Lädt alle Details, Meta-Daten und Logs eines Events.
*   `POST /events/(?P<id>\d+)`: Aktualisiert Event-Daten. Handelt auch das Logging.

### `TMGMT_Frontend_Dashboard`
*   Registriert den Shortcode `[tmgmt_dashboard]`.
*   Lädt die notwendigen Scripts und Styles (inkl. Leaflet für Karten).
*   Übergibt Konfigurationsdaten (Nonce, API-URL, Feld-Mappings) via `wp_localize_script` an das Frontend.

---

## Frontend-Logik (`frontend-dashboard.js`)

Das Frontend ist in Vanilla JS geschrieben und steuert das Kanban-Board sowie das Detail-Modal.

### Wichtige Funktionen
*   `loadBoard()`: Lädt die Kanban-Daten und rendert die Spalten.
*   `handleDrop()`: Verarbeitet Drag & Drop Events, aktualisiert den Status optimistisch und sendet den API-Request.
*   `openModal(id)`: Lädt Event-Details und baut das Modal dynamisch auf.
*   `renderModalContent(data)`: Generiert das HTML für das Modal basierend auf den empfangenen Daten.
*   `autoSave()`: Debounced Funktion, die Änderungen an Feldern automatisch speichert.
*   `checkRequiredFields()`: Prüft bei Statusänderungen gegen die `tmgmtData.status_requirements`. Falls Felder fehlen, wird das Bottom Sheet aktiviert.

### Bottom Sheet Workflow
1.  User ändert Status (via Dropdown oder Drag & Drop).
2.  JS prüft `required_fields` für den neuen Status.
3.  Wenn Daten fehlen:
    *   Statusänderung wird abgebrochen/revertiert.
    *   Bottom Sheet fährt hoch und zeigt Eingabefelder für die fehlenden Daten.
4.  User füllt Daten aus -> `autoSave` speichert sie.
5.  Sobald alle Daten da sind, kann der Statuswechsel erneut durchgeführt werden.

---

## Datenbank & Meta-Keys

Alle Event-Daten liegen in der `wp_postmeta` Tabelle.
Präfix: `_tmgmt_`

*   `_tmgmt_status`: Der aktuelle Status-Slug (Verknüpfung zu `tmgmt_status_def`).
*   `_tmgmt_venue_name`: Name des Veranstaltungsorts.
*   `_tmgmt_geo_lat` / `_tmgmt_geo_lng`: Geokoordinaten für die Karte.

---
*Technische Dokumentation Stand: 23.11.2025*
