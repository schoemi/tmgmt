<?php
/**
 * IMAP Connector
 *
 * Handles IMAP connection, fetching unread emails, marking as read,
 * appending to sent folder, and connection testing.
 * 
 * Uses webklex/php-imap library which works without native PHP IMAP extension.
 */
if (!defined('ABSPATH')) exit;

use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;

class TMGMT_IMAP_Connector {

    /**
     * IMAP client instance.
     *
     * @var Client|null
     */
    private $client = null;

    /**
     * IMAP configuration array from Connection Settings.
     *
     * @var array
     */
    private $config = array();

    /**
     * Constructor.
     * Registers WP-Cron hook and custom cron interval.
     */
    public function __construct() {
        add_action('tmgmt_imap_fetch_emails', array($this, 'handle_cron'));
        add_filter('cron_schedules', array($this, 'add_cron_interval'));
        add_action('init', array($this, 'schedule_cron'));
    }

    /**
     * Add custom cron interval based on configured fetch interval.
     */
    public function add_cron_interval(array $schedules): array {
        $interval_minutes = (int) get_option('tmgmt_imap_fetch_interval', 5);
        if ($interval_minutes < 1) {
            $interval_minutes = 5;
        }

        $schedules['tmgmt_imap_interval'] = array(
            'interval' => $interval_minutes * 60,
            'display'  => sprintf('Alle %d Minuten (TMGMT IMAP)', $interval_minutes),
        );

        return $schedules;
    }

    /**
     * Connect to the IMAP server using webklex/php-imap.
     *
     * @return bool True on success, false on failure.
     */
    public function connect(): bool {
        if ($this->client && $this->client->isConnected()) {
            return true;
        }

        $this->config = TMGMT_Connection_Settings::get_imap_config();

        if (empty($this->config['host']) || empty($this->config['username'])) {
            return false;
        }

        try {
            $cm = new ClientManager();
            
            $this->client = $cm->make([
                'host'          => $this->config['host'],
                'port'          => $this->config['port'] ?: 993,
                'encryption'    => $this->config['encryption'] ?: 'ssl',
                'validate_cert' => false,
                'username'      => $this->config['username'],
                'password'      => $this->config['password'],
                'protocol'      => 'imap',
                'authentication' => 'login',
            ]);

            $this->client->connect();
            return true;

        } catch (\Exception $e) {
            $this->log_error('IMAP-Verbindung fehlgeschlagen: ' . $e->getMessage());
            $this->client = null;
            return false;
        }
    }

    /**
     * Disconnect from the IMAP server.
     */
    public function disconnect(): void {
        if ($this->client) {
            try {
                $this->client->disconnect();
            } catch (\Exception $e) {
                // Ignore disconnect errors
            }
            $this->client = null;
        }
    }

    /**
     * Fetch all unread emails from the IMAP mailbox.
     *
     * @return array Array of associative arrays with email data.
     */
    public function fetch_unread_emails(): array {
        if (!$this->client || !$this->client->isConnected()) {
            return array();
        }

        $emails = array();

        try {
            $folder_name = $this->config['folder'] ?: 'INBOX';
            $folder = $this->client->getFolder($folder_name);
            
            if (!$folder) {
                $this->log_error('IMAP-Ordner nicht gefunden: ' . $folder_name);
                return array();
            }

            $messages = $folder->query()->unseen()->get();

            foreach ($messages as $message) {
                $email_data = $this->extract_email_data($message);
                if ($email_data !== null) {
                    $emails[] = $email_data;
                }
            }

        } catch (\Exception $e) {
            $this->log_error('Fehler beim Abrufen der E-Mails: ' . $e->getMessage());
        }

        return $emails;
    }

    /**
     * Extract all relevant data from a message object.
     *
     * @param mixed $message The message object from webklex/php-imap.
     * @return array|null Associative array with email fields, or null on failure.
     */
    private function extract_email_data($message): ?array {
        try {
            // Get From
            $from_email = '';
            $from_name = '';
            try {
                $from = $message->getFrom();
                // webklex/php-imap returns Attribute objects, not arrays
                if ($from) {
                    $from_arr = $from->toArray();
                    if (!empty($from_arr)) {
                        $first = is_array($from_arr) ? reset($from_arr) : $from_arr;
                        if (is_object($first)) {
                            $from_email = $first->mail ?? '';
                            $from_name = $first->personal ?? '';
                        } elseif (is_array($first)) {
                            $from_email = $first['mail'] ?? '';
                            $from_name = $first['personal'] ?? '';
                        }
                    }
                }
            } catch (\Exception $e) {
                // Ignore from parsing errors
            }

            // Get To
            $to_email = '';
            try {
                $to = $message->getTo();
                if ($to) {
                    $to_arr = $to->toArray();
                    if (!empty($to_arr)) {
                        $first = is_array($to_arr) ? reset($to_arr) : $to_arr;
                        if (is_object($first)) {
                            $to_email = $first->mail ?? '';
                        } elseif (is_array($first)) {
                            $to_email = $first['mail'] ?? '';
                        }
                    }
                }
            } catch (\Exception $e) {
                // Ignore to parsing errors
            }

            // Subject - handle Attribute object
            $subject = '';
            try {
                $subj = $message->getSubject();
                if ($subj) {
                    if (is_object($subj) && method_exists($subj, 'toString')) {
                        $subject = $subj->toString();
                    } elseif (is_object($subj) && method_exists($subj, 'first')) {
                        $subject = (string) $subj->first();
                    } elseif (is_object($subj) && method_exists($subj, '__toString')) {
                        $subject = (string) $subj;
                    } elseif (is_string($subj)) {
                        $subject = $subj;
                    } else {
                        $subject = (string) $subj;
                    }
                }
            } catch (\Exception $e) {
                $subject = '(Betreff konnte nicht gelesen werden)';
            }

            // Date - handle various date formats
            $email_date = current_time('mysql');
            try {
                $date = $message->getDate();
                if ($date) {
                    // Could be Attribute object
                    if (is_object($date) && method_exists($date, 'first')) {
                        $date = $date->first();
                    } elseif (is_object($date) && method_exists($date, 'toArray')) {
                        $arr = $date->toArray();
                        $date = !empty($arr) ? reset($arr) : null;
                    }
                    
                    if ($date instanceof \Carbon\Carbon || $date instanceof \DateTime) {
                        $email_date = $date->format('Y-m-d H:i:s');
                    } elseif (is_string($date)) {
                        $email_date = date('Y-m-d H:i:s', strtotime($date));
                    }
                }
            } catch (\Exception $e) {
                // Use current time as fallback
            }

            // Message-ID
            $message_id = '';
            try {
                $msg_id = $message->getMessageId();
                if ($msg_id) {
                    if (is_object($msg_id) && method_exists($msg_id, 'first')) {
                        $message_id = (string) $msg_id->first();
                    } elseif (is_object($msg_id) && method_exists($msg_id, 'toString')) {
                        $message_id = $msg_id->toString();
                    } else {
                        $message_id = (string) $msg_id;
                    }
                }
            } catch (\Exception $e) {
                // Generate a fallback message ID
            }
            
            // Ensure we have a message ID
            if (empty($message_id)) {
                $message_id = 'generated-' . md5($from_email . $subject . $email_date . microtime());
            }

            // In-Reply-To
            $in_reply_to = '';
            try {
                $header = $message->getHeader();
                if ($header) {
                    $in_reply_to_header = $header->get('in_reply_to');
                    if ($in_reply_to_header) {
                        if (is_object($in_reply_to_header) && method_exists($in_reply_to_header, 'first')) {
                            $in_reply_to = (string) $in_reply_to_header->first();
                        } else {
                            $in_reply_to = (string) $in_reply_to_header;
                        }
                    }
                }
            } catch (\Exception $e) {
                // Ignore
            }

            // Body
            $body_text = '';
            $body_html = '';
            
            try {
                if ($message->hasTextBody()) {
                    $body_text = $message->getTextBody();
                }
            } catch (\Exception $e) {
                // Ignore text body errors
            }
            
            try {
                if ($message->hasHTMLBody()) {
                    $body_html = $message->getHTMLBody();
                }
            } catch (\Exception $e) {
                // Ignore HTML body errors
            }

            // UID
            $uid = null;
            try {
                $uid = $message->getUid();
            } catch (\Exception $e) {
                // Ignore
            }

            return array(
                'message_id'  => $message_id,
                'from_email'  => $from_email,
                'from_name'   => $from_name,
                'to_email'    => $to_email,
                'subject'     => $subject,
                'body_text'   => $body_text,
                'body_html'   => $body_html,
                'email_date'  => $email_date,
                'in_reply_to' => $in_reply_to,
                'uid'         => $uid,
                '_message'    => $message, // Keep reference for marking as read
            );

        } catch (\Exception $e) {
            $this->log_error('Fehler beim Extrahieren der E-Mail-Daten: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Mark an email as read (Seen) on the IMAP server.
     *
     * @param int $uid The email UID.
     * @return bool True on success, false on failure.
     */
    public function mark_as_read(int $uid): bool {
        if (!$this->client || !$this->client->isConnected()) {
            return false;
        }

        try {
            $folder_name = $this->config['folder'] ?: 'INBOX';
            $folder = $this->client->getFolder($folder_name);
            
            if (!$folder) {
                return false;
            }

            $message = $folder->query()->whereUid($uid)->get()->first();
            if ($message) {
                $message->setFlag('Seen');
                return true;
            }

        } catch (\Exception $e) {
            $this->log_error('Fehler beim Markieren als gelesen: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Mark a message object as read directly.
     *
     * @param mixed $message The message object.
     * @return bool True on success, false on failure.
     */
    public function mark_message_as_read($message): bool {
        try {
            $message->setFlag('Seen');
            return true;
        } catch (\Exception $e) {
            $this->log_error('Fehler beim Markieren als gelesen: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Append a raw email to the configured Sent folder via IMAP APPEND.
     *
     * @param string $raw_email The complete raw email string (headers + body).
     * @return bool True on success, false on failure.
     */
    public function append_to_sent(string $raw_email): bool {
        if (empty($this->config)) {
            $this->config = TMGMT_Connection_Settings::get_imap_config();
        }

        if (!$this->client || !$this->client->isConnected()) {
            if (!$this->connect()) {
                return false;
            }
        }

        try {
            $sent_folder_name = $this->config['sent_folder'] ?: 'Sent';
            $sent_folder = $this->client->getFolder($sent_folder_name);
            
            if (!$sent_folder) {
                $this->log_error('Gesendet-Ordner nicht gefunden: ' . $sent_folder_name);
                return false;
            }

            $sent_folder->appendMessage($raw_email, ['\\Seen']);
            return true;

        } catch (\Exception $e) {
            $this->log_error('Fehler beim Ablegen im Gesendet-Ordner: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Test the IMAP connection.
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public function test_connection(): array {
        $config = TMGMT_Connection_Settings::get_imap_config();

        if (empty($config['host'])) {
            return array(
                'success' => false,
                'message' => 'IMAP-Host ist nicht konfiguriert.',
            );
        }

        if (empty($config['username'])) {
            return array(
                'success' => false,
                'message' => 'IMAP-Benutzername ist nicht konfiguriert.',
            );
        }

        if (empty($config['password'])) {
            return array(
                'success' => false,
                'message' => 'IMAP-Passwort ist nicht konfiguriert. Bitte Passwort erneut eingeben und speichern.',
            );
        }

        try {
            $cm = new ClientManager();
            
            // Map encryption setting to what the library expects
            $encryption = $config['encryption'] ?: 'ssl';
            
            $client = $cm->make([
                'host'          => $config['host'],
                'port'          => $config['port'] ?: 993,
                'encryption'    => $encryption,
                'validate_cert' => false,
                'username'      => $config['username'],
                'password'      => $config['password'],
                'protocol'      => 'imap',
                'authentication' => 'login', // Try explicit LOGIN auth
            ]);

            $client->connect();

            $folder_name = $config['folder'] ?: 'INBOX';
            $folder = $client->getFolder($folder_name);
            
            $message_count = 0;
            if ($folder) {
                $status = $folder->examine();
                $message_count = $status['exists'] ?? 0;
            }

            $client->disconnect();

            return array(
                'success' => true,
                'message' => sprintf(
                    'Verbindung erfolgreich. %d Nachrichten im Postfach.',
                    $message_count
                ),
            );

        } catch (ConnectionFailedException $e) {
            return array(
                'success' => false,
                'message' => 'IMAP-Verbindung fehlgeschlagen: ' . $e->getMessage(),
            );
        } catch (\Exception $e) {
            return array(
                'success' => false,
                'message' => 'Fehler: ' . $e->getMessage(),
            );
        }
    }

    /**
     * Schedule WP-Cron event for periodic email fetching.
     */
    public function schedule_cron(): void {
        $hook = 'tmgmt_imap_fetch_emails';

        if (!wp_next_scheduled($hook)) {
            wp_schedule_event(time(), 'tmgmt_imap_interval', $hook);
        }
    }

    /**
     * Handle WP-Cron callback: fetch emails, save to queue, and trigger assignment.
     */
    public function handle_cron(): void {
        if (!$this->connect()) {
            $this->log_error('IMAP-Verbindung fehlgeschlagen beim Cron-Abruf.');
            return;
        }

        try {
            $emails = $this->fetch_unread_emails();

            if (empty($emails)) {
                $this->disconnect();
                return;
            }

            $mail_queue = new TMGMT_Mail_Queue();

            foreach ($emails as $email_data) {
                if (empty($email_data['message_id'])) {
                    $this->log_error('E-Mail ohne Message-ID übersprungen: ' . ($email_data['subject'] ?? '(kein Betreff)'));
                    continue;
                }

                $message = $email_data['_message'] ?? null;
                unset($email_data['_message']);
                unset($email_data['uid']);

                $inserted = $mail_queue->insert($email_data);

                if ($inserted && $message) {
                    $this->mark_message_as_read($message);
                }
            }

            if (class_exists('TMGMT_Mail_Assigner')) {
                $assigner = new TMGMT_Mail_Assigner();
                $assigner->assign_new_emails();
            }

        } catch (\Exception $e) {
            $this->log_error('Fehler beim IMAP-Cron-Abruf: ' . $e->getMessage());
        } finally {
            $this->disconnect();
        }
    }

    /**
     * Log an error to TMGMT_Log_Manager if available, otherwise to error_log.
     */
    private function log_error(string $message): void {
        if (class_exists('TMGMT_Log_Manager')) {
            $log_manager = new TMGMT_Log_Manager();
            $log_manager->log(0, 'imap_error', $message, 0);
        } else {
            error_log('[TMGMT IMAP] ' . $message);
        }
    }
}
