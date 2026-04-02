<?php
/**
 * SMTP Sender
 *
 * Sends emails via the configured SMTP server using PHPMailer directly
 * (not wp_mail()) for full control over SMTP credentials, headers,
 * and the raw email string needed for IMAP-APPEND.
 */
if (!defined('ABSPATH')) exit;

require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class TMGMT_SMTP_Sender {

    /**
     * Send an email via the configured SMTP server.
     *
     * @param array $params {
     *     @type string $to         Recipient email address.
     *     @type string $subject    Email subject.
     *     @type string $body       HTML email body.
     *     @type string $in_reply_to Optional Message-ID of the original email for threading.
     *     @type string $from_name  Optional sender name override.
     *     @type string $from_email Optional sender email override.
     * }
     * @return array ['success' => bool, 'raw_email' => string, 'message_id' => string]
     */
    public function send(array $params): array {
        $smtp_config = TMGMT_Connection_Settings::get_smtp_config();

        $to         = $params['to'] ?? '';
        $subject    = $params['subject'] ?? '';
        $body       = $params['body'] ?? '';
        $in_reply_to = $params['in_reply_to'] ?? '';
        $from_name  = $params['from_name'] ?? $smtp_config['from_name'];
        $from_email = $params['from_email'] ?? $smtp_config['from_email'];

        if (empty($to) || empty($subject)) {
            return [
                'success'   => false,
                'raw_email' => '',
                'message_id' => '',
            ];
        }

        $mail = new PHPMailer(true);

        try {
            // SMTP configuration
            $mail->isSMTP();
            $mail->Host       = $smtp_config['host'];
            $mail->Port       = $smtp_config['port'];
            $mail->Username   = $smtp_config['username'];
            $mail->Password   = $smtp_config['password'];
            $mail->SMTPAuth   = true;
            $mail->CharSet    = 'UTF-8';

            // Encryption
            switch ($smtp_config['encryption']) {
                case 'ssl':
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    break;
                case 'tls':
                case 'starttls':
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    break;
                default:
                    $mail->SMTPSecure = '';
                    $mail->SMTPAutoTLS = false;
                    break;
            }

            // Sender and recipient
            $mail->setFrom($from_email, $from_name);
            $mail->addAddress($to);

            // Subject and body
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body    = $body;
            $mail->AltBody = wp_strip_all_tags($body);

            // In-Reply-To header for email threading
            if (!empty($in_reply_to)) {
                $mail->addCustomHeader('In-Reply-To', $in_reply_to);
                $mail->addCustomHeader('References', $in_reply_to);
            }

            // Send the email — send() calls preSend() internally,
            // so getSentMIMEMessage() is available afterwards.
            $mail->send();

            // Capture the Message-ID assigned by PHPMailer
            $message_id = $mail->getLastMessageID();

            // Get the raw MIME message for IMAP-APPEND
            $raw_email = $mail->getSentMIMEMessage();

            return [
                'success'    => true,
                'raw_email'  => $raw_email,
                'message_id' => $message_id,
            ];

        } catch (Exception $e) {
            return [
                'success'    => false,
                'raw_email'  => '',
                'message_id' => '',
            ];
        }
    }

    /**
     * Test the SMTP connection by sending a test email to the configured sender address.
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public function test_connection(): array {
        $smtp_config = TMGMT_Connection_Settings::get_smtp_config();

        if (empty($smtp_config['host']) || empty($smtp_config['from_email'])) {
            return [
                'success' => false,
                'message' => 'SMTP-Konfiguration unvollständig. Bitte Host und Absender-Adresse konfigurieren.',
            ];
        }

        $result = $this->send([
            'to'      => $smtp_config['from_email'],
            'subject' => 'TMGMT SMTP Verbindungstest',
            'body'    => '<p>Dies ist eine Test-E-Mail vom TMGMT Plugin. Die SMTP-Verbindung funktioniert korrekt.</p>',
        ]);

        if ($result['success']) {
            return [
                'success' => true,
                'message' => 'SMTP-Verbindung erfolgreich. Test-E-Mail wurde an ' . $smtp_config['from_email'] . ' gesendet.',
            ];
        }

        return [
            'success' => false,
            'message' => 'SMTP-Verbindung fehlgeschlagen. Bitte Zugangsdaten und Servereinstellungen prüfen.',
        ];
    }
}
