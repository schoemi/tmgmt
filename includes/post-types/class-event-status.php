<?php

class TMGMT_Event_Status {

    // Status Constants
    const NEW_INQUIRY = 'new_inquiry';
    const CHECKING_DATE = 'checking_date';
    const VERBALLY_AGREED = 'verbally_agreed';
    const CONFIRMED = 'confirmed';
    const CONTRACT_SENT = 'contract_sent';
    const CONTRACT_SIGNED = 'contract_signed';
    const TECH_COORDINATION = 'tech_coordination';
    const PREP_COMPLETE = 'prep_complete';
    const GIG_DONE = 'gig_done';
    const INVOICE_SENT = 'invoice_sent';
    const INVOICE_PAID = 'invoice_paid';
    const ARCHIVED = 'archived';
    const CANCELLED = 'cancelled';

    /**
     * Returns an array of all available statuses with their labels.
     * Fetches from tmgmt_status_def CPT.
     * 
     * @return array [slug => label]
     */
    public static function get_all_statuses() {
        $args = array(
            'post_type'      => 'tmgmt_status_def',
            'posts_per_page' => -1,
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
            'post_status'    => 'publish', // Only published statuses
        );

        $posts = get_posts($args);
        $statuses = array();

        foreach ($posts as $post) {
            $statuses[$post->post_name] = $post->post_title;
        }

        // Fallback if no statuses defined yet
        if (empty($statuses)) {
            return array('' => 'Keine Status definiert');
        }

        return $statuses;
    }

    /**
     * Returns the label for a specific status key.
     * 
     * @param string $key
     * @return string
     */
    public static function get_label($key) {
        $statuses = self::get_all_statuses();
        return isset($statuses[$key]) ? $statuses[$key] : $key;
    }



    /**
     * Returns an array of required fields for a given status.
     * 
     * @param string $status_slug
     * @return array List of required fields with 'id' and 'label'.
     */
    public static function get_required_fields($status_slug) {
        // Find the post with this slug
        $args = array(
            'name'        => $status_slug,
            'post_type'   => 'tmgmt_status_def',
            'post_status' => 'publish',
            'numberposts' => 1
        );
        
        $posts = get_posts($args);
        if (empty($posts)) {
            return array();
        }

        $post_id = $posts[0]->ID;
        $required_field_ids = get_post_meta($post_id, '_tmgmt_required_fields', true);

        if (empty($required_field_ids) || !is_array($required_field_ids)) {
            return array();
        }

        $all_fields = TMGMT_Event_Meta_Boxes::get_registered_fields();
        $required = array();

        foreach ($required_field_ids as $field_id) {
            if (isset($all_fields[$field_id])) {
                $required[] = array(
                    'id' => $field_id,
                    'label' => $all_fields[$field_id]
                );
            }
        }

        return $required;
    }

    /**
     * Returns an array of required fields for each status.
     * 
     * @return array [slug => [field1, field2]]
     */
    public static function get_status_requirements() {
        $args = array(
            'post_type'      => 'tmgmt_status_def',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        );

        $posts = get_posts($args);
        $requirements = array();

        foreach ($posts as $post) {
            $req = get_post_meta($post->ID, '_tmgmt_required_fields', true);
            if (is_array($req) && !empty($req)) {
                $requirements[$post->post_name] = $req;
            }
        }

        return $requirements;
    }
}
