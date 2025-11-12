<?php
/**
 * Get/Set HQ Location
 */

// Suppress any output before JSON
ob_start();

require_once __DIR__ . '/../config/config.php';

// Clear any output buffer
ob_end_clean();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get HQ location
    $hq = [
        'latitude' => DEFAULT_HQ_LAT,
        'longitude' => DEFAULT_HQ_LNG,
        'name' => defined('DEFAULT_HQ_NAME') ? DEFAULT_HQ_NAME : 'Riyadh, KSA',
        'timezone' => 'Asia/Riyadh'
    ];
    
    if (file_exists(HQ_LOCATION_FILE)) {
        $saved = json_decode(file_get_contents(HQ_LOCATION_FILE), true);
        if ($saved) {
            $hq = array_merge($hq, $saved);
        }
    }
    
    echo json_encode([
        'success' => true,
        'hq' => $hq
    ]);
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Set HQ location
    $input = json_decode(file_get_contents('php://input'), true);
    
    $latitude = floatval($input['latitude'] ?? DEFAULT_HQ_LAT);
    $longitude = floatval($input['longitude'] ?? DEFAULT_HQ_LNG);
    $name = $input['name'] ?? (defined('DEFAULT_HQ_NAME') ? DEFAULT_HQ_NAME : 'Riyadh, KSA');
    $timezone = $input['timezone'] ?? 'Asia/Riyadh';
    
    // Validate coordinates
    if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid coordinates']);
        exit;
    }
    
    // Validate timezone
    $validTimezones = timezone_identifiers_list();
    if (!in_array($timezone, $validTimezones)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid timezone']);
        exit;
    }
    
    $hq = [
        'latitude' => $latitude,
        'longitude' => $longitude,
        'name' => $name,
        'timezone' => $timezone,
        'updated_at' => time()
    ];
    
    // Ensure directory exists
    $dir = dirname(HQ_LOCATION_FILE);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create directory']);
            exit;
        }
    }
    
    // Write file
    $result = file_put_contents(HQ_LOCATION_FILE, json_encode($hq, JSON_PRETTY_PRINT));
    if ($result === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save settings']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'hq' => $hq
    ]);
    
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

