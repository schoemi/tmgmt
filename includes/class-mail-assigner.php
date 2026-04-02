<?php
/**
 * Mail Assigner
 *
 * Assigns emails from the Mail_Queue to events using two strategies:
 * 1. Event-ID matching in subject/body
 * 2. Contact email matching (fallback)
 *
 * Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8
 */

if (!defined('ABSPATH')) {
    exit;
}

class TMGMT_Mail_Assigner {

    /**
     * Extract an Event-ID from the given text using the configured pattern.
     *
     * Reads the pattern from Settings (e.g. "[TMGMT-{EVENT_ID}]"),
     * replaces {EVENT_ID} with regex [A-Z0-9]{8}, and searches the text.
     *
     * @param string $text The text to search (subject or body).
     * @return string|null The extracted Event-ID or null if not found.
     */
    public function find_event_id_in_text(string $text): ?string {
        $pattern = get_option('tmgmt_event_id_pattern', '[TMGMT-{EVENT_ID}]');

        // Escape the pattern for use in regex, then replace the placeholder
        $regex = preg_quote($pattern, '/');
        $regex = str_replace(preg_quote('{EVENT_ID}', '/'), '([A-Z0-9]{8})', $regex);
        $regex = '/' . $regex . '/';

        if (preg_match($regex, $text, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Find the most recently active event linked to a contact with the given email.
     *
     * Searches all contacts by _tmgmt_contact_email, then finds events
     * linked to those contacts via Veranstalter, and returns the event
     * with the most recent _tmgmt_event_date.
     *
     * @param string $email The sender email address.
     * @return int|null The event post ID or null if no match.
     */
    public function find_event_by_contact_email(string $email): ?int {
        if (empty($email)) {
            return null;
        }

        // Find contacts with this email
        $contacts = get_posts(array(
            'post_type'      => 'tmgmt_contact',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'     => '_tmgmt_contact_email',
                    'value'   => $email,
                    'compare' => '=',
                ),
            ),
            'fields' => 'ids',
        ));

        if (empty($contacts)) {
            return null;
        }

        // Find all Veranstalter that have any of these contacts assigned
        $veranstalter_ids = $this->find_veranstalter_by_contact_ids($contacts);

        if (empty($veranstalter_ids)) {
            return null;
        }

        // Find events linked to these Veranstalter, ordered by date descending
        $events = get_posts(array(
            'post_type'      => 'event',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_query'     => array(
                array(
                    'key'     => '_tmgmt_event_veranstalter_id',
                    'value'   => $veranstalter_ids,
                    'compare' => 'IN',
                ),
                array(
                    'key'     => '_tmgmt_event_date',
                    'compare' => 'EXISTS',
                ),
            ),
            'meta_key' => '_tmgmt_event_date',
            'orderby'  => 'meta_value',
            'order'    => 'DESC',
            'fields'   => 'ids',
        ));

        if (!empty($events)) {
            return (int) $events[0];
        }

        return null;
    }

    /**
     * Find Veranstalter IDs that have any of the given contact IDs assigned.
     *
     * @param array $contact_ids Array of contact post IDs.
     * @return array Array of Veranstalter post IDs.
     */
    private function find_veranstalter_by_contact_ids(array $contact_ids): array {
        $veranstalter_posts = get_posts(array(
            'post_type'      => 'tmgmt_veranstalter',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ));

        $matching = array();

        foreach ($veranstalter_posts as $v_id) {
            $assignments = get_post_meta($v_id, '_tmgmt_veranstalter_contacts', true);
            if (!is_array($assignments)) {
                continue;
            }
            foreach ($assignments as $assignment) {
                $cid = isset($assignment['contact_id']) ? intval($assignment['contact_id']) : 0;
                if (in_array($cid, $contact_ids, true)) {
                    $matching[] = $v_id;
                    break; // No need to check other contacts for this Veranstalter
                }
            }
        }

        return $matching;
    }

    /**
     * Process all emails with status "neu" and assign them to events.
     *
     * Priority:
     * 1. Event-ID in subject/body → assign with method "event_id"
     * 2. Contact email match → assign with method "contact_email"
     * 3. No match → set status to "nicht_zugeordnet"
     *
     * On successful assignment: updates Mail_Queue status and creates
     * a communication entry with type "imap_email".
     */
    public function assign_new_emails(): void {
        $queue = new TMGMT_Mail_Queue();
        $new_emails = $queue->get_by_status('neu');

        foreach ($new_emails as $email) {
            $event_id = $this->try_assign_by_event_id($email);

            if ($event_id) {
                $this->complete_assignment($queue, $email, $event_id, 'event_id');
                continue;
            }

            $event_id = $this->find_event_by_contact_email($email->from_email);

            if ($event_id) {
                $this->complete_assignment($queue, $email, $event_id, 'contact_email');
                continue;
            }

            // No match found
            $queue->update_status((int) $email->id, 'nicht_zugeordnet');
        }
    }

    /**
     * Try to find an event by Event-ID in the email subject and/or body.
     *
     * @param object $email The mail queue row object.
     * @return int|null The event post ID or null.
     */
    private function try_assign_by_event_id(object $email): ?int {
        $search_scope = get_option('tmgmt_event_id_search', 'both');
        $event_id_str = null;

        if ($search_scope === 'subject' || $search_scope === 'both') {
            $event_id_str = $this->find_event_id_in_text($email->subject);
        }

        if ($event_id_str === null && ($search_scope === 'body' || $search_scope === 'both')) {
            // Search in both text and HTML body
            $event_id_str = $this->find_event_id_in_text($email->body_text);
            if ($event_id_str === null) {
                $event_id_str = $this->find_event_id_in_text($email->body_html);
            }
        }

        if ($event_id_str === null) {
            return null;
        }

        // Look up the event by _tmgmt_event_id meta
        $events = get_posts(array(
            'post_type'      => 'event',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_query'     => array(
                array(
                    'key'     => '_tmgmt_event_id',
                    'value'   => $event_id_str,
                    'compare' => '=',
                ),
            ),
            'fields' => 'ids',
        ));

        if (!empty($events)) {
            return (int) $events[0];
        }

        return null;
    }

    /**
     * Complete an assignment: update queue status and create communication entry.
     *
     * @param TMGMT_Mail_Queue $queue  The mail queue instance.
     * @param object           $email  The mail queue row object.
     * @param int              $event_id The event post ID.
     * @param string           $method The assignment method (event_id, contact_email).
     */
    private function complete_assignment(TMGMT_Mail_Queue $queue, object $email, int $event_id, string $method): void {
        $queue->update_status((int) $email->id, 'zugeordnet', $event_id, $method);

        $comm = new TMGMT_Communication_Manager();
        $content = !empty($email->body_html) ? $email->body_html : $email->body_text;

        $comm->add_entry(
            $event_id,
            'imap_email',
            $email->from_email,
            $email->subject,
            $content,
            0 // System user (no logged-in user for automated assignment)
        );
    }

    /**
     * Manually assign a single email to an event.
     *
     * @param int    $queue_id The mail queue entry ID.
     * @param int    $event_id The event post ID to assign to.
     * @param string $method   The assignment method (default: "manuell").
     * @return bool True on success, false on failure.
     */
    public function assign_single(int $queue_id, int $event_id, string $method = 'manuell'): bool {
        $queue = new TMGMT_Mail_Queue();
        $email = $queue->get_by_id($queue_id);

        if (!$email) {
            return false;
        }

        $result = $queue->update_status($queue_id, 'zugeordnet', $event_id, $method);

        if ($result) {
            $comm = new TMGMT_Communication_Manager();
            $content = !empty($email->body_html) ? $email->body_html : $email->body_text;

            $comm->add_entry(
                $event_id,
                'imap_email',
                $email->from_email,
                $email->subject,
                $content,
                get_current_user_id()
            );
        }

        return $result;
    }
}
