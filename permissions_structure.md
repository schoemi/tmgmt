# TMGMT Berechtigungsstruktur

Dieses Dokument beschreibt die sinnvoll trennbaren Bereiche und Funktionen des Plugins, die durch ein detailliertes Rechtemanagement (Capabilities) gesteuert werden können.

## 1. Dashboard & Allgemeines
Bereiche, die den allgemeinen Zugriff auf das Plugin betreffen.

*   **Dashboard ansehen** (`tmgmt_view_dashboard`)
    *   Zugriff auf die Haupt-Dashboard-Seite mit KPIs und Übersichten.
*   **Einstellungen verwalten** (`tmgmt_manage_settings`)
    *   Zugriff auf die Plugin-Einstellungen (Routenplanung, Frontend-Layout, API-Keys).

## 2. Event-Management (Gigs)
Steuerung des Zugriffs auf die einzelnen Termine/Gigs. Hierbei sollte der Custom Post Type `event` eigene Capabilities erhalten, statt die Standard-Post-Rechte zu nutzen.

*   **Events ansehen** (`read_event`)
    *   Darf Events in Listen sehen (z.B. Terminliste).
*   **Events erstellen/bearbeiten** (`edit_events`, `edit_others_events`)
    *   Darf neue Gigs anlegen und bestehende bearbeiten.
*   **Events veröffentlichen** (`publish_events`)
    *   Darf Gigs live schalten (Status "Veröffentlicht").
*   **Events löschen** (`delete_events`, `delete_others_events`)
    *   Darf Gigs entfernen.
*   **Status direkt setzen** (`tmgmt_set_event_status_directly`)
    *   Darf den Status eines Events manuell im Dropdown ändern.
    *   *Ohne dieses Recht:* User müssen definierte Workflow-Aktionen (Transitionen) nutzen, um den Status zu ändern.

## 3. Touren-Management (Tourenpläne)
Steuerung der logistischen Planung und Touren-Erstellung. Auch hier sollte der CPT `tmgmt_tour` eigene Capabilities nutzen.

*   **Touren-Übersicht ansehen** (`tmgmt_view_tour_overview`)
    *   Zugriff auf den Reiter "Touren-Übersicht".
*   **Touren erstellen/bearbeiten** (`edit_tours`, `edit_others_tours`)
    *   Darf Tourenpläne anlegen und bearbeiten.
*   **Touren berechnen** (`tmgmt_calculate_tour`)
    *   Darf die automatische Routen- und Zeitberechnung anstoßen.
*   **Touren löschen** (`delete_tours`)
    *   Darf Tourenpläne entfernen.

## 4. Terminliste
*   **Terminliste ansehen** (`tmgmt_view_appointment_list`)
    *   Zugriff auf die schreibgeschützte, sortierte Terminliste.

## 5. Status-Management
Verwaltung der Status-Definitionen für Events.

*   **Status verwalten** (`tmgmt_manage_statuses`)
    *   Darf Status-Definitionen (Farben, Bezeichnungen) erstellen, bearbeiten oder löschen.

## 6. Kommunikation & Vorlagen
Verwaltung von E-Mail-Vorlagen und Kommunikation.

*   **Vorlagen verwalten** (`tmgmt_manage_email_templates`)
    *   Darf E-Mail-Templates erstellen und bearbeiten.
*   **E-Mails senden** (`tmgmt_send_emails`)
    *   Darf Mails über das System versenden (falls implementiert).

## 7. Logs & Historie
*   **Logs ansehen** (`tmgmt_view_logs`)
    *   Zugriff auf System-Logs und Änderungshistorie.

---

## Empfohlene Rollen-Zuweisung (Beispiel)

### Administrator
*   Alle Rechte (`manage_options` + alle `tmgmt_*` Capabilities).

### Tour-Manager
*   `tmgmt_view_dashboard`
*   `read_event`, `edit_events`, `publish_events`
*   `tmgmt_view_tour_overview`
*   `edit_tours`, `publish_tours`, `tmgmt_calculate_tour`
*   `tmgmt_view_appointment_list`

### Booker / Event-Planer
*   `tmgmt_view_dashboard`
*   `read_event`, `edit_events`, `publish_events`
*   `tmgmt_view_appointment_list`
*   *Kein Zugriff auf Touren-Details oder Einstellungen.*

### Viewer (Musiker/Crew)
*   `tmgmt_view_dashboard`
*   `read_event` (nur eigene oder alle, je nach Bedarf)
*   `tmgmt_view_appointment_list`
