# TMGMT API Documentation

## Email API

### 1. Get Email Templates
Retrieves a list of all available email templates.

**Endpoint:** `GET /wp-json/tmgmt/v1/email-templates`

**Response:**
```json
[
    {
        "id": 123,
        "title": "Setlist Notification"
    },
    {
        "id": 124,
        "title": "Contract Reminder"
    }
]
```

### 2. Preview Email
Generates a preview of an email for a specific event and template, with all placeholders replaced.

**Endpoint:** `POST /wp-json/tmgmt/v1/events/{id}/email-preview`

**Parameters:**
- `id` (int, required): The Event ID (in URL).
- `template_id` (int, required): The ID of the email template to use.

**Response:**
```json
{
    "recipient": "contact@venue.com",
    "subject": "Info: Summer Festival 2025",
    "body": "Hello,\n\nHere is the setlist for the upcoming event..."
}
```

### 3. Send Email
Sends an email for a specific event. Can optionally attach the generated Setlist PDF.

**Endpoint:** `POST /wp-json/tmgmt/v1/events/{id}/email-send`

**Parameters:**
- `id` (int, required): The Event ID (in URL).
- `recipient` (string, required): The email recipient.
- `subject` (string, required): The email subject.
- `body` (string, required): The email body content.
- `attach_pdf` (boolean, optional): If `true`, generates and attaches the Setlist PDF.

**Response:**
```json
{
    "success": true,
    "message": "E-Mail erfolgreich versendet."
}
```

**Error Response:**
```json
{
    "code": "email_failed",
    "message": "E-Mail konnte nicht gesendet werden.",
    "data": {
        "status": 500
    }
}
```

## Contract API

### 4. Contract Preview
Returns email preview data and a temporary PDF URL for the contract send dialog. Resolves all placeholders from the configured email template and generates a preview PDF.

**Endpoint:** `GET /wp-json/tmgmt/v1/events/{event_id}/contract-preview`

**Parameters:**
- `event_id` (int, required): The Event ID (in URL).
- `action_id` (int, required): The tmgmt_action Post-ID (query param).
- `template_id` (int, optional): Overrides the action's configured contract template ID (query param).

**Permission:** `edit_tmgmt_events` or `edit_posts`

**Response (200):**
```json
{
    "to": "max@example.com",
    "cc": "",
    "bcc": "",
    "subject": "Ihr Vertrag – Konzert Berlin",
    "body": "<p>Sehr geehrter Herr Mustermann, ...</p>",
    "attachments": [{ "id": 42, "name": "rider.pdf" }],
    "pdf_url": "https://example.com/wp-content/uploads/tmgmt-contracts/123/contract-123-preview-1234567890.pdf",
    "templates": [
        { "id": 10, "title": "Standard-Vertrag" },
        { "id": 11, "title": "Festival-Vertrag" }
    ],
    "selected_template_id": 10
}
```

When no email template is configured on the action, the response includes `"no_template": true` with empty email fields.

**Errors:** 404 (invalid event/action), 500 (PDF generation failure)

### 5. Send Contract
Generates the final contract PDF and sends it via email using the user-confirmed dialog values. Updates the event status and logs communication entries.

**Endpoint:** `POST /wp-json/tmgmt/v1/events/{event_id}/contract-send`

**Parameters (JSON body):**
- `action_id` (int, required): The tmgmt_action Post-ID.
- `to` (string, required): Recipient email address.
- `subject` (string, required): Email subject.
- `body` (string, optional): Email body (HTML).
- `cc` (string, optional): CC email address.
- `bcc` (string, optional): BCC email address.
- `template_id` (int, optional): Contract template ID override.

**Permission:** `edit_tmgmt_events` or `edit_posts`

**Response (200):**
```json
{
    "success": true,
    "message": "Vertrag gesendet."
}
```

**Errors:** 400 (missing required fields), 404 (invalid event/action), 500 (send failure)
