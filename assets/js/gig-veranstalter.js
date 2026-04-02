jQuery(document).ready(function($) {

    // --- Create New Veranstalter ---
    $('#tmgmt-create-veranstalter-btn').on('click', function() {
        Swal.fire({
            title: 'Neuen Veranstalter anlegen',
            html: '<input type="text" id="swal-veranstalter-name" class="swal2-input" placeholder="Name des Veranstalters">',
            showCancelButton: true,
            confirmButtonText: 'Anlegen',
            cancelButtonText: 'Abbrechen',
            preConfirm: function() {
                var name = $('#swal-veranstalter-name').val();
                if (!name || name.trim() === '') {
                    Swal.showValidationMessage('Bitte geben Sie einen Namen ein.');
                    return false;
                }
                return name.trim();
            }
        }).then(function(result) {
            if (result.isConfirmed && result.value) {
                createVeranstalter(result.value);
            }
        });
    });

    // --- Create Veranstalter via REST API ---
    function createVeranstalter(name) {
        Swal.fire({
            title: 'Erstelle Veranstalter...',
            allowOutsideClick: false,
            didOpen: function() {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: '/wp-json/tmgmt/v1/veranstalter',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ name: name }),
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', (typeof tmgmt_vars !== 'undefined' && tmgmt_vars.nonce) ? tmgmt_vars.nonce : wpApiSettings.nonce);
            },
            success: function(response) {
                if (response.success && response.id) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Veranstalter erstellt',
                        text: 'Der Veranstalter "' + response.title + '" wurde erfolgreich erstellt.',
                        showCancelButton: true,
                        confirmButtonText: 'Verknüpfen',
                        cancelButtonText: 'Bearbeiten'
                    }).then(function(result) {
                        if (result.isConfirmed) {
                            // Link to this event
                            loadVeranstalterDetails(response.id);
                        } else if (result.dismiss === Swal.DismissReason.cancel) {
                            // Open edit page in new tab
                            var editUrl = '/wp-admin/post.php?post=' + response.id + '&action=edit';
                            window.open(editUrl, '_blank');
                        }
                    });
                } else {
                    Swal.fire('Fehler', 'Veranstalter konnte nicht erstellt werden.', 'error');
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

    // --- Veranstalter AJAX Autocomplete Search ---
    $('#tmgmt_veranstalter_search').autocomplete({
        source: function(request, response) {
            $.ajax({
                url: ajaxurl,
                dataType: 'json',
                data: {
                    action: 'tmgmt_search_veranstalter',
                    term: request.term
                },
                success: function(data) {
                    var items = $.isArray(data) ? data : (data.data || []);
                    response($.map(items, function(item) {
                        var label = item.title || item.label || '';
                        if (item.city) {
                            label += ' (' + item.city + ')';
                        }
                        return {
                            label: label,
                            value: label,
                            id: item.id
                        };
                    }));
                }
            });
        },
        minLength: 2,
        select: function(event, ui) {
            event.preventDefault();
            $(this).val('');
            loadVeranstalterDetails(ui.item.id);
        }
    });

    // --- Load Veranstalter Details via AJAX ---
    function loadVeranstalterDetails(veranstalterId) {
        $.ajax({
            url: ajaxurl,
            dataType: 'json',
            data: {
                action: 'tmgmt_get_veranstalter_details',
                id: veranstalterId
            },
            success: function(response) {
                if (response.success && response.data) {
                    renderVeranstalterDetails(response.data);
                }
            }
        });
    }

    // --- Render Veranstalter Details in Meta Box ---
    function renderVeranstalterDetails(data) {
        // Set hidden field
        $('#tmgmt_event_veranstalter_id').val(data.id);

        // Name with edit link
        var nameHtml = data.title;
        if (data.edit_url) {
            nameHtml = '<a href="' + escHtml(data.edit_url) + '" target="_blank">' + escHtml(data.title) + '</a>';
        } else {
            nameHtml = escHtml(data.title);
        }
        $('#tmgmt-veranstalter-name').html(nameHtml);

        // Address
        var addrParts = [];
        if (data.address) {
            var streetLine = $.trim((data.address.street || '') + ' ' + (data.address.number || ''));
            if (streetLine) addrParts.push(escHtml(streetLine));
            var cityLine = $.trim((data.address.zip || '') + ' ' + (data.address.city || ''));
            if (cityLine) addrParts.push(escHtml(cityLine));
            if (data.address.country) addrParts.push(escHtml(data.address.country));
        }
        $('#tmgmt-veranstalter-address-content').html(
            addrParts.length > 0 ? addrParts.join('<br>') : '<em>Keine Adresse hinterlegt</em>'
        );

        // Contacts
        renderContacts(data.contacts || []);

        // Locations
        renderLocations(data.locations || []);

        // Toggle visibility: hide search, show info
        $('#tmgmt-veranstalter-search-wrap').hide();
        $('#tmgmt-veranstalter-info').show();
    }

    // --- Render Contacts ---
    function renderContacts(contacts) {
        var $container = $('#tmgmt-veranstalter-contacts-content');
        if (!contacts.length) {
            $container.html('<em>Keine Kontakte zugeordnet</em>');
            return;
        }
        var html = '';
        $.each(contacts, function(i, contact) {
            html += '<div style="margin-bottom: 8px; padding: 6px; background: #f9f9f9; border-left: 3px solid #2271b1;">';
            html += '<strong>' + escHtml(contact.role_label) + ':</strong> ' + escHtml(contact.name);
            if (contact.email) {
                html += '<br><span class="dashicons dashicons-email" style="font-size: 14px; width: 14px; height: 14px; vertical-align: text-bottom;"></span> ' + escHtml(contact.email);
            }
            if (contact.phone) {
                html += '<br><span class="dashicons dashicons-phone" style="font-size: 14px; width: 14px; height: 14px; vertical-align: text-bottom;"></span> ' + escHtml(contact.phone);
            }
            html += '</div>';
        });
        $container.html(html);
    }

    // --- Render Locations ---
    function renderLocations(locations) {
        var $container = $('#tmgmt-veranstalter-locations-content');
        if (!locations.length) {
            $container.html('<em>Keine Veranstaltungsorte zugeordnet</em>');
            return;
        }

        var html = '<select id="tmgmt-veranstalter-location-select" style="width: 100%; margin-bottom: 8px;">';
        html += '<option value="">-- Ort auswählen --</option>';
        $.each(locations, function(i, loc) {
            html += '<option value="' + loc.id + '"'
                + ' data-title="' + escAttr(loc.title) + '"'
                + ' data-street="' + escAttr(loc.street) + '"'
                + ' data-number="' + escAttr(loc.number) + '"'
                + ' data-zip="' + escAttr(loc.zip) + '"'
                + ' data-city="' + escAttr(loc.city) + '"'
                + ' data-country="' + escAttr(loc.country) + '"'
                + ' data-lat="' + escAttr(loc.lat || '') + '"'
                + ' data-lng="' + escAttr(loc.lng || '') + '"'
                + '>' + escHtml(loc.title) + '</option>';
        });
        html += '</select>';
        html += '<div id="tmgmt-veranstalter-location-address"></div>';

        $container.html(html);
    }

    // --- Location Selection: update Event Details location display ---
    $(document).on('change', '#tmgmt-veranstalter-location-select', function() {
        var $selected = $(this).find(':selected');
        var locationId = $(this).val();

        // Set hidden field in Event Details box
        $('#tmgmt_event_location_id').val(locationId);

        if (!locationId) {
            // Cleared selection — clear location address display and hide location info
            $('#tmgmt-veranstalter-location-address').html('');
            // Also update Event Details box
            $('#tmgmt-location-info').hide();
            $('#tmgmt-location-search-wrap').show();
            return;
        }

        // Read data attributes from selected option
        var title   = $selected.data('title') || '';
        var street  = $selected.data('street') || '';
        var number  = $selected.data('number') || '';
        var zip     = $selected.data('zip') || '';
        var city    = $selected.data('city') || '';
        var country = $selected.data('country') || '';
        var lat     = $selected.data('lat') || '';
        var lng     = $selected.data('lng') || '';

        // Show selected location address in Veranstalter meta box
        var addrParts = [];
        var streetLine = $.trim(street + ' ' + number);
        if (streetLine) addrParts.push(escHtml(streetLine));
        var cityLine = $.trim(zip + ' ' + city);
        if (cityLine) addrParts.push(escHtml(cityLine));
        if (country) addrParts.push(escHtml(country));
        $('#tmgmt-veranstalter-location-address').html(addrParts.join('<br>'));
        
        // Also update Event Details location info box
        $('#tmgmt-location-name').html('<a href="/wp-admin/post.php?post=' + locationId + '&action=edit" target="_blank">' + escHtml(title) + '</a>');
        $('#tmgmt-location-address').html(addrParts.length > 0 ? addrParts.join('<br>') : '<em>Keine Adresse hinterlegt</em>');
        
        if (lat || lng) {
            $('#tmgmt-location-geo').html('<span class="dashicons dashicons-location" style="font-size: 14px; width: 14px; height: 14px; vertical-align: text-bottom;"></span> ' + lat + ', ' + lng);
        } else {
            $('#tmgmt-location-geo').html('');
        }
        
        // Hide notes for now (would need to fetch from server)
        $('#tmgmt-location-notes').hide();
        
        // Show location info, hide search
        $('#tmgmt-location-search-wrap').hide();
        $('#tmgmt-location-info').show();
    });

    // --- Remove Veranstalter Link ---
    $(document).on('click', '#tmgmt-veranstalter-remove', function() {
        // Clear hidden fields
        $('#tmgmt_event_veranstalter_id').val('');
        $('#tmgmt_event_location_id').val('');

        // Clear dynamic content areas
        $('#tmgmt-veranstalter-name').html('');
        $('#tmgmt-veranstalter-address-content').html('');
        $('#tmgmt-veranstalter-contacts-content').html('');
        $('#tmgmt-veranstalter-locations-content').html('');

        // Toggle visibility: show search, hide info
        $('#tmgmt-veranstalter-info').hide();
        $('#tmgmt-veranstalter-search-wrap').show();
        
        // Also reset Event Details location info box
        $('#tmgmt-location-info').hide();
        $('#tmgmt-location-search-wrap').show();
    });

    // --- Utility: HTML escape ---
    function escHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function escAttr(str) {
        return escHtml(str);
    }

});
