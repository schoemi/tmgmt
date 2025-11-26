<?php

class TMGMT_PDF_Generator {

    public function __construct() {
        add_action('admin_post_tmgmt_generate_setlist_pdf', array($this, 'handle_pdf_generation'));
    }

    public function handle_pdf_generation() {
        if (!current_user_can('edit_posts')) {
            wp_die('Unauthorized');
        }

        $event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
        if (!$event_id) {
            wp_die('Invalid Event ID');
        }

        // Clear any previous output to ensure PDF headers work correctly
        while (ob_get_level()) {
            ob_end_clean();
        }

        $result = $this->generate_setlist_pdf($event_id, '', 'I');

        if (is_wp_error($result)) {
            wp_die($result->get_error_message());
        }
    }

    /**
     * Generate PDF for a Setlist
     *
     * @param int $event_id
     * @param string $template_file Template filename (basename)
     * @param string $output_mode 'D' (Download), 'I' (Inline), 'F' (File), 'S' (String)
     * @param string $output_path Path to save file if mode is 'F'
     * @return mixed
     */
    public function generate_setlist_pdf($event_id, $template_file = '', $output_mode = 'I', $output_path = '') {
        if (!class_exists('\Mpdf\Mpdf')) {
            return new WP_Error('mpdf_missing', 'mPDF library is not installed. Please run "composer require mpdf/mpdf" in the plugin directory.');
        }

        $event = get_post($event_id);
        if (!$event || $event->post_type !== 'event') {
            return new WP_Error('invalid_event', 'Invalid Event ID.');
        }

        // 1. Get Data
        $data = $this->get_setlist_data($event_id);
        $org_data = $this->get_organization_data();

        // 2. Load Template
        if (empty($template_file)) {
            $template_file = get_option('tmgmt_pdf_setlist_template', 'default.php');
        }
        
        $template_path = TMGMT_PLUGIN_DIR . 'templates/setlist/' . $template_file;
        if (!file_exists($template_path)) {
            // Fallback to default if exists, or error
            $template_path = TMGMT_PLUGIN_DIR . 'templates/setlist/default.php';
            if (!file_exists($template_path)) {
                return new WP_Error('template_missing', 'Template file not found.');
            }
        }

        // 3. Render HTML
        ob_start();
        include $template_path;
        $html = ob_get_clean();

        // 4. Generate PDF
        try {
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P'
            ]);
            
            // Set Metadata
            $mpdf->SetTitle('Setlist - ' . $data['event_title']);
            $mpdf->SetAuthor($org_data['name']);
            $mpdf->SetCreator('Töns Management Plugin');

            $mpdf->WriteHTML($html);

            if ($output_mode === 'F') {
                $mpdf->Output($output_path, \Mpdf\Output\Destination::FILE);
                return true;
            } elseif ($output_mode === 'S') {
                return $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
            } else {
                // Force headers - if this fails, we will see "Headers already sent" error which helps debugging
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="Setlist-' . $event_id . '.pdf"');
                header('Cache-Control: private, max-age=0, must-revalidate');
                header('Pragma: public');

                $mpdf->Output('Setlist-' . $event_id . '.pdf', $output_mode);
                exit;
            }

        } catch (\Mpdf\MpdfException $e) {
            return new WP_Error('mpdf_error', $e->getMessage());
        }
    }

    private function get_setlist_data($event_id) {
        // 1. Check if Event has a selected Setlist
        $selected_setlist_id = get_post_meta($event_id, '_tmgmt_selected_setlist', true);
        $setlist_post = null;

        if ($selected_setlist_id) {
            $setlist_post = get_post($selected_setlist_id);
        }

        // 2. Fallback: Find Setlist pointing to this Event
        if (!$setlist_post) {
            $setlists = get_posts(array(
                'post_type' => 'tmgmt_setlist',
                'meta_key' => '_tmgmt_setlist_event',
                'meta_value' => $event_id,
                'numberposts' => 1
            ));
            if ($setlists) {
                $setlist_post = $setlists[0];
            }
        }

        $processed_setlist = array();

        if ($setlist_post) {
            $titles_json = get_post_meta($setlist_post->ID, '_tmgmt_setlist_titles', true);
            $setlist_items = json_decode($titles_json, true);
            
            if (is_array($setlist_items)) {
                foreach ($setlist_items as $item) {
                    $title_id = isset($item['id']) ? $item['id'] : 0;
                    if (!$title_id) continue;

                    $duration_str = get_post_meta($title_id, '_tmgmt_title_duration', true);
                    $duration_sec = 0;
                    if (strpos($duration_str, ':') !== false) {
                        $parts = explode(':', $duration_str);
                        $duration_sec = intval($parts[0]) * 60 + intval($parts[1]);
                    } else {
                        $duration_sec = intval($duration_str);
                    }

                    $processed_setlist[] = array(
                        'type' => 'song',
                        'title' => get_the_title($title_id),
                        'artist' => get_post_meta($title_id, '_tmgmt_title_artist', true),
                        'key' => get_post_meta($title_id, '_tmgmt_title_key', true), // Assuming this exists or will exist
                        'bpm' => get_post_meta($title_id, '_tmgmt_title_bpm', true), // Assuming this exists or will exist
                        'duration' => $duration_sec
                    );
                }
            }
        }
        
        return array(
            'event_id' => $event_id,
            'event_title' => get_the_title($event_id),
            'event_date' => get_post_meta($event_id, '_tmgmt_event_date', true), // Correct meta key
            'location' => get_post_meta($event_id, '_tmgmt_venue_name', true), // Correct meta key
            'setlist' => $processed_setlist
        );
    }

    private function get_organization_data() {
        return array(
            'name' => get_option('tmgmt_org_name'),
            'contact' => get_option('tmgmt_org_contact'),
            'street' => get_option('tmgmt_org_street'),
            'number' => get_option('tmgmt_org_number'),
            'zip' => get_option('tmgmt_org_zip'),
            'city' => get_option('tmgmt_org_city'),
            'email' => get_option('tmgmt_org_email'),
            'phone' => get_option('tmgmt_org_phone'),
            'logo_id' => get_option('tmgmt_org_logo'),
            'logo_url' => get_option('tmgmt_org_logo') ? wp_get_attachment_url(get_option('tmgmt_org_logo')) : ''
        );
    }
}
