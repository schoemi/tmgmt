# TMGMT Konfigurations-Handbuch

Dieses Handbuch führt durch die Konfiguration des **Töns Management (TMGMT)** Plugins. Alle Einstellungen finden Sie im WordPress-Backend unter dem Menüpunkt **Einstellungen** (wenn Sie sich im Event-Bereich befinden) oder über die entsprechenden Untermenüs.

## 1. Allgemeine Einstellungen
*Pfad: Einstellungen > Allgemeine Einstellungen*

Hier konfigurieren Sie das grundlegende Verhalten des Plugins.

*   **Admin Leiste:** Sie können die WordPress Admin-Leiste für Nicht-Administratoren auf Desktop- und Mobilgeräten ausblenden. Dies sorgt für ein saubereres "App-Feeling" im Frontend.

## 2. Organisation
*Pfad: Einstellungen > Organisation*

Diese Daten werden für den PDF-Export und E-Mail-Signaturen verwendet.

*   **Stammdaten:** Name, Adresse, Kontakt, Steuernummern.
*   **Logo:** Laden Sie hier Ihr Organisations-Logo hoch. Es wird auf PDF-Dokumenten (z.B. Setlisten, Verträgen) platziert.

## 3. Routenplanung
*Pfad: Einstellungen > Routenplanung*

Essentiell für die automatische Berechnung von Fahrzeiten und Tagesplänen.

### API Konfiguration
Das Plugin benötigt einen API-Key für die Routenberechnung.
*   **OpenRouteService (Empfohlen):** Kostenlos bis zu einem gewissen Limit. [Key erstellen](https://openrouteservice.org/)
*   **HERE Maps:** Alternative für professionelle Nutzung. [Key erstellen](https://developer.here.com/)

### Standard-Startpunkt (Proberaum)
Definieren Sie hier den Startpunkt für alle Touren (z.B. Proberaum oder Lager).
*   **Adresse:** Geben Sie die Adresse ein.
*   **Geodaten:** Klicken Sie auf "Geodaten aus Adresse ermitteln", um die Koordinaten automatisch zu setzen.

### Pufferzeiten & Logistik
Hier definieren Sie die Sicherheitsmargen für Ihre Zeitpläne:
*   **Pufferzeit Anreise:** Wird zur reinen Fahrzeit addiert (z.B. 30 Min für Stau/Parken).
*   **Minimale Pufferzeit:** Absolute Untergrenze. Wird diese unterschritten, markiert das System die Tour als kritisch.
*   **Ladezeit:** Zeit, die der Bus VOR der Abfahrt am Startort sein muss.
*   **Busfaktor:** Multiplikator für Fahrzeiten (z.B. `1.2` = Bus braucht 20% länger als PKW).

## 4. Kunden Dashboard & Token Request
*Pfad: Einstellungen > Kunden Dashboard*

Das Kunden-Dashboard ermöglicht Veranstaltern, ihre Event-Daten einzusehen und zu bearbeiten.

### Zugriffs-Token Anfrage (Lost Password Flow)
Wenn ein Veranstalter seinen Zugangslink verloren hat, kann er ihn über ein Formular (`[tmgmt_token_request]`) neu anfordern.
*   **E-Mail bei Erfolg:** Wählen Sie die Vorlage, die gesendet wird, wenn die Daten (Event-ID + E-Mail) korrekt sind. **Wichtig:** Die Vorlage muss den Platzhalter `[customer_dashboard_link]` enthalten.
*   **E-Mail bei Misserfolg:** Wird gesendet, wenn keine Übereinstimmung gefunden wurde (Sicherheitsmaßnahme gegen Datenspionage).

### Feld-Berechtigungen
Steuern Sie detailliert, welche Daten der Veranstalter sehen oder ändern darf.
*   **Lesen:** Das Feld wird im Dashboard angezeigt.
*   **Schreiben:** Der Veranstalter kann das Feld bearbeiten (z.B. "Ansprechpartner Technik").

## 5. Frontend Layout
*Pfad: Einstellungen > Frontend Layout*

Hier können Sie das Aussehen des internen Event-Modals (Kanban-Board Detailansicht) anpassen.
*   **Reihenfolge:** Verschieben Sie Sektionen (z.B. "Vertragsdaten", "Notizen") nach oben oder unten.
*   **Sichtbarkeit:** Definieren Sie, welche Sektionen standardmäßig eingeklappt sind.
*   **Farben:** Geben Sie wichtigen Sektionen (z.B. "Status & Aktionen") eine eigene Hintergrundfarbe zur besseren Orientierung.
*   **Mobile vs. Desktop:** Sie können unterschiedliche Layouts für Desktop und Smartphone definieren.

## 6. Berechtigungen (Rollen)
*Pfad: Einstellungen > Berechtigungen*

Das Plugin bringt ein feingranulares Rechtesystem mit. Sie können für jede WordPress-Rolle (z.B. Administrator, Editor, Musiker) festlegen, was sie darf.

*   **Event Management:** Events sehen, erstellen, bearbeiten, löschen.
*   **Finanzen:** Gagen und Verträge einsehen (wichtig für Musiker, die diese Daten nicht sehen sollen).
*   **Touren:** Tourenpläne sehen oder verwalten.
*   **Einstellungen:** Zugriff auf die Plugin-Konfiguration.

## 7. PDF Export
*Pfad: Einstellungen > PDF Export*

*   **Setlist Template:** Wählen Sie hier die Vorlage für den Setlist-Export aus. Eigene Templates können im Ordner `templates/setlist/` abgelegt werden.

## 8. Live Tracking
*Pfad: Einstellungen > Live Tracking*

*   **Test-Modus:** Aktivieren Sie diesen Modus, um GPS-Daten zu simulieren. Nützlich zum Testen der "Live View" ohne sich tatsächlich zu bewegen.

---

## Einrichtung für Entwickler / Admins

### Shortcodes
Platzieren Sie diese Shortcodes auf Ihren geschützten Seiten:

*   `[tmgmt_dashboard]` - Das interne Kanban-Board für das Management.
*   `[tmgmt_tour_overview]` - Übersicht aller Tourenpläne.
*   `[tmgmt_token_request]` - Öffentliches Formular für Veranstalter zur Anforderung des Zugangslinks.

### Cronjobs
Das Plugin nutzt WordPress Cron für Hintergrundaufgaben. Stellen Sie sicher, dass WP-Cron auf Ihrem Server korrekt läuft.
