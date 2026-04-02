<?php
/**
 * Default Contract Template
 *
 * Available variables:
 * $event_id      (int)    – Event Post-ID
 * $signature_url (string) – URL of the organisation signature image (from tmgmt_contract_signature_id)
 *
 * All [placeholder] variables are replaced before this template is included,
 * so the template works with the already-parsed HTML string passed via ob_start()/include()/ob_get_clean().
 */
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 11pt; color: #222; line-height: 1.5; }

        .contract-wrapper { max-width: 720px; margin: 0 auto; padding: 40px 40px 60px; }

        h1.contract-title { font-size: 20pt; text-align: center; margin-bottom: 6px; }
        .contract-subtitle { text-align: center; font-size: 10pt; color: #666; margin-bottom: 30px; }

        .section { margin-bottom: 24px; }
        .section-title { font-size: 12pt; font-weight: bold; border-bottom: 1px solid #ccc; padding-bottom: 4px; margin-bottom: 10px; }

        table.data-table { width: 100%; border-collapse: collapse; }
        table.data-table td { padding: 4px 6px; vertical-align: top; }
        table.data-table td:first-child { width: 40%; font-weight: bold; color: #444; }

        .contract-body { margin-bottom: 24px; }
        .contract-body p { margin-bottom: 10px; }

        .signature-area { margin-top: 40px; display: table; width: 100%; }
        .signature-col { display: table-cell; width: 50%; padding-right: 20px; vertical-align: bottom; }

        .signature-block { position: relative; margin-top: 40px; }
        .signature-block p { margin-bottom: 4px; }

        .footer-note { margin-top: 40px; font-size: 9pt; color: #888; border-top: 1px solid #eee; padding-top: 8px; }
    </style>
</head>
<body>
<div class="contract-wrapper">

    <!-- Header -->
    <h1 class="contract-title">Veranstaltungsvertrag</h1>
    <p class="contract-subtitle">Vertragsnummer: [event_id] &nbsp;|&nbsp; Anfragedatum: [inquiry_date]</p>

    <!-- Vertragsparteien -->
    <div class="section">
        <div class="section-title">Vertragsparteien</div>
        <table class="data-table">
            <tr>
                <td>Auftraggeber:</td>
                <td>
                    [contact_salutation] [contact_firstname] [contact_lastname]<br>
                    [contact_company]<br>
                    [contact_street] [contact_number]<br>
                    [contact_zip] [contact_city]<br>
                    [contact_country]
                </td>
            </tr>
            <tr>
                <td>Kontakt E-Mail (Vertrag):</td>
                <td>[contact_email_contract]</td>
            </tr>
            <tr>
                <td>Kontakt Telefon (Vertrag):</td>
                <td>[contact_phone_contract]</td>
            </tr>
        </table>
    </div>

    <!-- Veranstaltungsdetails -->
    <div class="section">
        <div class="section-title">Veranstaltungsdetails</div>
        <table class="data-table">
            <tr>
                <td>Veranstaltung:</td>
                <td>[event_title]</td>
            </tr>
            <tr>
                <td>Datum:</td>
                <td>[event_date]</td>
            </tr>
            <tr>
                <td>Beginn:</td>
                <td>[event_start_time] Uhr</td>
            </tr>
            <tr>
                <td>Ankunft:</td>
                <td>[event_arrival_time] Uhr</td>
            </tr>
            <tr>
                <td>Veranstaltungsort:</td>
                <td>
                    [venue_name]<br>
                    [venue_street] [venue_number]<br>
                    [venue_zip] [venue_city]
                </td>
            </tr>
            <tr>
                <td>Anreise-Hinweise:</td>
                <td>[arrival_notes]</td>
            </tr>
        </table>
    </div>

    <!-- Technik-Kontakt -->
    <div class="section">
        <div class="section-title">Technischer Ansprechpartner</div>
        <table class="data-table">
            <tr>
                <td>Name:</td>
                <td>[contact_name_tech]</td>
            </tr>
            <tr>
                <td>E-Mail:</td>
                <td>[contact_email_tech]</td>
            </tr>
            <tr>
                <td>Telefon:</td>
                <td>[contact_phone_tech]</td>
            </tr>
        </table>
    </div>

    <!-- Honorar -->
    <div class="section">
        <div class="section-title">Honorar &amp; Zahlung</div>
        <table class="data-table">
            <tr>
                <td>Vereinbarte Gage:</td>
                <td>[fee] €</td>
            </tr>
            <tr>
                <td>Anzahlung:</td>
                <td>[deposit] €</td>
            </tr>
        </table>
    </div>

    <!-- Vertragsbedingungen -->
    <div class="section contract-body">
        <div class="section-title">Vertragsbedingungen</div>
        <p>
            Dieser Vertrag regelt die Durchführung der oben genannten Veranstaltung zwischen dem Auftraggeber
            und dem Auftragnehmer. Beide Parteien verpflichten sich zur Einhaltung der vereinbarten Konditionen.
        </p>
        <p>
            Die vereinbarte Gage ist spätestens 14 Tage nach der Veranstaltung auf das dem Auftragnehmer
            bekannte Konto zu überweisen, sofern keine abweichende Zahlungsvereinbarung getroffen wurde.
        </p>
        <p>
            Bei Absage durch den Auftraggeber weniger als 30 Tage vor dem Veranstaltungsdatum ist die
            volle Gage fällig. Bei Absage mehr als 30 Tage vorher ist die vereinbarte Anzahlung einzubehalten.
        </p>
        <p>
            Änderungen und Ergänzungen dieses Vertrages bedürfen der Schriftform.
        </p>
    </div>

    <!-- Unterschriften -->
    <div class="signature-area">
        <!-- Auftraggeber -->
        <div class="signature-col">
            <div class="signature-block" style="position:relative; margin-top:40px;">
                <p>Ort, Datum: ________________________</p>
                <p style="margin-top:40px;">________________________</p>
                <p style="font-size:9pt; color:#555;">Unterschrift Auftraggeber</p>
                <p style="font-size:9pt; color:#555;">[contact_firstname] [contact_lastname]</p>
            </div>
        </div>

        <!-- Auftragnehmer / Organisation -->
        <div class="signature-col">
            <div class="signature-block" style="position:relative; margin-top:40px;">
                <p>Ort, Datum: ________________________</p>
                <?php if (!empty($signature_url)): ?>
                <img src="<?php echo esc_url($signature_url); ?>"
                     style="position:absolute; top:-10px; left:0; max-height:60px; opacity:0.9;"
                     alt="Unterschrift">
                <?php endif; ?>
                <p style="margin-top:40px;">________________________</p>
                <p style="font-size:9pt; color:#555;">Unterschrift Auftragnehmer</p>
            </div>
        </div>
    </div>

    <div class="footer-note">
        Vertrag generiert am <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'))); ?>
        &nbsp;|&nbsp; Event-ID: <?php echo intval($event_id); ?>
    </div>

</div>
</body>
</html>
