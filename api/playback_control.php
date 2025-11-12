<?php
/**
 * Playback control (play, pause, next, previous, stop)
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? null;

if (!$action) {
    http_response_code(400);
    echo json_encode(['error' => 'Action required']);
    exit;
}

// Load playback state
$state = [];
if (file_exists(PLAYBACK_STATE_FILE)) {
    $state = json_decode(file_get_contents(PLAYBACK_STATE_FILE), true) ?? [];
}

$currentFile = $state['current_file'] ?? null;
$currentTime = floatval($state['current_time'] ?? 0);
$isPlaying = $state['is_playing'] ?? false;

// Get total duration - use adaptive step size for best user experience
$totalDuration = 0;
$actualDuration = 0;
$stepSize = 1.0; // Default step size

if ($currentFile) {
    $filePath = PROCESSED_PATH . '/' . $currentFile;
    if (file_exists($filePath)) {
        $data = json_decode(file_get_contents($filePath), true);
        $actualDuration = floatval($data['capture_duration'] ?? 0);
        $totalBytes = floatval($data['total_bytes'] ?? 0);
        
        // Use actual duration as total duration
        $totalDuration = $actualDuration;
        
        // Calculate adaptive step size to ensure good user experience
        // Minimum 10 steps for any capture, more for larger files
        $minSteps = 10;
        if ($totalBytes > 50 * 1024 * 1024) {
            $minSteps = 50; // 50MB+ files get at least 50 steps
        } else if ($totalBytes > 10 * 1024 * 1024) {
            $minSteps = 30; // 10MB+ files get at least 30 steps
        }
        
        // Calculate step size to achieve minimum steps
        if ($totalDuration > 0) {
            $calculatedStepSize = $totalDuration / $minSteps;
            
            // Round step size to a nice value for user experience
            if ($calculatedStepSize < 0.01) {
                // Very small steps: use 0.001s (millisecond precision)
                $stepSize = 0.001;
            } else if ($calculatedStepSize < 0.1) {
                // Small steps: use 0.01s (centisecond precision)
                $stepSize = 0.01;
            } else if ($calculatedStepSize < 1.0) {
                // Medium steps: use 0.1s (decisecond precision)
                $stepSize = 0.1;
            } else {
                // Large steps: use 1.0s (second precision)
                $stepSize = 1.0;
            }
        }
    }
}

// Handle actions
switch ($action) {
    case 'play':
        $state['is_playing'] = true;
        break;
        
    case 'pause':
        $state['is_playing'] = false;
        break;
        
    case 'stop':
        $state['is_playing'] = false;
        $state['current_time'] = 0;
        break;
        
    case 'first':
        // Skip to start (time 0)
        $state['is_playing'] = false;
        $state['current_time'] = 0;
        break;
        
    case 'last':
        // Skip to end (total duration)
        $state['is_playing'] = false;
        $state['current_time'] = $totalDuration;
        break;
        
    case 'next':
        // Jump to next step
        $nextTime = $currentTime + $stepSize;
        if ($nextTime <= $totalDuration) {
            $state['current_time'] = $nextTime;
        } else {
            // Already at or past the end
            $state['current_time'] = $totalDuration;
            $state['is_playing'] = false;
        }
        break;
        
    case 'previous':
        // Jump to previous step
        $prevTime = max(0, $currentTime - $stepSize);
        $state['current_time'] = $prevTime;
        break;
        
    case 'advance_time':
        // Auto-playback: jump to next step
        $nextTime = $currentTime + $stepSize;
        if ($nextTime <= $totalDuration) {
            $state['current_time'] = $nextTime;
        } else {
            // Reached the end
            $state['current_time'] = $totalDuration;
            $state['is_playing'] = false;
        }
        break;
        
    case 'set_time':
        $time = floatval($input['time'] ?? 0);
        // Clamp to valid range
        $state['current_time'] = max(0, min($time, $totalDuration));
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        exit;
}

$state['last_updated'] = time();
file_put_contents(PLAYBACK_STATE_FILE, json_encode($state, JSON_PRETTY_PRINT));

// Calculate total steps based on step size
$totalSteps = $totalDuration > 0 && $stepSize > 0 ? max(1, ceil($totalDuration / $stepSize)) : 1;
$currentStep = $totalDuration > 0 && $stepSize > 0 ? floor($state['current_time'] / $stepSize) + 1 : 1; // 1-indexed step number

echo json_encode([
    'success' => true,
    'action' => $action,
    'current_time' => $state['current_time'],
    'is_playing' => $state['is_playing'],
    'total_duration' => $totalDuration,
    'step_size' => $stepSize,
    'total_steps' => $totalSteps,
    'current_step' => $currentStep,
    'actual_duration' => $actualDuration
]);

