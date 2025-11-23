document.addEventListener('DOMContentLoaded', function() {
    const app = document.getElementById('tmgmt-kanban-app');
    if (!app) return;

    const apiUrl = tmgmtData.apiUrl;
    const nonce = tmgmtData.nonce;
    let boardData = null;
    let currentMap = null;

    // Initial Load
    loadBoard();

    function loadBoard() {
        app.innerHTML = '<div class="tmgmt-loading">Lade Daten...</div>';
        fetch(apiUrl + 'kanban', {
            headers: { 'X-WP-Nonce': nonce }
        })
        .then(response => response.json())
        .then(data => {
            boardData = data;
            renderBoard();
        })
        .catch(err => {
            app.innerHTML = '<div class="error">Fehler beim Laden: ' + err.message + '</div>';
        });
    }

    function renderBoard() {
        app.innerHTML = '';
        
        if (!boardData.columns || boardData.columns.length === 0) {
            app.innerHTML = '<div style="padding:20px; text-align:center; color:#666;">Keine Kanban-Spalten gefunden. Bitte im Backend konfigurieren.</div>';
            return;
        }

        const board = document.createElement('div');
        board.className = 'tmgmt-board';

        boardData.columns.forEach(col => {
            const colEl = document.createElement('div');
            colEl.className = 'tmgmt-column';
            colEl.dataset.id = col.id;
            colEl.dataset.statuses = JSON.stringify(col.statuses);

            // Header
            const header = document.createElement('div');
            header.className = 'tmgmt-column-header';
            header.textContent = col.title;
            colEl.appendChild(header);

            // Body
            const body = document.createElement('div');
            body.className = 'tmgmt-column-body';
            
            // Drop Zone Events
            body.addEventListener('dragover', e => {
                e.preventDefault();
                body.style.backgroundColor = '#e2e4e9';
            });
            body.addEventListener('dragleave', e => {
                body.style.backgroundColor = '';
            });
            body.addEventListener('drop', e => {
                e.preventDefault();
                body.style.backgroundColor = '';
                const eventId = e.dataTransfer.getData('text/plain');
                handleDrop(eventId, col);
            });

            // Cards
            const colEvents = boardData.events.filter(ev => col.statuses.includes(ev.status));
            colEvents.forEach(ev => {
                const card = createCard(ev);
                body.appendChild(card);
            });

            colEl.appendChild(body);
            board.appendChild(colEl);
        });

        app.appendChild(board);
    }

    function createCard(ev) {
        const card = document.createElement('div');
        card.className = 'tmgmt-card';
        card.draggable = true;
        card.dataset.id = ev.id;

        card.innerHTML = `
            <div class="tmgmt-card-title">${ev.title}</div>
            <div class="tmgmt-card-meta">
                <span>${ev.date || ''}</span>
                <span>${ev.city || ''}</span>
            </div>
        `;

        card.addEventListener('click', () => openModal(ev.id));
        
        card.addEventListener('dragstart', e => {
            e.dataTransfer.setData('text/plain', ev.id);
            card.style.opacity = '0.5';
        });

        card.addEventListener('dragend', e => {
            card.style.opacity = '1';
        });

        return card;
    }

    function handleDrop(eventId, targetCol) {
        // Determine new status (first one in column)
        const newStatus = targetCol.statuses[0];
        if (!newStatus) return;

        // Optimistic Update
        const eventIndex = boardData.events.findIndex(e => e.id == eventId);
        if (eventIndex > -1) {
            const oldStatus = boardData.events[eventIndex].status;
            if (oldStatus === newStatus) return; // No change

            boardData.events[eventIndex].status = newStatus;
            renderBoard(); // Re-render immediately

            // API Call
            updateEvent(eventId, { status: newStatus })
                .catch(err => {
                    console.error('Update failed', err);
                    // Revert on error
                    boardData.events[eventIndex].status = oldStatus;
                    renderBoard();
                    alert('Fehler beim Speichern des Status.');
                });
        }
    }

    // --- Modal & Editing ---

    const modal = document.getElementById('tmgmt-modal');
    // Re-select elements as they might be inside the modal content which we overwrite? 
    // No, the modal structure in PHP has modal-content. 
    // But I changed the CSS structure. Let's check the PHP structure again.
    // The PHP structure is:
    /*
        <div id="tmgmt-modal" class="tmgmt-modal" style="display:none;">
            <div class="tmgmt-modal-content">
                <span class="tmgmt-close">&times;</span>
                <h2 id="tmgmt-modal-title">Event Details</h2>
                <div id="tmgmt-modal-body">
                    <!-- Form will be injected here -->
                </div>
                <div class="tmgmt-modal-footer">
                    <button id="tmgmt-save-btn" class="button button-primary">Speichern</button>
                </div>
            </div>
        </div>
    */
    // My CSS expects a header, body, footer structure inside content.
    // I should update the PHP structure OR generate the whole content in JS.
    // Generating in JS is easier to match the new CSS structure.
    
    // Let's update the PHP structure first to match the CSS expectations? 
    // Or just overwrite innerHTML of modal-content in JS.
    
    const modalContent = document.querySelector('.tmgmt-modal-content');

    window.onclick = (event) => {
        if (event.target == modal) modal.style.display = 'none';
    };

    function openModal(id) {
        currentEditingId = id;
        modal.style.display = 'flex';
        modalContent.innerHTML = '<div class="tmgmt-loading">Lade Details...</div>';

        fetch(apiUrl + 'events/' + id, {
            headers: { 'X-WP-Nonce': nonce }
        })
        .then(res => res.json())
        .then(data => {
            renderModalContent(data);
        });
    }

    function renderModalContent(data) {
        const meta = data.meta || {};
        const logs = data.logs || [];
        const actions = data.actions || [];

        // Helper to create input
        const createInput = (label, name, value, type = 'text') => `
            <div class="tmgmt-form-group">
                <label>${label}</label>
                <input type="${type}" name="${name}" value="${value || ''}">
            </div>
        `;

        // Helper to create section
        const createSection = (title, content, isCollapsed = false) => `
            <div class="tmgmt-section ${isCollapsed ? 'collapsed' : ''}">
                <div class="tmgmt-section-title">${title}</div>
                <div class="tmgmt-section-content">
                    ${content}
                </div>
            </div>
        `;

        // --- Left Column Content ---
        
        // 1. Anfragedaten
        let inquiryHtml = '';
        inquiryHtml += createInput('Titel', 'title', data.title);
        inquiryHtml += createInput('Datum', 'date', meta.event_date, 'date');
        inquiryHtml += createInput('Startzeit', 'start_time', meta.event_start_time, 'time');
        // Status is special, maybe read-only here if we use transitions? 
        // Let's keep it as select for now, but transitions are preferred.
        inquiryHtml += '<div class="tmgmt-form-group"><label>Status</label><select name="status">';
        for (const [slug, label] of Object.entries(tmgmtData.statuses)) {
            const selected = (meta.status === slug) ? 'selected' : '';
            inquiryHtml += `<option value="${slug}" ${selected}>${label}</option>`;
        }
        inquiryHtml += '</select></div>';

        // 2. Veranstaltungsdaten
        let venueHtml = '';
        venueHtml += createInput('Location / Venue', 'venue_name', meta.venue_name); // Assuming venue_name exists or use street
        venueHtml += createInput('Straße', 'venue_street', meta.venue_street);
        venueHtml += `<div style="display:flex; gap:10px;">
            <div style="flex:1">${createInput('PLZ', 'venue_zip', meta.venue_zip)}</div>
            <div style="flex:2">${createInput('Stadt', 'venue_city', meta.venue_city)}</div>
        </div>`;
        venueHtml += createInput('Land', 'venue_country', meta.venue_country);

        // 3. Kontaktdaten
        let contactHtml = '';
        contactHtml += `<div style="display:flex; gap:10px;">
            <div style="flex:1">${createInput('Vorname', 'contact_firstname', meta.contact_firstname)}</div>
            <div style="flex:1">${createInput('Nachname', 'contact_lastname', meta.contact_lastname)}</div>
        </div>`;
        contactHtml += createInput('Email', 'contact_email', meta.contact_email, 'email');
        contactHtml += createInput('Telefon', 'contact_phone', meta.contact_phone, 'tel');

        // Technik
        contactHtml += '<h4 style="margin: 15px 0 10px; border-bottom: 1px solid #eee; padding-bottom: 5px;">Technik</h4>';
        contactHtml += createInput('Name (Technik)', 'contact_name_tech', meta.contact_name_tech);
        contactHtml += createInput('Email (Technik)', 'contact_email_tech', meta.contact_email_tech, 'email');
        contactHtml += createInput('Telefon (Technik)', 'contact_phone_tech', meta.contact_phone_tech, 'tel');

        // Programm
        contactHtml += '<h4 style="margin: 15px 0 10px; border-bottom: 1px solid #eee; padding-bottom: 5px;">Programm</h4>';
        contactHtml += createInput('Name (Programm)', 'contact_name_program', meta.contact_name_program);
        contactHtml += createInput('Email (Programm)', 'contact_email_program', meta.contact_email_program, 'email');
        contactHtml += createInput('Telefon (Programm)', 'contact_phone_program', meta.contact_phone_program, 'tel');

        // 4. Vertragsdaten
        let contractHtml = '';
        contractHtml += createInput('Gage', 'fee', meta.fee, 'number');
        contractHtml += createInput('Anfrage vom', 'inquiry_date', meta.inquiry_date, 'date');
        
        // --- Right Column Content ---

        // 1. Content / Notes
        let contentHtml = `
            <div class="tmgmt-form-group">
                <label>Notizen / Beschreibung</label>
                <textarea name="content" rows="10" style="width:100%; border:1px solid #dfe1e6; border-radius:4px; padding:8px;">${data.content || ''}</textarea>
            </div>
        `;

        // 2. Map
        let mapHtml = '<div id="tmgmt-map-container"></div>';
        mapHtml += `
            <input type="hidden" name="geo_lat" value="${meta.geo_lat || ''}">
            <input type="hidden" name="geo_lng" value="${meta.geo_lng || ''}">
        `;

        // 3. Logbook
        let logHtml = '<div class="tmgmt-logbook">';
        if (logs.length === 0) {
            logHtml += '<div class="tmgmt-log-entry">Keine Einträge.</div>';
        } else {
            logs.forEach(log => {
                logHtml += `
                    <div class="tmgmt-log-entry">
                        <div class="tmgmt-log-meta">${log.date} - ${log.user}</div>
                        <div>${log.message}</div>
                    </div>
                `;
            });
        }
        logHtml += '</div>';

        // --- Footer Actions ---
        let actionsHtml = '';
        if (actions.length > 0) {
            if (actions.length <= 3) {
                actions.forEach(action => {
                    if (action.target_status) {
                        actionsHtml += `<button class="tmgmt-btn tmgmt-btn-secondary tmgmt-transition-btn" data-target="${action.target_status}">${action.label}</button>`;
                    }
                });
            } else {
                actionsHtml += '<select id="tmgmt-action-select" style="padding: 8px; border-radius: 4px; border: 1px solid #dfe1e6;">';
                actionsHtml += '<option value="">-- Aktion wählen --</option>';
                actions.forEach(action => {
                    if (action.target_status) {
                        actionsHtml += `<option value="${action.target_status}">${action.label}</option>`;
                    }
                });
                actionsHtml += '</select>';
                actionsHtml += '<button class="tmgmt-btn tmgmt-btn-secondary" id="tmgmt-run-action-btn">Ausführen</button>';
            }
        }

        // --- Assemble HTML ---
        const html = `
            <div class="tmgmt-modal-header">
                <div style="display:flex; align-items:center; gap:10px;">
                    <h2>${data.title}</h2>
                    <span id="tmgmt-save-status" style="font-size:0.85em; color:#666; font-weight:normal;"></span>
                </div>
                <span class="tmgmt-close">&times;</span>
            </div>
            <div class="tmgmt-modal-body">
                <div class="tmgmt-col-left">
                    ${createSection('Anfragedaten', inquiryHtml)}
                    ${createSection('Veranstaltungsdaten', venueHtml)}
                    ${createSection('Kontaktdaten', contactHtml, true)}
                    ${createSection('Vertragsdaten', contractHtml, true)}
                </div>
                <div class="tmgmt-col-right">
                    ${createSection('Notizen', contentHtml)}
                    ${createSection('Karte', mapHtml)}
                    ${createSection('Logbuch', logHtml)}
                </div>
            </div>
            <div class="tmgmt-modal-footer">
                <div class="tmgmt-actions-left">
                    ${actionsHtml}
                </div>
                <!-- Auto-Save enabled, no save button needed -->
            </div>
        `;

        modalContent.innerHTML = html;

        // Bind Events
        bindModalEvents();
        
        // Initialize Map
        if (meta.geo_lat && meta.geo_lng) {
            setTimeout(() => {
                initMap(meta.geo_lat, meta.geo_lng);
            }, 100); // Small delay to ensure DOM is ready
        } else {
            document.getElementById('tmgmt-map-container').innerHTML = '<div style="padding:20px; text-align:center; color:#888;">Keine Geodaten vorhanden.</div>';
        }
    }

    function initMap(lat, lng) {
        const mapContainer = document.getElementById('tmgmt-map-container');
        if (!mapContainer) return;

        // Remove existing map if present
        if (currentMap) {
            currentMap.remove();
            currentMap = null;
        }

        currentMap = L.map('tmgmt-map-container').setView([lat, lng], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(currentMap);

        L.marker([lat, lng]).addTo(currentMap);
        
        // Fix map rendering issues in modal
        setTimeout(() => {
            currentMap.invalidateSize();
        }, 200);
    }

    function bindModalEvents() {
        const closeBtn = modalContent.querySelector('.tmgmt-close');
        const statusIndicator = modalContent.querySelector('#tmgmt-save-status');
        
        if (closeBtn) {
            closeBtn.onclick = () => modal.style.display = 'none';
        }

        // Helper: Debounce
        const debounce = (func, wait) => {
            let timeout;
            return function(...args) {
                const context = this;
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(context, args), wait);
            };
        };

        // AutoSave Function
        const savePayload = (payload) => {
            if (!currentEditingId) return;
            
            if (statusIndicator) statusIndicator.textContent = 'Speichere...';
            
            updateEvent(currentEditingId, payload)
                .then(() => {
                    if (statusIndicator) {
                        statusIndicator.textContent = 'Gespeichert';
                        setTimeout(() => { statusIndicator.textContent = ''; }, 2000);
                    }
                    // If status changed, reload board to reflect column change
                    if (payload.status) {
                        loadBoard();
                    }
                })
                .catch(err => {
                    console.error(err);
                    if (statusIndicator) statusIndicator.textContent = 'Fehler!';
                });
        };

        const autoSave = (name, value) => {
            const payload = {};
            payload[name] = value;
            savePayload(payload);
        };

        const debouncedSave = debounce((name, value) => {
            autoSave(name, value);
        }, 1000);

        // Geocoding Logic
        const performGeocode = () => {
            const streetInput = modalContent.querySelector('input[name="venue_street"]');
            const cityInput = modalContent.querySelector('input[name="venue_city"]');
            const zipInput = modalContent.querySelector('input[name="venue_zip"]');
            const countryInput = modalContent.querySelector('input[name="venue_country"]');
            const latInput = modalContent.querySelector('input[name="geo_lat"]');
            const lngInput = modalContent.querySelector('input[name="geo_lng"]');

            if (!cityInput || !cityInput.value.trim()) return;

            const street = streetInput ? streetInput.value : '';
            const city = cityInput.value;
            const zip = zipInput ? zipInput.value : '';
            const country = countryInput ? countryInput.value : '';

            const query = [street, zip, city, country].filter(Boolean).join(', ');
            
            // Optional: Show searching status
            // if (statusIndicator) statusIndicator.textContent = 'Suche Adresse...';

            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    if (data && data.length > 0) {
                        const lat = data[0].lat;
                        const lon = data[0].lon;
                        
                        // Update Hidden Fields
                        if (latInput) latInput.value = lat;
                        if (lngInput) lngInput.value = lon;

                        // Save Coordinates
                        savePayload({
                            geo_lat: lat,
                            geo_lng: lon
                        });

                        // Update Map
                        initMap(lat, lon);
                    }
                })
                .catch(err => {
                    console.error('Geocode Error', err);
                });
        };

        const debouncedGeocode = debounce(performGeocode, 1500);

        // Attach Listeners to Inputs
        const inputs = modalContent.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            if (!input.name) return;

            if (input.tagName === 'SELECT' || input.type === 'date' || input.type === 'time') {
                input.addEventListener('change', () => {
                    autoSave(input.name, input.value);
                });
            } else {
                // Text inputs
                input.addEventListener('input', () => {
                    if (statusIndicator) statusIndicator.textContent = '...';
                    debouncedSave(input.name, input.value);

                    // Trigger Geocode for address fields
                    if (['venue_street', 'venue_zip', 'venue_city', 'venue_country'].includes(input.name)) {
                        debouncedGeocode();
                    }
                });
            }
        });

        // Accordion Toggle
        const sectionTitles = modalContent.querySelectorAll('.tmgmt-section-title');
        sectionTitles.forEach(title => {
            title.onclick = () => {
                const section = title.parentElement;
                section.classList.toggle('collapsed');
                
                // If map section is expanded, invalidate size
                if (!section.classList.contains('collapsed') && section.querySelector('#tmgmt-map-container')) {
                    window.dispatchEvent(new Event('resize'));
                }
            };
        });

        // Transition Buttons
        const transitionBtns = modalContent.querySelectorAll('.tmgmt-transition-btn');
        transitionBtns.forEach(btn => {
            btn.onclick = () => {
                const target = btn.dataset.target;
                const statusSelect = modalContent.querySelector('select[name="status"]');
                if (statusSelect) {
                    statusSelect.value = target;
                    autoSave('status', target);
                }
            };
        });

        // Action Dropdown
        const runActionBtn = modalContent.querySelector('#tmgmt-run-action-btn');
        if (runActionBtn) {
            runActionBtn.onclick = () => {
                const select = modalContent.querySelector('#tmgmt-action-select');
                const target = select.value;
                if (target) {
                    const statusSelect = modalContent.querySelector('select[name="status"]');
                    if (statusSelect) {
                        statusSelect.value = target;
                        autoSave('status', target);
                    }
                }
            };
        }
    }





    function updateEvent(id, data) {
        return fetch(apiUrl + 'events/' + id, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce
            },
            body: JSON.stringify(data)
        })
        .then(res => {
            if (!res.ok) throw new Error('API Error');
            return res.json();
        });
    }
});
