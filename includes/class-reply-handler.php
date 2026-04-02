<?php
/**
 * Reply Handler
 *
 * Handles replying to IMAP emails: subject manipulation with Event-ID,
 * SMTP sending, IMAP-APPEND to Sent folder, and communication logging.
 *
 * Requirements: 4.1, 4.2, 4.3, 4.5, 4.6, 4.7, 4.8, 4.9
 */

if (!defined('ABSPATH')) {
    exit;
}

class TMGMT_Reply_Handler {

    /**
     * Check if the subject already contains an Event-ID in the configured pattern.
     *
     * @param string $subject The email subject to check.
     * @return bool True if the subject contains an Event-ID pattern.
     */
    public function has_event_id_in_subject(string $subject): bool {
        $pattern = get_option('tmgmt_event_id_pattern', '[TMGMT-{EVENT_ID}]');

        // Escape the pattern for regex, then replace placeholder with Event-ID regex
        $regex = preg_quote($pattern, '/');
        $regex = str_replace(preg_quote('{EVENT_ID}', '/'), '[A-Z0-9]{8}', $regex);
        $regex = '/' . $regex . '/';

        return (bool) preg_match($regex, $subject);
    }

    /**
     * Build the reply subject with Event-ID pattern prepended (if not already present)
     * and Re: prefix.
     *
     * Idempotent: applying this twice results in the Event-ID pattern appearing exactly once.
     *
     * @param string $original_subject The original email subject.
     * @param string $event_id         The 8-character Event-ID string (e.g. "25AB12CD").
     * @return string The built subject.
     */
    public function build_subject(string $original_subject, string $event_id): string {
        $pattern = get_option('tmgmt_event_id_pattern', '[TMGMT-{EVENT_ID}]');
        $event_id_tag = str_replace('{EVENT_ID}', $event_id, $pattern);

        $subject = $original_subject;

        // Strip existing Re:/RE:/re: prefixes (possibly multiple) for clean rebuild
        $subject = preg_replace('/^(Re:\s*)+/i', '', $subject);
        $subject = trim($subject);

        // Prepend Event-ID pattern only if not already present
        if (!$this->has_event_id_in_subject($subject)) {
            $subject = $event_id_tag . ' ' . $subject;
        }

        // Add Re: prefix
        $subject = 'Re: ' . $subject;

        return $subject;
    }

    /**
     * Send a reply to an email from the Mail Queue.
     *
     * Flow:
     * 1. Load original email from Mail_Queue
     * 2. Build subject with Event-ID pattern + Re: prefix
     * 3. Set In-Reply-To header to original Message-ID
     * 4. Send via TMGMT_SMTP_Sender::send()
     * 5. Append raw email to IMAP Sent folder
     * 6. Create communication entry with type "imap_reply"
     * 7. Return result
     *
     * Error handling:
     * - SMTP error → error message returned, no IMAP-APPEND, no communication entry
     * - IMAP-APPEND error → warning logged, communication entry still created
     *
     * @param int    $queue_id The Mail Queue entry ID of the original email.
     * @param int    $event_id The event post ID.
     * @param string $body     The HTML reply body.
     * @return array ['success' => bool, 'message' => string]
     */
    public function send_reply(int $queue_id, int $event_id, string $body): array {
        // 1. Load original email from Mail_Queue
        $queue = new TMGMT_Mail_Queue();
        $original = $queue->get_by_id($queue_id);

        if (!$original) {
            return [
                'success' => false,
                'message' => 'Original-E-Mail nicht gefunden.',
            ];
        }

        // Load the Event-ID string from post meta
        $event_id_str = get_post_meta($event_id, '_tmgmt_event_id', true);
        if (empty($event_id_str)) {
            return [
                'success' => false,
                'message' => 'Event-ID nicht gefunden.',
            ];
        }

        // 2. Build subject
        $subject = $this->build_subject($original->subject, $event_id_str);

        // 3. Prepare send parameters with In-Reply-To
        $smtp_sender = new TMGMT_SMTP_Sender();
        $send_result = $smtp_sender->send([
            'to'          => $original->from_email,
            'subject'     => $subject,
            'body'        => $body,
            'in_reply_to' => $original->message_id,
        ]);

        // 4. Handle SMTP error
        if (!$send_result['success']) {
            $this->log_error($event_id, 'SMTP-Versand fehlgeschlagen für Queue-ID ' . $queue_id);
            return [
                'success' => false,
                'message' => 'E-Mail konnte nicht versendet werden.',
            ];
        }

        // 5. Append to IMAP Sent folder
        $imap_warning = '';
        if (!empty($send_result['raw_email'])) {
            $imap = new TMGMT_IMAP_Connector();
            $append_ok = $imap->append_to_sent($send_result['raw_email']);
            $imap->disconnect();

            if (!$append_ok) {
                $imap_warning = 'E-Mail versendet, aber nicht im Gesendet-Ordner abgelegt.';
                $this->log_warning($event_id, 'IMAP-APPEND fehlgeschlagen für Queue-ID ' . $queue_id);
            }
        }

        // 6. Create communication entry (always, since SMTP was successful)
        $smtp_config = TMGMT_Connection_Settings::get_smtp_config();
        $comm = new TMGMT_Communication_Manager();
        $comm->add_entry(
            $event_id,
            'imap_reply',
            $original->from_email,
            $subject,
            $body
        );

        // 7. Return result
        $message = 'Antwort erfolgreich gesendet.';
        if (!empty($imap_warning)) {
            $message .= ' Warnung: ' . $imap_warning;
        }

        return [
            'success' => true,
            'message' => $message,
        ];
    }

    /**
     * Log an error via TMGMT_Log_Manager or error_log fallback.
     *
     * @param int    $event_id The event post ID.
     * @param string $message  The error message.
     */
    private function log_error(int $event_id, string $message): void {
        if (class_exists('TMGMT_Log_Manager')) {
            $log = new TMGMT_Log_Manager();
            $log->log($event_id, 'imap_reply_error', $message, 0);
        } else {
            error_log('[TMGMT Reply] ' . $message);
        }
    }

    /**
     * Log a warning via TMGMT_Log_Manager or error_log fallback.
     *
     * @param int    $event_id The event post ID.
     * @param string $message  The warning message.
     */
    private function log_warning(int $event_id, string $message): void {
        if (class_exists('TMGMT_Log_Manager')) {
            $log = new TMGMT_Log_Manager();
            $log->log($event_id, 'imap_reply_warning', $message, 0);
        } else {
            error_log('[TMGMT Reply Warning] ' . $message);
        }
    }
}
