<?php
/**
 * Get processing progress for a PCAP file
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

$filename = $_GET['filename'] ?? null;

if (!$filename) {
    http_response_code(400);
    echo json_encode(['error' => 'Filename required']);
    exit;
}

// Security: prevent directory traversal
$filename = basename($filename);

// Validate filename format
if (!preg_match('/^pcap_[a-f0-9_]+\.[a-f0-9]+\.json$/i', $filename)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid filename format']);
    exit;
}

$jsonPath = PROCESSED_PATH . '/' . $filename;

// Check if file exists
if (!file_exists($jsonPath)) {
    http_response_code(404);
    echo json_encode(['error' => 'File not found']);
    exit;
}

// Load current status from JSON (progress is stored directly in JSON)
$data = json_decode(file_get_contents($jsonPath), true);
$status = $data['status'] ?? 'unknown';

// Get progress from JSON file directly
$progress = isset($data['progress']) ? max(0, min(100, (int)$data['progress'])) : 0;
$statusText = $data['progress_text'] ?? 'Processing...';

if ($status === 'processed') {
    $progress = 100;
    $statusText = 'Complete!';
} elseif ($status === 'error') {
    $progress = 0;
    $statusText = 'Error: ' . ($data['error'] ?? 'Unknown error');
} elseif ($status === 'processing') {
    // Progress is already set from JSON file
    if ($progress === 0 && empty($statusText)) {
        $statusText = 'Starting...';
    }
}

echo json_encode([
    'success' => true,
    'filename' => $filename,
    'status' => $status,
    'progress' => $progress,
    'statusText' => $statusText
]);

