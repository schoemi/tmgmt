# Erweiterte Integration: Sequenzierte API-Calls

## Beispiel-Konfiguration für mehrstufige Aktionen

```json
{
  "name": "easyVerein Rechnung Komplett",
  "description": "Erstellt eine Rechnung und fügt Positionen hinzu.",
  "base_url": "https://easyverein.com/api/v2.0",
  "actions": [
    {
      "id": "create_invoice_full",
      "name": "Rechnung komplett erstellen",
      "sequence": [
        {
          "method": "POST",
          "endpoint": "/invoice",
          "body": {
            "relatedAddress": "{{contact.easyverein_id}}"
          },
          "response_mapping": {
            "invoice_id": "id"
          }
        },
        {
          "method": "POST",
          "endpoint": "/invoice-items",
          "body": {
            "invoice_id": "{{step.0.invoice_id}}",
            "items": [
              {
                "title": "{{item.title}}",
                "amount": "{{item.amount}}",
                "unitPrice": "{{item.unit_price}}"
              }
            ]
          }
        }
      ]
    }
  ]
}
```

## Ablauf

- Die Aktion `create_invoice_full` besteht aus zwei Schritten:
  1. Rechnung anlegen (`/invoice`), Response liefert die Rechnungs-ID.
  2. Rechnungspositionen hinzufügen (`/invoice-items`), nutzt die ID aus Schritt 1.
- Platzhalter wie `{{step.0.invoice_id}}` greifen auf die Response des jeweiligen Schritts zu.

## Hinweise
- Die PHP-Logik muss die Sequenz ausführen und Response-Daten zwischen den Schritten weiterreichen.
- Fehler werden nach jedem Schritt geloggt und die Sequenz ggf. abgebrochen.
- Die Integration ist beliebig erweiterbar für weitere Schritte.
