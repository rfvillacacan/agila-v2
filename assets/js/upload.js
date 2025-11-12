// Global variables
let pollingInterval = null;

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

// Upload file
function uploadFile(file) {
    const formData = new FormData();
    formData.append('pcap_file', file);
    
    // Clear file input
    document.getElementById('fileInput').value = '';
    
    showStatus(`Uploading ${file.name}...`, 'info');
    
    fetch('api/upload_pcap.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        // Try to parse JSON even if response is not ok
        return response.json().then(data => {
            if (!response.ok) {
                throw new Error(data.error || `Upload failed (HTTP ${response.status})`);
            }
            return data;
        }).catch(() => {
            // If JSON parsing fails, throw a generic error
            if (!response.ok) {
                throw new Error(`Upload failed: HTTP ${response.status} ${response.statusText}`);
            }
            throw new Error('Invalid response from server');
        });
    })
    .then(data => {
        if (data.success) {
            showStatus(data.message || 'File uploaded successfully. Click "Process" to start processing.', 'success');
            // Immediately refresh the list
            loadPcapList();
        } else {
            showStatus('Upload failed: ' + (data.error || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        console.error('Upload error:', error);
        const errorMsg = error.message || 'Unknown error occurred. Please check console for details.';
        showStatus('Upload failed: ' + errorMsg, 'error');
    });
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
        const statusClass = file.status === 'processed' ? 'status-processed' : 
                           file.status === 'processing' ? 'status-processing' : 
                           file.status === 'pending' ? 'status-pending' :
                           'status-error';
        const statusText = file.status === 'processed' ? 'READY' : 
                          file.status === 'processing' ? 'PROCESSING...' : 
                          file.status === 'pending' ? 'PENDING' :
                          'ERROR';
        
        const uploadedDate = new Date(file.uploaded_at * 1000).toLocaleString();
        const fileSize = file.total_bytes ? formatBytes(file.total_bytes) : 'N/A';
        
        let actions = '';
        if (file.status === 'processed') {
            actions = `
                <button class="btn btn-primary" onclick="loadPcapFile('${file.filename}')">Load & Play</button>
                <button class="btn btn-secondary" onclick="processPcapFile('${file.filename}')">🔄 Reprocess</button>
            `;
        } else if (file.status === 'pending') {
            actions = `
                <button class="btn btn-primary" onclick="processPcapFile('${file.filename}')">▶ Process</button>
            `;
        } else if (file.status === 'processing') {
            actions = '<span class="status-badge status-processing">Processing... Please wait</span>';
        } else if (file.status === 'error') {
            actions = `
                <button class="btn btn-secondary" onclick="processPcapFile('${file.filename}')">🔄 Retry</button>
            `;
        }
        
        return `
            <div class="file-item">
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
            alert('Error loading file: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error loading file');
    });
}

// Delete PCAP file
function deletePcapFile(filename, originalName) {
    if (!confirm(`Are you sure you want to delete "${originalName}"?`)) {
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

// Process PCAP file (for pending files) or reprocess (for processed files)
function processPcapFile(filename) {
    const isReprocess = confirm('Start processing this PCAP file? This may take a few minutes. Please do not close this page.');
    if (!isReprocess) {
        return;
    }
    
    showStatus('Processing file... This may take a few minutes. Please wait...', 'processing');
    
    fetch('api/reprocess_pcap.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ filename: filename })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showStatus('Processing started. Status will update automatically...', 'processing');
            loadPcapList();
        } else {
            showStatus('Error starting processing: ' + (data.error || 'Unknown error'), 'error');
            loadPcapList();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showStatus('Error starting processing: ' + (error.message || 'Unknown error'), 'error');
        loadPcapList();
    });
}

// Check for processing status updates
function checkProcessingStatus() {
    const fileList = document.getElementById('fileList');
    const hasProcessing = fileList && (fileList.textContent.includes('PROCESSING...') || fileList.textContent.includes('Processing...'));
    
    if (hasProcessing) {
        loadPcapList();
    }
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

