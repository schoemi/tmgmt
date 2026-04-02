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
     * @param int    $event_id      Event Post-ID.
     * @param string $template_file Template filename relative to templates/contract/.
     * @return string|WP_Error Rendered HTML string, or WP_Error on failure.
     */
    public function render_template( int $event_id, string $template_file = 'default.php' ): string|WP_Error {
        $template_path = TMGMT_PLUGIN_DIR . 'templates/contract/' . $template_file;

        if ( ! file_exists( $template_path ) ) {
            return new WP_Error(
                'template_missing',
                sprintf( 'Contract template not found: %s', $template_file )
            );
        }

        // Resolve signature URL from the stored attachment ID.
        $signature_id  = get_option( 'tmgmt_contract_signature_id' );
        $signature_url = $signature_id ? wp_get_attachment_url( (int) $signature_id ) : '';

        ob_start();
        include $template_path;
        $html = ob_get_clean();

        // Replace all [placeholder] tokens with event data.
        $html = TMGMT_Placeholder_Parser::parse( $html, $event_id );

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

        // Step 1: Check contract email address (Req. 5.4).
        $contract_email = get_post_meta( $event_id, '_tmgmt_contact_email_contract', true );
        if ( empty( $contract_email ) ) {
            $error = new WP_Error( 'missing_contract_email', 'Keine Vertrags-E-Mail-Adresse am Event hinterlegt.' );
            $log->log( $event_id, 'contract_error', $error->get_error_message() );
            return $error;
        }

        // Step 2: Determine template file (Req. 1.1, 1.5).
        $template_file = 'default.php';
        $template_path = TMGMT_PLUGIN_DIR . 'templates/contract/' . $template_file;
        if ( ! file_exists( $template_path ) ) {
            $error = new WP_Error( 'template_missing', sprintf( 'Contract template not found: %s', $template_file ) );
            $log->log( $event_id, 'contract_error', $error->get_error_message() );
            return $error;
        }

        // Step 3: Render template (Req. 4.1).
        $html = $this->render_template( $event_id, $template_file );
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
        $recipient = get_post_meta( $event_id, '_tmgmt_contact_email_contract', true );
        if ( empty( $recipient ) ) {
            return new WP_Error( 'missing_contract_email', 'Keine Vertrags-E-Mail-Adresse am Event hinterlegt.' );
        }

        // Resolve subject and body from email template (if configured).
        $subject = 'Ihr Vertrag';
        $body    = '';

        if ( $email_template_id ) {
            $template = get_post( $email_template_id );
            if ( $template ) {
                $subject = get_post_meta( $template->ID, '_tmgmt_email_subject', true ) ?: $subject;
                $body    = $template->post_content;
            }
        }

        // Ensure a valid customer dashboard token exists so [customer_dashboard_link]
        // can be resolved to a real URL (Req. 5.3).
        if ( strpos( $body, '[customer_dashboard_link]' ) !== false ) {
            $access_manager = new TMGMT_Customer_Access_Manager();
            $token_row      = $access_manager->get_valid_token( $event_id );

            if ( $token_row ) {
                $dashboard_url = home_url( '/?tmgmt_token=' . $token_row->token );
            } else {
                // No active token — create one now so the customer can access the dashboard.
                global $wpdb;
                $new_token = bin2hex( random_bytes( 32 ) );
                $wpdb->insert(
                    $wpdb->prefix . 'tmgmt_access_tokens',
                    array(
                        'event_id'   => $event_id,
                        'token'      => $new_token,
                        'created_by' => 0,
                        'status'     => 'active',
                    ),
                    array( '%d', '%s', '%d', '%s' )
                );
                $dashboard_url = home_url( '/?tmgmt_token=' . $new_token );
            }

            $dashboard_link = '<a href="' . esc_url( $dashboard_url ) . '">' . esc_url( $dashboard_url ) . '</a>';
            $body           = str_replace( '[customer_dashboard_link]', $dashboard_link, $body );
        }

        // Replace all remaining placeholders in subject and body (Req. 5.2).
        $subject = TMGMT_Placeholder_Parser::parse( $subject, $event_id );
        $body    = TMGMT_Placeholder_Parser::parse( $body, $event_id );

        $headers     = array( 'Content-Type: text/html; charset=UTF-8' );
        $attachments = file_exists( $pdf_path ) ? array( $pdf_path ) : array();

        $sent = wp_mail( $recipient, $subject, $body, $headers, $attachments );

        if ( ! $sent ) {
            return new WP_Error( 'email_send_failed', sprintf( 'E-Mail-Versand an %s fehlgeschlagen.', $recipient ) );
        }

        // Log communication entry (Req. 5.3).
        $comm = new TMGMT_Communication_Manager();
        $comm->add_entry( $event_id, 'email', $recipient, $subject, $body, 0 );

        return true;
    }
}
