<?php
/**
 * Setlist Post Type
 *
 * Registers the 'tmgmt_setlist' custom post type.
 */

if (!defined('ABSPATH')) {
    exit;
}

class TMGMT_Setlist_Post_Type {

    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_tmgmt_search_titles', array($this, 'ajax_search_titles'));
        
        // List Table Filters
        add_filter('views_edit-tmgmt_setlist', array($this, 'add_list_views'));
        add_action('pre_get_posts', array($this, 'filter_list_by_type'));
        
        // Custom Actions
        add_action('admin_post_tmgmt_duplicate_setlist', array($this, 'handle_duplicate_setlist'));
        add_action('wp_ajax_tmgmt_create_custom_setlist', array($this, 'ajax_create_custom_setlist'));
    }

    public function register_post_type() {
        $labels = array(
            'name'               => __('Setlists', 'toens-mgmt'),
            'singular_name'      => __('Setlist', 'toens-mgmt'),
            'menu_name'          => __('Setlists', 'toens-mgmt'),
            'name_admin_bar'     => __('Setlist', 'toens-mgmt'),
            'add_new'            => __('Neue Setlist', 'toens-mgmt'),
            'add_new_item'       => __('Neue Setlist hinzufügen', 'toens-mgmt'),
            'new_item'           => __('Neue Setlist', 'toens-mgmt'),
            'edit_item'          => __('Setlist bearbeiten', 'toens-mgmt'),
            'view_item'          => __('Setlist ansehen', 'toens-mgmt'),
            'all_items'          => __('Alle Setlists', 'toens-mgmt'),
            'search_items'       => __('Setlists suchen', 'toens-mgmt'),
            'not_found'          => __('Keine Setlists gefunden.', 'toens-mgmt'),
            'not_found_in_trash' => __('Keine Setlists im Papierkorb gefunden.', 'toens-mgmt')
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => 'edit.php?post_type=event',
            'query_var'          => true,
            'rewrite'            => array('slug' => 'tmgmt-setlist'),
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 21,
            'supports'           => array('title'),
            'show_in_rest'       => false,
        );

        register_post_type('tmgmt_setlist', $args);
    }

    public function enqueue_scripts($hook) {
        global $post;
        if (($hook === 'post-new.php' || $hook === 'post.php') && $post->post_type === 'tmgmt_setlist') {
            wp_enqueue_script('jquery-ui-sortable');
            wp_enqueue_script('tmgmt-setlist-js', TMGMT_PLUGIN_URL . 'assets/js/setlist-manager.js', array('jquery', 'jquery-ui-sortable'), TMGMT_VERSION, true);
            wp_localize_script('tmgmt-setlist-js', 'tmgmtSetlist', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('tmgmt_setlist_nonce')
            ));
            
            // Add some CSS for the table
            wp_add_inline_style('common', '
                .tmgmt-setlist-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                .tmgmt-setlist-table th, .tmgmt-setlist-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                .tmgmt-setlist-table th { background-color: #f9f9f9; }
                .tmgmt-setlist-row { cursor: move; background: #fff; }
                .tmgmt-setlist-row:hover { background: #f1f1f1; }
                .tmgmt-remove-title { color: #a00; cursor: pointer; text-decoration: none; }
                .tmgmt-remove-title:hover { color: #d00; }
                #tmgmt-title-search-results { 
                    position: absolute; 
                    background: #fff; 
                    border: 1px solid #ccc; 
                    width: 100%; 
                    max-height: 200px; 
                    overflow-y: auto; 
                    z-index: 1000; 
                    display: none;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                }
                .tmgmt-search-result { padding: 8px; cursor: pointer; border-bottom: 1px solid #eee; }
                .tmgmt-search-result:hover { background: #f0f0f1; }
                .tmgmt-search-result:last-child { border-bottom: none; }
            ');
        }
    }

    public function add_meta_boxes() {
        add_meta_box(
            'tmgmt_setlist_details',
            'Setlist Einstellungen',
            array($this, 'render_details_box'),
            'tmgmt_setlist',
            'side',
            'high'
        );

        add_meta_box(
            'tmgmt_setlist_titles',
            'Titel Liste',
            array($this, 'render_titles_box'),
            'tmgmt_setlist',
            'normal',
            'high'
        );
    }

    public function render_details_box($post) {
        $type = get_post_meta($post->ID, '_tmgmt_setlist_type', true);
        if (!$type) $type = 'standard'; // Default
        
        $session = get_post_meta($post->ID, '_tmgmt_setlist_session', true);
        $event_id = get_post_meta($post->ID, '_tmgmt_setlist_event', true);
        $duration = get_post_meta($post->ID, '_tmgmt_setlist_duration', true); 
        
        ?>
        <p>
            <label for="tmgmt_setlist_type"><strong>Typ</strong></label><br>
            <select name="tmgmt_setlist_type" id="tmgmt_setlist_type" style="width:100%;">
                <option value="standard" <?php selected($type, 'standard'); ?>>Standard-Setlist</option>
                <option value="custom" <?php selected($type, 'custom'); ?>>Custom-Setlist</option>
                <option value="template" <?php selected($type, 'template'); ?>>Setlist-Vorlage</option>
            </select>
        </p>

        <?php if ($type === 'custom') : ?>
            <?php
            $events = get_posts(array('post_type' => 'event', 'numberposts' => -1, 'post_status' => 'any'));
            $dates = array();
            $events_data = array();
            $wp_date_format = get_option('date_format');
            
            foreach ($events as $evt) {
                $raw_date = get_post_meta($evt->ID, '_tmgmt_event_date', true);
                $display_date = 'Unbekannt';
                $sort_key = '9999-99-99';

                if ($raw_date) {
                    $timestamp = false;
                    // Try d.m.Y
                    $dt = DateTime::createFromFormat('d.m.Y', $raw_date);
                    if ($dt) {
                        $timestamp = $dt->getTimestamp();
                    } else {
                        // Try Y-m-d
                        $dt = DateTime::createFromFormat('Y-m-d', $raw_date);
                        if ($dt) {
                            $timestamp = $dt->getTimestamp();
                        }
                    }

                    if ($timestamp) {
                        $sort_key = date('Y-m-d', $timestamp);
                        $display_date = date_i18n($wp_date_format, $timestamp);
                    } else {
                        $sort_key = $raw_date;
                        $display_date = $raw_date;
                    }
                }
                
                $dates[$sort_key] = $display_date;
                
                $events_data[] = array(
                    'id' => $evt->ID,
                    'title' => $evt->post_title,
                    'date' => $display_date,
                    'sort_date' => $sort_key
                );
            }
            ksort($dates); // Sort dates chronologically
            
            // Determine current date selection
            $current_event_date_display = '';
            if ($event_id) {
                $raw_current_date = get_post_meta($event_id, '_tmgmt_event_date', true);
                if ($raw_current_date) {
                     $timestamp = false;
                     $dt = DateTime::createFromFormat('d.m.Y', $raw_current_date);
                     if ($dt) $timestamp = $dt->getTimestamp();
                     else {
                         $dt = DateTime::createFromFormat('Y-m-d', $raw_current_date);
                         if ($dt) $timestamp = $dt->getTimestamp();
                     }
                     
                     if ($timestamp) {
                         $current_event_date_display = date_i18n($wp_date_format, $timestamp);
                     } else {
                         $current_event_date_display = $raw_current_date;
                     }
                }
            }
            ?>
            <p>
                <label for="tmgmt_setlist_event_date_filter"><strong>Datum filtern</strong></label><br>
                <select id="tmgmt_setlist_event_date_filter" style="width:100%;">
                    <option value="">-- Alle Daten --</option>
                    <?php foreach ($dates as $sort_key => $display_date) : ?>
                        <option value="<?php echo esc_attr($display_date); ?>" <?php selected($current_event_date_display, $display_date); ?>><?php echo esc_html($display_date); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p>
                <label for="tmgmt_setlist_event"><strong>Auftritt</strong></label><br>
                <select name="tmgmt_setlist_event" id="tmgmt_setlist_event" style="width:100%;">
                    <option value="">-- Auftritt wählen --</option>
                    <?php
                    // Sort events by date then title
                    usort($events_data, function($a, $b) {
                        if ($a['sort_date'] === $b['sort_date']) {
                            return strcmp($a['title'], $b['title']);
                        }
                        return strcmp($a['sort_date'], $b['sort_date']);
                    });

                    foreach ($events_data as $evt) {
                        echo '<option value="' . $evt['id'] . '" data-date="' . esc_attr($evt['date']) . '" ' . selected($event_id, $evt['id'], false) . '>' . esc_html($evt['title']) . '</option>';
                    }
                    ?>
                </select>
            </p>
            <script>
            jQuery(document).ready(function($) {
                const dateFilter = $('#tmgmt_setlist_event_date_filter');
                const eventSelect = $('#tmgmt_setlist_event');
                // Store original options
                const allOptions = eventSelect.find('option').clone();

                function filterEvents() {
                    const selectedDate = dateFilter.val();
                    const currentVal = eventSelect.val();
                    
                    eventSelect.empty();
                    
                    allOptions.each(function() {
                        const optDate = $(this).data('date');
                        // Always include the placeholder (empty value)
                        if ($(this).val() === '') {
                            eventSelect.append($(this).clone());
                            return;
                        }
                        
                        if (!selectedDate || optDate === selectedDate) {
                            eventSelect.append($(this).clone());
                        }
                    });
                    
                    // Restore value if it still exists in the filtered list
                    if (currentVal && eventSelect.find('option[value="' + currentVal + '"]').length) {
                        eventSelect.val(currentVal);
                    } else {
                        eventSelect.val('');
                    }
                }

                dateFilter.on('change', filterEvents);
                
                // Trigger once on load to filter if a date is already selected (e.g. from saved state)
                if (dateFilter.val()) {
                    filterEvents();
                }
            });
            </script>
        <?php else : ?>
            <p>
                <label for="tmgmt_setlist_session"><strong>Session / Saison</strong></label><br>
                <input type="text" name="tmgmt_setlist_session" id="tmgmt_setlist_session" value="<?php echo esc_attr($session); ?>" style="width:100%;" placeholder="z.B. 2024/2025">
            </p>
        <?php endif; ?>

        <p>
            <label for="tmgmt_setlist_duration_display"><strong>Gesamtspielzeit</strong></label><br>
            <input type="text" id="tmgmt_setlist_duration_display" value="<?php echo esc_attr($duration); ?>" readonly style="width:100%; background:#eee; border:none; font-weight:bold;">
            <input type="hidden" name="tmgmt_setlist_duration" id="tmgmt_setlist_duration" value="<?php echo esc_attr($duration); ?>">
        </p>

        <?php if ($type === 'template') : ?>
            <hr>
            <p>
                <a href="<?php echo esc_url(admin_url('admin-post.php?action=tmgmt_duplicate_setlist&post=' . $post->ID . '&_wpnonce=' . wp_create_nonce('tmgmt_duplicate_setlist_' . $post->ID))); ?>" class="button button-primary" style="width:100%; text-align:center;">Custom-Setlist erstellen</a>
            </p>
        <?php endif; ?>
        <?php
    }

    public function render_titles_box($post) {
        wp_nonce_field('tmgmt_save_setlist_meta', 'tmgmt_setlist_meta_nonce');

        $titles_json = get_post_meta($post->ID, '_tmgmt_setlist_titles', true);
        $titles = json_decode($titles_json, true);
        if (!is_array($titles)) $titles = array();
        ?>
        <div style="margin-bottom: 15px; position: relative;">
            <input type="text" id="tmgmt-title-search" placeholder="Titel suchen..." style="width: 300px; padding: 8px;">
            <div id="tmgmt-title-search-results"></div>
            <button type="button" class="button" id="tmgmt-add-title-btn" disabled>Hinzufügen</button>
            <span class="description">Suchen Sie nach Titeln und fügen Sie sie der Liste hinzu.</span>
        </div>

        <table class="tmgmt-setlist-table">
            <thead>
                <tr>
                    <th style="width: 30px;">#</th>
                    <th>Titel</th>
                    <th>Interpret</th>
                    <th style="width: 100px;">Dauer</th>
                    <th style="width: 50px;"></th>
                </tr>
            </thead>
            <tbody id="tmgmt-setlist-tbody">
                <?php
                if (!empty($titles)) {
                    foreach ($titles as $index => $item) {
                        $title_id = $item['id'];
                        // Fetch current data in case it changed? Or use stored data?
                        // Better fetch current data to be always up to date.
                        // But if we want to snapshot, we use stored.
                        // Let's fetch current data for display, but keep ID reference.
                        $post_title = get_the_title($title_id);
                        $artist = get_post_meta($title_id, '_tmgmt_title_artist', true);
                        $duration = get_post_meta($title_id, '_tmgmt_title_duration', true);
                        
                        echo '<tr class="tmgmt-setlist-row" data-id="' . esc_attr($title_id) . '" data-duration="' . esc_attr($duration) . '">';
                        echo '<td><span class="dashicons dashicons-menu" style="color:#ccc;"></span><input type="hidden" name="tmgmt_setlist_titles[]" value="' . esc_attr($title_id) . '"></td>';
                        echo '<td>' . esc_html($post_title) . '</td>';
                        echo '<td>' . esc_html($artist) . '</td>';
                        echo '<td>' . esc_html($duration) . '</td>';
                        echo '<td><a href="#" class="tmgmt-remove-title"><span class="dashicons dashicons-trash"></span></a></td>';
                        echo '</tr>';
                    }
                }
                ?>
            </tbody>
        </table>
        
        <div style="margin-top: 10px; text-align: right; color: #666;">
            <small>Drag & Drop zum Sortieren</small>
        </div>
        <?php
    }

    public function save_meta_boxes($post_id) {
        if (!isset($_POST['tmgmt_setlist_meta_nonce']) || !wp_verify_nonce($_POST['tmgmt_setlist_meta_nonce'], 'tmgmt_save_setlist_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['tmgmt_setlist_type'])) {
            update_post_meta($post_id, '_tmgmt_setlist_type', sanitize_text_field($_POST['tmgmt_setlist_type']));
        }
        if (isset($_POST['tmgmt_setlist_session'])) {
            update_post_meta($post_id, '_tmgmt_setlist_session', sanitize_text_field($_POST['tmgmt_setlist_session']));
        }
        if (isset($_POST['tmgmt_setlist_event'])) {
            update_post_meta($post_id, '_tmgmt_setlist_event', sanitize_text_field($_POST['tmgmt_setlist_event']));
        }
        if (isset($_POST['tmgmt_setlist_duration'])) {
            update_post_meta($post_id, '_tmgmt_setlist_duration', sanitize_text_field($_POST['tmgmt_setlist_duration']));
        }
        
        // Save Titles
        if (isset($_POST['tmgmt_setlist_titles']) && is_array($_POST['tmgmt_setlist_titles'])) {
            $titles_data = array();
            foreach ($_POST['tmgmt_setlist_titles'] as $title_id) {
                $titles_data[] = array('id' => sanitize_text_field($title_id));
            }
            update_post_meta($post_id, '_tmgmt_setlist_titles', wp_json_encode($titles_data));
        } else {
            // If not set (e.g. all deleted), save empty array
            // But check if the nonce was sent to ensure we are in the correct form submission
            // The nonce check at the top handles this.
            update_post_meta($post_id, '_tmgmt_setlist_titles', '[]');
        }
    }

    public function ajax_search_titles() {
        check_ajax_referer('tmgmt_setlist_nonce', 'nonce');

        $term = isset($_GET['term']) ? sanitize_text_field($_GET['term']) : '';
        
        $args = array(
            'post_type' => 'tmgmt_title',
            'post_status' => 'publish', // Or any?
            's' => $term,
            'posts_per_page' => 20
        );

        $query = new WP_Query($args);
        $results = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $id = get_the_ID();
                $artist = get_post_meta($id, '_tmgmt_title_artist', true);
                $duration = get_post_meta($id, '_tmgmt_title_duration', true);
                
                $results[] = array(
                    'id' => $id,
                    'title' => get_the_title(),
                    'artist' => $artist,
                    'duration' => $duration
                );
            }
        }
        
        wp_send_json_success($results);
    }

    public function add_list_views($views) {
        $current = isset($_GET['setlist_type']) ? $_GET['setlist_type'] : 'all';
        
        // All
        $views['all'] = sprintf(
            '<a href="%s" class="%s">%s</a>',
            remove_query_arg('setlist_type'),
            $current === 'all' ? 'current' : '',
            __('Alle', 'toens-mgmt')
        );
        
        // Standard
        $views['standard'] = sprintf(
            '<a href="%s" class="%s">%s</a>',
            add_query_arg('setlist_type', 'standard'),
            $current === 'standard' ? 'current' : '',
            __('Standard', 'toens-mgmt')
        );
        
        // Custom
        $views['custom'] = sprintf(
            '<a href="%s" class="%s">%s</a>',
            add_query_arg('setlist_type', 'custom'),
            $current === 'custom' ? 'current' : '',
            __('Custom', 'toens-mgmt')
        );
        
        // Template
        $views['template'] = sprintf(
            '<a href="%s" class="%s">%s</a>',
            add_query_arg('setlist_type', 'template'),
            $current === 'template' ? 'current' : '',
            __('Vorlagen', 'toens-mgmt')
        );
        
        return $views;
    }

    public function filter_list_by_type($query) {
        global $pagenow;
        
        if ($pagenow === 'edit.php' && $query->is_main_query() && $query->get('post_type') === 'tmgmt_setlist') {
            if (isset($_GET['setlist_type']) && in_array($_GET['setlist_type'], array('standard', 'custom', 'template'))) {
                $query->set('meta_key', '_tmgmt_setlist_type');
                $query->set('meta_value', $_GET['setlist_type']);
            }
        }
    }

    public function handle_duplicate_setlist() {
        if (!isset($_GET['post']) || !isset($_GET['_wpnonce'])) {
            wp_die('Missing parameters.');
        }

        $post_id = intval($_GET['post']);
        if (!wp_verify_nonce($_GET['_wpnonce'], 'tmgmt_duplicate_setlist_' . $post_id)) {
            wp_die('Security check failed.');
        }

        if (!current_user_can('edit_post', $post_id)) {
            wp_die('Permission denied.');
        }

        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'tmgmt_setlist') {
            wp_die('Invalid post.');
        }

        // Create new post
        $new_post_args = array(
            'post_title' => 'Setlist für den ' . date_i18n('d.m.Y'),
            'post_status' => 'draft', // Start as draft? Or publish? Let's say draft to be safe.
            'post_type' => 'tmgmt_setlist',
            'post_author' => get_current_user_id()
        );

        $new_post_id = wp_insert_post($new_post_args);

        if ($new_post_id) {
            // Copy Meta
            $titles_json = get_post_meta($post_id, '_tmgmt_setlist_titles', true);
            $duration = get_post_meta($post_id, '_tmgmt_setlist_duration', true);
            
            update_post_meta($new_post_id, '_tmgmt_setlist_type', 'custom');
            update_post_meta($new_post_id, '_tmgmt_setlist_titles', $titles_json);
            update_post_meta($new_post_id, '_tmgmt_setlist_duration', $duration);
            
            // Redirect to edit screen
            wp_redirect(admin_url('post.php?action=edit&post=' . $new_post_id));
            exit;
        } else {
            wp_die('Failed to create new setlist.');
        }
    }

    public function ajax_create_custom_setlist() {
        check_ajax_referer('tmgmt_create_custom_setlist_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Unauthorized');
        }

        $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;

        if (!$event_id || !$template_id) {
            wp_send_json_error('Missing parameters');
        }

        $event = get_post($event_id);
        if (!$event || $event->post_type !== 'event') {
            wp_send_json_error('Invalid Event');
        }

        $template = get_post($template_id);
        if (!$template || $template->post_type !== 'tmgmt_setlist') {
            wp_send_json_error('Invalid Template');
        }

        // Create new Setlist
        $new_title = 'Setlist - ' . $event->post_title;
        
        $new_post_args = array(
            'post_title' => $new_title,
            'post_status' => 'publish',
            'post_type' => 'tmgmt_setlist',
            'post_author' => get_current_user_id()
        );

        $new_setlist_id = wp_insert_post($new_post_args);

        if (is_wp_error($new_setlist_id)) {
            wp_send_json_error($new_setlist_id->get_error_message());
        }

        // Copy Meta from Template
        $titles_json = get_post_meta($template_id, '_tmgmt_setlist_titles', true);
        $duration = get_post_meta($template_id, '_tmgmt_setlist_duration', true);

        update_post_meta($new_setlist_id, '_tmgmt_setlist_type', 'custom');
        update_post_meta($new_setlist_id, '_tmgmt_setlist_titles', $titles_json);
        update_post_meta($new_setlist_id, '_tmgmt_setlist_duration', $duration);
        
        // Link to Event
        update_post_meta($new_setlist_id, '_tmgmt_setlist_event', $event_id);
        
        // Link Event to Setlist
        update_post_meta($event_id, '_tmgmt_selected_setlist', $new_setlist_id);

        wp_send_json_success(array(
            'id' => $new_setlist_id,
            'edit_url' => admin_url('post.php?post=' . $new_setlist_id . '&action=edit'),
            'message' => 'Setlist erstellt und zugewiesen.'
        ));
    }
}
