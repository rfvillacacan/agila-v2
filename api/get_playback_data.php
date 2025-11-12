<?php
/**
 * Get playback data for current time
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

// Load playback state
$state = [];
if (file_exists(PLAYBACK_STATE_FILE)) {
    $state = json_decode(file_get_contents(PLAYBACK_STATE_FILE), true) ?? [];
}

$currentFile = $state['current_file'] ?? null;
$currentTime = floatval($state['current_time'] ?? 0);

if (!$currentFile) {
    echo json_encode([
        'success' => true,
        'current_time' => 0,
        'sessions' => [],
        'total_sessions' => 0,
        'active_sessions' => 0
    ]);
    exit;
}

// Load PCAP data
$filePath = PROCESSED_PATH . '/' . $currentFile;
if (!file_exists($filePath)) {
    echo json_encode([
        'success' => true,
        'current_time' => $currentTime,
        'sessions' => [],
        'total_sessions' => 0,
        'active_sessions' => 0
    ]);
    exit;
}

$data = json_decode(file_get_contents($filePath), true);
if (!$data) {
    echo json_encode([
        'success' => true,
        'current_time' => $currentTime,
        'sessions' => [],
        'total_sessions' => 0,
        'active_sessions' => 0
    ]);
    exit;
}

// Get all sessions and filter those active during the current second
$sessions = $data['sessions'] ?? [];
$activeSessions = [];

// Get actual capture duration
$actualCaptureDuration = floatval($data['capture_duration'] ?? 0);
$totalBytes = floatval($data['total_bytes'] ?? 0);

// Calculate adaptive step size (same logic as playback_control.php)
$stepSize = 1.0;
$minSteps = 10;
if ($totalBytes > 50 * 1024 * 1024) {
    $minSteps = 50;
} else if ($totalBytes > 10 * 1024 * 1024) {
    $minSteps = 30;
}

if ($actualCaptureDuration > 0) {
    $calculatedStepSize = $actualCaptureDuration / $minSteps;
    if ($calculatedStepSize < 0.01) {
        $stepSize = 0.001;
    } else if ($calculatedStepSize < 0.1) {
        $stepSize = 0.01;
    } else if ($calculatedStepSize < 1.0) {
        $stepSize = 0.1;
    } else {
        $stepSize = 1.0;
    }
}

// Calculate current step window (round down to step boundary)
$currentStepStart = floor($currentTime / $stepSize) * $stepSize;
$currentStepEnd = $currentStepStart + $stepSize;

// Filter sessions that are active during this step window
// A session is active if it overlaps with the current step interval [currentStepStart, currentStepEnd)
foreach ($sessions as $session) {
    $startTime = floatval($session['relative_start'] ?? 0);
    $endTime = floatval($session['relative_end'] ?? 0);
    
    // Session is active during this step if:
    // - Session starts before the end of this step AND
    // - Session ends after the start of this step
    // This means: startTime < currentStepEnd AND endTime >= currentStepStart
    if ($startTime < $currentStepEnd && $endTime >= $currentStepStart) {
        $activeSessions[] = $session;
    }
}

// Get PCAP info
$captureStartTime = floatval($data['capture_start_time'] ?? 0);
$captureDuration = floatval($data['capture_duration'] ?? 0);

// Check if timestamp is unreasonably old (before year 2000)
// If so, use file modification time or current time as fallback
$year2000Timestamp = 946684800; // 2000-01-01 00:00:00 UTC
if ($captureStartTime < $year2000Timestamp) {
    // Use file modification time if available, otherwise use processed_at time
    $fileModTime = filemtime($filePath);
    if ($fileModTime && $fileModTime > $year2000Timestamp) {
        // Use file modification time minus capture duration as start time
        $captureStartTime = $fileModTime - $captureDuration;
    } else {
        // Use processed_at time if available
        $processedAt = $data['processed_at'] ?? null;
        if ($processedAt && $processedAt > $year2000Timestamp) {
            $captureStartTime = $processedAt - $captureDuration;
        } else {
            // Last resort: use current time minus duration
            $captureStartTime = time() - $captureDuration;
        }
    }
}

// Calculate actual timestamp for current playback time
$actualTimestamp = $captureStartTime + $currentTime;

$totalPackets = $data['total_packets'] ?? 0;
$totalBytes = $data['total_bytes'] ?? 0;

$pcapInfo = [
    'original_filename' => $data['original_filename'] ?? 'Unknown',
    'processed_at' => $data['processed_at'] ?? null,
    'capture_start_time' => $captureStartTime,
    'capture_duration' => $captureDuration,
    'total_packets' => $totalPackets,
    'total_bytes' => $totalBytes
];

// Calculate step information (adaptive step size)
$totalSteps = $captureDuration > 0 && $stepSize > 0 ? max(1, ceil($captureDuration / $stepSize)) : 1;
$currentStep = $captureDuration > 0 && $stepSize > 0 ? floor($currentTime / $stepSize) + 1 : 1; // 1-indexed step number

echo json_encode([
    'success' => true,
    'current_time' => $currentTime,
    'total_duration' => $captureDuration, // Actual capture duration
    'actual_duration' => $captureDuration, // Same as total_duration
    'step_size' => $stepSize, // Always 1 second
    'total_steps' => $totalSteps, // Total number of seconds
    'current_step' => $currentStep, // Current second (1-indexed)
    'actual_timestamp' => $actualTimestamp, // Actual Unix timestamp for current playback time
    'capture_start_timestamp' => $captureStartTime, // When capture started
    'sessions' => $activeSessions, // All sessions active during current second
    'total_sessions' => count($sessions),
    'active_sessions' => count($activeSessions),
    'pcap_file' => $currentFile,
    'pcap_info' => $pcapInfo
]);

