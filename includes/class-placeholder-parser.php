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

        // 2. Meta Fields
        // Map placeholder => meta_key
        $meta_map = array(
            // Event Details
            '[event_date]' => '_tmgmt_event_date',
            '[event_start_time]' => '_tmgmt_event_start_time',
            '[event_arrival_time]' => '_tmgmt_event_arrival_time',
            '[event_departure_time]' => '_tmgmt_event_departure_time',
            '[arrival_notes]' => '_tmgmt_arrival_notes',
            
            // Venue
            '[venue_name]' => '_tmgmt_venue_name',
            '[venue_street]' => '_tmgmt_venue_street',
            '[venue_number]' => '_tmgmt_venue_number',
            '[venue_zip]' => '_tmgmt_venue_zip',
            '[venue_city]' => '_tmgmt_venue_city',
            '[venue_country]' => '_tmgmt_venue_country',
            
            // Contact
            '[contact_salutation]' => '_tmgmt_contact_salutation',
            '[contact_firstname]' => '_tmgmt_contact_firstname',
            '[contact_lastname]' => '_tmgmt_contact_lastname',
            '[contact_company]' => '_tmgmt_contact_company',
            '[contact_street]' => '_tmgmt_contact_street',
            '[contact_number]' => '_tmgmt_contact_number',
            '[contact_zip]' => '_tmgmt_contact_zip',
            '[contact_city]' => '_tmgmt_contact_city',
            '[contact_country]' => '_tmgmt_contact_country',
            '[contact_email]' => '_tmgmt_contact_email',
            '[contact_phone]' => '_tmgmt_contact_phone',
            
            // Communication
            '[contact_email_contract]' => '_tmgmt_contact_email_contract',
            '[contact_phone_contract]' => '_tmgmt_contact_phone_contract',
            '[contact_name_tech]' => '_tmgmt_contact_name_tech',
            '[contact_email_tech]' => '_tmgmt_contact_email_tech',
            '[contact_phone_tech]' => '_tmgmt_contact_phone_tech',
            '[contact_name_program]' => '_tmgmt_contact_name_program',
            '[contact_email_program]' => '_tmgmt_contact_email_program',
            '[contact_phone_program]' => '_tmgmt_contact_phone_program',
            
            // Contract
            '[fee]' => '_tmgmt_fee',
            '[deposit]' => '_tmgmt_deposit',
            '[inquiry_date]' => '_tmgmt_inquiry_date',
        );

        foreach ($meta_map as $placeholder => $meta_key) {
            $value = get_post_meta($event_id, $meta_key, true);
            
            // Format Date Fields
            if (strpos($placeholder, 'date') !== false && !empty($value)) {
                $value = date_i18n(get_option('date_format'), strtotime($value));
            }
            
            $replacements[$placeholder] = $value;
        }

        // 3. Customer Dashboard Link
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
}
