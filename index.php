<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PCAP Network Analyzer - Dashboard</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="header-left">
                <h1>🌐 PCAP Network Analyzer</h1>
                <span class="author-credit">by Rolly Falco Villacacan</span>
            </div>
            <div class="header-stats">
                <div class="stat-item stat-item-pcap">
                    <span class="stat-label">Current PCAP:</span>
                    <select id="pcapFileSelect" class="pcap-file-select" title="Select a PCAP file to load">
                        <option value="">Loading...</option>
                    </select>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Active Sessions:</span>
                    <span id="activeSessions" class="stat-value">0</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Total Sessions:</span>
                    <span id="totalSessions" class="stat-value">0</span>
                </div>
                <button id="btnSettings" class="btn btn-secondary">⚙️ Settings</button>
                <a href="upload.php" class="btn btn-primary">📤 Upload PCAP</a>
            </div>
        </header>

        <!-- Playback Controls -->
        <div class="playback-controls">
            <div class="playback-buttons">
                <button id="btnFirst" class="btn-playback" title="First (Skip to start)">⏮</button>
                <button id="btnPrev" class="btn-playback" title="Previous (1s)">◀</button>
                <button id="btnPlayPause" class="btn-playback" title="Play/Pause">▶</button>
                <button id="btnNext" class="btn-playback" title="Next (1s)">▶</button>
                <button id="btnLast" class="btn-playback" title="Last (Skip to end)">⏭</button>
                <button id="btnLoop" class="btn-playback btn-loop" title="Toggle Loop">🔁</button>
            </div>
            <div class="playback-info">
                <div class="datetime-display-main">
                    <span class="datetime-label">Time:</span>
                    <span id="actualDateTime" class="datetime-value">--</span>
                </div>
                <div class="pcap-display">
                    <span class="step-label">Step:</span>
                    <span id="stepDisplay" class="step-display"></span>
                </div>
                <div class="step-jump-control">
                    <label for="stepJumpInput" class="step-jump-label">Jump to Step:</label>
                    <input type="number" id="stepJumpInput" class="step-jump-input" min="1" placeholder="Step #" title="Enter step number and press Enter">
                    <button id="btnJumpToStep" class="btn-step-jump" title="Jump to step">⤴</button>
                </div>
                <button id="btnToggleAutoZoom" class="btn-auto-zoom" title="Toggle Auto Zoom/Center">
                    <span id="autoZoomIcon">📍</span>
                    <span id="autoZoomLabel">Auto</span>
                </button>
            </div>
        </div>

        <!-- Map Container -->
        <div id="map" class="map-container">
            <!-- Watermark -->
            <div class="watermark">
                <span class="watermark-text">Conceptualized & Developed by <strong>Rolly Falco Villacacan</strong></span>
            </div>
        </div>

        <!-- Active Sessions Table Panel -->
        <div id="sessionsPanel" class="sessions-panel">
            <div class="sessions-panel-header">
                <h3>📊 Active Sessions</h3>
                <div class="sessions-panel-controls">
                    <button id="btnCollapseSessions" class="btn-icon" title="Collapse">−</button>
                    <button id="btnMaximizeSessions" class="btn-icon" title="Maximize">⛶</button>
                </div>
            </div>
            <div class="sessions-panel-body">
                <div class="sessions-table-wrapper">
                    <table id="sessionsTable" class="sessions-table">
                        <thead>
                            <tr>
                                <th>IP Address</th>
                                <th>Location</th>
                                <th>Protocol</th>
                                <th>Port</th>
                                <th>Date/Time</th>
                                <th>Packets</th>
                                <th>Bytes</th>
                                <th>Duration</th>
                            </tr>
                        </thead>
                        <tbody id="sessionsTableBody">
                            <tr>
                                <td colspan="8" class="no-sessions">No active sessions</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <footer class="footer">
            <div class="footer-content">
                <span class="footer-text">Conceptualized & Developed by <strong>Rolly Falco Villacacan</strong></span>
            </div>
        </footer>
    </div>

    <!-- Settings Modal -->
    <div id="settingsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>⚙️ Settings</h2>
                <button class="modal-close" onclick="closeSettingsModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="hqLatitude">HQ Latitude:</label>
                    <input type="number" id="hqLatitude" step="0.0001" min="-90" max="90" placeholder="24.7136">
                </div>
                <div class="form-group">
                    <label for="hqLongitude">HQ Longitude:</label>
                    <input type="number" id="hqLongitude" step="0.0001" min="-180" max="180" placeholder="46.6753">
                </div>
                <div class="form-group">
                    <label for="hqName">HQ Name:</label>
                    <input type="text" id="hqName" placeholder="Riyadh, KSA">
                </div>
                <div class="form-group">
                    <label for="timezone">Timezone:</label>
                    <select id="timezone" class="form-select">
                        <option value="UTC">UTC (Coordinated Universal Time)</option>
                        <option value="Asia/Riyadh" selected>Asia/Riyadh (Saudi Arabia)</option>
                        <option value="America/New_York">America/New_York (Eastern Time)</option>
                        <option value="America/Chicago">America/Chicago (Central Time)</option>
                        <option value="America/Denver">America/Denver (Mountain Time)</option>
                        <option value="America/Los_Angeles">America/Los_Angeles (Pacific Time)</option>
                        <option value="Europe/London">Europe/London (GMT)</option>
                        <option value="Europe/Paris">Europe/Paris (CET)</option>
                        <option value="Europe/Berlin">Europe/Berlin (CET)</option>
                        <option value="Asia/Dubai">Asia/Dubai (UAE)</option>
                        <option value="Asia/Kuwait">Asia/Kuwait</option>
                        <option value="Asia/Qatar">Asia/Qatar</option>
                        <option value="Asia/Bahrain">Asia/Bahrain</option>
                        <option value="Asia/Muscat">Asia/Muscat (Oman)</option>
                        <option value="Asia/Karachi">Asia/Karachi (Pakistan)</option>
                        <option value="Asia/Kolkata">Asia/Kolkata (India)</option>
                        <option value="Asia/Shanghai">Asia/Shanghai (China)</option>
                        <option value="Asia/Tokyo">Asia/Tokyo (Japan)</option>
                        <option value="Australia/Sydney">Australia/Sydney</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button onclick="closeSettingsModal()" class="btn btn-secondary">Cancel</button>
                <button onclick="saveHQLocation()" class="btn btn-primary">Save</button>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="assets/js/dashboard.js"></script>
</body>
</html>

