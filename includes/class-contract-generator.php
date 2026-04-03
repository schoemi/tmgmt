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
     * @param int $event_id  Event Post-ID.
     * @param int $action_id tmgmt_action Post-ID.
     * @return true|WP_Error
     */
    public function generate_and_send( int $event_id, int $action_id ): bool|WP_Error {
        $log = new TMGMT_Log_Manager();

        // Step 1: Check contract email address via Veranstalter → Kontakt (Rolle: vertrag).
        $contact_data   = TMGMT_Placeholder_Parser::get_contact_data_for_event( $event_id );
        $contract_email = $contact_data['vertrag']['email'];
        if ( empty( $contract_email ) ) {
            $error = new WP_Error( 'missing_contract_email', 'Keine Vertrags-E-Mail-Adresse am verknüpften Kontakt (Rolle: Vertrag) hinterlegt.' );
            $log->log( $event_id, 'contract_error', $error->get_error_message() );
            return $error;
        }

        // Step 2: Determine template post ID from action meta (Req. 4.1).
        $template_post_id = (int) get_post_meta( $action_id, '_tmgmt_action_contract_template_id', true );
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

        // Step 6: Send contract email (Req. 5.1, 5.2, 5.3).
        $email_template_id = (int) get_post_meta( $action_id, '_tmgmt_action_email_template_id', true );
        $mail_result = $this->send_contract_email( $event_id, $email_template_id, $pdf_result['path'] );
        if ( is_wp_error( $mail_result ) ) {
            $log->log( $event_id, 'contract_error', $mail_result->get_error_message() );
            return $mail_result;
        }

        // Step 7: Set event status to target status (Req. 5.5).
        $target_status = get_post_meta( $action_id, '_tmgmt_action_target_status', true );
        if ( empty( $target_status ) ) {
            $target_status = 'contract_sent';
        }
        update_post_meta( $event_id, '_tmgmt_status', $target_status );

        $log->log( $event_id, 'contract_sent', sprintf( 'Vertrag generiert und an %s gesendet. Status: %s', $contract_email, $target_status ) );

        return true;
    }

    /**
     * Sends the contract PDF as an email attachment to the contract contact.
     *
     * @param int    $event_id          Event Post-ID.
     * @param int    $email_template_id Email template Post-ID.
     * @param string $pdf_path          Absolute path to the PDF file.
     * @return true|WP_Error
     */
    public function send_contract_email( int $event_id, int $email_template_id, string $pdf_path ): bool|WP_Error {
        $contact_data = TMGMT_Placeholder_Parser::get_contact_data_for_event( $event_id );
        $recipient    = $contact_data['vertrag']['email'];
        if ( empty( $recipient ) ) {
            return new WP_Error( 'missing_contract_email', 'Keine Vertrags-E-Mail-Adresse am verknüpften Kontakt (Rolle: Vertrag) hinterlegt.' );
        }

        // Resolve subject and body from email template (if configured).
        $subject = 'Ihr Vertrag';
        $body    = '';

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

        $attachments = file_exists( $pdf_path ) ? array( $pdf_path ) : array();

        // Send via SMTP sender, consistent with the rest of the plugin (Bug 1 fix).
        $smtp_sender = new TMGMT_SMTP_Sender();
        $smtp_result = $smtp_sender->send( array(
            'to'          => $recipient,
            'subject'     => $subject,
            'body'        => $body,
            'attachments' => $attachments,
        ) );

        if ( ! $smtp_result['success'] ) {
            $detail = ! empty( $smtp_result['error'] ) ? ' (' . $smtp_result['error'] . ')' : '';
            return new WP_Error( 'email_send_failed', sprintf( 'E-Mail-Versand an %s fehlgeschlagen%s', $recipient, $detail ) );
        }

        // Log communication entry (Req. 5.3).
        $comm = new TMGMT_Communication_Manager();
        $comm->add_entry( $event_id, 'email', $recipient, $subject, $body, 0 );

        return true;
    }
}
