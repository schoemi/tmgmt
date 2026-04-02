jQuery(document).ready(function($) {

    // --- Kontakt-Suche (Autocomplete) ---
    $('.tmgmt-veranstalter-contact-search').each(function() {
        var $input = $(this);
        var role = $input.data('role');
        var $hidden = $input.siblings('input[type="hidden"]');

        $input.autocomplete({
            source: function(request, response) {
                $.ajax({
                    url: ajaxurl,
                    dataType: 'json',
                    data: {
                        action: 'tmgmt_search_contacts_for_veranstalter',
                        term: request.term
                    },
                    success: function(data) {
                        if (data.success && data.data) {
                            response($.map(data.data, function(item) {
                                var label = item.title;
                                if (item.email) {
                                    label += ' (' + item.email + ')';
                                }
                                return {
                                    label: label,
                                    value: item.title,
                                    id: item.id
                                };
                            }));
                        } else {
                            response([]);
                        }
                    }
                });
            },
            minLength: 2,
            select: function(event, ui) {
                $hidden.val(ui.item.id);
            }
        });

        // Feld leeren => hidden value zurücksetzen
        $input.on('input', function() {
            if (!$(this).val()) {
                $hidden.val('');
            }
        });
    });

    // --- Location-Suche (Autocomplete) ---
    $('.tmgmt-veranstalter-location-search').autocomplete({
        source: function(request, response) {
            $.ajax({
                url: ajaxurl,
                dataType: 'json',
                data: {
                    action: 'tmgmt_search_locations_for_veranstalter',
                    term: request.term
                },
                success: function(data) {
                    if (data.success && data.data) {
                        response($.map(data.data, function(item) {
                            var label = item.title;
                            if (item.city) {
                                label += ' (' + item.city + ')';
                            }
                            return {
                                label: label,
                                value: item.title,
                                id: item.id,
                                city: item.city || ''
                            };
                        }));
                    } else {
                        response([]);
                    }
                }
            });
        },
        minLength: 2,
        select: function(event, ui) {
            event.preventDefault();
            $(this).val('');

            // Prüfen ob Location schon zugeordnet
            var exists = false;
            $('.tmgmt-veranstalter-locations-list input[type="hidden"]').each(function() {
                if ($(this).val() == ui.item.id) {
                    exists = true;
                    return false;
                }
            });

            if (exists) return;

            var cityText = ui.item.city ? ' (' + ui.item.city + ')' : '';
            var html = '<div class="tmgmt-veranstalter-location-item" style="margin-bottom: 5px;">';
            html += '<input type="hidden" name="tmgmt_veranstalter_locations[]" value="' + ui.item.id + '">';
            html += '<span>' + $('<span>').text(ui.item.label).html() + '</span>';
            html += ' <button type="button" class="button tmgmt-veranstalter-remove-location" style="margin-left: 5px;">Entfernen</button>';
            html += '</div>';

            $('.tmgmt-veranstalter-locations-list').append(html);
        }
    });

    // --- Location entfernen ---
    $(document).on('click', '.tmgmt-veranstalter-remove-location', function() {
        $(this).closest('.tmgmt-veranstalter-location-item').remove();
    });

    // --- Neuen Ort anlegen (Dialog) ---
    $(document).on('click', '.tmgmt-veranstalter-create-location-btn', function() {
        Swal.fire({
            title: 'Neuen Ort anlegen',
            html: `
                <div style="text-align: left;">
                    <div style="margin-bottom: 10px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 5px;">Name *</label>
                        <input type="text" id="swal-loc-name" class="swal2-input" style="width: 100%; margin: 0;" placeholder="z.B. Stadthalle Musterstadt">
                    </div>
                    <div style="margin-bottom: 10px; display: flex; gap: 10px;">
                        <div style="flex: 3;">
                            <label style="display: block; font-weight: 600; margin-bottom: 5px;">Straße</label>
                            <input type="text" id="swal-loc-street" class="swal2-input" style="width: 100%; margin: 0;">
                        </div>
                        <div style="flex: 1;">
                            <label style="display: block; font-weight: 600; margin-bottom: 5px;">Nr.</label>
                            <input type="text" id="swal-loc-number" class="swal2-input" style="width: 100%; margin: 0;">
                        </div>
                    </div>
                    <div style="margin-bottom: 10px; display: flex; gap: 10px;">
                        <div style="flex: 1;">
                            <label style="display: block; font-weight: 600; margin-bottom: 5px;">PLZ</label>
                            <input type="text" id="swal-loc-zip" class="swal2-input" style="width: 100%; margin: 0;">
                        </div>
                        <div style="flex: 2;">
                            <label style="display: block; font-weight: 600; margin-bottom: 5px;">Ort</label>
                            <input type="text" id="swal-loc-city" class="swal2-input" style="width: 100%; margin: 0;">
                        </div>
                    </div>
                    <div style="margin-bottom: 10px;">
                        <label style="display: block; font-weight: 600; margin-bottom: 5px;">Land</label>
                        <input type="text" id="swal-loc-country" class="swal2-input" style="width: 100%; margin: 0;" value="Deutschland">
                    </div>
                </div>
            `,
            width: 500,
            showCancelButton: true,
            confirmButtonText: 'Anlegen & Zuordnen',
            cancelButtonText: 'Abbrechen',
            preConfirm: function() {
                var name = $('#swal-loc-name').val();
                if (!name || name.trim() === '') {
                    Swal.showValidationMessage('Bitte geben Sie einen Namen ein.');
                    return false;
                }
                return {
                    name: name.trim(),
                    street: $('#swal-loc-street').val(),
                    number: $('#swal-loc-number').val(),
                    zip: $('#swal-loc-zip').val(),
                    city: $('#swal-loc-city').val(),
                    country: $('#swal-loc-country').val()
                };
            }
        }).then(function(result) {
            if (result.isConfirmed && result.value) {
                createLocationAndAssign(result.value);
            }
        });
    });

    function createLocationAndAssign(data) {
        Swal.fire({
            title: 'Erstelle Ort...',
            allowOutsideClick: false,
            didOpen: function() {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: '/wp-json/tmgmt/v1/locations',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(data),
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', (typeof tmgmt_vars !== 'undefined' && tmgmt_vars.nonce) ? tmgmt_vars.nonce : wpApiSettings.nonce);
            },
            success: function(response) {
                if (response.success && response.id) {
                    // Add location to list
                    var cityText = response.city ? ' (' + response.city + ')' : '';
                    var html = '<div class="tmgmt-veranstalter-location-item" style="margin-bottom: 5px;">';
                    html += '<input type="hidden" name="tmgmt_veranstalter_locations[]" value="' + response.id + '">';
                    html += '<span>' + escHtml(response.title) + cityText + '</span>';
                    html += ' <button type="button" class="button tmgmt-veranstalter-remove-location" style="margin-left: 5px;">Entfernen</button>';
                    html += '</div>';

                    $('.tmgmt-veranstalter-locations-list').append(html);
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Ort erstellt',
                        text: 'Der Ort "' + response.title + '" wurde erstellt und zugeordnet.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire('Fehler', 'Ort konnte nicht erstellt werden.', 'error');
                }
            },
            error: function(xhr) {
                var msg = 'Unbekannter Fehler';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                Swal.fire('Fehler', msg, 'error');
            }
        });
    }

    function escHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
});
