# Integration Placeholders

In den Integrationen können folgende Platzhalter verwendet werden. Diese werden zur Laufzeit durch die entsprechenden Werte ersetzt.

## Hinweis zum Datumsformat
Alle Platzhalter für Datumswerte (z.B. `event_date`, `invoice_date`, `service_date`, `due_date`, `inquiry_date`) werden immer im Format `YYYY-MM-DD` (z. B. `2025-11-30`) ausgegeben – unabhängig von den WordPress-Datums-Einstellungen. Dieses Format entspricht der gängigen API-Konvention.

## Globale Platzhalter
- `{{api_token}}` – API-Key aus den Integrationseinstellungen
- `{{integration_slug}}` – Slug der Integration (z.B. `easyverein`)
- `{{site_url}}` – Basis-URL der WordPress-Seite
- `{{current_user_id}}` – ID des aktuellen Benutzers
- `{{current_user_email}}` – E-Mail des aktuellen Benutzers

## Event Platzhalter
- `{{event_id}}` – ID des Events
- `{{event_title}}` – Titel des Events
- `{{event_date}}` – Datum des Events
- `{{event_start_time}}` – Startzeit des Events
- `{{event_arrival_time}}` – Anreisezeit
- `{{event_departure_time}}` – Abreisezeit
- `{{event_status}}` – Status des Events
- `{{venue_name}}` – Name des Veranstaltungsorts
- `{{venue_street}}` – Straße des Veranstaltungsorts
- `{{venue_number}}` – Hausnummer des Veranstaltungsorts
- `{{venue_zip}}` – PLZ des Veranstaltungsorts
- `{{venue_city}}` – Ort des Veranstaltungsorts
- `{{venue_country}}` – Land des Veranstaltungsorts
- `{{geo_lat}}` – Latitude
- `{{geo_lng}}` – Longitude
- `{{arrival_notes}}` – Hinweise zur Anreise
- `{{inquiry_date}}` – Anfrage-Datum
- `{{fee}}` – Vereinbarte Gage
- `{{deposit}}` – Anzahlung

## Kontakt Platzhalter
- `{{contact_id}}` – ID des Kontakt-CPT
- `{{contact_salutation}}` – Anrede
- `{{contact_firstname}}` – Vorname
- `{{contact_lastname}}` – Nachname
- `{{contact_company}}` – Firma/Verein
- `{{contact_street}}` – Straße
- `{{contact_number}}` – Hausnummer
- `{{contact_zip}}` – PLZ
- `{{contact_city}}` – Ort
- `{{contact_country}}` – Land
- `{{contact_email_contract}}` – E-Mail (Vertrag)
- `{{contact_phone_contract}}` – Telefon (Vertrag)
- `{{contact_email_tech}}` – E-Mail (Technik)
- `{{contact_phone_tech}}` – Telefon (Technik)
- `{{contact_email_program}}` – E-Mail (Programm)
- `{{contact_phone_program}}` – Telefon (Programm)

## Rechnungs-Platzhalter
- `{{invoice_id}}` – ID der Rechnung
- `{{invoice_type}}` – Rechnungstyp
- `{{invoice_number}}` – Rechnungsnummer
- `{{invoice_ref_number}}` – Referenznummer
- `{{invoice_date}}` – Rechnungsdatum
- `{{invoice_service_date}}` – Leistungsdatum
- `{{invoice_recipient}}` – Rechnungsempfänger
- `{{invoice_due_date}}` – Zahlungsziel
- `{{invoice_intro_text}}` – Anschreibentext
- `{{invoice_closing_text}}` – Schlusstext
- `{{invoice_payment_info}}` – Zahlungsinformationen
- `{{invoice_accounting_id}}` – Buchhaltungs-ID
- `{{invoice_status}}` – Status der Rechnung
- `{{invoice_pdf_url}}` – PDF-URL
- `{{invoice_items}}` – Rechnungspositionen (JSON)
- `{{invoice_total}}` – Gesamtbetrag der Rechnung

## Service-Platzhalter (wenn verknüpft)
- `{{service_id}}` – ID des Service
- `{{service_type}}` – Typ des Service
- `{{service_price}}` – Preis
- `{{service_price_unit}}` – Einheit
- `{{service_vat_rate}}` – MwSt.-Satz

## Weitere Platzhalter
- `{{title}}` – Event-Titel
- `{{description}}` – Event-Beschreibung
- `{{payment_method}}` – Zahlungsart

## Beispiel für die Verwendung
```json
{
  "Authorization": "Bearer {{api_token}}",
  "body": {
    "contact": "{{contact_id}}",
    "event": "{{event_id}}",
    "amount": "{{invoice_total}}"
  }
}
```

## Hinweise
- Platzhalter können je nach Integration und Kontext variieren.
- Eigene Platzhalter können über die Integration-Manager-Logik ergänzt werden.
- Nicht verwendete Platzhalter werden ignoriert oder leer ersetzt.
