<?php
/**
 * Template Name: Live Tour Tracking
 * Post Type: tmgmt_tour
 */

get_header(); 

$tour_id = get_the_ID();
$data_json = get_post_meta($tour_id, 'tmgmt_tour_data', true);
$schedule = json_decode($data_json, true);
if (!is_array($schedule)) $schedule = array();

// Additional Meta
$mode = get_post_meta($tour_id, 'tmgmt_tour_mode', true);
$bus_travel = get_post_meta($tour_id, 'tmgmt_tour_bus_travel', true);
$pickup_shuttle_id = get_post_meta($tour_id, 'tmgmt_tour_pickup_shuttle', true);
$dropoff_shuttle_id = get_post_meta($tour_id, 'tmgmt_tour_dropoff_shuttle', true);

$pickup_shuttle_name = $pickup_shuttle_id ? get_the_title($pickup_shuttle_id) : '-';
$dropoff_shuttle_name = $dropoff_shuttle_id ? get_the_title($dropoff_shuttle_id) : '-';
?>

<div class="tmgmt-tour-container">
    
    <!-- Tabs Navigation -->
    <div class="tmgmt-tabs-nav">
        <button class="tmgmt-tab-btn active" data-tab="plan">Plan</button>
        <button class="tmgmt-tab-btn" data-tab="live">Live View</button>
    </div>

    <!-- Tab Content: Plan -->
    <div id="tmgmt-tab-plan" class="tmgmt-tab-content active">
        <div class="tmgmt-plan-header">
            <h1><?php the_title(); ?></h1>
            <div class="tmgmt-tour-meta">
                <strong>Datum:</strong> <?php echo date_i18n(get_option('date_format'), strtotime(get_post_meta($tour_id, 'tmgmt_tour_date', true))); ?>
                <span class="sep">|</span>
                <strong>Status:</strong> <?php echo ($mode === 'real') ? 'Echtplanung' : 'Entwurf'; ?>
                <?php if ($bus_travel): ?>
                    <span class="sep">|</span> <i class="fas fa-bus"></i> Busfahrt
                <?php endif; ?>
            </div>
        </div>

        <?php if ($pickup_shuttle_id || $dropoff_shuttle_id): ?>
        <div class="tmgmt-shuttle-info" style="background: #f9f9f9; padding: 10px; margin-bottom: 20px; border-radius: 4px; border: 1px solid #eee;">
            <strong>Shuttles:</strong>
            <?php if ($pickup_shuttle_id): ?>
                <span style="margin-right: 15px;">Abholung: <?php echo esc_html($pickup_shuttle_name); ?></span>
            <?php endif; ?>
            <?php if ($dropoff_shuttle_id): ?>
                <span>Rückfahrt: <?php echo esc_html($dropoff_shuttle_name); ?></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div class="tmgmt-table-responsive">
            <table class="tmgmt-tour-table">
                <thead>
                    <tr>
                        <th>Zeit</th>
                        <th>Aktivität</th>
                        <th>Ort / Details</th>
                        <th>Kontakte</th>
                        <th>Dauer</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($schedule as $item): 
                        $type = isset($item['type']) ? $item['type'] : '';
                        
                        // Row Style
                        $row_style = '';
                        if ($type === 'event') $row_style = 'background-color: #e6f7ff;';
                        elseif (strpos($type, 'travel') !== false) $row_style = 'color: #666; font-style: italic;';
                        elseif (strpos($type, 'shuttle') !== false) $row_style = 'background-color: #fff0f0;';
                        elseif ($type === 'start') $row_style = 'background-color: #e8f5e9;';

                        // Time Column
                        $time_col = '';
                        if (isset($item['arrival_time']) && isset($item['departure_time'])) {
                            if (strpos($type, 'travel') !== false) {
                                // For travel items, departure is start, arrival is end
                                $time_col = $item['departure_time'] . ' - ' . $item['arrival_time'];
                            } else {
                                // For stops/events, arrival is start, departure is end
                                $time_col = $item['arrival_time'] . ' - ' . $item['departure_time'];
                            }
                        } elseif (isset($item['arrival_time'])) {
                            $time_col = 'Ank: ' . $item['arrival_time'];
                        } elseif (isset($item['departure_time'])) {
                            $time_col = 'Abf: ' . $item['departure_time'];
                        }
                    ?>
                    <tr style="<?php echo esc_attr($row_style); ?>">
                        <td class="col-time" style="white-space: nowrap; font-weight: bold;"><?php echo esc_html($time_col); ?></td>
                        
                        <td class="col-type">
                            <?php 
                            if ($type === 'event') {
                                echo '<strong>Auftritt</strong>';
                                if (isset($item['show_start']) && !empty($item['show_start'])) {
                                    echo '<br><span style="font-size: 0.9em; color: #555;">' . esc_html($item['show_start']) . ' Uhr</span>';
                                }
                            }
                            elseif ($type === 'travel') echo 'Fahrt';
                            elseif ($type === 'shuttle_travel') echo 'Shuttle Fahrt';
                            elseif ($type === 'shuttle_stop') echo 'Shuttle Stop';
                            elseif ($type === 'start') echo 'Laden / Start';
                            elseif ($type === 'end') echo 'Ende';
                            else echo esc_html(ucfirst($type));
                            ?>
                        </td>
                        
                        <td class="col-location">
                            <?php 
                            if ($type === 'event') {
                                if (isset($item['title'])) echo '<strong>' . esc_html($item['title']) . '</strong>';
                                if (isset($item['organizer']) && $item['organizer']) echo ' (' . esc_html($item['organizer']) . ')';
                                echo '<br>';
                                if (isset($item['address'])) echo '<small>' . esc_html($item['address']) . '</small>';
                                elseif (isset($item['location'])) echo '<small>' . esc_html($item['location']) . '</small>';
                            } else {
                                if (isset($item['location'])) echo '<strong>' . esc_html($item['location']) . '</strong><br>';
                                if (isset($item['address'])) echo '<small>' . esc_html($item['address']) . '</small>';
                            }
                            
                            if (isset($item['from']) && isset($item['to'])) echo esc_html($item['from']) . ' &rarr; ' . esc_html($item['to']);
                            if (isset($item['distance'])) echo ' (' . round($item['distance'], 1) . ' km)';
                            
                            if (isset($item['notes']) && !empty($item['notes'])) echo '<br><em>' . esc_html($item['notes']) . '</em>';
                            
                            if (isset($item['error'])) echo '<br><span class="tmgmt-status-error" style="color:#d63638"><i class="fas fa-exclamation-circle"></i> ' . esc_html($item['error']) . '</span>';
                            if (isset($item['warning'])) echo '<br><span class="tmgmt-status-warning" style="color:#dba617"><i class="fas fa-exclamation-triangle"></i> ' . esc_html($item['warning']) . '</span>';
                            ?>
                        </td>
                        
                        <td class="col-contacts" style="font-size: 0.9em;">
                            <?php 
                            if ($type === 'event') {
                                if (!empty($item['contact_name']) || !empty($item['contact_phone'])) {
                                    echo '<strong>Vertrag:</strong><br>';
                                    if (!empty($item['contact_name'])) echo esc_html($item['contact_name']) . '<br>';
                                    if (!empty($item['contact_phone'])) echo esc_html($item['contact_phone']);
                                }
                                if (!empty($item['program_name']) || !empty($item['program_phone'])) {
                                    echo '<br><strong>Programm:</strong><br>';
                                    if (!empty($item['program_name'])) echo esc_html($item['program_name']) . '<br>';
                                    if (!empty($item['program_phone'])) echo esc_html($item['program_phone']);
                                }
                            }
                            ?>
                        </td>
                        
                        <td class="col-duration">
                            <?php 
                            if (isset($item['duration'])) echo $item['duration'] . ' min';
                            if (isset($item['loading_time'])) echo '<br>Laden: ' . $item['loading_time'] . ' min';
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tab Content: Live View -->
    <div id="tmgmt-tab-live" class="tmgmt-tab-content">
        <div id="tmgmt-live-view-app">
            <div id="tmgmt-live-map"></div>
            
            <button id="tmgmt-map-toggle" type="button" style="display:none;">
                <i class="fas fa-map"></i> Karte ausblenden
            </button>

            <div id="tmgmt-live-overlay">
                <div class="tmgmt-live-header">
                    <h1 id="tmgmt-tour-title">Lade Tour...</h1>
                    <div id="tmgmt-tour-status" class="status-badge">...</div>
                </div>

                <div class="tmgmt-live-info">
                    <div class="info-row">
                        <span class="label">Nächster Halt:</span>
                        <span class="value" id="tmgmt-next-stop">--</span>
                    </div>
                    <div class="info-row">
                        <span class="label" id="tmgmt-label-planned">Geplant:</span>
                        <span class="value" id="tmgmt-planned-time">--:--</span>
                    </div>
                    <div class="info-row" id="tmgmt-row-showtime" style="display:none;">
                        <span class="label">Showtime:</span>
                        <span class="value" id="tmgmt-show-time">--:--</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Erwartet (ETA):</span>
                        <span class="value" id="tmgmt-eta-time">--:--</span>
                    </div>
                    <div class="info-row">
                        <span class="label">Differenz:</span>
                        <span class="value" id="tmgmt-time-diff">--</span>
                    </div>
                </div>

                <div id="tmgmt-timeline" class="tmgmt-timeline">
                    <!-- Timeline items will be injected by JS -->
                </div>

                <div id="tmgmt-test-controls" style="display:none;">
                    <h3>Test Modus</h3>
                    <div class="control-group" style="margin-bottom: 10px; text-align: center;">
                        <input type="datetime-local" id="tmgmt-test-time" style="font-size: 12px; padding: 4px; width: 180px;">
                    </div>
                    <div class="control-group" style="text-align: center;">
                        <button type="button" class="tmgmt-btn-test" data-action="offset" data-value="-15">-15m</button>
                        <button type="button" class="tmgmt-btn-test" data-action="offset" data-value="-5">-5m</button>
                        <button type="button" class="tmgmt-btn-test" data-action="offset" data-value="5">+5m</button>
                        <button type="button" class="tmgmt-btn-test" data-action="offset" data-value="15">+15m</button>
                    </div>
                    <p class="hint">Klicke auf die Karte um Position zu setzen.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php get_footer(); ?>
