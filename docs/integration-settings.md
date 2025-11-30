# Integrations Einstellungen

## Zweck
Dieses Menü ermöglicht das sichere Hinterlegen von API Keys für alle angebundenen Integrationen (z.B. easyVerein). Die API Keys werden in der WordPress Options-Tabelle gespeichert und nicht im Code oder in JSON-Konfigurationen hinterlegt.

## Nutzung
- Menü unter Einstellungen > Integrationen
- API Key für easyVerein und weitere Integrationen eintragen
- Die Integration-JSON-Konfigurationen nutzen den Platzhalter `{{API_KEY}}`, der beim Laden automatisch durch den hinterlegten Key ersetzt wird

## Erweiterung
- Weitere Integrationen können einfach ergänzt werden, indem neue Felder im Settings-Menü und Platzhalter im JSON verwendet werden.

## Sicherheit
- Die API Keys sind nur für Administratoren sichtbar und werden nicht im Code oder in der Datenbank als Klartext angezeigt.

## Beispiel
```json
{
  "base_url": "https://api.easyverein.com/v2",
  "authentication": {
    "type": "bearer",
    "token": "{{API_KEY}}"
  },
  ...
}
```

## Hinweise
- Änderungen am API Key werden sofort für alle Integrationsaufrufe übernommen.
- Die Integration Manager Klasse ersetzt den Platzhalter automatisch beim Laden der Konfiguration.