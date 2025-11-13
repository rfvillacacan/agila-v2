<?php
/**
 * Reprocess a PCAP file
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/PcapParser.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$filename = $input['filename'] ?? null;

if (!$filename) {
    http_response_code(400);
    echo json_encode(['error' => 'Filename required']);
    exit;
}

// Security: prevent directory traversal
$filename = basename($filename);

// Validate filename format: pcap_<hex>.<hex>.json
if (!preg_match('/^pcap_[a-f0-9_]+\.[a-f0-9]+\.json$/i', $filename)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid filename format']);
    exit;
}

// Additional security: ensure it's actually a JSON file
if (pathinfo($filename, PATHINFO_EXTENSION) !== 'json') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file type']);
    exit;
}

$jsonPath = PROCESSED_PATH . '/' . $filename;

if (!file_exists($jsonPath)) {
    http_response_code(404);
    echo json_encode(['error' => 'File not found']);
    exit;
}

// Load existing data to get PCAP filename
$existingData = json_decode(file_get_contents($jsonPath), true);
$pcapFilename = $existingData['pcap_filename'] ?? null;

// If pcap_filename is not in JSON, try to derive it from the JSON filename
if (!$pcapFilename) {
    $baseName = pathinfo($filename, PATHINFO_FILENAME);
    $possibleExtensions = ['pcap', 'pcapng'];
    
    foreach ($possibleExtensions as $ext) {
        $possiblePcapPath = UPLOAD_PATH . '/' . $baseName . '.' . $ext;
        if (file_exists($possiblePcapPath)) {
            $pcapFilename = $baseName . '.' . $ext;
            break;
        }
    }
}

if (!$pcapFilename) {
    http_response_code(400);
    echo json_encode(['error' => 'PCAP filename not found. Cannot process file.']);
    exit;
}

$pcapPath = UPLOAD_PATH . '/' . basename($pcapFilename);

if (!file_exists($pcapPath)) {
    http_response_code(404);
    echo json_encode(['error' => 'Original PCAP file not found']);
    exit;
}

// Create placeholder with processing status and progress tracking
$placeholder = [
    'status' => 'processing',
    'original_filename' => $existingData['original_filename'] ?? 'Unknown',
    'uploaded_at' => $existingData['uploaded_at'] ?? time(),
    'processed_at' => null,
    'total_packets' => 0,
    'total_sessions' => 0,
    'total_bytes' => $existingData['total_bytes'] ?? filesize($pcapPath),
    'pcap_filename' => $pcapFilename,
    'error' => null,
    'progress' => 0,  // Progress percentage
    'progress_text' => 'Starting...'  // Progress status text
];

file_put_contents($jsonPath, json_encode($placeholder, JSON_PRETTY_PRINT));

// IMPORTANT: Set ignore_user_abort BEFORE sending response
// This ensures processing continues even if user navigates away
ignore_user_abort(true);
set_time_limit(PROCESSING_TIMEOUT);

// Send immediate response first
echo json_encode([
    'success' => true,
    'filename' => $filename,
    'status' => 'processing',
    'message' => 'Processing started. This may take a few minutes...'
]);

// Ensure output is sent immediately so user can navigate away
if (ob_get_level() > 0) {
    ob_end_flush();
}
flush();

// FastCGI/nginx may need this to close connection while script continues
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// Process in background (after response is sent and connection closed)

try {
    // Parse PCAP file with progress tracking (using JSON file directly)
    $parser = new PcapParser();
    $parser->setProgressFile($jsonPath); // Use JSON file instead of separate progress file
    $results = $parser->parse($pcapPath);
    
    // Remove progress fields from final result
    unset($results['progress']);
    unset($results['progress_text']);
    
    // Add metadata
    $results['status'] = 'processed';
    $results['original_filename'] = $placeholder['original_filename'];
    $results['uploaded_at'] = $placeholder['uploaded_at'];
    $results['processed_at'] = time();
    $results['pcap_filename'] = $pcapFilename;
    $results['total_bytes'] = $placeholder['total_bytes'];
    
    // Save processed data
    file_put_contents($jsonPath, json_encode($results, JSON_PRETTY_PRINT));
    
} catch (Exception $e) {
    // Save error
    $errorData = $placeholder;
    $errorData['status'] = 'error';
    $errorData['error'] = $e->getMessage();
    $errorData['processed_at'] = time();
    // Remove progress fields
    unset($errorData['progress']);
    unset($errorData['progress_text']);
    file_put_contents($jsonPath, json_encode($errorData, JSON_PRETTY_PRINT));
}

