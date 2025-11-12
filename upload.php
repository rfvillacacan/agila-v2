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

        <div class="file-list">
            <h2>Uploaded PCAP Files</h2>
            <div id="fileList"></div>
        </div>
    </div>

    <script src="assets/js/upload.js"></script>
</body>
</html>

