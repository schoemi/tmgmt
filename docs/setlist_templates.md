# Dokumentation: Setlist PDF Templates

Das TMGMT Plugin ermöglicht die Erstellung individueller PDF-Layouts für Setlisten. Templates sind PHP-Dateien, die HTML und CSS enthalten, welches anschließend von der mPDF-Bibliothek in ein PDF umgewandelt wird.

## Speicherort

Templates müssen im folgenden Ordner abgelegt werden:
`/wp-content/plugins/tmgmt/templates/setlist/`

Die Dateien sollten die Endung `.php` haben (z.B. `mein-layout.php`).

## Verfügbare Daten

In den Template-Dateien stehen zwei Haupt-Arrays zur Verfügung: `$data` und `$org_data`.

### 1. `$data` (Event & Setlist)

Enthält alle Informationen zum aktuellen Auftritt und der Setliste.

| Schlüssel | Beschreibung | Beispiel |
| :--- | :--- | :--- |
| `event_id` | ID des Events | `123` |
| `event_title` | Titel des Events | `Stadtfest Kleve` |
| `event_date` | Datum des Events | `24.12.2025` |
| `location` | Name der Location | `Stadthalle` |
| `setlist` | Array der Songs (siehe unten) | `[...]` |

#### Struktur eines Songs im `setlist` Array:

Jeder Eintrag im `setlist` Array ist ein assoziatives Array mit folgenden Schlüsseln:

| Schlüssel | Beschreibung |
| :--- | :--- |
| `title` | Titel des Songs |
| `artist` | Interpret / Original-Künstler |
| `key` | Tonart (z.B. "Am") |
| `bpm` | Tempo (BPM) |
| `duration` | Dauer in Sekunden (Integer) |

### 2. `$org_data` (Organisation)

Enthält die in den Einstellungen hinterlegten Stammdaten der Band/Organisation.

| Schlüssel | Beschreibung |
| :--- | :--- |
| `name` | Name der Organisation |
| `contact` | Ansprechpartner |
| `street` | Straße |
| `number` | Hausnummer |
| `zip` | PLZ |
| `city` | Stadt |
| `email` | E-Mail Adresse |
| `phone` | Telefonnummer |
| `logo_url` | URL zum Logo (falls vorhanden) |

## Beispiel Template

Hier ist ein einfaches Beispiel für eine `simple.php` Template-Datei:

```php
<html>
<head>
    <style>
        body { font-family: sans-serif; }
        .header { text-align: center; margin-bottom: 30px; }
        .logo { max-height: 80px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; border-bottom: 2px solid #000; padding: 5px; }
        td { border-bottom: 1px solid #ccc; padding: 5px; }
        .num { width: 30px; color: #666; }
        .meta { font-size: 0.8em; color: #666; }
    </style>
</head>
<body>

    <div class="header">
        <?php if (!empty($org_data['logo_url'])): ?>
            <img src="<?php echo $org_data['logo_url']; ?>" class="logo"><br>
        <?php endif; ?>
        <h1><?php echo esc_html($data['event_title']); ?></h1>
        <p><?php echo esc_html($data['location']); ?> | <?php echo esc_html($data['event_date']); ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Titel</th>
                <th>Artist</th>
                <th>Key</th>
                <th>BPM</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $count = 1;
            foreach ($data['setlist'] as $song): 
            ?>
            <tr>
                <td class="num"><?php echo $count++; ?></td>
                <td><strong><?php echo esc_html($song['title']); ?></strong></td>
                <td><?php echo esc_html($song['artist']); ?></td>
                <td><?php echo esc_html($song['key']); ?></td>
                <td><?php echo esc_html($song['bpm']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="margin-top: 50px; font-size: 0.8em; text-align: center; color: #999;">
        <?php echo esc_html($org_data['name']); ?> - <?php echo esc_html($org_data['email']); ?>
    </div>

</body>
</html>
```

## Tipps

*   **CSS:** mPDF unterstützt viele CSS-Eigenschaften, aber nicht alle modernen Features (wie Flexbox oder Grid). Verwenden Sie Tabellen für Layouts.
*   **Bilder:** Bilder müssen über absolute Pfade oder URLs eingebunden werden. `$org_data['logo_url']` liefert bereits eine URL.
*   **Seitenumbrüche:** Sie können `<pagebreak />` verwenden, um einen manuellen Seitenumbruch zu erzwingen.
