Anforderungen für eine Live Tracking Ansicht

## Zielsetzung
Ich möchte jetzt eine Möglichkeit schaffen das es für die Tourenplanung eine Art Live Ansicht gibt. Ich möchte in dieser Ansicht sehen, ob ich im Zeitplan bin.
Die Wegpunkte und der Zeitplan ergeben sich aus der Tourenplanung.
Die Live Ansicht soll den Standort des Gerätes nutzen.

Minimalanforderung: In der Ansicht wird an Hand des Tourenplanes dargestellt wo im Tourenplan man sich aktuell befindet.

Optimale Umsetzung: Über den Standort des Gerätes wird ermittelt wo man gerade ist. Diese Information wird mit dem Tourenplan abgeglichen. Es wird ermittelt ob man vor, im oder hinter dem Zeitplan ist (Toleranz 5min - Einstellbar in Settings)

## Technische Anforderungen
- Die Routen (Abfolge der Wegpunkte) soll über einen Kartendienst abgerufen werden. Die Wegpunkte sollen in Wordpress temporär gespeichert werden.
- Optional: Server-Side Worker / Cron für Vorberechnungen (z. B. Precompute realistische Fahrtzeiten zwischen Knoten).
- Sparsame Nutzung von externen APIs

## Anforderungen für das Frontend
- Frontend (in WordPress)
- Single Page View (React / Vue oder Vanilla JS) eingebettet als Page Template oder Gutenberg Block.
- Map UI (Leaflet oder Maps JS SDK) + Live-Overlay: aktuelle Position, Route, Wegpunkte mit geplanten Zeiten, Statusindikatoren (pünktlich/verspätet).
- Geolocation API (browser) liefert Device-Standort in Echtzeit (watchPosition).

## Realtime / Aktualisierung
- Für einfache Lösung: Polling vom Client an WP REST Endpoint (geringer Ops-Aufwand).

## Berechnung (Client oder Server)
- Core-Logik kann clientseitig ausgeführt werden (bei mobilen Browsern): Vergleiche aktueller Standort+geschätzte Fahrzeit zur nächsten geplanten Zeit.
- Oder serverseitig (z. B. wenn Zugriff auf Routing Service vom Server erfolgt) — Server gibt bereits Einschätzung „erwartete Ankunftszeit (ETA)“ zurück.

## Frontend: Komponenten & Verhalten
- Komponenten (Single Page / Block):
- Map (Leaflet)
- Sidebar / Header mit:
  - Selected Route (Titel)
  - Nächster geplanter Wegpunkt + geplanter Zeit vs. berechnete ETA
  - Status-Indikator (🟢 pünktlich / 🟡 leicht verspätet / 🔴 deutlich verspätet)
  - Liste aller Wegpunkte (mit Icons: vorbei/noch offen)
- Controls:
  - „Route neu laden“ (force fetch polylines & drive times),
  - Toggle: Show full route / only next segment
  - Tracking On/Off (zugriff Geolocation)
  - Mobile optimiert: große Buttons, sticky footer mit aktueller Abweichung.


#### Frontend-Flow (Vereinfachte Logik):

Client lädt GET /route/{id} → zeigt Wegpunkte + geplante Zeiten.

Client fragt segment-route für (z. B.) nächsten 3 Segmente und cached Polylines.

Client aktiviert navigator.geolocation.watchPosition() → erhält position regelmäßig.

Bei jedem Positionsupdate:

a) Berechne Distanz vom aktuellen Punkt zum nächsten Wegpunkt (haversine).

b) Frage (falls nötig) Routing Service nach Fahrzeit von position → next_waypoint (oder nutze vor­gecachte segment-Fahrtzeit + verbleibender Strecke als Heuristik).

c) ETA = jetzt + Fahrzeit + verbleibende Aufenthaltszeiten vorheriger Punkte (falls relevant).

d) Vergleiche ETA mit planned_arrival → setze Status.

e) UI: Update marker, ETA, color, Hinweis “Verspätung +8 min — Route neu berechnen?”.

#### Algorithmus zur Einschätzung „im Zeitplan?“

Ziel: robust, wenig API-Calls, verlässlich.

A) Einfache Heuristik (Client) — geringster Aufwand:

Verwende die geplanten planned_drive_time_seconds für kommende Segmente.

Falls aktuelle Standort innerhalb des Segmentes liegt, berechne verbleibende Strecke (Haversine) und schätze verbleibende Zeit = planned_drive_time * (verbleibende_dist / segment_total_dist).

ETA = jetzt + verbleibende_time + Sum(geplante 'stop' Dauern bis Ziel).

Vergleich: ETA vs. planned_arrival → delta (in Minuten).

Schwellen: delta ≤ +5 min = pünktlich; 5–15 = verspätet; >15 = kritisch.

B) Genauere Methode (Routing Service / Distance Matrix):

Bei Positionsupdate: abruf Routing API mit origin = current position, destination = next_waypoint → Rückgabewert: live_fahrzeit.

ETA = now + live_fahrzeit + Sum(geplante_stop_durations).

Vorteile: berücksichtigt Verkehr & Routing-Änderungen. Nachteile: API-Kosten/Rate limits.

C) Hybrid (praktisch empfohlen):

Standard: nutze Heuristik lokal (kein API Call).

Nur wenn Heuristik ergibt delta > Schwellwert (z. B. > 5 min) oder vor kritischem Wegpunkt, rufe Routing API für Bestätigung.

#### Caching & Performance

Cache Routing Service Antworten serverseitig (transient API oder WP option mit TTL z. B. 10–60 min).

Clientseitig: store Polylines + segment durations in localStorage mit timestamp.

Polling interval adaptiv: wenn ruhig → 30s; bei Bewegung/nahe Ziel → 5–10s.

Minimale Netzwerk-Last: Heuristik → weniger API Calls.

#### Sicherheit & Datenschutz (DSGVO)

Geolocation: hole explizit Browser-Permission (navigator.geolocation). Zeige klaren Hinweis wofür Standort verwendet wird.

Wenn Standorte zum Server gesendet werden (tracking), benötigst du:

klare Einwilligung (opt-in), Zweckbindung, Löschfristen.

Möglichkeit für Nutzer, Tracking zu stoppen & ihre Daten löschen zu lassen.

Wenn personenbezogene Daten (z. B. Fahrer-IDs) gespeichert werden: DSGVO-konforme Speicherung, TLS überall.

Wenn du Telemetrie/Analytics brauchst: pseudonymisieren.

#### Offline / schlechte Verbindung

App sollte tolerant gegenüber Verbindungsabbrüchen sein:

Client berechnet lokal weiter (Heuristik).

Bei reconnect → synchronisiert (z. B. last_known_position).

Map Tiles: für mobile Nutzung evtl. verringern Tile-Zoom/Cache.

#### UI/UX Vorschläge (konkret)

Karte links, Statuspanel rechts (Desktop). Mobile: Karte oben, Panel unten sticky.

Farbgebung Status:

Grün: ETA ≤ planned_arrival + 5 min

Gelb: ETA 5–15 min später

Rot: > 15 min später

Zeige kleine Timeline: Waypoint1 (08:00 ✓), Waypoint2 (08:45 ⏳ ETA 08:56 +11m).

Button „Neu berechnen“ für Manuelle Recalc.

Toast Benachrichtigung wenn kritische Verspätung erreicht.

### Testmodus
Um die Funktion gut testen bzw. simulieren zu können wird eine Testfunktion benötigt.
Hierfür sollen die Uhrzeit und die Location über Tool im Backend definiert werden können (und der Testmodus aktiviert werden)
Im Idealfall kann die aktuelle Position über eine Leaflet Karte gesetzt werden. Für das schnelle Einstellen der Uhr kann eine ansprechende Visualisierung verwendet werden. Hilfreich wären auch Button um die Uhr mit einem Klick vor- oder zurück zu stellen.
Beispiel +5 min / + 15 min / + 60 min und natürlich auch -5 min / -15 min / - 60 min