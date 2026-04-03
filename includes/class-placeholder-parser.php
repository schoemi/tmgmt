<?php

class TMGMT_Placeholder_Parser {

    /**
     * Returns a list of all available placeholders.
     * 
     * @return array List of placeholders
     */
    public static function get_placeholders() {
        $core_placeholders = array(
            '[event_id]' => 'Event ID',
            '[event_title]' => 'Event Titel',
            '[event_content]' => 'Event Beschreibung',
            '[event_link]' => 'Event Link',
        );

        $meta_placeholders = array(
            // Event Details
            '[event_date]' => 'Datum',
            '[event_start_time]' => 'Startzeit',
            '[event_arrival_time]' => 'Ankunftszeit',
            '[event_departure_time]' => 'Abfahrtszeit',
            '[arrival_notes]' => 'Anreise Notizen',
            
            // Venue
            '[venue_name]' => 'Location Name',
            '[venue_street]' => 'Location Straße',
            '[venue_number]' => 'Location Hausnummer',
            '[venue_zip]' => 'Location PLZ',
            '[venue_city]' => 'Location Stadt',
            '[venue_country]' => 'Location Land',
            
            // Contact
            '[contact_salutation]' => 'Anrede',
            '[contact_firstname]' => 'Vorname',
            '[contact_lastname]' => 'Nachname',
            '[contact_company]' => 'Firma',
            '[contact_street]' => 'Straße',
            '[contact_number]' => 'Hausnummer',
            '[contact_zip]' => 'PLZ',
            '[contact_city]' => 'Stadt',
            '[contact_country]' => 'Land',
            '[contact_email]' => 'E-Mail',
            '[contact_phone]' => 'Telefon',
            
            // Communication
            '[contact_email_contract]' => 'E-Mail (Vertrag)',
            '[contact_phone_contract]' => 'Telefon (Vertrag)',
            '[contact_name_tech]' => 'Name (Technik)',
            '[contact_email_tech]' => 'E-Mail (Technik)',
            '[contact_phone_tech]' => 'Telefon (Technik)',
            '[contact_name_program]' => 'Name (Programm)',
            '[contact_email_program]' => 'E-Mail (Programm)',
            '[contact_phone_program]' => 'Telefon (Programm)',
            
            // Contract
            '[fee]' => 'Gage',
            '[deposit]' => 'Anzahlung',
            '[inquiry_date]' => 'Anfragedatum',
            
            // Confirmation
            '[confirmation_link]' => 'Bestätigungs-Link',
            
            // Customer Dashboard
            '[customer_dashboard_link]' => 'Kunden Dashboard Link',
        );

        return array_merge($core_placeholders, $meta_placeholders);
    }

    /**
     * Replaces placeholders in text with event data.
     * 
     * @param string $text The text containing placeholders like [event_date]
     * @param int $event_id The ID of the event
     * @return string The parsed text
     */
    public static function parse($text, $event_id) {
        if (empty($text)) {
            return '';
        }

        $post = get_post($event_id);
        if (!$post) {
            return $text;
        }

        // 1. Core Fields
        $event_id_display = get_post_meta($event_id, '_tmgmt_event_id', true);
        if (empty($event_id_display)) $event_id_display = $event_id; // Fallback

        $replacements = array(
            '[event_id]' => $event_id_display,
            '[event_title]' => $post->post_title,
            '[event_content]' => $post->post_content,
            '[event_link]' => get_permalink($event_id),
        );

        // 2. Meta Fields (event-direct)
        $meta_map = array(
            '[event_date]'        => '_tmgmt_event_date',
            '[event_start_time]'  => '_tmgmt_event_start_time',
            '[event_arrival_time]'   => '_tmgmt_event_arrival_time',
            '[event_departure_time]' => '_tmgmt_event_departure_time',
            '[fee]'          => '_tmgmt_fee',
            '[deposit]'      => '_tmgmt_deposit',
            '[inquiry_date]' => '_tmgmt_inquiry_date',
        );

        foreach ($meta_map as $placeholder => $meta_key) {
            $value = get_post_meta($event_id, $meta_key, true);
            if (strpos($placeholder, 'date') !== false && !empty($value)) {
                $value = date_i18n(get_option('date_format'), strtotime($value));
            }
            $replacements[$placeholder] = $value;
        }

        // 2b. Contact Fields – resolved via veranstalter → contact CPT
        $contact_data = self::get_contact_data_for_event($event_id);

        // Vertrag-Kontakt → [contact_*] (Hauptkontakt für Platzhalter)
        $vertrag = $contact_data['vertrag'];
        $replacements['[contact_salutation]'] = $vertrag['salutation'];
        $replacements['[contact_firstname]']  = $vertrag['firstname'];
        $replacements['[contact_lastname]']   = $vertrag['lastname'];
        $replacements['[contact_company]']    = $vertrag['company'];
        $replacements['[contact_street]']     = $vertrag['street'];
        $replacements['[contact_number]']     = $vertrag['number'];
        $replacements['[contact_zip]']        = $vertrag['zip'];
        $replacements['[contact_city]']       = $vertrag['city'];
        $replacements['[contact_country]']    = $vertrag['country'];
        $replacements['[contact_email]']      = $vertrag['email'];
        $replacements['[contact_phone]']      = $vertrag['phone'];

        // Vertrag-Kontakt → [contact_*_contract]
        $replacements['[contact_email_contract]'] = $vertrag['email'];
        $replacements['[contact_phone_contract]'] = $vertrag['phone'];

        // Technik-Kontakt
        $technik = $contact_data['technik'];
        $replacements['[contact_name_tech]']  = trim($technik['firstname'] . ' ' . $technik['lastname']);
        $replacements['[contact_email_tech]'] = $technik['email'];
        $replacements['[contact_phone_tech]'] = $technik['phone'];

        // Programm-Kontakt
        $programm = $contact_data['programm'];
        $replacements['[contact_name_program]']  = trim($programm['firstname'] . ' ' . $programm['lastname']);
        $replacements['[contact_email_program]'] = $programm['email'];
        $replacements['[contact_phone_program]'] = $programm['phone'];
        
        // 2b. Venue Fields - get from linked location
        $location_id = get_post_meta($event_id, '_tmgmt_event_location_id', true);
        if (!empty($location_id)) {
            $location_post = get_post($location_id);
            $replacements['[venue_name]'] = $location_post ? $location_post->post_title : '';
            $replacements['[venue_street]'] = get_post_meta($location_id, '_tmgmt_location_street', true);
            $replacements['[venue_number]'] = get_post_meta($location_id, '_tmgmt_location_number', true);
            $replacements['[venue_zip]'] = get_post_meta($location_id, '_tmgmt_location_zip', true);
            $replacements['[venue_city]'] = get_post_meta($location_id, '_tmgmt_location_city', true);
            $replacements['[venue_country]'] = get_post_meta($location_id, '_tmgmt_location_country', true);
            $replacements['[arrival_notes]'] = get_post_meta($location_id, '_tmgmt_location_notes', true);
        } else {
            $replacements['[venue_name]'] = '';
            $replacements['[venue_street]'] = '';
            $replacements['[venue_number]'] = '';
            $replacements['[venue_zip]'] = '';
            $replacements['[venue_city]'] = '';
            $replacements['[venue_country]'] = '';
            $replacements['[arrival_notes]'] = '';
        }

        // 3. Confirmation Link
        if (strpos($text, '[confirmation_link]') !== false) {
            // Confirmation links are generated per-action request; resolve to empty string
            // when no active confirmation request exists for this event.
            global $wpdb;
            $confirmations_table = $wpdb->prefix . 'tmgmt_confirmations';
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT token FROM $confirmations_table WHERE event_id = %d AND status = 'pending' ORDER BY requested_at DESC LIMIT 1",
                    $event_id
                )
            );
            if ($row && !empty($row->token)) {
                $confirm_url = admin_url('admin-post.php?action=tmgmt_confirm_action&token=' . $row->token);
                $replacements['[confirmation_link]'] = '<a href="' . esc_url($confirm_url) . '">' . esc_url($confirm_url) . '</a>';
            } else {
                $replacements['[confirmation_link]'] = '';
            }
        }

        // 4. Customer Dashboard Link
        if (strpos($text, '[customer_dashboard_link]') !== false) {
            $access_manager = new TMGMT_Customer_Access_Manager();
            $token_row = $access_manager->get_valid_token($event_id);
            
            if ($token_row) {
                $link = home_url('/?tmgmt_token=' . $token_row->token);
                $replacements['[customer_dashboard_link]'] = '<a href="' . esc_url($link) . '">' . esc_url($link) . '</a>';
            } else {
                // Return a special marker that the frontend can detect to prompt for creation
                $replacements['[customer_dashboard_link]'] = '[[MISSING_TOKEN]]';
            }
        }

        // 4. Perform Replacement
        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    /**
     * Resolves contact data for an event via the linked Veranstalter CPT.
     *
     * Returns an array keyed by role ('vertrag', 'technik', 'programm'), each
     * containing the contact's meta fields. Missing contacts return empty strings.
     *
     * @param int $event_id
     * @return array{vertrag: array, technik: array, programm: array}
     */
    public static function get_contact_data_for_event( int $event_id ): array {
        $empty = array(
            'salutation' => '', 'firstname' => '', 'lastname' => '',
            'company'    => '', 'street'    => '', 'number'   => '',
            'zip'        => '', 'city'      => '', 'country'  => '',
            'email'      => '', 'phone'     => '',
        );

        $result = array(
            'vertrag'  => $empty,
            'technik'  => $empty,
            'programm' => $empty,
        );

        $veranstalter_id = get_post_meta( $event_id, '_tmgmt_event_veranstalter_id', true );
        if ( empty( $veranstalter_id ) ) {
            return $result;
        }

        $assignments = get_post_meta( $veranstalter_id, '_tmgmt_veranstalter_contacts', true );
        if ( ! is_array( $assignments ) ) {
            return $result;
        }

        foreach ( $assignments as $assignment ) {
            $role       = isset( $assignment['role'] ) ? $assignment['role'] : '';
            $contact_id = isset( $assignment['contact_id'] ) ? intval( $assignment['contact_id'] ) : 0;

            if ( ! isset( $result[ $role ] ) || ! $contact_id ) {
                continue;
            }

            if ( get_post_type( $contact_id ) !== 'tmgmt_contact' ) {
                continue;
            }

            $result[ $role ] = array(
                'salutation' => get_post_meta( $contact_id, '_tmgmt_contact_salutation', true ),
                'firstname'  => get_post_meta( $contact_id, '_tmgmt_contact_firstname',  true ),
                'lastname'   => get_post_meta( $contact_id, '_tmgmt_contact_lastname',   true ),
                'company'    => get_post_meta( $contact_id, '_tmgmt_contact_company',    true ),
                'street'     => get_post_meta( $contact_id, '_tmgmt_contact_street',     true ),
                'number'     => get_post_meta( $contact_id, '_tmgmt_contact_number',     true ),
                'zip'        => get_post_meta( $contact_id, '_tmgmt_contact_zip',        true ),
                'city'       => get_post_meta( $contact_id, '_tmgmt_contact_city',       true ),
                'country'    => get_post_meta( $contact_id, '_tmgmt_contact_country',    true ),
                'email'      => get_post_meta( $contact_id, '_tmgmt_contact_email',      true ),
                'phone'      => get_post_meta( $contact_id, '_tmgmt_contact_phone',      true ),
            );
        }

        return $result;
    }
}
