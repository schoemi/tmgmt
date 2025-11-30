# TMGMT Integrationen – JSON-Konfiguration

## Grundstruktur

Jede Integration wird als eigene JSON-Datei im Verzeichnis `includes/integrations/` abgelegt.

```json
{
  "name": "Integration Name",
  "description": "Kurze Beschreibung",
  "base_url": "https://api.zielsystem.de/v1",
  "actions": [ ... ],
  "mapping": { ... }
}
```

## Aktionen (`actions`)

Das Array `actions` enthält alle ausführbaren API-Aktionen. Jede Aktion benötigt:

- `id`: Eindeutige Kennung (z.B. "create_invoice")
- `name`: Anzeigename im Backend
- `method`: HTTP-Methode (`POST`, `GET`, etc.)
- `endpoint`: API-Endpunkt (relativ zu `base_url`)
- `headers`: HTTP-Header (z.B. Authentifizierung)
- `body`: Request-Body mit Platzhaltern (`{{...}}`)
- `response_mapping`: Zuordnung von API-Response-Feldern zu lokalen Feldern

**Beispiel:**
```json
{
  "id": "create_invoice",
  "name": "Rechnung erstellen",
  "method": "POST",
  "endpoint": "/invoice",
  "headers": {
    "Authorization": "Bearer {{api_token}}",
    "Content-Type": "application/json"
  },
  "body": {
    "relatedAddress": "{{contact.easyverein_id}}",
    "invoiceItems": [
      {
        "title": "{{item.title}}",
        "amount": "{{item.amount}}",
        "unitPrice": "{{item.unit_price}}"
      }
    ],
    "customPaymentMethod": "{{payment_method}}",
    "isReceipt": false,
    "closingDescription": "{{description}}"
  },
  "response_mapping": {
    "invoice_id": "id",
    "pdf_url": "document_url"
  }
}
```

## Platzhalter

Platzhalter im Body werden zur Laufzeit mit Daten aus WordPress (z.B. Event, Kontakt, Rechnung) ersetzt.  
Beispiel: `{{contact.easyverein_id}}`, `{{item.title}}`

## Mapping

Das Feld `mapping` beschreibt, wie lokale Felder auf die API-Felder abgebildet werden.  
Beispiel:
```json
"mapping": {
  "item.title": "Rechnungsposition Titel",
  "item.amount": "Menge",
  "item.unit_price": "Einzelpreis"
}
```

## Authentifizierung

Trage den API-Token als Platzhalter (`{{api_token}}`) oder direkt als Wert im Header ein.

## Erweiterung

Weitere Aktionen können einfach als neues Objekt im `actions`-Array ergänzt werden.

---

**Hinweis:**
- Kommentare sind im JSON nicht erlaubt.
- Die Datei muss gültiges JSON sein.
- Änderungen werden nach dem Speichern sofort im Backend angezeigt.

---

Damit kannst du beliebige Integrationen für externe APIs konfigurieren und im WP-Backend ausführen.
