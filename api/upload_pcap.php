<?php
/**
 * PCAP File Upload Handler
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['pcap_file'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}

$file = $_FILES['pcap_file'];

// Validate file
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize in php.ini',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE from HTML form',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
    ];
    $errorMsg = $errorMessages[$file['error']] ?? 'Upload error code: ' . $file['error'];
    echo json_encode(['error' => $errorMsg]);
    exit;
}

// Check file size
if ($file['size'] > MAX_FILE_SIZE) {
    http_response_code(400);
    echo json_encode(['error' => 'File too large. Maximum size: ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB']);
    exit;
}

// Validate extension
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['pcap', 'pcapng'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file type. Only .pcap and .pcapng files are allowed']);
    exit;
}

// Ensure directories exist
if (!is_dir(UPLOAD_PATH)) {
    if (!mkdir(UPLOAD_PATH, 0755, true)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create upload directory']);
        exit;
    }
}

if (!is_dir(PROCESSED_PATH)) {
    if (!mkdir(PROCESSED_PATH, 0755, true)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create processed directory']);
        exit;
    }
}

// Generate unique filename
$originalName = $file['name'];
$uniqueId = uniqid('pcap_', true);
$filename = $uniqueId . '.' . $ext;
$uploadPath = UPLOAD_PATH . '/' . $filename;

// Move uploaded file
try {
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save uploaded file. Please check directory permissions.']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Upload error: ' . $e->getMessage()]);
    exit;
}

// Create placeholder JSON file with "pending" status (not processing)
$processedFilename = $uniqueId . '.json';
$processedPath = PROCESSED_PATH . '/' . $processedFilename;

$placeholder = [
    'status' => 'pending',
    'original_filename' => $originalName,
    'uploaded_at' => time(),
    'processed_at' => null,
    'total_packets' => 0,
    'total_sessions' => 0,
    'total_bytes' => $file['size'],
    'pcap_filename' => $filename,
    'error' => null
];

try {
    if (file_put_contents($processedPath, json_encode($placeholder, JSON_PRETTY_PRINT)) === false) {
        // Clean up uploaded file if JSON creation fails
        @unlink($uploadPath);
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create placeholder file. Please check directory permissions.']);
        exit;
    }
} catch (Exception $e) {
    // Clean up uploaded file if JSON creation fails
    @unlink($uploadPath);
    http_response_code(500);
    echo json_encode(['error' => 'Error creating placeholder: ' . $e->getMessage()]);
    exit;
}

// Send immediate response - file is uploaded but NOT processed yet
echo json_encode([
    'success' => true,
    'filename' => $processedFilename,
    'original_filename' => $originalName,
    'status' => 'pending',
    'message' => 'File uploaded successfully. Click "Process" to start processing.'
]);

