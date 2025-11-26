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
