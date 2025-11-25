document.addEventListener('DOMContentLoaded', function() {
    const app = document.getElementById('tmgmt-kanban-app');
    if (!app) return;

    const apiUrl = tmgmtData.apiUrl;
    const nonce = tmgmtData.nonce;
    let boardData = null;
    let currentMap = null;

    // Field Labels Map
    const fieldLabels = {
        'title': 'Titel',
        'date': 'Datum',
        'start_time': 'Startzeit',
        'arrival_time': 'Ankunftszeit',
        'departure_time': 'Abfahrtszeit',
        'arrival_notes': 'Anreise Notizen',
        'venue_name': 'Location / Venue',
        'venue_street': 'Straße (Location)',
        'venue_number': 'Hausnummer (Location)',
        'venue_zip': 'PLZ (Location)',
        'venue_city': 'Stadt (Location)',
        'venue_country': 'Land (Location)',
        'contact_salutation': 'Anrede',
        'contact_firstname': 'Vorname',
        'contact_lastname': 'Nachname',
        'contact_company': 'Firma / Veranstalter',
        'contact_street': 'Straße (Kontakt)',
        'contact_number': 'Hausnummer (Kontakt)',
        'contact_zip': 'PLZ (Kontakt)',
        'contact_city': 'Stadt (Kontakt)',
        'contact_country': 'Land (Kontakt)',
        'contact_email': 'E-Mail',
        'contact_phone': 'Telefon',
        'contact_email_contract': 'E-Mail (Vertrag)',
        'contact_phone_contract': 'Telefon (Vertrag)',
        'contact_name_tech': 'Name (Technik)',
        'contact_email_tech': 'E-Mail (Technik)',
        'contact_phone_tech': 'Telefon (Technik)',
        'contact_name_program': 'Name (Programm)',
        'contact_email_program': 'E-Mail (Programm)',
        'contact_phone_program': 'Telefon (Programm)',
        'fee': 'Gage',
        'deposit': 'Anzahlung',
        'inquiry_date': 'Anfrage vom'
    };

    const checkRequiredFields = (targetStatus, requiredFields) => {
        if (!requiredFields || requiredFields.length === 0) return true;

        const missing = [];
        const fieldMap = tmgmtData.field_map || {};

        requiredFields.forEach(rawField => {
            // Map settings key (e.g. tmgmt_event_date) to API key (e.g. date)
            const field = fieldMap[rawField] || rawField;
            
            const input = modalContent.querySelector(`[name="${field}"]`);
            const val = input ? input.value : '';
            if (!val || val.trim() === '') {
                missing.push(field);
            }
        });

        if (missing.length > 0) {
            showBottomSheet(targetStatus, missing);
            return false;
        }
        return true;
    };

    const showBottomSheet = (targetStatus, missingFields) => {
        const sheet = modalContent.querySelector('#tmgmt-bottom-sheet');
        const container = sheet.querySelector('#tmgmt-sheet-body-missing');
        const closeBtn = sheet.querySelector('.tmgmt-close-sheet');
        
        container.innerHTML = '';
        
        const closeSheet = () => {
            sheet.classList.remove('open');
            setTimeout(() => sheet.style.display = 'none', 300);
        };

        if (closeBtn) {
            closeBtn.onclick = closeSheet;
        }

        missingFields.forEach(field => {
            const label = fieldLabels[field] || field;
            let type = 'text';
            if (field.includes('date')) type = 'date';
            if (field.includes('time')) type = 'time';
            if (field.includes('email')) type = 'email';
            if (field === 'fee') type = 'number';
            
            const div = document.createElement('div');
            div.className = 'tmgmt-form-group';
            div.innerHTML = `
                <label>${label}</label>
                <input type="${type}" name="${field}" class="tmgmt-sheet-input" style="width:100%; padding:8px; border:1px solid #dfe1e6; border-radius:4px;">
            `;
            container.appendChild(div);
        });

        const btn = document.createElement('button');
        btn.className = 'tmgmt-btn tmgmt-btn-primary';
        btn.textContent = 'Speichern & Fortfahren';
        btn.style.width = '100%';
        btn.style.marginTop = '10px';
        btn.onclick = () => {
            const inputs = container.querySelectorAll('input');
            const payload = {};
            let allFilled = true;
            inputs.forEach(inp => {
                if (!inp.value.trim()) allFilled = false;
                payload[inp.name] = inp.value;
            });

            if (!allFilled) {
                alert('Bitte alle Felder ausfüllen.');
                return;
            }

            // Add status to payload to save it in one go
            payload['status'] = targetStatus;

            updateEvent(currentEditingId, payload)
                .then(() => {
                    // Update UI inputs immediately
                    for (const [key, val] of Object.entries(payload)) {
                        const mainInput = modalContent.querySelector(`[name="${key}"]`);
                        if (mainInput) mainInput.value = val;
                    }
                    
                    // Update Status Select UI
                    const statusSelect = modalContent.querySelector('select[name="status"]');
                    if (statusSelect) {
                        statusSelect.value = targetStatus;
                    }
                    
                    // Reload Board & Modal
                    loadBoard();
                    openModal(currentEditingId);
                    
                    closeSheet();
                })
                .catch(err => {
                    alert('Fehler beim Speichern: ' + err.message);
                });
        };
        container.appendChild(btn);

        sheet.style.display = 'flex';
        setTimeout(() => {
            sheet.classList.add('open');
        }, 10);
    };

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
        
        // Dashboard Header
        const dashboardHeader = document.createElement('div');
        dashboardHeader.style.display = 'flex';
        dashboardHeader.style.justifyContent = 'flex-end';
        dashboardHeader.style.marginBottom = '20px';
        
        const createBtn = document.createElement('button');
        createBtn.className = 'tmgmt-btn tmgmt-btn-primary';
        createBtn.textContent = 'Neues Event';
        createBtn.onclick = createNewEvent;
        
        dashboardHeader.appendChild(createBtn);
        app.appendChild(dashboardHeader);

        if (!boardData.columns || boardData.columns.length === 0) {
            const msg = document.createElement('div');
            msg.style.padding = '20px';
            msg.style.textAlign = 'center';
            msg.style.color = '#666';
            msg.textContent = 'Keine Kanban-Spalten gefunden. Bitte im Backend konfigurieren.';
            app.appendChild(msg);
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
            if (col.color) {
                header.style.borderTop = `4px solid ${col.color}`;
                header.style.borderTopLeftRadius = '6px';
                header.style.borderTopRightRadius = '6px';
            }

            // Mobile Accordion Toggle
            header.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    // Close others if we want strict accordion behavior (optional, but cleaner)
                    // document.querySelectorAll('.tmgmt-column.expanded').forEach(c => {
                    //     if (c !== colEl) c.classList.remove('expanded');
                    // });
                    
                    colEl.classList.toggle('expanded');
                }
            });

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

    function createNewEvent() {
        const title = prompt('Titel für das neue Event:', 'Neues Event');
        if (!title) return;

        fetch(apiUrl + 'events', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce
            },
            body: JSON.stringify({ title: title })
        })
        .then(res => {
            if (!res.ok) throw new Error('Fehler beim Erstellen');
            return res.json();
        })
        .then(data => {
            if (data.success && data.id) {
                // Reload board to show new card
                loadBoard();
                // Open modal for the new event
                setTimeout(() => {
                    openModal(data.id);
                }, 500);
            }
        })
        .catch(err => {
            alert('Fehler: ' + err.message);
        });
    }

    function createCard(ev) {
        const card = document.createElement('div');
        card.className = 'tmgmt-card';
        card.draggable = true;
        card.dataset.id = ev.id;

        card.innerHTML = `
            <div class="tmgmt-card-title">${ev.title}</div>
            <div class="tmgmt-card-meta">
                <span>${ev.date || ''}${ev.time ? ' - ' + ev.time : ''}</span>
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
        const createSection = (title, content, isCollapsed = false, style = '', titleStyle = '') => `
            <div class="tmgmt-section ${isCollapsed ? 'collapsed' : ''}" style="${style}">
                <div class="tmgmt-section-title" style="${titleStyle}">${title}</div>
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

        // 2. Veranstaltungsdaten
        let venueHtml = '';
        venueHtml += createInput('Location / Venue', 'venue_name', meta.venue_name); // Assuming venue_name exists or use street
        venueHtml += `<div style="display:flex; gap:10px;">
            <div style="flex:3">${createInput('Straße', 'venue_street', meta.venue_street)}</div>
            <div style="flex:1">${createInput('Nr.', 'venue_number', meta.venue_number)}</div>
        </div>`;
        venueHtml += `<div style="display:flex; gap:10px;">
            <div style="flex:1">${createInput('PLZ', 'venue_zip', meta.venue_zip)}</div>
            <div style="flex:2">${createInput('Stadt', 'venue_city', meta.venue_city)}</div>
        </div>`;
        venueHtml += createInput('Land', 'venue_country', meta.venue_country);

        // Planung
        let planningHtml = '';
        planningHtml += `<div style="display:flex; gap:10px;">
            <div style="flex:1">${createInput('Späteste Anreise', 'arrival_time', meta.arrival_time, 'time')}</div>
            <div style="flex:1">${createInput('Späteste Abreise', 'departure_time', meta.departure_time, 'time')}</div>
        </div>`;
        planningHtml += `<div class="tmgmt-form-group">
            <label>Hinweise Anreise / Bus</label>
            <textarea name="arrival_notes" rows="3" style="width:100%; border:1px solid #dfe1e6; border-radius:4px; padding:8px;">${meta.arrival_notes || ''}</textarea>
        </div>`;

        // 3. Kontaktdaten
        let contactHtml = '';
        contactHtml += `<div style="display:flex; gap:10px;">
            <div style="flex:1">${createInput('Anrede', 'contact_salutation', meta.contact_salutation)}</div>
            <div style="flex:1">${createInput('Vorname', 'contact_firstname', meta.contact_firstname)}</div>
            <div style="flex:1">${createInput('Nachname', 'contact_lastname', meta.contact_lastname)}</div>
        </div>`;
        contactHtml += createInput('Firma / Verein', 'contact_company', meta.contact_company);
        
        contactHtml += `<div style="display:flex; gap:10px;">
            <div style="flex:3">${createInput('Straße', 'contact_street', meta.contact_street)}</div>
            <div style="flex:1">${createInput('Nr.', 'contact_number', meta.contact_number)}</div>
        </div>`;
        contactHtml += `<div style="display:flex; gap:10px;">
            <div style="flex:1">${createInput('PLZ', 'contact_zip', meta.contact_zip)}</div>
            <div style="flex:2">${createInput('Stadt', 'contact_city', meta.contact_city)}</div>
        </div>`;
        contactHtml += createInput('Land', 'contact_country', meta.contact_country);

        contactHtml += createInput('Email (Vertrag)', 'contact_email_contract', meta.contact_email_contract, 'email');
        contactHtml += createInput('Telefon (Vertrag)', 'contact_phone_contract', meta.contact_phone_contract, 'tel');

        // Weitere Ansprechpartner
        let otherContactsHtml = '';
        
        // Technik
        otherContactsHtml += '<h4 style="margin: 15px 0 10px; border-bottom: 1px solid #eee; padding-bottom: 5px;">Technik</h4>';
        otherContactsHtml += createInput('Name (Technik)', 'contact_name_tech', meta.contact_name_tech);
        otherContactsHtml += createInput('Email (Technik)', 'contact_email_tech', meta.contact_email_tech, 'email');
        otherContactsHtml += createInput('Telefon (Technik)', 'contact_phone_tech', meta.contact_phone_tech, 'tel');

        // Programm
        otherContactsHtml += '<h4 style="margin: 15px 0 10px; border-bottom: 1px solid #eee; padding-bottom: 5px;">Programm</h4>';
        otherContactsHtml += createInput('Name (Programm)', 'contact_name_program', meta.contact_name_program);
        otherContactsHtml += createInput('Email (Programm)', 'contact_email_program', meta.contact_email_program, 'email');
        otherContactsHtml += createInput('Telefon (Programm)', 'contact_phone_program', meta.contact_phone_program, 'tel');

        // 4. Vertragsdaten
        let contractHtml = '';
        contractHtml += createInput('Gage', 'fee', meta.fee, 'number');
        contractHtml += createInput('Anzahlung', 'deposit', meta.deposit, 'number');
        contractHtml += createInput('Anfrage vom', 'inquiry_date', meta.inquiry_date, 'date');
        
        // --- Right Column Content ---

        // 0. Status & Actions Box (Standard Section)
        let statusBoxHtml = `
            <div class="tmgmt-form-group">
                <label>Aktueller Status</label>
                <select name="status">
        `;
        for (const [slug, label] of Object.entries(tmgmtData.statuses)) {
            const selected = (meta.status === slug) ? 'selected' : '';
            statusBoxHtml += `<option value="${slug}" ${selected}>${label}</option>`;
        }
        statusBoxHtml += `
                </select>
            </div>
        `;

        // Actions Logic
        if (actions.length > 0) {
            statusBoxHtml += '<div class="tmgmt-actions-container" style="margin-top:15px; padding-top:15px; border-top:1px solid #eee;">';
            statusBoxHtml += '<label style="display:block; margin-bottom:8px; font-weight:500;">Mögliche Aktionen</label>';
            
            if (actions.length <= 3) {
                actions.forEach(action => {
                    const req = action.required_fields ? JSON.stringify(action.required_fields) : '[]';
                    const target = action.target_status || '';
                    statusBoxHtml += `<button class="tmgmt-btn tmgmt-btn-secondary tmgmt-action-btn" 
                        data-id="${action.id}" 
                        data-type="${action.type}" 
                        data-target="${target}" 
                        data-required='${req}' 
                        style="margin-right:5px; margin-bottom:5px;">${action.label}</button>`;
                });
            } else {
                statusBoxHtml += '<div style="display:flex; gap:5px;">';
                statusBoxHtml += '<select id="tmgmt-action-select" style="flex:1; padding: 8px; border-radius: 4px; border: 1px solid #dfe1e6;">';
                statusBoxHtml += '<option value="">-- Aktion wählen --</option>';
                actions.forEach(action => {
                    const req = action.required_fields ? JSON.stringify(action.required_fields) : '[]';
                    const target = action.target_status || '';
                    statusBoxHtml += `<option value="${action.id}" 
                        data-type="${action.type}" 
                        data-target="${target}" 
                        data-required='${req}'>${action.label}</option>`;
                });
                statusBoxHtml += '</select>';
                statusBoxHtml += '<button class="tmgmt-btn tmgmt-btn-secondary" id="tmgmt-run-action-btn">Ausführen</button>';
                statusBoxHtml += '</div>';
            }
            statusBoxHtml += '</div>';
        }


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
                let link = '';
                if (log.communication_id && log.communication_id > 0) {
                    link = ` <a href="#" class="tmgmt-open-comm" data-id="${log.communication_id}" style="font-size:0.9em; color:#0079bf;">(Details)</a>`;
                }
                logHtml += `
                    <div class="tmgmt-log-entry">
                        <div class="tmgmt-log-meta">${log.date} - ${log.user}</div>
                        <div>${log.message}${link}</div>
                    </div>
                `;
            });
        }
        logHtml += '</div>';

        // 3b. Communication
        const comms = data.communication || [];
        let commHtml = '<div class="tmgmt-communication-list">';
        if (comms.length === 0) {
            commHtml += '<div style="padding:10px; color:#888;">Keine Kommunikation vorhanden.</div>';
        } else {
            commHtml += '<table style="width:100%; border-collapse:collapse; font-size:0.9em;">';
            commHtml += '<thead style="background:#f4f5f7; text-align:left;"><tr><th style="padding:8px;">Datum</th><th style="padding:8px;">Typ</th><th style="padding:8px;">Von</th><th style="padding:8px;">An</th><th style="padding:8px;">Inhalt</th></tr></thead>';
            commHtml += '<tbody>';
            comms.forEach(c => {
                const typeLabel = c.type === 'email' ? '📧 E-Mail' : '📝 Notiz';
                const recipient = c.type === 'email' ? c.recipient : 'Intern';
                const subject = c.type === 'email' ? `<strong>${c.subject}</strong><br>` : '';
                const preview = c.content.length > 50 ? c.content.substring(0, 50) + '...' : c.content;
                
                commHtml += `<tr id="comm-row-${c.id}" style="border-bottom:1px solid #eee;">
                    <td style="padding:8px; vertical-align:top;">${c.date}</td>
                    <td style="padding:8px; vertical-align:top;">${typeLabel}</td>
                    <td style="padding:8px; vertical-align:top;">${c.user}</td>
                    <td style="padding:8px; vertical-align:top;">${recipient}</td>
                    <td style="padding:8px; vertical-align:top;">
                        ${subject}
                        <div class="comm-preview">${preview}</div>
                        <a href="#" class="tmgmt-open-comm-drawer" data-id="${c.id}" style="font-size:0.8em; color:#0079bf;">Anzeigen</a>
                    </td>
                </tr>`;
            });
            commHtml += '</tbody></table>';
        }
        commHtml += '</div>';

        // 4. Attachments
        const attachments = data.attachments || [];
        let attachmentsHtml = '<div class="tmgmt-attachments-list" style="margin-bottom:10px;">';
        if (attachments.length === 0) {
            attachmentsHtml += '<div style="color:#888; font-style:italic; font-size:0.9em;">Keine Anhänge</div>';
        } else {
            attachments.forEach(att => {
                const catLabel = att.category ? `<span style="background:#eee; padding:2px 6px; border-radius:4px; font-size:0.8em; margin-right:5px;">${att.category}</span>` : '';
                let deleteBtn = '';
                if (tmgmtData.can_delete_files) {
                    deleteBtn = `<span class="tmgmt-delete-attachment" data-id="${att.id}" style="cursor:pointer; margin-left:auto; font-size: 1.2em;" title="Löschen">🗑️</span>`;
                }
                attachmentsHtml += `
                    <div class="tmgmt-attachment-item" style="display:flex; align-items:center; gap:10px; padding:5px 0; border-bottom:1px solid #eee;">
                        <img src="${att.icon}" style="width:24px; height:24px;">
                        <div style="flex:1;">
                            ${catLabel}
                            <a href="${att.url}" target="_blank" style="text-decoration:none; color:#0079bf;">${att.filename}</a>
                        </div>
                        ${deleteBtn}
                    </div>
                `;
            });
        }
        attachmentsHtml += '</div>';
        attachmentsHtml += `
            <div style="display:flex; gap:5px; align-items:center; flex-wrap:wrap;">
                <select id="tmgmt-upload-category" style="padding:6px; border-radius:4px; border:1px solid #dfe1e6;">
                    <option value="">-- Kategorie --</option>
                    <option value="Vertrag">Vertrag</option>
                    <option value="Rider">Rider</option>
                    <option value="Setlist">Setlist</option>
                    <option value="Rechnung">Rechnung</option>
                    <option value="Sonstiges">Sonstiges</option>
                </select>
                <input type="file" id="tmgmt-file-upload" style="display:none;" multiple>
                <button class="tmgmt-btn tmgmt-btn-secondary" onclick="document.getElementById('tmgmt-file-upload').click()">Datei hochladen</button>
                <button class="tmgmt-btn tmgmt-btn-secondary" id="tmgmt-media-lib-btn">Aus Medienarchiv</button>
            </div>
            <div id="tmgmt-upload-progress" style="margin-top:5px; font-size:0.85em; color:#666;"></div>
        `;

        // --- Assemble HTML ---
        
        // Bottom Tabs Structure (Log & Communication)
        const bottomTabs = `
            <div class="tmgmt-tabs" style="display:flex; gap:5px; margin-bottom:15px; border-bottom:1px solid #ddd; padding-bottom:5px; margin-top: 20px;">
                <button class="tmgmt-tab-btn active" data-tab="log" style="padding:5px 10px; border:none; background:none; cursor:pointer; border-bottom:2px solid transparent;">Logbuch</button>
                <button class="tmgmt-tab-btn" data-tab="communication" style="padding:5px 10px; border:none; background:none; cursor:pointer; border-bottom:2px solid transparent;">Kommunikation</button>
            </div>
            <style>
                .tmgmt-tab-btn.active { border-bottom-color: #0079bf !important; font-weight:bold; color:#0079bf; }
                .tmgmt-tab-content { display: none; }
                .tmgmt-tab-content.active { display: block; }
            </style>
        `;

        // Layout Configuration
        const layout = tmgmtData.layout_settings || {};
        const isMobile = window.innerWidth <= 768;
        
        const getSectionConfig = (key, defaultOrder, defaultCollapsed) => {
            if (layout[key]) {
                const config = isMobile ? (layout[key].mobile || {}) : (layout[key].desktop || {});
                const colors = layout[key].colors || {};
                return {
                    order: config.order || defaultOrder,
                    collapsed: config.collapsed !== undefined ? config.collapsed : defaultCollapsed,
                    bgColor: colors.bg || '',
                    textColor: colors.text || ''
                };
            }
            return { order: defaultOrder, collapsed: defaultCollapsed, bgColor: '', textColor: '' };
        };

        // Define Sections with Keys
        const sections = [
            { key: 'inquiry_details', title: 'Anfragedaten', content: inquiryHtml, defaultOrder: 1, defaultCollapsed: false },
            { key: 'event_details', title: 'Veranstaltungsdaten', content: venueHtml, defaultOrder: 2, defaultCollapsed: false },
            { key: 'planning', title: 'Planung', content: planningHtml, defaultOrder: 3, defaultCollapsed: false },
            { key: 'contact_details', title: 'Kontaktdaten', content: contactHtml, defaultOrder: 4, defaultCollapsed: true },
            { key: 'other_contacts', title: 'Weitere Ansprechpartner', content: otherContactsHtml, defaultOrder: 5, defaultCollapsed: true },
            { key: 'contract_details', title: 'Vertragsdaten', content: contractHtml, defaultOrder: 6, defaultCollapsed: true },
            { key: 'status_box', title: 'Status & Aktionen', content: statusBoxHtml, defaultOrder: 7, defaultCollapsed: false },
            { key: 'notes', title: 'Notizen', content: contentHtml, defaultOrder: 8, defaultCollapsed: false },
            { key: 'files', title: 'Dateien / Anhänge', content: attachmentsHtml, defaultOrder: 9, defaultCollapsed: false },
            { key: 'map', title: 'Karte', content: mapHtml, defaultOrder: 10, defaultCollapsed: false },
            { key: 'logs', title: 'Verlauf', content: bottomTabs + `<div class="tmgmt-tab-content active" id="tab-log">${logHtml}</div><div class="tmgmt-tab-content" id="tab-communication">${commHtml}</div>`, defaultOrder: 11, defaultCollapsed: false }
        ];

        // Generate HTML for sections with order styles
        let sectionsHtml = '';
        sections.forEach(sec => {
            const config = getSectionConfig(sec.key, sec.defaultOrder, sec.defaultCollapsed);
            const style = `order: ${config.order};`;
            let titleStyle = '';
            if (config.bgColor) titleStyle += `background-color: ${config.bgColor};`;
            if (config.textColor) titleStyle += `color: ${config.textColor};`;
            
            sectionsHtml += createSection(sec.title, sec.content, config.collapsed, style, titleStyle);
        });

        const html = `
            <div class="tmgmt-modal-header">
                <div style="display:flex; align-items:center; gap:10px; flex:1;">
                    <input type="text" name="header_title" value="${data.title}" class="tmgmt-header-input" style="font-size: 1.5em; font-weight: bold; border: none; background: transparent; width: 100%; outline: none;">
                    <span id="tmgmt-save-status" style="font-size:0.85em; color:#666; font-weight:normal; white-space: nowrap;"></span>
                </div>
                <span class="tmgmt-close">&times;</span>
            </div>
            <div class="tmgmt-modal-body" style="display:flex; flex-wrap:wrap; gap:20px; align-content:flex-start;">
                ${sectionsHtml}
            </div>
            <div class="tmgmt-modal-footer">
                <div class="tmgmt-actions-left">
                </div>
                <!-- Auto-Save enabled, no save button needed -->
            </div>
            <div id="tmgmt-bottom-sheet" class="tmgmt-bottom-sheet" style="display:none;">
                <div class="tmgmt-sheet-overlay"></div>
                <div class="tmgmt-sheet-content">
                    <div class="tmgmt-sheet-header">
                        <h3 class="tmgmt-bottom-sheet-title">Fehlende Angaben</h3>
                        <span class="tmgmt-close-sheet" style="cursor:pointer; font-size:20px;">&times;</span>
                    </div>
                    <div id="tmgmt-sheet-body-missing" style="padding:20px; overflow-y:auto; flex:1;"></div>
                </div>
            </div>
            
            <div id="tmgmt-side-drawer" class="tmgmt-side-drawer" style="display:none;">
                <div class="tmgmt-drawer-header">
                    <h3 id="tmgmt-drawer-title">Details</h3>
                    <span class="tmgmt-close-drawer" style="cursor:pointer; font-size:20px;">&times;</span>
                </div>
                <div id="tmgmt-drawer-content" style="padding:20px; overflow-y:auto; flex:1;"></div>
            </div>
            <style>
                .tmgmt-side-drawer {
                    position: absolute;
                    top: 0;
                    right: 0;
                    bottom: 0;
                    width: 400px;
                    background: white;
                    box-shadow: -2px 0 10px rgba(0,0,0,0.1);
                    z-index: 1000;
                    display: flex;
                    flex-direction: column;
                    border-left: 1px solid #eee;
                    animation: slideIn 0.3s ease-out;
                }
                @keyframes slideIn {
                    from { transform: translateX(100%); }
                    to { transform: translateX(0); }
                }
                .tmgmt-drawer-header {
                    padding: 15px 20px;
                    border-bottom: 1px solid #eee;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    background: #f9f9f9;
                }
            </style>
        `;

        modalContent.innerHTML = html;

        // Tab Switching Logic
        const tabBtns = modalContent.querySelectorAll('.tmgmt-tab-btn');
        const tabContents = modalContent.querySelectorAll('.tmgmt-tab-content');

        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                // Remove active class from all
                tabBtns.forEach(b => b.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));

                // Add active class to clicked
                btn.classList.add('active');
                const tabId = btn.getAttribute('data-tab');
                const content = modalContent.querySelector(`#tab-${tabId}`);
                if (content) content.classList.add('active');
            });
        });

        // Log Details Click Handler
        const logDetailLinks = modalContent.querySelectorAll('.tmgmt-open-comm');
        logDetailLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const commId = link.getAttribute('data-id');
                if (commId) {
                    // Switch to Communication Tab
                    const commTabBtn = modalContent.querySelector('.tmgmt-tab-btn[data-tab="communication"]');
                    if (commTabBtn) commTabBtn.click();

                    // Highlight the entry
                    setTimeout(() => {
                        const commRow = modalContent.querySelector(`#comm-row-${commId}`);
                        if (commRow) {
                            commRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            commRow.style.backgroundColor = '#fff3cd'; // Highlight color
                            setTimeout(() => {
                                commRow.style.backgroundColor = '';
                            }, 2000);
                            
                            // Open the drawer
                            const drawerBtn = commRow.querySelector('.tmgmt-open-comm-drawer');
                            if (drawerBtn) drawerBtn.click();
                        }
                    }, 100);
                }
            });
        });

        // Side Drawer Logic
        const drawer = modalContent.querySelector('#tmgmt-side-drawer');
        const drawerContent = modalContent.querySelector('#tmgmt-drawer-content');
        const closeDrawerBtn = modalContent.querySelector('.tmgmt-close-drawer');

        if (closeDrawerBtn) {
            closeDrawerBtn.onclick = () => {
                drawer.style.display = 'none';
            };
        }

        const openDrawerBtns = modalContent.querySelectorAll('.tmgmt-open-comm-drawer');
        openDrawerBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const id = btn.getAttribute('data-id');
                const comm = data.communication.find(c => c.id == id);
                
                if (comm) {
                    const typeLabel = comm.type === 'email' ? '📧 E-Mail' : '📝 Notiz';
                    let html = `
                        <div style="margin-bottom:15px;">
                            <div style="font-size:0.85em; color:#666;">Datum</div>
                            <div>${comm.date}</div>
                        </div>
                        <div style="margin-bottom:15px;">
                            <div style="font-size:0.85em; color:#666;">Typ</div>
                            <div>${typeLabel}</div>
                        </div>
                        <div style="margin-bottom:15px;">
                            <div style="font-size:0.85em; color:#666;">Von</div>
                            <div>${comm.user}</div>
                        </div>
                    `;
                    
                    if (comm.type === 'email') {
                        html += `
                            <div style="margin-bottom:15px;">
                                <div style="font-size:0.85em; color:#666;">An</div>
                                <div>${comm.recipient}</div>
                            </div>
                            <div style="margin-bottom:15px;">
                                <div style="font-size:0.85em; color:#666;">Betreff</div>
                                <div><strong>${comm.subject}</strong></div>
                            </div>
                        `;
                    }
                    
                    html += `
                        <div style="margin-top:20px; border-top:1px solid #eee; padding-top:15px;">
                            <div style="font-size:0.85em; color:#666; margin-bottom:5px;">Inhalt</div>
                            <div style="white-space:pre-wrap; font-family:monospace; background:#f9f9f9; padding:10px; border-radius:4px; font-size:0.9em; overflow-x:auto;">${comm.content}</div>
                        </div>
                    `;
                    
                    drawerContent.innerHTML = html;
                    drawer.style.display = 'flex';
                }
            });
        });

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
                    // If status or title changed, reload board
                    if (payload.status || payload.title) {
                        loadBoard();
                    }
                    // If status changed, reload modal to update actions
                    if (payload.status) {
                        openModal(currentEditingId);
                    }
                    // For other fields, we do NOT reload the modal to avoid interrupting typing
                })
                .catch(err => {
                    console.error(err);
                    if (statusIndicator) statusIndicator.textContent = 'Fehler!';
                });
        };

        const autoSave = (name, value, options = {}) => {
            const payload = {};
            payload[name] = value;
            if (options.suppress_log) {
                payload['suppress_log'] = true;
            }
            savePayload(payload);
        };

        const debouncedSave = debounce((name, value, options) => {
            autoSave(name, value, options);
        }, 2000);

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
        const originalValues = {};

        // File Upload Listener
        const fileInput = modalContent.querySelector('#tmgmt-file-upload');
        if (fileInput) {
            fileInput.addEventListener('change', (e) => {
                const files = e.target.files;
                if (!files || files.length === 0) return;

                const categorySelect = modalContent.querySelector('#tmgmt-upload-category');
                const category = categorySelect ? categorySelect.value : '';

                const progressDiv = modalContent.querySelector('#tmgmt-upload-progress');
                progressDiv.textContent = 'Lade hoch...';

                const formData = new FormData();
                for (let i = 0; i < files.length; i++) {
                    formData.append('file_' + i, files[i]);
                }
                if (category) {
                    formData.append('category', category);
                }

                fetch(apiUrl + 'events/' + currentEditingId + '/attachments', {
                    method: 'POST',
                    headers: {
                        'X-WP-Nonce': nonce
                    },
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        progressDiv.textContent = 'Upload erfolgreich!';
                        // Reload modal to show new attachments
                        openModal(currentEditingId);
                    } else {
                        progressDiv.textContent = 'Fehler: ' + (data.message || 'Upload fehlgeschlagen');
                    }
                })
                .catch(err => {
                    progressDiv.textContent = 'Fehler: ' + err.message;
                });
            });
        }

        // Media Library Button
        const mediaBtn = modalContent.querySelector('#tmgmt-media-lib-btn');
        if (mediaBtn) {
            mediaBtn.onclick = (e) => {
                e.preventDefault();
                if (typeof wp !== 'undefined' && wp.media) {
                    const frame = wp.media({
                        title: 'Datei auswählen',
                        multiple: true,
                        button: { text: 'Auswählen' }
                    });

                    frame.on('select', () => {
                        const selection = frame.state().get('selection');
                        const ids = selection.map(attachment => attachment.id);
                        
                        const categorySelect = modalContent.querySelector('#tmgmt-upload-category');
                        const category = categorySelect ? categorySelect.value : '';

                        if (ids.length > 0) {
                            fetch(apiUrl + 'events/' + currentEditingId + '/attachments', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-WP-Nonce': nonce
                                },
                                body: JSON.stringify({ 
                                    media_ids: ids,
                                    category: category
                                })
                            })
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    openModal(currentEditingId);
                                }
                            });
                        }
                    });

                    frame.open();
                } else {
                    alert('Medienbibliothek ist hier nicht verfügbar. Bitte nutzen Sie den Upload.');
                }
            };
        }

        // Delete Attachment Listeners
        const deleteBtns = modalContent.querySelectorAll('.tmgmt-delete-attachment');
        deleteBtns.forEach(btn => {
            btn.onclick = (e) => {
                e.preventDefault();
                const attId = btn.dataset.id;
                if (confirm('Möchten Sie diesen Anhang wirklich entfernen?')) {
                    fetch(apiUrl + 'events/' + currentEditingId + '/attachments/' + attId, {
                        method: 'DELETE',
                        headers: {
                            'X-WP-Nonce': nonce
                        }
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            // Reload modal
                            openModal(currentEditingId);
                        } else {
                            alert('Fehler: ' + (data.message || 'Konnte Anhang nicht löschen'));
                        }
                    })
                    .catch(err => {
                        alert('Fehler: ' + err.message);
                    });
                }
            };
        });

        inputs.forEach(input => {
            if (!input.name) return;
            if (input.type === 'file') return; // Skip file input
            if (input.id === 'tmgmt-upload-category') return; // Skip category select

            // Store original value on focus
            input.addEventListener('focus', () => {
                originalValues[input.name] = input.value;
            });

            if (input.tagName === 'SELECT' || input.type === 'date' || input.type === 'time') {
                input.addEventListener('change', () => {
                    // Special handling for status change
                    if (input.name === 'status') {
                        const targetStatus = input.value;
                        const requirements = tmgmtData.status_requirements || {};
                        const requiredFields = requirements[targetStatus] || [];
                        
                        if (!checkRequiredFields(targetStatus, requiredFields)) {
                            // Revert change if requirements not met
                            input.value = originalValues[input.name];
                            return;
                        }
                    }
                    autoSave(input.name, input.value);
                });
            } else {
                // Text inputs
                input.addEventListener('input', () => {
                    if (statusIndicator) statusIndicator.textContent = '...';
                    
                    let fieldName = input.name;
                    let fieldValue = input.value;

                    // Sync Title Fields
                    if (input.name === 'title') {
                        const headerInput = modalContent.querySelector('input[name="header_title"]');
                        if (headerInput) headerInput.value = input.value;
                    } else if (input.name === 'header_title') {
                        fieldName = 'title';
                        const titleInput = modalContent.querySelector('input[name="title"]');
                        if (titleInput) titleInput.value = input.value;
                    }

                    // Use debounced save with suppress_log
                    debouncedSave(fieldName, fieldValue, { suppress_log: true });

                    // Trigger Geocode for address fields
                    if (['venue_street', 'venue_zip', 'venue_city', 'venue_country'].includes(input.name)) {
                        debouncedGeocode();
                    }
                });

                // On Blur, check if changed from original and log
                input.addEventListener('blur', () => {
                    const oldVal = originalValues[input.name] || '';
                    const newVal = input.value;
                    
                    let fieldName = input.name;
                    if (fieldName === 'header_title') fieldName = 'title';

                    if (oldVal !== newVal) {
                        // Send update with log enabled and old value
                        const payload = {};
                        payload[fieldName] = newVal;
                        payload['suppress_log'] = false;
                        payload['log_old_value'] = oldVal;
                        savePayload(payload);
                        
                        // Update original value
                        originalValues[input.name] = newVal;
                        
                        // Sync original values to prevent double logging
                        if (input.name === 'title') {
                             originalValues['header_title'] = newVal;
                        } else if (input.name === 'header_title') {
                             originalValues['title'] = newVal;
                        }
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

        // Tab Switching Logic
        const tabs = modalContent.querySelectorAll('.tmgmt-tab');
        tabs.forEach(tab => {
            tab.onclick = () => {
                // Remove active class from all tabs
                tabs.forEach(t => {
                    t.classList.remove('active');
                    t.style.borderBottom = 'none';
                    t.style.fontWeight = 'normal';
                });
                
                // Add active class to clicked tab
                tab.classList.add('active');
                tab.style.borderBottom = '2px solid #0079bf';
                tab.style.fontWeight = '600';
                
                // Hide all tab contents
                const contents = modalContent.querySelectorAll('.tmgmt-tab-content');
                contents.forEach(c => c.style.display = 'none');
                
                // Show target content
                const targetId = 'tab-' + tab.dataset.tab;
                const targetContent = modalContent.querySelector('#' + targetId);
                if (targetContent) {
                    targetContent.style.display = 'block';
                    
                    // Resize map if needed
                    if (tab.dataset.tab === 'map') {
                        window.dispatchEvent(new Event('resize'));
                    }
                }
            };
        });

        // Communication Toggle
        const commToggles = modalContent.querySelectorAll('.comm-toggle');
        commToggles.forEach(toggle => {
            toggle.onclick = (e) => {
                e.preventDefault();
                const row = toggle.closest('tr');
                const full = row.querySelector('.comm-full');
                const preview = row.querySelector('.comm-preview');
                
                if (full.style.display === 'none') {
                    full.style.display = 'block';
                    preview.style.display = 'none';
                    toggle.textContent = 'Verbergen';
                } else {
                    full.style.display = 'none';
                    preview.style.display = 'block';
                    toggle.textContent = 'Anzeigen';
                }
            };
        });

        // Open Communication from Log
        const logLinks = modalContent.querySelectorAll('.tmgmt-open-comm');
        logLinks.forEach(link => {
            link.onclick = (e) => {
                e.preventDefault();
                const commId = link.dataset.id;
                
                // Switch to Comm Tab
                const commTab = modalContent.querySelector('.tmgmt-tab[data-tab="comm"]');
                if (commTab) commTab.click();
                
                // Highlight Row
                setTimeout(() => {
                    const row = modalContent.querySelector('#comm-row-' + commId);
                    if (row) {
                        row.style.backgroundColor = '#fff3cd';
                        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        setTimeout(() => row.style.backgroundColor = 'transparent', 2000);
                        
                        // Expand
                        const toggle = row.querySelector('.comm-toggle');
                        if (toggle && toggle.textContent === 'Anzeigen') toggle.click();
                    }
                }, 100);
            };
        });





        // Transition Buttons
        const transitionBtns = modalContent.querySelectorAll('.tmgmt-transition-btn');
        transitionBtns.forEach(btn => {
            btn.onclick = () => {
                const target = btn.dataset.target;
                const required = JSON.parse(btn.dataset.required || '[]');
                
                if (checkRequiredFields(target, required)) {
                    const statusSelect = modalContent.querySelector('select[name="status"]');
                    if (statusSelect) {
                        statusSelect.value = target;
                        autoSave('status', target);
                    }
                }
            };
        });

        // Action Buttons (Direct)
        const actionBtns = modalContent.querySelectorAll('.tmgmt-action-btn');
        actionBtns.forEach(btn => {
            btn.onclick = () => {
                const actionId = btn.dataset.id;
                const type = btn.dataset.type;
                const target = btn.dataset.target;
                const required = JSON.parse(btn.dataset.required || '[]');
                handleAction(actionId, type, target, required);
            };
        });

        // Action Dropdown
        const runActionBtn = modalContent.querySelector('#tmgmt-run-action-btn');
        if (runActionBtn) {
            runActionBtn.onclick = () => {
                const select = modalContent.querySelector('#tmgmt-action-select');
                const actionId = select.value;
                if (actionId) {
                    const option = select.options[select.selectedIndex];
                    const type = option.dataset.type;
                    const target = option.dataset.target;
                    const required = JSON.parse(option.dataset.required || '[]');
                    handleAction(actionId, type, target, required);
                }
            };
        }
    }

    function handleAction(actionId, type, targetStatus, requiredFields) {
        // Check required fields first
        if (targetStatus && !checkRequiredFields(targetStatus, requiredFields)) {
            return;
        }

        if (type === 'email') {
            openActionSheet(actionId);
        } else if (type === 'note') {
            openNoteSheet(actionId);
        } else {
            // Webhook
            if (confirm('Aktion wirklich ausführen?')) {
                executeAction(actionId, {});
            }
        }
    }

    function openNoteSheet(actionId) {
        const sheet = document.getElementById('tmgmt-action-sheet');
        if (!sheet) return;

        const sheetBody = document.getElementById('tmgmt-sheet-body');
        const confirmBtn = document.getElementById('tmgmt-sheet-confirm');
        const cancelBtn = document.getElementById('tmgmt-sheet-cancel');
        const closeBtn = sheet.querySelector('.tmgmt-close-sheet');
        const overlay = sheet.querySelector('.tmgmt-sheet-overlay');

        sheetBody.innerHTML = `
            <div class="tmgmt-form-group">
                <label class="tmgmt-sheet-label">Notiz erfassen</label>
                <textarea id="tmgmt-note-body" class="tmgmt-sheet-textarea" rows="5" placeholder="Hier Notiz eingeben..."></textarea>
            </div>
        `;
        
        sheet.style.display = 'flex';
        setTimeout(() => sheet.classList.add('open'), 10);

        confirmBtn.onclick = () => {
            const note = document.getElementById('tmgmt-note-body').value;
            
            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Speichere...';

            executeAction(actionId, {
                note: note
            }, () => {
                closeSheet();
                confirmBtn.disabled = false;
                confirmBtn.textContent = 'Ausführen';
            });
        };

        const closeSheet = () => {
            sheet.classList.remove('open');
            setTimeout(() => sheet.style.display = 'none', 300);
        };

        if (cancelBtn) cancelBtn.onclick = closeSheet;
        if (closeBtn) closeBtn.onclick = closeSheet;
        if (overlay) overlay.onclick = closeSheet;
    }

    function openActionSheet(actionId) {
        const sheet = document.getElementById('tmgmt-action-sheet');
        if (!sheet) return;

        const sheetBody = document.getElementById('tmgmt-sheet-body');
        const confirmBtn = document.getElementById('tmgmt-sheet-confirm');
        const cancelBtn = document.getElementById('tmgmt-sheet-cancel');
        const closeBtn = sheet.querySelector('.tmgmt-close-sheet');
        const overlay = sheet.querySelector('.tmgmt-sheet-overlay');

        sheetBody.innerHTML = '<div class="tmgmt-loading">Lade Vorschau...</div>';
        sheet.style.display = 'flex';
        setTimeout(() => sheet.classList.add('open'), 10);

        // Fetch Preview
        fetch(apiUrl + `events/${currentEditingId}/actions/${actionId}/preview`, {
            headers: { 'X-WP-Nonce': nonce }
        })
        .then(res => res.json())
        .then(data => {
            if (data.code) throw new Error(data.message); // WP Error

            sheetBody.innerHTML = `
                <div class="tmgmt-form-group">
                    <label class="tmgmt-sheet-label">Empfänger</label>
                    <input type="text" id="tmgmt-email-recipient" class="tmgmt-sheet-input">
                </div>
                <div class="tmgmt-form-group">
                    <label class="tmgmt-sheet-label">Betreff</label>
                    <input type="text" id="tmgmt-email-subject" class="tmgmt-sheet-input">
                </div>
                <div class="tmgmt-form-group">
                    <label class="tmgmt-sheet-label">Nachricht</label>
                    <textarea id="tmgmt-email-body" class="tmgmt-sheet-textarea"></textarea>
                </div>
            `;

            // Set values safely
            document.getElementById('tmgmt-email-recipient').value = data.recipient || '';
            document.getElementById('tmgmt-email-subject').value = data.subject || '';
            document.getElementById('tmgmt-email-body').value = data.body || '';

            confirmBtn.onclick = () => {
                const recipient = document.getElementById('tmgmt-email-recipient').value;
                const subject = document.getElementById('tmgmt-email-subject').value;
                const body = document.getElementById('tmgmt-email-body').value;
                
                confirmBtn.disabled = true;
                confirmBtn.textContent = 'Sende...';

                executeAction(actionId, {
                    email_recipient: recipient,
                    email_subject: subject,
                    email_body: body
                }, () => {
                    closeSheet();
                    confirmBtn.disabled = false;
                    confirmBtn.textContent = 'Ausführen';
                });
            };
        })
        .catch(err => {
            sheetBody.innerHTML = `<div class="error">Fehler: ${err.message}</div>`;
        });

        const closeSheet = () => {
            sheet.classList.remove('open');
            setTimeout(() => sheet.style.display = 'none', 300);
        };

        if (cancelBtn) cancelBtn.onclick = closeSheet;
        if (closeBtn) closeBtn.onclick = closeSheet;
        if (overlay) overlay.onclick = closeSheet;
    }

    function executeAction(actionId, params, callback) {
        fetch(apiUrl + `events/${currentEditingId}/actions/${actionId}/execute`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce
            },
            body: JSON.stringify(params)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                if (data.new_status) {
                    // Update status in UI
                    const statusSelect = document.querySelector('select[name="status"]');
                    if (statusSelect) statusSelect.value = data.new_status;
                    // Reload board
                    loadBoard();
                    // Reload modal to refresh actions
                    openModal(currentEditingId);
                } else {
                    // Just reload modal to refresh logs/comm
                    openModal(currentEditingId);
                }
                if (callback) callback();
            } else {
                alert('Fehler: ' + (data.message || 'Unbekannter Fehler'));
                if (callback) callback(); // Reset button state
            }
        })
        .catch(err => {
            alert('Fehler: ' + err.message);
            if (callback) callback();
        });
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
