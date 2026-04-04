<?php

/**
 * TMGMT_Contract_Generator
 *
 * Orchestrates contract PDF generation, storage, and email delivery.
 */
class TMGMT_Contract_Generator {

    /**
     * Renders the contract template to HTML with all placeholders replaced.
     *
     * Loads the post_content of the given tmgmt_contract_template post, renders
     * Gutenberg blocks via apply_filters('the_content', ...), replaces all
     * placeholders via TMGMT_Placeholder_Parser::parse(), and inserts the
     * signature overlay image.
     *
     * @param int $event_id          Event Post-ID.
     * @param int $template_post_id  Post-ID of the tmgmt_contract_template post.
     * @return string|WP_Error Rendered HTML string, or WP_Error on failure.
     */
    public function render_template( int $event_id, int $template_post_id ): string|WP_Error {
        // Step 1: Load template post — must exist and be published (Req. 4.1).
        $template_post = get_post( $template_post_id );
        if ( ! $template_post || $template_post->post_status !== 'publish' ) {
            return new WP_Error(
                'template_missing',
                sprintf( 'Contract template post not found or not published: %d', $template_post_id )
            );
        }

        // Step 2: Render Gutenberg blocks to HTML (Req. 4.1).
        $html = apply_filters( 'the_content', $template_post->post_content );

        // Step 3: Guard against empty rendered content (Req. 4.2).
        if ( empty( trim( $html ) ) ) {
            return new WP_Error(
                'empty_template_content',
                sprintf( 'Rendered template content is empty for post ID: %d', $template_post_id )
            );
        }

        // Step 4: Replace all [placeholder] tokens with event data (Req. 4.3).
        $html = TMGMT_Placeholder_Parser::parse( $html, $event_id );

        // Step 5: Insert signature overlay image (Req. 1.9, 4.4).
        $signature_id  = get_option( 'tmgmt_contract_signature_id' );
        $signature_url = $signature_id ? wp_get_attachment_url( (int) $signature_id ) : '';

        if ( ! empty( $signature_url ) ) {
            $overlay = '<img src="' . esc_url( $signature_url ) . '" '
                     . 'style="position:absolute;top:-10px;left:0;max-height:60px;opacity:0.9;" '
                     . 'alt="Unterschrift">';

            if ( str_contains( $html, 'class="tmgmt-signature-marker"' ) ) {
                $html = str_replace(
                    'class="tmgmt-signature-marker"',
                    'class="tmgmt-signature-marker" style="position:relative;">' . $overlay . '<span style="display:none;"',
                    $html
                );
            } elseif ( str_contains( $html, '</body>' ) ) {
                $html = str_replace( '</body>', $overlay . '</body>', $html );
            } else {
                $html .= $overlay;
            }
        }

        return $html;
    }

    /**
     * Renders a contract preview: generates HTML and a temporary PDF.
     *
     * Unlike generate_and_send(), this method does NOT create a WordPress
     * attachment entry or persist any post-meta. The PDF is saved with a
     * preview-specific filename for temporary use in the send dialog.
     *
     * @param int $event_id    Event Post-ID.
     * @param int $template_id Post-ID of the tmgmt_contract_template post.
     * @return array{pdf_url: string, html: string}|WP_Error
     */
    public function render_preview( int $event_id, int $template_id ): array|WP_Error {
        $html = $this->render_template( $event_id, $template_id );
        if ( is_wp_error( $html ) ) {
            return $html;
        }

        $upload   = wp_upload_dir();
        $dir_path = trailingslashit( $upload['basedir'] ) . 'tmgmt-contracts/' . $event_id . '/';
        $dir_url  = trailingslashit( $upload['baseurl'] ) . 'tmgmt-contracts/' . $event_id . '/';

        if ( ! wp_mkdir_p( $dir_path ) ) {
            return new WP_Error( 'upload_dir_error', sprintf( 'Could not create upload directory: %s', $dir_path ) );
        }

        $filename    = 'contract-' . $event_id . '-preview-' . time() . '.pdf';
        $output_path = $dir_path . $filename;
        $output_url  = $dir_url . $filename;

        $pdf_generator = new TMGMT_PDF_Generator();
        $result        = $pdf_generator->generate_contract_pdf( $html, $output_path );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return array(
            'pdf_url' => $output_url,
            'html'    => $html,
        );
    }

    /**
     * Generates a contract PDF and saves it to the upload directory.
     *
     * @param string $html     Rendered HTML content.
     * @param int    $event_id Event Post-ID.
     * @return array{path: string, url: string}|WP_Error
     */
    public function save_pdf( string $html, int $event_id ): array|WP_Error {
        $upload      = wp_upload_dir();
        $dir_path    = trailingslashit( $upload['basedir'] ) . 'tmgmt-contracts/' . $event_id . '/';
        $dir_url     = trailingslashit( $upload['baseurl'] ) . 'tmgmt-contracts/' . $event_id . '/';

        if ( ! wp_mkdir_p( $dir_path ) ) {
            return new WP_Error( 'upload_dir_error', sprintf( 'Could not create upload directory: %s', $dir_path ) );
        }

        $filename    = 'contract-' . $event_id . '-' . time() . '.pdf';
        $output_path = $dir_path . $filename;
        $output_url  = $dir_url . $filename;

        $pdf_generator = new TMGMT_PDF_Generator();
        $result        = $pdf_generator->generate_contract_pdf( $html, $output_path );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return array(
            'path' => $output_path,
            'url'  => $output_url,
        );
    }

    /**
     * Main entry point: render template, generate PDF, send email, update status.
     *
     * @param int   $event_id  Event Post-ID.
     * @param int   $action_id tmgmt_action Post-ID.
     * @param array $overrides Optional overrides: to, cc, bcc, subject, body, template_id.
     *                         Override values take precedence over template-resolved values.
     * @return true|WP_Error
     */
    public function generate_and_send( int $event_id, int $action_id, array $overrides = [] ): bool|WP_Error {
        $log = $this->make_log_manager();

        // Step 1: Check contract email address via Veranstalter → Kontakt (Rolle: vertrag).
        // When 'to' override is provided, skip the contact lookup requirement.
        $contact_data   = TMGMT_Placeholder_Parser::get_contact_data_for_event( $event_id );
        $contract_email = $contact_data['vertrag']['email'];
        if ( empty( $contract_email ) && empty( $overrides['to'] ) ) {
            $error = new WP_Error( 'missing_contract_email', 'Keine Vertrags-E-Mail-Adresse am verknüpften Kontakt (Rolle: Vertrag) hinterlegt.' );
            $log->log( $event_id, 'contract_error', $error->get_error_message() );
            return $error;
        }

        // Step 2: Determine template post ID — override takes precedence (Req. 7.4).
        $template_post_id = ! empty( $overrides['template_id'] )
            ? (int) $overrides['template_id']
            : (int) get_post_meta( $action_id, '_tmgmt_action_contract_template_id', true );
        if ( ! $template_post_id ) {
            $error = new WP_Error( 'template_missing', 'Keine Vertragsvorlage an der Aktion konfiguriert.' );
            $log->log( $event_id, 'contract_error', $error->get_error_message() );
            return $error;
        }

        // Step 3: Render template (Req. 4.1).
        $html = $this->render_template( $event_id, $template_post_id );
        if ( is_wp_error( $html ) ) {
            $log->log( $event_id, 'contract_error', $html->get_error_message() );
            return $html;
        }

        // Step 4: Generate and save PDF (Req. 4.2, 4.3).
        $pdf_result = $this->save_pdf( $html, $event_id );
        if ( is_wp_error( $pdf_result ) ) {
            $log->log( $event_id, 'contract_error', $pdf_result->get_error_message() );
            return $pdf_result;
        }

        // Step 5: Persist PDF path and URL as post-meta (Req. 4.4).
        update_post_meta( $event_id, '_tmgmt_contract_pdf_path', $pdf_result['path'] );
        update_post_meta( $event_id, '_tmgmt_contract_pdf_url',  $pdf_result['url'] );

        // Step 5b: Register PDF as WordPress attachment (Req. 1, 2, 4).
        $attachment_result = $this->register_pdf_attachment( $event_id, $pdf_result['path'], $pdf_result['url'] );
        if ( is_wp_error( $attachment_result ) ) {
            // Error already logged inside register_pdf_attachment(); continue with send.
        }

        // Step 6: Send contract email with overrides (Req. 6.2, 6.7, 7.4).
        $email_template_id = (int) get_post_meta( $action_id, '_tmgmt_action_email_template_id', true );
        $email_overrides   = array_intersect_key( $overrides, array_flip( array( 'to', 'cc', 'bcc', 'subject', 'body' ) ) );
        $mail_result       = $this->send_contract_email( $event_id, $email_template_id, $pdf_result['path'], $email_overrides );
        if ( is_wp_error( $mail_result ) ) {
            $log->log( $event_id, 'contract_error', $mail_result->get_error_message() );
            return $mail_result;
        }

        // Step 7: Set event status to target status (Req. 6.4).
        $target_status = get_post_meta( $action_id, '_tmgmt_action_target_status', true );
        if ( empty( $target_status ) ) {
            $target_status = 'contract_sent';
        }
        update_post_meta( $event_id, '_tmgmt_status', $target_status );

        // Step 8: Log contract_sent entry with actually-sent recipient and target status (Req. 6.8).
        $actual_recipient = ! empty( $overrides['to'] ) ? $overrides['to'] : $contract_email;
        $log->log( $event_id, 'contract_sent', sprintf( 'Vertrag generiert und an %s gesendet. Status: %s', $actual_recipient, $target_status ) );

        return true;
    }

    /**
     * Sends the contract PDF as an email attachment to the contract contact.
     *
     * @param int    $event_id          Event Post-ID.
     * @param int    $email_template_id Email template Post-ID.
     * @param string $pdf_path          Absolute path to the PDF file.
     * @param array  $overrides         Optional overrides: to, cc, bcc, subject, body.
     *                                  Override values take precedence over template-resolved values.
     * @return true|WP_Error
     */
    public function send_contract_email( int $event_id, int $email_template_id, string $pdf_path, array $overrides = [] ): bool|WP_Error {
        $contact_data = TMGMT_Placeholder_Parser::get_contact_data_for_event( $event_id );
        $recipient    = $contact_data['vertrag']['email'];

        // Resolve subject and body from email template (if configured).
        $subject = 'Ihr Vertrag';
        $body    = '';
        $cc      = '';
        $bcc     = '';

        if ( $email_template_id ) {
            $template = get_post( $email_template_id );
            if ( $template ) {
                $subject = get_post_meta( $template->ID, '_tmgmt_email_subject', true ) ?: $subject;
                // Body is stored in post meta _tmgmt_email_body, not post_content.
                $body = get_post_meta( $template->ID, '_tmgmt_email_body', true );
            }
        }

        // Replace all placeholders in subject and body — this also handles
        // [customer_dashboard_link] internally via TMGMT_Placeholder_Parser (Bug 3 fix).
        $subject = TMGMT_Placeholder_Parser::parse( $subject, $event_id );
        $body    = TMGMT_Placeholder_Parser::parse( $body, $event_id );

        // Apply overrides — override values take precedence over template-resolved values (Req. 7.4).
        if ( isset( $overrides['to'] ) && $overrides['to'] !== '' ) {
            $recipient = $overrides['to'];
        }
        if ( isset( $overrides['cc'] ) ) {
            $cc = $overrides['cc'];
        }
        if ( isset( $overrides['bcc'] ) ) {
            $bcc = $overrides['bcc'];
        }
        if ( isset( $overrides['subject'] ) && $overrides['subject'] !== '' ) {
            $subject = $overrides['subject'];
        }
        if ( isset( $overrides['body'] ) ) {
            $body = $overrides['body'];
        }

        if ( empty( $recipient ) ) {
            return new WP_Error( 'missing_contract_email', 'Keine Vertrags-E-Mail-Adresse am verknüpften Kontakt (Rolle: Vertrag) hinterlegt.' );
        }

        $attachments = file_exists( $pdf_path ) ? array( $pdf_path ) : array();

        // Build send params with CC/BCC support.
        $send_params = array(
            'to'          => $recipient,
            'subject'     => $subject,
            'body'        => $body,
            'attachments' => $attachments,
        );
        if ( ! empty( $cc ) ) {
            $send_params['cc'] = $cc;
        }
        if ( ! empty( $bcc ) ) {
            $send_params['bcc'] = $bcc;
        }

        // Send via SMTP sender, consistent with the rest of the plugin (Bug 1 fix).
        $smtp_sender = $this->make_smtp_sender();
        $smtp_result = $smtp_sender->send( $send_params );

        if ( ! $smtp_result['success'] ) {
            $detail = ! empty( $smtp_result['error'] ) ? ' (' . $smtp_result['error'] . ')' : '';
            return new WP_Error( 'email_send_failed', sprintf( 'E-Mail-Versand an %s fehlgeschlagen%s', $recipient, $detail ) );
        }

        // Log communication entry with actually-sent values (Req. 6.7).
        $comm = $this->make_communication_manager();
        $comm->add_entry( $event_id, 'email', $recipient, $subject, $body, 0 );

        return true;
    }

    /**
     * Registers the contract PDF as a WordPress attachment linked to the event.
     *
     * Creates a WP attachment post with post_parent = event_id, stores the
     * attachment ID as _tmgmt_contract_attachment_id, and appends the attachment
     * to _tmgmt_event_attachments with category 'Vertrag'.
     *
     * @param int    $event_id Event Post-ID.
     * @param string $pdf_path Absolute file path to the PDF.
     * @param string $pdf_url  Public URL of the PDF.
     * @return int|WP_Error Attachment ID on success, WP_Error on failure.
     */
    protected function register_pdf_attachment( int $event_id, string $pdf_path, string $pdf_url ): int|WP_Error {
        $log = $this->make_log_manager();

        // Step 1: Create WP attachment post (Req. 1.1, 1.2, 1.3).
        $attachment_data = array(
            'post_mime_type' => 'application/pdf',
            'post_title'     => basename( $pdf_path ),
            'post_parent'    => $event_id,
            'guid'           => $pdf_url,
        );

        $attachment_id = wp_insert_attachment( $attachment_data, $pdf_path, $event_id );

        if ( is_wp_error( $attachment_id ) ) {
            $log->log( $event_id, 'contract_error', $attachment_id->get_error_message() );
            return $attachment_id;
        }

        if ( ! $attachment_id ) {
            $error = new WP_Error( 'attachment_failed', sprintf( 'WP-Attachment konnte nicht erstellt werden für: %s', basename( $pdf_path ) ) );
            $log->log( $event_id, 'contract_error', $error->get_error_message() );
            return $error;
        }

        // Step 2: Store attachment ID as post-meta on the event (Req. 1.4).
        update_post_meta( $event_id, '_tmgmt_contract_attachment_id', $attachment_id );

        // Step 3: Append to _tmgmt_event_attachments with normalization (Req. 2.1, 2.2, 2.3).
        $current_data = get_post_meta( $event_id, '_tmgmt_event_attachments', true );
        if ( ! is_array( $current_data ) ) {
            $current_data = array();
        }

        // Normalize existing entries (same pattern as class-action-handler.php).
        $normalized = array();
        $duplicate_index = -1;
        foreach ( $current_data as $item ) {
            if ( is_numeric( $item ) ) {
                $entry = array( 'id' => intval( $item ), 'category' => '' );
            } elseif ( is_array( $item ) && isset( $item['id'] ) ) {
                $entry = $item;
            } else {
                continue;
            }

            // Check for duplicate by attachment ID.
            if ( intval( $entry['id'] ) === $attachment_id ) {
                $entry['category'] = 'Vertrag';
                $duplicate_index = count( $normalized );
            }

            $normalized[] = $entry;
        }

        // If no duplicate was found, append the new entry.
        if ( $duplicate_index === -1 ) {
            $normalized[] = array( 'id' => $attachment_id, 'category' => 'Vertrag' );
        }

        update_post_meta( $event_id, '_tmgmt_event_attachments', $normalized );

        // Step 4: Log success (Req. 4.1).
        $log->log( $event_id, 'attachment_added', sprintf( 'Vertrag-PDF %s als Anhang registriert.', basename( $pdf_path ) ) );

        return $attachment_id;
    }

    /**
     * Factory method for SMTP sender. Override in tests.
     *
     * @return TMGMT_SMTP_Sender
     */
    protected function make_smtp_sender() {
        return new TMGMT_SMTP_Sender();
    }

    /**
     * Factory method for communication manager. Override in tests.
     *
     * @return TMGMT_Communication_Manager
     */
    protected function make_communication_manager() {
        return new TMGMT_Communication_Manager();
    }

    /**
     * Factory method for log manager. Override in tests.
     *
     * @return TMGMT_Log_Manager
     */
    protected function make_log_manager() {
        return new TMGMT_Log_Manager();
    }
}
