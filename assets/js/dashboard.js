// Global variables
let map;
let hqMarker;
let sessionMarkers = [];
let hqLocation = { lat: 24.7136, lng: 46.6753, name: 'Riyadh, KSA', timezone: 'Asia/Riyadh' };
let playbackInterval = null;
let isPlaying = false;
let autoZoomEnabled = true; // Auto zoom/center enabled by default
let loopEnabled = false; // Loop playback disabled by default
let previousSessionIds = new Set(); // Track previous session IDs to detect new ones
let isFirstUpdate = true; // Track if this is the first update (don't animate initial sessions)
let currentlyAnimatedSessionIds = new Set(); // Track session IDs that are currently animated
let totalSteps = 0; // Store total steps for step jumping
let stepSize = 1.0; // Store step size for step jumping

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeMap();
    loadHQLocation();
    initializePlaybackControls();
    loadPcapFileList();
    // Collapse sessions table by default
    const sessionsPanel = document.getElementById('sessionsPanel');
    if (sessionsPanel) {
        sessionsPanel.classList.add('collapsed');
        const btnCollapse = document.getElementById('btnCollapseSessions');
        if (btnCollapse) {
            btnCollapse.textContent = '+';
            btnCollapse.title = 'Expand';
        }
        const mapContainer = document.getElementById('map');
        if (mapContainer) {
            mapContainer.style.paddingBottom = '50px';
        }
    }
    // Reset to step 1 (time 0) on page load, then load data
    playbackAction('first').then(() => {
        loadPlaybackData();
    });
});

// Initialize Leaflet map
function initializeMap() {
    // Center on Riyadh, KSA by default
    map = L.map('map').setView([24.7136, 46.6753], 5); // Zoom out a bit more
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);
    
    // Add HQ marker
    updateHQMarker();
}

// Load HQ location
function loadHQLocation() {
    return fetch('api/hq_location.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.hq) {
                hqLocation = {
                    lat: parseFloat(data.hq.latitude) || 24.7136,
                    lng: parseFloat(data.hq.longitude) || 46.6753,
                    name: data.hq.name || 'Riyadh, KSA',
                    timezone: data.hq.timezone || 'Asia/Riyadh'
                };
                updateHQMarker();
            }
        })
        .catch(error => {
            console.error('Error loading HQ location:', error);
            // Use defaults if API fails
            hqLocation = { lat: 24.7136, lng: 46.6753, name: 'Riyadh, KSA', timezone: 'Asia/Riyadh' };
        });
}

// Update HQ marker on map
function updateHQMarker() {
    if (hqMarker) {
        map.removeLayer(hqMarker);
    }
    
    hqMarker = L.marker([hqLocation.lat, hqLocation.lng], {
        icon: L.divIcon({
            className: 'hq-marker',
            html: '<div style="background: #ff0000; width: 20px; height: 20px; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 0 10px rgba(255,0,0,0.8);"></div>',
            iconSize: [20, 20],
            iconAnchor: [10, 10]
        })
    }).addTo(map);
    
    hqMarker.bindPopup(`<b>${hqLocation.name}</b><br>HQ Location`);
}

// Initialize playback controls
function initializePlaybackControls() {
    document.getElementById('btnFirst').addEventListener('click', () => playbackAction('first'));
    document.getElementById('btnPrev').addEventListener('click', () => playbackAction('previous'));
    document.getElementById('btnPlayPause').addEventListener('click', togglePlayPause);
    document.getElementById('btnNext').addEventListener('click', () => playbackAction('next'));
    document.getElementById('btnLast').addEventListener('click', () => playbackAction('last'));
    document.getElementById('btnLoop').addEventListener('click', toggleLoop);
    document.getElementById('btnSettings').addEventListener('click', openSettingsModal);
    document.getElementById('btnToggleAutoZoom').addEventListener('click', toggleAutoZoom);
    
    // Step jump functionality
    const stepJumpInput = document.getElementById('stepJumpInput');
    const btnJumpToStep = document.getElementById('btnJumpToStep');
    
    const jumpToStep = () => {
        const stepValue = parseInt(stepJumpInput.value);
        if (totalSteps === 0) {
            alert('No PCAP file loaded. Please upload a PCAP file first.');
            stepJumpInput.value = '';
            return;
        }
        if (stepValue && stepValue >= 1 && stepValue <= totalSteps) {
            // Calculate time from step number (step 1 = second 0, step 2 = second 1, etc.)
            const targetTime = (stepValue - 1) * stepSize; // stepSize is 1.0
            playbackAction('set_time', targetTime);
            stepJumpInput.value = ''; // Clear input after jumping
        } else {
            alert(`Please enter a step number between 1 and ${totalSteps}`);
            stepJumpInput.focus();
        }
    };
    
    btnJumpToStep.addEventListener('click', jumpToStep);
    stepJumpInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            jumpToStep();
        }
    });
    
    // PCAP file selection
    const pcapFileSelect = document.getElementById('pcapFileSelect');
    if (pcapFileSelect) {
        pcapFileSelect.addEventListener('change', function() {
            const selectedFile = this.value;
            if (selectedFile) {
                // Stop any ongoing playback
                if (isPlaying) {
                    playbackAction('pause');
                }
                
                // Load the selected file
                fetch('api/set_playback.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ filename: selectedFile })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Reset to step 1 and reload data
                        playbackAction('first').then(() => {
                            loadPlaybackData();
                        });
                    } else {
                        alert('Error loading PCAP file: ' + (data.error || 'Unknown error'));
                        // Reload file list to reset dropdown
                        loadPcapFileList();
                    }
                })
                .catch(error => {
                    console.error('Error setting playback file:', error);
                    alert('Error loading PCAP file. Please try again.');
                    // Reload file list to reset dropdown
                    loadPcapFileList();
                });
            }
        });
    }
    
    // Initialize sessions panel controls
    document.getElementById('btnCollapseSessions').addEventListener('click', toggleSessionsPanel);
    document.getElementById('btnMaximizeSessions').addEventListener('click', maximizeSessionsPanel);
    document.querySelector('.sessions-panel-header').addEventListener('click', function(e) {
        // Only toggle if clicking on header, not on buttons
        if (e.target === this || e.target.tagName === 'H3') {
            toggleSessionsPanel();
        }
    });
}

// Toggle play/pause
function togglePlayPause() {
    if (isPlaying) {
        playbackAction('pause');
    } else {
        playbackAction('play');
    }
}

// Playback action
function playbackAction(action, time = null) {
    const body = { action: action };
    if (action === 'set_time' && time !== null) {
        body.time = time;
    }
    
    return fetch('api/playback_control.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (action === 'play') {
                isPlaying = true;
                document.getElementById('btnPlayPause').textContent = '⏸';
                startPlayback();
            } else if (action === 'pause') {
                isPlaying = false;
                document.getElementById('btnPlayPause').textContent = '▶';
                stopPlaybackInterval();
            } else if (action === 'stop') {
                isPlaying = false;
                document.getElementById('btnPlayPause').textContent = '▶';
                stopPlaybackInterval();
                loadPlaybackData();
            } else if (action === 'first' || action === 'last') {
                // Update local state to match server state
                isPlaying = data.is_playing || false;
                if (isPlaying) {
                    document.getElementById('btnPlayPause').textContent = '⏸';
                } else {
                    document.getElementById('btnPlayPause').textContent = '▶';
                }
                loadPlaybackData();
            } else if (action === 'set_time') {
                // Update local state to match server state
                isPlaying = data.is_playing || false;
                if (isPlaying) {
                    document.getElementById('btnPlayPause').textContent = '⏸';
                } else {
                    document.getElementById('btnPlayPause').textContent = '▶';
                }
                loadPlaybackData();
            } else {
                loadPlaybackData();
            }
        }
        return data;
    })
    .catch(error => {
        console.error('Error:', error);
        return null;
    });
}

// Start playback interval
function startPlayback() {
    if (playbackInterval) return;
    
    let isLooping = false; // Flag to prevent multiple simultaneous loop operations
    
    playbackInterval = setInterval(() => {
        // Skip if we're currently handling a loop restart
        if (isLooping) return;
        
        fetch('api/playback_control.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'advance_time' })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Check if playback reached the end
                // Check if playback stopped or we're at/past the total duration
                const reachedEnd = !data.is_playing || (data.current_time >= data.total_duration);
                
                if (reachedEnd) {
                    // If loop is enabled, restart from beginning
                    if (loopEnabled) {
                        isLooping = true; // Set flag to prevent interval interference
                        
                        // Reset to beginning and set playing state in sequence
                        fetch('api/playback_control.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'first' })
                        })
                        .then(response => response.json())
                        .then(restartData => {
                            if (restartData.success) {
                                // Immediately start playing again
                                return fetch('api/playback_control.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({ action: 'play' })
                                });
                            }
                            throw new Error('Failed to reset to first');
                        })
                        .then(response => response.json())
                        .then(playData => {
                            if (playData.success && playData.is_playing) {
                                // Verify we're at the beginning (time 0 or very close to it)
                                if (playData.current_time > 0.1) {
                                    console.warn('Warning: After reset, current_time is not 0:', playData.current_time);
                                }
                                isPlaying = true;
                                document.getElementById('btnPlayPause').textContent = '⏸';
                                // Load data to update display
                                return loadPlaybackData().then(() => {
                                    // Small delay to ensure state is fully synchronized before next interval tick
                                    return new Promise(resolve => setTimeout(resolve, 100));
                                });
                            }
                            throw new Error('Failed to restart playback - server state not playing');
                        })
                        .then(() => {
                            // Clear the flag after everything is done and state is synchronized
                            // This allows the next interval tick to proceed normally
                            isLooping = false;
                        })
                        .catch(error => {
                            console.error('Error in loop restart:', error);
                            isLooping = false;
                            // Stop playback on error
                            stopPlaybackInterval();
                            isPlaying = false;
                            document.getElementById('btnPlayPause').textContent = '▶';
                        });
                    } else {
                        // Stop playback if loop is disabled
                        stopPlaybackInterval();
                        isPlaying = false;
                        document.getElementById('btnPlayPause').textContent = '▶';
                        loadPlaybackData();
                    }
                } else {
                    // Normal playback - update display
                    loadPlaybackData();
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            stopPlaybackInterval();
            isLooping = false;
        });
    }, 1000); // Advance every second
}

// Stop playback interval
function stopPlaybackInterval() {
    if (playbackInterval) {
        clearInterval(playbackInterval);
        playbackInterval = null;
    }
}

// Stop playback (no longer used, but kept for compatibility)
function stopPlayback() {
    playbackAction('stop');
}

// Load playback data
function loadPlaybackData() {
    return fetch('api/get_playback_data.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateTimeDisplay(data);
                updatePcapFileDisplay(data);
                updateStats(data);
                // Update map with active sessions only (blips appear/disappear based on active status)
                updateMapWithSessions(data.sessions || []);
                // Update table with only active sessions at current timestamp
                updateSessionsTable(data.sessions || []);
            }
            return data;
        })
        .catch(error => {
            console.error('Error loading playback data:', error);
            return null;
        });
}

// Update time display
function updateTimeDisplay(data) {
    const currentTime = data.current_time || 0;
    const totalTime = data.total_duration || 0;
    
    // Store current timestamp globally for reference
    window.currentTimestamp = currentTime;
    
    // Store actual timestamp for use in table
    const actualTimestamp = data.actual_timestamp || data.capture_start_timestamp || null;
    window.currentActualTimestamp = actualTimestamp;
    
    // Calculate and display actual date/time from packet capture
    if (actualTimestamp) {
        const actualDate = new Date(actualTimestamp * 1000); // Convert Unix timestamp to JavaScript Date
        const dateTimeStr = formatDateTime(actualDate, hqLocation.timezone || 'UTC');
        const dateTimeEl = document.getElementById('actualDateTime');
        if (dateTimeEl) {
            dateTimeEl.textContent = dateTimeStr;
        }
    }
}

// Format date and time with timezone (including milliseconds)
function formatDateTime(date, timezone) {
    if (!date || isNaN(date.getTime())) {
        return '--';
    }
    
    try {
        // Use Intl.DateTimeFormat for timezone support if available
        if (timezone && timezone !== 'UTC') {
            try {
                const formatter = new Intl.DateTimeFormat('en-US', {
                    timeZone: timezone,
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false
                });
                
                const parts = formatter.formatToParts(date);
                const year = parts.find(p => p.type === 'year').value;
                const month = parts.find(p => p.type === 'month').value;
                const day = parts.find(p => p.type === 'day').value;
                const hour = parts.find(p => p.type === 'hour').value;
                const minute = parts.find(p => p.type === 'minute').value;
                const second = parts.find(p => p.type === 'second').value;
                
                // Get milliseconds
                const milliseconds = String(date.getMilliseconds()).padStart(3, '0');
                
                return `${year}-${month}-${day} ${hour}:${minute}:${second}.${milliseconds}`;
            } catch (e) {
                // Fallback to UTC if timezone conversion fails
            }
        }
        
        // Format: YYYY-MM-DD HH:MM:SS.mmm (UTC)
        const year = date.getUTCFullYear();
        const month = String(date.getUTCMonth() + 1).padStart(2, '0');
        const day = String(date.getUTCDate()).padStart(2, '0');
        const hours = String(date.getUTCHours()).padStart(2, '0');
        const minutes = String(date.getUTCMinutes()).padStart(2, '0');
        const seconds = String(date.getUTCSeconds()).padStart(2, '0');
        const milliseconds = String(date.getUTCMilliseconds()).padStart(3, '0');
        
        return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}.${milliseconds} UTC`;
    } catch (e) {
        return date.toLocaleString();
    }
}

// Format time (seconds to HH:MM:SS)
function formatTime(seconds) {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = Math.floor(seconds % 60);
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

// Load PCAP file list and populate dropdown
function loadPcapFileList() {
    fetch('api/get_pcap_files.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.files) {
                const select = document.getElementById('pcapFileSelect');
                if (!select) return;
                
                // Clear existing options
                select.innerHTML = '';
                
                // Filter only processed files
                const processedFiles = data.files.filter(file => file.status === 'processed');
                
                if (processedFiles.length === 0) {
                    select.innerHTML = '<option value="">No processed files available</option>';
                    return;
                }
                
                // Add options
                processedFiles.forEach(file => {
                    const option = document.createElement('option');
                    option.value = file.filename;
                    option.textContent = file.original_filename || file.filename;
                    option.title = `${file.original_filename || file.filename} - ${file.total_sessions || 0} sessions, ${file.total_packets || 0} packets`;
                    select.appendChild(option);
                });
                
                // Set current file if available
                loadPlaybackData().then(playbackData => {
                    if (playbackData && playbackData.pcap_file) {
                        select.value = playbackData.pcap_file;
                    }
                });
            }
        })
        .catch(error => {
            console.error('Error loading PCAP file list:', error);
            const select = document.getElementById('pcapFileSelect');
            if (select) {
                select.innerHTML = '<option value="">Error loading files</option>';
            }
        });
}

// Update PCAP file display
function updatePcapFileDisplay(data) {
    const filename = data.pcap_info?.original_filename || 'None';
    const select = document.getElementById('pcapFileSelect');
    if (select && data.pcap_file) {
        // Update the select to show current file
        select.value = data.pcap_file;
    }
    
    // Calculate and display step information (adaptive step size)
    const currentTime = data.current_time || 0;
    const totalDuration = data.total_duration || 0;
    actualDuration = data.actual_duration || totalDuration; // Actual capture duration (store globally)
    
    // Use adaptive step size from server
    stepSize = data.step_size || 1.0;
    totalSteps = data.total_steps !== undefined ? data.total_steps : (totalDuration > 0 && stepSize > 0 ? Math.max(1, Math.ceil(totalDuration / stepSize)) : 1);
    // Calculate current step from current time if not provided
    const currentStep = data.current_step !== undefined ? data.current_step : (totalDuration > 0 && stepSize > 0 ? Math.floor(currentTime / stepSize) + 1 : 1);
    
    // Display step information
    const stepDisplay = document.getElementById('stepDisplay');
    if (stepDisplay) {
        if (totalSteps > 0) {
            stepDisplay.textContent = `${currentStep} of ${totalSteps}`;
        } else {
            stepDisplay.textContent = '--';
        }
    }
    
    // Update step jump input
    const stepJumpInput = document.getElementById('stepJumpInput');
    if (stepJumpInput) {
        stepJumpInput.max = totalSteps;
        stepJumpInput.placeholder = totalSteps > 0 ? `1-${totalSteps}` : 'Step #';
    }
}

// Update stats
function updateStats(data) {
    document.getElementById('activeSessions').textContent = data.active_sessions || 0;
    document.getElementById('totalSessions').textContent = data.total_sessions || 0;
}

// Fit map to show all markers (blips) - AUTO FIT ONLY, NO HQ CENTERING
function fitMapToAllMarkers() {
    // Always ensure HQ marker exists (but don't center on it)
    if (!hqMarker && hqLocation) {
        updateHQMarker();
    }
    
    // Create array of all markers (sessions + HQ) - include HQ in bounds but don't prioritize it
    const allMarkers = [...sessionMarkers];
    if (hqMarker) {
        allMarkers.push(hqMarker);
    }
    
    if (allMarkers.length === 0) {
        // No markers, just return (don't center on anything)
        return;
    }
    
    try {
        // Get bounds of all markers
        const group = new L.featureGroup(allMarkers);
        const bounds = group.getBounds();
        
        if (bounds.isValid()) {
            // Fit bounds with minimal padding - just enough pixels to ensure blips are visible
            // Leaflet's padding is in pixels, so we use small values
            map.fitBounds(bounds, {
                maxZoom: 18, // Allow closer zoom
                padding: [50, 25, 25, 25] // top, right, bottom, left - minimal padding in pixels
            });
        } else {
            // Fallback: if bounds invalid, just show first marker
            if (sessionMarkers.length > 0) {
                map.setView(sessionMarkers[0].getLatLng(), 5);
            } else if (hqMarker) {
                map.setView([hqLocation.lat, hqLocation.lng], 5);
            }
        }
    } catch (e) {
        console.error('Error fitting map bounds:', e);
    }
}

// Update map with sessions - only active sessions get blips
// Blips are removed when sessions become inactive
function updateMapWithSessions(sessions) {
    // Create a unique ID for each session (using IP + port combination)
    const getSessionId = (session) => {
        return `${session.external_ip || session.source_ip || 'unknown'}_${session.destination_port || session.source_port || '0'}`;
    };
    
    // Get current session IDs
    const currentSessionIds = new Set();
    sessions.forEach(session => {
        currentSessionIds.add(getSessionId(session));
    });
    
    // For 1-second stepping: ALL active sessions at current second should be green
    // Clear previous animated sessions and add all current active sessions
    currentlyAnimatedSessionIds.clear();
    currentSessionIds.forEach(id => currentlyAnimatedSessionIds.add(id));
    
    // Update previous session IDs for next comparison
    previousSessionIds = new Set(currentSessionIds);
    isFirstUpdate = false; // Mark that we've done the first update
    
    // Remove ALL existing session markers first
    sessionMarkers.forEach(marker => map.removeLayer(marker));
    sessionMarkers = [];
    
    console.log(`Timestamp: ${formatTime(window.currentTimestamp || 0)} - Active sessions: ${sessions.length}, All animated (green): ${currentlyAnimatedSessionIds.size}`);
    
    // Add markers (blips) only for active sessions with geolocation
    sessions.forEach((session, index) => {
        const geo = session.geolocation;
        if (geo && geo.latitude && geo.longitude) {
            const sessionId = getSessionId(session);
            
            // Determine if this session should be animated (green):
            // - If it's in the currently animated set, it should be green and pulsing
            const shouldAnimate = currentlyAnimatedSessionIds.has(sessionId);
            
            // Create marker with animation class if it should be animated
            const markerClass = shouldAnimate ? 'session-marker session-marker-new' : 'session-marker';
            const marker = L.marker([geo.latitude, geo.longitude], {
                icon: L.divIcon({
                    className: markerClass,
                    html: '<div class="marker-dot"></div>',
                    iconSize: [16, 16],
                    iconAnchor: [8, 8]
                })
            }).addTo(map);
            
            // Store session ID on marker for reference
            marker._sessionId = sessionId;
            
            const popup = `
                <b>${session.external_ip || session.source_ip || 'Unknown IP'}</b><br>
                ${geo.city || ''}${geo.city && geo.country ? ', ' : ''}${geo.country || ''}<br>
                Protocol: ${session.protocol || 'UNKNOWN'}<br>
                Port: ${session.destination_port || session.source_port || 'N/A'}<br>
                Packets: ${session.packet_count || 0}<br>
                Bytes: ${(session.total_bytes || 0).toLocaleString()}
            `;
            marker.bindPopup(popup);
            
            sessionMarkers.push(marker);
        }
    });
    
    // Clean up animated session IDs that are no longer active (sessions that disappeared)
    currentlyAnimatedSessionIds.forEach(sessionId => {
        if (!currentSessionIds.has(sessionId)) {
            currentlyAnimatedSessionIds.delete(sessionId);
        }
    });
    
    // Always ensure HQ marker exists
    if (!hqMarker && hqLocation) {
        updateHQMarker();
    }
    
    // Auto-fit map to show all markers including HQ (only if auto-zoom is enabled)
    if (autoZoomEnabled) {
        // Use setTimeout to ensure map has updated after marker changes
        setTimeout(() => {
            fitMapToAllMarkers();
        }, 100);
    }
}

// Update sessions table - show only active sessions at current timestamp
function updateSessionsTable(sessions) {
    const tbody = document.getElementById('sessionsTableBody');
    
    if (!sessions || sessions.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="no-sessions">No active sessions at this timestamp</td></tr>';
        return;
    }
    
    // Get current actual timestamp for display
    const currentActualTimestamp = window.currentActualTimestamp || null;
    
    // Sort sessions by start time
    const sortedSessions = [...sessions].sort((a, b) => {
        const startA = parseFloat(a.relative_start || 0);
        const startB = parseFloat(b.relative_start || 0);
        return startA - startB;
    });
    
    tbody.innerHTML = sortedSessions.map((session, index) => {
        const geo = session.geolocation || {};
        const location = geo.city && geo.country 
            ? `${geo.city}, ${geo.country}`
            : geo.country || geo.city || 'Unknown';
        
        const protocol = (session.protocol || 'UNKNOWN').toLowerCase();
        const duration = (session.relative_end || 0) - (session.relative_start || 0);
        
        // Format date/time for this session at current playback time
        // Include sub-second precision from the actual timestamp
        let dateTimeDisplay = '--';
        if (currentActualTimestamp) {
            // Convert timestamp (seconds) to milliseconds for Date object
            const timestampMs = currentActualTimestamp * 1000;
            const sessionDate = new Date(timestampMs);
            dateTimeDisplay = formatDateTime(sessionDate, hqLocation.timezone || 'UTC');
        }
        
        return `
            <tr class="session-active" data-session-index="${index}">
                <td class="ip-address">${escapeHtml(session.external_ip || session.source_ip || 'N/A')}</td>
                <td class="location">${escapeHtml(location)}</td>
                <td><span class="protocol protocol-${protocol}">${escapeHtml(session.protocol || 'UNKNOWN')}</span></td>
                <td class="port">${session.destination_port || session.source_port || 'N/A'}</td>
                <td class="datetime">${dateTimeDisplay}</td>
                <td class="packets">${(session.packet_count || 0).toLocaleString()}</td>
                <td class="bytes">${(session.total_bytes || 0).toLocaleString()}</td>
                <td class="duration">${formatDuration(duration)}</td>
            </tr>
        `;
    }).join('');
}

// Format duration in seconds
function formatDuration(seconds) {
    if (seconds < 1) {
        return '< 1s';
    }
    if (seconds < 60) {
        return seconds.toFixed(1) + 's';
    }
    const m = Math.floor(seconds / 60);
    const s = Math.floor(seconds % 60);
    return `${m}m ${s}s`;
}

// Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Toggle loop playback
function toggleLoop() {
    loopEnabled = !loopEnabled;
    const btn = document.getElementById('btnLoop');
    
    if (loopEnabled) {
        btn.classList.add('active');
        btn.title = 'Loop Enabled - Click to Disable';
    } else {
        btn.classList.remove('active');
        btn.title = 'Loop Disabled - Click to Enable';
    }
}

// Toggle auto zoom/center
function toggleAutoZoom() {
    autoZoomEnabled = !autoZoomEnabled;
    const btn = document.getElementById('btnToggleAutoZoom');
    const icon = document.getElementById('autoZoomIcon');
    const label = document.getElementById('autoZoomLabel');
    
    if (autoZoomEnabled) {
        btn.classList.remove('disabled');
        icon.textContent = '📍';
        label.textContent = 'Auto';
        btn.title = 'Disable Auto Zoom/Center';
        // Immediately fit map when enabling auto-zoom
        fitMapToAllMarkers();
    } else {
        btn.classList.add('disabled');
        icon.textContent = '🔒';
        label.textContent = 'Manual';
        btn.title = 'Enable Auto Zoom/Center';
    }
}

// Toggle sessions panel collapse
function toggleSessionsPanel() {
    const panel = document.getElementById('sessionsPanel');
    const btnCollapse = document.getElementById('btnCollapseSessions');
    const mapContainer = document.getElementById('map');
    
    if (panel.classList.contains('collapsed')) {
        panel.classList.remove('collapsed');
        btnCollapse.textContent = '−';
        btnCollapse.title = 'Collapse';
        mapContainer.style.paddingBottom = '50vh';
    } else {
        panel.classList.add('collapsed');
        btnCollapse.textContent = '+';
        btnCollapse.title = 'Expand';
        mapContainer.style.paddingBottom = '50px';
    }
}

// Maximize/restore sessions panel
function maximizeSessionsPanel() {
    const panel = document.getElementById('sessionsPanel');
    const btnMaximize = document.getElementById('btnMaximizeSessions');
    const mapContainer = document.getElementById('map');
    
    if (panel.classList.contains('maximized')) {
        panel.classList.remove('maximized');
        btnMaximize.textContent = '⛶';
        btnMaximize.title = 'Maximize';
        const isCollapsed = panel.classList.contains('collapsed');
        mapContainer.style.paddingBottom = isCollapsed ? '50px' : '50vh';
    } else {
        panel.classList.add('maximized');
        panel.classList.remove('collapsed');
        btnMaximize.textContent = '⛶';
        btnMaximize.title = 'Restore';
        mapContainer.style.paddingBottom = '0';
        document.getElementById('btnCollapseSessions').textContent = '−';
    }
}

// Settings Modal
function openSettingsModal() {
    // Ensure HQ location is loaded before opening modal
    if (!hqLocation.lat || !hqLocation.lng) {
        loadHQLocation().then(() => {
            populateSettingsForm();
            document.getElementById('settingsModal').classList.add('active');
        });
    } else {
        populateSettingsForm();
        document.getElementById('settingsModal').classList.add('active');
    }
}

function populateSettingsForm() {
    document.getElementById('hqLatitude').value = hqLocation.lat || 24.7136;
    document.getElementById('hqLongitude').value = hqLocation.lng || 46.6753;
    document.getElementById('hqName').value = hqLocation.name || 'Riyadh, KSA';
    document.getElementById('timezone').value = hqLocation.timezone || 'Asia/Riyadh';
}

function closeSettingsModal() {
    document.getElementById('settingsModal').classList.remove('active');
}

function saveHQLocation() {
    const lat = parseFloat(document.getElementById('hqLatitude').value);
    const lng = parseFloat(document.getElementById('hqLongitude').value);
    const name = document.getElementById('hqName').value.trim();
    const timezone = document.getElementById('timezone').value;
    
    // Validate coordinates
    if (isNaN(lat) || lat < -90 || lat > 90) {
        alert('Invalid latitude. Must be between -90 and 90.');
        return;
    }
    if (isNaN(lng) || lng < -180 || lng > 180) {
        alert('Invalid longitude. Must be between -180 and 180.');
        return;
    }
    
    if (!name) {
        alert('HQ Name cannot be empty.');
        return;
    }
    
    fetch('api/hq_location.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ latitude: lat, longitude: lng, name: name, timezone: timezone })
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(data => {
                throw new Error(data.error || 'HTTP error: ' + response.status);
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            hqLocation = {
                lat: parseFloat(data.hq.latitude) || lat,
                lng: parseFloat(data.hq.longitude) || lng,
                name: data.hq.name || name,
                timezone: data.hq.timezone || timezone
            };
            updateHQMarker();
            closeSettingsModal();
            // Show success message
            alert('Settings saved successfully!');
        } else {
            alert('Error saving settings: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error saving settings: ' + (error.message || 'Unknown error'));
    });
}

// Close modal on outside click
window.onclick = function(event) {
    const modal = document.getElementById('settingsModal');
    if (event.target === modal) {
        closeSettingsModal();
    }
}

