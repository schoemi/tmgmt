<?php

class TMGMT_Placeholder_Parser {

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
        $replacements = array(
            '[event_id]' => $event_id,
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
            $replacements[$placeholder] = $value;
        }

        // 3. Perform Replacement
        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }
}
