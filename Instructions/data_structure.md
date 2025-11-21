# Daten Struktur

## Custom Post Type - "Gigs"

### Funktionsberschreibung
Gigs enthält alle Informationen zu einem Auftritt. Ein Auftritt besteht aus Daten zu den Ansprechpartner, der Veranstaltung selber und der Änderungshistorie des Status und die Protokollierung der Kommunikation.


### Properties

#### Gruppe Veranstaltungsdaten
- Datum der Veranstaltung : Datum
- Geplante Auftrittstzeit : Uhrzeit
- Späteste Anreisezeit: Uhrzeit
- Späteste Abreisezeit: Uhrzeit
- Adresse Veranstaltungsort: Adresse
- Geodaten Veranstaltungsort: Geolocation
- Hinweise Anreise / Bus: Text Area

#### Gruppe Kontaktdaten
- Anrede : Text
- Vorname: Text
- Nachname: Text
- Firma / Verein: Text
- Postadresse (Straße, Hausnummer, PLZ, Ort, Land)
- Kontakt-E-Mail Vertrag: E-Mail-Adresse
- Kontakt-Telefon Vertrag: Telefon-Nummer
- Kontakt-E-Mail Technik: E-Mail-Adresse
- Kontakt-Telefon Technik: Telefon-Mummer
- Kontakt-E-Mail Programm: E-Mail-Adresse
- Kontakt-Telefon Programm: Telefon-Nummer

#### Gruppe Anfragedaten
- Anfrage vom: Datum / Uhrzeit
- Status: Auswahlfeld aus Datentyp Status

### Gruppe Vertragsdaten
- Vereinbarte Gage: Währung in Euro
- Anzahlung: Währung in Euro
- Unterzeichneter Vertrag: Upload-Datei

### Gruppe - Kommunikations- und Aktions-Log
- Einträge vom Datentyp mgmt-logs



## Custom Post Type - Management Logs (mgmt-logs)

### Funktionsbeschreibung
Ein Logfile zu alle Kommunikationen und Aktionen zu einem Gig. Es können sowohl manuell erfasste Daten sein (z.B. Gesprächsprotokoll) oder Log-Einträge aus Aktionen wie Status-Wechsel oder Webhook-Aufrufe sein.

### Properties
- Gig (Verknüpfung zu einem Eintrag aus dem CPT Gigs)
- Zeitstempel (Datum und Uhrzeit)
- Benutzer (Verknüpfung zu dem User der eine Aktion ausgelöst oder Daten verändert hat)
- Typ (z.B. Statuswechsel, Webhook Aufruf, Gesprächsprotokoll)






