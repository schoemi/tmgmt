jQuery(document).ready(function($) {
    
    // --- Map Initialization ---
    if (typeof tmgmtTourData !== 'undefined' && tmgmtTourData.length > 0 && typeof L !== 'undefined' && $('#tmgmt-tour-map').length) {
        var map = L.map('tmgmt-tour-map');
        var bounds = [];
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        var counter = 1;
        tmgmtTourData.forEach(function(item) {
            var lat = null;
            var lng = null;
            var title = '';
            var iconColor = 'blue'; // Default

            if (item.type === 'start') {
                lat = item.lat;
                lng = item.lng;
                title = 'Start: ' + item.location;
            } else if (item.type === 'event') {
                lat = item.lat;
                lng = item.lng;
                title = counter + '. ' + item.title + ' (' + item.location + ')';
                counter++;
            } else if (item.type === 'shuttle_stop') {
                lat = item.lat;
                lng = item.lng;
                title = 'Shuttle: ' + item.location;
            }
            
            if (lat && lng) {
                var marker = L.marker([lat, lng], {
                    title: title
                }).addTo(map);
                
                marker.bindPopup('<strong>' + title + '</strong>');
                bounds.push([lat, lng]);
            }
        });

        if (bounds.length > 0) {
            map.fitBounds(bounds, {padding: [50, 50]});
        } else {
            map.setView([51.1657, 10.4515], 6); // Default Germany
        }
    }

    // --- Save Settings ---
    $('#tmgmt-save-settings').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true);
        
        var data = {
            action: 'tmgmt_save_tour_settings',
            nonce: tmgmt_vars.nonce,
            tour_id: $('#tmgmt_tour_id').val(),
            date: $('#tmgmt_tour_date').val(),
            mode: $('#tmgmt_tour_mode').val(),
            bus_travel: $('#tmgmt_tour_bus_travel').is(':checked'),
            end_at_base: $('#tmgmt_tour_end_at_base').is(':checked'),
            pickup_shuttle: $('#tmgmt_tour_pickup_shuttle').val(),
            dropoff_shuttle: $('#tmgmt_tour_dropoff_shuttle').val()
        };

        $.post(tmgmt_vars.ajaxurl, data, function(response) {
            btn.prop('disabled', false);
            if (response.success) {
                alert('Einstellungen gespeichert.');
            } else {
                alert('Fehler: ' + response.data);
            }
        });
    });

    // --- Calculate Tour ---
    $('#tmgmt-calc-tour').on('click', function() {
        var btn = $(this);
        var spinner = $('#tmgmt-spinner');
        
        var date = $('#tmgmt_tour_date').val();
        var mode = $('#tmgmt_tour_mode').val();
        var tour_id = $('#tmgmt_tour_id').val();
        
        if (!date) {
            alert('Bitte wählen Sie ein Datum.');
            return;
        }
        
        btn.prop('disabled', true);
        spinner.addClass('is-active');
        
        // First Save Settings, then Calculate
        var saveData = {
            action: 'tmgmt_save_tour_settings',
            nonce: tmgmt_vars.nonce,
            tour_id: tour_id,
            date: date,
            mode: mode,
            bus_travel: $('#tmgmt_tour_bus_travel').is(':checked'),
            end_at_base: $('#tmgmt_tour_end_at_base').is(':checked'),
            pickup_shuttle: $('#tmgmt_tour_pickup_shuttle').val(),
            dropoff_shuttle: $('#tmgmt_tour_dropoff_shuttle').val()
        };

        $.post(tmgmt_vars.ajaxurl, saveData, function(saveResponse) {
            if (!saveResponse.success) {
                btn.prop('disabled', false);
                spinner.removeClass('is-active');
                alert('Fehler beim Speichern: ' + saveResponse.data);
                return;
            }

            // Now Calculate
            $.post(tmgmt_vars.ajaxurl, {
                action: 'tmgmt_calculate_tour',
                date: date,
                mode: mode,
                tour_id: tour_id,
                nonce: tmgmt_vars.nonce // We need to check if calculate_tour accepts frontend nonce or if we need to allow it
                // Note: calculate_tour checks 'tmgmt_backend_nonce'. We might need to adjust that or pass the right nonce.
                // Since we are in frontend, we should probably update calculate_tour to accept a generic nonce or a specific frontend one if user is logged in.
                // For now, let's assume we need to fix the nonce check in PHP.
            }, function(response) {
                // We need to save the result (JSON) back to the post meta
                // The backend does this by submitting the form.
                // Here we need another AJAX call to save the result.
                
                if (response.success) {
                    var resultJson = JSON.stringify(response.data);
                    
                    // Save Result
                    $.post(tmgmt_vars.ajaxurl, {
                        action: 'tmgmt_save_tour_result', // We need to create this action
                        nonce: tmgmt_vars.nonce,
                        tour_id: tour_id,
                        tour_data: resultJson
                    }, function(saveResultResponse) {
                        btn.prop('disabled', false);
                        spinner.removeClass('is-active');
                        if (saveResultResponse.success) {
                            location.reload(); // Reload to show new data
                        } else {
                            alert('Fehler beim Speichern des Ergebnisses.');
                        }
                    });
                } else {
                    btn.prop('disabled', false);
                    spinner.removeClass('is-active');
                    alert('Fehler bei der Berechnung: ' + response.data);
                }
            });
        });
    });
});
