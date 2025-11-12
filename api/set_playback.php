<?php
/**
 * Set the active PCAP file for playback
 */

require_once __DIR__ . '/../config/config.php';

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

// Validate file exists
$filePath = PROCESSED_PATH . '/' . $filename;
if (!file_exists($filePath)) {
    http_response_code(404);
    echo json_encode(['error' => 'File not found']);
    exit;
}

// Load file data
$data = json_decode(file_get_contents($filePath), true);
if (!$data || ($data['status'] ?? '') !== 'processed') {
    http_response_code(400);
    echo json_encode(['error' => 'File not processed yet']);
    exit;
}

// Update playback state
$state = [
    'current_file' => $filename,
    'current_time' => 0,
    'is_playing' => false,
    'last_updated' => time()
];

file_put_contents(PLAYBACK_STATE_FILE, json_encode($state, JSON_PRETTY_PRINT));

echo json_encode([
    'success' => true,
    'filename' => $filename,
    'original_filename' => $data['original_filename'] ?? 'Unknown',
    'total_sessions' => $data['total_sessions'] ?? 0,
    'total_packets' => $data['total_packets'] ?? 0
]);

