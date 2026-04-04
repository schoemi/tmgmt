/**
 * Contract Send Dialog – intercepts contract_generation action buttons,
 * loads preview data via REST, and opens the jQuery-UI send dialog.
 *
 * Localized data: tmgmtContractDialog.restUrl, tmgmtContractDialog.nonce
 */
(function ($) {
    'use strict';

    if (typeof tmgmtContractDialog === 'undefined') {
        return;
    }

    var restUrl = tmgmtContractDialog.restUrl.replace(/\/+$/, '');
    var nonce   = tmgmtContractDialog.nonce;

    /**
     * Handle contract_generation action button clicks.
     * The inline handler in render_actions_box() skips this type,
     * so this delegated handler takes over.
     */
    $(document).on('click.contractDialog', '.tmgmt-trigger-action', function (e) {
        var $btn = $(this);
        var type = $btn.data('type');

        if (type !== 'contract_generation') {
            return; // let the existing handler deal with it
        }

        e.preventDefault();

        var actionId = $btn.data('id');
        var label    = $btn.data('label');
        var $dialog  = $('#tmgmt-contract-send-dialog');
        var eventId  = $dialog.data('event-id');

        loadPreviewAndOpenDialog(eventId, actionId, label, $btn);
    });

    /**
     * Fetch preview data and open the dialog.
     */
    function loadPreviewAndOpenDialog(eventId, actionId, label, $btn) {
        // Show loading state on button
        $btn.prop('disabled', true).text('Lade Vorschau...');

        // Show loading spinner inside dialog (in case it's already open)
        $('#tmgmt-contract-loading').show();

        $.ajax({
            url: restUrl + '/events/' + eventId + '/contract-preview',
            method: 'GET',
            data: { action_id: actionId },
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', nonce);
            },
            success: function (data) {
                $btn.prop('disabled', false).text(label);
                populateDialog(data, actionId);
                openDialog(label, eventId, actionId);
            },
            error: function (xhr) {
                $btn.prop('disabled', false).text(label);
                $('#tmgmt-contract-loading').hide();

                var msg = 'Fehler beim Laden der Vorschau.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }

                var status = xhr.status || 0;

                if (status === 404) {
                    // 404: show error, don't open dialog (Req. 1.5)
                    if (typeof Swal !== 'undefined') {
                        Swal.fire('Fehler', msg, 'error');
                    } else {
                        alert(msg);
                    }
                } else {
                    // 500 or other: open dialog with error in PDF column, disable send (Req. 3.4)
                    populateDialog({
                        to: '', cc: '', bcc: '', subject: '', body: '',
                        attachments: [], pdf_url: '', templates: [], selected_template_id: 0
                    }, $btn.data('id'));

                    $('#tmgmt-contract-pdf-preview').attr('src', 'about:blank');
                    $('#tmgmt-contract-loading').hide();
                    var $right = $('.tmgmt-contract-dialog-right');
                    $right.find('.tmgmt-contract-pdf-error').remove();
                    $right.prepend(
                        '<div class="tmgmt-contract-pdf-error" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:#fff;z-index:11;color:#d63638;font-weight:600;padding:20px;text-align:center;">'
                        + $('<span>').text(msg).html()
                        + '</div>'
                    );
                    $('#tmgmt-contract-send-btn').prop('disabled', true);

                    openDialog(label, $dialog.data('event-id'), $btn.data('id'));
                }
            }
        });
    }

    /**
     * Populate dialog fields from the preview response.
     */
    function populateDialog(data, actionId) {
        $('#tmgmt-contract-to').val(data.to || '');
        $('#tmgmt-contract-cc').val(data.cc || '');
        $('#tmgmt-contract-bcc').val(data.bcc || '');
        $('#tmgmt-contract-subject').val(data.subject || '');
        $('#tmgmt-contract-body').val(data.body || '');

        // Attachments list
        var $attachList = $('#tmgmt-contract-attachments-list').empty();
        if (data.attachments && data.attachments.length) {
            $.each(data.attachments, function (_, att) {
                $attachList.append('<li>' + $('<span>').text(att.name).html() + '</li>');
            });
        }

        // PDF preview iframe
        var $iframe = $('#tmgmt-contract-pdf-preview');
        if (data.pdf_url) {
            $iframe.attr('src', data.pdf_url);
        } else {
            $iframe.attr('src', 'about:blank');
        }

        // Template selector
        var $selector = $('#tmgmt-contract-template-selector').empty();
        var $row      = $('#tmgmt-contract-template-row');

        if (data.templates && data.templates.length > 1) {
            $.each(data.templates, function (_, tpl) {
                var $opt = $('<option>').val(tpl.id).text(tpl.title);
                if (tpl.id === data.selected_template_id) {
                    $opt.prop('selected', true);
                }
                $selector.append($opt);
            });
            $row.show();
        } else {
            // Single or no template – hide selector
            if (data.templates && data.templates.length === 1) {
                $selector.append(
                    $('<option>').val(data.templates[0].id).text(data.templates[0].title).prop('selected', true)
                );
            }
            $row.hide();
        }

        // Store action_id on the dialog for the send flow
        $('#tmgmt-contract-send-dialog').data('action-id', actionId);

        // Hide loading spinner
        $('#tmgmt-contract-loading').hide();
    }

    /**
     * Open the dialog as a jQuery-UI dialog.
     */
    function openDialog(title, eventId, actionId) {
        var $dialog = $('#tmgmt-contract-send-dialog');

        // Clear any previous PDF error overlay
        $('.tmgmt-contract-pdf-error').remove();

        $dialog.dialog({
            title: title || 'Vertrag senden',
            modal: true,
            width: Math.min($(window).width() - 60, 1100),
            height: Math.min($(window).height() - 60, 700),
            draggable: true,
            resizable: true,
            closeOnEscape: true,
            open: function () {
                // Ensure buttons don't submit the post form
                $(this).closest('.ui-dialog').find('button').attr('type', 'button');
            },
            close: function () {
                // Reset iframe to avoid stale content
                $('#tmgmt-contract-pdf-preview').attr('src', 'about:blank');
            }
        });
    }

    /**
     * Template switching – reload PDF preview when user picks a different template.
     */
    $(document).on('change', '#tmgmt-contract-template-selector', function () {
        var $dialog    = $('#tmgmt-contract-send-dialog');
        var eventId    = $dialog.data('event-id');
        var actionId   = $dialog.data('action-id');
        var templateId = $(this).val();

        // Show loading spinner over the PDF column
        $('#tmgmt-contract-loading').show();
        // Clear current preview while loading
        $('#tmgmt-contract-pdf-preview').attr('src', 'about:blank');

        $.ajax({
            url: restUrl + '/events/' + eventId + '/contract-preview',
            method: 'GET',
            data: { action_id: actionId, template_id: templateId },
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', nonce);
            },
            success: function (data) {
                var $iframe = $('#tmgmt-contract-pdf-preview');
                if (data.pdf_url) {
                    $iframe.attr('src', data.pdf_url);
                } else {
                    $iframe.attr('src', 'about:blank');
                }
                $('#tmgmt-contract-loading').hide();
            },
            error: function (xhr) {
                $('#tmgmt-contract-loading').hide();

                var msg = 'Fehler beim Laden der Vorschau.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }

                if (typeof Swal !== 'undefined') {
                    Swal.fire('Fehler', msg, 'error');
                } else {
                    alert(msg);
                }
            }
        });
    });

    /**
     * Send button – collect field values and POST to contract-send endpoint.
     */
    $(document).on('click', '#tmgmt-contract-send-btn', function () {
        var $dialog  = $('#tmgmt-contract-send-dialog');
        var eventId  = $dialog.data('event-id');
        var actionId = $dialog.data('action-id');
        var $sendBtn = $(this);

        var payload = {
            action_id:   actionId,
            template_id: parseInt($('#tmgmt-contract-template-selector').val(), 10) || 0,
            to:          $('#tmgmt-contract-to').val(),
            cc:          $('#tmgmt-contract-cc').val(),
            bcc:         $('#tmgmt-contract-bcc').val(),
            subject:     $('#tmgmt-contract-subject').val(),
            body:        $('#tmgmt-contract-body').val()
        };

        // Disable send button to prevent double-clicks
        $sendBtn.prop('disabled', true);

        $.ajax({
            url: restUrl + '/events/' + eventId + '/contract-send',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', nonce);
            },
            success: function () {
                $dialog.dialog('close');

                if (typeof Swal !== 'undefined') {
                    Swal.fire('Erfolg', 'Vertrag wurde erfolgreich gesendet.', 'success').then(function () {
                        location.reload();
                    });
                } else {
                    alert('Vertrag wurde erfolgreich gesendet.');
                    location.reload();
                }
            },
            error: function (xhr) {
                $sendBtn.prop('disabled', false);

                var msg = 'Fehler beim Senden des Vertrags.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }

                if (typeof Swal !== 'undefined') {
                    Swal.fire('Fehler', msg, 'error');
                } else {
                    alert(msg);
                }
            }
        });
    });

    /**
     * Cancel button closes the dialog.
     */
    $(document).on('click', '#tmgmt-contract-cancel-btn', function () {
        $('#tmgmt-contract-send-dialog').dialog('close');
    });

})(jQuery);
