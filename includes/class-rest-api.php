<?php

class TMGMT_REST_API {

    const NAMESPACE = 'tmgmt/v1';

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {
        // Get Kanban Data
        register_rest_route(self::NAMESPACE, '/kanban', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_kanban_data'),
            'permission_callback' => array($this, 'check_permission'),
        ));

        // Get Single Event
        register_rest_route(self::NAMESPACE, '/events/(?P<id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_event'),
            'permission_callback' => array($this, 'check_permission'),
            'args'                => array(
                'id' => array(
                    'validate_callback' => function($param, $request, $key) {
                        return is_numeric($param);
                    }
                ),
            ),
        ));

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
        // Allow if user has specific capability OR is admin/editor
        return current_user_can('edit_tmgmt_events') || current_user_can('edit_posts');
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

        $log_manager = new TMGMT_Log_Manager();
        $changes = array();

        // Label Map for human readable names
        $label_map = array(
            'title' => 'Titel',
            'content' => 'Notizen / Beschreibung',
            'date' => 'Datum',
            'start_time' => 'Startzeit',
            'arrival_time' => 'Ankunftszeit',
            'departure_time' => 'Abfahrtszeit',
            'venue_name' => 'Location / Venue',
            'venue_street' => 'Straße',
            'venue_number' => 'Hausnummer',
            'venue_zip' => 'PLZ',
            'venue_city' => 'Stadt',
            'venue_country' => 'Land',
            'geo_lat' => 'Breitengrad',
            'geo_lng' => 'Längengrad',
            'arrival_notes' => 'Anreise Notizen',
            'status' => 'Status',
            'contact_salutation' => 'Anrede',
            'contact_firstname' => 'Vorname',
            'contact_lastname' => 'Nachname',
            'contact_company' => 'Firma',
            'contact_street' => 'Straße (Kontakt)',
            'contact_number' => 'Hausnummer (Kontakt)',
            'contact_zip' => 'PLZ (Kontakt)',
            'contact_city' => 'Stadt (Kontakt)',
            'contact_country' => 'Land (Kontakt)',
            'contact_email' => 'E-Mail',
            'contact_phone' => 'Telefon',
            'contact_email_contract' => 'E-Mail (Vertrag)',
            'contact_phone_contract' => 'Telefon (Vertrag)',
            'contact_name_tech' => 'Name (Technik)',
            'contact_email_tech' => 'E-Mail (Technik)',
            'contact_phone_tech' => 'Telefon (Technik)',
            'contact_name_program' => 'Name (Programm)',
            'contact_email_program' => 'E-Mail (Programm)',
            'contact_phone_program' => 'Telefon (Programm)',
            'inquiry_date' => 'Anfragedatum',
            'fee' => 'Gage',
            'deposit' => 'Anzahlung',
        );

        // 1. Update Core Post Data (Title, Content)
        $post_data = array('ID' => $event_id);
        $post_updated = false;
        
        $suppress_log = isset($params['suppress_log']) ? $params['suppress_log'] : false;
        $custom_old_value = isset($params['log_old_value']) ? $params['log_old_value'] : null;

        if (isset($params['title'])) {
            $new_title = sanitize_text_field($params['title']);
            if ($post->post_title !== $new_title) {
                if (!$suppress_log) {
                    $old_title = ($custom_old_value !== null) ? $custom_old_value : $post->post_title;
                    if ($old_title !== $new_title) {
                        $changes[] = sprintf('Titel wurde von "%s" zu "%s" geändert', $old_title, $new_title);
                    }
                }
                $post_data['post_title'] = $new_title;
                $post_updated = true;
            }
        }
        if (isset($params['content'])) {
            $new_content = wp_kses_post($params['content']);
            if ($post->post_content !== $new_content) {
                if (!$suppress_log) {
                    $changes[] = 'Notizen / Beschreibung wurde geändert';
                }
                $post_data['post_content'] = $new_content;
                $post_updated = true;
            }
        }
        
        if ($post_updated) {
            wp_update_post($post_data);
        }

        // 2. Update Meta Data
        $meta_map = array(
            'date'           => '_tmgmt_event_date',
            'start_time'     => '_tmgmt_event_start_time',
            'arrival_time'   => '_tmgmt_event_arrival_time',
            'departure_time' => '_tmgmt_event_departure_time',
            'venue_name'     => '_tmgmt_venue_name',
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
            'contact_email'      => '_tmgmt_contact_email',
            'contact_phone'      => '_tmgmt_contact_phone',
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
                $old_value = get_post_meta($event_id, $meta_key, true);
                
                // Always update if changed
                if ($old_value !== $new_value) {
                    update_post_meta($event_id, $meta_key, $new_value);
                }

                // Logging Logic
                if (!$suppress_log) {
                    $label = isset($label_map[$param_key]) ? $label_map[$param_key] : $param_key;
                    
                    // Determine "Old Value" for Log
                    // If custom_old_value is provided, use it. Otherwise use DB value.
                    // Note: If we just updated the DB above, $old_value is the PREVIOUS DB value.
                    // But if we are in a "blur" scenario where DB was already updated by auto-save,
                    // $old_value might equal $new_value.
                    // So we should rely on $custom_old_value if provided.
                    
                    $log_old_val = ($custom_old_value !== null) ? $custom_old_value : $old_value;
                    
                    // Only log if there is a difference between what we think is old and new
                    if ($log_old_val !== $new_value) {
                        // Special handling for Status
                        if ($param_key === 'status') {
                            $old_label = TMGMT_Event_Status::get_label($log_old_val);
                            $new_label = TMGMT_Event_Status::get_label($new_value);
                            $changes[] = sprintf('%s wurde von "%s" zu "%s" geändert', $label, $old_label, $new_label);
                            
                            // Trigger hook (only once per status change really, but here we might trigger it on blur too? 
                            // Ideally status changes are not debounced so they don't use suppress_log=true usually)
                            if ($old_value !== $new_value) {
                                do_action('tmgmt_event_updated', $event_id, $params);
                            }
                        } else {
                            // Truncate long values for log
                            $log_old_str = strlen($log_old_val) > 50 ? substr($log_old_val, 0, 47) . '...' : $log_old_val;
                            $log_new_str = strlen($new_value) > 50 ? substr($new_value, 0, 47) . '...' : $new_value;
                            
                            if (empty($log_old_val)) {
                                $changes[] = sprintf('%s wurde auf "%s" gesetzt', $label, $log_new_str);
                            } else if (empty($new_value)) {
                                $changes[] = sprintf('%s wurde gelöscht (war "%s")', $label, $log_old_str);
                            } else {
                                $changes[] = sprintf('%s wurde von "%s" zu "%s" geändert', $label, $log_old_str, $log_new_str);
                            }
                        }
                    }
                }
            }
        }

        // Log changes
        if (!empty($changes)) {
            foreach ($changes as $change) {
                $log_manager->log($event_id, 'api_update', $change);
            }
        }

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

    public function get_kanban_data($request) {
        // 1. Get Columns
        $columns = array();
        $col_posts = get_posts(array(
            'post_type' => 'tmgmt_kanban_col',
            'posts_per_page' => -1,
            'meta_key' => '_tmgmt_kanban_order',
            'orderby' => 'meta_value_num',
            'order' => 'ASC'
        ));

        foreach ($col_posts as $col) {
            $status_ids = get_post_meta($col->ID, '_tmgmt_kanban_statuses', true);
            if (!is_array($status_ids)) $status_ids = array();
            
            // Convert Status IDs to Slugs
            $status_slugs = array();
            foreach ($status_ids as $id) {
                $p = get_post($id);
                if ($p && $p->post_status === 'publish') {
                    $status_slugs[] = $p->post_name;
                }
            }

            $columns[] = array(
                'id' => $col->ID,
                'title' => $col->post_title,
                'statuses' => $status_slugs
            );
        }

        // 2. Get Events
        $events = get_posts(array(
            'post_type' => 'event',
            'posts_per_page' => -1,
            'post_status' => array('publish', 'future', 'draft', 'pending', 'private')
        ));

        $event_data = array();
        foreach ($events as $event) {
            $status = get_post_meta($event->ID, '_tmgmt_status', true);
            $date = get_post_meta($event->ID, '_tmgmt_event_date', true);
            $city = get_post_meta($event->ID, '_tmgmt_venue_city', true);
            
            $event_data[] = array(
                'id' => $event->ID,
                'title' => $event->post_title,
                'status' => $status,
                'date' => $date,
                'city' => $city
            );
        }

        return array(
            'columns' => $columns,
            'events' => $event_data,
            'statuses' => TMGMT_Event_Status::get_all_statuses()
        );
    }

    public function get_event($request) {
        $event_id = $request['id'];
        $post = get_post($event_id);
        
        if (!$post || $post->post_type !== 'event') {
            return new WP_Error('not_found', 'Event nicht gefunden', array('status' => 404));
        }

        // Fetch all meta
        $meta = get_post_meta($event_id);
        $clean_meta = array();
        foreach ($meta as $key => $values) {
            if (strpos($key, '_tmgmt_') === 0) {
                $clean_key = str_replace('_tmgmt_', '', $key);
                $clean_meta[$clean_key] = $values[0];
            }
        }

        // Fetch Logs
        $log_manager = new TMGMT_Log_Manager();
        $logs = $log_manager->get_logs($event_id);
        // Format logs for frontend
        $formatted_logs = array();
        foreach ($logs as $log) {
            $user_info = get_userdata($log->user_id);
            $formatted_logs[] = array(
                'id' => $log->id,
                'date' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->created_at)),
                'user' => $user_info ? $user_info->display_name : 'System',
                'message' => $log->message,
                'type' => $log->type
            );
        }

        // Fetch Actions for current status
        $current_status = isset($clean_meta['status']) ? $clean_meta['status'] : '';
        $actions = array();
        if ($current_status) {
            $status_def = get_page_by_path($current_status, OBJECT, 'tmgmt_status_def');
            if ($status_def) {
                $raw_actions = get_post_meta($status_def->ID, '_tmgmt_status_actions', true);
                if (is_array($raw_actions)) {
                    $actions = $raw_actions;
                }
            }
        }

        return array(
            'id' => $event_id,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'meta' => $clean_meta,
            'logs' => $formatted_logs,
            'actions' => $actions
        );
    }
}
