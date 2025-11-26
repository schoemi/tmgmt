jQuery(document).ready(function($) {
    const searchInput = $('#tmgmt-title-search');
    const resultsContainer = $('#tmgmt-title-search-results');
    const tbody = $('#tmgmt-setlist-tbody');
    const hiddenInput = $('#tmgmt_setlist_titles');
    const durationInput = $('#tmgmt_setlist_duration');
    const durationDisplay = $('#tmgmt_setlist_duration_display');

    // Sortable
    if (tbody.length) {
        tbody.sortable({
            handle: '.dashicons-menu',
            update: function() {
                updateState();
            }
        });
    }

    // Search
    let searchTimeout;
    searchInput.on('input', function() {
        clearTimeout(searchTimeout);
        const term = $(this).val();
        
        if (term.length < 2) {
            resultsContainer.hide();
            return;
        }

        searchTimeout = setTimeout(function() {
            $.ajax({
                url: tmgmtSetlist.ajaxurl,
                data: {
                    action: 'tmgmt_search_titles',
                    nonce: tmgmtSetlist.nonce,
                    term: term
                },
                success: function(res) {
                    if (res.success && res.data.length > 0) {
                        let html = '';
                        res.data.forEach(item => {
                            html += `<div class="tmgmt-search-result" 
                                data-id="${item.id}" 
                                data-title="${item.title}" 
                                data-artist="${item.artist}" 
                                data-duration="${item.duration}">
                                <strong>${item.title}</strong> - ${item.artist} (${item.duration})
                            </div>`;
                        });
                        resultsContainer.html(html).show();
                    } else {
                        resultsContainer.html('<div style="padding:8px;">Keine Ergebnisse</div>').show();
                    }
                }
            });
        }, 300);
    });

    // Add Title
    resultsContainer.on('click', '.tmgmt-search-result', function() {
        const id = $(this).data('id');
        const title = $(this).data('title');
        const artist = $(this).data('artist');
        const duration = $(this).data('duration');

        const row = `
            <tr class="tmgmt-setlist-row" data-id="${id}" data-duration="${duration}">
                <td><span class="dashicons dashicons-menu" style="color:#ccc;"></span><input type="hidden" name="tmgmt_setlist_titles[]" value="${id}"></td>
                <td>${title}</td>
                <td>${artist}</td>
                <td>${duration}</td>
                <td><a href="#" class="tmgmt-remove-title"><span class="dashicons dashicons-trash"></span></a></td>
            </tr>
        `;
        
        tbody.append(row);
        updateState();
        
        searchInput.val('');
        resultsContainer.hide();
    });

    // Remove Title
    tbody.on('click', '.tmgmt-remove-title', function(e) {
        e.preventDefault();
        $(this).closest('tr').remove();
        updateState();
    });

    // Close search on click outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#tmgmt-title-search, #tmgmt-title-search-results').length) {
            resultsContainer.hide();
        }
    });

    function updateState() {
        let totalSeconds = 0;

        tbody.find('tr').each(function() {
            const durationStr = $(this).data('duration') || '00:00';
            totalSeconds += parseDuration(durationStr);
        });

        const formattedDuration = formatDuration(totalSeconds);
        durationInput.val(formattedDuration);
        durationDisplay.val(formattedDuration);
    }

    function parseDuration(str) {
        if (!str) return 0;
        const parts = str.split(':').map(Number);
        if (parts.length === 2) {
            return parts[0] * 60 + parts[1];
        } else if (parts.length === 3) {
            return parts[0] * 3600 + parts[1] * 60 + parts[2];
        }
        return 0;
    }

    function formatDuration(seconds) {
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = seconds % 60;
        
        const pad = (num) => num.toString().padStart(2, '0');
        
        if (h > 0) {
            return `${pad(h)}:${pad(m)}:${pad(s)}`;
        } else {
            return `${pad(m)}:${pad(s)}`;
        }
    }
    
    // Initial calculation on load (in case PHP didn't do it or to sync)
    // Actually PHP renders the value, but let's ensure it matches the table
    // updateState(); // Optional, might overwrite saved value if logic differs. Better trust saved value or recalc?
    // Let's recalc to be safe and consistent.
    if (tbody.find('tr').length > 0) {
        updateState();
    }
});
