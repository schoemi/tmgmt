# Datenstruktur – Töns Management Plugin

## Custom Post Type: Event

### Post Type Details
- **Post Type Slug:** `event`
- **Hierarchisch:** Nein
- **Öffentlich sichtbar:** Nein (nur im Backend)
- **Unterstützte Features:** title, editor, custom-fields

### Event-Felder (Meta Fields)

#### Pflichtfelder
| Feld | Meta Key | Typ | Beschreibung |
|------|----------|-----|--------------|
| Veranstaltungsdatum | `event_date` | `date` | Datum des Auftritts (YYYY-MM-DD) |
| Ansprechpartner Vorname | `contact_first_name` | `text` | Vorname des Veranstalters/Ansprechpartners |
| Ansprechpartner Nachname | `contact_last_name` | `text` | Nachname des Veranstalters/Ansprechpartners |

#### Optional Felder
| Feld | Meta Key | Typ | Beschreibung |
|------|----------|-----|--------------|
| Veranstaltungsort | `event_location` | `text` | Name des Veranstaltungsortes |
| Adresse | `event_address` | `textarea` | Vollständige Adresse |
| Stadt | `event_city` | `text` | Stadt |
| PLZ | `event_zip` | `text` | Postleitzahl |
| Land | `event_country` | `text` | Land |
| Honorar | `event_fee` | `number` | Gage/Honorar (in EUR) |
| Ansprechpartner E-Mail | `contact_email` | `email` | E-Mail des Ansprechpartners |
| Ansprechpartner Telefon | `contact_phone` | `text` | Telefonnummer |
| Veranstalter Firma | `organizer_company` | `text` | Name der Veranstaltungsfirma |
| Bühnenzeit | `stage_time` | `time` | Geplante Bühnenzeit (HH:MM) |
| Soundcheck Zeit | `soundcheck_time` | `time` | Soundcheck-Zeit (HH:MM) |
| Technik-Anforderungen | `tech_requirements` | `textarea` | Technische Anforderungen/Rider |
| Notizen | `event_notes` | `textarea` | Interne Notizen |
| Status | `event_status` | `text` | Aktueller Status (ID aus Status-Verwaltung) |

---

## Status-Verwaltung

### Datenbank-Tabelle: `wp_tmgmt_statuses`

| Spalte | Typ | Beschreibung |
|--------|-----|--------------|
| `id` | `BIGINT` | Primary Key, Auto Increment |
| `name` | `VARCHAR(100)` | Name des Status (z.B. "Anfrage", "Bestätigt") |
| `slug` | `VARCHAR(100)` | URL-freundlicher Slug (z.B. "anfrage", "bestaetigt") |
| `color` | `VARCHAR(7)` | Hex-Farbe für Kanban-Darstellung (z.B. "#3498db") |
| `sort_order` | `INT` | Sortierreihenfolge |
| `created_at` | `DATETIME` | Erstellungsdatum |
| `updated_at` | `DATETIME` | Letzte Änderung |

### Standard-Status (bei Plugin-Aktivierung)
1. Anfrage (#95a5a6)
2. In Planung (#3498db)
3. Bestätigt (#2ecc71)
4. Abgeschlossen (#27ae60)
5. Abgesagt (#e74c3c)

---

## Status-Log

### Datenbank-Tabelle: `wp_tmgmt_status_log`

| Spalte | Typ | Beschreibung |
|--------|-----|--------------|
| `id` | `BIGINT` | Primary Key, Auto Increment |
| `event_id` | `BIGINT` | Event Post ID (Foreign Key) |
| `old_status` | `VARCHAR(100)` | Vorheriger Status (Name oder ID) |
| `new_status` | `VARCHAR(100)` | Neuer Status (Name oder ID) |
| `changed_by` | `BIGINT` | User ID des Änderers |
| `changed_at` | `DATETIME` | Zeitpunkt der Änderung |
| `note` | `TEXT` | Optional: Notiz zur Änderung |

### Log-Trigger
- Automatisch bei jeder Statusänderung eines Events
- Speichert User, Zeitpunkt, alten und neuen Status
- Optional: Notiz zur Begründung der Änderung

---

## API-Struktur (für N8N Integration)

### REST API Endpoints

#### Events
- `GET /wp-json/tmgmt/v1/events` - Liste aller Events
- `GET /wp-json/tmgmt/v1/events/{id}` - Einzelnes Event
- `POST /wp-json/tmgmt/v1/events` - Neues Event erstellen
- `PUT /wp-json/tmgmt/v1/events/{id}` - Event aktualisieren
- `DELETE /wp-json/tmgmt/v1/events/{id}` - Event löschen

#### Status
- `GET /wp-json/tmgmt/v1/statuses` - Liste aller Status-Optionen
- `POST /wp-json/tmgmt/v1/statuses` - Neuen Status erstellen
- `PUT /wp-json/tmgmt/v1/statuses/{id}` - Status aktualisieren
- `DELETE /wp-json/tmgmt/v1/statuses/{id}` - Status löschen

#### Status Log
- `GET /wp-json/tmgmt/v1/events/{id}/status-log` - Status-Historie eines Events

### Authentifizierung
- Application Passwords (WordPress Standard)
- API-Key Authentifizierung (optional für N8N)

---

## Webhook-Konfiguration

### Webhook-Events
1. `event.created` - Neues Event erstellt
2. `event.updated` - Event aktualisiert
3. `event.status_changed` - Status geändert
4. `event.deleted` - Event gelöscht

### Webhook-Datenstruktur
```json
{
  "event": "event.status_changed",
  "timestamp": "2025-11-21T10:30:00Z",
  "data": {
    "event_id": 123,
    "event_title": "Auftritt Stadtfest",
    "old_status": "in-planung",
    "new_status": "bestaetigt",
    "changed_by": "admin@example.com"
  }
}
```

### Webhook-Verwaltung
- Admin-Seite zur Konfiguration von Webhook-URLs
- Auswahl welche Events getriggert werden sollen
- Test-Funktion für Webhooks
