<?php

class TMGMT_Tour_Manager {

    public function __construct() {
        add_action('wp_ajax_tmgmt_calculate_tour', array($this, 'ajax_calculate_tour'));
    }

    public function ajax_calculate_tour() {
        check_ajax_referer('tmgmt_backend_nonce', 'nonce');
        
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        if (!$date) {
            wp_send_json_error('Kein Datum angegeben.');
        }

        $tour_data = $this->calculate_tour($date);
        
        if (is_wp_error($tour_data)) {
            wp_send_json_error($tour_data->get_error_message());
        }

        wp_send_json_success($tour_data);
    }

    public function calculate_tour($date) {
        // Debug Logging
        error_log('TMGMT Tour Calculation Start for Date: ' . $date);

        // 1. Get Settings
        $start_lat = get_option('tmgmt_route_start_lat');
        $start_lng = get_option('tmgmt_route_start_lng');
        $start_name = get_option('tmgmt_route_start_name', 'Start');
        $buffer_arrival = (int)get_option('tmgmt_route_buffer_arrival', 30);
        $buffer_departure = (int)get_option('tmgmt_route_buffer_departure', 30);
        $loading_time = (int)get_option('tmgmt_route_loading_time', 60);
        $bus_factor = (float)get_option('tmgmt_route_bus_factor', 1.0);
        $status_filter = get_option('tmgmt_route_status_filter', array());

        error_log('Settings - Start: ' . $start_lat . '/' . $start_lng . ', Status Filter: ' . print_r($status_filter, true));

        if (!$start_lat || !$start_lng) {
            return new WP_Error('missing_start', 'Startpunkt (Proberaum) ist nicht konfiguriert.');
        }

        // 2. Get Events
        $args = array(
            'post_type' => 'event',
            'numberposts' => -1,
            'meta_query' => array(
                array(
                    'key' => 'tmgmt_event_date',
                    'value' => $date,
                    'compare' => '='
                )
            )
        );
        
        $events = get_posts($args);
        error_log('Events found for date: ' . count($events));

        $filtered_events = array();

        foreach ($events as $event) {
            $status = get_post_meta($event->ID, 'tmgmt_event_status', true);
            $time = get_post_meta($event->ID, 'tmgmt_event_start_time', true);
            $lat = get_post_meta($event->ID, 'tmgmt_geo_lat', true);
            $lng = get_post_meta($event->ID, 'tmgmt_geo_lng', true);
            $city = get_post_meta($event->ID, 'tmgmt_venue_city', true);

            error_log("Checking Event ID {$event->ID} ('{$event->post_title}'): Status={$status}, Lat={$lat}, Lng={$lng}");

            // If filter is active, check status
            if (!empty($status_filter) && !in_array($status, $status_filter)) {
                error_log("-> Skipped due to status filter.");
                continue;
            }
            
            if (!$lat || !$lng) {
                error_log("-> Skipped due to missing geodata.");
                // Skip events without geo data for now
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

        if (empty($filtered_events)) {
            error_log('No events left after filtering.');
            return new WP_Error('no_events', 'Keine passenden Events mit Geodaten für diesen Tag gefunden.');
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

        // 5. Calculate Times (Backwards Pass)
        // We start from the last event's show time and calculate backwards to find the latest departure.
        // Note: This is a simplified logic. Real logic needs to handle multiple events.
        // We iterate backwards from the last event.
        
        // Find the last event index
        $last_event_idx = -1;
        for ($i = count($schedule) - 1; $i >= 0; $i--) {
            if ($schedule[$i]['type'] === 'event') {
                $last_event_idx = $i;
                break;
            }
        }

        if ($last_event_idx !== -1) {
            // Calculate times for the last event
            $event = &$schedule[$last_event_idx];
            $show_start = strtotime($date . ' ' . $event['show_start']);
            
            // Arrival at venue
            $arrival_time = $show_start - ($event['buffer_arrival'] * 60);
            $event['arrival_time'] = date('H:i', $arrival_time);
            
            // Departure from venue (after show)
            // We assume show duration? Or just use buffer departure as "time after show start"?
            // Usually show duration is needed. If missing, we assume 0 or just use buffer departure as "time after show".
            // Let's assume show takes 60 mins for now if not specified.
            $show_duration = 60; 
            $departure_time = $show_start + ($show_duration * 60) + ($event['buffer_departure'] * 60);
            $event['departure_time'] = date('H:i', $departure_time);

            // Now propagate backwards
            $current_arrival_time = $arrival_time;
            
            for ($i = $last_event_idx - 1; $i >= 0; $i--) {
                $item = &$schedule[$i];
                
                if ($item['type'] === 'travel') {
                    // Departure from previous location = Arrival at next - Duration
                    $departure_from_prev = $current_arrival_time - ($item['duration'] * 60);
                    $item['departure_time'] = date('H:i', $departure_from_prev);
                    $item['arrival_time'] = date('H:i', $current_arrival_time);
                    $current_arrival_time = $departure_from_prev;
                } elseif ($item['type'] === 'event') {
                    // This is an earlier event.
                    // Its departure time must be <= current_arrival_time (which is departure for next travel)
                    $item['departure_time'] = date('H:i', $current_arrival_time);
                    
                    // Check if this conflicts with its own show time + buffer
                    // For now, we just set the departure time based on the NEXT event requirement.
                    // But we also need to calculate its arrival time based on ITS show time.
                    
                    $this_show_start = strtotime($date . ' ' . $item['show_start']);
                    $this_arrival = $this_show_start - ($item['buffer_arrival'] * 60);
                    $item['arrival_time'] = date('H:i', $this_arrival);
                    
                    // Update current_arrival_time for the travel before this event
                    $current_arrival_time = $this_arrival;
                } elseif ($item['type'] === 'start') {
                    // Departure from start
                    $item['departure_time'] = date('H:i', $current_arrival_time);
                    // Loading start
                    $loading_start = $current_arrival_time - ($item['loading_time'] * 60);
                    $item['arrival_time'] = date('H:i', $loading_start); // "Arrival" at start means start of work
                }
            }
            
            // Propagate forward for return trip
            $last_event = $schedule[$last_event_idx];
            $last_departure = strtotime($date . ' ' . $last_event['departure_time']);
            
            // Find travel after last event
            for ($i = $last_event_idx + 1; $i < count($schedule); $i++) {
                $item = &$schedule[$i];
                if ($item['type'] === 'travel') {
                    $item['departure_time'] = date('H:i', $last_departure);
                    $arrival_at_end = $last_departure + ($item['duration'] * 60);
                    $item['arrival_time'] = date('H:i', $arrival_at_end);
                    $last_departure = $arrival_at_end;
                } elseif ($item['type'] === 'end') {
                    $item['arrival_time'] = date('H:i', $last_departure);
                }
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
