<?php
/**
 * Default Setlist Template
 * 
 * Available variables:
 * $data (array) - Event and Setlist data
 * $org_data (array) - Organization data
 */

$setlist = $data['setlist'];
$total_duration = 0;
?>
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; font-size: 12pt; }
        header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        h1 { margin: 0; font-size: 24pt; }
        h2 { margin: 5px 0 0; font-size: 14pt; color: #555; }
        .meta { font-size: 10pt; color: #777; margin-top: 5px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { text-align: left; border-bottom: 2px solid #000; padding: 5px; }
        td { padding: 5px; border-bottom: 1px solid #ddd; vertical-align: top; }
        
        .col-pos { width: 5%; text-align: center; color: #888; }
        .col-title { width: 45%; font-weight: bold; }
        .col-artist { width: 25%; }
        .col-key { width: 10%; text-align: center; }
        .col-bpm { width: 5%; text-align: center; }
        .col-dur { width: 10%; text-align: right; }
        
        .type-pause { background-color: #f0f0f0; font-style: italic; }
        .type-note { background-color: #fffbe6; }
        
        footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 9pt; color: #aaa; border-top: 1px solid #eee; padding-top: 5px; }
    </style>
</head>
<body>

<header>
    <?php if (!empty($org_data['logo_url'])): ?>
        <img src="<?php echo $org_data['logo_url']; ?>" style="height: 60px; width: auto; margin-bottom: 10px;">
    <?php endif; ?>
    
    <h1><?php echo esc_html($data['event_title']); ?></h1>
    <?php if (!empty($data['location'])): ?>
        <h2>@ <?php echo esc_html($data['location']); ?></h2>
    <?php endif; ?>
    
    <div class="meta">
        <?php 
        if (!empty($data['event_date'])) {
            echo date_i18n(get_option('date_format'), strtotime($data['event_date']));
        }
        ?>
    </div>
</header>

<table>
    <thead>
        <tr>
            <th class="col-pos">#</th>
            <th class="col-title">Titel</th>
            <th class="col-artist">Interpret</th>
            <th class="col-key">Key</th>
            <th class="col-bpm">BPM</th>
            <th class="col-dur">Dauer</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $pos = 1;
        foreach ($setlist as $block): 
            $type = isset($block['type']) ? $block['type'] : 'song';
            
            // Skip if it's a custom block that shouldn't be in the list? 
            // Assuming all blocks in setlist are to be printed.
            
            $row_class = 'type-' . $type;
            $duration = isset($block['duration']) ? intval($block['duration']) : 0;
            $total_duration += $duration;
            
            $dur_formatted = '';
            if ($duration > 0) {
                $minutes = floor($duration / 60);
                $seconds = $duration % 60;
                $dur_formatted = sprintf('%d:%02d', $minutes, $seconds);
            }
        ?>
            <tr class="<?php echo $row_class; ?>">
                <td class="col-pos">
                    <?php echo ($type === 'song') ? $pos++ : ''; ?>
                </td>
                
                <td class="col-title">
                    <?php 
                    if ($type === 'song') {
                        echo esc_html($block['title']);
                    } elseif ($type === 'pause') {
                        echo 'PAUSE';
                        if (!empty($block['title'])) echo ' - ' . esc_html($block['title']);
                    } elseif ($type === 'note') {
                        echo '<em>' . esc_html($block['content']) . '</em>';
                    }
                    ?>
                </td>
                
                <td class="col-artist">
                    <?php echo ($type === 'song') ? esc_html($block['artist']) : ''; ?>
                </td>
                
                <td class="col-key">
                    <?php echo ($type === 'song') ? esc_html($block['key']) : ''; ?>
                </td>
                
                <td class="col-bpm">
                    <?php echo ($type === 'song') ? esc_html($block['bpm']) : ''; ?>
                </td>
                
                <td class="col-dur">
                    <?php echo $dur_formatted; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        
        <!-- Total Duration -->
        <tr style="border-top: 2px solid #000; font-weight: bold;">
            <td colspan="5" style="text-align: right;">Gesamtdauer:</td>
            <td style="text-align: right;">
                <?php 
                $h = floor($total_duration / 3600);
                $m = floor(($total_duration % 3600) / 60);
                echo sprintf('%d:%02d h', $h, $m);
                ?>
            </td>
        </tr>
    </tbody>
</table>

<footer>
    Setlist generiert von Töns Management | <?php echo esc_html($org_data['name']); ?>
</footer>

</body>
</html>
