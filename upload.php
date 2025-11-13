<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload PCAP File</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        .upload-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .upload-zone {
            border: 3px dashed #4a90e2;
            border-radius: 10px;
            padding: 60px 20px;
            text-align: center;
            background: #1a1a2e;
            margin-bottom: 30px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .upload-zone:hover {
            border-color: #5ba0f2;
            background: #1f1f3e;
        }
        .upload-zone.dragover {
            border-color: #5ba0f2;
            background: #252545;
        }
        .upload-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        .file-input-wrapper {
            margin-top: 20px;
        }
        .file-list {
            margin-top: 30px;
        }
        .file-item {
            background: #1a1a2e;
            border: 1px solid #2d2d44;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .file-info {
            flex: 1;
        }
        .file-name {
            font-weight: bold;
            color: #fff;
            margin-bottom: 5px;
        }
        .file-meta {
            font-size: 0.9em;
            color: #aaa;
        }
        .file-actions {
            display: flex;
            gap: 10px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: bold;
            margin-left: 10px;
        }
        .status-pending {
            background: #ffa500;
            color: #000;
        }
        .status-processing {
            background: #2196f3;
            color: #fff;
            animation: pulse 2s infinite;
        }
        .status-processed {
            background: #4caf50;
            color: #fff;
        }
        .status-error {
            background: #f44336;
            color: #fff;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        .status-message {
            padding: 12px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .status-message.status-success {
            background: #4caf50;
            color: #fff;
        }
        .status-message.status-processing {
            background: #2196f3;
            color: #fff;
        }
        .status-message.status-error {
            background: #f44336;
            color: #fff;
        }
        .status-message.status-info {
            background: #4a90e2;
            color: #fff;
        }
        
        /* Custom Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 10000;
            justify-content: center;
            align-items: center;
        }
        .modal-overlay.show {
            display: flex;
        }
        .modal-dialog {
            background: #1a1a2e;
            border: 2px solid #2d2d44;
            border-radius: 15px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
            animation: modalSlideIn 0.3s ease;
        }
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .modal-title {
            color: #4a90e2;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .modal-message {
            color: #fff;
            font-size: 16px;
            margin-bottom: 25px;
            line-height: 1.5;
        }
        .modal-buttons {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
        }
        .modal-btn {
            padding: 10px 25px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        .modal-btn-primary {
            background: #4a90e2;
            color: #fff;
        }
        .modal-btn-primary:hover {
            background: #5ba0f2;
        }
        .modal-btn-secondary {
            background: #2d2d44;
            color: #fff;
            border: 1px solid #3d3d54;
        }
        .modal-btn-secondary:hover {
            background: #3d3d54;
        }
    </style>
</head>
<body>
    <div class="upload-container">
        <header class="header">
            <h1>📤 Upload PCAP File</h1>
            <a href="index.php" class="btn btn-primary">← Back to Dashboard</a>
        </header>

        <div class="upload-zone" id="uploadZone">
            <div class="upload-icon">📁</div>
            <h2>Drop PCAP file here or click to browse</h2>
            <p>Supported formats: .pcap, .pcapng</p>
            <div class="file-input-wrapper">
                <input type="file" id="fileInput" accept=".pcap,.pcapng" style="display: none;">
                <button class="btn btn-primary" id="chooseFileBtn">Choose File</button>
            </div>
        </div>

        <div id="uploadStatus" class="status-message" style="display: none;"></div>
        
        <!-- Upload Progress Bar -->
        <div id="uploadProgressContainer" style="display: none; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                <span style="color: #fff; font-weight: bold;">Uploading...</span>
                <span id="uploadProgressPercent" style="color: #4a90e2; font-weight: bold;">0%</span>
            </div>
            <div style="background: #2d2d44; border-radius: 10px; height: 20px; overflow: hidden;">
                <div id="uploadProgressBar" style="background: linear-gradient(90deg, #4a90e2, #5ba0f2); height: 100%; width: 0%; transition: width 0.3s;"></div>
            </div>
        </div>
        
        <!-- Processing Progress Bar (for parent page) -->
        <div id="processingProgressContainer" style="display: none; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                <span style="color: #fff; font-weight: bold;">Processing...</span>
                <span id="processingProgressPercent" style="color: #2196f3; font-weight: bold;">0%</span>
            </div>
            <div style="background: #2d2d44; border-radius: 10px; height: 20px; overflow: hidden;">
                <div id="processingProgressBar" style="background: linear-gradient(90deg, #2196f3, #42a5f5); height: 100%; width: 0%; transition: width 0.3s;"></div>
            </div>
        </div>

        <div class="file-list">
            <h2>Uploaded PCAP Files</h2>
            <div id="fileList"></div>
        </div>
    </div>

    <!-- Custom Confirm Modal -->
    <div id="confirmModal" class="modal-overlay">
        <div class="modal-dialog">
            <div class="modal-title" id="modalTitle">Confirm</div>
            <div class="modal-message" id="modalMessage"></div>
            <div class="modal-buttons">
                <button class="modal-btn modal-btn-secondary" id="modalCancel">Cancel</button>
                <button class="modal-btn modal-btn-primary" id="modalConfirm">OK</button>
            </div>
        </div>
    </div>
    
    <!-- Custom Alert Modal -->
    <div id="alertModal" class="modal-overlay">
        <div class="modal-dialog">
            <div class="modal-title" id="alertTitle">Alert</div>
            <div class="modal-message" id="alertMessage"></div>
            <div class="modal-buttons">
                <button class="modal-btn modal-btn-primary" id="alertOK" style="width: 100%;">OK</button>
            </div>
        </div>
    </div>

    <script src="assets/js/upload.js"></script>
</body>
</html>

