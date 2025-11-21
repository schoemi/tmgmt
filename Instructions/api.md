# Töns Management REST API Dokumentation

Die Töns Management API ermöglicht den externen Zugriff auf Events und Logbücher, um Integrationen mit Tools wie n8n, Zapier oder anderen Systemen zu realisieren.

## Basis URL

Alle Endpunkte befinden sich unter folgendem Namespace:

```
/wp-json/tmgmt/v1/
```

Beispiel für eine lokale Installation: `http://localhost:8885/wp-json/tmgmt/v1/`

## Authentifizierung

Die API nutzt die Standard-WordPress-Authentifizierung. Für externe Tools (wie n8n) wird die Verwendung von **Application Passwords** empfohlen.

1.  Gehen Sie im WordPress-Backend zu **Benutzer > Profil**.
2.  Scrollen Sie zu "Anwendungspasswörter".
3.  Vergeben Sie einen Namen (z.B. "n8n") und klicken Sie auf "Neues Anwendungspasswort hinzufügen".
4.  Nutzen Sie dieses Passwort zusammen mit Ihrem Benutzernamen für die **Basic Auth** Authentifizierung.

---

## Endpunkte

### 1. Event aktualisieren

Aktualisiert die Daten eines bestehenden Events.

*   **URL:** `/events/{id}`
*   **Methode:** `POST`
*   **URL Parameter:**
    *   `id` (int): Die ID des Events.

#### Body Parameter (JSON)

Alle Parameter sind optional. Es werden nur die übergebenen Felder aktualisiert.

| Parameter | Typ | Beschreibung |
| :--- | :--- | :--- |
| `title` | string | Titel des Events |
| `content` | string | Beschreibung / Inhalt |
| `date` | string (YYYY-MM-DD) | Datum der Veranstaltung |
| `start_time` | string (HH:MM) | Geplante Auftrittszeit |
| `arrival_time` | string (HH:MM) | Späteste Anreisezeit |
| `departure_time` | string (HH:MM) | Späteste Abreisezeit |
| `venue_street` | string | Straße des Veranstaltungsorts |
| `venue_number` | string | Hausnummer |
| `venue_zip` | string | Postleitzahl |
| `venue_city` | string | Ort |
| `venue_country` | string | Land |
| `geo_lat` | string | Breitengrad |
| `geo_lng` | string | Längengrad |
| `arrival_notes` | string | Hinweise zur Anreise |
| `status` | string | Slug des neuen Status (z.B. `confirmed`, `cancelled`). **Löst Log-Eintrag aus.** |
| `contact_salutation` | string | Anrede Kontaktperson |
| `contact_firstname` | string | Vorname Kontaktperson |
| `contact_lastname` | string | Nachname Kontaktperson |
| `contact_company` | string | Firma / Verein |
| `contact_email_contract` | string | E-Mail (Vertrag) |
| `contact_phone_contract` | string | Telefon (Vertrag) |
| `contact_name_tech` | string | Name (Technik) |
| `contact_email_tech` | string | E-Mail (Technik) |
| `contact_phone_tech` | string | Telefon (Technik) |
| `contact_name_program` | string | Name (Programm) |
| `contact_email_program` | string | E-Mail (Programm) |
| `contact_phone_program` | string | Telefon (Programm) |
| `fee` | string | Gage |
| `deposit` | string | Anzahlung |

#### Beispiel Request (n8n / cURL)

```json
POST /wp-json/tmgmt/v1/events/123
Content-Type: application/json

{
  "venue_city": "Berlin",
  "venue_street": "Alexanderplatz",
  "status": "confirmed",
  "contact_email_tech": "tech@example.com"
}
```

#### Response

```json
{
  "success": true,
  "message": "Event aktualisiert",
  "id": "123"
}
```

---

### 2. Log-Eintrag hinzufügen

Fügt dem Logbuch eines Events einen neuen Eintrag hinzu.

*   **URL:** `/events/{id}/log`
*   **Methode:** `POST`
*   **URL Parameter:**
    *   `id` (int): Die ID des Events.

#### Body Parameter (JSON)

| Parameter | Typ | Erforderlich | Beschreibung |
| :--- | :--- | :--- | :--- |
| `message` | string | Ja | Die Nachricht für das Logbuch. |
| `type` | string | Nein | Typ des Eintrags (Standard: `api_info`). |

#### Beispiel Request

```json
POST /wp-json/tmgmt/v1/events/123/log
Content-Type: application/json

{
  "message": "Vertrag wurde extern via DocuSign unterschrieben.",
  "type": "contract_signed"
}
```

#### Response

```json
{
  "success": true,
  "message": "Log Eintrag erstellt"
}
```
