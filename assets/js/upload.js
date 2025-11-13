// Global variables
let pollingInterval = null;
let processingFiles = new Set(); // Track files that are currently being processed

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeUpload();
    loadPcapList();
    
    // Poll for processing status updates
    setInterval(checkProcessingStatus, 2000);
});

// Initialize upload zone
function initializeUpload() {
    const uploadZone = document.getElementById('uploadZone');
    const fileInput = document.getElementById('fileInput');
    const chooseFileBtn = document.getElementById('chooseFileBtn');
    
    // Click to browse - but exclude clicks on the button
    uploadZone.addEventListener('click', (e) => {
        // Don't trigger if clicking on the button or file input wrapper
        if (e.target === chooseFileBtn || e.target.closest('.file-input-wrapper')) {
            return;
        }
        fileInput.click();
    });
    
    // Button click handler
    chooseFileBtn.addEventListener('click', (e) => {
        e.stopPropagation(); // Prevent bubbling to upload zone
        fileInput.click();
    });
    
    // Drag and drop
    uploadZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadZone.classList.add('dragover');
    });
    
    uploadZone.addEventListener('dragleave', () => {
        uploadZone.classList.remove('dragover');
    });
    
    uploadZone.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadZone.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            handleFileSelect(files[0]);
        }
    });
    
    // File input change
    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            handleFileSelect(e.target.files[0]);
        }
    });
}

// Handle file selection
function handleFileSelect(file) {
    // Validate file type
    const ext = file.name.split('.').pop().toLowerCase();
    if (!['pcap', 'pcapng'].includes(ext)) {
        showStatus('Invalid file type. Only .pcap and .pcapng files are allowed.', 'error');
        return;
    }
    
    // Validate file size (100MB max)
    if (file.size > 100 * 1024 * 1024) {
        showStatus('File too large. Maximum size is 100MB.', 'error');
        return;
    }
    
    uploadFile(file);
}

// Upload file with progress tracking
function uploadFile(file) {
    const formData = new FormData();
    formData.append('pcap_file', file);
    
    // Clear file input
    document.getElementById('fileInput').value = '';
    
    // Show upload progress bar
    const progressContainer = document.getElementById('uploadProgressContainer');
    const progressBar = document.getElementById('uploadProgressBar');
    const progressPercent = document.getElementById('uploadProgressPercent');
    const statusEl = document.getElementById('uploadStatus');
    
    progressContainer.style.display = 'block';
    statusEl.style.display = 'none';
    progressBar.style.width = '0%';
    progressPercent.textContent = '0%';
    
    // Use XMLHttpRequest for upload progress tracking
    const xhr = new XMLHttpRequest();
    
    // Track upload progress
    xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable) {
            const percent = Math.round((e.loaded / e.total) * 100);
            progressBar.style.width = percent + '%';
            progressPercent.textContent = percent + '%';
        }
    });
    
    xhr.addEventListener('load', () => {
        progressContainer.style.display = 'none';
        
        if (xhr.status === 200) {
            try {
                const data = JSON.parse(xhr.responseText);
        if (data.success) {
                    showStatus(data.message || 'File uploaded successfully. Click "Process" to start processing.', 'success');
            loadPcapList();
                } else {
                    showStatus('Upload failed: ' + (data.error || 'Unknown error'), 'error');
                }
            } catch (e) {
                showStatus('Upload failed: Invalid response from server', 'error');
            }
        } else {
            showStatus('Upload failed: HTTP ' + xhr.status, 'error');
        }
    });
    
    xhr.addEventListener('error', () => {
        progressContainer.style.display = 'none';
        showStatus('Upload failed: Network error', 'error');
    });
    
    xhr.addEventListener('abort', () => {
        progressContainer.style.display = 'none';
        showStatus('Upload cancelled', 'error');
    });
    
    xhr.open('POST', 'api/upload_pcap.php');
    xhr.send(formData);
}

// Load PCAP file list
function loadPcapList() {
    fetch('api/get_pcap_files.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayPcapList(data.files || []);
            }
        })
        .catch(error => {
            console.error('Error loading file list:', error);
        });
}

// Display PCAP file list
function displayPcapList(files) {
    const container = document.getElementById('fileList');
    
    if (files.length === 0) {
        container.innerHTML = '<p style="color: #aaa; text-align: center; padding: 20px;">No PCAP files uploaded yet.</p>';
        return;
    }
    
    container.innerHTML = files.map(file => {
        // Override status if file is in processingFiles set (optimistic update)
        let actualStatus = file.status;
        if (processingFiles.has(file.filename)) {
            actualStatus = 'processing';
        }
        
        const statusClass = actualStatus === 'processed' ? 'status-processed' : 
                           actualStatus === 'processing' ? 'status-processing' : 
                           actualStatus === 'pending' ? 'status-pending' :
                           'status-error';
        const statusText = actualStatus === 'processed' ? 'READY' : 
                          actualStatus === 'processing' ? 'PROCESSING...' : 
                          actualStatus === 'pending' ? 'PENDING' :
                          'ERROR';
        
        const uploadedDate = new Date(file.uploaded_at * 1000).toLocaleString();
        const fileSize = file.total_bytes ? formatBytes(file.total_bytes) : 'N/A';
        
        let actions = '';
        // Use actualStatus (which includes optimistic processing state) for actions
        if (actualStatus === 'processed') {
            actions = `
                <button class="btn btn-primary" onclick="loadPcapFile('${file.filename}')">▶ Load & Play</button>
            `;
        } else if (actualStatus === 'pending') {
            actions = `
                <button class="btn btn-primary" onclick="processPcapFile('${file.filename}')">▶ Process</button>
            `;
        } else if (actualStatus === 'processing') {
            // Check if popup is open for this file
            const hasPopup = activePopups.has(file.filename) && activePopups.get(file.filename) && !activePopups.get(file.filename).closed;
            if (hasPopup) {
                actions = '<span class="status-badge status-processing">Processing... (Popup open)</span>';
            } else {
                actions = '<span class="status-badge status-processing">Processing... Please wait</span>';
            }
        } else if (actualStatus === 'error') {
            actions = `
                <button class="btn btn-secondary" onclick="processPcapFile('${file.filename}')">🔄 Retry</button>
            `;
        }
        
        return `
            <div class="file-item" data-filename="${escapeHtml(file.filename)}">
                <div class="file-info">
                    <div class="file-name">
                        ${escapeHtml(file.original_filename)}
                        <span class="status-badge ${statusClass}">${statusText}</span>
                    </div>
                    <div class="file-meta">
                        Uploaded: ${uploadedDate} | 
                        Sessions: ${file.total_sessions || 0} | 
                        Packets: ${file.total_packets || 0} | 
                        Size: ${fileSize}
                        ${file.error ? `<br><span style="color: #f44336; font-weight: bold;">⚠️ Error: ${escapeHtml(file.error)}</span>` : ''}
                    </div>
                </div>
                <div class="file-actions">
                    ${actions}
                    <button class="btn btn-danger" onclick="deletePcapFile('${file.filename}', '${escapeHtml(file.original_filename)}')">🗑️ Delete</button>
                </div>
            </div>
        `;
    }).join('');
}

// Load PCAP file into dashboard
function loadPcapFile(filename) {
    fetch('api/set_playback.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ filename: filename })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'index.php';
        } else {
            showAlertModal('Error', 'Error loading file: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlertModal('Error', 'Error loading file. Please try again.');
    });
}

// Delete PCAP file
async function deletePcapFile(filename, originalName) {
    const confirmed = await showConfirmModal(
        'Delete PCAP File',
        `Are you sure you want to delete "${originalName}"? This action cannot be undone.`
    );
    
    if (!confirmed) {
        return;
    }
    
    fetch('api/delete_pcap.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ filename: filename })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showStatus('File deleted successfully', 'success');
            loadPcapList();
        } else {
            showStatus('Error deleting file: ' + (data.error || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showStatus('Error deleting file', 'error');
    });
}

// Store active popup windows for each file
const activePopups = new Map(); // filename -> popup window

// Custom confirm modal
function showConfirmModal(title, message) {
    return new Promise((resolve) => {
        const modal = document.getElementById('confirmModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalMessage = document.getElementById('modalMessage');
        const modalConfirm = document.getElementById('modalConfirm');
        const modalCancel = document.getElementById('modalCancel');
        
        modalTitle.textContent = title;
        modalMessage.textContent = message;
        modal.classList.add('show');
        
        let cleanup = () => {}; // Placeholder
        
        const onConfirm = () => {
            cleanup();
            resolve(true);
        };
        
        const onCancel = () => {
            cleanup();
            resolve(false);
        };
        
        const onOverlayClick = (e) => {
            if (e.target === modal) {
                onCancel();
            }
        };
        
        const onKeyDown = (e) => {
            if (e.key === 'Escape') {
                onCancel();
            } else if (e.key === 'Enter') {
                onConfirm();
            }
        };
        
        cleanup = () => {
            modal.classList.remove('show');
            modalConfirm.removeEventListener('click', onConfirm);
            modalCancel.removeEventListener('click', onCancel);
            modal.removeEventListener('click', onOverlayClick);
            document.removeEventListener('keydown', onKeyDown);
        };
        
        modalConfirm.addEventListener('click', onConfirm);
        modalCancel.addEventListener('click', onCancel);
        modal.addEventListener('click', onOverlayClick);
        document.addEventListener('keydown', onKeyDown);
        
        // Focus on confirm button
        modalConfirm.focus();
    });
}

// Custom alert modal
function showAlertModal(title, message) {
    return new Promise((resolve) => {
        const modal = document.getElementById('alertModal');
        const modalTitle = document.getElementById('alertTitle');
        const modalMessage = document.getElementById('alertMessage');
        const modalOK = document.getElementById('alertOK');
        
        modalTitle.textContent = title;
        modalMessage.textContent = message;
        modal.classList.add('show');
        
        let cleanup = () => {}; // Placeholder
        
        const onOK = () => {
            cleanup();
            resolve();
        };
        
        const onOverlayClick = (e) => {
            if (e.target === modal) {
                onOK();
            }
        };
        
        const onKeyDown = (e) => {
            if (e.key === 'Escape' || e.key === 'Enter') {
                onOK();
            }
        };
        
        cleanup = () => {
            modal.classList.remove('show');
            modalOK.removeEventListener('click', onOK);
            modal.removeEventListener('click', onOverlayClick);
            document.removeEventListener('keydown', onKeyDown);
        };
        
        modalOK.addEventListener('click', onOK);
        modal.addEventListener('click', onOverlayClick);
        document.addEventListener('keydown', onKeyDown);
        
        // Focus on OK button
        modalOK.focus();
    });
}

// Process PCAP file (for pending files) or reprocess (for processed files)
async function processPcapFile(filename) {
    // Check if this file is already being processed
    if (processingFiles.has(filename)) {
        // If popup exists, focus it
        if (activePopups.has(filename)) {
            const existingPopup = activePopups.get(filename);
            if (existingPopup && !existingPopup.closed) {
                existingPopup.focus();
                return;
            } else {
                // Popup was closed, remove from tracking
                activePopups.delete(filename);
                processingFiles.delete(filename);
            }
        }
    }
    
    const confirmed = await showConfirmModal(
        'Process PCAP File',
        'Start processing this PCAP file? This will open a popup window to show progress.'
    );
    
    if (!confirmed) {
        return;
    }
    
    // Add to processingFiles set to track optimistic state
    processingFiles.add(filename);
    
    // Immediately update UI to show processing status (optimistic update)
    updateFileStatusInUI(filename, 'processing');
    
    // Create unique popup window name for each file
    const popupName = 'processPopup_' + filename.replace(/[^a-zA-Z0-9]/g, '_');
    const popup = window.open('', popupName, 'width=600,height=500,resizable=yes,scrollbars=yes');
    
    if (!popup) {
        showAlertModal('Popup Blocked', 'Please allow popups for this site to process files.');
        processingFiles.delete(filename);
        updateFileStatusInUI(filename, 'pending');
        return;
    }
    
    // Store popup reference
    activePopups.set(filename, popup);
    
    // Clean up when popup is closed manually
    const checkClosed = setInterval(() => {
        if (popup.closed) {
            clearInterval(checkClosed);
            activePopups.delete(filename);
            // Don't remove from processingFiles - processing might still be running
        }
    }, 1000);
    
    // Get base URL for API calls
    const baseUrl = window.location.origin + window.location.pathname.replace(/\/[^/]*$/, '');
    
    // Write HTML content to popup
    popup.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Processing PCAP File</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
                    color: #fff;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                    padding: 20px;
                }
                .container {
                    background: #1a1a2e;
                    border: 2px solid #2d2d44;
                    border-radius: 15px;
                    padding: 40px;
                    max-width: 500px;
                    width: 100%;
                    text-align: center;
                }
                .icon { font-size: 64px; margin-bottom: 20px; animation: spin 2s linear infinite; }
                @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
                h1 { color: #4a90e2; margin-bottom: 10px; }
                .filename { color: #aaa; margin-bottom: 30px; word-break: break-all; }
                .progress-text { font-size: 24px; font-weight: bold; color: #4a90e2; margin-bottom: 10px; }
                .progress-bar-container {
                    background: #2d2d44;
                    border-radius: 10px;
                    height: 30px;
                    overflow: hidden;
                    margin-bottom: 15px;
                }
                .progress-bar {
                    background: linear-gradient(90deg, #4a90e2, #5ba0f2);
                    height: 100%;
                    width: 0%;
                    transition: width 0.3s ease;
                }
                .status-text { color: #aaa; font-size: 14px; }
                .success { background: #4caf50; color: #fff; padding: 15px; border-radius: 8px; margin-top: 20px; display: none; }
                .error { background: #f44336; color: #fff; padding: 15px; border-radius: 8px; margin-top: 20px; display: none; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="icon">⚙️</div>
                <h1>Processing PCAP File</h1>
                <div class="filename" id="filename">Loading...</div>
                <div class="progress-text" id="progressPercent">0%</div>
                <div class="progress-bar-container">
                    <div class="progress-bar" id="progressBar"></div>
                </div>
                <div class="status-text" id="statusText">Starting...</div>
                <div class="success" id="successMsg">Processing complete! This window will close automatically.</div>
                <div class="error" id="errorMsg"></div>
            </div>
            <script>
                const filename = '${filename}';
                const baseUrl = '${baseUrl}';
                let progressInterval;
                
                // Get filename
                fetch(baseUrl + '/api/get_pcap_files.php')
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && data.files) {
                            const file = data.files.find(f => f.filename === filename);
                            if (file) {
                                document.getElementById('filename').textContent = file.original_filename;
                            }
                        }
                    });
                
                // Start processing
                fetch(baseUrl + '/api/reprocess_pcap.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ filename: filename })
    })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.error || 'Failed to start processing');
                    }
                    
                    // Poll for progress
                    progressInterval = setInterval(() => {
                        fetch(baseUrl + '/api/get_processing_progress.php?filename=' + encodeURIComponent(filename))
                            .then(r => r.json())
    .then(data => {
        if (data.success) {
                                    const percent = Math.max(0, Math.min(100, Math.round(data.progress || 0)));
                                    document.getElementById('progressBar').style.width = percent + '%';
                                    document.getElementById('progressPercent').textContent = percent + '%';
                                    if (data.statusText) {
                                        document.getElementById('statusText').textContent = data.statusText;
                                    }
                                    
                                    if (data.status === 'processed') {
                                        clearInterval(progressInterval);
                                        document.getElementById('progressBar').style.width = '100%';
                                        document.getElementById('progressPercent').textContent = '100%';
                                        document.getElementById('statusText').textContent = 'Complete!';
                                        document.getElementById('successMsg').style.display = 'block';
                                        
                                        // Notify parent window and close after 2 seconds
                                        if (window.opener) {
                                            window.opener.postMessage({ type: 'processing_complete', filename: filename }, '*');
                                        }
                                        setTimeout(() => window.close(), 2000);
                                    } else if (data.status === 'error') {
                                        clearInterval(progressInterval);
                                        document.getElementById('errorMsg').textContent = data.error || 'Processing failed';
                                        document.getElementById('errorMsg').style.display = 'block';
                                    }
                                }
                            })
                            .catch(err => console.error('Error:', err));
                    }, 500);
                })
                .catch(err => {
                    document.getElementById('errorMsg').textContent = err.message;
                    document.getElementById('errorMsg').style.display = 'block';
                });
            </script>
        </body>
        </html>
    `);
    popup.document.close();
}

// Global message handler for all processing completions
window.addEventListener('message', function(event) {
    if (event.data && event.data.type === 'processing_complete') {
        const filename = event.data.filename;
        processingFiles.delete(filename);
        activePopups.delete(filename);
            loadPcapList();
    }
});

// Update file status in UI immediately (optimistic update)
function updateFileStatusInUI(filename, newStatus) {
    const fileList = document.getElementById('fileList');
    if (!fileList) return;
    
    // Find the file item by data attribute (use querySelectorAll to avoid CSS selector escaping issues)
    const fileItems = fileList.querySelectorAll('.file-item');
    let fileItem = null;
    for (let item of fileItems) {
        if (item.getAttribute('data-filename') === filename) {
            fileItem = item;
            break;
        }
    }
    if (!fileItem) return;
    
    // Update status badge
    const statusBadge = fileItem.querySelector('.status-badge');
    if (statusBadge) {
        if (newStatus === 'processing') {
            statusBadge.className = 'status-badge status-processing';
            statusBadge.textContent = 'PROCESSING...';
        } else if (newStatus === 'pending') {
            statusBadge.className = 'status-badge status-pending';
            statusBadge.textContent = 'PENDING';
        }
    }
    
    // Update actions section
    const actionsDiv = fileItem.querySelector('.file-actions');
    if (actionsDiv) {
        if (newStatus === 'processing') {
            // Replace buttons with processing message, but keep delete button
            const deleteBtn = actionsDiv.querySelector('button[onclick*="deletePcapFile"]');
            const deleteBtnHtml = deleteBtn ? deleteBtn.outerHTML : '';
            actionsDiv.innerHTML = '<span class="status-badge status-processing">Processing... Please wait</span>' + deleteBtnHtml;
        }
    }
}

// Check for processing status updates
function checkProcessingStatus() {
    // Only check if there are files being processed
    if (processingFiles.size === 0) {
        return;
    }
    
    // Check status of all processing files
    fetch('api/get_pcap_files.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.files) {
                let needsRefresh = false;
                const completedFiles = [];
                
                data.files.forEach(file => {
                    if (processingFiles.has(file.filename)) {
                        // If server confirms it's processed or errored, remove from tracking
                        if (file.status === 'processed' || file.status === 'error') {
                            processingFiles.delete(file.filename);
                            activePopups.delete(file.filename);
                            completedFiles.push(file.filename);
                            needsRefresh = true;
                        }
                    }
                });
                
                // Refresh to show actual status if any files were completed
                if (needsRefresh) {
        loadPcapList();
    }
            }
        })
        .catch(error => console.error('Error checking processing status:', error));
}

// Show status message
function showStatus(message, type) {
    const statusEl = document.getElementById('uploadStatus');
    statusEl.textContent = message;
    statusEl.className = `status-message status-${type}`;
    statusEl.style.display = 'block';
    
    if (type === 'success' || type === 'error') {
        setTimeout(() => {
            statusEl.style.display = 'none';
        }, 5000);
    }
}

// Format bytes
function formatBytes(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

// Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

