<?php
/**
 * Get list of uploaded PCAP files
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

$files = [];

// Scan processed directory
if (is_dir(PROCESSED_PATH)) {
    $jsonFiles = glob(PROCESSED_PATH . '/*.json');
    
    foreach ($jsonFiles as $jsonFile) {
        $data = json_decode(file_get_contents($jsonFile), true);
        if ($data) {
            $files[] = [
                'filename' => basename($jsonFile),
                'original_filename' => $data['original_filename'] ?? 'Unknown',
                'pcap_filename' => $data['pcap_filename'] ?? null,
                'status' => $data['status'] ?? 'unknown',
                'total_packets' => $data['total_packets'] ?? 0,
                'total_sessions' => $data['total_sessions'] ?? 0,
                'total_bytes' => $data['total_bytes'] ?? 0,
                'uploaded_at' => $data['uploaded_at'] ?? filemtime($jsonFile),
                'processed_at' => $data['processed_at'] ?? null,
                'error' => $data['error'] ?? null
            ];
        }
    }
}

// Sort by uploaded_at (newest first)
usort($files, function($a, $b) {
    return ($b['uploaded_at'] ?? 0) - ($a['uploaded_at'] ?? 0);
});

echo json_encode([
    'success' => true,
    'files' => $files
]);

