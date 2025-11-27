<?php

class TMGMT_Live_Tracking {

    const NAMESPACE = 'tmgmt/v1';

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {
        // Get Live Data for a Tour
        register_rest_route(self::NAMESPACE, '/tours/(?P<id>\d+)/live', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_tour_live_data'),
            'permission_callback' => '__return_true', // Public access for the view (maybe restrict later?)
        ));

        // Update Test Mode Settings (Admin only)
        register_rest_route(self::NAMESPACE, '/live/test-mode', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'update_test_mode'),
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
        ));
        
        // Get Test Mode Settings
        register_rest_route(self::NAMESPACE, '/live/test-mode', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_test_mode'),
            'permission_callback' => '__return_true',
        ));
    }

    public function get_tour_live_data($request) {
        try {
            $tour_id = $request['id'];
            $tour = get_post($tour_id);

            if (!$tour || $tour->post_type !== 'tmgmt_tour') {
                return new WP_Error('not_found', 'Tour nicht gefunden', array('status' => 404));
            }

            // 1. Get Tour Data
            $data_json = get_post_meta($tour_id, 'tmgmt_tour_data', true);
            $schedule = json_decode($data_json, true);
            
            if (!is_array($schedule)) {
                $schedule = array(); // Return empty if no data
            }

            // 2. Parse Waypoints
            $waypoints = array();
            foreach ($schedule as $item) {
                // We only care about items with location and time
                if (empty($item['lat']) || empty($item['lng'])) continue;

                $type = isset($item['type']) ? $item['type'] : 'unknown';
                
                // Determine Name based on Type
                $name = 'Unbekannt';
                if (!empty($item['location_name'])) {
                    $name = $item['location_name'];
                } elseif (!empty($item['location'])) {
                    $name = $item['location'];
                } elseif (!empty($item['address'])) {
                    $name = $item['address'];
                }

                if ($type === 'event' && !empty($item['title'])) {
                    $name = $item['title'];
                } elseif ($type === 'travel') {
                    $to = isset($item['to']) ? $item['to'] : '';
                    $name = $to ? "Fahrt nach $to" : "Fahrt";
                } elseif ($type === 'shuttle_travel') {
                    $to = isset($item['to']) ? $item['to'] : '';
                    $name = $to ? "Shuttle nach $to" : "Shuttle Fahrt";
                } elseif ($type === 'start') {
                    $name = ($name !== 'Unbekannt') ? "Start: $name" : "Start";
                } elseif ($type === 'shuttle_stop') {
                    $name = ($name !== 'Unbekannt') ? "Shuttle: $name" : "Shuttle Stop";
                }

                $wp = array(
                    'type' => $type,
                    'name' => $name,
                    'lat' => floatval($item['lat']),
                    'lng' => floatval($item['lng']),
                    'planned_arrival' => isset($item['arrival_time']) ? $item['arrival_time'] : null,
                    'planned_departure' => isset($item['departure_time']) ? $item['departure_time'] : null,
                    'event_id' => isset($item['id']) ? $item['id'] : null,
                    // Extra details for popup
                    'event_name' => isset($item['title']) ? $item['title'] : '',
                    'organizer' => isset($item['organizer']) ? $item['organizer'] : '',
                    'show_start' => isset($item['show_start']) ? $item['show_start'] : '',
                );
                
                $waypoints[] = $wp;
            }

            // 3. Get Test Mode Data
            $test_mode_active = get_option('tmgmt_live_test_mode_active', false);
            $simulated_lat = get_option('tmgmt_live_test_lat', null);
            $simulated_lng = get_option('tmgmt_live_test_lng', null);
            $simulated_time_offset = get_option('tmgmt_live_test_time_offset', 0); // Offset in seconds from real time

            // Calculate simulated time
            $current_timestamp = current_time('timestamp');
            $simulated_timestamp = $current_timestamp + (int)$simulated_time_offset;
            
            $has_position = ($simulated_lat !== '' && $simulated_lat !== null && $simulated_lng !== '' && $simulated_lng !== null);

            return array(
                'tour_id' => $tour_id,
                'title' => $tour->post_title,
                'date' => get_post_meta($tour_id, 'tmgmt_tour_date', true),
                'waypoints' => $waypoints,
                'test_mode' => array(
                    'active' => (bool)$test_mode_active,
                    'simulated_position' => $has_position ? array('lat' => floatval($simulated_lat), 'lng' => floatval($simulated_lng)) : null,
                    'simulated_timestamp' => $simulated_timestamp,
                    'offset' => (int)$simulated_time_offset,
                    'simulated_iso' => date('Y-m-d\TH:i', $simulated_timestamp)
                )
            );
        } catch (Throwable $e) {
            return new WP_Error('internal_error', $e->getMessage(), array('status' => 500, 'trace' => $e->getTraceAsString()));
        }
    }

    public function update_test_mode($request) {
        $params = $request->get_json_params();

        if (isset($params['active'])) {
            update_option('tmgmt_live_test_mode_active', (bool)$params['active']);
        }

        if (isset($params['lat']) && isset($params['lng'])) {
            update_option('tmgmt_live_test_lat', floatval($params['lat']));
            update_option('tmgmt_live_test_lng', floatval($params['lng']));
        }

        if (isset($params['simulated_time'])) {
            // Calculate offset from current time
            $sim_time = strtotime($params['simulated_time']);
            if ($sim_time) {
                $offset = $sim_time - current_time('timestamp');
                update_option('tmgmt_live_test_time_offset', $offset);
            }
        }

        if (isset($params['offset'])) {
            // Absolute offset setting
            update_option('tmgmt_live_test_time_offset', intval($params['offset']));
        }
        
        if (isset($params['offset_delta'])) {
            // Relative change (e.g. +5 mins)
            $current = get_option('tmgmt_live_test_time_offset', 0);
            update_option('tmgmt_live_test_time_offset', $current + intval($params['offset_delta']));
        }

        return $this->get_test_mode($request);
    }

    public function get_test_mode($request) {
        $active = get_option('tmgmt_live_test_mode_active', false);
        $lat = get_option('tmgmt_live_test_lat', null);
        $lng = get_option('tmgmt_live_test_lng', null);
        $offset = get_option('tmgmt_live_test_time_offset', 0);
        
        // Calculate simulated time
        $simulated_timestamp = current_time('timestamp') + $offset;
        
        $has_position = ($lat !== '' && $lat !== null && $lng !== '' && $lng !== null);

        return array(
            'active' => (bool)$active,
            'lat' => $has_position ? floatval($lat) : null,
            'lng' => $has_position ? floatval($lng) : null,
            'offset' => (int)$offset,
            'simulated_time' => date('Y-m-d H:i:s', $simulated_timestamp),
            'simulated_iso' => date('Y-m-d\TH:i', $simulated_timestamp),
            'simulated_position' => $has_position ? array('lat' => floatval($lat), 'lng' => floatval($lng)) : null
        );
    }
}
