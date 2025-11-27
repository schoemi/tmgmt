<?php
/**
 * Template for Tour Bulk Export (Bus Briefing)
 * 
 * Variables available:
 * $tours (array of tour data)
 * $org_data (array)
 */
?>
<html>
<head>
    <style>
        body { font-family: sans-serif; font-size: 10pt; }
        h1 { font-size: 16pt; margin-bottom: 10px; }
        h2 { font-size: 14pt; margin-bottom: 5px; border-bottom: 1px solid #ccc; padding-bottom: 5px; }
        .meta { font-size: 9pt; color: #555; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: top; }
        th { background-color: #f5f5f5; font-weight: bold; }
        tr { page-break-inside: avoid; } /* Prevent rows from breaking */
        .stop-type { font-weight: bold; font-size: 0.9em; text-transform: uppercase; color: #444; }
        .address { font-size: 1.1em; }
        .notes { font-style: italic; color: #666; margin-top: 5px; }
        .qr-code { text-align: center; width: 100px; }
    </style>
</head>
<body>

<!-- Define Footer -->
<htmlpagefooter name="myFooter">
    <div style="text-align: center; font-size: 8pt; color: #999; border-top: 1px solid #eee; padding-top: 5px;">
        Generiert am <?php echo date('d.m.Y H:i'); ?> | Seite {PAGENO}/{nbpg}
    </div>
</htmlpagefooter>
<sethtmlpagefooter name="myFooter" value="on" />

<?php foreach ($tours as $index => $tour): ?>
    <!-- Page Break for subsequent tours -->
    <?php if ($index > 0): ?>
        <pagebreak />
    <?php endif; ?>

    <div class="tour-page">
        <div style="text-align: right; font-size: 9pt; color: #999;">
            <?php echo esc_html($org_data['name']); ?> | Bus-Briefing
        </div>

        <h1>Tour: <?php echo date_i18n('l, d.m.Y', strtotime($tour['date'])); ?></h1>
        
        <div class="meta">
            <strong>Datum:</strong> <?php echo date_i18n('d.m.Y', strtotime($tour['date'])); ?><br>
            <strong>Stationen:</strong> <?php echo $tour['stop_count']; ?><br>
            <strong>Gesamtstrecke:</strong> <?php echo round($tour['total_distance'], 1); ?> km
        </div>

        <h2>Fahrplan & Wegpunkte</h2>

        <table>
            <thead>
                <tr>
                    <th style="width: 15%;">Zeit (Plan)</th>
                    <th style="width: 55%;">Ort / Adresse</th>
                    <th style="width: 30%;">Details / Maps</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tour['waypoints'] as $wp): ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($wp['time']); ?></strong>
                        <div class="stop-type" style="margin-top:5px;"><?php echo esc_html($wp['type_label']); ?></div>
                    </td>
                    <td>
                        <div class="address">
                            <strong><?php echo esc_html($wp['name']); ?></strong><br>
                            <?php echo esc_html($wp['address']); ?>
                        </div>
                        <?php if (!empty($wp['notes'])): ?>
                            <div class="notes">
                                <strong>Hinweise:</strong><br>
                                <?php echo nl2br(esc_html($wp['notes'])); ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center;">
                        <?php if ($wp['lat'] && $wp['lng']): 
                            $maps_url = "https://www.google.com/maps/search/?api=1&query=" . $wp['lat'] . "," . $wp['lng'];
                        ?>
                            <!-- QR Code for Google Maps -->
                            <barcode code="<?php echo $maps_url; ?>" type="QR" class="barcode" size="0.8" error="M" disableborder="1" />
                            <br>
                            <a href="<?php echo $maps_url; ?>" target="_blank" style="font-size: 8pt; text-decoration: none; color: #0073aa;">Google Maps</a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endforeach; ?>

</body>
</html>
