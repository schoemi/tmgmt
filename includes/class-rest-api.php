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
            $city = get_post_meta($event->ID, '_tmgmt_venue_city', true);
            
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

        return array(
            'id' => $event_id,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'meta' => $clean_meta,
            'logs' => $formatted_logs,
            'actions' => $actions,
            'attachments' => $attachments
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
        if ($type !== 'email') {
            return new WP_Error('invalid_type', 'Vorschau nur für E-Mails verfügbar', array('status' => 400));
        }

        $email_template_id = get_post_meta($action_id, '_tmgmt_action_email_template_id', true);
        if (!$email_template_id) {
            return new WP_Error('no_template', 'Keine E-Mail Vorlage definiert', array('status' => 400));
        }

        $subject_raw = get_post_meta($email_template_id, '_tmgmt_email_subject', true);
        $body_raw = get_post_meta($email_template_id, '_tmgmt_email_body', true);
        $recipient_raw = get_post_meta($email_template_id, '_tmgmt_email_recipient', true);

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

        if ($type === 'email') {
            // Use provided subject/body or fallback to template
            $subject = isset($params['email_subject']) ? $params['email_subject'] : '';
            $body = isset($params['email_body']) ? $params['email_body'] : '';
            $recipient = isset($params['email_recipient']) ? $params['email_recipient'] : '';
            
            // If not provided (e.g. direct execution without preview), parse from template
            if (empty($subject) || empty($body) || empty($recipient)) {
                $email_template_id = get_post_meta($action_id, '_tmgmt_action_email_template_id', true);
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

            $sent = wp_mail($recipient, $subject, nl2br($body), $headers);
            if ($sent) {
                $log_message .= " - E-Mail gesendet an: $recipient";
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
}
