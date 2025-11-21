<?php

class TMGMT_REST_API {

    const NAMESPACE = 'tmgmt/v1';

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {
        // Update Event
        register_rest_route(self::NAMESPACE, '/events/(?P<id>\d+)', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'update_event'),
            'permission_callback' => array($this, 'check_permission'),
            'args'                => array(
                'id' => array(
                    'validate_callback' => function($param, $request, $key) {
                        return is_numeric($param);
                    }
                ),
            ),
        ));

        // Add Log Entry
        register_rest_route(self::NAMESPACE, '/events/(?P<id>\d+)/log', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'add_log_entry'),
            'permission_callback' => array($this, 'check_permission'),
            'args'                => array(
                'message' => array(
                    'required' => true,
                    'type'     => 'string',
                ),
                'type' => array(
                    'required' => false,
                    'type'     => 'string',
                    'default'  => 'api_info',
                ),
            ),
        ));
    }

    public function check_permission($request) {
        // Basic permission check: User must be able to edit posts
        // For external access (n8n), Application Passwords or Basic Auth is usually used.
        // WordPress handles the auth before this callback if headers are present.
        return current_user_can('edit_posts');
    }

    public function update_event($request) {
        $event_id = $request['id'];
        $params = $request->get_json_params();

        if (!$params) {
            $params = $request->get_body_params();
        }

        $post = get_post($event_id);
        if (!$post || $post->post_type !== 'event') {
            return new WP_Error('not_found', 'Event nicht gefunden', array('status' => 404));
        }

        $updated = false;
        $log_manager = new TMGMT_Log_Manager();

        // 1. Update Core Post Data (Title, Content)
        $post_data = array('ID' => $event_id);
        if (isset($params['title'])) {
            $post_data['post_title'] = sanitize_text_field($params['title']);
            $updated = true;
        }
        if (isset($params['content'])) {
            $post_data['post_content'] = wp_kses_post($params['content']);
            $updated = true;
        }
        
        if ($updated) {
            wp_update_post($post_data);
        }

        // 2. Update Meta Data
        // Map simple keys to internal meta keys
        $meta_map = array(
            'date'           => '_tmgmt_event_date',
            'start_time'     => '_tmgmt_event_start_time',
            'arrival_time'   => '_tmgmt_event_arrival_time',
            'departure_time' => '_tmgmt_event_departure_time',
            'venue_street'   => '_tmgmt_venue_street',
            'venue_number'   => '_tmgmt_venue_number',
            'venue_zip'      => '_tmgmt_venue_zip',
            'venue_city'     => '_tmgmt_venue_city',
            'venue_country'  => '_tmgmt_venue_country',
            'geo_lat'        => '_tmgmt_geo_lat',
            'geo_lng'        => '_tmgmt_geo_lng',
            'arrival_notes'  => '_tmgmt_arrival_notes',
            'status'         => '_tmgmt_status',
            // Contact
            'contact_salutation' => '_tmgmt_contact_salutation',
            'contact_firstname'  => '_tmgmt_contact_firstname',
            'contact_lastname'   => '_tmgmt_contact_lastname',
            'contact_company'    => '_tmgmt_contact_company',
            'contact_street'     => '_tmgmt_contact_street',
            'contact_number'     => '_tmgmt_contact_number',
            'contact_zip'        => '_tmgmt_contact_zip',
            'contact_city'       => '_tmgmt_contact_city',
            'contact_country'    => '_tmgmt_contact_country',
            'contact_email_contract' => '_tmgmt_contact_email_contract',
            'contact_phone_contract' => '_tmgmt_contact_phone_contract',
            'contact_name_tech'      => '_tmgmt_contact_name_tech',
            'contact_email_tech'     => '_tmgmt_contact_email_tech',
            'contact_phone_tech'     => '_tmgmt_contact_phone_tech',
            'contact_name_program'   => '_tmgmt_contact_name_program',
            'contact_email_program'  => '_tmgmt_contact_email_program',
            'contact_phone_program'  => '_tmgmt_contact_phone_program',
            // Inquiry
            'inquiry_date'   => '_tmgmt_inquiry_date',
            // Contract
            'fee'            => '_tmgmt_fee',
            'deposit'        => '_tmgmt_deposit',
        );

        foreach ($meta_map as $param_key => $meta_key) {
            if (isset($params[$param_key])) {
                $new_value = sanitize_text_field($params[$param_key]);
                
                // Special handling for Status to trigger logs
                if ($param_key === 'status') {
                    $old_status = get_post_meta($event_id, '_tmgmt_status', true);
                    if ($old_status !== $new_value) {
                        update_post_meta($event_id, $meta_key, $new_value);
                        $log_manager->log($event_id, 'status_change', "Status via API geändert auf: " . TMGMT_Event_Status::get_label($new_value));
                        
                        // Trigger hook
                        do_action('tmgmt_event_updated', $event_id, $params);
                    }
                } else {
                    update_post_meta($event_id, $meta_key, $new_value);
                }
            }
        }

        // Log the API update itself
        $log_manager->log($event_id, 'api_update', 'Event Daten via API aktualisiert.');

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Event aktualisiert',
            'id'      => $event_id
        ), 200);
    }

    public function add_log_entry($request) {
        $event_id = $request['id'];
        $params = $request->get_json_params();

        if (!$params) {
            $params = $request->get_body_params();
        }

        $post = get_post($event_id);
        if (!$post || $post->post_type !== 'event') {
            return new WP_Error('not_found', 'Event nicht gefunden', array('status' => 404));
        }

        $message = sanitize_text_field($params['message']);
        $type = isset($params['type']) ? sanitize_text_field($params['type']) : 'api_info';

        $log_manager = new TMGMT_Log_Manager();
        $log_manager->log($event_id, $type, $message);

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Log Eintrag erstellt'
        ), 200);
    }
}
