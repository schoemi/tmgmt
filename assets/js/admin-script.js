jQuery(document).ready(function($) {
    var $statusSelect = $('#tmgmt_status');
    var initialStatus = $statusSelect.val();
    var $publishButton = $('#publish');
    var validationRules = tmgmt_vars.validation_rules;

    // Update initial status when changed (if we want to track "previous" properly, 
    // we actually need to capture it on page load, which we did. 
    // But if the user changes it multiple times before saving, we might want the *original* DB value?
    // For "Save without changing status", we usually mean "Revert to what it was in the DB".
    // However, standard WP behavior is just "don't save the new value".
    // Let's stick to: Revert to initialStatus (value on page load).

    // --- Log Table Sorting & Filtering ---
    
    // Filter by User
    $('#tmgmt-log-user-filter').on('change', function() {
        var user = $(this).val();
        var $rows = $('#tmgmt-log-table tbody tr');
        
        if (!user) {
            $rows.show();
        } else {
            $rows.each(function() {
                var rowUser = $(this).find('td:nth-child(2)').text(); // 2nd column is User
                if (rowUser === user) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        }
    });

    // Sort Columns
    $('.sortable').on('click', function() {
        var table = $(this).parents('table').eq(0);
        var rows = table.find('tr:gt(0)').toArray().sort(comparer($(this).index()));
        this.asc = !this.asc;
        if (!this.asc) { rows = rows.reverse(); }
        for (var i = 0; i < rows.length; i++) { table.append(rows[i]); }
        
        // Update Icons
        $('.sortable span').removeClass('dashicons-arrow-up-alt2 dashicons-arrow-down-alt2').addClass('dashicons-sort');
        $(this).find('span').removeClass('dashicons-sort').addClass(this.asc ? 'dashicons-arrow-up-alt2' : 'dashicons-arrow-down-alt2');
    });

    function comparer(index) {
        return function(a, b) {
            var valA = getCellValue(a, index), valB = getCellValue(b, index);
            return $.isNumeric(valA) && $.isNumeric(valB) ? valA - valB : valA.toString().localeCompare(valB);
        };
    }

    function getCellValue(row, index) {
        var cell = $(row).children('td').eq(index);
        // Prefer data-value attribute if exists (for dates), else text
        return cell.attr('data-value') ? cell.attr('data-value') : cell.text();
    }

    // --- End Log Table ---

    $publishButton.on('click', function(e) {
        var selectedStatus = $statusSelect.val();
        
        // If status hasn't changed, or no rules for this status, let it pass
        if (selectedStatus === initialStatus || !validationRules[selectedStatus]) {
            return true;
        }

        var missingFields = [];
        var rules = validationRules[selectedStatus];

        // Check each required field
        rules.forEach(function(rule) {
            var $field = $('#' + rule.id);
            if ($field.length && !$field.val()) {
                missingFields.push(rule);
            }
        });

        if (missingFields.length === 0) {
            return true;
        }

        // Prevent submission
        e.preventDefault();

        // Build Modal Content
        var modalHtml = '<div id="tmgmt-validation-modal" style="display:none;">';
        modalHtml += '<p>Für den Status <strong>' + $("#tmgmt_status option:selected").text().trim() + '</strong> fehlen folgende Pflichtfelder:</p>';
        modalHtml += '<form id="tmgmt-validation-form">';
        
        missingFields.forEach(function(field) {
            var $originalField = $('#' + field.id);
            var inputType = $originalField.attr('type') || 'text';
            var stepAttr = $originalField.attr('step') ? 'step="' + $originalField.attr('step') + '"' : '';
            
            modalHtml += '<div class="tmgmt-modal-field" style="margin-bottom:10px;">';
            modalHtml += '<label style="display:block;font-weight:bold;">' + field.label + '</label>';
            modalHtml += '<input type="' + inputType + '" name="' + field.id + '" ' + stepAttr + ' class="widefat" style="width:100%;">';
            modalHtml += '</div>';
        });

        modalHtml += '</form>';
        modalHtml += '</div>';

        // Remove existing modal if any
        $('#tmgmt-validation-modal').remove();
        $('body').append(modalHtml);

        // Open Dialog
        $('#tmgmt-validation-modal').dialog({
            title: 'Pflichtfelder fehlen',
            modal: true,
            width: 400,
            buttons: {
                "Speichern & Status ändern": function() {
                    // Copy values back to original form
                    var allFilled = true;
                    $('#tmgmt-validation-form input').each(function() {
                        var val = $(this).val();
                        var id = $(this).attr('name');
                        if (!val) {
                            allFilled = false;
                            $(this).css('border', '1px solid red');
                        } else {
                            $('#' + id).val(val);
                            $(this).css('border', '');
                        }
                    });

                    if (allFilled) {
                        $(this).dialog("close");
                        // Trigger click again, but bypass validation? 
                        // Or just submit the form directly.
                        // Clicking #publish again might trigger this handler again.
                        // We can use a flag or unbind.
                        $publishButton.off('click'); 
                        $publishButton.click();
                    }
                },
                "Speichern (Status zurücksetzen)": function() {
                    // Revert status
                    $statusSelect.val(initialStatus);
                    $(this).dialog("close");
                    // Submit
                    $publishButton.off('click');
                    $publishButton.click();
                }
            }
        });
    });
    
    // --- Geocoding & Map ---
    var map;
    var marker;

    function initMap(lat, lng) {
        if (!lat || !lng) return;
        
        var latNum = parseFloat(lat);
        var lngNum = parseFloat(lng);

        if (isNaN(latNum) || isNaN(lngNum)) return;

        $('#tmgmt-map').show();
        
        if (!map) {
            map = L.map('tmgmt-map').setView([latNum, lngNum], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);
            marker = L.marker([latNum, lngNum]).addTo(map);
        } else {
            map.setView([latNum, lngNum], 13);
            if (marker) {
                marker.setLatLng([latNum, lngNum]);
            } else {
                marker = L.marker([latNum, lngNum]).addTo(map);
            }
            // Fix map rendering issues when showing hidden div
            setTimeout(function(){ map.invalidateSize();}, 100);
        }
    }

    // Init map on load if coords exist
    var initialLat = $('#tmgmt_geo_lat').val();
    var initialLng = $('#tmgmt_geo_lng').val();
    if (initialLat && initialLng) {
        initMap(initialLat, initialLng);
    }

    $('#tmgmt-geocode-btn').on('click', function() {
        console.log('Geocode button clicked');
        var btn = $(this);
        var street = $('#tmgmt_venue_street').val();
        var number = $('#tmgmt_venue_number').val();
        var zip = $('#tmgmt_venue_zip').val();
        var city = $('#tmgmt_venue_city').val();
        var country = $('#tmgmt_venue_country').val();

        console.log('Address data:', street, number, zip, city, country);

        if (!street || !city) {
            Swal.fire({
                icon: 'warning',
                title: 'Fehlende Angaben',
                text: 'Bitte mindestens Straße und Ort angeben.'
            });
            return;
        }

        btn.prop('disabled', true).text('Suche...');

        // Construct Query
        var query = street + ' ' + number + ', ' + zip + ' ' + city + ', ' + country;
        
        // Use Nominatim API
        $.ajax({
            url: 'https://nominatim.openstreetmap.org/search',
            data: {
                q: query,
                format: 'json',
                limit: 1
            },
            dataType: 'json',
            success: function(data) {
                btn.prop('disabled', false).text('Adresse auflösen');
                if (data && data.length > 0) {
                    var lat = data[0].lat;
                    var lon = data[0].lon;
                    
                    $('#tmgmt_geo_lat').val(lat);
                    $('#tmgmt_geo_lng').val(lon);
                    
                    initMap(lat, lon);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Nicht gefunden',
                        text: 'Adresse konnte nicht gefunden werden.'
                    });
                }
            },
            error: function() {
                btn.prop('disabled', false).text('Adresse auflösen');
                Swal.fire({
                    icon: 'error',
                    title: 'Fehler',
                    text: 'Fehler bei der Geocodierung.'
                });
            }
        });
    });
    
    // --- File Deletion ---
    $(document).on('click', '.tmgmt-delete-file', function() {
        var btn = $(this);
        var attachmentId = btn.data('id');

        Swal.fire({
            title: 'Datei löschen?',
            text: "Möchten Sie diese Datei wirklich löschen? Dies kann nicht rückgängig gemacht werden.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ja, löschen!',
            cancelButtonText: 'Abbrechen'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'tmgmt_delete_file',
                        attachment_id: attachmentId,
                        nonce: tmgmt_vars.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            btn.closest('tr').fadeOut(function() {
                                $(this).remove();
                            });
                            Swal.fire(
                                'Gelöscht!',
                                'Die Datei wurde gelöscht.',
                                'success'
                            );
                        } else {
                            Swal.fire(
                                'Fehler!',
                                response.data.message,
                                'error'
                            );
                        }
                    },
                    error: function() {
                        Swal.fire(
                            'Fehler!',
                            'Fehler beim Löschen.',
                            'error'
                        );
                    }
                });
            }
        });
    });
});
