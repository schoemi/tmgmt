# Platzhalter-Referenz

Alle verfügbaren Platzhalter für E-Mail-Vorlagen, Vertragsvorlagen und andere Texte.
Platzhalter werden durch `TMGMT_Placeholder_Parser::parse()` ersetzt.

## Event

| Platzhalter | Beschreibung | Quelle |
|---|---|---|
| `[event_id]` | Event ID | `_tmgmt_event_id` (Fallback: WP Post-ID) |
| `[event_title]` | Event Titel | `post_title` |
| `[event_content]` | Event Beschreibung | `post_content` |
| `[event_link]` | Event Link | `get_permalink()` |
| `[event_date]` | Datum | `_tmgmt_event_date` |
| `[event_start_time]` | Startzeit | `_tmgmt_event_start_time` |
| `[event_arrival_time]` | Ankunftszeit | `_tmgmt_event_arrival_time` |
| `[event_departure_time]` | Abfahrtszeit | `_tmgmt_event_departure_time` |

## Location (Venue)

| Platzhalter | Beschreibung | Quelle |
|---|---|---|
| `[venue_name]` | Location Name | `post_title` des verknüpften Location-Posts |
| `[venue_street]` | Straße | `_tmgmt_location_street` |
| `[venue_number]` | Hausnummer | `_tmgmt_location_number` |
| `[venue_zip]` | PLZ | `_tmgmt_location_zip` |
| `[venue_city]` | Stadt | `_tmgmt_location_city` |
| `[venue_country]` | Land | `_tmgmt_location_country` |
| `[arrival_notes]` | Anreise Notizen | `_tmgmt_location_notes` |

## Kontakt (Veranstalter)

| Platzhalter | Beschreibung | Quelle |
|---|---|---|
| `[contact_salutation]` | Anrede | `_tmgmt_contact_salutation` |
| `[contact_firstname]` | Vorname | `_tmgmt_contact_firstname` |
| `[contact_lastname]` | Nachname | `_tmgmt_contact_lastname` |
| `[contact_company]` | Firma | `_tmgmt_contact_company` |
| `[contact_street]` | Straße | `_tmgmt_contact_street` |
| `[contact_number]` | Hausnummer | `_tmgmt_contact_number` |
| `[contact_zip]` | PLZ | `_tmgmt_contact_zip` |
| `[contact_city]` | Stadt | `_tmgmt_contact_city` |
| `[contact_country]` | Land | `_tmgmt_contact_country` |
| `[contact_email]` | E-Mail | `_tmgmt_contact_email` |
| `[contact_phone]` | Telefon | `_tmgmt_contact_phone` |

## Kommunikation

| Platzhalter | Beschreibung | Quelle |
|---|---|---|
| `[contact_email_contract]` | E-Mail (Vertrag) | `_tmgmt_contact_email_contract` |
| `[contact_phone_contract]` | Telefon (Vertrag) | `_tmgmt_contact_phone_contract` |
| `[contact_name_tech]` | Name (Technik) | `_tmgmt_contact_name_tech` |
| `[contact_email_tech]` | E-Mail (Technik) | `_tmgmt_contact_email_tech` |
| `[contact_phone_tech]` | Telefon (Technik) | `_tmgmt_contact_phone_tech` |
| `[contact_name_program]` | Name (Programm) | `_tmgmt_contact_name_program` |
| `[contact_email_program]` | E-Mail (Programm) | `_tmgmt_contact_email_program` |
| `[contact_phone_program]` | Telefon (Programm) | `_tmgmt_contact_phone_program` |

## Vertrag & Finanzen

| Platzhalter | Beschreibung | Quelle |
|---|---|---|
| `[fee]` | Gage | `_tmgmt_fee` |
| `[deposit]` | Anzahlung | `_tmgmt_deposit` |
| `[inquiry_date]` | Anfragedatum | `_tmgmt_inquiry_date` |

## Links

| Platzhalter | Beschreibung | Hinweis |
|---|---|---|
| `[confirmation_link]` | Bestätigungs-Link | Letzter offener `pending`-Eintrag aus `wp_tmgmt_confirmations`; leer wenn keiner vorhanden |
| `[customer_dashboard_link]` | Kunden Dashboard Link | Gültiges Token aus `wp_tmgmt_customer_access`; `[[MISSING_TOKEN]]` wenn kein Token existiert |
