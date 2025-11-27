jQuery(document).ready(function($) {
    // Tab Switching Logic
    $('.tmgmt-tab-btn').on('click', function() {
        const tabId = $(this).data('tab');
        
        // Update Buttons
        $('.tmgmt-tab-btn').removeClass('active');
        $(this).addClass('active');
        
        // Update Content
        $('.tmgmt-tab-content').removeClass('active');
        $('#tmgmt-tab-' + tabId).addClass('active');
        
        // Fix Map Rendering if switching to Live View
        if (tabId === 'live' && map) {
            setTimeout(function() {
                map.invalidateSize();
            }, 100);
        }
    });

    // Map Toggle Logic (Mobile)
    $('#tmgmt-map-toggle').on('click', function() {
        const $map = $('#tmgmt-live-map');
        $map.toggleClass('collapsed');
        
        if ($map.hasClass('collapsed')) {
            $(this).html('<i class="fas fa-map"></i> Karte anzeigen');
        } else {
            $(this).html('<i class="fas fa-map"></i> Karte ausblenden');
            // Invalidate size after transition to ensure map renders correctly
            setTimeout(function() {
                if (map) map.invalidateSize();
            }, 350);
        }
    });

    if (!$('#tmgmt-live-map').length) return;

    const tourId = tmgmt_live_vars.tour_id;
    const apiUrl = tmgmt_live_vars.api_url;
    const nonce = tmgmt_live_vars.nonce;

    let map;
    let routeLayer;
    let positionMarker;
    let waypoints = [];
    let testMode = false;
    let simulatedOffset = 0;

    // Init Map
    function initMap() {
        map = L.map('tmgmt-live-map').setView([51.1657, 10.4515], 6); // Default Germany

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        // Map Click for Test Mode
        map.on('click', function(e) {
            if (testMode) {
                updateTestPosition(e.latlng.lat, e.latlng.lng);
            }
        });
    }

    function updateTestPosition(lat, lng) {
        // Immediate feedback
        updatePositionMarker(lat, lng);
        
        $.ajax({
            url: apiUrl + '/live/test-mode',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ lat: lat, lng: lng }),
            beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', nonce); },
            success: function(data) {
                handleTestMode(data);
            }
        });
    }

    // Fetch Data
    function loadTourData() {
        $.ajax({
            url: apiUrl + '/tours/' + tourId + '/live',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', nonce);
            },
            success: function(data) {
                renderTour(data);
                handleTestMode(data.test_mode);
            },
            error: function(xhr, status, error) {
                console.error('Error loading tour data:', status, error);
                console.log('Response:', xhr.responseText);
                $('#tmgmt-tour-title').text('Fehler beim Laden');
                $('#tmgmt-tour-status').text('API Error').addClass('error');
            }
        });
    }

    function renderTour(data) {
        $('#tmgmt-tour-title').text(data.title);
        waypoints = data.waypoints;

        // Render Timeline
        renderTimeline();

        // Clear existing layers
        if (routeLayer) map.removeLayer(routeLayer);

        const latlngs = [];
        const markers = L.layerGroup().addTo(map);

        waypoints.forEach(function(wp) {
            const latlng = [wp.lat, wp.lng];
            latlngs.push(latlng);

            // Marker Icon based on type
            let color = '#3388ff';
            if (wp.type === 'start') color = '#28a745';
            if (wp.type === 'end') color = '#dc3545';
            if (wp.type && wp.type.indexOf('shuttle') !== -1) color = '#fd7e14';

            const markerHtml = `<div style="background-color: ${color}; width: 12px; height: 12px; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 0 4px rgba(0,0,0,0.3);"></div>`;
            
            const icon = L.divIcon({
                className: 'tmgmt-wp-icon',
                html: markerHtml,
                iconSize: [16, 16],
                iconAnchor: [8, 8]
            });

            let popupContent = `<strong>${wp.name}</strong>`;
            if (wp.type === 'event') {
                if (wp.organizer) popupContent += `<br><small>Veranstalter: ${wp.organizer}</small>`;
                if (wp.show_start) popupContent += `<br><strong>Showtime: ${wp.show_start} Uhr</strong>`;
            }
            popupContent += `<br><span style="color:#666">Geplant: ${wp.planned_arrival || '-'}</span>`;

            L.marker(latlng, { icon: icon })
                .bindPopup(popupContent)
                .addTo(markers);
        });

        // Draw Polyline (simple straight lines for now, later use geometry from API if available)
        routeLayer = L.polyline(latlngs, { color: 'blue', weight: 3, opacity: 0.6 }).addTo(map);
        
        // Fit bounds only on first load
        if (!positionMarker) {
            map.fitBounds(routeLayer.getBounds(), { padding: [50, 50] });
        }
    }

    function renderTimeline() {
        const $container = $('#tmgmt-timeline');
        $container.empty();

        waypoints.forEach((wp, index) => {
            const time = wp.planned_arrival || wp.planned_departure || '--:--';
            
            let icon = '📍';
            if (wp.type === 'event') icon = '🎤';
            else if (wp.type === 'start') icon = '🏁';
            else if (wp.type === 'end') icon = '🏁';
            else if (wp.type && wp.type.indexOf('shuttle') !== -1) icon = '🚌';
            
            const html = `
                <div class="tmgmt-timeline-item" id="wp-item-${index}">
                    <div class="tmgmt-timeline-time">${time}</div>
                    <div class="tmgmt-timeline-icon">${icon}</div>
                    <div class="tmgmt-timeline-name">${wp.name}</div>
                    <div class="tmgmt-timeline-eta"></div>
                </div>
            `;
            $container.append(html);
        });
    }

    function handleTestMode(tm) {
        testMode = tm.active;
        simulatedOffset = tm.offset;

        if (testMode) {
            $('#tmgmt-test-controls').show();
            $('#tmgmt-tour-status').text('Test Modus').addClass('warning');
            $('.leaflet-container').css('cursor', 'crosshair'); // Indicate clickable
            
            // Update Sim Time Display
            if (tm.simulated_iso) {
                $('#tmgmt-test-time').val(tm.simulated_iso);
            } else {
                // Fallback if ISO not provided
                const now = new Date();
                const simTime = new Date(now.getTime() + (simulatedOffset * 1000));
                // Format to YYYY-MM-DDTHH:mm
                const iso = simTime.getFullYear() + '-' + 
                            String(simTime.getMonth()+1).padStart(2, '0') + '-' + 
                            String(simTime.getDate()).padStart(2, '0') + 'T' + 
                            String(simTime.getHours()).padStart(2, '0') + ':' + 
                            String(simTime.getMinutes()).padStart(2, '0');
                $('#tmgmt-test-time').val(iso);
            }

            // Calculate Sim Time Object for Status
            const now = new Date();
            const simTime = new Date(now.getTime() + (simulatedOffset * 1000));

            if (tm.simulated_position) {
                updatePositionMarker(tm.simulated_position.lat, tm.simulated_position.lng);
                calculateStatus(tm.simulated_position.lat, tm.simulated_position.lng, simTime);
            }
        } else {
            $('#tmgmt-test-controls').hide();
            startRealTracking();
        }
    }

    // Time Input Change
    $('#tmgmt-test-time').on('change', function() {
        const val = $(this).val();
        if (!val) return;
        
        $.ajax({
            url: apiUrl + '/live/test-mode',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ simulated_time: val }),
            beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', nonce); },
            success: function(data) {
                handleTestMode(data);
            }
        });
    });

    function updatePositionMarker(lat, lng) {
        const latlng = [lat, lng];
        
        if (!positionMarker) {
            const icon = L.divIcon({
                className: 'tmgmt-pos-icon',
                html: '<div style="background-color: #007bff; width: 16px; height: 16px; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 0 10px rgba(0,123,255,0.5);"></div>',
                iconSize: [22, 22],
                iconAnchor: [11, 11]
            });
            positionMarker = L.marker(latlng, { icon: icon, zIndexOffset: 1000, draggable: testMode }).addTo(map);
            
            // Allow dragging in test mode
            positionMarker.on('dragend', function(e) {
                if (testMode) {
                    const pos = e.target.getLatLng();
                    updateTestPosition(pos.lat, pos.lng);
                }
            });
        } else {
            positionMarker.setLatLng(latlng);
            if (positionMarker.dragging) {
                testMode ? positionMarker.dragging.enable() : positionMarker.dragging.disable();
            }
        }
    }

    function updateTestPosition(lat, lng) {
        $.ajax({
            url: apiUrl + '/live/test-mode',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ lat: lat, lng: lng }),
            beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', nonce); },
            success: function(data) {
                handleTestMode(data);
            }
        });
    }

    // Controls
    $('.tmgmt-btn-test').click(function() {
        const delta = $(this).data('value') * 60; // minutes to seconds
        $.ajax({
            url: apiUrl + '/live/test-mode',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ offset_delta: delta }),
            beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', nonce); },
            success: function(data) {
                handleTestMode(data);
            }
        });
    });

    // Real Tracking (Placeholder)
    function startRealTracking() {
        if ("geolocation" in navigator) {
            navigator.geolocation.watchPosition(function(position) {
                updatePositionMarker(position.coords.latitude, position.coords.longitude);
                calculateStatus(position.coords.latitude, position.coords.longitude, new Date());
            }, function(error) {
                console.warn("Geolocation error:", error);
            }, {
                enableHighAccuracy: true
            });
        }
    }

    // Simple Status Calculation (Heuristic)
    function calculateStatus(lat, lng, currentTime) {
        if (waypoints.length < 2) return;

        // Find current segment
        let closestSegmentIndex = -1;
        let minSegmentDist = Infinity;

        for (let i = 0; i < waypoints.length - 1; i++) {
            const p = { x: lat, y: lng };
            const v = { x: waypoints[i].lat, y: waypoints[i].lng };
            const w = { x: waypoints[i+1].lat, y: waypoints[i+1].lng };
            
            const d = distToSegment(p, v, w);
            if (d < minSegmentDist) {
                minSegmentDist = d;
                closestSegmentIndex = i;
            }
        }

        // Determine Next Stop
        // If we are very close to the END of the segment, we might be "at" the stop.
        // But generally, if we are on segment i, we are heading to i+1.
        let nextStopIndex = closestSegmentIndex + 1;
        
        // Edge case: if we are closer to the start of the segment than the line itself? 
        // The segment logic handles "projection", so it's robust enough.
        
        // Update Timeline UI
        $('.tmgmt-timeline-item').removeClass('active passed');
        
        for (let i = 0; i < waypoints.length; i++) {
            const $item = $('#wp-item-' + i);
            if (i < nextStopIndex) {
                $item.addClass('passed');
                $item.find('.tmgmt-timeline-eta').text('');
            } else if (i === nextStopIndex) {
                $item.addClass('active');
            }
        }

        const targetWp = waypoints[nextStopIndex];

        if (targetWp) {
            $('#tmgmt-next-stop').text(targetWp.name);
            $('#tmgmt-planned-time').text(targetWp.planned_arrival || '-');

            // Calculate ETA
            // Simple: Distance / Speed (60km/h)
            const distToTarget = getDistanceFromLatLonInKm(lat, lng, targetWp.lat, targetWp.lng);
            const speedKmh = 60; 
            const hours = distToTarget / speedKmh;
            const seconds = hours * 3600;
            
            const eta = new Date(currentTime.getTime() + (seconds * 1000));
            const etaStr = eta.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            
            $('#tmgmt-eta-time').text(etaStr);
            
            // Update ETA in timeline for active item
            $('#wp-item-' + nextStopIndex).find('.tmgmt-timeline-eta').text('ETA: ' + etaStr);

            // Diff
            if (targetWp.planned_arrival) {
                const [ph, pm] = targetWp.planned_arrival.split(':');
                const plannedDate = new Date(currentTime);
                plannedDate.setHours(ph, pm, 0);
                
                // Handle date rollover if needed (not implemented yet)
                
                const diffMs = eta - plannedDate;
                const diffMins = Math.round(diffMs / 60000);
                
                let diffText = diffMins > 0 ? `+${diffMins} min` : `${diffMins} min`;
                $('#tmgmt-time-diff').text(diffText);

                // Color
                const color = diffMins > 5 ? '#d63638' : (diffMins > 0 ? '#dba617' : '#00a32a');
                $('#tmgmt-time-diff').css('color', color);
                
                // Update timeline item color/text
                const $etaEl = $('#wp-item-' + nextStopIndex).find('.tmgmt-timeline-eta');
                $etaEl.append(` <span style="color:${color}">(${diffText})</span>`);

                // Update Main Status Badge
                const $statusBadge = $('#tmgmt-tour-status');
                if (!testMode) { // Don't overwrite "Test Modus" text if active, or maybe append?
                    if (diffMins > 15) {
                        $statusBadge.text('Verspätet').css({background: '#d63638', color: '#fff'});
                    } else if (diffMins > 5) {
                        $statusBadge.text('Leichte Verzögerung').css({background: '#dba617', color: '#fff'});
                    } else {
                        $statusBadge.text('Pünktlich').css({background: '#00a32a', color: '#fff'});
                    }
                }
            }
        }
    }

    // Math Helpers for Segment Distance (Flat approximation is sufficient for selection)
    function sqr(x) { return x * x }
    function dist2(v, w) { return sqr(v.x - w.x) + sqr(v.y - w.y) }
    function distToSegmentSquared(p, v, w) {
        var l2 = dist2(v, w);
        if (l2 == 0) return dist2(p, v);
        var t = ((p.x - v.x) * (w.x - v.x) + (p.y - v.y) * (w.y - v.y)) / l2;
        t = Math.max(0, Math.min(1, t));
        return dist2(p, { x: v.x + t * (w.x - v.x),
                            y: v.y + t * (w.y - v.y) });
    }
    function distToSegment(p, v, w) { return Math.sqrt(distToSegmentSquared(p, v, w)); }

    function getDistanceFromLatLonInKm(lat1,lon1,lat2,lon2) {
        var R = 6371; // Radius of the earth in km
        var dLat = deg2rad(lat2-lat1);  // deg2rad below
        var dLon = deg2rad(lon2-lon1); 
        var a = 
            Math.sin(dLat/2) * Math.sin(dLat/2) +
            Math.cos(deg2rad(lat1)) * Math.cos(deg2rad(lat2)) * 
            Math.sin(dLon/2) * Math.sin(dLon/2)
            ; 
        var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a)); 
        var d = R * c; // Distance in km
        return d;
    }

    function deg2rad(deg) {
        return deg * (Math.PI/180)
    }

    // Start
    initMap();
    loadTourData();
    
    // Poll for updates (every 10s)
    setInterval(loadTourData, 10000);
});
