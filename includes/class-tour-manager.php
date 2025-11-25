<?php

class TMGMT_Tour_Manager {

    public function __construct() {
        add_action('wp_ajax_tmgmt_calculate_tour', array($this, 'ajax_calculate_tour'));
    }

    public function ajax_calculate_tour() {
        check_ajax_referer('tmgmt_backend_nonce', 'nonce');
        
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $mode = isset($_POST['mode']) ? sanitize_text_field($_POST['mode']) : 'real';

        if (!$date) {
            wp_send_json_error('Kein Datum angegeben.');
        }

        $tour_data = $this->calculate_tour($date, $mode);
        
        if (is_wp_error($tour_data)) {
            wp_send_json_error($tour_data->get_error_message());
        }

        wp_send_json_success($tour_data);
    }

    public function calculate_tour($date, $mode = 'real') {
        // Debug Logging
        error_log('TMGMT Tour Calculation Start for Date: ' . $date . ' Mode: ' . $mode);

        // 1. Get Settings
        $start_lat = get_option('tmgmt_route_start_lat');
        $start_lng = get_option('tmgmt_route_start_lng');
        $start_name = get_option('tmgmt_route_start_name', 'Start');
        $buffer_arrival = (int)get_option('tmgmt_route_buffer_arrival', 30);
        $min_buffer_arrival = (int)get_option('tmgmt_route_min_buffer_arrival', 15);
        $max_idle_time = (int)get_option('tmgmt_route_max_idle_time', 120);
        $buffer_departure = (int)get_option('tmgmt_route_buffer_departure', 30);
        $show_duration = (int)get_option('tmgmt_route_show_duration', 60);
        $loading_time = (int)get_option('tmgmt_route_loading_time', 60);
        $bus_factor = (float)get_option('tmgmt_route_bus_factor', 1.0);
        $min_free_slot = (int)get_option('tmgmt_route_min_free_slot', 60);
        $status_filter = get_option('tmgmt_route_status_filter', array());

        // Convert Status Filter IDs to Slugs
        if (!empty($status_filter)) {
            $status_slugs = array();
            foreach ($status_filter as $status_id) {
                // Check if it's an ID (numeric)
                if (is_numeric($status_id)) {
                    $status_post = get_post($status_id);
                    if ($status_post) {
                        $status_slugs[] = $status_post->post_name;
                    }
                } else {
                    // Already a slug?
                    $status_slugs[] = $status_id;
                }
            }
            $status_filter = $status_slugs;
        }

        error_log('Settings - Start: ' . $start_lat . '/' . $start_lng . ', Status Filter (Slugs): ' . print_r($status_filter, true));

        if (!$start_lat || !$start_lng) {
            return new WP_Error('missing_start', 'Startpunkt (Proberaum) ist nicht konfiguriert.');
        }

        // 2. Get Events
        $args = array(
            'post_type' => 'event',
            'post_status' => 'any',
            'numberposts' => -1,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_tmgmt_event_date',
                    'value' => $date,
                    'compare' => '='
                ),
                array(
                    'key' => 'tmgmt_event_date',
                    'value' => $date,
                    'compare' => '='
                )
            )
        );
        
        $events = get_posts($args);
        $raw_count = count($events);
        error_log('Events found for date: ' . $raw_count);

        $filtered_events = array();
        $unroutable_events = array();
        $debug_log = array();

        foreach ($events as $event) {
            $status = get_post_meta($event->ID, '_tmgmt_status', true);
            if (!$status) $status = get_post_meta($event->ID, 'tmgmt_status', true); // Fallback

            $time = get_post_meta($event->ID, '_tmgmt_event_start_time', true);
            if (!$time) $time = get_post_meta($event->ID, 'tmgmt_event_start_time', true);

            $lat = get_post_meta($event->ID, '_tmgmt_geo_lat', true);
            if (!$lat) $lat = get_post_meta($event->ID, 'tmgmt_geo_lat', true);

            $lng = get_post_meta($event->ID, '_tmgmt_geo_lng', true);
            if (!$lng) $lng = get_post_meta($event->ID, 'tmgmt_geo_lng', true);

            $city = get_post_meta($event->ID, '_tmgmt_venue_city', true);
            if (!$city) $city = get_post_meta($event->ID, 'tmgmt_venue_city', true);

            $debug_info = "ID {$event->ID}: Status='{$status}', Lat='{$lat}', Lng='{$lng}'";
            error_log("Checking Event " . $debug_info);

            // If filter is active, check status (ONLY IN REAL MODE)
            if ($mode === 'real' && !empty($status_filter) && !in_array($status, $status_filter)) {
                error_log("-> Skipped due to status filter.");
                $debug_log[] = $debug_info . " -> Skipped (Status Filter: " . implode(',', $status_filter) . ")";
                continue;
            }
            
            if (!$lat || !$lng) {
                error_log("-> Skipped due to missing geodata.");
                $debug_log[] = $debug_info . " -> Skipped (Missing Geo)";
                
                $unroutable_events[] = array(
                    'id' => $event->ID,
                    'title' => $event->post_title,
                    'start_time' => $time,
                    'location' => $city ?: 'Unbekannt',
                    'error' => 'Keine Geodaten (Lat/Lng fehlt)'
                );
                continue;
            }

            $filtered_events[] = array(
                'id' => $event->ID,
                'title' => $event->post_title,
                'start_time' => $time,
                'lat' => $lat,
                'lng' => $lng,
                'location' => $city
            );
            error_log("-> Added to route.");
        }

        if (empty($filtered_events) && empty($unroutable_events)) {
            error_log('No events left after filtering.');
            return new WP_Error('no_events', 'Keine passenden Events für diesen Tag gefunden. (Gefunden: ' . $raw_count . ') <br>Details:<br>' . implode('<br>', $debug_log));
        }

        // 3. Sort by Time
        usort($filtered_events, function($a, $b) {
            return strcmp($a['start_time'], $b['start_time']);
        });

        // 4. Build Schedule Structure
        $schedule = array();
        $current_location = array('lat' => $start_lat, 'lng' => $start_lng, 'name' => $start_name);
        
        // Start Point
        $schedule[] = array(
            'type' => 'start',
            'location' => $current_location['name'],
            'lat' => $start_lat,
            'lng' => $start_lng,
            'loading_time' => $loading_time
        );

        foreach ($filtered_events as $event) {
            // Calculate travel from current to event
            $travel = $this->get_travel_data($current_location, $event, $bus_factor);
            
            $schedule[] = array(
                'type' => 'travel',
                'duration' => $travel['duration'], // minutes
                'distance' => $travel['distance'], // km
                'from' => $current_location['name'],
                'to' => $event['location']
            );

            $schedule[] = array(
                'type' => 'event',
                'id' => $event['id'],
                'title' => $event['title'],
                'location' => $event['location'],
                'lat' => $event['lat'],
                'lng' => $event['lng'],
                'show_start' => $event['start_time'],
                'buffer_arrival' => $buffer_arrival,
                'buffer_departure' => $buffer_departure
            );

            $current_location = array('lat' => $event['lat'], 'lng' => $event['lng'], 'name' => $event['location']);
        }

        // Return Trip
        $start_point = array('lat' => $start_lat, 'lng' => $start_lng, 'name' => $start_name);
        $travel_back = $this->get_travel_data($current_location, $start_point, $bus_factor);
        
        $schedule[] = array(
            'type' => 'travel',
            'duration' => $travel_back['duration'],
            'distance' => $travel_back['distance'],
            'from' => $current_location['name'],
            'to' => 'Rückfahrt'
        );

        $schedule[] = array(
            'type' => 'end',
            'location' => $start_name,
            'lat' => $start_lat,
            'lng' => $start_lng
        );

        // 5. Calculate Times
        // New Logic:
        // 1. Calculate Start of Tour (Backwards from First Event) to ensure we arrive on time.
        // 2. Calculate Departures for ALL events based on Show Duration (Forward Pass).
        // 3. Calculate Arrivals for subsequent events based on Travel (Forward Pass).

        if (!empty($schedule)) {
            // Find first event index
            $first_event_idx = -1;
            foreach ($schedule as $i => $item) {
                if ($item['type'] === 'event') {
                    $first_event_idx = $i;
                    break;
                }
            }

            if ($first_event_idx !== -1) {
                // --- A. Anchor: First Event Arrival ---
                $first_event = &$schedule[$first_event_idx];
                $first_show_start = strtotime($date . ' ' . $first_event['show_start']);
                $first_arrival_needed = $first_show_start - ($first_event['buffer_arrival'] * 60);
                
                $first_event['arrival_time'] = date('H:i', $first_arrival_needed);

                // --- B. Backwards Pass: Start of Day ---
                $current_time = $first_arrival_needed;
                for ($i = $first_event_idx - 1; $i >= 0; $i--) {
                    $item = &$schedule[$i];
                    if ($item['type'] === 'travel') {
                        $item['arrival_time'] = date('H:i', $current_time);
                        $departure_time = $current_time - ($item['duration'] * 60);
                        $item['departure_time'] = date('H:i', $departure_time);
                        $current_time = $departure_time;
                    } elseif ($item['type'] === 'start') {
                        $item['departure_time'] = date('H:i', $current_time);
                        $loading_start = $current_time - ($item['loading_time'] * 60);
                        $item['arrival_time'] = date('H:i', $loading_start);
                    }
                }

                // --- C. Forward Pass: Events & Subsequent Travel ---
                // We start tracking time from the First Event's Arrival
                
                $last_departure_time = 0;
                $current_arrival_timestamp = $first_arrival_needed;

                for ($i = $first_event_idx; $i < count($schedule); $i++) {
                    $item = &$schedule[$i];

                    if ($item['type'] === 'event') {
                        // Event Logic
                        $show_start = strtotime($date . ' ' . $item['show_start']);
                        
                        // Calculate Actual Buffer (Show Start - Arrival)
                        $item['actual_buffer'] = round(($show_start - $current_arrival_timestamp) / 60);

                        // Thresholds
                        // Standard Limit: Show Start - Standard Buffer (e.g. 20:00 - 30m = 19:30)
                        // Min Limit: Show Start - Min Buffer (e.g. 20:00 - 15m = 19:45)
                        
                        $standard_limit = $show_start - ($item['buffer_arrival'] * 60);
                        $min_limit = $show_start - ($min_buffer_arrival * 60);

                        // Gap Analysis (Draft Mode Only)
                        if ($mode === 'draft') {
                            $free_time = ($standard_limit - $current_arrival_timestamp) / 60; // minutes
                            if ($free_time >= $min_free_slot) {
                                $item['free_slot_before'] = array(
                                    'duration' => round($free_time),
                                    'start' => date('H:i', $current_arrival_timestamp),
                                    'end' => date('H:i', $standard_limit)
                                );
                            }
                        }

                        // Check for lateness
                        if ($current_arrival_timestamp > $show_start) {
                            // Level 3: Arrived after Show Start
                            $item['error'] = 'Auftrittszeit vor Ankunft';
                            $item['time_diff'] = abs($item['actual_buffer']); // Minutes late
                        } elseif ($current_arrival_timestamp > $min_limit) {
                            // Level 2: Arrived after Min Buffer Limit (but before Show Start)
                            $item['error'] = 'Vorlaufzeit zu gering';
                            $item['time_diff'] = $item['actual_buffer']; // Remaining buffer
                        } elseif ($current_arrival_timestamp > $standard_limit) {
                            // Level 1: Arrived after Standard Buffer Limit (but before Min Limit)
                            $item['warning'] = 'Achtung: Kurze Vorlaufzeit';
                            $item['time_diff'] = $item['actual_buffer']; // Remaining buffer
                        } elseif ($current_arrival_timestamp < ($show_start - ($max_idle_time * 60))) {
                            // Idle Warning: Arrived way too early
                            $item['idle_warning'] = 'Lange Wartezeit';
                            $item['time_diff'] = $item['actual_buffer']; // Waiting time
                        }

                        // Arrival Time:
                        // For First Event: Already set (Anchor).
                        // For Others: Should be set by previous Travel step.
                        
                        // Calculate Departure: Show Start + Duration + Buffer
                        $departure = $show_start + ($show_duration * 60) + ($item['buffer_departure'] * 60);
                        $item['departure_time'] = date('H:i', $departure);
                        
                        $last_departure_time = $departure;

                    } elseif ($item['type'] === 'travel') {
                        // Travel Logic
                        // Start travel at last_departure_time
                        $item['departure_time'] = date('H:i', $last_departure_time);
                        
                        $arrival = $last_departure_time + ($item['duration'] * 60);
                        $item['arrival_time'] = date('H:i', $arrival);
                        
                        $last_departure_time = $arrival; // Arrived at next location
                        $current_arrival_timestamp = $arrival;

                        // Set Arrival for next item (Event or End)
                        if (isset($schedule[$i+1])) {
                            $schedule[$i+1]['arrival_time'] = date('H:i', $arrival);
                        }
                    } elseif ($item['type'] === 'end') {
                        // End Logic
                        // Arrival already set by previous travel
                    }
                }
            }
        }

        // Append Unroutable Events
        if (!empty($unroutable_events)) {
            foreach ($unroutable_events as $ue) {
                $schedule[] = array(
                    'type' => 'event',
                    'id' => $ue['id'],
                    'title' => $ue['title'],
                    'location' => $ue['location'],
                    'error' => $ue['error'],
                    'show_start' => $ue['start_time'] ?: '??:??',
                    'time_diff' => 0 // Placeholder
                );
            }
        }

        return $schedule;
    }

    private function get_travel_data($from, $to, $bus_factor) {
        $ors_key = get_option('tmgmt_ors_api_key');
        $here_key = get_option('tmgmt_here_api_key');

        // Try HERE Maps first if key exists
        if ($here_key) {
            $data = $this->fetch_here_route($from, $to, $here_key);
            if ($data) {
                // Apply bus factor if not already handled by vehicle type (HERE supports bus, but let's keep it simple)
                // If we use car routing, apply factor.
                $data['duration'] = $data['duration'] * $bus_factor;
                return $data;
            }
        }

        // Try ORS if key exists
        if ($ors_key) {
            $data = $this->fetch_ors_route($from, $to, $ors_key);
            if ($data) {
                $data['duration'] = $data['duration'] * $bus_factor;
                return $data;
            }
        }

        // Fallback: Haversine
        $dist = $this->haversine($from['lat'], $from['lng'], $to['lat'], $to['lng']);
        $speed = 60; // km/h average
        $duration = ($dist / $speed) * 60 * $bus_factor; // minutes

        return array(
            'distance' => round($dist, 1),
            'duration' => round($duration)
        );
    }

    private function fetch_here_route($from, $to, $key) {
        $url = "https://router.hereapi.com/v8/routes?transportMode=car&origin={$from['lat']},{$from['lng']}&destination={$to['lat']},{$to['lng']}&return=summary&apiKey={$key}";
        
        $response = wp_remote_get($url);
        if (is_wp_error($response)) return false;
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['routes'][0]['sections'][0]['summary'])) {
            $summary = $data['routes'][0]['sections'][0]['summary'];
            return array(
                'distance' => round($summary['length'] / 1000, 1), // meters to km
                'duration' => round($summary['duration'] / 60) // seconds to minutes
            );
        }
        return false;
    }

    private function fetch_ors_route($from, $to, $key) {
        $url = "https://api.openrouteservice.org/v2/directions/driving-car?api_key={$key}&start={$from['lng']},{$from['lat']}&end={$to['lng']},{$to['lat']}";
        
        $response = wp_remote_get($url);
        if (is_wp_error($response)) return false;
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['features'][0]['properties']['segments'][0])) {
            $summary = $data['features'][0]['properties']['segments'][0];
            return array(
                'distance' => round($summary['distance'] / 1000, 1), // meters to km
                'duration' => round($summary['duration'] / 60) // seconds to minutes
            );
        }
        return false;
    }

    private function haversine($lat1, $lon1, $lat2, $lon2) {
        $earth_radius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $earth_radius * $c;
    }
}
