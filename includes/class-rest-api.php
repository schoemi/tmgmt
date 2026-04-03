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

        // Create Event
        register_rest_route(self::NAMESPACE, '/events', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'create_event'),
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

        // Preview Action (Email)
        register_rest_route(self::NAMESPACE, '/events/(?P<id>\d+)/actions/(?P<action_id>\d+)/preview', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'preview_action'),
            'permission_callback' => array($this, 'check_permission'),
        ));

        // Execute Action
        register_rest_route(self::NAMESPACE, '/events/(?P<id>\d+)/actions/(?P<action_id>\d+)/execute', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'execute_action'),
            'permission_callback' => array($this, 'check_permission'),
        ));

        // Attachments
        register_rest_route(self::NAMESPACE, '/events/(?P<id>\d+)/attachments', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_attachments'),
            'permission_callback' => array($this, 'check_permission'),
        ));

        // Delete Attachment
        register_rest_route(self::NAMESPACE, '/events/(?P<id>\d+)/attachments/(?P<attachment_id>\d+)', array(
            'methods'             => 'DELETE',
            'callback'            => array($this, 'delete_attachment'),
            'permission_callback' => function($request) {
                return current_user_can('administrator');
            },
        ));

        // Get Email Templates
        register_rest_route(self::NAMESPACE, '/email-templates', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_email_templates'),
            'permission_callback' => array($this, 'check_permission'),
        ));

        // Preview Email
        register_rest_route(self::NAMESPACE, '/events/(?P<id>\d+)/email-preview', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'preview_email'),
            'permission_callback' => array($this, 'check_permission'),
            'args'                => array(
                'template_id' => array(
                    'required' => true,
                    'validate_callback' => function($param, $request, $key) {
                        return is_numeric($param);
                    }
                ),
            ),
        ));

        // Send Email
        register_rest_route(self::NAMESPACE, '/events/(?P<id>\d+)/email-send', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'send_email'),
            'permission_callback' => array($this, 'check_permission'),
            'args'                => array(
                'recipient' => array('required' => true, 'type' => 'string'),
                'subject' => array('required' => true, 'type' => 'string'),
                'body' => array('required' => true, 'type' => 'string'),
                'attach_pdf' => array('required' => false, 'type' => 'boolean'),
            ),
        ));

        // --- IMAP Ticket-System Endpoints ---

        // Mail Queue: List (with optional ?status= filter)
        register_rest_route(self::NAMESPACE, '/mail-queue', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_mail_queue'),
            'permission_callback' => array($this, 'check_permission'),
            'args'                => array(
                'status' => array(
                    'required' => false,
                    'type'     => 'string',
                    'default'  => 'neu',
                ),
            ),
        ));

        // Mail Queue: Single entry
        register_rest_route(self::NAMESPACE, '/mail-queue/(?P<id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_mail_queue_entry'),
            'permission_callback' => array($this, 'check_permission'),
            'args'                => array(
                'id' => array(
                    'validate_callback' => function($param, $request, $key) {
                        return is_numeric($param);
                    }
                ),
            ),
        ));

        // Mail Queue: Assign to event
        register_rest_route(self::NAMESPACE, '/mail-queue/(?P<id>\d+)/assign', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'assign_mail_queue_entry'),
            'permission_callback' => array($this, 'check_permission'),
            'args'                => array(
                'id' => array(
                    'validate_callback' => function($param, $request, $key) {
                        return is_numeric($param);
                    }
                ),
                'event_id' => array(
                    'required' => true,
                    'type'     => 'integer',
                ),
            ),
        ));

        // Mail Queue: Create event from email
        register_rest_route(self::NAMESPACE, '/mail-queue/(?P<id>\d+)/create-event', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'create_event_from_email'),
            'permission_callback' => array($this, 'check_permission'),
            'args'                => array(
                'id' => array(
                    'validate_callback' => function($param, $request, $key) {
                        return is_numeric($param);
                    }
                ),
            ),
        ));

        // Veranstalter: Create new
        register_rest_route(self::NAMESPACE, '/veranstalter', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'create_veranstalter'),
            'permission_callback' => array($this, 'check_permission'),
            'args'                => array(
                'name' => array(
                    'required' => true,
                    'type'     => 'string',
                ),
            ),
        ));

        // Location: Create new
        register_rest_route(self::NAMESPACE, '/locations', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'create_location'),
            'permission_callback' => array($this, 'check_permission'),
            'args'                => array(
                'name' => array(
                    'required' => true,
                    'type'     => 'string',
                ),
            ),
        ));

        // Mail Queue: Reply to email
        register_rest_route(self::NAMESPACE, '/mail-queue/(?P<id>\d+)/reply', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'reply_mail_queue_entry'),
            'permission_callback' => array($this, 'check_permission'),
            'args'                => array(
                'id' => array(
                    'validate_callback' => function($param, $request, $key) {
                        return is_numeric($param);
                    }
                ),
                'body' => array(
                    'required' => true,
                    'type'     => 'string',
                ),
            ),
        ));

        // Event Tickets: IMAP emails for an event
        register_rest_route(self::NAMESPACE, '/events/(?P<id>\d+)/tickets', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_event_tickets'),
            'permission_callback' => array($this, 'check_permission'),
            'args'                => array(
                'id' => array(
                    'validate_callback' => function($param, $request, $key) {
                        return is_numeric($param);
                    }
                ),
            ),
        ));

        // IMAP: Test connection (admin only)
        register_rest_route(self::NAMESPACE, '/imap/test', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'test_imap_connection'),
            'permission_callback' => array($this, 'check_admin_permission'),
        ));

        // SMTP: Test connection (admin only)
        register_rest_route(self::NAMESPACE, '/smtp/test', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'test_smtp_connection'),
            'permission_callback' => array($this, 'check_admin_permission'),
        ));

        // IMAP: Manual fetch trigger (admin only)
        register_rest_route(self::NAMESPACE, '/imap/fetch-now', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'imap_fetch_now'),
            'permission_callback' => array($this, 'check_admin_permission'),
        ));
    }

    public function check_permission($request) {
        // Allow if user has specific capability OR is admin/editor
        return current_user_can('edit_tmgmt_events') || current_user_can('edit_posts');
    }

    public function create_event($request) {
        $params = $request->get_json_params();
        if (!$params) {
            $params = $request->get_body_params();
        }

        $title = isset($params['title']) ? sanitize_text_field($params['title']) : 'Neues Event';
        
        // Create Post
        $post_id = wp_insert_post(array(
            'post_title'  => $title,
            'post_type'   => 'event',
            'post_status' => 'publish'
        ));

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Set default status if available
        // Find the first status of the first column
        $col_posts = get_posts(array(
            'post_type' => 'tmgmt_kanban_col',
            'posts_per_page' => 1,
            'meta_key' => '_tmgmt_kanban_order',
            'orderby' => 'meta_value_num',
            'order' => 'ASC'
        ));

        if (!empty($col_posts)) {
            $status_ids = get_post_meta($col_posts[0]->ID, '_tmgmt_kanban_statuses', true);
            if (is_array($status_ids) && !empty($status_ids)) {
                $first_status_post = get_post($status_ids[0]);
                if ($first_status_post) {
                    update_post_meta($post_id, '_tmgmt_status', $first_status_post->post_name);
                }
            }
        }

        // Set Inquiry Date to today
        update_post_meta($post_id, 'tmgmt_inquiry_date', current_time('Y-m-d\TH:i'));

        // Log creation
        $log_manager = new TMGMT_Log_Manager();
        $log_manager->log($post_id, 'api_create', 'Event erstellt');

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Event erstellt',
            'id'      => $post_id
        ), 201);
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
            'location_id' => 'Veranstaltungsort',
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
            'location_id'    => '_tmgmt_event_location_id',
            'status'         => '_tmgmt_status',
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

            $color = get_post_meta($col->ID, '_tmgmt_kanban_color', true);

            $columns[] = array(
                'id' => $col->ID,
                'title' => $col->post_title,
                'statuses' => $status_slugs,
                'color' => $color ? $color : '#cccccc'
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
            $date_raw = get_post_meta($event->ID, '_tmgmt_event_date', true);
            $time_raw = get_post_meta($event->ID, '_tmgmt_event_start_time', true);
            
            // Get city from linked location
            $location_id = get_post_meta($event->ID, '_tmgmt_event_location_id', true);
            $city = '';
            if (!empty($location_id)) {
                $city = get_post_meta($location_id, '_tmgmt_location_city', true);
            }
            
            $formatted_date = '';
            if ($date_raw) {
                $formatted_date = date_i18n(get_option('date_format'), strtotime($date_raw));
            }

            $formatted_time = '';
            if ($time_raw) {
                $formatted_time = date_i18n(get_option('time_format'), strtotime($time_raw));
            }

            $event_data[] = array(
                'id' => $event->ID,
                'title' => $event->post_title,
                'status' => $status,
                'date' => $formatted_date,
                'time' => $formatted_time,
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
        
        // Fetch Communication
        $comm_manager = new TMGMT_Communication_Manager();
        $communication = $comm_manager->get_entries($event_id);

        // Format logs for frontend
        $formatted_logs = array();
        foreach ($logs as $log) {
            $user_info = get_userdata($log->user_id);
            $formatted_logs[] = array(
                'id' => $log->id,
                'date' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->created_at)),
                'user' => $user_info ? $user_info->display_name : 'System',
                'message' => $log->message,
                'type' => $log->type,
                'communication_id' => isset($log->communication_id) ? $log->communication_id : 0
            );
        }
        
        // Format Communication
        $formatted_comm = array();
        foreach ($communication as $comm) {
            $user_info = get_userdata($comm->user_id);
            $formatted_comm[] = array(
                'id' => $comm->id,
                'date' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($comm->created_at)),
                'user' => $user_info ? $user_info->display_name : 'System',
                'type' => $comm->type,
                'recipient' => $comm->recipient,
                'subject' => $comm->subject,
                'content' => $comm->content
            );
        }

        // Fetch Actions for current status
        $current_status = isset($clean_meta['status']) ? $clean_meta['status'] : '';
        $actions = array();
        if ($current_status) {
            $status_def = get_page_by_path($current_status, OBJECT, 'tmgmt_status_def');
            if ($status_def) {
                $available_action_ids = get_post_meta($status_def->ID, '_tmgmt_available_actions', true);
                
                if (is_array($available_action_ids) && !empty($available_action_ids)) {
                    foreach ($available_action_ids as $action_id) {
                        $action_post = get_post($action_id);
                        if (!$action_post || $action_post->post_status !== 'publish') continue;

                        $target_status = get_post_meta($action_id, '_tmgmt_action_target_status', true);
                        $type = get_post_meta($action_id, '_tmgmt_action_type', true);
                        
                        // Build action object compatible with frontend
                        $action_data = array(
                            'id' => $action_id,
                            'label' => $action_post->post_title,
                            'type' => $type,
                            'target_status' => $target_status,
                            'required_fields' => array()
                        );

                        // If action changes status, fetch required fields for target status
                        if ($target_status) {
                            $target_def = get_page_by_path($target_status, OBJECT, 'tmgmt_status_def');
                            if ($target_def) {
                                $req = get_post_meta($target_def->ID, '_tmgmt_required_fields', true);
                                if (is_array($req) && !empty($req)) {
                                    $action_data['required_fields'] = $req;
                                }
                            }
                        }
                        
                        $actions[] = $action_data;
                    }
                }
            }
        }

        // Fetch Attachments
        $attachment_data = isset($clean_meta['event_attachments']) ? maybe_unserialize($clean_meta['event_attachments']) : array();
        $attachments = array();
        
        if (is_array($attachment_data)) {
            foreach ($attachment_data as $item) {
                // Normalize structure
                $att_id = 0;
                $category = '';
                
                if (is_numeric($item)) {
                    $att_id = intval($item);
                } elseif (is_array($item) && isset($item['id'])) {
                    $att_id = intval($item['id']);
                    $category = isset($item['category']) ? $item['category'] : '';
                }

                if ($att_id > 0) {
                    $att_post = get_post($att_id);
                    if ($att_post) {
                        $attachments[] = array(
                            'id' => $att_id,
                            'title' => $att_post->post_title,
                            'filename' => basename(get_attached_file($att_id)),
                            'url' => wp_get_attachment_url($att_id),
                            'type' => $att_post->post_mime_type,
                            'icon' => wp_mime_type_icon($att_id),
                            'category' => $category
                        );
                    }
                }
            }
        }

        // Fetch Tour Info
        $tours_info = array();
        $event_date = isset($clean_meta['event_date']) ? $clean_meta['event_date'] : '';
        
        if ($event_date) {
            $tours = get_posts(array(
                'post_type' => 'tmgmt_tour',
                'numberposts' => -1,
                'meta_query' => array(
                    array(
                        'key' => 'tmgmt_tour_date',
                        'value' => $event_date,
                        'compare' => '='
                    )
                )
            ));

            foreach ($tours as $tour) {
                $data_json = get_post_meta($tour->ID, 'tmgmt_tour_data', true);
                $schedule = json_decode($data_json, true);
                $mode = get_post_meta($tour->ID, 'tmgmt_tour_mode', true);
                if (!$mode) $mode = 'draft';

                $is_in_tour = false;
                $event_status_in_tour = 'ok';

                if (is_array($schedule)) {
                    foreach ($schedule as $item) {
                        if (isset($item['type']) && $item['type'] === 'event' && isset($item['id']) && $item['id'] == $event_id) {
                            $is_in_tour = true;
                            if (isset($item['error'])) {
                                $event_status_in_tour = 'error';
                            } elseif (isset($item['warning'])) {
                                $event_status_in_tour = 'warning';
                            }
                            break;
                        }
                    }
                }

                if ($is_in_tour) {
                    $tours_info[] = array(
                        'id' => $tour->ID,
                        'title' => $tour->post_title,
                        'link' => get_permalink($tour->ID),
                        'mode' => $mode,
                        'status' => $event_status_in_tour
                    );
                }
            }
        }

        // Inject contact data resolved via Veranstalter → Contact CPT
        $contact_data = TMGMT_Placeholder_Parser::get_contact_data_for_event( $event_id );
        $vertrag  = $contact_data['vertrag'];
        $technik  = $contact_data['technik'];
        $programm = $contact_data['programm'];

        $clean_meta['contact_salutation']     = $vertrag['salutation'];
        $clean_meta['contact_firstname']      = $vertrag['firstname'];
        $clean_meta['contact_lastname']       = $vertrag['lastname'];
        $clean_meta['contact_company']        = $vertrag['company'];
        $clean_meta['contact_street']         = $vertrag['street'];
        $clean_meta['contact_number']         = $vertrag['number'];
        $clean_meta['contact_zip']            = $vertrag['zip'];
        $clean_meta['contact_city']           = $vertrag['city'];
        $clean_meta['contact_country']        = $vertrag['country'];
        $clean_meta['contact_email']          = $vertrag['email'];
        $clean_meta['contact_phone']          = $vertrag['phone'];
        $clean_meta['contact_email_contract'] = $vertrag['email'];
        $clean_meta['contact_phone_contract'] = $vertrag['phone'];
        $clean_meta['contact_name_tech']      = trim( $technik['firstname'] . ' ' . $technik['lastname'] );
        $clean_meta['contact_email_tech']     = $technik['email'];
        $clean_meta['contact_phone_tech']     = $technik['phone'];
        $clean_meta['contact_name_program']   = trim( $programm['firstname'] . ' ' . $programm['lastname'] );
        $clean_meta['contact_email_program']  = $programm['email'];
        $clean_meta['contact_phone_program']  = $programm['phone'];

        return array(
            'id' => $event_id,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'meta' => $clean_meta,
            'logs' => $formatted_logs,
            'communication' => $formatted_comm,
            'actions' => $actions,
            'attachments' => $attachments,
            'tours' => $tours_info
        );
    }

    public function preview_action($request) {
        $event_id = $request['id'];
        $action_id = $request['action_id'];

        $action_post = get_post($action_id);
        if (!$action_post || $action_post->post_type !== 'tmgmt_action') {
            return new WP_Error('not_found', 'Aktion nicht gefunden', array('status' => 404));
        }

        $type = get_post_meta($action_id, '_tmgmt_action_type', true);
        if ($type !== 'email' && $type !== 'email_confirmation') {
            return new WP_Error('invalid_type', 'Vorschau nur für E-Mails verfügbar', array('status' => 400));
        }

        $email_template_id = get_post_meta($action_id, '_tmgmt_action_email_template_id', true);

        $subject_raw   = $email_template_id ? get_post_meta($email_template_id, '_tmgmt_email_subject', true) : '';
        $body_raw      = $email_template_id ? get_post_meta($email_template_id, '_tmgmt_email_body', true) : '';
        $recipient_raw = $email_template_id ? get_post_meta($email_template_id, '_tmgmt_email_recipient', true) : '';

        // Fallback for recipient if empty
        if (empty($recipient_raw)) {
            $recipient_raw = '[contact_email_contract]';
        }

        // Fallback for subject if empty
        if (empty($subject_raw)) {
            $subject_raw = 'Info: [event_title]';
        }

        $subject = TMGMT_Placeholder_Parser::parse($subject_raw, $event_id);
        $body = TMGMT_Placeholder_Parser::parse($body_raw, $event_id);
        $recipient = TMGMT_Placeholder_Parser::parse($recipient_raw, $event_id);

        return array(
            'subject' => $subject,
            'body' => $body,
            'recipient' => $recipient
        );
    }

    public function execute_action($request) {
        $event_id = $request['id'];
        $action_id = $request['action_id'];
        $params = $request->get_json_params();

        $action_post = get_post($action_id);
        if (!$action_post || $action_post->post_type !== 'tmgmt_action') {
            return new WP_Error('not_found', 'Aktion nicht gefunden', array('status' => 404));
        }

        $type = get_post_meta($action_id, '_tmgmt_action_type', true);
        $log_manager = new TMGMT_Log_Manager();
        $log_message = "Aktion ausgeführt: " . $action_post->post_title;

        if ($type === 'email' || $type === 'email_confirmation') {
            // Always load template ID upfront
            $email_template_id = get_post_meta($action_id, '_tmgmt_action_email_template_id', true);

            // Use provided subject/body or fallback to template
            $subject = isset($params['email_subject']) ? $params['email_subject'] : '';
            $body = isset($params['email_body']) ? $params['email_body'] : '';
            $recipient = isset($params['email_recipient']) ? $params['email_recipient'] : '';
            
            // If not provided (e.g. direct execution without preview), parse from template
            if (empty($subject) || empty($body) || empty($recipient)) {
                if ($email_template_id) {
                    if (empty($subject)) {
                        $raw = get_post_meta($email_template_id, '_tmgmt_email_subject', true);
                        $subject = TMGMT_Placeholder_Parser::parse($raw, $event_id);
                    }
                    if (empty($body)) {
                        $raw = get_post_meta($email_template_id, '_tmgmt_email_body', true);
                        $body = TMGMT_Placeholder_Parser::parse($raw, $event_id);
                    }
                    if (empty($recipient)) {
                        $raw = get_post_meta($email_template_id, '_tmgmt_email_recipient', true);
                        if (empty($raw)) $raw = '[contact_email_contract]'; // Fallback
                        $recipient = TMGMT_Placeholder_Parser::parse($raw, $event_id);
                    }
                }
            }

            // Handle Confirmation Link
            if ($type === 'email_confirmation') {
                $conf_manager = new TMGMT_Confirmation_Manager();
                $conf_result = $conf_manager->create_request($event_id, $action_id, $recipient);
                
                if ($conf_result) {
                    $body = str_replace('{{confirmation_link}}', $conf_result['link'], $body);
                    $body = str_replace('{{confirmation_url}}', $conf_result['link'], $body);
                }
            }

            // Headers
            $headers = array('Content-Type: text/html; charset=UTF-8');
            
            // Handle CC/BCC/Reply-To
            $cc_raw = get_post_meta($email_template_id, '_tmgmt_email_cc', true);
            $bcc_raw = get_post_meta($email_template_id, '_tmgmt_email_bcc', true);
            $reply_to_raw = get_post_meta($email_template_id, '_tmgmt_email_reply_to', true);

            if (!empty($cc_raw)) {
                $cc = TMGMT_Placeholder_Parser::parse($cc_raw, $event_id);
                if (!empty($cc)) $headers[] = 'Cc: ' . $cc;
            }
            if (!empty($bcc_raw)) {
                $bcc = TMGMT_Placeholder_Parser::parse($bcc_raw, $event_id);
                if (!empty($bcc)) $headers[] = 'Bcc: ' . $bcc;
            }
            if (!empty($reply_to_raw)) {
                $reply_to = TMGMT_Placeholder_Parser::parse($reply_to_raw, $event_id);
                if (!empty($reply_to)) $headers[] = 'Reply-To: ' . $reply_to;
            }

            // Handle Attachments
            $attachments = array();
            
            // 0. Template Attachments
            $tpl_attachments = get_post_meta($email_template_id, '_tmgmt_email_attachments', true);
            if (is_array($tpl_attachments)) {
                foreach ($tpl_attachments as $att_id) {
                    $path = get_attached_file($att_id);
                    if ($path && file_exists($path)) {
                        $attachments[] = $path;
                    }
                }
            }

            $sent = wp_mail($recipient, $subject, nl2br($body), $headers, $attachments);
            if ($sent) {
                $log_message .= " - E-Mail gesendet an: $recipient";
                
                // Save Communication
                $comm_manager = new TMGMT_Communication_Manager();
                $comm_id = $comm_manager->add_entry($event_id, 'email', $recipient, $subject, $body);
                
                $log_manager->log($event_id, 'email_sent', "E-Mail '$subject' an $recipient gesendet.", null, $comm_id);
            } else {
                return new WP_Error('mail_failed', 'E-Mail konnte nicht gesendet werden', array('status' => 500));
            }

        } elseif ($type === 'webhook') {
            $webhook_id = get_post_meta($action_id, '_tmgmt_action_webhook_id', true);
            $webhook_url = get_post_meta($webhook_id, '_tmgmt_webhook_url', true);
            $webhook_method = get_post_meta($webhook_id, '_tmgmt_webhook_method', true);

            if ($webhook_url) {
                 // Fetch all meta for payload
                 $meta = get_post_meta($event_id);
                 $clean_meta = array();
                 foreach ($meta as $key => $values) {
                     if (strpos($key, '_tmgmt_') === 0) {
                         $clean_key = str_replace('_tmgmt_', '', $key);
                         $clean_meta[$clean_key] = $values[0];
                     }
                 }

                 // Expanded Payload
                 $payload = array(
                    'event_id' => $event_id,
                    'title' => get_the_title($event_id),
                    'content' => get_post_field('post_content', $event_id),
                    'status' => isset($clean_meta['status']) ? $clean_meta['status'] : '',
                    'meta' => $clean_meta
                 );
                 
                 $args = array(
                    'method' => $webhook_method === 'GET' ? 'GET' : 'POST',
                    'timeout' => 20,
                    'body' => $webhook_method === 'POST' ? json_encode($payload) : null,
                    'headers' => array('Content-Type' => 'application/json')
                 );
                 
                 $request_url = $webhook_url;
                 if ($webhook_method === 'GET') {
                     $request_url = add_query_arg($payload, $webhook_url);
                 }

                 $response = wp_remote_request($request_url, $args);
                 if (is_wp_error($response)) {
                     $log_message .= " - Webhook Fehler: " . $response->get_error_message();
                 } else {
                     $code = wp_remote_retrieve_response_code($response);
                     $log_message .= " - Webhook Status: $code";
                 }
            }
        } elseif ($type === 'note') {
            $note = isset($params['note']) ? sanitize_textarea_field($params['note']) : '';
            if (!empty($note)) {
                $log_message .= " - Notiz: " . $note;
                
                // Save Communication
                $comm_manager = new TMGMT_Communication_Manager();
                $comm_id = $comm_manager->add_entry($event_id, 'note', 'Intern', '', $note);
                
                $log_manager->log($event_id, 'note_added', "Notiz hinzugefügt.", null, $comm_id);
            }
        }

        // Update Status if needed
        $target_status = get_post_meta($action_id, '_tmgmt_action_target_status', true);
        if ($target_status) {
            update_post_meta($event_id, '_tmgmt_status', $target_status);
            $log_message .= " - Status geändert zu: $target_status";
        }

        $log_manager->log($event_id, 'action_executed', $log_message);

        return array(
            'success' => true,
            'message' => 'Aktion erfolgreich ausgeführt',
            'new_status' => $target_status
        );
    }

    public function handle_attachments($request) {
        $event_id = $request['id'];
        $post = get_post($event_id);
        
        if (!$post || $post->post_type !== 'event') {
            return new WP_Error('not_found', 'Event nicht gefunden', array('status' => 404));
        }

        $files = $request->get_file_params();
        $params = $request->get_json_params();
        // Fallback for multipart params
        if (!$params) $params = $request->get_body_params();

        // Debugging: Check if we received anything
        if (empty($files) && (empty($params) || empty($params['media_ids']))) {
            return new WP_Error('no_data', 'Keine Dateien oder Media-IDs empfangen.', array(
                'status' => 400, 
                'debug_files' => $files, 
                'debug_params' => $params,
                'debug_body' => $request->get_body()
            ));
        }

        $log_manager = new TMGMT_Log_Manager();
        $new_items = array();

        // 1. Handle File Uploads (Multipart)
        if (!empty($files)) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            $category = isset($params['category']) ? sanitize_text_field($params['category']) : '';

            foreach ($files as $file_key => $file) {
                // Ensure $_FILES is populated for media_handle_upload
                if (!isset($_FILES[$file_key])) {
                    $_FILES[$file_key] = $file;
                }

                $attachment_id = media_handle_upload($file_key, $event_id);
                
                if (is_wp_error($attachment_id)) {
                    return $attachment_id;
                }
                
                $new_items[] = array(
                    'id' => $attachment_id,
                    'category' => $category
                );
                $log_manager->log($event_id, 'attachment_added', 'Datei hochgeladen: ' . basename($file['name']) . ($category ? " ($category)" : ''));
            }
        }

        // 2. Handle Linking Existing Media (JSON)
        if ($params && isset($params['media_ids'])) {
            $ids = is_array($params['media_ids']) ? $params['media_ids'] : array($params['media_ids']);
            $category = isset($params['category']) ? sanitize_text_field($params['category']) : '';

            foreach ($ids as $mid) {
                if (get_post($mid)) {
                    $new_items[] = array(
                        'id' => intval($mid),
                        'category' => $category
                    );
                    $log_manager->log($event_id, 'attachment_linked', 'Datei verknüpft (ID: ' . $mid . ')' . ($category ? " ($category)" : ''));
                }
            }
        }

        // Update Meta
        if (!empty($new_items)) {
            $current_data = get_post_meta($event_id, '_tmgmt_event_attachments', true);
            $current_data = maybe_unserialize($current_data);
            if (!is_array($current_data)) $current_data = array();
            
            // Normalize current data to array of objects
            $normalized_current = array();
            foreach ($current_data as $item) {
                if (is_numeric($item)) {
                    $normalized_current[] = array('id' => intval($item), 'category' => '');
                } elseif (is_array($item) && isset($item['id'])) {
                    $normalized_current[] = $item;
                }
            }

            // Merge new items
            foreach ($new_items as $new_item) {
                $found = false;
                foreach ($normalized_current as &$existing) {
                    if ($existing['id'] === $new_item['id']) {
                        $existing['category'] = $new_item['category']; // Update category
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $normalized_current[] = $new_item;
                }
            }

            update_post_meta($event_id, '_tmgmt_event_attachments', $normalized_current);
        }

        return array(
            'success' => true,
            'message' => 'Anhänge gespeichert',
            'items' => $new_items
        );
    }

    public function delete_attachment($request) {
        $event_id = $request['id'];
        $attachment_id = intval($request['attachment_id']);
        
        $post = get_post($event_id);
        if (!$post || $post->post_type !== 'event') {
            return new WP_Error('not_found', 'Event nicht gefunden', array('status' => 404));
        }

        $current_data = get_post_meta($event_id, '_tmgmt_event_attachments', true);
        $current_data = maybe_unserialize($current_data);
        if (!is_array($current_data)) $current_data = array();

        $normalized_current = array();
        $found = false;
        $removed_item = null;

        foreach ($current_data as $item) {
            $item_id = 0;
            $item_cat = '';
            
            if (is_numeric($item)) {
                $item_id = intval($item);
            } elseif (is_array($item) && isset($item['id'])) {
                $item_id = intval($item['id']);
                $item_cat = isset($item['category']) ? $item['category'] : '';
            }

            if ($item_id === $attachment_id) {
                $found = true;
                $removed_item = array('id' => $item_id, 'category' => $item_cat);
                continue; // Skip adding this to new array
            }

            $normalized_current[] = is_array($item) ? $item : array('id' => $item_id, 'category' => '');
        }

        if ($found) {
            update_post_meta($event_id, '_tmgmt_event_attachments', $normalized_current);
            
            $log_manager = new TMGMT_Log_Manager();
            $log_manager->log($event_id, 'attachment_removed', 'Datei entfernt (ID: ' . $attachment_id . ')' . ($removed_item['category'] ? " ({$removed_item['category']})" : ''));

            return array(
                'success' => true,
                'message' => 'Anhang entfernt',
                'remaining' => $normalized_current
            );
        } else {
            return new WP_Error('not_found', 'Anhang nicht in diesem Event gefunden', array('status' => 404));
        }
    }

    public function get_email_templates($request) {
        $templates = get_posts(array(
            'post_type' => 'tmgmt_email_template',
            'numberposts' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC'
        ));

        $data = array();
        foreach ($templates as $post) {
            $data[] = array(
                'id' => $post->ID,
                'title' => $post->post_title
            );
        }

        return rest_ensure_response($data);
    }

    public function preview_email($request) {
        $event_id = $request['id'];
        $template_id = $request['template_id'];

        $event = get_post($event_id);
        if (!$event || $event->post_type !== 'event') {
            return new WP_Error('invalid_event', 'Event not found', array('status' => 404));
        }

        $recipient_raw = get_post_meta($template_id, '_tmgmt_email_recipient', true);
        $subject_raw = get_post_meta($template_id, '_tmgmt_email_subject', true);
        $body_raw = get_post_meta($template_id, '_tmgmt_email_body', true);

        // Default recipient if empty
        if (empty($recipient_raw)) {
            $recipient_raw = '[contact_email_contract]';
        }
        // Default subject if empty
        if (empty($subject_raw)) {
            $subject_raw = 'Info: [event_title]';
        }

        $recipient = TMGMT_Placeholder_Parser::parse($recipient_raw, $event_id);
        $subject = TMGMT_Placeholder_Parser::parse($subject_raw, $event_id);
        $body = TMGMT_Placeholder_Parser::parse($body_raw, $event_id);

        return rest_ensure_response(array(
            'recipient' => $recipient,
            'subject' => $subject,
            'body' => $body
        ));
    }

    public function send_email($request) {
        $event_id = $request['id'];
        $recipient = $request['recipient'];
        $subject = $request['subject'];
        $body = $request['body'];
        $attach_pdf = $request['attach_pdf'];

        $event = get_post($event_id);
        if (!$event || $event->post_type !== 'event') {
            return new WP_Error('invalid_event', 'Event not found', array('status' => 404));
        }

        $attachments = array();
        $temp_file = '';

        if ($attach_pdf) {
            if (!class_exists('TMGMT_PDF_Generator')) {
                require_once TMGMT_PLUGIN_DIR . 'includes/class-pdf-generator.php';
            }
            
            $pdf_gen = new TMGMT_PDF_Generator();
            $upload_dir = wp_upload_dir();
            $temp_dir = $upload_dir['basedir'] . '/tmgmt_temp';
            
            if (!file_exists($temp_dir)) {
                mkdir($temp_dir, 0755, true);
            }
            
            $filename = 'Setlist-' . sanitize_title($event->post_title) . '.pdf';
            $temp_file = $temp_dir . '/' . $filename;
            
            $result = $pdf_gen->generate_setlist_pdf($event_id, '', 'F', $temp_file);
            
            if (is_wp_error($result)) {
                return $result;
            }
            
            $attachments[] = $temp_file;
        }

        $headers = array('Content-Type: text/html; charset=UTF-8');
        $sent = wp_mail($recipient, $subject, nl2br($body), $headers, $attachments);

        // Cleanup
        if ($temp_file && file_exists($temp_file)) {
            unlink($temp_file);
        }

        if ($sent) {
            $log_manager = new TMGMT_Log_Manager();
            $comm_manager = new TMGMT_Communication_Manager();
            
            $comm_id = $comm_manager->add_entry($event_id, 'email', $recipient, $subject, $body);
            $log_manager->log($event_id, 'email_sent', "E-Mail '$subject' an $recipient gesendet." . ($attach_pdf ? " (mit PDF)" : ""), null, $comm_id);
            
            return rest_ensure_response(array('success' => true, 'message' => 'E-Mail erfolgreich versendet.'));
        } else {
            return new WP_Error('email_failed', 'E-Mail konnte nicht gesendet werden.', array('status' => 500));
        }
    }

    private function generate_event_pdf($event_id) {
        // Implement PDF generation logic here
        // This is a placeholder function. You should replace this with actual PDF generation code.
        
        $event = get_post($event_id);
        if (!$event || $event->post_type !== 'event') {
            return new WP_Error('not_found', 'Event nicht gefunden', array('status' => 404));
        }

        // Example: Generate a simple PDF with event title and content
        $pdf_content = "<h1>" . get_the_title($event_id) . "</h1>";
        $pdf_content .= "<div>" . apply_filters('the_content', $event->post_content) . "</div>";

        // Convert to PDF (using a library like TCPDF, FPDF, etc.)
        // For this example, we'll just return the HTML content as a string.
        return $pdf_content;
    }

    // --- IMAP Ticket-System Callback Methods ---

    /**
     * Permission check for admin-only endpoints.
     */
    public function check_admin_permission($request) {
        return current_user_can('manage_options');
    }

    /**
     * GET /mail-queue — List mail queue entries filtered by status.
     */
    public function get_mail_queue($request) {
        $status = sanitize_text_field($request->get_param('status'));
        $queue = new TMGMT_Mail_Queue();

        if (!empty($status)) {
            $entries = $queue->get_by_status($status);
        } else {
            $entries = $queue->get_by_status('neu');
        }

        return rest_ensure_response(array(
            'entries' => $entries,
            'total'   => count($entries),
        ));
    }

    /**
     * GET /mail-queue/{id} — Single mail queue entry.
     */
    public function get_mail_queue_entry($request) {
        $id = (int) $request['id'];
        $queue = new TMGMT_Mail_Queue();
        $entry = $queue->get_by_id($id);

        if (!$entry) {
            return new WP_Error('not_found', 'E-Mail nicht gefunden.', array('status' => 404));
        }

        return rest_ensure_response($entry);
    }

    /**
     * POST /mail-queue/{id}/assign — Manual assignment to an event.
     */
    public function assign_mail_queue_entry($request) {
        $id = (int) $request['id'];
        $params = $request->get_json_params();
        $event_id = isset($params['event_id']) ? (int) $params['event_id'] : 0;

        if ($event_id <= 0) {
            return new WP_Error('invalid_event', 'Ungültige Event-ID.', array('status' => 400));
        }

        $assigner = new TMGMT_Mail_Assigner();
        $result = $assigner->assign_single($id, $event_id);

        if ($result) {
            return rest_ensure_response(array('success' => true, 'message' => 'E-Mail erfolgreich zugeordnet.'));
        }

        return new WP_Error('assign_failed', 'Zuordnung fehlgeschlagen.', array('status' => 500));
    }

    /**
     * POST /mail-queue/{id}/create-event — Create a new event from an email.
     */
    public function create_event_from_email($request) {
        $id = (int) $request['id'];

        $queue = new TMGMT_Mail_Queue();
        $email = $queue->get_by_id($id);

        if (!$email) {
            return new WP_Error('not_found', 'E-Mail nicht gefunden.', array('status' => 404));
        }

        if ($email->event_id > 0) {
            return new WP_Error('already_assigned', 'E-Mail ist bereits einem Event zugeordnet.', array('status' => 400));
        }

        // Create the event with email subject as title
        $title = !empty($email->subject) ? $email->subject : 'Neues Event (aus E-Mail)';
        
        $post_id = wp_insert_post(array(
            'post_title'  => sanitize_text_field($title),
            'post_type'   => 'event',
            'post_status' => 'publish',
        ));

        if (is_wp_error($post_id)) {
            return new WP_Error('create_failed', 'Event konnte nicht erstellt werden.', array('status' => 500));
        }

        // Set default status (first status of first column)
        $col_posts = get_posts(array(
            'post_type' => 'tmgmt_kanban_col',
            'posts_per_page' => 1,
            'meta_key' => '_tmgmt_kanban_order',
            'orderby' => 'meta_value_num',
            'order' => 'ASC'
        ));

        if (!empty($col_posts)) {
            $status_ids = get_post_meta($col_posts[0]->ID, '_tmgmt_kanban_statuses', true);
            if (is_array($status_ids) && !empty($status_ids)) {
                $first_status_post = get_post($status_ids[0]);
                if ($first_status_post) {
                    update_post_meta($post_id, '_tmgmt_status', $first_status_post->post_name);
                }
            }
        }

        // Set inquiry date to email date
        if (!empty($email->email_date)) {
            update_post_meta($post_id, '_tmgmt_inquiry_date', date('Y-m-d\TH:i', strtotime($email->email_date)));
        } else {
            update_post_meta($post_id, '_tmgmt_inquiry_date', current_time('Y-m-d\TH:i'));
        }

        // Try to find existing Veranstalter/Contact by email, or create new one
        $veranstalter_id = $this->find_or_create_veranstalter_from_email($email);
        if ($veranstalter_id) {
            update_post_meta($post_id, '_tmgmt_event_veranstalter_id', $veranstalter_id);
        }

        // Store email body as event notes/description
        $content = !empty($email->body_text) ? $email->body_text : wp_strip_all_tags($email->body_html);
        if (!empty($content)) {
            wp_update_post(array(
                'ID' => $post_id,
                'post_content' => wp_kses_post($content),
            ));
        }

        // Log creation
        $log_manager = new TMGMT_Log_Manager();
        $log_manager->log($post_id, 'api_create', 'Event aus E-Mail erstellt');

        // Assign the email to the new event
        $assigner = new TMGMT_Mail_Assigner();
        $assigner->assign_single($id, $post_id, 'email_to_event');

        return rest_ensure_response(array(
            'success'  => true,
            'event_id' => $post_id,
            'message'  => 'Event erfolgreich erstellt und E-Mail zugeordnet.',
            'edit_url' => get_edit_post_link($post_id, 'raw'),
        ));
    }

    /**
     * Find existing Veranstalter by contact email, or create a new one.
     *
     * @param object $email The mail queue entry.
     * @return int|null The Veranstalter ID or null.
     */
    private function find_or_create_veranstalter_from_email($email): ?int {
        if (empty($email->from_email)) {
            return null;
        }

        // 1. Try to find existing contact with this email
        $contacts = get_posts(array(
            'post_type'      => 'tmgmt_contact',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_query'     => array(
                array(
                    'key'     => '_tmgmt_contact_email',
                    'value'   => $email->from_email,
                    'compare' => '=',
                ),
            ),
            'fields' => 'ids',
        ));

        $existing_contact_id = null;

        if (!empty($contacts)) {
            $existing_contact_id = (int) $contacts[0];
            
            // Find Veranstalter that has this contact assigned
            $veranstalter_posts = get_posts(array(
                'post_type'      => 'tmgmt_veranstalter',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
            ));

            foreach ($veranstalter_posts as $v_id) {
                $assignments = get_post_meta($v_id, '_tmgmt_veranstalter_contacts', true);
                if (!is_array($assignments)) {
                    continue;
                }
                foreach ($assignments as $assignment) {
                    $cid = isset($assignment['contact_id']) ? intval($assignment['contact_id']) : 0;
                    if ($cid === $existing_contact_id) {
                        return (int) $v_id; // Found existing Veranstalter
                    }
                }
            }
        }

        // 2. No existing Veranstalter found - use existing contact or create new one
        $contact_id = $existing_contact_id;
        
        if (!$contact_id) {
            // Create new Contact
            $contact_name = !empty($email->from_name) ? $email->from_name : $email->from_email;
            $contact_id = wp_insert_post(array(
                'post_title'  => sanitize_text_field($contact_name),
                'post_type'   => 'tmgmt_contact',
                'post_status' => 'publish',
            ));

            if (is_wp_error($contact_id) || !$contact_id) {
                return null;
            }

            // Set contact meta
            update_post_meta($contact_id, '_tmgmt_contact_email', sanitize_email($email->from_email));
            
            if (!empty($email->from_name)) {
                $name_parts = explode(' ', $email->from_name, 2);
                if (count($name_parts) >= 2) {
                    update_post_meta($contact_id, '_tmgmt_contact_firstname', sanitize_text_field($name_parts[0]));
                    update_post_meta($contact_id, '_tmgmt_contact_lastname', sanitize_text_field($name_parts[1]));
                } else {
                    update_post_meta($contact_id, '_tmgmt_contact_lastname', sanitize_text_field($email->from_name));
                }
            }
        }

        // Create Veranstalter
        $veranstalter_name = !empty($email->from_name) ? $email->from_name : $email->from_email;
        $veranstalter_id = wp_insert_post(array(
            'post_title'  => sanitize_text_field($veranstalter_name),
            'post_type'   => 'tmgmt_veranstalter',
            'post_status' => 'publish',
        ));

        if (is_wp_error($veranstalter_id) || !$veranstalter_id) {
            return null;
        }

        // Assign contact to Veranstalter with role "vertrag"
        $contacts_assignment = array(
            array(
                'contact_id' => $contact_id,
                'role'       => 'vertrag',
            ),
        );
        update_post_meta($veranstalter_id, '_tmgmt_veranstalter_contacts', $contacts_assignment);

        return (int) $veranstalter_id;
    }

    /**
     * POST /veranstalter — Create a new Veranstalter.
     */
    public function create_veranstalter($request) {
        $params = $request->get_json_params();
        $name = isset($params['name']) ? sanitize_text_field($params['name']) : '';

        if (empty($name)) {
            return new WP_Error('invalid_name', 'Name ist erforderlich.', array('status' => 400));
        }

        $veranstalter_id = wp_insert_post(array(
            'post_title'  => $name,
            'post_type'   => 'tmgmt_veranstalter',
            'post_status' => 'publish',
        ));

        if (is_wp_error($veranstalter_id)) {
            return new WP_Error('create_failed', 'Veranstalter konnte nicht erstellt werden.', array('status' => 500));
        }

        // Set optional address fields
        $address_fields = array(
            'street'  => '_tmgmt_veranstalter_street',
            'number'  => '_tmgmt_veranstalter_number',
            'zip'     => '_tmgmt_veranstalter_zip',
            'city'    => '_tmgmt_veranstalter_city',
            'country' => '_tmgmt_veranstalter_country',
        );

        foreach ($address_fields as $param => $meta_key) {
            if (isset($params[$param])) {
                update_post_meta($veranstalter_id, $meta_key, sanitize_text_field($params[$param]));
            }
        }

        return rest_ensure_response(array(
            'success' => true,
            'id'      => $veranstalter_id,
            'title'   => $name,
            'message' => 'Veranstalter erfolgreich erstellt.',
        ));
    }

    /**
     * POST /locations — Create a new Location.
     */
    public function create_location($request) {
        $params = $request->get_json_params();
        $name = isset($params['name']) ? sanitize_text_field($params['name']) : '';

        if (empty($name)) {
            return new WP_Error('invalid_name', 'Name ist erforderlich.', array('status' => 400));
        }

        $location_id = wp_insert_post(array(
            'post_title'  => $name,
            'post_type'   => 'tmgmt_location',
            'post_status' => 'publish',
        ));

        if (is_wp_error($location_id)) {
            return new WP_Error('create_failed', 'Ort konnte nicht erstellt werden.', array('status' => 500));
        }

        // Set optional address fields
        $address_fields = array(
            'street'  => '_tmgmt_location_street',
            'number'  => '_tmgmt_location_number',
            'zip'     => '_tmgmt_location_zip',
            'city'    => '_tmgmt_location_city',
            'country' => '_tmgmt_location_country',
            'lat'     => '_tmgmt_location_lat',
            'lng'     => '_tmgmt_location_lng',
            'notes'   => '_tmgmt_location_notes',
        );

        foreach ($address_fields as $param => $meta_key) {
            if (isset($params[$param])) {
                update_post_meta($location_id, $meta_key, sanitize_text_field($params[$param]));
            }
        }

        return rest_ensure_response(array(
            'success'  => true,
            'id'       => $location_id,
            'title'    => $name,
            'street'   => isset($params['street']) ? $params['street'] : '',
            'number'   => isset($params['number']) ? $params['number'] : '',
            'zip'      => isset($params['zip']) ? $params['zip'] : '',
            'city'     => isset($params['city']) ? $params['city'] : '',
            'country'  => isset($params['country']) ? $params['country'] : '',
            'lat'      => isset($params['lat']) ? $params['lat'] : '',
            'lng'      => isset($params['lng']) ? $params['lng'] : '',
            'message'  => 'Ort erfolgreich erstellt.',
        ));
    }

    /**
     * POST /mail-queue/{id}/reply — Send reply to an email.
     */
    public function reply_mail_queue_entry($request) {
        $id = (int) $request['id'];
        $params = $request->get_json_params();
        $body = isset($params['body']) ? wp_kses_post($params['body']) : '';

        if (empty($body)) {
            return new WP_Error('empty_body', 'Antworttext darf nicht leer sein.', array('status' => 400));
        }

        // Get the event_id from the mail queue entry
        $queue = new TMGMT_Mail_Queue();
        $entry = $queue->get_by_id($id);

        if (!$entry) {
            return new WP_Error('not_found', 'E-Mail nicht gefunden.', array('status' => 404));
        }

        $event_id = (int) $entry->event_id;
        if ($event_id <= 0) {
            return new WP_Error('not_assigned', 'E-Mail ist keinem Event zugeordnet.', array('status' => 400));
        }

        $handler = new TMGMT_Reply_Handler();
        $result = $handler->send_reply($id, $event_id, $body);

        if ($result['success']) {
            return rest_ensure_response($result);
        }

        return new WP_Error('reply_failed', $result['message'], array('status' => 500));
    }

    /**
     * GET /events/{id}/tickets — IMAP emails for an event.
     */
    public function get_event_tickets($request) {
        $event_id = (int) $request['id'];
        global $wpdb;

        $comm_table = $wpdb->prefix . 'tmgmt_communication';
        $queue_table = $wpdb->prefix . 'tmgmt_mail_queue';

        // Get communication entries of type imap_email or imap_reply for this event
        $entries = $wpdb->get_results($wpdb->prepare(
            "SELECT c.id, c.type, c.recipient, c.subject, c.content, c.created_at
             FROM $comm_table c
             WHERE c.event_id = %d AND c.type IN ('imap_email', 'imap_reply')
             ORDER BY c.created_at ASC",
            $event_id
        ));

        $tickets = array();
        foreach ($entries as $entry) {
            $preview = wp_trim_words(wp_strip_all_tags($entry->content), 30, '...');

            // Try to find matching mail queue entry for additional data
            $queue_entry = $wpdb->get_row($wpdb->prepare(
                "SELECT id, from_email, from_name, message_id
                 FROM $queue_table
                 WHERE event_id = %d AND subject = %s
                 LIMIT 1",
                $event_id,
                $entry->subject
            ));

            $tickets[] = array(
                'id'         => (int) $entry->id,
                'queue_id'   => $queue_entry ? (int) $queue_entry->id : null,
                'type'       => $entry->type,
                'from_email' => $queue_entry ? $queue_entry->from_email : $entry->recipient,
                'from_name'  => $queue_entry ? $queue_entry->from_name : '',
                'subject'    => $entry->subject,
                'date'       => $entry->created_at,
                'preview'    => $preview,
                'body_html'  => $entry->content,
            );
        }

        return rest_ensure_response(array(
            'tickets' => $tickets,
            'total'   => count($tickets),
        ));
    }

    /**
     * POST /imap/test — Test IMAP connection.
     */
    public function test_imap_connection($request) {
        $connector = new TMGMT_IMAP_Connector();
        $result = $connector->test_connection();
        return rest_ensure_response($result);
    }

    /**
     * POST /smtp/test — Test SMTP connection.
     */
    public function test_smtp_connection($request) {
        $sender = new TMGMT_SMTP_Sender();
        $result = $sender->test_connection();
        return rest_ensure_response($result);
    }

    /**
     * POST /imap/fetch-now — Manual immediate IMAP fetch.
     */
    public function imap_fetch_now($request) {
        $debug = array();
        
        try {
            $debug[] = 'Starting fetch';
            $connector = new TMGMT_IMAP_Connector();

            $debug[] = 'Connecting...';
            if (!$connector->connect()) {
                return new WP_Error('imap_error', 'IMAP-Verbindung fehlgeschlagen.', array('status' => 500));
            }
            $debug[] = 'Connected';

            $debug[] = 'Fetching emails...';
            $emails = $connector->fetch_unread_emails();
            $debug[] = 'Fetched ' . count($emails) . ' emails';
            
            $queue = new TMGMT_Mail_Queue();
            $inserted = 0;
            $errors = array();

            foreach ($emails as $index => $email_data) {
                try {
                    $debug[] = 'Processing email ' . ($index + 1);
                    $uid = $email_data['uid'] ?? null;
                    $message = $email_data['_message'] ?? null;
                    unset($email_data['uid']);
                    unset($email_data['_message']);

                    $result = $queue->insert($email_data);
                    if ($result) {
                        $inserted++;
                        // Mark as read using the message object if available
                        if ($message) {
                            try {
                                $connector->mark_message_as_read($message);
                            } catch (\Exception $e) {
                                // Ignore mark as read errors
                            }
                        }
                    }
                    $debug[] = 'Email ' . ($index + 1) . ' processed, result: ' . ($result ? 'inserted' : 'skipped');
                } catch (\Exception $e) {
                    $errors[] = 'Email ' . ($index + 1) . ': ' . $e->getMessage();
                }
            }

            $debug[] = 'Disconnecting...';
            $connector->disconnect();
            $debug[] = 'Disconnected';

            // Trigger assignment
            $debug[] = 'Running assigner...';
            if (class_exists('TMGMT_Mail_Assigner')) {
                try {
                    $assigner = new TMGMT_Mail_Assigner();
                    $assigner->assign_new_emails();
                    $debug[] = 'Assigner completed';
                } catch (\Exception $e) {
                    $errors[] = 'Assigner: ' . $e->getMessage();
                }
            }

            return rest_ensure_response(array(
                'success'  => true,
                'fetched'  => count($emails),
                'inserted' => $inserted,
                'message'  => sprintf('%d E-Mails abgerufen, %d neu eingefügt.', count($emails), $inserted),
                'debug'    => $debug,
                'errors'   => $errors,
            ));
        } catch (\Exception $e) {
            return new WP_Error('imap_error', 'Fehler: ' . $e->getMessage() . ' | Debug: ' . implode(' -> ', $debug), array('status' => 500));
        }
    }
}
